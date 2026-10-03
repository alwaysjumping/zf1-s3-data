# Technical Research and Architecture Proposal

## Workerman-Based Real-Time Notification System for the Existing Zend Framework 1 Platform

**Document Type:** Technical Research and Architecture Proposal\
**Target Audience:** Management, Technical Leads, System Architects, and
Developers\
**Platform:** PHP 7.4, Zend Framework 1, MariaDB, Workerman\
**Status:** Proposed Architecture\
**Date:** October 2026

------------------------------------------------------------------------

## Executive Summary

The existing application uses Zend Framework 1 (ZF1), PHP 7.4, and
MariaDB. Traditional HTTP request/response processing is appropriate for
business operations, but it does not efficiently provide immediate
server-to-browser notification after the original request has completed.

This paper proposes adding **Workerman** as a separate real-time
communication service. Workerman maintains WebSocket connections and
delivers small real-time signals. It does **not replace ZF1** and does
not become the primary business or database layer.

The proposed architecture deliberately does **not require Redis**.
Reliable handoff from ZF1 to Workerman uses a **MariaDB transactional
outbox**, a separate **Dispatcher** process, and a protected **internal
signal interface** exposed by Workerman.

> **MariaDB stores authoritative state, ZF1 owns business logic and
> authorization, and Workerman transports real-time signals.**

Clients receive signals such as `notification.created`, containing an
identifier, and retrieve authoritative data through the authenticated
ZF1 REST API.

Real-time signaling uses an **at-least-once delivery model**. Duplicate
signals are permitted and identified by an immutable `event_id`.
Exactly-once network delivery is not required.

------------------------------------------------------------------------

# 1. Purpose

The purpose of this research is to define how Workerman can be
integrated into the existing ZF1 application to provide reliable
real-time notifications without rewriting the business application.

The solution should preserve ZF1 and MariaDB, provide WebSocket
delivery, avoid moving normal CRUD/business logic into Workerman,
continue operating during temporary real-time outages, avoid Redis in
the current design, and support duplicate-safe at-least-once event
delivery.

# 2. Existing Environment

  Component                       Technology
  ------------------------------- --------------------------------
  Backend                         Zend Framework 1
  PHP                             PHP 7.4
  Database                        MariaDB
  Existing client communication   HTTP/HTTPS and REST-style APIs
  Proposed real-time service      Workerman
  Server                          Linux/CentOS
  Durable event handoff           MariaDB transactional outbox
  Redis                           Not used

# 3. Why Real-Time Communication Is Needed

Traditional HTTP is client initiated:

``` text
Browser -> ZF1 -> MariaDB -> ZF1 -> Browser
```

After a request ends, the server cannot use that completed request to
spontaneously notify the browser minutes later. Polling can repeatedly
ask for changes, but creates recurring HTTP/ZF1/database work and
introduces latency based on the polling interval.

WebSocket instead maintains a persistent bidirectional connection:

``` text
Browser                        Server
WebSocket <=================> WebSocket
    |                             |
   TCP <========================> TCP
```

This lets the server signal the browser immediately when an event
occurs.

# 4. PHP Socket vs. WebSocket

A raw socket and WebSocket are different concepts. A socket provides the
underlying network channel; WebSocket defines an application protocol
over that channel.

``` text
Application
    |
WebSocket protocol
    |
TCP socket
    |
Operating system/network
```

Building directly on raw PHP sockets would require handling the
WebSocket handshake, frames, masking, ping/pong, close behavior,
connection lifecycle, concurrency, event loops and long-running process
management. Workerman provides these networking/protocol facilities.

# 5. What Workerman Does

Workerman is a long-running, event-driven PHP networking
framework/runtime suitable for WebSocket and TCP services.

Normal PHP/ZF1 request:

``` text
request -> PHP/ZF1 -> response -> request ends
```

Workerman:

``` text
start PHP CLI process
        |
open listeners
        |
enter event loop
        |
connect/message/close/timer callbacks
        |
continue running
```

Workerman therefore complements rather than replaces the existing ZF1
request lifecycle.

# 6. Proposed Architecture

``` text
                           Browser
                              |
                 +------------+------------+
                 |                         |
                 | HTTPS / REST            | WSS
                 v                         v
                ZF1                    Workerman
                 |                         ^
                 v                         |
              MariaDB                     | internal signal
                 |                         |
          realtime_outbox                 |
                 |                         |
                 v                         |
             Dispatcher -------------------+
```

