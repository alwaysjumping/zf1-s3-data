# MySQL/MariaDB Transaction Guide

**Audience:** PHP / Zend Framework 1 developers\
**Database:** MySQL / MariaDB\
**Storage engine:** InnoDB

## 1. What Is a Transaction?

A database **transaction** groups one or more SQL operations into one
logical unit of work.

> Either all related operations are successfully saved, or none of them
> are saved.

Example:

``` sql
START TRANSACTION;

UPDATE accounts
SET balance = balance - 100
WHERE id = 1;

UPDATE accounts
SET balance = balance + 100
WHERE id = 2;

COMMIT;
```

Both updates belong to one business operation:

``` text
Account A: -$100
Account B: +$100
```

They should not be permanently saved separately.

## 2. Why Do We Need Transactions?

Without a transaction:

``` text
1. Subtract $100 from A -> SUCCESS
2. Add $100 to B        -> ERROR
```

The database could become inconsistent:

``` text
Before:
A = $1,000
B = $500

After error:
A = $900
B = $500
```

With a transaction:

``` text
             START TRANSACTION
                    |
              SQL operation 1
                    |
              SQL operation 2
                    |
             +------+------+
             |             |
          SUCCESS         ERROR
             |             |
          COMMIT        ROLLBACK
             |             |
          Save all      Cancel all
```

Transactions protect **data consistency**.

## 3. Main Transaction Commands

### `START TRANSACTION`

Starts the transaction:

``` sql
START TRANSACTION;
```

or:

``` sql
BEGIN;
```

PHP/ZF1:

``` php
$db->beginTransaction();
```

### SQL statements

Statements such as `INSERT`, `UPDATE`, and `DELETE` execute inside the
transaction. Their changes exist as **uncommitted changes** until the
transaction is finished.

### `COMMIT`

Makes the transaction changes permanent:

``` sql
COMMIT;
```

PHP:

``` php
$db->commit();
```

Think:

``` text
COMMIT = SAVE
```

### `ROLLBACK`

Discards the transaction changes:

``` sql
ROLLBACK;
```

PHP:

``` php
$db->rollBack();
```

Think:

``` text
ROLLBACK = CANCEL
```

## 4. Why Is ROLLBACK Needed Before COMMIT?

The SQL statements have already executed inside the transaction even
though they are not permanent yet.

Example:

``` sql
START TRANSACTION;

UPDATE accounts
SET balance = balance - 100
WHERE id = 1;
```

The current transaction now contains the change. If the next operation
fails:

``` sql
UPDATE accounts
SET balance = balance + 100
WHERE id = 2;
```

the application can execute:

``` sql
ROLLBACK;
```

Conceptually:

``` text
Before:
A = $1,000
B = $500

BEGIN

A = $900       <- uncommitted

Second UPDATE fails

ROLLBACK

A = $1,000
B = $500
```

`ROLLBACK` explicitly tells the database to discard the transaction's
work.

## 5. COMMIT and ROLLBACK Are Alternatives

They are two alternative endings:

``` text
                beginTransaction()
                       |
                 SQL operations
                       |
                 Did all succeed?
                   /        \
                 YES        NO
                  |          |
              commit()   rollBack()
                  |          |
              SAVE ALL   CANCEL ALL
```

Normally, you do not execute both for the same transaction path.

## 6. Standard PHP Pattern

``` php
$db->beginTransaction();

try {

    $db->insert('table_a', $dataA);

    $db->insert('table_b', $dataB);

    $db->commit();

} catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

Flow:

``` text
beginTransaction()
       |
INSERT table_a
       |
INSERT table_b
       |
   +---+---+
   |       |
 success  exception
   |       |
 commit  rollBack
```

## 7. Money Transfer Example

``` php
$db->beginTransaction();

try {

    $db->query(
        'UPDATE accounts
         SET balance = balance - 100
         WHERE id = 1'
    );

    $db->query(
        'UPDATE accounts
         SET balance = balance + 100
         WHERE id = 2'
    );

    $db->commit();

} catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

Successful result:

``` text
Before:
A = $1,000
B = $500

After COMMIT:
A = $900
B = $600
```

If the second update fails, `ROLLBACK` cancels the first update too.

## 8. Example for the ZF1 Notification System

Creating a notification and its realtime outbox event should be one
logical database operation:

``` text
Create notification
        +
Create realtime outbox event
```

Without a transaction:

``` text
INSERT notification  -> SUCCESS
INSERT outbox         -> ERROR
```

You could get:

``` text
notifications:
5001 exists

realtime_outbox:
event missing
```

With a transaction:

``` php
$db->beginTransaction();

try {

    $db->insert(
        'notifications',
        array(
            'user_id' => $userId,
            'message' => 'New report'
        )
    );

    $notificationId = $db->lastInsertId();

    $db->insert(
        'realtime_outbox',
        array(
            'event_name' => 'notification.created',
            'user_id'    => $userId,
            'payload'    => json_encode(array(
                'notification_id' => $notificationId
            ))
        )
    );

    $db->commit();

} catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

Now:

``` text
notification  YES
outbox event  YES

      OR

notification  NO
outbox event  NO
```

This is a core part of the **transactional outbox pattern**.

## 9. Transactions Can Mix SQL Operations

For example:

``` sql
START TRANSACTION;

INSERT INTO orders (...);

INSERT INTO order_items (...);

UPDATE products
SET stock = stock - 1
WHERE id = 100;

INSERT INTO audit_log (...);

COMMIT;
```

A transaction can contain related `INSERT`, `UPDATE`, `DELETE`, and
appropriate locking reads.

## 10. When Should You Use a Transaction?

Use a transaction when several related database changes must succeed or
fail together.

Examples:

``` text
INSERT + INSERT
UPDATE + UPDATE
INSERT + UPDATE
DELETE + INSERT
Multiple related tables
Order + order items + inventory
Notification + realtime outbox
```

A useful question is:

> If query #2 fails, would keeping query #1 leave the database in an
> incorrect or incomplete business state?

If yes, a transaction is often appropriate.

A single independent update normally does not need an explicitly managed
transaction:

``` sql
UPDATE users
SET last_login = NOW()
WHERE id = 100;
```

## 11. InnoDB Is Important

Use a transactional storage engine such as **InnoDB**:

``` sql
CREATE TABLE accounts (
    id BIGINT UNSIGNED NOT NULL,
    balance DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;
```

Check:

``` sql
SHOW CREATE TABLE accounts;
```

Look for:

``` text
ENGINE=InnoDB
```

Non-transactional tables do not provide the rollback protection you
expect.

## 12. Autocommit

MySQL/MariaDB normally uses autocommit for ordinary standalone
statements.

Conceptually:

``` text
UPDATE
  |
automatically committed
```

An explicit transaction changes this:

``` text
BEGIN
  |
  +-- UPDATE
  +-- INSERT
  +-- DELETE
  |
  +-- COMMIT
       or
  +-- ROLLBACK
```

This lets several statements behave as one unit.

## 13. What If You Do Neither COMMIT Nor ROLLBACK?

Example:

``` php
$db->beginTransaction();

$db->update(...);

// error occurs
// no commit
// no rollback
```

The transaction can remain open while the connection remains active.
Open transactions can retain locks and database resources.

Therefore:

``` text
SUCCESS -> COMMIT
FAILURE -> ROLLBACK
```

Explicitly finish the transaction.

## 14. Transactions and Locks

Transactions can hold locks.

Example:

``` text
Transaction A
    |
    +-- UPDATE row 1
    |      |
    |      +-- lock
    |
    |              Transaction B
    |                   |
    |                   +-- UPDATE row 1
    |                          |
    |                          +-- waits
    |
    +-- COMMIT
           |
       lock released
```

Keep transactions reasonably short. Avoid unnecessary slow HTTP/API
calls or user interaction while a database transaction is open.

## 15. ACID Properties

Transactions are commonly described with **ACID**.

### Atomicity

All related operations are saved together or discarded together.

### Consistency

A successful transaction should leave the database in a valid state
according to database rules and application invariants.

### Isolation

Concurrent transactions are controlled so intermediate/uncommitted work
is not simply treated as committed data. Exact behavior depends on the
isolation level.

### Durability

After a successful commit, the database is designed to preserve the
committed result according to its durability guarantees.

``` text
A = Atomicity
C = Consistency
I = Isolation
D = Durability
```

## 16. Transaction Isolation

Common isolation levels include:

``` text
READ UNCOMMITTED
READ COMMITTED
REPEATABLE READ
SERIALIZABLE
```

Isolation controls how concurrent transactions interact.

Check the actual server setting rather than assuming it:

``` sql
SELECT @@transaction_isolation;
```

The exact variable name can differ on older server versions.

## 17. SELECT ... FOR UPDATE

Sometimes you need to read a row and then update it safely in the same
transaction:

``` sql
START TRANSACTION;

SELECT balance
FROM accounts
WHERE id = 1
FOR UPDATE;

UPDATE accounts
SET balance = balance - 100
WHERE id = 1;

COMMIT;
```

`FOR UPDATE` performs a locking read under InnoDB transaction semantics.
Use it carefully because locks affect concurrency.

## 18. Savepoints

A savepoint lets you roll back part of a transaction:

``` sql
START TRANSACTION;

INSERT INTO table_a (...);

SAVEPOINT step1;

INSERT INTO table_b (...);

ROLLBACK TO SAVEPOINT step1;

COMMIT;
```

Conceptually:

``` text
BEGIN
  |
Operation A
  |
SAVEPOINT S1
  |
Operation B
  |
Operation C fails
  |
ROLLBACK TO S1
  |
COMMIT Operation A
```

For ordinary business operations, a full rollback is often simpler.

## 19. Nested Transactions

Do not assume repeated calls to:

``` php
$db->beginTransaction();
```

automatically create independent nested transactions.

If partial rollback is required, use an intentionally designed savepoint
strategy supported by your database/application layer.

For most ZF1 services, keep one clear transaction boundary.

## 20. DDL Statements Need Special Care

Schema-changing statements such as:

``` sql
CREATE TABLE
ALTER TABLE
DROP TABLE
```

can have special implicit-commit/transaction behavior depending on
MySQL/MariaDB and the statement.

Do not assume schema changes roll back exactly like normal InnoDB row
changes.

## 21. Transaction Boundary

A **transaction boundary** defines what database work must succeed or
fail together.

For example:

``` text
Business operation:
"Create notification"

BEGIN
  |
INSERT notification
  |
INSERT realtime_outbox
  |
COMMIT
```

Ask:

> What database changes must remain consistent with each other?

Those changes usually belong inside the same transaction.

## 22. Recommended ZF1 Service Pattern

``` php
public function createSomething(array $data)
{
    $db = $this->getDb();

    $db->beginTransaction();

    try {

        // Database operation #1

        // Database operation #2

        // Database operation #3

        $db->commit();

    } catch (Exception $e) {

        $db->rollBack();

        throw $e;
    }
}
```

Keep the transaction around one logical business operation rather than
spreading it across unrelated controllers or long-running work.

## 23. Common Mistakes

### Forgetting COMMIT

``` php
$db->beginTransaction();
$db->insert(...);

// no commit
```

### Forgetting ROLLBACK

``` php
$db->beginTransaction();

try {
    // ...
} catch (Exception $e) {
    // transaction not explicitly ended
}
```

### Using non-transactional tables

Verify the storage engine when rollback matters.

### Keeping transactions open too long

Long transactions increase lock contention.

### Assuming transactions solve every concurrency problem

Some workflows also need appropriate isolation, constraints, locking,
atomic updates, or idempotency.

### Sending WebSocket messages before commit

Avoid:

``` text
BEGIN
  |
INSERT notification
  |
SEND WebSocket event
  |
COMMIT
```

A WebSocket message cannot be rolled back after the client receives it.

Prefer:

``` text
BEGIN
  |
INSERT notification
  |
INSERT outbox
  |
COMMIT
  |
Dispatcher
  |
Workerman
  |
Browser
```

## 24. Transaction vs Transactional Outbox

A database transaction can atomically protect database records:

``` text
BEGIN
  |
INSERT A
  |
INSERT B
  |
COMMIT
```

It cannot normally make an external WebSocket send part of the same
database commit.

The transactional outbox pattern solves this by storing the intent to
send:

``` text
BEGIN
  |
  +-- INSERT notification
  +-- INSERT outbox event
  |
COMMIT
  |
Dispatcher reads committed outbox
  |
Workerman sends signal
```

This is especially important for a reliable ZF1 + MariaDB + Workerman
notification system.

## 25. Quick Reference

SQL:

``` sql
START TRANSACTION;

-- related SQL operations

COMMIT;
```

On failure:

``` sql
ROLLBACK;
```

PHP/ZF1:

``` php
$db->beginTransaction();

try {

    // related database operations

    $db->commit();

} catch (Exception $e) {

    $db->rollBack();

    throw $e;
}
```

## 26. Easy Mental Model

``` text
beginTransaction()
       =
START EDITING

INSERT / UPDATE / DELETE
       =
MAKE CHANGES

commit()
       =
SAVE

rollBack()
       =
CANCEL
```

Full flow:

``` text
                BEGIN
                  |
             Make changes
                  |
           +------+------+
           |             |
        success        failure
           |             |
        COMMIT        ROLLBACK
           |             |
          SAVE          CANCEL
```

## 27. Final Rules

1.  Use a transaction when related database changes must succeed or fail
    together.
2.  SQL changes inside a transaction have executed even though they are
    not yet committed.
3.  `COMMIT` makes the transaction changes permanent.
4.  `ROLLBACK` discards the transaction changes.
5.  `COMMIT` and `ROLLBACK` are alternative transaction endings.
6.  Use a transactional engine such as InnoDB.
7.  Keep transactions reasonably short.
8.  Explicitly finish transactions on both success and failure paths.
9.  Be careful with DDL and nested-transaction assumptions.
10. For the realtime notification system, commit the notification and
    outbox record together, then let the dispatcher and Workerman send
    the realtime signal afterward.

> **Core principle:** A transaction protects data consistency by making
> related database operations behave as one logical unit of work.
