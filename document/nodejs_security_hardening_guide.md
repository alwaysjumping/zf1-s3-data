# Node.js Security Hardening Guide

## 1. Purpose

This guide explains important security settings and design practices for running a Node.js application in production.

A secure Node.js deployment is not created by one setting. Security should be applied in layers:

```text
Browser / API Client
        |
        | HTTPS
        v
+---------------------------+
| Nginx / Reverse Proxy     |
| - TLS                     |
| - Request limits          |
| - Rate limiting           |
| - Security headers        |
+-------------+-------------+
              |
              v
+---------------------------+
| Node.js Application       |
| - Authentication          |
| - Authorization           |
| - Validation              |
| - CSRF / CORS             |
| - Secure sessions         |
| - Safe error handling     |
+-------------+-------------+
              |
              v
+---------------------------+
| Database / Storage        |
| - Least privilege         |
| - Parameterized queries   |
| - Protected credentials   |
+---------------------------+
```

Keep Node.js, npm dependencies, the operating system, reverse proxy, database, and supporting libraries on supported and patched versions.

---

# Part I — Runtime and Process Security

## 2. Use a Supported Node.js Release

Production applications should run on a currently supported Node.js release.

Do not keep an old Node.js version merely because the application still starts successfully. Unsupported runtimes stop receiving normal security fixes.

Before upgrading, test:

- application code
- native modules
- database drivers
- authentication libraries
- build tools
- test suite

---

## 3. Never Run the Application as Root

Avoid:

```bash
sudo node server.js
```

Create a dedicated low-privilege operating-system account for the application.

Conceptually:

```text
root
 |
 +--> starts/manages service when necessary

nodeapp user
 |
 +--> runs Node.js
      |
      +--> read application files
      +--> write only required directories
      +--> no unnecessary system access
```

If Nginx is used as the public reverse proxy, Node.js normally does not need to bind directly to ports 80 or 443.

---

## 4. Bind Node.js to a Private Interface

When Nginx and Node.js are on the same server, prefer listening only on localhost:

```javascript
app.listen(3000, '127.0.0.1', () => {
    console.log('Application started');
});
```

Architecture:

```text
Internet
   |
   v
Nginx :443
   |
   v
127.0.0.1:3000
   |
   v
Node.js
```

Do not expose the internal Node.js port to the Internet unless there is a specific architecture requiring it.

Also enforce this with the host/network firewall.

---

## 5. Use a Process Manager or Service Manager

Do not depend on manually running:

```bash
node app.js
```

in a terminal for production.

Use an appropriate service manager such as systemd, a container orchestrator, or another controlled process-management mechanism.

A service manager can provide:

- automatic restart
- dedicated user
- controlled environment
- log handling
- resource limits
- startup at boot

A process manager improves availability but does not replace application security.

---

## 6. Production Environment

Set the application environment deliberately:

```bash
NODE_ENV=production
```

Many frameworks and libraries change behavior in production mode.

Do not use `NODE_ENV=production` as a security boundary by itself. Explicitly configure all important production security controls.

---

# Part II — Secrets and Configuration

## 7. Never Hard-Code Secrets

Avoid:

```javascript
const jwtSecret = 'my-secret-password';
const dbPassword = 'database123';
```

Secrets include:

```text
Database passwords
JWT signing keys
Session secrets
API keys
Encryption keys
Private keys
OAuth client secrets
```

Use protected server-side configuration or an approved secret-management system.

---

## 8. Environment Variables

A common pattern is:

```javascript
const dbPassword = process.env.DB_PASSWORD;
```

Do not expose secrets to browser-side JavaScript.

Environment variables are convenient configuration transport, but access to the process environment must still be protected.

---

## 9. `.env` Files

If a `.env` file is used:

```text
DB_HOST=127.0.0.1
DB_USER=myapp
DB_PASSWORD=...
SESSION_SECRET=...
```

protect it carefully.

Do not:

- place it in a public web directory
- commit it to Git
- send it to users
- include it in public deployment archives

Add it to `.gitignore` where appropriate:

```gitignore
.env
.env.*
```

Keep a non-secret template if useful:

```text
.env.example
```

---

## 10. Validate Required Configuration at Startup

Fail safely when required configuration is missing.

Example:

```javascript
const requiredVariables = [
    'DB_HOST',
    'DB_USER',
    'DB_PASSWORD',
    'SESSION_SECRET'
];

for (const name of requiredVariables) {
    if (!process.env[name]) {
        throw new Error(`Missing required configuration: ${name}`);
    }
}
```

Do not silently fall back to insecure default secrets.

---

# Part III — HTTPS and Reverse Proxy

## 11. Use HTTPS

Production authentication and sensitive APIs should use HTTPS.

HTTPS protects:

- passwords
- cookies
- session identifiers
- JWTs
- API data
- documents

A common architecture is:

```text
Browser
   |
   | HTTPS :443
   v
Nginx
   |
   | HTTP/Fast local connection
   v
Node.js :3000
```

TLS is terminated at Nginx while Node.js listens only on a trusted local/private interface.

---

## 12. Configure Proxy Trust Carefully

Express applications behind Nginx may need proxy awareness.

Example:

```javascript
app.set('trust proxy', 1);
```

Do not blindly use overly broad proxy trust if requests can reach Node.js from untrusted networks.

Incorrect proxy trust can affect:

- client IP detection
- secure-cookie behavior
- rate limiting
- protocol detection

Trust only the actual proxy topology.

---

# Part IV — HTTP Security Headers

## 13. Use Helmet with Express

For Express applications, Helmet is commonly used to configure useful HTTP security headers.

Example:

```javascript
const helmet = require('helmet');

app.use(helmet());
```

Security headers can also be configured at Nginx.

Avoid conflicting policies between Nginx and Node.js. Decide which layer owns each header.

---

## 14. Content Security Policy

CSP can reduce the impact of some XSS vulnerabilities.

Example concept:

```javascript
app.use(
    helmet.contentSecurityPolicy({
        directives: {
            defaultSrc: ["'self'"],
            objectSrc: ["'none'"],
            baseUri: ["'self'"],
            frameAncestors: ["'self'"]
        }
    })
);
```

A strict CSP can break legacy front-end applications using:

- inline JavaScript
- inline styles
- `eval`
- dynamic scripts
- third-party resources

Introduce CSP gradually and test it.

Do not automatically add `'unsafe-inline'` and `'unsafe-eval'` simply to remove CSP errors.

---

## 15. MIME Sniffing

Use the equivalent of:

```http
X-Content-Type-Options: nosniff
```

Helmet can configure this, or Nginx can provide it.

---

## 16. Clickjacking Protection

Use CSP `frame-ancestors` where practical.

Legacy support may also use:

```http
X-Frame-Options: SAMEORIGIN
```

Test carefully if the application legitimately uses iframes.

---

## 17. HSTS

For a permanently HTTPS-only application, HSTS can be configured at the TLS/reverse-proxy layer.

Example Nginx header:

```nginx
add_header Strict-Transport-Security
    "max-age=31536000"
    always;
```

Enable it only after HTTPS operation is stable.

---

# Part V — Request Size and Parsing

## 18. Limit JSON Request Size

Do not accept unlimited request bodies.

Express example:

```javascript
app.use(express.json({
    limit: '1mb'
}));
```

Choose the value according to actual API requirements.

---

## 19. Limit URL-Encoded Requests

```javascript
app.use(express.urlencoded({
    extended: false,
    limit: '1mb'
}));
```

Do not configure huge limits globally merely because one endpoint needs large data.

---

## 20. Coordinate Proxy and Application Limits

If Nginx is in front:

```nginx
client_max_body_size 10m;
```

Node.js should also enforce suitable endpoint/application limits.

Conceptually:

```text
Nginx request limit
       |
       v
Node.js parser limit
       |
       v
Endpoint validation
       |
       v
Business logic
```

---

# Part VI — Input Validation

## 21. Treat All Client Input as Untrusted