Detailed event flow:

``` text
User action
    |
   ZF1
    |
    +-- business changes
    +-- INSERT notification
    +-- INSERT realtime_outbox event
    |
 MariaDB COMMIT
    |
 Dispatcher
    |
 internal TCP + ACK
    |
 Workerman
    |
 WebSocket signal
    |
 Browser
    |
 authenticated REST request if data is needed
    |
   ZF1 -> MariaDB
```

# 7. Responsibility Separation

## ZF1

ZF1 owns authentication, authorization, business rules, REST APIs,
notification creation, read/dismiss/reminder operations, validation,
database transactions, and access to authoritative business data.

## MariaDB

MariaDB stores authoritative notification/business state and the durable
real-time outbox.

## Workerman

Workerman owns WebSocket connection lifecycle, real-time connection
authentication, user-to-connection routing, internal event acceptance,
signal delivery, heartbeat, and connection cleanup.

## Dispatcher

The Dispatcher reads/claims pending outbox events, sends them to
Workerman's internal listener, processes acknowledgements, retries
failures, and updates outbox status.

## Client

The browser receives and deduplicates signals, fetches authoritative
data from ZF1, displays notifications, and sends business-state changes
through ZF1.

# 8. Signal-Only WebSocket Design

Workerman should normally send a small signal rather than full business
data:

``` json
{
    "event_id": "7d861a4f9c514842a36d04fd4cb52e18",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

The browser then requests:

``` text
GET /api/notifications/5001
```

ZF1 performs authentication and authorization and returns current
authoritative data.

``` text
Workerman -> "something changed" -> Browser
                                      |
                                      | REST
                                      v
                                     ZF1
                                      |
                                      v
                                   MariaDB
```

This keeps sensitive business rules and data authorization out of
Workerman.

# 9. Transactional Outbox

When ZF1 creates a notification, it should create the corresponding
real-time event in the **same MariaDB transaction**:

``` text
BEGIN

INSERT notification
INSERT realtime_outbox

COMMIT
```

This means the notification and its dispatch record are committed
together. Network delivery is deliberately separated from the user's
HTTP transaction.

A conceptual table is:

``` sql
CREATE TABLE realtime_outbox (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id        CHAR(32) NOT NULL,
    event_name      VARCHAR(100) NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    payload         TEXT NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    retry_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL,
    processing_at   DATETIME NULL,
    sent_at         DATETIME NULL,
    last_error      TEXT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_realtime_outbox_event_id (event_id),
    KEY idx_realtime_outbox_status_id (status, id)
);
```

The final production schema should define stale-claim recovery and
concurrency behavior before multiple Dispatchers are enabled.

# 10. Dispatcher

The Dispatcher should be a separate supervised PHP CLI process, not part
of a normal web request.

``` text
Find pending event
      |
Claim it
      |
Send to Workerman
      |
Wait for ACK
   +--+--+
   |     |
 success failure
   |     |
 mark    retry/recover
 sent
```

Separating the Dispatcher means a Workerman outage does not make a
user's normal ZF1 request wait for repeated network attempts.

# 11. Internal Signal Interface

Without Redis, the Dispatcher sends events to a protected Workerman
internal listener. If all components run on one server, it may bind to
loopback:

``` text
WebSocket listener: 0.0.0.0:8080
Internal listener:  127.0.0.1:8081
```

Example internal event:

``` json
{
    "payload": {
        "event_id": "7d861a4f9c514842a36d04fd4cb52e18",
        "event": "notification.created",
        "user_id": 123,
        "data": {
            "notification_id": 5001
        },
        "timestamp": "2026-10-02T04:00:00Z",
        "nonce": "18c82ca4f5a3494a"
    },
    "signature": "HMAC-SHA256-SIGNATURE"
}
```

The listener should validate schema, event type, payload size,
timestamp/nonce policy, and message authentication before accepting an
event.

# 12. Internal Authentication and ACK

Where appropriate, an HMAC can authenticate internal messages:

``` text
HMAC-SHA256(canonical_payload, shared_secret)
```

The secret should be random, protected from source control, restricted
by filesystem permissions, and managed operationally.

Writing bytes to TCP does not prove Workerman accepted the event.
Workerman should therefore return an acknowledgement such as:

``` json
{
    "event_id": "7d861a4f9c514842a36d04fd4cb52e18",
    "status": "accepted"
}
```

The Dispatcher records successful handoff only after a valid ACK.

# 13. Notification Creation Flow

``` text
Alice        ZF1       MariaDB      Dispatcher     Workerman       Bob
  |           |           |             |              |            |
  | request   |           |             |              |            |
  |---------->|           |             |              |            |
  |           | INSERT notification     |              |            |
  |           | INSERT outbox E100      |              |            |
  |           | COMMIT    |             |              |            |
  | response  |           |             |              |            |
  |<----------|           |             |              |            |
  |           |           |             | E100         |            |
  |           |           |             |------------->|            |
  |           |           |             |              |----------->|
  |           |           |             |<-----ACK-----|            |
  |           |           | mark sent   |              |            |
