# Data Exchange Architecture

## Bidirectional Asynchronous Package Exchange Across Isolated Inner and Outer Sites

**Document status:** Architecture discussion baseline\
**Version:** 1.0\
**Date:** 2026-10-07

------------------------------------------------------------------------

## 1. Introduction

This document describes a data-exchange architecture for a company that
operates two completely network-isolated business environments:

-   **Inner Site** --- used by internal users working inside the
    company's internal environment.
-   **Outer Site** --- used by external users who access company
    business services through a private VPN.

The Inner Site and Outer Site cannot communicate directly through
TCP/IP, HTTP, REST APIs, WebSockets, database connections, or any other
network protocol.

The only approved mechanism for transferring data between the
environments is an **authorized dongle device**.

The architecture therefore does not use traditional online
synchronization. Instead, it uses a **bidirectional, asynchronous,
store-and-forward, package-based data exchange mechanism**.

------------------------------------------------------------------------

## 2. User Groups

### 2.1 Group A --- Internal Users

Group A users work inside the company's internal environment and use the
Inner Site.

``` text
Group A
   |
   v
Inner Site
```

### 2.2 Group B --- External Users

Group B users work outside the internal environment.

They connect through a private VPN and use the Outer Site.

``` text
Group B
   |
Private VPN
   |
   v
Outer Site
```

The VPN gives Group B access to the Outer Site. It does **not** create
connectivity between the Outer Site and Inner Site.

------------------------------------------------------------------------

## 3. Network Isolation

The architecture assumes a strict network boundary:

``` text
+-------------------+             +-------------------+
|    INNER SITE     |             |    OUTER SITE     |
|                   |             |                   |
| Internal users    |             | External users    |
| Applications      |             | Applications      |
| Database          |             | Database          |
| File storage      |             | File storage      |
+-------------------+             +-------------------+

          NO DIRECT NETWORK CONNECTION
```

The following direct communications are prohibited between the sites:

-   TCP/IP
-   HTTP/HTTPS
-   REST APIs
-   WebSockets
-   direct database connections
-   shared network folders
-   message brokers over a network
-   any other direct network communication

Data crosses the boundary only through the authorized dongle transfer
mechanism.

------------------------------------------------------------------------

## 4. Basic Exchange Model

The basic Inner-to-Outer flow is:

``` text
INNER SITE

Application
    |
    v
Data Exchange Service
    |
    v
Exchange Outbox
    |
    v
Package Generator
    |
    v
Send Directory
    |
    v
========================
   Authorized Dongle
========================
    |
    v
Receive Directory
    |
    v
Package Validator
    |
    v
Package Processor
    |
    v
Database / File System

OUTER SITE
```

The reverse direction uses the same concept:

``` text
Outer Site -> Dongle -> Inner Site
```

Therefore, both sites should run compatible Data Exchange subsystems.

------------------------------------------------------------------------

## 5. Example: Email from Inner Site to Outer Site

Suppose an internal user sends an email to an external user.

The flow is:

1.  The Inner Site processes and stores the email according to normal
    business rules.
2.  The Email module publishes an exchange event such as
    `email.created`.
3.  The Data Exchange Service stores the event in an exchange outbox.
4.  A package worker generates an exchange package.
5.  The completed package is placed in the Inner Site's send directory.
6.  The dongle software retrieves the package.
7.  The dongle transports the package across the isolated boundary.
8.  The package is placed in the Outer Site's receive directory.
9.  The Outer Site validates the package.
10. The event dispatcher identifies `email.created`.
11. The appropriate Email handler processes the event.
12. The Outer Site updates its database and file storage.
13. The Outer Site records that the package was processed.
14. An acknowledgement may be generated for transfer back to the Inner
    Site.

------------------------------------------------------------------------

## 6. Architectural Layers

The architecture should be divided into three major layers.

### 6.1 Business Layer

Examples:

-   Email
-   Notification
-   Blog
-   Product
-   CRM
-   future business modules

Business modules should not directly control the dongle or manually
create arbitrary transfer files.

### 6.2 Data Exchange Layer

The Data Exchange Layer provides reusable infrastructure:

-   event publication
-   outbox
-   package generation
-   package validation
-   event dispatch
-   event handlers
-   duplicate protection
-   sequence tracking
-   acknowledgements
-   retries
-   failure handling
-   audit logging

### 6.3 Transport Layer

The transport layer moves completed packages:

``` text
send/ -> Authorized Dongle -> receive/
```

It should not need to understand Email, CRM, Blog, or other business
logic.

This separation allows the business system and transport mechanism to
evolve independently.

------------------------------------------------------------------------

## 7. Common Data Exchange Service

Business modules should not write directly to the send directory.

Avoid:

``` text
Email ---------> send/
Blog ----------> send/
CRM -----------> send/
Product -------> send/
Notification --> send/
```

Prefer:

``` text
Email -----------+
Blog ------------+
CRM -------------+--> Data Exchange Service
Product ---------+          |
Notification ----+          v
                       Exchange Outbox
                             |
                             v
                      Package Generator
                             |
                             v
                           send/
```

A business module publishes a business event.

Conceptually:

``` php
$exchangeService->publish(
    'email.created',
    'OUTER',
    [
        'email_id'     => $emailId,
        'sender_id'    => $senderId,
        'recipient_id' => $recipientId,
        'subject'      => $subject,
        'body'         => $body
    ]
);
```

The business module says **what happened** and **where the information
needs to go**.

The exchange framework handles the transport preparation.

------------------------------------------------------------------------

## 8. Business Events Instead of Database Replication

The system should synchronize business events rather than replicate SQL
statements or database tables directly.

Prefer:

``` text
email.created
email.deleted
customer.updated
product.updated
```

rather than:

``` text
INSERT INTO ...
UPDATE ...
DELETE FROM ...
```

Example:

``` json
{
    "package_id": "PKG-10001",
    "source_site": "INNER",
    "destination_site": "OUTER",
    "event_type": "email.created",
    "entity_id": "EMAIL-500",
    "payload": {
        "sender_id": 1001,
        "recipient_id": 8002,
        "subject": "Project Update",
        "body": "..."
    }
}
```

The receiving application's handler decides how the event affects its
own database and file system.

This reduces coupling between the two sites' database implementations.

------------------------------------------------------------------------

## 9. Event Naming

A consistent naming convention is recommended:

``` text
<domain>.<action>
```

Examples:

``` text
email.created
email.read
email.deleted

blog.created
blog.updated
blog.deleted

product.created
product.updated

crm.customer.created
crm.customer.updated

notification.created

exchange.ack
```

The convention makes event routing and documentation easier.

------------------------------------------------------------------------

## 10. Exchange Outbox

Outgoing exchange events should first be recorded reliably in MariaDB.

Example table:

``` sql
CREATE TABLE exchange_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id CHAR(36) NOT NULL,
    source_site VARCHAR(32) NOT NULL,
    destination_site VARCHAR(32) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id VARCHAR(100) DEFAULT NULL,
    payload LONGTEXT NOT NULL,
    sequence_no BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    packaged_at DATETIME DEFAULT NULL,
    exported_at DATETIME DEFAULT NULL,
    acknowledged_at DATETIME DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_package_id (package_id),
    KEY idx_status (status),
    KEY idx_sequence (sequence_no),
    KEY idx_destination_status (destination_site, status)
);
```

