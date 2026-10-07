# Gmail-Like Internal Email Service — Feature and Design Guide

## 1. Purpose

This document describes the functional behavior and recommended design of a Gmail-like email service for a large company intranet. The target environment may contain approximately 10,000 employees and may operate without Internet access.

The goal is not to reproduce Gmail exactly. The goal is to adopt its useful email concepts while adding enterprise controls appropriate for an internal corporate system.

---

## 2. Main Mailbox Areas

A recommended user interface contains:

```text
Mail
├── Inbox
├── Starred
├── Snoozed
├── Sent
├── Drafts
├── All Mail
├── Spam / Quarantine
└── Trash

Labels
├── Projects
├── Customers
├── Reports
└── User-defined labels
```

### 2.1 Inbox

The Inbox contains incoming messages/conversations that currently require the user's attention.

A message can leave the Inbox without being deleted. For example, the user can archive it.

### 2.2 Sent

Contains messages sent by the current user.

### 2.3 Drafts

Contains messages that have been created but not yet sent. The system should support automatic draft saving.

### 2.4 Starred

Contains messages/conversations the user has marked with a star for quick access.

### 2.5 Snoozed

Contains messages temporarily removed from the Inbox until a specified date/time.

### 2.6 All Mail

All Mail represents the user's retained mail, including messages that have been archived.

An archived message normally disappears from Inbox but remains available in All Mail, search results, and applicable labels.

### 2.7 Trash

Contains messages the user has deleted. Trash is different from Archive.

### 2.8 Spam / Quarantine

For an internal-only system, traditional Internet spam may be less important, but a quarantine area can still be useful for suspicious attachments, policy violations, compromised accounts, or automated-message abuse.

---

## 3. To, Cc, and Bcc

Every outgoing message can have different recipient types.

| Type | Meaning | Visible to recipients | Typical purpose |
|---|---|---|---|
| To | Primary recipient | Yes | Person expected to receive or act on the message |
| Cc | Carbon Copy | Yes | Person who should be informed |
| Bcc | Blind Carbon Copy | No | Hidden recipient |

### 3.1 To

`To` identifies the primary recipient or recipients.

Example:

```text
From: Alice
To: John
Subject: Project Report
```

John is the main recipient.

A useful business convention is:

```text
To = Please act / respond if necessary.
```

This is a convention rather than a technical permission rule.

### 3.2 Cc

Cc means Carbon Copy.

Example:

```text
From: Alice
To: John
Cc: Mary
Subject: Project Report
```

John is the primary recipient. Mary receives the same message because she should be informed.

Both John and Mary can see that Mary was included in Cc.

A useful convention is:

```text
Cc = Please be aware / for your information.
```

Cc recipients are normal recipients. They can reply, reply all, forward, archive, star, label, or delete their own mailbox copy/state.

### 3.3 Bcc

Bcc means Blind Carbon Copy.

Example:

```text
From: Alice
To: John
Cc: Mary
Bcc: David
```

John, Mary, and David receive the message, but John and Mary must not be told that David was a Bcc recipient.

If there are multiple Bcc recipients, they should not normally be shown the identities of the other Bcc recipients.

### 3.4 Security rule for Bcc

Bcc visibility must be enforced by the server/API, not only hidden by JavaScript.

Bad design:

```text
API returns all Bcc recipients
        ↓
Browser JavaScript hides Bcc field
```

A user could inspect the API response and discover hidden recipients.

Correct design:

```text
Database
   ↓
Authorization / visibility logic
   ↓
API returns only recipient information the current user may see
   ↓
Browser
```

---

## 4. Reply Behavior

Cc and Bcc recipients are allowed to reply. Recipient type does not make a message read-only.

Assume:

```text
From: Alice
To: John
Cc: Mary
Bcc: David
```

### 4.1 Reply

If John clicks Reply:

```text
From: John
To: Alice
```

Mary and David do not automatically receive John's reply.

Likewise, if Mary clicks Reply, the reply normally goes to Alice.

If David, the Bcc recipient, clicks Reply, the reply normally goes to Alice. This can be safe because it does not necessarily expose David to John or Mary.

### 4.2 Reply All

If John clicks Reply All, a typical recipient set is:

```text
From: John
To: Alice
Cc: Mary
```

David is not included because John does not know that David was Bcc'd.

### 4.3 Bcc and Reply All

A Bcc recipient requires special care.

If David uses Reply All, the resulting message may reveal David's participation to visible recipients.

The UI should consider warning:

```text
You received this message via Bcc.
Replying to all may reveal that you received this message.
```

### 4.4 Important rule

Receiving one message in a conversation does not automatically subscribe the recipient to every future reply.

