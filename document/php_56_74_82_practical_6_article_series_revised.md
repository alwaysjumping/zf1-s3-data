# PHP 5.6, PHP 7.4, and PHP 8.2
## A Practical 6-Part Series for Legacy Application Developers

**Audience:** PHP developers, maintainers of legacy applications, technical managers, and teams planning a staged PHP modernization.

**Purpose:** This series explains how PHP changed from 5.6 to 7.4 and then to 8.2, with emphasis on language design, type safety, error handling, backward compatibility, performance, security, and realistic migration of legacy business applications.

> **Lifecycle note — October 2026:** PHP 5.6 and PHP 7.4 are unsupported historical branches. PHP 8.2 receives security fixes through December 31, 2026. These versions are compared because they are common migration milestones, not because unsupported versions should be chosen for new production systems.

> **Migration principle:** A PHP 5.6 → PHP 8.2 migration crosses many intermediate releases. Production migration work should review the official migration guides for PHP 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, and 8.2 rather than reading only the guide for the final target.

---

# Article 1 — From PHP 5.6 to PHP 8.2: How the Language Changed

PHP 5.6, PHP 7.4, and PHP 8.2 represent three very different stages in the evolution of PHP.

A PHP 5.6 application may still look familiar to a modern PHP developer, but the language around it changed substantially over the following years. PHP 7 introduced a major engine generation, stronger type declarations, new operators, and a different error model. PHP 7.4 added typed properties and more concise syntax. PHP 8 then introduced union types, attributes, named arguments, constructor property promotion, `match`, the nullsafe operator, enums, readonly features, and additional typing improvements.

For a developer maintaining a legacy business application, the important question is not simply:

> “What new syntax was added?”

The more useful question is:

> “How did the programming model change, and what does that mean for code written in the PHP 5 era?”

## 1. PHP 5.6: the final stage of the PHP 5 generation

PHP 5.6 introduced several useful features. One example is variadic functions:

```php
function total($first, ...$values)
{
    $sum = $first;

    foreach ($values as $value) {
        $sum += $value;
    }

    return $sum;
}

echo total(10, 20, 30);
```

PHP 5.6 also introduced argument unpacking:

```php
$values = [10, 20, 30];

echo total(...$values);
```

These features were valuable, but the language was still largely characterized by loosely defined application contracts.

A typical class might look like this:

```php
class Ticket
{
    protected $id;
    protected $subject;
    protected $status;

    public function __construct($id, $subject, $status)
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->status = $status;
    }

    public function getId()
    {
        return $this->id;
    }

    public function isOpen()
    {
        return $this->status === 'open';
    }
}
```

The class itself does not tell PHP that:

- `$id` should be an integer;
- `$subject` should be a string;
- `$status` should be one of a limited set of values;
- `getId()` should return an integer;
- `isOpen()` should return a boolean.

Developers often documented those expectations with PHPDoc:

```php
/**
 * @param int $id
 * @param string $subject
 * @param string $status
 */
```

PHPDoc is useful, but it is not the same as a runtime-enforced type declaration.

## 2. PHP 7: stronger contracts

PHP 7.0 introduced scalar type declarations and return type declarations.

A method could now express more of its contract:

```php
public function findUser(int $id): array
{
    // ...
}
```

This is an important change in programming style.

Instead of writing:

```php
function calculateTax($amount)
{
    // ...
}
```

a developer can express:

```php
function calculateTax(float $amount): float
{
    // ...
}
```

The function contract becomes easier to understand, easier to test, and easier for IDEs and static-analysis tools to inspect.

PHP 7 also introduced the null-coalescing operator:

```php
$username = $_GET['username'] ?? 'guest';
```

This replaced a common pattern:

```php
$username = isset($_GET['username'])
    ? $_GET['username']
    : 'guest';
```

Another addition was the spaceship operator:

```php
$result = $a <=> $b;
```

This is especially useful in sorting callbacks:

```php
usort($tickets, function ($a, $b) {
    return $a['priority'] <=> $b['priority'];
});
```

PHP 7 also introduced anonymous classes:

```php
$logger = new class {
    public function log(string $message): void
    {
        echo $message;
    }
};
```

Anonymous classes can be useful in tests or for small implementations, although important domain components usually remain clearer as named classes.

## 3. PHP 7.1, 7.2, and 7.3 continued the transition

Developers migrating from PHP 5.6 to PHP 7.4 should not think of PHP 7.4 as one single change.

PHP 7.1 added features including nullable types:

```php
function findUser(int $id): ?User
{
    // ...
}
```

This expresses an important contract:

```text
User OR null
```

Later PHP 7 releases continued evolving syntax and behavior, while also deprecating older functionality.

That is why the migration path should conceptually be:

```text
PHP 5.6
  ↓
PHP 7.0
  ↓
PHP 7.1
  ↓
PHP 7.2
  ↓
PHP 7.3
  ↓
PHP 7.4
```

even if the application is not physically deployed on every intermediate version.

## 4. PHP 7.4: typed properties

PHP 7.4 introduced one of the most important object-model improvements of the PHP 7 generation: typed properties.

Before:

```php
class User
{
    private $id;
    private $name;
}
```

PHP 7.4:

```php
class User
{
    private int $id;
    private string $name;
}
```

This allows PHP to enforce object state more directly.

It also makes classes easier to understand without reading every setter, constructor, or comment.

However, typed properties introduce a new responsibility: initialization.

For example:

```php
class User
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
```

If `$id` has not been initialized before `getId()` is called, the application will fail.

This can expose assumptions hidden inside older code.

## 5. PHP 7.4: arrow functions

PHP 7.4 also introduced arrow functions.

Traditional callback:

```php
$ids = array_map(function ($ticket) {
    return $ticket['id'];
}, $tickets);
```

Arrow function:

```php
$ids = array_map(
    fn($ticket) => $ticket['id'],
    $tickets
);
```

Arrow functions are useful for short expressions, but they should not be used merely because they are newer.

If a callback contains complicated business logic, a normal named method or closure may remain clearer.

## 6. PHP 8.0: another major generation

PHP 8 introduced several features that significantly change how application code can be written.

### Union types

PHP 7 could not directly declare:

```text
integer OR string
```

PHP 8 can:

```php
function normalizeId(int|string $id): int
{
    return (int) $id;
}
```

This allows more API contracts to move from PHPDoc into the language.

### Constructor property promotion

Traditional:

```php
class User
{
    private int $id;
    private string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
```

PHP 8:

```php
class User
{
    public function __construct(
        private int $id,
        private string $name
    ) {
    }
}
```

This significantly reduces boilerplate.

### Named arguments

```php
createUser(
    username: 'alice',
    active: true
);
```

