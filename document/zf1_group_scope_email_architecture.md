# ZF1 Group Scope and Email Communication Architecture

## 1. Purpose

This document consolidates the design discussed for adding
**communication/data-isolation groups** to an existing **Zend Framework
1 + PHP 7.4 + MariaDB + DHTMLX 3.5** application.

The group feature does **not** replace roles or permissions:

``` text
Permission / Role = WHAT can this user do?
Group / Scope     = WITH WHOM / WHICH DATA can this user do it?
```

Authorization should follow:

``` text
Authentication
      ↓
Module Permission
      ↓
Group / Resource Scope
      ↓
Business Rule
      ↓
ALLOW / DENY
```

## 2. Core Group Model

Users and groups have a many-to-many relationship. A user can belong to
several groups, and each group can contain many users.

``` text
Group A: A1, A2, A3, X
Group B: B1, B2, X
```

For new communication/resources, the user explicitly selects which of
their groups is in scope:

``` text
☑ Group A
☐ Group B
☑ Group C
```

The server must enforce:

``` text
Selected Groups ⊆ User's Active Groups
```

Never trust group IDs supplied by DHTMLX/JavaScript without server-side
validation.

Potential group types are `NORMAL`, `DEPARTMENT`, `PROJECT`, `TEAM`,
`TEMPORARY`, and `SYSTEM`. Version 1 can begin with `NORMAL`.

Group membership roles are separate from site-wide roles:

``` text
OWNER
MANAGER
MEMBER
```

An application permission and group role can work together:

``` text
group.manage_members
        +
OWNER/MANAGER of Group A
        ↓
May manage Group A members
```

## 3. Company Isolation

Groups should belong to a company.

``` sql
CREATE TABLE user_group (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    description     VARCHAR(500) NULL,
    group_type      VARCHAR(30) NOT NULL DEFAULT 'NORMAL',
    status          VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL,
    updated_by      BIGINT UNSIGNED NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_group_company_status (company_id, status),
    KEY idx_group_type (group_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

The normal rule is:

``` text
User company == Group company
```

Cross-company groups should be an explicit future feature, not an
accidental consequence of missing checks.

For authorization, obtain `user_id` and `company_id` from the
authenticated server identity:

``` php
$identity = Zend_Auth::getInstance()->getIdentity();

