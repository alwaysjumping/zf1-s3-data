# Messenger System — Complete Technical Reference
## Standalone PHP 7.4, MariaDB, Workerman, ES5, jQuery, Multi-Tab Election, and Adaptive Synchronization

**Document version:** 1.0 — 8 October 2026  
**Scope:** Consolidates our design discussions and the latest standalone research implementation, including the original ZF1 goals, configurable realtime modes, multi-tab coordination, and reduced REST traffic.  
**Source baseline:** `Messenger_PHP74_Adaptive_REST_Sync.zip`  
**Status:** Working prototype reported by the user for earlier versions; the latest adaptive-sync release requires live end-to-end verification. **Not production certified.**

---

## 1. Executive summary

The Messenger module provides private and group conversations for an enterprise intranet. The original application uses Zend Framework 1 (ZF1), PHP, MariaDB and a legacy browser interface. To make the implementation easier to study and test, a standalone version was created using **pure PHP 7.4**, **MariaDB/InnoDB**, **HTML/CSS**, **ES5 JavaScript**, and **locally hosted jQuery**.

The standalone project can operate in two modes:

1. **Polling mode:** no Workerman dependency. The browser discovers new activity through periodic REST API requests.
2. **Workerman mode:** a WebSocket server sends lightweight change hints; browsers fetch authoritative content through REST. REST also serves as a recovery mechanism.

A single elected tab coordinates background activity within a same-user, same-company browser session. The newest design uses **adaptive REST synchronization**: it avoids rapid polling while Workerman is healthy, and slows polling when users are idle or tabs are hidden.

**Architectural principle:** MariaDB and authorized REST APIs remain the source of truth. WebSocket and cross-tab notifications are *hints*, not the message store.

### 1.1 Goals

- Preserve core Messenger functionality in either realtime mode.
- Operate on an isolated intranet, without public CDNs or Redis.
- Support legacy browsers including Chrome 49 and Firefox 52, subject to browser testing.
- Use PHP 7.4-compatible source and ES5-compatible JavaScript.
- Reduce duplicate server requests across tabs and during inactivity.
- Keep company boundaries and conversation membership enforced on the server.
- Make source code inspectable and runnable in a small local environment.

### 1.2 Non-goals of the current standalone prototype

- Complete feature parity with the larger ZF1 skeleton.
- Full DHTMLX 3.5 interface integration.
- Production-grade high availability or horizontal WebSocket scaling.
- Guaranteed exactly-once WebSocket event delivery.
- Preventing browser users from inspecting code or downloaded data.

---

## 2. Development history and major decisions

| Stage | Decision or outcome |
|---|---|
| Original Messenger | ZF1, PHP 7.4, MariaDB, DHTMLX 3.5 and jQuery |
| Realtime architecture | REST polling, Workerman WebSocket, or Node.js WebSocket according to deployment capability |
| Standalone study project | Pure PHP + MariaDB + HTML/CSS + ES5 + local jQuery |
| WebSocket gateway | Workerman 4.1.x selected for standalone project |
| Multiple tabs | `localStorage` lease and `storage` events, avoiding reliance on BroadcastChannel |
| Gateway auth | Short-lived, one-time, DB-backed WebSocket ticket |
| Configurable mode | `realtime_mode` chooses `polling` or `workerman` |
| Performance refinement | Adaptive polling and leader-only routine background discovery |
| Data integrity | REST reconciliation is authoritative; WebSocket messages are lightweight hints |

The broader ZF1 project was built incrementally through foundation, authentication, conversations, membership, messages, groups, attachments, outbox, WebSocket gateways, frontend and synchronization phases. The standalone application is intentionally simpler.

---

## 3. Technology and deployment requirements

| Component | Choice |
|---|---|
| Application language | PHP 7.4 |
| Database | MariaDB / InnoDB |
| Frontend | HTML, CSS, ES5 JavaScript, jQuery |
| Web server | Apache or PHP built-in server for development |
| Optional realtime service | Workerman 4.1.x |
| Realtime transport | WebSocket for hints, REST for authoritative data |
| Browser coordination | `localStorage` and `storage` event |
| File safety | Quarantine and ClamAV scanning |
| External dependencies | No CDN, Redis, or Node.js required by standalone app |
| Recommended test environment | Windows/XAMPP for PHP site; Linux/WSL2 for Workerman |

