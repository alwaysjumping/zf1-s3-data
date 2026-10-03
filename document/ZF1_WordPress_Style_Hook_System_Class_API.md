# ZF1 WordPress-Style Hook System — `S3_Hook` Class API

> **API rule:** This design uses the global `S3_Hook` class with static methods only. No standalone global hook wrapper functions are declared; all public hook operations are static methods on `S3_Hook`.


## 1. Overview

This document describes a WordPress-style **Action** and **Filter** hook
system for a Zend Framework 1 (ZF1) application using PHP 7.4 and
MariaDB.

The design supports dynamically installed modules without requiring
every module class to be instantiated during every request.

### Goals

-   Support `S3_Hook::do_action()`-style hooks.
-   Support `S3_Hook::apply_filters()`-style hooks.
-   Store persistent hook registrations in MariaDB.
-   Allow modules to declare hooks in `config.json`.
-   Generate a PHP hook cache when modules are installed, removed,
    enabled, or disabled.
-   Avoid querying MariaDB for hook discovery during normal requests.
-   Lazy-load hook handler classes only when a hook executes.
-   Support hook priorities.
-   Support module enable/disable.
-   Keep hook registration out of service constructors.

------------------------------------------------------------------------

## 2. Architecture

``` text
                 MODULE INSTALLATION
                        |
                        v
                   config.json
                        |
                        v
                 Module Installer
                        |
              +---------+---------+
              |                   |
              v                   v
          modules DB         module_hooks DB
              |                   |
              +---------+---------+
                        |
                        v
                  rebuild cache
                        |
                        v
              data/cache/hooks.php


                  NORMAL REQUEST
                        |
                        v
                      ZF1
                   Bootstrap
                        |
                        v
                 require hooks.php
                        |
                        v
                   Hook Registry
                        |
                        v
                    Controller
                        |
                        v
                     Service
                        |
             +----------+----------+
             |                     |
             v                     v
        S3_Hook::do_action()          S3_Hook::apply_filters()
             |                     |
             +----------+----------+
                        |
                        v
             Find required handlers
                        |
                        v
              Lazy-load handler
                        |
                        v
                 Execute method
```

The database is the authoritative persistent registry. The generated PHP
cache is used during normal requests for performance.

------------------------------------------------------------------------

## 3. Database Table

``` sql
CREATE TABLE module_hooks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_name     VARCHAR(100) NOT NULL,
    hook_name       VARCHAR(150) NOT NULL,
    hook_type       ENUM('action', 'filter') NOT NULL,
    handler_class   VARCHAR(255) NOT NULL,
    handler_method  VARCHAR(100) NOT NULL,
    priority        INT NOT NULL DEFAULT 10,
    enabled         TINYINT(1) NOT NULL DEFAULT 1,

    PRIMARY KEY (id),

    INDEX idx_hook (
        hook_name,
        hook_type,
        enabled,
        priority
    ),

    INDEX idx_module (module_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Example registrations:

``` sql
INSERT INTO module_hooks
(
    module_name,
    hook_name,
    hook_type,
    handler_class,
    handler_method,
    priority
)
VALUES
(
    'notification',
    'document.after_save',
    'action',
    'Notification_Hook',
    'documentSaved',
    10
),
(
    'audit',
    'document.after_save',
    'action',
    'Audit_Hook',
    'documentSaved',
    20
),
(
    'security',
    'document.title',
    'filter',
    'Security_Hook',
    'filterDocumentTitle',
    10
);
```

------------------------------------------------------------------------

## 4. Recommended Project Structure

``` text
project/
|
+-- application/
|   +-- Bootstrap.php
|   |
|   +-- controllers/
|   +-- models/
|   +-- services/
|   |
|   +-- modules/
|       +-- notification/
|       |   +-- controllers/
|       |   +-- models/
|       |   +-- services/
|       |   +-- hooks/
|       |       +-- Notification.php
|       |
|       +-- audit/
|       |   +-- hooks/
|       |       +-- Audit.php
|       |
|       +-- security/
|           +-- hooks/
|               +-- Security.php
|
+-- library/
|   +-- App/
|       +-- hooks.php
|       +-- Hook/
|           +-- Manager.php
|           +-- Registry.php
|           +-- Cache.php
|
+-- data/
    +-- cache/
        +-- hooks.php
```

------------------------------------------------------------------------

## 5. Hook Registry

Create:

`library/App/Hook/Registry.php`

``` php
<?php

class App_Hook_Registry
{
    protected $actions = array();
    protected $filters = array();

    public function registerAction(
        $name,
        $class,
        $method,
        $priority = 10,
        $module = null
    ) {
        $this->register(
            'action',
            $name,
            $class,
            $method,
            $priority,
            $module
        );
    }

    public function registerFilter(
        $name,
        $class,
        $method,
        $priority = 10,
        $module = null
    ) {
        $this->register(
            'filter',
            $name,
            $class,
            $method,
            $priority,
            $module
        );
    }

    protected function register(
        $type,
        $name,
        $class,
        $method,
        $priority,
        $module
    ) {
        $item = array(
            'module'   => $module,
            'class'    => $class,
            'method'   => $method,
            'priority' => (int) $priority
        );

        if ($type === 'action') {
            if (!isset($this->actions[$name])) {
                $this->actions[$name] = array();
            }

            $this->actions[$name][] = $item;
            return;
        }

        if (!isset($this->filters[$name])) {
            $this->filters[$name] = array();
        }

        $this->filters[$name][] = $item;
    }

    public function loadArray(array $config)
    {
        if (!empty($config['actions'])) {
            foreach ($config['actions'] as $name => $hooks) {
                foreach ($hooks as $hook) {
                    $this->registerAction(
                        $name,
                        $hook['class'],
                        $hook['method'],
                        $hook['priority'],
                        isset($hook['module']) ? $hook['module'] : null
                    );
                }
            }
        }

        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $name => $hooks) {
                foreach ($hooks as $hook) {
                    $this->registerFilter(
                        $name,
                        $hook['class'],
                        $hook['method'],
                        $hook['priority'],
                        isset($hook['module']) ? $hook['module'] : null
                    );
                }
            }
        }

        $this->sort();

        return $this;
    }

    public function sort()
    {
        foreach ($this->actions as &$hooks) {
            usort($hooks, array($this, 'comparePriority'));
        }

        foreach ($this->filters as &$hooks) {
            usort($hooks, array($this, 'comparePriority'));
        }
    }

    public function comparePriority($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }

        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

    public function getActions($name)
    {
        return isset($this->actions[$name])
            ? $this->actions[$name]
            : array();
    }

    public function getFilters($name)
    {
        return isset($this->filters[$name])
            ? $this->filters[$name]
            : array();
    }

    public function hasAction($name)
    {
        return !empty($this->actions[$name]);
    }

    public function hasFilter($name)
    {
        return !empty($this->filters[$name]);
    }
}
```

------------------------------------------------------------------------

## 6. Hook Manager

Create:

`library/App/Hook/Manager.php`

``` php
<?php

