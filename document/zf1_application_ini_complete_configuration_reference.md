# Zend Framework 1 `application.ini` Configuration Reference

> Target: Zend Framework 1.x with PHP 7.4\
> Purpose: practical reference for ZF1 application configuration.

## 1. Configuration Model

ZF1 does not have one permanently closed list of every possible
`application.ini` key. Configuration can come from:

1.  `Zend_Application`.
2.  Built-in `Zend_Application_Resource_*` plugins.
3.  Options delegated to underlying components.
4.  `phpSettings.*`.
5.  Custom application settings.
6.  Custom application-resource plugins.

Main built-in resource families:

``` text
resources.cachemanager.*
resources.db.*
resources.frontController.*
resources.layout.*
resources.locale.*
resources.log.*
resources.mail.*
resources.modules.*
resources.multidb.*
resources.navigation.*
resources.router.*
resources.session.*
resources.translate.*
resources.useragent.*
resources.view.*
```

A framework setting:

``` ini
resources.session.name = "S3_TEST1_SESSION"
```

has built-in behavior. A custom setting:

``` ini
app.security.jwt.accessTokenLifetime = 900
```

only has meaning when your own code reads it.

------------------------------------------------------------------------

## 2. Environments and Inheritance

``` ini
[production]

[staging : production]

[testing : production]

[development : production]
```

Typical `index.php`:

``` php
defined('APPLICATION_ENV')
    || define(
        'APPLICATION_ENV',
        getenv('APPLICATION_ENV')
            ? getenv('APPLICATION_ENV')
            : 'production'
    );

$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);
```

Recommended pattern:

``` ini
[production]

phpSettings.display_errors = 0
resources.session.name = "S3_SESSION"

[development : production]

phpSettings.display_errors = 1
resources.session.name = "S3_DEV_SESSION"

[test1 : development]

resources.session.name = "S3_TEST1_SESSION"

[test2 : development]

resources.session.name = "S3_TEST2_SESSION"
```

------------------------------------------------------------------------

## 3. `phpSettings.*`

Applies PHP runtime configuration where PHP permits runtime changes.

``` ini
phpSettings.display_errors = 0
phpSettings.display_startup_errors = 0
phpSettings.log_errors = 1
phpSettings.error_reporting = E_ALL
phpSettings.date.timezone = "UTC"
phpSettings.default_charset = "UTF-8"
phpSettings.memory_limit = "256M"
phpSettings.max_execution_time = 30
```

Production baseline:

``` ini
phpSettings.display_errors = 0
phpSettings.display_startup_errors = 0
phpSettings.log_errors = 1
phpSettings.error_reporting = E_ALL
```

Not every PHP INI directive can be changed at runtime.

------------------------------------------------------------------------

## 4. `includePaths.*`

``` ini
includePaths.library =
    APPLICATION_PATH "/../library"

includePaths.vendorLegacy =
    APPLICATION_PATH "/../vendor-legacy"
```

Useful for legacy ZF1 libraries and application classes.

------------------------------------------------------------------------

## 5. Bootstrap

``` ini
bootstrap.path =
    APPLICATION_PATH "/Bootstrap.php"

bootstrap.class =
    "Bootstrap"
```

Typical class:

``` php
class Bootstrap
    extends Zend_Application_Bootstrap_Bootstrap
{
}
```

Use `application.ini` for declarative configuration and Bootstrap
methods for initialization that requires logic.

------------------------------------------------------------------------

## 6. `appnamespace`

``` ini
appnamespace = "Application"
```

This is part of ZF1 application/autoloading conventions. It should not
be confused with modern PHP namespaces such as:

``` php
namespace S3\Email\Service;
```

------------------------------------------------------------------------

## 7. `autoloaderNamespaces[]`

For legacy prefix-based classes:

``` ini
autoloaderNamespaces[] = "S3_"
autoloaderNamespaces[] = "My_"
```

Examples:

``` text
S3_Controller_Api
S3_Controller_Plugin_Auth
My_Service_Test
```

This is not PSR-4 namespace mapping.

------------------------------------------------------------------------

## 8. `pluginPaths.*`

Custom application-resource plugins can be registered through plugin
paths.

