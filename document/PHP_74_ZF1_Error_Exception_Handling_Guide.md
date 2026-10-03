# PHP Error and Exception Handling Guide

## For PHP 7.4 and Zend Framework 1 Projects

## 1. Introduction

This document explains PHP exception handling from the basics through
practical use in a Zend Framework 1 (ZF1) application.

The main concepts are:

``` text
throw
try
catch
finally
Exception
RuntimeException
Throwable
Error
custom exceptions
rethrowing
exception wrapping
```

The most important idea is:

> An exception represents a situation where normal execution cannot
> continue as expected, unless another part of the application knows how
> to handle the problem.

------------------------------------------------------------------------

## 2. A Problem Without Exceptions

Consider a division function:

``` php
function divide($a, $b)
{
    if ($b == 0) {
        return false;
    }

    return $a / $b;
}
```

The caller must remember to check the special return value:

``` php
$result = divide(10, 0);

if ($result === false) {
    echo 'Division failed.';
}
```

This works for simple code, but large applications can become difficult
to maintain when many methods use special values such as:

``` text
false
null
-1
0
special strings
```

to indicate failures.

Exceptions provide a separate failure path.

------------------------------------------------------------------------

## 3. Throwing an Exception

A function can report an exceptional condition using `throw`:

``` php
function divide($a, $b)
{
    if ($b == 0) {
        throw new Exception(
            'Cannot divide by zero.'
        );
    }

    return $a / $b;
}
```

Calling:

``` php
$result = divide(10, 0);
```

causes normal execution to stop at the `throw`.

``` text
Caller
  |
  v
divide(10, 0)
  |
  v
$b == 0
  |
  v
throw Exception
  |
  X normal flow stops
```

For example:

``` php
echo 'A';

$result = divide(10, 0);

echo 'B';
```

`B` is not reached through the normal execution path.

------------------------------------------------------------------------

## 4. `try` and `catch`

Use `try` when an operation may throw an exception and `catch` when the
current code knows how to respond.

``` php
try {

    $result = divide(10, 0);

    echo $result;

} catch (Exception $e) {

    echo 'Error: ';
    echo $e->getMessage();
}
```

The flow is:

``` text
try
 |
 v
divide()
 |
 X Exception
 |
 v
catch
 |
 v
handle failure
```

If no exception is thrown, the `catch` block is skipped.

------------------------------------------------------------------------

## 5. The Exception Object

In:

``` php
catch (Exception $e)
```

`$e` is the exception object.

Common methods include:

``` php
$e->getMessage();
$e->getCode();
$e->getFile();
$e->getLine();
$e->getTrace();
$e->getTraceAsString();
$e->getPrevious();
```

Example:

``` php
try {

    throw new Exception(
        'User does not exist.',
        1001
    );

} catch (Exception $e) {

    echo $e->getMessage();
    echo $e->getCode();
    echo $e->getFile();
    echo $e->getLine();
}
```

Do not normally expose file names, line numbers, stack traces, SQL
errors, or other internal exception details to API users. Those details
belong in server-side logs.

------------------------------------------------------------------------

## 6. Why Exceptions Are Better Than `die()`

Older PHP code sometimes contains:

``` php
if (!$user) {
    die('User not found.');
}
```

`die()` terminates the current script immediately.

With an exception:

``` php
if (!$user) {
    throw new Exception(
        'User not found.'
    );
}
```

a higher application layer can decide what to do:

``` php
try {

    $user = loadUser(1001);

} catch (Exception $e) {

    error_log($e->getMessage());

    // Return a controlled response.
}
```

This provides better separation between the code that detects a problem
and the code that decides how the application should respond.

------------------------------------------------------------------------

## 7. Custom Exceptions

Applications can define their own exception classes.

``` php
class UserNotFoundException
    extends Exception
{
}
```

Then:

``` php
function findUser($id)
{
    $user = null;

    // Load user...

    if (!$user) {
        throw new UserNotFoundException(
            'User was not found.'
        );
    }

    return $user;
}
```

Caller:

``` php
try {

    $user = findUser(1001);

} catch (UserNotFoundException $e) {

    echo 'User does not exist.';
}
```

A custom exception communicates the *type* of failure instead of forcing
callers to inspect message text.

------------------------------------------------------------------------