Untrusted input includes:

```text
req.body
req.query
req.params
req.headers
cookies
uploaded files
WebSocket messages
external API responses
```

Validate:

- type
- length
- format
- allowed values
- numeric range
- required fields
- business constraints

---

## 22. Prefer Allow-Lists

Instead of trying to identify every dangerous value, define what is accepted.

Example:

```javascript
const allowedSortFields = [
    'name',
    'created_at',
    'status'
];

if (!allowedSortFields.includes(req.query.sort)) {
    return res.status(400).json({
        error: 'Invalid sort field'
    });
}
```

This is especially important when user input affects SQL identifiers, filenames, commands, or application behavior.

---

## 23. Validation Libraries

Schema-validation libraries can help centralize validation.

Regardless of library choice, validation should occur at the application boundary before untrusted data reaches important business logic.

---

# Part VII — SQL Injection

## 24. Never Build SQL by Concatenation

Unsafe:

```javascript
const sql =
    "SELECT * FROM users WHERE username = '" +
    req.body.username +
    "'";
```

Use parameterized queries.

Conceptually:

```javascript
const sql =
    'SELECT * FROM users WHERE username = ?';

const rows = await db.query(
    sql,
    [req.body.username]
);
```

The exact placeholder syntax depends on the database driver.

---

## 25. Database Least Privilege

Do not connect Node.js to MariaDB/MySQL as:

```text
root
```

Create a dedicated application account with only the required privileges.

Typical application privileges might include:

```text
SELECT
INSERT
UPDATE
DELETE
```

Do not grant administrative permissions unless the application truly requires them.

---

# Part VIII — XSS

## 26. Avoid Unsafe HTML Injection

Do not place untrusted values into HTML using unsafe APIs.

Browser-side code should prefer:

```javascript
element.textContent = value;
```

rather than:

```javascript
element.innerHTML = value;
```

when rendering plain text.

If the application intentionally accepts rich HTML, use a carefully selected sanitization strategy appropriate to the environment.

---

## 27. Template Escaping

Use template-engine escaping rather than raw/unescaped rendering for untrusted data.

Remember that escaping depends on context:

```text
HTML text
HTML attribute
JavaScript
URL
CSS
```

Avoid placing untrusted values into dangerous contexts whenever possible.

---

# Part IX — Authentication

## 28. Password Storage

Never store plaintext passwords.

Do not use simple fast hashes such as raw:

```text
MD5
SHA-1
SHA-256
```

for password storage.

Use a password-hashing algorithm/library designed for passwords, such as Argon2 or bcrypt, configured appropriately for the deployment.

---

## 29. Login Rate Limiting

Protect login endpoints with rate limiting.

Conceptually:

```text
Request
   |
   v
Nginx rate limit
   |
   v
Application rate limit
   |
   v
Credential verification
   |
   v
Security logging
```

Rate limiting should account for shared NAT/proxy environments to avoid blocking many legitimate users behind one IP.

---

## 30. Generic Authentication Errors

Avoid revealing whether a username exists.

Prefer:

```text
Invalid username or password.
```

rather than different messages such as:

```text
Username does not exist.
Password is incorrect.
```

when account enumeration is a concern.

---

## 31. MFA

For sensitive applications, consider multi-factor authentication.

TOTP is a common option.

MFA should complement, not replace:

- strong password handling
- secure sessions
- rate limiting
- authorization
- logging

---

# Part X — Session Security

## 32. Prefer Server-Side Sessions for Traditional Web Applications

A session architecture may look like:

```text
Browser
   |
   +--> Random session cookie
              |
              v
          Node.js
              |
              v
       Server-side session store
```

Do not store sensitive application state directly in a client-controlled cookie unless the design explicitly protects its confidentiality and integrity.

---

## 33. Secure Session Cookies

For an HTTPS application:

```javascript
cookie: {
    httpOnly: true,
    secure: true,
    sameSite: 'lax'
}
```

Meaning:

```text
httpOnly
    JavaScript cannot normally read the cookie

secure
    Cookie is sent only over HTTPS

sameSite
    Helps control cross-site cookie sending
```

