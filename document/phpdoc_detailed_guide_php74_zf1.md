# PHPDoc Detailed Guide for PHP 7.4 and Zend Framework 1

## 1. What Is PHPDoc?

PHPDoc is a **documentation standard for PHP source code**. It uses
specially formatted comment blocks called **DocBlocks** to describe
classes, methods, properties, parameters, return values, exceptions,
array structures, deprecated APIs, and other information useful to
developers and development tools.

PHPDoc is especially useful in legacy PHP applications because older
methods often do not use native parameter and return type declarations.

A normal comment:

``` php
// Get the user.
```

A PHPDoc DocBlock:

``` php
/**
 * Returns a user by ID.
 *
 * @param int $userId User ID.
 * @return Application_Model_User|null User or null when not found.
 */
public function getUser($userId)
{
    // ...
}
```

A PHPDoc block starts with:

``` text
/**
```

rather than:

``` text
/*
```

Tools such as IDEs, documentation generators, and static-analysis tools
can interpret PHPDoc information.

------------------------------------------------------------------------

## 2. Why PHPDoc Is Useful

Consider an old-style method:

``` php
public function getUser($id)
{
    // ...
}
```

Without documentation, another developer may not know:

-   What type `$id` should be.
-   What the method returns.
-   Whether it can return `null`.
-   Which exceptions it may throw.
-   What business rules it applies.

PHPDoc can clarify these details:

``` php
/**
 * Returns a user by ID.
 *
 * @param int $id User ID.
 *
 * @return Application_Model_User|null
 *
 * @throws InvalidArgumentException
 *     If the user ID is invalid.
 */
public function getUser($id)
{
    // ...
}
```

PHPDoc is useful for:

-   Developer documentation
-   IDE autocomplete
-   IDE navigation
-   Type inference
-   Static analysis
-   API documentation generation
-   Understanding legacy code
-   Supporting safe refactoring and migration

------------------------------------------------------------------------

## 3. Basic DocBlock Structure

A typical DocBlock has a short description, an optional detailed
description, and structured tags:

``` php
/**
 * Short description.
 *
 * Longer description explaining additional behavior,
 * requirements, limitations, side effects, or business rules.
 *
 * @param type $variable Description.
 * @return type Description.
 * @throws ExceptionType Description.
 */
```

Example:

``` php
/**
 * Deletes an email owned by a user.
 *
 * The email can only be deleted when the specified user
 * owns the email and the email has not already been archived.
 *
 * @param int $userId ID of the authenticated user.
 * @param int $emailId ID of the email to delete.
 *
 * @return bool True when the email was deleted.
 *
 * @throws InvalidArgumentException
 *     If either ID is invalid.
 *
 * @throws My_Exception_AccessDenied
 *     If the user does not own the email.
 */
public function deleteEmail($userId, $emailId)
{
    // ...
}
```

Think of a DocBlock as:

``` text
Human-readable description
            +
Structured @tags
```

------------------------------------------------------------------------

## 4. `@param`

`@param` documents a function or method parameter.

Syntax:

``` text
@param TYPE $variable Description
```

Example:

``` php
/**
 * @param int $userId User ID.
 */
```

Multiple parameters:

``` php
/**
 * Sends an email.
 *
 * @param int $userId Sender's user ID.
 * @param string $subject Email subject.
 * @param string $message Email body.
 *
 * @return bool
 */
public function sendEmail(
    $userId,
    $subject,
    $message
) {
    // ...
}
```

This is particularly useful in legacy code without native type
declarations.

------------------------------------------------------------------------

## 5. `@return`

`@return` documents what a method returns.

String:

``` php
/**
 * @return string
 */
public function getName()
{
    return $this->name;
}
```

Object:

``` php
/**
 * @return Application_Model_User
 */
public function getUser()
{
    // ...
}
```

Array:

``` php
/**
 * @return array
 */
public function getUsers()
{
    // ...
}
```

No return value:

``` php
/**
 * @return void
 */
public function clearCache()
{
    // ...
}
```

------------------------------------------------------------------------

## 6. Nullable and Union Types