Named arguments can make calls easier to read, especially when multiple parameters are optional or boolean.

However, they introduce an important API design consideration: parameter names become more important to callers.

If an external caller uses:

```php
createUser(username: 'alice');
```

renaming the parameter from `$username` to `$name` may become a backward-compatibility issue.

### `match`

PHP 8 introduced `match`:

```php
$label = match ($status) {
    'open'   => 'Open',
    'closed' => 'Closed',
    default  => 'Unknown',
};
```

This is concise and expressive, but developers should not treat it as a mechanical replacement for every `switch`.

`match` has different semantics, including strict comparison.

### Nullsafe operator

Old code:

```php
$country = null;

if ($user !== null) {
    $address = $user->getAddress();

    if ($address !== null) {
        $country = $address->getCountry();
    }
}
```

PHP 8:

```php
$country = $user?->getAddress()?->getCountry();
```

This can make optional object chains much easier to read.

## 7. PHP 8.1 and PHP 8.2

PHP 8.1 introduced major language features including enums and readonly properties.

Example enum:

```php
enum TicketStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Pending = 'pending';
}
```

Instead of:

```php
$status = 'opne';
```

which contains a typo that may go unnoticed, code can use:

```php
$status = TicketStatus::Open;
```

Enums allow the domain to express a controlled set of values.

Readonly properties also allow stronger immutability:

```php
class User
{
    public function __construct(
        public readonly int $id
    ) {
    }
}
```

PHP 8.2 expanded readonly capabilities and continued development of the type system.

## 8. Syntax evolution is only part of the story

The migration problem is bigger than syntax.

A real legacy system contains:

```text
Application code
      ↓
Framework
      ↓
Third-party libraries
      ↓
Custom libraries
      ↓
PHP extensions
      ↓
Database drivers
      ↓
Web server / operating system
```

A PHP upgrade may affect any of these layers.

For example, a PHP 5.6 business application may rely on:

- an old Zend Framework distribution;
- deprecated database APIs;
- a custom PHP extension;
- a legacy image library;
- an old email library;
- session behavior tested only on PHP 5;
- error handling that assumes PHP 5 semantics.

The code may parse successfully on a newer runtime while still behaving differently.

## 9. Compatibility before modernization

A useful migration principle is:

```text
Phase 1 — Compatibility
Make the existing application run correctly.

Phase 2 — Modernization
Improve syntax, types, architecture, and maintainability.
```

This is much safer than changing everything at once.

If the application stops working after a large upgrade, developers need to know whether the cause is:

```text
PHP runtime behavior?
Framework change?
New type declaration?
Database code rewrite?
Dependency update?
Refactoring?
```

The fewer simultaneous changes, the easier the problem is to diagnose.

## Key Takeaways

PHP changed from a relatively loose PHP 5 programming model toward a much more expressive and enforceable language.

The important changes include:

```text
PHP 5.6
  ↓
variadics, argument unpacking
  ↓
PHP 7
  ↓
scalar types, return types, Throwable model
  ↓
PHP 7.4
  ↓
typed properties, arrow functions
  ↓
PHP 8
  ↓
union types, named arguments,
property promotion, match, nullsafe operator
  ↓
PHP 8.1 / 8.2
  ↓
enums, readonly features,
further type-system improvements
```

But the most important migration lesson is not syntax.

It is this:

> Preserve and verify existing behavior before aggressively modernizing the design.

---

## References and Source Documents

1. PHP Manual — Migrating from PHP 5.5.x to PHP 5.6.x  
   https://www.php.net/manual/en/migration56.php
2. PHP Manual — Migrating from PHP 5.6.x to PHP 7.0.x  
   https://www.php.net/manual/en/migration70.php
3. PHP Manual — Migrating from PHP 7.0.x to PHP 7.1.x  
   https://www.php.net/manual/en/migration71.php
4. PHP Manual — Migrating from PHP 7.1.x to PHP 7.2.x  
   https://www.php.net/manual/en/migration72.php
5. PHP Manual — Migrating from PHP 7.2.x to PHP 7.3.x  
   https://www.php.net/manual/en/migration73.php
6. PHP Manual — Migrating from PHP 7.3.x to PHP 7.4.x  
   https://www.php.net/manual/en/migration74.php
7. PHP Manual — Migrating from PHP 7.4.x to PHP 8.0.x  
   https://www.php.net/manual/en/migration80.php
8. PHP Manual — Migrating from PHP 8.0.x to PHP 8.1.x  
   https://www.php.net/manual/en/migration81.php
9. PHP Manual — Migrating from PHP 8.1.x to PHP 8.2.x  
   https://www.php.net/manual/en/migration82.php

---

# Article 2 — The Evolution of PHP Types and Object-Oriented Design

One of the clearest differences between PHP 5.6 and PHP 8.2 is how much information the language itself can express about program state.

A PHP 5.6 application can be well designed, but developers often depend on naming conventions, PHPDoc, runtime validation, and developer discipline to keep data consistent.

Modern PHP can express far more of the application's intended contract directly in code.

## 1. PHP 5.6: conventions and documentation

A common PHP 5.6 class might be:

```php
class User
{
    private $id;
    private $name;
    private $email;

    public function __construct($id, $name, $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }
}
```

Nothing stops a caller from doing:

```php
$user = new User(
    'ABC',
    500,
    array('wrong')
);
```

unless the constructor manually validates every value.

PHPDoc can help:

```php
/**
 * @param int $id
 * @param string $name
 * @param string $email
 */
```

but PHPDoc is descriptive rather than enforced by the runtime.

## 2. Parameter and return types

PHP 7 introduced scalar and return declarations:

```php
function findUser(int $id): array
{
    // ...
}
```

This improves several things at once:

- the function is easier to understand;
- IDEs can reason about it better;
- incorrect calls are discovered earlier;
- refactoring becomes safer;
- tests can focus on well-defined contracts.

However, adding types to legacy code can expose hidden assumptions.

Imagine old code:

```php
$user = $service->findUser($_GET['id']);
```

`$_GET['id']` is typically a string.

If code has silently depended on coercion for years, adding strict typing without investigation can change behavior.

Therefore the correct migration sequence is:

```text
Observe actual input
      ↓
Write tests
      ↓
Define desired contract
      ↓
Add type declaration
      ↓
Update callers
```

## 3. Nullable types

A legacy repository may return either an object or `null`.

PHPDoc:

```php
/**
 * @return User|null
 */
```

PHP 7.1:

```php
public function find(int $id): ?User
{
    // ...
}
```

This makes absence explicit.

It also forces developers to think about caller behavior:

```php
$user = $repository->find($id);

if ($user === null) {
    // not found
}
```

