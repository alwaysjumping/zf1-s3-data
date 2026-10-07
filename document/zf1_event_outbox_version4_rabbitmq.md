# ZF1 Event Outbox System — Version 4
## Message Broker Architecture with RabbitMQ

**Target stack**

- Zend Framework 1
- PHP 7.4
- MariaDB
- PHP CLI
- RabbitMQ
- systemd on CentOS
- No Redis required

---

# 1. Purpose

Version 4 evolves the MariaDB Event Outbox architecture into a broker-based event system.

The most important rule remains unchanged:

> Business modules write business data and an outbox event in the same MariaDB transaction. They do not synchronously depend on realtime, audit, analytics, email, or other secondary services.

Version 4 adds **RabbitMQ** between the MariaDB outbox and downstream consumers.

```text
                         SAME DATABASE TRANSACTION
                         -------------------------

User
 |
 v
ZF1 Business Module
 |
 +---- INSERT/UPDATE business data
 |
 +---- INSERT event_outbox
 |
 +---- COMMIT
 |
 v
Return response


                         ASYNCHRONOUS
                         ------------

MariaDB event_outbox
        |
        v
Outbox Publisher
        |
        | publish
        v
+-----------------------------+
|          RabbitMQ           |
|                             |
|       s3.events exchange    |
+-----------------------------+
        |
        +----------------+----------------+----------------+
        |                |                |                |
        v                v                v                v
 realtime.queue      audit.queue    analytics.queue    other.queue
        |                |                |
        v                v                v
 Realtime Consumer   Audit Consumer  Analytics Consumer
```

RabbitMQ becomes responsible for broker-side routing and queueing, while MariaDB remains responsible for the transactional bridge between business data and event publication.

---

# 2. Why Keep the Outbox When RabbitMQ Exists?

A tempting implementation is:

```text
ZF1
 |
 +---- MariaDB
 |
 +---- RabbitMQ
```

This creates a **dual-write problem**.

Example:

```text
1. INSERT email into MariaDB
      SUCCESS

2. Publish email.sent to RabbitMQ
      FAILURE
```

The business operation exists, but the event was lost.

The reverse order is also dangerous:

```text
1. Publish RabbitMQ event
      SUCCESS

2. MariaDB transaction
      FAILURE
```

Consumers can receive an event for business data that was never committed.

Version 4 therefore keeps the Transactional Outbox Pattern:

```text
BEGIN

    save business data

    INSERT event_outbox

COMMIT
```

Only after commit does a separate publisher send the event to RabbitMQ.

```text
MariaDB transaction
       |
       v
 event_outbox
       |
       v
Outbox Publisher
       |
       v
RabbitMQ
```

---

# 3. Version 3 vs Version 4

## Version 3

```text
MariaDB
 |
 +-- event_outbox
 |
 +-- event_delivery
        |
        v
   PHP Workers
        |
        +--> Realtime
        +--> Audit
        +--> Analytics
```

MariaDB manages individual consumer delivery records.

## Version 4

```text
MariaDB
 |
 +-- event_outbox
        |
        v
 Outbox Publisher
        |
        v
     RabbitMQ
        |
    Exchange
   /    |     \
  v     v      v
queue  queue   queue
 |      |       |
 v      v       v
consumer...
```

RabbitMQ now performs much of the downstream routing and queue management.

Therefore Version 4 does **not need `event_delivery` for normal broker consumers**.

The MariaDB outbox instead tracks whether the publisher successfully handed the event to RabbitMQ.

---

# 4. Main Components

Version 4 contains these major components:

```text
1. Business Module
2. S3_Event_Outbox
3. MariaDB event_outbox
4. S3_Event_Publisher
5. RabbitMQ Exchange
6. RabbitMQ Queues
7. RabbitMQ Consumers
8. Event-specific handlers
9. Idempotency storage
10. systemd services
```

Responsibilities:

```text
Business Module
    Creates business events.

Outbox
    Durably stores events in the same transaction.

Publisher
    Reads unpublished outbox events and publishes them.

Exchange
    Routes messages according to routing keys.

Queue
    Stores messages until a consumer processes them.

Consumer
    Reads messages from a queue.

Dispatcher
    Executes the correct PHP handler for event_type.

Handler
    Performs event-specific work.
```

---

# 5. RabbitMQ Concepts

## Producer

A program publishing a message.

In Version 4:

```text
S3_Event_Publisher
```

is the RabbitMQ producer.

## Exchange

The producer normally publishes to an exchange.

```text
Publisher
   |
   v
s3.events
```

The exchange decides which queue or queues receive the event.

## Queue

A queue stores messages for consumers.

Examples:

```text
s3.realtime
s3.audit
s3.analytics
```

## Binding

A binding connects an exchange to a queue using a routing pattern.

Example:

```text
email.sent
     |
     v
s3.events
     |
     +----> s3.realtime
     +----> s3.audit
     +----> s3.analytics
```

## Consumer

A long-running process that receives messages from a queue.

## ACK

Consumer tells RabbitMQ:

```text
I successfully processed this message.
```

RabbitMQ can then remove it from the queue.

## NACK

Consumer reports failure.

Depending on configuration, the message can be requeued or routed elsewhere.

---

# 6. Exchange Type

For business events, a RabbitMQ **topic exchange** is a good fit.

Exchange:

```text
s3.events
```

Routing keys:

```text
email.sent
email.received
blog.created
blog.updated
product.created
product.updated
crm.lead.assigned
document.approved
task.completed
```

Topic patterns can route families of events.

```text
email.*
blog.*
product.*
crm.#
```

`*` matches one routing-key segment.

`#` matches zero or more segments.

---

# 7. Example Routing

```text
                            s3.events
                           Topic Exchange
                                |
             +------------------+------------------+
             |                  |                  |
             v                  v                  v
       s3.realtime          s3.audit          s3.analytics

Bindings:

s3.realtime
    email.*
    blog.*
    product.*
    crm.*
    document.*
    task.*

s3.audit
    #

s3.analytics
    email.sent
    blog.created
    product.created
    product.updated
```

