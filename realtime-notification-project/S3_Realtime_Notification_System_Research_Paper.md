# Research Paper: Realtime Notification and Reminder System for the S3 Business Platform

**Document Type:** Technical Research and Architecture Proposal  
**Audience:** Management, Technical Leadership, System Architects, Development Team  
**Version:** 1.0  
**Date:** October 2026  
**Target Platform:** Zend Framework 1 (ZF1), PHP 7.4, MariaDB, DHTMLX 3.5, CentOS, Windows  
**Status:** Architecture finalized; reference implementation prepared

---

## Executive Summary

The existing S3 business platform requires a notification mechanism capable of informing users about important business events—such as new messages, shared documents, assigned tasks, system events, and scheduled reminders—without requiring users to manually refresh application screens.

A conventional polling-only solution is simple but introduces unnecessary repeated HTTP requests and can delay notification delivery. A pure WebSocket solution provides faster delivery, but if it becomes responsible for business data and business rules it creates a second application layer that is more difficult to secure, maintain, and recover.

This paper proposes a hybrid architecture in which **Zend Framework 1 (ZF1) and MariaDB remain the authoritative business and data layer, while realtime servers are used only to deliver lightweight event signals**.

The central architectural principle is:

> **Realtime signaling improves delivery speed; it does not own business truth. ZF1 and MariaDB remain responsible for correctness, authorization, persistence, and recovery.**

Three deployment profiles are supported so that subsidiaries with different infrastructure capabilities can use the same notification system:

1. **REST API / Polling** — for environments that cannot operate a dedicated realtime server.
2. **Workerman + WebSocket** — for environments that can run a long-running PHP CLI service but cannot install Node.js.
3. **Node.js + WebSocket** — for environments with higher infrastructure capability.

All three profiles share the same MariaDB schema, ZF1 services, APIs, event model, reminder subsystem, browser behavior, and Windows client behavior. This avoids maintaining three independent notification products.

The architecture also introduces a transactional outbox, event identifiers, automatic WebSocket-to-polling fallback, periodic reconciliation, multi-tab browser coordination, and server-authoritative reminder processing. These mechanisms are designed so that temporary realtime failures delay notifications rather than lose them.

Redis is intentionally not required in the current design. MariaDB is the durable source of truth, while active WebSocket connection mappings are maintained in the realtime server's process memory.

---

## 1. Background and Business Need

The S3 platform is an established business application based on Zend Framework 1, PHP 7.4, MariaDB, DHTMLX 3.5, and CentOS. Users perform business activities through the web application, and some environments may additionally use a Windows desktop notification client.

Traditional web applications generally discover changes in one of two ways:

- the user refreshes or navigates to another page; or
- the browser repeatedly polls the server for changes.

Neither approach provides an ideal experience for time-sensitive business events. Users may need to know promptly when:

- a message is received;
- a document is shared;
- a task is assigned;
- an important system event occurs; or
- a reminder becomes due.

The requirement is therefore broader than simply “adding WebSocket.” The system must provide timely delivery while preserving the security, authorization, reliability, and maintainability of the existing S3 application.

---

## 2. Research Objectives

The design research addressed the following questions:

1. How can realtime notifications be added without rewriting the existing ZF1 application?
2. Should the realtime server retrieve business data directly from MariaDB?
3. How can subsidiaries with different server capabilities use one common architecture?
4. How should the system behave when Workerman, Node.js, WebSocket, or the internal signal channel fails?
5. How can notification delivery avoid data loss and tolerate duplicate signals?
6. How should multiple browser tabs behave without creating unnecessary WebSocket connections?
7. How can reminders use the same infrastructure without relying on a user's browser remaining open?
8. How can the Windows desktop client participate without directly accessing MariaDB?
9. Is Redis required for the initial architecture?
10. How can the design remain compatible with the existing PHP 7.4/ZF1 environment?

The resulting architecture prioritizes incremental integration and failure tolerance over introducing a new business-application stack.

---

## 3. Existing Technical Environment

The target environment is:

| Layer | Existing Technology |
|---|---|
| Backend | Zend Framework 1 |
| PHP | PHP 7.4 |
| Database | MariaDB |
| Web UI | DHTMLX 3.5 + JavaScript |
| Server OS | CentOS |
| Desktop | Windows |
| Authentication / Authorization | Existing ZF1 application |

The design deliberately keeps ZF1 responsible for business authorization. Introducing realtime communication must not create a separate authorization model that can disagree with the main application.

---

## 4. Architectural Decision

### 4.1 Separate Business Data from Realtime Signaling

The most important design decision is to keep full notification business data inside the existing application boundary.

Workerman and Node.js operate primarily as **signal routers**, not as secondary business servers.

For example, ZF1 may generate the following internal signal after committing a notification:

```json
{
  "user_id": 200,
  "event_id": 9005,
  "event": "notification.created",
  "notification_id": 1060
}
```

The realtime server uses `user_id` to identify the user's active connections. The browser may receive only:

```json
{
  "event_id": 9005,
  "event": "notification.created",
  "notification_id": 1060
}
```

The browser then obtains the protected object through the normal authenticated application API:

```http
GET /api/notifications/1060
```

This creates a clean separation:

- **ZF1:** business logic, authentication, authorization, persistence and APIs;
- **MariaDB:** durable source of truth;
- **Workerman / Node.js:** fast signal transport;
- **Browser / Windows client:** presentation and user interaction.

### 4.2 Why This Separation Matters

If the realtime server directly implements notification authorization, database queries, reminder rules, read state, and business entity access, the organization effectively creates a second business application.

That would increase:

- duplicated logic;
- authorization inconsistency risk;
- maintenance cost;
- testing requirements;
- deployment complexity; and
- migration risk for the legacy platform.

The signal-only approach keeps the realtime component intentionally small.

---

## 5. Deployment Profiles

Subsidiaries do not all have identical infrastructure capabilities. The architecture therefore defines three profiles.

### 5.1 Group A — Node.js + WebSocket

Group A environments can install and operate Node.js.

```text
ZF1 → Internal Signal → Node.js → WebSocket → Client
                         ↑
                     signal only
```

Node.js maintains active client connections and routes signals. Clients obtain actual notification data from ZF1.

### 5.2 Group B — Workerman + WebSocket

Group B cannot install Node.js but can run a long-running PHP CLI process.

```text
ZF1 → Internal Signal → Workerman → WebSocket → Client
                         ↑
                     signal only
```

Workerman is particularly suitable for this environment because it provides the long-running event-driven networking model that ordinary Apache/PHP request processing does not provide.

Workerman runs independently from the ZF1 web request lifecycle.

### 5.3 Group C — REST Polling

Group C cannot operate a dedicated realtime process.

```text
Client → periodic HTTPS request → ZF1 → MariaDB
```

This environment still uses the same notification tables, events, APIs, reminder logic, and client notification manager.

### 5.4 One System, Three Transports

These profiles must not become three separate notification implementations.

The transport is configurable, while the business system remains common.

```text
                         MariaDB
              ┌────────────┼────────────┐
              │            │            │
         Reminders   Notifications    Events
              │            │            │
              └────────────┼────────────┘
                           │
                           ▼
                          ZF1
                 Business / Auth / API
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
           Node.js      Workerman     Polling
           Group A       Group B       Group C
              │            │            │
              └────────────┼────────────┘
                           ▼
                  Browser / Windows
```

---

## 6. Why Standard PHP Request Processing Is Not the WebSocket Server

Traditional PHP execution under Apache or PHP-FPM follows a request/response lifecycle:

```text
Request → PHP starts/handles request → Response → request finishes
```

WebSocket requires long-lived connections:

```text
Client ⇄ persistent connection ⇄ server
```

Although PHP socket functions can technically be used to build a WebSocket implementation, a production implementation must correctly handle protocol handshakes, frames, masking, fragmentation, ping/pong, connection closing, nonblocking I/O, concurrent clients, timeouts, and error recovery.

The recommended PHP solution is therefore a dedicated long-running Workerman process rather than implementing the WebSocket protocol manually inside the existing ZF1 application.

