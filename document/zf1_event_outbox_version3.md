# ZF1 Event Outbox System — Version 3

## 1. Purpose

Version 3 is a multi-consumer event/outbox architecture for Zend Framework 1, PHP 7.4, and MariaDB. Business modules publish events such as `email.sent`, `blog.created`, `product.updated`, and `crm.lead.assigned`; independent consumers such as realtime notification, audit, and analytics process them asynchronously.

```text
ZF1 Business Modules
        |
        v
   event_outbox
        |
        v
   event_delivery
        |
   PHP Worker(s)
        |
        v
   Dispatcher
    /   |    \
   v    v     v
Realtime Audit Analytics
```

Version 3 does not require Redis, RabbitMQ, Kafka, or another broker.

## 2. Version progression

```text
Version 1: ZF1 -> MariaDB Outbox -> PHP Worker -> Realtime Service
Version 2: ZF1 -> MariaDB Outbox -> Multiple PHP Workers -> Realtime Service
Version 3: ZF1 -> MariaDB Outbox -> Multiple independent consumers
Version 4: MariaDB Outbox -> Publisher -> Message Broker -> Consumers
```

## 3. Core design rule

Business modules publish business events; they do not synchronously call secondary services.

Bad:

```php
$emailService->send($data);
$realtimeService->notify($data);
$auditService->send($data);
```

Better:

```php
$outbox->publish(
    'email.sent',
    'email',
    $emailId,
    array(
        'email_id' => $emailId,
        'sender_id' => $senderId
    )
);
```

## 4. Why Version 3 uses two tables

A single event can have different outcomes per consumer:

```text
event 1001: email.sent

realtime  -> completed
audit     -> completed
analytics -> failed
```

Therefore Version 3 separates the immutable event from each delivery attempt:

```text
event_outbox 1 ---- N event_delivery
```

## 5. Database schema

### 5.1 event_outbox