## 4. Typed properties

PHP 7.4 allows:

```php
class User
{
    private int $id;
    private string $name;
    private ?string $phone = null;
}
```

This is much more informative than:

```php
private $id;
private $name;
private $phone;
```

But typed properties introduce the concept of an uninitialized typed property.

For example:

```php
class User
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
```

If `$id` has never been initialized, access is invalid.

Older PHP code sometimes creates objects in partially initialized states. A PHP 7.4 migration can expose this design problem.

## 5. Union types in PHP 8

Legacy APIs sometimes legitimately accept multiple input types.

Example:

```php
/**
 * @param int|string $id
 */
function normalizeId($id)
{
    return (int) $id;
}
```

PHP 8:

```php
function normalizeId(int|string $id): int
{
    return (int) $id;
}
```

The contract becomes executable.

Union types are useful, but developers should not use them to avoid deciding what an API really means.

For example:

```php
function save(int|string|array|object|null $value)
```

may technically be valid, but such a broad contract can indicate an unclear design.

## 6. `mixed`

PHP 8 introduced `mixed` for intentionally broad values:

```php
function normalize(mixed $value): string
{
    return (string) $value;
}
```

This is useful when broad input is truly intentional.

It is clearer than pretending that a value has a narrow type when it does not.

## 7. `never`

Later PHP 8 releases provide `never` for functions that cannot complete normally.

Example:

```php
function abortRequest(string $message): never
{
    throw new RuntimeException($message);
}
```

This tells developers and tools that control flow will not return to the caller.

## 8. Enums replace string conventions

A common legacy pattern is:

```php
$status = 'OPEN';
```

Potential problems include:

```php
$status = 'open';
$status = 'OPENED';
$status = 'OPNE';
```

If the database and business logic expect only a small fixed set, PHP 8.1 enums can make the model safer:

```php
enum TicketStatus: string
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';
    case Pending = 'PENDING';
}
```

Usage:

```php
$ticket->setStatus(TicketStatus::Open);
```

Now arbitrary strings are not accepted accidentally.

## 9. Readonly state

Modern PHP can express state that should not change after construction.

```php
final class UserId
{
    public function __construct(
        public readonly int $value
    ) {
    }
}
```

This communicates an important design rule:

> Once the identifier exists, it cannot be replaced.

In PHP 5-era applications, the same rule often exists only as convention.

## 10. Constructor property promotion

Legacy class:

```php
class Product
{
    private int $id;
    private string $name;
    private float $price;

    public function __construct(
        int $id,
        string $name,
        float $price
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }
}
```

PHP 8:

```php
class Product
{
    public function __construct(
        private int $id,
        private string $name,
        private float $price
    ) {
    }
}
```

This reduces boilerplate without changing the core object model.

## 11. Modernization example: one class across three versions

### PHP 5.6

```php
class Ticket
{
    private $id;
    private $subject;
    private $status;

    public function __construct($id, $subject, $status)
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->status = $status;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function isOpen()
    {
        return $this->status === 'open';
    }
}
```

### PHP 7.4

```php
class Ticket
{
    private int $id;
    private string $subject;
    private string $status;

    public function __construct(
        int $id,
        string $subject,
        string $status
    ) {
        $this->id = $id;
        $this->subject = $subject;
        $this->status = $status;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
```

### PHP 8.2

```php
enum TicketStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}

final class Ticket
{
    public function __construct(
        private readonly int $id,
        private string $subject,
        private TicketStatus $status
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::Open;
    }
}
```

The difference is not simply shorter syntax.

The PHP 8.2 version expresses more domain rules directly in the language:

```text
ID must be integer
ID cannot change
subject must be string
status must be a TicketStatus
isOpen always returns boolean
```

## 12. Do not over-modernize immediately

A legacy migration should not necessarily rewrite every model as an enum-rich, readonly, fully typed domain object on day one.

The safer process is:

```text
Compatibility
   ↓
Tests
   ↓
Behavior stabilization
   ↓
Type improvements
   ↓
Domain modernization
```

This allows teams to distinguish runtime incompatibilities from design refactoring.

## Key Takeaways

Modern PHP offers much stronger ways to describe program contracts.

The evolution can be summarized as:

```text
PHPDoc and conventions
       ↓
parameter / return types
       ↓
nullable types
       ↓
typed properties
       ↓
union and mixed types
       ↓
enums
       ↓
readonly state
       ↓
richer domain modeling
```

The goal is not to use every feature.

The goal is to make incorrect program states harder to represent.

---

## References and Source Documents

1. PHP Manual — PHP 7.0 New Features  
   https://www.php.net/manual/en/migration70.new-features.php
2. PHP Manual — PHP 7.1 New Features  
   https://www.php.net/manual/en/migration71.new-features.php
3. PHP Manual — PHP 7.4 New Features  
   https://www.php.net/manual/en/migration74.new-features.php
4. PHP Manual — PHP 8.0 New Features  
   https://www.php.net/manual/en/migration80.new-features.php
5. PHP Manual — PHP 8.1 New Features  
   https://www.php.net/manual/en/migration81.new-features.php
6. PHP Manual — PHP 8.2 New Features  
   https://www.php.net/manual/en/migration82.new-features.php
7. PHP Manual — Enumerations  
   https://www.php.net/manual/en/language.enumerations.php
8. PHP Manual — Object Properties  
   https://www.php.net/manual/en/language.oop5.properties.php

---

# Article 3 — Errors, Exceptions, Deprecations, and Backward Compatibility

The most dangerous part of a PHP migration is usually not new syntax.

It is old behavior.

An application may depend on an API that was removed, a comparison rule that changed, a warning that became an exception-like failure, or a framework technique that newer PHP versions discourage.

This article brings those issues together because they are all part of one migration question:

> What can make previously working PHP 5.6 code fail or behave differently on PHP 7.4 or PHP 8.2?

## 1. PHP 5-era error handling

Legacy applications often separate PHP errors and exceptions.

Example:

```php
set_error_handler('myErrorHandler');

try {
    $service->process();
} catch (Exception $e) {
    // handle exception
}
```

In PHP 5, many engine-level failures were not ordinary `Exception` objects.

This made it difficult to build one consistent application-level failure boundary.

## 2. PHP 7 and `Throwable`

PHP 7 introduced the `Throwable` interface.

Conceptually:

```text
Throwable
├── Exception
└── Error
```

This means application infrastructure can now handle unexpected exceptions and engine `Error` objects through one top-level interface.

Example:

```php
try {
    $service->process();
} catch (Throwable $e) {
    $logger->error(
        $e->getMessage(),
        ['exception' => $e]
    );

    throw $e;
}
```

