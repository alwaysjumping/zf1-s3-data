# Apache and PHP Security Hardening Guide

## 1. Purpose

This document provides a practical security-hardening guide for an Apache + PHP web application, with special consideration for a legacy Zend Framework 1 (ZF1) application using PHP 7.4, DHTMLX 3.5, and MariaDB.

> **Important:** PHP 7.4 is end-of-life. Hardening Apache and PHP is valuable, but configuration cannot replace security updates. A migration to a supported PHP version should be part of the security plan.

---

## 2. Security Layers

Security should be implemented in layers:

```text
Client / Browser
      |
      | HTTPS
      v
+-----------------------------+
| Apache                      |
| - TLS                       |
| - Directory protection      |
| - Request restrictions      |
| - Security headers          |
| - Request limits            |
+-------------+---------------+
              |
              v
+-----------------------------+
| PHP                         |
| - Runtime restrictions      |
| - Error handling            |
| - Session security          |
| - Upload restrictions       |
| - Resource limits           |
+-------------+---------------+
              |
              v
+-----------------------------+
| Zend Framework 1            |
| - Authentication            |
| - Authorization             |
| - CSRF protection           |
| - Input validation          |
| - Output escaping           |
+-------------+---------------+
              |
              v
           MariaDB
```

Apache/PHP configuration is defense in depth. It cannot by itself prevent SQL injection, XSS, broken authorization, insecure uploads, or application logic flaws.

---

# Part I — Apache Security

## 3. Keep Apache and Its Dependencies Updated

Regularly maintain:

- Apache
- OpenSSL
- PHP
- MariaDB
- Operating system
- Composer/application dependencies

Configuration hardening reduces attack surface; security updates fix known vulnerabilities.

---

## 4. Hide Detailed Apache Information

Use:

```apache
ServerTokens Prod
ServerSignature Off
```

`ServerTokens Prod` minimizes server information exposed in HTTP responses. `ServerSignature Off` prevents Apache-generated pages from displaying detailed server information.

These settings reduce information disclosure but are not substitutes for patching.

---

## 5. Disable HTTP TRACE

```apache
TraceEnable Off
```

TRACE is normally unnecessary for an application.

Do not blindly disable methods such as `PUT`, `PATCH`, `DELETE`, or `OPTIONS` if your API legitimately requires them.

---

## 6. Set the DocumentRoot to `public/`

A recommended ZF1 structure is:

```text
C:\sites\myproject\
|
+-- application\
+-- library\
+-- vendor\
+-- tests\
+-- storage\
+-- public\
    +-- index.php
    +-- css\
    +-- js\
    +-- images\
```

Do not expose the whole project:

```apache
DocumentRoot "C:/sites/myproject"
```

Prefer:

```apache
DocumentRoot "C:/sites/myproject/public"
```

This prevents direct web access to `application/`, `library/`, `vendor/`, `tests/`, and `storage/`.

---

## 7. Disable Directory Listings

Use:

```apache
Options -Indexes
```

Without this setting, a directory without an index file might expose a listing of files such as backups, documents, or logs.

---

## 8. Configure Directory Access Carefully

Example:

```apache
<Directory "C:/sites/myproject/public">
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>
```

Enable additional options such as symbolic-link following only when required.

---

## 9. Prefer Server Configuration Over `.htaccess`

If you control Apache, prefer:

```apache
AllowOverride None
```

and place rewrite/security configuration directly in the virtual host.

Legacy applications may depend on `.htaccess`. If so, migrate its rules first or allow only the override classes actually required rather than automatically using `AllowOverride All`.

---

## 10. ZF1 Front-Controller Rewriting

A typical configuration can route non-file requests to `index.php`:

```apache
<Directory "C:/sites/myproject/public">
    Options -Indexes
    AllowOverride None
    Require all granted

    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} -s [OR]
    RewriteCond %{REQUEST_FILENAME} -l [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    RewriteRule ^ index.php [L]
</Directory>
```

Test rewrite behavior against the existing application before production deployment.

---

## 11. Protect Hidden Files

Defense in depth:

```apache
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

This helps protect files such as `.env`, `.git`, and `.htpasswd`.

The stronger design is still to keep sensitive files outside `public/`.

---

## 12. Protect Sensitive File Types

For example:

```apache
<FilesMatch "\.(ini|log|sql|bak|conf)$">
    Require all denied
</FilesMatch>
```

Sensitive files include:

```text
application.ini
database.sql
debug.log
index.php.bak
```

Do not depend only on extension blocking. Sensitive data should normally live outside the web root.

---

## 13. Protect Git Metadata

Never expose `.git/`.

An exposed Git repository may reveal current and historical source code, configuration, filenames, and credentials accidentally committed in the past.

Correct `DocumentRoot` design normally prevents this.

---

## 14. Use HTTPS

HTTPS protects data in transit, including:

- usernames/passwords
- session cookies
- JWTs
- documents
- API requests and responses

Redirect HTTP to HTTPS where appropriate:

```apache
<VirtualHost *:80>
    ServerName app.example.local
    Redirect permanent / https://app.example.local/
</VirtualHost>
```

---

## 15. TLS Configuration

Disable obsolete SSL/TLS protocols where client compatibility permits.

Conceptually:

```text
Disable:
- SSLv2
- SSLv3
- TLS 1.0
- TLS 1.1

Use currently supported modern TLS versions.
```

The exact cipher/protocol configuration should match the Apache and OpenSSL versions deployed.

---

## 16. HSTS

After confirming the site is permanently HTTPS-only:

```apache
Header always set Strict-Transport-Security "max-age=31536000"
```

Do not enable HSTS prematurely on a hostname that must still support HTTP.

---

## 17. `X-Content-Type-Options`

Recommended:

```apache
Header always set X-Content-Type-Options "nosniff"
```

This reduces MIME-sniffing behavior. Correct server MIME types are still required.

---

## 18. Referrer Policy

A useful starting point is:

```apache
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

A more restrictive policy may be appropriate for highly sensitive systems after testing.

---

## 19. Clickjacking / Frame Protection

Modern CSP can restrict framing:

```apache
Header always set Content-Security-Policy "frame-ancestors 'self'"
```

A traditional header is:

```apache
Header always set X-Frame-Options "SAMEORIGIN"
```

Because legacy DHTMLX applications may use iframes, frame restrictions must be tested carefully before deployment.

---

## 20. Content Security Policy (CSP)

A starting policy could resemble:

```apache
Header always set Content-Security-Policy "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
```

Do not deploy a strict CSP directly into a legacy application without testing. DHTMLX/legacy code may use:

- inline JavaScript
- inline styles
- iframes
- dynamically generated scripts
- old event-handler patterns

Recommended process:

```text
Inventory current behavior
        |
        v
Create initial policy
        |
        v
Test
        |
        v
Find violations
        |
        v
Modernize problematic code
        |
        v
Tighten CSP
```

---

## 21. Permissions Policy

Restrict browser capabilities that the application does not need, such as camera or microphone access.

Choose directives based on actual application requirements rather than copying a generic restrictive policy.

---

## 22. Request Body Limits

Apache can restrict request size:

```apache
LimitRequestBody 10485760
```

The example is approximately 10 MB.

Coordinate limits across layers:

```text
Apache LimitRequestBody
        |
        v
PHP post_max_size
        |
        v
PHP upload_max_filesize
        |
        v
Application validation
```

Choose values according to legitimate upload requirements.

---

## 23. Timeouts

Configure reasonable Apache connection/request timeouts.

Avoid unnecessarily long global timeouts because slow or idle connections consume resources. Heavy document-processing endpoints may require separate handling.

---

## 24. Disable Unnecessary Apache Modules

Enable only modules required by the application.

Commonly required modules may include:

```text
mod_rewrite
mod_headers
mod_ssl
```

Do not enable modules merely because they are installed.

---

## 25. Apache OS Permissions

The Apache service account should have minimum privileges.

Typical policy:

```text
Application source:
    Read

Writable storage/cache/temp:
    Read + Write only where required

Operating-system and unrelated application files:
    No unnecessary access
```