```

Bob then retrieves authoritative notification data through ZF1.

# 14. Read, Dismiss, and Reminder Operations

Receiving a signal is not the same as reading a notification.

``` text
Delivered = signal reached client
Read      = user viewed/opened notification
Dismissed = user explicitly dismissed it
```

When Bob reads notification 5001:

``` text
Browser
   |
   | PUT /api/notifications/5001/read
   v
ZF1
   |
   +-- authorize
   +-- UPDATE notification
   +-- INSERT notification.read outbox event
   |
 COMMIT
```

The resulting real-time signal can synchronize Bob's other active
connections.

The same pattern applies to dismiss and reminder operations. Workerman
should not directly update business tables.

# 15. Multiple Connections

A user may have several active devices/tabs. Server-side connection
management should therefore model:

``` text
user_id -> connection[]
```

not:

``` text
user_id -> one connection
```

Browser `BroadcastChannel` may optimize communication between tabs on
one browser/device, but does not replace server-side multi-device
support.

# 16. Reconnection and REST Synchronization

WebSocket is not authoritative event history. If the browser is
disconnected while E100, E101 and E102 occur, those signals may not
reach it immediately.

After reconnect:

``` text
WebSocket reconnect
       |
       v
REST synchronization
       |
       v
ZF1
       |
       v
MariaDB
```

The synchronization API can initially use unread/current notification
state and later evolve to a cursor/version approach if required.

> A missed WebSocket signal must never mean lost authoritative business
> data.

# 17. Delivery Semantics: At-Least-Once

Real-time signaling shall use an **at-least-once** model.

Each outbox event receives a globally unique, immutable `event_id` at
creation. The same ID is preserved across all retries.

Example:

``` text
Dispatcher -> E100 -> Workerman -> Browser
     |
     X crashes before marking sent

restart

Dispatcher -> E100 -> Workerman -> Browser
```

The browser may receive E100 twice. This is expected.

Clients shall deduplicate using `event_id`, and event handlers should be
idempotent where practical.

**Exactly-once network delivery is not required.**

This is appropriate because MariaDB---not WebSocket delivery---is
authoritative.

# 18. Failure Scenarios

## Workerman Down

``` text
ZF1 -> MariaDB -> Outbox -> Dispatcher -X-> Workerman
```

The business transaction remains valid. The event remains available for
retry. ZF1/REST continues operating.

## WebSocket Healthy, Internal Listener Down

``` text
Browser <==== WebSocket ====> Workerman
                               ^
                               X
                               |
                           Dispatcher
```

Users may appear connected while new internal events cannot enter
Workerman. Therefore internal-signal health must be monitored
independently from WebSocket health. Events remain/recover in the outbox
for retry.

## Dispatcher Down

Pending events remain durable. A stale-claim recovery policy is required
if a Dispatcher dies after claiming an event.

## Dispatcher Crashes After Workerman Accepts Event

The Dispatcher may retry the same event. This produces the intended
at-least-once behavior. `event_id` permits deduplication.

## Browser Offline

The notification remains in MariaDB. The browser synchronizes through
REST after reconnect/login.

## MariaDB Unavailable

If the business transaction cannot commit, neither the notification nor
its corresponding outbox event should be considered committed. No signal
should claim that an uncommitted operation occurred.

# 19. Graceful Degradation

When everything is healthy:

``` text
Business event -> immediate WebSocket signal -> realtime UX
```

When the real-time subsystem is unavailable:

``` text
Business event
    |