The audit queue receives every event because:

```text
#
```

matches all routing keys.

---

# 8. MariaDB `event_outbox`

```sql
CREATE TABLE event_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Unique business event ID',

    event_type VARCHAR(100) NOT NULL
        COMMENT 'Event type and RabbitMQ routing key',

    entity_type VARCHAR(50) DEFAULT NULL
        COMMENT 'Related business entity type',

    entity_id VARCHAR(100) DEFAULT NULL
        COMMENT 'Related business entity ID',

    payload LONGTEXT DEFAULT NULL
        COMMENT 'JSON encoded event payload',

    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        COMMENT 'pending, publishing, published, failed',

    retry_count INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of RabbitMQ publish failures',

    max_retries INT UNSIGNED NOT NULL DEFAULT 20
        COMMENT 'Maximum publish attempts',

    next_retry_at DATETIME DEFAULT NULL
        COMMENT 'Earliest next publish attempt',

    locked_by VARCHAR(100) DEFAULT NULL
        COMMENT 'Publisher process owning the event',

    locked_at DATETIME DEFAULT NULL
        COMMENT 'Time publisher claimed the event',

    last_error TEXT DEFAULT NULL
        COMMENT 'Last publisher error',

    created_at DATETIME NOT NULL
        COMMENT 'Event creation time',

    published_at DATETIME DEFAULT NULL
        COMMENT 'Time RabbitMQ publication was confirmed',

    PRIMARY KEY (id),

    KEY idx_publisher (
        status,
        next_retry_at,
        id
    ),

    KEY idx_stale (
        status,
        locked_at
    ),

    KEY idx_event_type (
        event_type
    ),

    KEY idx_entity (
        entity_type,
        entity_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Lifecycle:

```text
pending
   |
   | publisher claims
   v
publishing
   |
   +---- broker publish confirmed ----> published
   |
   +---- failure ----------------------> pending
                                             |
                                             | retry later
                                             v
                                         publishing

after maximum attempts:

failed
```

---

# 9. Event Message Format

A standard event envelope is important.

```json
{
    "event_id": 1001,
    "event_type": "email.sent",
    "entity_type": "email",
    "entity_id": "50021",
    "occurred_at": "2026-10-06T03:00:00+09:00",
    "payload": {
        "email_id": 50021,
        "sender_id": 100,
        "recipient_id": 200
    }
}
```

The broker routing key is:

```text
email.sent
```

Keep `event_type` in the message as well. Consumers should not need to infer all business meaning from broker metadata.

---

# 10. PHP RabbitMQ Client

A common PHP client is:

```text
php-amqplib/php-amqplib
```

For an offline environment, download and prepare the package and its Composer dependencies on an Internet-connected machine, then transfer the resulting dependency set to the server according to your normal offline deployment process.

The examples below assume Composer's generated:

```text
vendor/autoload.php
```

is available to the CLI publisher/consumer.

Do not manually copy only one package directory while omitting its required dependencies.

---

# 11. Configuration

Example `application.ini`:

```ini
event.publisher.sleepSeconds = 1
event.publisher.staleMinutes = 10
event.publisher.maxRetries = 20

rabbitmq.host = "127.0.0.1"
rabbitmq.port = 5672
rabbitmq.user = "s3app"
rabbitmq.password = "CHANGE_ME"
rabbitmq.vhost = "/s3"

rabbitmq.exchange = "s3.events"

rabbitmq.queue.realtime = "s3.realtime"
rabbitmq.queue.audit = "s3.audit"
rabbitmq.queue.analytics = "s3.analytics"
```

Do not commit production RabbitMQ passwords to source control. Prefer environment/server-managed secrets in production.

---

# 12. `S3_Event_Outbox`

```text
library/S3/Event/Outbox.php
```

```php
<?php

class S3_Event_Outbox
{
    protected $db;

    protected $maxRetries;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        $maxRetries = 20
    ) {
        $this->db = $db;
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
                'Unable to encode event payload: ' .
                json_last_error_msg()
            );
        }

        $this->db->insert(
            'event_outbox',
            array(
                'event_type'   => $eventType,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'payload'      => $json,
                'status'       => 'pending',
                'retry_count'  => 0,
                'max_retries'  => $this->maxRetries,
                'created_at'   => date('Y-m-d H:i:s')
            )
        );

        return (int) $this->db->lastInsertId();
    }
}
```

---

# 13. Business Module Example

```php
$db->beginTransaction();

