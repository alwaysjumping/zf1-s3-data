# ZF1 Services and Controller Plugins Guide

## 1. Overview

In Zend Framework 1, **Controller, Service, Model, and Plugin have
different responsibilities**.

ZF1 has a formal controller-plugin mechanism through:

``` php
Zend_Controller_Plugin_Abstract
```

A **service layer** is mainly an application architecture pattern that
you create yourself.

``` text
Browser / DHTMLX
       |
       | HTTP request
       v
Controller Plugin
       |
       | Cross-cutting request processing
       v
Controller
       |
       | Coordinates the request
       v
Service
       |
       | Business logic
       v
Model / DbTable
       |
       v
MariaDB
```

## 2. Controller

For:

``` text
/api/email/getlist
```

ZF1 may route to:

``` php
class Api_EmailController extends Zend_Controller_Action
{
    public function getlistAction()
    {
        // ...
    }
}
```

The controller mainly handles the **HTTP request and response**. It
should ideally coordinate work rather than contain all business and
database logic.

## 3. Service

A service contains **business/application logic**. `Service` is not a
special ZF1 component like `Zend_Controller_Plugin_Abstract`; it is an
architectural layer you create.

Traditional ZF1 structure:

``` text
library/
└── My/
    └── Service/
        ├── Email.php
        └── Notification.php
```

Example:

``` php
class My_Service_Email
{
    public function getList($userId)
    {
        $table = new Application_Model_DbTable_Email();
        return $table->getEmailList($userId);
    }
}
```

Controller:

``` php
class Api_EmailController extends Zend_Controller_Action
{
    public function getlistAction()
    {
        $userId = $this->_getParam('authenticatedUserId');

        $service = new My_Service_Email();
        $result = $service->getList($userId);

        $this->_helper->json($result);
    }
}
```

Flow:

``` text
EmailController
      |
      v
EmailService
      |
      v
Email Model / DbTable
      |
      v
MariaDB
```

## 4. Service vs Model

The **Model / DbTable** primarily handles data and persistence:

``` php
class Application_Model_DbTable_Email
    extends Zend_Db_Table_Abstract
{
    protected $_name = 'email';

    public function getEmailList($userId)
    {
        // Database query.
    }
}
```

The **Service** handles business rules:

``` php
class My_Service_Email
{
    public function deleteEmail($userId, $emailId)
    {
        $emailTable = new Application_Model_DbTable_Email();

        $email = $emailTable->find($emailId)->current();

        if (!$email) {
            throw new Exception('Email not found.');
        }

        if ($email->owner_id != $userId) {
            throw new Exception('Permission denied.');
        }

        $emailTable->deleteEmail($emailId);

        return true;
    }
}
```

Think of it as:

``` text
Model / DbTable:
How do I read/write email data?

Service:
Is the user allowed to perform this operation?
What other operations should happen?
```

A service can coordinate multiple components:

``` text
EmailService
     |
     +-- UserModel
     +-- EmailModel
     +-- RecipientModel
     +-- NotificationService
     +-- AuditModel
```

## 5. ZF1 Controller Plugin

ZF1 provides:

``` php
Zend_Controller_Plugin_Abstract
```

A controller plugin participates in the **request dispatch lifecycle**.

Example:

``` php
class My_Controller_Plugin_JwtAuth
    extends Zend_Controller_Plugin_Abstract
{
}
```

Plugins are useful for logic that applies across many controllers:

``` text
Authentication
API detection
JWT validation
Request logging
Maintenance mode
Global security checks
Language selection
Security headers
```

## 6. JWT Authentication as a Plugin

Without a plugin, every API controller might repeat JWT validation.

Instead:

``` text
API Request
    |
    v
JwtAuth Plugin
    |
    | Validate JWT
    |
    +------ Invalid ------> HTTP 401
    |
    v
Controller
```

This centralizes authentication.

## 7. ZF1 Plugin Lifecycle

Important hooks include:

``` php
routeStartup()
routeShutdown()
dispatchLoopStartup()
preDispatch()
postDispatch()
dispatchLoopShutdown()
```

Simplified lifecycle:

``` text
HTTP Request
     |
     v
routeStartup()
     |
     v
ROUTING
     |
     v
routeShutdown()
     |
     v
dispatchLoopStartup()
     |
     v
preDispatch()
     |
     v
Controller Action
     |
     v
postDispatch()
     |
     v
dispatchLoopShutdown()
     |
     v
HTTP Response
```

### routeStartup()

Runs before routing. The final module/controller/action may not yet be
known.

### routeShutdown()

Runs after routing. ZF1 now knows:

``` text
module
controller
action
```

Example:

``` php
public function routeShutdown(
    Zend_Controller_Request_Abstract $request
) {
    if (strtolower($request->getModuleName()) === 'api') {
        // API request.
    }
}
```