MariaDB authoritative state
    |
ZF1/REST continues
    |
outbox retry and/or later client synchronization
```

Thus Workerman is not a single point of failure for core business
operations.

# 20. Workerman Process Model and Scaling

Workerman can use multiple worker processes:

``` text
              Master
          +-----+-----+
          |     |     |
          v     v     v
         W1    W2    W3
```

PHP memory is isolated between processes. W1 cannot directly access W2's
ordinary PHP arrays/connections.

For an initial controlled prototype, one worker simplifies routing.
Before increasing worker count, the design must introduce explicit
inter-process routing and be load tested.

Possible future approaches include Workerman Channel, GatewayWorker, or
another deliberate IPC/distributed routing design.

# 21. Avoid Blocking the Event Loop

Workerman should remain focused on lightweight real-time work. Long
blocking operations inside callbacks can prevent a worker from servicing
its other connections.

Avoid moving long SQL queries, PDF/report generation, large file
operations, slow external requests, or long sleeps into Workerman's
event callbacks.

This reinforces the architecture:

``` text
ZF1       = business processing
Workerman = realtime transport
```

# 22. WebSocket Authentication and Authorization

A browser must not be trusted merely because it sends:

``` json
{"user_id": 123}
```

Workerman should derive user identity from a server-validated
credential. One possible design is a short-lived real-time credential
issued by authenticated ZF1 and validated when opening the WebSocket
connection.

Business authorization remains in ZF1. If a client requests
`/api/notifications/9999`, ZF1 must verify that the authenticated user
is allowed to access it.

# 23. WSS, Heartbeat, and Reconnect

Production browser connections should use `wss://`, normally with TLS
handled directly or by a reverse proxy.

Long-lived connections require heartbeat handling so stale connections
can be detected and cleaned up.

Clients should automatically reconnect using increasing delay/backoff
rather than a tight loop. Jitter can help avoid a reconnect storm after
a service restart. After reconnection, the client should synchronize
authoritative state through REST.

# 24. Event Envelope

A stable event format allows reuse:

``` json
{
    "event_id": "...",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

Initial event types may include:

``` text
notification.created
notification.updated
notification.read
notification.dismissed
notification.reminder
```

Future uses may include task, document, email, permission, or system
events without changing the core transport architecture.

# 25. Why Redis Is Not Required

Workerman does not require Redis to provide WebSocket service.

The current architecture uses:

``` text
MariaDB Outbox
      |
Dispatcher
      |
Internal Workerman Listener
      |
WebSocket
```

Redis could be reconsidered later for distributed pub/sub or
coordination if future scaling requirements justify the additional
infrastructure. It is intentionally not required for the current
implementation.

# 26. Alternatives Considered

  --------------------------------------------------------------------------------
  Approach                Strengths               Main Considerations
  ----------------------- ----------------------- --------------------------------
  REST polling            Simple, existing stack  Repeated requests and polling
                                                  latency

  Raw PHP sockets         Maximum control         High
                                                  protocol/event-loop/operations
                                                  burden

  Workerman               PHP-based, WebSocket    Long-running process and IPC
                          capable, incremental    knowledge required
                          integration             

  Redis Pub/Sub +         Convenient event        Additional infrastructure; basic
  Workerman               distribution            Pub/Sub is not durable event
                                                  storage

  SSE                     Simple server-to-client Primarily one-way; WebSocket
                          stream                  selected for broader realtime
                                                  use
  --------------------------------------------------------------------------------

Workerman is attractive because it adds a specialized realtime
capability while retaining the current PHP technology base and existing
ZF1 business layer.

# 27. Deployment and Supervision

On CentOS/Linux, Workerman and Dispatcher should be supervised services:

``` text
                    systemd
                  /                          v           v
          Workerman       Dispatcher
              |               |
              |               v
              |            MariaDB
              |               |
              +<--------------+
              |
              v
           Browsers
