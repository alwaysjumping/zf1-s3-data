# Online User Presence and 2-Hour Inactivity Logout

## 1. Purpose

This document defines a practical design for a PHP 7.4 / Zend Framework
1 / MariaDB application that needs to:

-   identify approximately which users are online;
-   track meaningful user activity;
-   expire a login after **2 hours of inactivity**;
-   support multiple browser tabs;
-   avoid automatic heartbeats/polling accidentally keeping sessions
    alive;
-   use a background worker or cron job for cleanup without depending on
    it for security.

------------------------------------------------------------------------

## 2. Presence and Session Inactivity Are Different

### Online presence

Presence answers:

> Does the user's browser appear to be currently connected or recently
> active?

A browser heartbeat can update:

``` text
last_seen_at
```

For example:

``` text
last_seen_at within 2 minutes
        |
        v
      ONLINE
```

### Session inactivity

Session inactivity answers:

> Has the user performed meaningful activity within the last 2 hours?

Use a separate value:

``` text
last_activity_at
```

For example:

``` text
last_activity_at < 2 hours ago
        |
        v
   SESSION VALID

last_activity_at >= 2 hours ago
        |
        v
  SESSION EXPIRED
```

Do not use a presence heartbeat as meaningful user activity.

------------------------------------------------------------------------

## 3. Recommended Database Table

