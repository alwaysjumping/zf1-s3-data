# Detailed PHP Coding Style Guide

## For PHP 7.4, Zend Framework 1, and Gradual Modernization

This guide defines a detailed PHP coding standard for an existing **Zend
Framework 1 (ZF1) + PHP 7.4** project while preparing the codebase for
gradual modernization.

The recommended baseline is **PSR-12**, supplemented with practical
project rules for naming, PHPDoc, controllers, services,
models/repositories, database access, security, exceptions, logging,
PHPUnit, and legacy-code migration.

> **Core rule:** Apply modern standards consistently to new code, but
> modernize stable legacy ZF1 code gradually rather than reformatting
> the entire application at once.

------------------------------------------------------------------------

# 1. PHP Files

Use:

-   UTF-8 encoding, preferably without BOM.
-   4 spaces for indentation.
-   No tab characters for indentation.
-   One statement per line.
-   `<?php` for PHP code.
-   No closing `?>` in PHP-only files.

Good:

``` php
<?php

class EmailService
{
}
```

Avoid:

``` php
<?php

class EmailService
{
}

?>
```

Omitting the closing tag helps prevent accidental whitespace from being
sent to the browser before HTTP headers.

------------------------------------------------------------------------

# 2. Follow PSR-12 Formatting

Use spaces around operators and after control keywords.

Good:

``` php
if ($userId > 0) {
    $user = $this->getUser($userId);
}
```

Bad:

``` php
if($userId>0){
    $user=$this->getUser($userId);
}
```

Use one statement per line.

Good:

``` php
$userId = 100;
$userName = 'John';
$isActive = true;
```

Bad:

``` php
$userId = 100; $userName = 'John'; $isActive = true;
```

------------------------------------------------------------------------

# 3. Indentation

Use **4 spaces** for each indentation level.

``` php
if ($user->isActive()) {
    if ($user->hasPermission('email.send')) {
        $this->sendEmail($user);
    }
}
```

For multiline method parameters:

``` php
public function createEmail(
    $userId,
    $subject,
    $message,
    array $recipientIds
) {
    // ...
}
```

For multiline method calls:

``` php
$result = $service->createEmail(
    $userId,
    $subject,
    $message,
    $recipientIds
);
```

------------------------------------------------------------------------

# 4. Braces

Always use braces for control structures.

Good:

``` php
if ($user === null) {
    return false;
}
```

Avoid:

``` php
if ($user === null)
    return false;
```

Also avoid:

``` php
if ($user === null) return false;
```

Using braces makes future modifications safer.

------------------------------------------------------------------------

# 5. Class Names

For modern namespaced code, use **PascalCase**:

``` php
class EmailService
{
}

class UserRepository
{
}

class JwtAuthenticationService
{
}
```

For existing ZF1 classes, preserve legacy class naming where necessary:

``` php
class My_Service_Email
{
}

class Application_Model_User
{
}

class Application_Model_DbTable_Email
{
}

class Api_EmailController extends Zend_Controller_Action
{
}
```

Do not rename every legacy class merely to satisfy modern naming
conventions. Large-scale renaming can introduce unnecessary risk.

------------------------------------------------------------------------

# 6. Method Names

Use **camelCase**.

Good:

``` php
public function getEmailList()
{
}

public function createEmail()
{
}

public function validateAccessToken()
{
}

public function findUserById()
{
}
```

Avoid unclear abbreviations:

``` php
public function getUsr()
{
}
```

Prefer:

``` php
public function getUser()
{
}
```

Method names should describe what the method actually does.

------------------------------------------------------------------------

# 7. Variable Names

Use descriptive `camelCase` names.

Good:

``` php
$userId = 100;
$emailAddress = 'user@example.com';
$accessToken = '...';
$documentId = 500;
$recipientIds = [];
```

Avoid:

``` php
$u = 100;
$e = 'user@example.com';
$x = '...';
$d = 500;
```

Short names are acceptable when their meaning is obvious:

``` php
for ($i = 0; $i < $count; $i++) {
    // ...
}
```

------------------------------------------------------------------------

# 8. Boolean Variable Names

Boolean variables should make their meaning obvious.

Good:

``` php
$isActive = true;
$isAdmin = false;
$hasPermission = true;
$canDelete = false;
$shouldNotify = true;
```

Then conditions read naturally:

``` php
if ($isActive && $hasPermission) {
    // ...
}
```

Avoid ambiguous names:

``` php
$status = true;
$flag = false;
```

when a clearer boolean name is available.

------------------------------------------------------------------------

# 9. Constants

Use uppercase snake case.

``` php
const ACCESS_TOKEN_LIFETIME = 900;
const MAX_LOGIN_ATTEMPTS = 5;
const DEFAULT_PAGE_SIZE = 20;
```

