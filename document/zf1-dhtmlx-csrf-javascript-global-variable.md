# CSRF Token in a JavaScript Global Variable --- ZF1 + DHTMLX 3.5

For a **Zend Framework 1 (ZF1) + DHTMLX 3.5** project, keeping a CSRF
token in a JavaScript global variable can be acceptable, but there are
important security considerations.

## 1. JavaScript Global Variable

For example:

``` html
<script>
var csrfToken = "a8f73c91e2...";
</script>
```

JavaScript can then include the token when sending a request.

The main security concern is **XSS (Cross-Site Scripting)**. Any
JavaScript successfully executing in the same page/origin can generally
access the global variable:

``` javascript
console.log(window.csrfToken);
```

Therefore, an XSS vulnerability could allow malicious JavaScript to read
the CSRF token.

However, moving the token from a JavaScript global variable into a
hidden input or `<meta>` element does **not** by itself protect the
token from XSS. Malicious same-origin JavaScript can normally read those
values too.

## 2. Recommended Browser-Side Approach

Instead of creating another global variable, you can render the token
into a `<meta>` element:

``` html
<meta name="csrf-token"
      content="<?= htmlspecialchars($this->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
```

Then retrieve it when needed:

``` javascript
function getCsrfToken() {
    var element = document.querySelector('meta[name="csrf-token"]');

    return element ? element.getAttribute('content') : null;
}
```

This does not make the token XSS-proof, but it avoids unnecessary global
state and provides a consistent location for JavaScript code to retrieve
the token.

## 3. Using It with DHTMLX

For a DHTMLX form:

``` javascript
var token = getCsrfToken();

dhxForm.setItemValue("csrf_token", token);
```

The request can then contain:

``` text
POST /user/save

name=John
email=john@example.com
csrf_token=a8f73c91e2...
```

## 4. Using an HTTP Header

For AJAX requests, another clean approach is to send the token in a
custom HTTP header:

``` javascript
var xhr = new XMLHttpRequest();

xhr.open("POST", "/user/save", true);

xhr.setRequestHeader(
    "X-CSRF-Token",
    getCsrfToken()
);

xhr.send(data);
```

The request conceptually becomes:

``` text
POST /user/save
Cookie: PHPSESSID=abc123
X-CSRF-Token: a8f73c91e2...

...
```

## 5. Store the Authoritative Token on the Server

The browser-visible token should not be considered the authoritative
copy.

The expected CSRF token should be stored server-side in the user's
PHP/Zend session.

For example:

``` php
$session = new Zend_Session_Namespace('security');

$session->csrfToken = $token;
```

The architecture is:

``` text
PHP/Zend Session
      |
      +-- csrfToken = ABC123
              |
              | render into page
              v
Browser
      |
      +-- token = ABC123
              |
              | POST / AJAX
              v
ZF1 Server
      |
      +-- Compare received token
          with session token
              |
         +----+----+
         |         |
        YES        NO
         |         |
         v         v
      Continue    403
```

## 6. ZF1 Server-Side Validation

If the token is sent using the `X-CSRF-Token` header:

``` php
$session = new Zend_Session_Namespace('security');

$expectedToken = $session->csrfToken;
$receivedToken = $this->getRequest()->getHeader('X-CSRF-Token');

if (
    !$expectedToken ||
    !$receivedToken ||
    !hash_equals($expectedToken, $receivedToken)
) {
    throw new Zend_Controller_Action_Exception(
        'Invalid CSRF token',
        403
    );
}
```

If the token is sent as a normal POST field:

``` php
$session = new Zend_Session_Namespace('security');

$expectedToken = $session->csrfToken;
$receivedToken = $this->getRequest()->getPost('csrf_token');

if (
    !$expectedToken ||
    !$receivedToken ||
    !hash_equals($expectedToken, $receivedToken)
) {
    throw new Zend_Controller_Action_Exception(
        'Invalid CSRF token',
        403
    );
}
```

## 7. Multi-Tab Consideration

For an application where users commonly open multiple browser tabs,
avoid regenerating a single session CSRF token after every successful
request.

For example:

``` text
Initial session token = ABC123

Tab A                        Tab B
-----                        -----
ABC123                       ABC123

Submit
  |
  v
Server changes token
ABC123 -> XYZ789

                             Submit using ABC123
                                    |
                                    v
                             Token rejected
```

A stable session-scoped token avoids this particular problem:

``` text
Session token = ABC123

Tab A ---- ABC123 ----+
                      |
Tab B ---- ABC123 ----+----> Server compares with ABC123
                      |
Tab C ---- ABC123 ----+
```

The token should still be replaced when appropriate, such as when a new
authenticated session is established.

## 8. Global Variable vs. Meta Element

### Global variable

``` javascript
var csrfToken = "ABC123";
```

Advantages:

-   Simple
-   Easy to integrate with legacy JavaScript
-   Convenient for DHTMLX 3.5 code

Disadvantages:

-   Adds global state
-   Any same-origin JavaScript can directly reference it
-   Global variables can be accidentally overwritten

### Meta element

``` html
<meta name="csrf-token" content="ABC123">
```

Advantages:

-   Avoids adding another global JavaScript variable
-   Provides one consistent location for the token
-   Easy for different JavaScript modules to retrieve

Disadvantages:

-   It is still readable by same-origin JavaScript
-   It does not protect against a successful XSS attack

## 9. Important Security Point

A CSRF token is designed primarily to protect against **cross-site
request forgery**, not XSS.

If an attacker successfully runs JavaScript inside your application's
origin, that code may be able to read the CSRF token and submit
authenticated requests.

Therefore:

``` text
CSRF Token
    +
XSS Prevention
    +
Authorization
    +
Secure Session Management
    +
Input Validation
    +
Secure Cookies
    +
HTTPS
```

should be used together.

## 10. Recommendation for ZF1 + DHTMLX 3.5

Your existing global-variable approach is **not inherently wrong**.

If changing a large amount of legacy DHTMLX 3.5 code would be difficult,
you do not need to redesign the entire application simply because the
CSRF token is stored in a JavaScript global variable.

For new or refactored code, a `<meta>` element plus a small
`getCsrfToken()` function is a cleaner approach.

Most importantly:

1.  Generate the CSRF token using a cryptographically secure random
    generator.
2.  Keep the authoritative expected token in the server-side session.
3.  Include the token with every state-changing request.
4.  Validate it before processing `POST`, `PUT`, `PATCH`, or `DELETE`
    operations.
5.  Use a timing-safe comparison such as `hash_equals()`.
6.  Return an HTTP `403` response when validation fails.
7.  Do not regenerate a single session token after every request when
    multiple browser tabs need to work simultaneously.
8.  Protect the application against XSS.
9.  Use secure session cookie settings and HTTPS.
10. Perform authorization checks independently of CSRF validation.
