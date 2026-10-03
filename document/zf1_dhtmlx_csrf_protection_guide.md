# CSRF Protection Guide for Zend Framework 1 + DHTMLX 3.5

## 1. Purpose

This guide documents the CSRF design discussed for a legacy application
using Zend Framework 1 (ZF1), PHP 7.4, DHTMLX 3.5, AJAX, iframe-based
pages, browser sessions, and multiple browser tabs.

The recommended design is a **session-based synchronizer token** with
centralized validation in a ZF1 controller plugin.

## 2. What Is CSRF?

CSRF means **Cross-Site Request Forgery**. It targets applications where
a browser automatically sends authentication credentials such as session
cookies.

``` text
User logs in
    |
    v
Browser has authenticated session cookie
    |
    v
User visits malicious site
    |
    v
Malicious page attempts a state-changing request
    |
    v
Target application
```

A CSRF token gives the application an additional secret value that a
legitimate application page must submit with state-changing requests.

CSRF tokens are only one part of application security. They do not
replace authentication, authorization, HTTPS, secure cookies, or XSS
prevention.

## 3. ZF1 Built-In CSRF Support

ZF1 includes `Zend_Form_Element_Hash`.

Example:

``` php
class Application_Form_User extends Zend_Form
{
    public function init()
    {
        $this->addElement(
            'text',
            'username',
            array('required' => true)
        );

        $this->addElement(
            'hash',
            'csrf_token',
            array(
                'salt'    => 'user_form',
                'timeout' => 3600
            )
        );

        $this->addElement(
            'submit',
            'submit',
            array('label' => 'Save')
        );
    }
}
```

Validation occurs with normal form validation:

``` php
$form = new Application_Form_User();

if ($this->getRequest()->isPost()) {
    if ($form->isValid($this->getRequest()->getPost())) {
        // Form and CSRF validation succeeded.
    }
}
```

This is useful for traditional `Zend_Form` forms.

For an application containing DHTMLX 3.5, AJAX calls, iframe pages, and
many endpoints, a centralized service is easier to apply consistently.

## 4. Recommended Architecture

``` text
                     Browser
                        |
                 PHP / ZF1 Session
                        |
                        v
              Generate random CSRF token
                        |
              +---------+---------+
              |         |         |
              v         v         v
            Tab A     Tab B     Tab C
              |         |         |
              +---------+---------+
                        |
          POST / PUT / PATCH / DELETE
                        |
        X-CSRF-Token or csrf_token field
                        |
                        v
              ZF1 Controller Plugin
                        |
                +-------+-------+
                |               |
             Valid           Invalid
                |               |
                v               v
           Controller        HTTP 403
                |
                v
             Service
                |
                v
             MariaDB
```

Use one stable CSRF token for the relevant session rather than
generating a new token whenever a page or tab opens.

## 5. Why One Token per Session?

Do not use this design:

``` text
Tab A opens -> Token A

Tab B opens -> Token B
               |
               +-- Token A invalidated
```

It causes Tab A to fail.

Use:

``` text
PHP Session
    |
    +-- CSRF = 7f21ab...
             |
       +-----+-----+
       |     |     |
       v     v     v
     Tab A Tab B Tab C
```

All tabs belonging to the same session use the same token.

## 6. Suggested Project Structure

``` text
application/
├── Bootstrap.php
└── modules/
    └── email/
        ├── controllers/
        ├── models/
        └── views/

library/
└── S3/
    ├── Security/
    │   └── Csrf.php
    └── Controller/
        └── Plugin/
            └── Csrf.php

public/
├── index.php
└── js/
    └── security/
        └── csrf.js
```

Responsibilities:

``` text
S3_Security_Csrf
    -> generation, storage, validation, rotation

S3_Controller_Plugin_Csrf
    -> centralized HTTP enforcement

csrf.js
    -> browser-side access to the token

Controller / Service
    -> application and business logic
```

## 7. `S3_Security_Csrf`

Create `library/S3/Security/Csrf.php`:

``` php
<?php

class S3_Security_Csrf
{
    const SESSION_NAMESPACE = 'S3_CSRF';
    const TOKEN_KEY = 'token';
    const CREATED_KEY = 'created_at';

    public static function getToken()
    {
        $session = self::getSession();

        if (
            empty($session->{self::TOKEN_KEY})
            || !is_string($session->{self::TOKEN_KEY})
        ) {
            self::generateToken();
        }

        return $session->{self::TOKEN_KEY};
    }

    public static function generateToken()
    {
        $session = self::getSession();

        $session->{self::TOKEN_KEY} =
            bin2hex(random_bytes(32));

        $session->{self::CREATED_KEY} = time();

        return $session->{self::TOKEN_KEY};
    }

    public static function validate($token)
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $session = self::getSession();

        if (
            empty($session->{self::TOKEN_KEY})
            || !is_string($session->{self::TOKEN_KEY})
        ) {
            return false;
        }

        return hash_equals(
            $session->{self::TOKEN_KEY},
            $token
        );
    }

    public static function destroy()
    {
        $session = self::getSession();

        unset(
            $session->{self::TOKEN_KEY},
            $session->{self::CREATED_KEY}
        );
    }

    public static function rotate()
    {
        self::destroy();

        return self::generateToken();
    }

    protected static function getSession()
    {
        return new Zend_Session_Namespace(
            self::SESSION_NAMESPACE
        );
    }
}
```

`random_bytes(32)` produces 32 cryptographically secure random bytes.
`bin2hex()` converts them into a convenient 64-character hexadecimal
representation.

`hash_equals()` is used for security-sensitive token comparison.

## 8. Expose the Token to the View

``` php
$this->view->csrfToken =
    S3_Security_Csrf::getToken();
```

A convenient HTML representation is:

``` html
<meta
    name="csrf-token"
    content="<?php echo $this->escape($this->csrfToken); ?>"
>
```

## 9. JavaScript Helper

Create `public/js/security/csrf.js`:

``` javascript
var S3Csrf = {

    getToken: function () {
        var element = document.querySelector(
            'meta[name="csrf-token"]'
        );

        if (!element) {
            return '';
        }

        return element.getAttribute('content') || '';
    }
};
```

Usage:

``` javascript
var token = S3Csrf.getToken();
```

## 10. AJAX Integration

Prefer an HTTP header for AJAX:

``` text
X-CSRF-Token
```

Example:

``` javascript
var xhr = new XMLHttpRequest();

xhr.open(
    'POST',
    '/email/template/save',
    true
);

xhr.setRequestHeader(
    'Content-Type',
    'application/x-www-form-urlencoded'
);

xhr.setRequestHeader(
    'X-CSRF-Token',
    S3Csrf.getToken()
);

xhr.send(
    'name=' + encodeURIComponent('Test')
);
```

## 11. DHTMLX 3.5 Integration

For legacy DHTMLX forms, support a hidden field:

``` javascript
{
    type: "hidden",
    name: "csrf_token",
    value: ""
}
```

Before submission:

``` javascript
myForm.setItemValue(
    "csrf_token",
    S3Csrf.getToken()
);

myForm.send(
    "/email/template/save",
    "post"
);
```

The server accepts either:

``` text
X-CSRF-Token: <token>
```

or:

``` text
csrf_token=<token>
```

Prefer the header when the client supports it; retain the request
parameter for normal/legacy form compatibility.

## 12. Central ZF1 Controller Plugin

Create `library/S3/Controller/Plugin/Csrf.php`:

``` php
<?php

class S3_Controller_Plugin_Csrf
    extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(
        Zend_Controller_Request_Abstract $request
    ) {
        if (!$this->requiresProtection($request)) {
            return;
        }

        $token = $this->getTokenFromRequest($request);

        if (!S3_Security_Csrf::validate($token)) {
            $this->rejectRequest();
        }
    }

    protected function requiresProtection(
        Zend_Controller_Request_Abstract $request
    ) {
        $method = strtoupper($request->getMethod());

        return in_array(
            $method,
            array('POST', 'PUT', 'PATCH', 'DELETE'),
            true
        );
    }

    protected function getTokenFromRequest(
        Zend_Controller_Request_Abstract $request
    ) {
        $token = $request->getHeader('X-CSRF-Token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = $request->getParam(
            'csrf_token',
            ''
        );

        return is_string($token) ? $token : '';
    }

    protected function rejectRequest()
    {
        $response = $this->getResponse();

        $response->setHttpResponseCode(403);

        $response->setHeader(
            'Content-Type',
            'application/json; charset=UTF-8',
            true
        );

        $response->setBody(
            Zend_Json::encode(
                array(
                    'success' => false,
                    'error'   => 'invalid_csrf_token',
                    'message' => 'Invalid CSRF token.'
                )
            )
        );

        $response->sendResponse();

        exit;
    }
}
```

