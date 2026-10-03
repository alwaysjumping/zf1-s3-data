# JWT Integration Guide for Zend Framework 1 (ZF1)

## 1. Purpose

This document summarizes the JWT design discussed for a legacy
application using:

-   Zend Framework 1 (ZF1)
-   PHP 7.4
-   MariaDB
-   API endpoints such as `/api/email/getlist`
-   Offline dependency deployment
-   `firebase/php-jwt`

The recommended design separates short-lived **JWT access tokens** from
longer-lived **refresh tokens**, and keeps JWT-specific code out of
individual controllers.

------------------------------------------------------------------------

## 2. What is JWT?

JWT means **JSON Web Token**. It is a compact standard for carrying
claims between systems.

A signed JWT normally has three parts:

``` text
HEADER.PAYLOAD.SIGNATURE
```

Example structure:

``` text
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9
.
eyJzdWIiOiIxMjMiLCJyb2xlIjoibWFuYWdlciJ9
.
<signature>
```

### 2.1 Header

The header identifies the token type and signing algorithm.

``` json
{
    "alg": "HS256",
    "typ": "JWT"
}
```

### 2.2 Payload

The payload contains claims.

``` json
{
    "iss": "https://example.com",
    "aud": "example-api",
    "sub": "12345",
    "role": "manager",
    "iat": 1790000000,
    "exp": 1790000900
}
```

Common claims include:

  Claim   Meaning
  ------- --------------------------------------
  `iss`   Issuer
  `aud`   Intended audience
  `sub`   Subject, commonly a user ID
  `iat`   Issued-at time
  `nbf`   Not valid before this time
  `exp`   Expiration time
  `jti`   Unique token identifier, when needed

Application-specific claims may also be added, but the token should
remain small and should not contain unnecessary sensitive information.

------------------------------------------------------------------------

## 3. JWT Is Normally Signed, Not Encrypted

A normal signed JWT protects **integrity and authenticity**, not
confidentiality.

The header and payload are Base64URL encoded. Anyone who obtains the
token can generally decode those parts.

Therefore, never put secrets such as these in a normal JWT payload:

``` json
{
    "password": "secret123",
    "private_key": "...",
    "database_password": "..."
}
```

A more appropriate payload is:

``` json
{
    "sub": "123",
    "role": "manager",
    "exp": 1790000900
}
```

Always protect API traffic with HTTPS.

------------------------------------------------------------------------

## 4. What Does the Signature Protect?

Suppose the server signs this payload:

``` json
{
    "sub": "123",
    "role": "user"
}
```

An attacker changes it to:

``` json
{
    "sub": "123",
    "role": "admin"
}
```

The original signature no longer matches the modified header/payload.

``` text
Original JWT
    |
    +-- role=user
    +-- valid signature
            |
            +--> accepted

Modified JWT
    |
    +-- role=admin
    +-- old signature
            |
            +--> signature verification fails
```

The server must verify the signature and validate the required claims
before trusting the token.

------------------------------------------------------------------------

## 5. Why Use JWT?

JWT is not mandatory for every web application.

A traditional ZF1 web application can continue using PHP sessions:

``` text
Browser
   |
Session cookie
   |
   v
ZF1
   |
PHP session
```

JWT becomes especially useful when an API is consumed by multiple
clients:

``` text
                  +-- Web application
                  |
API --------------+-- Mobile application
                  |
                  +-- Desktop application
                  |
                  +-- Other internal systems
```

Clients can send an access token instead of depending on a traditional
PHP session.

------------------------------------------------------------------------

## 6. Authentication Flow

A client first authenticates using its normal credentials.

``` http
POST /api/auth/login
Content-Type: application/json

{
    "username": "john",
    "password": "********"
}
```

The server performs normal authentication:

``` text
Client
   |
username/password
   |
   v
/api/auth/login
   |
   v
ZF1 authentication
   |
   v
MariaDB
   |
   +-- Invalid --> HTTP 401
   |
   +-- Valid --> issue access token + refresh token
```

An example response is:

``` json
{
    "access_token": "<JWT>",
    "refresh_token": "<random-refresh-token>",
    "token_type": "Bearer",
    "expires_in": 900
}
```

`900` seconds is 15 minutes. This is only an example; token lifetime
should be chosen according to the application's security and usability
requirements.

------------------------------------------------------------------------

## 7. Using an Access Token

The client sends the JWT using the HTTP `Authorization` header:

``` http
GET /api/email/getlist
Authorization: Bearer <access-token>
```

The server should perform checks such as:

``` text
Request
   |
Extract Bearer token
   |
   v
Verify signature
   |
   v
Require configured algorithm
   |
   v
Validate claims
   |
   +-- exp
   +-- nbf, if used
   +-- iss
   +-- aud
   |
   v
Identify user
   |
   v
Check account/session state when required
   |
   v
Check authorization/permissions
   |
   v
Controller action
```

Invalid or expired authentication normally results in:

``` text
HTTP 401 Unauthorized
```

A valid user who is authenticated but lacks permission for an operation
normally receives:

``` text
HTTP 403 Forbidden
```

------------------------------------------------------------------------

## 8. Authentication and Authorization Are Different

JWT authentication does not automatically authorize every operation.

``` text
Authentication
"Who are you?"

        !=

Authorization
"What are you allowed to do?"
```

For example:

``` text
JWT valid
   |
User ID = 123
   |
   +-- Read permitted records       -> allowed
   +-- Update own profile           -> allowed
   +-- Delete another user's data   -> denied
   +-- Administration               -> depends on permissions
```

Authorization should remain a separate application responsibility.

------------------------------------------------------------------------

## 9. Why Should Access Tokens Be Short-Lived?

A JWT access token is normally a bearer credential. Anyone possessing a
valid token may be able to use it until it expires, subject to any
additional server-side checks.

A very long lifetime increases the damage window if the token is stolen.

For example:

``` text
Issued:   10:00
Expires:  10:15
Lifetime: 15 minutes
```

A short lifetime limits the useful lifetime of a stolen access token.

However, short-lived access tokens create a usability problem: users
should not have to enter their password every few minutes.

That is the main reason for using a refresh token.

------------------------------------------------------------------------

## 10. What Is a Refresh Token?

A refresh token is a separate, longer-lived credential used to obtain a
new short-lived access token.

``` text
Access Token
------------
Purpose: access API resources
Lifetime: short
Sent: frequently with API requests


Refresh Token
-------------
Purpose: obtain a new access token
Lifetime: longer
Sent: only to the refresh endpoint
```

A refresh token should not normally be sent with every API request.

------------------------------------------------------------------------

## 11. Refresh Flow

Suppose the access token expires.

``` text
Client
   |
API request
   |
   v
Access token expired
   |
   v
HTTP 401
```

The client can then use its refresh credential:

``` http
POST /api/auth/refresh
```

Conceptually:

``` text
Refresh Token
     |
     v
/api/auth/refresh
     |
     v
Validate refresh token
     |
   Valid?
   /    \
 No      Yes
 |        |
401       v
       New access token
```

This allows the user to remain signed in without making the access token
long-lived.

------------------------------------------------------------------------

## 12. Why Not Use a 30-Day Access Token?

A 30-day JWT access token would be convenient, but if it is stolen, it
could potentially remain useful for a long time.

Separating the credentials gives better control:

  ---------------------------------------------------------------------------
  Property                Access Token                Refresh Token
  ----------------------- --------------------------- -----------------------
  Typical lifetime        Short                       Longer

  Used for normal API     Yes                         No
  requests                                            

  Purpose                 Resource access             Obtain new access token

  Sent frequently         Yes                         No

  Server-side tracking    Optional/design-dependent   Recommended

  Revocation              More difficult if fully     Designed to be
                          stateless                   manageable
  ---------------------------------------------------------------------------

This design combines short-lived API credentials with a longer login
session.

------------------------------------------------------------------------

## 13. Refresh Token Does Not Need to Be a JWT

For this ZF1 architecture, a useful design is:

``` text
Access Token  --> JWT
Refresh Token --> cryptographically random opaque value
```

PHP 7.4 can generate a secure random value using:

``` php
$refreshToken = bin2hex(random_bytes(32));
```

This creates 32 random bytes (256 bits) represented as 64 hexadecimal
characters.

------------------------------------------------------------------------

## 14. Do Not Store Raw Refresh Tokens in MariaDB

Instead of storing the reusable raw refresh token, store a cryptographic
hash.

``` php
$tokenHash = hash('sha256', $refreshToken);
```

Flow:

``` text
Raw refresh token
      |
      +--> sent to client
      |
      v
    SHA-256
      |
      v
token hash stored in MariaDB
```

A possible table is:

``` sql
CREATE TABLE refresh_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_refresh_token_hash (token_hash),
    KEY idx_refresh_user_id (user_id)
) ENGINE=InnoDB;
```

Additional fields can later be added for token families, devices,
sessions, replacement tokens, IP/security metadata, or rotation/reuse
detection.

------------------------------------------------------------------------

## 15. Refresh-Token Rotation

A stronger design rotates refresh tokens whenever they are used.

``` text
Login
  |
  +-- Access A
  +-- Refresh A

Refresh A used
  |
  +-- invalidate Refresh A
  +-- issue Access B
  +-- issue Refresh B

Refresh B used
  |
  +-- invalidate Refresh B
  +-- issue Access C
  +-- issue Refresh C
```

An already-used refresh token should not remain usable indefinitely.

A mature implementation can also detect reuse of an old token and revoke
the associated token family/session.

------------------------------------------------------------------------

## 16. Logout

A stateless access JWT may remain cryptographically valid until its
expiration time unless the application performs additional server-side
revocation/session checks.

A practical logout design is:

``` text
Logout
   |
   v
Revoke refresh token/session
   |
   v
No additional access tokens can be issued
   |
   v
Existing short-lived access token expires soon
```

Applications requiring immediate revocation can maintain additional
server-side session or access-token state, although doing so reduces
some of the statelessness benefit of JWT.

------------------------------------------------------------------------

# 17. Recommended ZF1 Architecture

JWT logic should not be scattered across API controllers.

A cleaner structure is:

``` text
application/
|
+-- controllers/
|   +-- Api/
|       +-- AuthController.php
|
+-- models/
|   +-- DbTable/
|       +-- RefreshTokens.php
|
+-- services/
|   +-- Auth/
|       +-- JwtService.php
|       +-- RefreshTokenService.php
|       +-- AuthenticationService.php
|
+-- plugins/
    +-- ApiAuthentication.php
```

Responsibilities:

### `JwtService`

``` text
createAccessToken()
verifyAccessToken()
extract/validate claims
```

### `RefreshTokenService`

``` text
create()
validate()
rotate()
revoke()
```

### `AuthenticationService`

``` text
authenticate username/password
load authenticated user
enforce account-level authentication rules
```

### `ApiAuthentication` front-controller plugin

``` text
detect protected API request
extract Authorization header
validate Bearer token
establish authenticated user context
reject invalid requests
```

------------------------------------------------------------------------

## 18. Recommended API Flow

``` text
                        CLIENT
                           |
                    username/password
                           |
                           v
                    /api/auth/login
                           |
                           v
                    ZF1 authentication
                           |
                     +-----+-----+
                     |           |
                  Invalid       Valid
                     |           |
                    401          v
                         +---------------+
                         | JWT Access    |
                         | Token         |
                         +---------------+
                                 +
                         +---------------+
                         | Random Refresh|
                         | Token         |
                         +---------------+

                              |
                              v

GET /api/email/getlist
Authorization: Bearer <JWT>
                              |
                              v
                    ApiAuthentication
                              |
                        verify token
                              |
                     +--------+--------+
                     |                 |
                  Invalid             Valid
                     |                 |
                    401                v
                              Authorization
                                    check
                                  /       \
                               denied    allowed
                                 |          |
                                403         v
                                        API action
```

Refresh:

``` text
POST /api/auth/refresh
        |
        v
Validate refresh token
        |
        v
Rotate refresh token
        |
        +-- New JWT access token
        |
        +-- New refresh token
```

------------------------------------------------------------------------

# 19. `firebase/php-jwt`

The library selected for the project discussion is:

``` text
firebase/php-jwt
```

The application should hide direct library calls behind its own service
instead of using `JWT::encode()` and `JWT::decode()` throughout
controllers.

Prefer:

``` php
$token = $jwtService->createAccessToken($userId);

$claims = $jwtService->verifyAccessToken($token);
```

over controller code that directly depends on:

``` php
Firebase\JWT\JWT::encode(...);
Firebase\JWT\JWT::decode(...);
```

This makes later library/runtime migration easier.

------------------------------------------------------------------------

## 20. PHP 7.4 Compatibility Issue

The application currently uses PHP 7.4.

During the installation discussion, `firebase/php-jwt 6.10.0` was
selected because it supports PHP 7.4. However, Composer currently
reports a security advisory affecting the pre-7.0 package line.

The Composer advisory identifier encountered was:

``` text
PKSA-y2cr-5h3j-g3ys
```

Therefore, this is a deliberate legacy-compatibility decision and should
be documented and periodically reviewed.

Do not disable Composer's security checks globally merely to install one
package.

If an exception is required, scope it only to the specific advisory and
retain audit visibility.

------------------------------------------------------------------------

## 21. Offline Installation Strategy

The recommended offline process is:

``` text
Internet-connected preparation computer
        |
        | Composer resolves package
        v
composer.lock + vendor/
        |
        | approved offline transfer
        v
Offline application environment
```

On the online preparation machine, create a dedicated directory, for
example:

``` powershell
mkdir C:\offline-packages\php-jwt
cd C:\offline-packages\php-jwt
```

A pinned dependency is preferable:

``` json
{
    "require": {
        "firebase/php-jwt": "6.10.0"
    }
}
```

After Composer successfully resolves the approved dependency set,
transfer the complete result, including:

``` text
composer.json
composer.lock
vendor/
```

Do not copy only `JWT.php`. The library uses namespaced classes and
Composer/PSR-4 autoloading.

The offline server does not need Composer merely to load a previously
prepared `vendor` directory.

------------------------------------------------------------------------

## 22. Loading Composer Dependencies in ZF1

The Composer autoloader can coexist with ZF1's legacy autoloading.

For example:

``` php
require_once dirname(__DIR__) . '/vendor/autoload.php';
```

Then library classes can be imported:

``` php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
```

The rest of the legacy ZF1 application does not have to be converted to
namespaces.

------------------------------------------------------------------------

## 23. Basic JWT Library Test

After offline installation, first test the library independently before
integrating it into ZF1.

Example using HS256:

``` php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = 'replace-with-a-long-cryptographically-random-secret';

$now = time();

$payload = array(
    'iss' => 'https://example.com',
    'aud' => 'example-api',
    'sub' => '123',
    'iat' => $now,
    'exp' => $now + 900
);

$token = JWT::encode(
    $payload,
    $key,
    'HS256'
);

echo $token . PHP_EOL;

$decoded = JWT::decode(
    $token,
    new Key($key, 'HS256')
);

print_r($decoded);
```

Do not use the literal example key in production.

------------------------------------------------------------------------

## 24. HS256 vs. RS256

### HS256

HS256 uses one shared secret.

``` text
Signing:
server + secret --> JWT

Verification:
server + same secret --> valid/invalid
```

Advantages:

-   Simple
-   Fast
-   Easy for one application

Important consideration:

-   Every component that can verify using the shared secret can also
    create valid signatures if it possesses that same secret.

### RS256

RS256 uses an RSA key pair.

``` text
Private key --> signing

Public key  --> verification
```

Advantages:

-   Private signing key can remain isolated
-   Other services can verify using only the public key

For RSA, use modern, sufficiently strong keys (at least 2048 bits) and
follow the security requirements of the library/version in use.

The algorithm should be a server-side configuration decision. Do not
dynamically accept arbitrary algorithms just because the JWT header
requests them.

------------------------------------------------------------------------

# 25. Composer Security Advisory

The installation attempt for `firebase/php-jwt 6.10.0` was blocked by
Composer because of:

``` text
PKSA-y2cr-5h3j-g3ys
```

This advisory is associated with the legacy package line being
installed.

If the project deliberately accepts that risk for PHP 7.4 compatibility,
keep the exception narrow:

``` text
Good:
specific package/version
+
specific advisory exception
+
documented reason
+
continued composer audit
+
migration plan

Avoid:
disable all Composer security checks globally
```

Run security audits on the online dependency-preparation environment:

``` powershell
composer audit
```

A security exception should be treated as temporary technical debt and
reviewed when the PHP runtime can be upgraded.

------------------------------------------------------------------------

## 26. Xdebug Message During Composer

A message such as:

``` text
Xdebug: [Step Debug] Time-out connecting to debugging client...
```

is unrelated to JWT or Composer dependency resolution.

It means command-line PHP attempted to connect to an Xdebug debugging
client and could not.

It does not explain the Composer security-advisory rejection.

------------------------------------------------------------------------

# 27. Suggested Access-Token Policy

A starting policy could be:

``` text
Type:        JWT
Algorithm:   one explicitly configured algorithm
Lifetime:    approximately 10-15 minutes
Transport:   HTTPS only
Header:      Authorization: Bearer <token>
```