``` sql
CREATE TABLE user_presence (
    user_id BIGINT UNSIGNED NOT NULL
        COMMENT 'Application user ID',

    last_seen_at DATETIME NOT NULL
        COMMENT 'Last browser heartbeat',

    last_activity_at DATETIME NOT NULL
        COMMENT 'Last meaningful user activity',

    PRIMARY KEY (user_id),

    KEY idx_last_seen (
        last_seen_at
    ),

    KEY idx_last_activity (
        last_activity_at
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

`last_seen_at` is used for presence.

`last_activity_at` is used for inactivity policy.

The authoritative session activity value can also be maintained in the
server-side session store. The important requirement is that it is
controlled and checked by the server.

------------------------------------------------------------------------

## 4. Heartbeat

The browser can call:

``` text
POST /api/presence/heartbeat
```

every 30--60 seconds.

For an existing presence record, heartbeat should update only:

``` sql
UPDATE user_presence
SET last_seen_at = NOW()
WHERE user_id = ?;
```

A create-or-update form can be:

``` sql
INSERT INTO user_presence (
    user_id,
    last_seen_at,
    last_activity_at
)
VALUES (
    ?,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    last_seen_at = NOW();
```

Notice that the duplicate-key branch does **not** change
`last_activity_at`.

------------------------------------------------------------------------

## 5. Query Online Users

For example, define online as a heartbeat within two minutes:

``` sql
SELECT
    user_id,
    last_seen_at,
    last_activity_at
FROM user_presence
WHERE last_seen_at >=
      DATE_SUB(NOW(), INTERVAL 2 MINUTE);
```

This threshold is independent from the 2-hour login inactivity timeout.

------------------------------------------------------------------------

## 6. Why Login/Logout Flags Are Not Reliable Enough

This model is insufficient:

``` text
Login  -> online = 1
Logout -> online = 0
```

The server may never receive logout when:

-   the browser crashes;
-   the computer loses power;
-   the network disappears;
-   the browser is forcibly terminated;
-   a tab/window closes unexpectedly.

With heartbeat:

``` text
Browser disappears
      |
      v
Heartbeat stops
      |
      v
last_seen_at becomes old
      |
      v
User becomes OFFLINE
```

No explicit logout notification is required to determine presence.

------------------------------------------------------------------------

## 7. Why Heartbeat Must Not Keep the Login Alive

Assume the user stops working at 10:00.

If heartbeat resets session activity:

``` text
10:00 user stops working
10:01 heartbeat
10:02 heartbeat
10:03 heartbeat
...
15:00 heartbeat
```

the session might never expire.

Therefore:

``` text
Heartbeat
    |
    v
last_seen_at


Meaningful user activity
    |
    v
last_activity_at
```

They have different purposes.

------------------------------------------------------------------------

## 8. Two-Hour Inactivity Rule

Two hours is:

``` text
2 * 60 * 60 = 7200 seconds
```

The server should perform this sequence for protected requests:

``` text
Authenticated request
        |
        v
Read OLD last_activity
        |
        v
Has 7200 seconds elapsed?
       / \
     YES  NO
      |    |
      v    v
   Reject  Allow
      |      |
      v      v
 invalidate update activity
 session    when appropriate
```

The order matters:

> **Check first. Update second.**

------------------------------------------------------------------------

## 9. PHP 7.4 Example

``` php
<?php

$timeoutSeconds = 7200;

$lastActivity = isset(
    $_SESSION['last_activity']
)
    ? (int) $_SESSION['last_activity']
    : 0;

if ($lastActivity > 0) {
    $inactiveSeconds =
        time() - $lastActivity;

    if ($inactiveSeconds >= $timeoutSeconds) {
        Zend_Session::destroy();

        $this->getResponse()
            ->setHttpResponseCode(401);

        $this->_helper->json(
            array(
                'success' => false,
                'code' => 'SESSION_INACTIVE',
                'message' =>
                    'Session expired due to inactivity.'
            )
        );

        return;
    }
}

/*
 * Only meaningful activity should execute this.
 */
$_SESSION['last_activity'] = time();
```

Do not update `last_activity` before testing expiration.

------------------------------------------------------------------------

## 10. Why Request-Time Validation Is Better Than Depending on a Worker

Suppose:

``` text
10:00      last activity

12:00      2-hour limit reached

12:00:01   user sends request

12:10      scheduled cleanup runs
```

If the application depends only on the scheduled cleanup, the request at
12:00:01 could be accepted before cleanup occurs.

Instead:

``` text
12:00:01 request
      |
      v
Authentication layer
      |
      v
Check last_activity
      |
      v
>= 7200 seconds
      |
      v
REJECT
```

Therefore the 2-hour rule should be an authentication/authorization rule
evaluated when a protected request arrives.

A worker is still useful, but it has a different responsibility.

------------------------------------------------------------------------

## 11. Background Job Responsibility

Use cron or a background worker for cleanup:

``` text
Background cleanup
      |
      +--> remove stale session records
      |
      +--> clean old presence records
      |
      +--> clean expired authentication data
      |
      +--> perform retention housekeeping
```

It should not be the only mechanism deciding whether an incoming
protected request is allowed.

Example presence cleanup:

``` sql
DELETE FROM user_presence
WHERE last_seen_at <
      DATE_SUB(NOW(), INTERVAL 30 DAY);
```

The cleanup retention period does not have to equal the two-hour
inactivity timeout.

------------------------------------------------------------------------

## 12. Meaningful Activity

Define deliberately what extends the session.

Typical meaningful activity:

``` text
opening/navigating a business page
saving a form
sending an email
editing a document
creating/updating a task
searching
performing a user-initiated business API request
```

Automatic requests normally should not extend it:

``` text
/api/presence/heartbeat
/api/notification/poll
/api/system/status
```

This prevents background JavaScript from keeping an abandoned session
alive indefinitely.

------------------------------------------------------------------------

## 13. Automatic Requests Must Still Check Expiration

A background endpoint should not necessarily extend the timeout, but it
must still respect an already-expired session.

``` text
Automatic request
      |
      v
Check old last_activity
      |
   +--+--+
   |     |
expired valid
   |     |
   v     v
  401   process
          |
          X
     do not refresh
     last_activity
```

This distinction is important:

``` text
CHECK session expiration
```

and:

``` text
REFRESH session activity
```

are separate operations.

------------------------------------------------------------------------

## 14. Centralize the Session Check

Do not duplicate the inactivity logic throughout controller actions.

Recommended request flow:

``` text
Request
   |
   v
Central authentication/session layer
   |
   +-- logged in?
   |
   +-- inactivity expired?
   |
   +-- authorized?
   |
   v
Controller
```

In ZF1, put the rule in the centralized authentication/plugin/controller
infrastructure used by the application so protected endpoints cannot
accidentally bypass it.

------------------------------------------------------------------------

## 15. Multiple Browser Tabs

Suppose:

``` text
User 100

Tab A
Tab B
Tab C
```

If these tabs share the same login session, meaningful activity in any
tab should normally keep that session active.

Example:

``` text
Tab B user action
      |
      v
server updates last_activity
      |
      v
Tab A later requests page
      |
      v
same session remains valid
```

Closing or leaving one tab idle should not log the user out while
another tab is actively being used.

------------------------------------------------------------------------

## 16. Browser Idle Warning

The frontend can improve user experience by showing a warning shortly
before expiration.

Example:

``` text
1 hour 55 minutes inactive
        |
        v
"Your session will expire in 5 minutes."
```

At two hours, the frontend can show the login page.

However:

> Browser JavaScript is not the security authority.

The server must reject requests whose inactivity period has expired even
if the browser timer failed, the machine slept, or JavaScript was
modified.

------------------------------------------------------------------------

## 17. API Response for Expired Session

Recommended response:

``` http
HTTP/1.1 401 Unauthorized
Content-Type: application/json
```

Example:

``` json
{
    "success": false,
    "code": "SESSION_INACTIVE",
    "message": "Session expired due to inactivity."
}
```

The frontend can centrally detect this condition and redirect to login.

------------------------------------------------------------------------

## 18. Central AJAX Handling

A simple jQuery example:

``` javascript
$(document).ajaxError(
    function (event, xhr) {
        if (xhr.status === 401) {
            window.location.href = '/login';
        }
    }
);
```

In the production application, also inspect the application's API error
code when different authentication failures need different handling.

This is preferable to duplicating logout handling in every AJAX call.

------------------------------------------------------------------------

## 19. Complete Request Flow

``` text
                     BROWSER
                        |
                        v
              Authenticated request
                        |
                        v
              +--------------------+
              | Authentication /   |
              | Session Layer      |
              +--------------------+
                        |
                        v
                 Session exists?
                    /       \
                  NO         YES
                  |           |
                  v           v
                 401     Read old
                         last_activity
                              |
                              v
                       >= 2 hours?
                         /       \
                       YES        NO
                        |          |
                        v          v
                    invalidate   Determine
                     session     request type
                        |         /       \
                        v    meaningful  automatic
                       401       |          |
                                 v          |
                              refresh       |
                           last_activity    |
                                 |          |
                                 +----+-----+
                                      |
                                      v
                                  Controller
```

------------------------------------------------------------------------

## 20. Presence Flow

``` text
Browser
   |
   | heartbeat every ~60 seconds
   v
/api/presence/heartbeat
   |
   v
Check whether session already expired
   |
   +-- expired -> 401
   |
   +-- valid
          |
          v
   update last_seen_at
          |
          X
   do not refresh
   last_activity
```

------------------------------------------------------------------------

## 21. Suggested Initial Timing

``` text
Heartbeat interval:
    60 seconds

Online threshold:
    last_seen_at within 2 minutes

Session inactivity timeout:
    2 hours

Idle warning:
    about 5 minutes before expiration

Cleanup:
    periodic; its exact timing is not the security boundary
```

These values can be changed independently.

------------------------------------------------------------------------

## 22. Security Rules

1.  The server is the authority for session expiration.
2.  Check inactivity before accepting a protected operation.
3.  Read/check the old timestamp before updating it.
4.  Heartbeats must not automatically reset inactivity.
5.  Automatic notification polling must not keep a session alive
    forever.
6.  Do not depend on browser logout events.
7.  Do not depend solely on a scheduled cleanup worker.
8.  Use a consistent expired-session API response.
9.  Centralize the inactivity check.
10. Apply the rule to all protected APIs/pages.
11. Treat frontend timers as user-experience helpers only.
12. Keep cleanup and authorization as separate responsibilities.

------------------------------------------------------------------------

## 23. Final Recommended Architecture

``` text
                    ONLINE PRESENCE

Browser
   |
   | heartbeat
   v
last_seen_at
   |
   +-- recent ----------> ONLINE
   |
   +-- old -------------> OFFLINE


                  SESSION SECURITY

Meaningful user activity
        |
        v
last_activity_at
        |
        | checked on every protected request
        v
   +-------------+
   | < 2 hours   |----> allow
   +-------------+
          |
          | >= 2 hours
          v
    session expired
          |
          v
         401


                       CLEANUP

Cron / background worker
        |
        +--> stale session cleanup
        +--> old presence cleanup
        +--> expired-data housekeeping
```

The central principle is:

> **Presence tells you whether a browser appears to be around.
> Inactivity determines whether its authenticated session may still be
> used. The server enforces the two-hour inactivity rule before
> accepting protected requests, while background jobs handle cleanup.**
