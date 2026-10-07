# Redis for ZF1 --- Practical Beginner's Guide

## Redis with Zend Framework 1, PHP 7.4, MariaDB, Authorization, Presence, Caching, and Realtime Systems

## 1. Purpose

This document explains Redis in a practical and beginner-friendly way
and shows how it can be introduced into a Zend Framework 1 (ZF1), PHP
7.4, and MariaDB application.

The main principle is:

``` text
MariaDB
    = authoritative business database

Redis
    = very fast supporting data store
```

Redis should not automatically replace MariaDB. In a typical business
application, they work together.

A useful starting architecture is:

``` text
                         ZF1
                          |
             +------------+------------+
             |                         |
             v                         v
          MariaDB                    Redis
             |                         |
             |                         +-- Cache
             |                         +-- Permission cache
             |                         +-- Online presence
             |                         +-- Counters
             |                         +-- Rate limiting
             |                         +-- Temporary state
             |
             +-- Users
             +-- Email
             +-- Products
             +-- CRM
             +-- Roles / permissions
             +-- Transactional outbox
```

For the first implementation, Redis should be introduced gradually
rather than used everywhere at once.

------------------------------------------------------------------------

# 2. What Is Redis?

Redis is a data server designed for very fast access to data. Its
working data is normally kept in memory (RAM).

The easiest way to understand Redis is:

``` text
Redis
    =
Very fast shared key/value data store
```

For example:

``` text
name -> John
```

or:

``` text
user:100:name -> John
```

A MariaDB table might contain:

``` text
users
--------------------------------
id    name       department_id
100   John       5
101   David      8
```

Redis might contain supporting data such as:

``` text
user:100:name             -> John
user:100:online           -> 1
user:100:last_seen        -> 1791354000
permission:user:100       -> cached permissions
notification:unread:100   -> 12
```

The important distinction is:

``` text
MariaDB
    -> permanent structured business records

Redis
    -> fast shared/temporary/supporting data
```

------------------------------------------------------------------------

# 3. Why Is Redis Fast?

MariaDB provides powerful relational database capabilities:

``` text
SQL
JOIN
indexes
transactions
constraints
persistent storage
query planning
```

Redis primarily works with data in RAM and provides direct operations on
keys and specialized data structures.

Very simplified Redis access:

``` text
Application
     |
     | GET user:100
     v
   Redis
     |
     v
    RAM
```

Compared with a relational query:

``` text
Application
     |
     | SELECT ...
     v
  MariaDB
     |
     +-- SQL processing
     +-- query planning
     +-- indexes
     +-- rows
     +-- storage
```

This does not mean MariaDB is bad or unnecessarily slow. Redis and
MariaDB solve different problems.

------------------------------------------------------------------------

# 4. Redis Is Shared

A normal PHP variable exists only inside that PHP execution context.

For example:

``` php
$cache = array();
```

Different PHP requests may run in different processes:

``` text
Request A
    |
    v
PHP Process A
    |
    +-- local PHP memory


Request B
    |
    v
PHP Process B
    |
    +-- different PHP memory
```

Redis provides shared state:

``` text
PHP Request A -------+
                     |
PHP Request B -------+
                     |
PHP Request C -------+---- Redis
                     |
Workerman -----------+
                     |
CLI Worker ----------+
```

This is one of the major reasons Redis is useful in web applications.

------------------------------------------------------------------------

# 5. Basic Redis Key/Value Operations

The simplest Redis model is:

``` text
KEY -> VALUE
```

Example:

``` text
SET name "John"
```

Read it:

``` text
GET name
```

Result:

``` text
John
```

For an application, use meaningful names:

``` text
user:100:name
user:100:last_seen
auth:permissions:user:100
product:500
notification:unread:100
```

Example:

``` text
SET user:100:name "John"
GET user:100:name
```

------------------------------------------------------------------------

# 6. Key Naming Convention

Use consistent namespaces separated by colons.

Examples:

``` text
product:500
user:100
auth:permissions:user:100
presence:user:100
notification:unread:user:100
rate-limit:user:100
```

A useful general format is:

``` text
<system>:<resource>:<identifier>
```

For example:

``` text
auth:permissions:user:100
cache:product:500
presence:user:100
```

Consistent naming makes administration and debugging much easier.

------------------------------------------------------------------------

# 7. Expiration and TTL

One of Redis's most useful features is automatic expiration.

For example:

``` text
SETEX login:token:abc123 3600 "100"
```

means:

``` text
Key:
    login:token:abc123

Value:
    100

Lifetime:
    3600 seconds
```

After the expiration time, Redis removes the key automatically.

In PHP:

``` php
$redis->setex(
    'test:user:100',
    3600,
    'John'
);
```