Choose SameSite behavior according to legitimate application flows.

---

## 34. Strong Session Secret

Do not use:

```javascript
secret: 'secret'
```

Use a strong randomly generated secret stored outside source code.

---

## 35. Do Not Use the Default In-Memory Session Store for Production

For Express session-based applications, the default development memory store is not designed as a production session database.

Use a suitable production session store such as a properly secured Redis/database-backed implementation appropriate to the deployment.

Protect the session store from untrusted network access.

---

## 36. Session Regeneration

After successful login or privilege elevation, regenerate the session identifier to reduce session-fixation risk.

Conceptually:

```text
Anonymous session
      |
      v
Login succeeds
      |
      v
Regenerate session ID
      |
      v
Authenticated session
```

---

## 37. Session Expiration

Implement:

- inactivity timeout
- absolute lifetime where appropriate
- logout invalidation
- reauthentication for sensitive operations

Do not leave high-value sessions valid indefinitely.

---

# Part XI — JWT Security

## 38. JWT Is Signed, Not Automatically Encrypted

A normal signed JWT payload can be decoded by the client.

Do not put secrets in it.

Example appropriate claims:

```text
sub
iat
exp
iss
aud
roles/permissions when carefully designed
```

Avoid:

```text
passwords
private keys
database credentials
confidential document contents
```

---

## 39. Validate JWT Claims

JWT verification should check more than a signature where applicable.

Validate expected:

```text
algorithm
issuer
audience
expiration
not-before
subject/context
```

Do not accept whatever algorithm/header the token happens to request without an explicit verification policy.

---

## 40. JWT Signing Keys

Store signing keys/secrets outside source code.

Use sufficiently strong keys appropriate to the selected algorithm.

Support key rotation as the system matures.

---

## 41. Short-Lived Access Tokens

Use relatively short-lived access tokens and an explicit refresh-token design where longer sessions are needed.

A common architecture is:

```text
Access token
    short lifetime

Refresh token
    longer lifetime
    stored/handled more carefully
    revocable
    rotated where appropriate
```

---

# Part XII — CSRF

## 42. When CSRF Matters

CSRF is especially relevant when browsers automatically send authentication credentials such as cookies.

Protect state-changing operations such as:

```text
POST
PUT
PATCH
DELETE
```

with an appropriate CSRF strategy when cookie/session authentication is used.

---

## 43. CSRF Token Design

Conceptually:

```text
Session
   |
   +--> CSRF token

Browser
   |
   +--> sends session cookie automatically
   |
   +--> explicitly sends CSRF token
              |
              v
          Node.js
              |
              v
        Validate token
```

SameSite cookies help but should not automatically be treated as a complete replacement for CSRF defenses in every application architecture.

---

# Part XIII — CORS

## 44. CORS Is Not Authentication

CORS controls which browser origins can read/use certain cross-origin responses.

It does not replace authentication or authorization.

---

## 45. Avoid Unnecessary Wildcard CORS

Do not automatically use:

```http
Access-Control-Allow-Origin: *
```

for a private authenticated API.

Allow only origins that actually require browser access.

Example concept:

```javascript
const allowedOrigins = new Set([
    'https://app.example.com'
]);
```

Validate the request origin against the explicit allow-list.

---

## 46. Credentials and CORS

Cookie-authenticated cross-origin applications require especially careful configuration of:

- allowed origins
- credentials
- SameSite
- CSRF protection

Do not reflect arbitrary `Origin` headers while allowing credentials.

---

# Part XIV — Authorization

## 47. Authentication Is Not Authorization

Authentication:

```text
Who are you?
```

Authorization:

```text
Are you allowed to do this?
```

Every sensitive API/controller action should enforce authorization on the server.

---

## 48. Never Trust UI Restrictions

This is not security:

```javascript
deleteButton.style.display = 'none';
```

A user can still manually call the endpoint.

The server must verify permission:

```text
Request
   |
   v
Authentication
   |
   v
Authorization
   |
   +--> allowed
   |
   +--> 403 Forbidden
```

