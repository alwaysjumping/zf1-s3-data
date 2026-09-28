# XSS (Cross-Site Scripting): Explanation and Prevention

## 1. What is XSS?

**XSS (Cross-Site Scripting)** is a web-security vulnerability where an
attacker gets malicious JavaScript or other executable markup into
content that another user's browser treats as trusted page code.

For applications using technologies such as **Zend Framework 1 (ZF1),
DHTMLX, JavaScript, and MariaDB**, XSS is especially important because
values may move through forms, grids, AJAX responses, and database
records before being displayed again.

------------------------------------------------------------------------

## 2. Simple XSS Example

Suppose an application accepts a user's name:

``` html
<input name="name">
```

An attacker enters:

``` html
<script>alert('XSS')</script>
```

The PHP application saves it:

``` php
$name = $_POST['name'];

$db->insert('users', array(
    'name' => $name
));
```

Later, the application displays it without escaping:

``` php
echo $user['name'];
```

The browser could receive:

``` html
Hello <script>alert('XSS')</script>
```

Instead of displaying the text, the browser executes the JavaScript.

A real attack could do more than display an alert. For example,
malicious JavaScript might:

-   Perform actions as the logged-in user
-   Modify the displayed page
-   Display a fake login form
-   Read information accessible to JavaScript
-   Send requests using the user's authenticated session

------------------------------------------------------------------------

## 3. Main Types of XSS

### 3.1 Stored XSS

Stored XSS occurs when malicious content is stored permanently, often in
a database.

Typical flow:

``` text
Attacker
   |
   v
POST form
   |
   v
PHP / ZF1 Controller
   |
   v
MariaDB
   |
   v
Another user opens the page
   |
   v
Malicious JavaScript executes
```

Example malicious input:

``` html
<img src=x onerror="alert('XSS')">
```

If this value is stored in MariaDB and later output without appropriate
encoding, the `onerror` handler may execute.

Stored XSS can be especially serious because one malicious database
record can affect multiple users.

------------------------------------------------------------------------

### 3.2 Reflected XSS

Reflected XSS happens when request input is immediately returned in the
generated page.

Example:

``` php
echo "Search: " . $_GET['q'];
```

If `q` contains malicious markup and the application outputs it
directly, a specially crafted request could cause JavaScript to execute.

Safer:

``` php
echo "Search: " . htmlspecialchars(
    $_GET['q'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
```

------------------------------------------------------------------------

### 3.3 DOM-Based XSS

DOM-based XSS occurs primarily in client-side JavaScript.

Dangerous example:

``` javascript
document.getElementById("result").innerHTML =
    location.hash.substring(1);
```

The application is inserting untrusted data directly into `innerHTML`.

Prefer:

``` javascript
document.getElementById("result").textContent =
    location.hash.substring(1);
```

------------------------------------------------------------------------

## 4. The Most Important Prevention Rule

Do not try to prevent XSS only by searching for or removing `<script>`
tags.

The main rule is:

> **Encode untrusted data according to the context where it is
> inserted.**

For normal HTML text in PHP:

``` php
echo htmlspecialchars(
    $user['name'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
```

If the database contains:

``` html
<script>alert('XSS')</script>
```

HTML encoding turns special characters into text representations, so the
browser displays the content instead of treating it as executable HTML.

------------------------------------------------------------------------

## 5. PHP / ZF1 Output Encoding

Avoid raw output of untrusted values:

``` php
echo $user['name'];
```

Prefer:

``` php
echo htmlspecialchars(
    $user['name'],
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
```

For a ZF1 project, it is useful to centralize escaping through view
helpers or another consistent output-encoding mechanism rather than
relying on developers to remember it for every individual `echo`.

------------------------------------------------------------------------

## 6. JavaScript Prevention

Avoid inserting untrusted content using `innerHTML`.

Dangerous:

``` javascript
element.innerHTML = userInput;
```

Prefer:

``` javascript
element.textContent = userInput;
```

With jQuery, avoid:

``` javascript
$("#name").html(userInput);
```

Prefer:

``` javascript
$("#name").text(userInput);
```

The same principle applies to DHTMLX components. If a grid cell,
template, tooltip, form label, or other component interprets a value as
HTML, make sure untrusted values are properly encoded or sanitized.

------------------------------------------------------------------------

## 7. JSON APIs

Returning JSON does not automatically prevent XSS.

PHP:

``` php
header('Content-Type: application/json; charset=UTF-8');

echo json_encode(array(
    'name' => $user['name']
));
```

JavaScript:

``` javascript
fetch('/api/user')
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        document.getElementById('name').textContent = data.name;
    });
```

Avoid:

``` javascript
document.getElementById('name').innerHTML = data.name;
```

Even though the value arrived through JSON, it can become dangerous if
it is later inserted into an HTML execution context.

------------------------------------------------------------------------

## 8. User-Generated HTML

Sometimes users legitimately need to enter formatted HTML, for example:

``` html
<p>Hello <strong>Sam</strong></p>
```