Check the remaining lifetime:

``` php
$ttl = $redis->ttl(
    'test:user:100'
);
```

TTL is particularly useful for:

``` text
Cache
Online presence
Temporary tokens
Rate limiting
Short-lived state
```

------------------------------------------------------------------------

# 8. Redis Data Types

Redis supports more than simple strings.

Important types include:

  Type         Typical use
  ------------ ----------------------------------------
  String       Cache, JSON, token, counter
  Hash         Object-like fields
  List         Ordered values / simple queue patterns
  Set          Unique membership
  Sorted Set   Rankings and ordered membership
  Stream       Event/message processing

For a beginner, start with Strings.

Later, learn the other structures as actual requirements appear.

------------------------------------------------------------------------

# 9. Strings

Example:

``` text
SET user:100:name "John"
GET user:100:name
```

Strings can also contain JSON.

PHP example:

``` php
$user = array(
    'id' => 100,
    'name' => 'John',
    'department_id' => 5
);

$redis->setex(
    'user:100',
    3600,
    json_encode($user)
);
```

Reading:

``` php
$json = $redis->get(
    'user:100'
);

if ($json !== false) {
    $user = json_decode(
        $json,
        true
    );
}
```

------------------------------------------------------------------------

# 10. Hashes

A Redis Hash stores multiple fields under one key.

Conceptually:

``` text
user:100
    |
    +-- name = John
    +-- department_id = 5
    +-- status = active
```

Commands:

``` text
HSET user:100 name "John"
HSET user:100 department_id 5

HGET user:100 name
```

Result:

``` text
John
```

Hashes can be useful when individual fields need to be changed
independently.

For simple cached PHP objects, JSON Strings are often easier initially.

------------------------------------------------------------------------

# 11. Counters

Redis supports atomic increment/decrement operations.

Example:

``` text
SET notification:unread:100 12
```

Increment:

``` text
INCR notification:unread:100
```

Result:

``` text
13
```

Decrement:

``` text
DECR notification:unread:100
```

This is useful for:

``` text
Unread notification counts
Request counters
Rate limiting
Statistics
Sequence-like temporary counters
```

Because these operations are atomic, multiple processes can safely
increment the same counter without the ordinary read-modify-write race.

------------------------------------------------------------------------

# 12. Installing Redis for a ZF1 Server

The exact package commands depend on the CentOS/RHEL-family version and
enabled repositories.

The basic architecture is:

``` text
ZF1 / PHP
     |
     | TCP
     v
Redis Server
```

Redis commonly uses:

``` text
TCP port 6379
```

For a simple single-server installation, prefer:

``` text
127.0.0.1:6379
```

when only applications on the same server need Redis.

Do not expose Redis publicly to the Internet.

Production security should consider:

``` text
Network binding
Firewall
Authentication / ACL configuration where appropriate
Least network exposure
Service account and OS permissions
Logging and monitoring
Backups/persistence requirements
```

------------------------------------------------------------------------

# 13. PHP Redis Client

PHP needs a Redis client.

A common choice is the native `phpredis` extension.

For PHP 7.4, select a phpredis version/build that is actually compatible
with PHP 7.4 rather than assuming the newest package supports the old
PHP runtime.

Once the extension is installed, PHP can use:

``` php
$redis = new Redis();

$redis->connect(
    '127.0.0.1',
    6379
);
```

------------------------------------------------------------------------

# 14. Basic PHP Test

``` php
<?php

$redis = new Redis();

$redis->connect(
    '127.0.0.1',
    6379
);

$redis->set(
    'test:name',
    'John'
);

$name = $redis->get(
    'test:name'
);

echo $name;
```

Expected output:

``` text
John
```

Test expiration:

``` php
$redis->setex(
    'test:temporary',
    60,
    'Hello'
);
```

Read:

``` php
$value = $redis->get(
    'test:temporary'
);
```

Check TTL:

``` php
$ttl = $redis->ttl(
    'test:temporary'
);
```

Delete:

``` php
$redis->del(
    'test:temporary'
);
```

------------------------------------------------------------------------

# 15. ZF1 Configuration

A simple `application.ini` configuration could be:

``` ini
redis.host = "127.0.0.1"
redis.port = 6379
redis.timeout = 1
```

If authentication is configured:

``` ini
redis.password = "..."
```

Production secrets should not be hard-coded throughout PHP source files.

------------------------------------------------------------------------

# 16. Bootstrap Integration

A simple legacy ZF1 approach is to create the Redis client during
Bootstrap initialization.

``` php
protected function _initRedis()
{
    $options = $this->getOptions();

    $redis = new Redis();

    $redis->connect(
        $options['redis']['host'],
        (int) $options['redis']['port'],
        1.0
    );

    Zend_Registry::set(
        'redis',
        $redis
    );

    return $redis;
}
```

