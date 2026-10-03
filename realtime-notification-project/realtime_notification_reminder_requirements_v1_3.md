# Realtime Notification and Reminder System --- Application Requirements

**Version:** 1.1\
**Target environment:** Zend Framework 1 (ZF1), PHP 7.4, MariaDB, DHTMLX
3.5, CentOS, Windows desktop client\
**Redis:** Not used in the current architecture

## 1. Purpose

This document defines the requirements for a unified notification and
reminder system for the existing ZF1 business application and its
Windows desktop companion application.

The system must support subsidiaries with different infrastructure
capabilities while preserving one common business architecture, data
model, API model, event protocol, and client behavior.

The central design principle is:

> **ZF1 and MariaDB are the authoritative business and data layer.
> Realtime servers provide lightweight signals only. Clients retrieve
> authoritative data through normal authenticated ZF1 APIs.**

The realtime transport is therefore an acceleration mechanism, not the
source of notification data.

------------------------------------------------------------------------

## 2. Existing Environment

The solution shall integrate with the existing environment:

-   Backend: Zend Framework 1
-   PHP: PHP 7.4
-   Database: MariaDB
-   Frontend: DHTMLX 3.5 and JavaScript
-   Server OS: CentOS
-   Desktop client: Windows
-   Existing authentication and authorization remain controlled by ZF1.

The notification implementation should be independent of DHTMLX iframe
internals and operate at the top-level application layer.

------------------------------------------------------------------------

## 3. Subsidiary Infrastructure Groups

### 3.1 Group A --- High-Capability Infrastructure

Group A subsidiaries can install and operate Node.js.

Primary notification transport:

-   Node.js realtime signal server
-   WebSocket between realtime server and clients
-   ZF1 REST API for authoritative data retrieval

### 3.2 Group B --- Medium-Capability Infrastructure

Group B subsidiaries cannot install Node.js but can operate a
long-running PHP CLI process.

Primary notification transport:

-   Workerman realtime signal server
-   WebSocket between Workerman and clients
-   ZF1 REST API for authoritative data retrieval

Workerman shall run independently from Apache/ZF1.

### 3.3 Group C --- Basic Infrastructure

Group C subsidiaries cannot run a dedicated realtime server.

Primary notification transport:

-   ZF1 REST API
-   Periodic polling
-   No WebSocket server required

------------------------------------------------------------------------

## 4. Supported Notification Methods

The system shall support three configurable methods:

1.  `polling` --- ZF1 REST API polling
2.  `workerman` --- Workerman + WebSocket signaling
3.  `nodejs` --- Node.js + WebSocket signaling

These are deployment profiles and transport choices, not separate
notification systems.

------------------------------------------------------------------------

## 5. Common Architecture

All three methods shall share:

-   the same ZF1 business logic;
-   the same MariaDB notification data;
-   the same notification event log;
-   the same reminder subsystem;
-   the same authenticated ZF1 APIs;
-   the same logical event/signal protocol;
-   the same browser notification manager;
-   the same Windows desktop application;
-   the same read, dismiss, snooze, complete, and synchronization rules.

Conceptual architecture:

``` text
                         MariaDB
             ┌─────────────┼──────────────┐
             │             │              │
        Reminders    Notifications     Events
             │             │              │
             └─────────────┼──────────────┘
                           │
                           ▼
                          ZF1
             Business / Auth / REST APIs
                           │
             ┌─────────────┼─────────────┐
             │             │             │
             ▼             ▼             ▼
          Group A       Group B       Group C
          Node.js       Workerman      Polling
             │             │             │
             │ Signal      │ Signal      │ REST
             └─────────────┼─────────────┘
                           ▼
                 Browser / Desktop
                           │
                           ▼
               Authenticated ZF1 API
                           │
                           ▼
                  Authoritative Data
```

------------------------------------------------------------------------

## 6. Realtime Servers Are Signal Servers

### 6.1 Core Rule

Workerman and Node.js shall normally **not fetch full notification
business data from MariaDB**.

They shall primarily distribute lightweight signals.

For example, an internal ZF1-to-Workerman signal may be:

``` json
{
    "user_id": 200,
    "event_id": 9005,
    "event": "notification.created",
    "notification_id": 1060
}
```

Workerman uses `user_id` for routing but does not need to send it to the
client.

The WebSocket client may receive:

``` json
{
    "event_id": 9005,
    "event": "notification.created",
    "notification_id": 1060
}
```

The client then retrieves authoritative data through ZF1:

``` http
GET /api/notifications/1060
```

or performs event synchronization:

``` http
GET /api/notification-events?after=9004
```

### 6.2 Responsibilities of ZF1

ZF1 shall remain responsible for:

-   authentication;
-   authorization;
-   business rules;
-   notification creation;
-   reminder management;
-   MariaDB access;
-   notification ownership checks;
-   read state;
-   dismiss/snooze/complete actions;
-   REST APIs;
-   realtime-token issuance;
-   event persistence;
-   recovery synchronization.

### 6.3 Responsibilities of Workerman / Node.js

The realtime server shall be limited primarily to:

-   authenticating WebSocket clients;
-   maintaining user-to-connection mappings;
-   accepting trusted internal signals from ZF1;
-   routing signals to connected users;
-   supporting multiple connections for a user;
-   heartbeat/ping-pong;
-   dead connection cleanup;
-   connection lifecycle management.

The realtime server shall not become a second business application.

------------------------------------------------------------------------

## 7. Redis

Redis shall **not** be used in the current implementation.

The current architecture shall use:

-   MariaDB for durable notification/reminder/event state;
-   in-process memory in Workerman or Node.js for active WebSocket
    connections;
-   internal ZF1-to-realtime-server signals;
-   REST synchronization/recovery for correctness.

Redis may be reconsidered later only if requirements such as multiple
realtime-server instances, cross-process pub/sub, or substantially
higher scale make it necessary.

------------------------------------------------------------------------

## 8. Configurable Notification Method

Administrators shall be able to select the appropriate method for each
subsidiary.

Example configuration values:

``` ini
notification.enabled = true
notification.transport = "workerman"

notification.websocket.url = "wss://realtime.company.local/ws"
notification.polling.interval = 15
notification.recovery.enabled = true
notification.polling_fallback.enabled = true
```

The administration UI should support:

``` text
Notification Settings

Enable Notifications:
[✓]

Notification Method:
[ Workerman + WebSocket ▼ ]

WebSocket URL:
[ wss://realtime.company.local/ws ]

Polling Interval:
[ 15 ] seconds

Automatic Polling Fallback:
[✓]

[Test Connection]

[Save]
```

Production WebSocket deployments shall use `wss://`.

------------------------------------------------------------------------

## 9. Client Configuration API

The effective configuration should be owned by the server.

Example:

``` http
GET /api/realtime/config
```

Example response:

``` json
{
    "enabled": true,
    "method": "workerman",
    "transport": "websocket",
    "websocket_url": "wss://realtime.company.local/ws",
    "polling_interval": 15,
    "recovery_enabled": true,
    "polling_fallback_enabled": true
}
```

This permits the same website JavaScript and Windows application to
operate at all subsidiaries.

------------------------------------------------------------------------

## 10. Notification Types

Initial notification types shall include:

-   `message`
-   `document_shared`
-   `task_assigned`
-   `system`
-   `reminder`

Additional types may be added without changing the transport
architecture.

------------------------------------------------------------------------

## 11. Notification Signals and Events

Initial logical notification events shall include:

-   `notification.created`
-   `notification.read`
-   `notification.read_all`
-   `notification.deleted`

Reminder events shall include:

-   `reminder.created`
-   `reminder.updated`
-   `reminder.triggered`
-   `reminder.snoozed`
-   `reminder.dismissed`
-   `reminder.completed`
-   `reminder.cancelled`

Signals should remain lightweight. Clients fetch business data from ZF1
when required.

------------------------------------------------------------------------

## 12. Event Identifier

Every persisted event shall have a monotonically increasing `event_id`.

`event_id` shall support:

-   synchronization;
-   recovery;
-   ordering;
-   duplicate detection;
-   at-least-once delivery.

Clients shall not assume that every realtime signal is delivered exactly
once.

------------------------------------------------------------------------

## 13. Browser Multi-Tab Coordination

The website shall use:

-   `BroadcastChannel` for communication between tabs;
-   `localStorage` for leader coordination/heartbeat where required.

Only one tab should normally act as the leader.

### WebSocket mode

``` text
Realtime Server
      │
   WebSocket
      │
  Leader Tab
      │
BroadcastChannel
  ┌───┼───┐
  ▼   ▼   ▼
Tab2 Tab3 Tab4
```

### Polling mode

``` text
ZF1 REST API
      │
   Polling
      │
  Leader Tab
      │
BroadcastChannel
  ┌───┼───┐
  ▼   ▼   ▼
Tab2 Tab3 Tab4
```

