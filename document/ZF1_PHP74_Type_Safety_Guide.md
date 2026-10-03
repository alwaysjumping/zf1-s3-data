# Adding Type Safety to an Existing Zend Framework 1 Project

## 1. Project Context

The existing application uses:

-   Zend Framework 1 (ZF1)
-   PHP 7.4
-   MariaDB
-   DHTMLX 3.5 for parts of the user interface
-   The same ZF1-based system is deployed for several child/subsidiary
    companies.

The existing PHP code was largely developed without explicit type
declarations. For example:

``` php
public function getUser($id)
{
    return $this->userTable->find($id);
}
```

The goal is **not to redesign the application** simply to use modern PHP
typing.

The main goal is:

> Add useful PHP type declarations gradually to reduce development
> errors and make the existing code safer and easier to maintain.

------------------------------------------------------------------------

## 2. Why Add Types?

Without type declarations, PHP allows many incorrect values to pass
between methods.

For example:

``` php
public function updateUser($id, $data)
{
    // Update user
}
```

A developer could accidentally reverse the arguments:

``` php
$this->updateUser($data, $id);
```

Depending on the implementation, the error might not be discovered until
much later.

Adding types makes the contract clearer:

``` php
public function updateUser(int $id, array $data): bool
{
    // Update user

    return true;
}
```

Now incorrect usage is easier for PHP, IDEs, tests, and static-analysis
tools to detect.

Types are particularly useful at boundaries such as:

``` text
Controller
    ↓
Service
    ↓
Model / Repository
    ↓
Database
```

They also document what each method expects and returns.

------------------------------------------------------------------------

## 3. PHP 7.4 Type Features We Can Use

Because the project runs on PHP 7.4, all changes must remain compatible
with PHP 7.4.

### 3.1 Parameter Types

Old:

``` php
public function getUser($userId)
{
}
```

Improved:

``` php
public function getUser(int $userId)
{
}
```

Other examples:

``` php
public function setUsername(string $username)
{
}

public function setEnabled(bool $enabled)
{
}

public function calculateTotal(float $amount)
{
}

public function saveUsers(array $users)
{
}
```

------------------------------------------------------------------------

## 4. Return Types

Old:

``` php
public function isActive($userId)
{
    return true;
}
```

Improved:

``` php
public function isActive(int $userId): bool
{
    return true;
}
```

Another example:

``` php
public function getUsername(int $userId): string
{
    return 'John';
}
```

Array return:

``` php
public function getUsers(): array
{
    return array();
}
```

------------------------------------------------------------------------

## 5. Typed Properties

PHP 7.4 supports typed class properties.

Old:

``` php
class UserService
{
    private $userId;
    private $username;
}
```

Improved:

``` php
class UserService
{
    private int $userId;
    private string $username;
}
```

Object dependencies can also be typed:

``` php
class UserService
{
    private UserTable $userTable;
}
```

Be careful with initialization. A typed property must have a valid value
before it is read.

------------------------------------------------------------------------

## 6. Nullable Types

Sometimes a value legitimately may be `null`.

For example:

``` php
public function findUser(int $id): ?array
{
    $user = $this->findUserInDatabase($id);

    if (!$user) {
        return null;
    }

    return $user;
}
```

`?array` means:

``` text
array OR null
```

Another example:

``` php
public function getParent(): ?User
{
    return $this->parent;
}
```

------------------------------------------------------------------------

## 7. Class and Interface Types

Types are especially valuable for object dependencies.

Instead of:

``` php
public function save($user)
{
}
```

use:

``` php
public function save(User $user): bool
{
    return true;
}
```

Interfaces are also useful:

``` php
public function send(NotificationInterface $notification): bool
{
    return true;
}
```

This prevents unrelated objects from being passed accidentally and makes
the intended API clearer.

------------------------------------------------------------------------

## 8. Array Types

For existing ZF1 applications, arrays are common.

Old:

``` php
public function saveUser($data)
{
}
```

Improved:

``` php
public function saveUser(array $data): bool
{
    return true;
}
```

This guarantees that callers provide an array, although it does not
guarantee the structure inside the array.

PHPDoc can document that structure:

``` php
/**
 * @param array $data User data.
 * @return bool
 */
public function saveUser(array $data): bool
{
    return true;
}
```