Every new message has its own recipient list.

This means:

```text
Conversation membership != Message delivery
```

That distinction is essential to a correct email implementation.

---

## 5. Conversation Threads

Related messages should be grouped into conversations/threads.

Instead of displaying:

```text
Project Alpha
RE: Project Alpha
RE: Project Alpha
RE: Project Alpha
```

show:

```text
Project Alpha                         4 messages

Alice
  Original message

John
  Reply

Mary
  Reply

Alice
  Latest reply
```

### 5.1 Thread versus message

A thread is a logical container.

A message is an individual email inside the thread.

Recommended conceptual relationship:

```text
Thread #100
│
├── Message #1001
├── Message #1002
├── Message #1003
└── Message #1004
```

Each message has its own:

- sender;
- recipients;
- body;
- attachments;
- sent time;
- headers/metadata.

Do not assume every participant in the thread received every message.

---

## 6. Archive

Archive means:

> Remove this mail/conversation from my Inbox while keeping it available for future use.

Archive does **not** mean Delete.

Example Inbox:

```text
Inbox
├── Project A Question
├── Meeting Tomorrow
├── Monthly Report
└── Server Maintenance
```

The user archives `Monthly Report`.

Afterward:

```text
Inbox
├── Project A Question
├── Meeting Tomorrow
└── Server Maintenance
```

The Monthly Report still exists.

It can be found through:

```text
All Mail
Search
Labels
Conversation history
```

### 6.1 Archive versus Delete

| Behavior | Archive | Delete |
|---|---|---|
| Remove from Inbox | Yes | Yes |
| Keep as normal retained mail | Yes | No |
| Available in All Mail | Yes | Normally not as normal mail |
| Move to Trash | No | Yes |
| Searchable as retained mail | Yes | Depends on Trash/search policy |
| Intended meaning | Finished for now | No longer wanted |

### 6.2 New reply to archived conversation

Suppose John archives a Project Alpha conversation.

Later Alice sends John a new message in the same conversation.

The conversation should normally return to John's Inbox because there is a new incoming message requiring attention.

Therefore:

```text
Archive != Permanently hide this thread
```

Archive means that the current mail no longer needs to remain in the Inbox.

---

## 7. Where Archived Mail Is Found

A Gmail-style design does not require a traditional Archive folder.

Recommended navigation:

```text
Inbox
Starred
Snoozed
Sent
Drafts
All Mail
Trash
```

When a user archives a message:

```text
Inbox
   │
   │ Archive
   ▼
Remove Inbox state
   │
   ├── Still in All Mail
   ├── Still searchable
   └── Still associated with labels
```

This is simpler than physically moving the underlying message between storage folders.

---

## 8. Labels

Labels provide flexible organization.

Example:

```text
Projects
├── Project Alpha
└── Project Beta

Departments
├── Finance
└── Engineering
```

One message/conversation can have several labels:

```text
Project Alpha
Important Customer
Finance
```

This is different from a strict folder model where one item normally has one physical location.

Labels should normally be user-specific unless they are explicitly defined as shared/enterprise labels.

---

## 9. Stars and Importance

A star is a user-specific marker.

Example:

```text
★ Project Alpha Report
```

Mary can star a message while John does not.

Therefore the star should not normally be stored as a global property of the message.

The same principle applies to many mailbox states:

- read/unread;
- starred;
- Inbox/archive state;
- labels;
- snooze state;
- deleted/Trash state.

---

## 10. User-Specific Mailbox State

This is one of the most important architectural concepts.

Suppose one message is delivered to John, Mary, and David.

Later:

```text
John  → Read + Archived + Starred
Mary  → Unread + Inbox
David → Read + Trash
```

The underlying message is the same, but each user has different mailbox state.

Therefore, do not model the message like this:

```text
mail_messages.folder = 'inbox'
mail_messages.is_read = 1
```

Those values cannot correctly represent multiple users.

Instead:

```text
                  Message #1001
                       │
          ┌────────────┼────────────┐
          ▼            ▼            ▼
        John          Mary         David
       archived       inbox        trash
       read           unread       read
       starred        normal       normal
```

---

## 11. Recommended Database Model

The exact production schema will depend on retention, scale, search, attachment storage, and thread rules, but the following is a useful starting point.

### 11.1 mail_threads

```sql
CREATE TABLE mail_threads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject VARCHAR(998) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
```

The thread represents a conversation.

### 11.2 mail_messages

```sql
CREATE TABLE mail_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(998) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_thread_id (thread_id),
    KEY idx_sender_id (sender_id),
    KEY idx_sent_at (sent_at)
);
```