## 8. Multiple Exception Types

An application can distinguish several failures:

``` php
class UserException extends Exception
{
}

class UserNotFoundException
    extends UserException
{
}

class UserDisabledException
    extends UserException
{
}

class DatabaseException
    extends RuntimeException
{
}
```

Then:

``` php
try {

    $user = loginUser(
        $username,
        $password
    );

} catch (UserNotFoundException $e) {

    // Handle missing user.

} catch (UserDisabledException $e) {

    // Handle disabled user.

} catch (DatabaseException $e) {

    // Handle database failure.
}
```

This is easier to maintain than comparing error-message strings.

------------------------------------------------------------------------

## 9. Catch Order Matters

Suppose:

``` php
class UserException
    extends Exception
{
}

class UserNotFoundException
    extends UserException
{
}
```

Use the most specific exception first:

``` php
try {

    // ...

} catch (UserNotFoundException $e) {

    // Specific handling.

} catch (UserException $e) {

    // General user exception.

} catch (Exception $e) {

    // General exception.
}
```

Think of the order as:

``` text
Most specific
     |
     v
More general
     |
     v
Most general
```

A broad parent catch placed too early can consume exceptions before a
more specific handler gets a chance to process them.

------------------------------------------------------------------------

## 10. `finally`

PHP supports `finally`:

``` php
try {

    // Operation.

} catch (Exception $e) {

    // Failure handling.

} finally {

    // Cleanup.
}
```

The `finally` block executes whether the `try` succeeds or throws an
exception.

Example:

``` php
$file = fopen(
    '/tmp/example.txt',
    'r'
);

try {

    // Process file.

} finally {

    if (is_resource($file)) {
        fclose($file);
    }
}
```

`finally` is useful when cleanup must occur regardless of success or
failure.

------------------------------------------------------------------------

## 11. Database Transactions

Exception handling is especially useful with database transactions.

``` php
$db->beginTransaction();

try {

    $users->insert(
        array(
            'username' => 'john'
        )
    );

    $roles->insert(
        array(
            'user_id' => 1001,
            'role_id' => 2
        )
    );

    $db->commit();

} catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

Flow:

``` text
beginTransaction()
       |
       v
insert user
       |
       v
insert role
       |
       +--------- success --------+
       |                          |
       X failure                  v
       |                       commit
       v
   exception
       |
       v
     catch
       |
       v
   rollBack()
       |
       v
   throw $e
```

This pattern is useful when multiple database changes must succeed
together.

------------------------------------------------------------------------

## 12. Rethrowing an Exception

Consider:

``` php
catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

The exception is caught temporarily so the method can perform cleanup.

`throw $e` then sends the same exception to the caller.

This is called **rethrowing**.

Example:

``` php
function saveUser()
{
    $db = getDatabase();

    $db->beginTransaction();

    try {

        // Save data.

        $db->commit();

    } catch (Exception $e) {

        $db->rollBack();

        throw $e;
    }
}
```

Higher layer:

``` php
try {

    saveUser();

} catch (Exception $e) {

    error_log(
        $e->getMessage()
    );

    echo 'Saving failed.';
}
```

Flow:

``` text
Controller
    |
    v
saveUser()
    |
    v
Database
    |
    X failure
    |
    v
catch
    |
    +-- rollback
    |
    +-- throw $e
            |
            v
       Controller catch
```

------------------------------------------------------------------------

## 13. Exception Inheritance in PHP 7.4

A simplified hierarchy is:

``` text
Throwable
├── Error
│   ├── TypeError
│   ├── ParseError
│   └── ...
│
└── Exception
    ├── RuntimeException
    ├── LogicException
    ├── InvalidArgumentException
    └── ...
```

A custom class can extend one of these:

``` php
class User_JwtException
    extends RuntimeException
{
}
```

Its inheritance becomes:

``` text
User_JwtException
       |
       v
RuntimeException
       |
       v
Exception
       |
       v
Throwable
```

Therefore:

``` php
catch (User_JwtException $e)
```

can catch it.

So can:

``` php
catch (RuntimeException $e)
```

and:

``` php
catch (Exception $e)
```

because those are parent classes.

------------------------------------------------------------------------

## 14. `Exception` vs `Error`