try {
    $emailId = $emailModel->insert(
        $emailData
    );

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

The request does **not** wait for RabbitMQ consumers.

---

# 14. RabbitMQ Connection Factory

```text
library/S3/Event/RabbitConnectionFactory.php
```

```php
<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;

class S3_Event_RabbitConnectionFactory
{
    protected $config;

    public function __construct(Zend_Config $config)
    {
        $this->config = $config;
    }

    public function create()
    {
        return new AMQPStreamConnection(
            $this->config->host,
            (int) $this->config->port,
            $this->config->user,
            $this->config->password,
            $this->config->vhost
        );
    }
}
```

For production, connection timeout, read/write timeout, heartbeat, TLS, and recovery policy should be explicitly configured and tested for your network and client-library version.

---

# 15. RabbitMQ Topology Setup

The exchange and queues should be durable.

```text
library/S3/Event/RabbitTopology.php
```

```php
<?php

class S3_Event_RabbitTopology
{
    protected $exchange;

    public function __construct($exchange)
    {
        $this->exchange = $exchange;
    }

    public function declareTopology($channel)
    {
        /*
         * exchange_declare(
         *   exchange,
         *   type,
         *   passive,
         *   durable,
         *   auto_delete
         * )
         */
        $channel->exchange_declare(
            $this->exchange,
            'topic',
            false,
            true,
            false
        );

        $this->declareQueue(
            $channel,
            's3.realtime',
            array(
                'email.*',
                'blog.*',
                'product.*',
                'crm.*',
                'document.*',
                'task.*'
            )
        );

        $this->declareQueue(
            $channel,
            's3.audit',
            array('#')
        );

        $this->declareQueue(
            $channel,
            's3.analytics',
            array(
                'email.sent',
                'blog.created',
                'product.created',
                'product.updated'
            )
        );
    }

    protected function declareQueue(
        $channel,
        $queue,
        array $bindings
    ) {
        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false
        );

        foreach ($bindings as $routingKey) {
            $channel->queue_bind(
                $queue,
                $this->exchange,
                $routingKey
            );
        }
    }
}
```

In a larger deployment, topology may be provisioned separately from application startup. Keeping topology definitions in source still provides an auditable description of expected infrastructure.

---

# 16. Publisher Responsibilities

The publisher:

```text
1. Claims one pending outbox event.
2. Commits the short MariaDB claim transaction.
3. Builds the event envelope.
4. Publishes it to RabbitMQ.
5. Waits for publisher confirmation.
6. Marks the outbox row published.
7. On failure, schedules retry.
```

Do **not** keep the MariaDB transaction open while waiting on RabbitMQ.

---

# 17. `S3_Event_Publisher`

```text
library/S3/Event/Publisher.php
```

```php
<?php

use PhpAmqpLib\Message\AMQPMessage;

class S3_Event_Publisher
{
    protected $db;

    protected $channel;

    protected $exchange;

    protected $publisherId;

    protected $staleMinutes;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        $channel,
        $exchange,
        $staleMinutes = 10
    ) {
        $this->db = $db;
        $this->channel = $channel;
        $this->exchange = $exchange;

        $this->publisherId =
            gethostname() . '-' . getmypid();

        $this->staleMinutes = max(
            1,
            (int) $staleMinutes
        );

        /*
         * Enable RabbitMQ publisher confirms.
         */
        $this->channel->confirm_select();
    }

    public function processOne()
    {
        $event = $this->claimEvent();

        if (!$event) {
            return false;
        }

        try {
            $this->publishToRabbitMq($event);

            $this->markPublished(
                $event['id']
            );

        } catch (Exception $e) {
            $this->markFailedAttempt(
                $event,
                $e
            );

            /*
             * Depending on the error, the caller may also
             * rebuild the RabbitMQ connection/channel.
             */
        }

        return true;
    }

    protected function claimEvent()
    {
        $this->db->beginTransaction();

        try {
            $sql = "
                SELECT *
                FROM event_outbox
                WHERE status = 'pending'
                  AND (
                      next_retry_at IS NULL
                      OR next_retry_at <= NOW()
                  )
                ORDER BY id
                LIMIT 1
                FOR UPDATE
            ";

            $event = $this->db->fetchRow($sql);

            if (!$event) {
                $this->db->commit();

                return null;
            }

            $now = date('Y-m-d H:i:s');

            $where = array(
                $this->db->quoteInto(
                    'id = ?',
                    $event['id']
                ),
                "status = 'pending'"
            );

            $affected = $this->db->update(
                'event_outbox',
                array(
                    'status'    => 'publishing',
                    'locked_by' => $this->publisherId,
                    'locked_at' => $now
                ),
                $where
            );

            if ($affected !== 1) {
                throw new Exception(
                    'Unable to claim event ' .
                    $event['id']
                );
            }

            $this->db->commit();

            $event['status'] = 'publishing';
            $event['locked_by'] =
                $this->publisherId;
            $event['locked_at'] = $now;

            return $event;

        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    protected function publishToRabbitMq(
        array $event
    ) {
        $payload = json_decode(
            $event['payload'],
            true
        );

        if ($payload === null &&
            $event['payload'] !== 'null') {
            throw new Exception(
                'Invalid outbox payload JSON'
            );
        }

        $envelope = array(
            'event_id'    => (int) $event['id'],
            'event_type'  => $event['event_type'],
            'entity_type' => $event['entity_type'],
            'entity_id'   => $event['entity_id'],
            'occurred_at' => $event['created_at'],
            'payload'     => $payload
        );

        $json = json_encode($envelope);

        if ($json === false) {
            throw new Exception(
                'Unable to encode event envelope'
            );
        }

        $message = new AMQPMessage(
            $json,
            array(
                'content_type'  =>
                    'application/json',

                'delivery_mode' => 2,

                'message_id' =>
                    (string) $event['id'],

                'type' =>
                    $event['event_type'],

                'timestamp' =>
                    time()
            )
        );

        $this->channel->basic_publish(
            $message,
            $this->exchange,
            $event['event_type']
        );

        /*
         * Wait for broker publisher confirmation.
         *
         * Exact timeout method/signature can vary by
         * php-amqplib version. Pin and test the client
         * version used by your deployment.
         */
        $this->channel
            ->wait_for_pending_acks_returns();
    }

    protected function markPublished($eventId)
    {
        $where = array(
            $this->db->quoteInto(
                'id = ?',
                $eventId
            ),
            $this->db->quoteInto(
                'locked_by = ?',
                $this->publisherId
            ),
            "status = 'publishing'"
        );

        $affected = $this->db->update(
            'event_outbox',
            array(
                'status'        => 'published',
                'published_at'  =>
                    date('Y-m-d H:i:s'),
                'next_retry_at' => null,
                'locked_by'     => null,
                'locked_at'     => null,
                'last_error'    => null
            ),
            $where
        );

        if ($affected !== 1) {
            throw new Exception(
                'Lost ownership of event ' .
                $eventId
            );
        }
    }

    protected function markFailedAttempt(
        array $event,
        Exception $e
    ) {
        $retryCount =
            (int) $event['retry_count'] + 1;

        $maxRetries =
            (int) $event['max_retries'];

        $error = substr(
            $e->getMessage(),
            0,
            65535
        );

        $where = array(
            $this->db->quoteInto(
                'id = ?',
                $event['id']
            ),
            $this->db->quoteInto(
                'locked_by = ?',
                $this->publisherId
            ),
            "status = 'publishing'"
        );

        if ($retryCount >= $maxRetries) {
            $this->db->update(
                'event_outbox',
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

        $delay = $this->getRetryDelay(
            $retryCount
        );

        $this->db->update(
            'event_outbox',
            array(
                'status'      => 'pending',
                'retry_count' => $retryCount,

                'next_retry_at' => date(
                    'Y-m-d H:i:s',
                    time() + $delay
                ),

                'locked_by'  => null,
                'locked_at'  => null,
                'last_error' => $error
            ),
            $where
        );
    }

    protected function getRetryDelay($retryCount)
    {
        $delays = array(
            5,
            15,
            30,
            60,
            300,
            900,
            1800,
            3600
        );

        $index = (int) $retryCount - 1;

        if ($index < 0) {
            $index = 0;
        }

        if ($index >= count($delays)) {
            return 3600;
        }

        return $delays[$index];
    }

    public function recoverStaleEvents()
    {
        $minutes = $this->staleMinutes;

        $sql = "
            UPDATE event_outbox
            SET
                status = 'pending',
                locked_by = NULL,
                locked_at = NULL,
                next_retry_at = NOW(),
                last_error =
                    'Recovered abandoned publisher claim'
            WHERE status = 'publishing'
              AND locked_at IS NOT NULL
              AND locked_at <
                  DATE_SUB(
                      NOW(),
                      INTERVAL {$minutes} MINUTE
                  )
        ";

        return $this->db
            ->query($sql)
            ->rowCount();
    }
}
```

---

# 18. Publisher Confirms Are Important

There is a major difference between:

```text
PHP called basic_publish()
```

and:

```text
RabbitMQ confirmed the publication
```

Version 4 should use publisher confirms.

Conceptually:

```text
Publisher
   |
   | publish event 1001
   v
RabbitMQ
   |
   | ACK publication
   v
Publisher
   |
   v
mark event 1001 published
```

Without confirmation, the publisher could mark an event as published without sufficient evidence that the broker accepted it.

---

# 19. Important Exactly-Once Limitation

Publisher confirms still do **not** make the whole system exactly once.

Consider:

```text
Publisher
   |
   | event 1001
   v
RabbitMQ
   |
   | confirm
   v
Publisher
   |
   X process/database failure
```

RabbitMQ has the message, but MariaDB may still say:

```text
publishing
```

Stale recovery can republish it.

Therefore RabbitMQ may receive event 1001 again.

This is expected.

Version 4 uses:

> **at-least-once publication + idempotent consumers**

---

# 20. Generic Consumer Dispatcher

Each consumer may need different functions for different `event_type` values.

Do not put all event logic in a huge worker `switch`.

```text
RabbitMQ message
      |
      v
Consumer
      |
      v
Event Dispatcher
      |
      +-- email.sent --------> EmailSent Handler
      |
      +-- blog.created ------> BlogCreated Handler
      |
      +-- product.updated ---> ProductUpdated Handler
```

Interface:

```php
<?php

interface S3_Event_MessageHandlerInterface
{
    public function handle(array $event);
}
```

Dispatcher:

```php
<?php

class S3_Event_MessageDispatcher
{
    protected $handlers = array();

    public function register(
        $eventType,
        S3_Event_MessageHandlerInterface $handler
    ) {
        $this->handlers[$eventType] = $handler;

        return $this;
    }

    public function dispatch(array $event)
    {
        if (empty($event['event_type'])) {
            throw new Exception(
                'Missing event_type'
            );
        }

        $eventType = $event['event_type'];

        if (!isset($this->handlers[$eventType])) {
            throw new Exception(
                'No handler for event type: ' .
                $eventType
            );
        }

        $this->handlers[$eventType]
            ->handle($event);
    }
}
```

---

# 21. Example `EmailSent` Handler

```php
<?php

class S3_Event_Handler_EmailSent
    implements S3_Event_MessageHandlerInterface
{
    protected $notificationService;

    public function __construct(
        $notificationService
    ) {
        $this->notificationService =
            $notificationService;
    }

    public function handle(array $event)
    {
        if (empty($event['payload']['email_id'])) {
            throw new Exception(
                'email_id is required'
            );
        }

        $emailId =
            (int) $event['payload']['email_id'];

        $this->notificationService
            ->notifyEmailSent($emailId);
    }
}
```

---

# 22. Example `BlogCreated` Handler

```php
<?php

class S3_Event_Handler_BlogCreated
    implements S3_Event_MessageHandlerInterface
{
    protected $notificationService;

    public function __construct(
        $notificationService
    ) {
        $this->notificationService =
            $notificationService;
    }

    public function handle(array $event)
    {
        $blogId =
            (int) $event['payload']['blog_id'];

        $this->notificationService
            ->notifyBlogCreated($blogId);
    }
}
```

Registration:

```php
$dispatcher =
    new S3_Event_MessageDispatcher();

$dispatcher->register(
    'email.sent',
    new S3_Event_Handler_EmailSent(
        $notificationService
    )
);

$dispatcher->register(
    'blog.created',
    new S3_Event_Handler_BlogCreated(
        $notificationService
    )
);
```

---

# 23. Generic RabbitMQ Consumer

```text
library/S3/Event/RabbitConsumer.php
```

```php
<?php

class S3_Event_RabbitConsumer
{
    protected $channel;

    protected $queue;

    protected $dispatcher;

    public function __construct(
        $channel,
        $queue,
        S3_Event_MessageDispatcher $dispatcher
    ) {
        $this->channel = $channel;
        $this->queue = $queue;
        $this->dispatcher = $dispatcher;
    }

    public function run()
    {
        /*
         * Process one unacknowledged message at a time.
         * Increase carefully after measuring handlers.
         */
        $this->channel->basic_qos(
            null,
            1,
            null
        );

        $callback = function ($message) {
            $this->processMessage($message);
        };

        $this->channel->basic_consume(
            $this->queue,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    protected function processMessage($message)
    {
        try {
            $event = json_decode(
                $message->body,
                true
            );

            if (!is_array($event)) {
                throw new Exception(
                    'Invalid event JSON'
                );
            }

            $this->dispatcher->dispatch(
                $event
            );

            /*
             * ACK only after successful processing.
             */
            $message->ack();

        } catch (Exception $e) {
            error_log(
                'Consumer error: ' .
                $e->getMessage()
            );

            /*
             * Simple first implementation:
             * reject without immediate requeue.
             *
             * Production should normally configure a
             * dead-letter/retry strategy rather than create
             * an infinite hot requeue loop.
             */
            $message->nack(
                false,
                false
            );
        }
    }
}
```

---

# 24. Why ACK Must Happen After Processing

Wrong:

```text
Receive message
     |
     v
ACK
     |
     v
Process
     |
     X crash
```

RabbitMQ believes the message completed, so the event can be lost from the consumer's perspective.

Correct:

```text
Receive
   |
   v
Process
   |
   v
Success
   |
   v
ACK
```

If the consumer dies before ACK, RabbitMQ can redeliver the unacknowledged message.

This is another reason handlers must be idempotent.

---

# 25. Consumer Idempotency Table

Each durable consumer should be able to detect an already processed event.

Example:

```sql
CREATE TABLE processed_event (
    consumer VARCHAR(100) NOT NULL
        COMMENT 'Logical consumer name',

    event_id BIGINT UNSIGNED NOT NULL
        COMMENT 'Original event_outbox ID',

    processed_at DATETIME NOT NULL,

    PRIMARY KEY (
        consumer,
        event_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Examples:

```text
realtime  1001
audit     1001
analytics 1001
```

This allows the same event to be processed independently by different consumers while preventing duplicate work within one consumer.

---

# 26. Idempotent Consumer Pattern

For a database-based consumer:

```text
BEGIN

    Has (consumer,event_id) already been processed?

    YES:
        COMMIT
        ACK RabbitMQ

    NO:
        perform local database changes
        INSERT processed_event
        COMMIT
        ACK RabbitMQ
```

The local business changes and idempotency record should be in the same local database transaction when possible.

Example skeleton:

```php
$db->beginTransaction();

try {
    $exists = $db->fetchOne(
        "
        SELECT event_id
        FROM processed_event
        WHERE consumer = ?
          AND event_id = ?
        ",
        array(
            'audit',
            $event['event_id']
        )
    );

    if ($exists) {
        $db->commit();

        return;
    }

    /*
     * Perform audit operation.
     */

    $db->insert(
        'processed_event',
        array(
            'consumer'     => 'audit',
            'event_id'     => $event['event_id'],
            'processed_at' =>
                date('Y-m-d H:i:s')
        )
    );

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();

    throw $e;
}
```

---

# 27. Dead-Letter Queue

Simply doing:

```text
failure -> requeue immediately -> failure -> requeue immediately
```

can create a hot infinite loop.

A production broker architecture should have a failure strategy.

One useful pattern:

```text
main queue
   |
   | processing fails
   v
retry/dead-letter mechanism
   |
   | delay
   v
main queue

after policy limit / poison message
   |
   v
dead-letter queue
```

Example queue names:

```text
s3.realtime
s3.realtime.retry
s3.realtime.dead

s3.audit
s3.audit.retry
s3.audit.dead
```

The exact RabbitMQ retry topology should be chosen deliberately. Common approaches include TTL + dead-letter exchanges or delayed-message infrastructure when available. Do not implement immediate unlimited requeue.

---

# 28. Poison Messages

A poison message is one that repeatedly fails because its content or handler is invalid.

Example:

```json
{
    "event_type": "email.sent",
    "payload": {
        "email_id": null
    }
}
```

Retrying this every second will not fix it.

It should eventually be isolated for investigation:

```text
s3.realtime.dead
```

Operations can inspect:

```text
event_id
event_type
payload
failure reason
```

and decide whether to repair/replay or discard it.

---

# 29. Publisher CLI

```text
scripts/event-publisher.php
```

Conceptual complete bootstrap:

```php
<?php

define(
    'APPLICATION_PATH',
    realpath(
        dirname(__FILE__) . '/../application'
    )
);

define(
    'APPLICATION_ENV',
    getenv('APPLICATION_ENV')
        ? getenv('APPLICATION_ENV')
        : 'production'
);

set_include_path(
    realpath(
        dirname(__FILE__) . '/../library'
    ) .
    PATH_SEPARATOR .
    get_include_path()
);

require_once
    dirname(__FILE__) .
    '/../vendor/autoload.php';

require_once 'Zend/Application.php';

$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH .
        '/configs/application.ini'
);