---

## 49. Object-Level Authorization

For requests such as:

```text
GET /documents/123
DELETE /users/45
```

verify that the authenticated user is allowed to access that specific object.

Do not rely only on the fact that the user is logged in.

---

# Part XV — File Upload Security

## 50. Limit Upload Size

Apply limits at multiple layers:

```text
Nginx
   |
   v
Node.js upload parser
   |
   v
Application validation
```

Do not accept unlimited uploads.

---

## 51. Never Trust Uploaded Filenames

Avoid storing a file directly using the user-supplied name.

Prefer:

```text
Original:
    report.pdf

Stored:
    random-generated-id.dat

Database:
    original_name = report.pdf
    storage_name  = generated identifier
```

---

## 52. Validate File Content

Do not trust only:

```text
filename extension
client MIME type
```

Validate file type/content according to the application's requirements.

---

## 53. Store Uploads Outside the Public Directory

Prefer:

```text
project/
+-- src/
+-- public/
+-- storage/
    +-- uploads/
```

Then access protected files through application authorization.

---

## 54. Prevent Script Execution

Uploaded files must never become executable application code.

Do not store untrusted uploads in a location from which Nginx or another web server will execute scripts.

---

# Part XVI — Filesystem and Path Security

## 55. Prevent Path Traversal

Never directly construct paths from untrusted input:

```javascript
const filename = req.query.file;
const path = '/storage/' + filename;
```

Attackers may attempt values such as:

```text
../../../../etc/passwd
```

Use server-generated identifiers and explicit mappings whenever possible.

If filesystem paths must be derived, resolve and validate them against an expected base directory.

---

## 56. Least Filesystem Privilege

Node.js should only be able to write to directories that actually require modification.

Application source should normally not be writable by the running application process.

This helps reduce the impact of an application compromise.

---

# Part XVII — Command Injection

## 57. Avoid Shell Commands with User Input

Dangerous design:

```javascript
exec('convert ' + req.body.filename);
```

Untrusted data can turn command construction into command injection.

Prefer APIs that accept argument arrays without invoking a shell, and validate every argument.

Better architecture:

```text
User input
   |
   v
Validation / allow-list
   |
   v
Fixed executable
   |
   v
Separate argument array
```

Avoid shell execution entirely when a native/library API can perform the task.

---

# Part XVIII — Prototype Pollution and Object Handling

## 58. Be Careful with Untrusted Object Keys

JavaScript applications may be exposed to prototype-pollution problems when untrusted object keys are recursively merged into application objects.

Avoid blindly merging arbitrary user objects into configuration/security-sensitive objects.

Use:

- explicit schemas
- allow-listed properties
- maintained libraries
- dependency updates

---

## 59. Mass Assignment

Avoid patterns that copy all client fields directly into database/domain objects:

```javascript
Object.assign(user, req.body);
```

An attacker might supply fields such as:

```text
role
isAdmin
status
ownerId
```

Prefer explicit assignment:

```javascript
user.name = req.body.name;
user.email = req.body.email;
```

after validation.

---

# Part XIX — Error Handling

## 60. Do Not Return Stack Traces to Users

Avoid production responses containing:

```text
filesystem paths
stack traces
SQL details
environment values
internal service names
```

Use generic client responses and protected server logs.

---

## 61. Central Error Handler

Express example:

```javascript
app.use((err, req, res, next) => {
    console.error(err);

    res.status(500).json({
        error: 'Internal server error'
    });
});
```

In a real application, use structured logging and ensure secrets are redacted.

---

## 62. Handle 404 Separately

Example:

```javascript
app.use((req, res) => {
    res.status(404).json({
        error: 'Not found'
    });
});
```

Do not reveal unnecessary routing or filesystem information.

---

# Part XX — Logging

## 63. Security-Relevant Events

Consider logging:

```text
login success/failure
logout
authorization denial
administrative changes
password changes
MFA changes
sensitive document access
upload events
token revocation
security-validation failures
```

---