This is especially useful in:

- front controllers;
- API error middleware;
- CLI command boundaries;
- job workers;
- logging infrastructure.

But it does not mean every method should contain:

```php
catch (Throwable $e)
```

Catching too broadly can hide bugs.

## 3. Expected vs unexpected exceptions

Expected business failure:

```php
try {
    $orderService->cancel($orderId);
} catch (OrderAlreadyShippedException $e) {
    return [
        'success' => false,
        'message' => 'The order has already shipped.'
    ];
}
```

Unexpected failure:

```php
try {
    $orderService->cancel($orderId);
} catch (Throwable $e) {
    // log at application boundary
    // return generic error response
}
```

The first can be handled meaningfully.

The second should usually be logged and translated into a safe client response.

## 4. API error architecture

Older JavaScript-heavy applications often force every page to interpret many different server errors.

A better pattern is:

```text
Browser
   ↓
Controller/API endpoint
   ↓
Service
   ↓
Repository/Database
   ↓
Exception
   ↓
Central error handler
   ↓
Structured JSON response
```

For example:

```json
{
    "success": false,
    "error": {
        "code": "AUTH_EXPIRED",
        "message": "Your session has expired."
    }
}
```

The server keeps technical details in the log rather than exposing:

```text
SQL query
filesystem path
stack trace
database credentials
internal class names
```

## 5. Removed database APIs

One of the best-known PHP 5 → PHP 7 compatibility problems is the old `mysql` extension.

Legacy:

```php
$link = mysql_connect($host, $user, $password);
mysql_select_db($database, $link);

$result = mysql_query(
    'SELECT * FROM users',
    $link
);
```

The old extension was removed in PHP 7.

Migration requires a supported API such as MySQLi or PDO.

PDO example:

```php
$pdo = new PDO(
    $dsn,
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
);

$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE id = ?'
);

$stmt->execute([$id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

This should not be treated as a simple function rename.

The migration should review:

- character set handling;
- escaping;
- prepared statements;
- transaction behavior;
- error handling;
- result formats;
- reconnect behavior.

## 6. Loose comparison changes in PHP 8

Legacy PHP applications sometimes rely heavily on loose comparison:

```php
if ($value == 0) {
    // ...
}
```

PHP 8 changed behavior for some number/string comparisons.

This means compatibility testing must include business logic, not just checking whether files parse.

A migration test suite should specifically exercise:

```text
"0"
0
""
null
false
"10"
"10abc"
non-numeric strings
```

where the application depends on loose comparisons.

Where practical, explicit comparison is clearer:

```php
if ($value === 0) {
    // exact integer zero
}
```

or explicit normalization:

```php
$id = (int) $value;
```

but conversion rules must match the application's intended behavior.

## 7. Dynamic properties in PHP 8.2

Legacy PHP often relies on dynamic object properties:

```php
class User
{
}

$user = new User();
$user->name = 'Alice';
```

PHP 8.2 deprecates dynamic property creation in many ordinary classes.

This matters greatly for old frameworks and home-grown hydration systems.

Before changing code, identify why the dynamic property exists.

Possible reasons:

```text
intentional property bag
ORM hydration
framework magic
plugin metadata
temporary template data
programming mistake
```

Example of an accidental property:

```php
$user->naem = 'Alice';
```

Older PHP can silently create `$naem`.

A stricter model makes this mistake easier to discover.

## 8. `#[AllowDynamicProperties]`

PHP 8.2 provides:

```php
#[AllowDynamicProperties]
class LegacyDataObject
{
}
```

This can be appropriate when the dynamic nature is intentional.

But applying the attribute to every class simply to silence deprecations defeats the purpose of the migration.

Treat it as a compatibility tool, not an automatic modernization strategy.

## 9. Deprecation messages are valuable

During migration, developers sometimes react to a large number of deprecation warnings by disabling them.

That may make logs quieter, but it removes useful information.

A better process is:

```text
Collect
  ↓
Deduplicate
  ↓
Classify
  ↓
Prioritize
  ↓
Fix
  ↓
Retest
```

Categories might include:

```text
Application code
Framework
Third-party library
Custom PHP extension
Runtime configuration
Database API
```

## 10. Build a compatibility register

For a large legacy system, keep a structured migration register.

Example:

| Component | Issue | Affected version | Replacement | Risk | Test |
|---|---|---|---|---|---|
| User model | dynamic properties | PHP 8.2 | declare properties | medium | hydration tests |
| DB helper | `mysql_*` API | PHP 7 | PDO/MySQLi | high | CRUD + transactions |
| Comparison logic | loose numeric/string comparison | PHP 8 | explicit normalization | high | validation suite |
| Error handler | catches only `Exception` | PHP 7+ | review `Throwable` boundary | medium | error-path tests |

This makes migration progress visible and auditable.

## 11. Read every intermediate migration guide

A common mistake is:

> “We are going directly from PHP 5.6 to PHP 8.2, so I only need the PHP 8.2 migration guide.”

That is incorrect.

The PHP 8.2 migration guide focuses primarily on differences from PHP 8.1.

You still need the changes introduced in:

```text
7.0
7.1
7.2
7.3
7.4
8.0
8.1
8.2
```

A production migration crosses all of them logically.

## Key Takeaways

Backward compatibility is the central risk of a large PHP upgrade.

Pay particular attention to:

- removed extensions and APIs;
- deprecated behavior;
- comparison semantics;
- dynamic properties;
- error handling;
- third-party dependencies;
- assumptions hidden by weak typing.

A migration is successful when behavior is verified, not merely when the application starts.

---

## References and Source Documents

1. PHP Manual — Errors in PHP 7  
   https://www.php.net/manual/en/language.errors.php7.php
2. PHP Manual — Throwable  
   https://www.php.net/manual/en/class.throwable.php
3. PHP Manual — Error  
   https://www.php.net/manual/en/class.error.php
4. PHP Manual — Exceptions  
   https://www.php.net/manual/en/language.exceptions.php
5. PHP Manual — PHP 7.0 Removed Extensions and SAPIs  
   https://www.php.net/manual/en/migration70.removed-exts-sapis.php
6. PHP Manual — PHP 8.0 Backward Incompatible Changes  
   https://www.php.net/manual/en/migration80.incompatible.php
7. PHP Manual — PHP 8.2 Deprecated Features  
   https://www.php.net/manual/en/migration82.deprecated.php
8. PHP Manual — Predefined Attributes  
   https://www.php.net/manual/en/reserved.attributes.php

---

# Article 4 — Performance, OPcache, Benchmarking, and Support Lifecycles

Performance is one of the most frequently discussed reasons for upgrading PHP, but it is also one of the easiest areas to describe poorly.