### 11.3 mail_recipients

```sql
CREATE TABLE mail_recipients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    recipient_type ENUM('TO', 'CC', 'BCC') NOT NULL,
    PRIMARY KEY (id),
    KEY idx_message_id (message_id),
    KEY idx_user_id (user_id),
    KEY idx_message_user (message_id, user_id)
);
```

Example:

```text
message_id | user_id | recipient_type
-----------+---------+---------------
1001       | 101     | TO
1001       | 102     | CC
1001       | 103     | BCC
```

### 11.4 mail_user_messages

This table stores per-user mailbox state.

```sql
CREATE TABLE mail_user_messages (
    user_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    is_inbox TINYINT(1) NOT NULL DEFAULT 1,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    is_important TINYINT(1) NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    is_trashed TINYINT(1) NOT NULL DEFAULT 0,
    snoozed_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, message_id),
    KEY idx_user_inbox (user_id, is_inbox),
    KEY idx_user_unread (user_id, is_read),
    KEY idx_user_starred (user_id, is_starred),
    KEY idx_user_trashed (user_id, is_trashed),
    KEY idx_snoozed_until (snoozed_until)
);
```

The production design may choose state flags, mailbox labels, or a more normalized state model. The key requirement is that mailbox state belongs to the user, not globally to the message.

### 11.5 mail_attachments

```sql
CREATE TABLE mail_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(1000) NOT NULL,
    mime_type VARCHAR(255) NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_message_id (message_id)
);
```

For enterprise deployment, attachment records should also support security scanning status, hashes, classification, and retention metadata as needed.

---

## 12. Archive Implementation

Do not physically move the underlying message file/data when a user clicks Archive.

Conceptually:

```text
Before Archive
--------------
user_id      = 101
message_id   = 1001
is_inbox     = 1
is_archived  = 0

After Archive
-------------
user_id      = 101
message_id   = 1001
is_inbox     = 0
is_archived  = 1
```

The underlying `mail_messages` row remains unchanged.

Another recipient's mailbox state remains unchanged.

Example:

```text
Message #1001

John:
    is_inbox = 0
    is_archived = 1

Mary:
    is_inbox = 1
    is_archived = 0
```

---

## 13. All Mail Query Concept

A simplified All Mail query might retrieve user-visible messages that are not in Trash.

Conceptually:

```sql
SELECT m.*
FROM mail_messages m
JOIN mail_user_messages um
    ON um.message_id = m.id
WHERE um.user_id = :user_id
  AND um.is_trashed = 0
ORDER BY m.sent_at DESC;
```

Inbox adds the Inbox-state condition:

```sql
AND um.is_inbox = 1
```

Archive is therefore largely a mailbox-state change rather than a storage move.

---

## 14. Delete and Trash

Delete should generally mean moving the user's message state to Trash first.

Example:

```text
Before Delete
is_trashed = 0

After Delete
is_inbox   = 0
is_trashed = 1
```

The message should not necessarily be physically deleted from shared storage immediately because:

1. other recipients may still require it;
2. corporate retention policy may require it;
3. audit/compliance requirements may prohibit immediate destruction;
4. recovery may be supported for a limited period.

Therefore distinguish:

```text
User removes mail from mailbox
        !=
Physical destruction of enterprise mail record
```

---

## 15. Drafts

Drafts are different from delivered messages.

Recommended behavior:

- create a draft automatically after the user starts composing;
- autosave periodically and on important UI events;
- allow reopening and editing;
- support To/Cc/Bcc before sending;
- support draft attachments;
- convert/finalize the draft into a sent message during send processing;
- prevent accidental duplicate sending.

For a multi-tab application, draft editing should also consider concurrency/version checks so two tabs do not silently overwrite each other.

---

## 16. Sending Flow

A simplified sending flow is:

```text
User clicks Send
      │
      ▼
Validate request
      │
      ├── Sender authorized?
      ├── At least one recipient?
      ├── Recipients valid?
      ├── Attachment policy valid?
      └── Message size allowed?
      │
      ▼
Create/finalize message
      │
      ▼
Create recipient records
      │
      ▼
Create per-user mailbox state
      │
      ├── Recipient → Inbox
      └── Sender → Sent
      │
      ▼
Generate event/outbox record
      │
      ▼
Notification processing
      │
      ▼
Return success
```

For reliability, database changes that logically belong to one send operation should be handled transactionally where appropriate.

---

## 17. Search

A Gmail-like service should provide both simple and advanced search.

Examples:

```text
project alpha
from:john
from:john has:attachment
is:unread
is:starred
subject:report
after:2026/01/01
before:2026/10/01
```

