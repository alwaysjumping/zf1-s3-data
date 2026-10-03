# ZF1 WordPress-Style Hook System

## 1. Overview

This document describes a WordPress-style **Action** and **Filter** hook
system for a Zend Framework 1 (ZF1) application using PHP 7.4 and
MariaDB.

The design supports dynamically installed modules without requiring
every module class to be instantiated during every request.

### Goals

-   Support `do_action()`-style hooks.
-   Support `apply_filters()`-style hooks.
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
        do_action()          apply_filters()
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

## 8. WordPress-Style Helper Functions

Create:

`library/App/hooks.php`

``` php
<?php

function app_hook_manager()
{
    if (!Zend_Registry::isRegistered('hookManager')) {
        throw new RuntimeException(
            'Hook manager has not been initialized.'
        );
    }

    return Zend_Registry::get('hookManager');
}

function do_action($name)
{
    $args = func_get_args();

    call_user_func_array(
        array(app_hook_manager(), 'doAction'),
        $args
    );
}

function apply_filters($name, $value)
{
    $args = func_get_args();

    return call_user_func_array(
        array(app_hook_manager(), 'applyFilters'),
        $args
    );
}

function has_action($name)
{
    return app_hook_manager()->hasAction($name);
}

function has_filter($name)
{
    return app_hook_manager()->hasFilter($name);
}
```

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
        do_action(
            'document.before_save',
            $data
        );

        $db = Zend_Db_Table::getDefaultAdapter();

        $db->insert(
            'documents',
            $data
        );

        $documentId = $db->lastInsertId();

        do_action(
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
$title = apply_filters(
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
        add_action(
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
do_action('document.after_save')


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