Therefore, follower tabs shall normally neither open their own WebSocket
nor independently poll the server.

------------------------------------------------------------------------

## 14. Tab Coordinator

`S3TabCoordinator` shall provide:

-   unique tab ID;
-   leader election;
-   heartbeat;
-   leader timeout detection;
-   failover election;
-   BroadcastChannel messaging;
-   leader shutdown handling.

Recommended defaults:

``` text
Heartbeat interval: 3 seconds
Leader timeout:     8 seconds
Election delay:     randomized
```

A short-lived duplicate leader situation shall not cause incorrect state
because event processing must be idempotent.

------------------------------------------------------------------------

## 15. Browser Components

Recommended structure:

``` text
S3TabCoordinator
        │
        ▼
S3NotificationTransportManager
        │
   ┌────┴─────┐
   ▼          ▼
Polling    WebSocket
Transport  Transport
   │          │
   │     Workerman/Node
   └────┬─────┘
        │
        ▼
Lightweight Signal
        │
        ▼
S3NotificationManager
        │
        ├── authenticated ZF1 API fetch
        │
        ▼
S3NotificationUI
```

The browser should not require separate Workerman and Node.js WebSocket
client implementations because both servers shall implement the same
client protocol.

------------------------------------------------------------------------

## 16. Automatic Polling Fallback

For Group A and Group B, WebSocket is an optimization for immediate
signaling.

If the realtime server becomes unavailable, the application shall be
able to fall back to REST polling.

``` text
WebSocket
    │
    X unavailable
    │
    ▼
REST polling
    │
    ├── synchronize missed events
    └── periodically retry WebSocket
                 │
                 ▼
          WebSocket restored
                 │
                 ▼
           stop fallback polling
```

Recommended mapping:

  Group   Primary               Fallback
  ------- --------------------- -------------
  A       Node.js WebSocket     ZF1 polling
  B       Workerman WebSocket   ZF1 polling
  C       ZF1 polling           ZF1 polling

------------------------------------------------------------------------

## 17. Workerman Temporary Outage

A Workerman outage shall **not cause notification data loss**.

Example:

``` text
ZF1 creates notification
        │
        ├── notification saved
        ├── event saved
        └── outbox/signal work saved
                 │
                 ▼
              Workerman
                  X
                 DOWN
```

The business transaction remains valid.

During the outage:

1.  notifications continue to be stored in MariaDB;
2.  event history remains available;
3.  clients may use fallback polling;
4.  the internal dispatcher may retry pending signals;
5.  after reconnect, clients shall synchronize through the authenticated
    ZF1 event API.

Realtime availability and data correctness shall therefore be
independent.

------------------------------------------------------------------------

## 18. Recovery After Realtime Outage

When a client reconnects, it shall synchronize using its last known
event ID.

Example:

``` http
GET /api/notification-events?after=9004
```

ZF1 may return:

``` json
{
    "events": [
        {
            "event_id": 9005,
            "event": "notification.created",
            "notification_id": 1060
        },
        {
            "event_id": 9006,
            "event": "notification.created",
            "notification_id": 1061
        }
    ],
    "last_event_id": 9006
}
```

The client may then retrieve the required notification data through
authenticated APIs.

Recovery logic must avoid a race between incoming WebSocket signals and
REST recovery. Events received during recovery should be buffered and/or
ordered and deduplicated by `event_id`.

------------------------------------------------------------------------

## 19. Internal Signal Failure While WebSocket Remains Healthy

The system shall treat the WebSocket connection and the internal ZF1-to-realtime-server signal channel as two independent communication paths.

The following failure condition is possible:

```text
Browser / Desktop
       │
       │ WebSocket ✓
       ▼
   Workerman
       ▲
       │ Internal signal ✗
       │
      ZF1
```

In this condition, Workerman is running normally and client WebSocket connections remain active, but ZF1 cannot deliver new internal signals to Workerman.

A healthy WebSocket connection shall therefore **not** be treated as proof that the complete realtime notification delivery path is healthy.

### 19.1 Expected Behavior

If ZF1 creates a notification while the internal signal channel is unavailable:

```text
ZF1 business action
       │
       ▼
MariaDB transaction
       │
       ├── notification saved      ✓
       ├── event saved             ✓
       └── outbox item saved       ✓
              │
              ▼
          Dispatcher
              │
              │ internal signal
              ▼
          Workerman
              X
       signal channel failure
```