Statements such as:

> “PHP 8.2 is twice as fast as PHP 5.6.”

can be misleading without a workload, configuration, hardware environment, and measurement methodology.

The meaningful question is:

> “How does our application behave on each runtime under a realistic workload?”

## 1. Runtime performance is only one part of web performance

Consider a request:

```text
Browser
  ↓
Web server
  ↓
PHP application
  ↓
MariaDB
  ↓
Filesystem/cache
  ↓
External API
  ↓
Response
```

If PHP execution takes 20 ms but the database query takes 600 ms, changing PHP versions cannot remove the entire 600 ms database cost.

Similarly, if an endpoint spends most of its time waiting for:

- network storage;
- third-party APIs;
- SMTP;
- image processing;
- file I/O;

the PHP runtime is only one part of total latency.

## 2. Microbenchmarks can mislead

A simple benchmark:

```php
$start = microtime(true);

for ($i = 0; $i < 1000000; $i++) {
    $value = $i * 2;
}

echo microtime(true) - $start;
```

measures a small CPU loop.

It does not represent:

```text
framework bootstrap
routing
session startup
authentication
database queries
template rendering
JSON serialization
disk I/O
network calls
```

Microbenchmarks are useful for studying a narrow language operation, but they should not be presented as complete application performance.

## 3. Benchmark a real endpoint

A realistic benchmark might target:

```text
GET /tickets/list
```

where the endpoint includes:

```text
session check
authorization
MariaDB query
sorting/filtering
data transformation
JSON generation
```

Measure:

- requests per second;
- median response time;
- p95 latency;
- p99 latency;
- CPU utilization;
- memory usage;
- database execution time;
- error rate.

## 4. Keep the environment controlled

A meaningful runtime comparison should document:

```text
PHP exact version
Operating system
CPU
RAM
Web server
PHP-FPM/Apache settings
OPcache settings
Enabled extensions
Application revision
Database revision
Database data set
Concurrency
Warm-up procedure
Test duration
Number of repeated runs
```

If these variables change between tests, the benchmark no longer isolates the PHP runtime.

## 5. OPcache matters

Production PHP normally benefits greatly from OPcache.

Without OPcache, PHP may repeatedly parse and compile scripts.

With OPcache, compiled bytecode can be reused.

Therefore a benchmark comparing one runtime with OPcache enabled against another with OPcache disabled is not meaningful.

At minimum, record:

```ini
opcache.enable
opcache.memory_consumption
opcache.max_accelerated_files
opcache.validate_timestamps
```

along with any other relevant deployment settings.

## 6. Warm vs cold testing

A cold benchmark may include costs that are not representative of steady production traffic.

Warm-up can affect:

- OPcache;
- filesystem cache;
- application cache;
- database cache;
- connection pools.

A useful benchmark often reports whether the measurement represents:

```text
cold start
warm steady state
or both
```

## 7. Application modernization can contaminate comparison

Suppose the PHP 5.6 version uses:

```text
ZF1
old DB abstraction
old template layer
```

while the PHP 8.2 version uses:

```text
new framework
new database layer
different caching
different SQL
```

If PHP 8.2 is faster, the result cannot be attributed purely to the runtime.

That benchmark may still be useful, but it should be called:

> **whole-system migration benchmark**

rather than:

> **PHP engine benchmark**

## 8. Database bottlenecks still require database tuning

Imagine:

```text
PHP execution: 40 ms
MariaDB query: 900 ms
```

Upgrading PHP may improve the 40 ms portion.

The 900 ms query still needs database analysis.

This is why application profiling should separate:

```text
PHP CPU
database
network
disk
external services
```

before performance conclusions are drawn.

## 9. Memory behavior also matters

Throughput is not the only performance metric.

For PHP-FPM or Apache worker environments, memory usage affects how many concurrent worker processes the server can safely run.

If one application worker uses:

```text
100 MB
```

and the server has:

```text
8 GB available
```

the practical concurrency limit can be very different from an application using:

```text
40 MB per worker
```

Therefore include memory measurements in performance testing.

## 10. Support lifecycle is a performance and operations issue too

Unsupported PHP versions present more than security risk.

They can also create operational problems:

- modern libraries stop supporting them;
- package versions become frozen;
- OS packages disappear;
- debugging tools move on;
- engineers become less familiar with the runtime;
- newer security protocols may be harder to support.

As of October 2026:

```text
PHP 5.6 — unsupported
PHP 7.4 — unsupported
PHP 8.2 — security-fix support through 31 Dec 2026
```

Therefore PHP 7.4 may be useful as a migration milestone for some legacy systems, but it should not be treated as a current long-term production destination.

## 11. Security support should be rechecked before publication

Support dates change as time passes.

A publishing team should verify the current status immediately before publishing an article.

Do not permanently hard-code lifecycle advice without checking the official PHP support pages.

## 12. A responsible benchmark statement

Good:

> On our test server, using the same application revision, MariaDB data set, OPcache configuration, and concurrency level, PHP 8.2 produced lower median and p95 response times than PHP 7.4.

Weak:

> PHP 8.2 is always 50% faster.

The first statement is reproducible.

The second overgeneralizes.

## Key Takeaways

When comparing PHP performance:

```text
control the environment
        ↓
use realistic workload
        ↓
enable equivalent OPcache configuration
        ↓
measure latency + throughput + memory
        ↓
separate database/network time
        ↓
repeat the test
        ↓
publish methodology with results
```

The purpose of benchmarking is not to prove that a newer version is faster.

It is to understand the real application.

---

## References and Source Documents

1. PHP 5.6.0 Release Announcement  
   https://www.php.net/releases/5_6_0.php
2. PHP 7.4.0 Release Announcement  
   https://www.php.net/releases/7_4_0.php
3. PHP 8.2 Release Announcement  
   https://www.php.net/releases/8.2/en.php
4. PHP Manual — OPcache  
   https://www.php.net/manual/en/book.opcache.php
5. PHP — Supported Versions  
   https://www.php.net/supported-versions.php
6. PHP — Unsupported Branches  
   https://www.php.net/eol.php

---

# Article 5 — Migrating a Legacy PHP / Zend Framework 1 Application

A real PHP migration rarely starts with a small standalone script.

It often starts with a business system that has been running for years.

Consider a typical legacy stack:

```text
Browser
   ↓
DHTMLX / JavaScript
   ↓
Zend Framework 1
   ↓
PHP 5.6 or PHP 7.x
   ↓
MariaDB
   ↓
Filesystem / email / PDFs / APIs
```

Such a system may contain hundreds of controllers, models, forms, database queries, background jobs, and integrations.