Central enforcement is preferable to relying on every developer to
remember to call the CSRF validator in every controller action.

## 13. Register the Plugin

In the ZF1 Bootstrap:

``` php
protected function _initControllerPlugins()
{
    $front = Zend_Controller_Front::getInstance();

    $front->registerPlugin(
        new S3_Controller_Plugin_Csrf()
    );
}
```

## 14. HTTP Method Policy

Normally:

``` text
GET       -> no CSRF validation
HEAD      -> no CSRF validation

POST      -> CSRF required
PUT       -> CSRF required
PATCH     -> CSRF required
DELETE    -> CSRF required
```

GET and HEAD endpoints should remain free of state-changing side
effects.

## 15. Validation Flow

``` text
HTTP request
     |
     v
ZF1 Front Controller
     |
     v
CSRF Controller Plugin
     |
     +-- safe method? -> continue
     |
     +-- state-changing method
              |
              v
       Read X-CSRF-Token
              |
        missing?
              |
              v
       Try csrf_token field
              |
              v
       Validate against session
              |
         +----+----+
         |         |
       valid     invalid
         |         |
         v         v
    Controller   HTTP 403
```

## 16. Token Lifetime

Tie the CSRF token primarily to the relevant login/session lifetime
rather than using a short independent timeout.

``` text
Login / session
      |
      +-- token generated
      |
      v
User works
      |
      v
Logout / session expiration
      |
      +-- token no longer usable
```

A short independent timeout can cause long-lived forms to fail
unexpectedly.

## 17. Login Handling

The login form itself may require CSRF protection.

After successful authentication, regenerate the session ID:

``` php
Zend_Session::regenerateId();
```

Then rotate the CSRF token:

``` php
S3_Security_Csrf::rotate();
```

Flow:

``` text
Anonymous session
      |
      +-- CSRF A
      |
      v
Login succeeds
      |
      +-- regenerate session ID
      +-- rotate CSRF token
      |
      v
Authenticated session
      |
      +-- CSRF B
```

## 18. Logout

Example:

``` php
S3_Security_Csrf::destroy();

Zend_Auth::getInstance()
    ->clearIdentity();

Zend_Session::destroy();
```

The old session/token should no longer be accepted.

## 19. Same-Origin iframe Pages

Same-origin iframe pages normally share the same PHP session cookie.

``` text
Main Window
      |
      +---------------+
      |               |
      v               v
   iframe A        iframe B
      |               |
      +-------+-------+
              |
              v
       same PHP session
              |
              v
       same CSRF token
```

Do not generate a separate token for each iframe.

## 20. JWT APIs

Traditional CSRF attacks matter primarily when browsers automatically
attach authentication credentials such as cookies.

An API authenticated only through an explicitly supplied bearer token:

``` text
Authorization: Bearer <JWT>
```

has a different threat model.

Conceptually:

``` text
Cookie-authenticated browser endpoint
        |
        +-- CSRF protection

Bearer-token-only API
        |
        +-- JWT validation
```

Do not exempt an endpoint simply because its path starts with `/api`.
Decide according to the actual authentication mechanism.

If an API endpoint also relies on browser session cookies, it can still
require CSRF protection.

## 21. Exclusions

External webhooks and similar endpoints may not have a user's session
token. They should use their own appropriate authentication/signature
verification.

Avoid broad exclusions such as:

``` php
if (strpos($url, '/api/') === 0) {
    // Skip CSRF
}
```

Use narrow, explicit exclusions for known endpoints and known
authentication models.

## 22. Session Cookie Hardening

For an HTTPS production deployment, consider settings such as:

``` ini
session.use_only_cookies = 1
session.use_strict_mode = 1

session.cookie_httponly = 1
session.cookie_secure = 1

session.cookie_samesite = "Lax"
```

`session.cookie_secure = 1` requires HTTPS.

SameSite is an additional defense and should not be treated as a
replacement for the CSRF token in this architecture.

## 23. Authentication, Authorization, and CSRF Are Different

Authentication:

``` text
Who is the user?
```

CSRF protection:

``` text
Does this state-changing browser request contain
the token associated with this session?
```

Authorization:

``` text
Is this authenticated user allowed to perform
this operation on this resource?
```

A valid CSRF token does not grant permission.

Recommended protected operation flow:

``` text
Request
   |
   v
Authentication
   |
   v
CSRF validation
   |
   v
Authorization
   |
   v
Input validation
   |
   v
Business logic
   |
   v
Database
```

## 24. CSRF and XSS

