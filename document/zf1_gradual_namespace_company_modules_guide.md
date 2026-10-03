# ZF1 Gradual Namespace and Company Module Architecture

## 1. Purpose

This guide defines the namespace and module strategy for the existing
Zend Framework 1 (ZF1) application.

The existing project contains many non-namespaced PHP classes. The
project will begin using namespaces for new development, but the
complete codebase will not be converted at once. Migration will happen
gradually, beginning with the Email modules.

The design must also support the company structure:

-   The parent company develops modules.
-   Child companies can develop their own modules.
-   A module developed by one child company can be installed and reused
    by another company.
-   Multiple implementations of the same business feature can coexist.
-   The namespace prefix identifies the original developer/owner of the
    code.
-   Existing legacy ZF1 code must continue to work.

## 2. Gradual Migration

Existing code can remain unchanged:

``` php
class Email_Model_Template
{
}
```

New code can use namespaces:

``` php
namespace S3\Email\Service;

class TemplateService
{
}
```

The migration policy is:

``` text
Existing stable code -> no forced conversion
New Email code       -> use namespaces
Modified old code    -> migrate when practical
```

This avoids a risky all-at-once rewrite.

## 3. Company Namespace Prefixes

The agreed namespace prefixes are:

``` text
Parent company -> S3
Company A      -> S3A
Company B      -> S3B
Company C      -> S3C
...
```

For Email:

``` text
Parent company -> S3\Email
Company A      -> S3A\Email
Company B      -> S3B\Email
```

Examples:

``` php
namespace S3\Email\Service;
```

``` php
namespace S3A\Email\Service;
```

``` php
namespace S3B\Email\Service;
```

These are different PHP classes even if the class names are identical:

``` text
S3\Email\Service\TemplateService
S3A\Email\Service\TemplateService
S3B\Email\Service\TemplateService
```

## 4. Namespace Prefix Represents Code Origin

The company prefix represents who developed or owns the implementation,
not the company currently using it.

If Company A develops:

``` text
S3A\Email\Service\TemplateService
```

and Company B later installs Company A's module, it should normally
remain:

``` text
S3A\Email\Service\TemplateService
```

It should not automatically be renamed to:

``` text
S3B\Email\Service\TemplateService
```

This preserves module origin and helps with maintenance, debugging,
upgrades, and sharing fixes.

## 5. The Filesystem Collision Problem

Namespaces prevent PHP class-name collisions, but not filesystem
collisions.

If both Company A and Company B supplied:

``` text
email/
├── controllers/
│   └── IndexController.php
├── models/
├── views/
└── Bootstrap.php
```

both would try to install into:

``` text
application/modules/email/
```

and files could overwrite each other.

Therefore, namespaces alone are not enough.

## 6. Agreed Solution: Unique ZF1 Module Names

Each company implementation receives a unique ZF1 module name:

``` text
Parent company -> email
Company A      -> emailA
Company B      -> emailB
```

The mapping is:

``` text
ZF1 Module     PHP Namespace
--------------------------------
email          S3\Email
emailA         S3A\Email
emailB         S3B\Email
```

This prevents both filesystem/module collisions and PHP class-name
collisions.

## 7. Recommended Directory Structure

``` text
application/
└── modules/
    ├── email/
    │   ├── controllers/
    │   ├── models/
    │   ├── views/
    │   ├── src/
    │   │   ├── Service/
    │   │   ├── Repository/
    │   │   ├── Validator/
    │   │   ├── Exception/
    │   │   └── Util/
    │   └── Bootstrap.php
    │
    ├── emailA/
    │   ├── controllers/
    │   ├── models/
    │   ├── views/
    │   ├── src/
    │   │   ├── Service/
    │   │   ├── Repository/
    │   │   ├── Validator/
    │   │   ├── Exception/
    │   │   └── Util/
    │   └── Bootstrap.php
    │
    └── emailB/
        ├── controllers/
        ├── models/
        ├── views/
        ├── src/
        │   ├── Service/
        │   ├── Repository/
        │   ├── Validator/
        │   ├── Exception/
        │   └── Util/
        └── Bootstrap.php
```

Traditional ZF1 directories remain available for legacy components. New
namespaced PHP classes are placed under `src/`.

## 8. Namespace-to-Directory Mapping

``` text
S3\Email\
    -> application/modules/email/src/

S3A\Email\
    -> application/modules/emailA/src/

S3B\Email\
    -> application/modules/emailB/src/
```

Example:

``` text
Class:
S3A\Email\Service\TemplateService

File:
application/modules/emailA/src/Service/TemplateService.php
```

Do not unnecessarily use `S3A\EmailA`; the company identity is already
represented by `S3A`.

## 9. Example Namespaced Service

File:

``` text
application/modules/emailA/src/Service/TemplateService.php
```

``` php
<?php

namespace S3A\Email\Service;

class TemplateService
{
    public function getMessage()
    {
        return 'Company A Email Service';
    }
}
```

Use it with:

``` php
use S3A\Email\Service\TemplateService;

$service = new TemplateService();
```

or:

``` php
$service = new \S3A\Email\Service\TemplateService();
```

## 10. Keep Existing ZF1 Autoloading

Do not replace the current ZF1 autoloading mechanism.

The target is:

``` text
Application
    |
    +-- Existing ZF1 autoloader
    |       |
    |       +-- legacy non-namespaced classes
    |
    +-- Namespace autoloader
            |
            +-- S3\Email\...
            +-- S3A\Email\...
            +-- S3B\Email\...
```

Old and new code can therefore coexist.

## 11. Namespace Autoloader

Create:

``` text
library/S3NamespaceAutoloader.php
```

``` php
<?php

class S3NamespaceAutoloader
{
    private static $prefixes = array();

    public static function register()
    {
        spl_autoload_register(
            array(__CLASS__, 'autoload')
        );
    }

    public static function addNamespace(
        $prefix,
        $baseDirectory
    ) {
        $prefix = trim($prefix, '\\') . '\\';

        $baseDirectory =
            rtrim($baseDirectory, '/\\')
            . DIRECTORY_SEPARATOR;

        self::$prefixes[$prefix] =
            $baseDirectory;
    }

    public static function autoload($class)
    {
        foreach (
            self::$prefixes as
            $prefix => $baseDirectory
        ) {
            $length = strlen($prefix);

            if (
                strncmp($prefix, $class, $length)
                !== 0
            ) {
                continue;
            }

            $relativeClass =
                substr($class, $length);

            $file =
                $baseDirectory
                . str_replace(
                    '\\',
                    DIRECTORY_SEPARATOR,
                    $relativeClass
                )
                . '.php';

            if (is_file($file)) {
                require $file;

                return true;
            }
        }

        return false;
    }
}
```

This provides a small PSR-4-style mapping while preserving the legacy
ZF1 autoloader.

## 12. Bootstrap Registration

In the main ZF1 Bootstrap:

``` php
protected function _initNamespaceAutoloader()
{
    require_once APPLICATION_PATH
        . '/../library/S3NamespaceAutoloader.php';

    S3NamespaceAutoloader::register();

    S3NamespaceAutoloader::addNamespace(
        'S3\\Email',
        APPLICATION_PATH . '/modules/email/src'
    );

    S3NamespaceAutoloader::addNamespace(
        'S3A\\Email',
        APPLICATION_PATH . '/modules/emailA/src'
    );

    S3NamespaceAutoloader::addNamespace(
        'S3B\\Email',
        APPLICATION_PATH . '/modules/emailB/src'
    );
}
```

## 13. First Autoload Test

Before migrating production business logic, create:

``` text
application/modules/emailA/src/Service/TestService.php
```

``` php
<?php

namespace S3A\Email\Service;

class TestService
{
    public function getMessage()
    {
        return 'Hello from Company A Email module';
    }
}
```

An existing ZF1 controller can call it:

``` php
<?php

use S3A\Email\Service\TestService;

class EmailA_IndexController
    extends Zend_Controller_Action
{
    public function indexAction()
    {
        $service = new TestService();

        echo $service->getMessage();
    }
}
```

This should be tested before migrating real Email services.

## 14. New Namespaced Code Can Use Legacy Classes

An existing model can remain:

``` php
class EmailA_Model_Template
{
}
```

A new service can call it:

``` php
<?php

namespace S3A\Email\Service;

class TemplateService
{
    public function find($id)
    {
        $model =
            new \EmailA_Model_Template();

        return $model->find($id);
    }
}
```

The leading `\` means the class is in PHP's global namespace.

Without it, PHP could try to resolve the class relative to
`S3A\Email\Service`.

## 15. Recommended Migration Boundary

A useful transition architecture is:

``` text
Legacy ZF1 Controller
        |
        v
New Namespaced Service
        |
        v
Legacy ZF1 Model / DbTable
```

Example:

``` php
use S3A\Email\Service\TemplateService;

class EmailA_TemplateController
    extends Zend_Controller_Action
{
    public function saveAction()
    {
        $service = new TemplateService();

        $service->save(
            $this->getRequest()->getPost()
        );
    }
}
```

Service:

``` php
<?php

namespace S3A\Email\Service;

class TemplateService
{
    public function save(array $data)
    {
        $table =
            new \EmailA_Model_DbTable_Template();

        // Validation
        // Business logic

        return $table->insert($data);
    }
}
```

This lets business logic become modernized without immediately rewriting
ZF1 controllers and models.

## 16. Company Modules Can Reuse Parent Code

Parent:

``` php
<?php

namespace S3\Email\Service;

class MailSender
{
    public function send()
    {
        // ...
    }
}
```

Company A:

``` php
<?php

namespace S3A\Email\Service;

use S3\Email\Service\MailSender;

class NotificationService
{
    public function send()
    {
        $sender = new MailSender();

        $sender->send();
    }
}
```

Relationship:

``` text
S3A Email
    |
    +-- uses
           |
           v
      S3 Email