If a method may return an object or `null`:

``` php
/**
 * @param int $userId
 *
 * @return Application_Model_User|null
 */
public function findUser($userId)
{
    // ...
}
```

The `|` means **or**:

``` text
Application_Model_User OR null
```

Another example:

``` php
/**
 * @return string|null
 */
public function getEmailAddress()
{
    // ...
}
```

PHPDoc can also describe other unions:

``` php
/**
 * @return int|false
 */
public function findRecordId()
{
    // ...
}
```

------------------------------------------------------------------------

## 7. Documenting Arrays

Writing only:

``` php
/**
 * @return array
 */
```

does not explain what is inside the array.

For an array of integers:

``` php
/**
 * @return int[]
 */
public function getUserIds()
{
    return [
        10,
        20,
        30,
    ];
}
```

For strings:

``` php
/**
 * @return string[]
 */
public function getRoles()
{
    return [
        'admin',
        'manager',
        'staff',
    ];
}
```

For objects:

``` php
/**
 * @return Application_Model_User[]
 */
public function getUsers()
{
    // ...
}
```

This means an array containing `Application_Model_User` objects.

------------------------------------------------------------------------

## 8. Array Shapes

Sometimes an array has a fixed structure:

``` php
$user = [
    'id' => 100,
    'name' => 'John',
    'email' => 'john@example.com',
    'active' => true,
];
```

A detailed PHPDoc type can describe it:

``` php
/**
 * @return array{
 *     id: int,
 *     name: string,
 *     email: string,
 *     active: bool
 * }
 */
public function getUserInfo()
{
    // ...
}
```

Conceptually:

``` text
array
│
├── id      -> int
├── name    -> string
├── email   -> string
└── active  -> bool
```

Array-shape syntax is especially useful to IDEs and static-analysis
tools.

------------------------------------------------------------------------

## 9. Documenting Input Arrays

Suppose a service accepts:

``` php
$data = [
    'subject' => 'Meeting',
    'message' => 'Meeting at 10 AM',
    'recipientIds' => [10, 20, 30],
];
```

Document it as:

``` php
/**
 * Creates an email.
 *
 * @param int $userId Sender's user ID.
 * @param array{
 *     subject: string,
 *     message: string,
 *     recipientIds: int[]
 * } $data Email information.
 *
 * @return int ID of the newly created email.
 */
public function createEmail($userId, array $data)
{
    // ...
}
```

This is more informative than:

``` php
/**
 * @param array $data
 */
```

------------------------------------------------------------------------

## 10. `@var`

`@var` documents properties and variables.

ZF1 property example:

``` php
/**
 * Email database table.
 *
 * @var Application_Model_DbTable_Email
 */
protected $emailTable;
```

Configuration value:

``` php
/**
 * JWT access-token lifetime in seconds.
 *
 * @var int
 */
private $accessTokenLifetime = 900;
```

PHPDoc helps the IDE understand properties in legacy code without typed
properties.

------------------------------------------------------------------------

## 11. PHPDoc and IDE Autocomplete

Consider:

``` php
protected $emailTable;
```

An IDE may not know the property's type.

Add:

``` php
/**
 * @var Application_Model_DbTable_Email
 */
protected $emailTable;
```

The IDE can now provide better:

-   Autocomplete
-   Method suggestions
-   Navigation
-   Type checking
-   Refactoring assistance

This is particularly useful in older ZF1 applications.

------------------------------------------------------------------------

## 12. Local Variable `@var`

PHPDoc can also describe a local variable:

``` php
/** @var Application_Model_User $user */
$user = $row;
```

Then the IDE can understand:

``` php
$user->
```

as an `Application_Model_User`.

Do not add local `@var` comments everywhere. Use them when the type
cannot be inferred reliably.

------------------------------------------------------------------------

## 13. `@throws`

`@throws` documents exceptions a method can produce.

Example:

``` php
/**
 * Deletes a document.
 *
 * @param int $userId
 * @param int $documentId
 *
 * @return void
 *
 * @throws InvalidArgumentException
 *     If an ID is invalid.
 *
 * @throws My_Exception_DocumentNotFound
 *     If the document does not exist.
 *
 * @throws My_Exception_AccessDenied
 *     If the user cannot delete the document.
 */
public function deleteDocument(
    $userId,
    $documentId
) {
    // ...
}
```