Modern class example:

``` php
class JwtService
{
    private const DEFAULT_LIFETIME = 900;
}
```

Constants should replace important magic values.

------------------------------------------------------------------------

# 10. Namespaces

For new modern code, use namespaces:

``` php
<?php

namespace App\Service;

class EmailService
{
}
```

Place imports near the top:

``` php
<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

class JwtService
{
}
```

Prefer:

``` php
JWT::encode($payload, $key, 'HS256');
```

instead of repeatedly writing:

``` php
\Firebase\JWT\JWT::encode(
    $payload,
    $key,
    'HS256'
);
```

when an import makes the code easier to read.

For legacy ZF1 classes, namespaces do not need to be introduced
everywhere at once.

------------------------------------------------------------------------

# 11. One Main Class Per File

Prefer:

``` text
src/
└── Service/
    ├── EmailService.php
    ├── UserService.php
    ├── DocumentService.php
    └── NotificationService.php
```

For legacy ZF1:

``` text
library/
└── My/
    ├── Service/
    │   ├── Email.php
    │   ├── User.php
    │   └── Notification.php
    └── Controller/
        └── Plugin/
            └── JwtAuth.php
```

Avoid placing many unrelated classes into one file such as:

``` text
AllServices.php
```

------------------------------------------------------------------------

# 12. Arrays

For new PHP 7.4 code, prefer short array syntax:

``` php
$user = [
    'id' => 100,
    'name' => 'John',
    'active' => true,
];
```

Instead of:

``` php
$user = array(
    'id' => 100,
    'name' => 'John',
    'active' => true,
);
```

Do not mechanically convert every existing `array()` in untouched legacy
code.

For multiline arrays, use one element per line and a trailing comma:

``` php
$config = [
    'issuer' => 'example.com',
    'audience' => 'example.com',
    'lifetime' => 900,
];
```

Nested arrays should remain readable:

``` php
$config = [
    'jwt' => [
        'issuer' => 'example.com',
        'audience' => 'example.com',
        'lifetime' => 900,
    ],
    'notification' => [
        'enabled' => true,
    ],
];
```

------------------------------------------------------------------------

# 13. Strings

Use single quotes when interpolation is not required:

``` php
$message = 'User not found.';
```

Use double quotes when interpolation genuinely improves readability:

``` php
$message = "Welcome, {$userName}.";
```

Do not use double quotes everywhere without reason.

------------------------------------------------------------------------

# 14. String Concatenation

Use spaces around `.`.

Good:

``` php
$message = 'Hello ' . $userName;
```

Avoid:

``` php
$message = 'Hello '.$userName;
```

For complicated strings, `sprintf()` can improve readability:

``` php
$message = sprintf(
    'User %s with ID %d logged in from %s at %s',
    $userName,
    $userId,
    $ipAddress,
    $date
);
```

------------------------------------------------------------------------

# 15. Strict Comparisons

Prefer strict comparisons.

Good:

``` php
if ($status === 'active') {
    // ...
}
```

Avoid:

``` php
if ($status == 'active') {
    // ...
}
```

Prefer:

``` php
if ($result !== false) {
    // ...
}
```

Be careful with:

``` text
0
"0"
false
null
""
[]
```

because PHP loose comparisons may treat different values as equivalent.

------------------------------------------------------------------------

# 16. Explicit Null Checks

When `null` has a specific meaning, check it explicitly:

``` php
if ($user === null) {
    throw new UserNotFoundException();
}
```

Avoid:

``` php
if (!$user) {
    // ...
}
```

when `false`, `0`, `''`, and `null` mean different things.

------------------------------------------------------------------------

# 17. Early Returns

Prefer early returns over deeply nested conditions.

Harder to read:

``` php
public function sendEmail($user)
{
    if ($user !== null) {
        if ($user->isActive()) {
            if ($user->hasEmail()) {
                if ($user->canReceiveEmail()) {
                    return $this->send($user);
                }
            }
        }
    }

    return false;
}
```

Better:

``` php
public function sendEmail($user)
{
    if ($user === null) {
        return false;
    }

    if (!$user->isActive()) {
        return false;
    }

    if (!$user->hasEmail()) {
        return false;
    }

    if (!$user->canReceiveEmail()) {
        return false;
    }

    return $this->send($user);
}
```

The successful path is now easier to understand.

------------------------------------------------------------------------

# 18. Keep Methods Focused

A method should have one clear responsibility.

Avoid:

``` php
public function saveAction()
{
    // Validate HTTP request.
    // Authenticate user.
    // Check permissions.
    // Validate email.
    // Query database.
    // Insert email.
    // Insert recipients.
    // Send notifications.
    // Write audit log.
    // Send SMTP email.
    // Generate JSON.
}
```