class App_Hook_Manager
{
    protected $registry;
    protected $instances = array();

    public function __construct(App_Hook_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function doAction($name)
    {
        $args = func_get_args();
        array_shift($args);

        $hooks = $this->registry->getActions($name);

        foreach ($hooks as $hook) {
            $this->executeAction($hook, $args);
        }
    }

    protected function executeAction(array $hook, array $args)
    {
        $object = $this->getHandlerInstance($hook['class']);
        $method = $hook['method'];

        if (!method_exists($object, $method)) {
            throw new RuntimeException(
                'Hook method not found: '
                . $hook['class']
                . '::'
                . $method
            );
        }

        call_user_func_array(
            array($object, $method),
            $args
        );
    }

    public function applyFilters($name, $value)
    {
        $args = func_get_args();

        array_shift($args);
        array_shift($args);

        $hooks = $this->registry->getFilters($name);

        foreach ($hooks as $hook) {
            $object = $this->getHandlerInstance($hook['class']);
            $method = $hook['method'];

            if (!method_exists($object, $method)) {
                throw new RuntimeException(
                    'Hook method not found: '
                    . $hook['class']
                    . '::'
                    . $method
                );
            }

            $callbackArgs = array_merge(
                array($value),
                $args
            );

            $value = call_user_func_array(
                array($object, $method),
                $callbackArgs
            );
        }

        return $value;
    }

    protected function getHandlerInstance($class)
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (!class_exists($class)) {
            throw new RuntimeException(
                'Hook handler class not found: ' . $class
            );
        }

        // Lazy creation: only instantiate when the hook executes.
        $this->instances[$class] = new $class();

        return $this->instances[$class];
    }

    public function hasAction($name)
    {
        return $this->registry->hasAction($name);
    }

    public function hasFilter($name)
    {
        return $this->registry->hasFilter($name);
    }
}
```

------------------------------------------------------------------------

## 7. Hook Cache

Create:

`library/App/Hook/Cache.php`

``` php
<?php

class App_Hook_Cache
{
    protected $db;
    protected $cacheFile;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        $cacheFile
    ) {
        $this->db = $db;
        $this->cacheFile = $cacheFile;
    }

    public function rebuild()
    {
        $hooks = $this->loadHooksFromDatabase();

        $content = "<?php\n\n";
        $content .= "return ";
        $content .= var_export($hooks, true);
        $content .= ";\n";

        $this->writeAtomic($content);

        return true;
    }

    public function load()
    {
        if (!file_exists($this->cacheFile)) {
            $this->rebuild();
        }

        $hooks = require $this->cacheFile;

        if (!is_array($hooks)) {
            throw new RuntimeException(
                'Invalid hook cache file.'
            );
        }

        return $hooks;
    }

    protected function loadHooksFromDatabase()
    {
        $result = array(
            'actions' => array(),
            'filters' => array()
        );

        $select = $this->db
            ->select()
            ->from(
                'module_hooks',
                array(
                    'module_name',
                    'hook_name',
                    'hook_type',
                    'handler_class',
                    'handler_method',
                    'priority'
                )
            )
            ->where('enabled = ?', 1)
            ->order(array(
                'hook_name ASC',
                'priority ASC',
                'id ASC'
            ));

        $rows = $this->db->fetchAll($select);

        foreach ($rows as $row) {
            if ($row['hook_type'] === 'action') {
                $section = 'actions';
            } elseif ($row['hook_type'] === 'filter') {
                $section = 'filters';
            } else {
                continue;
            }

            $hookName = $row['hook_name'];

            if (!isset($result[$section][$hookName])) {
                $result[$section][$hookName] = array();
            }

            $result[$section][$hookName][] = array(
                'module'   => $row['module_name'],
                'class'    => $row['handler_class'],
                'method'   => $row['handler_method'],
                'priority' => (int) $row['priority']
            );
        }

        return $result;
    }

    protected function writeAtomic($content)
    {
        $directory = dirname($this->cacheFile);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true)) {
                throw new RuntimeException(
                    'Cannot create hook cache directory: ' . $directory
                );
            }
        }

        $tempFile = tempnam($directory, 'hooks_');

        if ($tempFile === false) {
            throw new RuntimeException(
                'Cannot create temporary hook cache file.'
            );
        }

        try {
            if (file_put_contents(
                $tempFile,
                $content,
                LOCK_EX
            ) === false) {
                throw new RuntimeException(
                    'Cannot write hook cache.'
                );
            }

            // Windows requires special care when the destination exists.
            if (file_exists($this->cacheFile)) {
                if (!unlink($this->cacheFile)) {
                    throw new RuntimeException(
                        'Cannot remove old hook cache.'
                    );
                }
            }

            if (!rename($tempFile, $this->cacheFile)) {
                throw new RuntimeException(
                    'Cannot replace hook cache.'
                );
            }

        } catch (Exception $e) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            throw $e;
        }
    }

    public function clear()
    {
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    public function exists()
    {
        return file_exists($this->cacheFile);
    }
}
```

### Windows Note

The `unlink()` followed by `rename()` approach can briefly leave the
destination cache file absent. For a production system with concurrent
Apache requests, consider a stronger locking strategy or versioned cache
files.

------------------------------------------------------------------------

## 8. Public Hook API

The project does **not** declare global hook functions. Developers call the global `S3_Hook` class directly:

```php
S3_Hook::add_action(...);
S3_Hook::do_action(...);
S3_Hook::add_filter(...);
S3_Hook::apply_filters(...);
```

The implementation is provided by `library/S3/Hook.php`.

------------------------------------------------------------------------

## 9. ZF1 Bootstrap Integration

In `application/Bootstrap.php`:

``` php
protected function _initHookManager()
{
    $this->bootstrap('db');

    $db = $this->getResource('db');

    require_once APPLICATION_PATH
        . '/../library/App/hooks.php';

    $cacheFile =
        APPLICATION_PATH
        . '/../data/cache/hooks.php';

    $cache = new App_Hook_Cache(
        $db,
        $cacheFile
    );

    $config = $cache->load();

    $registry = new App_Hook_Registry();
    $registry->loadArray($config);

    $manager = new App_Hook_Manager(
        $registry
    );

    Zend_Registry::set(
        'hookManager',
        $manager
    );

    Zend_Registry::set(
        'hookCache',
        $cache
    );

    return $manager;
}
```

Normal requests now load the PHP cache instead of querying
`module_hooks`.

------------------------------------------------------------------------

## 10. Action Example

Suppose a document service saves a document:

``` php
class Document_Service
{
    public function save(array $data)
    {
        S3_Hook::do_action(
            'document.before_save',
            $data
        );

        $db = Zend_Db_Table::getDefaultAdapter();

        $db->insert(
            'documents',
            $data
        );

        $documentId = $db->lastInsertId();

        S3_Hook::do_action(
            'document.after_save',
            $documentId,
            $data
        );

        return $documentId;
    }
}
```

The core service does not know which modules listen to
`document.after_save`.

------------------------------------------------------------------------

## 11. Notification Hook Handler

Example:

``` php
class Notification_Hook
{
    public function documentSaved($documentId, $data)
    {
        $service = new Notification_Service();

        $service->sendDocumentSavedNotification(
            $documentId
        );
    }
}
```

No registration is performed in the constructor.

The handler is instantiated only if `document.after_save` actually runs.

------------------------------------------------------------------------

## 12. Audit Hook Handler

``` php
class Audit_Hook
{
    public function documentSaved($documentId, $data)
    {
        $db = Zend_Db_Table::getDefaultAdapter();

        $db->insert(
            'audit_log',
            array(
                'event_name' => 'document.saved',
                'object_id'  => $documentId,
                'created_at' => date('Y-m-d H:i:s')
            )
        );
    }
}
```

With priorities:

``` text
document.after_save
        |
        +-- Priority 10
        |      Notification_Hook::documentSaved()
        |
        +-- Priority 20
               Audit_Hook::documentSaved()
