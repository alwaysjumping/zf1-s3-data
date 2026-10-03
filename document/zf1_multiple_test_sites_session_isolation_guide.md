# ZF1 Multiple Test Sites: Separate PHP Sessions in One Browser

## 1. Problem

During ZF1 development, several test sites may run on the same computer
using different ports:

``` text
http://localhost:8001
http://localhost:8002
http://localhost:8003
```

Even though the ports are different, the sites can appear to share or
overwrite the same PHP session in one browser.

This often forces developers to use different browsers such as Chrome,
Firefox, and Opera for different test sites.

A cleaner solution is to give each ZF1 test application its own
**session cookie name**.

------------------------------------------------------------------------

## 2. Why Different Ports Do Not Separate Cookies

A browser cookie is associated with properties such as:

-   domain/host
-   path
-   cookie name
-   security attributes

The TCP port is not used as a cookie-isolation boundary.

Therefore, these applications:

``` text
http://localhost:8001
http://localhost:8002
http://localhost:8003
```

all use the same host:

``` text
localhost
```

If every application uses the default PHP session cookie name:

``` text
PHPSESSID
```

the applications can interfere with each other's session cookie.

Conceptually:

``` text
localhost:8001
    PHPSESSID = AAA

localhost:8002
    PHPSESSID = BBB

localhost:8003
    PHPSESSID = CCC
```

Because the browser does not isolate these cookies by port, this can
cause unexpected login/session behavior.

------------------------------------------------------------------------

## 3. Recommended ZF1 Solution

Give every test application a unique session name.

For example:

``` text
Test Site 1
Port: 8001
Session cookie: S3_TEST1_SESSION

Test Site 2
Port: 8002
Session cookie: S3_TEST2_SESSION

Test Site 3
Port: 8003
Session cookie: S3_TEST3_SESSION
```

The browser can then store separate cookies for the same host because
the cookie names are different.

``` text
localhost

S3_TEST1_SESSION = AAA
S3_TEST2_SESSION = BBB
S3_TEST3_SESSION = CCC
```

------------------------------------------------------------------------

## 4. ZF1 `application.ini` Configuration

ZF1 provides the `Zend_Application` session resource.

The session name can be configured in `application.ini` with:

``` ini
resources.session.name = "S3_TEST1_SESSION"
```

For three test applications:

### Test Site 1

``` ini
resources.session.name = "S3_TEST1_SESSION"
```

URL:

``` text
http://localhost:8001
```

### Test Site 2

``` ini
resources.session.name = "S3_TEST2_SESSION"
```

URL:

``` text
http://localhost:8002
```

### Test Site 3

``` ini
resources.session.name = "S3_TEST3_SESSION"
```

URL:

``` text
http://localhost:8003
```

Now all three applications can normally be opened in the same browser.

------------------------------------------------------------------------

## 5. Is `resources.session.name` a Normal ZF1 Setting?

Yes.

It is part of the ZF1 `Zend_Application` session-resource configuration
approach.

The configuration:

``` ini
resources.session.name = "S3_TEST1_SESSION"
```

configures the PHP/Zend session name through the ZF1 session resource.

Its purpose is comparable to configuring the session option in PHP code:

``` php
Zend_Session::setOptions(array(
    'name' => 'S3_TEST1_SESSION'
));
```

Using `application.ini` is usually cleaner when the application already
uses `Zend_Application` resources.

------------------------------------------------------------------------

## 6. Important: Configure the Name Before the Session Starts

The session name must be configured before the application starts the
PHP/Zend session.

Avoid doing this:

``` php
Zend_Session::start();

// Too late to change fundamental session configuration here.
Zend_Session::setOptions(array(
    'name' => 'S3_TEST1_SESSION'
));
```

Prefer configuration through `application.ini` and allow the ZF1
bootstrap/resource system to initialize the session correctly.

If the application manually calls:

``` php
Zend_Session::start();
```

very early in `public/index.php` or `Bootstrap.php`, review the
initialization order to ensure the session options are applied first.

------------------------------------------------------------------------

## 7. Environment-Specific Session Names

For a development environment with many copies of the same application,
use a clear naming convention.

Example:

``` text
S3_DEV_SESSION
S3_TEST1_SESSION
S3_TEST2_SESSION
S3_TEST3_SESSION
S3_STAGING_SESSION
```

Or, if test installations correspond to particular projects:

``` text
S3_EMAIL_TEST_SESSION
S3_API_TEST_SESSION
S3_SECURITY_TEST_SESSION
```

The important requirement is that applications sharing the same hostname
use different session cookie names when their sessions must remain
independent.

------------------------------------------------------------------------