```

Operational procedures should support boot startup, restart after
failure, controlled reload/restart, status inspection, and centralized
logs.

# 28. Monitoring

Monitoring should include:

-   Workerman process/listener health;
-   active WebSocket connection count;
-   worker restarts/crashes;
-   heartbeat failures;
-   internal signal listener health;
-   HMAC/schema rejection counts;
-   Dispatcher process health;
-   last successful dispatch;
-   pending outbox count;
-   oldest pending event age;
-   retry count;
-   stale claimed events;
-   failed/dead-letter events;
-   event creation-to-delivery latency.

A healthy WebSocket port does **not** prove that the internal signal
path is healthy.

# 29. Logging and Traceability

Use `event_id` across logs:

``` text
ZF1:        Created notification 5001 / event E100
Dispatcher: Claimed E100
Dispatcher: Sending E100
Workerman:  Accepted E100
Workerman:  Routed E100 to user 123
Dispatcher: ACK E100
Dispatcher: Marked E100 sent
```

This provides end-to-end operational traceability without requiring full
business payloads in logs.

# 30. Retry, Concurrency, and Retention

Retries should use controlled backoff rather than a tight loop. The
production policy should define maximum retries/escalation, stale-claim
recovery, dead-letter handling, and administrative replay.

A single Dispatcher is simplest initially. If multiple Dispatchers are
introduced, atomic claiming/locking is required so two processes do not
intentionally claim the same pending row.

Successfully delivered outbox records also need a retention/cleanup
policy so the table does not grow indefinitely.

# 31. Security Requirements

1.  Use `wss://` in production.
2.  Authenticate WebSocket connections.
3.  Never trust browser-supplied `user_id`.
4.  Keep business authorization in ZF1.
5.  Restrict the internal listener to trusted interfaces/networks.
6.  Authenticate internal events where appropriate.
7.  Protect shared secrets.
8.  Validate timestamp/nonce if replay protection is enabled.
9.  Validate event names, schemas, and payload sizes.
10. Avoid unnecessary sensitive business data in signals.
11. Use least-privilege OS service accounts and firewall rules.
12. Log security failures without exposing secrets.

# 32. Implementation Phases

**Phase 1 --- Prototype:** establish stable Workerman WebSocket
communication using a single worker.

**Phase 2 --- Authentication:** validate realtime credentials and map
authenticated users to connections.

**Phase 3 --- Connection Manager:** support `user_id -> connection[]`,
cleanup, heartbeat, and reconnect.

**Phase 4 --- Internal Event Interface:** implement protected TCP
listener, schema validation, authentication/HMAC, timestamp/nonce
policy, and ACK.

**Phase 5 --- Transactional Outbox:** add immutable `event_id` and
transactionally create business state plus outbox event.

**Phase 6 --- Dispatcher:** implement claiming, delivery, ACK
processing, retry, stale-claim recovery, and logging.

**Phase 7 --- Browser Integration:** implement event handling,
deduplication, REST retrieval, and notification UI.

**Phase 8 --- State Synchronization:** implement read, dismiss,
reminder, and multi-device synchronization.

**Phase 9 --- Monitoring:** monitor Workerman, internal signal path,
Dispatcher, and outbox backlog.

**Phase 10 --- Production Validation:** load, security,
failure/recovery, and long-running stability testing.

# 33. Testing Plan

Testing should include:

-   WebSocket connect/authenticate/disconnect;
-   notification creation and REST retrieval;
-   read/dismiss/reminder;
-   invalid WebSocket credentials;
-   forged user identity;
-   unauthorized notification IDs;
-   invalid HMAC, malformed JSON, replay and oversized messages;
-   Workerman outage;
-   internal listener-only outage;
-   Dispatcher outage/restart;
-   Dispatcher crash after Workerman accepts an event;
-   duplicate `event_id` handling;
-   browser disconnect/reconnect and REST synchronization;
-   Workerman restart with connected clients;
-   MariaDB transaction rollback;
-   stale outbox claim recovery;
-   extended memory/CPU/file-descriptor/latency stability testing.

# 34. Architectural Rules

1.  **MariaDB is authoritative.**
2.  **ZF1 owns business logic and authorization.**
3.  **Workerman transports realtime signals rather than becoming a
    second business application.**
4.  **Browser business-state changes go through authenticated ZF1
    APIs.**
5.  **A notification must not depend on WebSocket delivery for its
    existence.**
6.  **Realtime events may be duplicated; deduplicate using immutable
    `event_id`.**
7.  **Reconnect is followed by authoritative synchronization when
    necessary.**
