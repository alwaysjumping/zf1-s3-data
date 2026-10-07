# JWT Refresh Token and Browser Cookie Guide

## 1. Purpose

This document explains how to store JWT access tokens, refresh tokens,
and normal application values in a browser for a PHP 7.4 / Zend
Framework 1 application.

The main principle is:

> **Keep the refresh token in a Secure, HttpOnly cookie. Use separate
> normal cookies only for non-sensitive values that JavaScript needs to
> read.**

------------------------------------------------------------------------

# 2. Access Token vs Refresh Token

The two tokens have different responsibilities.

## Access Token

An access token is normally:

-   short-lived
-   sent with API requests
-   used to authorize access to protected APIs
-   commonly sent in the `Authorization` header

Example:

``` http
Authorization: Bearer <access-token>
```

Conceptually:

``` text
JavaScript
    |
    | Authorization: Bearer <access-token>
    v
ZF1 API
```

For a browser application, the access token can be kept in JavaScript
memory when practical rather than permanently stored in browser storage.

------------------------------------------------------------------------

## Refresh Token

A refresh token is normally:

-   longer-lived
-   more sensitive
-   used only to obtain a new access token
-   rotated after successful use
-   protected more carefully than an access token

The recommended browser design is:

``` text
Refresh Token
     |
     v
Secure + HttpOnly Cookie
```

JavaScript does not need to know the refresh-token value.

------------------------------------------------------------------------

# 3. Why Not Store the Refresh Token in localStorage?

Avoid:

``` javascript
localStorage.setItem('refresh_token', refreshToken);
```

JavaScript can later read it:

``` javascript
const token = localStorage.getItem('refresh_token');
```

If malicious JavaScript executes in the page because of an XSS
vulnerability, it may also be able to read and steal the refresh token.

A long-lived refresh token is particularly valuable to an attacker.

------------------------------------------------------------------------

# 4. HttpOnly Cookie

An `HttpOnly` cookie is sent by the browser to the server, but
JavaScript cannot read its value through `document.cookie`.

Example:

``` text
refresh_token
    |
    +-- HttpOnly
    +-- Secure
    +-- SameSite
```

Conceptually:

``` text
Browser
|
+-- JavaScript
|      |
|      +-- Cannot read refresh_token
|
+-- Cookie Storage
       |
       +-- refresh_token
              |
              +-- HttpOnly
              +-- Secure
              +-- SameSite
```

------------------------------------------------------------------------

# 5. Setting the Refresh Token in PHP 7.4

Example:

``` php
setcookie(
    'refresh_token',
    $refreshToken,
    [
        'expires'  => time() + (30 * 24 * 60 * 60),
        'path'     => '/api/auth',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);
```

Important properties:

``` text
Secure
    -> Send the cookie only over HTTPS.

HttpOnly
    -> Prevent JavaScript from reading the cookie.

SameSite
    -> Helps control cross-site cookie sending.

Path
    -> Restricts where the browser sends the cookie.
```

A narrow path such as:

``` text
/api/auth
```

can reduce unnecessary exposure of the refresh-token cookie to unrelated
application endpoints.

------------------------------------------------------------------------

# 6. Login Flow

A recommended login flow is:

``` text
Browser
   |
   | username + password
   v
POST /api/auth/login
   |
   v
ZF1 Authentication
   |
   +------------------------+
   |                        |
   v                        v
Access Token          Refresh Token
   |                        |
   v                        v
Response Body         Set-Cookie
                            |
                            +-- HttpOnly
                            +-- Secure
                            +-- SameSite
```

JavaScript receives the access token.

The browser receives and stores the refresh token automatically through
`Set-Cookie`.

------------------------------------------------------------------------

# 7. Refresh Flow

When the access token expires:

``` text
API Request
    |
    v
Access Token Expired
    |
    v
POST /api/auth/refresh
```

The browser automatically sends the matching refresh-token cookie.

Conceptually:

``` text
Browser                            ZF1

Refresh Token A
     |
     | POST /api/auth/refresh
     | Cookie: refresh_token=A
     +---------------------------->
                                  |
                                  | Validate A
                                  |
                                  | Revoke A
                                  |
                                  | Generate B
                                  |
                                  | Store hash(B)
                                  |
     <----------------------------+
        New Access Token
        Set-Cookie: refresh_token=B
```

JavaScript never needs to read token A or token B.

------------------------------------------------------------------------

# 8. Reading the Refresh Token in PHP

The server can read the cookie:

``` php
$refreshToken = $_COOKIE['refresh_token'] ?? null;

if (!$refreshToken) {
    // Return HTTP 401.
}
```

The server then validates the token according to the refresh-token
database/session design.

------------------------------------------------------------------------

# 9. Refresh-Token Rotation

Refresh tokens should be rotated after successful use.

Example:

``` text
Token A
   |
   | refresh request
   v
Validate A
   |
   v
Revoke A
   |
   v
Generate Token B
   |
   v
Store B
   |
   v
Send B as new HttpOnly cookie
```

After rotation:

``` text
Token A -> revoked

Token B -> active
```

The old token should not continue to work normally.

------------------------------------------------------------------------

# 10. Store a Hash of the Refresh Token in MariaDB

Where the refresh-token design permits opaque random tokens, it is
preferable to store a one-way hash rather than the raw token.

Example:

``` php
$refreshToken = bin2hex(random_bytes(32));

$tokenHash = hash('sha256', $refreshToken);
```

Browser receives:

``` text
refreshToken
```

MariaDB stores:

``` text
SHA-256(refreshToken)
```

When the browser later presents the refresh token:

``` text
Received Token
     |
     v
SHA-256
     |
     v
Token Hash
     |
     v
Compare with database
```

If the database is exposed, the attacker does not immediately obtain
usable raw refresh tokens from that table.

------------------------------------------------------------------------

# 11. Normal Cookies Are Still Allowed

Using an HttpOnly cookie for the refresh token does **not** mean all
application cookies must be HttpOnly.

There are two useful categories.

``` text
Browser Cookies
|
+-- Security-sensitive cookie
|      |
|      +-- refresh_token
|      +-- HttpOnly = true
|
+-- Normal application cookies
       |
       +-- language
       +-- theme
       +-- grid_page_size
       +-- selected UI preferences
```

Normal cookies can remain JavaScript-readable when the application
genuinely needs that behavior.

------------------------------------------------------------------------

# 12. Creating a Normal JavaScript-Readable Cookie

PHP example:

``` php
setcookie(
    'language',
    'en',
    [
        'expires'  => time() + (365 * 24 * 60 * 60),
        'path'     => '/',
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax'
    ]
);
```

Because:

``` php
'httponly' => false
```

JavaScript can read this cookie.

------------------------------------------------------------------------

# 13. Creating a Cookie with JavaScript

Example:

``` javascript
document.cookie =
    'language=en; Path=/; SameSite=Lax; Secure';
```

Another example:

``` javascript
document.cookie =
    'grid_page_size=50; Path=/; SameSite=Lax; Secure';
```

These values can later be obtained from `document.cookie`.

------------------------------------------------------------------------

# 14. Reading Normal Cookies with JavaScript

Basic example:

``` javascript
console.log(document.cookie);
```

It may return:

``` text
language=en; theme=dark; grid_page_size=50
```

An HttpOnly refresh-token cookie will not appear in this
JavaScript-readable string.

Therefore, the browser may internally contain:

``` text
refresh_token=SECRET
language=en
theme=dark
```

while JavaScript sees only:

``` text
language=en; theme=dark
```

That is intentional.

------------------------------------------------------------------------

# 15. Example JavaScript Cookie Helper

For older JavaScript environments, a simple helper can be used:

``` javascript
function getCookie(name) {
    var prefix = name + '=';
    var cookies = document.cookie.split(';');

    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].replace(/^\s+/, '');

        if (cookie.indexOf(prefix) === 0) {
            return decodeURIComponent(
                cookie.substring(prefix.length)
            );
        }
    }

    return null;
}
```

Usage:

``` javascript
var language = getCookie('language');

console.log(language);
```

Again, this helper cannot read an HttpOnly refresh-token cookie.

------------------------------------------------------------------------

# 16. What Is Safe to Put in JavaScript-Readable Cookies?

Typical examples include:

``` text
language
theme
display mode
grid page size
selected tab
non-sensitive UI preferences
```

For example:

``` text
language=en
theme=dark
grid_page_size=50
```

These values should still be validated before the server relies on them.

------------------------------------------------------------------------

# 17. What Should Not Be Put in JavaScript-Readable Cookies?

Do not store sensitive secrets there.

Examples:

``` text
Password                 NO
Refresh Token            NO
Private Encryption Key   NO
AES Key                  NO
API Secret               NO
Server Secret            NO
```

If JavaScript can read a value, an XSS vulnerability may potentially
read it too.

------------------------------------------------------------------------

# 18. Never Trust a Normal Cookie for Authorization

A browser user can modify ordinary cookies.

For example, this is dangerous:

``` text
is_admin=1
```

The server must not say:

``` php
if ($_COOKIE['is_admin'] === '1') {
    // Give administrator permissions.
}
```

A user could modify that cookie.

Authorization must always be verified using trusted server-side
information.

For example:

``` text
Access Token
      |
      v
Verify Signature
      |
      v
Read User Identity / Claims
      |
      v
Server-Side Permission Check
```

A UI preference cookie can affect presentation.

It must not grant security privileges.

------------------------------------------------------------------------

# 19. Recommended Cookie Separation

A practical design is:

``` text
Browser
|
+-- refresh_token
|      |
|      +-- Secure
|      +-- HttpOnly
|      +-- SameSite
|      +-- restricted Path
|
+-- language
|      |
|      +-- Secure
|      +-- JavaScript readable
|
+-- theme
|      |
|      +-- Secure
|      +-- JavaScript readable
|
+-- grid_page_size
       |
       +-- Secure
       +-- JavaScript readable
```

The refresh token is isolated from normal application preferences.

------------------------------------------------------------------------

# 20. Recommended Token Architecture

``` text
LOGIN
  |
  v
ZF1
  |
  +---------------------------+
  |                           |
  v                           v
Access Token             Refresh Token
  |                           |
  v                           v
Return to JS             Set-Cookie
                              |
                              +-- HttpOnly
                              +-- Secure
                              +-- SameSite
```

API request:

``` text
JavaScript
    |
    | Authorization: Bearer <access-token>
    v
ZF1 API
```

Refresh:

``` text
Access Token Expired
        |
        v
POST /api/auth/refresh
        |
        v
Browser automatically sends
refresh-token cookie
        |
        v
ZF1 validates token
        |
        v
Rotate refresh token
        |
        +--------------------+
        |                    |
        v                    v
New Access Token       New HttpOnly Cookie
```

------------------------------------------------------------------------

# 21. Cookies and CSRF

There is an important consequence of putting the refresh token in a
cookie:

> Browsers automatically attach eligible cookies to requests.

Therefore, the refresh endpoint should have appropriate
CSRF/cross-origin protections.

Possible protections include, depending on the application's
architecture:

-   `SameSite` cookie policy
-   checking request origin
-   CSRF-token protection where appropriate
-   restricting allowed HTTP methods
-   rejecting unexpected cross-origin requests
-   HTTPS everywhere

Do not assume `HttpOnly` prevents CSRF.

`HttpOnly` protects against JavaScript **reading** the cookie.

It does not mean the browser will stop **sending** the cookie.

These are different threats:

``` text
HttpOnly
    |
    +--> Helps protect cookie confidentiality from JavaScript/XSS theft

SameSite / CSRF controls
    |
    +--> Help protect against unwanted cross-site requests
```