## 64. Never Log Secrets

Avoid logging:

```text
passwords
session IDs
full JWTs
refresh tokens
private keys
encryption keys
database passwords
authorization headers
sensitive document contents
```

Implement log redaction where needed.

---

## 65. Structured Logging

Structured logs can make auditing and alerting easier.

Useful fields may include:

```text
timestamp
request ID
event type
user ID
result
client IP when appropriate
resource ID
```

Avoid excessive personal/sensitive information.

---

# Part XXI — Dependency Security

## 66. Keep Dependencies Minimal

Every dependency adds:

```text
code
transitive dependencies
maintenance risk
potential vulnerabilities
```

Do not install a package for trivial functionality without considering its maintenance and dependency footprint.

---

## 67. Use a Lock File

Commit the appropriate package lock file, commonly:

```text
package-lock.json
```

This helps make dependency installation reproducible.

---

## 68. Use Deterministic Production Installation

For projects using npm with a valid lock file, production/build pipelines commonly use:

```bash
npm ci
```

rather than allowing dependency versions to drift unexpectedly.

---

## 69. Audit Dependencies

Use dependency-security review tools appropriate to the environment.

For npm projects:

```bash
npm audit
```

Treat audit output as input to investigation rather than blindly applying every automatic upgrade without testing.

---

## 70. Do Not Run Untrusted Package Scripts

npm packages may execute lifecycle scripts during installation.

Only install dependencies from trusted sources and review unusual packages carefully.

For highly controlled/offline environments, obtain and verify dependencies on a connected staging machine, then transfer the approved dependency set/artifacts according to your internal process.

---

# Part XXII — Production Source and Development Tools

## 71. Do Not Deploy Unnecessary Development Material

Production should not expose:

```text
tests
debug scripts
temporary files
database dumps
source maps containing sensitive source when inappropriate
developer credentials
local configuration
```

Deploy only what production requires.

---

## 72. Disable Development Debugging

Do not expose Node.js debugging interfaces to untrusted networks.

Development/debug ports and inspector functionality should be disabled or tightly restricted in production.

---

# Part XXIII — Denial-of-Service Considerations

## 73. Node.js Event Loop

Node.js uses an event-driven architecture.

CPU-heavy synchronous work can block other requests:

```text
Request A
   |
   +--> expensive synchronous CPU task
              |
              +--> event loop blocked
                       |
                       +--> Requests B/C/D wait
```

Avoid expensive synchronous operations on request paths.

---

## 74. Avoid Synchronous APIs in Hot Request Paths

Be careful with synchronous operations such as large filesystem operations, expensive cryptography, compression, or CPU-heavy transformations during normal HTTP request processing.

Use appropriate asynchronous APIs or worker/background processing for heavy tasks.

---

## 75. Bound Expensive Operations

Apply limits to:

```text
request size
file size
JSON depth/complexity where relevant
pagination size
search complexity
database result size
compression/decompression
image/document processing
```

An authenticated user can also cause resource exhaustion, so limits should not exist only at the login boundary.

---

# Part XXIV — WebSocket Security

## 76. Authenticate WebSocket Connections

Do not assume a WebSocket is trusted because the HTTP upgrade succeeded.

Authenticate the connection and authorize individual operations/messages where necessary.

---

## 77. Validate Every WebSocket Message

Treat WebSocket data exactly like HTTP input:

```text
Untrusted message
       |
       v
Schema validation
       |
       v
Authentication context
       |
       v
Authorization
       |
       v
Business logic
```

---

## 78. Limit Message Size and Rate

Apply:

- maximum message size
- rate limits
- idle timeouts
- connection limits

to reduce abuse and resource exhaustion.

---

# Part XXV — Secure Express Application Skeleton

## 79. Example