```

## 17. Company B Can Use Company A's Module

Company B may install all of:

``` text
application/modules/
├── email/
├── emailA/
└── emailB/
```

Company A's shared module retains:

``` text
S3A\Email\...
```

Company B's own code uses:

``` text
S3B\Email\...
```

Company B code may explicitly depend on Company A:

``` php
<?php

namespace S3B\Email\Service;

use S3A\Email\Service\TemplateService;

class ImportService
{
    public function import()
    {
        $templateService =
            new TemplateService();

        // ...
    }
}
```

Do not rename Company A's shared module merely because Company B
installs it.

## 18. Do Not Use Inheritance for Company Hierarchy

The organizational relationship:

``` text
Parent Company
      |
      +-- Company A
```

does not imply:

``` text
S3A class extends S3 class
```

Use inheritance only when it is appropriate to the actual
object-oriented design.

Company ownership is code identity, not PHP inheritance.

## 19. Namespace Naming Standard

Recommended convention:

``` text
<Company>\<Feature>\<Layer>\<Class>
```

Examples:

``` text
S3\Email\Service\MailService
S3\Email\Repository\TemplateRepository

S3A\Email\Service\TemplateService
S3A\Email\Validator\AddressValidator

S3B\Email\Service\ArchiveService
```

## 20. General Module Naming Standard

The same convention can later be applied to other features:

``` text
Feature        Parent       Company A       Company B
------------------------------------------------------
Email          email        emailA          emailB
Document       document     documentA       documentB
Report         report       reportA         reportB
Workflow       workflow     workflowA       workflowB
```

Namespaces remain clean:

``` text
email       -> S3\Email
emailA      -> S3A\Email
emailB      -> S3B\Email

document    -> S3\Document
documentA   -> S3A\Document
documentB   -> S3B\Document
```

## 21. Multiple Implementations Can Coexist

Both the modules:

``` text
email
emailA
emailB
```

and classes:

``` text
S3\Email\Service\TemplateService
S3A\Email\Service\TemplateService
S3B\Email\Service\TemplateService
```

can coexist.

The module names solve physical/ZF1 identity collisions, while
namespaces solve PHP class identity collisions.

## 22. Initial Migration Rules

1.  Do not force namespace conversion of stable legacy classes.
2.  Require namespaces for new Email service/business classes.
3.  `S3` identifies parent-company code.
4.  `S3A`, `S3B`, etc. identify child-company code.
5.  Parent Email module name is `email`.
6.  Company A Email module name is `emailA`.
7.  Company B Email module name is `emailB`.
8.  New namespaced classes go under `src/`.
9.  Shared modules retain their original module/namespace identity.
10. Keep the existing ZF1 autoloader.
11. Add namespace autoloading alongside it.
12. Use `\Legacy_Class_Name` when namespaced code accesses a global
    legacy class.
13. Do not use inheritance merely to represent parent/child company
    relationships.
14. Test namespace loading with a small service before migrating
    production code.

## 23. Recommended Migration Flow

``` text
Existing ZF1 Application
          |
          v
Define company/module namespace standard
          |
          v
Add namespace autoloader
          |
          v
Test S3A\Email\Service\TestService
          |
          v
Require namespaces for new Email code
          |
          v
Keep legacy controllers/models operational
          |
          v
Move new business logic into namespaced services
          |
          v
Gradually modernize existing Email code
```

## 24. Future Improvement: Module Manifest

For the first implementation, explicit Bootstrap mappings are simple and
appropriate.

As the number of child companies/modules grows, a module manifest can
later describe each module.

Example:

``` json
{
    "name": "emailA",
    "vendor": "S3A",
    "namespace": "S3A\\Email",
    "source": "src"
}
```

Future flow:

``` text
Install emailA
      |
      v
Read module manifest
      |
      +-- module    = emailA
      +-- vendor    = S3A
      +-- namespace = S3A\Email
      +-- source    = src
      |
      v
Register namespace
      |
      v
Load module
```

This is a future enhancement, not a requirement for the first migration
stage.

## 25. Final Architecture

``` text
                         ZF1 Application
                               |
          +--------------------+--------------------+
          |                    |                    |
          v                    v                    v
        email                emailA               emailB
          |                    |                    |
          v                    v                    v
     Parent Company        Company A            Company B
          |                    |                    |
          v                    v                    v
      S3\Email             S3A\Email            S3B\Email
          |                    |                    |
          v                    v                    v
         src/                 src/                 src/
```

Meanwhile:

``` text
Existing ZF1 Application
          |
     +----+----+
     |         |
     v         v
 Legacy      New code
 no          namespaced
 namespace   classes
     |         |
     +----+----+
          |
          v
   Same application
```

This architecture provides a practical step-by-step namespace migration
while preserving the existing ZF1 application. It also supports
independently developed parent/child-company modules, allows multiple
Email implementations to coexist, and allows one company's module to be
shared with another company without losing the original module identity.