Possible outgoing statuses include:

``` text
PENDING
PROCESSING
PACKAGED
EXPORTED
WAITING_ACK
ACKNOWLEDGED
FAILED
```

`PROCESSING` is useful when workers must safely claim records without
multiple workers generating the same package.

------------------------------------------------------------------------

## 11. Directory Structure

A more explicit directory structure is recommended instead of only
`send/` and `receive/`.

``` text
/data-exchange/

    send/
        building/
        ready/
        exported/
        failed/

    receive/
        incoming/
        processing/
        processed/
        rejected/
        failed/

    quarantine/

    logs/
```

### 11.1 Building and Ready Directories

A package should not become visible to the dongle while it is still
being written.

Use:

``` text
send/building/PKG-10001.tmp
```

After generation is fully complete and the file is closed, atomically
rename or move it to:

``` text
send/ready/PKG-10001.pkg
```

The dongle software should only collect packages from `send/ready/`.

This prevents transfer of incomplete packages.

### 11.2 Receive Claiming

Similarly, a receiver can claim an incoming file by moving:

``` text
receive/incoming/PKG-10001.pkg
```

to:

``` text
receive/processing/PKG-10001.pkg
```

before processing it.

This reduces the risk of two workers processing the same physical file
simultaneously.

------------------------------------------------------------------------

## 12. Package Format

A package may conceptually contain:

``` text
PKG-10001.pkg
|
+-- manifest.json
+-- payload.json
+-- attachments/
|   +-- 001.pdf
|   +-- 002.xlsx
|
+-- signature.sig
```

Large binary attachments should generally remain separate binary files
rather than being Base64-encoded inside a large JSON payload.

------------------------------------------------------------------------

## 13. Manifest

Example:

``` json
{
    "format": "COMPANY-DX",
    "version": 1,
    "package_id": "PKG-10001",
    "source_site": "INNER",
    "destination_site": "OUTER",
    "event_type": "email.created",
    "entity_type": "email",
    "entity_id": "EMAIL-500",
    "sequence_no": 1052,
    "created_at": "2026-10-07T04:30:00+09:00",
    "payload": {
        "file": "payload.json",
        "sha256": "..."
    },
    "attachments": [
        {
            "file": "attachments/001.pdf",
            "sha256": "..."
        }
    ]
}
```

The manifest contains information required to identify, validate, route,
audit, and process the package.

------------------------------------------------------------------------

## 14. Package Lifecycle

A typical outgoing lifecycle is:

``` text
Business Event
     |
     v
Exchange Outbox
     |
     v
Package Generation
     |
     v
send/building/
     |
     v
send/ready/
     |
     v
Dongle Transfer
```

Receiving:

``` text
receive/incoming/
       |
       v
receive/processing/
       |
       v
Package Validation
       |
       +---- invalid ----> rejected/quarantine
       |
       v
Event Dispatch
       |
       v
Business Processing
       |
       +---- failure ----> receive/failed/
       |
       v
Record Success
       |
       v
receive/processed/
```

------------------------------------------------------------------------

## 15. Duplicate Protection and Idempotency

Duplicate delivery must be expected.

For example, the dongle might retry a transfer and deliver the same
package twice.

Every package therefore requires a globally unique `package_id`.

The receiver keeps a permanent processing record.

Example:

``` sql
CREATE TABLE exchange_received (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id CHAR(36) NOT NULL,
    source_site VARCHAR(32) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    sequence_no BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    checksum VARCHAR(64) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    received_at DATETIME NOT NULL,
    processing_started_at DATETIME DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_package_id (package_id),
    KEY idx_source_sequence (source_site, sequence_no),
    KEY idx_status (status)
);
```

Processing concept:

``` text
Receive package
      |
      v
Has package_id already been processed?
      |
   +--+--+
   |     |
  YES    NO
   |     |
Ignore   Validate
         |
         v
       Process
```

Business handlers should also be designed to be idempotent where
practical.

For example, processing the same deletion request twice should ideally
leave the entity deleted rather than producing inconsistent data.

------------------------------------------------------------------------

## 16. Ordering and Sequence Numbers

Ordering should be kept simple in the initial implementation.

Ordering matters when related operations must be applied in the same
logical order.

Example:

``` text
Inner Site:

1. email.created  EMAIL-100
2. email.deleted  EMAIL-100
```

Correct processing:

``` text
Create EMAIL-100
      |
      v
Delete EMAIL-100
```

If the delete package arrives first and the create package arrives
afterward, the final state can become incorrect.

### 16.1 Why Out-of-Order Delivery Can Happen

Suppose Inner generates:

``` text
001.pkg
002.pkg
003.pkg
```

If `002.pkg` temporarily fails to transfer, Outer may receive:

``` text
001.pkg
003.pkg
```

and later:

``` text
002.pkg
```

Therefore, arrival order cannot always be assumed to equal generation
order.

### 16.2 Recommended Version 1 Approach

Assign a sequence number to outgoing packages:

``` text
Inner -> Outer

1001
1002
1003
1004
```

Outer can detect:

``` text
Expected: 1003
Received: 1004
```

and record a warning that package `1003` may be missing.

However, the global sequence should **not automatically block all later
business processing**.

For example:

``` text
1001 email.created
1002 email.deleted
1003 blog.created
1004 product.updated
```

If an Email package is missing, it may be unnecessary to stop unrelated
Blog and Product processing.

Therefore:

> **Global package sequence numbers are primarily used for
> missing-package detection, abnormal-order detection, auditing, and
> troubleshooting. They should not automatically impose strict global
> business-event ordering.**

If a particular business entity later requires strict ordering, an
entity-level version or ordering key can be introduced.

Example:

``` text
Customer #500

Version 1 -> created
Version 2 -> name changed
Version 3 -> address changed
Version 4 -> status changed
```

This allows strict ordering where it is actually required without
blocking the entire exchange system.

------------------------------------------------------------------------

## 17. Event Dispatcher and Handler Registry

The receiver should not contain a large chain of hard-coded conditional
business logic.

Use an event-handler registry.

Conceptually:

``` php
$dispatcher->register(
    'email.created',
    new EmailCreatedHandler()
);

$dispatcher->register(
    'email.deleted',
    new EmailDeletedHandler()
);

$dispatcher->register(
    'blog.created',
    new BlogCreatedHandler()
);

$dispatcher->register(
    'crm.customer.updated',
    new CustomerUpdatedHandler()
);
```

The receiver can then call:

``` php
$dispatcher->dispatch(
    $package->getEventType(),
    $package
);
```

Conceptually:

``` text
Package
   |
   v
Event Dispatcher
   |
   +--> email.created ------> EmailCreatedHandler
   |
   +--> email.deleted ------> EmailDeletedHandler
   |
   +--> blog.created -------> BlogCreatedHandler
   |
   +--> crm.customer.updated -> CustomerUpdatedHandler
```

The Data Exchange framework handles routing. Business modules own their
business processing.

------------------------------------------------------------------------

## 18. Receiver Processing Protocol

Recommended processing:

``` text
Receive package
      |
      v
Claim physical file
      |
      v
Check package structure
      |
      v
Check format and version
      |
      v
Check destination site
      |
      v
Verify digital signature
      |
      v
Verify hashes
      |
      v
Check duplicate package_id
      |
      v
Check sequence information
      |
      v
Validate event/payload
      |
      v
Begin business processing
      |
      v
Dispatch event
      |
      v
Record successful processing
      |
      v
Generate ACK if required
      |
      v
Move to processed/
```

Invalid or suspicious packages must not update business data.

------------------------------------------------------------------------

## 19. Database Transactions

Where possible, business processing and the receiver's
successful-processing record should be committed in the same database
transaction.

Conceptually:

``` php
$db->beginTransaction();

try {
    $dispatcher->dispatch($eventType, $package);

    $receivedRepository->markProcessed($packageId);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

This helps prevent a failure such as:

``` text
Business record inserted
        |
        v
Server crashes
        |
        v
Package not marked processed
        |
        v
Worker restarts
        |
        v
Same operation executed again
```

Transactions and idempotent handlers should work together.

------------------------------------------------------------------------

## 20. Reliable Acknowledgement and Retry Protocol

Acknowledgement is essential because **package generated** and **destination successfully processed package** are different states.

### 20.1 Normal Acknowledgement Flow

The receiver creates an `exchange.ack` package after successful processing:

```json
{
  "event_type": "exchange.ack",
  "package_id": "ACK-200",
  "payload": {
    "original_package_id": "PKG-100",
    "result": "SUCCESS"
  }
}
```

The ACK has its own package ID, while `original_package_id` identifies the business package being acknowledged.

### 20.2 The ACK Can Also Be Lost

Outer may successfully process `PKG-100`, while `ACK-200` fails to return to Inner. Inner cannot distinguish between:

1. `PKG-100` never reaching Outer; and
2. Outer successfully processing `PKG-100`, but the ACK being lost.

Therefore:

> **A missing acknowledgement means the outcome is unknown. It does not mean the original package failed.**

### 20.3 Retry Using the Same Package Identity

When the retry policy is triggered, Inner resends the original package with the **same `package_id`**:

```text
First attempt: PKG-100
Retry:         PKG-100
```

Outer checks its durable processing record. If `PKG-100` was already successfully processed, it must not execute the business operation again. Instead, it regenerates another successful ACK.

```text
INNER                                      OUTER

PKG-100 ---------------------------------> Process once
                       X <--------------- ACK-200

WAITING_ACK
    |
Retry PKG-100 ---------------------------> Duplicate detected
                                            |
                                     Do not process again
                                            |
                       <---------------- ACK-201
    |
ACKNOWLEDGED
```

`ACK-200` and `ACK-201` can have different ACK package IDs, but both reference `original_package_id = PKG-100`.

### 20.4 Persist Processing Results and ACK Requirements

Outer should durably retain:

```text
package_id   = PKG-100
status       = PROCESSED
result       = SUCCESS
processed_at = ...
ack_status   = PENDING / EXPORTED
```

If the server crashes after the business transaction commits but before the ACK is generated, the durable record allows the ACK to be generated later.

### 20.5 No ACK-of-ACK

Acknowledgement packages are never themselves acknowledged:

```text
Business package -> ACK -> STOP
```

Otherwise an endless ACK-of-ACK chain would result. A lost ACK is recovered by retrying the original package.

### 20.6 Sender and Receiver States

Sender:

```text
PENDING -> PACKAGED -> EXPORTED -> WAITING_ACK
                                      |
                     +----------------+----------------+
                     |                                 |
                 ACK received                     retry policy
                     |                                 |
                     v                                 v
               ACKNOWLEDGED                 resend same package_id
```

Receiver:

```text
RECEIVED -> PROCESSING -> PROCESSED -> ACK_PENDING -> ACK_EXPORTED
```

`ACK_EXPORTED` does not prove that the sender received the ACK.

### 20.7 Reliability Model

```text
At-least-once package delivery
        +
Stable package_id across retries
        +
Duplicate detection
        +
Idempotent business processing
        +
Persistent processing result
        +
Regeneratable ACK
        +
No ACK-of-ACK
```

Fundamental rule:

> **Failure to receive an acknowledgement does not tell the sender whether the original package failed or the acknowledgement failed. The sender retries the original package using the same package identity. The receiver performs duplicate-safe/idempotent processing and acknowledges an already-successfully-processed package again. Acknowledgements themselves are never acknowledged.**

---

## 21. Comparison with TCP Reliability

TCP solves a conceptually similar uncertainty problem at the network transport layer.

### 21.1 TCP ACKs Can Also Be Lost

```text
Computer A                              Computer B

DATA ---------------------------------> Received
                     X <-------------- ACK
```

The sender cannot immediately know whether the data was lost or the data arrived and only the ACK was lost. TCP therefore uses retransmission mechanisms.

### 21.2 Retransmission and Duplicate Handling

TCP tracks byte positions using sequence numbers. Retransmitted bytes can be recognized as belonging to positions already received.

Conceptually:

| TCP | Data Exchange |
|---|---|
| TCP bytes/segments | Exchange package |
| TCP sequence information | `package_id` + optional `sequence_no` |
| TCP ACK | `exchange.ack` |
| Retransmission | Resend original package |
| Duplicate handling | `package_id` duplicate detection |
| Ordered byte stream | Business-specific ordering where required |
| Network state | Durable exchange tables/directories |
| Fast network retry | Delayed dongle/package retry |

### 21.3 Ordering

TCP can buffer out-of-order network data and present an ordered byte stream to the application.

The Data Exchange system deliberately does not require one strict global business-event stream because Email, CRM, Blog, Product, and Notification events may be unrelated. Global `sequence_no` is primarily used for gap/order detection and auditing in Version 1. Strict entity-level ordering can be added where business consistency requires it.

### 21.4 No Endless ACK-of-ACK

TCP does not achieve reliability by recursively acknowledging every ACK forever. Likewise:

```text
Business package -> acknowledgement
```

is sufficient for the Data Exchange protocol. A lost acknowledgement is recovered by retransmitting the original package.

### 21.5 Important Difference: Durable State

TCP operates between connected systems and can retry on network time scales. The dongle architecture may have minutes or hours between transfer opportunities.

The Data Exchange system must therefore survive:

- server restart;
- application restart;
- power failure;
- dongle removal;
- delayed physical transfer;
- long intervals between transfers.

Durable MariaDB records and filesystem packages are essential.

### 21.6 Important Difference: TCP ACK vs Business ACK

A TCP ACK concerns transport-level receipt/progress of bytes. It does **not** mean the receiving application successfully committed an email, CRM update, or other business transaction.

The Data Exchange success ACK has higher-level meaning:

```text
Receive package
      |
Validate
      |
Process business event
      |
Commit transaction
      |
Record SUCCESS
      |