Prefer separation:

``` text
Controller
    ↓
EmailService
    ├── EmailRepository
    ├── PermissionService
    ├── NotificationService
    └── AuditService
```

A controller can then be small:

``` php
public function saveAction()
{
    $data = $this->getRequest()->getPost();

    $result = $this->emailService->createEmail(
        $this->authenticatedUserId,
        $data
    );

    $this->_helper->json($result);
}
```

------------------------------------------------------------------------

# 19. Controller Style

Controllers should primarily handle HTTP concerns.

Recommended flow:

``` text
HTTP Request
    ↓
Read request data
    ↓
Perform basic request validation
    ↓
Call Service
    ↓
Convert result to HTTP / JSON response
```

Example:

``` php
class Api_EmailController extends Zend_Controller_Action
{
    public function getlistAction()
    {
        $userId = $this->_getParam(
            'authenticatedUserId'
        );

        $emails = $this->emailService->getEmailList(
            $userId
        );

        $this->_helper->json([
            'success' => true,
            'data' => $emails,
        ]);
    }
}
```

Do not put large SQL queries or complicated business rules directly in
controllers.

------------------------------------------------------------------------

# 20. Service Style

Services should represent application/business operations.

``` php
class My_Service_Email
{
    private $emailTable;
    private $permissionService;

    public function __construct(
        Application_Model_DbTable_Email $emailTable,
        My_Service_Permission $permissionService
    ) {
        $this->emailTable = $emailTable;
        $this->permissionService = $permissionService;
    }

    public function deleteEmail($userId, $emailId)
    {
        $email = $this->emailTable->findById($emailId);

        if ($email === null) {
            throw new My_Exception_EmailNotFound();
        }

        if (!$this->permissionService->canDeleteEmail(
            $userId,
            $email
        )) {
            throw new My_Exception_AccessDenied();
        }

        return $this->emailTable->deleteById($emailId);
    }
}
```

The service layer should answer questions such as:

-   Can this user delete this email?
-   What should happen when an email is created?
-   Should a notification be generated?
-   Should an audit record be created?
-   Which business validations must pass?

------------------------------------------------------------------------

# 21. Model / Repository Style

Keep persistence operations in the database/model/repository layer.

``` php
class Application_Model_DbTable_Email
    extends Zend_Db_Table_Abstract
{
    protected $_name = 'email';

    public function getByUserId($userId)
    {
        $select = $this->select()
            ->where('user_id = ?', $userId)
            ->order('created_at DESC');

        return $this->fetchAll($select);
    }
}
```

Avoid:

``` php
public function getlistAction()
{
    $db = Zend_Db_Table::getDefaultAdapter();

    // Large SQL query here...
}
```

Prefer:

``` text
Controller
    ↓
Service
    ↓
Model / Repository
    ↓
MariaDB
```

------------------------------------------------------------------------

# 22. Dependency Injection

Avoid creating every dependency inside a service.

Harder to test:

``` php
class My_Service_Email
{
    public function send()
    {
        $table = new Application_Model_DbTable_Email();
        $logger = new My_Logger();
        $notification = new My_Service_Notification();

        // ...
    }
}
```

Prefer injecting important dependencies:

``` php
class My_Service_Email
{
    private $emailTable;
    private $notificationService;

    public function __construct(
        Application_Model_DbTable_Email $emailTable,
        My_Service_Notification $notificationService
    ) {
        $this->emailTable = $emailTable;
        $this->notificationService = $notificationService;
    }
}
```

Benefits:

``` text
Easier unit testing
Easier dependency replacement
Less hidden coupling
Easier migration to another framework
```

------------------------------------------------------------------------

# 23. PHPDoc

Use PHPDoc where it adds information.

``` php
/**
 * Returns emails visible to the specified user.
 *
 * Archived emails are included, but permanently deleted
 * records are excluded.
 *
 * @param int $userId Authenticated user ID.
 *
 * @return Application_Model_Email[]
 *
 * @throws InvalidArgumentException
 *     If the user ID is invalid.
 */
public function getEmailList($userId)
{
    // ...
}
```

PHPDoc is especially useful for legacy untyped PHP code.

------------------------------------------------------------------------

# 24. Property PHPDoc

``` php
/**
 * Email database table.
 *
 * @var Application_Model_DbTable_Email
 */
private $emailTable;
```

Another example:

``` php
/**
 * JWT access-token lifetime in seconds.
 *
 * @var int
 */
private $tokenLifetime = 900;
```

This improves IDE autocomplete and type inference.

------------------------------------------------------------------------