```sql
CREATE TABLE event_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique business event ID',
    event_type VARCHAR(100) NOT NULL
        COMMENT 'Event name such as email.sent',
    entity_type VARCHAR(50) DEFAULT NULL
        COMMENT 'Related entity type such as email',
    entity_id VARCHAR(100) DEFAULT NULL
        COMMENT 'Related business entity ID',
    payload LONGTEXT DEFAULT NULL
        COMMENT 'JSON encoded event payload',
    created_at DATETIME NOT NULL
        COMMENT 'Time event was created',
    PRIMARY KEY (id),
    KEY idx_event_type (event_type),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.2 event_delivery

```sql
CREATE TABLE event_delivery (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique delivery record ID',
    event_id BIGINT UNSIGNED NOT NULL
        COMMENT 'Related event_outbox ID',
    consumer VARCHAR(100) NOT NULL
        COMMENT 'Consumer such as realtime, audit, analytics',
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        COMMENT 'pending, processing, completed, failed',
    retry_count INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of failed attempts',
    max_retries INT UNSIGNED NOT NULL DEFAULT 10
        COMMENT 'Maximum attempts before permanent failure',
    next_retry_at DATETIME DEFAULT NULL
        COMMENT 'Earliest retry time',
    locked_by VARCHAR(100) DEFAULT NULL
        COMMENT 'Worker currently owning delivery',
    locked_at DATETIME DEFAULT NULL
        COMMENT 'Time worker claimed delivery',
    last_error TEXT DEFAULT NULL
        COMMENT 'Last processing error',
    created_at DATETIME NOT NULL
        COMMENT 'Delivery creation time',
    processed_at DATETIME DEFAULT NULL
        COMMENT 'Successful processing time',
    PRIMARY KEY (id),
    UNIQUE KEY uk_event_consumer (event_id, consumer),
    KEY idx_worker (status, next_retry_at, id),
    KEY idx_stale (status, locked_at),
    KEY idx_consumer (consumer, status, next_retry_at),
    CONSTRAINT fk_event_delivery_event
        FOREIGN KEY (event_id) REFERENCES event_outbox (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

The unique `(event_id, consumer)` key prevents duplicate delivery rows.

## 6. Project structure

```text
project/
├── application/configs/application.ini
├── library/S3/Event/
│   ├── Factory.php
│   ├── Outbox.php
│   ├── Router.php
│   ├── Dispatcher.php
│   ├── Worker.php
│   ├── HandlerInterface.php
│   └── Handler/
│       ├── Realtime.php
│       ├── Audit.php
│       └── Analytics.php
├── scripts/event-worker.php
└── sql/event_v3.sql
```

## 7. Handler interface

`library/S3/Event/HandlerInterface.php`

```php
<?php

interface S3_Event_HandlerInterface
{
    public function handle(array $event, array $delivery);
}
```

## 8. Event router

The router answers: **which consumers receive this event type?**

`library/S3/Event/Router.php`

```php
<?php

class S3_Event_Router
{
    protected $routes = array();

    public function register($eventType, array $consumers)
    {
        $this->routes[$eventType] = $consumers;
        return $this;
    }

    public function getConsumers($eventType)
    {
        if (!isset($this->routes[$eventType])) {
            return array();
        }

        return $this->routes[$eventType];
    }
}
```

Example:

```php
$router = new S3_Event_Router();

$router->register('email.sent', array('realtime', 'audit', 'analytics'));
$router->register('blog.created', array('realtime', 'audit'));
$router->register('product.updated', array('realtime', 'audit', 'analytics'));
$router->register('document.approved', array('realtime', 'audit'));
```

## 9. Shared route factory

Use one route definition for both web and CLI processes.

`library/S3/Event/Factory.php`

```php
<?php

class S3_Event_Factory
{
    public static function createRouter()
    {
        $router = new S3_Event_Router();

        $router->register('email.sent', array('realtime', 'audit', 'analytics'));
        $router->register('blog.created', array('realtime', 'audit'));
        $router->register('product.updated', array('realtime', 'audit', 'analytics'));
        $router->register('crm.lead.assigned', array('realtime', 'audit'));
        $router->register('document.approved', array('realtime', 'audit'));

        return $router;
    }
}
```

## 10. Outbox publisher

`library/S3/Event/Outbox.php`

```php
<?php

class S3_Event_Outbox
{
    protected $db;
    protected $router;
    protected $maxRetries;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Event_Router $router,
        $maxRetries = 10
    ) {
        $this->db = $db;
        $this->router = $router;
        $this->maxRetries = (int) $maxRetries;
    }

    public function publish(
        $eventType,
        $entityType = null,
        $entityId = null,
        array $payload = array()
    ) {
        $json = json_encode($payload);

        if ($json === false) {
            throw new Exception(
                'Cannot encode event payload: ' . json_last_error_msg()
            );
        }

        $consumers = $this->router->getConsumers($eventType);

        if (empty($consumers)) {
            throw new Exception(
                'No consumers registered for event: ' . $eventType
            );
        }

        $now = date('Y-m-d H:i:s');

        $this->db->insert('event_outbox', array(
            'event_type'  => $eventType,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'payload'     => $json,
            'created_at'  => $now
        ));

        $eventId = (int) $this->db->lastInsertId();

        foreach ($consumers as $consumer) {
            $this->db->insert('event_delivery', array(
                'event_id'     => $eventId,
                'consumer'     => $consumer,
                'status'       => 'pending',
                'retry_count'  => 0,
                'max_retries'  => $this->maxRetries,
                'created_at'   => $now
            ));
        }

        return $eventId;
    }
}
```

## 11. Business transaction example

```php
$router = S3_Event_Factory::createRouter();
$outbox = new S3_Event_Outbox($db, $router, 10);

$db->beginTransaction();

try {
    $emailId = $emailModel->insert($emailData);

    $outbox->publish(
        'email.sent',
        'email',
        $emailId,
        array(
            'email_id'     => $emailId,
            'sender_id'    => $senderId,
            'recipient_id' => $recipientId
        )
    );

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

This transaction covers database state and outbox/delivery creation. Actual SMTP transmission cannot be rolled back by MariaDB.

## 12. Dispatcher

The dispatcher answers: **which PHP handler processes this consumer?**

`library/S3/Event/Dispatcher.php`

```php
<?php

class S3_Event_Dispatcher
{
    protected $handlers = array();

    public function register(
        $consumer,
        S3_Event_HandlerInterface $handler
    ) {
        $this->handlers[$consumer] = $handler;
        return $this;
    }

    public function dispatch(array $event, array $delivery)
    {
        $consumer = $delivery['consumer'];

        if (!isset($this->handlers[$consumer])) {
            throw new Exception(
                'No handler registered for consumer: ' . $consumer
            );
        }

        $this->handlers[$consumer]->handle($event, $delivery);
    }
}
```

```text
Router:     event_type -> consumer names
Dispatcher: consumer   -> PHP handler object
```

## 13. Realtime handler

`library/S3/Event/Handler/Realtime.php`

```php
<?php

class S3_Event_Handler_Realtime
    implements S3_Event_HandlerInterface
{
    protected $url;
    protected $connectTimeout;
    protected $totalTimeout;

    public function __construct(
        $url,
        $connectTimeout = 2,
        $totalTimeout = 5
    ) {
        $this->url = $url;
        $this->connectTimeout = (int) $connectTimeout;
        $this->totalTimeout = (int) $totalTimeout;
    }

    public function handle(array $event, array $delivery)
    {
        $payload = json_decode($event['payload'], true);

        if ($payload === null && $event['payload'] !== 'null') {
            throw new Exception('Invalid event JSON payload');
        }

        $request = array(
            'event_id'     => (int) $event['id'],
            'delivery_id'  => (int) $delivery['id'],
            'event_type'   => $event['event_type'],
            'entity_type'  => $event['entity_type'],
            'entity_id'    => $event['entity_id'],
            'payload'      => $payload
        );

        $json = json_encode($request);

        if ($json === false) {
            throw new Exception('Cannot encode realtime request');
        }

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->totalTimeout,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Event-Id: ' . $event['id']
            )
        ));

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception(
                'Realtime request failed [' . $errno . ']: ' . $error
            );
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception(
                'Realtime service returned HTTP ' . $httpCode
            );
        }
    }
}
```

## 14. Audit handler

Example audit table:

```sql
CREATE TABLE event_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id VARCHAR(100) DEFAULT NULL,
    payload LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Handler:

```php
<?php

class S3_Event_Handler_Audit
    implements S3_Event_HandlerInterface
{
    protected $db;

    public function __construct(Zend_Db_Adapter_Abstract $db)
    {
        $this->db = $db;
    }

    public function handle(array $event, array $delivery)
    {
        $this->db->insert('event_audit', array(
            'event_id'    => $event['id'],
            'event_type'  => $event['event_type'],
            'entity_type' => $event['entity_type'],
            'entity_id'   => $event['entity_id'],
            'payload'     => $event['payload'],
            'created_at'  => date('Y-m-d H:i:s')
        ));
    }
}
```

The unique event key is the basis for idempotency. Production code should recognize the database driver's duplicate-key error as an already-processed event, while rethrowing unrelated SQL errors.

## 15. Analytics handler

```sql
CREATE TABLE event_analytics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_analytics_event (event_id),
    KEY idx_analytics_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
<?php

class S3_Event_Handler_Analytics
    implements S3_Event_HandlerInterface
{
    protected $db;

    public function __construct(Zend_Db_Adapter_Abstract $db)
    {
        $this->db = $db;
    }

    public function handle(array $event, array $delivery)
    {
        $this->db->insert('event_analytics', array(
            'event_id'   => $event['id'],
            'event_type' => $event['event_type'],
            'created_at' => date('Y-m-d H:i:s')
        ));
    }
}
```

## 16. Worker

Version 3 claims **delivery rows**, not event rows. That allows different consumers for the same event to run independently.

`library/S3/Event/Worker.php`