Application code can retrieve it with:

``` php
$redis = Zend_Registry::get(
    'redis'
);
```

This is simple for an existing ZF1 project.

As the application grows, a dedicated Redis service is cleaner.

------------------------------------------------------------------------

# 17. Central Redis Service

Suggested structure:

``` text
library/
└── S3/
    └── Redis/
        └── Service.php
```

Example:

``` php
<?php

class S3_Redis_Service
{
    /**
     * @var Redis
     */
    protected $redis;

    public function __construct(
        $host,
        $port,
        $timeout = 1.0
    ) {
        $this->redis = new Redis();

        $this->redis->connect(
            $host,
            $port,
            $timeout
        );
    }

    public function getClient()
    {
        return $this->redis;
    }

    public function get($key)
    {
        return $this->redis->get(
            $key
        );
    }

    public function set(
        $key,
        $value
    ) {
        return $this->redis->set(
            $key,
            $value
        );
    }

    public function setWithTtl(
        $key,
        $value,
        $seconds
    ) {
        return $this->redis->setex(
            $key,
            $seconds,
            $value
        );
    }

    public function delete($key)
    {
        return $this->redis->del(
            $key
        );
    }

    public function exists($key)
    {
        return $this->redis->exists(
            $key
        );
    }

    public function ttl($key)
    {
        return $this->redis->ttl(
            $key
        );
    }
}
```

Then business modules do not need to know Redis connection details.

------------------------------------------------------------------------

# 18. First Recommended Use: Cache

Caching is the easiest Redis feature to introduce into an existing ZF1
application.

Suppose Product 500 normally requires:

``` sql
SELECT *
FROM products
WHERE id = 500;
```

Without Redis:

``` text
Request
   |
   v
ZF1
   |
   v
MariaDB
   |
   v
Product
```

With Redis:

``` text
Request
   |
   v
ZF1
   |
   v
Redis
   |
   +-- HIT  -> return cached product
   |
   +-- MISS -> MariaDB
                  |
                  v
             load product
                  |
                  v
              Redis SET
                  |
                  v
               return
```

This is the **Cache-Aside Pattern**.

------------------------------------------------------------------------

# 19. Product Cache Example

``` php
public function find($productId)
{
    $productId = (int) $productId;

    $redis = Zend_Registry::get(
        'redis'
    );

    $key =
        'product:' . $productId;

    /*
     * 1. Try Redis.
     */
    $json = $redis->get($key);

    if ($json !== false) {
        return json_decode(
            $json,
            true
        );
    }

    /*
     * 2. Cache miss: query MariaDB.
     */
    $select = $this->db->select()
        ->from('products')
        ->where(
            'id = ?',
            $productId
        );

    $product =
        $this->db->fetchRow(
            $select
        );

    if (!$product) {
        return false;
    }

    /*
     * 3. Cache for five minutes.
     */
    $redis->setex(
        $key,
        300,
        json_encode($product)
    );

    return $product;
}
```

First request:

``` text
Redis MISS
    |
    v
MariaDB
    |
    v
Redis SET
```

Later requests:

``` text
Redis HIT
    |
    v
Return immediately
```

------------------------------------------------------------------------

# 20. Cache Invalidation

Caching introduces an important problem: stale data.

Suppose Redis contains:

``` text
product:500
price = 100
```

Then MariaDB changes to:

``` text
product:500
price = 120
```

Redis may still return 100.

Therefore the application needs an invalidation strategy.

A simple pattern is:

``` php
$this->db->update(
    'products',
    $data,
    array(
        'id = ?' => $productId
    )
);

$redis->del(
    'product:' . $productId
);
```

Flow:

``` text
Update MariaDB
      |
      v
Delete Redis cache
      |
      v
Next read
      |
      v
Redis MISS
      |
      v
MariaDB
      |
      v
Fresh Redis cache
```

A useful rule is:

> When the source-of-truth record changes, invalidate the related cache.

TTL is a safety net, but it should not be the only consistency strategy
when fresh data matters.

------------------------------------------------------------------------

# 21. Redis for the Authorization System

Redis fits naturally behind the RBAC authorization service.

Existing architecture:

``` text
EmailPolicy
ProductPolicy
CrmCustomerPolicy
       |
       v
S3_Authorization_Service
       |
       v
MariaDB
```

With Redis:

``` text
EmailPolicy
ProductPolicy
CrmCustomerPolicy
       |
       v
S3_Authorization_Service
       |
       v
Redis Permission Cache
       |
    cache miss
       |
       v
MariaDB
```

The Policy classes do not need to change.

They still call:

``` php
$this->authorization->isAllowed(
    $userId,
    'document.read'
);
```

