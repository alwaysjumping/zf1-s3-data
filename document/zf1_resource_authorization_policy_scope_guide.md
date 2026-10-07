# ZF1 Resource Authorization Architecture

## RBAC Permissions, Resource Policies, Resource Scopes, and Model Integration

## 1. Purpose

This document consolidates the authorization design discussed for a Zend
Framework 1 (ZF1), PHP 7.4, MariaDB, and DHTMLX application.

The architecture separates four concepts:

``` text
Role
  |
  v
Permission
  |
  v
Resource Policy
  |
  v
Resource Scope
```

The core mental model is:

``` text
Permission = What kind of operation may the user perform?
Policy     = May the user perform it on THIS particular resource?
Scope      = Which resources may the user retrieve?
```

The goals are to centralize authorization, avoid scattered role checks,
prevent unauthorized records from reaching the browser, support DHTMLX
grid/list APIs, and allow different business resources to use their
natural authorization relationships.

------------------------------------------------------------------------

## 2. Authentication and Authorization

Authentication answers:

``` text
Who are you?
```

Authorization answers:

``` text
What are you allowed to do?
```

Resource authorization asks:

``` text
Are you allowed to perform THIS action
on THIS particular resource?
```

Recommended request flow:

``` text
REQUEST
   |
   v
Authentication
   |
   v
Session inactivity check
   |
   v
RBAC permission
   |
   v
Resource Policy / Scope
   |
   v
Business logic
   |
   v
Audit where appropriate
```

For the previously discussed two-hour inactivity design, authentication
and session validation happen before resource authorization.

------------------------------------------------------------------------

## 3. Why RBAC Alone Is Not Enough

A permission such as:

``` text
document.read
```

answers whether the user can read documents in general. It does not
necessarily answer whether the user can read Document #500.

For example:

``` text
Employee
    -> own documents

Department Manager
    -> department documents

Administrator
    -> all documents
```

Therefore:

``` text
RBAC Permission
      |
      v
General capability

Resource Policy
      |
      v
Single-resource decision

Resource Scope
      |
      v
List/query restriction
```

------------------------------------------------------------------------

## 4. Recommended RBAC Tables

### `roles`