Do not run Apache as Administrator/root merely to avoid file-permission problems.

---

# Part II — PHP Security

## 26. Hide PHP Information

```ini
expose_php = Off
```

This reduces unnecessary implementation information in responses.

---

## 27. Production Error Display

Production:

```ini
display_errors = Off
display_startup_errors = Off
```

Development may enable detailed error display, but production users should never receive stack traces, database errors, server paths, or internal class/file information.

---

## 28. Enable Protected Error Logging

```ini
log_errors = On
error_log = "C:\logs\php\php-error.log"
```

Store logs outside the web root and protect them with operating-system permissions.

Logs may contain paths, stack traces, SQL errors, user identifiers, and other sensitive diagnostic data.

---

## 29. Error Reporting vs Error Display

Turning off `display_errors` does not mean errors should be ignored.

Recommended architecture:

```text
Browser
   |
   +--> Generic safe error response

Protected server log
   |
   +--> Detailed diagnostic information
```

---

# Part III — PHP Session Security

## 30. Cookies Only

```ini
session.use_only_cookies = 1
```

Avoid propagating PHP session IDs through URLs.

---

## 31. Disable Transparent Session IDs

```ini
session.use_trans_sid = 0
```

Avoid URLs such as:

```text
/account?PHPSESSID=abcdef...
```

URL-based session IDs can leak through browser history, logs, copied links, and referrer behavior.

---

## 32. Strict Session Mode

```ini
session.use_strict_mode = 1
```

This causes PHP to reject uninitialized session IDs rather than simply accepting arbitrary identifiers supplied by a client.

It contributes to session-fixation protection.

---

## 33. HttpOnly Session Cookies

```ini
session.cookie_httponly = 1
```

This prevents ordinary JavaScript from reading the session cookie.

It reduces one consequence of some XSS attacks, but does not make XSS harmless.

---

## 34. Secure Session Cookies

For HTTPS applications:

```ini
session.cookie_secure = 1
```

This instructs browsers to send the session cookie only over secure HTTPS connections.

---

## 35. SameSite

A practical starting point:

```ini
session.cookie_samesite = "Lax"
```

Available policies include:

```text
Strict
Lax
None
```

Choose the value based on legitimate cross-site authentication/application flows.

---

## 36. Recommended Session Baseline

For HTTPS production:

```ini
session.use_only_cookies = 1
session.use_strict_mode = 1
session.use_trans_sid = 0

session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
```

---

## 37. Regenerate Session IDs

After successful authentication or privilege elevation, regenerate the session identifier.

Conceptually:

```text
Anonymous session
      |
      v
Successful login
      |
      v
Regenerate session ID
      |
      v
Authenticated session
```

This is application-level behavior and helps mitigate session fixation.

---

## 38. Session Expiration

Sensitive sessions should not remain valid indefinitely.

Use application-level policies for:

- inactivity timeout
- absolute session lifetime
- reauthentication for highly sensitive operations

Do not rely solely on PHP garbage collection as a precise security timeout mechanism.

---

# Part IV — PHP Runtime Restrictions

## 39. Remote Includes

Recommended:

```ini
allow_url_include = Off
```

Do not dynamically include user-controlled paths:

```php
include $_GET['file'];
```

Configuration cannot make fundamentally unsafe include logic safe.

---

## 40. `allow_url_fopen`

If not needed:

```ini
allow_url_fopen = Off
```

However, some libraries legitimately use URL-aware streams. Evaluate application requirements before disabling it.

---

## 41. `open_basedir`

An optional defense-in-depth restriction:

```ini
open_basedir = "C:\sites\myproject;C:\sites\storage;C:\temp"
```

Test carefully because frameworks and libraries may need access to temporary directories, Composer packages, certificates, cache, uploads, or logs.

OS-level permissions remain the stronger filesystem boundary.

---

## 42. `disable_functions`

If the application never requires process execution, consider disabling unnecessary functions such as:

```text
exec
system
shell_exec
passthru
proc_open
popen
```