Generate SUCCESS ACK
```

Therefore:

> **A Data Exchange SUCCESS ACK means that the destination successfully processed and committed the business package according to the exchange protocol.**

TCP makes an unreliable packet network usable as a reliable ordered byte stream. The Data Exchange protocol uses related reliability principles at a higher layer to make delayed physical package transfer usable for reliable business-event synchronization.

---

## 22. Failure Types

Technical failures and business rejections should be distinguished.

### 21.1 Technical/Security Failures

Examples:

``` text
INVALID_SIGNATURE
CHECKSUM_FAILURE
CORRUPTED_PACKAGE
UNSUPPORTED_VERSION
INVALID_DESTINATION
MALFORMED_PACKAGE
```

These normally mean the package itself cannot safely be processed.

### 21.2 Business Failures

Examples:

``` text
INVALID_RECIPIENT
CUSTOMER_NOT_FOUND
PRODUCT_NOT_AVAILABLE
BUSINESS_RULE_REJECTED
```

The package may be valid and authentic, but the requested business
operation cannot be completed.

Keeping these categories separate makes administration and
troubleshooting much clearer.

------------------------------------------------------------------------

## 23. Security Model

The authorized dongle is an important transport security mechanism, but
the receiving site should not trust a business package merely because it
arrived through that dongle.

The receiving site should be able to independently answer:

1.  Is this a supported Data Exchange package?
2.  Is this package intended for this site?
3.  Was it genuinely generated by the expected source?
4.  Has its protected content changed?
5.  Has this package already been processed?
6.  Is its format/version supported?

### 22.1 Package ID

`package_id` provides unique package identity and supports duplicate
detection.

### 22.2 Source and Destination

The manifest identifies:

``` json
{
    "source_site": "INNER",
    "destination_site": "OUTER"
}
```

Outer should reject a package not intended for Outer.

However, a text field saying `"source_site": "INNER"` does not by itself
prove that Inner created the package.

### 22.3 SHA-256 Hashes

Hashes can be stored for the payload and attachments.

Example:

``` json
{
    "payload": {
        "file": "payload.json",
        "sha256": "..."
    }
}
```

Outer recalculates the hash and compares it with the expected value.

This detects accidental corruption.

A plain hash alone is **not sufficient to establish authenticity**,
because an attacker capable of modifying both the content and
unprotected manifest could calculate a new hash.

### 22.4 Digital Signatures

Digital signatures provide the stronger protection.

Conceptually:

``` text
INNER
  |
  | Private signing key
  v
Sign protected package data
  |
  v
Package + signature
  |
  v
Dongle
  |
  v
OUTER
  |
  | Trusted INNER public key
  v
Verify signature
  |
 +----+----+
 |         |
Valid    Invalid
 |         |
Process   Reject
```

A correctly designed signature process provides:

-   **authenticity** --- evidence that the package was signed by the
    holder of the trusted source private key;
-   **integrity** --- signed content cannot be modified without
    invalidating the signature.

The exact canonical data covered by the signature must be defined
carefully in the detailed protocol.

### 22.5 Encryption

Encryption solves a different problem.

``` text
Digital signature -> authenticity + integrity
Encryption        -> confidentiality
```

If packages contain confidential email bodies, attachments, CRM data, or
other sensitive information, encryption can protect the data while it
resides on or passes through the dongle.

Encryption can be added according to the organization's security
requirements and key-management design.

### 22.6 Package Security and Dongle Security Are Separate

Keep these as separate layers:

``` text
+----------------------------------+
| Package Security                 |
|                                  |
| Unique identity                  |
| Destination validation           |
| Hash/integrity validation        |
| Digital signature                |
| Encryption if required           |
+----------------+-----------------+
                 |
                 v
+----------------------------------+
| Dongle / Transport Security      |
|                                  |
| Authorized device                |
| Device authentication            |
| Transfer controls                |
| Operational controls             |
+----------------------------------+
```

The design principle is:

> **Every exchange package must carry enough security metadata to allow
> the receiving site to independently verify its authenticity,
> integrity, destination, uniqueness, and supported format before
> business processing.**

------------------------------------------------------------------------

## 24. Quarantine

A package that fails important security or structural validation should
not be processed.

Example:

``` text
receive/incoming/
       |
       v
Validation
       |
   +---+---+
   |       |
 Valid   Invalid
   |       |
   v       v
Process  quarantine/
```

The quarantine record should retain enough diagnostic information for
authorized administrators to investigate without silently discarding
evidence.

------------------------------------------------------------------------

## 25. Versioning

Versioning should exist from the beginning.

Example:

``` json
{
    "format": "COMPANY-DX",
    "version": 1
}
```

A receiver that does not support a package version should reject or
quarantine it safely instead of guessing how to process it.

Individual events may also eventually require their own schema versions:

``` json
{
    "event_type": "email.created",
    "event_version": 2
}
```

This makes long-term evolution easier.

------------------------------------------------------------------------

## 26. Attachments

Email and other modules may exchange large files.

Do not put large binary files directly into JSON as Base64 unless there
is a specific reason.

Prefer:

``` text
package.pkg

manifest.json
payload.json

attachments/
    001.bin
    002.bin
```

The payload can reference them:

``` json
{
    "email_id": "E100928",
    "subject": "Project Report",
    "attachments": [
        {
            "attachment_id": "A100",
            "filename": "report.pdf",
            "content_type": "application/pdf",
            "package_file": "attachments/001.bin"
        }
    ]
}
```

Each attachment should have integrity metadata in the manifest.

------------------------------------------------------------------------

## 27. Core Database Components

A practical implementation may use these core tables:

``` text
exchange_outbox
    Outgoing exchange events

exchange_received
    Received and processed package records

exchange_sequence
    Outgoing sequence generation

exchange_sequence_state
    Incoming sequence observations / expected sequence

exchange_errors
    Detailed processing failures

exchange_audit_log
    Operational and security audit trail
```

A dead-letter mechanism may be added later if operational requirements
justify it.

------------------------------------------------------------------------

## 28. Logging and Audit

The system should record important lifecycle events such as:

-   business event published
-   outbox record created
-   package generation started
-   package generation completed
-   package exported
-   package received
-   validation started
-   validation failed
-   signature verified
-   duplicate detected
-   sequence gap detected
-   event handler started
-   event handler completed
-   business rejection
-   technical failure
-   acknowledgement generated
-   acknowledgement received
-   package quarantined

Audit records should include useful identifiers such as:

``` text
package_id
event_type
source_site
destination_site
entity_id
sequence_no
timestamp
status
error_code
```

Sensitive payload content should not automatically be copied into logs.

------------------------------------------------------------------------

## 29. Retry and Recovery

Failures should not require manually recreating business operations.

Outgoing package generation can retry from the durable outbox.

Incoming processing failures can be retained in:

``` text
receive/failed/
```

with corresponding database error records.

Retries must still honor duplicate protection and transaction rules.

Security-validation failures should generally be quarantined rather than
automatically retried as ordinary processing failures.

------------------------------------------------------------------------

## 30. Recommended Version 1 Scope

The first implementation should remain conservative and understandable.

Recommended Version 1 features:

-   MariaDB exchange outbox
-   common Data Exchange Service
-   package generator
-   filesystem package transport
-   `building` and `ready` send states
-   `incoming` and `processing` receive states
-   JSON manifest
-   JSON business payload
-   separate binary attachments
-   unique package IDs
-   event type registry
-   business event handlers
-   receiver duplicate protection
-   idempotent handler design
-   sequence numbers for gap/order detection
-   acknowledgement events
-   retry and failure handling
-   audit logging
-   SHA-256 integrity metadata
-   digital signature verification
-   quarantine for invalid packages
-   package format versioning
-   encryption where confidentiality requirements demand it

Strict global business-event ordering is **not** required for Version 1.

------------------------------------------------------------------------

## 31. Important Design Principles

### Principle 1 --- No direct network synchronization

``` text
Inner X----------------X Outer
```

All cross-boundary business data moves through approved packages and the
dongle.

### Principle 2 --- Business modules do not control transport

``` text
Business Module
      |
      v