```

------------------------------------------------------------------------

## 13. Filter Example

Application:

``` php
$title = S3_Hook::apply_filters(
    'document.title',
    $document['title'],
    $document
);
```

Handler:

``` php
class Security_Hook
{
    public function filterDocumentTitle(
        $title,
        $document
    ) {
        if (!empty($document['confidential'])) {
            return '[CONFIDENTIAL] ' . $title;
        }

        return $title;
    }
}
```

The value can pass through multiple filters:

``` text
"Financial Report"
        |
        v
Security Filter
        |
        v
"[CONFIDENTIAL] Financial Report"
        |
        v
Department Filter
        |
        v
"[HR] [CONFIDENTIAL] Financial Report"
```

------------------------------------------------------------------------

## 14. Module `config.json`

A module can declare its hooks in its package configuration:

``` json
{
    "module": {
        "name": "notification",
        "version": "2.4.1"
    },

    "hooks": [
        {
            "type": "action",
            "name": "document.after_save",
            "class": "Notification_Hook",
            "method": "documentSaved",
            "priority": 10
        },
        {
            "type": "action",
            "name": "user.after_create",
            "class": "Notification_Hook",
            "method": "userCreated",
            "priority": 10
        }
    ]
}
```

The module installer reads this configuration and registers the hooks in
`module_hooks`.

------------------------------------------------------------------------

## 15. Registering Hooks During Installation

``` php
protected function registerHooks(
    $moduleName,
    array $hooks
) {
    foreach ($hooks as $hook) {

        $this->validateHook($hook);

        $this->db->insert(
            'module_hooks',
            array(
                'module_name'    => $moduleName,
                'hook_name'      => $hook['name'],
                'hook_type'      => $hook['type'],
                'handler_class'  => $hook['class'],
                'handler_method' => $hook['method'],
                'priority'       => isset($hook['priority'])
                    ? (int) $hook['priority']
                    : 10,
                'enabled'        => 1
            )
        );
    }
}
```

------------------------------------------------------------------------

## 16. Hook Validation

Only trusted module configuration should be allowed to define executable
PHP callbacks.

``` php
protected function validateHook(array $hook)
{
    $required = array(
        'type',
        'name',
        'class',
        'method'
    );

    foreach ($required as $field) {
        if (empty($hook[$field])) {
            throw new InvalidArgumentException(
                'Missing hook field: ' . $field
            );
        }
    }

    if (!in_array(
        $hook['type'],
        array('action', 'filter'),
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid hook type: ' . $hook['type']
        );
    }

    if (!preg_match(
        '/^[a-z0-9._-]+$/',
        $hook['name']
    )) {
        throw new InvalidArgumentException(
            'Invalid hook name.'
        );
    }

    if (!preg_match(
        '/^[A-Za-z_][A-Za-z0-9_]*$/',
        $hook['class']
    )) {
        throw new InvalidArgumentException(
            'Invalid hook class.'
        );
    }

    if (!preg_match(
        '/^[A-Za-z_][A-Za-z0-9_]*$/',
        $hook['method']
    )) {
        throw new InvalidArgumentException(
            'Invalid hook method.'
        );
    }
}
```

------------------------------------------------------------------------

## 17. Module Installation Flow

``` php
public function install(array $config)
{
    $this->validateConfig($config);

    $moduleName = $config['module']['name'];

    $this->db->beginTransaction();

    try {
        $this->registerModule($config);

        if (!empty($config['hooks'])) {
            $this->registerHooks(
                $moduleName,
                $config['hooks']
            );
        }

        $this->db->commit();

    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }

    // Rebuild only after successful commit.
    $this->hookCache->rebuild();
}
```

Flow:

``` text
BEGIN TRANSACTION
       |
       +-- Install/register module
       |
       +-- Register hooks
       |
       v
     COMMIT
       |
       v