More detailed PHPDoc can help IDEs and static analyzers:

``` php
/**
 * @param array{
 *     username:string,
 *     email:string,
 *     enabled:bool
 * } $data
 */
public function saveUser(array $data): bool
{
    return true;
}
```

The PHPDoc syntax does not change PHP's runtime behavior, but analysis
tools can use it.

------------------------------------------------------------------------

## 9. PHP Does Not Type Local Variables This Way

Do not try to write:

``` php
string $name = 'John';
int $age = 30;
```

PHP 7.4 does not support local-variable type declarations using that
syntax.

Instead:

``` php
$name = 'John';
$age = 30;
```

Focus type declarations on:

-   Function parameters
-   Method parameters
-   Return values
-   Class properties
-   Class dependencies
-   Interfaces

This gives much of the benefit without trying to type every local
variable.

------------------------------------------------------------------------

## 10. Example: Existing ZF1 Service

An older service might look like:

``` php
class UserService
{
    private $userTable;

    public function getUser($id)
    {
        return $this->userTable->find($id);
    }

    public function saveUser($data)
    {
        // Save user

        return true;
    }
}
```

A gradual PHP 7.4 improvement could be:

``` php
class UserService
{
    private UserTable $userTable;

    public function __construct(UserTable $userTable)
    {
        $this->userTable = $userTable;
    }

    public function getUser(int $id): ?array
    {
        $user = $this->userTable->find($id);

        if (!$user) {
            return null;
        }

        return $user;
    }

    public function saveUser(array $data): bool
    {
        // Save user

        return true;
    }
}
```

This immediately makes the service API easier to understand.

------------------------------------------------------------------------

## 11. Example: Controller → Service

A controller may currently contain:

``` php
class UserController extends Zend_Controller_Action
{
    public function editAction()
    {
        $id = $this->_getParam('id');

        $data = $this->getRequest()->getPost();

        $this->userService->updateUser($id, $data);
    }
}
```

There is an important issue here.

Request parameters are frequently strings.

For example:

``` php
$id = $this->_getParam('id');
```

could produce:

``` text
"123"
```

instead of integer:

``` text
123
```

Therefore, validate and normalize data at the application boundary:

``` php
$id = (int) $this->_getParam('id');

$data = $this->getRequest()->getPost();

$result = $this->userService->updateUser(
    $id,
    $data
);
```

The service can then use a stronger contract:

``` php
public function updateUser(int $id, array $data): bool
{
    // ...

    return true;
}
```

This is an important principle:

> Validate and normalize external data at the boundary, then use
> stronger types inside the application.

External data includes:

-   GET parameters
-   POST data
-   JSON/API requests
-   Database values
-   Configuration values
-   Files
-   Command-line input
-   Data received from another company/system

------------------------------------------------------------------------

## 12. Database Values Need Special Attention

MariaDB/database drivers may return some values as strings even when the
database column represents a number.

For example:

``` php
$row['user_id']
```

might contain:

``` text
"100"
```

If a service requires:

``` php
public function getUser(int $id): ?array
```

normalize the value before calling it:

``` php
$userId = (int) $row['user_id'];

$user = $userService->getUser($userId);
```

Do not blindly add types without understanding what existing database
code actually returns.

------------------------------------------------------------------------

## 13. PHP 7.4 Limitations

Do not introduce PHP 8-only syntax into this project.

For example, this is **not PHP 7.4 compatible**:

``` php
public function find(int|string $id)
{
}
```

Union types were introduced after PHP 7.4.

For PHP 7.4, you may need PHPDoc when multiple types are genuinely
accepted:

``` php
/**
 * @param int|string $id
 */
public function find($id)
{
}
```

Other PHP 8 features should likewise be avoided while PHP 7.4
compatibility is required.

------------------------------------------------------------------------

## 14. Do Not Enable Strict Types Everywhere Immediately

PHP supports:

``` php
declare(strict_types=1);
```

Strict typing can be useful, but enabling it throughout a large legacy
ZF1 project immediately can expose many compatibility problems.

For example, old application code may frequently pass:

``` text
"123"
```

to methods that conceptually expect:

``` text
123
```

A safer migration strategy is:

``` text
Existing application
        ↓
Add useful type declarations
        ↓
Fix discovered mismatches
        ↓
Add tests
        ↓
Add static analysis
        ↓
Stabilize the code
        ↓
Consider strict_types for selected/new files
        ↓
Gradually expand strict typing if appropriate
```

Do not begin by automatically inserting:

``` php
declare(strict_types=1);
```

into every PHP file.

------------------------------------------------------------------------

## 15. Use PHPDoc When Runtime Types Are Not Yet Safe

Some legacy methods may be difficult to type immediately.

Example:

``` php
public function getData($id)
{
}
```

If changing its signature could break existing callers, start with
documentation:

``` php
/**
 * @param int $id
 * @return array|null
 */
public function getData($id)
{
}
```

After verifying all callers, it can later become:

``` php
public function getData(int $id): ?array
{
}
```

Therefore, PHPDoc can be used as an intermediate migration step.

------------------------------------------------------------------------

## 16. Static Analysis

Runtime types catch problems when code executes.

Static analysis can detect many problems before execution.

For example:

``` php
public function updateUser(int $id, array $data): bool
{
    return true;
}
```

Incorrect code:

``` php
$data = array(
    'name' => 'John'
);

$id = 10;

$this->updateUser($data, $id);
```

A capable IDE or static analyzer can detect that:

``` text
Argument #1 should be int
but array was provided.

Argument #2 should be array
but int was provided.
```

For a legacy ZF1 project, PHPStan can be introduced gradually. Start at
a permissive level and increase the analysis level as problems are
fixed.

Static analysis is especially valuable because it can inspect code paths
that unit tests may not execute.

------------------------------------------------------------------------

## 17. Unit Tests and Types Work Together

Types should not replace unit tests.

Types verify things such as:

``` text
getUser() requires an integer.
saveUser() requires an array.
isEnabled() returns a boolean.
```

Unit tests verify behavior:

``` text
Does getUser(100) return the correct user?
Does updateUser() actually update MariaDB?
Does invalid input get rejected?
Does deleting a nonexistent record behave correctly?
```

The combination is stronger:

``` text
Type Declarations
        +
Static Analysis
        +
Unit Tests
        =
Safer Legacy Application
```

------------------------------------------------------------------------

## 18. Recommended Migration Strategy

Do **not** convert the entire ZF1 application in one operation.

Use an incremental approach.

### Phase 1 --- New Code

Require useful type declarations for new service/domain code where PHP
7.4 supports them.

Example:

``` php
public function createUser(array $data): int
{
}
```

### Phase 2 --- Stable Service Classes

Start adding types to stable service classes.

Before:

``` php
public function deleteUser($id)
{
}
```

After:

``` php
public function deleteUser(int $id): bool
{
}
```

Service methods are a good starting point because they form contracts
between application layers.

### Phase 3 --- Model/Repository Layer

Add types around database-access methods where their behavior is well
understood.

Example:

``` php
public function findById(int $id): ?array
{
}
```

### Phase 4 --- Controllers and Input Boundaries

Controllers receive loosely typed external data.

Validate and normalize it before passing it into typed services.

Example:

``` php
$id = (int) $this->_getParam('id');

$this->userService->deleteUser($id);
```

### Phase 5 --- Typed Properties

Once initialization behavior is clear, gradually type appropriate
properties.

``` php
private UserService $userService;
```

### Phase 6 --- Static Analysis

Run PHPStan or another compatible analyzer and fix errors gradually.

### Phase 7 --- Unit Tests

Increase unit-test coverage around services, database operations,
modules, and business-critical behavior.

### Phase 8 --- Strict Types

Only after the application is better understood and tested should
`strict_types` be evaluated for new or selected files.

------------------------------------------------------------------------

## 19. Deployment Across Subsidiary Companies

Because the same application runs for several subsidiary companies,
type-safety changes should be treated as shared application changes.

A recommended flow is:

``` text
Developer Environment
        ↓
Add Types
        ↓
Static Analysis
        ↓
Unit Tests
        ↓
Integration Tests
        ↓
Test Database / Staging
        ↓
Test Company-Specific Configuration
        ↓
Pilot Deployment
        ↓
Verify Logs and Behavior
        ↓
Deploy to Other Subsidiaries
```