Conceptually:

``` text
deleteDocument()
      │
      ├── Success
      ├── InvalidArgumentException
      ├── DocumentNotFound
      └── AccessDenied
```

This helps callers understand which failures they should handle.

------------------------------------------------------------------------

## 14. `@deprecated`

During modernization, old methods can be marked as deprecated:

``` php
/**
 * Returns a user.
 *
 * @deprecated Use UserService::findById() instead.
 *
 * @param int $userId
 * @return Application_Model_User|null
 */
public function getOldUser($userId)
{
    // ...
}
```

This is useful during a gradual migration:

``` text
Old ZF1 code
    │
    ├── getOldUser()
    │       @deprecated
    │
    ▼
New service
    │
    └── UserService::findById()
```

IDEs can warn developers when deprecated methods are used.

------------------------------------------------------------------------

## 15. `@see`

`@see` points developers to related code or documentation:

``` php
/**
 * Validates an API access token.
 *
 * @see My_Controller_Plugin_JwtAuth
 */
public function validateToken($token)
{
    // ...
}
```

Use it when the relationship is genuinely useful.

------------------------------------------------------------------------

## 16. `@since`

`@since` can document when functionality was introduced:

``` php
/**
 * Validates JWT authentication.
 *
 * @since 2.4.0
 */
public function validateToken($token)
{
    // ...
}
```

This is useful when the application has meaningful release versions.

------------------------------------------------------------------------

## 17. Class-Level PHPDoc

PHPDoc can document an entire class:

``` php
/**
 * Provides business operations for email management.
 *
 * This service handles email-related business rules including
 * email creation, recipient validation, permission checking,
 * and email deletion.
 *
 * Database persistence is delegated to the email DbTable/model.
 */
class My_Service_Email
{
}
```

Good class-level PHPDoc answers:

> What is this class responsible for?

Avoid descriptions that simply repeat the class name:

``` php
/**
 * Email service.
 */
class My_Service_Email
{
}
```

------------------------------------------------------------------------

## 18. PHPDoc for a ZF1 Controller

Controllers do not need huge DocBlocks, but documentation can explain
important architectural behavior:

``` php
/**
 * API controller for email operations.
 */
class Api_EmailController
    extends Zend_Controller_Action
{
    /**
     * Returns emails visible to the authenticated user.
     *
     * Authentication is performed by the JWT controller
     * plugin before this action executes.
     *
     * Endpoint:
     *
     *     GET /api/email/getlist
     *
     * @return void
     */
    public function getlistAction()
    {
        $userId = $this->_getParam(
            'authenticatedUserId'
        );

        $service = new My_Service_Email();

        $result = $service->getList($userId);

        $this->_helper->json($result);
    }
}
```

Architecture:

``` text
JWT authentication
       ↓
Controller Plugin

Business logic
       ↓
EmailService

HTTP response
       ↓
EmailController
```

------------------------------------------------------------------------

## 19. PHPDoc for a ZF1 Model / DbTable

Example:

``` php
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
     * Returns email records visible to a user.
     *
     * @param int $userId User ID.
     *
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function getByUserId($userId)
    {
        $select = $this->select()
            ->where('user_id = ?', $userId)
            ->order('created_at DESC');

        return $this->fetchAll($select);
    }
}
```

------------------------------------------------------------------------

## 20. PHPDoc for a JWT Service