``` sql
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Role ID',

    role_key VARCHAR(100) NOT NULL
        COMMENT 'Stable role identifier',

    name VARCHAR(150) NOT NULL
        COMMENT 'Display name',

    description VARCHAR(500) DEFAULT NULL
        COMMENT 'Role description',

    is_active TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Whether role can currently be used',

    PRIMARY KEY (id),

    UNIQUE KEY uk_role_key (
        role_key
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

### `permissions`

``` sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        COMMENT 'Permission ID',

    permission_key VARCHAR(150) NOT NULL
        COMMENT 'Stable permission identifier',

    module VARCHAR(50) NOT NULL
        COMMENT 'Owning business module',

    description VARCHAR(500) DEFAULT NULL
        COMMENT 'Human-readable description',

    PRIMARY KEY (id),

    UNIQUE KEY uk_permission_key (
        permission_key
    ),

    KEY idx_module (
        module
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

### `user_roles`

``` sql
CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (
        user_id,
        role_id
    ),

    KEY idx_role (
        role_id
    )
) ENGINE=InnoDB;
```

### `role_permissions`

``` sql
CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (
        role_id,
        permission_id
    ),

    KEY idx_permission (
        permission_id
    )
) ENGINE=InnoDB;
```

Relationship:

``` text
users
  |
  +---- user_roles ---- roles
                         |
                  role_permissions
                         |
                         v
                    permissions
```

------------------------------------------------------------------------

## 5. Permission Naming

Prefer capabilities rather than page or button names.

Good:

``` text
document.read
document.update
document.approve
```

Avoid:

``` text
document_page_1
document_edit_button
```

Useful conventions:

``` text
<module>.<action>
<module>.<resource>.<action>
```

Examples:

``` text
email.read
email.send
email.delete

blog.post.read
blog.post.create
blog.post.update
blog.post.publish

crm.customer.read
crm.customer.update

document.read
document.update
document.approve
document.download
```

For access levels:

``` text
crm.customer.read.own
crm.customer.read.team
crm.customer.read.region
crm.customer.read.all
```

Conceptually:

``` text
OWN < TEAM < REGION < ALL
```

Document permissions could similarly use:

``` text
document.read.own
document.read.department
document.read.all
```

------------------------------------------------------------------------

## 6. Central Authorization Service

Suggested structure:

``` text
library/
└── S3/
    └── Authorization/
        ├── Service.php
        ├── Exception.php
        └── Policy/
            ├── Interface.php
            ├── Document.php
            ├── Email.php
            ├── Product.php
            └── CrmCustomer.php
```

Typical calls:

``` php
$authorization->isAllowed(
    $userId,
    'document.read'
);
```

and:

``` php
$authorization->requirePermission(
    $userId,
    'document.read'
);
```

`isAllowed()` returns true/false. `requirePermission()` rejects the
request consistently when authorization is missing.

Effective permissions can be loaded with:

``` sql
SELECT DISTINCT
    p.permission_key
FROM user_roles ur
INNER JOIN role_permissions rp
    ON rp.role_id = ur.role_id
INNER JOIN permissions p
    ON p.id = rp.permission_id
INNER JOIN roles r
    ON r.id = ur.role_id
WHERE ur.user_id = ?
  AND r.is_active = 1;
```

Convert the result into a request-level lookup:

``` php
array(
    'email.read' => true,
    'email.send' => true,
    'document.read' => true,
    'document.approve' => true
);
```

Do not query MariaDB separately for every `isAllowed()` call. A
request-level PHP cache is sufficient initially; Redis is not required.

------------------------------------------------------------------------

## 7. HTTP Responses

Use HTTP 401 when authentication/session is invalid:

``` json
{
    "success": false,
    "code": "AUTH_REQUIRED",
    "message": "Authentication is required."
}
```

For inactivity:

``` json
{
    "success": false,
    "code": "SESSION_INACTIVE",
    "message": "Session expired due to inactivity."
}
```

Use HTTP 403 when the user is authenticated but lacks permission:

``` json
{
    "success": false,
    "code": "PERMISSION_DENIED",
    "message": "You do not have permission to perform this action."
}
```

Conceptually:

``` text
401 = Authentication/session is not accepted.
403 = User is known, but the operation is not allowed.
```

For especially sensitive resources, returning 404 rather than 403 can be
considered when revealing resource existence would itself be sensitive.

------------------------------------------------------------------------

# Resource Policy

## 8. What Is a Policy?

A Policy answers:

``` text
Can this user perform this operation
on this ONE particular resource?
```

Examples:

``` text
Can User 100 read Document #500?
Can User 100 update Customer #700?
Can User 100 read Email #900?
```

Policies are primarily PHP business-rule code. Generic policy database
tables are not required.

------------------------------------------------------------------------

## 9. Document Policy Example

Suppose a document naturally contains:

``` text
id
title
owner_user_id
department_id
status
```

A permission-based Policy can be:

``` php
public function canRead(
    array $user,
    array $document
) {
    $userId = $user['id'];

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.all'
    )) {
        return true;
    }

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.department'
    )) {
        return $document['department_id']
            == $user['department_id'];
    }

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.own'
    )) {
        return $document['owner_user_id']
            == $userId;
    }

    return false;
}
```

Different operations can have different rules. For example:

``` text
READ:
    department documents

UPDATE:
    own documents

APPROVE:
    department documents except own

DELETE:
    none
```

Approval example:

``` php
public function canApprove(
    array $user,
    array $document
) {
    if (!$this->authorization->isAllowed(
        $user['id'],
        'document.approve.department'
    )) {
        return false;
    }

    if ($document['department_id']
        != $user['department_id']) {
        return false;
    }

    if ($document['owner_user_id']
        == $user['id']) {
        return false;
    }

    if ($document['status']
        != 'submitted') {
        return false;
    }

    return true;
}
```

------------------------------------------------------------------------

# Resource Scope

## 10. What Is Scope?

Scope answers:

``` text
Which records may this user retrieve?
```

It is especially important for:

``` text
DHTMLX grids
lists
searches
pagination
counts
exports
reports
```

Do not fetch all rows and filter them afterward in PHP.

Bad:

``` php
$rows = $db->fetchAll(
    'SELECT * FROM customers'
);
```

Better:

``` sql
SELECT *
FROM customers
WHERE sales_team_id = ?;
```

Authorization becomes part of the SQL query.

------------------------------------------------------------------------

## 11. Policy vs. Scope

``` text
POLICY                           SCOPE

One resource                     Many resources

Can I read                       Which documents
Document #500?                   can I retrieve?

PHP decision                     SQL restriction
```

Example:

``` text
Employee:
    Policy -> owner == user
    Scope  -> WHERE owner_user_id = :user_id

Manager:
    Policy -> same department
    Scope  -> WHERE department_id = :department_id

Admin:
    Policy -> allow
    Scope  -> no extra restriction
```

Policy and Scope must represent the same authorization rules.

------------------------------------------------------------------------

## 12. Keep Scope in the Policy Initially

A separate Scope class is not necessary for the first implementation.

``` php
class S3_Authorization_Policy_Document
{
    public function canRead(
        array $user,
        array $document
    ) {
        // Single-resource authorization.
    }

    public function applyReadScope(
        Zend_Db_Select $select,
        array $user
    ) {
        // Query/list authorization.
    }
}
```

Example:

``` php
public function applyReadScope(
    Zend_Db_Select $select,
    array $user
) {
    $userId = $user['id'];

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.all'
    )) {
        return $select;
    }

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.department'
    )) {
        $select->where(
            'd.department_id = ?',
            $user['department_id']
        );

        return $select;
    }

    if ($this->authorization->isAllowed(
        $userId,
        'document.read.own'
    )) {
        $select->where(
            'd.owner_user_id = ?',
            $userId
        );

        return $select;
    }

    $select->where('1 = 0');

    return $select;
}
```

`1 = 0` provides a deny-by-default empty result.

------------------------------------------------------------------------

# Resource Table Design

## 13. Not Every Table Needs `owner_user_id` or `department_id`

Do not add these columns to every resource table just to support
authorization.

Use each resource's natural relationships.

  Resource               Natural authorization relationships
  ---------------------- --------------------------------------------
  Document               owner, department, sharing, classification
  Email                  sender, recipients, mailbox
  Product                often company-wide permission
  CRM Customer           assigned salesperson, team, region
  Blog                   author, editor, publication status
  Notification           recipient
  System Configuration   usually permission-only

The design question is:

``` text
What business relationship determines
who may access this resource?
```

The Policy adapts to the resource, not the resource to the Policy.

------------------------------------------------------------------------

# Common Policy Interface

## 14. Interface

``` php
<?php

interface S3_Authorization_Policy_Interface
{
    public function canRead(
        array $user,
        array $resource
    );

    public function applyReadScope(
        Zend_Db_Select $select,
        array $user
    );
}
```

Resource-specific methods such as `canSend()`, `canApprove()`, or
`canPublish()` can remain outside the common interface.

------------------------------------------------------------------------

# Email Example

## 15. Email Tables

``` sql
CREATE TABLE emails (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    KEY idx_sender (
        sender_user_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

``` sql
CREATE TABLE email_recipients (
    email_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    recipient_type VARCHAR(10) NOT NULL
        COMMENT 'to, cc, bcc',

    PRIMARY KEY (
        email_id,
        user_id,
        recipient_type
    ),

    KEY idx_user (
        user_id,
        email_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Permissions:

``` text
email.read
email.send
email.delete
email.admin.read
```

------------------------------------------------------------------------

## 16. Email Policy

``` php
<?php

class S3_Authorization_Policy_Email
    implements S3_Authorization_Policy_Interface
{
    protected $db;
    protected $authorization;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Authorization_Service $authorization
    ) {
        $this->db = $db;
        $this->authorization = $authorization;
    }

    public function canRead(
        array $user,
        array $email
    ) {
        $userId = (int) $user['id'];

        if (!$this->authorization->isAllowed(
            $userId,
            'email.read'
        )) {
            return false;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'email.admin.read'
        )) {
            return true;
        }

        if ((int) $email['sender_user_id']
            === $userId) {
            return true;
        }

        $select = $this->db->select()
            ->from(
                'email_recipients',
                array('email_id')
            )
            ->where(
                'email_id = ?',
                $email['id']
            )
            ->where(
                'user_id = ?',
                $userId
            )
            ->limit(1);

        $result = $this->db->fetchOne($select);

        return $result !== false;
    }

    public function applyReadScope(
        Zend_Db_Select $select,
        array $user
    ) {
        $userId = (int) $user['id'];

        if (!$this->authorization->isAllowed(
            $userId,
            'email.read'
        )) {
            $select->where('1 = 0');
            return $select;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'email.admin.read'
        )) {
            return $select;
        }

        $condition = $this->db->quoteInto(
            'e.sender_user_id = ?',
            $userId
        );

        $condition .=
            ' OR EXISTS (
                SELECT 1
                FROM email_recipients er
                WHERE er.email_id = e.id
                  AND er.user_id = ' .
            $this->db->quote($userId) .
            '
            )';

        $select->where(
            '(' . $condition . ')'
        );

        return $select;
    }
}
```

Effective normal-user query:

``` sql
SELECT e.*
FROM emails e
WHERE
    e.sender_user_id = 100
    OR EXISTS (
        SELECT 1
        FROM email_recipients er
        WHERE er.email_id = e.id
          AND er.user_id = 100
    )
ORDER BY e.id DESC;
```

------------------------------------------------------------------------

# Product Example

## 17. Product Table

``` sql
CREATE TABLE products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uk_product_code (
        product_code
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Permissions:

``` text
product.read
product.create
product.update
product.delete
```

------------------------------------------------------------------------

## 18. Product Policy

``` php
<?php

class S3_Authorization_Policy_Product
    implements S3_Authorization_Policy_Interface
{
    protected $authorization;

    public function __construct(
        S3_Authorization_Service $authorization
    ) {
        $this->authorization = $authorization;
    }

    public function canRead(
        array $user,
        array $product
    ) {
        return $this->authorization->isAllowed(
            $user['id'],
            'product.read'
        );
    }

    public function canCreate(array $user)
    {
        return $this->authorization->isAllowed(
            $user['id'],
            'product.create'
        );
    }

    public function canUpdate(
        array $user,
        array $product
    ) {
        return $this->authorization->isAllowed(
            $user['id'],
            'product.update'
        );
    }

    public function canDelete(
        array $user,
        array $product
    ) {
        return $this->authorization->isAllowed(
            $user['id'],
            'product.delete'
        );
    }

    public function applyReadScope(
        Zend_Db_Select $select,
        array $user
    ) {
        if (!$this->authorization->isAllowed(
            $user['id'],
            'product.read'
        )) {
            $select->where('1 = 0');
        }

        return $select;
    }
}
```

Products demonstrate that a Policy does not have to contain complicated
row-level restrictions. An authorized user may simply see all products.

------------------------------------------------------------------------

# CRM Example

## 19. CRM Rules

Example:

``` text
Salesperson
    -> assigned customers

Sales Manager
    -> customers in their team

Regional Manager
    -> customers in their region

CRM Administrator
    -> all customers
```

### Customer table

``` sql
CREATE TABLE crm_customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    company_name VARCHAR(255) NOT NULL,

    assigned_user_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Salesperson responsible for customer',

    sales_team_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Sales team responsible for customer',

    region_id BIGINT UNSIGNED DEFAULT NULL
        COMMENT 'Customer sales region',

    status VARCHAR(20) NOT NULL,

    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    KEY idx_assigned_user (
        assigned_user_id
    ),

    KEY idx_sales_team (
        sales_team_id
    ),

    KEY idx_region (
        region_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Permissions:

``` text
crm.customer.read.own
crm.customer.read.team
crm.customer.read.region
crm.customer.read.all

crm.customer.update.own
crm.customer.update.team
crm.customer.update.all

crm.customer.create
crm.customer.delete
```

------------------------------------------------------------------------

## 20. CRM Customer Policy

``` php
<?php

class S3_Authorization_Policy_CrmCustomer
    implements S3_Authorization_Policy_Interface
{
    protected $db;
    protected $authorization;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Authorization_Service $authorization
    ) {
        $this->db = $db;
        $this->authorization = $authorization;
    }

    public function canRead(
        array $user,
        array $customer
    ) {
        $userId = (int) $user['id'];

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.all'
        )) {
            return true;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.region'
        )) {
            if (!empty($user['region_id'])
                && (int) $customer['region_id']
                    === (int) $user['region_id']) {
                return true;
            }
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.team'
        )) {
            if (!empty($user['sales_team_id'])
                && (int) $customer['sales_team_id']
                    === (int) $user['sales_team_id']) {
                return true;
            }
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.own'
        )) {
            if ((int) $customer['assigned_user_id']
                === $userId) {
                return true;
            }
        }

        return false;
    }

    public function applyReadScope(
        Zend_Db_Select $select,
        array $user
    ) {
        $userId = (int) $user['id'];

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.all'
        )) {
            return $select;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.region'
        )) {
            if (empty($user['region_id'])) {
                $select->where('1 = 0');
                return $select;
            }

            $select->where(
                'c.region_id = ?',
                $user['region_id']
            );

            return $select;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.team'
        )) {
            if (empty($user['sales_team_id'])) {
                $select->where('1 = 0');
                return $select;
            }

            $select->where(
                'c.sales_team_id = ?',
                $user['sales_team_id']
            );

            return $select;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.read.own'
        )) {
            $select->where(
                'c.assigned_user_id = ?',
                $userId
            );

            return $select;
        }

        $select->where('1 = 0');

        return $select;
    }

    public function canUpdate(
        array $user,
        array $customer
    ) {
        $userId = (int) $user['id'];

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.update.all'
        )) {
            return true;
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.update.team'
        )) {
            return !empty($user['sales_team_id'])
                && (int) $customer['sales_team_id']
                    === (int) $user['sales_team_id'];
        }

        if ($this->authorization->isAllowed(
            $userId,
            'crm.customer.update.own'
        )) {
            return (int) $customer['assigned_user_id']
                === $userId;
        }

        return false;
    }
}
```

------------------------------------------------------------------------

# Using Policy and Scope in Models

## 21. Core Rule

> The Model builds the normal business query. The Policy adds the
> authorization Scope.

``` text
Controller
    |
    v
Model
    |
    | builds normal query
    v
Policy::applyReadScope()
    |
    | adds authorization WHERE conditions
    v
MariaDB
```

The Model should not contain role-specific logic.

------------------------------------------------------------------------

## 22. CRM Model

``` php
<?php

class Application_Model_CrmCustomer
{
    protected $db;
    protected $policy;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Authorization_Policy_CrmCustomer $policy
    ) {
        $this->db = $db;
        $this->policy = $policy;
    }

    public function getList(array $user)
    {
        $select = $this->db->select()
            ->from(
                array('c' => 'crm_customers')
            )
            ->order('c.company_name ASC');

        $this->policy->applyReadScope(
            $select,
            $user
        );

        return $this->db->fetchAll(
            $select
        );
    }
}
```

The same method can produce:

Salesperson:

``` sql
SELECT c.*
FROM crm_customers c
WHERE c.assigned_user_id = 100
ORDER BY c.company_name;
```

Sales Manager:

``` sql
SELECT c.*
FROM crm_customers c
WHERE c.sales_team_id = 5
ORDER BY c.company_name;
```

Regional Manager:

``` sql
SELECT c.*
FROM crm_customers c
WHERE c.region_id = 3
ORDER BY c.company_name;
```

Administrator:

``` sql
SELECT c.*
FROM crm_customers c
ORDER BY c.company_name;
```

------------------------------------------------------------------------

## 23. Scoped Single-Record Lookup

A useful pattern is:

``` php
public function findReadableById(
    $id,
    array $user
) {
    $select = $this->db->select()
        ->from(
            array('c' => 'crm_customers')
        )
        ->where(
            'c.id = ?',
            (int) $id
        );

    $this->policy->applyReadScope(
        $select,
        $user
    );

    return $this->db->fetchRow(
        $select
    );
}
```

For a Sales Manager this may become:

``` sql
SELECT c.*
FROM crm_customers c
WHERE c.id = 500
  AND c.sales_team_id = 5;
```

Unauthorized data therefore never leaves MariaDB.

`canRead()` is still useful when the resource is already loaded or a
complex business operation requires an object-level decision.

------------------------------------------------------------------------

## 24. Email Model

``` php
<?php

class Application_Model_Email
{
    protected $db;
    protected $policy;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Authorization_Policy_Email $policy
    ) {
        $this->db = $db;
        $this->policy = $policy;
    }

    public function getList(array $user)
    {
        $select = $this->db->select()
            ->from(
                array('e' => 'emails')
            )
            ->order('e.id DESC');

        $this->policy->applyReadScope(
            $select,
            $user
        );

        return $this->db->fetchAll(
            $select
        );
    }

    public function findReadableById(
        $emailId,
        array $user
    ) {
        $select = $this->db->select()
            ->from(
                array('e' => 'emails')
            )
            ->where(
                'e.id = ?',
                (int) $emailId
            );

        $this->policy->applyReadScope(
            $select,
            $user
        );

        return $this->db->fetchRow(
            $select
        );
    }
}
```

------------------------------------------------------------------------

## 25. Product Model

``` php
<?php

class Application_Model_Product
{
    protected $db;
    protected $policy;

    public function __construct(
        Zend_Db_Adapter_Abstract $db,
        S3_Authorization_Policy_Product $policy
    ) {
        $this->db = $db;
        $this->policy = $policy;
    }

    public function getList(array $user)
    {
        $select = $this->db->select()
            ->from(
                array('p' => 'products')
            )
            ->order('p.name ASC');

        $this->policy->applyReadScope(
            $select,
            $user
        );

        return $this->db->fetchAll(
            $select
        );
    }

    public function findReadableById(
        $productId,
        array $user
    ) {
        $select = $this->db->select()
            ->from(
                array('p' => 'products')
            )
            ->where(
                'p.id = ?',
                (int) $productId
            );

        $this->policy->applyReadScope(
            $select,
            $user
        );

        return $this->db->fetchRow(
            $select
        );
    }
}
```

------------------------------------------------------------------------

# DHTMLX Integration

## 26. Grid Flow

``` text
DHTMLX Grid
     |
     | GET /api/crm/customer/getlist
     v
Controller
     |
     v
CrmCustomerModel::getList($user)
     |
     v
Build Zend_Db_Select
     |
     v
CrmCustomerPolicy::applyReadScope()
     |
     v
MariaDB
     |
     v
Authorized rows only
     |
     v
DHTMLX Grid
```

Example controller:

``` php
public function getListAction()
{
    $user = $this->getCurrentUser();

    $rows = $this->customerModel
        ->getList($user);

    /*
     * Convert $rows to the required
     * DHTMLX grid response format.
     */
}
```

The browser should never receive unauthorized rows.

------------------------------------------------------------------------

# Protect Every Data Path

## 27. Search

Do not secure `getList()` and forget `search()`.

``` php
public function search(
    $keyword,
    array $user
) {
    $select = $this->db->select()
        ->from(
            array('c' => 'crm_customers')
        )
        ->where(
            'c.company_name LIKE ?',
            '%' . $keyword . '%'
        );

    $this->policy->applyReadScope(
        $select,
        $user
    );

    return $this->db->fetchAll(
        $select
    );
}
```

------------------------------------------------------------------------

## 28. Counts and Pagination

Even counts can reveal information.

``` php
public function countReadable(
    array $user
) {
    $select = $this->db->select()
        ->from(
            array('c' => 'crm_customers'),
            array(
                'total' => 'COUNT(*)'
            )
        );

    $this->policy->applyReadScope(
        $select,
        $user
    );

    return (int)
        $this->db->fetchOne($select);
}
```

For a team manager:

``` sql
SELECT COUNT(*)
FROM crm_customers c
WHERE c.sales_team_id = 5;
```

------------------------------------------------------------------------

## 29. Export

Excel, CSV, and other exports must use the same Scope as the grid.

``` php
public function getExportData(
    array $user
) {
    $select = $this->db->select()
        ->from(
            array('c' => 'crm_customers')
        );

    $this->policy->applyReadScope(
        $select,
        $user
    );

    return $this->db->fetchAll(
        $select
    );
}
```

Otherwise the grid could be secure while an export leaks the full
database.

Reports, dashboards, totals, grouped queries, and other derived outputs
based on protected resources must also respect Scope.

------------------------------------------------------------------------

# Update and Delete

## 30. Update Scope

Read and update access may differ.

``` php
public function findUpdatableById(
    $customerId,
    array $user
) {
    $select = $this->db->select()
        ->from(
            array('c' => 'crm_customers')
        )
        ->where(
            'c.id = ?',
            (int) $customerId
        );

    $this->policy->applyUpdateScope(
        $select,
        $user
    );

    return $this->db->fetchRow(
        $select
    );
}
```

Usage:

``` php
$customer =
    $customerModel->findUpdatableById(
        $customerId,
        $user
    );

if (!$customer) {
    throw new S3_Authorization_Exception(
        'Customer not found or access denied.'
    );
}
```

A Policy may therefore expose:

``` text
applyReadScope()
applyUpdateScope()
applyDeleteScope()
```

when each operation has different access rules.

For sensitive/high-concurrency operations, authorization conditions can
also be part of the UPDATE itself:

``` sql
UPDATE crm_customers
SET company_name = ?
WHERE id = 500
  AND sales_team_id = 5;
```

The application then checks the affected-row result.

------------------------------------------------------------------------

# Explicit Sharing

## 31. Optional Resource Sharing

If a real requirement appears such as:

``` text
Document #500 belongs to Finance
but is explicitly shared with User 100.
```

a sharing table may be introduced.

A generic example:

``` sql
CREATE TABLE resource_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    resource_type VARCHAR(50) NOT NULL,

    resource_id BIGINT UNSIGNED NOT NULL,

    subject_type VARCHAR(20) NOT NULL
        COMMENT 'user, role, department',

    subject_id BIGINT UNSIGNED NOT NULL,

    permission_key VARCHAR(150) NOT NULL,

    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    KEY idx_resource (
        resource_type,
        resource_id
    ),

    KEY idx_subject (
        subject_type,
        subject_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Do not add this merely because Policies exist. Add it only when explicit
resource sharing is a real requirement.

A document Scope might later contain:

``` sql
WHERE
    d.owner_user_id = :user_id

OR EXISTS (
    SELECT 1
    FROM document_shares ds
    WHERE ds.document_id = d.id
      AND ds.user_id = :user_id
)
```

------------------------------------------------------------------------

# Avoid Over-Generalization

## 32. Do Not Build a Rule Engine Too Early

Avoid immediately creating:

``` text
resources
resource_policies
resource_scopes
resource_rules
resource_conditions
```

and trying to represent every authorization rule as database
configuration.

Real rules can be complex:

``` text
Manager may approve documents
from their department,
except their own documents,
only when status = submitted.
```

Trying to encode all of this in generic database conditions creates a
custom programming language.

Prefer:

``` text
Database
    |
    +-- users
    +-- roles
    +-- permissions
    +-- user_roles
    +-- role_permissions

PHP
    |
    +-- DocumentPolicy
    +-- EmailPolicy
    +-- ProductPolicy
    +-- CrmCustomerPolicy
```

------------------------------------------------------------------------

# Frontend Authorization

## 33. DHTMLX Permissions

The server can send effective permissions for UI behavior:

``` json
{
    "permissions": {
        "document.read": true,
        "document.create": true,
        "document.update": true,
        "document.delete": false,
        "document.approve": false
    }
}
```

ES5-compatible example:

``` javascript
if (!permissions['document.delete']) {
    toolbar.disableItem('delete');
}
```

Remember:

``` text
DHTMLX permission check
    =
User experience

PHP/server permission check
    =
Security
```

Frontend permissions must never be trusted as the authorization
boundary.

------------------------------------------------------------------------

# Model Dependencies

## 34. Do Not Read `Zend_Auth` Inside Every Model

Avoid:

``` php
class Application_Model_CrmCustomer
{
    public function getList()
    {
        $userId = Zend_Auth::getInstance()
            ->getIdentity()->id;

        // ...
    }
}
```

This creates a hidden dependency on the current web request and makes
the model harder to use from:

``` text
PHPUnit
CLI workers
cron
administration utilities
background processing
```

Prefer explicit authorization context:

``` php
$model->getList($user);
```

------------------------------------------------------------------------

# Authorization Context

## 35. Future Improvement

Passing a `$user` array is fine initially. As the application grows,
introduce:

``` text
S3_Authorization_Context
```

For example:

``` php
$context = new S3_Authorization_Context(
    $userId,
    $departmentId,
    $salesTeamId,
    $regionId
);
```

Then:

``` php
$model->getList($context);

$model->findReadableById(
    $id,
    $context
);
```

and:

``` php
$policy->applyReadScope(
    $select,
    $context
);
```

This creates a clearer contract than depending on arbitrary user-array
keys.

------------------------------------------------------------------------

# Method Naming

## 36. Prefer Explicit Scoped Names

For protected resources, prefer names such as:

``` text
findReadableById()
findUpdatableById()
countReadable()
getReadableList()
```

Compare:

``` php
$customer = $model->find($id);
```

with:

``` php
$customer = $model->findReadableById(
    $id,
    $user
);
```

The second makes authorization behavior visible.

A useful team rule is:

> Any Model method that can return protected resources must either apply
> an authorization Scope itself or be clearly identified as an
> internal/unscoped method that must never be exposed directly through a
> user-facing API.

------------------------------------------------------------------------

# Policy/Scope Testing

## 37. Keep Policy and Scope Consistent

There is some intentional duplication between:

``` text
canRead()
```

and:

``` text
applyReadScope()
```

A developer could change one and forget the other. Every authorization
change should therefore review and test both.

Example CRM test data:

``` text
User 100:
    team = 5
    region = 3

Customer 1:
    assigned_user = 100
    -> OWN should see

Customer 2:
    assigned_user = 200
    team = 5
    -> TEAM should see

Customer 3:
    team = 8
    region = 3
    -> REGION should see

Customer 4:
    region = 9
    -> ALL should see
```

Test both:

``` php
$policy->canRead(
    $user,
    $customer
);
```

and the query produced by:

``` php
$policy->applyReadScope(
    $select,
    $user
);
```

The single-resource result and list result must agree.

------------------------------------------------------------------------

# Auditing

## 38. Audit Sensitive Operations

Good candidates include:

``` text
document.approve
document.delete
user.permission.change
role.permission.change
product.delete
```

Example audit record:

``` text
Time:
    2026-10-06 14:30:21

User:
    100

Action:
    document.approve

Resource:
    document:500

Result:
    allowed

IP:
    ...

Request ID:
    ...
```

Denied high-risk operations can also be logged. Avoid excessive logging
of harmless UI permission checks.

------------------------------------------------------------------------

# Administration

## 39. Permission Administration UI

A future administration site can provide:

``` text
Security Administration

Users
Roles
Permissions
Role Permissions
User Roles
Resource Sharing
Permission Audit
```

Example:

``` text
Role: Department Manager

[x] email.read
[x] email.send
[x] blog.read
[ ] blog.delete
[x] document.read
[x] document.update
[x] document.approve
[ ] document.delete
```

------------------------------------------------------------------------

# Complete Architecture

## 40. Request Architecture

``` text
                         REQUEST
                            |
                            v
                  +------------------+
                  | Authentication   |
                  | Who are you?     |
                  +------------------+
                            |
                            v
                  +------------------+
                  | Session Timeout  |
                  | inactivity       |
                  +------------------+
                            |
                            v
                  +------------------+
                  | RBAC Permission  |
                  | General action   |
                  +------------------+
                            |
                            v
                  +------------------+
                  | Resource Policy  |
                  | Single object    |
                  +------------------+
                            |
                            v
                  +------------------+
                  | Resource Scope   |
                  | Query/list       |
                  +------------------+
                            |
                            v
                  +------------------+
                  | Business Logic   |
                  +------------------+
                            |
                            v
                  +------------------+
                  | Audit            |
                  +------------------+
```

Model/query architecture:

``` text
Controller
    |
    | user/context
    v
Business Service
    |
    v
Model
    |
    | builds business query
    v
Resource Policy
    |
    | applyReadScope()
    | applyUpdateScope()
    v
MariaDB
```

------------------------------------------------------------------------

# Implementation Plan

## 41. Phase 1 --- RBAC

Create:

``` text
roles
permissions
user_roles
role_permissions
```

Implement:

``` text
S3_Authorization_Service
isAllowed()
requirePermission()
```

Use request-level permission caching.

## 42. Phase 2 --- First Policy

Choose one resource, such as Document or CRM Customer.

Implement:

``` text
canRead()
canUpdate()
applyReadScope()
```

Add unit tests.

## 43. Phase 3 --- Model Scoping

Protect:

``` text
getList()
findReadableById()
search()
countReadable()
export()
```

Then add update/delete scopes where needed.

## 44. Phase 4 --- Other Resources

Add:

``` text
EmailPolicy
ProductPolicy
CrmCustomerPolicy
DocumentPolicy
BlogPolicy
...
```

Each Policy uses its resource's natural relationships.

## 45. Phase 5 --- Administration and Audit

Add role/permission administration, audit logging, and explicit resource
sharing only when required.

## 46. Phase 6 --- Authorization Context

When user arrays become difficult to maintain, introduce:

``` text
S3_Authorization_Context
```

------------------------------------------------------------------------

# Design Rules

## 47. Important Rules

1.  **Deny by default.** If no rule grants access, reject it or return
    an empty scope.

2.  **Check capabilities, not hard-coded role names.**

    Prefer:

    ``` php
    $authorization->isAllowed(
        $userId,
        'document.approve'
    );
    ```

3.  **Do not trust frontend permissions.** DHTMLX checks are UX only.

4.  **Apply Scope at the database level.** Unauthorized rows should
    normally never reach PHP or the browser.

5.  **Apply Scope to every data path.** Lists, searches, counts,
    exports, reports, and single-record lookups all matter.

6.  **Do not force common ownership columns onto every resource table.**

7.  **Keep Policy and Scope synchronized and tested.**

8.  **Do not build a generic authorization rule language prematurely.**

9.  **Re-check authorization for every write operation.** An earlier GET
    authorization result must not authorize a later update/delete
    automatically.

10. **Prefer explicit scoped model method names** such as
    `findReadableById()`.

------------------------------------------------------------------------

# Final Mental Model

Ask three questions.

### 1. Can the user perform this TYPE of action?

Example:

``` text
Can John approve documents?
```

Use:

``` text
RBAC Permission
```

### 2. Can the user perform it on THIS ONE resource?

Example:

``` text
Can John approve Document #500?
```

Use:

``` text
Resource Policy
```

### 3. Which resources should the user receive in a list/query?

Example:

``` text
Which documents should appear in John's DHTMLX grid?
```

Use:

``` text
Resource Scope
```

Final design:

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
      |
      v
Business operation
      |
      v
Audit where required
```

The central principle is:

> **The Policy adapts to the resource; the resource does not need to be
> redesigned to fit the Policy.**

Email can use sender/recipient relationships, Product can use
company-wide permissions, CRM can use salesperson/team/region
relationships, and Document can use owner/department/sharing rules while
all participate in one consistent authorization architecture.