# 25. Complex Array PHPDoc

Instead of:

``` php
/**
 * @param array $data
 */
```

describe the expected structure:

``` php
/**
 * Creates an email.
 *
 * @param int $userId
 * @param array{
 *     subject: string,
 *     message: string,
 *     recipientIds: int[]
 * } $data
 *
 * @return int Newly created email ID.
 */
public function createEmail(
    $userId,
    array $data
) {
    // ...
}
```

For an array of objects:

``` php
/**
 * @return Application_Model_User[]
 */
public function getUsers()
{
    // ...
}
```

For nullable results:

``` php
/**
 * @return Application_Model_User|null
 */
public function findUser($userId)
{
    // ...
}
```

------------------------------------------------------------------------

# 26. Do Not Overuse PHPDoc

Avoid PHPDoc that only repeats obvious code.

Less useful:

``` php
/**
 * Gets the name.
 *
 * @return string The name.
 */
public function getName(): string
{
    return $this->name;
}
```

Better:

``` php
/**
 * Returns the employee's current display name.
 *
 * Historical audit records retain the name that was active
 * when the audit event was created.
 */
public function getName(): string
{
    return $this->name;
}
```

PHPDoc should explain information that the signature cannot communicate
clearly.

------------------------------------------------------------------------

# 27. Native PHP 7.4 Types

For new code, use native types where safe:

``` php
public function findUser(int $userId): ?User
{
    // ...
}
```

PHP 7.4 supports typed properties:

``` php
private string $secret;
private int $tokenLifetime;
```

However, do not mechanically add types to legacy ZF1 methods without
checking existing callers.

A safer first step:

``` php
/**
 * @param int $userId
 * @return Application_Model_User|null
 */
public function getUser($userId)
{
    // ...
}
```

After tests confirm existing behavior, native types can be introduced
gradually.

------------------------------------------------------------------------

# 28. Exceptions

Use meaningful exception classes.

Avoid:

``` php
throw new Exception('Error');
```

Prefer:

``` php
throw new EmailNotFoundException(
    'The requested email was not found.'
);
```

Or:

``` php
throw new AccessDeniedException(
    'The user cannot delete this email.'
);
```

Then callers can handle failures precisely:

``` php
try {
    $service->deleteEmail(
        $userId,
        $emailId
    );
} catch (EmailNotFoundException $e) {
    // Return not-found response.
} catch (AccessDeniedException $e) {
    // Return forbidden response.
}
```

Document meaningful exceptions:

``` php
/**
 * @throws EmailNotFoundException
 *     If the requested email does not exist.
 *
 * @throws AccessDeniedException
 *     If the user cannot delete the email.
 */
```

------------------------------------------------------------------------

# 29. Never Silently Swallow Exceptions

Avoid:

``` php
try {
    $service->sendEmail($data);
} catch (Exception $e) {
}
```

The application now has no indication that something failed.

Instead:

``` php
try {
    $service->sendEmail($data);
} catch (EmailSendException $e) {
    $this->logger->error(
        'Unable to send email.',
        [
            'exception' => $e,
        ]
    );

    throw $e;
}
```

An exception should be handled, translated, logged where appropriate, or
allowed to propagate.

------------------------------------------------------------------------

# 30. Database Security

Never concatenate untrusted input into SQL.

Bad:

``` php
$sql = 'SELECT * FROM users WHERE id = '
    . $_GET['id'];
```

Use parameterized queries:

``` php
$select = $this->select()
    ->where('id = ?', $userId);
```

Or:

``` php
$sql = '
    SELECT *
    FROM users
    WHERE id = ?
';

$user = $db->fetchRow(
    $sql,
    [$userId]
);
```

Parameterization should be the normal defense against SQL injection.

------------------------------------------------------------------------

# 31. Input Validation

Treat all external input as untrusted:

``` text
$_GET
$_POST
$_COOKIE
HTTP headers
JSON request bodies
Uploaded files
JWT claims
URL parameters
```

Example:

``` php
$userId = filter_var(
    $this->_getParam('userId'),
    FILTER_VALIDATE_INT
);

if ($userId === false || $userId <= 0) {
    throw new InvalidArgumentException(
        'Invalid user ID.'
    );
}
```

Validation should reflect the business meaning of the value.

For example, an integer ID should normally also be checked for a valid
range.

------------------------------------------------------------------------

# 32. Output Escaping

Use the correct escaping mechanism for the output context.

For HTML:

``` php
echo htmlspecialchars(
    $userName,
    ENT_QUOTES,
    'UTF-8'
);
```

Do not confuse:

``` text
SQL parameterization
HTML escaping
JSON encoding
URL encoding
```

They protect different contexts.