**PHP 7.4 is end-of-life.** Use this stack only where compatibility requires it, isolate and harden the deployment, and plan a supported PHP migration.

Workerman is optional in polling mode and is **not bundled** in the source ZIP. The gateway's Composer dependencies must be installed or transferred from a connected build machine when Workerman mode is selected.

---

## 4. High-level architecture

```text
                        +------------------------+
                        |    Browser tabs        |
                        |  A / B / C (same user) |
                        +-----------+------------+
                                    |
                      localStorage election + hints
                                    |
                         +----------+----------+
                         | One background leader|
                         +----------+----------+
                                    |
                    +---------------+----------------+
                    |                                |
              polling mode                     Workerman mode
                    |                                |
          REST discovery requests              WebSocket hints
                    |                                |
                    |                      +---------v---------+
                    |                      | Workerman gateway |
                    |                      +---------+---------+
                    |                                |
                    |                        MariaDB outbox
                    |                                |
                    +----------------+---------------+
                                     |
                              REST APIs (PHP)
                                     |
                          +----------v----------+
                          | MariaDB / InnoDB    |
                          | messages, members,  |
                          | conversations, etc. |
                          +---------------------+
```

The client sends and edits messages through REST in **both** modes. The server writes database records and durable outbox entries transactionally. In Workerman mode the gateway reads outbox events and notifies interested WebSocket clients. A notified browser fetches permitted content through REST. In polling mode the elected browser tab periodically checks for changes.

### 4.1 Why use hints rather than pushing complete messages?

- The REST endpoint consistently enforces current membership and visibility rules.
- It reduces sensitive payload exposure in the gateway.
- Reconnection and duplicate events can be handled through idempotent reconciliation.
- The same client rendering path works with and without Workerman.

---

## 5. Project structure

```text
app/
  bootstrap.php                 Authentication, database and helpers
  config.example.php            Copy to config.php
database/
  schema.sql                    Core MariaDB tables
  upgrade_workerman.sql         WebSocket gateway tickets
  upgrade_multitab.sql          Session-based gateway fencing
public/
  index.php                     Login and Messenger UI
  router.php                    Built-in development server router
  api/index.php                 REST endpoints
  assets/app.js                 ES5 UI, realtime and adaptive sync
  assets/app.css
  assets/jquery.min.js          Locally hosted dependency
scripts/
  seed.php                      Demo data
  scan.php                      Quarantine scanner
workerman/
  server.php                    WebSocket gateway
  README.md                     Installation and operation
tests/
  static_smoke.php
  config_mode_smoke.php
README.md
FEATURE_MATRIX.md
REALTIME_MODES.md
MULTITAB_LEADER_ELECTION.md
ADAPTIVE_SYNC.md
TEST_GUIDE.md
START_WINDOWS_XAMPP.md
```

The `app/`, `database/`, `scripts/`, `storage/` and `workerman/` directories should not be exposed as web-accessible files. Point the web server document root at `public/`.

---

## 6. Configuration and operating modes

Copy `app/config.example.php` to `app/config.php` and adjust it for your environment.

```php
<?php
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=messenger_demo;charset=utf8mb4',
    'user' => 'root',
    'password' => '',
    'allow_demo_seed' => false,
    'max_upload_bytes' => 20971520,
    'require_scan' => true,
    'clamav_binary' => 'clamscan',

    'realtime_mode' => 'polling',   // 'polling' or 'workerman'
    'poll_interval_ms' => 5000,

    'workerman_ws_url' => 'ws://127.0.0.1:2346',
    'workerman_bind' => 'websocket://127.0.0.1:2346',
    'workerman_poll_seconds' => 1
];
```

**These are example development values, not a secure production configuration.** Use restricted database credentials and `wss://` behind HTTPS for remote users.

### 6.1 Mode A: polling without Workerman

```php
'realtime_mode' => 'polling',
'poll_interval_ms' => 5000,
```

- No Workerman installation or running process is required.
- No WebSocket ticket is requested.
- Background change discovery uses REST.
- Multi-tab coordination limits routine polling to one elected tab where browser storage is available.
- Other tabs fetch authorized content when signaled or when they regain focus.
- New messages may appear after the polling interval, not instantaneously.

For a fresh polling-only installation, the core `database/schema.sql` is sufficient.

### 6.2 Mode B: Workerman

```php
'realtime_mode' => 'workerman',
'poll_interval_ms' => 5000,
'workerman_ws_url' => 'ws://127.0.0.1:2346',
'workerman_bind' => 'websocket://127.0.0.1:2346',
```

