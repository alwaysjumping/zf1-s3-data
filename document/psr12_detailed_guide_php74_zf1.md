# PSR-12 Detailed Guide

## Extended Coding Style for PHP

This guide explains **PSR-12 (Extended Coding Style)** in detail, with
practical examples for PHP 7.4 and legacy Zend Framework 1 projects.

> **Main idea:** PSR-12 defines how PHP source code should be formatted
> consistently. It does not define application architecture, security
> rules, or business logic.

------------------------------------------------------------------------

# 1. What Is PSR-12?

**PSR** means:

> PHP Standards Recommendation

PSR-12 is:

> **PSR-12 --- Extended Coding Style**

It defines common formatting rules so PHP written by different
developers has a consistent appearance.

The same logic could be written like this:

``` php
if($userId>0){
$user=$this->getUser($userId);
}
```

or:

``` php
if ($userId > 0)
{
    $user = $this->getUser($userId);
}
```

PSR-12 standardizes the style:

``` php
if ($userId > 0) {
    $user = $this->getUser($userId);
}
```

PSR-12 deals with areas such as:

``` text
PSR-12
   │
   ├── File structure
   ├── Indentation
   ├── Whitespace
   ├── Classes
   ├── Interfaces
   ├── Traits
   ├── Properties
   ├── Methods
   ├── Arguments
   ├── Control structures
   ├── Operators
   ├── Closures
   └── General layout
```

------------------------------------------------------------------------

# 2. PSR-12 Is a Formatting Standard

PSR-12 tells you how PHP code should be formatted.

It does **not** tell you whether your application should use:

``` text
Controller
Service
Repository
Factory
Event
Plugin
Middleware
```

Those are architecture decisions.

For example, both of these concerns are useful but different:

``` text
PSR-12
    ↓
How should this method be formatted?

Architecture
    ↓
Should this method belong in a Controller,
Service, Repository, or another component?
```

------------------------------------------------------------------------

# 3. PSR-1, PSR-2, and PSR-12

PSR-12 builds on the basic coding conventions of PSR-1 and replaced the
older PSR-2 coding-style recommendation.

A simple mental model:

``` text
PSR-1
   │
   │ Basic coding standard
   ▼
PSR-2
   │
   │ Older coding style recommendation
   ▼
PSR-12
       Extended coding style
```

For new or modernized PHP code, use **PSR-12** rather than starting with
PSR-2.

------------------------------------------------------------------------

# 4. PHP Opening Tag

Use:

``` php
<?php
```

Do not use a short opening tag such as:

``` php
<?
```

Normal file:

``` php
<?php

namespace App\Service;

class EmailService
{
}
```

------------------------------------------------------------------------

# 5. PHP-Only Files Should Not Use a Closing Tag

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

Why?

Whitespace accidentally added after `?>` can become output. That may
cause problems such as HTTP headers already having been sent.

For PHP-only source files, omitting the closing tag avoids this class of
problem.

------------------------------------------------------------------------

# 6. File Encoding

Use UTF-8.

For a practical project standard:

``` text
Encoding: UTF-8
BOM:      No
```

Avoiding a BOM is useful because invisible bytes before the PHP opening
tag can cause unwanted output in older applications.

------------------------------------------------------------------------

# 7. Line Endings

PSR-12 specifies Unix-style line endings:

``` text
LF
```

rather than:

``` text
CRLF
```

On Windows, editors may default to CRLF. A project-wide editor/Git
policy can keep line endings consistent and avoid unnecessary Git diffs.

------------------------------------------------------------------------

# 8. Indentation

Use **4 spaces** for each indentation level.

Good:

``` php
if ($user->isActive()) {
    $this->sendEmail($user);
}
```

Nested example:

``` php
if ($user->isActive()) {
    if ($user->hasPermission('email.send')) {
        $this->sendEmail($user);
    }
}
```

Think of indentation as:

``` text
Level 0: no spaces
Level 1:     4 spaces
Level 2:         8 spaces
Level 3:             12 spaces
```

Do not use tabs for indentation.

------------------------------------------------------------------------

# 9. Line Length

PSR-12 does not impose a hard maximum line length. It recommends keeping
lines reasonably readable.

A practical project target is often around:

``` text
80–120 characters
```

Do not damage readability simply to reach an arbitrary number.

Long:

``` php
$result = $this->emailService->createEmail($userId, $subject, $message, $recipientIds, $attachments, $options);
```

More readable:

``` php
$result = $this->emailService->createEmail(
    $userId,
    $subject,
    $message,
    $recipientIds,
    $attachments,
    $options
);
```

------------------------------------------------------------------------

# 10. Blank Lines

Use blank lines to separate logical sections.

Good:

``` php
$user = $this->getUser($userId);

if ($user === null) {
    throw new UserNotFoundException();
}

$permissions = $this->getPermissions($user);

return $permissions;
```

Avoid excessive empty lines:

``` php
$user = $this->getUser($userId);



if ($user === null) {



    throw new UserNotFoundException();
}
```

Whitespace should improve readability, not create visual noise.

------------------------------------------------------------------------

# 11. Namespace Declaration

Example:

``` php
<?php

namespace App\Service;

class EmailService
{
}
```

With imports:

``` php
<?php

namespace App\Service;

use App\Repository\EmailRepository;
use Psr\Log\LoggerInterface;

class EmailService
{
}
```

A clear file structure is:

``` text
<?php

namespace ...

use ...
use ...

class ...
```

------------------------------------------------------------------------

# 12. `use` Statements

Put imports near the top of the file.

``` php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
```

Avoid unnecessarily writing long fully qualified class names throughout
a file.

Instead of repeatedly using:

``` php
\Firebase\JWT\JWT::encode(
    $payload,
    $key,
    'HS256'
);
```

import the class:

``` php
use Firebase\JWT\JWT;
```

and then use:

``` php
JWT::encode(
    $payload,
    $key,
    'HS256'
);
```

Aliases are useful for name conflicts:

``` php
use App\Model\User;
use External\Package\User as ExternalUser;
```

------------------------------------------------------------------------

# 13. Class Declarations

The opening brace for a class goes on the **next line**.

Correct:

``` php
class EmailService
{
}
```

Not:

``` php
class EmailService {
}
```

An important pattern to remember is:

``` text
Class declaration
    → opening brace on next line

Method declaration
    → opening brace on next line

Control structure
    → opening brace on same line
```

------------------------------------------------------------------------

# 14. Extending Classes

Example:

``` php
class Api_EmailController extends Zend_Controller_Action
{
}
```

For a longer declaration, formatting may be split to keep it readable.

For example:

``` php
class Application_Model_DbTable_Email
    extends Zend_Db_Table_Abstract
{
}
```

This is useful in legacy ZF1 code with long class names.

------------------------------------------------------------------------

# 15. Interfaces

Example:

``` php
interface EmailRepositoryInterface
{
    public function findById($emailId);

    public function deleteById($emailId);
}
```

Implementation:

``` php
class EmailRepository implements EmailRepositoryInterface
{
}
```

If an implementation list is long, split it consistently:

``` php
class EmailService implements
    EmailSenderInterface,
    LoggerAwareInterface
{
}
```

------------------------------------------------------------------------

# 16. Traits

Example:

``` php
class EmailService
{
    use LoggerTrait;

    public function sendEmail()
    {
        // ...
    }
}
```

Trait usage belongs inside the class body.

------------------------------------------------------------------------

# 17. Properties

Declare visibility explicitly.

Good:

``` php
private $emailTable;

protected $logger;

public $name;
```

Avoid old-style `var` declarations:

``` php
var $emailTable;
```

For PHP 7.4, typed properties can be used in appropriate new code:

``` php
private string $secret;

private int $tokenLifetime;

private bool $enabled;
```

------------------------------------------------------------------------

# 18. One Property Per Declaration

Avoid:

``` php
private $name, $email, $status;
```

Prefer:

``` php
private $name;

private $email;

private $status;
```

This makes documentation and future modifications clearer.

------------------------------------------------------------------------

# 19. Constants

Example:

``` php
class JwtService
{
    private const DEFAULT_LIFETIME = 900;

    private const ALGORITHM = 'HS256';
}
```

Use descriptive constant names.

Project convention can use uppercase snake case:

``` php
const MAX_LOGIN_ATTEMPTS = 5;
const DEFAULT_PAGE_SIZE = 20;
```

------------------------------------------------------------------------

# 20. Method Declarations

The opening brace for a method goes on the next line.

Correct:

``` php
public function getUser($userId)
{
    // ...
}
```

Not:

``` php
public function getUser($userId) {
    // ...
}
```

Basic pattern:

``` text
visibility function methodName(arguments)
{
    code
}
```

------------------------------------------------------------------------

# 21. Method Visibility

Always specify method visibility.

``` php
public function getUser()
{
}
```

``` php
protected function validateUser()
{
}
```

``` php
private function normalizeData()
{
}
```

Do not rely on implicit visibility.

------------------------------------------------------------------------

# 22. Method Arguments

Short parameter list:

``` php
public function getUser($userId)
{
}
```

Multiline parameter list:

``` php
public function createEmail(
    $userId,
    $subject,
    $message
) {
    // ...
}
```

When a parameter list is multiline, put parameters consistently on
separate lines.

Notice:

``` php
) {
```

For a multiline declaration, the opening brace appears on the same line
as the closing parenthesis.

------------------------------------------------------------------------

# 23. Multiline Function Calls

Short call:

``` php
$user = $service->getUser($userId);
```

Longer call:

``` php
$result = $service->createEmail(
    $userId,
    $subject,
    $message,
    $recipientIds
);
```

Consistent multiline formatting makes code review easier.

------------------------------------------------------------------------

# 24. Trailing Commas

Where supported by the PHP version, trailing commas can be useful in
multiline argument lists.

PHP 7.4 example:

``` php
$result = $service->createEmail(
    $userId,
    $subject,
    $message,
);
```

This can produce cleaner Git diffs when adding another argument later.

For a legacy application, adopt this only if the team chooses to use it
consistently.

------------------------------------------------------------------------

# 25. Return Types

PHP 7.4 supports native return types:

``` php
public function getUser(int $userId): ?User
{
}
```

Spacing matters:

``` text
): ?User
```

not:

``` text
):?User
```

Another example:

``` php
public function isActive(): bool
{
    return $this->active;
}
```

------------------------------------------------------------------------

# 26. Nullable Types

Example:

``` php
public function findUser(int $userId): ?User
{
}
```

`?User` means:

``` text
User OR null
```

For legacy ZF1 code, native types should be introduced carefully because
older callers may rely on PHP's loose typing.

------------------------------------------------------------------------

# 27. `if` Statements

Correct:

``` php
if ($user->isActive()) {
    $this->sendEmail($user);
}
```

Notice:

``` text
if + space + (
condition
) + space + {
```

Avoid:

``` php
if($user->isActive()){
    $this->sendEmail($user);
}
```

------------------------------------------------------------------------

# 28. `if`, `elseif`, and `else`

Correct:

``` php
if ($score >= 90) {
    $grade = 'A';
} elseif ($score >= 80) {
    $grade = 'B';
} else {
    $grade = 'C';
}
```

Use:

``` php
} elseif (...) {
```

and:

``` php
} else {
```

on the same line.

------------------------------------------------------------------------

# 29. Long Conditions

A long condition can be split for readability.

Instead of:

``` php
if ($user->isActive() && $user->hasPermission('email.send') && !$user->isBlocked()) {
    $this->sendEmail($user);
}
```

use:

``` php
if (
    $user->isActive()
    && $user->hasPermission('email.send')
    && !$user->isBlocked()
) {
    $this->sendEmail($user);
}
```

This makes each condition easy to review.

------------------------------------------------------------------------

# 30. `switch`

Example:

``` php
switch ($status) {
    case 'pending':
        $this->handlePending();
        break;

    case 'approved':
        $this->handleApproved();
        break;

    case 'rejected':
        $this->handleRejected();
        break;

    default:
        throw new InvalidArgumentException(
            'Unknown status.'
        );
}
```

Indent each `case` consistently within the `switch`.

------------------------------------------------------------------------

# 31. `while`

``` php
while ($row = $result->fetch()) {
    $this->processRow($row);
}
```

There is a space after `while`.

------------------------------------------------------------------------

# 32. `do while`

``` php
do {
    $this->process();
} while ($this->hasMore());
```

------------------------------------------------------------------------

# 33. `for`

``` php
for ($i = 0; $i < $count; $i++) {
    $this->process($i);
}
```

Keep the expression readable with appropriate spaces.

------------------------------------------------------------------------

# 34. `foreach`

Simple:

``` php
foreach ($users as $user) {
    $this->processUser($user);
}
```

Key/value:

``` php
foreach ($users as $userId => $user) {
    $this->processUser(
        $userId,
        $user
    );
}
```

------------------------------------------------------------------------

# 35. `try` and `catch`

Example:

``` php
try {
    $service->sendEmail($data);
} catch (EmailSendException $e) {
    $logger->error(
        'Email delivery failed.',
        [
            'exception' => $e,
        ]
    );
}
```

Multiple exception handlers:

``` php
try {
    $service->deleteEmail($userId, $emailId);
} catch (EmailNotFoundException $e) {
    // Handle missing email.
} catch (AccessDeniedException $e) {
    // Handle denied access.
}
```

------------------------------------------------------------------------

# 36. `finally`

``` php
try {
    $this->process();
} catch (Exception $e) {
    $this->handleError($e);
} finally {
    $this->cleanup();
}
```

------------------------------------------------------------------------

# 37. Operators

Use spaces around binary operators.

``` php
$total = $price + $tax;

$isAllowed = $isActive && $hasPermission;

$message = 'Hello ' . $userName;
```

Avoid:

``` php
$total=$price+$tax;
```

Unary operators remain attached:

``` php
$count++;
--$remaining;
!$isActive;
```

------------------------------------------------------------------------

# 38. Assignment Operators

Good:

``` php
$count += 1;

$total *= $quantity;

$message .= ' completed';
```

Avoid:

``` php
$count+=1;
```

------------------------------------------------------------------------

# 39. Ternary Operator

Short and readable:

``` php
$status = $isActive ? 'active' : 'inactive';
```

Multiline:

``` php
$status = $isActive
    ? 'active'
    : 'inactive';
```

Avoid deeply nested ternary expressions. If the logic becomes difficult
to understand, use `if`/`else` or extract a method.

------------------------------------------------------------------------

# 40. Null Coalescing Operator

PHP 7.4 supports `??`.

``` php
$page = $options['page'] ?? 1;
```

This is often clearer than:

``` php
$page = isset($options['page'])
    ? $options['page']
    : 1;
```

Use it when missing/`null` semantics are appropriate.

------------------------------------------------------------------------

# 41. Closures

Example:

``` php
$filter = function ($user) {
    return $user->isActive();
};
```

Using an external variable:

``` php
$filter = function ($user) use ($organizationId) {
    return $user->organization_id === $organizationId;
};
```

Keep spacing around `function`, `use`, parentheses, and braces
consistent.

------------------------------------------------------------------------

# 42. Arrow Functions

PHP 7.4 supports arrow functions:

``` php
$ids = array_map(
    fn ($user) => $user->id,
    $users
);
```

Use them for short expressions.

If logic becomes complicated, use a normal closure or a named method.

------------------------------------------------------------------------

# 43. Anonymous Classes

Example:

``` php
$handler = new class {
    public function handle($message)
    {
        // ...
    }
};
```

Anonymous classes can be useful for small local implementations but
should not replace a normal named class when the behavior is substantial
or reusable.

------------------------------------------------------------------------

# 44. Method Chaining

Method chaining can be formatted vertically:

``` php
$select = $this->select()
    ->where('user_id = ?', $userId)
    ->where('deleted = ?', 0)
    ->order('created_at DESC');
```

This style is especially useful with ZF1's database APIs.

------------------------------------------------------------------------

# 45. Arrays and Project Style

A recommended project array style is:

``` php
$data = [
    'id' => 100,
    'name' => 'John',
    'active' => true,
];
```

Nested:

``` php
$config = [
    'database' => [
        'host' => 'localhost',
        'name' => 'application',
    ],
    'jwt' => [
        'lifetime' => 900,
    ],
];
```

Short syntax is appropriate for new PHP 7.4 code. There is no need to
convert untouched legacy `array()` expressions merely for appearance.

------------------------------------------------------------------------

# 46. PSR-12 and PHPDoc

PSR-12 and PHPDoc solve different problems:

``` text
PSR-12
    ↓
How source code is formatted

PHPDoc
    ↓
How behavior and types are documented
```

Example:

``` php
/**
 * Returns emails visible to the specified user.
 *
 * @param int $userId Authenticated user ID.
 *
 * @return Application_Model_Email[]
 */
public function getEmailList($userId)
{
    // ...
}
```

A project benefits from using both consistently.

------------------------------------------------------------------------

# 47. Comments

A useful comment explains reasoning.

Good:

``` php
// Preserve the original creation time because audit reports
// depend on it.
$record->updated_at = $now;
```

Less useful:

``` php
// Set updated_at.
$record->updated_at = $now;
```

A formatting standard cannot make a poor comment useful. Comments should
document non-obvious intent, constraints, or reasons.

------------------------------------------------------------------------

# 48. PSR-12 Does Not Define Architecture

PSR-12 does not require:

``` text
Controller → Service → Repository
```

It does not define dependency injection, MVC architecture, unit testing,
or repository patterns.

Your complete project standard can therefore contain several layers:

``` text
Project Coding Standard
          │
          ├── PSR-12
          │      Formatting
          │
          ├── PHPDoc
          │      Documentation
          │
          ├── Architecture rules
          │      Controller → Service → Model
          │
          ├── Security rules
          │      Validation / escaping / SQL parameters
          │
          └── Testing rules
                 PHPUnit
```

------------------------------------------------------------------------

# 49. PSR-12 Does Not Make Code Secure

This can be consistently formatted but insecure:

``` php
$sql = 'SELECT * FROM users WHERE id = ' . $_GET['id'];
```

PSR-12 does not replace:

``` text
Input validation
SQL parameterization
Output escaping
Authentication
Authorization
CSRF protection
Secure session handling
```

For example, database input should normally be parameterized:

``` php
$select = $this->select()
    ->where('id = ?', $userId);
```

Formatting and security are separate concerns.

------------------------------------------------------------------------

# 50. PSR-12 Does Not Guarantee Good Design

This can follow PSR-12 formatting perfectly:

``` php
public function saveAction()
{
    // 500 lines of business logic...
}
```

but still be poorly designed.

Architecture still matters:

``` text
Controller
    ↓
Service
    ↓
Repository / Model
    ↓
Database
```

PSR-12 improves consistency, not responsibility separation.

------------------------------------------------------------------------

# 51. Applying PSR-12 to Legacy ZF1

Do not automatically reformat the entire legacy application in one
change.

Suppose a developer changes only three functional lines. Reformatting
the whole file might create:

``` text
3 functional changes
+
300 style changes
=
Difficult Git review
```

A safer strategy:

``` text
Stable legacy ZF1 code
        │
        └── Leave mostly unchanged

Code currently being modified
        │
        ├── Improve formatting locally
        ├── Add useful PHPDoc
        ├── Add tests when important
        └── Refactor carefully

New code
        │
        └── Follow PSR-12 consistently
```

------------------------------------------------------------------------

# 52. Legacy ZF1 Example

Old-style code:

``` php
class Application_Model_User extends Zend_Db_Table_Abstract
{
    public function getuser($id){
        if($id>0){
            return $this->fetchRow("id=".$id);
        }
        return false;
    }
}
```

A cleaner version:

``` php
class Application_Model_User
    extends Zend_Db_Table_Abstract
{
    public function getUser($userId)
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->fetchRow(
            $this->select()
                ->where('id = ?', $userId)
        );
    }
}
```

Changes include:

``` text
getuser       → getUser
$id           → $userId
Formatting    → consistent style
Nested logic  → early return
Raw condition → parameterized condition
```

Some of these are behavioral/refactoring changes rather than PSR-12
changes, so important legacy behavior should be protected with tests
before broader refactoring.

------------------------------------------------------------------------

# 53. ZF1 Controller Example

``` php
class Api_EmailController
    extends Zend_Controller_Action
{
    public function getlistAction()
    {
        $userId = $this->_getParam(
            'authenticatedUserId'
        );

        $service = new My_Service_Email();

        $emails = $service->getEmailList(
            $userId
        );

        $this->_helper->json([
            'success' => true,
            'data' => $emails,
        ]);
    }
}
```

This combines consistent formatting with a thin-controller project
architecture.

------------------------------------------------------------------------

# 54. ZF1 Service Example

``` php
class My_Service_Email
{
    /**
     * @var Application_Model_DbTable_Email
     */
    private $emailTable;

    public function __construct(
        Application_Model_DbTable_Email $emailTable
    ) {
        $this->emailTable = $emailTable;
    }

    public function getEmailList($userId)
    {
        if ((int) $userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid user ID.'
            );
        }

        return $this->emailTable->getByUserId(
            $userId
        );
    }
}
```

------------------------------------------------------------------------