Potential search fields include:

- sender;
- recipient;
- To/Cc;
- subject;
- body;
- attachment filename;
- date range;
- read/unread;
- starred;
- label;
- attachment presence;
- thread.

Search results must always apply authorization and Bcc-visibility rules.

---

## 18. Filters / Rules

A later version can support user-defined automatic processing.

Example:

```text
IF
    sender = manager
THEN
    mark important
    add label "Management"
```

Another example:

```text
IF
    subject contains "Weekly Report"
THEN
    add label "Reports"
    archive
```

Possible conditions:

- sender;
- recipient;
- subject;
- body contains words;
- attachment exists;
- attachment type;
- sender department;
- priority/classification.

Possible actions:

- add label;
- mark read;
- mark important;
- star;
- archive;
- notify;
- forward, subject to company policy.

---

## 19. Snooze

Snooze temporarily removes a message/conversation from the active Inbox.

Example:

```text
Snooze until tomorrow 09:00
```

Conceptually:

```text
is_inbox = 0
snoozed_until = 2026-10-07 09:00:00
```

A background worker later restores the Inbox state when the snooze time is reached.

This can integrate naturally with the company's notification/reminder infrastructure.

---

## 20. Scheduled Send

Scheduled Send allows a message to be prepared now and delivered later.

```text
Compose
   ↓
Schedule Send
   ↓
Pending scheduled message
   ↓
Background worker
   ↓
Delivery at requested time
```

The system should handle cancellation, rescheduling, duplicate prevention, server restart recovery, and audit logging.

---

## 21. Undo Send

Undo Send can be implemented by delaying final delivery for a short configurable interval.

```text
Click Send
   ↓
Pending-send state
   ↓
Undo window
   ├── Undo → Return to draft
   └── Expire → Finalize delivery
```

Do not claim that a fully delivered message can always be recalled. True recall is a different enterprise feature and has different semantics.

---

## 22. Employee Directory Integration

For an internal corporate system, recipients should integrate with the employee directory.

When the user enters:

```text
To: jo
```

suggest:

```text
John Smith — Engineering
John Williams — Finance
Joseph Brown — Security
```

Useful directory information may include:

- display name;
- employee identifier;
- department;
- title;
- internal email address;
- active/inactive status.

The system should avoid sending to inactive or unauthorized accounts according to company policy.

---

## 23. Distribution Lists

Support group addresses such as:

```text
engineering@company
security-team@company
management@company
```

The system should define whether membership is expanded at send time and retain enough delivery/audit information to determine who actually received a message.

Dynamic organizational groups may also be useful:

```text
Company
├── Division A
│   ├── Department A1
│   └── Department A2
└── Division B
```

---

## 24. Attachments

Attachment handling should include:

- upload;
- download according to permission;
- preview where supported;
- filename and size display;
- MIME/type validation;
- extension policy;
- malware/security scanning where available;
- hashing;
- maximum message/file size;
- audit logging;
- retention policy.

A recommended flow is:

```text
Upload
  ↓
Temporary storage
  ↓
Validate size/type
  ↓
Security scan
  ↓
Store securely
  ↓
Create attachment metadata
  ↓
Associate with message/draft
```

---

## 25. Notifications

Mail delivery can publish an event rather than tightly coupling Email to every notification channel.

```text
Email Service
      │
      ▼
Event / Outbox
      │
      ▼
Notification Service
      │
      ├── Web notification
      ├── Desktop notification
      └── Notification Center
```

This permits the same enterprise notification platform to support Email, CRM, Blog, Projects, HR, Workflow, and other systems.

---

## 26. Security Requirements

An enterprise internal mail service should include at least:

1. authenticated access;
2. server-side authorization;
3. Bcc privacy enforcement;
4. CSRF protection where applicable;
5. output encoding/XSS protection for message rendering;
6. safe HTML-email sanitization;
7. attachment validation;
8. malware scanning where available;
9. audit logging;
10. retention controls;
11. account disable/termination handling;
12. administrator-access auditing;
13. rate/abuse controls;
14. secure session/token management;
15. backup and disaster recovery.

Never trust HTML email directly. Sanitize dangerous tags, attributes, URLs, scripts, embedded active content, and other unsafe constructs according to the chosen rendering model.

---

## 27. Audit Logging

Important actions should be auditable, particularly in a corporate environment.

Examples:

```text
MESSAGE_SENT
MESSAGE_VIEWED        (if policy requires)
MESSAGE_ARCHIVED
MESSAGE_TRASHED
MESSAGE_RESTORED
ATTACHMENT_DOWNLOADED
MAILBOX_DELEGATED
ADMIN_MAIL_ACCESS
RETENTION_ACTION
```