Rebuild hook cache
```

------------------------------------------------------------------------

## 18. Module Uninstallation

``` php
public function uninstall($moduleName)
{
    $this->db->beginTransaction();

    try {
        $this->db->delete(
            'module_hooks',
            array(
                'module_name = ?' => $moduleName
            )
        );

        $this->unregisterModule(
            $moduleName
        );

        $this->db->commit();

    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }

    $this->hookCache->rebuild();
}
```

All hooks belonging to the removed module disappear from the generated
cache.

------------------------------------------------------------------------

## 19. Module Enable and Disable

It is better to preserve hook registrations when a module is disabled.

Example module table:

``` text
modules
----------------
id
name
version
enabled
```

The cache query can join `modules`:

``` php
$select = $this->db
    ->select()
    ->from(
        array('h' => 'module_hooks'),
        array(
            'module_name',
            'hook_name',
            'hook_type',
            'handler_class',
            'handler_method',
            'priority'
        )
    )
    ->join(
        array('m' => 'modules'),
        'm.name = h.module_name',
        array()
    )
    ->where('h.enabled = ?', 1)
    ->where('m.enabled = ?', 1)
    ->order(array(
        'h.hook_name ASC',
        'h.priority ASC',
        'h.id ASC'
    ));
```

Disable:

``` php
public function disableModule($moduleName)
{
    $this->db->update(
        'modules',
        array('enabled' => 0),
        array('name = ?' => $moduleName)
    );

    $this->hookCache->rebuild();
}
```

Enable:

``` php
public function enableModule($moduleName)
{
    $this->db->update(
        'modules',
        array('enabled' => 1),
        array('name = ?' => $moduleName)
    );

    $this->hookCache->rebuild();
}
```

------------------------------------------------------------------------

## 20. Generated Hook Cache Example

`data/cache/hooks.php` may look like:

``` php
<?php

return array(
    'actions' => array(
        'document.after_save' => array(
            array(
                'module'   => 'notification',
                'class'    => 'Notification_Hook',
                'method'   => 'documentSaved',
                'priority' => 10
            ),
            array(
                'module'   => 'audit',
                'class'    => 'Audit_Hook',
                'method'   => 'documentSaved',
                'priority' => 20
            )
        )
    ),

    'filters' => array(
        'document.title' => array(
            array(
                'module'   => 'security',
                'class'    => 'Security_Hook',
                'method'   => 'filterDocumentTitle',
                'priority' => 10
            )
        )
    )
);
```

Normal requests simply execute:

``` php
$config = require $cacheFile;
```

------------------------------------------------------------------------

## 21. Cache Rebuild Rules

Do not rebuild the hook cache on every request.

Rebuild it only when the registry changes:

``` text
Install module
      |
      +--> rebuildHookCache()

Uninstall module
      |
      +--> rebuildHookCache()

Enable module
      |
      +--> rebuildHookCache()

Disable module
      |
      +--> rebuildHookCache()

Change hook configuration
      |
      +--> rebuildHookCache()
```

------------------------------------------------------------------------

## 22. Error Handling

Hook failures need a deliberate policy.

A notification hook may be non-critical, while authorization or
validation hooks may be critical.

Example logging approach:

``` php
protected function logHookError(
    array $hook,
    Exception $e
) {
    if (!Zend_Registry::isRegistered('logger')) {
        return;
    }

    $logger = Zend_Registry::get('logger');

    $logger->err(
        sprintf(
            'Hook error [%s::%s]: %s',
            $hook['class'],
            $hook['method'],
            $e->getMessage()
        )
    );
}
```

A future version can add metadata such as:

``` json
{
    "type": "action",
    "name": "document.after_save",
    "class": "Notification_Hook",
    "method": "documentSaved",
    "priority": 10,
    "critical": false
}
```

Then:

-   `critical = false`: log the error and continue.
-   `critical = true`: rethrow the exception and stop processing.

------------------------------------------------------------------------

## 23. Why Hooks Should Not Be Registered in Constructors

Avoid:

``` php
class Notification_Service
{
    public function __construct()
    {
        S3_Hook::add_action(
            'document.after_save',
            array($this, 'documentSaved')
        );
    }
}
```

This requires creating the object before the application can discover
its hook.

With many modules:

``` text
Request
  |
  +-- new Notification_Service()
  +-- new Audit_Service()
  +-- new Workflow_Service()
  +-- new Email_Service()
  +-- new Report_Service()
  +-- ...
```

Most objects may never be needed by the request.

The registry/cache architecture instead performs:

``` text
Request
   |
   v
Load lightweight hook metadata
   |
   v
Application executes
   |
   v
document.after_save occurs
   |
   v
Find only handlers for that hook
   |
   v
Instantiate only those handlers
```

This is more suitable for a modular ZF1 application.

------------------------------------------------------------------------

## 24. Recommended Hook Naming Convention

Use predictable lowercase names:

``` text
application.before_dispatch
application.after_dispatch

user.before_create
user.after_create
user.before_update
user.after_update
user.before_delete
user.after_delete

document.before_save
document.after_save
document.before_delete
document.after_delete

auth.before_login
auth.after_login
auth.login_failed
auth.logout

api.before_request
api.after_request
```

A consistent naming convention makes module integration easier to
understand and document.

------------------------------------------------------------------------

## 25. Final Design Principles

The hook system separates four responsibilities:

``` text
Core Application
      |
      | Defines WHEN an event occurs
      v
S3_Hook::do_action('document.after_save')


Module config.json
      |
      | Declares WHAT the module listens to
      v
Hook declaration


Module Installer
      |
      | Records WHO handles the event
      v
module_hooks


Hook Cache + Manager
      |
      | Finds and executes handlers efficiently
      v