PHP 7 introduced `Throwable` as the common interface implemented by both
`Exception` and `Error`.

This matters because:

``` php
catch (Exception $e)
```

does **not** catch every engine-level `Error`.

For example, some invalid type operations can throw `TypeError`, which
belongs to the `Error` branch.

A top-level boundary can use:

``` php
try {

    // Application operation.

} catch (Throwable $e) {

    error_log(
        $e->getMessage()
    );
}
```

However, do not use a broad `catch (Throwable)` everywhere and silently
ignore programming bugs. Broad catches are most useful at controlled
top-level boundaries where the application logs the failure and produces
an appropriate response.

------------------------------------------------------------------------

## 15. `RuntimeException`

`RuntimeException` is an exception class representing problems
encountered while an operation is running.

Example:

``` php
if (!file_exists($filename)) {
    throw new RuntimeException(
        'Required file does not exist.'
    );
}
```

For the JWT subsystem, we used:

``` php
class User_JwtException
    extends RuntimeException
{
}
```

This gives the application a dedicated JWT failure type while still
fitting PHP's normal exception hierarchy.

------------------------------------------------------------------------

## 16. `InvalidArgumentException`

Use `InvalidArgumentException` when a caller supplies an invalid
argument.

Example:

``` php
function createUser($username)
{
    if (!is_string($username)) {
        throw new InvalidArgumentException(
            'Username must be a string.'
        );
    }

    if ($username === '') {
        throw new InvalidArgumentException(
            'Username cannot be empty.'
        );
    }
}
```

This clearly communicates that the caller violated the method's input
contract.

------------------------------------------------------------------------

## 17. Exception Codes

PHP exceptions can contain numeric codes.

``` php
throw new Exception(
    'User not found.',
    1001
);
```

Then:

``` php
catch (Exception $e) {

    echo $e->getCode();
}
```

returns:

``` text
1001
```

Named constants are preferable to unexplained numbers.

For example:

``` php
class User_JwtException
    extends RuntimeException
{
    const TOKEN_INVALID = 1001;
    const TOKEN_EXPIRED = 1002;
    const TOKEN_INVALID_SIGNATURE = 1003;
    const TOKEN_NOT_YET_VALID = 1004;
    const TOKEN_INVALID_CLAIMS = 1005;
}
```

Usage:

``` php
throw new User_JwtException(
    'JWT token has expired.',
    User_JwtException::TOKEN_EXPIRED
);
```

This is much clearer than:

``` php
throw new User_JwtException(
    'JWT token has expired.',
    1002
);
```

------------------------------------------------------------------------

## 18. Exception Wrapping

Sometimes a library throws its own exception, but the rest of the
application should not depend directly on that library.

Our JWT service is a good example.

The JWT library can throw:

``` text
ExpiredException
SignatureInvalidException
BeforeValidException
UnexpectedValueException
```

Instead of making every controller understand those classes,
`User_JwtService` converts them into the application's own exception.

``` php
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
}
```

Flow:

``` text
firebase/php-jwt
       |
       X
ExpiredException
       |
       v
User_JwtService
       |
       +-- catches library exception
       |
       v
User_JwtException
       |
       v
ZF1 application
```

This is called **exception wrapping** or exception translation.

------------------------------------------------------------------------

## 19. Previous Exceptions

When wrapping an exception, preserve the original exception:

``` php
throw new User_JwtException(
    'JWT token has expired.',
    User_JwtException::TOKEN_EXPIRED,
    $e
);
```

The new exception can retain the original as its previous exception.

Later:

``` php
$previous =
    $e->getPrevious();
```

This provides useful debugging information.

Conceptually:

``` text
User_JwtException
       |
       +-- message
       +-- application error code
       |
       +-- previous
               |
               v
       ExpiredException
```

The external API does not need the internal details, but logs can
preserve the exception chain.

------------------------------------------------------------------------

## 20. JWT Exception Example

A representative JWT exception class:

``` php
class User_JwtException
    extends RuntimeException
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
        parent::__construct(
            $message,
            0,
            $previous
        );

        $this->jwtErrorCode =
            $jwtErrorCode;
    }

    public function getJwtErrorCode()
    {
        return $this->jwtErrorCode;
    }
}
```

Then:

``` php
try {

    $claims =
        $jwtService
            ->verifyAccessToken($token);

} catch (User_JwtException $e) {

    // Authentication failed.
}
```

The controller/plugin no longer needs to know the internals of
`firebase/php-jwt`.

------------------------------------------------------------------------

## 21. Why Hide Library Exceptions?

Without an application-specific exception, every caller might need:

``` php
try {

    // Verify JWT.

} catch (ExpiredException $e) {

    // ...

} catch (SignatureInvalidException $e) {

    // ...

} catch (BeforeValidException $e) {

    // ...

} catch (UnexpectedValueException $e) {

    // ...
}
```

With a service boundary:

``` php
try {

    $claims =
        $jwtService
            ->verifyAccessToken($token);

} catch (User_JwtException $e) {

    // Handle JWT authentication failure.
}
```

This reduces coupling between the application and a third-party library.

If the JWT library changes later, most controllers do not need to
change.

------------------------------------------------------------------------

## 22. Exceptions and API Responses

Internal exception information and public API responses should be
separated.

Internally:

``` text
Firebase ExpiredException
        |
        v
User_JwtException
        |
        v
API authentication layer
```

Externally:

``` http
HTTP/1.1 401 Unauthorized
Content-Type: application/json
```

``` json
{
    "error": "invalid_token",
    "message": "Authentication required."
}
```

Do not return:

``` text
PHP file path
source line
stack trace
SQL statement
database password
JWT secret
library internals
```

to the client.

Those details should remain server-side.

------------------------------------------------------------------------

## 23. Logging Exceptions

A simple example:

``` php
catch (Exception $e) {

    error_log(
        sprintf(
            '%s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        )
    );
}
```

For development, a stack trace can be useful:

``` php
error_log(
    $e->getTraceAsString()
);
```

Production logging should be designed so that sensitive information is
not written accidentally.

Avoid logging:

``` text
Passwords
JWT signing secrets
Raw refresh tokens
Database credentials
Other authentication secrets
```

------------------------------------------------------------------------

## 24. When to Throw an Exception

A practical rule is:

> Throw an exception when a method cannot fulfill the operation its
> contract promises to perform.

Example:

``` php
public function createUser(array $data)
{
    if (empty($data['username'])) {
        throw new InvalidArgumentException(
            'Username is required.'
        );
    }

    // Create user...
}
```

JWT example:

``` php
public function verifyAccessToken($token)
{
    if ($token === '') {
        throw new User_JwtException(
            'Token is empty.',
            User_JwtException::TOKEN_INVALID
        );
    }

    // Verify...
}
```

------------------------------------------------------------------------

## 25. When Not to Throw an Exception

Exceptions should not replace normal `if` statements.

Bad:

``` php
try {

    if ($user->isAdmin()) {
        throw new Exception('ADMIN');
    }

} catch (Exception $e) {

    // Admin operation.
}
```

Correct:

``` php
if ($user->isAdmin()) {
    // Admin operation.
}
```

Normal application states are often better represented with:

``` text
if/else
boolean values
null
result objects
normal return values
```

depending on the method's contract.

------------------------------------------------------------------------

## 26. Is "Not Found" an Exception?

It depends on the method.

A lookup method might intentionally return `null`:

``` php
public function findUser($id)
{
    $row = $this->table->find($id);

    if (!$row) {
        return null;
    }

    return $row;
}
```

The caller expects that a user might not exist.

But another method may promise that a user must exist:

``` php
public function requireUser($id)
{
    $user = $this->findUser($id);

    if (!$user) {
        throw new UserNotFoundException(
            'Required user does not exist.'
        );
    }

    return $user;
}
```

Neither approach is automatically correct or incorrect. The important
point is to define a clear method contract and use it consistently.

------------------------------------------------------------------------

## 27. Recommended ZF1 Layering

For a larger ZF1 application:

``` text
Controller
    |
    v
Service
    |
    v
Repository / DbTable
    |
    v
MariaDB
```

Responsibilities can be separated as follows:

``` text
Controller
    Handles HTTP request/response.
    Converts application failures to HTTP status codes.

Service
    Contains business rules.
    Throws meaningful application exceptions.

Repository / DbTable
    Performs persistence operations.

MariaDB
    Stores data.
```

This architecture prevents controllers from becoming filled with
database-specific error handling.