$application->bootstrap();

$bootstrap = $application->getBootstrap();

$db = $bootstrap->getResource('db');

$config = new Zend_Config_Ini(
    APPLICATION_PATH .
        '/configs/application.ini',
    APPLICATION_ENV
);

$factory =
    new S3_Event_RabbitConnectionFactory(
        $config->rabbitmq
    );

$connection = $factory->create();

$channel = $connection->channel();

$topology = new S3_Event_RabbitTopology(
    $config->rabbitmq->exchange
);

$topology->declareTopology($channel);

$publisher = new S3_Event_Publisher(
    $db,
    $channel,
    $config->rabbitmq->exchange,
    $config->event->publisher->staleMinutes
);

$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    pcntl_signal(
        SIGTERM,
        function () use (&$running) {
            $running = false;
        }
    );

    pcntl_signal(
        SIGINT,
        function () use (&$running) {
            $running = false;
        }
    );
}

$lastRecovery = 0;

while ($running) {
    try {
        if ((time() - $lastRecovery) >= 60) {
            $publisher->recoverStaleEvents();

            $lastRecovery = time();
        }

        $processed =
            $publisher->processOne();

        if (!$processed) {
            sleep(
                (int)
                $config->event
                    ->publisher
                    ->sleepSeconds
            );
        }

    } catch (Exception $e) {
        error_log(
            'Publisher error: ' .
            $e->getMessage()
        );

        sleep(1);
    }
}