Notification_Hook::documentSaved()
```

### Recommended Rules

1.  The core application defines stable hook points.
2.  Modules declare their hook handlers in `config.json`.
3.  The module installer validates and persists registrations.
4.  MariaDB is the authoritative persistent registry.
5.  `hooks.php` is the optimized runtime representation.
6.  Rebuild the cache only when module/hook configuration changes.
7.  Never instantiate all module classes merely to register hooks.
8.  Lazy-load handler classes when their hook actually executes.
9.  Use priorities to define deterministic execution order.
10. Treat callback metadata as trusted configuration and validate it.
11. Decide explicitly which hooks are critical and which may fail
    independently.
12. Use safe cache replacement/locking for concurrent production
    requests.

This provides WordPress-style extensibility while remaining appropriate
for a dynamically installed ZF1 module architecture.

---

## 26. Unit Testing the Hook System

The hook infrastructure should be tested separately from individual business modules. A useful test structure is:

```text
tests/
├── bootstrap.php
├── library/
│   └── App/
│       └── Hook/
│           ├── ManagerTest.php
│           └── RegistryTest.php
├── modules/
│   ├── NotificationHookTest.php
│   └── AuditHookTest.php
└── integration/
    └── DocumentHookTest.php
```

The recommended test layers are:

```text
1. Hook Manager / Registry
   ├── action execution
   ├── action arguments
   ├── filter transformation
   ├── filter chaining
   ├── priority
   └── missing hooks

2. Individual Hook Classes
   ├── Notification_Hook
   ├── Audit_Hook
   └── other module hooks

3. Integration
   └── application service -> S3_Hook::do_action()/S3_Hook::apply_filters() -> handler

4. Hook Compiler / Cache
   ├── config/DB metadata
   ├── enabled/disabled modules
   └── generated hooks.php
```

### 26.1 Make `App_Hook_Manager` Easier to Test

The manager normally creates a handler lazily with `new $class()`. Add a small injection method so unit tests can provide a fake handler:

```php
public function setHandlerInstance($class, $instance)
{
    $this->instances[$class] = $instance;

    return $this;
}
```

This does not change normal production behavior. It simply allows a unit test to inject a controlled object.

### 26.2 Fake Action Handler

```php
class Test_DocumentHook
{
    public $called = false;
    public $documentId = null;
    public $data = null;
    public $userId = null;

    public function documentSaved(
        $documentId,
        $data = null,
        $userId = null
    ) {
        $this->called = true;
        $this->documentId = $documentId;
        $this->data = $data;
        $this->userId = $userId;
    }
}
```

### 26.3 Test That an Action Executes

```php
class App_Hook_ManagerTest
    extends PHPUnit_Framework_TestCase
{
    public function testActionIsExecuted()
    {
        $registry = new App_Hook_Registry();

        $registry->registerAction(
            'document.after_save',
            'Test_DocumentHook',
            'documentSaved',
            10
        );

        $manager = new App_Hook_Manager($registry);
        $handler = new Test_DocumentHook();

        $manager->setHandlerInstance(
            'Test_DocumentHook',
            $handler
        );

        $manager->doAction(
            'document.after_save',
            123
        );

        $this->assertTrue($handler->called);
        $this->assertEquals(123, $handler->documentId);
    }
}
```

This verifies:

```text
register action
      |
      v
doAction()
      |
      v
find handler
      |
      v
execute method
      |
      v
correct argument received
```

### 26.4 Test Multiple Action Arguments

```php
public function testActionReceivesArguments()
{
    $registry = new App_Hook_Registry();

    $registry->registerAction(
        'document.after_save',
        'Test_DocumentHook',
        'documentSaved',
        10
    );

    $manager = new App_Hook_Manager($registry);
    $handler = new Test_DocumentHook();

    $manager->setHandlerInstance(
        'Test_DocumentHook',
        $handler
    );

    $data = array(
        'title' => 'Test Document'
    );

    $manager->doAction(
        'document.after_save',
        123,
        $data,
        50
    );

    $this->assertEquals(123, $handler->documentId);
    $this->assertEquals(
        'Test Document',
        $handler->data['title']
    );
    $this->assertEquals(50, $handler->userId);
}
```

### 26.5 Test a Filter

Fake filter:

```php
class Test_TitleFilter
{
    public function addPrefix($title)
    {
        return '[CONFIDENTIAL] ' . $title;
    }
}
```

Test:

```php
public function testFilterChangesValue()
{
    $registry = new App_Hook_Registry();

    $registry->registerFilter(
        'document.title',
        'Test_TitleFilter',
        'addPrefix',
        10
    );

    $manager = new App_Hook_Manager($registry);
    $filter = new Test_TitleFilter();

    $manager->setHandlerInstance(
        'Test_TitleFilter',
        $filter
    );

    $result = $manager->applyFilters(
        'document.title',
        'Secret Report'
    );

    $this->assertEquals(
        '[CONFIDENTIAL] Secret Report',
        $result
    );
}
```

### 26.6 Test Filter Chaining and Priority

```php
class Test_FilterA
{
    public function filter($value)
    {
        return $value . '-A';
    }
}

class Test_FilterB
{
    public function filter($value)
    {
        return $value . '-B';
    }
}
```

Test:

```php
public function testMultipleFiltersChainInPriorityOrder()
{
    $registry = new App_Hook_Registry();

    $registry->registerFilter(
        'test.value',
        'Test_FilterA',
        'filter',
        20
    );

    $registry->registerFilter(
        'test.value',
        'Test_FilterB',
        'filter',
        10
    );

    $manager = new App_Hook_Manager($registry);

    $manager->setHandlerInstance(
        'Test_FilterA',
        new Test_FilterA()
    );

    $manager->setHandlerInstance(
        'Test_FilterB',
        new Test_FilterB()
    );

    $result = $manager->applyFilters(
        'test.value',
        'START'
    );

    $this->assertEquals(
        'START-A-B',
        $result
    );
}
```

This test assumes the registry/manager convention that a larger priority value executes first. Keep the convention consistent throughout the implementation and tests.

### 26.7 Test Action Priority

```php
class Test_OrderHook
{
    public $calls = array();

    public function first()
    {
        $this->calls[] = 'first';
    }

    public function second()
    {
        $this->calls[] = 'second';
    }