Concept:

``` ini
pluginPaths.My_Application_Resource =
    APPLICATION_PATH "/../library/My/Application/Resource"
```

A custom resource such as:

``` text
My_Application_Resource_Example
```

can expose:

``` ini
resources.example.foo = "bar"
```

This extensibility is another reason there is no closed universal list
of `resources.*` keys.

------------------------------------------------------------------------

# 9. Front Controller Resource

Class:

``` text
Zend_Application_Resource_Frontcontroller
```

Prefix:

``` text
resources.frontController.*
```

Common settings:

``` ini
resources.frontController.controllerDirectory =
    APPLICATION_PATH "/controllers"

resources.frontController.moduleDirectory =
    APPLICATION_PATH "/modules"

resources.frontController.defaultModule = "default"
resources.frontController.defaultControllerName = "index"
resources.frontController.defaultAction = "index"
```

For a modular project:

``` ini
resources.frontController.moduleDirectory =
    APPLICATION_PATH "/modules"
```

Example:

``` text
application/modules/
├── default/
├── api/
├── email/
├── emailA/
└── emailB/
```

Controller plugins can also be registered, although explicit Bootstrap
registration is often clearer for authentication, CSRF, API security,
and plugin execution order:

``` php
protected function _initControllerPlugins()
{
    $front = Zend_Controller_Front::getInstance();

    $front->registerPlugin(
        new S3_Controller_Plugin_Auth()
    );

    $front->registerPlugin(
        new S3_Controller_Plugin_Csrf()
    );
}
```

------------------------------------------------------------------------

# 10. Modules Resource

Class:

``` text
Zend_Application_Resource_Modules
```

Enable:

``` ini
resources.modules[] =
```

Typical module:

``` text
application/modules/email/
├── Bootstrap.php
├── controllers/
├── models/
├── views/
└── src/
```

Bootstrap:

``` php
class Email_Bootstrap
    extends Zend_Application_Module_Bootstrap
{
}
```

Common modular configuration:

``` ini
resources.frontController.moduleDirectory =
    APPLICATION_PATH "/modules"

resources.modules[] =
```

------------------------------------------------------------------------

# 11. Database Resource

Class:

``` text
Zend_Application_Resource_Db
```

Prefix:

``` text
resources.db.*
```

MariaDB example:

``` ini
resources.db.adapter = "pdo_mysql"

resources.db.params.host = "127.0.0.1"
resources.db.params.port = "3306"
resources.db.params.username = "app_user"
resources.db.params.password = "CHANGE_ME"
resources.db.params.dbname = "application_db"
resources.db.params.charset = "utf8mb4"

resources.db.isDefaultTableAdapter = true
```

Important groups:

``` text
adapter
params.*
isDefaultTableAdapter
```

`params.*` is adapter-specific, so additional valid options depend on
the selected `Zend_Db` adapter.

Do not commit real production database passwords into source control.

------------------------------------------------------------------------

# 12. MultiDb Resource

Class:

``` text
Zend_Application_Resource_Multidb
```

Use for multiple database connections.

Example:

``` ini
resources.multidb.primary.adapter = "pdo_mysql"
resources.multidb.primary.params.host = "127.0.0.1"
resources.multidb.primary.params.dbname = "main_db"
resources.multidb.primary.params.username = "user"
resources.multidb.primary.params.password = "password"
resources.multidb.primary.isDefaultTableAdapter = true

resources.multidb.audit.adapter = "pdo_mysql"
resources.multidb.audit.params.host = "127.0.0.1"
resources.multidb.audit.params.dbname = "audit_db"
resources.multidb.audit.params.username = "audit_user"
resources.multidb.audit.params.password = "password"
```

Prefer descriptive names such as:

``` text
primary
audit
reporting
archive
```

------------------------------------------------------------------------

# 13. Session Resource

Class:

``` text
Zend_Application_Resource_Session
```

Prefix:

``` text
resources.session.*
```

The resource handles `saveHandler` specially; other options are
generally passed to `Zend_Session::setOptions()`.

## Session name

``` ini
resources.session.name = "S3_SESSION"
```

For multiple test sites:

``` ini
resources.session.name = "S3_TEST1_SESSION"
```

This is particularly useful for sites such as:

``` text
localhost:8001
localhost:8002
localhost:8003
```

because cookie isolation does not use the TCP port.

## Save path

``` ini
resources.session.save_path =
    APPLICATION_PATH "/../data/sessions"
```

## Cookie options

``` ini
resources.session.use_cookies = true
resources.session.use_only_cookies = true

resources.session.cookie_lifetime = 0
resources.session.cookie_path = "/"

resources.session.cookie_httponly = true
resources.session.cookie_secure = true
```

Optional domain:

``` ini
resources.session.cookie_domain = ".example.com"
```

Do not set a broad cookie domain unless cross-subdomain sharing is
intentional.

For plain HTTP local development:

``` ini
resources.session.cookie_secure = false
```

## Garbage collection

Historical/underlying PHP session options include:

``` ini
resources.session.gc_probability = 1
resources.session.gc_divisor = 100
resources.session.gc_maxlifetime = 1440
```

Only change these when you understand the session storage lifecycle.

## Other session-related options

Depending on ZF1/PHP support:

``` text
serialize_handler
cache_limiter
cache_expire
use_trans_sid
remember_me_seconds
```

Example:

``` ini
resources.session.remember_me_seconds = 86400
```

Do not treat this setting alone as a complete secure persistent-login
implementation.

## Custom save handler

``` ini
resources.session.saveHandler.class =
    "Zend_Session_SaveHandler_DbTable"

resources.session.saveHandler.options.name =
    "session"
```

The DbTable handler accepts table/column mapping options.

### Dependency warning

A database-backed session handler requires the database adapter first:

``` text
DB Resource
    |
    v
Default Zend_Db adapter
    |
    v
Session Resource
    |
    v
DbTable Save Handler
```

Historical ZF1 documentation contains old PHP session directives that
PHP 7.4 may have deprecated or removed. Do not copy every historical
session option blindly.

------------------------------------------------------------------------

# 14. Router Resource

Class:

``` text
Zend_Application_Resource_Router
```

Prefix:

``` text
resources.router.*
```

Login route:

``` ini
resources.router.routes.login.route = "/login"

resources.router.routes.login.defaults.module = "default"
resources.router.routes.login.defaults.controller = "auth"
resources.router.routes.login.defaults.action = "login"
```

Parameter route:

``` ini
resources.router.routes.email.route = "/email/:id"

resources.router.routes.email.defaults.module = "email"
resources.router.routes.email.defaults.controller = "index"
resources.router.routes.email.defaults.action = "view"
resources.router.routes.email.defaults.id = ""
```

API route:

``` ini
resources.router.routes.apiEmail.route = "/api/email/:id"

resources.router.routes.apiEmail.defaults.module = "api"
resources.router.routes.apiEmail.defaults.controller = "email"
resources.router.routes.apiEmail.defaults.action = "index"
```

Chain-name separator:

``` ini
resources.router.chainNameSeparator = "_"
```

Exact route options depend on the `Zend_Controller_Router_Route*`
implementation.

For large API routing tables, programmatic Bootstrap registration can be
easier to maintain.

------------------------------------------------------------------------

# 15. Layout Resource

Class:

``` text
Zend_Application_Resource_Layout
```

Prefix:

``` text
resources.layout.*
```

Typical:

``` ini
resources.layout.layout = "layout"

resources.layout.layoutPath =
    APPLICATION_PATH "/layouts/scripts"
```

Directory:

``` text
application/layouts/scripts/layout.phtml
```

Additional valid options can come from `Zend_Layout`.

For JSON API controllers, view/layout rendering is normally disabled.

------------------------------------------------------------------------

# 16. View Resource

Class:

``` text
Zend_Application_Resource_View
```

Prefix:

``` text
resources.view.*
```

Common:

``` ini
resources.view.encoding = "UTF-8"
resources.view.doctype = "HTML5"
```

Additional behavior comes from `Zend_View` and its helpers.

------------------------------------------------------------------------

# 17. Log Resource

Class:

``` text
Zend_Application_Resource_Log
```

Prefix:

``` text
resources.log.*
```