$channel->close();
$connection->close();
```

A production implementation should reconnect RabbitMQ after connection/channel failures instead of assuming one connection lives forever.

---

# 30. Realtime Consumer CLI

```text
scripts/realtime-consumer.php
```

```php
<?php

/*
 * Bootstrap application and create:
 *
 * $config
 * $notificationService
 * $connection
 * $channel
 */

$dispatcher =
    new S3_Event_MessageDispatcher();

$dispatcher->register(
    'email.sent',
    new S3_Event_Handler_EmailSent(
        $notificationService
    )
);

$dispatcher->register(
    'blog.created',
    new S3_Event_Handler_BlogCreated(
        $notificationService
    )
);

/*
 * Register all realtime event handlers here.
 */

$consumer = new S3_Event_RabbitConsumer(
    $channel,
    $config->rabbitmq->queue->realtime,
    $dispatcher
);

$consumer->run();
```

Audit and analytics consumers use their own queues and dispatchers.

---

# 31. Project Structure

```text
project/
|
+-- application/
|   +-- configs/
|       +-- application.ini
|
+-- library/
|   +-- S3/
|       +-- Event/
|           +-- Outbox.php
|           +-- Publisher.php
|           +-- RabbitConnectionFactory.php
|           +-- RabbitTopology.php
|           +-- RabbitConsumer.php
|           +-- MessageDispatcher.php
|           +-- MessageHandlerInterface.php
|           |
|           +-- Handler/
|               +-- EmailSent.php
|               +-- BlogCreated.php
|               +-- ProductUpdated.php
|               +-- DocumentApproved.php
|
+-- scripts/
|   +-- event-publisher.php
|   +-- realtime-consumer.php
|   +-- audit-consumer.php
|   +-- analytics-consumer.php
|
+-- sql/
|   +-- event_outbox_v4.sql
|   +-- processed_event.sql
|
+-- vendor/
```

---

# 32. systemd — Publisher

```text
/etc/systemd/system/s3-event-publisher.service
```

```ini
[Unit]
Description=S3 Event Outbox RabbitMQ Publisher
After=network.target mariadb.service rabbitmq-server.service