    public function third()
    {
        $this->calls[] = 'third';
    }
}
```

Test:

```php
public function testActionPriority()
{
    $registry = new App_Hook_Registry();

    $registry->registerAction(
        'test.action',
        'Test_OrderHook',
        'third',
        10
    );

    $registry->registerAction(
        'test.action',
        'Test_OrderHook',
        'first',
        100
    );

    $registry->registerAction(
        'test.action',
        'Test_OrderHook',
        'second',
        50
    );

    $manager = new App_Hook_Manager($registry);
    $handler = new Test_OrderHook();

    $manager->setHandlerInstance(
        'Test_OrderHook',
        $handler
    );

    $manager->doAction('test.action');

    $this->assertEquals(
        array('first', 'second', 'third'),
        $handler->calls
    );
}
```

### 26.8 Test an Unknown Action

An unregistered action should normally do nothing and should not fail the request:

```php
public function testUnknownActionDoesNothing()
{
    $registry = new App_Hook_Registry();
    $manager = new App_Hook_Manager($registry);

    $manager->doAction(
        'something.does.not.exist',
        123
    );

    $this->assertTrue(true);
}
```

### 26.9 Test an Unknown Filter

An unknown filter must return the original value:

```php
public function testUnknownFilterReturnsOriginalValue()
{
    $registry = new App_Hook_Registry();
    $manager = new App_Hook_Manager($registry);

    $result = $manager->applyFilters(
        'unknown.filter',
        'ABC'
    );

    $this->assertEquals('ABC', $result);
}
```

This is an important contract of the filter system.

### 26.10 Test the Public `S3_Hook::do_action()` Wrapper

Register a test manager in `Zend_Registry`:

```php
Zend_Registry::set(
    'hookManager',
    $manager
);
```

Then test the public API:

```php
S3_Hook::do_action(
    'document.after_save',
    123
);

$this->assertTrue(
    $handler->called
);
```

This verifies the full path:

```text
S3_Hook::do_action()
     |
     v
Zend_Registry
     |
     v
App_Hook_Manager
     |
     v
App_Hook_Registry
     |
     v
Hook Handler
```

### 26.11 Test the Public `S3_Hook::apply_filters()` Wrapper

```php
Zend_Registry::set(
    'hookManager',
    $manager
);

$result = S3_Hook::apply_filters(
    'document.title',
    'Report'
);

$this->assertEquals(
    '[CONFIDENTIAL] Report',
    $result
);
```

### 26.12 Test Handler Reuse

The manager should normally instantiate a hook handler only once per request and reuse it for later calls.

A useful test is:

```php
public function testHandlerInstanceIsReused()
{
    $registry = new App_Hook_Registry();

    $registry->registerAction(
        'test.one',
        'Test_DocumentHook',
        'documentSaved',
        10
    );

    $registry->registerAction(
        'test.two',
        'Test_DocumentHook',
        'documentSaved',
        10
    );

    $manager = new App_Hook_Manager($registry);
    $handler = new Test_DocumentHook();

    $manager->setHandlerInstance(
        'Test_DocumentHook',
        $handler
    );

    $manager->doAction('test.one', 1);
    $manager->doAction('test.two', 2);

    $this->assertEquals(2, $handler->documentId);
}
```

For stricter testing, a factory/constructor counter can be used to prove that only one instance was constructed.

### 26.13 Test Invalid Handler Classes and Methods

Bad hook configuration should be detected clearly.

Examples to test:

```text
Unknown handler class
Existing class but unknown method
Invalid hook type
Invalid hook name
Missing required config field
```

For example:

```php
public function testUnknownHandlerClassThrowsException()
{
    $registry = new App_Hook_Registry();

    $registry->registerAction(
        'test.action',
        'Class_That_Does_Not_Exist',
        'run',
        10
    );

    $manager = new App_Hook_Manager($registry);

    $this->setExpectedException(
        'RuntimeException'
    );

    $manager->doAction('test.action');
}
```

Use the exception assertion syntax that matches the PHPUnit version selected for PHP 7.4.

### 26.14 Test Real Module Hooks Separately

Do not make every unit test execute the whole application stack.

Instead of always testing:

```text
Document_Service
      |
      v
Hook Manager
      |
      v
Notification_Hook
      |
      v
Notification_Service
      |
      v
Database / Email
```

unit-test `Notification_Hook` separately with controlled dependencies whenever possible.

Then keep a smaller number of integration tests for the complete path.

### 26.15 Hook Compiler / Cache Tests

The runtime manager and the registry compiler are separate responsibilities and should have separate tests.

Recommended compiler/cache tests:

```text
testCompilerReadsAction
testCompilerReadsFilter
testCompilerPreservesPriority
testDisabledModuleIsExcluded
testDisabledHookIsExcluded
testInvalidHookConfigurationFails
testGeneratedRegistryCanBeRequired
testCacheRebuildContainsExpectedHandlers
```

Conceptually:

```text
                 HOOK TEST SUITE

        +---------------+---------------+
        |                               |
        v                               v
   Runtime Tests                  Compiler Tests
        |                               |
        v                               v
Registry / Manager               config / DB metadata
Actions / Filters                       |
Priorities                              v
Wrappers                         Hook Cache Builder
Handlers                                |
                                        v
                                  hooks.php
```

### 26.16 Minimum Recommended Test Suite

Before using hooks heavily throughout the ZF1 application, implement at least these tests:

| Test | Purpose |
|---|---|
| `testActionIsExecuted` | Verify action dispatch |
| `testActionReceivesArguments` | Verify action parameters |
| `testMultipleActionsExecute` | Verify multiple listeners |
| `testActionPriority` | Verify deterministic action order |
| `testUnknownActionDoesNothing` | Verify safe missing action |
| `testFilterChangesValue` | Verify value transformation |
| `testFilterReceivesExtraArguments` | Verify filter context |
| `testMultipleFiltersChainInPriorityOrder` | Verify filter composition |
| `testUnknownFilterReturnsOriginalValue` | Verify safe missing filter |
| `testHandlerInstanceIsReused` | Verify lazy handler caching |
| `testUnknownHandlerClassThrowsException` | Detect bad configuration |
| `testInvalidHandlerMethodThrowsException` | Detect bad callback methods |
| `testGeneratedRegistryCanBeRequired` | Verify generated cache syntax |
| `testDisabledModuleIsExcluded` | Verify module enable/disable behavior |

### 26.17 Why Unit Tests Matter for Future Migration

The tests define the expected behavior of the hook API:

```text
S3_Hook::do_action()
S3_Hook::apply_filters()
priority ordering
argument forwarding
filter chaining
missing-hook behavior
module enable/disable behavior
```

Later, the internal implementation can change without changing the public behavior.

For example:

```text
Today
ZF1 + App_Hook_Manager

        |
        | same tests
        v