```php
<?php

class S3_Event_Worker
{
    protected $db;
    protected $dispatcher;
    protected $workerId;
    protected $staleMinutes;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Event_Dispatcher $dispatcher,
        $staleMinutes = 10
    ) {
        $this->db = $db;
        $this->dispatcher = $dispatcher;
        $this->staleMinutes = max(1, (int) $staleMinutes);
        $this->workerId = gethostname() . '-' . getmypid();
    }

    public function processOne()
    {
        $delivery = $this->claimDelivery();

        if (!$delivery) {
            return false;
        }

        try {
            $event = $this->loadEvent($delivery['event_id']);

            if (!$event) {
                throw new Exception(
                    'Event not found: ' . $delivery['event_id']
                );
            }

            $this->dispatcher->dispatch($event, $delivery);
            $this->markCompleted($delivery['id']);
        } catch (Exception $e) {
            $this->markFailedAttempt($delivery, $e);
        }

        return true;
    }

    protected function claimDelivery()
    {
        $this->db->beginTransaction();

        try {
            $sql = "
                SELECT *
                FROM event_delivery
                WHERE status = 'pending'
                  AND (
                      next_retry_at IS NULL
                      OR next_retry_at <= NOW()
                  )
                ORDER BY id
                LIMIT 1
                FOR UPDATE
            ";

            $delivery = $this->db->fetchRow($sql);

            if (!$delivery) {
                $this->db->commit();
                return null;
            }

            $now = date('Y-m-d H:i:s');

            $where = array(
                $this->db->quoteInto('id = ?', $delivery['id']),
                "status = 'pending'"
            );

            $affected = $this->db->update(
                'event_delivery',
                array(
                    'status'    => 'processing',
                    'locked_by' => $this->workerId,
                    'locked_at' => $now
                ),
                $where
            );

            if ($affected !== 1) {
                throw new Exception(
                    'Unable to claim delivery ' . $delivery['id']
                );
            }

            $this->db->commit();

            $delivery['status'] = 'processing';
            $delivery['locked_by'] = $this->workerId;
            $delivery['locked_at'] = $now;

            return $delivery;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    protected function loadEvent($eventId)
    {
        return $this->db->fetchRow(
            'SELECT * FROM event_outbox WHERE id = ?',
            $eventId
        );
    }

    protected function markCompleted($deliveryId)
    {
        $where = array(
            $this->db->quoteInto('id = ?', $deliveryId),
            $this->db->quoteInto('locked_by = ?', $this->workerId),
            "status = 'processing'"
        );

        $affected = $this->db->update(
            'event_delivery',
            array(
                'status'        => 'completed',
                'processed_at'  => date('Y-m-d H:i:s'),
                'next_retry_at' => null,
                'locked_by'     => null,
                'locked_at'     => null,
                'last_error'    => null
            ),
            $where
        );

        if ($affected !== 1) {
            throw new Exception(
                'Lost ownership of delivery ' . $deliveryId
            );
        }
    }

    protected function markFailedAttempt(
        array $delivery,
        Exception $exception
    ) {
        $retryCount = (int) $delivery['retry_count'] + 1;
        $maxRetries = (int) $delivery['max_retries'];
        $error = substr($exception->getMessage(), 0, 65535);

        $where = array(
            $this->db->quoteInto('id = ?', $delivery['id']),
            $this->db->quoteInto('locked_by = ?', $this->workerId),
            "status = 'processing'"
        );

        if ($retryCount >= $maxRetries) {
            $this->db->update(
                'event_delivery',
                array(
                    'status'        => 'failed',
                    'retry_count'   => $retryCount,
                    'next_retry_at' => null,
                    'locked_by'     => null,
                    'locked_at'     => null,
                    'last_error'    => $error
                ),
                $where
            );
            return;
        }

        $delay = $this->getRetryDelay($retryCount);

        $this->db->update(
            'event_delivery',
            array(
                'status'        => 'pending',
                'retry_count'   => $retryCount,
                'next_retry_at' => date(
                    'Y-m-d H:i:s',
                    time() + $delay
                ),
                'locked_by'     => null,
                'locked_at'     => null,
                'last_error'    => $error
            ),
            $where
        );
    }

    protected function getRetryDelay($retryCount)
    {
        $delays = array(5, 15, 30, 60, 300, 900, 1800, 3600);
        $index = (int) $retryCount - 1;

        if ($index < 0) {
            $index = 0;
        }

        if ($index >= count($delays)) {
            return 3600;
        }

        return $delays[$index];
    }

    public function recoverStaleDeliveries()
    {
        $minutes = $this->staleMinutes;

        $sql = "
            UPDATE event_delivery
            SET
                status = 'pending',
                locked_by = NULL,
                locked_at = NULL,
                next_retry_at = NOW(),
                last_error = 'Recovered abandoned delivery'
            WHERE status = 'processing'
              AND locked_at IS NOT NULL
              AND locked_at < DATE_SUB(
                  NOW(),
                  INTERVAL {$minutes} MINUTE
              )
        ";

        $stmt = $this->db->query($sql);
        return $stmt->rowCount();
    }
}
```