Do not assume that code behaving correctly for one subsidiary
automatically behaves identically for every subsidiary.

Differences may exist in:

-   Configuration
-   Enabled modules
-   Database schema/version
-   Existing data
-   Company-specific business rules
-   Permissions
-   Integration services

The shared code should therefore be tested together with each
deployment's relevant configuration.

------------------------------------------------------------------------

## 20. What Should Be Typed First?

A practical priority is:

### High Priority

``` text
Service method parameters
Service return values
Repository/model method parameters
Repository/model return values
Important class dependencies
Security-related functions
Module installation APIs
Module uninstallation APIs
API/service boundaries
```

### Medium Priority

``` text
Controller helper methods
Utility classes
Configuration-processing classes
Frequently reused internal classes
```

### Lower Priority

Avoid changing old, stable code merely to add types when the change
provides little benefit and carries significant regression risk.

The goal is not:

> Add a type everywhere.

The goal is:

> Add types where they prevent real mistakes and clarify important
> contracts.

------------------------------------------------------------------------

## 21. Example Recommended ZF1 Architecture

A useful direction for the existing project is:

``` text
HTTP Request
     │
     ▼
ZF1 Controller
     │
     │ Validate / normalize input
     ▼
Service Layer
     │
     │ Stronger parameter + return types
     ▼
Model / Repository
     │
     │ Database operations
     ▼
MariaDB
```

Example:

``` php
class UserController extends Zend_Controller_Action
{
    public function deleteAction()
    {
        $id = (int) $this->_getParam('id');

        $success = $this->userService->deleteUser($id);

        if (!$success) {
            // Handle failure
        }
    }
}
```

Service:

``` php
class UserService
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function deleteUser(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->repository->deleteById($id);
    }
}
```

Repository:

``` php
class UserRepository
{
    public function deleteById(int $id): bool
    {
        // Database operation

        return true;
    }
}
```

The controller deals with external input, while the internal layers use
clearer contracts.

------------------------------------------------------------------------

## 22. Suggested Coding Rule for the Project

A practical project rule could be:

> All new PHP code should use PHP 7.4-compatible parameter types, return
> types, and property types whenever the expected type is clear and
> stable. Existing code should be migrated incrementally after its
> callers and runtime behavior have been verified. External input must
> be validated and normalized before being passed to typed application
> services.

For example:

``` php
public function getUser(int $id): ?array
{
}
```

is preferred over:

``` php
public function getUser($id)
{
}
```

when the method contract is known.

------------------------------------------------------------------------

## 23. What Not to Do

Avoid a blind automated conversion such as:

``` text
Find every function
        ↓
Guess parameter types
        ↓
Insert types automatically
        ↓
Deploy
```

An automated tool cannot reliably know whether:

``` php
function process($value)
```

expects:

``` text
string
integer
array
object
string|null
integer|string
```

Guessing incorrectly can introduce new failures.

Automation is useful for **analysis and reporting**, but type
declarations should be based on actual method contracts.

------------------------------------------------------------------------

## 24. Recommended Long-Term Direction

For this ZF1/PHP 7.4 application, a sensible improvement path is:

``` text
Current ZF1 Code
      │
      ▼
PHPDoc Improvements
      │
      ▼
PHP 7.4 Type Declarations
      │
      ▼
Static Analysis
      │
      ▼
Unit Tests
      │
      ▼
Better Input Validation
      │
      ▼
Gradual strict_types Adoption
      │
      ▼
Safer and More Maintainable Legacy System
```

This approach improves reliability without requiring an immediate
framework rewrite.

------------------------------------------------------------------------

## 25. Final Recommendation

For this project, begin with **method boundaries**, especially service
classes.

Example:

``` php
public function updateUser(
    int $userId,
    array $data
): bool {
    // ...
}
```

Then gradually add:

``` text
1. Parameter types
2. Return types
3. Class/interface dependency types
4. Typed properties
5. PHPDoc for complex array structures
6. Static analysis
7. Unit tests
8. Strict typing where appropriate
```

Avoid trying to type every variable or automatically modify the entire
ZF1 codebase at once.

The objective is not to make an old application look like a new PHP
application. The objective is to introduce enough type information to
catch mistakes earlier, make contracts clearer, and reduce regression
risk while keeping the existing ZF1 system stable.
