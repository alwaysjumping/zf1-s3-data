# CSRF Token: Why It Is Needed and How It Works

A **CSRF token** protects a website from **Cross-Site Request Forgery
(CSRF)**---an attack where another website tricks a logged-in user's
browser into sending an unwanted request to your application.

This is especially important for a Zend Framework 1 (ZF1) application
because operations such as **create, update, and delete** may rely on
the user's authenticated session.

## 1. The Basic Problem

Suppose your application has this endpoint:

``` text
POST /user/delete
```

A logged-in administrator can delete a user by sending:

``` text
POST /user/delete
id=100
```

After login, the browser has a session cookie:

``` text
PHPSESSID=abc123...
```

Normally:

``` text
Browser
   |
   | POST /user/delete
   | Cookie: PHPSESSID=abc123
   v
Your Server
```

The server sees the valid session cookie and knows which user is logged
in.

The problem is that browsers can sometimes send authentication cookies
automatically even when a request was initiated from another website.

## 2. How a CSRF Attack Works

Imagine the administrator is logged into:

``` text
https://internal.example.com
```

Then the administrator visits a malicious page.

That page attempts to cause the browser to submit something like:

``` html
<form action="https://internal.example.com/user/delete"
      method="POST">
    <input type="hidden" name="id" value="100">
</form>

<script>
document.forms[0].submit();
</script>
```

Conceptually:

``` text
Administrator
     |
     | logged in
     v
Your Application
     |
     | session cookie exists in browser
     |
     +----------------------------+
                                  |
Administrator visits             |
malicious website                 |
     |                            |
     v                            |
Attacker's Website                |
     |                            |
     | forged POST                |
     v                            |
Your Application <---------------+
     |
     | "Valid session!"
     v
Delete user 100
```

The attacker does **not necessarily need to know the user's session
ID**.

The important problem is that the victim's browser may automatically
provide authentication credentials, depending on cookie settings and
request context.

Modern `SameSite` cookies significantly reduce many traditional CSRF
scenarios, but they should generally be treated as an additional defense
rather than the sole protection for sensitive state-changing operations.

## 3. How a CSRF Token Solves the Problem

When the legitimate user loads your form, your server generates a
cryptographically random value:

``` text
9d8e31c84f...
```

The server associates it with the user's session:

``` php
$_SESSION['csrf_token'] = '9d8e31c84f...';
```

And puts the token in the legitimate page:

``` html
<form method="POST" action="/user/delete">

    <input type="hidden"
           name="csrf_token"
           value="9d8e31c84f...">

    <input type="hidden"
           name="id"
           value="100">

    <button type="submit">Delete</button>

</form>
```

Now a legitimate request contains both:

``` text
Cookie:
PHPSESSID=abc123

POST body:
id=100
csrf_token=9d8e31c84f...
```

The server verifies the token before performing the operation.

``` php
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid CSRF token');
}
```

## 4. Why the Attacker Cannot Simply Send a Token

The malicious website can attempt:

``` text
POST /user/delete

id=100
csrf_token=???
```

But it normally cannot read the CSRF token from your application's page
because of the browser's **same-origin policy**.

So the server receives:

``` text
Session cookie:  valid
CSRF token:      missing/wrong
```

and rejects the request:

``` text
403 Forbidden
```

This gives you two separate requirements:

``` text
Authentication
      +
CSRF verification
      |
      v
Operation allowed
```

A valid session answers:

> **Who is making this request?**

The CSRF token helps establish:

> **Did this state-changing request originate through a flow authorized
> by my application?**

## 5. Typical Request Flow

For a ZF1 application, you can think of it this way:

``` text
1. User logs in
       |
       v
2. Server creates session
       |
       +--> PHPSESSID
       |
       +--> CSRF token
              |
              v
3. User opens form
       |
       v
4. Server puts CSRF token into page
       |
       v
5. User clicks Save
       |
       | POST
       | PHPSESSID
       | csrf_token
       v
6. ZF1 receives request
       |
       +--> Is session valid? ---- No ---> Reject
       |
      Yes
       |
       +--> Is CSRF valid? ------- No ---> 403
       |
      Yes
       |
       v
7. Validate authorization/input
       |
       v
8. Update MariaDB
```

## 6. Which Requests Need CSRF Protection?

Generally, protect requests that **change server state**:

  HTTP Method   Typical Purpose   CSRF Check
  ------------- ----------------- ------------
  `GET`         Read data         Usually no
  `POST`        Create/change     **Yes**
  `PUT`         Update            **Yes**
  `PATCH`       Partial update    **Yes**
  `DELETE`      Delete            **Yes**

Avoid using `GET` for destructive operations.

Do not design:

``` text
GET /user/delete?id=100
```

Prefer something like:

``` text
DELETE /user/100
```

or, in an older application:

``` text
POST /user/delete
```

with CSRF validation.

## 7. Important Distinction: CSRF vs. XSS

A CSRF token is **not** a general protection against XSS.

CSRF:

``` text
Attacker's website
      |
      | forged request
      v
Your website
```

XSS:

``` text
Malicious JavaScript
      |
      v
runs inside
YOUR website
```

If an attacker successfully executes JavaScript in your application's
origin through XSS, that script may be able to read CSRF tokens from the
page and send authenticated requests.

Therefore, you need multiple layers of security:

``` text
Session security
      +
CSRF protection
      +
XSS prevention
      +
Authorization
      +
Input validation
      +
Secure cookies
```

## 8. CSRF Protection for ZF1 + DHTMLX

Because DHTMLX forms and grids send `POST` requests, a practical design
is:

``` text
Login
  |
  v
PHP Session
  |
  +---- CSRF token
  |
  v
DHTMLX page
  |
  +---- dhxForm
  |        |
  |        +---- csrf_token
  |
  +---- dhxGrid
           |
           +---- csrf_token
                    |
                    v
              ZF1 Controller
                    |
              CSRF validation
                    |
             +------+------+
             |             |
           valid         invalid
             |             |
             v             v
          Process        HTTP 403
```

### Multi-Tab Consideration

One particularly important design choice is **not to regenerate a single
session CSRF token after every form submission**.

Doing that can cause a multi-browser-tab problem:

``` text
Tab A                         Tab B
-----                         -----
Token = ABC                   Token = ABC

Submit form
   |
   v
Server changes token
ABC -> XYZ

                              Submit form with ABC
                                     |
                                     v
                              Token rejected
```

For a ZF1 application with DHTMLX 3.5 and multiple browser tabs, a
**stable session-scoped token**, or another carefully designed
multi-token strategy, is usually easier to work with.

## 9. Summary

A CSRF token is needed because authentication cookies may be sent
automatically by the browser.

Without CSRF protection:

``` text
Valid session cookie
        |
        v
Server may accept forged request
```

With CSRF protection:

``` text
Valid session cookie
        +
Valid CSRF token
        |
        v
Request accepted
```

A strong CSRF design should be combined with:

-   Secure session management
-   `SameSite` cookies
-   `Secure` and `HttpOnly` cookie attributes where appropriate
-   XSS prevention
-   Authorization checks
-   Input validation
-   Correct HTTP methods
-   HTTPS

For a legacy **Zend Framework 1 + DHTMLX 3.5 + MariaDB** application,
CSRF validation should normally be centralized so that state-changing
requests are checked consistently.