## 17. Why claim event_delivery instead of event_outbox?

```text
event 1001
  |
  +-- delivery 2001 -> realtime
  +-- delivery 2002 -> audit
  +-- delivery 2003 -> analytics
```

Workers can independently claim 2001, 2002, and 2003. Locking the whole event would unnecessarily serialize independent consumers.

The claim transaction must remain short:

```text
BEGIN
SELECT ... FOR UPDATE
UPDATE pending -> processing
COMMIT

-- DB lock is released here --

call external/local consumer
mark completed or retry
```

Do not keep `FOR UPDATE` open while waiting on a network call.

## 18. Retry schedule

Recommended initial backoff:

```text
failure 1 -> 5 sec
failure 2 -> 15 sec
failure 3 -> 30 sec
failure 4 -> 1 min
failure 5 -> 5 min
failure 6 -> 15 min
failure 7 -> 30 min
failure 8+ -> 1 hour
```

Each consumer retries independently.

## 19. Crash recovery

If a worker dies after claiming a delivery, the row may remain `processing`. Periodic recovery resets old claims to `pending` using `locked_at` and the configured stale interval.

This is also why consumers must be idempotent: a worker can die after the remote service processes the event but before local status becomes `completed`.

## 20. Idempotency

Version 3 should be treated as **at-least-once delivery**.

A receiving service should persist processed event IDs:

```sql
CREATE TABLE processed_event (
    event_id BIGINT UNSIGNED NOT NULL,
    processed_at DATETIME NOT NULL,
    PRIMARY KEY (event_id)
) ENGINE=InnoDB;
```

Receiver flow:

```text
receive event 1001
      |
already processed?
   /       \
 yes       no
  |         |
return     perform operation
200         |
            +-> store 1001
            +-> return 200
```

Where the same event can legitimately be processed under separate consumer identities, use `(event_id, consumer)` as the idempotency key.

## 21. CLI worker

`scripts/event-worker.php`

```php
<?php

define(
    'APPLICATION_PATH',
    realpath(dirname(__FILE__) . '/../application')
);

define(
    'APPLICATION_ENV',
    getenv('APPLICATION_ENV')
        ? getenv('APPLICATION_ENV')
        : 'production'
);

set_include_path(
    realpath(dirname(__FILE__) . '/../library') .
    PATH_SEPARATOR .
    get_include_path()
);

require_once 'Zend/Application.php';

$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

$application->bootstrap();
$bootstrap = $application->getBootstrap();
$db = $bootstrap->getResource('db');

$config = new Zend_Config_Ini(
    APPLICATION_PATH . '/configs/application.ini',
    APPLICATION_ENV
);

$dispatcher = new S3_Event_Dispatcher();

$dispatcher->register(
    'realtime',
    new S3_Event_Handler_Realtime(
        $config->event->realtime->url,
        $config->event->realtime->connectTimeout,
        $config->event->realtime->totalTimeout
    )
);

$dispatcher->register(
    'audit',
    new S3_Event_Handler_Audit($db)
);

$dispatcher->register(
    'analytics',
    new S3_Event_Handler_Analytics($db)
);

$worker = new S3_Event_Worker(
    $db,
    $dispatcher,
    $config->event->worker->staleMinutes
);

$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    pcntl_signal(SIGTERM, function () use (&$running) {
        $running = false;
    });

    pcntl_signal(SIGINT, function () use (&$running) {
        $running = false;
    });
}

$lastRecovery = 0;

echo date('Y-m-d H:i:s') . " Event worker started.\n";

while ($running) {
    try {
        if ((time() - $lastRecovery) >= 60) {
            $worker->recoverStaleDeliveries();
            $lastRecovery = time();
        }

        $processed = $worker->processOne();

        if (!$processed) {
            sleep((int) $config->event->worker->sleepSeconds);
        }
    } catch (Exception $e) {
        error_log('Event worker error: ' . $e->getMessage());
        sleep(1);
    }
}

echo date('Y-m-d H:i:s') . " Event worker stopped.\n";
```

The router is used by the web-side publisher, so use `S3_Event_Factory::createRouter()` when constructing `S3_Event_Outbox` in the web application.

