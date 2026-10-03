# ZF1 Event-Driven Extension Architecture

## Overview

This guide describes a cleaner alternative to a pure WordPress-style
hook system for a Zend Framework 1 application using PHP 7.4. The
architecture separates **events**, **subscribers**, **services**, and
**filters**, while supporting lazy loading and dynamically installed
modules.

The main components are:

-   Event objects: explicit contracts describing something that
    happened.
-   Event Dispatcher: sends an event to interested subscribers.
-   Subscribers: module-specific listeners.
-   Subscriber Registry: maps event names to subscribers.
-   Subscriber Factory: lazy-creates subscribers.
-   Service Container: lazy-creates dependencies.
-   Filter Manager: transforms/extents values such as grid columns and
    menu items.
-   Compiled registries: avoid scanning/instantiating every module on
    each request.

## Recommended Structure

``` text
project/
├── application/
│   ├── Bootstrap.php
│   ├── controllers/
│   ├── services/
│   ├── events/
│   │   └── DocumentSaved.php
│   └── modules/
│       ├── notification/
│       │   ├── subscribers/Notification.php
│       │   └── services/Notification.php
│       └── audit/
│           └── subscribers/Audit.php
├── library/App/
│   ├── Event/
│   │   ├── EventInterface.php
│   │   ├── AbstractEvent.php
│   │   ├── DispatcherInterface.php
│   │   ├── Dispatcher.php
│   │   ├── SubscriberInterface.php
│   │   ├── SubscriberRegistry.php
│   │   └── SubscriberFactory.php
│   ├── Filter/Manager.php
│   └── Service/Container.php
└── data/cache/
    ├── events.php
    └── filters.php
```

## Event Interface

`library/App/Event/EventInterface.php`

``` php
<?php

interface App_Event_EventInterface
{
    public function getName();
}
```

## Abstract Event

`library/App/Event/AbstractEvent.php`

``` php
<?php

abstract class App_Event_AbstractEvent
    implements App_Event_EventInterface
{
    protected $propagationStopped = false;

    public function stopPropagation()
    {
        $this->propagationStopped = true;
        return $this;
    }

    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }
}
```

## DocumentSaved Event

`application/events/DocumentSaved.php`

``` php
<?php

class Application_Event_DocumentSaved
    extends App_Event_AbstractEvent
{
    const NAME = 'document.saved';

    protected $documentId;
    protected $data;
    protected $userId;

    public function __construct($documentId, array $data, $userId)
    {
        $this->documentId = $documentId;
        $this->data = $data;
        $this->userId = $userId;
    }

    public function getName()
    {
        return self::NAME;
    }

    public function getDocumentId()
    {
        return $this->documentId;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}
```

This is clearer than loose arguments such as:

``` php
do_action('document.saved', $documentId, $data, $userId);
```

because subscribers can explicitly call `getDocumentId()`, `getData()`,
and `getUserId()`.

## Subscriber Interface

`library/App/Event/SubscriberInterface.php`

``` php
<?php

interface App_Event_SubscriberInterface
{
    public static function getSubscribedEvents();
}
```

## Notification Subscriber

``` php
<?php

class Notification_EventSubscriber
    implements App_Event_SubscriberInterface
{
    protected $notificationService;

    public function __construct(Notification_Service $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'document.saved' => array(
                'method' => 'onDocumentSaved',
                'priority' => 10
            )
        );
    }

    public function onDocumentSaved(
        Application_Event_DocumentSaved $event
    ) {
        $this->notificationService
            ->sendDocumentSavedNotification(
                $event->getDocumentId(),
                $event->getUserId()
            );
    }
}
```

## Lightweight Service Container

`library/App/Service/Container.php`

``` php
<?php

class App_Service_Container
{
    protected $services = array();
    protected $factories = array();

    public function set($name, $service)
    {
        $this->services[$name] = $service;
        return $this;
    }

    public function factory($name, $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException(
                'Service factory must be callable.'
            );
        }

        $this->factories[$name] = $callback;
        return $this;
    }

    public function has($name)
    {
        return isset($this->services[$name])
            || isset($this->factories[$name]);
    }

    public function get($name)
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        if (!isset($this->factories[$name])) {
            throw new RuntimeException(
                'Service not found: ' . $name
            );
        }

        $service = call_user_func(
            $this->factories[$name],
            $this
        );

        $this->services[$name] = $service;
        return $service;
    }
}
```

## Service Container Bootstrap

``` php
protected function _initServiceContainer()
{
    $this->bootstrap('db');

    $db = $this->getResource('db');

    $container = new App_Service_Container();
    $container->set('db', $db);

    $container->factory(
        'notification.service',
        function ($container) {
            return new Notification_Service(
                $container->get('db')
            );
        }
    );

    Zend_Registry::set('serviceContainer', $container);

    return $container;
}
```