This preserves PHP compatibility for Group B without coupling persistent network connections to Apache request workers.

---

## 7. Notification Data Model

The system supports the following baseline notification categories:

- `message`
- `document_shared`
- `task_assigned`
- `system`
- `reminder`

A notification contains user ownership, type, title, message, related entity information, optional application action URL, read state, and timestamps.

The notification itself is not the only reliability record. The architecture also maintains a notification event log and a transactional outbox.

---

## 8. Event Log and `event_id`

Each logical notification change produces an event with a monotonically increasing `event_id`.

Examples include:

- `notification.created`
- `notification.read`
- `notification.read_all`
- `notification.deleted`
- `reminder.triggered`
- `reminder.snoozed`
- `reminder.dismissed`
- `reminder.completed`

The `event_id` has three major purposes:

1. **Ordering** — clients can process events in sequence.
2. **Deduplication** — duplicate realtime signals can be safely ignored.
3. **Recovery** — a client can request all events after its last known identifier.

For example:

```http
GET /api/notification-events?after=9004
```

If event `9005` was missed, ZF1 can return it even if the realtime signal was never received.

This makes WebSocket delivery recoverable rather than fragile.

---

## 9. Transactional Outbox Pattern

A major reliability problem occurs if an application updates business data and then immediately attempts a network call to a realtime server.

Consider this unsafe sequence:

```text
1. Save notification to database
2. Commit
3. Send signal to Workerman
4. Network failure
```

The business record exists, but the signal may be lost.

The proposed architecture records the signal work inside the same database transaction:

```text
BEGIN
  business change
  create notification
  create notification event
  create outbox item
COMMIT
```

A separate dispatcher processes the outbox after the transaction commits.

```text
MariaDB Outbox
      │
      ▼
Dispatcher
      │
      ▼
Internal Signal
      │
      ▼
Workerman / Node.js
```

If signaling fails, the outbox row remains available for retry.

An example retry schedule is:

```text
5 sec → 15 sec → 30 sec → 60 sec → 300 sec
```

The exact retry policy is configurable.

This design means a realtime-network failure does not undo a successfully completed business transaction.

---

## 10. Delivery Semantics: At-Least-Once

The architecture intentionally uses **at-least-once signaling** rather than attempting exactly-once network delivery.

A duplicate can occur when:

1. Workerman receives an event;
2. the dispatcher fails before recording successful completion; and
3. the dispatcher retries the same outbox item.

The client therefore treats `event_id` as the deduplication key.

Exactly-once network delivery is not required because durable state and client reconciliation provide correctness.

This is simpler and more resilient than relying on a distributed exactly-once guarantee.

---

## 11. Failure Analysis

Reliability was treated as a primary architecture requirement rather than an afterthought.

### 11.1 Realtime Server Completely Unavailable

If Workerman or Node.js stops:

```text
ZF1 business operation        ✓
MariaDB notification          ✓
MariaDB event                 ✓
Outbox item                   ✓
Realtime signal               ✗ temporarily
```

The notification is not lost.

The dispatcher retries, while clients can use REST polling/recovery. When realtime service returns, normal signaling resumes.

### 11.2 WebSocket Disconnects

The browser transport manager can switch to polling when WebSocket is unavailable.

```text
WebSocket
    X
    │
    ▼
Polling fallback
    │
    ▼
ZF1 API
```

The system periodically attempts to restore WebSocket and can stop fallback polling when the realtime transport becomes healthy again.

### 11.3 Workerman Is Healthy but the Internal Signal Channel Fails

This is a less obvious and important failure mode.

```text
Browser
   │
   │ WebSocket ✓
   ▼
Workerman
   ▲
   │ Internal signal ✗
   │
  ZF1
```

From the browser's perspective, WebSocket still appears connected. A design that uses WebSocket connection status alone would incorrectly assume that notification delivery is healthy.

The architecture therefore uses two independent recovery mechanisms:

- the server-side outbox retries the failed ZF1-to-Workerman signal; and
- the client periodically reconciles its last `event_id` with ZF1 even while WebSocket remains connected.

For example:

```http
GET /api/notification-events?after=9004
```