It configures `Zend_Log`, including writers and filters.

Concept:

``` text
Zend_Log
   |
   +-- Stream writer
   +-- Syslog writer
   +-- Other writer
```

For a large application, consider:

``` text
application.log
api.log
auth.log
security.log
error.log
```

Never log:

``` text
passwords
raw JWT access tokens
refresh tokens
session IDs
private keys
certificate passwords
```

Writer/filter options depend on the selected `Zend_Log` writer/filter.

------------------------------------------------------------------------

# 18. Mail Resource

Class:

``` text
Zend_Application_Resource_Mail
```

Prefix:

``` text
resources.mail.*
```

It can configure default mail behavior and transport.

Concept:

``` text
Mail Resource
    |
    +-- default sender
    |
    +-- transport
          |
          +-- SMTP
          +-- Sendmail
          +-- custom
```

SMTP-specific options can include concepts such as:

``` text
host
port
auth
username
password
ssl
```

Exact options depend on the transport.

Do not commit production SMTP credentials into source control.

------------------------------------------------------------------------

# 19. Cache Manager Resource

Class:

``` text
Zend_Application_Resource_Cachemanager
```

Prefix:

``` text
resources.cachemanager.*
```

It defines named caches.

Concept:

``` text
Cache Manager
   |
   +-- default
   +-- metadata
   +-- api
   +-- report
```

Each cache can have:

``` text
frontend
backend
frontend options
backend options
```

Exact options depend on the chosen `Zend_Cache` frontend/backend.

------------------------------------------------------------------------

# 20. Locale Resource

Class:

``` text
Zend_Application_Resource_Locale
```

Prefix:

``` text
resources.locale.*
```

Typical:

``` ini
resources.locale.default = "en_US"
resources.locale.force = true
```

The resource initializes `Zend_Locale`.

Locale caching can also be configured depending on application design.

------------------------------------------------------------------------

# 21. Translate Resource

Class:

``` text
Zend_Application_Resource_Translate
```

Prefix:

``` text
resources.translate.*
```

Conceptual example:

``` ini
resources.translate.adapter = "array"
resources.translate.data =
    APPLICATION_PATH "/languages"

resources.translate.options.scan = "directory"
```

Exact options depend on the selected `Zend_Translate` adapter.

Different translation adapters do not necessarily accept the same
options.

------------------------------------------------------------------------

# 22. Navigation Resource

Class:

``` text
Zend_Application_Resource_Navigation
```

Prefix:

``` text
resources.navigation.*
```

Conceptual menu:

``` text
Home
Email
    Templates
    Send
    History
Administration
    Users
    Permissions
```

Navigation pages may use properties such as:

``` text
label
module
controller
action
route
params
resource
privilege
pages
```

Example:

``` ini
resources.navigation.pages.home.label = "Home"
resources.navigation.pages.home.controller = "index"
resources.navigation.pages.home.action = "index"

resources.navigation.pages.email.label = "Email"
resources.navigation.pages.email.module = "email"
```

Nested `pages.*` can create submenus.

Exact properties depend on the `Zend_Navigation_Page` type.

------------------------------------------------------------------------

# 23. UserAgent Resource

Class:

``` text
Zend_Application_Resource_Useragent
```

Prefix:

``` text
resources.useragent.*
```

It initializes/configures `Zend_Http_UserAgent`.

Use it only if server-side device/user-agent detection is needed.

For a normal business application, resources such as DB, session,
router, modules, logging, view, and layout are usually more important.

------------------------------------------------------------------------

# 24. Custom Application Settings

Recommended convention:

``` ini
app.company.name = "Example Company"

app.api.version = "1"

app.upload.maxSize = 10485760

app.security.jwt.accessTokenLifetime = 900
app.security.jwt.refreshTokenLifetime = 2592000

app.email.maxAttachmentSize = 10485760
```

Distinction:

``` text
resources.session.name
        |
        v
ZF1 interprets it


app.security.jwt.accessTokenLifetime
        |
        v
Your application interprets it
```

A clean naming rule is:

``` text
resources.* = framework/resource configuration
app.*       = application-specific configuration
```

Useful custom groups:

``` text
app.api.*
app.auth.*
app.security.*
app.email.*
app.upload.*
app.notification.*
```

------------------------------------------------------------------------

# 25. Constants in INI Files

ZF1 configuration often uses constants:

``` ini
bootstrap.path =
    APPLICATION_PATH "/Bootstrap.php"
```

`APPLICATION_PATH` must exist before loading configuration.

Typical:

``` php
defined('APPLICATION_PATH')
    || define(
        'APPLICATION_PATH',
        realpath(dirname(__FILE__) . '/../application')
    );
```

------------------------------------------------------------------------

# 26. Resource Initialization Order

Some resources depend on others.

Example:

``` text
Database
   |
   v
DB-backed Session Save Handler
```

For explicit dependencies:

``` php
protected function _initSomething()
{
    $this->bootstrap('db');

    $db = $this->getResource('db');

    // ...
}
```

Do not depend on accidental resource order when a real dependency
exists.

------------------------------------------------------------------------

# 27. Configuration vs Bootstrap Code

Use INI for declarative settings:

``` ini
resources.session.name = "S3_SESSION"
```

Use Bootstrap code for logic:

``` php
protected function _initControllerPlugins()
{
    $front =
        Zend_Controller_Front::getInstance();

    $front->registerPlugin(
        new S3_Controller_Plugin_Auth()
    );
}
```

Rule:

``` text
Static configuration
       |
       v
application.ini


Conditional/complex initialization
       |
       v
Bootstrap.php
```

------------------------------------------------------------------------

# 28. Configuration Security

Recommended structure:

``` text
project/
├── application/
│   └── configs/
│       └── application.ini
├── library/
├── data/
└── public/
    └── index.php
```

Apache `DocumentRoot` should point to:

``` text
project/public
```

not the project root.

Do not expose or commit secrets such as:

``` text
database passwords
SMTP passwords
JWT signing secrets
private keys
API secrets
certificate passwords
```

Production:

``` ini
phpSettings.display_errors = 0
phpSettings.display_startup_errors = 0
phpSettings.log_errors = 1
```

------------------------------------------------------------------------

# 29. Session Security Baseline

HTTPS production:

``` ini
resources.session.use_cookies = true
resources.session.use_only_cookies = true

resources.session.cookie_httponly = true
resources.session.cookie_secure = true

resources.session.cookie_path = "/"
```

HTTP-only local test environment:

``` ini
resources.session.cookie_secure = false
```

Independent local sites:

``` ini
resources.session.name = "S3_TEST_A_SESSION"
```

and:

``` ini
resources.session.name = "S3_TEST_B_SESSION"
```

------------------------------------------------------------------------

# 30. Full Practical Baseline

``` ini
[production]

; ============================================================
; PHP
; ============================================================

phpSettings.display_errors = 0
phpSettings.display_startup_errors = 0
phpSettings.log_errors = 1
phpSettings.error_reporting = E_ALL
phpSettings.date.timezone = "UTC"
phpSettings.default_charset = "UTF-8"


; ============================================================
; Include Paths
; ============================================================

includePaths.library =
    APPLICATION_PATH "/../library"


; ============================================================
; Bootstrap
; ============================================================

bootstrap.path =
    APPLICATION_PATH "/Bootstrap.php"

bootstrap.class =
    "Bootstrap"


; ============================================================
; Application
; ============================================================

appnamespace = "Application"


; ============================================================
; Front Controller
; ============================================================

resources.frontController.moduleDirectory =
    APPLICATION_PATH "/modules"

resources.frontController.defaultModule = "default"
resources.frontController.defaultControllerName = "index"
resources.frontController.defaultAction = "index"


; ============================================================
; Modules
; ============================================================

resources.modules[] =


; ============================================================
; Database
; ============================================================

resources.db.adapter = "pdo_mysql"

resources.db.params.host = "127.0.0.1"
resources.db.params.port = "3306"

resources.db.params.dbname = "s3"
resources.db.params.username = "s3_app"
resources.db.params.password = "CHANGE_ME"

resources.db.params.charset = "utf8mb4"

resources.db.isDefaultTableAdapter = true


; ============================================================
; Session
; ============================================================

resources.session.name = "S3_SESSION"

resources.session.use_cookies = true
resources.session.use_only_cookies = true

resources.session.cookie_httponly = true
resources.session.cookie_secure = true

resources.session.cookie_path = "/"


; ============================================================
; Layout
; ============================================================

resources.layout.layout = "layout"

resources.layout.layoutPath =
    APPLICATION_PATH "/layouts/scripts"


; ============================================================
; View
; ============================================================

resources.view.encoding = "UTF-8"
resources.view.doctype = "HTML5"


; ============================================================
; Application-Specific
; ============================================================

app.api.version = "1"

app.security.jwt.accessTokenLifetime = 900
app.security.jwt.refreshTokenLifetime = 2592000


[development : production]

phpSettings.display_errors = 1
phpSettings.display_startup_errors = 1

resources.session.name = "S3_DEV_SESSION"
resources.session.cookie_secure = false


[test1 : development]

resources.session.name = "S3_TEST1_SESSION"


[test2 : development]

resources.session.name = "S3_TEST2_SESSION"
```