Claims could include:

``` json
{
    "iss": "your-issuer",
    "aud": "your-api",
    "sub": "12345",
    "iat": 1790000000,
    "exp": 1790000900
}
```

Keep authorization claims minimal. For permissions that can change
frequently, consider resolving current authorization from server-side
state rather than embedding a large, long-lived permission snapshot in
the token.

------------------------------------------------------------------------

# 28. Suggested Refresh-Token Policy

A starting design could be:

``` text
Type:            opaque random value
Randomness:      256 bits
Storage:         SHA-256 hash in MariaDB
Lifetime:        longer than access token
Rotation:        yes
Revocation:      yes
Raw DB storage:  no
```

Example generation:

``` php
$refreshToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $refreshToken);
```

The raw refresh token is returned to the authorized client. Only the
hash is persisted.

------------------------------------------------------------------------

# 29. Error Handling

A consistent API policy is useful.

Examples:

``` text
Missing Authorization header
    --> 401

Malformed Bearer token
    --> 401

Invalid JWT signature
    --> 401

Expired access token
    --> 401

Invalid issuer/audience
    --> 401

Revoked/invalid refresh token
    --> 401

Authenticated but insufficient permission
    --> 403
```

Avoid returning detailed cryptographic error information to clients.

Detailed errors can be written to protected server logs where
appropriate.

------------------------------------------------------------------------

# 30. Security Checklist

Before production use:

-   Use HTTPS.
-   Never put passwords or private secrets in JWT payloads.
-   Keep access tokens short-lived.
-   Use one explicitly configured signing algorithm.
-   Use strong signing keys/secrets.
-   Validate signatures before trusting claims.
-   Validate `exp`.
-   Validate `iss` and `aud`.
-   Validate `nbf` when used.
-   Do not trust authorization merely because the JWT is valid.
-   Generate refresh tokens using `random_bytes()`.
-   Store refresh-token hashes rather than raw refresh tokens.
-   Rotate refresh tokens.
-   Support refresh-token revocation.
-   Revoke refresh sessions on logout.
-   Protect signing keys and HMAC secrets outside source control.
-   Do not log complete access or refresh tokens.
-   Rate-limit login and refresh endpoints.
-   Keep Composer dependencies pinned and audited.
-   Document any security-advisory exception.
-   Plan migration away from unsupported legacy runtime/dependency
    combinations.

------------------------------------------------------------------------

# 31. Recommended Implementation Order

A practical implementation sequence for the ZF1 project is:

``` text
1. Define JWT security policy
       |
       v
2. Install/test firebase/php-jwt offline
       |
       v
3. Create JwtService
       |
       v
4. Create /api/auth/login
       |
       v
5. Create ApiAuthentication plugin
       |
       v
6. Protect API endpoints
       |
       v
7. Create refresh_tokens table
       |
       v
8. Create RefreshTokenService
       |
       v
9. Create /api/auth/refresh
       |
       v
10. Add refresh-token rotation
       |
       v
11. Add logout/revocation
       |
       v
12. Add PHPUnit unit/integration tests
       |
       v
13. Security review
```

------------------------------------------------------------------------

# 32. Final Architecture

``` text
Client
  |
  +-- POST /api/auth/login
  |        |
  |        v
  |    AuthenticationService
  |        |
  |        +--> JwtService --------> JWT access token
  |        |
  |        +--> RefreshTokenService -> refresh token
  |
  +-- GET /api/...
  |        |
  |   Authorization: Bearer <JWT>
  |        |
  |        v
  |   ApiAuthentication
  |        |
  |        +--> JwtService
  |        |
  |        +--> authorization check
  |        |
  |        +--> controller
  |
  +-- POST /api/auth/refresh
  |        |
  |        v
  |   RefreshTokenService
  |        |
  |        +--> validate
  |        +--> rotate
  |        +--> revoke old token
  |        |
  |        +--> JwtService --> new access token
  |
  +-- POST /api/auth/logout
           |
           v
      revoke refresh session
```

------------------------------------------------------------------------

## 33. Key Takeaway

The simplest way to remember the design is:

``` text
Access Token
=
short-lived API pass

Refresh Token
=
longer-lived credential used to obtain
another short-lived API pass
```

For this ZF1 application, JWT should be treated as one part of the
authentication architecture---not as a replacement for HTTPS,
authorization, secure session management, database security, or normal
application security controls.