If event `9005` was persisted but never signaled, the client discovers it through reconciliation.

A healthy WebSocket is therefore **not considered proof of end-to-end realtime health**.

### 11.4 Operational Health States

The realtime subsystem can be described using three states:

| State | WebSocket | Internal Signal | Result |
|---|---:|---:|---|
| `HEALTHY` | Healthy | Healthy | Immediate realtime delivery |
| `DEGRADED` | Healthy | Unhealthy | Connections remain active; outbox retry and REST reconciliation protect delivery |
| `DOWN` | Unavailable | Unavailable/not usable | Client falls back to polling/recovery |

The realtime service should expose protected health information so operations staff can distinguish degraded signaling from a complete service outage.

---

## 12. Periodic Reconciliation

WebSocket provides immediacy but periodic REST reconciliation provides an independent correctness check.

A configurable interval—initially approximately 30–60 seconds—is appropriate for many deployments.

This is not the same as normal polling.

- **WebSocket signaling:** immediate notification path.
- **Periodic reconciliation:** lightweight verification that no event was missed.
- **Fallback polling:** temporary primary transport when WebSocket is unavailable.

This distinction is important because a connected WebSocket can still coexist with an upstream signal failure.

---

## 13. Browser Multi-Tab Architecture

Opening several tabs should not require every tab to create its own WebSocket connection.

The browser design elects one tab as the **leader**.

```text
                 Workerman / Node.js
                         │
                    WebSocket
                         │
                    Leader Tab
                    /    |    \
                   /     |     \
      BroadcastChannel   |   BroadcastChannel
                 /       |       \
              Tab 2    Tab 3    Tab 4
```

The leader owns the realtime transport and broadcasts local notification signals to follower tabs using `BroadcastChannel`.

`localStorage` is used for leader ownership/heartbeat coordination, while `BroadcastChannel` is used for event distribution.

If the leader tab closes or becomes unhealthy, another tab can become leader.

Brief duplicate leadership is acceptable because `event_id` deduplication protects correctness.

Benefits include:

- fewer WebSocket connections;
- reduced server resource use;
- consistent cross-tab state;
- simpler reconnect behavior; and
- better compatibility with users who routinely open multiple application tabs.

---

## 14. Read-State Synchronization

Notification read state belongs to the user and notification—not to a browser tab.

If a notification is marked read in one tab or in the Windows client, the state is persisted through ZF1 and propagated/synchronized to other clients.

The same principle applies to actions such as dismissing or completing reminder occurrences.

MariaDB remains authoritative if two clients temporarily disagree.

---

## 15. Reminder Architecture

A reminder and a notification are related but different concepts.

- A **reminder** represents scheduled business intent.
- A **notification** represents an alert delivered when the reminder becomes due.

The reminder schedule is controlled by the server rather than by a browser timer.

```text
Reminder definition
       │
       ▼
Reminder Worker
       │
       ▼
Due occurrence
       │
       ├── notification
       ├── event
       └── outbox
              │
              ▼
       Realtime / Polling
```

This means reminders still trigger if the user's browser is closed at the scheduled time.

### 15.1 Reminder Actions

The baseline user actions are:

- **Open** — navigate to the related business item;
- **Snooze** — hide the current alert and schedule it again later;
- **Dismiss** — acknowledge/hide the current occurrence without declaring the underlying work complete;
- **Complete** — mark the underlying reminder/work complete;
- **Cancel** — stop the reminder definition.

For recurring reminders, dismissing one occurrence must not cancel future occurrences.

The system therefore models reminder occurrences separately from the reminder definition.

---

## 16. Windows Desktop Client

The Windows desktop application follows the same architectural boundary as the browser.

It never connects directly to MariaDB.

```text
Windows Client
     │
     ├── WSS signal / REST polling
     │
     └── HTTPS authenticated ZF1 API
```

Potential client capabilities include:

- system tray integration;
- Windows toast notifications;
- unread count;
- recent notification list;
- opening the related S3 application page;
- reconnect and polling fallback;
- reminder snooze/dismiss/complete; and
- synchronization with browser state.