Passwords above are placeholders, not a recommendation to store
production secrets in source control.

------------------------------------------------------------------------

# 31. Quick Reference Table

  Configuration                   Purpose
  ------------------------------- --------------------------------------
  `phpSettings.*`                 PHP runtime settings
  `includePaths.*`                PHP include paths
  `bootstrap.path`                Bootstrap file
  `bootstrap.class`               Bootstrap class
  `appnamespace`                  ZF1 application namespace convention
  `autoloaderNamespaces[]`        Legacy prefix autoloading
  `pluginPaths.*`                 Custom application-resource paths
  `resources.frontController.*`   MVC front controller
  `resources.modules.*`           Module initialization
  `resources.db.*`                Single database
  `resources.multidb.*`           Multiple databases
  `resources.session.*`           Session configuration
  `resources.router.*`            Routing
  `resources.layout.*`            Layout
  `resources.view.*`              View
  `resources.log.*`               Logging
  `resources.mail.*`              Mail/transport
  `resources.cachemanager.*`      Named caches
  `resources.locale.*`            Locale
  `resources.translate.*`         Translation
  `resources.navigation.*`        Navigation
  `resources.useragent.*`         User-agent detection
  `app.*`                         Application-defined settings

------------------------------------------------------------------------

# 32. PHP 7.4 Compatibility Warning

ZF1 documentation spans much older PHP generations. Historical examples
may contain PHP/session directives that PHP 7.4 deprecated or removed.

Therefore:

1.  Do not copy every historical option into a PHP 7.4 application.
2.  Configure only settings you need.
3.  Verify PHP-level directives against PHP 7.4.
4.  Distinguish ZF1 resource behavior from PHP runtime behavior.
5.  Test configuration changes before production.

This is especially important for historical session hashing and
compatibility options.

------------------------------------------------------------------------

# 33. Recommended Review Priority

For a large existing ZF1 application, review configuration in this
order:

``` text
1. PHP error/security settings
2. Front Controller
3. Modules
4. Database
5. Session
6. Router/API routes
7. Authentication/controller plugins
8. View/Layout
9. Logging
10. Mail
11. Cache
12. Custom app.* settings
```

Do not enable resources merely because ZF1 provides them.

------------------------------------------------------------------------

# 34. Final Mental Model

Instead of thinking of `application.ini` as a fixed schema, follow the
target component:

``` text
application.ini
      |
      +-- phpSettings.* ------------> PHP runtime
      |
      +-- resources.session.* ------> Zend_Session
      |
      +-- resources.db.* -----------> Zend_Db
      |
      +-- resources.router.* -------> Zend_Controller_Router
      |
      +-- resources.layout.* -------> Zend_Layout
      |
      +-- resources.view.* ---------> Zend_View
      |
      +-- resources.mail.* ---------> Zend_Mail
      |
      +-- resources.cachemanager.* -> Zend_Cache
      |
      +-- app.* --------------------> Your application
```

That model explains why some resource families have many
component-specific options and why custom resources can add entirely new
configuration families.

For a mature ZF1 application, keep configuration explicit, minimal,
environment-aware, secure, and documented.