Do not blindly copy a large Internet list. Document/PDF conversion or other application tools may legitimately need process execution.

`disable_functions` is defense in depth, not a security sandbox.

---

## 43. Runtime Extension Loading

For normal production environments:

```ini
enable_dl = Off
```

Approved PHP extensions should normally be loaded through server configuration.

---

## 44. CGI/FastCGI Path Information

Where relevant to the PHP handler:

```ini
cgi.fix_pathinfo = 0
```

Its security relevance depends on how Apache invokes PHP, so treat this as deployment-specific rather than universal.

---

# Part V — File Upload Security

## 45. Disable Uploads When Not Needed

If uploads are unnecessary:

```ini
file_uploads = Off
```

Otherwise:

```ini
file_uploads = On
```

and harden the complete upload pipeline.

---

## 46. Upload Size

Example:

```ini
upload_max_filesize = 10M
```

Choose the value according to legitimate application requirements.

---

## 47. POST Size

Example:

```ini
post_max_size = 12M
```

`post_max_size` must accommodate the complete request, not only the uploaded file.

---

## 48. Number of Uploaded Files

Example:

```ini
max_file_uploads = 5
```

Do not permit an unnecessarily large number of files per request.

---

## 49. Upload Temporary Directory

```ini
upload_tmp_dir = "C:\php-temp\uploads"
```

The directory should:

- not be web-accessible
- have restrictive OS permissions
- be writable only by accounts/processes that require it

---

## 50. Never Trust the Client Filename

Avoid directly using:

```php
$_FILES['file']['name']
```

as the physical storage filename.

Prefer:

```text
User filename:
    quarterly-report.pdf

Physical server filename:
    generated-random-identifier.dat

Database:
    original_name = quarterly-report.pdf
    storage_name  = generated identifier
```

---

## 51. Never Trust Client MIME Information Alone

Do not treat:

```php
$_FILES['file']['type']
```

as authoritative.

Validate file content/type on the server according to application policy.

---

## 52. Store Uploads Outside `public/`

Prefer:

```text
myproject/
+-- application/
+-- public/
+-- storage/
    +-- uploads/
```

instead of:

```text
public/
+-- uploads/
```

Then deliver protected documents through application authorization:

```text
GET /document/view/id/123
           |
           v
Authentication
           |
           v
Authorization
           |
           v
Locate protected file
           |
           v
Return authorized response
```

---

# Part VI — PHP Resource Limits

## 53. Memory

Example:

```ini
memory_limit = 256M
```

This prevents an individual PHP process/request from consuming unlimited memory.

Tune it according to real workloads. PDF/image processing may need more memory than ordinary CRUD operations.

---

## 54. Execution Time

Example:

```ini
max_execution_time = 30
```

Do not make the global timeout extremely large merely because one operation is slow. Consider isolating heavy background/document-processing work where practical.

---

## 55. Input Parsing Time

Example:

```ini
max_input_time = 60
```

Tune this according to upload/request behavior.

---

## 56. Input Variable Count

Example:

```ini
max_input_vars = 1000
```

Legacy DHTMLX forms/grids may submit many parameters. Measure the application before lowering this value aggressively.

---

## 57. Input Nesting

Keep `max_input_nesting_level` at a reasonable value unless the application genuinely requires deeply nested request structures.

---

## 58. Network Timeouts

External network calls should have explicit, reasonable connection and request timeouts.

For HTTP client libraries, configure timeouts in application code rather than allowing remote failures to hold PHP workers indefinitely.

---

# Part VII — Application Security That Apache/PHP Cannot Replace

## 59. Database Least Privilege

Do not run the web application using MariaDB `root`.

Use a dedicated application account and grant only the permissions required.

Typical CRUD permissions may include:

```text
SELECT
INSERT
UPDATE
DELETE
```

Administrative database permissions should not be granted without a real need.

---

## 60. SQL Injection Prevention

This is unsafe:

```php
$sql = "
    SELECT *
    FROM users
    WHERE username = '" . $_POST['username'] . "'
";
```

Use parameterized database queries.

Recommended flow:

```text
Request
   |
   v
Validation
   |
   v
Service
   |
   v
Repository / DbTable
   |
   v
Bound parameters
   |
   v
MariaDB
```

---

## 61. XSS Prevention

For HTML text context:

```php
echo htmlspecialchars(
    $value,
    ENT_QUOTES,
    'UTF-8'
);
```

Output encoding is context-sensitive. HTML text, attributes, JavaScript, URLs, and CSS have different requirements.

CSP is additional protection; it does not replace correct output encoding.

---

## 62. CSRF Protection

For cookie/session-authenticated applications, protect state-changing requests.

Typical targets include:

```text
POST
PUT
PATCH
DELETE
```

Architecture:

```text
Login session
      |
      +-- Session cookie
      |
      +-- CSRF token
              |
              v
       State-changing request
              |
              v
       Validate CSRF token
```

For a multi-tab ZF1 application, a stable session-bound CSRF token is generally easier to manage than invalidating the token after every request.

---

## 63. Authentication and Authorization

Authentication answers:

```text
Who is the user?
```

Authorization answers:

```text
Is this user allowed to perform this operation?
```

Every sensitive server operation must perform authorization checks.

Hiding a button with CSS or JavaScript is not authorization.

---

## 64. Password Storage

Do not store passwords using plain MD5, SHA-1, or a simple SHA-256 hash.

Use PHP's password API:

```php
$hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);
```

Verification:

```php
if (password_verify($password, $hash)) {
    // Password is valid.
}
```

---

## 65. Secret Management

Never expose secrets through:

```text
JavaScript
HTML
public/
Git
URLs
Error responses
```

Examples of secrets:

```text
Database passwords
JWT signing secrets
Private keys
API secrets
Encryption keys
```

Keep them in protected server-side configuration and restrict filesystem access.

---

# Part VIII — Logging and Monitoring

## 66. Security Events to Log

Useful audit events include:

- successful and failed logins
- authorization failures
- administrative changes
- sensitive document access
- uploads
- security-validation failures
- refresh-token revocation
- important configuration changes

---

## 67. Data That Should Not Be Logged

Avoid logging:

- passwords
- complete session IDs
- private keys
- raw refresh tokens
- encryption keys
- sensitive document contents

Protect logs with OS permissions and a suitable retention policy.

---

# Part IX — Development vs Production

## 68. Development

Development may use:

```text
Detailed errors
Xdebug
Developer diagnostics
Verbose logging
```

---

## 69. Production

Production should use:

```text
display_errors = Off
Protected server-side logs
No Xdebug
HTTPS
Secure cookies
Minimum information disclosure
Restricted filesystem permissions
```

Xdebug should normally not be loaded on a production server.

---

# Part X — Suggested PHP Production Baseline

## 70. Example `php.ini`

The following is a starting template, not a universal configuration:

```ini
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; PHP Production Security Baseline
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

; ------------------------------------------------------------
; Information exposure
; ------------------------------------------------------------

expose_php = Off


; ------------------------------------------------------------
; Error handling
; ------------------------------------------------------------

display_errors = Off
display_startup_errors = Off

log_errors = On
error_log = "C:\logs\php\php-error.log"


; ------------------------------------------------------------
; Session security
; ------------------------------------------------------------

session.use_only_cookies = 1
session.use_strict_mode = 1
session.use_trans_sid = 0

session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"


; ------------------------------------------------------------
; Remote inclusion
; ------------------------------------------------------------

allow_url_include = Off


; ------------------------------------------------------------
; Runtime extension loading
; ------------------------------------------------------------

enable_dl = Off


; ------------------------------------------------------------
; Uploads
; Adjust for actual application requirements
; ------------------------------------------------------------

file_uploads = On

upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 5

upload_tmp_dir = "C:\php-temp\uploads"


; ------------------------------------------------------------
; Temporary files
; ------------------------------------------------------------

sys_temp_dir = "C:\php-temp"


; ------------------------------------------------------------
; Resource limits
; Tune after measuring the application
; ------------------------------------------------------------

memory_limit = 256M

max_execution_time = 30
max_input_time = 60

max_input_vars = 1000


; ------------------------------------------------------------
; CGI/FastCGI
; Relevant only depending on deployment
; ------------------------------------------------------------

cgi.fix_pathinfo = 0
```