8.  **Realtime failure must not invalidate a successful core business
    transaction.**
9.  **The internal signal path is monitored independently.**
10. **Do not assume Workerman worker processes share PHP memory.**

# 35. Advantages and Risks

  -----------------------------------------------------------------------
  Area                                Assessment
  ----------------------------------- -----------------------------------
  Existing application impact         Low: ZF1 remains authoritative

  Team technology alignment           Strong: realtime service remains
                                      PHP-based

  Data reliability                    Strong: MariaDB + transactional
                                      outbox

  Realtime outage behavior            Graceful degradation and retry

  Security boundary                   Business authorization remains in
                                      ZF1

  Extensibility                       Stable event envelope supports
                                      additional event types

  Operational complexity              Adds long-running Workerman and
                                      Dispatcher services

  Scaling complexity                  Multi-process/multi-server routing
                                      needs explicit design

  Delivery semantics                  At-least-once requires
                                      deduplication/idempotency
  -----------------------------------------------------------------------

# 36. Recommendation

For the existing PHP 7.4 / Zend Framework 1 / MariaDB platform,
Workerman is a suitable candidate for adding realtime WebSocket
notification capability while preserving the existing business
application.

Recommended model:

``` text
ZF1
    = business application

MariaDB
    = authoritative state + durable outbox

Dispatcher
    = reliable asynchronous event handoff

Workerman
    = realtime transport/router

WebSocket
    = realtime client signal channel

REST
    = authoritative data retrieval and state changes
```

Redis is not required for the initial architecture.

The first deployment should remain intentionally simple, with explicit
later design work for multi-process/distributed routing if measured load
requires it.

# 37. Conclusion

Workerman's role is not to replace the existing ZF1 application. It adds
persistent, low-latency server-to-client communication beside the
established business system.

``` text
Business operation
       |
      ZF1
       |
    MariaDB
       |
Transactional Outbox
       |
   Dispatcher
       |
   Workerman
       |
    WebSocket
       |
     Client
       |
   ZF1 REST API
       |
    MariaDB
```

The combination of transactional outbox, Dispatcher, ACK, retry,
immutable `event_id`, client deduplication, and REST synchronization
provides a practical **at-least-once realtime delivery model** without
requiring exactly-once network delivery.

This protects the existing investment in ZF1 and MariaDB while adding
realtime capability incrementally and leaving a clear path for future
modernization.

# 38. References

Implementation should be verified against the documentation for the
exact Workerman version selected for PHP 7.4.

1.  **Workerman Documentation** --- official documentation for
    installation, Worker, WebSocket, connection handling, timers,
    heartbeat, process management, status, Channel, and related
    features.
2.  **GatewayWorker Documentation** --- relevant if future scale
    requires specialized distributed connection management.
3.  **RFC 6455 --- The WebSocket Protocol** --- IETF WebSocket protocol
    specification.
4.  **RFC 8446 --- TLS 1.3** --- relevant to secure WebSocket transport.
5.  **MariaDB Documentation** --- transactions, InnoDB locking,
    indexing, and database operational behavior.
6.  **OWASP WebSocket Security Cheat Sheet** --- security guidance for
    WebSocket authentication, origin validation, message handling,
    logging, and related controls.

Official navigation: - Workerman: https://manual.workerman.net/ -
WebSocket RFC: https://www.rfc-editor.org/rfc/rfc6455 - TLS 1.3 RFC:
https://www.rfc-editor.org/rfc/rfc8446 - MariaDB documentation:
https://mariadb.com/docs/ - OWASP WebSocket Security Cheat Sheet:
https://cheatsheetseries.owasp.org/cheatsheets/WebSocket_Security_Cheat_Sheet.html

------------------------------------------------------------------------

## Appendix A --- Final Mental Model

``` text
Socket
    = underlying network communication mechanism

WebSocket
    = persistent bidirectional application protocol

Workerman
    = long-running PHP networking framework handling realtime connections

ZF1
    = business application and authorization layer

MariaDB
    = authoritative persistent state

Transactional Outbox
    = durable record that a realtime event must be dispatched

Dispatcher
    = process that transfers outbox events to Workerman

event_id
    = stable identity used to detect duplicate event delivery

At-least-once
    = delivery may repeat; duplicates must be handled safely
```