$currentUserId = (int) $identity->id;
$companyId     = (int) $identity->company_id;
```

Do not trust a browser-submitted `company_id`.

## 4. Membership Table

``` sql
CREATE TABLE user_group_member (
    group_id        BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    member_role     VARCHAR(20) NOT NULL DEFAULT 'MEMBER',
    status          VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    joined_at       DATETIME NOT NULL,
    expires_at      DATETIME NULL,
    added_by        BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, user_id),
    KEY idx_user_status (user_id, status),
    KEY idx_group_status (group_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A membership grants current scope only when the membership and group are
active and the membership has not expired.

## 5. Membership History

``` sql
CREATE TABLE user_group_member_history (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id        BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    action          VARCHAR(30) NOT NULL,
    old_role        VARCHAR(20) NULL,
    new_role        VARCHAR(20) NULL,
    performed_by    BIGINT UNSIGNED NOT NULL,
    performed_at    DATETIME NOT NULL,
    note            VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_group_user (group_id, user_id),
    KEY idx_user (user_id),
    KEY idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Useful actions include `MEMBER_ADDED`, `MEMBER_REMOVED`,
`MEMBER_REACTIVATED`, `MEMBER_ACTIVATED`, `MEMBER_DEACTIVATED`,
`ROLE_CHANGED`, and `EXPIRATION_CHANGED`.

## 6. Group Lifecycle

Prefer `ACTIVE`, `INACTIVE`, and `ARCHIVED` statuses instead of
destructive deletion. An archived group should no longer grant current
access or be selectable for new communication, while historical records
remain.

For documents, the initial recommended policy is:

``` text
Archived/removed membership → current document access revoked
```

Email is intentionally different because it represents historical
delivery.

## 7. GroupScope Service

`S3_Service_GroupScope` answers scope/access questions and does not
modify membership.

``` text
library/S3/Service/GroupScope.php
```

``` php
class S3_Service_GroupScope
{
    protected $db;

    public function __construct(
        Zend_Db_Adapter_Abstract $db = null
    ) {
        if ($db === null) {
            $db = Zend_Db_Table::getDefaultAdapter();
        }

        $this->db = $db;
    }
}
```

Injecting the adapter makes tests easier.

Suggested exceptions:

``` text
S3_Exception_GroupScope
S3_Exception_InvalidGroup
S3_Exception_InvalidRecipient
```

Example base exception:

``` php
class S3_Exception_GroupScope extends Exception
{
}
```

### Loading active groups

``` php
public function getUserGroups($userId, $companyId)
{
    $sql = "
        SELECT g.id, g.name, g.group_type, m.member_role
        FROM user_group_member m
        INNER JOIN user_group g ON g.id = m.group_id
        WHERE m.user_id = ?
          AND g.company_id = ?
          AND m.status = 'ACTIVE'
          AND g.status = 'ACTIVE'
          AND (m.expires_at IS NULL OR m.expires_at > NOW())
        ORDER BY g.name
    ";

    return $this->db->fetchAll(
        $sql,
        array((int) $userId, (int) $companyId)
    );
}
```

### Selected-group validation

Use one query rather than one query per submitted group. Normalize
legitimate duplicates, but malformed API values should be rejected
rather than silently discarded.

Conceptually:

``` text
Requested: 10, 20, 30
Allowed:   10, 20, 30
→ ALLOW

Requested: 10, 20, 999
Allowed:   10, 20
→ DENY
```

## 8. Recipient Scope

The central recipient rule is:

``` text
Recipient ∈ Selected Groups
```

It is **not** sufficient that sender and recipient share some unrelated
group.

Example:

``` text
A1: Group A + Group B
X:  Group A + Group C

A1 selects Group B only.
A1 tries to send to X.
→ DENY
```

Recipient validation should be performed in SQL using the selected group
IDs and requested recipient IDs, while requiring active/non-expired
memberships and company isolation.

## 9. GroupManager Service

`S3_Service_GroupManager` changes group state, while `GroupScope` checks
relationships.

``` text
GroupScope
    getUserGroups()
    validateSelectedGroups()
    getReachableUsers()
    validateRecipients()

GroupManager
    createGroup()
    updateGroup()
    archiveGroup()
    addMember()
    removeMember()
    changeMemberRole()
    getMembers()
```

Suggested constants:

``` php
const ROLE_OWNER   = 'OWNER';
const ROLE_MANAGER = 'MANAGER';
const ROLE_MEMBER  = 'MEMBER';

const STATUS_ACTIVE   = 'ACTIVE';
const STATUS_INACTIVE = 'INACTIVE';
const STATUS_ARCHIVED = 'ARCHIVED';
```

Creating a group should be transactional:

``` text
BEGIN
  Create group
  Create creator as OWNER
  Write history
COMMIT
```

Adding a member should distinguish:

``` text
No existing membership → INSERT
Existing INACTIVE      → REACTIVATE
Existing ACTIVE        → reject duplicate
```

Prefer deactivating membership rather than physically deleting it.

### Last-owner protection

A group must not accidentally have zero owners. Removing or demoting the
last active owner must fail. Promote another member to OWNER first.

The owner count/check and modification should occur inside the same
transaction; stricter production code should lock relevant membership
rows so concurrent requests cannot both remove the final owners.

## 10. Group Administration API

Suggested endpoints:

``` text
GET    /api/groups
POST   /api/groups
GET    /api/groups/:group_id
PUT    /api/groups/:group_id

GET    /api/groups/:group_id/members
POST   /api/groups/:group_id/members
PUT    /api/groups/:group_id/members/:user_id
DELETE /api/groups/:group_id/members/:user_id

POST   /api/groups/:group_id/archive
```

ZF1 controllers should remain thin:

``` text
HTTP Request
    ↓
Authentication
    ↓
Permission
    ↓
Controller
    ↓
GroupManager / GroupScope
    ↓
MariaDB
```

The controller handles HTTP, authentication, permission checks, request
parsing, and response formatting. Services contain business rules and
SQL-related operations.

For old ZF1 routing, one action may dispatch based on `GET`, `POST`,
`PUT`, or `DELETE`.

Session/cookie-authenticated state-changing requests (`POST`, `PUT`,
`PATCH`, `DELETE`) should use the existing centralized CSRF protection,
preferably via `X-CSRF-Token`.

## 11. DHTMLX Group Management

A practical two-panel screen:

``` text
┌────────────────────────────────────────────────────────┐
│ Group Management                                       │
├─────────────────────┬──────────────────────────────────┤
│ Groups              │ Members                          │
│ Group A             │ A1       OWNER                   │
│ Group B             │ A2       MANAGER                 │
│ Group C             │ A3       MEMBER                  │
│                     │ X        MEMBER                  │
├─────────────────────┼──────────────────────────────────┤
│ New/Edit/Archive    │ Add/Change Role/Remove           │
└─────────────────────┴──────────────────────────────────┘
```

The guiding rule is:

``` text
DHTMLX filtering = user experience
ZF1 validation   = security
```

Hiding or disabling a button never replaces server-side authorization.

# Email Architecture

## 12. Email Communication Flow

``` text
Compose Email
     ↓
Load sender's active groups
     ↓
Select groups
     ↓
Server returns recipients reachable through selected groups
     ↓
Select recipients
     ↓
Subject / Body
     ↓
SEND
     ↓
Server revalidates permission + groups + recipients + content
     ↓
Transaction
```

The two essential checks are:

``` text
Selected Groups ⊆ Sender's Current Groups
Recipients      ⊆ Members of Selected Groups
```

## 13. Email Message

``` sql
CREATE TABLE email_message (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id          BIGINT UNSIGNED NOT NULL,
    sender_user_id      BIGINT UNSIGNED NOT NULL,
    parent_email_id     BIGINT UNSIGNED NULL,
    thread_id           BIGINT UNSIGNED NULL,
    subject             VARCHAR(255) NOT NULL,
    body                MEDIUMTEXT NOT NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'SENT',
    created_at          DATETIME NOT NULL,
    sent_at             DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sender (sender_user_id, sent_at),
    KEY idx_company (company_id, sent_at),
    KEY idx_thread (thread_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Possible statuses include `DRAFT`, `SENT`, and optionally `CANCELLED`.

Sent message content should normally be immutable.

## 14. Selected Group Snapshot

``` sql
CREATE TABLE email_message_group (
    email_id        BIGINT UNSIGNED NOT NULL,
    group_id        BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (email_id, group_id),
    KEY idx_group_email (group_id, email_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

This preserves which groups the sender selected at send time.

## 15. Actual Recipients

``` sql
CREATE TABLE email_recipient (
    email_id        BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    recipient_type  VARCHAR(10) NOT NULL,
    delivered_at    DATETIME NOT NULL,
    PRIMARY KEY (email_id, user_id),
    KEY idx_user_email (user_id, email_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Recipient types are `TO`, `CC`, and `BCC`.

Using `(email_id, user_id)` as the primary key prevents one user from
being stored simultaneously as multiple recipient types. Normalize
duplicates with precedence such as:

``` text
TO > CC > BCC
```

## 16. Why Groups and Recipients Are Both Stored

For Email #500:

``` text
Selected scope:
    Group A
    Group C

Actually delivered to:
    A2
    A3
    C1
```

`email_message_group` records the selected communication scope.
`email_recipient` records actual historical delivery. Both are useful
after memberships change.

## 17. Per-User Mailbox State

Use a separate mailbox table:

``` sql
CREATE TABLE email_mailbox (
    email_id        BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    mailbox_type    VARCHAR(20) NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    read_at         DATETIME NULL,
    is_starred      TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at      DATETIME NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (email_id, user_id, mailbox_type),
    KEY idx_user_mailbox (
        user_id,
        mailbox_type,
        deleted_at,
        email_id
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Initial mailbox types:

``` text
INBOX
SENT
DRAFT
```

Trash can initially be represented by `deleted_at`.

Example delivery:

``` text
email_message:
    #500 sender=A1

email_recipient:
    500 A2 TO
    500 A3 TO
    500 X  CC

email_mailbox:
    500 A1 SENT
    500 A2 INBOX
    500 A3 INBOX
    500 X  INBOX
```

There is one shared message but independent mailbox state for every
user.

## 18. Read, Star, Delete, and Trash

Read/unread and starred state are personal. If A2 reads or stars an
email, A3's state is unchanged.

Deleting an email means updating only that user's mailbox state:

``` text
A2 deletes #500
→ A2 mailbox deleted_at = NOW()

A1 SENT remains
A3 INBOX remains
X INBOX remains
```

Sender deletion of a Sent copy does **not** recall the email.

``` text
Delete ≠ Recall
```

Recall should be a separate future feature if required.

Trash can be implemented using `deleted_at`:

``` text
Inbox: deleted_at IS NULL
Trash: deleted_at IS NOT NULL
Restore: deleted_at = NULL
```

Permanent deletion of one user's mailbox entry must not delete the
underlying message while other mailbox references remain. Physical
cleanup should also honor the business retention policy.

## 19. Historical Email vs Current Membership

This is one of the most important rules.

``` text
Monday:
A2 belongs to Group A.
A1 sends #500 to A2 through Group A.

Tuesday:
A2 is removed from Group A.
```

A2 normally keeps read access to #500 because it was legitimately
delivered.

Inbox/read access should therefore be based on `email_mailbox`, not
current `user_group_member`.

However, historical receipt does not grant future communication rights.

``` text
A2 presses Reply
      ↓
Current group scope is checked
      ↓
Allowed now?
  YES → send
  NO  → reject
```

**Historical access does not automatically grant current communication
permission.**

## 20. Inbox and Sent Queries

Inbox:

``` sql
SELECT
    e.id,
    e.sender_user_id,
    e.subject,
    e.sent_at,
    mb.is_read,
    mb.read_at,
    mb.is_starred
FROM email_mailbox mb
INNER JOIN email_message e
    ON e.id = mb.email_id
WHERE mb.user_id = :currentUserId
  AND mb.mailbox_type = 'INBOX'
  AND mb.deleted_at IS NULL
ORDER BY e.sent_at DESC;
```

Sent:

``` sql
SELECT
    e.id,
    e.subject,
    e.sent_at
FROM email_mailbox mb
INNER JOIN email_message e
    ON e.id = mb.email_id
WHERE mb.user_id = :currentUserId
  AND mb.mailbox_type = 'SENT'
  AND mb.deleted_at IS NULL
ORDER BY e.sent_at DESC;
```

## 21. Email Read Authorization

Never authorize reading merely because an email ID exists. A manipulated
`/api/email/501` must not reveal someone else's email.

Use the authenticated user:

``` sql
SELECT e.*
FROM email_mailbox mb
INNER JOIN email_message e
    ON e.id = mb.email_id
WHERE mb.email_id = :emailId
  AND mb.user_id = :currentUserId
  AND mb.deleted_at IS NULL
LIMIT 1;
```

`currentUserId` comes from `Zend_Auth`, not the request body.

## 22. BCC Privacy

For:

``` text
TO:  A2
CC:  A3
BCC: X
```

the sender may see all recipients. A2/A3 must not see X as BCC. A BCC
recipient may know that they themselves were BCC'd, but other BCC
recipients must remain hidden.

Do not return all `email_recipient` rows indiscriminately.

## 23. Recipient Lookup API

Recommended:

``` text
POST /api/email/recipients
```

Request:

``` json
{
    "group_ids": [10, 30]
}
```

Response:

``` json
{
    "success": true,
    "data": {
        "recipients": [
            {"id": 102, "name": "A2"},
            {"id": 103, "name": "A3"},
            {"id": 301, "name": "X"}
        ]
    }
}
```

Return only information required by the UI.

## 24. DHTMLX Compose Behavior

The UI can use a checkbox group grid and recipient grid:

``` text
Groups
☑ Group A
☐ Group B
☑ Group C

Recipients
☑ A2
☑ A3
☐ A4
☑ X
```

When group selection changes:

``` text
Remember selected recipients
        ↓
Reload valid recipients
        ↓
Keep selections still valid
        ↓
Remove selections no longer valid
```

The server must still validate everything when Send is pressed.

## 25. Send Payload

Initial version:

``` json
{
    "group_ids": [10, 30],
    "recipient_ids": [102, 103, 301],
    "subject": "Monthly Report",
    "body": "<p>...</p>"
}
```

Later TO/CC/BCC form:

``` json
{
    "group_ids": [10, 30],
    "recipients": {
        "to": [102, 103],
        "cc": [301],
        "bcc": [401]
    },
    "subject": "Monthly Report",
    "body": "<p>...</p>"
}
```

Do not trust submitted `sender_user_id` or `company_id`.

## 26. Transactional Send

``` text
BEGIN
  Validate current groups
  Validate current recipients
  Insert email_message
  Insert email_message_group
  Insert email_recipient
  Insert sender SENT mailbox
  Insert recipient INBOX mailboxes
  Write audit information
COMMIT
```

Any failure must roll back the complete send operation.

## 27. Email Service Structure

For gradual namespace adoption, new classes can live under:

``` text
application/modules/email/src/Service/
```

Recommended services:

``` text
S3\Email\Service\EmailSendService
S3\Email\Service\MailboxService
S3\Email\Service\DraftService
S3\Email\Service\RecipientService
S3\Email\Service\ThreadService
```

Responsibilities:

``` text
EmailSendService
    sending
    current group/recipient validation
    transactional delivery

MailboxService
    inbox
    sent
    read
    mark read
    star
    delete
    restore

DraftService
    create
    update
    delete
    send

RecipientService
    TO/CC/BCC normalization
    recipient visibility/BCC rules

ThreadService
    reply
    reply-all
    thread relationships
```

This prevents one oversized Email service.

## 28. Drafts

Drafts are editable; sent messages are immutable.

A draft can save proposed groups and recipients, but permission must be
revalidated at send time.

``` text
Monday: Draft uses Group A + Group B
Friday: Sender no longer belongs to Group B
Friday send attempt → reject/update invalid scope
```

Previously saving a draft does not permanently authorize future
delivery.

## 29. Reply, Reply All, Forward, Threads

Reply and Reply All must use **current** group scope, even if the
original email was valid historically.

Forward is a new send operation:

``` text
Old Email
    ↓
Forward
    ↓
Select current groups
    ↓
Select currently reachable recipients
    ↓
New Email
```

Threads can use:

``` text
parent_email_id
thread_id
```

so replies share one conversation thread.

## 30. HTML Email Security

If TinyMCE supplies HTML:

``` text
TinyMCE HTML
      ↓
Server-side HTML sanitization
      ↓
email_message.body
```

Client-generated HTML is untrusted input.

# Documents and Chat

## 31. Documents

Documents can use a dedicated relationship:

``` sql
CREATE TABLE document_group (
    document_id     BIGINT UNSIGNED NOT NULL,
    group_id        BIGINT UNSIGNED NOT NULL,
    shared_by       BIGINT UNSIGNED NOT NULL,
    shared_at       DATETIME NOT NULL,
    PRIMARY KEY (document_id, group_id),
    KEY idx_group_document (group_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Unlike Email, document access normally depends on **current**
membership. Leaving the group removes current document access.

## 32. Chat

Suggested tables:

``` text
chat_conversation
chat_participant
chat_conversation_group
chat_message
```

Groups used to establish a conversation can be recorded, but explicit
participants should be preserved for conversation history. Membership
changes should not silently rewrite historical participant lists.

## 33. Why Not One Universal Resource-Group Table?

Avoid forcing all features into:

``` text
resource_type
resource_id
group_id
```

because the semantics differ:

``` text
Email     → historical delivery matters
Documents → current membership matters
Chat      → explicit participants matter
```

Dedicated tables keep the business rules clear.

# API and Error Handling

## 34. JSON Responses

Success:

``` json
{
    "success": true,
    "data": {}
}
```

Error:

``` json
{
    "success": false,
    "error": {
        "code": "INVALID_RECIPIENT",
        "message": "One or more recipients are not allowed."
    }
}
```

Useful codes include:

``` text
AUTH_REQUIRED
ACCESS_DENIED
INVALID_GROUP_SCOPE
INVALID_RECIPIENT
LAST_OWNER_REQUIRED
INVALID_EMAIL_DATA
EMAIL_SEND_FAILED
```

Use appropriate HTTP statuses such as 200, 201, 400, 401, 403, 404, 405,
409, 422, and 500.

## 35. Global JavaScript Error Handling

``` text
jQuery AJAX ─────────┐
DHTMLX AJAX ─────────┼──► S3 Global Error Handler
DHTMLX DataProcessor ┘
                         ├── 401 authentication
                         ├── 403 authorization
                         ├── 500 system
                         └── module/page errors
```

Pages should not repeatedly implement
token-expiration/login/system-error handling.

# Testing

## 36. GroupScope Tests

Test:

``` text
single group
multiple groups
duplicate IDs
inactive group
inactive membership
expired membership
different-company group
valid recipient
recipient outside selected groups
recipient sharing only an unselected group
duplicate reachability removed
empty groups
empty recipients
manipulated group ID
manipulated recipient ID
```

Critical case:

``` text
A1: Group A + B
X:  Group A

A1 selects Group B.
A1 tries to send to X.
Expected: DENY
```

## 37. GroupManager Tests

Test group creation with initial owner, blank names,
add/reactivate/duplicate membership, invalid roles, cross-company
membership, role changes, last-owner removal/demotion, archive behavior,
history, transaction rollback, and concurrent owner changes.

## 38. Email Tests

Test valid delivery, invalid recipients, malformed IDs, one shared
message with multiple mailbox rows, independent read/star/delete state,
sender deletion not recalling mail, BCC privacy, draft revalidation,
reply/reply-all current-scope validation, forward as a new send, and
continued read access to historically delivered mail after group
removal.

# Recommended Implementation Order

``` text
1. user_group
2. user_group_member
3. user_group_member_history

4. S3_Service_GroupScope
5. S3_Service_GroupManager
6. Group tests

7. Group administration API
8. DHTMLX group-management UI

9. email_message
10. email_message_group
11. email_recipient
12. email_mailbox

13. Recipient lookup API
14. DHTMLX group selector
15. DHTMLX recipient selector
16. EmailSendService
17. Inbox / Sent / Read / Delete
18. Drafts

19. TO / CC / BCC
20. Reply / Reply All / Forward
21. Threads
22. Attachments
23. Search / folders / retention if required
```

# Final Architecture

``` text
                         AUTHENTICATED USER
                                │
                   ┌────────────┴────────────┐
                   ▼                         ▼
              PERMISSIONS                  GROUPS
             "what can I do?"          "with whom?"
                   │                         │
                   └────────────┬────────────┘
                                ▼
                           GROUP SCOPE
                                │
                 ┌──────────────┼──────────────┐
                 ▼              ▼              ▼
               EMAIL          DOCUMENT         CHAT
                 │              │              │
                 ▼              ▼              ▼
        historical delivery  current scope  participants
                 │
                 ▼
              MAILBOX
          ┌──────┼──────┐
          ▼      ▼      ▼
        INBOX   SENT   DRAFT
```

Administration:

``` text
Administrator / Authorized Group Manager
                  │
                  ▼
             GroupManager
                  │
         ┌────────┼────────┐
         ▼        ▼        ▼
       Groups  Members   History
```

The central design principles are:

> **Permissions determine what a user may do. Groups determine with whom
> or within which data scope the operation may occur.**

> **For Email, group membership determines who may be selected at send
> time, while recipient and mailbox records preserve historical
> delivery.**

> **Historical access does not automatically grant current communication
> permission.**

# Integration Checklist

Before copying the example SQL/PHP directly into production, adapt it to
the actual application:

-   actual user table and ID columns
-   company ID column
-   user display-name columns
-   existing permission service
-   existing Email tables
-   existing ZF1 base API controller
-   audit framework
-   CSRF plugin
-   routing conventions
-   table prefixes
-   foreign-key/deletion policy
-   actual DHTMLX 3.5 build behavior

Those details should be matched to the real project rather than guessed.