[Service]
Type=simple

User=apache
Group=apache

WorkingDirectory=/var/www/myproject

Environment=APPLICATION_ENV=production

ExecStart=/usr/bin/php /var/www/myproject/scripts/event-publisher.php

Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

---

# 33. systemd — Realtime Consumer

```text
/etc/systemd/system/s3-realtime-consumer.service
```

```ini
[Unit]
Description=S3 RabbitMQ Realtime Consumer
After=network.target rabbitmq-server.service

[Service]
Type=simple

User=apache
Group=apache

WorkingDirectory=/var/www/myproject

Environment=APPLICATION_ENV=production

ExecStart=/usr/bin/php /var/www/myproject/scripts/realtime-consumer.php

Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable:

```bash
sudo systemctl daemon-reload

sudo systemctl enable s3-event-publisher
sudo systemctl enable s3-realtime-consumer

sudo systemctl start s3-event-publisher
sudo systemctl start s3-realtime-consumer
```

Logs:

```bash
sudo journalctl -u s3-event-publisher -f

sudo journalctl -u s3-realtime-consumer -f
```

---

# 34. What Happens If RabbitMQ Is Down?

This is a critical Version 4 scenario.

```text
ZF1
 |
 +-- business data
 |
 +-- event_outbox
 |
 +-- COMMIT
 |
 v
User receives response
```

The business request does not need RabbitMQ.

Later:

```text
Outbox Publisher
      |
      v
RabbitMQ
   X DOWN
      |
      v
publish failure
      |
      v
event_outbox remains retryable
```

Example:

```text
status        pending
retry_count   1
next_retry_at future
last_error    connection refused
```

When RabbitMQ returns:

```text
pending -> publishing -> published
```

This is one of the primary reasons to retain the MariaDB outbox.

---

# 35. What Happens If a Consumer Is Down?

RabbitMQ remains available:

```text
Publisher -> RabbitMQ -> s3.realtime queue
                           |
                           X consumer down
```

Messages remain queued according to RabbitMQ durability/retention configuration.

When the consumer returns:

```text
s3.realtime
     |
     v
consumer
     |
     v
process backlog
```

ZF1 and the publisher do not need to wait for the consumer.

---

# 36. What Happens If RabbitMQ Restarts?

For reliable broker storage, use:

- durable exchange;
- durable queues;
- persistent messages;
- appropriate RabbitMQ durable queue type/configuration;
- publisher confirms.

The examples use:

```php
delivery_mode => 2
```

for persistent messages and declare exchange/queues durable.

Broker durability still depends on RabbitMQ configuration and operational design. Application code alone cannot guarantee storage against every infrastructure failure.

---

# 37. Backpressure

Suppose analytics becomes slow.

```text
RabbitMQ

realtime queue    10 messages
audit queue       20 messages
analytics queue   500,000 messages
```

Realtime can continue normally because analytics has an independent queue.

Operations can:

```text
1 analytics consumer
        |
        v
add more consumers
        |
        v
4 analytics consumers
```

RabbitMQ distributes queue messages among competing consumers.

This is a major advantage over coupling all downstream work into one PHP request.

---

# 38. Prefetch

Consumer prefetch controls how many unacknowledged messages RabbitMQ sends to a consumer.

Conservative starting point:

```php
$channel->basic_qos(
    null,
    1,
    null
);
```

Meaning conceptually:

```text
send one
   |
wait for ACK
   |
send next
```

Later, for fast handlers:

```text
prefetch = 10
```

may improve throughput.

Do not increase it blindly for slow or memory-heavy jobs.

---

# 39. Ordering

Do not assume global event ordering in a distributed system with multiple queues and multiple consumers.

Example:

```text
product.created
product.updated
```

may require ordering for one entity.

If strict ordering is a real business requirement, design explicitly for it, considering:

- routing;
- queue topology;
- number of competing consumers;
- per-entity sequencing/version numbers;
- idempotency;
- stale-event detection.

Do not rely solely on auto-increment event IDs to guarantee processing order across independent consumers.

---

# 40. Event Versioning

As the system grows, payload formats will change.

A useful envelope extension is:

```json
{
    "event_id": 1001,
    "event_type": "email.sent",
    "event_version": 1,
    "payload": {}
}
```

Then a future incompatible schema can use:

```text
event_version = 2
```

Consumers can validate supported versions.

Recommended:

```text
event_type       identifies meaning
event_version    identifies payload contract version
```

---

# 41. Improved Outbox Schema with Event Version

If versioning is adopted, add:

```sql
event_version SMALLINT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Version of event payload contract'
```

and publish it in the message envelope.

This is recommended for Version 4 because broker-based systems tend to have independently deployed consumers.

---

# 42. Security

Do not expose RabbitMQ port `5672` publicly.

Prefer:

```text
ZF1 servers
Publisher servers
Consumer servers
        |
        v