## 22. Configuration

`application.ini`:

```ini
event.worker.sleepSeconds = 1
event.worker.staleMinutes = 10
event.worker.maxRetries = 10

event.realtime.url = "http://127.0.0.1:8080/api/events"
event.realtime.connectTimeout = 2
event.realtime.totalTimeout = 5
```

## 23. Event-specific functions

There are two routing levels:

```text
Event Router:
email.sent -> realtime, audit, analytics

Consumer Dispatcher:
realtime -> S3_Event_Handler_Realtime
```

If the realtime consumer itself needs different functions for each event type, add a second dispatcher inside that consumer rather than a giant worker `switch`.

```php
class S3_Realtime_EventDispatcher
{
    protected $handlers = array();

    public function register($eventType, $callback)
    {
        $this->handlers[$eventType] = $callback;
    }

    public function dispatch(array $event)
    {
        $type = $event['event_type'];

        if (!isset($this->handlers[$type])) {
            throw new Exception(
                'Unsupported realtime event: ' . $type
            );
        }

        call_user_func($this->handlers[$type], $event);
    }
}
```

For a large number of event types, prefer one event-specific class per responsibility instead of a giant switch statement.

## 24. Event naming

Use a stable `<module>.<action>` convention:

```text
email.sent
email.received
email.deleted
blog.created
blog.updated
product.created
product.updated
crm.customer.created
crm.lead.assigned
document.approved
document.rejected
task.assigned
task.completed
```

Treat event names as an API contract.

## 25. Payload design

Prefer compact payloads:

```json
{
  "email_id": 50021,
  "sender_id": 100,
  "recipient_id": 200
}
```

Avoid passwords, private keys, session IDs, access tokens, or other secrets in general-purpose event payloads.

## 26. Worker loop behavior

```php
while ($running) {
    $processed = $worker->processOne();

    if (!$processed) {
        sleep(1);
    }
}
```

If work exists, the worker continues immediately. It sleeps only when no eligible delivery exists. If one handler takes 15 seconds, one worker remains busy for 15 seconds; it does not automatically create 15 workers.

## 27. Multiple workers

Multiple PHP processes can process deliveries concurrently:

```text
 event_delivery
      |
 +----+----+
 |    |    |
 v    v    v
W1   W2   W3
```

Start with one worker. After verifying the deployed MariaDB version and concurrency behavior, `FOR UPDATE SKIP LOCKED` can be evaluated as a later optimization.

An optional consumer-specific worker can claim only one consumer:

```sql
SELECT *
FROM event_delivery
WHERE status = 'pending'
  AND consumer = ?
  AND (
      next_retry_at IS NULL
      OR next_retry_at <= NOW()
  )
ORDER BY id
LIMIT 1
FOR UPDATE;
```

## 28. systemd service

`/etc/systemd/system/s3-event-worker.service`:

```ini
[Unit]
Description=S3 Event Delivery Worker
After=network.target mariadb.service

[Service]
Type=simple
User=apache
Group=apache
WorkingDirectory=/var/www/myproject
Environment=APPLICATION_ENV=production
ExecStart=/usr/bin/php /var/www/myproject/scripts/event-worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Commands:

```bash
sudo systemctl daemon-reload
sudo systemctl enable s3-event-worker
sudo systemctl start s3-event-worker
sudo systemctl status s3-event-worker
sudo journalctl -u s3-event-worker -f
```

## 29. Monitoring queries

```sql
SELECT consumer, status, COUNT(*) AS total
FROM event_delivery
GROUP BY consumer, status
ORDER BY consumer, status;
```

Recent failures:

```sql
SELECT
    d.id AS delivery_id,
    d.event_id,
    e.event_type,
    d.consumer,
    d.retry_count,
    d.last_error,
    d.next_retry_at
FROM event_delivery AS d
INNER JOIN event_outbox AS e
    ON e.id = d.event_id
WHERE d.retry_count > 0
  AND d.status IN ('pending', 'failed')
ORDER BY d.id DESC
LIMIT 100;
```

Manual retry of a permanently failed delivery:

```sql
UPDATE event_delivery
SET
    status = 'pending',
    retry_count = 0,
    next_retry_at = NOW(),
    locked_by = NULL,
    locked_at = NULL,
    last_error = NULL