Future
Laminas + new hook/event implementation
```

If the tests continue to pass, the application can migrate its infrastructure with much lower risk.

---

## 27. Updated Final Recommendation

For the current ZF1 project, keep the hook programming model simple:

```php
S3_Hook::do_action(
    'document.after_save',
    $documentId,
    $data
);

$title = S3_Hook::apply_filters(
    'document.title',
    $title,
    $document
);
```

Internally, keep the architecture disciplined:

```text
WordPress-style public API
          |
          v
    App_Hook_Manager
          |
          v
    App_Hook_Registry
          |
          v
Compiled hooks.php metadata
          |
          v
Lazy handler creation
          |
          v
Module Hook Classes

          +

Comprehensive Unit Tests
```

This keeps the system understandable for developers working on the existing ZF1 application while still providing performance, modularity, deterministic priorities, validation, and migration safety.


---

# Revision: `S3_Hook` Class-Method-Only API

This revision replaces the public global-function API with direct class-method calls. Developers should use:

```php
S3_Hook::add_action(...);
S3_Hook::do_action(...);
S3_Hook::add_filter(...);
S3_Hook::apply_filters(...);
```

Do not declare or use global `S3_Hook::add_action()`, `S3_Hook::do_action()`, `S3_Hook::add_filter()`, or `S3_Hook::apply_filters()` wrapper functions.

## Recommended `S3_Hook`

Create `library/S3/Hook.php`:

```php
<?php

class S3_Hook
{
    protected static $actions = array();
    protected static $filters = array();
    protected static $sequence = 0;

    public static function S3_Hook::add_action($hookName, $callback, $priority = 10)
    {
        self::validateHookName($hookName);
        self::validateCallback($callback);

        if (!isset(self::$actions[$hookName])) {
            self::$actions[$hookName] = array();
        }

        self::$actions[$hookName][] = array(
            'callback' => $callback,
            'priority' => (int) $priority,
            'sequence' => self::$sequence++
        );

        self::sortHandlers(self::$actions[$hookName]);
    }

    public static function S3_Hook::do_action($hookName)
    {
        if (empty(self::$actions[$hookName])) {
            return;
        }

        $args = func_get_args();
        array_shift($args);

        foreach (self::$actions[$hookName] as $handler) {
            call_user_func_array($handler['callback'], $args);
        }
    }

    public static function S3_Hook::add_filter($hookName, $callback, $priority = 10)
    {
        self::validateHookName($hookName);
        self::validateCallback($callback);

        if (!isset(self::$filters[$hookName])) {
            self::$filters[$hookName] = array();
        }

        self::$filters[$hookName][] = array(
            'callback' => $callback,
            'priority' => (int) $priority,
            'sequence' => self::$sequence++
        );

        self::sortHandlers(self::$filters[$hookName]);
    }

    public static function S3_Hook::apply_filters($hookName, $value)
    {
        if (empty(self::$filters[$hookName])) {
            return $value;
        }

        $args = func_get_args();
        array_shift($args);
        array_shift($args);

        foreach (self::$filters[$hookName] as $handler) {
            $callbackArgs = array_merge(array($value), $args);

            $value = call_user_func_array(
                $handler['callback'],
                $callbackArgs
            );
        }

        return $value;
    }

    public static function S3_Hook::has_action($hookName)
    {
        return !empty(self::$actions[$hookName]);
    }

    public static function S3_Hook::has_filter($hookName)
    {
        return !empty(self::$filters[$hookName]);
    }

    public static function reset()
    {
        self::$actions = array();
        self::$filters = array();
        self::$sequence = 0;
    }

    protected static function sortHandlers(&$handlers)
    {
        usort($handlers, array('S3_Hook', 'compareHandlers'));
    }

    public static function compareHandlers($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            if ($a['sequence'] == $b['sequence']) {
                return 0;
            }

            return ($a['sequence'] < $b['sequence']) ? -1 : 1;
        }

        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

    protected static function validateHookName($hookName)
    {
        if (!is_string($hookName) || $hookName === '') {
            throw new InvalidArgumentException(
                'Hook name must be a non-empty string.'
            );
        }
    }

    protected static function validateCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException(
                'Hook callback is not callable.'
            );
        }
    }
}
```

Lower numeric priorities execute first. The `sequence` value preserves registration order when two handlers have the same priority.

## Action Usage

```php
S3_Hook::add_action(
    'document.after_save',
    array('Notification_Hook', 'documentSaved'),
    10
);

S3_Hook::do_action(
    'document.after_save',
    $documentId,
    $data
);
```

## Filter Usage

```php
S3_Hook::add_filter(
    'document.title',
    array('Security_Hook', 'filterDocumentTitle'),
    10
);

$title = S3_Hook::apply_filters(
    'document.title',
    $title,
    $document
);
```

## Application Service Example

```php
class Document_Service
{
    public function save(array $data)
    {
        $data = S3_Hook::apply_filters(
            'document.before_save',
            $data
        );

        $db = Zend_Db_Table::getDefaultAdapter();
        $db->insert('documents', $data);

        $documentId = $db->lastInsertId();

        S3_Hook::do_action(
            'document.after_save',
            $documentId,
            $data
        );

        return $documentId;
    }
}
```

Use filters for transformations and actions for notifications that something happened.

## Compiled Module Registration

The module installer can compile `config.json` hook declarations into `data/cache/hooks.php`:

```php
<?php

S3_Hook::add_action(
    'document.after_save',
    array('Notification_Hook', 'documentSaved'),
    10
);

S3_Hook::add_action(
    'document.after_save',
    array('Audit_Hook', 'documentSaved'),
    20
);

S3_Hook::add_filter(
    'document.title',
    array('Security_Hook', 'filterDocumentTitle'),
    10
);
```

Normal requests only load the compiled registration file. They do not need to scan or instantiate every installed module.

```text
config.json
    |
    v
Module Installer
    |
    v
Hook Compiler
    |
    v
data/cache/hooks.php
    |
    v
S3_Hook::add_action()
S3_Hook::add_filter()
    |
    v
Application
    |
    +--> S3_Hook::do_action()
    |
    +--> S3_Hook::apply_filters()