------------------------------------------------------------------------

## 28. Example Service Exception Translation

Suppose a service writes a user:

``` php
class User_UserService
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    public function createUser(array $data)
    {
        if (empty($data['username'])) {
            throw new InvalidArgumentException(
                'Username is required.'
            );
        }

        try {

            return $this->users->insert(
                $data
            );

        } catch (Zend_Db_Exception $e) {

            throw new User_DatabaseException(
                'Could not create user.',
                0,
                $e
            );
        }
    }
}
```

Controller:

``` php
try {

    $userId =
        $userService
            ->createUser($data);

} catch (InvalidArgumentException $e) {

    // HTTP 400

} catch (User_DatabaseException $e) {

    error_log(
        $e->getMessage()
    );

    // HTTP 500
}
```

The controller understands the application's exception types rather than
low-level database implementation details.

------------------------------------------------------------------------

## 29. Exceptions and HTTP Status Codes

Exceptions can be translated into HTTP responses at an API boundary.

For example:

``` text
InvalidArgumentException
        |
        v
400 Bad Request

AuthenticationException
        |
        v
401 Unauthorized

AuthorizationException
        |
        v
403 Forbidden

NotFoundException
        |
        v
404 Not Found

Unexpected server/database failure
        |
        v
500 Internal Server Error
```

Do not assume every exception should automatically become a specific
HTTP code. The API boundary should deliberately perform that
translation.

------------------------------------------------------------------------

## 30. Catch Only What You Can Handle

Avoid unnecessarily broad catches:

``` php
try {

    doSomething();

} catch (Exception $e) {

    // Ignore everything.
}
```

This is especially dangerous:

``` php
catch (Exception $e) {
}
```

because failures disappear silently.

A better approach is:

``` php
try {

    doSomething();

} catch (SpecificException $e) {

    // Handle the failure that this layer
    // actually understands.
}
```

If the current layer cannot resolve the problem, log/wrap/rethrow it or
allow it to propagate to an appropriate higher-level boundary.

------------------------------------------------------------------------

## 31. Do Not Use Empty Catch Blocks

Bad:

``` php
try {

    saveData();

} catch (Exception $e) {

}
```

This can make debugging extremely difficult.

At minimum, decide explicitly whether to:

``` text
handle
log
wrap
rethrow
convert to an application result
```

Never silently discard an unexpected failure without a deliberate
reason.

------------------------------------------------------------------------

## 32. Do Not Show Exception Details to Users

Bad production response:

``` php
catch (Exception $e) {

    echo $e->getMessage();
    echo $e->getFile();
    echo $e->getLine();
    echo $e->getTraceAsString();
}
```

Better:

``` php
catch (Exception $e) {

    error_log(
        $e->getMessage()
    );

    $this->getResponse()
        ->setHttpResponseCode(500)
        ->setBody(
            json_encode(
                array(
                    'error' =>
                        'internal_error',
                    'message' =>
                        'An internal error occurred.'
                )
            )
        );
}
```

The user receives a safe message while technical information remains in
logs.

------------------------------------------------------------------------

## 33. Exceptions in PHPUnit Tests

PHPUnit can verify that code throws the expected exception.

Example:

``` php
public function testEmptyUsernameIsRejected()
{
    $this->expectException(
        InvalidArgumentException::class
    );

    $service->createUser(
        array(
            'username' => ''
        )
    );
}
```

For the JWT service:

``` php
public function testInvalidTokenIsRejected()
{
    $this->expectException(
        User_JwtException::class
    );

    $this->service
        ->verifyAccessToken(
            'invalid-token'
        );
}
```

You can also inspect a caught exception when its application-specific
code matters:

``` php
try {

    $this->service
        ->verifyAccessToken(
            $expiredToken
        );

    $this->fail(
        'Expected User_JwtException.'
    );

} catch (User_JwtException $e) {

    $this->assertSame(
        User_JwtException::TOKEN_EXPIRED,
        $e->getJwtErrorCode()
    );
}
```

------------------------------------------------------------------------

## 34. Normal Flow vs Exception Flow

### Normal flow

``` text
Controller
    |
    v
Service
    |
    v
Repository
    |
    v
MariaDB
    |
    v
Result
    |
    v
Service
    |
    v
Controller
    |
    v
HTTP success response
```