The transport abstraction allows the desktop client to use WebSocket in Groups A/B and polling in Group C without changing notification business semantics.

---

## 17. Security Architecture

### 17.1 Client Authentication

A client must never be allowed to claim an arbitrary `user_id` when opening a WebSocket.

The preferred flow is:

```text
Authenticated browser session
        │
        ▼
POST /api/realtime/token
        │
        ▼
ZF1 derives authenticated user
        │
        ▼
Short-lived realtime token
        │
        ▼
WebSocket authentication
        │
        ▼
Realtime server binds connection to user
```

The realtime token should be short-lived and purpose-specific.

The realtime server authenticates the connection but does not replace ZF1 business authorization.

### 17.2 Internal Signal Authentication

The internal ZF1-to-realtime signal endpoint must not be a public browser API.

Recommended controls include:

- HTTPS where appropriate;
- loopback/private-network binding where possible;
- firewall restrictions;
- timestamp validation;
- HMAC-SHA256 request signing;
- high-entropy shared secret stored outside source code; and
- timing-safe signature comparison.

A conceptual signature is:

```text
HMAC-SHA256(secret, timestamp + "." + raw_request_body)
```

### 17.3 Application Authorization

All protected notification and reminder data continues to be retrieved through authenticated ZF1 APIs.

This preserves existing authorization boundaries and avoids trusting a lightweight realtime service with business-access decisions.

### 17.4 Browser State Changes

Cookie/session-based state-changing APIs should continue to use the application's CSRF protection for actions such as:

- mark read;
- mark all read;
- snooze;
- dismiss;
- complete; and
- cancel.

---

## 18. Why Redis Is Not Required Initially

Redis can be valuable in large distributed realtime systems, particularly when multiple realtime-server instances need shared connection/session/event coordination.

However, it is not required for the current architecture.

The initial design uses:

- MariaDB for durable notifications, events, reminders, and outbox work;
- Workerman/Node.js process memory for active connections; and
- REST recovery for missed events.

Avoiding Redis initially reduces infrastructure requirements for subsidiaries and makes deployment easier.

Redis can be reconsidered later if horizontal scaling requirements justify it. Its absence is therefore an intentional scope decision, not a limitation in notification correctness.

---

## 19. Operational and Management Benefits

### 19.1 Incremental Modernization

The proposal adds realtime capability without requiring immediate migration away from ZF1, PHP 7.4, DHTMLX 3.5, or MariaDB.

### 19.2 Common Product Across Different Subsidiaries

The same business implementation supports subsidiaries with different infrastructure capabilities. Deployment configuration selects the transport rather than creating separate codebases.

### 19.3 Failure Isolation

A realtime outage does not have to become a business-system outage.

The notification record remains in MariaDB, and the user can recover it through REST synchronization.

### 19.4 Centralized Security Decisions

ZF1 remains the place where business authorization is evaluated. This reduces the risk of inconsistent access rules between the main application and the realtime server.

### 19.5 Reduced Unnecessary Network Traffic

Where WebSocket is available, the client does not need aggressive continuous polling to discover new notifications. Reconciliation can be much lighter and less frequent.

### 19.6 Future Extensibility

The same event infrastructure can support additional notification categories without changing the transport architecture.

---

## 20. Tradeoffs and Risks

No architecture removes all operational cost. The proposed design introduces several components that require disciplined operation.

### 20.1 Additional Long-Running Service

Groups A and B must operate Node.js or Workerman as a supervised service. Service monitoring, restart policy, logging, and TLS/reverse-proxy configuration are required.

### 20.2 Outbox Maintenance

Outbox backlog, retry failures, and permanently failed items must be observable. Retention/cleanup jobs are also required for old processed rows and event records.

### 20.3 Client Complexity

Leader election, reconnect behavior, deduplication, fallback polling, and reconciliation are more complex than a simple polling loop. The benefit is substantially stronger reliability and reduced unnecessary traffic.

### 20.4 Skeleton-to-Production Integration

The current reference implementation was built against a structural skeleton of the existing project. Production integration still needs to connect to the application's final REST routing, CSRF middleware/helper, exact user identity fields, ACL/business-entity authorization, and DHTMLX presentation layer.