the notification and event shall remain safely persisted in MariaDB.

Failure of the internal signal channel shall not roll back or invalidate the completed business transaction.

### 19.2 Outbox Retry

If the dispatcher cannot send the internal signal, the related outbox item shall remain retryable.

Conceptually:

```text
event_id     = 9005
status       = pending
attempts     = 1
last_error   = internal signal connection failed
available_at = next retry time
```

The dispatcher shall retry according to the configured retry policy.

Example:

```text
Internal signal fails
        │
        ▼
Outbox remains pending
        │
        ▼
5-second retry
        │
        X
15-second retry
        │
        X
30-second retry
        │
        ✓
Internal channel restored
        │
        ▼
Workerman receives signal
        │
        ▼
WebSocket clients receive signal
```

Because delivery uses an at-least-once model, delayed or duplicate signals are acceptable. Clients shall deduplicate events using `event_id`.

### 19.3 Periodic Client Reconciliation

Clients shall periodically perform a lightweight reconciliation with ZF1 even while their WebSocket connection is healthy.

This protects against failures where:

- the WebSocket connection remains active;
- Workerman remains operational;
- but the internal ZF1-to-Workerman signal path is unavailable.

Example reconciliation request:

```http
GET /api/notification-events?after=9004
```

Example response:

```json
{
    "events": [
        {
            "event_id": 9005,
            "event": "notification.created",
            "notification_id": 1060
        }
    ],
    "last_event_id": 9005
}
```

The client shall process any missing events and retrieve authoritative business data from the normal authenticated ZF1 API when required.

A configurable reconciliation interval shall be supported. An initial operational value of approximately **30 to 60 seconds** is recommended, subject to deployment load and notification-latency requirements.

This reconciliation is a lightweight correctness check and is distinct from normal fallback polling.

### 19.4 Do Not Disconnect Healthy WebSockets

Failure of the internal signal channel alone shall not require Workerman to close healthy client WebSocket connections.

The preferred behavior is:

```text
Internal signal channel fails
          │
          ├── existing WebSockets remain connected
          │
          ├── ZF1 outbox retries failed signals
          │
          ├── clients periodically reconcile with ZF1
          │
          └── internal signal channel recovers
                         │
                         ▼
                  normal realtime
```

This avoids unnecessary reconnect storms and preserves other healthy realtime-server functionality.

### 19.5 Realtime Health States

The system should distinguish at least the following operational states:

```text
HEALTHY
    WebSocket service ✓
    Internal signal   ✓

DEGRADED
    WebSocket service ✓
    Internal signal   ✗

DOWN
    Realtime server   ✗
```

Workerman and Node.js implementations should expose a protected/internal health endpoint or equivalent status mechanism.

Example conceptual response:

```json
{
    "status": "degraded",
    "websocket": "healthy",
    "internal_signal": "unhealthy"
}
```

Health information may be used by administrators, monitoring services, or a future connection-test function.

### 19.6 Independent Reliability Layers

The notification architecture shall use independent reliability layers:

```text
                    MariaDB
                       │
                 Source of Truth
                       │
          ┌────────────┴────────────┐
          │                         │
     Internal Signal           REST Recovery
          │                         │
          ▼                         │
 Workerman / Node.js                │
          │                         │
      WebSocket                     │
          │                         │
          └────────────┬────────────┘
                       ▼
                     Client
```

Responsibilities:

| Mechanism | Responsibility |
|---|---|
| MariaDB | Durable notification, reminder, event, and outbox state |
| Internal signal | Immediately inform the realtime server about a committed event |
| WebSocket | Immediately signal connected clients |
| Outbox retry | Recover failed ZF1-to-realtime-server signal delivery |
| Periodic REST reconciliation | Detect events missed even when WebSocket remains connected |
| Fallback polling | Continue operation when realtime transport is unavailable |
| `event_id` | Ordering, synchronization, and duplicate detection |

### 19.7 Required Reliability Rule

The implementation shall follow this rule:

> **A connected WebSocket is not sufficient evidence that end-to-end realtime notification delivery is healthy. Clients shall periodically reconcile their last processed `event_id` with the authenticated ZF1 event API, while the server-side outbox independently retries failed internal signals.**

This requirement applies to both Workerman and Node.js realtime deployments.

---

## 20. Notification Database

### `notifications`

``` sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique notification identifier',
    user_id BIGINT UNSIGNED NOT NULL
        COMMENT 'ID of the user who owns and receives the notification',
    type VARCHAR(50) NOT NULL
        COMMENT 'Notification type, such as message, document_shared, task_assigned, system, or reminder',
    title VARCHAR(255) NOT NULL
        COMMENT 'Short notification title displayed to the user',
    message TEXT NOT NULL
        COMMENT 'Notification message or summary displayed to the user',

    entity_type VARCHAR(50) DEFAULT NULL
        COMMENT 'Type of related business entity, such as message, document, task, or reminder',
    entity_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Identifier of the related business entity',
    action_url VARCHAR(500) DEFAULT NULL
        COMMENT 'Server-controlled application URL opened when the user selects the notification',

    is_read TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Read state: 0 = unread, 1 = read',

    created_at DATETIME NOT NULL
        COMMENT 'Date and time when the notification was created',
    read_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when the user marked the notification as read',

    PRIMARY KEY (id),

    KEY idx_user_read (user_id, is_read),
    KEY idx_user_created (user_id, created_at),
    KEY idx_entity (entity_type, entity_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
```

------------------------------------------------------------------------

## 21. Notification Event Log

The event log shall persist synchronization information independently
from realtime delivery.

``` sql
CREATE TABLE notification_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Monotonically increasing event identifier used for ordering, deduplication, and recovery',
    user_id BIGINT UNSIGNED NOT NULL
        COMMENT 'ID of the user to whom this event belongs',
    notification_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Related notification ID; NULL for events not associated with one notification, such as read_all',
    event_type VARCHAR(50) NOT NULL
        COMMENT 'Logical event type, such as notification.created, notification.read, or reminder.dismissed',
    payload_json LONGTEXT NOT NULL
        COMMENT 'Immutable JSON event payload used for synchronization and recovery',
    created_at DATETIME NOT NULL
        COMMENT 'Date and time when the event was persisted',

    PRIMARY KEY (event_id),
    KEY idx_user_event (user_id, event_id),
    KEY idx_notification (notification_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
```

Because realtime signals are intentionally small, `payload_json` may
contain the immutable event/recovery information required by clients
rather than duplicating every business object.

------------------------------------------------------------------------

## 22. Reliable Internal Signal / Outbox

ZF1 business persistence shall not depend on Workerman or Node.js being
available at transaction time.

A durable outbox is recommended:

``` sql
CREATE TABLE notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique outbox work item identifier',
    event_id BIGINT UNSIGNED NOT NULL
        COMMENT 'Notification event that must be signaled to the configured realtime server',

    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        COMMENT 'Dispatch state, for example pending, processing, processed, or failed',
    attempts INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of realtime signal delivery attempts',

    available_at DATETIME NOT NULL
        COMMENT 'Earliest date and time at which the dispatcher may attempt this item',
    locked_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when a dispatcher claimed the item for processing',
    processed_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when realtime signal dispatch completed successfully',

    last_error TEXT DEFAULT NULL
        COMMENT 'Most recent dispatch error message for diagnostics and retry handling',
    created_at DATETIME NOT NULL
        COMMENT 'Date and time when the outbox item was created',

    PRIMARY KEY (id),
    UNIQUE KEY uk_event_id (event_id),
    KEY idx_dispatch (status, available_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
```

The transaction model shall be:

``` text
BEGIN

Business change
Create notification if required
Create notification event
Create outbox item

COMMIT
```

After commit, the dispatcher sends the lightweight internal signal.

------------------------------------------------------------------------

## 23. Internal Signal Delivery

The ZF1-side dispatcher shall communicate with Workerman or Node.js
through an internal authenticated channel.

Conceptually:

``` text
ZF1
 │
 │ internal signal
 ▼
Workerman / Node.js
 │
 │ WebSocket signal
 ▼
Client
 │
 │ authenticated HTTPS
 ▼
ZF1 API
```

An internal endpoint may be used:

``` http
POST /internal/events
```

Example payload:

``` json
{
    "user_id": 200,
    "event_id": 9005,
    "event": "notification.created",
    "notification_id": 1060
}
```

The internal signal must not be accepted from untrusted browser clients.

------------------------------------------------------------------------

## 24. Internal Signal Security

ZF1-to-realtime-server communication shall be authenticated.

HMAC-SHA256 may be used:

``` text
signature =
HMAC-SHA256(
    timestamp + "." + rawBody,
    secret
)
```

Example headers:

``` text
X-S3-Timestamp
X-S3-Signature
```

The receiver shall:

-   validate the signature;
-   reject stale timestamps;
-   reject malformed requests;
-   restrict access to the internal endpoint;
-   use HTTPS/TLS where appropriate.

The HMAC secret shall never be exposed to JavaScript or the Windows
client.

------------------------------------------------------------------------

## 25. Notification Delivery Semantics

Realtime signaling shall use an **at-least-once** model.

A signal may occasionally be delivered more than once, for example if:

1.  Workerman receives the signal;
2.  the dispatcher fails before marking the outbox row processed;
3.  the dispatcher retries.

Clients shall therefore deduplicate using `event_id`.

Exactly-once network delivery is not required.

------------------------------------------------------------------------

## 26. ZF1 Notification APIs

Suggested endpoints:

``` text
GET  /api/notifications
GET  /api/notifications/{id}
GET  /api/notifications/unread-count

POST /api/notifications/{id}/read
POST /api/notifications/read-all

GET  /api/notification-events?after={eventId}

GET  /api/realtime/config
POST /api/realtime/token
```

The authenticated user's identity shall be obtained from server
authentication state, not from a client-provided `user_id`.

------------------------------------------------------------------------

## 27. Realtime Connection Authentication

WebSocket clients shall be authenticated before receiving user-specific
signals.

Recommended browser flow:

``` text
Authenticated ZF1 session
        │
        ▼
POST /api/realtime/token
        │
        ▼
Short-lived realtime token
        │
        ▼
Open WebSocket
        │
        ▼
Send authentication message
        │
        ▼
Realtime server verifies token
        │
        ▼
Bind socket to authenticated user
```

The realtime server shall never trust a plain `user_id` supplied by the
client.

A reconnect should obtain a fresh realtime token rather than relying
indefinitely on an old token.

------------------------------------------------------------------------

## 28. Workerman Requirements

Workerman shall run as a separate long-running PHP CLI process.

Correct:

``` text
PHP CLI
   │
   ▼
Workerman
   │
WebSocket
```

Incorrect:

``` text
Apache
  │
ZF1 Controller
  │
infinite WebSocket loop
```

A compatible Workerman version shall be selected for PHP 7.4.

On CentOS, the Workerman process should be supervised by `systemd` or an
equivalent service manager so it can restart after failure.

------------------------------------------------------------------------

## 29. Node.js Requirements

The Node.js implementation shall follow the same signal-only model as
Workerman.

The browser and Windows application shall receive the same logical
WebSocket messages regardless of whether the server is implemented with
Node.js or Workerman.

------------------------------------------------------------------------

## 30. Multiple Connections Per User

The realtime server shall support multiple active connections for one
user.

Example:

``` text
User 200
   │
   ├── Browser leader
   ├── Windows desktop
   └── Browser on another computer
```

Conceptually:

``` text
user_id -> set of active connections
```

The server shall send a relevant signal to all currently connected
endpoints for the authenticated user.

------------------------------------------------------------------------

## 31. Windows Desktop Application

The Windows desktop application shall use the same server configuration
and logical transport model.

Conceptual interface:

``` text
INotificationTransport
       │
  ┌────┴────┐
  ▼         ▼
Polling   WebSocket
```

The desktop application shall:

-   authenticate through supported application APIs;
-   receive WebSocket signals where available;
-   fall back to polling where configured;
-   retrieve actual data from authenticated ZF1 APIs;
-   display Windows notifications/toasts;
-   maintain recent notifications;
-   support unread state;
-   open related ZF1 resources;
-   support reminder actions;
-   reconnect automatically.

It shall never connect directly to MariaDB.

------------------------------------------------------------------------

## 32. Reminder System

A reminder is a scheduled business object.

A notification is the mechanism used to alert the user when a reminder
becomes due.

``` text
Reminder
    │
time becomes due
    │
    ▼
Reminder Worker
    │
    ▼
Notification / Event
    │
    ▼
Realtime Signal or Polling
    │
    ▼
Client
    │
    ▼
Authenticated ZF1 API
```

The reminder system shall remain functional even when no browser or
Windows application is running.

------------------------------------------------------------------------

## 33. Reminder Data

The reminder definition shall include at least:

-   ID;
-   user ID;
-   title;
-   message/description;
-   reminder date/time;
-   related entity type;
-   related entity ID;
-   action URL;
-   status;
-   recurrence configuration;
-   creation/update timestamps;
-   completion timestamp;
-   cancellation timestamp.

Initial reminder statuses:

-   `pending`
-   `triggered`
-   `completed`
-   `cancelled`

------------------------------------------------------------------------

## 34. Reminder Occurrences

Recurring reminders shall distinguish the reminder definition from
individual occurrences.

Suggested structure:

``` sql
CREATE TABLE reminder_occurrences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique identifier of this reminder occurrence',
    reminder_id BIGINT UNSIGNED NOT NULL
        COMMENT 'ID of the parent reminder definition',

    scheduled_at DATETIME NOT NULL
        COMMENT 'Original scheduled date and time for this occurrence',
    triggered_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when this occurrence was triggered',
    dismissed_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when the user dismissed this occurrence without completing it',
    completed_at DATETIME DEFAULT NULL
        COMMENT 'Date and time when this occurrence was marked complete',
    snoozed_until DATETIME DEFAULT NULL
        COMMENT 'New date and time at which a snoozed occurrence should alert the user again',

    PRIMARY KEY (id),
    KEY idx_reminder (reminder_id),
    KEY idx_scheduled (scheduled_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
```

This prevents dismissing one occurrence from accidentally cancelling
future recurring occurrences.

------------------------------------------------------------------------

## 35. Reminder Actions

### Open

Open the related business resource.

### Snooze

Temporarily hide the current occurrence and schedule it again.

Suggested choices:

-   5 minutes
-   15 minutes
-   30 minutes
-   1 hour
-   tomorrow
-   custom time

### Dismiss

Acknowledge and hide the current occurrence.

Dismiss shall normally apply only to that occurrence.

It shall not mean that the underlying work was completed.

### Complete

Indicate that the reminder or associated work has been completed.

### Cancel

Stop the reminder definition entirely.

Summary:

``` text
Dismiss  = stop showing this occurrence
Snooze   = show this occurrence again later
Complete = underlying reminder/work is finished
Cancel   = stop the reminder itself
```

------------------------------------------------------------------------

## 36. Reminder APIs

Suggested endpoints:

``` text
GET    /api/reminders
POST   /api/reminders
PUT    /api/reminders/{id}

POST   /api/reminders/{id}/snooze
POST   /api/reminders/{id}/dismiss
POST   /api/reminders/{id}/complete
POST   /api/reminders/{id}/cancel
```

For recurring reminders, occurrence identifiers should be included where
an action applies to one specific occurrence.

All operations shall validate authenticated-user authorization.

------------------------------------------------------------------------

## 37. Reminder Synchronization

Reminder state changes shall synchronize between website and desktop
clients.

Example:

``` text
Website                         Desktop
   │                               │
   │ Reminder occurrence           │
   │                               │
   │                           Dismiss
   │                               │
   │                               ▼
   │                              ZF1
   │                               │
   │                   reminder.dismissed event
   │                               │
   │                    realtime lightweight signal
   ◄───────────────────────────────┘
   │
   ▼
fetch authoritative state from ZF1
```

The same principle applies to:

-   snooze;
-   complete;
-   cancel;
-   update.

------------------------------------------------------------------------

## 38. Reminder Worker

The server shall determine when reminders are due.

The browser and Windows application shall not be authoritative reminder
timers.

The worker shall process due reminders using concurrency-safe
claiming/locking so that two workers cannot trigger the same occurrence
twice.

When an occurrence becomes due, the worker shall:

1.  mark/claim the occurrence;
2.  create the required notification/event state;
3.  create an outbox item if realtime signaling is required;
4.  commit the transaction;
5.  allow the dispatcher to signal connected clients.

------------------------------------------------------------------------

## 39. Recurring Reminders

The architecture shall allow recurring reminders such as:

-   daily;
-   weekly;
-   monthly;
-   every three months;
-   yearly.

A future implementation may use a simplified recurrence model or an
iCalendar-style recurrence rule.

Each occurrence shall have independent state.

------------------------------------------------------------------------

## 40. Time Zones

Scheduled instants should be stored consistently, preferably in UTC.

Recurring reminders representing local wall-clock time shall also retain
their recurrence timezone.

Example:

``` text
Every weekday at 09:00
Timezone: Asia/Tokyo
```

This must remain 09:00 local time across applicable timezone/DST
changes.

------------------------------------------------------------------------

## 41. Read State and Cross-Client Synchronization

Notification read state belongs to the authenticated user's notification
record, not to an individual browser tab or desktop instance.

If a notification is marked read on:

-   one browser tab;
-   another browser/computer; or
-   the Windows application,