- Install Workerman 4.1.x, including its autoloader.
- Apply `database/upgrade_workerman.sql`, then `database/upgrade_multitab.sql`.
- Start the gateway with `php workerman/server.php start`.
- One elected tab maintains the shared WebSocket connection for a same-user browser session.
- The gateway sends hints; clients use REST to load current data.
- If the socket disconnects, the leader resumes adaptive REST polling.
- A connected gateway still uses a low-frequency REST safety check.

**Changing mode:** edit `app/config.php` and reload **all** Messenger tabs. Already-open pages do not dynamically inherit configuration changes. Stop Workerman after switching to polling mode if no other application needs it.

### 6.3 Mode comparison

| Capability | Polling | Workerman |
|---|---|---|
| Send/edit/delete messages | REST | REST |
| Fetch history and attachments | REST | REST |
| Discover incoming changes | Scheduled REST | WebSocket hints |
| Normal idle traffic | Adaptive REST | Near-zero REST, plus safety checks |
| WebSocket server required | No | Yes |
| Multi-tab coordination | Background polling leader | WebSocket leader |
| Recovery | Next REST poll | REST fallback + reconnect |
| Data store | MariaDB | MariaDB |

---

## 7. REST API reference

All actions are exposed under `public/api/index.php?action=ACTION`. Most successful JSON responses follow the `{ "ok": true, "data": ... }` convention. Verify exact request fields and authorization rules against `public/api/index.php`.

| Action | Typical method | Purpose |
|---|---|---|
| `login` | POST | Start authenticated session |
| `logout` | POST | End session |
| `csrf` | GET | Obtain CSRF information |
| `me` | GET | Current user |
| `users` | GET | Company-scoped directory |
| `conversations` | GET | Conversation list |
| `create` | POST | Create or reuse private/group conversation |
| `members` | GET | List members |
| `group` | POST | Group operations |
| `messages` | GET | Message history and incremental retrieval |
| `send` | POST | Send a message |
| `edit` | POST | Edit message |
| `delete` | POST | Soft-delete message |
| `reactions` | POST | Add/remove reaction |
| `read` | POST | Update read cursor |
| `typing` | POST | Typing state |
| `search` | GET | Search permitted messages |
| `sync` | GET | Synchronization snapshot |
| `upload` | POST multipart | Upload to quarantine |
| `files` | GET | List attachments |
| `download` | GET | Authorized file download |
| `ws-ticket` | POST | Obtain one-time WebSocket ticket in Workerman mode |

Mutating requests use session authentication and CSRF protection. Do not expose `ws-ticket` in polling mode.

### 7.1 Message flow

1. User submits text and a client-generated UUID.
2. REST endpoint validates session, company and conversation membership.
3. Server writes message with a conversation sequence number.
4. Server records an outbox event in the database transaction.
5. Response returns to sender.
6. Workerman emits a hint, **or** polling discovers the changed state.
7. Client fetches authorized messages and updates the interface.
8. Duplicated UUID requests should not create duplicate messages.

### 7.2 Incremental retrieval

Example:

```text
GET /api/index.php?action=messages&conversation_id=1&after=25
```

The sequence cursor lets the client retrieve later messages without reloading all history. It is not a substitute for server-side permission checks.

---

## 8. Data model and consistency

The core schema includes:

- `companies`: company scope.
- `users`: employee accounts and company membership.
- `conversations`: private/group metadata and message sequence.
- `members`: membership and visibility starting sequence.
- `private_pairs`: deduplicate private conversation pairs.
- `messages`: message content, sender, sequence and state.
- `message_revisions`: edit history.
- `reactions`: message reactions.
- `attachments`: upload metadata and scan state.
- `typing`: ephemeral typing information.
- `outbox`: durable change records.

Optional Workerman migration:

- `gateway_tickets`: hashed one-time tickets, expiry, consumption and, after the multi-tab upgrade, `session_hash`.

### 8.1 Transactional sequence allocation

A conversation-specific sequence is allocated under database locking to preserve message ordering within a conversation. The message and its outbox event are recorded together.

### 8.2 Outbox ordering limitation

Auto-increment outbox IDs do **not** guarantee transaction commit order. A gateway that simply reads `WHERE id > last_id` can miss a row committed late. Therefore, this prototype uses periodic REST reconciliation as a safety net. A production design should adopt a commit-safe event publication strategy and explicitly test concurrent transactions.