## 8. Example `application.ini`

A test configuration could contain:

``` ini
[production]

resources.session.name = "S3_SESSION"
resources.session.use_only_cookies = true
resources.session.cookie_httponly = true


[development : production]

resources.session.name = "S3_DEV_SESSION"


[test1 : development]

resources.session.name = "S3_TEST1_SESSION"


[test2 : development]

resources.session.name = "S3_TEST2_SESSION"
```

The exact environment structure depends on how the existing ZF1 project
selects its application environment.

------------------------------------------------------------------------

## 9. Useful Session Cookie Settings

In addition to a unique session name, session cookie security should be
considered.

Example:

``` ini
resources.session.name = "S3_TEST1_SESSION"
resources.session.use_only_cookies = true
resources.session.cookie_httponly = true
```

For an HTTPS environment:

``` ini
resources.session.cookie_secure = true
```

Do not enable `cookie_secure = true` on a plain HTTP-only local test
site, because a Secure cookie is intended to be sent over HTTPS.

------------------------------------------------------------------------

## 10. Alternative: Different Development Hostnames

Another clean solution is to use different local hostnames rather than
only different ports.

For example:

``` text
test-a.local
test-b.local
test-c.local
```

On Windows, local hostname mappings can be added to:

``` text
C:\Windows\System32\drivers\etc\hosts
```

Example:

``` text
127.0.0.1 test-a.local
127.0.0.1 test-b.local
127.0.0.1 test-c.local
```

Apache virtual hosts can then map each hostname to a different
application.

Example:

``` apache
<VirtualHost *:80>
    ServerName test-a.local
    DocumentRoot "E:/zf1-test-a/public"
</VirtualHost>

<VirtualHost *:80>
    ServerName test-b.local
    DocumentRoot "E:/zf1-test-b/public"
</VirtualHost>

<VirtualHost *:80>
    ServerName test-c.local
    DocumentRoot "E:/zf1-test-c/public"
</VirtualHost>
```

The browser then sees separate hosts:

``` text
test-a.local
test-b.local
test-c.local
```

which provides more natural cookie separation.

------------------------------------------------------------------------

## 11. Which Solution Should Be Used?

For an existing setup that already uses:

``` text
localhost:8001
localhost:8002
localhost:8003
```

the simplest solution is:

``` ini
resources.session.name = "UNIQUE_SESSION_NAME"
```

for each application.

This requires very little infrastructure change.

For a larger or more permanent development environment, separate local
hostnames can provide cleaner isolation.

Both approaches can also be combined:

``` text
test-a.local
    S3_TEST_A_SESSION

test-b.local
    S3_TEST_B_SESSION
```

This makes the environment explicit and reduces accidental session
collisions.

------------------------------------------------------------------------

## 12. Recommended Setup

For the current ZF1 test environment:

``` text
http://localhost:8001
    resources.session.name = "S3_TEST1_SESSION"

http://localhost:8002
    resources.session.name = "S3_TEST2_SESSION"

http://localhost:8003
    resources.session.name = "S3_TEST3_SESSION"
```

Then use all test applications in the same browser.

``` text
Chrome
  |
  +-- localhost:8001 --> S3_TEST1_SESSION
  |
  +-- localhost:8002 --> S3_TEST2_SESSION
  |
  +-- localhost:8003 --> S3_TEST3_SESSION
```

There is no need to use Chrome for one application, Firefox for another,
and Opera for another solely to separate these PHP sessions.

------------------------------------------------------------------------

## 13. Relationship to Authentication and CSRF

Separating the session cookie is especially important when the ZF1
application stores information such as:

``` text
Zend_Auth identity
CSRF token
user ID
permissions
temporary form state
session configuration
```

in the PHP session.

Without session isolation, one test application can appear to affect
another application's authentication or CSRF state.

With unique session names:

``` text
Test Site A
    |
    +-- independent login
    +-- independent Zend_Auth state
    +-- independent CSRF state
    +-- independent session data

Test Site B
    |
    +-- independent login
    +-- independent Zend_Auth state
    +-- independent CSRF state
    +-- independent session data
```

This is much safer and more convenient for testing.

------------------------------------------------------------------------

## 14. Final Recommendation

Use a unique ZF1 session name for every test-site instance.

Example:

``` ini
; Test Site A
resources.session.name = "S3_TEST_A_SESSION"
```

``` ini
; Test Site B
resources.session.name = "S3_TEST_B_SESSION"
```

``` ini
; Test Site C
resources.session.name = "S3_TEST_C_SESSION"
```

This is the smallest change for the existing multi-port environment and
allows multiple ZF1 test applications to remain logged in independently
in a single browser.