Audit logs should be protected from normal-user modification.

---

## 28. Recommended Version Plan

### Version 1 — Essential Mail

Implement:

- Inbox;
- Sent;
- Drafts;
- Trash;
- All Mail;
- Compose;
- To/Cc/Bcc;
- Reply;
- Reply All;
- Forward;
- conversation threads;
- attachments;
- read/unread;
- stars;
- archive;
- basic search;
- employee autocomplete;
- pagination;
- notification event;
- audit logging;
- administrator basics.

### Version 2 — Gmail-Like Productivity

Add:

- labels;
- nested labels;
- advanced search;
- filters/rules;
- snooze;
- scheduled send;
- Undo Send;
- signatures;
- automatic replies;
- templates;
- mute conversation;
- distribution lists.

### Version 3 — Enterprise Features

Add:

- shared mailboxes;
- delegated access;
- mailbox quotas;
- retention policies;
- legal/compliance controls as required;
- attachment security policies;
- security classification;
- advanced auditing;
- administrative search/access under controlled policy;
- archiving/records integration.

### Version 4 — Enterprise Integration

Integrate with:

```text
Employee Directory
Calendar
Tasks
Documents
Workflow
Notification Center
Projects
CRM
```

### Version 5 — Intelligent Mail

If an approved internal AI platform is available, consider:

- thread summaries;
- draft reply suggestions;
- writing assistance;
- translation;
- action-item extraction;
- decision extraction;
- semantic search;
- message classification;
- urgency detection;
- unanswered-mail detection;
- internal knowledge integration.

All AI processing should respect mail permissions and company security policy.

---

## 29. Core Design Principles

### Principle 1 — Message data and mailbox state are different

```text
Message
    = shared mail content/record

Mailbox state
    = what a particular user has done with that message
```

### Principle 2 — Thread and delivery are different

A thread groups related messages, but every message has its own recipient list.

### Principle 3 — Archive is not Delete

```text
Archive → remove from Inbox, retain mail
Delete  → move user's mail state to Trash
```

### Principle 4 — Bcc is a server-side privacy requirement

Never depend on the browser to hide confidential Bcc information.

### Principle 5 — One user's action must not modify another user's mailbox state

```text
John archives message
        ↓
Only John's Inbox state changes

Mary's mailbox remains unchanged
```

### Principle 6 — Physical deletion is an enterprise policy decision

A user's Delete action should not automatically destroy a shared enterprise record.

### Principle 7 — Design for scale early

With approximately 10,000 employees, the system can eventually contain tens or hundreds of millions of recipient-state rows and messages. Index design, pagination, search architecture, retention, attachment storage, background jobs, and audit volume must therefore be considered from the beginning.

---

## 30. Recommended High-Level Architecture

```text
                    ┌──────────────────────┐
                    │    Web Mail Client   │
                    └──────────┬───────────┘
                               │
                          Mail REST API
                               │
                    ┌──────────▼───────────┐
                    │     Mail Service     │
                    │                      │
                    │ Compose / Send       │
                    │ Reply / Forward      │
                    │ Threads              │
                    │ Mailbox State        │
                    │ Labels / Search      │
                    │ Security             │
                    └──────────┬───────────┘
                               │
             ┌─────────────────┼──────────────────┐
             ▼                 ▼                  ▼
       Mail Database     Attachment Store    Search Service
             │
             ▼
        Event / Outbox
             │
       ┌─────┼───────────┐
       ▼     ▼           ▼
 Notification       Audit Service      Other Systems
```

If desktop mail clients must be supported, SMTP/IMAP compatibility can be added as an integration boundary without making those protocols the center of the business-domain design.

---

## 31. Summary

A Gmail-like internal mail service should be treated as a complete enterprise communication platform rather than only a table containing sender, receiver, subject, and body.

The most important concepts are:

- To identifies primary recipients;
- Cc identifies visible informational recipients;
- Bcc identifies hidden recipients;
- all recipient types can reply;
- Reply and Reply All determine who receives subsequent messages;
- Cc/Bcc recipients do not automatically receive every future reply;
- threads group related messages but do not define delivery;
- Archive removes mail from Inbox without deleting it;
- archived mail remains accessible through All Mail, search, and labels;
- a new incoming reply can return an archived conversation to Inbox;
- mailbox state must be stored per user;
- Bcc privacy must be enforced by the server;
- enterprise deletion, retention, security, attachment handling, and auditing must be designed deliberately.

Getting these concepts correct in Version 1 provides a strong foundation for later Gmail-like productivity features, enterprise integrations, and internal AI capabilities.