In this situation, simply encoding the entire value would prevent the
formatting from rendering.

Use an **HTML sanitizer** with an allowlist.

For example, the application might permit:

``` text
p
strong
em
ul
ol
li
```

while rejecting dangerous elements such as:

``` text
script
iframe
object
embed
```

Dangerous event attributes should also be rejected, including:

``` text
onclick
onerror
onload
onmouseover
```

Dangerous URL schemes such as the following must also be handled:

``` text
javascript:
```

Writing a complete HTML sanitizer yourself is difficult. A
well-maintained sanitizer library is generally safer.

------------------------------------------------------------------------

## 9. Defense in Depth

A good architecture uses several protection layers:

``` text
Browser
   |
   | Form / DHTMLX / AJAX
   v
ZF1 Controller
   |
   +-- Validate input
   |
   v
MariaDB
   |
   | Stored values remain untrusted
   v
ZF1 View / API
   |
   +-- Context-aware output encoding
   |
   v
JavaScript / DHTMLX
   |
   +-- textContent instead of innerHTML
   |
   v
Browser
   |
   +-- Content Security Policy
```

------------------------------------------------------------------------

## 10. Secure Session Cookies

Useful PHP session settings include:

``` ini
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Lax
```

### HttpOnly

`HttpOnly` prevents JavaScript from directly reading the session cookie.

However, this does **not** completely neutralize XSS. Malicious
JavaScript running inside the application may still be able to perform
authenticated requests as the victim.

### Secure

`Secure` tells the browser to send the cookie only over HTTPS.

### SameSite

`SameSite` provides additional protection against certain cross-site
request scenarios and is particularly useful as part of CSRF defenses.

------------------------------------------------------------------------

## 11. Content Security Policy (CSP)

CSP provides an additional defense against XSS.

A restrictive starting example is:

``` http
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'
```

This is only a starting point.

Legacy applications, including older DHTMLX applications, may depend
heavily on inline JavaScript. Therefore, CSP should first be tested
carefully before enforcing a strict production policy.

A practical migration can begin by identifying:

-   Inline `<script>` blocks
-   Inline event handlers such as `onclick`
-   Dynamically generated JavaScript
-   External script sources
-   `eval()` or similar dynamic execution
-   DHTMLX components that generate inline code

------------------------------------------------------------------------

## 12. XSS vs CSRF

XSS and CSRF are different vulnerabilities.

### XSS

XSS means attacker-controlled code executes within the trusted origin of
your application.

Primary protections include:

-   Output encoding
-   Safe DOM APIs
-   HTML sanitization
-   Content Security Policy

### CSRF

CSRF tricks an authenticated browser into sending an unwanted request.

Primary protections include:

-   CSRF tokens
-   SameSite cookies
-   Origin/Referer validation where appropriate

### Important

A CSRF token does **not** prevent XSS.

A successful XSS vulnerability may be able to access CSRF tokens that
are available to page JavaScript and then submit valid authenticated
requests.

Therefore, an application needs protection against **both XSS and
CSRF**.

------------------------------------------------------------------------

## 13. Recommended Audit for ZF1 + DHTMLX

For a legacy ZF1/DHTMLX application, search the source code for places
where untrusted values can enter HTML or JavaScript contexts.

Important PHP patterns include:

``` php
echo $value;
print $value;
printf(...);
```

Check whether values are properly encoded before output.

Important JavaScript patterns include:

``` javascript
innerHTML
outerHTML
insertAdjacentHTML()
document.write()
eval()
new Function()
```

If jQuery is used, check:

``` javascript
.html()
.append()
.prepend()
.after()
.before()
```

These methods are not automatically vulnerabilities, but they require
careful review when their arguments contain untrusted data.

Also review DHTMLX code that creates:

-   Grid cells
-   Form values
-   Tooltips
-   Labels
-   Templates
-   Windows
-   Popups
-   HTML-based cells
-   Dynamically generated content

------------------------------------------------------------------------

## 14. Practical Security Rules

For this type of application:

1.  Treat all external input as untrusted.
2.  Validate input on the server.
3.  Do not assume database content is trusted.
4.  Encode values when outputting them.
5.  Use context-appropriate encoding.
6.  Prefer `textContent` over `innerHTML`.
7.  Prefer `.text()` over jQuery `.html()` for plain text.
8.  Sanitize HTML when HTML input must be supported.
9.  Use secure cookie settings.
10. Implement a suitable CSP.
11. Protect state-changing requests against CSRF.
12. Regularly review legacy DHTMLX and JavaScript code for unsafe DOM
    operations.

------------------------------------------------------------------------

## 15. Key Principle

The safest mental model is:

``` text
USER INPUT
    |
    v
UNTRUSTED DATA
    |
    v
VALIDATE
    |
    v
DATABASE
    |
    |  Still untrusted
    v
OUTPUT ENCODING / SANITIZATION
    |
    v
SAFE HTML / DOM OUTPUT
```

**Data does not become trusted merely because it has been stored in your
database.**

Always consider the context where the data will eventually be rendered.