### preDispatch()

Runs before the controller action. It is often a good place for
authentication and authorization:

``` php
public function preDispatch(
    Zend_Controller_Request_Abstract $request
) {
    if ($request->getModuleName() != 'api') {
        return;
    }

    // Validate JWT...
}
```

``` text
Routing
   |
   v
preDispatch()
   |
   +-- JWT valid?
   +-- User authenticated?
   +-- Endpoint allowed?
   |
   v
Controller Action
```

### postDispatch()

Runs after a controller action and can be useful for certain logging or
response-processing tasks.

## 8. Service and Plugin Together

Possible structure:

``` text
library/
└── My/
    ├── Jwt.php
    ├── Service/
    │   ├── Email.php
    │   └── Notification.php
    └── Controller/
        └── Plugin/
            └── JwtAuth.php
```

JWT helper:

``` php
class My_Jwt
{
    public function createToken($userId)
    {
        // firebase/php-jwt
    }

    public function validateToken($token)
    {
        // firebase/php-jwt
    }
}
```

Plugin:

``` php
class My_Controller_Plugin_JwtAuth
    extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(
        Zend_Controller_Request_Abstract $request
    ) {
        // Is this API?
        // Is endpoint public?
        // Get Authorization header.
        // Call My_Jwt.
        // Set authenticated user.
    }
}
```

Service:

``` php
class My_Service_Email
{
    public function getList($userId)
    {
        // Email business logic.
    }
}
```

Controller:

``` php
class Api_EmailController extends Zend_Controller_Action
{
    public function getlistAction()
    {
        $userId = $this->_getParam('authenticatedUserId');

        $service = new My_Service_Email();
        $result = $service->getList($userId);

        $this->_helper->json($result);
    }
}
```

## 9. Complete Request Flow

``` text
Browser
   |
   | GET /api/email/getlist
   | Authorization: Bearer ...
   v
Apache
   |
   v
public/index.php
   |
   v
ZF1 Front Controller
   |
   v
Router
   |
   +-- module = api
   +-- controller = email
   +-- action = getlist
   |
   v
JwtAuth Plugin
   |
   +-- Is module "api"? YES
   +-- Is public endpoint? NO
   +-- Bearer token exists? YES
   |
   v
My_Jwt
   |
   v
firebase/php-jwt
   |
   +-- Signature valid
   +-- Token not expired
   +-- Claims valid
   |
   v
userId = 100
   |
   v
EmailController::getlistAction()
   |
   v
EmailService::getList(100)
   |
   v
Email Model / DbTable
   |
   v
MariaDB
   |
   v
JSON Response
   |
   v
Browser
```

## 10. Component Responsibilities

  Component         Main Responsibility                     Example
  ----------------- --------------------------------------- -------------------
  Controller        HTTP request/response coordination      `EmailController`
  Service           Business/application logic              `EmailService`
  Model / DbTable   Data/database operations                `EmailTable`
  Plugin            Cross-cutting request lifecycle logic   `JwtAuthPlugin`
  Library/helper    Reusable technical functionality        `My_Jwt`

Examples:

``` text
JWT verification
       ↓
Plugin + My_Jwt

"Can this user delete this email?"
       ↓
Service

DELETE FROM email ...
       ↓
Model / DbTable

Read request and return JSON
       ↓
Controller
```

## 11. How to Decide Where Code Belongs

Ask these questions:

1.  **Does this need to happen automatically for many HTTP requests?**\
    Consider a **Plugin**: JWT authentication, API logging, global
    security checks.

2.  **Is this a business operation that controllers, CLI commands, cron
    jobs, or other services might need?**\
    Consider a **Service**: `sendEmail()`, `deleteDocument()`,
    `approveDocument()`, `createNotification()`.

3.  **Is this mainly reading or writing application data?**\
    Use a **Model / DbTable**: `findEmailById()`, `insertEmail()`,
    `updateEmail()`.

4.  **Is this mainly translating an HTTP request into an application
    operation and producing an HTTP response?**\
    Use a **Controller**.

## 12. Recommended JWT/API Architecture

``` text
ApiJwtAuthPlugin
       |
       | Handles request authentication
       v
My_Jwt
       |
       | Handles JWT technology
       v
firebase/php-jwt


EmailController
       |
       | Handles HTTP API
       v
EmailService
       |
       | Handles email business rules
       v
Email Model / DbTable
       |
       | Handles persistence
       v
MariaDB
```

## 13. Gradual Adoption

You do not need to rewrite an existing ZF1 application.

``` text
Existing ZF1 Project
        |
        +-- Keep existing controllers
        +-- Keep existing models
        +-- Add services for new/complex business logic
        +-- Add plugins for application-wide request behavior
```

A practical first step is to use a controller plugin for JWT
authentication and gradually move complex business operations from
controllers into service classes.