Values such as `10M`, `12M`, `256M`, `30`, and `1000` are examples. Tune them to the real application.

---

# Part XI — Suggested Apache Production Baseline

## 71. Example Apache Configuration

```apache
ServerTokens Prod
ServerSignature Off

TraceEnable Off


<VirtualHost *:80>

    ServerName app.example.local

    Redirect permanent / https://app.example.local/

</VirtualHost>


<VirtualHost *:443>

    ServerName app.example.local

    DocumentRoot "C:/sites/myproject/public"


    # ---------------------------------------------------------
    # TLS
    # ---------------------------------------------------------

    SSLEngine On

    SSLCertificateFile "C:/Apache24/conf/certs/server.crt"
    SSLCertificateKeyFile "C:/Apache24/conf/certs/server.key"


    # ---------------------------------------------------------
    # Application directory
    # ---------------------------------------------------------

    <Directory "C:/sites/myproject/public">

        Options -Indexes

        AllowOverride None

        Require all granted


        RewriteEngine On

        RewriteCond %{REQUEST_FILENAME} -s [OR]
        RewriteCond %{REQUEST_FILENAME} -l [OR]
        RewriteCond %{REQUEST_FILENAME} -d

        RewriteRule ^ - [L]

        RewriteRule ^ index.php [L]

    </Directory>


    # ---------------------------------------------------------
    # Hidden files
    # ---------------------------------------------------------

    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>


    # ---------------------------------------------------------
    # Sensitive file extensions
    # ---------------------------------------------------------

    <FilesMatch "\.(ini|log|sql|bak|conf)$">
        Require all denied
    </FilesMatch>


    # ---------------------------------------------------------
    # Security headers
    # ---------------------------------------------------------

    <IfModule mod_headers.c>

        Header always set X-Content-Type-Options "nosniff"

        Header always set Referrer-Policy "strict-origin-when-cross-origin"

        Header always set X-Frame-Options "SAMEORIGIN"

        # Enable only after confirming permanent HTTPS operation.
        Header always set Strict-Transport-Security "max-age=31536000"

    </IfModule>

</VirtualHost>
```

Adapt this template to the real server paths, certificate configuration, PHP handler, ZF1 rewriting, DHTMLX iframe behavior, and application URLs.

---

# Part XII — Settings Requiring Special Care in a Legacy ZF1/DHTMLX Application

## 72. Compatibility Checklist

| Setting | Suggested Approach | Important Consideration |
|---|---|---|
| `DocumentRoot` | `public/` only | High priority |
| `Options` | `-Indexes` | Normally safe |
| `AllowOverride` | Prefer `None` | Migrate existing `.htaccess` first |
| HTTPS | Enable | Plan certificates/networking |
| HSTS | Enable after HTTPS migration | Do not enable prematurely |
| CSP | Introduce gradually | Legacy JS may break |
| Frame restrictions | Consider | Test DHTMLX iframe behavior |
| `expose_php` | `Off` | Normally safe |
| `display_errors` | `Off` in production | Keep protected logs |
| `log_errors` | `On` | Protect log directory |
| `session.use_strict_mode` | `1` | Recommended |
| `session.cookie_httponly` | `1` | Recommended |
| `session.cookie_secure` | `1` | Requires HTTPS |
| SameSite | Start with `Lax` | Test application flows |
| `allow_url_include` | `Off` | Recommended |
| `allow_url_fopen` | Evaluate | Libraries may require it |
| `open_basedir` | Consider | Test framework/libraries |
| `disable_functions` | Evaluate | Do not break required tools |
| `file_uploads` | Need-based | Disable when unused |
| `upload_max_filesize` | Restrict | Match document requirements |
| `post_max_size` | Restrict | Must accommodate full request |
| `memory_limit` | Restrict/tune | PDF/image work may need more |
| `max_execution_time` | Restrict/tune | Heavy jobs may need separate design |
| Xdebug | Disable in production | Development only |