------------------------------------------------------------------------

# 33. Password Handling

Never store plaintext passwords.

Use:

``` php
$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);
```

Verify:

``` php
if (password_verify(
    $password,
    $passwordHash
)) {
    // Password is correct.
}
```

Do not design a custom password hashing algorithm.

------------------------------------------------------------------------

# 34. Secrets

Avoid hard-coded secrets:

``` php
$jwtSecret = 'secret123';
```

Prefer protected configuration outside source control.

Conceptually:

``` text
Source Code
     │
     └── Reads configuration
                │
                ▼
         Protected configuration
         / environment
```

Do not commit production:

``` text
Database passwords
JWT signing secrets
Private keys
API credentials
Certificate passwords
```

to Git.

------------------------------------------------------------------------

# 35. JWT Code Separation

Keep third-party JWT implementation details isolated.

Recommended architecture:

``` text
Controller Plugin
       ↓
My_Jwt / JwtService
       ↓
firebase/php-jwt
```

Example:

``` php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class My_Jwt
{
    public function createToken($payload)
    {
        return JWT::encode(
            $payload,
            $this->secret,
            'HS256'
        );
    }

    public function validateToken($token)
    {
        return JWT::decode(
            $token,
            new Key(
                $this->secret,
                'HS256'
            )
        );
    }
}
```

Controllers should not need to know the details of the third-party JWT
library.

------------------------------------------------------------------------

# 36. Comments

Comments should explain **why**, not merely repeat **what**.

Bad:

``` php
// Add one.
$count++;
```

Useful:

``` php
// Start version numbers at 1 because version 0 is reserved
// for imported legacy documents.
$version++;
```

Avoid commented-out dead code:

``` php
// $oldResult = oldFunction();
// oldFunction2();
// oldFunction3();
```

Use Git history instead.

------------------------------------------------------------------------

# 37. TODO and FIXME Comments

Make TODO comments specific.

Good:

``` php
// TODO: Replace the legacy SHA-1 import check after all
// existing module packages have been migrated.
```

Bad:

``` php
// TODO: Fix this.
```

A useful TODO should explain what remains and preferably why.

Use `FIXME` only when there is a known defect that genuinely requires
attention:

``` php
// FIXME: This fallback can return duplicate rows when two
// imports run concurrently.
```

------------------------------------------------------------------------

# 38. Avoid Surprising Side Effects

A method named:

``` php
getUser()
```

should normally retrieve a user.

It should not unexpectedly:

``` text
Retrieve a user
Delete expired accounts
Send email
Write audit records
```

If an operation has meaningful side effects, choose a name that
communicates the operation.

------------------------------------------------------------------------

# 39. Avoid Magic Numbers

Bad:

``` php
if ($loginAttempts >= 5) {
    // ...
}
```

Better:

``` php
const MAX_LOGIN_ATTEMPTS = 5;

if ($loginAttempts >= self::MAX_LOGIN_ATTEMPTS) {
    // ...
}
```

Similarly:

``` php
const ACCESS_TOKEN_LIFETIME = 900;
```

is clearer than repeatedly writing:

``` php
$expiresAt = time() + 900;
```

------------------------------------------------------------------------

# 40. Avoid Magic Strings

Instead of repeatedly using:

``` php
if ($status === 'approved') {
    // ...
}
```

consider constants:

``` php
class DocumentStatus
{
    const PENDING = 'pending';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
}
```

Usage:

``` php
if (
    $document->getStatus()
    === DocumentStatus::APPROVED
) {
    // ...
}
```

------------------------------------------------------------------------

# 41. PHPUnit Coding Style

Test names should describe behavior.

Good:

``` php
public function testValidTokenReturnsUserId()
{
}

public function testExpiredTokenIsRejected()
{
}

public function testInactiveUserCannotDeleteEmail()
{
}
```

Avoid:

``` php
public function test1()
{
}
```

Use Arrange → Act → Assert when it improves readability:

``` php
public function testValidSubjectIsAccepted()
{
    // Arrange
    $service = new My_Service_Email();

    // Act
    $result = $service->validateSubject(
        'Meeting tomorrow'
    );

    // Assert
    $this->assertTrue($result);
}
```

------------------------------------------------------------------------

# 42. Test One Behavior at a Time

Avoid one giant test that verifies many unrelated behaviors.

Prefer:

``` text
testValidSubjectIsAccepted()

testEmptySubjectIsRejected()

testLongSubjectIsRejected()

testUnauthorizedUserCannotDeleteEmail()

testExpiredJwtIsRejected()
```

When one test fails, the failure should clearly indicate what behavior
changed.

------------------------------------------------------------------------

# 43. Organize Tests

