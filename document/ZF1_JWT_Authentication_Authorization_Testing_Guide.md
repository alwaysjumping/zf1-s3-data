# ZF1 JWT Authentication, Authorization, Refresh Tokens, and Testing Guide

## 1. Purpose

This document consolidates the JWT/API authentication design discussed
for the existing Zend Framework 1 application.

### Target environment

-   Zend Framework 1 (ZF1)
-   PHP 7.4
-   MariaDB
-   DHTMLX 3.5 frontend
-   `firebase/php-jwt` 6.10.0
-   Composer dependencies prepared on an online development machine and
    transferred to offline environments
-   Custom application library prefix: `User_`

> **Important:** The examples use representative user-table fields such
> as `id`, `username`, `password_hash`, and `active`. Replace these with
> the actual fields in the existing application.

------------------------------------------------------------------------

## 2. Architecture

The recommended design separates authentication, authorization, and
refresh-session management.

``` text
Client
  |
  | POST /api/auth/login
  v
ZF1 AuthController
  |
  +-- Verify username/password against MariaDB
  |
  +-- User_JwtService
  |      |
  |      +-- Issue short-lived access JWT
  |
  +-- User_RefreshTokenService
         |
         +-- Issue opaque refresh token
         +-- Store only SHA-256 hash in MariaDB

Normal API request
  |
  | Authorization: Bearer <access JWT>
  v
User_ApiAuthPlugin
  |
  +-- Extract Bearer token
  +-- Verify signature and claims
  +-- Read user ID from sub
  +-- Load current user from database
  +-- Reject disabled/deleted users
  v
API Controller / Service
  |
  v
User_AuthorizationService
  |
  +-- Check roles/permissions
```

Authentication answers **who the user is**. Authorization answers **what
the authenticated user may do**.

------------------------------------------------------------------------

## 3. Project Structure

A suggested structure is:

``` text
zf1-s3/
├── application/
│   ├── Bootstrap.php
│   ├── configs/
│   │   └── application.ini
│   └── modules/
│       └── api/
│           └── controllers/
│               ├── AuthController.php
│               └── ErrorController.php
├── library/
│   └── User/
│       ├── ApiAuthPlugin.php
│       ├── AuthorizationService.php
│       ├── JwtException.php
│       ├── JwtService.php
│       ├── RefreshTokenService.php
│       └── DbTable/
│           └── RefreshTokens.php
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   │   └── JwtServiceTest.php
│   ├── Integration/
│   │   └── RefreshTokenServiceTest.php
│   └── Functional/
│       ├── ApiTestCase.php
│       ├── AuthControllerTest.php
│       └── EmailControllerTest.php
├── vendor/
│   └── autoload.php
└── phpunit.xml
```

ZF1 underscore autoloading maps:

``` text
User_JwtService
    ->
User/JwtService.php
```

Register the namespace:

``` php
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('User_');
```

------------------------------------------------------------------------

## 4. Installing `firebase/php-jwt` for an Offline Environment

On an internet-connected development computer, create or update
`composer.json` and install the exact PHP-7.4-compatible package
selected for this project.

``` json
{
    "require": {
        "firebase/php-jwt": "6.10.0"
    }
}
```

Run Composer on the online machine and transfer the complete Composer
installation output to the offline server:

``` text
composer.json
composer.lock
vendor/
```

Do not manually copy only individual PHP files from the JWT package.
Preserve Composer's generated autoloader and dependency layout.

Load it near application startup:

``` php
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($vendorAutoload)) {
    throw new RuntimeException(
        'Composer autoloader was not found.'
    );
}

require_once $vendorAutoload;
```

### Security-advisory note

The selected 6.10.0 version was chosen because the project remains on
PHP 7.4. Security advisories and package support ranges can change.
Re-check the current upstream advisory information before production
deployment and whenever PHP or the JWT package is upgraded. Do not
globally disable Composer security checks merely to make an installation
succeed.

------------------------------------------------------------------------

## 5. JWT Fundamentals

A JWT consists of:

``` text
HEADER.PAYLOAD.SIGNATURE
```

A signed JWT is **not encrypted**. Anyone possessing it can decode the
header and payload. Therefore never put passwords, private keys,
database credentials, or other secrets in JWT claims.

The signature protects integrity:

``` text
payload modified
    ->
signature no longer matches
    ->
token rejected
```

For this project:

``` text
Algorithm: HS256
Access-token lifetime: 900 seconds (15 minutes)
Issuer: s3
Audience: s3-api
```

The JWT identifies the user primarily through:

``` text
sub = user ID
```

Permissions should normally be checked against current server-side data
instead of embedding the complete permission set in a long-lived token.

------------------------------------------------------------------------

## 6. Signing Secret

Do not commit the HS256 signing secret to source control or hardcode it
in `application.ini`.

The application can obtain it through an environment variable:

``` php
$secret = getenv('S3_JWT_SECRET');

if ($secret === false || $secret === '') {
    throw new RuntimeException(
        'S3_JWT_SECRET is not configured.'
    );
}
```

For Apache, one possible deployment mechanism is:

``` apache
SetEnv S3_JWT_SECRET "your-random-secret"
```

`SetEnv` is only one mechanism. OS environment variables, deployment
configuration, PHP-FPM environment configuration, or a
secrets-management system can also be used.

Generate a cryptographically random secret rather than a human password:

``` powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

This produces 32 random bytes represented as 64 hexadecimal characters.

------------------------------------------------------------------------

## 7. Application Configuration

`application/configs/application.ini`:

``` ini
jwt.algorithm = "HS256"
jwt.issuer = "s3"
jwt.audience = "s3-api"
jwt.accessTokenLifetime = 900
```

The secret is intentionally absent.

------------------------------------------------------------------------

## 8. JWT Exception Class

`library/User/JwtException.php`:

``` php
<?php

class User_JwtException extends RuntimeException
{
    const TOKEN_INVALID = 1001;
    const TOKEN_EXPIRED = 1002;
    const TOKEN_INVALID_SIGNATURE = 1003;
    const TOKEN_NOT_YET_VALID = 1004;
    const TOKEN_INVALID_CLAIMS = 1005;

    protected $jwtErrorCode;

    public function __construct(
        $message,
        $jwtErrorCode,
        Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->jwtErrorCode = $jwtErrorCode;
    }