```javascript
'use strict';

const express = require('express');
const helmet = require('helmet');

const app = express();


// ------------------------------------------------------------
// Reverse proxy
// Configure according to the real proxy topology.
// ------------------------------------------------------------

app.set('trust proxy', 1);


// ------------------------------------------------------------
// Security headers
// ------------------------------------------------------------

app.use(helmet());


// ------------------------------------------------------------
// Request parsing limits
// ------------------------------------------------------------

app.use(express.json({
    limit: '1mb'
}));

app.use(express.urlencoded({
    extended: false,
    limit: '1mb'
}));


// ------------------------------------------------------------
// Example health endpoint
// Keep health information minimal.
// ------------------------------------------------------------

app.get('/health', (req, res) => {
    res.json({
        status: 'ok'
    });
});


// ------------------------------------------------------------
// Application routes
// ------------------------------------------------------------

// app.use('/api', apiRouter);


// ------------------------------------------------------------
// 404
// ------------------------------------------------------------

app.use((req, res) => {
    res.status(404).json({
        error: 'Not found'
    });
});


// ------------------------------------------------------------
// Central error handler
// ------------------------------------------------------------

app.use((err, req, res, next) => {
    console.error(err);

    res.status(500).json({
        error: 'Internal server error'
    });
});


// ------------------------------------------------------------
// Listen only on localhost when Nginx is the public proxy.
// ------------------------------------------------------------

app.listen(3000, '127.0.0.1', () => {
    console.log('Application started');
});
```

This is only a starting skeleton. Authentication, authorization, CSRF, CORS, sessions, validation, database access, rate limiting, and logging must be configured according to the application architecture.

---

# Part XXVI — Nginx + Node.js Example

## 80. Reverse Proxy Example

```nginx
server {
    listen 80;
    server_name app.example.com;

    return 301 https://$host$request_uri;
}


server {
    listen 443 ssl;
    server_name app.example.com;

    ssl_certificate
        /etc/nginx/certs/server.crt;

    ssl_certificate_key
        /etc/nginx/certs/server.key;

    client_max_body_size 10m;

    add_header X-Content-Type-Options
        "nosniff"
        always;

    add_header Referrer-Policy
        "strict-origin-when-cross-origin"
        always;

    location / {
        proxy_pass http://127.0.0.1:3000;

        proxy_http_version 1.1;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;

        proxy_set_header X-Forwarded-For
            $proxy_add_x_forwarded_for;

        proxy_set_header X-Forwarded-Proto
            $scheme;
    }
}
```

Adapt TLS, proxy trust, timeouts, headers, rate limits, and request sizes to the real deployment.

---

# Part XXVII — Recommended Application Structure

## 81. Separation of Responsibilities

A maintainable Node.js backend might use:

```text
src/
|
+-- controllers/
|
+-- services/
|
+-- repositories/
|
+-- middleware/
|   +-- authentication.js
|   +-- authorization.js
|   +-- validation.js
|   +-- error-handler.js
|
+-- routes/
|
+-- config/
|
+-- security/
|
+-- app.js
|
+-- server.js
```

Architecture:

```text
Route
  |
  v
Middleware
  |
  +--> Authentication
  +--> Validation
  +--> Authorization
  |
  v
Controller
  |
  v
Service
  |
  v
Repository
  |
  v
Database
```

Do not put all security/business/database logic directly into route handlers.

---

# Part XXVIII — Security Checklist

## 82. Node.js Runtime

- [ ] Use a supported Node.js release.
- [ ] Keep Node.js patched.
- [ ] Run as a non-root user.
- [ ] Bind internal Node.js ports to localhost/private interfaces.
- [ ] Protect internal ports with a firewall.
- [ ] Use a controlled service/process manager.
- [ ] Set production configuration deliberately.
- [ ] Do not expose the Node.js inspector/debug port.

## 83. Secrets

- [ ] Do not hard-code credentials.
- [ ] Do not commit `.env` files containing secrets.
- [ ] Keep secrets out of browser JavaScript.
- [ ] Validate required configuration at startup.
- [ ] Rotate secrets/keys according to system requirements.
- [ ] Restrict access to secret storage.

## 84. HTTP