### 8.3 Visibility on rejoin

The standalone model tracks a member's `first_seq`. A member removed and later re-added should not automatically see messages from before the latest join period. The simplified model does not retain complete membership-period history.

---

## 9. Multi-tab leader election

### 9.1 Problem

Without coordination, three Messenger tabs can each maintain a WebSocket connection or issue the same background polling request. This increases server load and may duplicate UI work.

### 9.2 Approach

The browser stores a short-lived lease in `localStorage`, keyed by company and user. Each tab has its own identifier. The elected leader renews the lease; followers observe changes using the `storage` event.

**Current timing:**

| Setting | Value |
|---|---|
| Heartbeat | 2 seconds |
| Lease expiry | 7 seconds |
| Takeover jitter | 0–550 milliseconds |
| Leader takeover | Typically after expiry plus scheduling delay |

In Workerman mode the leader owns the socket. In polling mode the leader owns scheduled background discovery. Followers respond to shared hints by fetching only the content they need.

### 9.3 Election flow

```text
Tab opens
    |
Read shared lease
    |
    +-- Valid leader lease exists --> Become follower
    |
    +-- Missing/expired lease -----> Wait randomized delay
                                        |
                                     Recheck
                                        |
                                  Attempt leadership
                                        |
                              Renew lease every 2s
```

If a leader closes or stops heartbeating, another tab eventually takes over. When a tab becomes visible again, it reconciles with REST.

### 9.4 Why `localStorage` instead of BroadcastChannel?

The target browsers are old. `localStorage` and the `storage` event provide a more broadly compatible coordination mechanism for Chrome 49 and Firefox 52. This design should still be tested on those exact browser versions.

### 9.5 Split-brain and server fencing

`localStorage` is **not an atomic lock**. Two tabs may briefly believe they are leaders. In Workerman mode, the gateway uses the authenticated session hash to close an older connection when another socket authenticates for the same user/company/session. This limits duplicate sockets but is not a distributed consensus system.

Important limitations:

- Separate browser sessions may each have their own leader.
- If storage is disabled, multiple tabs may independently poll or connect.
- The provided gateway is single-worker; scaling to multiple workers needs shared coordination.
- Closing or freezing a tab can delay takeover.
- Shared-session logout propagation is not immediate in the current UI.
- Fencing does not guarantee exactly-once delivery.

---

## 10. Adaptive synchronization: why REST traffic was excessive

The earlier implementation checked for updates every five seconds, even when users did nothing. With multiple tabs, repeated conversation, message and typing requests could consume unnecessary PHP workers and MariaDB resources.

The improvement is to distinguish **change discovery** from **content retrieval**:

- One leader performs routine change discovery.
- Other tabs refresh relevant content only after receiving a hint or resuming.
- A healthy Workerman connection replaces frequent REST discovery.
- Background checks slow down as user activity decreases.
- A low-frequency reconciliation remains for missed events.

### 10.1 Current scheduling policy

| Condition | Intended synchronization interval |
|---|---|
| Active, visible polling leader | `poll_interval_ms` (default 5 seconds) |
| Visible but idle for 30 seconds | 15–30 seconds |
| Hidden tab | 60 seconds |
| Healthy Workerman leader | 300-second REST safety reconciliation |
| Tab focus / visibility resume | Immediate reconciliation |
| Workerman disconnected | Adaptive REST fallback |

Intervals are approximate and subject to browser timer throttling and network latency.

### 10.2 Workerman mode: expected behavior

```text
WebSocket healthy
    |
    +-- No new events --> No 5-second REST discovery
    |
    +-- WebSocket hint --> Fetch changed conversation/messages
    |
    +-- Tab resumes ---> REST reconcile
    |
    +-- 5 minutes -----> Safety REST reconcile
    |
    +-- Socket closes -> Adaptive REST polling + reconnect attempts
```

The leader relays hints to followers through `localStorage`. Each follower independently uses authenticated REST requests for data it is authorized to see.

### 10.3 Polling mode: expected behavior

```text
Leader tab: REST conversation discovery on adaptive timer
       |
       +-- No changes --> Schedule next check
       |
       +-- Changes ----> Publish cross-tab signal
                              |
                        Follower tabs
                              |
                      Fetch relevant content
```

Polling cannot provide instant notification when no request is in flight. A low-frequency polling strategy trades notification latency for reduced server traffic.