Only the internal implementation of `S3_Authorization_Service` changes.

------------------------------------------------------------------------

# 22. Permission Cache Example

Possible Redis key:

``` text
auth:permissions:user:100
```

Value:

``` json
{
    "email.read": true,
    "email.send": true,
    "product.read": true,
    "crm.customer.read.team": true
}
```

Example:

``` php
public function getPermissions($userId)
{
    $userId = (int) $userId;

    $key =
        'auth:permissions:user:'
        . $userId;

    $json = $this->redis->get(
        $key
    );

    if ($json !== false) {
        return json_decode(
            $json,
            true
        );
    }

    $permissions =
        $this->loadPermissionsFromDatabase(
            $userId
        );

    $this->redis->setex(
        $key,
        300,
        json_encode($permissions)
    );

    return $permissions;
}
```

This is again Cache-Aside.

------------------------------------------------------------------------

# 23. Permission Cache Invalidation

Suppose an administrator changes User 100's roles.

The old Redis permission data must not continue to authorize the user.

After changing role assignment:

``` php
$redis->del(
    'auth:permissions:user:'
    . $userId
);
```

The next request:

``` text
isAllowed()
    |
    v
Redis MISS
    |
    v
MariaDB
    |
    v
Load new permissions
    |
    v
Redis SET
```

Similarly, when a role's permissions change, all users affected by that
role need an invalidation/versioning strategy.

For the first implementation, explicit invalidation plus a short TTL can
be sufficient. Larger deployments can later introduce permission
versioning or broader invalidation mechanisms.

------------------------------------------------------------------------

# 24. Redis and Resource Policy/Scope

Redis does not replace the Policy and Scope architecture.

Keep:

``` text
RBAC Permission
      |
      v
Resource Policy
      |
      v
Resource Scope
```

Redis only accelerates repeated permission retrieval.

For example:

``` text
CrmCustomerPolicy
       |
       | isAllowed(
       |   user,
       |   crm.customer.read.team
       | )
       v
Authorization Service
       |
       v
Redis
```

The CRM Scope still adds:

``` sql
WHERE c.sales_team_id = ?
```

to MariaDB.

Redis should not become a substitute for row-level SQL authorization.

------------------------------------------------------------------------

# 25. Redis for Online Presence

Redis is also well suited to online presence.

Previously, presence was represented by heartbeats such as:

``` text
Browser
    |
    | heartbeat every 60 seconds
    v
Server
```

Redis can store:

``` text
presence:user:100
```

with a TTL.

Example:

``` php
$redis->setex(
    'presence:user:' . $userId,
    120,
    (string) time()
);
```

If the browser sends a heartbeat every 60 seconds:

``` text
60 sec heartbeat
120 sec TTL
```

then the key remains alive while the browser continues communicating.

If the browser closes, crashes, loses the network, or otherwise stops
heartbeats:

``` text
presence:user:100
       |
       | no refresh
       v
expires automatically
```

This is simpler than manually deleting presence records.

------------------------------------------------------------------------

# 26. Presence Is Not Session Inactivity

Keep these concepts separate:

``` text
Online Presence
    =
Is the browser probably still connected/active?

Session Inactivity
    =
Is this authenticated session still allowed?
```

For the two-hour inactivity requirement:

``` text
Meaningful user activity
    -> updates last_activity

Automatic heartbeat
    -> does NOT reset meaningful activity
```

Never allow:

``` text
heartbeat
    |
    v
reset two-hour security inactivity timer
```

Otherwise an abandoned browser tab could remain authenticated
indefinitely.

Redis presence can complement the existing session timeout design, but
it should not weaken it.

------------------------------------------------------------------------

# 27. Redis for Notification Counters

Suppose a user has:

``` text
12 unread notifications
```

Redis key:

``` text
notification:unread:100
```

Set:

``` php
$redis->set(
    'notification:unread:100',
    12
);
```

Increment:

``` php
$redis->incr(
    'notification:unread:100'
);
```

Decrement:

``` php
$redis->decr(
    'notification:unread:100'
);
```

This can be useful for fast UI counters.

However, decide carefully whether the counter is merely a cache or the
authoritative value. For a business application, keeping authoritative
notification records in MariaDB and treating Redis counters as
derived/supporting state is often safer.

------------------------------------------------------------------------

# 28. Redis for Rate Limiting

Redis is useful for limiting repeated requests.

For example:

``` text
rate-limit:login:192.0.2.10
```

Increment the counter:

``` php
$count = $redis->incr(
    $key
);
```

When first created, assign expiration:

``` php
if ($count === 1) {
    $redis->expire(
        $key,
        60
    );
}
```

Conceptually:

``` text
First request
    counter = 1
    TTL = 60 sec

Second request
    counter = 2

...

Too many requests
    -> temporarily reject
```

Production rate limiting needs careful atomic design and correct
handling of proxies/client identity, but Redis is a natural platform for
it.

------------------------------------------------------------------------

# 29. Redis Pub/Sub

Redis supports Publish/Subscribe.

Conceptually:

``` text
ZF1
 |
 | PUBLISH
 v
Redis
 |
 +-----------+-----------+
 |                       |
 v                       v
Workerman               Node.js
Subscriber              Subscriber
```

A publisher sends to a channel:

``` text
PUBLISH notifications "{...}"
```

Subscribers listen to:

``` text
notifications
```

This can be useful for realtime signaling.

------------------------------------------------------------------------

# 30. Pub/Sub Is Not a Durable Business Queue

This distinction is critical.

Redis Pub/Sub is designed for live message distribution. If a subscriber
is unavailable, a published message is not retained for that offline
subscriber like a durable business event.

Therefore do not replace a reliable transactional business-event design
with simple Redis Pub/Sub.

Keep the existing transactional outbox concept:

``` text
Business transaction
       |
       v
MariaDB
       |
       +-- business data
       |
       +-- event_outbox
              |
              v
          CLI Worker
              |
              v
      downstream service
```

Redis can later complement this architecture:

``` text
MariaDB Outbox
      |
      v
Worker
      |
      +-- downstream API
      |
      +-- Redis Pub/Sub for live signal where appropriate
```

But Pub/Sub alone should not be treated as durable event storage.

------------------------------------------------------------------------

# 31. Redis Streams

Redis Streams are different from Pub/Sub.

Simplified:

``` text
Redis Stream
    |
    +-- Event 1
    +-- Event 2
    +-- Event 3
    +-- Event 4
          |
          +-- Consumer Group A
          +-- Consumer Group B
```

Streams provide event/message-processing capabilities such as consumer
groups and pending entries.

They are more suitable than Pub/Sub for some queue/event-processing
scenarios.

However, Streams are a more advanced Redis topic.

For the current ZF1 project, learn Redis in this order:

``` text
GET / SET
DEL
SETEX / EXPIRE / TTL
INCR / DECR
Hashes / Sets
Locks
Pub/Sub
Streams
```

Do not introduce Streams merely because they exist.

------------------------------------------------------------------------

# 32. Redis and the Transactional Outbox

The current reliable business-event architecture can remain:

``` text
ZF1 Business Module
        |
        | same MariaDB transaction
        v
Business Tables + event_outbox
        |
        v
PHP CLI Worker
        |
        v
Realtime / downstream service
```

Redis does not remove the dual-write problem between business data and
event publication.

Therefore:

``` text
MariaDB transactional outbox
    =
reliability boundary

Redis
    =
optional supporting technology
```

If a broker such as RabbitMQ is added later, the outbox can still
remain:

``` text
ZF1
 |
 v
MariaDB Outbox
 |
 v
Outbox Publisher
 |
 v
RabbitMQ
```

Redis can still independently serve caching, presence, counters, rate
limiting, and other fast-state requirements.

------------------------------------------------------------------------

# 33. Redis Failure Handling

Redis can fail.

Examples:

``` text
Redis service stopped
Network problem
Timeout
Memory pressure
Configuration problem
Server restart
```

If Redis is only a cache, Redis failure should normally not make the
main business application unavailable.

Preferred behavior:

``` text
             Redis
               |
       +-------+-------+
       |               |
      HIT            failure/miss
       |               |
       v               v
   use cache         MariaDB
```

Example:

``` php
try {
    $cached = $redis->get(
        $key
    );
} catch (Exception $e) {
    $cached = false;
}

if ($cached !== false) {
    return json_decode(
        $cached,
        true
    );
}

/*
 * Fall back to MariaDB.
 */
```

This leads to an important rule:

> If Redis is an optimization, Redis failure should not normally stop
> core business functions.

------------------------------------------------------------------------

# 34. Use Short Redis Timeouts

A cache should not make a PHP request wait for a long time when Redis is
unavailable.

For example:

``` php
$redis->connect(
    '127.0.0.1',
    6379,
    1.0
);
```

The exact production timeout should be tested for the deployment
environment, but the general idea is:

``` text
Fast cache
    should fail fast
```

rather than:

``` text
Redis unavailable
    |
    v
PHP waits a long time
    |
    v
User request becomes slow
```

------------------------------------------------------------------------

# 35. Do Not Store the Only Copy of Critical Business Data in Redis

Do not make Redis the only storage location for:

``` text
Customers
Invoices
Orders
Contracts
Emails
Products
Critical audit records
```

For the current architecture:

``` text
MariaDB
    =
Source of truth

Redis
    =
Fast supporting system
```

Redis does offer persistence mechanisms, but introducing Redis does not
mean critical relational business records should automatically move away
from MariaDB.

------------------------------------------------------------------------

# 36. Redis Persistence

Although Redis is primarily memory-oriented, it can be configured to
persist data to disk.

Common Redis persistence concepts include:

``` text
RDB
    -> periodic snapshots

AOF
    -> append operations to a log
```

Persistence configuration depends on what Redis is being used for.

For example:

``` text
Disposable cache
    -> losing cache may be acceptable

Online presence
    -> rebuilding presence may be acceptable

Critical queue-like data
    -> persistence/recovery requirements become important
```

Therefore persistence should be designed according to the Redis use case
rather than enabled blindly.

------------------------------------------------------------------------

# 37. Memory Management

Because Redis primarily uses RAM, memory planning matters.

Do not assume:

``` text
Redis RAM
    =
unlimited
```

Consider:

``` text
How many keys?
How large are values?
What TTLs are used?
What happens when memory is full?
Which eviction policy is configured?
```

For caches, an eviction policy may be appropriate.

For important state, automatic eviction may be unacceptable.

The Redis role must therefore be defined clearly before production
deployment.

------------------------------------------------------------------------

# 38. Security

Redis should be treated as an internal infrastructure service.

Recommended principles:

``` text
Do not expose Redis publicly.
Bind to appropriate interfaces.
Use firewall restrictions.
Use authentication/ACLs where appropriate.
Protect credentials.
Use least privilege.
Monitor access and failures.
Keep Redis patched.
```

For a single ZF1 server with local Redis:

``` text
ZF1/PHP
    |
    v
127.0.0.1:6379
```

is much safer than unnecessarily listening on every interface.

If multiple application servers need Redis:

``` text
App Server A ----+
                 |
App Server B ----+---- Private Redis Network
                 |
Worker ----------+
```

restrict Redis to trusted private hosts/networks.

------------------------------------------------------------------------

# 39. Redis Does Not Replace Authorization

Never treat the presence of a Redis key as sufficient authorization
unless that key is part of a properly designed authorization cache.

For example, do not do:

``` text
user:100 exists in Redis
    therefore user is authorized
```

Authorization remains:

``` text
Authentication
      |
      v
Session validation
      |
      v
RBAC Permission
      |
      v
Resource Policy
      |
      v
Resource Scope
```

Redis only makes some supporting operations faster.

------------------------------------------------------------------------

# 40. Redis Does Not Replace MariaDB Indexing

Redis should not be used as a workaround for poorly indexed SQL.

Before caching an expensive MariaDB query, also ask:

``` text
Is the SQL correct?
Does it have the right composite index?
What does EXPLAIN show?
Is the query returning too much data?
Can pagination improve it?
```

Correct database design comes first.

Then Redis can reduce repeated work.

------------------------------------------------------------------------

# 41. When Redis Is a Good Fit

Good Redis candidates include:

``` text
Repeatedly read cache data
Permission cache
Online presence
Temporary state
Rate limiting
Counters
Short-lived tokens
Shared state between PHP processes
Realtime Pub/Sub signals
Distributed coordination/locks
```

------------------------------------------------------------------------

# 42. When Redis May Not Be Necessary

Do not add Redis automatically for:

``` text
Small rarely-used tables
Simple CRUD that MariaDB handles easily
Data that must always be strongly current
One-time queries
Low-traffic features
Business data that naturally belongs in relational tables
```

Every additional infrastructure component adds:

``` text
Installation
Configuration
Monitoring
Security
Backup/recovery decisions
Failure handling
Developer knowledge
Operational maintenance
```

Use Redis where the benefit justifies that complexity.

------------------------------------------------------------------------

# 43. Recommended Architecture for the Current ZF1 Project

A practical architecture is:

``` text
                            ZF1
                             |
             +---------------+---------------+
             |                               |
             v                               v
          MariaDB                          Redis
             |                               |
             +-- Users                       +-- Permission cache
             +-- Roles                       +-- Online presence
             +-- Permissions                 +-- Product/cache data
             +-- Email                       +-- Temporary data
             +-- Product                     +-- Counters
             +-- CRM                         +-- Rate limiting
             +-- Notifications
             +-- event_outbox
```

The business database remains authoritative.

------------------------------------------------------------------------

# 44. Recommended Redis Service Layers

As the project grows:

``` text
library/
└── S3/
    ├── Redis/
    │   └── Service.php
    │
    ├── Cache/
    │   └── Service.php
    │
    ├── Authorization/
    │   └── Service.php
    │
    └── Presence/
        └── Service.php
```

Responsibilities:

``` text
S3_Redis_Service
    -> low-level Redis communication

S3_Cache_Service
    -> generic cache behavior

S3_Authorization_Service
    -> RBAC + permission caching

S3_Presence_Service
    -> online-user TTL keys
```

Business modules should depend on meaningful services rather than
scattering Redis commands throughout controllers.

------------------------------------------------------------------------

# 45. Example Cache Service

``` php
<?php

class S3_Cache_Service
{
    protected $redis;

    public function __construct(
        S3_Redis_Service $redis
    ) {
        $this->redis = $redis;
    }

    public function getJson($key)
    {
        try {
            $json = $this->redis->get(
                $key
            );
        } catch (Exception $e) {
            return false;
        }

        if ($json === false) {
            return false;
        }

        $value = json_decode(
            $json,
            true
        );

        if (json_last_error()
            !== JSON_ERROR_NONE) {
            return false;
        }

        return $value;
    }

    public function setJson(
        $key,
        array $value,
        $ttl
    ) {
        try {
            return $this->redis->setWithTtl(
                $key,
                json_encode($value),
                $ttl
            );
        } catch (Exception $e) {
            return false;
        }
    }

    public function delete($key)
    {
        try {
            return $this->redis->delete(
                $key
            );
        } catch (Exception $e) {
            return false;
        }
    }
}
```

Then a Product model/service does not need to manipulate JSON and Redis
connection details directly.

------------------------------------------------------------------------

# 46. Cache-Aside Pattern Summary

The normal read flow is:

``` text
READ REQUEST
     |
     v
Check Redis
     |
 +---+---+
 |       |
HIT     MISS
 |       |
 v       v
Return  MariaDB
          |
          v
       Cache result
          |
          v
        Return
```

The write flow is:

``` text
WRITE REQUEST
      |
      v
Update MariaDB
      |
      v
Invalidate Redis
      |
      v
Return success
```

This is one of the most useful Redis patterns for an existing
ZF1/MariaDB system.

------------------------------------------------------------------------

# 47. Suggested Learning Sequence

For this project, learn Redis incrementally.

## Stage 1 --- Basic Redis

Learn:

``` text
SET
GET
DEL
SETEX
EXPIRE
TTL
INCR
DECR
```

## Stage 2 --- PHP/ZF1 Integration

Implement:

``` text
S3_Redis_Service
application.ini settings
Bootstrap initialization
failure handling
```

## Stage 3 --- Product Cache

Cache:

``` text
product:<id>
```

Learn:

``` text
cache hit
cache miss
TTL
invalidation
fallback to MariaDB
```

## Stage 4 --- Authorization Cache

Add:

``` text
auth:permissions:user:<id>
```

behind:

``` text
S3_Authorization_Service
```

No Email/Product/CRM Policy changes should be required.

## Stage 5 --- Presence

Use:

``` text
presence:user:<id>
```

with heartbeat + TTL.

Keep presence separate from the two-hour inactivity security timeout.

## Stage 6 --- Counters and Rate Limiting

Experiment with:

``` text
INCR
DECR
EXPIRE
```

## Stage 7 --- Advanced Redis

Only when needed, study:

``` text
Hashes
Sets
Sorted Sets
Distributed locks
Pub/Sub
Streams
```

------------------------------------------------------------------------

# 48. Redis vs. MariaDB --- Quick Comparison

  -----------------------------------------------------------------------
  Feature                 MariaDB                 Redis
  ----------------------- ----------------------- -----------------------
  Main storage            Disk + memory/cache     Primarily memory

  Relational tables       Excellent               No

  SQL                     Yes                     No

  JOIN                    Yes                     No traditional SQL JOIN

  Transactions            Strong relational       Different transaction
                          support                 model

  Very fast key lookup    Good                    Excellent

  TTL per key             Not natural             Excellent

  Counters                Possible                Excellent

  Online presence         Possible                Excellent

  Cache                   Possible but not ideal  Excellent

  Complex reporting       Excellent               Not primary purpose

  Business source of      Excellent               Usually not the default
  truth                                           choice

  Pub/Sub                 Not primary role        Built in

  Streams                 Not primary role        Built in
  -----------------------------------------------------------------------

The two systems complement each other.

------------------------------------------------------------------------

# 49. Example Combined Request

Suppose a user opens a CRM page.

``` text
Browser
   |
   | GET /api/crm/customer/getlist
   v
ZF1
   |
   +-- Validate session
   |
   +-- Check two-hour inactivity
   |
   +-- Authorization Service
   |       |
   |       +-- Redis permission cache
   |       |
   |       +-- MariaDB on cache miss
   |
   +-- CrmCustomerPolicy
   |
   +-- applyReadScope()
   |
   v
MariaDB
   |
   | WHERE sales_team_id = 5
   v
Authorized CRM rows
   |
   v
DHTMLX Grid
```