Data Exchange Service
```

### Principle 3 --- Exchange business events, not SQL commands

``` text
email.created
```

is preferable to transferring:

``` text
INSERT INTO emails ...
```

### Principle 4 --- Expect duplicate delivery

Every package has a unique identity, and receivers are idempotent.

### Principle 5 --- Detect ordering problems without unnecessarily blocking unrelated work

Sequence numbers initially support detection, auditing, and
troubleshooting.

Strict ordering is introduced only where a business entity actually
requires it.

### Principle 6 --- Do not trust transport alone

The receiving site independently validates the package before changing
business data.

### Principle 7 --- Separate package security from dongle security

The dongle protects and controls the transport mechanism.

The package protocol protects the business data and proves package
validity.

### Principle 8 --- Failed packages must remain observable

Do not silently discard failures.

Use failure, rejection, quarantine, and audit records.

### Principle 9 --- Design for evolution

Use package and event versions from the beginning.

------------------------------------------------------------------------

## 32. Final Architecture

``` text
                     INNER SITE
+--------------------------------------------------+
|                                                  |
| Email / Blog / CRM / Product / Notification     |
|                    |                             |
|                    v                             |
|          Data Exchange Service                  |
|                    |                             |
|                    v                             |
|             Exchange Outbox                     |
|                    |                             |
|                    v                             |
|            Package Generator                    |
|                    |                             |
|                    v                             |
|              send/ready/                        |
|                                                  |
+--------------------+-----------------------------+
                     |
                     v
             +---------------+
             |  AUTHORIZED   |
             |    DONGLE     |
             +-------+-------+
                     |
              AIR-GAPPED
               BOUNDARY
                     |
                     v
+--------------------+-----------------------------+
|                    |                             |
|                    v                             |
|          receive/incoming/                      |
|                    |                             |
|                    v                             |
|          Package Validation                     |
|                    |                             |
|        +-----------+-----------+                 |
|        |                       |                 |
|      Valid                   Invalid             |
|        |                       |                 |
|        v                       v                 |
| Event Dispatcher          Quarantine             |
|        |                                         |
|        +--> Email Handler                        |
|        +--> Blog Handler                         |
|        +--> CRM Handler                          |
|        +--> Product Handler                      |
|        +--> Notification Handler                 |
|        |                                         |
|        v                                         |
| Database / Filesystem                            |
|        |                                         |
|        v                                         |
| Received/Audit Record                            |
|        |                                         |
|        v                                         |
| Optional exchange.ack                            |
|                                                  |
|                     OUTER SITE                   |
+--------------------------------------------------+
```

The same architecture operates in reverse for Outer-to-Inner exchange.

------------------------------------------------------------------------

## 33. Conclusion

The proposed system is a **bidirectional asynchronous store-and-forward
business-event exchange architecture for network-isolated
environments**.

Its most important characteristic is separation of responsibility:

``` text
BUSINESS LAYER
      |
      | business events
      v
DATA EXCHANGE LAYER
      |
      | validated packages
      v
TRANSPORT LAYER
      |
      v
AUTHORIZED DONGLE
```

This design avoids direct network communication while still supporting
reliable synchronization of Email, Notification, Blog, Product, CRM, and
future business services.

The architecture is intentionally designed so that:

-   the business modules do not depend on the physical transport
    mechanism;
-   the dongle does not need to understand business logic;
-   duplicate or delayed transfers can be handled safely;
-   missing or abnormal package sequences can be detected;
-   security validation occurs before received data affects the business
    system;
-   acknowledgements can provide eventual confirmation;
-   failures remain traceable and recoverable;
-   the package protocol can evolve through explicit versioning.

The next detailed design stage should define the **transactional
event-publication protocol**, especially how a business transaction and
its exchange-outbox record are committed safely so that a successful
business change cannot occur without the corresponding exchange event
being recorded.

---

## 33. Reliable Acknowledgement and Retry Protocol

Acknowledgement requires special treatment because an acknowledgement package can itself fail to reach the original sender.

Assume the Inner Site sends `PKG-100` to the Outer Site:

```text
INNER                                      OUTER

PKG-100 ----------------------------------->
                                           Validate
                                           Process
                                           Commit business data
                                           Record PKG-100 = PROCESSED
                                           Generate ACK-200

                     X <-------------------- ACK-200
                     ACK transfer failed
```

Outer has successfully processed `PKG-100`, but Inner has not received the acknowledgement. From Inner's point of view, this looks the same as a failure of the original package transfer.

### 33.1 A Missing ACK Means the Result Is Unknown

Inner cannot distinguish between these two situations:

```text
Case A
------
PKG-100 never reached Outer.

Case B
------
PKG-100 reached Outer and was processed successfully,
but the ACK failed to return to Inner.
```

In both cases Inner sees:

```text
PKG-100 = WAITING_ACK
```

Therefore:

> **Failure to receive an acknowledgement is an unknown state. It must not automatically be interpreted as business-processing failure.**

### 33.2 Retry the Original Package

After the configured retry condition is reached, Inner may make the original package available for transfer again.

The retry must retain the same logical package identity:

```text
package_id = PKG-100
```

Do not create `PKG-101` merely because `PKG-100` is being retried. A new package ID would make the receiver unable to recognize the retry as the same logical operation.

Example:

```text
INNER                                      OUTER

PKG-100 ----------------------------------->
                                           Process successfully
                                           Record PKG-100

                     X <-------------------- ACK-200

Inner remains WAITING_ACK

PKG-100 ----------------------------------->
                                           Lookup PKG-100
                                           Already PROCESSED
                                           DO NOT process again
                                           Generate another ACK

                <--------------------------- ACK-201

Inner receives ACK-201
PKG-100 = ACKNOWLEDGED
```

### 33.3 Receiver Duplicate Handling

When Outer receives a package, it first checks `package_id`.

```text
Receive PKG-100
      |
      v
Lookup package_id
      |
   +--+--+
   |     |
 New   Already processed
   |     |
   v     v
Process  Do not repeat business action
   |     |
   +--+--+
      |
      v
Return/regenerate acknowledgement
```

This is why durable duplicate detection and idempotent processing are essential parts of the protocol.

### 33.4 ACK Package Identity

An acknowledgement is itself a transport package and therefore has its own `package_id`.

Example:

```json
{
    "package_id": "ACK-201",
    "event_type": "exchange.ack",
    "payload": {
        "original_package_id": "PKG-100",
        "result": "SUCCESS"
    }
}
```

The distinction is:

```text
ACK package identity:
    package_id = ACK-201

Business package being acknowledged:
    original_package_id = PKG-100