### 10.4 Example traffic estimate

Suppose three tabs previously made **three REST calls every five seconds**. Over ten minutes:

- Prior behavior: `3 tabs × 3 calls × 120 cycles = 1,080 requests`.
- Hypothetical single-leader design with one call every ten seconds: `60 requests`.
- Healthy Workerman with no activity-triggered calls: only occasional safety reconciliation.

**These are illustrative assumptions, not measurements of the current source.** Actual requests vary with selected conversations, popups, typing indicators, user interactions, reconnects and follower refreshes.

### 10.5 Remaining sources of REST requests

Even in optimized Workerman mode, REST is still expected for:

- Initial page loading and authentication.
- Opening or switching conversations.
- Sending/editing/deleting messages.
- Loading history, members and attachments.
- Reactions and read markers.
- New WebSocket hints.
- Tab resume or leader changes.
- Reconnection and safety checks.
- Fallback while Workerman is unavailable.

Thus, **zero REST traffic at all times is not a goal**. Eliminating unnecessary *routine idle polling* is the goal.

---

## 11. Workerman gateway

### 11.1 Authentication handshake

1. Browser obtains a one-time ticket through an authenticated, CSRF-protected REST request.
2. Server generates a random ticket and stores its SHA-256 hash with a short expiry.
3. Browser connects to the configured WebSocket URL.
4. Browser sends the ticket in the first WebSocket frame.
5. Gateway validates and consumes the ticket against MariaDB.
6. Gateway associates the socket with user, company and session.
7. Gateway sends a ready acknowledgement.
8. The client begins receiving hints.

Ticket TTL in the reference design is approximately **30 seconds**. The raw ticket should not be logged or placed in a URL.

### 11.2 Gateway notifications

The gateway sends small messages such as:

```json
{"type":"hint","conversation_id":"123"}
```

It does not send full message bodies through the gateway. A client must validate access again when retrieving data via REST.

### 11.3 Gateway failure and recovery

| Failure | Expected handling |
|---|---|
| Workerman not started | Client uses REST fallback |
| Socket disconnects | Leader schedules reconnect and resumes polling |
| Ticket expires | Client requests a fresh ticket |
| Leader tab closes | Follower takes over after lease expiration |
| Hint missed | Safety REST reconciliation catches up |
| Database unavailable | REST and gateway operations may fail; log and retry safely |
| Internal outbox scanning delayed | Delivery is delayed; REST remains authoritative |

The gateway reads MariaDB outbox entries periodically, currently configured at one second. Avoid making that scan expensive at enterprise scale.

---

## 12. Messenger features

### 12.1 Conversations

- Private one-to-one conversations.
- Duplicate private-pair prevention.
- Group conversations with a 100-member cap.
- Add/remove members and rename groups.
- Company-scoped membership checks.

### 12.2 Messages

- Send and retrieve message history.
- Client UUID-based send deduplication.
- Edit and soft-delete.
- Message revision records.
- Reactions.
- Read cursors.
- Typing indicators.
- Basic text search via SQL `LIKE`.

### 12.3 User interface

- Conversation list and message area.
- Local ES5/jQuery assets.
- Up to three quick-chat popups.
- Group management controls.
- File list and download actions.

### 12.4 Attachments

1. Upload is stored in a quarantine directory.
2. Server records metadata and SHA-256.
3. ClamAV scanner examines the file.
4. Approved files become eligible for authorized linking and download.
5. Rejected or unscanned files must not be downloadable.

The standalone UI does **not** yet provide a completely seamless approved-file attachment workflow; some steps require explicit API interaction after scanning.

---

## 13. Security requirements

### 13.1 Authentication and authorization

- Use server-side sessions for the standalone prototype.
- Validate CSRF on state-changing REST operations.
- Validate active account status.
- Enforce company isolation on every relevant API.
- Enforce membership and message visibility on every read/write.
- Do not trust conversation IDs or company IDs supplied by the browser.
- Validate file ownership and scan status before download.
- Rate-limit authentication, upload and message endpoints.

### 13.2 WebSocket security

- Use **WSS** for remote users.
- Use short-lived, single-use tickets.
- Avoid raw ticket logging.
- Revalidate membership when routing events.
- Enforce session revocation on the server for production.
- Consider stronger fencing and shared gateway state before multi-worker scaling.

### 13.3 Offline/intranet considerations