------------------------------------------------------------------------

# 22. Multi-Tab Applications

For applications opened in several browser tabs, the HttpOnly
refresh-token cookie is naturally shared according to the cookie's
domain/path rules.

Each tab does not need its own copy of the refresh token in JavaScript.

``` text
Browser
|
+-- Shared HttpOnly Refresh Cookie
|
+-- Tab A
|      +-- Access Token in memory
|
+-- Tab B
|      +-- Access Token in memory
|
+-- Tab C
       +-- Access Token in memory
```

Care is needed when multiple tabs attempt refresh-token rotation at the
same time.

A coordination mechanism such as `BroadcastChannel`, where supported by
the application's browser requirements, can help tabs coordinate
access-token refresh activity.

The server must still enforce refresh-token validity and rotation.

------------------------------------------------------------------------

# 23. Logout

Logout should invalidate the server-side refresh-token session and
expire the browser cookie.

Example:

``` php
setcookie(
    'refresh_token',
    '',
    [
        'expires'  => time() - 3600,
        'path'     => '/api/auth',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);
```

The cookie attributes, especially `path`, should match the cookie being
removed.

The server should also revoke/invalidate the corresponding refresh-token
record.

Conceptually:

``` text
POST /api/auth/logout
        |
        +--> Revoke refresh token in MariaDB
        |
        +--> Expire HttpOnly cookie
        |
        +--> Remove access token from JS memory
```

------------------------------------------------------------------------

# 24. Security Summary

## Access Token

Recommended characteristics:

``` text
Short lifetime
Used for API authorization
Sent using Authorization: Bearer
Can be held in JavaScript memory
```

## Refresh Token

Recommended characteristics:

``` text
Longer lifetime
Secure
HttpOnly
SameSite
Rotated after successful use
Server-side record/hash
Revocable
```

## Normal Application Cookies

Recommended characteristics:

``` text
Only non-sensitive values
JavaScript readable when necessary
Never trusted for authorization
Validated by the server when used
```

------------------------------------------------------------------------

# 25. Final Recommended Design

For a PHP 7.4 + ZF1 browser application:

``` text
ACCESS TOKEN
|
+-- Short-lived
+-- JavaScript/API layer
+-- Authorization header
+-- Prefer memory when practical


REFRESH TOKEN
|
+-- Longer-lived
+-- Secure cookie
+-- HttpOnly
+-- SameSite
+-- Rotation
+-- Server-side revocation
+-- Never intentionally exposed to JavaScript


NORMAL COOKIE
|
+-- UI/application preferences
+-- JavaScript readable if required
+-- No sensitive secrets
+-- Never trusted for authorization
```

The key rule is:

> **The refresh token and normal application cookies should be
> separate.**

You can continue using JavaScript-readable cookies for ordinary
application values while protecting the refresh token in its own Secure,
HttpOnly cookie.

---

# 26. Where Should the Access Token Be Stored?

For a browser-based PHP 7.4 + Zend Framework 1 application, a good default is:

> **Keep the access token in JavaScript memory rather than storing it permanently in `localStorage`, `sessionStorage`, or a normal cookie.**

The recommended separation is:

```text
ACCESS TOKEN
    |
    +-- JavaScript memory
    +-- Short lifetime
    +-- Sent in Authorization header


REFRESH TOKEN
    |
    +-- Secure cookie
    +-- HttpOnly
    +-- Longer lifetime
    +-- JavaScript cannot read it
```

This keeps the long-lived credential protected inside an HttpOnly cookie and makes the access token temporary and disposable.

---

# 27. Simple JavaScript Access-Token Storage

A simple in-memory implementation can be:

```javascript
var accessToken = null;

function setAccessToken(token) {
    accessToken = token;
}

function getAccessToken() {
    return accessToken;
}

function clearAccessToken() {
    accessToken = null;
}
```

After login:

```javascript
$.ajax({
    url: '/api/auth/login',
    type: 'POST',
    data: {
        username: username,
        password: password
    },
    success: function (response) {
        setAccessToken(response.access_token);

        // The refresh token is not handled by JavaScript.
        // The server has already stored it as an HttpOnly cookie.
    }
});
```

---

# 28. Sending the Access Token to the API

The access token should normally be sent using the HTTP `Authorization` header.

Example:

```javascript
$.ajax({
    url: '/api/email/getlist',
    type: 'GET',
    headers: {
        'Authorization': 'Bearer ' + getAccessToken()
    }
});
```

The request looks conceptually like:

```http
GET /api/email/getlist HTTP/1.1
Authorization: Bearer <access-token>
```

The ZF1 API verifies the JWT before allowing access to the protected endpoint.

---

# 29. Why Avoid localStorage for the Access Token?

It is technically possible to store an access token in:

```javascript
localStorage.setItem('access_token', token);
```

However, JavaScript running in the page can read it:

```javascript
var token = localStorage.getItem('access_token');
```

Therefore, an XSS vulnerability may expose the token.

Keeping the access token only in memory reduces how long the token remains stored in the browser.

The security model becomes:

```text
Access Token
    |
    +-- Short-lived
    +-- Memory only
    +-- Lost on reload
    +-- Easy to replace


Refresh Token
    |
    +-- Long-lived
    +-- HttpOnly cookie
    +-- Used to recover a new access token
```

---

# 30. What Happens After F5 or Page Reload?

JavaScript memory is cleared when the page reloads.

Before reload:

```text
accessToken = "eyJ..."
```

After reload:

```text
accessToken = null
```

This is expected.

The application should then use the refresh-token cookie to obtain a new access token.

```text
Page Reload
    |
    v
accessToken = null
    |
    v
POST /api/auth/refresh
    |
    v
Browser automatically sends
HttpOnly refresh-token cookie
    |
    v
ZF1 validates refresh token
    |
    v
ZF1 returns new access token
    |
    v
JavaScript stores new access token in memory
```

The user normally does not need to log in again while the refresh-token session is still valid.

---

# 31. Example Application Startup Flow

A simple startup sequence can be:

```javascript
var accessToken = null;

function initializeApplication() {
    $.ajax({
        url: '/api/auth/refresh',
        type: 'POST',
        success: function (response) {
            accessToken = response.access_token;

            startApplication();
        },
        error: function () {
            showLoginPage();
        }
    });
}
```

Conceptually:

```text
Browser Opens Application
        |
        v
No access token exists yet
        |
        v
POST /api/auth/refresh
        |
        +-- Valid refresh cookie
        |       |
        |       v
        |   Get new access token
        |       |
        |       v
        |   Start application
        |
        +-- Invalid / expired refresh cookie
                |
                v
            Show login page
```

The exact refresh behavior should match the application's refresh-token rotation policy.

---

# 32. Access-Token Expiration During an API Request

Suppose the access token expires after 10 minutes.

```text
JavaScript
    |
    | API request
    v
ZF1 API
    |
    v
Access token expired
    |
    v
HTTP 401
```

The client can then:

```text
HTTP 401
   |
   v
POST /api/auth/refresh
   |
   v
Get new access token
   |
   v
Store it in memory
   |
   v
Retry the original API request
```

A centralized JavaScript AJAX handler is useful so every page does not need to duplicate this logic.

---

# 33. Example Central Refresh Logic

A simplified approach:

```javascript
var accessToken = null;

function refreshAccessToken(successCallback, errorCallback) {
    $.ajax({
        url: '/api/auth/refresh',
        type: 'POST',

        success: function (response) {
            accessToken = response.access_token;

            if (successCallback) {
                successCallback();
            }
        },

        error: function () {
            accessToken = null;

            if (errorCallback) {
                errorCallback();
            }
        }
    });
}
```

Then an API request can retry after refresh.

In a real application, it is important to prevent several simultaneous failed API requests from starting several refresh operations at the same time.

---

# 34. Multi-Tab Browser Behavior