```

Several different ACK packages may validly acknowledge the same original package:

```text
ACK-200 -> original_package_id = PKG-100
ACK-201 -> original_package_id = PKG-100
ACK-202 -> original_package_id = PKG-100
```

Inner only needs one valid successful ACK for `PKG-100` to change its state to `ACKNOWLEDGED`.

### 33.5 ACK Generation Must Be Recoverable

Outer should persist the processing result before relying on ACK transfer.

For example:

```text
PKG-100
processing_status = PROCESSED
ack_status        = PENDING
```

If Outer crashes after committing the business operation but before creating the ACK:

```text
Process PKG-100
      |
      v
Commit business data
      |
      v
Record PKG-100 = PROCESSED
      |
      X  Server crash

ACK was not generated
```

After restart, the system can see that the package was already processed and that an acknowledgement is still required.

It can then generate or regenerate the ACK without repeating the business operation.

### 33.6 Do Not Use ACK-of-ACK

An acknowledgement package should **not itself require a business acknowledgement**.

Avoid:

```text
Business Package
      |
      v
ACK
      |
      v
ACK of ACK
      |
      v
ACK of ACK of ACK
      |
     ...
```

Instead:

```text
Business Package
      |
      v
ACK
      |
     STOP
```

If an ACK is lost, retrying the original package causes the receiver to recognize the duplicate and return another ACK.

This closes the reliability loop without creating an infinite acknowledgement chain.

### 33.7 Sender State Model

A practical sender state model is:

```text
PENDING
   |
   v
PACKAGED
   |
   v
EXPORTED
   |
   v
WAITING_ACK
   |
   +------ retry condition ------+
   |                             |
   |                             v
   |                    Resend same package_id
   |                             |
   |                             +----> WAITING_ACK
   |
   v
ACKNOWLEDGED
```

The retry policy should be configurable because physical dongle transport may take minutes or hours. A normal network-style timeout of seconds would not be appropriate.

### 33.8 Receiver State Model

A practical receiver model is:

```text
RECEIVED
   |
   v
PROCESSING
   |
   v
PROCESSED
   |
   v
ACK_PENDING
   |
   v
ACK_EXPORTED
```

`ACK_EXPORTED` means only that Outer made the ACK available for transport. It does **not** prove that Inner received the ACK.

### 33.9 Delivery Semantics

The resulting model can be described as:

```text
At-least-once package delivery
        +
Stable package_id across retries
        +
Duplicate detection
        +
Idempotent business processing
        +
Persistent processing result
        +
Regeneratable acknowledgement
        +
No ACK-of-ACK
```

The physical package may be transferred more than once, but the business effect should occur only once.

This is more practical than attempting to guarantee that a physical transfer happens exactly once.

### 33.10 Fundamental ACK Rule

The protocol should adopt the following rule:

> **Failure to receive an acknowledgement never tells the sender whether the original package failed or the acknowledgement failed. The sender therefore retries the original package using the same package identity. The receiver provides duplicate-safe/idempotent processing and returns an acknowledgement again for an already-successfully-processed package. Acknowledgements themselves are not acknowledged.**

---

## 34. Comparison with TCP Reliability

The acknowledgement/retry design has useful similarities to TCP. The comparison helps explain why retransmission and duplicate detection are necessary, while also showing why the Data Exchange protocol requires stronger application-level semantics.

### 34.1 TCP Also Uses Acknowledgements

Simplified TCP communication:

```text
Computer A                         Computer B

    DATA --------------------------->
                                     Receive data

         <--------------------------- ACK
```

TCP uses acknowledgements to tell the sender which byte positions have been received.

### 34.2 TCP Has the Same Lost-ACK Uncertainty

An ACK can be lost:

```text
Computer A                         Computer B

    DATA --------------------------->
                                     Receive data

                       X <----------- ACK
                       ACK lost
```

Computer A cannot directly know whether:

```text
1. the original DATA was lost
```

or:

```text
2. the DATA arrived but the ACK was lost
```

This is conceptually the same uncertainty faced by Inner when an `exchange.ack` does not return.

### 34.3 TCP Retransmits Unacknowledged Data

If required acknowledgement progress does not occur, TCP can retransmit data.

Simplified:

```text
Send DATA
    |
    v
Wait for acknowledgement progress
    |
    +--> acknowledged -> continue
    |
    +--> retransmission condition
              |
              v
         retransmit data
```

The receiver must therefore be able to recognize data it has already received.

### 34.4 TCP Sequence Numbers Support Duplicate Detection and Ordering

TCP associates bytes with sequence positions.

Simplified example:

```text
SEQ = 1000
DATA = ABCDE
```

If the same bytes are retransmitted, TCP can recognize their sequence range and avoid delivering duplicate bytes to the application stream.

This is conceptually similar to the Data Exchange system using:

```text
package_id = PKG-100
```

for duplicate recognition.

The mechanisms are not identical, but the reliability pattern is similar:

```text
TCP                          Data Exchange
-------------------------    ----------------------------
Sequence positions           package_id / sequence_no
Acknowledgements             exchange.ack
Retransmission               Resend original package
Duplicate handling           package_id duplicate check
Ordering/reassembly          Business/order rules
```

### 34.5 TCP Uses Cumulative Acknowledgement

TCP acknowledgements generally describe byte-stream progress rather than acknowledging individual application messages.

Simplified:

```text
Sender sends byte ranges beginning at:

1000
1003
1006
```

An acknowledgement such as:

```text
ACK 1006
```

means, conceptually, that the receiver is ready for the stream beginning at byte 1006 because the preceding contiguous bytes have been received.

The Data Exchange protocol does not need to copy TCP's byte-level cumulative-ACK mechanism. Its units are durable business packages, not transient byte-stream segments.

### 34.6 TCP Provides Ordered Byte-Stream Delivery

TCP reconstructs an ordered byte stream even if IP packets arrive out of order.

Conceptually:

```text
Network arrival:

1000
1001
1003
1002

TCP application stream:

1000
1001
1002
1003
```

The Data Exchange system should **not automatically apply this strict global ordering rule to all business packages**.

Email, CRM, Blog, Product, and Notification events may be independent. A missing Email package should not necessarily stop an unrelated Product package.

Therefore:

- TCP provides ordered delivery for one byte stream.
- Data Exchange uses global sequence information mainly for gap/order detection and applies stricter business ordering only where required.

### 34.7 TCP Does Not Create an Infinite ACK-of-ACK Chain

TCP does not solve reliability by endlessly acknowledging acknowledgements:

```text
DATA
 |
 v
ACK
 |
 v
ACK of ACK
 |
 v
ACK of ACK of ACK
```

The Data Exchange protocol follows the same high-level principle: an `exchange.ack` does not itself require another business acknowledgement.

### 34.8 Important Difference: TCP Is Online

TCP endpoints have an active network path while the connection exists.

Retries and acknowledgements commonly occur on timescales of milliseconds or seconds.

The Data Exchange architecture has an air-gapped boundary:

```text
INNER
   |
   X   No direct network path
   |
OUTER
```

Retry may depend on physical dongle movement and can take minutes or hours.

Therefore, Data Exchange state must be durable across:

- server restarts;
- application restarts;
- power failures;
- dongle removal;
- operator delays;
- long intervals between transfers.

This is why MariaDB tables, persistent files, durable package IDs, and processing history are required.

### 34.9 Most Important Difference: TCP ACK Is Not a Business ACK

A TCP acknowledgement indicates transport-level byte-stream receipt/progress. It does **not** mean that the receiving business application successfully completed its operation.

For example:

```text
TCP successfully delivers bytes
          |
          v