A useful project structure:

``` text
tests/
├── Unit/
│   ├── Service/
│   └── Model/
│
├── Integration/
│   ├── Database/
│   └── Api/
│
├── fixtures/
└── bootstrap.php
```

Unit tests should be fast and isolated where possible.

Integration tests may intentionally bootstrap ZF1 or use a dedicated
test database.

Never run automated tests against the production database.

------------------------------------------------------------------------

# 44. Logging

Use structured, meaningful logs.

Avoid:

``` php
$this->logger->error('Error');
```

Prefer:

``` php
$this->logger->error(
    'Email delivery failed.',
    [
        'emailId' => $emailId,
        'userId' => $userId,
        'exception' => $exception,
    ]
);
```

Never log:

``` text
Passwords
JWT signing secrets
Private keys
Certificate passwords
Raw refresh tokens
Sensitive authentication credentials
```

------------------------------------------------------------------------

# 45. User-Facing Errors vs Internal Logs

Developer logs and user-facing messages have different purposes.

Internal log:

``` text
Unable to load email 4921: database connection timeout.
```

Client response:

``` json
{
    "success": false,
    "error": "Unable to process the request."
}
```

Do not expose:

``` text
Stack traces
Database credentials
Raw SQL internals
Filesystem paths
Private configuration
Secrets
```

to clients.

------------------------------------------------------------------------

# 46. Predictable Return Values

Keep method return types predictable.

Avoid a method that sometimes returns:

``` text
array
false
"error"
null
```

Prefer a clear contract:

``` php
/**
 * @return Application_Model_User|null
 */
public function findUser($userId)
{
    // ...
}
```

Or throw a documented exception when failure is exceptional.

------------------------------------------------------------------------

# 47. Avoid Global State Where Practical

Legacy ZF1 applications often use:

``` php
Zend_Registry::get('db');
```

or static/global state.

Do not necessarily rewrite all existing code immediately, but new
services should prefer explicit dependencies:

``` php
class EmailService
{
    private $emailRepository;

    public function __construct(
        EmailRepository $emailRepository
    ) {
        $this->emailRepository =
            $emailRepository;
    }
}
```

This is easier to test and migrate.

------------------------------------------------------------------------

# 48. Keep Framework-Specific Code Near the Edges

A useful architecture is:

``` text
HTTP / ZF1
    ↓
Controller
    ↓
Service
    ↓
Business Rules
    ↓
Repository / Model
    ↓
ZF1 DB / MariaDB
```

Try not to make every business service depend directly on:

``` text
Zend_Controller
Zend_View
Zend_Request
Zend_Response
```

The less framework-dependent your business logic is, the easier a future
ZF1 → Laminas migration becomes.

------------------------------------------------------------------------

# 49. Controller Plugins

Use controller plugins for cross-cutting request behavior, not ordinary
business logic.

Examples:

``` text
JWT authentication
Request logging
Global authorization checks
Request initialization
```

Example:

``` php
class My_Controller_Plugin_JwtAuth
    extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(
        Zend_Controller_Request_Abstract $request
    ) {
        if (
            strtolower($request->getModuleName())
            !== 'api'
        ) {
            return;
        }

        // Validate JWT before the controller action.
    }
}
```

Do not put email, document, accounting, or other business operations in
controller plugins.

------------------------------------------------------------------------

# 50. Authentication vs Authorization

Keep these responsibilities separate.

``` text
Authentication
    "Who are you?"
        ↓
User ID = 100

Authorization
    "Can user 100 perform this operation?"
        ↓
YES / NO
```

A JWT plugin may authenticate the user, while a service or permission
component determines whether the authenticated user may perform a
specific business operation.

------------------------------------------------------------------------

# 51. Poorly Structured Example

``` php
public function deleteAction()
{
    $id = $_POST['id'];

    $db = Zend_Db_Table::getDefaultAdapter();

    $row = $db->fetchRow(
        'SELECT * FROM email WHERE id=' . $id
    );

    if ($row) {
        if ($row['user_id'] == $_SESSION['user_id']) {
            $db->query(
                'DELETE FROM email WHERE id=' . $id
            );

            echo json_encode([
                'success' => true,
            ]);
        }
    }

    exit;
}
```

Problems:

``` text
Controller contains business logic
Direct superglobal access
SQL injection risk
Loose comparison
Authorization mixed with persistence
Manual JSON output
No meaningful exceptions
Hard to unit test
```

------------------------------------------------------------------------

# 52. Better Structured Version

Controller:

``` php
public function deleteAction()
{
    $emailId = filter_var(
        $this->_getParam('id'),
        FILTER_VALIDATE_INT
    );

    if ($emailId === false || $emailId <= 0) {
        throw new InvalidArgumentException(
            'Invalid email ID.'
        );
    }

    $userId = $this->_getParam(
        'authenticatedUserId'
    );

    $this->emailService->deleteEmail(
        $userId,
        $emailId
    );

    $this->_helper->json([
        'success' => true,
    ]);
}
```

Service:

``` php
public function deleteEmail(
    $userId,
    $emailId
) {
    $email = $this->emailRepository
        ->findById($emailId);

    if ($email === null) {
        throw new EmailNotFoundException();
    }

    if ((int) $email->user_id !== (int) $userId) {
        throw new AccessDeniedException();
    }

    $this->emailRepository->deleteById(
        $emailId
    );
}
```

Repository / model:

``` php
public function findById($emailId)
{
    $row = $this->find($emailId)->current();

    return $row ?: null;
}

public function deleteById($emailId)
{
    return $this->delete([
        'id = ?' => $emailId,
    ]);
}
```

Architecture:

``` text
Controller
    │
    │ HTTP handling
    ▼
Service
    │
    │ Business rules
    ▼
Repository / DbTable
    │
    │ Database operations
    ▼
MariaDB
```

This structure is easier to test, understand, and migrate.

------------------------------------------------------------------------

# 53. Style for Existing Legacy Code

Do not run a formatter over the entire legacy application immediately.

A change containing:

``` text
10 real functional changes
+
10,000 formatting changes
=
Very difficult code review
```

creates unnecessary risk.

Use a gradual rule:

``` text
Untouched legacy ZF1 code
        │
        └── Leave mostly unchanged

Modified legacy code
        │
        ├── Improve style locally
        ├── Add tests
        ├── Add useful PHPDoc
        ├── Remove obvious duplication
        └── Extract business logic when appropriate

New code
        │
        ├── PSR-12
        ├── Namespaces
        ├── Composer
        ├── Native types where safe
        ├── Dependency injection
        └── PHPUnit tests
```

------------------------------------------------------------------------

# 54. Recommended Modernization Direction

A strong long-term coding standard looks like:

``` text
PHP Source
    │
    ├── PSR-12 formatting
    ├── Clear naming
    ├── Namespaces for new code
    ├── Composer dependencies
    ├── Useful PHPDoc
    ├── Native types where appropriate
    ├── Strict input validation
    ├── Parameterized database queries
    ├── Meaningful exceptions
    ├── Structured logging
    └── PHPUnit tests

Architecture
    │
    ├── Controller
    │      HTTP concerns
    │
    ├── Service
    │      Business logic
    │
    ├── Repository / Model
    │      Persistence
    │
    └── Plugin
           Cross-cutting request concerns
```

------------------------------------------------------------------------

# 55. Suggested ZF1 Project Structure

For gradual modernization:

``` text
project/
├── application/
│   ├── controllers/
│   ├── models/
│   ├── modules/
│   └── configs/
│
├── library/
│   ├── Zend/
│   └── My/
│       ├── Service/
│       ├── Repository/
│       ├── Exception/
│       ├── Controller/
│       │   └── Plugin/
│       └── Jwt.php
│
├── tests/
│   ├── Unit/
│   │   ├── Service/
│   │   └── Model/
│   ├── Integration/
│   │   ├── Database/
│   │   └── Api/
│   └── bootstrap.php
│
├── vendor/
├── composer.json
└── phpunit.xml
```

This allows old ZF1 code and gradually modernized code to coexist.

------------------------------------------------------------------------

# 56. Example Complete Service

``` php
<?php

/**
 * Provides business operations for email management.
 *
 * This service contains email-related business rules.
 * HTTP-specific behavior belongs in the controller.
 */
class My_Service_Email
{
    /**
     * Email persistence component.
     *
     * @var Application_Model_DbTable_Email
     */
    private $emailTable;

    /**
     * Permission service.
     *
     * @var My_Service_Permission
     */
    private $permissionService;

    /**
     * @param Application_Model_DbTable_Email $emailTable
     * @param My_Service_Permission $permissionService
     */
    public function __construct(
        Application_Model_DbTable_Email $emailTable,
        My_Service_Permission $permissionService
    ) {
        $this->emailTable = $emailTable;
        $this->permissionService = $permissionService;
    }

    /**
     * Deletes an email after verifying ownership and permission.
     *
     * @param int $userId Authenticated user ID.
     * @param int $emailId Email ID.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *     If either ID is invalid.
     *
     * @throws My_Exception_EmailNotFound
     *     If the email does not exist.
     *
     * @throws My_Exception_AccessDenied
     *     If the user cannot delete the email.
     */
    public function deleteEmail($userId, $emailId)
    {
        $userId = (int) $userId;
        $emailId = (int) $emailId;

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid user ID.'
            );
        }

        if ($emailId <= 0) {
            throw new InvalidArgumentException(
                'Invalid email ID.'
            );
        }

        $email = $this->emailTable->findById(
            $emailId
        );

        if ($email === null) {
            throw new My_Exception_EmailNotFound();
        }

        if (!$this->permissionService->canDeleteEmail(
            $userId,
            $email
        )) {
            throw new My_Exception_AccessDenied();
        }

        $this->emailTable->deleteById($emailId);
    }
}
```