The migration must therefore be treated as an engineering project rather than a language exercise.

## 1. Build an inventory first

Before changing PHP, record the environment.

### Runtime

```text
PHP version
Web server
Operating system
32-bit / 64-bit
PHP SAPI
php.ini differences
```

### Extensions

Examples:

```text
mysqli / PDO
mbstring
openssl
curl
gd / imagick
intl
xml
zip
custom extensions
```

### Framework and libraries

Record:

```text
Zend Framework 1 distribution
ZF1 patches/forks
manually bundled libraries
Composer packages
custom libraries
PDF libraries
mail libraries
authentication libraries
```

### Application features

List critical workflows:

```text
login
logout
sessions
CSRF
file upload
downloads
database CRUD
reports
email
PDF generation
images
background jobs
APIs
permissions
exports
```

## 2. Establish the current behavior

Before migration, the current application is your behavioral reference.

Capture:

- important HTTP responses;
- database changes;
- generated files;
- error cases;
- session behavior;
- authentication behavior;
- permissions.

If possible, build automated regression tests.

Even a modest suite is valuable.

Example:

```text
Login succeeds
Login fails with wrong password
Session survives navigation
Logout invalidates session
Ticket create/update/delete works
File upload works
Email queue works
Report generates
API rejects invalid token
```

## 3. Create a migration environment

Do not begin by replacing the production runtime.

Build a separate environment:

```text
production copy
      ↓
sanitized/test data
      ↓
new PHP runtime
      ↓
migration logging enabled
```

The migration environment should be as close to production as practical.

Differences in:

- web server;
- file permissions;
- extensions;
- timezone;
- locale;
- database;
- PHP configuration;

can hide problems.

## 4. Enable comprehensive error reporting in the migration environment

A safe test environment should expose problems clearly.

For example:

```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

This may be useful locally, but production should normally log errors rather than display technical details to users.

The migration environment should collect:

```text
deprecations
warnings
notices
fatal errors
uncaught throwables
```

## 5. Classify every failure

Do not fix issues randomly.

Classify them:

```text
A. removed PHP feature
B. changed PHP behavior
C. framework incompatibility
D. third-party library
E. PHP extension
F. configuration
G. application bug
H. operating-system difference
```

This helps reveal patterns.

For example, 200 warnings may all originate from one old library.

Fixing or replacing the library may remove most of them at once.

## 6. PHP 5.6 → PHP 7.x stage

Historically, this transition can expose:

- removed `mysql_*` functions;
- changed error handling;
- incompatibilities in older libraries;
- stricter handling in various APIs;
- framework assumptions.

The exact list depends on the application, so use the official migration guides rather than relying on a generic checklist alone.

## 7. PHP 7.4 → PHP 8.x stage

PHP 8 migration may expose:

- behavior changes in comparisons;
- stricter internal function handling;
- library compatibility issues;
- assumptions in framework code;
- dynamic property issues by PHP 8.2;
- custom extension incompatibility.

Again, review the 8.0, 8.1, and 8.2 migration guides separately.

## 8. Do not combine framework migration unless necessary

If the long-term plan is:

```text
ZF1 → Laminas MVC
```

it is tempting to combine:

```text
PHP upgrade
framework rewrite
database-layer rewrite
frontend modernization
```

into one project.

This dramatically increases diagnostic complexity.

When practical, separate them:

```text
Step 1
Stabilize tests

Step 2
Make legacy application compatible with newer PHP

Step 3
Deploy and observe

Step 4
Improve internal architecture

Step 5
Migrate ZF1 → Laminas
```

The exact sequence depends on library compatibility, but the principle remains useful: minimize simultaneous change.

## 9. Database behavior must be tested separately

A runtime upgrade can reveal database issues indirectly.

Check:

```text
connection charset
prepared statements
transaction handling
error modes
NULL handling
date conversion
numeric conversion
result formats
```

If the migration also changes the DB library, do not assume equivalent behavior without tests.

## 10. Session and authentication testing

Authentication failures are particularly dangerous because they may not appear in a simple page-render test.

Verify:

```text
session creation
session regeneration
logout
cookie flags
session timeout
multi-tab behavior
CSRF token behavior
remember-me mechanisms
API tokens
```

If the application uses custom session storage, test that storage directly.

## 11. File-processing workflows

Legacy business systems often rely on:

```text
uploads
temporary files
PDF generation
image processing
CSV/Excel export
ZIP archives
filesystem permissions
```

A new PHP version, OS package, or extension build can change these workflows even if the application code is unchanged.

## 12. Email and TLS

Mail libraries may depend on:

- OpenSSL;
- SMTP TLS behavior;
- certificate validation;
- authentication mechanisms.

A runtime upgrade should include actual test messages against the intended mail infrastructure.

## 13. CLI and cron jobs

Web testing is not enough.

Also test:

```text
cron scripts
queue workers
maintenance scripts
imports
exports
backup helpers
scheduled reports
```

CLI may load a different `php.ini` than the web server.

That difference alone can create difficult migration bugs.

## 14. Custom PHP extensions

If the system uses a compiled custom PHP extension, it requires special attention.

A PHP extension built for one major/minor runtime is not automatically compatible with another.

The migration plan should include:

```text
source availability
compiler/toolchain
extension API compatibility
build process
test suite
deployment package
rollback package
```

This can become one of the most critical path items.

## 15. Production rollout

A migration should have:

```text
backup
deployment plan
rollback plan
monitoring
log review
health checks
database validation
user acceptance testing
```

Do not define success as:

> “Apache starts.”

Define success as:

> “Critical business workflows behave correctly under the new runtime.”

## 16. Post-deployment observation

After migration, monitor:

- application errors;
- PHP-FPM worker crashes;
- memory usage;
- CPU;
- slow requests;
- database load;
- session failures;
- email failures;
- background-job failures.

Some problems appear only under real concurrency.

## Key Takeaways

A legacy PHP migration is best handled as a controlled sequence:

```text
Inventory
   ↓
Baseline behavior
   ↓
Tests
   ↓
Migration environment
   ↓
Compatibility fixes
   ↓
Integration testing
   ↓
Performance testing
   ↓
Production rollout
   ↓
Observation
   ↓