### Exception flow

``` text
Controller
    |
    v
Service
    |
    v
Repository
    |
    v
MariaDB
    |
    X failure
    |
    v
exception
    |
    v
Service
    |
    +-- cleanup
    +-- translate/wrap if appropriate
    |
    v
Controller / API error boundary
    |
    +-- log technical details
    +-- select HTTP status
    |
    v
safe error response
```

------------------------------------------------------------------------

## 35. Four Keywords to Remember

### `throw`

Means:

> Normal execution cannot continue; report this exceptional condition.

``` php
throw new RuntimeException(
    'Operation failed.'
);
```

### `try`

Means:

> Execute this operation knowing that it may throw.

``` php
try {
    doSomething();
}
```

### `catch`

Means:

> If this specific exception reaches me, I know how to respond.

``` php
catch (RuntimeException $e) {
    // Handle.
}
```

### `finally`

Means:

> Execute this cleanup whether the operation succeeds or fails.

``` php
finally {
    // Cleanup.
}
```

------------------------------------------------------------------------

## 36. Recommended Exception Strategy for This ZF1 Project

A practical architecture is:

``` text
Third-party library / Zend / MariaDB
               |
               | low-level exception
               v
         Application Service
               |
               | translate when useful
               v
       Application Exception
               |
               v
      Controller / API Boundary
               |
       +-------+-------+
       |               |
       v               v
     Log            HTTP response
 technical         safe/generic
 details              error
```

Examples:

``` text
Firebase ExpiredException
        ->
User_JwtException
        ->
401 response

Zend_Db_Exception
        ->
User_DatabaseException
        ->
500 response

InvalidArgumentException
        ->
400 response
```

The exact mapping should be defined by the API contract.

------------------------------------------------------------------------

## 37. Practical Rules

For the PHP 7.4/ZF1 application:

1.  Throw exceptions when an operation cannot fulfill its contract.
2.  Catch an exception only where the code can meaningfully handle,
    translate, log, or clean up after it.
3.  Use custom exception classes for important application domains.
4.  Catch specific exceptions before general parent exceptions.
5.  Preserve the previous exception when wrapping another exception.
6.  Rethrow after transaction cleanup when the current layer cannot
    resolve the failure.
7.  Never use exceptions as ordinary `if/else` control flow.
8.  Avoid empty catch blocks.
9.  Do not expose stack traces or internal exception details to API
    clients.
10. Log enough server-side information for diagnosis without logging
    secrets.
11. Use `finally` when cleanup must happen regardless of success or
    failure.
12. Understand that `Exception` and `Error` both implement `Throwable`
    in PHP 7.4.
13. Use broad `Throwable` catches mainly at carefully chosen application
    boundaries, not everywhere.
14. Test important exception paths with PHPUnit.
15. Keep third-party exception details behind service boundaries when
    practical.

------------------------------------------------------------------------

## 38. Final Example

A complete simplified service/controller flow:

``` php
class UserNotFoundException
    extends RuntimeException
{
}

class UserService
{
    public function getUser($id)
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException(
                'Invalid user ID.'
            );
        }

        try {

            // Example database lookup.
            $user = $this->findUser($id);

        } catch (Zend_Db_Exception $e) {

            throw new RuntimeException(
                'Database operation failed.',
                0,
                $e
            );
        }

        if (!$user) {
            throw new UserNotFoundException(
                'User does not exist.'
            );
        }

        return $user;
    }
}
```

Controller:

``` php
try {

    $user =
        $userService->getUser(
            $userId
        );

    // Return successful response.

} catch (InvalidArgumentException $e) {

    // 400 Bad Request

} catch (UserNotFoundException $e) {

    // 404 Not Found

} catch (RuntimeException $e) {

    error_log(
        $e->getMessage()
    );

    // 500 Internal Server Error
}
```

The central idea is:

``` text
Detect problem
      |
      v
throw
      |
      v
exception travels upward
      |
      v
appropriate catch
      |
      +-- handle
      +-- cleanup
      +-- log
      +-- translate
      +-- or rethrow
```

Once this model is familiar, the exception handling used in JWT
verification, refresh-token transactions, database operations, API
responses, and PHPUnit tests becomes much easier to understand.