An intranet is not automatically secure. Keep dependencies local, restrict service ports, use TLS where possible, segment services, review logs, and use least-privilege database accounts.

### 13.4 Browser limitations

JavaScript cannot reliably prevent browser inspection, copying already-delivered content, or all downloads. Confidential content requires authorization and controlled server delivery, not reliance on disabled browser UI.

---

## 14. Installation and startup

### 14.1 Fresh polling-mode installation

1. Install PHP 7.4 and MariaDB.
2. Create the database and import `database/schema.sql`.
3. Copy `app/config.example.php` to `app/config.php`.
4. Set MariaDB credentials and `'realtime_mode' => 'polling'`.
5. For a **local demo only**, temporarily enable `allow_demo_seed`.
6. Run:

```bash
php scripts/seed.php
php -S 127.0.0.1:8080 -t public public/router.php
```

7. Open `http://127.0.0.1:8080/`.
8. Turn demo seeding back off after setup.

Demo accounts: `alice`, `bob`, `carol`, `dave`; demo password: `demo1234`. Dave belongs to a separate company. **Never use these credentials in production.**

### 14.2 Enable Workerman later

1. Install Workerman 4.1.x using Composer or transfer a prepared `vendor/` directory offline.
2. Apply SQL migrations **in order**:

```text
database/upgrade_workerman.sql
database/upgrade_multitab.sql
```

3. Set `'realtime_mode' => 'workerman'`.
4. Set the WebSocket URL to a browser-reachable address; `127.0.0.1` is only correct when the browser runs on the same machine.
5. Start the gateway:

```bash
php workerman/server.php start
```

6. Reload all browser tabs and test socket election, messaging and fallback.

**Migration note:** `upgrade_multitab.sql` uses an `ALTER TABLE` statement; apply it once. Do not rerun it blindly.

### 14.3 Antivirus scanning

Set `clamav_binary` to the correct local scanner path, then run:

```bash
php scripts/scan.php
```

In a real deployment, automate scanning with a restricted scheduled worker and monitor failures. Quarantined files must remain inaccessible until approved.

---

## 15. Verification and acceptance test plan

### 15.1 Basic functionality

- Log in as Alice and Bob in different browser sessions.
- Create a private conversation; ensure duplicate creation reuses it.
- Send, edit, delete and react to messages.
- Create a group with Alice, Bob and Carol.
- Confirm Dave cannot access the other company's conversation.
- Remove and re-add a member; test message visibility.
- Upload, scan, link and download an attachment.
- Test three quick-chat popups and fourth-popup limit.

### 15.2 Polling mode

- Set mode to `polling` and stop Workerman.
- Open three tabs for the same account.
- Confirm no WebSocket connections or ticket requests occur.
- Observe routine REST discovery from the elected tab.
- Leave tabs idle; verify longer polling intervals.
- Hide tabs; verify reduced background activity.
- Close the leader; verify follower takeover.
- Send a message from Bob; verify eventual delivery in Alice's tabs.

### 15.3 Workerman mode

- Apply both migrations and start Workerman.
- Open three tabs in one authenticated browser session.
- Confirm only one active leader WebSocket.
- Verify no five-second REST discovery loop while connected.
- Send a message from another user; verify hint-triggered refresh.
- Close leader tab; verify takeover.
- Stop Workerman; verify REST fallback.
- Restart Workerman; verify reconnect and reconciliation.
- Test sleep/wake, offline/online and expired tickets.

### 15.4 Security and failure tests

- Invalid CSRF token is rejected.
- Disabled account cannot use protected APIs.
- Cross-company access is denied.
- Unapproved attachments cannot be downloaded.
- Concurrent sends preserve conversation ordering.
- Duplicate UUIDs do not duplicate messages.
- Two tabs racing for leadership do not permanently duplicate sockets.
- Workerman outages do not lose stored messages.

### 15.5 Browser and load testing

Test on actual Chrome 49 and Firefox 52 installations if compatibility is mandatory. Simulate many idle tabs and users; measure REST requests/minute, PHP worker utilization, MariaDB queries, WebSocket connections, reconnect rates and time to deliver a message.

**Validation boundary:** Static syntax and smoke checks were reported for earlier generated releases, but they do not prove production behavior. This document does not claim that all scenarios above have been executed.

---

## 16. Known limitations and technical debt