Modernization
```

Do not let a language upgrade become an uncontrolled rewrite.

---

## References and Source Documents

1. PHP Manual — Migrating from PHP 5.6.x to PHP 7.0.x  
   https://www.php.net/manual/en/migration70.php
2. PHP Manual — Migrating from PHP 7.0.x to PHP 7.1.x  
   https://www.php.net/manual/en/migration71.php
3. PHP Manual — Migrating from PHP 7.1.x to PHP 7.2.x  
   https://www.php.net/manual/en/migration72.php
4. PHP Manual — Migrating from PHP 7.2.x to PHP 7.3.x  
   https://www.php.net/manual/en/migration73.php
5. PHP Manual — Migrating from PHP 7.3.x to PHP 7.4.x  
   https://www.php.net/manual/en/migration74.php
6. PHP Manual — Migrating from PHP 7.4.x to PHP 8.0.x  
   https://www.php.net/manual/en/migration80.php
7. PHP Manual — Migrating from PHP 8.0.x to PHP 8.1.x  
   https://www.php.net/manual/en/migration81.php
8. PHP Manual — Migrating from PHP 8.1.x to PHP 8.2.x  
   https://www.php.net/manual/en/migration82.php

---

# Article 6 — Complete Migration Case Study: PHP 5.6 → PHP 7.4 → PHP 8.2

This final article combines the previous topics into one realistic example.

Assume a legacy PHP service used by a Zend Framework 1 application.

## 1. Original PHP 5.6 service

```php
class UserService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function findUser($id)
    {
        if (!$id) {
            throw new Exception('Invalid user id');
        }

        $sql = 'SELECT * FROM users WHERE id = ?';

        return $this->db->fetchRow(
            $sql,
            array($id)
        );
    }
}
```

At first glance the code is simple.

But before changing it, we need to understand its hidden contract.

Questions:

```text
What types are accepted for $id?
Is "0" valid?
Is 0 valid?
What does fetchRow return if no record exists?
Does it return false, null, or an array?
What DB adapter is used?
Which exception do callers catch?
Do callers depend on the exact result array?
```

Without those answers, modernization is guessing.

## 2. Add behavioral tests first

Suppose investigation shows:

```text
positive integer/string IDs are accepted
zero is invalid
missing record returns false
invalid ID throws Exception
successful lookup returns associative array
```

Write tests describing that behavior.

Example pseudo-PHPUnit:

```php
public function testFindUserReturnsArray()
{
    $result = $service->findUser(10);

    $this->assertIsArray($result);
}

public function testFindUserRejectsZero()
{
    $this->expectException(Exception::class);

    $service->findUser(0);
}
```

The exact PHPUnit version depends on the runtime, but the principle is the same.

## 3. First migration goal: compatibility

The first goal should not be:

> Rewrite the entire service using PHP 8 syntax.

The first goal is:

> Make the existing application behavior work reliably on the newer runtime.

This may mean temporarily keeping code structurally similar while replacing incompatible APIs.

## 4. PHP 7.4-compatible version

After confirming the contract, we may decide the service should accept integers and return either an array or `null`.

```php
class UserService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function findUser(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Invalid user id'
            );
        }

        $sql = 'SELECT * FROM users WHERE id = ?';

        $row = $this->db->fetchRow(
            $sql,
            [$id]
        );

        return $row ?: null;
    }
}
```

This version is more explicit, but notice something important:

```php
return $row ?: null;
```

changes the old contract if callers expected `false`.

That change is only safe if:

- callers are updated;
- tests verify the new contract;
- the application intentionally adopts `null`.

This illustrates why adding types can reveal design decisions that were previously hidden.

## 5. Add an explicit repository

A later refactoring might separate database access:

```php
class UserRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchRow(
            'SELECT * FROM users WHERE id = ?',
            [$id]
        );

        return $row ?: null;
    }
}
```

Service:

```php
class UserService
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function findUser(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Invalid user id'
            );
        }

        return $this->users->findById($id);
    }
}
```

Now business validation and persistence are separated.

## 6. PHP 8.2 domain model

After runtime compatibility is stable, the application may introduce an explicit domain object:

```php
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email
    ) {
    }
}
```

Repository:

```php
final class UserRepository
{
    public function __construct(
        private DatabaseAdapter $db
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetchRow(
            'SELECT id, username, email
             FROM users
             WHERE id = ?',
            [$id]
        );

        if (!$row) {
            return null;
        }

        return new User(
            (int) $row['id'],
            $row['username'],
            $row['email']
        );
    }
}
```

Service:

```php
final class UserService
{
    public function __construct(
        private UserRepository $users
    ) {
    }

    public function findUser(int $id): ?User
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Invalid user id'
            );
        }

        return $this->users->findById($id);
    }
}
```

Now the application has a much clearer contract:

```text
findUser()
accepts: positive integer
returns: User or null
throws: InvalidArgumentException for invalid IDs
```

## 7. Centralized error handling

Suppose a controller previously contained:

```php
try {
    $user = $service->findUser($id);
} catch (Exception $e) {
    echo $e->getMessage();
}
```

Problems:

- internal error messages may leak;
- response formats vary;
- logging may be inconsistent;
- engine `Error` objects may not be handled by the same path.

A modern application boundary can distinguish expected and unexpected failures.

Conceptually:

```php
try {
    $response = $controller->dispatch();
} catch (InvalidArgumentException $e) {
    // controlled client error
} catch (Throwable $e) {
    // log technical detail
    // return generic server error
}
```

For JSON:

```json
{
    "success": false,
    "error": {
        "code": "INVALID_ARGUMENT",
        "message": "The requested user ID is invalid."
    }
}
```

The detailed stack trace remains in the server log.

## 8. Dynamic property discovery

During PHP 8.2 testing, suppose the application reports:

```text
Creation of dynamic property Legacy_User::$department is deprecated
```

Do not immediately suppress it.

Investigate the class.

Maybe:

```php
class Legacy_User
{
    public $id;
    public $name;
}
```

and later:

```php
$user->department = $row['department'];
```

Possible fixes include explicitly declaring:

```php
public $department;
```

or redesigning hydration.

If the class is intentionally a dynamic property bag, `#[AllowDynamicProperties]` may be a temporary or intentional compatibility measure.

## 9. Database migration issue

Suppose another module still uses:

```php
mysql_query($sql);
```

That module cannot simply be ignored during the PHP 7+ migration.

A migration plan should identify all usage and replace the entire old database access layer or isolate compatibility changes carefully.

Prepared statements should be used where appropriate:

```php
$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE email = ?'
);

$stmt->execute([$email]);
```

This is both a compatibility and security improvement.

## 10. Comparison regression

Suppose legacy validation contains:

```php
if ($value == 0) {
    return false;
}
```

Tests should include:

```text
0
"0"
""
null
false
"abc"
```

because a runtime migration may change loose-comparison outcomes.

If the intended rule is:

> Reject only integer zero after normalization

then make normalization explicit.

If the intended rule is:

> Reject any empty value

then express that rule explicitly instead.

The goal is not to preserve accidental behavior forever.

The goal is to identify and intentionally define the business rule.