Services are created lazily only when `get()` is called.

## Subscriber Factory

`library/App/Event/SubscriberFactory.php`

``` php
<?php

class App_Event_SubscriberFactory
{
    protected $container;
    protected $instances = array();

    public function __construct(App_Service_Container $container)
    {
        $this->container = $container;
    }

    public function get($class)
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        switch ($class) {
            case 'Notification_EventSubscriber':
                $subscriber = new Notification_EventSubscriber(
                    $this->container->get('notification.service')
                );
                break;

            case 'Audit_EventSubscriber':
                $subscriber = new Audit_EventSubscriber(
                    $this->container->get('db')
                );
                break;

            default:
                throw new RuntimeException(
                    'Unknown event subscriber: ' . $class
                );
        }

        if (!$subscriber instanceof App_Event_SubscriberInterface) {
            throw new RuntimeException(
                'Invalid event subscriber: ' . $class
            );
        }

        $this->instances[$class] = $subscriber;
        return $subscriber;
    }
}
```

## Subscriber Registry

`library/App/Event/SubscriberRegistry.php`

``` php
<?php

class App_Event_SubscriberRegistry
{
    protected $listeners = array();

    public function add(
        $eventName,
        $subscriberClass,
        $method,
        $priority = 10
    ) {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = array();
        }

        $this->listeners[$eventName][] = array(
            'class' => $subscriberClass,
            'method' => $method,
            'priority' => (int) $priority
        );

        return $this;
    }

    public function loadArray(array $config)
    {
        foreach ($config as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $this->add(
                    $eventName,
                    $listener['class'],
                    $listener['method'],
                    isset($listener['priority'])
                        ? $listener['priority']
                        : 10
                );
            }
        }

        return $this->sort();
    }

    public function sort()
    {
        foreach ($this->listeners as &$listeners) {
            usort($listeners, array($this, 'comparePriority'));
        }

        return $this;
    }

    public function comparePriority($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }

        return ($a['priority'] > $b['priority']) ? -1 : 1;
    }

    public function getListeners($eventName)
    {
        return isset($this->listeners[$eventName])
            ? $this->listeners[$eventName]
            : array();
    }
}
```

## Dispatcher Interface

`library/App/Event/DispatcherInterface.php`

``` php
<?php

interface App_Event_DispatcherInterface
{
    public function dispatch(App_Event_EventInterface $event);
}
```

Business services should depend on this interface rather than directly
on ZF1 infrastructure.

## Event Dispatcher

`library/App/Event/Dispatcher.php`

``` php
<?php

class App_Event_Dispatcher
    implements App_Event_DispatcherInterface
{
    protected $registry;
    protected $factory;

    public function __construct(
        App_Event_SubscriberRegistry $registry,
        App_Event_SubscriberFactory $factory
    ) {
        $this->registry = $registry;
        $this->factory = $factory;
    }

    public function dispatch(App_Event_EventInterface $event)
    {
        $listeners = $this->registry->getListeners(
            $event->getName()
        );

        foreach ($listeners as $listener) {
            if (
                method_exists($event, 'isPropagationStopped')
                && $event->isPropagationStopped()
            ) {
                break;
            }

            $subscriber = $this->factory->get(
                $listener['class']
            );

            $method = $listener['method'];

            if (!method_exists($subscriber, $method)) {
                throw new RuntimeException(
                    'Subscriber method not found: '
                    . $listener['class']
                    . '::'
                    . $method
                );
            }

            $subscriber->$method($event);
        }

        return $event;
    }
}
```

## Compiled Event Registry

Generate `data/cache/events.php` during module installation, removal,
enabling, disabling, or update:

``` php
<?php

return array(
    'document.saved' => array(
        array(
            'class' => 'Notification_EventSubscriber',
            'method' => 'onDocumentSaved',
            'priority' => 10
        ),
        array(
            'class' => 'Audit_EventSubscriber',
            'method' => 'onDocumentSaved',
            'priority' => 20
        )
    )
);
```

Normal HTTP requests load this PHP array instead of scanning all
modules.

## Dispatcher Bootstrap

``` php
protected function _initEventDispatcher()
{
    $this->bootstrap('serviceContainer');

    $container = $this->getResource(
        'serviceContainer'
    );

    $cacheFile =
        APPLICATION_PATH . '/../data/cache/events.php';

    $config = file_exists($cacheFile)
        ? require $cacheFile
        : array();

    $registry = new App_Event_SubscriberRegistry();
    $registry->loadArray($config);

    $factory = new App_Event_SubscriberFactory(
        $container
    );

    $dispatcher = new App_Event_Dispatcher(
        $registry,
        $factory
    );

    Zend_Registry::set(
        'eventDispatcher',
        $dispatcher
    );

    return $dispatcher;
}
```