the other active clients shall eventually synchronize the same state.

Realtime signals may accelerate this synchronization, but ZF1 remains
authoritative.

------------------------------------------------------------------------

## 42. Security Requirements

The implementation shall:

-   never trust client-supplied `user_id`;
-   authenticate all protected APIs;
-   verify notification/reminder ownership;
-   use CSRF protection for state-changing cookie/session-authenticated
    browser requests;
-   use `wss://` for production WebSocket connections;
-   authenticate every WebSocket client;
-   use short-lived realtime connection credentials;
-   authenticate ZF1-to-realtime-server internal signals;
-   protect HMAC/signing secrets;
-   validate configured realtime destinations;
-   never expose server secrets to JavaScript;
-   never allow desktop applications to connect directly to MariaDB;
-   validate or control notification action URLs;
-   safely render notification text.

Untrusted text shall be rendered using mechanisms such as:

``` javascript
element.textContent = notification.message;
```

rather than directly assigning untrusted data to `innerHTML`.

------------------------------------------------------------------------

## 43. Reliability Model

The architecture shall follow these principles:

``` text
MariaDB + ZF1
    =
authoritative state

Workerman / Node.js
    =
fast lightweight signaling

REST API
    =
authoritative data retrieval

Event log
    =
synchronization and recovery

Outbox
    =
reliable internal signal retry

event_id
    =
ordering and deduplication
```

A realtime-server outage shall degrade notification speed, not data
correctness.

------------------------------------------------------------------------

## 44. Example End-to-End Notification Flow

``` text
1. ZF1 business action
        │
        ▼
2. S3_NotificationService
        │
        ├── create/update notification
        ├── create event #9005
        └── create outbox item
        │
        ▼
3. COMMIT
        │
        ▼
4. Dispatcher
        │
        │ internal authenticated signal
        ▼
5. Workerman / Node.js
        │
        │ lightweight WebSocket signal
        ▼
6. Browser leader / Windows client
        │
        ├── Browser leader broadcasts to follower tabs
        │
        ▼
7. Authenticated ZF1 API request
        │
        ▼
8. ZF1 authorization
        │
        ▼
9. Authoritative notification data
        │
        ▼
10. Display / synchronize UI
```

------------------------------------------------------------------------

## 45. Example Workerman Failure Flow

``` text
ZF1 business action
       │
       ▼
MariaDB transaction succeeds
       │
       ├── notification
       ├── event
       └── pending outbox
              │
              ▼
          Workerman
              X
            unavailable
              │
      ┌───────┴────────┐
      ▼                ▼
Outbox retry      Client polling
      │                │
      └───────┬────────┘
              ▼
          ZF1 REST API
              │
              ▼
     Authoritative recovery
```

When Workerman returns, WebSocket signaling resumes. No notification
business data needs to be reconstructed from Workerman.

------------------------------------------------------------------------

## 46. Final Component Responsibilities

### MariaDB

Stores durable business and synchronization state.

### ZF1

Owns business logic, authentication, authorization, APIs, notifications,
reminders, and authoritative data.

### Dispatcher

Reads committed outbox work and sends trusted lightweight signals to the
configured realtime server.

### Workerman

Provides PHP-based WebSocket signaling for Group B.

### Node.js

Provides Node.js-based WebSocket signaling for Group A.

### Polling Transport

Provides notification discovery/recovery without a dedicated realtime
server and acts as fallback where configured.

### Browser

Receives signals, coordinates tabs, retrieves authoritative data from
ZF1, and presents notification/reminder UI.

### Windows Application

Receives signals or polls, retrieves authoritative data from ZF1, and
presents desktop notification/reminder UI.

------------------------------------------------------------------------

## 47. Core Architectural Rules

1.  **There is one notification and reminder system with multiple
    delivery transports.**
2.  **ZF1 and MariaDB remain the source of truth.**
3.  **Workerman and Node.js primarily send lightweight realtime
    signals.**
4.  **Clients retrieve actual protected business data through
    authenticated ZF1 APIs.**
5.  **Realtime-server availability must not determine whether
    notification data is saved.**
6.  **REST synchronization provides recovery when realtime signaling is
    unavailable.**
7.  **BroadcastChannel coordinates multiple browser tabs.**
8.  **Workerman runs as a separate PHP CLI service, not inside
    ZF1/Apache request processing.**
9.  **Redis is not required and is excluded from the current
    architecture.**
10. **The same browser and Windows client architecture shall support
    Groups A, B, and C.**