These are integration boundaries rather than changes to the core architecture.

### 20.5 PHP 7.4 Lifecycle

The notification implementation is designed for the current PHP 7.4 environment. PHP/runtime modernization should be treated as a separate platform lifecycle project rather than coupling it to the initial notification rollout.

---

## 21. Recommended Deployment Strategy

A staged deployment reduces risk.

### Phase 1 — Core Persistence and APIs

Deploy:

- notification tables;
- event log;
- outbox;
- notification service;
- REST APIs; and
- polling client.

This establishes the source-of-truth system before realtime delivery is enabled.

### Phase 2 — Workerman Pilot

Enable Workerman in one suitable Group B/test environment.

Validate:

- connection authentication;
- internal HMAC signal delivery;
- outbox retries;
- reconnect behavior;
- multi-tab coordination;
- periodic reconciliation; and
- health monitoring.

### Phase 3 — Reminder System

Enable the server-side reminder worker and validate due-time, snooze, dismiss, complete, recurring occurrence, and timezone behavior.

### Phase 4 — Node.js Profile

Enable the Node.js realtime server for Group A using the same signal protocol and client behavior.

### Phase 5 — Windows Client

Integrate desktop toast/tray behavior using the already-established APIs and transport protocol.

### Phase 6 — Operational Hardening

Add or finalize:

- monitoring dashboards/alerts;
- outbox backlog thresholds;
- service health checks;
- log rotation;
- event/outbox retention;
- load tests;
- failure-injection tests;
- security review; and
- production runbooks.

---

## 22. Testing Strategy

The architecture should be tested at several levels.

### 22.1 Unit Tests

Test notification/reminder service behavior including:

- create notification;
- mark read;
- mark all read;
- ownership validation;
- event creation;
- outbox creation;
- reminder triggering;
- snooze;
- dismiss;
- complete; and
- duplicate/idempotent actions.

### 22.2 Integration Tests

Test complete flows such as:

```text
ZF1 transaction
 → MariaDB event/outbox
 → dispatcher
 → internal signal
 → Workerman/Node.js
 → WebSocket
 → client API retrieval
```

### 22.3 Failure Tests

Explicitly test:

- Workerman stopped;
- Node.js stopped;
- internal signal listener unavailable while WebSocket remains healthy;
- database temporarily unavailable;
- dispatcher restart;
- duplicate signal delivery;
- browser reconnect;
- leader tab closes;
- multiple tabs briefly become leader;
- client misses several event IDs;
- expired realtime token; and
- polling fallback/recovery.

The system should be considered production-ready only after failure behavior is tested, not merely successful-path delivery.

---

## 23. Monitoring Recommendations

Useful operational metrics include:

- active WebSocket connections;
- authenticated connections;
- internal signal success/failure rate;
- outbox pending count;
- age of oldest pending outbox item;
- dispatcher retry count;
- permanently failed outbox count;
- reconciliation recovery count;
- WebSocket reconnect rate;
- fallback polling activations;
- reminder worker delay; and
- API error rate.

The age of the oldest pending outbox item is particularly valuable because it indicates whether realtime signaling is falling behind even if processes are technically still running.

---

## 24. Architectural Summary

The final architecture can be summarized as follows:

```text
                         MariaDB
              ┌────────────┼────────────┐
              │            │            │
         Reminders   Notifications    Events
              │            │            │
              └────────────┼────────────┘
                           │
                           ▼
                          ZF1
              Business / Auth / REST APIs
                           │
                        Outbox
                           │
                      Dispatcher
                           │
                   Internal Signal
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
          Workerman                  Node.js
           Group B                    Group A
              │                         │
              └────── WebSocket ─────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
         Browser Leader             Windows App
              │                         │
       BroadcastChannel                 │
              │                         │
              └────────────┬────────────┘
                           ▼
                  Authenticated ZF1 API
                           │
                           ▼
                    Actual Full Data

Group C uses authenticated ZF1 REST polling.
```

The trust boundary is intentionally clear:

```text
MariaDB / ZF1  = business truth, authorization and durable state
Realtime Server = authenticated lightweight signal transport
Browser/Desktop = presentation, interaction and API consumption
BroadcastChannel = local browser-tab coordination
```

---

## 25. Conclusion

The proposed realtime notification and reminder system provides a practical modernization path for the existing S3 platform without requiring a disruptive rewrite.

Its most important property is that **realtime delivery is not allowed to become a single point of correctness**. Notifications, events, reminders, and retry work are persisted in MariaDB; ZF1 remains responsible for business authorization; and clients can recover missed events through authenticated APIs.

Workerman and Node.js improve notification latency by carrying lightweight signals. They do not become alternative business backends. This substantially limits the scope and security responsibility of the realtime layer.

The three deployment profiles allow the organization to use one logical notification product across subsidiaries with different infrastructure capabilities. Polling remains a supported baseline, Workerman provides a PHP-compatible realtime option, and Node.js provides an alternative for higher-capability environments.

The transactional outbox, `event_id` synchronization model, at-least-once signaling, multi-tab leader coordination, automatic polling fallback, and periodic reconciliation collectively address the major failure scenarios identified during the design research.

In particular, the architecture handles the subtle case where a WebSocket remains connected while the internal ZF1-to-realtime signal channel is unavailable. Server-side retries and client-side reconciliation ensure that connection status alone is never mistaken for delivery correctness.

The result is a system designed around a simple operational principle:

> **Business events must remain durable and recoverable even when realtime infrastructure is temporarily unavailable.**

This architecture can therefore be introduced incrementally, evaluated in controlled deployments, and expanded without changing the core notification business model.

---

## Appendix A — Core Architectural Rules

1. ZF1 and MariaDB remain the authoritative business and data layer.
2. Workerman and Node.js are primarily lightweight signal servers.
3. Clients retrieve protected notification/reminder data through authenticated ZF1 APIs.
4. Business transactions do not depend on successful realtime network delivery.
5. Notification events and outbox work are persisted transactionally.
6. Signaling uses at-least-once semantics; clients deduplicate with `event_id`.
7. WebSocket failure triggers polling/recovery rather than data loss.
8. A healthy WebSocket does not prove that the internal signal channel is healthy.
9. Clients periodically reconcile their last processed `event_id` with ZF1 even while WebSocket is connected.
10. Healthy WebSocket connections should not be unnecessarily disconnected when only the internal signal channel is degraded.
11. One browser leader normally owns the external transport; follower tabs synchronize locally.
12. Reminder timing is server-authoritative.
13. The Windows client never directly accesses MariaDB.
14. Redis is not required in the current architecture.
15. Group A, B and C deployments share the same business implementation and differ primarily in transport configuration.

---

## Appendix B — Key API Surface

Representative notification and realtime endpoints:

```text
GET  /api/notifications
GET  /api/notifications/{id}
GET  /api/notifications/unread-count
POST /api/notifications/{id}/read
POST /api/notifications/read-all
GET  /api/notification-events?after={eventId}
GET  /api/realtime/config
POST /api/realtime/token
```

Representative reminder endpoints:

```text
GET    /api/reminders
POST   /api/reminders
PUT    /api/reminders/{id}
POST   /api/reminders/{id}/snooze
POST   /api/reminders/{id}/dismiss
POST   /api/reminders/{id}/complete
POST   /api/reminders/{id}/cancel
```

---

## Appendix C — Implementation Status

A reference source package has been prepared against the S3 structural project skeleton. It includes:

- MariaDB notification/reminder schema;
- ZF1 notification and reminder services;
- notification event and outbox processing;
- signed internal signal client;
- notification/realtime/reminder REST controllers;
- realtime token/configuration support;
- browser leader election and cross-tab coordination;
- WebSocket and polling transports;
- periodic event reconciliation;
- Workerman realtime server;
- Node.js realtime server alternative;
- reminder worker;
- notification dispatcher; and
- CentOS service examples.

Production integration must still map the reference implementation to the complete application's final REST routing, CSRF implementation, exact identity/ACL model, DHTMLX presentation layer, monitoring environment, and deployment secrets.