1. The standalone app is a research/test implementation, not a drop-in replacement for the ZF1 Messenger.
2. The latest adaptive design needs live MariaDB, Workerman and multi-browser testing.
3. Outbox ID scanning is not commit-order safe.
4. `localStorage` leader election is best-effort, not atomic.
5. Workerman gateway is single-worker; scaling requires shared coordination.
6. Session logout is not instantly broadcast to every tab in the current UI.
7. WebSocket hint delivery is not exactly-once.
8. Full read-receipt UI is not present.
9. Search is basic SQL `LIKE`.
10. Attachment approval/linking UI is incomplete.
11. The simplified membership model does not preserve complete join/leave history.
12. No full Node.js gateway or DHTMLX integration in the standalone version.
13. PHP 7.4 and old browsers have security and maintenance risks.
14. Existing `README.md`, `REALTIME_MODES.md`, `MULTITAB_LEADER_ELECTION.md` and `FEATURE_MATRIX.md` contain some descriptions inherited from earlier versions (such as independent polling or missing leader election). **Use the latest implementation and `ADAPTIVE_SYNC.md` as the baseline; update those older descriptions before external publication.**

---

## 17. Recommended next improvements

**Priority 1 — correctness and observability**

- Add a developer diagnostics panel showing realtime mode, leader/follower status, WebSocket state, last successful REST sync and current poll interval.
- Add integration tests covering leader failover, simultaneous tabs, lost hints and WebSocket reconnect.
- Fix outbox publication to avoid commit-order gaps.
- Make logout/session revocation propagate promptly across tabs and gateway connections.

**Priority 2 — performance**

- Return compact conversation change tokens or sequence cursors instead of fetching full lists when nothing changed.
- Add appropriate MariaDB indexes and verify `EXPLAIN` plans for conversations, messages, membership and outbox.
- Add jitter/backoff to retries and rate-limit noisy clients.
- Measure polling load and tune active/idle/hidden intervals from real usage.

**Priority 3 — enterprise integration**

- Integrate with the existing ZF1 session/employee/company authorization model.
- Adapt the UI to DHTMLX 3.5 where required.
- Build a robust background antivirus workflow.
- Add complete read receipts and membership-period history.
- Plan migration from PHP 7.4 and unsupported browser versions.

---

## 18. Glossary

| Term | Meaning |
|---|---|
| REST polling | Browser periodically requests server state |
| WebSocket | Long-lived, bidirectional browser/server connection |
| Workerman | PHP process framework used here as a WebSocket gateway |
| Leader tab | Tab responsible for shared background discovery or socket |
| Follower tab | Tab that listens for shared hints and fetches its own data |
| Lease | Expiring `localStorage` record indicating current leader |
| Heartbeat | Periodic lease renewal |
| Fencing | Server mechanism that supersedes duplicate gateway connections |
| Outbox | Durable database records describing committed changes |
| Reconciliation | REST fetch that aligns UI with authoritative database state |
| Hint | Lightweight signal indicating that content may have changed |
| CSRF | Cross-site request forgery protection |
| Idempotency | Repeating a request does not repeat its effect |
| Quarantine | Holding area for uploads awaiting malware scanning |

---

## 19. References in the project package

- `README.md` — installation and feature overview.
- `REALTIME_MODES.md` — mode selection and migration.
- `MULTITAB_LEADER_ELECTION.md` — browser lease and gateway fencing.
- `ADAPTIVE_SYNC.md` — event-driven and adaptive synchronization.
- `FEATURE_MATRIX.md` — standalone vs ZF1 feature differences (contains some outdated entries).
- `TEST_GUIDE.md` — manual functional and security tests.
- `START_WINDOWS_XAMPP.md` — local Windows setup.
- `workerman/README.md` — gateway installation and operating notes.
- `database/schema.sql`, `database/upgrade_workerman.sql`, `database/upgrade_multitab.sql` — authoritative schema and migrations.
- `public/assets/app.js`, `public/api/index.php`, `workerman/server.php` — implementation source.

---

## 20. Final architecture decision

**Keep a single configurable Messenger codebase with two realtime modes.** Use **Workerman + event-driven REST retrieval** where a gateway is available. Use **adaptive, leader-coordinated REST polling** where it is not. Preserve the same MariaDB message store and REST authorization layer in both modes. Retain reconciliation because WebSocket hints and browser leader election can fail or be delayed.

This balances compatibility, reliability, operational flexibility and reduced idle traffic for the current standalone prototype.