- [ ] Use HTTPS.
- [ ] Configure reverse-proxy trust correctly.
- [ ] Add appropriate security headers.
- [ ] Introduce CSP carefully.
- [ ] Limit request body sizes.
- [ ] Configure suitable timeouts.
- [ ] Rate-limit sensitive endpoints.

## 85. Application

- [ ] Validate all untrusted input.
- [ ] Prefer allow-lists.
- [ ] Use parameterized SQL.
- [ ] Prevent XSS with safe rendering/escaping.
- [ ] Enforce authentication.
- [ ] Enforce server-side authorization.
- [ ] Check object-level authorization.
- [ ] Implement CSRF protection where required.
- [ ] Configure CORS narrowly.
- [ ] Prevent mass assignment.
- [ ] Avoid command injection.
- [ ] Prevent path traversal.

## 86. Sessions / Tokens

- [ ] Use `HttpOnly` cookies.
- [ ] Use `Secure` cookies with HTTPS.
- [ ] Choose an appropriate SameSite policy.
- [ ] Use a strong session secret.
- [ ] Use a production session store.
- [ ] Regenerate sessions after login.
- [ ] Enforce session expiration.
- [ ] Keep JWT access tokens short-lived where appropriate.
- [ ] Validate JWT algorithms and claims.
- [ ] Protect JWT signing keys.
- [ ] Design refresh-token revocation/rotation carefully.

## 87. Uploads

- [ ] Limit upload size.
- [ ] Validate file content/type.
- [ ] Do not trust client filenames.
- [ ] Generate server-side storage names.
- [ ] Store protected uploads outside the public directory.
- [ ] Prevent uploaded files from becoming executable.
- [ ] Perform authorization before protected downloads.

## 88. Dependencies

- [ ] Keep dependencies minimal.
- [ ] Commit the package lock file.
- [ ] Use deterministic installation.
- [ ] Review dependency vulnerabilities.
- [ ] Test upgrades before production.
- [ ] Avoid untrusted packages/install scripts.
- [ ] Maintain an approved offline dependency process when Internet access is unavailable.

## 89. Logging

- [ ] Log important security events.
- [ ] Use structured logs where useful.
- [ ] Do not log passwords.
- [ ] Do not log raw session IDs.
- [ ] Do not log full JWTs/refresh tokens.
- [ ] Do not log private/encryption keys.
- [ ] Protect log files.
- [ ] Implement retention and rotation.

---

# Part XXIX — Recommended Hardening Order

## 90. Implementation Roadmap

```text
        Supported Node.js Version
                   |
                   v
        Non-Root Service Account
                   |
                   v
       Private Node.js Listener
                   |
                   v
             Nginx + HTTPS
                   |
                   v
          Secret Management
                   |
                   v
       Production Error Handling
                   |
                   v
          Input Validation
                   |
                   v
    Authentication + Authorization
                   |
                   v
          Secure Sessions/JWT
                   |
                   v
            CSRF + CORS
                   |
                   v
       SQL Injection Prevention
                   |
                   v
            XSS Prevention
                   |
                   v
       Secure Upload Handling
                   |
                   v
    Command/Path Injection Defense
                   |
                   v
      Request/Resource Limits
                   |
                   v
       Security Headers / CSP
                   |
                   v
      Dependency Management
                   |
                   v
       Logging and Monitoring
                   |
                   v
           Security Testing
```

---

# 91. Final Principle

Node.js security should combine:

```text
Supported Runtime
       +
Patched Dependencies
       +
Least Privilege
       +
Private Application Port
       +
Nginx / HTTPS
       +
Secret Management
       +
Input Validation
       +
Authentication
       +
Authorization
       +
Secure Sessions / Tokens
       +
CSRF / CORS Controls
       +
Parameterized Database Queries
       +
XSS Prevention
       +
Safe File Handling
       +
Command / Path Safety
       +
Resource Limits
       +
Security Headers
       +
Safe Error Handling
       +
Logging / Monitoring
       +
Testing
```

Do not rely on one package such as Helmet, one reverse-proxy setting, or one authentication library as the complete security solution. Security comes from the complete architecture and from consistently enforcing controls at every trust boundary.