``` php
<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Provides JWT creation and validation.
 *
 * This class isolates firebase/php-jwt from the rest of
 * the application.
 */
class My_Jwt
{
    /**
     * JWT signing secret.
     *
     * @var string
     */
    private $secret;

    /**
     * Token lifetime in seconds.
     *
     * @var int
     */
    private $lifetime;

    /**
     * Creates the JWT service.
     *
     * @param string $secret JWT signing secret.
     * @param int $lifetime Access-token lifetime in seconds.
     */
    public function __construct(
        $secret,
        $lifetime = 900
    ) {
        $this->secret = $secret;
        $this->lifetime = $lifetime;
    }

    /**
     * Creates an access token for a user.
     *
     * @param int $userId Authenticated user ID.
     *
     * @return string Encoded JWT.
     */
    public function createToken($userId)
    {
        $now = time();

        $payload = [
            'sub' => (string) $userId,
            'iat' => $now,
            'exp' => $now + $this->lifetime,
        ];

        return JWT::encode(
            $payload,
            $this->secret,
            'HS256'
        );
    }

    /**
     * Validates and decodes an access token.
     *
     * @param string $token Encoded JWT.
     *
     * @return object Decoded JWT payload.
     *
     * @throws Exception
     *     If the token is invalid or expired.
     */
    public function validateToken($token)
    {
        return JWT::decode(
            $token,
            new Key($this->secret, 'HS256')
        );
    }
}
```

------------------------------------------------------------------------

## 21. PHPDoc vs Native PHP Types

PHP 7.4 supports native type declarations:

``` php
public function getUser(int $userId): ?User
{
    // ...
}
```

A DocBlock such as this mostly repeats the signature:

``` php
/**
 * @param int $userId
 * @return User|null
 */
public function getUser(int $userId): ?User
{
    // ...
}
```

Prefer PHPDoc that adds useful behavioral information:

``` php
/**
 * Returns an active user belonging to the current organization.
 *
 * Disabled and deleted users are treated as not found.
 *
 * @throws UserNotFoundException
 *     If no accessible user exists.
 */
public function getUser(int $userId): ?User
{
    // ...
}
```

A useful rule is:

> Native types describe what the data is. PHPDoc should document
> information the method signature cannot clearly communicate.

------------------------------------------------------------------------

## 22. PHPDoc Does Not Enforce Types at Runtime

This is important.

``` php
/**
 * @param int $userId
 */
public function getUser($userId)
{
    // ...
}
```

PHPDoc does **not** force `$userId` to be an integer.

Code may still call:

``` php
getUser('ABC');
```

PHPDoc is documentation and tooling metadata.

A native declaration:

``` php
public function getUser(int $userId)
{
    // ...
}
```

is part of PHP's type system.

Conceptually:

``` text
PHPDoc
   │
   ├── Documentation
   ├── IDE assistance
   ├── Static analysis
   └── Documentation generation

Native PHP types
   │
   └── PHP language/runtime type system
```

Do not treat PHPDoc as input validation.

------------------------------------------------------------------------

## 23. PHPDoc and Static Analysis

PHPDoc can provide richer information to static-analysis tools.

Example:

``` php
/**
 * @param int[] $userIds
 * @return Application_Model_User[]
 */
public function findUsers(array $userIds)
{
    // ...
}
```

PHP itself only sees `array`, while documentation/static-analysis tools
can understand the expected element types.

An array shape provides even more information:

``` php
/**
 * @param array{
 *     subject: string,
 *     message: string,
 *     recipientIds: int[]
 * } $data
 */
public function createEmail(array $data)
{
    // ...
}
```

This can help detect mistakes before runtime.

------------------------------------------------------------------------

## 24. PHPDoc for Callbacks

A callback can be documented with `callable`:

``` php
/**
 * Processes each user using the supplied callback.
 *
 * @param Application_Model_User[] $users
 * @param callable $callback
 *
 * @return void
 */
public function processUsers(
    array $users,
    callable $callback
) {
    foreach ($users as $user) {
        $callback($user);
    }
}
```

Where supported by your analysis tooling, a more precise callable
signature may be documented:

``` php
/**
 * @param callable(Application_Model_User): bool $filter
 */
```

------------------------------------------------------------------------

## 25. PHPDoc for Constants

Constants may also be documented when their purpose is not obvious:

``` php
/**
 * Default access-token lifetime in seconds.
 *
 * @var int
 */
const DEFAULT_ACCESS_TOKEN_LIFETIME = 900;
```

Avoid unnecessary comments for self-explanatory constants.

------------------------------------------------------------------------