Each browser tab has its own JavaScript memory.

For example:

```text
Browser
|
+-- Shared HttpOnly refresh-token cookie
|
+-- Tab A
|      |
|      +-- accessToken A in memory
|
+-- Tab B
|      |
|      +-- accessToken B in memory
|
+-- Tab C
       |
       +-- accessToken C in memory
```

The HttpOnly refresh-token cookie is shared according to normal cookie domain/path rules, but JavaScript variables are not shared between tabs.

This is important when refresh-token rotation is enabled.

---

# 35. Refresh-Token Rotation and Multiple Tabs

Consider this situation:

```text
Tab A
Tab B
Tab C
```

All tabs may detect an expired access token around the same time.

Without coordination:

```text
Tab A -> refresh using token R1
Tab B -> refresh using token R1
Tab C -> refresh using token R1
```

If the server rotates `R1` when Tab A uses it:

```text
R1 -> revoked
R2 -> issued
```

then the requests from Tab B and Tab C may fail because they attempted to use the old token.

Therefore, multi-tab refresh coordination is important.

Possible solutions include:

- use `BroadcastChannel` to coordinate refresh activity
- designate one tab to perform refresh
- let other tabs wait for completion
- carefully design a short server-side grace/reuse-detection policy if appropriate
- centralize authentication state handling

Do not simply disable rotation to avoid the race condition.

---

# 36. BroadcastChannel for Authentication Coordination

Where browser support fits the application requirements, `BroadcastChannel` can coordinate authentication events.

Example concept:

```text
Tab A
   |
   | "refresh-started"
   v
BroadcastChannel
   |
   +------> Tab B waits
   |
   +------> Tab C waits
```

After refresh:

```text
Tab A
   |
   | "refresh-complete"
   v
BroadcastChannel
   |
   +------> Tab B continues
   |
   +------> Tab C continues
```

Be cautious about broadcasting raw access-token values unnecessarily.

A safer design is often to broadcast authentication state events and allow each tab to obtain or update its own short-lived access token through the application's defined session mechanism.

---

# 37. Access-Token Lifetime

Access tokens should normally have a relatively short lifetime.

A common design is approximately:

```text
Access Token
    -> 5 to 15 minutes

Refresh Token
    -> days or weeks
```

The exact lifetime should be selected according to the application's security requirements and user experience.

The important relationship is:

```text
Access Token Lifetime
      <<
Refresh Token Lifetime
```

The access token is temporary.

The refresh token maintains the longer browser session.

---

# 38. Final Browser Storage Recommendation

For this architecture:

```text
PASSWORD
|
+-- Never store it in browser storage


ACCESS TOKEN
|
+-- JavaScript memory
+-- Short-lived
+-- Authorization header
+-- Re-create after page reload using refresh flow


REFRESH TOKEN
|
+-- Secure cookie
+-- HttpOnly
+-- SameSite
+-- Longer-lived
+-- Rotate after successful use
+-- Server-side revocation support


NORMAL APPLICATION VALUES
|
+-- Normal cookies or other browser storage
+-- Only non-sensitive information
```

The overall authentication lifecycle is:

```text
LOGIN
  |
  +--> Access Token
  |       |
  |       +--> JavaScript memory
  |
  +--> Refresh Token
          |
          +--> Secure HttpOnly cookie


API REQUEST
  |
  +--> Authorization: Bearer <access-token>


ACCESS TOKEN EXPIRES
  |
  +--> POST /api/auth/refresh
          |
          +--> Browser sends HttpOnly refresh cookie
          |
          +--> Server validates and rotates refresh token
          |
          +--> Server returns new access token
                  |
                  +--> JavaScript memory


PAGE RELOAD
  |
  +--> Access token disappears
  |
  +--> Refresh endpoint restores a new access token
```

The key rule is:

> **Do not rely on persistent JavaScript-accessible storage for the access token when an in-memory access token plus a protected refresh-token cookie can provide the required session behavior.**

