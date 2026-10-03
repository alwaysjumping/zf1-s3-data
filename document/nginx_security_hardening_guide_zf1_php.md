# Nginx Security Hardening Guide for PHP / Zend Framework 1

## 1. Purpose

This document consolidates the Nginx security settings and architecture discussed for a PHP web application, with special consideration for a legacy Zend Framework 1 (ZF1) application using PHP 7.4, DHTMLX 3.5, and MariaDB.

> **Important:** PHP 7.4 is end-of-life. Nginx and PHP-FPM hardening is valuable, but configuration cannot replace security updates. Migration to a supported PHP version should remain part of the security plan.

---

## 2. Nginx + PHP Architecture

With Nginx, PHP is normally executed by PHP-FPM:

```text
Browser
   |
   | HTTPS
   v
+-------------------------+
| Nginx                   |
|                         |
| - TLS                   |
| - Security headers      |
| - Request limits        |
| - Static files          |
| - Access restrictions   |
+-----------+-------------+
            |
            | FastCGI
            v
+-------------------------+
| PHP-FPM                 |
|                         |
| - PHP runtime           |
| - Session handling      |
| - Resource limits       |
| - Application execution |
+-----------+-------------+
            |
            v
+-------------------------+
| Zend Framework 1        |
|                         |
| - Authentication        |
| - Authorization         |
| - CSRF                  |
| - Validation            |
| - Output escaping       |
+-----------+-------------+
            |
            v
          MariaDB
```

---

# Part I — Basic Nginx Hardening

## 3. Hide the Nginx Version

In `nginx.conf`:

```nginx
http {
    server_tokens off;
}
```

This minimizes Nginx version information exposed to clients.

It is information minimization, not a substitute for keeping Nginx patched.

---

## 4. Run Nginx with a Restricted Account

Typical Linux configurations use an account such as:

```nginx
user nginx;
```

or:

```nginx
user www-data;
```

Nginx worker processes should not run as root.

The service account should have only the permissions required by the application.

```text
Application source:
    Read

public/:
    Read

Writable storage/cache/temp:
    Read + Write only where required

Private keys/configuration:
    Restricted

Unrelated system files:
    No unnecessary access
```

---

# Part II — ZF1 Document Root and Routing

## 5. Expose Only `public/`

Recommended structure:

```text
/var/www/myproject/
|
+-- application/
+-- library/
+-- vendor/
+-- tests/
+-- storage/
+-- public/
    +-- index.php
    +-- css/
    +-- js/
    +-- images/
```

Do not use:

```nginx
root /var/www/myproject;
```

Use:

```nginx
root /var/www/myproject/public;
```

This prevents direct web access to framework code, tests, storage, and configuration outside `public/`.

---

## 6. ZF1 Front Controller

A common ZF1 configuration is:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Request flow:

```text
/css/style.css
      |
      +--> Existing file --> Nginx serves it directly

/user/profile
      |
      +--> Not a physical file
              |
              v
          index.php
              |
              v
          ZF1 Router
```

This performs the role commonly handled by Apache `mod_rewrite`.

---

# Part III — PHP Execution Security

## 7. Do Not Allow Arbitrary PHP Execution

A general PHP handler may look like:

```nginx
location ~ \.php$ {
    try_files $uri =404;

    include fastcgi_params;

    fastcgi_param SCRIPT_FILENAME
        $document_root$fastcgi_script_name;

    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

The important protection is:

```nginx
try_files $uri =404;
```

It prevents nonexistent PHP paths from being forwarded to PHP-FPM.

---

## 8. Stronger ZF1 Design: Execute Only `index.php`

ZF1 is a front-controller application, so a stronger design is often possible:

```nginx
location = /index.php {
    include fastcgi_params;

    fastcgi_param SCRIPT_FILENAME
        $document_root/index.php;

    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

Then reject other directly requested PHP files:

```nginx
location ~ \.php$ {
    return 404;
}
```

Conceptually:

```text
/index.php
    --> allowed

/user/list
    --> routed to index.php

/test.php
    --> rejected
```

Before enabling this restriction, confirm that the legacy application does not depend on other directly accessible PHP scripts.

---

# Part IV — Sensitive Files and Directories

## 9. Protect Hidden Files

Example:

```nginx
location ~ /\. {
    deny all;
}
```

This can help protect files such as:

```text
.env
.git
.htpasswd
```

If certificate automation uses a standardized hidden directory such as an ACME challenge path, configure the required exception carefully.

---

## 10. Protect `.git`

Defense in depth:

```nginx
location ~ /\.git {
    deny all;
}
```

An exposed Git repository may reveal:

- source code
- historical source code
- configuration
- internal paths
- accidentally committed credentials

---

## 11. Protect Backup and Configuration Files

For example:

```nginx
location ~* \.(ini|log|sql|bak|conf)$ {
    deny all;
}
```

Examples include:

```text
application.ini
database.sql
debug.log
index.php.bak
```

The stronger design is to keep these files outside `public/`.

---

## 12. Disable Directory Listing

Use:

```nginx
autoindex off;
```

Do not enable:

```nginx
autoindex on;
```

for directories containing sensitive information.

---

# Part V — HTTPS and TLS

## 13. Redirect HTTP to HTTPS

Example:

```nginx
server {
    listen 80;
    server_name app.example.com;

    return 301 https://$host$request_uri;
}
```

Flow:

```text
HTTP
 |
 v
301 Redirect
 |
 v
HTTPS
```

---

## 14. Basic HTTPS Server

Example:

```nginx
server {
    listen 443 ssl;

    server_name app.example.com;

    root /var/www/myproject/public;

    ssl_certificate
        /etc/nginx/certs/server.crt;

    ssl_certificate_key
        /etc/nginx/certs/server.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

The TLS private key must have restrictive filesystem permissions.

---

## 15. TLS Protocols

Do not support obsolete protocols simply for compatibility.

Conceptually:

```text
Disable:
- SSLv2
- SSLv3
- TLS 1.0
- TLS 1.1

Use currently supported modern TLS versions.
```

The exact protocol and cipher configuration should match the installed Nginx/OpenSSL versions and required client compatibility.

Avoid copying old cipher lists from outdated tutorials.

---

## 16. HSTS

After confirming the hostname is permanently HTTPS-only:

```nginx
add_header Strict-Transport-Security
    "max-age=31536000"
    always;
```

Do not enable HSTS before HTTPS is stable.

---

# Part VI — Security Headers

## 17. Prevent MIME Sniffing

```nginx
add_header X-Content-Type-Options
    "nosniff"
    always;
```

Correct MIME types should still be configured.

---

## 18. Referrer Policy

A useful starting point:

```nginx
add_header Referrer-Policy
    "strict-origin-when-cross-origin"
    always;
```

A more restrictive policy can be considered for highly sensitive applications after testing.

---

## 19. Clickjacking Protection

Traditional header:

```nginx
add_header X-Frame-Options
    "SAMEORIGIN"
    always;
```

Modern CSP:

```nginx
add_header Content-Security-Policy
    "frame-ancestors 'self'"
    always;
```

Because legacy DHTMLX applications may use iframes, frame restrictions must be tested carefully.

Same-origin iframe use is compatible with `SAMEORIGIN` / `'self'`; cross-origin framing may be blocked.

---

## 20. Content Security Policy

An initial policy might be:

```nginx
add_header Content-Security-Policy
    "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
    always;
```

Do not deploy a strict CSP directly into a legacy application without testing.

Legacy DHTMLX or older JavaScript may depend on:

```text
inline JavaScript
inline CSS
iframes
old event handlers
dynamic script generation
```

Recommended migration:

```text
Current application
        |
        v
Inventory JS/CSS behavior
        |
        v
Test CSP
        |
        v
Find violations
        |
        v
Modernize problematic code
        |
        v
Strengthen CSP
```

Avoid automatically adding `'unsafe-inline'` and `'unsafe-eval'` everywhere merely to remove CSP errors.

---

# Part VII — Request Limits

## 21. Request Body Size

Nginx can restrict upload/request size:

```nginx
client_max_body_size 10m;
```

Coordinate the limits:

```text
Nginx
client_max_body_size
        |
        v
PHP
post_max_size
        |
        v
PHP
upload_max_filesize
        |
        v
Application
file validation
```

Example:

```nginx
client_max_body_size 12m;
```

PHP:

```ini
post_max_size = 12M
upload_max_filesize = 10M
```

These are examples only. Choose limits according to the application's actual document requirements.

---

## 22. Request Header Limits

Nginx provides controls such as:

```nginx
client_header_buffer_size
large_client_header_buffers
```

Do not unnecessarily configure extremely large header buffers.

---

## 23. Request Timeouts

Examples:

```nginx
client_header_timeout 10s;
client_body_timeout 30s;
send_timeout 30s;
```

These can reduce resources consumed by slow clients.

Tune them according to legitimate network conditions and upload sizes.

---

## 24. Keepalive Timeout

Example:

```nginx
keepalive_timeout 15s;
```

Avoid excessively long keepalive values without a demonstrated requirement.

---

# Part VIII — HTTP Method Restrictions

## 25. Restrict Methods by Endpoint

For an endpoint that should only serve files:

```nginx
limit_except GET HEAD {
    deny all;
}
```

Do not apply this globally if the application legitimately requires:

```text
POST
PUT
PATCH
DELETE
OPTIONS
```

Restrict methods according to the purpose of each endpoint.

---

# Part IX — Rate and Connection Limiting

## 26. Request Rate Limiting

Define a rate-limiting zone:

```nginx
limit_req_zone $binary_remote_addr
    zone=login_limit:10m
    rate=5r/m;
```

Example login location:

```nginx
location = /user/login {
    limit_req zone=login_limit burst=5 nodelay;

    try_files $uri /index.php?$query_string;
}
```

Nginx rate limiting should complement application-level login throttling, monitoring, and MFA where appropriate.

---

## 27. Connection Limiting

Example:

```nginx
limit_conn_zone $binary_remote_addr
    zone=perip:10m;
```

Then:

```nginx
limit_conn perip 20;
```

Choose values based on real traffic.

Be careful with IP-based limits when many legitimate users share one NAT/proxy address.

---

# Part X — Proxy and Client IP Security

## 28. Do Not Automatically Trust Client IP Headers

Headers such as:

```text
X-Forwarded-For
X-Real-IP
```

must not automatically be trusted from arbitrary clients.

An attacker can send a forged header such as:

```http
X-Forwarded-For: 127.0.0.1
```

If Nginx is placed behind a trusted load balancer or reverse proxy, configure real-IP trust only for the known proxy addresses.

---

# Part XI — PHP-FPM Security

## 29. PHP-FPM Architecture

```text
Nginx
  |
  | FastCGI
  v
PHP-FPM
  |
  v
ZF1
```

PHP-FPM is part of the application's security boundary and requires separate hardening.

---

## 30. Prefer a Unix Socket Where Appropriate

Example:

```nginx
fastcgi_pass unix:/run/php/php-fpm.sock;
```

This avoids exposing PHP-FPM on a network TCP port.

If TCP is required:

```nginx
fastcgi_pass 127.0.0.1:9000;
```

bind PHP-FPM only to trusted interfaces and firewall it appropriately.

**Never expose PHP-FPM port 9000 directly to an untrusted network.**

---

## 31. PHP-FPM Pool User

Example:

```ini
user = www-data
group = www-data
```

PHP workers should not run as root.

Use a dedicated low-privilege account where practical.

---

## 32. PHP-FPM Socket Permissions

Example:

```ini
listen = /run/php/php-fpm.sock

listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

Adapt the account/group to the actual Nginx and PHP-FPM service accounts.

Goal:

```text
Nginx:
    permitted to connect

Unrelated users:
    no unnecessary access
```

---

## 33. PHP-FPM Process Limits

Example:

```ini
pm = dynamic

pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
```

These values are not universal recommendations.

Calculate them according to:

```text
Available RAM
Average PHP worker memory
Traffic
Database workload
Document processing
```

Process limits provide resource containment and help prevent uncontrolled worker growth.

---

## 34. Request Termination Timeout

Example:

```ini
request_terminate_timeout = 60s
```

Tune carefully because PDF/image/document processing may legitimately take longer.

---

## 35. Clear Environment Variables

PHP-FPM commonly supports:

```ini
clear_env = yes
```

This limits environment variables automatically inherited by workers.

If the application relies on environment variables, configure the required values deliberately and test the application.

---

## 36. Limit Executable Script Extensions

PHP-FPM can use:

```ini
security.limit_extensions = .php
```

Do not permit old extensions such as `.php3`, `.php4`, or `.phtml` unless the application genuinely requires them.

---

## 37. Protect PHP-FPM Configuration

Files such as:

```text
php.ini
php-fpm.conf
www.conf
```

must not be web-accessible.

Protect them with appropriate OS permissions.

---

# Part XII — FastCGI Security

## 38. `SCRIPT_FILENAME`

A key FastCGI parameter is:

```nginx
fastcgi_param SCRIPT_FILENAME
    $document_root$fastcgi_script_name;
```

This identifies the PHP script PHP-FPM should execute.

Incorrect FastCGI filesystem mapping can create security problems. Avoid copying configuration without understanding the path mapping.

---

## 39. Do Not Pass Arbitrary Files to PHP-FPM

When using a general PHP handler, use:

```nginx
try_files $uri =404;
```

For a ZF1 front-controller application, consider the stronger design of allowing only:

```text
/index.php
```

to execute through PHP-FPM.

---

# Part XIII — Static Files

## 40. Let Nginx Serve Static Assets

Nginx should normally serve:

```text
CSS
JavaScript
images
fonts
```

directly.

Application requests go through PHP:

```text
/style.css
    |
    +--> Nginx

/user/profile
    |
    +--> index.php
            |
            v
         PHP-FPM
            |
            v
           ZF1
```

This improves separation of responsibilities and performance.

---

# Part XIV — Upload Security

## 41. Prevent PHP Execution in Upload Directories

If a legacy system has:

```text
public/uploads/
```

at minimum ensure uploaded PHP files cannot execute.

However, the preferred design is to move uploads outside the web root:

```text
/var/www/myproject/storage/uploads/
```

Then the application performs authentication and authorization before returning a file.

---

# Part XV — Protected Documents

## 42. Recommended Protected Document Architecture

For sensitive PDFs/documents:

```text
Browser
   |
   v
GET /document/view/id/123
   |
   v
Nginx
   |
   v
ZF1 Controller
   |
   v
Authentication
   |
   v
Authorization
   |
   v
Document Service
   |
   v
Protected Storage
```

Do not simply publish confidential files as:

```text
/public/documents/secret.pdf
```

because possession of the URL may bypass application authorization.

---

## 43. Nginx `internal`

Nginx supports internal-only locations:

```nginx
location /protected-files/ {
    internal;

    alias /var/www/myproject/storage/documents/;
}
```

A client cannot directly access this location in the normal way.

---

## 44. `X-Accel-Redirect`

A useful protected-file design is:

```text
Browser
    |
    v
ZF1 Controller
    |
    v
Authentication
    |
    v
Authorization
    |
    +--> DENIED
    |       |
    |       +--> 403
    |
    +--> ALLOWED
            |
            v
      X-Accel-Redirect
            |
            v
          Nginx
            |
            v
      Protected file
```

PHP/ZF1 makes the authorization decision, while Nginx efficiently performs the actual large-file transfer.

This is particularly useful for protected PDFs and other large documents.

---

# Part XVI — Error Handling

## 45. Custom Error Pages

Example:

```nginx
error_page 404 /404.html;
error_page 500 502 503 504 /50x.html;
```

Avoid exposing internal implementation details to clients.

PHP production errors should also be logged rather than displayed.

---

# Part XVII — Logging

## 46. Nginx Logging

Example:

```nginx
access_log /var/log/nginx/app-access.log;
error_log  /var/log/nginx/app-error.log warn;
```

Protect logs with OS-level permissions.

Logs may contain:

- client IP addresses
- URLs
- request paths
- user agents
- error information

---

## 47. Keep Secrets Out of Logs

Do not intentionally log:

```text
Passwords
Full session IDs
JWTs
Refresh tokens
Private keys
Encryption keys
```

Avoid putting secrets in URLs because query strings and paths commonly appear in access logs.

---

# Part XVIII — Production PHP Security Still Applies

## 48. PHP Security Baseline

Moving from Apache to Nginx does not replace PHP hardening.

A useful production baseline includes:

```ini
expose_php = Off

display_errors = Off
display_startup_errors = Off

log_errors = On

session.use_only_cookies = 1
session.use_strict_mode = 1
session.use_trans_sid = 0

session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"

allow_url_include = Off

enable_dl = Off
```

Also tune:

```text
upload_max_filesize
post_max_size
max_file_uploads
memory_limit
max_execution_time
max_input_time
max_input_vars
```

according to real application requirements.

---

## 49. Disable Development Debugging in Production

Production PHP should generally use:

```ini
display_errors = Off
log_errors = On
```

Xdebug should normally be disabled in production.

---

# Part XIX — Practical ZF1 Nginx Configuration

## 50. Example Server Configuration

The following is a starting template:

```nginx
server_tokens off;


server {
    listen 80;

    server_name app.example.com;

    return 301 https://$host$request_uri;
}


server {
    listen 443 ssl;

    server_name app.example.com;


    # --------------------------------------------------------
    # Application root
    # --------------------------------------------------------

    root /var/www/myproject/public;
    index index.php;


    # --------------------------------------------------------
    # TLS
    # --------------------------------------------------------

    ssl_certificate
        /etc/nginx/certs/server.crt;

    ssl_certificate_key
        /etc/nginx/certs/server.key;


    # --------------------------------------------------------
    # Security headers
    # --------------------------------------------------------

    add_header X-Content-Type-Options
        "nosniff"
        always;

    add_header Referrer-Policy
        "strict-origin-when-cross-origin"
        always;

    add_header X-Frame-Options
        "SAMEORIGIN"
        always;

    # Enable only after HTTPS is permanent.
    add_header Strict-Transport-Security
        "max-age=31536000"
        always;


    # --------------------------------------------------------
    # Request limits
    # --------------------------------------------------------

    client_max_body_size 12m;

    client_header_timeout 10s;
    client_body_timeout 30s;
    send_timeout 30s;


    # --------------------------------------------------------
    # ZF1 routing
    # --------------------------------------------------------

    location / {
        try_files
            $uri
            $uri/
            /index.php?$query_string;
    }


    # --------------------------------------------------------
    # Main PHP front controller
    # --------------------------------------------------------

    location = /index.php {
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME
            $document_root/index.php;

        fastcgi_pass
            unix:/run/php/php-fpm.sock;
    }


    # --------------------------------------------------------
    # Prevent other PHP files from executing directly
    # --------------------------------------------------------

    location ~ \.php$ {
        return 404;
    }


    # --------------------------------------------------------
    # Hidden files
    # --------------------------------------------------------

    location ~ /\. {
        deny all;
    }


    # --------------------------------------------------------
    # Backup/configuration files
    # --------------------------------------------------------

    location ~* \.(ini|log|sql|bak|conf)$ {
        deny all;
    }


    # --------------------------------------------------------
    # Protected document storage
    # --------------------------------------------------------

    location /protected-files/ {
        internal;

        alias /var/www/myproject/storage/documents/;
    }


    # --------------------------------------------------------
    # Logging
    # --------------------------------------------------------

    access_log
        /var/log/nginx/app-access.log;

    error_log
        /var/log/nginx/app-error.log warn;
}
```

This is a starting template, not a production configuration to copy unchanged.

Adapt:

```text
server_name
certificate paths
PHP-FPM socket
project paths
upload limits
timeouts
DHTMLX iframe requirements
protected-file architecture
TLS configuration
```

to the real environment.

---

# Part XX — Practical PHP-FPM Configuration

## 51. Example PHP-FPM Pool

```ini
[www]

user = www-data
group = www-data

listen = /run/php/php-fpm.sock

listen.owner = www-data
listen.group = www-data
listen.mode = 0660

security.limit_extensions = .php

clear_env = yes

pm = dynamic

pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6

request_terminate_timeout = 60s
```

The process counts and timeout are examples. Tune them to server resources and application workload.

---

# Part XXI — Nginx vs Apache

## 52. Apache Architecture

```text
Apache
   |
   +-- mod_rewrite
   |
   +-- .htaccess possible
   |
   +-- PHP handler
          |
          v
         ZF1
```

---

## 53. Nginx Architecture

```text
Nginx
   |
   +-- try_files
   |
   +-- security rules
   |
   +-- static files
   |
   +-- FastCGI
          |
          v
       PHP-FPM
          |
          v
         ZF1
```

Nginx does not use Apache-style `.htaccess`.

Security configuration is normally centralized in locations such as:

```text
/etc/nginx/nginx.conf
/etc/nginx/conf.d/
/etc/nginx/sites-available/
```

depending on the Linux distribution and installation.

---

# Part XXII — Configuration Testing

## 54. Test Before Reloading

Always validate configuration before reloading Nginx:

```bash
nginx -t
```

If the test succeeds, reload Nginx using the service mechanism appropriate to the operating system/distribution.

Do not skip configuration validation on a production server.

---

# Part XXIII — Security Checklist

## 55. Nginx Checklist

- [ ] Keep Nginx and OpenSSL patched.
- [ ] `server_tokens off`
- [ ] Run workers with a low-privilege account.
- [ ] Set `root` to the application's `public/` directory.
- [ ] Keep directory listing disabled.
- [ ] Protect hidden files.
- [ ] Never expose `.git`.
- [ ] Protect backup/configuration files.
- [ ] Use HTTPS.
- [ ] Disable obsolete TLS protocols where possible.
- [ ] Enable HSTS only after HTTPS is permanent.
- [ ] Add appropriate security headers.
- [ ] Introduce CSP gradually.
- [ ] Test frame restrictions against DHTMLX.
- [ ] Limit request body size.
- [ ] Configure reasonable request timeouts.
- [ ] Restrict HTTP methods by endpoint where useful.
- [ ] Consider rate limiting for login/sensitive endpoints.
- [ ] Consider connection limits based on actual traffic.
- [ ] Trust forwarded client-IP headers only from known proxies.
- [ ] Protect logs.
- [ ] Keep secrets out of URLs/logs.

---

## 56. PHP-FPM Checklist

- [ ] Never expose PHP-FPM directly to an untrusted network.
- [ ] Prefer a Unix socket where appropriate.
- [ ] Run PHP workers as a non-root account.
- [ ] Restrict socket permissions.
- [ ] Use `security.limit_extensions = .php`.
- [ ] Configure process limits based on available RAM/workload.
- [ ] Configure a reasonable request termination timeout.
- [ ] Protect PHP-FPM configuration files.
- [ ] Map `SCRIPT_FILENAME` correctly.
- [ ] Prefer only `index.php` execution for a compatible ZF1 front-controller application.
- [ ] Disable Xdebug in production.

---

## 57. Protected File Checklist

- [ ] Store confidential files outside `public/`.
- [ ] Authenticate the user before access.
- [ ] Perform server-side authorization for every file request.
- [ ] Do not rely on hidden URLs.
- [ ] Prevent PHP execution in upload directories.
- [ ] Consider Nginx `internal` locations.
- [ ] Consider `X-Accel-Redirect` for large protected files.
- [ ] Log sensitive-document access appropriately without logging secrets.

---

# Part XXIV — Recommended Implementation Order

## 58. Hardening Roadmap

```text
             Nginx / PHP Security
                     |
                     v
          Upgrade supported software
                     |
                     v
             root = public/
                     |
                     v
                  HTTPS
                     |
                     v
           PHP-FPM isolation
                     |
                     v
        Only index.php executable
                     |
                     v
        Hidden/config file denial
                     |
                     v
           Secure PHP sessions
                     |
                     v
        Production error handling
                     |
                     v
          Protected file storage
                     |
                     v
       Authentication/authorization
                     |
                     v
             CSRF protection
                     |
                     v
      SQL injection/XSS prevention
                     |
                     v
          Secure file uploads
                     |
                     v
         Request/resource limits
                     |
                     v
           Security headers
                     |
                     v
          Gradual CSP rollout
                     |
                     v
          Rate limiting/auditing
```

---

# 59. Final Principle

Nginx security is strongest when combined with secure PHP-FPM, application security, and operating-system controls:

```text
Supported Software
       +
Correct Nginx root
       +
HTTPS
       +
PHP-FPM Isolation
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
Secure Uploads
       +
Protected Document Storage
       +
Security Headers
       +
Logging / Auditing
       +
Testing
```

For a legacy ZF1/DHTMLX application, potentially disruptive controls such as strict CSP, restrictive frame policies, aggressive timeouts, and limiting PHP execution only to `index.php` should be introduced carefully and tested against the existing application before production deployment.