## Document Service

``` php
<?php

class Document_Service
{
    protected $db;
    protected $dispatcher;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        App_Event_DispatcherInterface $dispatcher
    ) {
        $this->db = $db;
        $this->dispatcher = $dispatcher;
    }

    public function save(array $data, $userId)
    {
        $this->db->beginTransaction();

        try {
            $this->db->insert(
                'documents',
                $data
            );

            $documentId = $this->db->lastInsertId();

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $event = new Application_Event_DocumentSaved(
            $documentId,
            $data,
            $userId
        );

        $this->dispatcher->dispatch($event);

        return $documentId;
    }
}
```

The service announces that a document was saved without knowing which
modules react to that event.

## Audit Subscriber

``` php
<?php

class Audit_EventSubscriber
    implements App_Event_SubscriberInterface
{
    protected $db;

    public function __construct(
        Zend_Db_Adapter_Abstract $db
    ) {
        $this->db = $db;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'document.saved' => array(
                'method' => 'onDocumentSaved',
                'priority' => 20
            )
        );
    }

    public function onDocumentSaved(
        Application_Event_DocumentSaved $event
    ) {
        $this->db->insert(
            'audit_log',
            array(
                'event_name' => 'document.saved',
                'object_id' => $event->getDocumentId(),
                'user_id' => $event->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            )
        );
    }
}
```

## Filter Manager

Events should announce occurrences. Filters should transform values.

`library/App/Filter/Manager.php`

``` php
<?php

class App_Filter_Manager
{
    protected $filters = array();

    public function add($name, $callback, $priority = 10)
    {
        if (!isset($this->filters[$name])) {
            $this->filters[$name] = array();
        }

        $this->filters[$name][] = array(
            'callback' => $callback,
            'priority' => (int) $priority
        );

        usort(
            $this->filters[$name],
            array($this, 'comparePriority')
        );

        return $this;
    }

    public function comparePriority($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return 0;
        }

        return ($a['priority'] > $b['priority']) ? -1 : 1;
    }

    public function apply($name, $value)
    {
        if (empty($this->filters[$name])) {
            return $value;
        }

        $args = func_get_args();
        array_shift($args);
        array_shift($args);

        foreach ($this->filters[$name] as $filter) {
            $callbackArgs = array_merge(
                array($value),
                $args
            );

            $value = call_user_func_array(
                $filter['callback'],
                $callbackArgs
            );
        }

        return $value;
    }
}
```

A production module system should eventually give filters the same
compiled-registry/lazy-factory treatment as events.

## DHTMLX Grid Filter Example

``` php
$columns = array(
    array(
        'name' => 'id',
        'label' => 'ID'
    ),
    array(
        'name' => 'title',
        'label' => 'Title'
    )
);

$columns = $filterManager->apply(
    'document.grid.columns',
    $columns
);
```

Module filter:

``` php
public function addStatusColumn(array $columns)
{
    $columns[] = array(
        'name' => 'status',
        'label' => 'Status'
    );

    return $columns;
}
```

Good filter candidates include:

``` text
document.grid.columns
document.grid.data
document.export.fields
menu.items
toolbar.items
document.metadata
```

## Security Boundary

Do not use loosely coupled events to make critical security decisions.

Avoid using events as the authority for:

``` text
Authentication
Authorization
CSRF protection
Certificate verification
Encryption/decryption
Critical input validation
Database transaction integrity
```

Prefer explicit services:

``` php
if (!$authorizationService->canViewDocument(
    $user,
    $document
)) {
    throw new App_Exception_Forbidden();
}
```

## Transaction Boundary

Be careful about optional event subscribers inside database
transactions.

A useful default pattern is:

``` text
BEGIN
  |
  +-- Validate
  +-- Save required database state
  |
COMMIT
  |
  v
DocumentSavedEvent
  |
  +-- Notification
  +-- Optional logging
  +-- Other extension side effects
```

An event named `document.saved` should normally represent successfully
committed state.

If some operation is mandatory for transaction correctness, keep it
explicit inside the service/transaction rather than treating it as an
optional event subscriber.

## Unit Testing

The dispatcher interface makes services easy to test.

``` php
class Test_EventDispatcher
    implements App_Event_DispatcherInterface
{
    public $events = array();

    public function dispatch(
        App_Event_EventInterface $event
    ) {
        $this->events[] = $event;
        return $event;
    }
}
```

Test:

``` php
public function testSaveDispatchesDocumentSavedEvent()
{
    $dispatcher = new Test_EventDispatcher();

    $service = new Document_Service(
        $this->db,
        $dispatcher
    );

    $documentId = $service->save(
        array('title' => 'Test'),
        100
    );

    $this->assertCount(
        1,
        $dispatcher->events
    );

    $event = $dispatcher->events[0];

    $this->assertInstanceOf(
        'Application_Event_DocumentSaved',
        $event
    );

    $this->assertEquals(
        $documentId,
        $event->getDocumentId()
    );
}
```

Notification, Audit, Email, and other optional modules do not need to be
initialized in this unit test.

## Suggested Module Configuration

A module can declare subscribers:

``` json
{
    "module": {
        "name": "notification",
        "version": "2.4.1"
    },
    "subscribers": [
        "Notification_EventSubscriber"
    ]
}
```

During installation, the registry compiler can inspect:

``` php
Notification_EventSubscriber::getSubscribedEvents();
```

and generate `data/cache/events.php`.

Normal requests therefore do not scan every module directory.

## Event Naming

Prefer past-tense names because events describe things that already
happened:

``` text
document.saved
document.updated
document.deleted

user.created
user.updated
user.deleted

report.generated

module.installed
module.enabled
module.disabled
module.uninstalled
```

## Priorities

Higher priority can execute first:

``` text
Priority 100
Priority 50
Priority 20
Priority 10
Priority 0
```

Do not use priority to hide critical workflow dependencies. If step B
absolutely requires step A, model that relationship explicitly in a
service/workflow.

## Error Handling

Subscriber failures need a deliberate policy.

``` text
Notification subscriber -> usually optional
Analytics subscriber    -> usually optional
Audit subscriber        -> may be required
Critical state update   -> should usually be explicit
```

Optional subscriber errors may be logged and processing may continue.
Critical business behavior should normally remain an explicit service
call.

## Why Not Constructor Hook Registration?

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

This requires constructing services merely to discover their
registrations.

The compiled event registry instead provides:

``` text
Request
   |
   v
Load events.php
   |
   v
Application executes
   |
   v
DocumentSavedEvent occurs
   |
   v
Find only matching subscribers
   |
   v
Lazy-create only required subscribers/services
```

## Command Bus: Add Later If Needed

Do not introduce a Command Bus immediately.

Start with:

``` text
Existing ZF1
     |
     v
Service Layer
     |
     +-- Event Dispatcher
     +-- Subscribers
     +-- Service Container
     +-- Filter Pipeline
```

If workflows later become complex, commands can represent requests:

``` text
SaveDocumentCommand
DeleteDocumentCommand
CreateUserCommand
GenerateReportCommand
```

The conceptual distinction is:

``` text
COMMAND
"What should the system do?"

SaveDocumentCommand
        |
        v
SaveDocumentHandler

EVENT
"What already happened?"

DocumentSavedEvent
        |
        v
Notification / Audit / Workflow

FILTER
"What value may extensions transform?"

Grid Columns / Menu Items / Export Fields
```

## Future Laminas Migration

Keep application services dependent on your own interfaces:

``` php
App_Event_DispatcherInterface
```

rather than directly on framework-specific event infrastructure.

Then:

``` text
Current:
ZF1
 |
 +-- Application Services
 +-- Events
 +-- Subscribers
 +-- Dispatcher Interface

Future:
Laminas MVC
 |
 +-- Same/Adapted Application Services
 +-- Same Event Contracts
 +-- Same Subscribers
```

This reduces migration work.

## Recommended Implementation Order

1.  Implement `EventInterface`, `AbstractEvent`, `DispatcherInterface`,
    `Dispatcher`, `SubscriberRegistry`, and `SubscriberFactory`.
2.  Add the lightweight `ServiceContainer`.
3.  Introduce only a few useful events first, such as `document.saved`,
    `document.deleted`, and `user.created`.
4.  Add the compiled `events.php` registry and connect rebuilding to
    module installation/uninstallation.
5.  Add the Filter system for UI/data extension points.
6.  Add unit tests around services and event dispatch.
7.  Evaluate a Command Bus only if application workflows later justify
    it.

## Final Recommendation

Use:

``` text
Event Dispatcher
    -> Something happened

Subscriber
    -> A module reacts to the event

Event Object
    -> Explicit event data contract

Service Container
    -> Lazy dependency creation

Compiled Registry
    -> Fast module discovery

Filter Pipeline
    -> Extensible value transformation

Explicit Services
    -> Security and critical business operations
```

This keeps the useful extensibility of WordPress-style hooks while
providing clearer contracts, better dependency management, easier unit
testing, lazy loading, and a cleaner migration path from ZF1 toward
Laminas MVC.