Application receives request
          |
          v
Database operation fails
```

TCP may have done its job correctly even though the business operation failed.

The Data Exchange acknowledgement is deliberately higher-level:

```text
Receive package
      |
      v
Validate package
      |
      v
Process business event
      |
      v
Commit database transaction
      |
      v
Record processing result
      |
      v
Generate SUCCESS ACK
```

Therefore a successful Data Exchange ACK means something stronger than a TCP ACK:

> **The destination site successfully processed the business package according to the exchange protocol.**

A failure/rejection result can likewise communicate that a valid package was received but its business operation could not be completed.

### 34.10 TCP and Data Exchange Side-by-Side

| TCP concept | Data Exchange concept |
|---|---|
| TCP byte/segment data | Exchange package |
| Sequence positions | `package_id` plus optional `sequence_no` |
| TCP acknowledgement | `exchange.ack` |
| Retransmission | Resend original package |
| Duplicate byte/segment handling | `package_id` duplicate detection |
| Ordered byte-stream reconstruction | Ordering rules where business data requires them |
| Receive buffers / connection state | Receive directories and durable database state |
| Network retransmission | Dongle/package retry |
| Transport-level acknowledgement | Business-processing acknowledgement |

### 34.11 Shared Reliability Pattern

At a high level, both designs use a similar reliability pattern:

```text
Send
  |
  v
Identify data
  |
  v
Receive
  |
  v
Acknowledge
  |
  v
Acknowledgement missing?
  |
  v
Retransmit
  |
  v
Receiver detects duplicate
  |
  v
Do not apply duplicate effect
  |
  v
Acknowledge again
```

The difference is the abstraction level:

> **TCP makes an unreliable packet network usable as a reliable ordered byte stream. The Data Exchange protocol makes delayed and potentially repeated physical dongle transfers usable for reliable business-event synchronization between isolated sites.**

---

## 35. Updated Reliability Summary

The complete exchange reliability model should now be understood as:

```text
Durable business event
        |
        v
Durable outbox
        |
        v
Package with stable package_id
        |
        v
Physical transfer (may fail or repeat)
        |
        v
Destination validation
        |
        v
Duplicate check
        |
        v
Idempotent business processing
        |
        v
Durable processing result
        |
        v
Regeneratable business ACK
        |
        v
Physical ACK transfer (may fail or repeat)
        |
        v
Sender marks original package ACKNOWLEDGED
```

This model accepts that neither the original physical transfer nor the ACK transfer is perfectly reliable. Reliability is achieved by **durable state, stable identity, retransmission, duplicate detection, idempotent processing, and business-level acknowledgement** rather than by assuming that each dongle transfer succeeds exactly once.

---

# Checksum and SHA-256 Integrity Design

## Purpose

The Data Exchange framework uses SHA-256 to detect whether a package file, payload, or attachment was corrupted or modified during package creation, storage, dongle transfer, or import.

For this architecture, the preferred term in security-sensitive documentation is **cryptographic hash** rather than simply **checksum**. The word checksum is broader and may also refer to non-cryptographic mechanisms such as CRC32.

The standard algorithm for the exchange system is:

```text
SHA-256
```

SHA-256 produces a 256-bit digest, commonly represented as 64 hexadecimal characters.

## Important distinction: transport integrity vs package security

A SHA-256 value can tell us whether data changed, but an unprotected SHA-256 value does not prove who created the package.

Therefore the architecture separates two concepts:

```text
TRANSPORT INTEGRITY
-------------------
External SHA-256 of final .pkg file

Purpose:
- Detect incomplete copy
- Detect storage corruption
- Detect accidental modification
- Detect damaged removable media transfer

PACKAGE SECURITY
----------------
Digital signature + signed manifest + internal SHA-256 hashes

Purpose:
- Verify authenticity
- Verify cryptographic integrity
- Protect package metadata
- Protect payload and attachment hashes
```

The external SHA-256 file is useful for transport checking, but it is **not** a substitute for the package digital signature.

## Recommended final package files

After Inner Site completes a package, the send directory contains:

```text
send/ready/

PKG-10001.pkg
PKG-10001.pkg.sha256
```

The `.sha256` file can use the common format:

```text
8f91ab73b51e12c78345d9a52c13b7624af736ca3a1f18f9ea830db219327b71  PKG-10001.pkg
```

## Package generation sequence

The package must be completely finalized before calculating its final SHA-256 hash.

```text
Build package
    |
    v
Write manifest/payload/attachments/signature
    |
    v
Finish container creation
    |
    v
Flush and close package file
    |
    v
Calculate SHA-256 of final .pkg
    |
    v
Create .sha256 sidecar file
    |
    v
Move package + .sha256 to send/ready/
    |
    v
Treat package as immutable
```

### Immutability rule

> A package file must be finalized and closed before its SHA-256 hash is calculated. After the hash is generated, the package file is immutable. Any modification requires recalculating the hash and regenerating associated integrity metadata.

Do not calculate the final package SHA-256 while the package is still being written.

## Why the whole-package SHA-256 should be outside the package

Do not try to store the final hash of `PKG-10001.pkg` inside `PKG-10001.pkg` itself.

Doing so creates a circular dependency:

```text
Create package
    |
    v
Hash = AAA
    |
    v
Write AAA into package
    |
    v
Package changed
    |
    v
Hash becomes BBB
    |
    v
Write BBB into package
    |
    v
Package changes again
```

Therefore the whole-package SHA-256 belongs outside the final package:

```text
PKG-10001.pkg
PKG-10001.pkg.sha256
```

## Internal file hashes

The package manifest should also contain SHA-256 hashes for important internal files.

Example:

```json
{
    "format": "COMPANY-DX",
    "version": 1,
    "package_id": "PKG-10001",
    "source_site": "INNER",
    "destination_site": "OUTER",
    "event_type": "email.created",
    "files": [
        {
            "path": "payload.json",
            "sha256": "..."
        },
        {
            "path": "attachments/001.pdf",
            "sha256": "..."
        },
        {
            "path": "attachments/002.xlsx",
            "sha256": "..."
        }
    ]
}
```

This provides file-level diagnostics. If a package contains several attachments, the receiver can identify exactly which file failed integrity verification.

## Why SHA-256 alone is not enough for authenticity

Suppose an attacker can modify both the package and its external `.sha256` file.

They could:

```text
Modify PKG-10001.pkg
        |
        v
Calculate a new SHA-256
        |
        v
Replace PKG-10001.pkg.sha256
```

The receiver would see matching hashes even though the package was maliciously replaced.

Therefore:

> The external `.sha256` file detects transport or storage corruption, but it must not be treated as proof that the package originated from Inner Site.

Authenticity comes from the digital signature.

## Recommended trust chain

```text
payload.json ---------------- SHA-256 ----+
                                       |
attachment 001.pdf ---------- SHA-256 ----+
                                       |
attachment 002.xlsx ---------- SHA-256 ----+
                                       |
                                       v
                                  manifest.json
                                       |
                                       v
                             canonical signing data
                                       |
                                       v
                              digital signature
                                       |
                                       v
                                 signature.sig
```

Outer Site performs two independent layers of validation:

```text
LEVEL 1 - Transport verification