## 26. PHPDoc for Interfaces

Interfaces are a good place to document contracts:

``` php
/**
 * Defines operations required for email persistence.
 */
interface My_EmailRepositoryInterface
{
    /**
     * Finds an email by ID.
     *
     * @param int $emailId
     *
     * @return Application_Model_Email|null
     */
    public function findById($emailId);

    /**
     * Deletes an email.
     *
     * @param int $emailId
     *
     * @return bool
     */
    public function delete($emailId);
}
```

The interface describes the contract; implementations provide the
behavior.

------------------------------------------------------------------------

## 27. PHPDoc and PHPUnit Tests

PHPDoc can make test helpers easier to understand, but test method names
should still clearly describe behavior.

Example:

``` php
use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    /**
     * Creates a valid email service for testing.
     *
     * @return My_Service_Email
     */
    private function createService()
    {
        // ...
    }

    public function testEmptySubjectIsRejected()
    {
        $service = $this->createService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->validateSubject('');
    }
}
```

Do not use PHPDoc as a replacement for descriptive test names.

------------------------------------------------------------------------

## 28. Good vs Bad PHPDoc

### Bad: Repeats the code

``` php
/**
 * Sets the name.
 *
 * @param string $name The name.
 *
 * @return void
 */
public function setName(string $name): void
{
    $this->name = $name;
}
```

Most of this is already obvious.

### Better: Explains business behavior

``` php
/**
 * Changes the employee's display name.
 *
 * Changing the display name does not modify the immutable
 * name stored in historical audit records.
 */
public function setName(string $name): void
{
    // ...
}
```

The second version provides information the signature cannot
communicate.

------------------------------------------------------------------------

## 29. Do Not Over-Document

Avoid comments such as:

``` php
// Increment counter by one.
$counter++;
```

Avoid DocBlocks that only restate method names.

Use detailed documentation where it adds value, especially for:

-   Public service methods
-   Complex business rules
-   Security-sensitive operations
-   Legacy untyped code
-   Complex arrays
-   Exceptions
-   Side effects
-   API contracts
-   Deprecated functionality
-   Code used by multiple modules/developers

------------------------------------------------------------------------

## 30. Recommended PHPDoc Style

A useful general pattern is:

``` php
/**
 * Short description of what the method does.
 *
 * Additional explanation only when useful:
 * business rules, security requirements, side effects,
 * assumptions, or special behavior.
 *
 * @param int $userId Description when necessary.
 * @param array $data Description.
 *
 * @return SomeType Description when necessary.
 *
 * @throws SomeException
 *     Explain under what condition it occurs.
 */
public function someMethod($userId, array $data)
{
    // ...
}
```

Do not force every small method to have a large DocBlock.

------------------------------------------------------------------------

## 31. Recommended Approach for a Legacy ZF1 Project

PHPDoc can act as a bridge during modernization:

``` text
Legacy ZF1
Loosely typed methods
       │
       ▼
Add useful PHPDoc
       │
       ▼
IDE understands more types
       │
       ▼
Add PHPUnit tests
       │
       ▼
Refactor safely
       │
       ▼
Add native PHP types gradually
       │
       ▼
Modern framework / modern PHP
```

A practical modernization strategy is:

1.  Do not add meaningless DocBlocks to every existing method.
2.  Add PHPDoc when modifying important legacy code.
3.  Document service-layer public APIs carefully.
4.  Document complex arrays and return structures.
5.  Document expected exceptions.
6.  Add native PHP 7.4 types where doing so is safe.
7.  Add PHPUnit tests before major refactoring.
8.  Gradually replace redundant PHPDoc types with native types as the
    codebase modernizes.

------------------------------------------------------------------------

## 32. Final Principle

The goal of PHPDoc is not to create more comments.

The goal is to make the codebase easier to **understand, navigate,
analyze, test, refactor, and maintain**.

Use this combination:

``` text
Clear names
    +
Native PHP types where appropriate
    +
Useful PHPDoc
    +
Focused comments for non-obvious decisions
    +
Automated tests
```

This provides much more value than adding documentation mechanically to
every line of code.