private network / VPN
        |
        v
RabbitMQ
```

Recommended controls:

- dedicated RabbitMQ application account;
- dedicated virtual host;
- least-privilege permissions;
- strong credentials;
- TLS when crossing untrusted networks;
- firewall restrictions;
- do not use the default guest account remotely;
- keep management UI restricted;
- do not put secrets in event payloads;
- validate event schemas in consumers.

Example conceptual RabbitMQ account:

```text
user: s3app
vhost: /s3
```

Grant only the permissions the application needs.

---

# 43. RabbitMQ Firewall

If RabbitMQ is on a separate internal server, allow the AMQP port only from authorized application/consumer hosts.

Conceptually:

```text
Internet
   X
   |
RabbitMQ :5672

Application private IPs
   |
   +---- allowed
```

Do not simply open AMQP to the entire Internet.

The RabbitMQ management interface should be even more restricted.

---

# 44. Monitoring

Monitor both MariaDB and RabbitMQ.

## MariaDB

Pending publication:

```sql
SELECT COUNT(*)
FROM event_outbox
WHERE status = 'pending';
```

Failed publication:

```sql
SELECT COUNT(*)
FROM event_outbox
WHERE status = 'failed';
```

Old pending events:

```sql
SELECT
    id,
    event_type,
    retry_count,
    next_retry_at,
    last_error,
    created_at
FROM event_outbox
WHERE status IN (
    'pending',
    'failed'
)
ORDER BY id;
```

## RabbitMQ

Monitor:

```text
queue depth
ready messages
unacknowledged messages
consumer count
publish rate
deliver rate
ack rate
redeliveries
dead-letter queues
connection/channel health
disk alarms
memory alarms
```

---

# 45. Manual Publisher Retry

For an administrator-controlled retry:

```sql
UPDATE event_outbox
SET
    status = 'pending',
    retry_count = 0,
    next_retry_at = NOW(),
    locked_by = NULL,
    locked_at = NULL,
    last_error = NULL
WHERE id = 1001
  AND status = 'failed';
```

Be aware that republishing can produce a duplicate message. Consumers must remain idempotent.

---

# 46. Retention

Once an event is marked `published`, it should not necessarily be deleted immediately.

A practical starting policy might retain published outbox records for troubleshooting.

Example query for records older than 90 days:

```sql
SELECT id
FROM event_outbox
WHERE status = 'published'
  AND published_at <
      DATE_SUB(NOW(), INTERVAL 90 DAY);
```

Actual retention should be chosen according to operational, audit, and storage requirements.

---

# 47. RabbitMQ Does Not Replace Business Storage

RabbitMQ should not become the authoritative database for:

```text
emails
products
customers
documents
tasks
```

MariaDB remains the business system of record.

RabbitMQ transports events between components.

```text
MariaDB
   =
business state

RabbitMQ
   =
event/message transport
```

---

# 48. Failure Matrix

| Failure | Business request | Outbox | RabbitMQ | Recovery |
|---|---|---|---|---|
| Realtime service down | succeeds | published | queued | consumer catches up |
| Consumer crashes before ACK | unaffected | published | redelivery possible | idempotent consumer |
| RabbitMQ down | succeeds | pending/retry | unavailable | publisher retries |
| Publisher crashes before publish | succeeds | publishing temporarily | none yet | stale recovery |
| Publisher crashes after broker confirm but before DB update | succeeds | may remain publishing | message exists | republish possible; idempotency |
| MariaDB unavailable during business request | fails normally | no reliable commit | irrelevant | DB recovery |
| Poison message | unaffected | published | repeated consumer failure | dead-letter handling |

---

# 49. Testing Plan

## Test A — Normal flow

1. Start MariaDB.
2. Start RabbitMQ.
3. Start publisher.
4. Start consumers.
5. Publish `email.sent`.
6. Verify outbox becomes `published`.
7. Verify appropriate queues receive/deliver it.
8. Verify handlers execute.
9. Verify ACK.

## Test B — RabbitMQ down

1. Stop RabbitMQ.
2. Perform business action.
3. Verify business transaction succeeds.
4. Verify outbox event exists.
5. Verify publisher records retry.
6. Restart RabbitMQ.
7. Verify event becomes published.

## Test C — Consumer down

1. Keep RabbitMQ/publisher running.
2. Stop realtime consumer.
3. Generate events.
4. Verify realtime queue grows.
5. Start consumer.
6. Verify backlog drains.

## Test D — Consumer crash before ACK

1. Receive event.
2. Kill consumer before ACK.
3. Restart consumer.
4. Verify redelivery.
5. Verify idempotency prevents duplicate effect.

## Test E — Duplicate publication

1. Publish event.
2. Simulate publisher failure after broker acceptance.
3. Recover/retry outbox.
4. Verify duplicate can reach consumer.
5. Verify business effect happens once.

## Test F — Poison message

1. Send invalid payload.
2. Verify handler rejects it.
3. Verify retry/dead-letter policy.
4. Verify it does not create an infinite hot loop.

## Test G — Multiple consumers

Verify:

```text
email.sent
   |
   +--> realtime
   +--> audit
   +--> analytics
```

and that one consumer's outage does not stop the others.

---

# 50. Deployment Sequence

Recommended implementation order:

```text
1. Install/configure RabbitMQ in test environment.

2. Create dedicated vhost/user.

3. Create Version 4 event_outbox schema.

4. Implement S3_Event_Outbox.

5. Implement RabbitConnectionFactory.

6. Implement RabbitTopology.