This example combines:

``` text
Clear naming
Focused responsibility
Dependency injection
Useful PHPDoc
Input checking
Meaningful exceptions
Service-layer business logic
```

------------------------------------------------------------------------

# 57. Example Complete DbTable

``` php
<?php

/**
 * Database table gateway for email records.
 */
class Application_Model_DbTable_Email
    extends Zend_Db_Table_Abstract
{
    /**
     * Database table name.
     *
     * @var string
     */
    protected $_name = 'email';

    /**
     * Primary key.
     *
     * @var string
     */
    protected $_primary = 'id';

    /**
     * Finds an email by ID.
     *
     * @param int $emailId
     *
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findById($emailId)
    {
        $row = $this->find((int) $emailId)->current();

        return $row ?: null;
    }

    /**
     * Returns emails belonging to a user.
     *
     * @param int $userId
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function getByUserId($userId)
    {
        $select = $this->select()
            ->where('user_id = ?', (int) $userId)
            ->order('created_at DESC');

        return $this->fetchAll($select);
    }

    /**
     * Deletes an email by ID.
     *
     * @param int $emailId
     *
     * @return int Number of deleted rows.
     */
    public function deleteById($emailId)
    {
        return $this->delete([
            'id = ?' => (int) $emailId,
        ]);
    }
}
```

------------------------------------------------------------------------

# 58. Example PHPUnit Test Style

``` php
<?php

use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    public function testInvalidEmailIdIsRejected()
    {
        $emailTable = $this->createMock(
            Application_Model_DbTable_Email::class
        );

        $permissionService = $this->createMock(
            My_Service_Permission::class
        );

        $service = new My_Service_Email(
            $emailTable,
            $permissionService
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->deleteEmail(100, 0);
    }

    public function testMissingEmailIsRejected()
    {
        $emailTable = $this->createMock(
            Application_Model_DbTable_Email::class
        );

        $emailTable
            ->method('findById')
            ->with(500)
            ->willReturn(null);

        $permissionService = $this->createMock(
            My_Service_Permission::class
        );

        $service = new My_Service_Email(
            $emailTable,
            $permissionService
        );

        $this->expectException(
            My_Exception_EmailNotFound::class
        );

        $service->deleteEmail(100, 500);
    }
}
```

The test names describe behavior and the service can be tested without a
real database because its dependencies are injected.

------------------------------------------------------------------------

# 59. Pre-Commit Review Checklist

Before committing PHP code, check:

-   Is formatting consistent with PSR-12?
-   Are indentation and braces consistent?
-   Are class, method, and variable names clear?
-   Are boolean names meaningful?
-   Are magic values replaced when appropriate?
-   Are strict comparisons used where appropriate?
-   Is external input validated?
-   Are SQL queries parameterized?
-   Is output escaped for its context?
-   Are passwords handled with PHP's password API?
-   Are secrets kept outside source control?
-   Is business logic outside controllers where practical?
-   Are persistence operations kept in models/repositories?
-   Are methods focused on one responsibility?
-   Are dependencies explicit where practical?
-   Are exceptions meaningful?
-   Are exceptions not silently swallowed?
-   Does PHPDoc add useful information?
-   Are complex array structures documented?
-   Are important behavior changes covered by tests?
-   Are logs useful without exposing sensitive information?
-   Did the change avoid unnecessary formatting of unrelated legacy
    code?

------------------------------------------------------------------------

# 60. Core Principle

The goal is not to make every old ZF1 file look modern immediately.

The goal is to make the application progressively:

``` text
Consistent
    +
Readable
    +
Secure
    +
Testable
    +
Maintainable
    +
Easy to migrate
```

For a mature ZF1 application, the safest approach is **gradual
modernization**:

``` text
Existing stable code
        ↓
Protect important behavior with tests
        ↓
Improve code when it is touched
        ↓
Extract business logic into services
        ↓
Add useful PHPDoc
        ↓
Introduce native types carefully
        ↓
Use Composer and namespaces for new code
        ↓
Move toward a modern Laminas architecture
```

Consistency, clarity, security, and testability are more important than
mechanically changing every legacy file to look modern.