Redis speeds up permission lookup.

MariaDB still enforces the resource Scope.

This preserves the clean authorization architecture:

``` text
Redis
    -> fast permission cache

Policy
    -> business authorization rule

Scope
    -> MariaDB row restriction
```

------------------------------------------------------------------------

# 50. Example Online Presence Request

``` text
Browser
   |
   | heartbeat every 60 seconds
   v
ZF1
   |
   | authenticate session
   v
Redis
   |
   | SETEX presence:user:100 120 <timestamp>
   v
Presence refreshed
```

If heartbeats stop:

``` text
120 seconds
    |
    v
Redis expires key
    |
    v
User considered offline
```

But:

``` text
Heartbeat
    X
must not refresh
    |
    v
two-hour meaningful-activity timer
```

------------------------------------------------------------------------

# 51. Example Business Event Flow

Keep reliable business events separate:

``` text
User sends email
      |
      v
ZF1 Email Service
      |
      v
MariaDB Transaction
      |
      +-- INSERT email
      |
      +-- INSERT event_outbox
      |
      v
COMMIT
      |
      v
PHP CLI Outbox Worker
      |
      v
Realtime Notification Service
```

Redis can be added for supporting functions:

``` text
                     Redis
                       |
             +---------+---------+
             |                   |
        Presence             Counters/cache
```

Do not change the reliable outbox merely because Redis is available.

------------------------------------------------------------------------

# 52. Operational Questions to Answer Before Production

Before using Redis in production, define:

``` text
What data will Redis contain?

Is that data disposable or critical?

What happens if Redis restarts?

Should Redis data persist?

What is the maximum acceptable Redis outage?

Can the application fall back to MariaDB?

What TTL should each key type use?

How will cache invalidation work?

How much RAM is required?

What happens when Redis reaches its memory limit?

Which servers are allowed to connect?

How will Redis be monitored?

How will failures be logged?
```

These questions prevent Redis from becoming an undefined dependency.

------------------------------------------------------------------------

# 53. Recommended First Production Use

For this ZF1 project, a conservative first Redis use is:

``` text
Permission Cache
```

Why?

The existing Policy classes already call:

``` php
$authorization->isAllowed(
    $userId,
    $permission
);
```

Therefore Redis can be introduced internally:

``` text
Policy
   |
   v
S3_Authorization_Service
   |
   v
Redis
   |
 cache miss
   |
   v
MariaDB
```

The business modules and Policies do not need to know Redis exists.

This gives a clean separation:

``` text
Email / Product / CRM
        |
        v
Policy
        |
        v
Authorization Service
        |
   +----+----+
   |         |
 Redis    MariaDB
```

After this is stable, online presence is another natural Redis use case.

------------------------------------------------------------------------

# 54. Final Recommendations

For the current ZF1/PHP 7.4/MariaDB application:

1.  Keep MariaDB as the source of truth for business records.
2.  Introduce Redis as a supporting infrastructure component.
3.  Start with simple cache operations.
4.  Use a central `S3_Redis_Service`.
5.  Prefer Cache-Aside for normal business caching.
6.  Always design cache invalidation.
7.  Use short/fail-fast Redis connection timeouts.
8.  If Redis is only a cache, gracefully fall back to MariaDB.
9.  Use Redis permission caching behind `S3_Authorization_Service`.
10. Keep Resource Policy and Resource Scope unchanged.
11. Consider Redis TTL keys for online presence.
12. Never let presence heartbeats reset the two-hour security inactivity
    timer.
13. Use Redis counters where fast atomic counters are useful.
14. Use Pub/Sub only for live signaling, not as a durable replacement
    for the transactional outbox.
15. Study Redis Streams later if a real queue/event-stream requirement
    appears.
16. Do not expose Redis publicly.
17. Plan memory, persistence, monitoring, and failure behavior before
    production use.
18. Do not introduce Redis simply to avoid fixing SQL/indexing problems.

The simplest mental model to remember is:

``` text
                    ZF1
                     |
          +----------+----------+
          |                     |
          v                     v
       MariaDB                Redis
          |                     |
          |                     +-- FAST
          |                     +-- TEMPORARY
          |                     +-- SHARED
          |                     +-- CACHE
          |                     +-- TTL
          |
          +-- PERMANENT
          +-- RELATIONAL
          +-- BUSINESS DATA
          +-- SOURCE OF TRUTH
```

Redis is therefore not a replacement for the existing database
architecture. It is a fast supporting layer that can improve
performance, shared state, online presence, counters, rate limiting, and
selected realtime functions while allowing the ZF1 application to keep
MariaDB as its reliable business-data foundation.