    public function getJwtErrorCode()
    {
        return $this->jwtErrorCode;
    }
}
```

------------------------------------------------------------------------

## 9. JWT Service

`library/User/JwtService.php`:

``` php
<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class User_JwtService
{
    protected $secret;
    protected $algorithm;
    protected $issuer;
    protected $audience;
    protected $accessTokenLifetime;

    public function __construct(array $config)
    {
        $this->secret = $config['secret'];
        $this->algorithm = $config['algorithm'];
        $this->issuer = $config['issuer'];
        $this->audience = $config['audience'];
        $this->accessTokenLifetime =
            (int) $config['accessTokenLifetime'];

        if ($this->secret === '') {
            throw new InvalidArgumentException(
                'JWT secret is required.'
            );
        }

        if ($this->algorithm !== 'HS256') {
            throw new InvalidArgumentException(
                'Only HS256 is configured.'
            );
        }

        if ($this->accessTokenLifetime <= 0) {
            throw new InvalidArgumentException(
                'Invalid access token lifetime.'
            );
        }
    }

    public function createAccessToken(
        $userId,
        array $additionalClaims = array()
    ) {
        $now = time();

        $payload = array(
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTokenLifetime,
            'jti' => bin2hex(random_bytes(16))
        );

        unset(
            $additionalClaims['iss'],
            $additionalClaims['aud'],
            $additionalClaims['sub'],
            $additionalClaims['iat'],
            $additionalClaims['nbf'],
            $additionalClaims['exp'],
            $additionalClaims['jti']
        );

        $payload = array_merge(
            $payload,
            $additionalClaims
        );

        return JWT::encode(
            $payload,
            $this->secret,
            $this->algorithm
        );
    }

    public function verifyAccessToken($token)
    {
        if (!is_string($token) || $token === '') {
            throw new User_JwtException(
                'JWT token is empty.',
                User_JwtException::TOKEN_INVALID
            );
        }

        try {
            $decoded = JWT::decode(
                $token,
                new Key(
                    $this->secret,
                    $this->algorithm
                )
            );
        } catch (ExpiredException $e) {
            throw new User_JwtException(
                'JWT token has expired.',
                User_JwtException::TOKEN_EXPIRED,
                $e
            );
        } catch (BeforeValidException $e) {
            throw new User_JwtException(
                'JWT token is not valid yet.',
                User_JwtException::TOKEN_NOT_YET_VALID,
                $e
            );
        } catch (SignatureInvalidException $e) {
            throw new User_JwtException(
                'JWT signature is invalid.',
                User_JwtException::TOKEN_INVALID_SIGNATURE,
                $e
            );
        } catch (UnexpectedValueException $e) {
            throw new User_JwtException(
                'JWT token is invalid.',
                User_JwtException::TOKEN_INVALID,
                $e
            );
        } catch (DomainException $e) {
            throw new User_JwtException(
                'JWT token is invalid.',
                User_JwtException::TOKEN_INVALID,
                $e
            );
        }

        $claims = (array) $decoded;
        $this->validateClaims($claims);

        return $claims;
    }

    protected function validateClaims(array $claims)
    {
        if (
            !isset($claims['iss'])
            || $claims['iss'] !== $this->issuer
        ) {
            throw new User_JwtException(
                'Invalid JWT issuer.',
                User_JwtException::TOKEN_INVALID_CLAIMS
            );
        }

        if (
            !isset($claims['aud'])
            || $claims['aud'] !== $this->audience
        ) {
            throw new User_JwtException(
                'Invalid JWT audience.',
                User_JwtException::TOKEN_INVALID_CLAIMS
            );
        }

        if (
            !isset($claims['sub'])
            || $claims['sub'] === ''
        ) {
            throw new User_JwtException(
                'JWT subject is missing.',
                User_JwtException::TOKEN_INVALID_CLAIMS
            );
        }

        if (!isset($claims['exp'])) {
            throw new User_JwtException(
                'JWT expiration is missing.',
                User_JwtException::TOKEN_INVALID_CLAIMS
            );
        }
    }

    public function getAccessTokenLifetime()
    {
        return $this->accessTokenLifetime;
    }
}
```

------------------------------------------------------------------------

## 10. Refresh Tokens

The access JWT is intentionally short-lived. A separate refresh token
allows the client to obtain another access JWT without submitting the
user's password again.

The refresh token is:

-   opaque rather than a JWT;
-   generated using `random_bytes()`;
-   longer-lived than the access JWT;
-   sent only to the refresh/logout endpoints;
-   stored in MariaDB only as a SHA-256 hash;
-   rotated whenever it is successfully used.

Generation:

``` php
$refreshToken = bin2hex(random_bytes(32));
```

Hash:

``` php
$tokenHash = hash('sha256', $refreshToken);
```

The raw token is returned to the client but never persisted.

------------------------------------------------------------------------

## 11. Refresh-Token Database

``` sql
CREATE TABLE refresh_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    family_id CHAR(32) NOT NULL,
    parent_token_id BIGINT UNSIGNED NULL,
    replaced_by_token_id BIGINT UNSIGNED NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoke_reason VARCHAR(50) NULL,
    created_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_refresh_token_hash (token_hash),
    KEY idx_refresh_user (user_id),
    KEY idx_refresh_family (family_id),
    KEY idx_refresh_parent (parent_token_id),
    KEY idx_refresh_expiry (expires_at),
    KEY idx_refresh_user_active (
        user_id,
        revoked_at,
        expires_at
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

If upgrading an existing populated table, do not blindly add a
`NOT NULL family_id` column in one step. A safer migration is:

1.  Add it nullable.
2.  Populate existing rows.
3.  Verify the data.
4.  Change the column to `NOT NULL`.

Foreign keys can be added after the exact type of the application's real
user ID is confirmed.

------------------------------------------------------------------------

## 12. Refresh-Token DbTable

`library/User/DbTable/RefreshTokens.php`:

``` php
<?php

class User_DbTable_RefreshTokens
    extends Zend_Db_Table_Abstract
{
    protected $_name = 'refresh_tokens';
    protected $_primary = 'id';
}
```

------------------------------------------------------------------------

## 13. Refresh-Token Service

`library/User/RefreshTokenService.php`:

``` php
<?php

class User_RefreshTokenService
{
    const TOKEN_BYTES = 32;
    const TOKEN_LIFETIME = 2592000; // 30 days

    protected $table;

    public function __construct(
        User_DbTable_RefreshTokens $table = null
    ) {
        if ($table === null) {
            $table = new User_DbTable_RefreshTokens();
        }

        $this->table = $table;
    }

    public function hashToken($token)
    {
        return hash('sha256', $token);
    }

    public function create(
        $userId,
        $ipAddress = null,
        $userAgent = null,
        $familyId = null,
        $parentTokenId = null
    ) {
        if ($familyId === null) {
            $familyId = bin2hex(random_bytes(16));
        }

        $token = bin2hex(
            random_bytes(self::TOKEN_BYTES)
        );

        $now = time();
        $expiresAt = $now + self::TOKEN_LIFETIME;

        $id = $this->table->insert(
            array(
                'user_id' => $userId,
                'family_id' => $familyId,
                'parent_token_id' => $parentTokenId,
                'token_hash' => $this->hashToken($token),
                'created_at' => date('Y-m-d H:i:s', $now),
                'expires_at' => date(
                    'Y-m-d H:i:s',
                    $expiresAt
                ),
                'created_ip' => $ipAddress,
                'user_agent' => $userAgent
            )
        );

        return array(
            'id' => $id,
            'token' => $token,
            'family_id' => $familyId,
            'expires_at' => $expiresAt
        );
    }

    public function findToken($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $select = $this->table
            ->select()
            ->where(
                'token_hash = ?',
                $this->hashToken($token)
            )
            ->limit(1);

        $row = $this->table->fetchRow($select);

        return $row ?: null;
    }

    public function rotate(
        $oldToken,
        $ipAddress = null,
        $userAgent = null
    ) {
        $row = $this->findToken($oldToken);

        if (!$row) {
            return false;
        }

        if ($row->revoked_at !== null) {
            if ($row->revoke_reason === 'rotated') {
                $this->revokeFamily(
                    $row->family_id,
                    'reuse_detected'
                );
            }

            return false;
        }

        if (strtotime($row->expires_at) <= time()) {
            return false;
        }

        $db = $this->table->getAdapter();
        $db->beginTransaction();

        try {
            $now = date('Y-m-d H:i:s');

            $updated = $this->table->update(
                array(
                    'revoked_at' => $now,
                    'last_used_at' => $now,
                    'revoke_reason' => 'rotated'
                ),
                array(
                    'id = ?' => $row->id,
                    'revoked_at IS NULL'
                )
            );

            if ($updated !== 1) {
                $db->rollBack();
                return false;
            }

            $newToken = $this->create(
                $row->user_id,
                $ipAddress,
                $userAgent,
                $row->family_id,
                $row->id
            );

            $this->table->update(
                array(
                    'replaced_by_token_id' =>
                        $newToken['id']
                ),
                array(
                    'id = ?' => $row->id
                )
            );

            $db->commit();

            return array(
                'user_id' => $row->user_id,
                'refresh_token' =>
                    $newToken['token'],
                'refresh_token_expires_at' =>
                    $newToken['expires_at']
            );
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function revokeFamily(
        $familyId,
        $reason = 'revoked'
    ) {
        return $this->table->update(
            array(
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoke_reason' => $reason
            ),
            array(
                'family_id = ?' => $familyId,
                'revoked_at IS NULL'
            )
        );
    }

    public function logout($token)
    {
        $row = $this->findToken($token);

        if (!$row) {
            return true;
        }

        $this->revokeFamily(
            $row->family_id,
            'logout'
        );

        return true;
    }

    public function revokeAllForUser(
        $userId,
        $reason = 'logout_all'
    ) {
        return $this->table->update(
            array(
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoke_reason' => $reason
            ),
            array(
                'user_id = ?' => $userId,
                'revoked_at IS NULL'
            )
        );
    }
}
```

### Rotation model

``` text
Login
  |
  +-- Family X
       |
       A

Refresh with A
       |
       A revoked as "rotated"
       |
       B

Refresh with B
       |
       B revoked as "rotated"
       |
       C
```

If an already rotated token is later presented again, the service treats
it as potential reuse and revokes the active family.

A conditional update:

``` sql
... WHERE id = ? AND revoked_at IS NULL
```

prevents two ordinary concurrent requests from both successfully
consuming the same refresh token.

------------------------------------------------------------------------

## 14. Multiple Devices and Token Families

Each independent login receives a different family:

``` text
User 1001

Desktop
  Family A
     A1 -> A2 -> A3

Laptop
  Family B
     B1 -> B2

Phone
  Family C
     C1
```

Logging out the desktop can revoke only Family A while the laptop and
phone remain logged in.

"Logout everywhere" revokes every active refresh token belonging to the
user.

------------------------------------------------------------------------

## 15. Login Endpoint

Recommended endpoint:

``` text
POST /api/auth/login
Content-Type: application/json
```

Request:

``` json
{
    "username": "john",
    "password": "secret"
}
```

Response:

``` json
{
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 900
}
```

Do not send passwords in URL query strings.

The login implementation should:

1.  Require POST.
2.  Parse JSON.
3.  Validate required input.
4.  Authenticate using the application's existing
    password/authentication mechanism.
5.  Verify current account state.
6.  Create an access JWT.
7.  Create a refresh token.
8.  Return both credentials.

Use a generic authentication error such as:

``` json
{
    "error": "invalid_credentials",
    "message": "Invalid username or password."
}
```

This avoids revealing whether a username exists.

If the legacy application does not currently use `password_hash()` /
`password_verify()`, integrate with the existing authentication
mechanism instead of silently changing password storage.

------------------------------------------------------------------------

## 16. Refresh Endpoint

``` text
POST /api/auth/refresh
```

Request:

``` json
{
    "refresh_token": "..."
}
```

Successful refresh:

``` text
Refresh A
    |
    +-- validate
    +-- revoke A
    +-- create B
    +-- create new access JWT
```

Response:

``` json
{
    "access_token": "...",
    "refresh_token": "...B...",
    "token_type": "Bearer",
    "expires_in": 900
}
```

The client must replace the old refresh token with the newly returned
token.

Before issuing a new access JWT, the refresh endpoint should also
confirm that the associated user still exists and is still permitted to
log in.

------------------------------------------------------------------------

## 17. Logout

Recommended request:

``` text
POST /api/auth/logout
Authorization: Bearer <access JWT>
```

Body:

``` json
{
    "refresh_token": "..."
}
```

Logout revokes the refresh-token family.

It should be idempotent: presenting an unknown/already-revoked refresh
token should not reveal whether that credential ever existed.

### Access-token behavior after logout

A stateless access JWT already issued before logout can remain valid
until its `exp` time. With a 15-minute lifetime, the maximum normal
residual window is short.

Immediate access-token invalidation would require additional server-side
revocation/session state. That complexity is intentionally not part of
the baseline design.

------------------------------------------------------------------------

## 18. Central API Authentication Plugin

The API controllers should not individually call `JWT::decode()`.

Instead:

``` text
Request
  |
  v
User_ApiAuthPlugin
  |
  +-- Is API route?
  +-- Is route public?
  +-- Extract Authorization header
  +-- Require Bearer
  +-- User_JwtService::verifyAccessToken()
  +-- Load current user from DB
  +-- Check current account status
  +-- Store API identity
  v
Controller
```

Suggested public routes:

``` php
protected $publicRoutes = array(
    'api/auth/login',
    'api/auth/refresh',
    'api/error/unauthorized'
);
```

Logout remains protected.

### Bearer header

``` http
Authorization: Bearer eyJ...
```

Never accept access tokens through URLs such as:

``` text
/api/email/getlist?token=...
```

URLs can be copied into logs, browser history, proxy logs, and
analytics.

### Apache header compatibility

The plugin can first use:

``` php
$request->getHeader('Authorization');
```

and, if necessary, fall back to:

``` php
$_SERVER['HTTP_AUTHORIZATION']
```

depending on the Apache/PHP deployment.

------------------------------------------------------------------------

## 19. Current User

After successful authentication:

``` php
Zend_Registry::set(
    'apiCurrentUser',
    $user
);

Zend_Registry::set(
    'apiJwtClaims',
    $claims
);
```

Controllers can obtain the current user:

``` php
$user = Zend_Registry::get(
    'apiCurrentUser'
);
```

The database account should still be checked on protected requests when
current account status matters. A correctly signed JWT should not allow
a deleted, locked, or disabled account to continue indefinitely.

------------------------------------------------------------------------

## 20. Routing Requirement

The authentication plugin example assumes:

``` text
/api/email/getlist

module     = api
controller = email
action     = getlist
```

Verify the application's actual custom routing.

If `/api/email/getlist` resolves differently, update:

``` php
isApiRequest()
isPublicRoute()
```

to match the real ZF1 routing model.

------------------------------------------------------------------------

## 21. Authentication Error Handling

Authentication failures should produce:

``` text
HTTP 401 Unauthorized
WWW-Authenticate: Bearer
Content-Type: application/json
```

Example:

``` json
{
    "error": "invalid_token",
    "message": "Authentication required."
}
```

Do not expose low-level JWT verification exceptions to clients.

A dedicated API error action is preferable to duplicating JSON
authentication-error responses across controllers.

Be careful to make the unauthorized error route itself public; otherwise
forwarding an authentication failure to it can create an authentication
loop.

------------------------------------------------------------------------

## 22. Authorization

JWT authentication and permission authorization are separate.

``` text
Valid JWT
   |
   v
Authenticated user
   |
   v
Permission check
   |
   +-- allowed -> continue
   |
   +-- denied  -> HTTP 403
```

Typical permission names:

``` text
email.read
email.create
email.delete
blog.read
blog.create
blog.delete
```

Suggested schema:

``` sql
CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_name (name)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_name (name)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Service:

``` php
<?php

class User_AuthorizationService
{
    protected $db;

    public function __construct(
        Zend_Db_Adapter_Abstract $db
    ) {
        $this->db = $db;
    }

    public function isAllowed(
        $userId,
        $permission
    ) {
        $sql = '
            SELECT COUNT(*)
            FROM user_roles ur
            INNER JOIN role_permissions rp
                ON rp.role_id = ur.role_id
            INNER JOIN permissions p
                ON p.id = rp.permission_id
            WHERE ur.user_id = ?
              AND p.name = ?
        ';

        $count = $this->db->fetchOne(
            $sql,
            array(
                $userId,
                $permission
            )
        );

        return ((int) $count > 0);
    }
}
```

Example:

``` php
if (
    !$authorization->isAllowed(
        $user->id,
        'email.delete'
    )
) {
    // HTTP 403
}
```

------------------------------------------------------------------------

## 23. HTTP Status Codes

Use these semantics consistently:

``` text
400 Bad Request
    Invalid request structure/input

401 Unauthorized
    Missing JWT
    Invalid JWT
    Expired JWT
    Unknown/deactivated authenticated account
    Invalid refresh token

403 Forbidden
    Authentication succeeded,
    but the user lacks permission

405 Method Not Allowed
    Endpoint called with an unsupported HTTP method
```

------------------------------------------------------------------------

## 24. Bootstrap Services

A representative Bootstrap setup:

``` php
protected function _initUserLibrary()
{
    $autoloader =
        Zend_Loader_Autoloader::getInstance();

    $autoloader->registerNamespace('User_');

    return $autoloader;
}

protected function _initJwt()
{
    $options = $this->getOptions();

    if (!isset($options['jwt'])) {
        throw new RuntimeException(
            'JWT configuration missing.'
        );
    }

    $secret = getenv('S3_JWT_SECRET');

    if ($secret === false || $secret === '') {
        throw new RuntimeException(
            'S3_JWT_SECRET is not configured.'
        );
    }

    $config = $options['jwt'];
    $config['secret'] = $secret;

    $service = new User_JwtService($config);

    Zend_Registry::set(
        'jwtService',
        $service
    );

    return $service;
}

protected function _initRefreshToken()
{
    $this->bootstrap('db');

    $service =
        new User_RefreshTokenService();

    Zend_Registry::set(
        'refreshTokenService',
        $service
    );

    return $service;
}

protected function _initAuthorization()
{
    $this->bootstrap('db');

    $db = $this->getResource('db');

    $service =
        new User_AuthorizationService($db);

    Zend_Registry::set(
        'authorizationService',
        $service
    );

    return $service;
}

protected function _initApiAuthentication()
{
    $this->bootstrap('frontController');
    $this->bootstrap('jwt');

    $frontController =
        $this->getResource(
            'frontController'
        );

    $jwtService =
        Zend_Registry::get(
            'jwtService'
        );

    $frontController->registerPlugin(
        new User_ApiAuthPlugin(
            $jwtService
        )
    );
}
```

------------------------------------------------------------------------

## 25. Testing Strategy

Use three test levels.

``` text
Unit
  |
  +-- No MariaDB
  +-- JwtService
  +-- isolated business logic

Integration
  |
  +-- Dedicated MariaDB test DB
  +-- RefreshTokenService
  +-- SQL and transactions

Functional
  |
  +-- Complete ZF1 request lifecycle
  +-- Router
  +-- Plugins
  +-- Controllers
  +-- Authentication
  +-- Authorization
```

Do not run automated destructive tests against the production database.

------------------------------------------------------------------------

## 26. PHPUnit Configuration

`phpunit.xml`:

``` xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>

        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>

        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Testing environment:

``` ini
[testing : production]

phpSettings.display_errors = 1
phpSettings.display_startup_errors = 1

resources.db.adapter = "PDO_MYSQL"
resources.db.params.host = "127.0.0.1"
resources.db.params.username = "s3_test"
resources.db.params.password = "test-password"
resources.db.params.dbname = "s3_test"
resources.db.params.charset = "utf8mb4"

jwt.algorithm = "HS256"
jwt.issuer = "s3"
jwt.audience = "s3-api"
jwt.accessTokenLifetime = 900
```

Use a test-only JWT secret in the test bootstrap:

``` php
putenv(
    'S3_JWT_SECRET='
    . '0123456789abcdef0123456789abcdef'
);
```

Never reuse this example secret in production.

------------------------------------------------------------------------

## 27. JWT Unit Tests

Important JWT tests include:

``` text
Valid token accepted
Invalid signature rejected
Expired token rejected
Wrong issuer rejected
Wrong audience rejected
Protected claims cannot be overridden
```

Example:

``` php
public function testCreateAndVerifyToken()
{
    $token =
        $this->service
            ->createAccessToken(1001);

    $claims =
        $this->service
            ->verifyAccessToken($token);

    $this->assertSame(
        '1001',
        $claims['sub']
    );

    $this->assertSame(
        's3',
        $claims['iss']
    );

    $this->assertSame(
        's3-api',
        $claims['aud']
    );

    $this->assertArrayHasKey(
        'exp',
        $claims
    );

    $this->assertArrayHasKey(
        'jti',
        $claims
    );
}
```

Invalid signature:

``` php
public function testInvalidSignatureIsRejected()
{
    $otherService =
        new User_JwtService(
            array(
                'secret' =>
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'algorithm' => 'HS256',
                'issuer' => 's3',
                'audience' => 's3-api',
                'accessTokenLifetime' => 900
            )
        );

    $token =
        $otherService
            ->createAccessToken(1001);

    $this->expectException(
        User_JwtException::class
    );

    $this->service
        ->verifyAccessToken($token);
}
```

------------------------------------------------------------------------

## 28. Refresh-Token Integration Tests

Important database-backed tests:

``` text
Raw refresh token is never stored
Token A rotates to B
A cannot be used after rotation
Reuse of A revokes Family X
Logout revokes current family
Different login families are independent
Logout everywhere revokes all user sessions
```

Raw-token test:

``` php
public function testRawRefreshTokenIsNotStored()
{
    $result =
        $this->service->create(
            1001,
            '127.0.0.1',
            'PHPUnit'
        );

    $row =
        $this->db->fetchRow(
            'SELECT *
             FROM refresh_tokens
             WHERE id = ?',
            $result['id']
        );

    $this->assertNotSame(
        $result['token'],
        $row['token_hash']
    );

    $this->assertSame(
        hash(
            'sha256',
            $result['token']
        ),
        $row['token_hash']
    );
}
```

Family-reuse test:

``` php
public function testReuseRevokesEntireFamily()
{
    $first =
        $this->service->create(1001);

    $second =
        $this->service->rotate(
            $first['token']
        );

    $this->assertNotFalse($second);

    $reuse =
        $this->service->rotate(
            $first['token']
        );

    $this->assertFalse($reuse);

    $result =
        $this->service->rotate(
            $second['refresh_token']
        );

    $this->assertFalse($result);
}
```

------------------------------------------------------------------------

## 29. Functional API Tests

Functional tests should exercise the ZF1 request lifecycle.

Important scenarios:

``` text
POST /api/auth/login
    valid credentials -> 200 + tokens
    invalid password  -> 401
    missing fields    -> 400
    GET instead POST  -> 405

POST /api/auth/refresh
    valid token       -> 200 + rotated refresh token
    reused token      -> 401
    invalid token     -> 401

GET /api/email/getlist
    no JWT            -> 401
    malformed JWT     -> 401
    valid JWT         -> controller executes
    disabled user     -> 401

DELETE /api/email/delete
    valid JWT, no permission -> 403

POST /api/auth/logout
    valid access JWT + refresh token
        -> refresh family revoked
```

Example protected endpoint test:

``` php
public function testEmailListRequiresAuthentication()
{
    $this->dispatch(
        '/api/email/getlist'
    );

    $this->assertResponseCode(401);
}
```

Valid token:

``` php
public function testValidJwtAllowsApiRequest()
{
    $userId =
        $this->createTestUser(
            'john',
            'Test123!'
        );

    $token =
        $this->createAccessToken(
            $userId
        );

    $this->getRequest()
        ->setHeader(
            'Authorization',
            'Bearer ' . $token
        );

    $this->dispatch(
        '/api/email/getlist'
    );

    $this->assertResponseCode(200);
}
```

Disabled-user test:

``` php
public function testDisabledUserCannotUseValidJwt()
{
    $userId =
        $this->createTestUser(
            'john',
            'Test123!'
        );

    $token =
        $this->createAccessToken(
            $userId
        );

    $this->db->update(
        'users',
        array(
            'active' => 0
        ),
        array(
            'id = ?' => $userId
        )
    );

    $this->getRequest()
        ->setHeader(
            'Authorization',
            'Bearer ' . $token
        );

    $this->dispatch(
        '/api/email/getlist'
    );

    $this->assertResponseCode(401);
}
```

------------------------------------------------------------------------

## 30. Running PHPUnit

Unit tests:

``` powershell
vendor\bin\phpunit --testsuite Unit
```

Integration tests:

``` powershell
vendor\bin\phpunit --testsuite Integration
```

Functional tests:

``` powershell
vendor\bin\phpunit --testsuite Functional
```

Everything:

``` powershell
vendor\bin\phpunit
```

Run the fast unit tests frequently while developing. Run the complete
suite before releases and after authentication/security changes.

------------------------------------------------------------------------

## 31. Security Rules

The implementation should follow these rules:

1.  Use HTTPS for every authenticated API request.
2.  Never place passwords in URLs.
3.  Never place access or refresh tokens in URLs.
4.  Never log raw access JWTs or refresh tokens.
5.  Never store raw refresh tokens in MariaDB.
6.  Keep access JWT lifetime short.
7.  Rotate refresh tokens after successful use.
8.  Revoke compromised refresh-token families.
9.  Validate JWT signature, algorithm, issuer, audience, expiration, and
    subject.
10. Use the database for current account/permission state where
    necessary.
11. Keep the signing secret outside source control.
12. Use cryptographically random secrets.
13. Use a dedicated test database.
14. Return generic authentication errors rather than leaking internal
    verification details.
15. Keep authentication and authorization logically separate.
16. Re-check dependency security advisories when upgrading or deploying.

------------------------------------------------------------------------

## 32. Production Improvements

After the baseline system is working, useful improvements include:

### UTC timestamps

Use a consistent UTC policy across PHP and MariaDB rather than depending
on the server's local timezone.

### Cleanup task

Periodically remove old expired/revoked refresh-token records according
to an appropriate retention policy.

### Account-state checks on refresh

Before issuing another access JWT, ensure that the user still exists and
is active.

### Logging

Log security events without logging credentials:

``` text
Login success/failure
Refresh success/failure
Refresh-token reuse detected
Family revoked
Logout
Logout everywhere
Disabled-account API attempt
Authorization denied
```

Suitable fields include user ID, event type, timestamp, IP address where
appropriate, and request/correlation ID.

Do not log:

``` text
Password
Raw access JWT
Raw refresh token
Signing secret
```

### Concurrency testing

Test two requests attempting to rotate the same refresh token at
approximately the same time. Only one should successfully consume it.

### Clock abstraction

For highly deterministic unit tests, inject a clock/time provider into
token services rather than relying directly on `time()` and `sleep()`.

### Repository/service separation

For maintainability:

``` text
Controller
    |
    v
Application Service
    |
    +-- Authentication Service
    +-- Authorization Service
    +-- JWT Service
    +-- Refresh Token Service
    |
    v
Repository / DbTable
    |
    v
MariaDB
```

This makes the business layer easier to unit-test without a database.

------------------------------------------------------------------------

## 33. Final Request Flows

### Login

``` text
Client
  |
  | username + password
  v
POST /api/auth/login
  |
  v
Authentication
  |
  +-- invalid -> 401
  |
  v
User_JwtService
  |
  +-- Access JWT (15 min)
  |
  v
User_RefreshTokenService
  |
  +-- Refresh token (30 days)
  +-- DB stores hash only
  v
Client receives both
```

### Protected API request

``` text
Client
  |
  | Authorization: Bearer <JWT>
  v
/api/email/getlist
  |
  v
User_ApiAuthPlugin
  |
  +-- token missing/invalid -> 401
  |
  +-- verify JWT
  +-- load current user
  +-- check account
  |
  v
Authorization
  |
  +-- permission missing -> 403
  |
  v
Controller / Service
```

### Refresh

``` text
Access JWT expires
  |
  v
POST /api/auth/refresh
  |
  | Refresh A
  v
Validate A
  |
  +-- invalid/revoked/expired -> 401
  |
  v
Atomically revoke A
  |
  v
Create Refresh B
  |
  v
Create new Access JWT
  |
  v
Return JWT + B
```

### Reuse detection

``` text
A -> B

Later:
old A presented again
       |
       v
A is already "rotated"
       |
       v
Potential reuse detected
       |
       v
Revoke Family X
       |
       v
B can no longer refresh
```

### Logout

``` text
POST /api/auth/logout
Authorization: Bearer <JWT>
Refresh token in body
       |
       v
Authenticate access JWT
       |
       v
Find refresh-token family
       |
       v
Revoke family
       |
       v
No future refresh from that login session
```

------------------------------------------------------------------------

## 34. Deployment Checklist

Before enabling this in production, verify:

``` text
[ ] Composer autoloader loads correctly
[ ] User_ namespace autoloads correctly
[ ] firebase/php-jwt loads correctly
[ ] Current dependency security status reviewed
[ ] S3_JWT_SECRET is supplied externally
[ ] Production secret is cryptographically random
[ ] application.ini contains no production JWT secret
[ ] HTTPS is enforced
[ ] Real users table/model is wired into authentication
[ ] Real active/locked/deleted account rules are implemented
[ ] /api routing matches User_ApiAuthPlugin
[ ] Login and refresh are public
[ ] Unauthorized error route is public
[ ] Logout is protected
[ ] Protected APIs require Bearer JWT
[ ] Access tokens expire after intended lifetime
[ ] Raw refresh tokens are never stored
[ ] Refresh-token rotation works
[ ] Reuse detection works
[ ] Logout revokes one family
[ ] Logout-everywhere revokes all user sessions
[ ] Permission checks return 403
[ ] Authentication failures return 401
[ ] Dedicated MariaDB test database is configured
[ ] Unit tests pass
[ ] Integration tests pass
[ ] Functional API tests pass
[ ] Logs do not contain passwords or tokens
[ ] Expired/revoked refresh-token cleanup is planned
```

------------------------------------------------------------------------

## 35. Recommended Final Architecture

``` text
                    ZF1 Application
                         |
          +--------------+--------------+
          |                             |
          v                             v
  Browser / DHTMLX                  API Client
                                        |
                                        v
                              User_ApiAuthPlugin
                                        |
                                        v
                               User_JwtService
                                        |
                                 firebase/php-jwt
                                        |
                                        v
                              Authenticated User
                                        |
                                        v
                         User_AuthorizationService
                                        |
                              +---------+---------+
                              |                   |
                           allowed              denied
                              |                   |
                              v                   v
                      Controller/Service        403
                              |
                              v
                          Repository
                              |
                              v
                           MariaDB

Login / refresh session:
        |
        v
User_RefreshTokenService
        |
        +-- opaque random refresh token
        +-- SHA-256 token hash
        +-- rotation
        +-- token families
        +-- reuse detection
        +-- logout
        +-- logout everywhere
        |
        v
MariaDB refresh_tokens
```

This design allows the existing ZF1 application to add modern
Bearer-token API authentication without requiring every API controller
to implement JWT logic independently.