PKG-10001.pkg
       |
       v
SHA-256(package)
       |
       v
Compare with external .sha256


LEVEL 2 - Package security verification

Verify digital signature
       |
       v
Verify signed manifest
       |
       v
Verify internal file hashes
       |
       v
Continue business processing
```

## Dongle software responsibility

The dongle software may also verify the external SHA-256 after copying.

Example flow:

```text
INNER
  |
  v
PKG-10001.pkg
PKG-10001.pkg.sha256
  |
  v
Copy to dongle
  |
  v
Recalculate SHA-256
  |
  +-- MATCH ----> Continue
  |
  +-- MISMATCH -> Retry / report copy failure
  |
  v
Copy to OUTER
  |
  v
Outer recalculates SHA-256 again
```

However, Outer Site must never trust only the dongle's result.

> The receiving Data Exchange Service must independently perform all security validation.

## Receiver validation order

A recommended package validation sequence is:

```text
Receive PKG-10001.pkg
        |
        v
Basic file/container sanity checks
        |
        v
Verify external package SHA-256
        |
        v
Open package safely
        |
        v
Read manifest
        |
        v
Validate format/version/destination
        |
        v
Verify digital signature
        |
        v
Verify payload SHA-256
        |
        v
Verify attachment SHA-256 values
        |
        v
Check duplicate package_id
        |
        v
Validate business payload
        |
        v
Process business event
```

## What happens when a hash check fails

A SHA-256 mismatch must prevent business processing.

```text
receive/incoming/
       |
       v
Calculate SHA-256
       |
       v
Compare
   +---+---+
   |       |
 MATCH  MISMATCH
   |       |
   v       v
Continue  Quarantine
            |
            v
         Audit log
```

Suggested audit information:

```text
package_id        = PKG-10001
status            = QUARANTINED
error_code        = CHECKSUM_MISMATCH
expected_sha256   = ...
actual_sha256     = ...
received_at       = ...
```

A package with failed integrity verification must not receive a SUCCESS acknowledgement.

A future protocol may define a negative result such as:

```text
exchange.nack
```

or:

```text
exchange.ack
result = FAILED
error_code = CHECKSUM_MISMATCH
```

That behavior should be defined together with retry policy.

## CRC32, MD5, SHA-1 and SHA-256

```text
Algorithm     Recommended use in this project
------------------------------------------------
CRC32         No for security-sensitive integrity
MD5           No
SHA-1         No
SHA-256       Yes
```

CRC32 is useful for accidental error detection, but it is not cryptographically secure. MD5 and SHA-1 should not be used for security-sensitive integrity validation in this system.

## PHP 7.4 sample: calculate package SHA-256

PHP 7.4 already provides the required hashing functions. No third-party library is required.

Use `hash_file()` for package files, especially large files.

```php
<?php

$packageFile = '/data-exchange/send/ready/PKG-10001.pkg';

$hash = hash_file('sha256', $packageFile);

if ($hash === false) {
    throw new RuntimeException(
        'Failed to calculate package SHA-256 hash.'
    );
}

echo $hash;
```

Do not read a large package completely into PHP memory only to hash it.

Avoid:

```php
$data = file_get_contents($packageFile);
$hash = hash('sha256', $data);
```

Prefer:

```php
$hash = hash_file('sha256', $packageFile);
```

## PHP 7.4 sample: create `.sha256` sidecar file

```php
<?php

$packageFile = '/data-exchange/send/ready/PKG-10001.pkg';
$hashFile    = $packageFile . '.sha256';

if (!is_file($packageFile)) {
    throw new RuntimeException(
        'Package file does not exist: ' . $packageFile
    );
}

$hash = hash_file('sha256', $packageFile);

if ($hash === false) {
    throw new RuntimeException(
        'Failed to calculate SHA-256 hash.'
    );
}

$content = $hash . '  ' . basename($packageFile) . PHP_EOL;

if (file_put_contents($hashFile, $content, LOCK_EX) === false) {
    throw new RuntimeException(
        'Failed to create SHA-256 file.'
    );
}
```

## PHP 7.4 sample: verify received package hash

```php
<?php

$packageFile = '/data-exchange/receive/incoming/PKG-10001.pkg';
$hashFile    = $packageFile . '.sha256';

if (!is_file($packageFile)) {
    throw new RuntimeException('Package file not found.');
}

if (!is_file($hashFile)) {
    throw new RuntimeException('SHA-256 file not found.');
}

$line = trim(file_get_contents($hashFile));
$parts = preg_split('/\s+/', $line, 2);

if (!$parts || empty($parts[0])) {
    throw new RuntimeException('Invalid SHA-256 file.');
}

$expectedHash = strtolower($parts[0]);

if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
    throw new RuntimeException('Invalid SHA-256 value.');
}

$actualHash = hash_file('sha256', $packageFile);

if ($actualHash === false) {
    throw new RuntimeException(
        'Failed to calculate package SHA-256.'
    );
}

if (!hash_equals($expectedHash, strtolower($actualHash))) {
    throw new RuntimeException(
        'Package SHA-256 verification failed.'
    );
}

echo 'Package hash is valid.';
```

## Reusable PHP service

```php
<?php

class DataExchange_HashService
{
    const ALGORITHM = 'sha256';

    public function calculateFileHash($filename)
    {
        if (!is_file($filename)) {
            throw new RuntimeException(
                'File does not exist: ' . $filename
            );
        }

        if (!is_readable($filename)) {
            throw new RuntimeException(
                'File is not readable: ' . $filename
            );
        }

        $hash = hash_file(self::ALGORITHM, $filename);

        if ($hash === false) {
            throw new RuntimeException(
                'Failed to calculate SHA-256: ' . $filename
            );
        }

        return strtolower($hash);
    }

    public function verifyFileHash($filename, $expectedHash)
    {
        $expectedHash = strtolower(trim($expectedHash));

        if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
            throw new InvalidArgumentException(
                'Invalid SHA-256 hash.'
            );
        }

        $actualHash = $this->calculateFileHash($filename);

        return hash_equals($expectedHash, $actualHash);
    }

    public function createHashFile($packageFile)
    {
        $hash = $this->calculateFileHash($packageFile);

        $hashFile = $packageFile . '.sha256';

        $content =
            $hash .
            '  ' .
            basename($packageFile) .
            PHP_EOL;

        if (file_put_contents(
            $hashFile,
            $content,
            LOCK_EX
        ) === false) {
            throw new RuntimeException(
                'Failed to create hash file: ' . $hashFile
            );
        }

        return $hashFile;
    }
}
```

## Final checksum/hash design principles

1. Use SHA-256 as the standard cryptographic hash algorithm.
2. Calculate the final package hash only after the package file is fully written and closed.
3. Treat the final package as immutable after hashing.
4. Store the whole-package SHA-256 outside the package in a `.sha256` sidecar file.
5. Use internal SHA-256 values in the manifest for payloads and attachments.
6. Protect the manifest with a digital signature.
7. Do not treat an unprotected SHA-256 value as proof of authenticity.
8. Allow the dongle software to verify transport integrity, but require Outer Site to verify independently.
9. Quarantine packages that fail integrity validation.
10. Never process business data from a package whose integrity verification failed.