# 55. ZF1 DbTable Example

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

Vertical method chaining makes the query construction easy to scan.

------------------------------------------------------------------------

# 56. PSR-12 and PHPUnit

Test code should follow the same style baseline.

``` php
use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    public function testInvalidUserIdIsRejected()
    {
        $service = $this->createService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->getEmailList(0);
    }
}
```

Production and test code should not use completely different formatting
conventions.

------------------------------------------------------------------------

# 57. Automated Style Checking

In a larger project, code-style tools can help enforce formatting
consistently.

Conceptually:

``` text
PHPUnit
    ↓
Tests behavior

Static analyzer
    ↓
Analyzes types and possible code problems

Code-style checker
    ↓
Reports style violations

Code formatter/fixer
    ↓
Automatically fixes supported style violations
```

For a legacy ZF1 repository, first use a style tool in **check/report
mode**. Do not automatically reformat the whole repository until the
team has decided how to manage the resulting Git changes.

------------------------------------------------------------------------

# 58. PSR-12 Review Checklist

When reviewing a PHP file, check:

-   `<?php` is used.
-   PHP-only files omit `?>`.
-   UTF-8 is used.
-   LF line endings are used according to project policy.
-   Indentation uses 4 spaces.
-   Tabs are not used for indentation.
-   Statements are separated correctly.
-   Class braces are placed correctly.
-   Method braces are placed correctly.
-   Control-structure braces are placed correctly.
-   There is a space after `if`, `for`, `foreach`, `while`, `switch`,
    `catch`, etc.
-   Binary operators have appropriate whitespace.
-   Multiline parameter and argument lists are formatted consistently.
-   Namespace and `use` declarations are organized.
-   Visibility is declared.
-   Long expressions are split readably.
-   Blank lines improve readability.
-   New code follows the standard consistently.

------------------------------------------------------------------------

# 59. Easy Rules to Remember

A compact mental checklist:

``` text
1. Use 4 spaces; do not indent with tabs.

2. Class opening brace:
       next line

3. Method opening brace:
       next line

4. Control structure opening brace:
       same line

5. Put a space after:
       if
       for
       foreach
       while
       switch
       catch

6. Use spaces around binary operators.

7. Use one statement per line.

8. Format multiline arguments consistently.

9. PHP-only files:
       no closing ?>

10. Apply PSR-12 consistently to new code.
```

The visual pattern is:

``` php
class EmailService
{
    public function sendEmail()
    {
        if ($this->canSend()) {
            $this->send();
        }
    }
}
```

Remember:

``` text
class        → { on next line
function     → { on next line
if           → { on same line
```

------------------------------------------------------------------------

# 60. PSR-12 in a Gradual Modernization Plan

For a legacy PHP project:

``` text
Existing ZF1 code
      │
      ▼
Adopt PSR-12 for new code
      │
      ▼
Add useful PHPDoc to important legacy APIs
      │
      ▼
Add PHPUnit tests
      │
      ▼
Refactor modified code safely
      │
      ▼
Introduce Composer and namespaces
      │
      ▼
Introduce native PHP types carefully
      │
      ▼
Migrate framework code gradually
```

PSR-12 should help modernization, not become a reason to rewrite stable
code unnecessarily.

------------------------------------------------------------------------

# 61. How PSR-12 Fits With the Full Project Standard

Think of PSR-12 as one layer:

``` text
PSR-12
    │
    └── Consistent formatting

PHPDoc
    │
    └── Documentation and richer type information

PHPUnit
    │
    └── Behavioral protection

Controller → Service → Model / Repository
    │
    └── Architecture

Validation + SQL parameterization + output escaping
    │
    └── Security

Git
    │
    └── Controlled change history
```

Together these provide a much stronger engineering standard than PSR-12
alone.

------------------------------------------------------------------------

# 62. Final Principle

PSR-12 can be thought of as the **grammar for how PHP source code should
look**.

It provides consistency, but it does not replace good:

``` text
Architecture
Security
Documentation
Testing
Naming
Error handling
Database design
Business logic
```

For a legacy ZF1 + PHP 7.4 project, a practical policy is:

> **Use PSR-12 consistently for new code, improve modified legacy code
> gradually, and avoid large formatting-only changes that make Git
> history and code review difficult.**

This gives the project a consistent path from legacy ZF1 code toward a
cleaner modern PHP codebase.