---

# Part XIII — Recommended Implementation Priority

## 73. Hardening Roadmap

```text
Supported Apache/PHP/OS versions
              |
              v
DocumentRoot = public/
              |
              v
            HTTPS
              |
              v
       Secure sessions
              |
              v
 Production error handling
              |
              v
   Least-privilege OS access
              |
              v
    Protected file storage
              |
              v
 Authentication / MFA
              |
              v
       Authorization
              |
              v
             CSRF
              |
              v
 SQL injection prevention
              |
              v
     XSS/output encoding
              |
              v
     Secure file uploads
              |
              v
      Security headers
              |
              v
       Gradual CSP rollout
              |
              v
       Logging/auditing
              |
              v
       Security testing
```

---

# Part XIV — Final Security Checklist

## 74. Apache

- [ ] Keep Apache/OpenSSL patched.
- [ ] `ServerTokens Prod`
- [ ] `ServerSignature Off`
- [ ] `TraceEnable Off`
- [ ] Point `DocumentRoot` only to `public/`.
- [ ] Disable directory indexing.
- [ ] Protect hidden/sensitive files.
- [ ] Prevent `.git` exposure.
- [ ] Use HTTPS.
- [ ] Disable obsolete TLS protocols where possible.
- [ ] Enable HSTS only after HTTPS is permanent.
- [ ] Configure security headers.
- [ ] Introduce CSP gradually.
- [ ] Limit request size.
- [ ] Configure reasonable timeouts.
- [ ] Disable unnecessary modules.
- [ ] Run Apache with least OS privilege.

## 75. PHP

- [ ] Move away from unsupported PHP 7.4.
- [ ] `expose_php = Off`
- [ ] `display_errors = Off` in production.
- [ ] `display_startup_errors = Off`
- [ ] `log_errors = On`
- [ ] Store logs outside the web root.
- [ ] `session.use_only_cookies = 1`
- [ ] `session.use_strict_mode = 1`
- [ ] `session.use_trans_sid = 0`
- [ ] `session.cookie_httponly = 1`
- [ ] `session.cookie_secure = 1` with HTTPS.
- [ ] Set an appropriate SameSite policy.
- [ ] Regenerate session IDs after login.
- [ ] Enforce application-level session expiry.
- [ ] `allow_url_include = Off`
- [ ] Evaluate `allow_url_fopen`.
- [ ] Evaluate `open_basedir`.
- [ ] Evaluate unnecessary process-execution functions.
- [ ] `enable_dl = Off`
- [ ] Configure upload limits.
- [ ] Store uploads outside `public/`.
- [ ] Generate server-side storage filenames.
- [ ] Validate uploaded content.
- [ ] Configure resource limits.
- [ ] Disable Xdebug in production.

## 76. Application

- [ ] Use a least-privilege MariaDB account.
- [ ] Use parameterized SQL.
- [ ] Validate untrusted input.
- [ ] Escape output according to context.
- [ ] Implement CSRF protection.
- [ ] Perform authorization server-side.
- [ ] Use `password_hash()` / `password_verify()`.
- [ ] Protect secrets and encryption keys.
- [ ] Log security-relevant events.
- [ ] Never log passwords, private keys, or raw tokens.
- [ ] Test security changes before production deployment.

---

# 77. Final Principle

The strongest design is not one special Apache directive or one `php.ini` setting.

```text
Supported Software
       +
HTTPS
       +
Correct DocumentRoot
       +
Least Privilege
       +
Secure Sessions
       +
Authentication
       +
Authorization
       +
CSRF Protection
       +
Parameterized SQL
       +
Context-Aware Output Encoding
       +
Secure File Handling
       +
Security Headers
       +
Logging and Auditing
       +
Testing
```

For a legacy ZF1/DHTMLX application, introduce potentially disruptive controls such as strict CSP, restrictive frame policies, `open_basedir`, and large `disable_functions` lists gradually and test them against the existing application before production deployment.