## 11. Performance benchmark

After compatibility is complete, compare the application under controlled conditions.

Example test:

```text
Endpoint:
/api/users/list

Data:
500,000 users

Concurrency:
20

Duration:
5 minutes

Same:
- database
- application revision
- server hardware
- web server configuration
- OPcache policy
```

Measure:

```text
median
p95
p99
requests/sec
CPU
memory
DB time
error rate
```

Do not publish only the fastest single request.

## 12. Staged rollout strategy

A realistic enterprise migration might be:

### Stage A — Preparation

```text
Inventory dependencies
Create tests
Capture baseline metrics
Prepare rollback
```

### Stage B — Compatibility environment

```text
Install target runtime
Install required extensions
Run application
Collect errors/deprecations
```

### Stage C — Fix high-risk incompatibilities

```text
database API
framework errors
authentication
sessions
custom extensions
file processing
```

### Stage D — Regression testing

```text
business workflows
API
reports
imports/exports
cron
email
```

### Stage E — Load/performance testing

```text
representative traffic
database load
memory
worker stability
```

### Stage F — Production deployment

```text
backup
maintenance/change window
deployment
health checks
monitoring
```

### Stage G — Modernization

After stability:

```text
typed properties
service/repository separation
enums
readonly state
better exception hierarchy
framework migration
```

## 13. Why PHP 7.4 may appear in a migration plan

Some legacy teams use PHP 7.4 as an intermediate compatibility milestone because it represents the mature end of the PHP 7 generation.

However, PHP 7.4 is unsupported.

Therefore distinguish:

```text
migration/testing milestone
```

from:

```text
recommended current production destination
```

They are not the same.

## 14. Final production checklist

Before final migration:

```text
[ ] Framework compatibility verified
[ ] Third-party libraries reviewed
[ ] PHP extensions installed/tested
[ ] Custom extensions rebuilt/tested
[ ] Deprecated/removed APIs addressed
[ ] Error handling reviewed
[ ] Sessions tested
[ ] Authentication tested
[ ] CSRF tested
[ ] File uploads tested
[ ] PDF/image processing tested
[ ] Email tested
[ ] Database transactions tested
[ ] CLI/cron tested
[ ] API integrations tested
[ ] Performance benchmarked
[ ] Memory usage checked
[ ] Logs clean enough for production
[ ] Backup complete
[ ] Rollback tested
[ ] Monitoring enabled
```

## 15. Final lesson

The migration from PHP 5.6 through PHP 7.4 to PHP 8.2 is not primarily about replacing old syntax with new syntax.

It is about making hidden assumptions explicit.

PHP 5.6 applications often contain rules such as:

```text
"this value is probably an integer"
"this property should exist"
"this function usually returns an array"
"this status should be one of three strings"
```

Modern PHP gives developers better tools to express those rules directly.

The safest modernization sequence is:

```text
Understand
   ↓
Test
   ↓
Migrate
   ↓
Verify
   ↓
Measure
   ↓
Modernize
```

That order reduces risk while still allowing a legacy application to benefit from modern PHP design.

---

## References and Source Documents

1. PHP Manual — PHP 5.6 Migration Guide  
   https://www.php.net/manual/en/migration56.php
2. PHP Manual — PHP 7.0 Migration Guide  
   https://www.php.net/manual/en/migration70.php
3. PHP Manual — PHP 7.1 Migration Guide  
   https://www.php.net/manual/en/migration71.php
4. PHP Manual — PHP 7.2 Migration Guide  
   https://www.php.net/manual/en/migration72.php
5. PHP Manual — PHP 7.3 Migration Guide  
   https://www.php.net/manual/en/migration73.php
6. PHP Manual — PHP 7.4 Migration Guide  
   https://www.php.net/manual/en/migration74.php
7. PHP Manual — PHP 8.0 Migration Guide  
   https://www.php.net/manual/en/migration80.php
8. PHP Manual — PHP 8.1 Migration Guide  
   https://www.php.net/manual/en/migration81.php
9. PHP Manual — PHP 8.2 Migration Guide  
   https://www.php.net/manual/en/migration82.php
10. PHP Manual — Throwable  
    https://www.php.net/manual/en/class.throwable.php
11. PHP Manual — Enumerations  
    https://www.php.net/manual/en/language.enumerations.php
12. PHP — Supported Versions  
    https://www.php.net/supported-versions.php
13. PHP — Unsupported Branches  
    https://www.php.net/eol.php

---

# Appendix A — Recommended Reading Order for a PHP 5.6 → PHP 8.2 Migration

Read the official migration documentation in this order:

```text
PHP 5.6
  ↓
PHP 7.0
  ↓
PHP 7.1
  ↓
PHP 7.2
  ↓
PHP 7.3
  ↓
PHP 7.4
  ↓
PHP 8.0
  ↓
PHP 8.1
  ↓
PHP 8.2
```

Do not read only the PHP 8.2 migration guide.

It documents changes from PHP 8.1 to PHP 8.2 rather than every compatibility change since PHP 5.6.

---

# Appendix B — Editorial Checklist for Publishing This Series

Before publication:

```text
[ ] Verify all version-specific claims against php.net
[ ] Recheck current PHP support status
[ ] Test code examples on the claimed version
[ ] Distinguish migration compatibility from modernization
[ ] Do not present microbenchmarks as universal performance results
[ ] Document benchmark hardware/configuration/workload
[ ] Include framework and extension compatibility
[ ] Explain behavior changes, not only syntax changes
[ ] Do not recommend suppressing deprecations as the main solution
[ ] Include rollback guidance for production migration
[ ] Review security/support wording immediately before publication
```

---

# Master Reference List

- PHP Manual  
  https://www.php.net/manual/en/

- PHP 5.6 Migration Guide  
  https://www.php.net/manual/en/migration56.php

- PHP 7.0 Migration Guide  
  https://www.php.net/manual/en/migration70.php

- PHP 7.1 Migration Guide  
  https://www.php.net/manual/en/migration71.php

- PHP 7.2 Migration Guide  
  https://www.php.net/manual/en/migration72.php

- PHP 7.3 Migration Guide  
  https://www.php.net/manual/en/migration73.php

- PHP 7.4 Migration Guide  
  https://www.php.net/manual/en/migration74.php

- PHP 8.0 Migration Guide  
  https://www.php.net/manual/en/migration80.php

- PHP 8.1 Migration Guide  
  https://www.php.net/manual/en/migration81.php

- PHP 8.2 Migration Guide  
  https://www.php.net/manual/en/migration82.php

- PHP Supported Versions  
  https://www.php.net/supported-versions.php

- PHP Unsupported Branches  
  https://www.php.net/eol.php