CSRF protection does not replace XSS protection.

Malicious JavaScript executing in the application's own origin may be
able to make authenticated requests and access browser-visible CSRF
values.

Also use:

-   Context-appropriate output escaping
-   Server-side HTML sanitization for rich HTML
-   Content Security Policy where practical
-   Input validation
-   Safe JavaScript practices
-   Secure session cookies

## 25. Stolen Session ID and CSRF Token

If an attacker obtains both the authenticated session ID and CSRF token,
treat the situation as session compromise.

CSRF does not make a stolen authenticated session safe.

Use layered protections:

``` text
HTTPS
Secure cookies
HttpOnly
SameSite
Session ID regeneration
Authentication
Authorization
XSS prevention
Session expiration
Logging / monitoring
```

## 26. Do Not Rotate on Every Save

Do not do this:

``` text
Tab A and Tab B use token X.

Tab A saves.
Server changes token to Y.

Tab B still has X.
Tab B saves.
Result: 403.
```

Keep the token stable during the relevant session. Rotate at meaningful
boundaries such as successful login.

## 27. Error Response

A useful AJAX response is:

``` json
{
    "success": false,
    "error": "invalid_csrf_token",
    "message": "Invalid CSRF token."
}
```

with:

``` text
HTTP 403 Forbidden
```

The client can then prompt for refresh or re-authentication if the
session has ended.

## 28. `Zend_Form_Element_Hash` vs Central Service

Traditional form:

``` text
Zend_Form
    |
    +-- Zend_Form_Element_Hash
```

Application-wide design:

``` text
Normal forms
DHTMLX
AJAX
iframe pages
custom endpoints
       |
       v
S3_Security_Csrf
       |
       v
S3_Controller_Plugin_Csrf
```

For a DHTMLX/AJAX-heavy ZF1 application, centralized enforcement is more
consistent.

## 29. Implementation Checklist

### Server

-   [ ] Create `S3_Security_Csrf`.
-   [ ] Generate tokens with `random_bytes()`.
-   [ ] Compare tokens with `hash_equals()`.
-   [ ] Store the token server-side in the session.
-   [ ] Create `S3_Controller_Plugin_Csrf`.
-   [ ] Protect POST, PUT, PATCH, and DELETE.
-   [ ] Return HTTP 403 for invalid/missing tokens.
-   [ ] Regenerate the session ID after successful login.
-   [ ] Rotate the CSRF token after successful login.
-   [ ] Destroy the token/session during logout.
-   [ ] Keep exclusions narrow and explicit.

### Browser

-   [ ] Expose the token safely to application JavaScript.
-   [ ] Prefer `X-CSRF-Token` for AJAX.
-   [ ] Support `csrf_token` for legacy/DHTMLX forms.
-   [ ] Do not independently generate the token in JavaScript.
-   [ ] Do not rotate the token per request.

### Deployment

-   [ ] Use HTTPS.
-   [ ] Use Secure cookies in HTTPS production.
-   [ ] Use HttpOnly session cookies.
-   [ ] Configure an appropriate SameSite policy.
-   [ ] Use strict session handling where compatible.

### Application Security

-   [ ] Keep authentication checks.
-   [ ] Keep per-resource authorization checks.
-   [ ] Validate request input.
-   [ ] Escape output.
-   [ ] Sanitize rich HTML.
-   [ ] Protect against XSS.
-   [ ] Do not treat CSRF as a replacement for session security.

## 30. Final Recommended Design

``` text
                         ZF1 Application
                               |
             +-----------------+-----------------+
             |                                   |
      Normal Zend_Form                    DHTMLX / AJAX
             |                                   |
             +-----------------+-----------------+
                               |
                               v
                       CSRF Token Service
                               |
                    one token per session
                               |
                               v
                    Controller CSRF Plugin
                               |
             POST / PUT / PATCH / DELETE
                               |
                   +-----------+-----------+
                   |                       |
                   v                       v
                 Valid                   Invalid
                   |                       |
                   v                       v
              Controller                403 JSON
                   |
                   v
          Authorization / Validation
                   |
                   v
                Service
                   |
                   v
                MariaDB
```

`Zend_Form_Element_Hash` remains useful for conventional ZF1 forms. For
a legacy application with DHTMLX 3.5, AJAX, iframe pages, and multi-tab
usage, the centralized `S3_Security_Csrf` plus
`S3_Controller_Plugin_Csrf` architecture provides one consistent CSRF
mechanism across the application.