WHERE id = 2003
  AND status = 'failed';
```

In production, expose manual retry through an authorized administration function rather than routinely editing the database manually.

## 30. Retention

Completed events will grow indefinitely. Define a retention policy, for example 90 days, and only remove an event when all of its deliveries are complete.

```sql
SELECT e.id
FROM event_outbox AS e
WHERE e.created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
AND NOT EXISTS (
    SELECT 1
    FROM event_delivery AS d
    WHERE d.event_id = e.id
      AND d.status <> 'completed'
);
```

## 31. Failure scenarios

```text
Realtime down:
  realtime -> retry
  audit -> succeeds
  analytics -> succeeds

Audit failure:
  only audit delivery retries

Worker crash before external call:
  stale recovery resets processing -> pending

Worker crash after remote processing:
  event may be delivered again; idempotency prevents duplicate effect

Missing handler:
  dispatcher throws; delivery follows retry/failure path
```

A missing handler is a configuration problem and should be surfaced operationally rather than expected to heal through retries.

## 32. Testing plan

1. Publish `email.sent`; verify event plus three delivery rows.
2. Verify all deliveries reach `completed` in the normal case.
3. Stop realtime service; verify audit/analytics can complete while realtime retries.
4. Restart realtime service; verify realtime eventually completes.
5. Kill a worker after claim; verify stale recovery returns the delivery to pending.
6. Send the same event twice to a receiver; verify idempotent business effect.
7. Start two workers; verify deliveries are not simultaneously owned by both.
8. Force analytics failure; verify realtime and audit remain completed.
9. Test transaction rollback; verify business row, event, and deliveries all roll back together.

## 33. Security

Recommended controls:

- private/internal networking where possible;
- TLS over untrusted networks;
- service authentication;
- strict JSON validation;
- event-type allowlists;
- payload size limits;
- no secrets in event payloads;
- authorization for any data fetched after receiving an event;
- do not expose the CLI worker as a public controller.

## 34. Complete execution flow

```text
User action
    |
    v
ZF1 Business Module
    |
    | BEGIN
    +-- save business data
    +-- insert event_outbox
    +-- insert event_delivery(realtime)
    +-- insert event_delivery(audit)
    +-- insert event_delivery(analytics)
    | COMMIT
    v
Return response

             ASYNCHRONOUS

          event_delivery
                |
                v
            PHP Worker
                |
          claim delivery
                |
       pending -> processing
                |
              COMMIT
                |
                v
            Dispatcher
          /      |       \
         v       v        v
    Realtime   Audit   Analytics
         |       |        |
         v       v        v
   completed/retry independently
```

## 35. Recommended implementation order

```text
1. Create event_outbox
2. Create event_delivery
3. Implement HandlerInterface
4. Implement Router
5. Implement shared Factory
6. Implement Outbox publisher
7. Implement Dispatcher
8. Implement realtime handler
9. Implement Worker
10. Test claim logic
11. Test retry/backoff
12. Test realtime outage
13. Add audit handler
14. Add analytics handler
15. Test independent failures
16. Add stale recovery
17. Add receiver idempotency
18. Run CLI worker manually
19. Install systemd service
20. Add monitoring and retention
```

## 36. Version 3 vs Version 4

Version 3:

```text
ZF1 -> MariaDB event_outbox/event_delivery -> PHP workers -> consumers
```

Version 4 may add:

```text
ZF1 -> MariaDB Outbox -> Outbox Publisher -> Message Broker -> Consumers
```

The business modules can keep the same `publish()` abstraction, so Version 3 does not block a future move to RabbitMQ, NATS, Kafka, or another broker.

## 37. Final recommendation

For Version 3, keep the operational stack simple:

```text
Zend Framework 1
PHP 7.4
MariaDB
PHP CLI worker(s)
systemd
```

The essential reliability rules are:

1. business modules do not synchronously depend on secondary services;
2. events are stored durably in MariaDB;
3. each consumer has independent delivery state;
4. workers claim delivery rows before processing;
5. database locks are held only briefly;
6. external calls happen after the claim transaction commits;
7. failures use retry/backoff;
8. stale claims are recovered;
9. consumers are idempotent;
10. routing and dispatching are centralized while the worker stays generic.