7. Declare topic exchange.

8. Declare one queue: s3.realtime.

9. Implement Publisher.

10. Enable publisher confirms.

11. Test MariaDB -> RabbitMQ publication.

12. Implement generic consumer.

13. Implement MessageDispatcher.

14. Implement one event handler.

15. Implement ACK-after-success.

16. Add idempotency.

17. Test duplicate delivery.

18. Add audit queue/consumer.

19. Add analytics queue/consumer.

20. Add dead-letter/retry strategy.

21. Add systemd units.

22. Add monitoring.

23. Load-test.

24. Document event contracts.

25. Migrate production gradually.
```

---

# 51. Migration from Version 3

Version 3:

```text
event_outbox
    |
event_delivery
    |
PHP delivery workers
```

Version 4:

```text
event_outbox
    |
publisher
    |
RabbitMQ
    |
queues
    |
consumers
```

Do not abruptly delete Version 3 infrastructure during migration.

A safer migration is:

```text
1. Deploy RabbitMQ.

2. Deploy Version 4 publisher/consumer in test.

3. Verify event contracts.

4. Verify idempotency.

5. Stop Version 3 workers at a controlled point.

6. Ensure no required Version 3 deliveries are abandoned.

7. Start Version 4 path.

8. Monitor both outbox and broker.

9. Archive/remove obsolete event_delivery infrastructure later.
```

The exact cutover procedure depends on whether production events can be generated during migration.

---

# 52. Why RabbitMQ Fits Version 4

For this architecture RabbitMQ provides useful features that Version 3 had to model manually:

```text
queueing
routing
consumer distribution
ACK/NACK
redelivery
durable queues
publisher confirms
dead-letter routing
backpressure isolation
multiple consumers
```

MariaDB remains valuable for transactional publication.

The combination is:

```text
MariaDB Outbox
      +
RabbitMQ Broker
      +
Idempotent Consumers
```

---

# 53. When Not to Move to Version 4

Do not introduce RabbitMQ merely because it is more advanced.

Version 3 may remain better when:

```text
event volume is modest
few consumers exist
operations staff is small
MariaDB workers are reliable enough
simplicity is more important
```

Version 4 becomes attractive when:

```text
many independent consumers exist
queues grow significantly
consumer scaling is required
routing becomes complex
consumer outages must be isolated
broker observability is useful
```

A broker adds operational responsibility:

```text
installation
upgrades
monitoring
backup/recovery planning
security
TLS/certificates
disk capacity
memory capacity
cluster design
queue policies
```

---

# 54. Recommended Reliability Model

Version 4 should explicitly use:

> **At-least-once event delivery with idempotent consumers.**

Do not promise exactly-once processing.

The complete path is:

```text
Business transaction
      |
      v
Transactional Outbox
      |
      v
Safe Publisher Claim
      |
      v
RabbitMQ Publish
      |
      v
Publisher Confirm
      |
      v
Durable Queue
      |
      v
Consumer
      |
      v
Idempotency Check
      |
      v
Business Handler
      |
      v
ACK
```

Every boundary is designed so that a crash tends to cause a **retry** rather than silent event loss.

Retries imply possible duplicates.

Idempotency makes those duplicates safe.

---

# 55. Final Architecture

```text
+---------------------------------------------------------+
|                    ZF1 APPLICATION                     |
|                                                         |
| Email   Blog   Product   CRM   Document   Task          |
|   \       |       |       |       |       /             |
|    +------+-------+-------+-------+------+              |
|                       |                                 |
|                 S3_Event_Outbox                         |
+-----------------------|---------------------------------+
                        |
                        v
               +------------------+
               |     MariaDB      |
               |   event_outbox   |
               +------------------+
                        |
                        v
               +------------------+
               | Outbox Publisher |
               +------------------+
                        |
                        | AMQP
                        v
             +-----------------------+
             |       RabbitMQ        |
             |                       |
             |   s3.events (topic)   |
             +-----------+-----------+
                         |
          +--------------+--------------+
          |              |              |
          v              v              v
   s3.realtime       s3.audit      s3.analytics
       queue            queue           queue
          |              |              |
          v              v              v
     Realtime         Audit         Analytics
     Consumer        Consumer        Consumer
          |              |              |
          v              v              v
     Dispatcher      Dispatcher      Dispatcher
          |              |              |
          v              v              v
      Handlers         Handlers        Handlers
```

---

# 56. Key Rules to Keep

1. Never make normal business HTTP requests wait for secondary consumers.
2. Save business state and the outbox event in one MariaDB transaction.
3. Publish to RabbitMQ only after the transaction commits.
4. Keep publisher DB claim transactions short.
5. Use durable broker topology appropriate to the deployment.
6. Publish persistent messages.
7. Use publisher confirms.
8. ACK only after successful consumer processing.
9. Assume messages can be delivered more than once.
10. Make consumers idempotent.
11. Avoid unlimited immediate NACK/requeue loops.
12. Use dead-letter handling for poison messages.
13. Keep event names stable.
14. Version event contracts as they evolve.
15. Keep RabbitMQ private and authenticated.
16. Monitor both the outbox and broker queues.
17. Keep MariaDB as the business system of record.
18. Add more consumers without changing business modules.
19. Do not introduce broker complexity until the system actually benefits from it.
20. Treat the outbox + broker + consumers as one reliability architecture, not as unrelated components.

---

# 57. Suggested Next Improvements

After the basic Version 4 implementation is stable, consider these improvements individually:

```text
V4.1
  Dead-letter exchange + retry queues

V4.2
  Consumer idempotency helper/service

V4.3
  JSON event schema validation

V4.4
  Event contract/version registry

V4.5
  Publisher batching

V4.6
  Multiple publisher processes using SKIP LOCKED
  after MariaDB-version verification

V4.7
  RabbitMQ TLS

V4.8
  Queue monitoring and alert thresholds

V4.9
  Administrative event replay tool

V4.10
  High-availability RabbitMQ deployment
  if business requirements justify it
```

The first production priority should remain correctness and recoverability, not maximum throughput.
