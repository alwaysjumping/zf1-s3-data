# PHPUnit: Complete Educational Handbook

**From beginner fundamentals to enterprise testing strategy**  
**Target stack:** PHP 7.4 · PHPUnit 9.6 · Laravel 8 · MariaDB 10.11  
**Audience:** PHP developers maintaining business applications, including Laravel applications and legacy systems  
**Laravel 8 adaptation:** October 2026

> This is an independently authored learning guide, not the official PHPUnit manual. The examples target PHPUnit 9.6 and PHP 7.4. PHP 7.4 and Laravel 8 are outside standard security support; use an isolated, supported development environment where possible and plan an upgrade.

## Table of contents

1. [What is PHPUnit and why is it important?](#article-1-what-is-phpunit-and-why-is-it-important)
2. [Writing and running your first tests](#article-2-writing-and-running-your-first-tests)
3. [Assertions, fixtures, mocks and dependency injection](#article-3-assertions-fixtures-mocks-and-dependency-injection)
4. [Practical PHPUnit with Laravel 8 and MariaDB](#article-4-practical-phpunit-with-laravel8-and-mariadb)
5. [Coverage, isolation and test automation](#article-5-coverage-isolation-and-test-automation)
6. [Enterprise testing strategy and roadmap](#article-6-enterprise-testing-strategy-and-roadmap)
7. [Quick-reference commands](#quick-reference-commands)
8. [References](#references)

---

# Article 1: What Is PHPUnit and Why Is It Important?

## 1.1 Manual testing versus automated testing

When developers finish a PHP feature, they often open a browser, enter data and inspect the output. This is valuable, but repeatedly checking every feature becomes expensive as the system grows. A modification to authentication, for example, might unexpectedly affect token refresh or authorization.

**PHPUnit** is an open-source PHP testing framework that runs tests automatically and reports whether defined expectations were met. It is most commonly used for unit testing, but also supports integration and functional testing.

A test follows a simple pattern:

1. Prepare inputs and dependencies.
2. Execute application code.
3. Assert that the result or interaction matches the expected behavior.

```php
function add($a, $b)
{
    return $a + $b;
}
```

```php
use PHPUnit\Framework\TestCase;

final class AdditionTest extends TestCase
{
    public function testAddition(): void
    {
        $this->assertSame(30, add(10, 20));
    }
}
```

## 1.2 Why it matters

- **Early defect detection:** invalid calculations and unexpected exceptions are caught before release.
- **Regression protection:** old tests run again after changes and expose broken behavior.
- **Less repetitive manual checking:** many isolated tests execute in seconds, although integration tests can be slower.
- **Safer refactoring:** reliable behavioral tests help developers reorganize legacy code.
- **Executable documentation:** a descriptive test shows what a class is expected to do.
- **Better design:** testable code often has smaller responsibilities and explicit dependencies.

Automated tests do **not** prove a program is bug-free. Their value depends on the scenarios tested and the quality of assertions.

## 1.3 Types of testing

| Type | Focus | Example |
|---|---|---|
| Unit | One class or business rule in isolation | Password policy |
| Integration | Collaboration with real components | Repository + MariaDB |
| Functional | Controller/API behavior | `POST /api/auth/login` returns 401 |
| End-to-end | Full workflow | Login → create document → verify |
| Regression | Previously correct behavior stays correct | Token rotation after a code change |

**Takeaway:** PHPUnit is most valuable as an ongoing safety net, not as a one-time correctness check.

---

# Article 2: Writing and Running Your First Tests

## 2.1 Install PHPUnit

PHPUnit 9.6 is suitable for PHP 7.4. On a compatible development machine:

```bash
composer require --dev 'phpunit/phpunit:^9.6'
```

Verify:

```bash
vendor/bin/phpunit --version
```

On Windows:

```bat
vendor\bin\phpunit --version
```

For offline environments, prepare and validate the dependency set on a compatible connected machine, then transfer the locked dependencies and complete `vendor/` directory through an approved process. Confirm that the offline PHP extensions and platform match.

## 2.2 Sample project

```text
phpunit-demo/
├── src/
│   └── Calculator.php
├── tests/
│   └── CalculatorTest.php
├── phpunit.xml
└── vendor/
```

`src/Calculator.php`:

```php
<?php
class Calculator
{
    public function add($a, $b)
    {
        return $a + $b;
    }

    public function divide($a, $b)
    {
        if ($b == 0) {
            throw new InvalidArgumentException('Division by zero.');
        }
        return $a / $b;
    }
}
```

`tests/CalculatorTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Calculator.php';

final class CalculatorTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new Calculator();
    }

    public function testAddition(): void
    {
        $this->assertSame(30, $this->calculator->add(10, 20));
    }

    public function testDivision(): void
    {
        $this->assertEquals(5, $this->calculator->divide(20, 4));
    }

    public function testDivisionByZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->divide(10, 0);
    }
}
```

`phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Run:

```bash
vendor/bin/phpunit
```

Typical result:

```text
...  3 / 3 (100%)
OK (3 tests, 3 assertions)
```

Actual assertion totals can differ for more complex PHPUnit helpers. A **test** is a test execution; an **assertion** is a checked expectation. One test can have several assertions.

## 2.3 Key assertions

| Assertion | Checks |
|---|---|
| `assertSame(10, $value)` | Value **and type** are identical |
| `assertEquals(10, $value)` | Values compare equal |
| `assertTrue($value)` | Strictly true |
| `assertFalse($value)` | Strictly false |
| `assertNull($value)` | Null |
| `assertCount(3, $items)` | Collection count |
| `assertArrayHasKey('id', $row)` | Key exists |
| `assertInstanceOf(User::class, $user)` | Object type |
| `assertStringContainsString('ok', $text)` | Substring |

Prefer `assertSame()` when types matter; `10` and `'10'` are not identical.

## 2.4 Data providers

One test method can run against several datasets:

```php
/** @dataProvider additionCases */
public function testAdditionCases($a, $b, $expected): void
{
    $this->assertSame($expected, (new Calculator())->add($a, $b));
}

public function additionCases(): array
{
    return [
        [10, 20, 30],
        [-5, 5, 0],
        [0, 0, 0],
    ];
}
```

This method belongs inside a `TestCase` subclass. Each dataset produces a separate test execution.

## 2.5 TDD in one paragraph

**Test-driven development** follows Red → Green → Refactor: write a failing test, implement the minimum behavior to pass, then improve the design while keeping tests green. Writing tests after implementation is also useful, particularly when introducing PHPUnit into an existing project.

---

# Article 3: Assertions, Fixtures, Mocks and Dependency Injection

## 3.1 Test fixtures

A fixture is controlled state prepared for a test. PHPUnit runs `setUp()` before each test and `tearDown()` afterward:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->service = new UserService($this->repository);
}

protected function tearDown(): void
{
    unset($this->service);
    parent::tearDown();
}
```

Each test should be independent of test order.

## 3.2 Why dependency injection helps

A service that constructs its own PDO connection is difficult to isolate. Instead, inject a repository interface:

```php
<?php
interface UserRepositoryInterface
{
    public function exists($username);
    public function insert($username);
}

class UserService
{
    private $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function register($username)
    {
        if (!is_string($username) || trim($username) === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if ($this->repository->exists($username)) {
            throw new RuntimeException('Username already exists.');
        }
        return $this->repository->insert($username);
    }
}
```

The real implementation talks to MariaDB. The unit test substitutes a controlled test double.

## 3.3 Mock interactions and stubbed responses

```php
<?php
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testRegistrationInsertsUserOnce(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('exists')->with('john')->willReturn(false);
        $repo->expects($this->once())
            ->method('insert')->with('john')->willReturn(1001);

        $service = new UserService($repo);
        $this->assertSame(1001, $service->register('john'));
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->method('exists')->willReturn(true);
        $repo->expects($this->never())->method('insert');

        $this->expectException(RuntimeException::class);
        (new UserService($repo))->register('john');
    }
}
```

A **stub** supplies predetermined responses; a **mock** verifies expected interactions. PHPUnit's `createMock()` can support both styles.

## 3.4 Avoid real side effects in unit tests

Mock email senders, HTTP clients, file storage gateways and repositories when testing service behavior. Verify, for example, that `sendWelcomeEmail('john')` was requested exactly once; do not send an actual email during the unit test.

Do not mock everything. Tests that only confirm their own mock setup can miss broken application logic. Complement unit tests with real integration tests.

## 3.5 Exceptions

```php
public function testEmptyUsernameThrows(): void
{
    $repo = $this->createMock(UserRepositoryInterface::class);
    $repo->expects($this->never())->method('insert');

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Username is required.');
    (new UserService($repo))->register('');
}
```

For wrapped exceptions, verify the public exception type and, where necessary, inspect `getPrevious()` to ensure the original cause was preserved.

---

# Article 4: Practical PHPUnit with Laravel 8 and MariaDB

Laravel 8 includes first-class testing support. A normal Laravel application already contains `phpunit.xml`, `tests/TestCase.php`, `tests/Unit/` and `tests/Feature/`. Laravel's feature tests can exercise routes, middleware, controllers, validation and JSON APIs without launching a browser. Database tests use Eloquent and Laravel's database testing helpers.

## 4.1 Recommended project layout

```text
laravel8-project/
├── app/
│   ├── Http/Controllers/
│   ├── Models/User.php
│   └── Services/
├── database/
│   ├── factories/
│   └── migrations/
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── TestCase.php
│   ├── Unit/
│   └── Feature/
├── .env.testing
├── phpunit.xml
├── composer.json
└── vendor/
```

Laravel 8 supports PHP 7.3+, so PHP 7.4 can run an appropriate Laravel 8 release. Both PHP 7.4 and Laravel 8 are now outside their standard security support periods; use them only in an appropriately isolated legacy environment and plan upgrades. PHPUnit 9.6 is appropriate for PHP 7.4; check the installed Laravel project's Composer constraints before upgrading dependencies.

## 4.2 Install and execute PHPUnit

For an existing Laravel 8 project, first inspect the installed test dependencies:

```bash
composer show phpunit/phpunit
php artisan --version
php vendor/bin/phpunit --version
```

If PHPUnit is missing and the project's dependencies allow it:

```bash
composer require --dev 'phpunit/phpunit:^9.6' --with-all-dependencies
```

Do not blindly upgrade a locked Laravel 8 application: review changes to `composer.lock` and verify compatibility. Execute tests using:

```bash
php artisan test
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php vendor/bin/phpunit
```

On Windows, use `php artisan test` or `php vendor/bin/phpunit` with the intended PHP 7.4 executable.

## 4.3 Configure an isolated test environment

Create a dedicated MariaDB database, for example `laravel8_test`, and a database user with permissions **only** on that database. Apply the application's migrations to the testing database rather than manually maintaining a divergent schema.

Example `.env.testing` (illustrative; do not commit real credentials):

```dotenv
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel8_test
DB_USERNAME=laravel_test_user
DB_PASSWORD=replace_with_secret
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
```

Laravel 8 uses `MAIL_MAILER` for mail transport and typically `QUEUE_CONNECTION` for queues. If your application's `config/*.php` files have been customized, use the actual configuration keys. Avoid reusing cached production configuration: `php artisan config:clear` in a safe development environment before running tests. Never run `migrate:fresh`, `RefreshDatabase`, or destructive tests against production.

For CI, set `APP_KEY` to a generated testing-only key and ensure the test runner has the required PHP extensions. Laravel's `phpunit.xml` may also set test environment values; confirm these do not accidentally override `.env.testing` with an unsafe database name.

## 4.4 PHPUnit and Laravel base test classes

Pure PHP unit tests can extend `PHPUnit\Framework\TestCase`. Tests that need the Laravel container, application configuration, routing, Eloquent or framework helpers should extend `Tests\TestCase`.

`tests/TestCase.php` in a typical Laravel 8 project:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
```

`CreatesApplication` is the trait supplied by the Laravel 8 project skeleton that boots the application. Preserve your project's generated bootstrap rather than replacing it with a Laravel 8-style loader.

## 4.5 Unit testing a Laravel service

Create `app/Services/DiscountService.php`:

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class DiscountService
{
    public function discountAmount(float $price, float $percent): float
    {
        if ($price < 0 || $percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Invalid discount input.');
        }

        return $price * $percent / 100;
    }
}
```

Create `tests/Unit/DiscountServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\DiscountService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DiscountServiceTest extends TestCase
{
    public function test_calculates_discount(): void
    {
        $service = new DiscountService();
        $this->assertSame(10.0, $service->discountAmount(100, 10));
    }

    public function test_rejects_negative_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DiscountService())->discountAmount(-1, 10);
    }
}
```

This unit test requires no Laravel application bootstrap or database. Prefer this approach for isolated business rules.

## 4.6 Testing Eloquent models with MariaDB

Laravel 8 typically places the user model in `app/Models/User.php`. Its default migration creates a `users` table; use the actual schema and fillable attributes of your application.

Create `tests/Feature/UserDatabaseTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_saved_and_found(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test.user@example.test',
            'password' => bcrypt('test-password'),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'test.user@example.test',
        ]);

        $this->assertSame(
            'test.user@example.test',
            User::findOrFail($user->id)->email
        );
    }
}
```

`RefreshDatabase` may migrate the schema and use transactions when supported. It is **destructive if pointed at the wrong database**. Verify database isolation before running it. Transactions do not automatically undo changes made through separate connections, asynchronous workers or external systems. If you use factories, Laravel 8 commonly supports `User::factory()->create()` for models with `HasFactory` and an appropriate factory definition.

## 4.7 Feature testing HTTP routes and JSON APIs

Create a simple example endpoint in `routes/api.php`:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/echo-name', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string',
    ]);

    return response()->json(['name' => $validated['name']], 200);
});
```

Create `tests/Feature/EchoNameApiTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class EchoNameApiTest extends TestCase
{
    public function test_accepts_valid_json(): void
    {
        $response = $this->postJson('/api/echo-name', [
            'name' => 'John',
        ]);

        $response->assertOk()
                 ->assertJsonPath('name', 'John');
    }

    public function test_rejects_missing_name(): void
    {
        $response = $this->postJson('/api/echo-name', []);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors('name');
    }
}
```

Routes in `routes/api.php` normally receive the `/api` URL prefix. `postJson()` sends a JSON request with appropriate headers. These are Laravel feature tests: no `Zend_Test_PHPUnit_ControllerTestCase` or manual front-controller dispatch is needed.

## 4.8 Mocking Laravel dependencies and facades

For services resolved through Laravel's container, bind mocks using `$this->mock()` or `$this->instance()`. Avoid mocking the code under test; mock its collaborators.

```php
$mock = $this->mock(\App\Contracts\Notifier::class, function ($mock) {
    $mock->shouldReceive('send')
         ->once()
         ->with('john', 'Welcome');
});
```

This assumes `App\Contracts\Notifier` exists and the application resolves it through the container. Laravel's mocking helpers commonly use Mockery; do not mix their expectations with PHPUnit's `createMock()` API without understanding the differences.

For mail or queue behavior, Laravel provides purpose-built fakes:

```php
use Illuminate\Support\Facades\Mail;

Mail::fake();
// Execute the application action that sends a mailable.
Mail::assertNothingSent(); // Only appropriate if no mail is expected.
```

Use `Mail::assertSent()` with the application's real mailable class when mail *should* be sent. Similar helpers exist for `Queue`, `Event`, `Notification` and `Storage`; fakes verify interactions but do not prove that real external delivery works.

## 4.9 Authentication and authorization tests

Laravel 8 supports `actingAs()` to simulate an authenticated user in feature tests:

```php
public function test_authenticated_user_can_access_profile(): void
{
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
         ->get('/profile')
         ->assertOk();
}
```

This assumes `/profile` exists, requires authentication, and responds with HTTP 200. Include `RefreshDatabase` in the test class and create the route before using this example. To test authorization, verify permitted and forbidden roles separately; HTTP 403 is common for authenticated users lacking permission, while unauthenticated API requests often return HTTP 401. The exact behavior depends on middleware and exception handling.

If the application uses Sanctum, Passport or a custom JWT service, test its real token issuance, expiry, signature validation, revocation and rotation semantics. Do not assume Laravel 8 ships with JWT or refresh-token rotation by default.

## 4.10 Recommended Laravel test commands

```bash
php artisan test
php artisan test --filter=test_accepts_valid_json
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php vendor/bin/phpunit --coverage-html build/coverage
```

For HTML coverage, enable a compatible Xdebug or PCOV driver and configure the PHPUnit 9.6 coverage include filter for `app/`. Keep coverage reports and testing credentials out of public web directories.

**Key difference from Laravel 8:** Laravel provides application-aware testing, request helpers, database assertions, factories, fakes and migrations. Use those framework features rather than carrying over Laravel 8 bootstrap, model or controller test patterns.

---

# Article 5: Coverage, Isolation and Test Automation

## 5.1 Coverage is a map, not proof

Coverage shows which executable lines, branches or paths were exercised. It does not prove that the assertions are meaningful. A test can execute a broken calculation without checking its output.

PHPUnit 9.6 supports coverage reporting with compatible drivers such as Xdebug or PCOV. With Xdebug 3, enable coverage mode:

```ini
xdebug.mode=coverage
```

Generate an HTML report:

```bash
vendor/bin/phpunit --coverage-html build/coverage
```

Open `build/coverage/index.html`. The coverage filter in `phpunit.xml` should target application source, not vendor dependencies.

## 5.2 Database isolation approaches

| Approach | Best for | Limitation |
|---|---|---|
| Transaction rollback | Tests using one connection, no commits | Does not undo implicit commits or other connections |
| Per-test fixtures + cleanup | Predictable shared test DB | Cleanup must be robust |
| Separate database per worker | Parallel integration suites | Provisioning overhead |
| Disposable database instance | High-isolation CI runs | More setup and runtime |

Never run destructive cleanup against production. Do not copy sensitive production records into tests without approved anonymization.

## 5.3 Flaky tests

A flaky test alternates between pass and fail without relevant code changes. Typical causes: current time, random values, shared data, race conditions, network services and test ordering. Inject clocks, use deterministic fixtures, and explicitly test concurrency with independent connections/processes where needed.

## 5.4 Commands for targeted execution

```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
vendor/bin/phpunit tests/Unit/UserServiceTest.php
vendor/bin/phpunit --filter testRegistration
vendor/bin/phpunit --stop-on-failure
vendor/bin/phpunit --testdox
```

## 5.5 JUnit reports and CI

```bash
mkdir -p build/reports
vendor/bin/phpunit --log-junit build/reports/junit.xml
```

A CI server can check out code, run syntax checks, execute unit/integration/functional suites, store reports and block merges on critical failures. This works on an isolated intranet with an internal Git repository and pre-provisioned dependencies.

Windows PowerShell 5-compatible script (`run-tests.ps1`):

```powershell
$ErrorActionPreference = 'Stop'
$php = 'C:\php74\php.exe'
$phpunit = Join-Path $PSScriptRoot 'vendor\bin\phpunit'
& $php $phpunit --configuration (Join-Path $PSScriptRoot 'phpunit.xml') --testsuite Unit
if ($LASTEXITCODE -ne 0) { exit 1 }
Write-Host 'Unit tests passed.'
```

Linux (`run-tests.sh`):

```bash
#!/usr/bin/env bash
set -e
cd "$(dirname "$0")"
php vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit
```

## 5.6 Mutation testing

Mutation tools deliberately change small parts of code (for example `>` to `>=`) and check whether tests detect the change. They can expose weak assertions that line coverage misses. For PHP 7.4, select a mutation-testing tool release compatible with the runtime; run it separately from fast feedback tests.

---

# Article 6: Enterprise Testing Strategy and Roadmap

## 6.1 Risk-based priorities

Not every module deserves equal initial effort. Prioritize according to impact, likelihood of failure, complexity, rate of change and past defects.

| Priority | Area | Main tests |
|---|---|---|
| P1 | Authentication and authorization | Login, JWT, refresh rotation, permissions |
| P1 | Critical transactions | Atomicity, rollback, concurrent updates |
| P1 | Protected documents | Access checks, integrity, storage behavior |
| P2 | Email and messaging | Persistence, validation, duplicates |
| P2 | Notifications | Outbox, retry, idempotency, worker recovery |
| P3 | CRM and product | Calculations, repository behavior |
| P3 | Reporting | Correctness of calculations and data |
| P4 | UI preferences | Validation and basic workflows |

This ranking is illustrative; adjust it to actual organizational risk.

## 6.2 Team responsibilities

- **Developers:** tests for new behavior, regression tests for bug fixes, run relevant suites and review test code.
- **Technical leads:** test architecture, critical-module priorities, maintainability and reliability.
- **QA:** exploratory, acceptance, browser compatibility and end-to-end testing.
- **Build/DevOps:** internal CI, reproducible environments, reports and protected test infrastructure.

## 6.3 Quality standards

Use descriptive test names, Arrange–Act–Assert structure, deterministic fixtures and independent tests. Keep tests readable. Never weaken assertions merely to make a failing build pass.

Suggested merge checklist:

- [ ] New critical behavior has tests.
- [ ] Normal, invalid and boundary cases are covered.
- [ ] Relevant exception behavior is verified.
- [ ] Database changes have appropriate integration tests.
- [ ] Authentication and permission implications are reviewed.
- [ ] No test depends on production data or secrets.
- [ ] Tests are independent and repeatable.
- [ ] Relevant test suites pass.
- [ ] Test changes receive code review.

## 6.4 Six-phase adoption roadmap

### Phase 1 — Foundation

Install PHPUnit 9.6; configure `phpunit.xml`, bootstrap and suites; run a small working example.

### Phase 2 — Critical services

Add unit tests for authentication, validation, calculations and frequently modified code. Introduce dependency injection selectively.

### Phase 3 — Database integration

Provision `s3_test`; load fixtures; verify repositories, constraints, transactions and rollback.

### Phase 4 — Laravel feature/API tests

Test routes, JSON contracts, status codes, login, permissions and error handling.

### Phase 5 — Automation

Provide repeatable Windows/Linux scripts; integrate an internal CI runner; publish JUnit and coverage reports; establish appropriate merge gates.

### Phase 6 — Continuous improvement

Track flaky tests, runtime, escaped defects and critical behavior coverage. Add regression tests after bugs and review priorities periodically.

## 6.5 Practical testing policy

1. New high-risk features should include automated tests.
2. Significant bug fixes should include a regression test where feasible.
3. Integration tests must use isolated, least-privilege resources.
4. Critical failures block release until investigated or explicitly risk-accepted through governance.
5. Coverage is supporting evidence, not the sole quality target.
6. Automated tests complement security reviews, manual testing and performance testing.

## 6.6 Testing asynchronous notifications

For a Laravel 8 application with a MariaDB outbox and optional Workerman/WebSocket or REST polling, use several layers:

- Unit tests for event creation, payload validation and deduplication rules.
- Integration tests for outbox persistence, retry state and worker recovery.
- Failure tests for unavailable workers and broken internal signal channels.
- Client/browser tests for reconnect and multi-tab coordination (these may require JavaScript tooling beyond PHPUnit).

**Final principle:** The goal is not the maximum number of tests; it is dependable feedback that makes important software safer to change.

---

# Quick-Reference Commands

| Task | Command |
|---|---|
| Version | `vendor/bin/phpunit --version` |
| All suites | `vendor/bin/phpunit` |
| Unit suite | `vendor/bin/phpunit --testsuite Unit` |
| Integration suite | `vendor/bin/phpunit --testsuite Integration` |
| One test file | `vendor/bin/phpunit tests/Unit/UserServiceTest.php` |
| Match method name | `vendor/bin/phpunit --filter testRegister` |
| Stop on failure | `vendor/bin/phpunit --stop-on-failure` |
| Readable names | `vendor/bin/phpunit --testdox` |
| HTML coverage | `vendor/bin/phpunit --coverage-html build/coverage` |
| JUnit XML | `vendor/bin/phpunit --log-junit build/reports/junit.xml` |

On Windows, use `vendor\bin\phpunit` or invoke `php vendor/bin/phpunit` with the correct PHP 7.4 executable. Create report directories before writing into them.

# References

1. PHPUnit 9.6 Manual — https://docs.phpunit.de/en/9.6/
2. PHPUnit Assertions — https://docs.phpunit.de/en/9.6/assertions.html
3. PHPUnit Test Doubles — https://docs.phpunit.de/en/9.6/test-doubles.html
4. PHPUnit Fixtures — https://docs.phpunit.de/en/9.6/fixtures.html
5. PHPUnit Coverage — https://docs.phpunit.de/en/9.6/code-coverage-analysis.html
6. PHPUnit XML Configuration — https://docs.phpunit.de/en/9.6/configuration.html
7. PHP Exceptions — https://www.php.net/manual/en/language.exceptions.php
8. Laravel 8 Testing Documentation — https://laravel.com/docs/8.x/testing
9. MariaDB Documentation — https://mariadb.com/docs/
10. Xdebug — https://xdebug.org/docs/
11. PCOV — https://github.com/krakjoe/pcov
12. Infection Mutation Testing — https://infection.github.io/
13. Martin Fowler, Practical Test Pyramid — https://martinfowler.com/articles/practical-test-pyramid.html

---

*End of handbook.*

Additional Laravel 8 references: https://laravel.com/docs/8.x/http-tests · https://laravel.com/docs/8.x/database-testing · https://laravel.com/docs/8.x/mocking · https://laravel.com/docs/8.x/installation