```

# Unit Testing `S3_Hook`

Because the registry is static, each test must start with clean state. `S3_Hook::reset()` provides this.

## Test Helpers

```php
class Test_ActionHandler
{
    public $called = false;
    public $documentId = null;
    public $data = null;

    public function documentSaved($documentId, $data = null)
    {
        $this->called = true;
        $this->documentId = $documentId;
        $this->data = $data;
    }
}

class Test_FilterHandler
{
    public function addPrefix($value)
    {
        return '[TEST] ' . $value;
    }
}

class Test_OrderHandler
{
    public $calls = array();

    public function first()
    {
        $this->calls[] = 'first';
    }

    public function second()
    {
        $this->calls[] = 'second';
    }

    public function third()
    {
        $this->calls[] = 'third';
    }
}
```

## PHPUnit Tests

```php
class S3_HookTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        S3_Hook::reset();
    }

    protected function tearDown()
    {
        S3_Hook::reset();
    }

    public function testActionIsExecuted()
    {
        $handler = new Test_ActionHandler();

        S3_Hook::add_action(
            'document.after_save',
            array($handler, 'documentSaved'),
            10
        );

        S3_Hook::do_action(
            'document.after_save',
            123
        );

        $this->assertTrue($handler->called);
        $this->assertEquals(123, $handler->documentId);
    }

    public function testActionReceivesMultipleArguments()
    {
        $handler = new Test_ActionHandler();

        S3_Hook::add_action(
            'document.after_save',
            array($handler, 'documentSaved')
        );

        $data = array('title' => 'Test Document');

        S3_Hook::do_action(
            'document.after_save',
            123,
            $data
        );

        $this->assertEquals(123, $handler->documentId);
        $this->assertEquals(
            'Test Document',
            $handler->data['title']
        );
    }

    public function testUnknownActionDoesNothing()
    {
        S3_Hook::do_action('unknown.action', 123);
        $this->assertTrue(true);
    }

    public function testFilterChangesValue()
    {
        $handler = new Test_FilterHandler();

        S3_Hook::add_filter(
            'document.title',
            array($handler, 'addPrefix')
        );

        $result = S3_Hook::apply_filters(
            'document.title',
            'Report'
        );

        $this->assertEquals('[TEST] Report', $result);
    }

    public function testUnknownFilterReturnsOriginalValue()
    {
        $result = S3_Hook::apply_filters(
            'unknown.filter',
            'ABC'
        );

        $this->assertEquals('ABC', $result);
    }

    public function testActionPriority()
    {
        $handler = new Test_OrderHandler();

        S3_Hook::add_action(
            'test.action',
            array($handler, 'third'),
            30
        );

        S3_Hook::add_action(
            'test.action',
            array($handler, 'first'),
            10
        );

        S3_Hook::add_action(
            'test.action',
            array($handler, 'second'),
            20
        );

        S3_Hook::do_action('test.action');

        $this->assertEquals(
            array('first', 'second', 'third'),
            $handler->calls
        );
    }

    public function testSamePriorityKeepsRegistrationOrder()
    {
        $handler = new Test_OrderHandler();

        S3_Hook::add_action(
            'test.action',
            array($handler, 'first'),
            10
        );

        S3_Hook::add_action(
            'test.action',
            array($handler, 'second'),
            10
        );

        S3_Hook::add_action(
            'test.action',
            array($handler, 'third'),
            10
        );

        S3_Hook::do_action('test.action');

        $this->assertEquals(
            array('first', 'second', 'third'),
            $handler->calls
        );
    }

    public function testResetRemovesRegisteredHooks()
    {
        $handler = new Test_ActionHandler();

        S3_Hook::add_action(
            'document.after_save',
            array($handler, 'documentSaved')
        );

        $this->assertTrue(
            S3_Hook::has_action('document.after_save')
        );

        S3_Hook::reset();

        $this->assertFalse(
            S3_Hook::has_action('document.after_save')
        );
    }
}
```

If your PHPUnit version uses namespaces, replace `PHPUnit_Framework_TestCase` with `PHPUnit\Framework\TestCase`.

## Filter Chaining Test

```php
class Test_FilterA
{
    public function filter($value)
    {
        return $value . '-A';
    }
}

class Test_FilterB
{
    public function filter($value)
    {
        return $value . '-B';
    }
}
```

```php
public function testMultipleFiltersAreChained()
{
    $a = new Test_FilterA();
    $b = new Test_FilterB();

    S3_Hook::add_filter(
        'test.value',
        array($a, 'filter'),
        10
    );

    S3_Hook::add_filter(
        'test.value',
        array($b, 'filter'),
        20
    );

    $result = S3_Hook::apply_filters(
        'test.value',
        'START'
    );

    $this->assertEquals(
        'START-A-B',
        $result
    );
}
```

## Minimum Test Checklist

| Test | Purpose |
|---|---|
| Action execution | Registered action runs |
| Action arguments | Arguments reach the callback |
| Multiple actions | Multiple modules can listen |
| Action priority | Lower priority runs first |
| Same-priority order | Registration order is stable |
| Missing action | No listener is safe |
| Filter transformation | Filter can change a value |
| Filter chaining | Output feeds the next filter |
| Filter priority | Filters run deterministically |
| Missing filter | Original value is returned |
| `S3_Hook::has_action()` | Registered action is detectable |
| `S3_Hook::has_filter()` | Registered filter is detectable |
| `reset()` | Static test state is cleared |
| Invalid callback | Bad registrations are rejected |
| Compiled registry | Generated `hooks.php` registers correctly |

## Final Architecture

```text
                 MODULE PACKAGE
                      |
                      v
                  config.json
                      |
                      v
               Module Installer
                      |
                      v
                Hook Compiler
                      |
                      v
            data/cache/hooks.php
                      |
                      v
        +---------------------------+
        |         S3_Hook           |
        |---------------------------|
        | S3_Hook::add_action()              |
        | S3_Hook::do_action()               |
        | S3_Hook::add_filter()              |
        | S3_Hook::apply_filters()           |
        | S3_Hook::has_action()              |
        | S3_Hook::has_filter()              |
        | reset()                   |
        +-------------+-------------+
                      |
              +-------+-------+
              |               |
              v               v
           Actions          Filters
              |               |
              v               v
        Module Handler   Module Handler
```

The application-facing API is class-method-only. No global hook functions are required.
