# Enterprise Pivot Analysis & Internal BI Platform

## Detailed Architecture, Requirements, Security, Database Design, and Implementation Roadmap

**Target environment:** PHP 7.4, DHTMLX 3.5, MariaDB, offline/intranet
deployment\
**Document status:** Consolidated design specification\
**Scope:** Excel-like Pivot analysis, drill-down, saved reports,
sharing, exports, scheduling, performance, charts, and dashboards

------------------------------------------------------------------------

## 1. Executive Summary

This document defines an enterprise Pivot Analysis platform for an
internal business system. The goal is to provide Excel-like PivotTable
capabilities inside the company intranet while retaining centralized
authorization, row-level security, auditability, performance controls,
and offline operation.

The fundamental architecture is:

``` text
DHTMLX Pivot Designer
        ↓
Pivot Definition JSON
        ↓
PHP Pivot API
        ↓
Authentication / Authorization
        ↓
Dataset Metadata + Security Scope
        ↓
Query Planner / Query Builder
        ↓
MariaDB Reporting Data
        ↓
Aggregate Tuples
        ↓
Generic Pivot Result Engine
        ↓
Rows + Columns + Measures + Cells
        ↓
Grid / Chart / Export / Dashboard / Drill-down
```

The browser never submits arbitrary SQL. It sends a structured Pivot
Definition containing trusted field codes. PHP validates the definition
against server-controlled dataset metadata, adds mandatory security
predicates, builds parameterized SQL, and generates a generic
hierarchical Pivot result.

The recommended rollout starts with a small synchronous Pivot
implementation and progressively adds generic hierarchies, a visual
designer, secure drill-down, report governance, background processing,
reporting-database optimization, charts, and dashboards.

------------------------------------------------------------------------

## 2. Primary Design Principles

1.  **DHTMLX is the presentation layer, not the analytical engine.**
2.  **PHP owns Pivot semantics and validation.**
3.  **MariaDB performs source aggregation.**
4.  **The browser never sends SQL, table names, or raw database
    expressions.**
5.  **Datasets provide a semantic/security boundary over database
    sources.**
6.  **Mandatory security filters are separate from user filters.**
7.  **Saved reports store definitions, not SQL or authorization
    snapshots.**
8.  **Shared reports execute under the recipient's current
    permissions.**
9.  **Drill-down and export recheck current authorization.**
10. **Operational and analytical workloads should eventually be
    separated.**
11. **All JavaScript, CSS, fonts, icons, and libraries must be hosted
    internally.**
12. **Explicit Run is preferred over Auto Run for large enterprise
    datasets.**

------------------------------------------------------------------------

## 3. Functional Scope

### 3.1 Core Pivot capabilities

The platform should support:

-   Rows
-   Columns
-   Values
-   Filters
-   Multiple row dimensions
-   Multiple column dimensions
-   Multiple measures
-   SUM
-   COUNT
-   AVG
-   MIN
-   MAX
-   Date grouping
    -   Year
    -   Quarter
    -   Month
    -   Week
    -   Day
-   Text filters
-   Numeric filters
-   Date filters
-   Subtotals
-   Row grand totals
-   Column grand totals
-   Expand/collapse
-   Sorting
-   Top/Bottom N
-   Drill-down
-   Saved reports
-   Sharing
-   Version history
-   Certified reports
-   Pivot export
-   Detail export
-   Background jobs
-   Scheduled reports
-   Charts
-   Dashboards

### 3.2 Later analytical features

These should be added only after the core engine is stable:

-   DISTINCT COUNT with mathematically correct rollups
-   Show Values As
-   \% of row total
-   \% of column total
-   \% of grand total
-   Running total
-   Rank
-   Difference from
-   Calculated measures
-   Conditional formatting
-   Slicers
-   Advanced charting
-   Published snapshots
-   AI/natural-language analysis

------------------------------------------------------------------------

## 4. Canonical Pivot Definition

The frontend maintains one canonical plain-JavaScript object.

``` json
{
  "version": 1,
  "dataset": {
    "code": "SALES",
    "version": 1
  },
  "rows": [
    {
      "field": "department",
      "subtotal": true
    },
    {
      "field": "product",
      "subtotal": true
    }
  ],
  "columns": [
    {
      "field": "order_date",
      "group": "year"
    },
    {
      "field": "order_date",
      "group": "quarter"
    }
  ],
  "values": [
    {
      "id": "revenue",
      "field": "sales_amount",
      "aggregation": "sum",
      "label": "Revenue"
    },
    {
      "id": "profit",
      "field": "profit_amount",
      "aggregation": "sum",
      "label": "Profit"
    }
  ],
  "filters": [
    {
      "field": "order_date",
      "group": "year",
      "operator": "eq",
      "value": 2026
    }
  ],
  "options": {
    "subtotals": true,
    "rowGrandTotal": true,
    "columnGrandTotal": true,
    "emptyCells": "blank"
  },
  "visualization": {
    "mode": "grid",
    "chartType": null
  }
}
```

UI-only state must remain outside this definition:

``` text
expanded nodes
selected cell
scroll position
panel widths
open dialogs
temporary drag state
```

This separation makes saving, versioning, undo/redo, hashing, caching,
and testing much simpler.

------------------------------------------------------------------------

## 5. Dataset Semantic Layer

Users must not select arbitrary database tables or columns.

A **dataset** defines:

-   trusted source
-   available fields
-   business labels
-   field roles
-   field data types
-   stable keys
-   display labels
-   allowed aggregations
-   allowed date groups
-   drill-down capability
-   export capability
-   cardinality information
-   dataset permissions
-   row-level security policy
-   result limits

Example:

``` text
Dataset: SALES

Department
  role: dimension
  key_column: department_id
  label_column: department_name

Product
  role: dimension
  key_column: product_id
  label_column: product_name

Order Date
  role: dimension
  value_column: order_date
  type: date

Sales Amount
  role: measure
  value_column: sales_amount
  default aggregation: SUM
```

Stable key + label is essential. Two departments can theoretically have
the same label but different IDs; they must not merge.

------------------------------------------------------------------------

## 6. Database Metadata Schema

### 6.1 `pivot_datasets`

``` sql
CREATE TABLE pivot_datasets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    source_type VARCHAR(30) NOT NULL DEFAULT 'TABLE',
    source_name VARCHAR(255) NOT NULL,

    owner_department_id BIGINT UNSIGNED NULL,

    version INT UNSIGNED NOT NULL DEFAULT 1,
    execution_mode VARCHAR(30) NOT NULL DEFAULT 'MANUAL',

    max_row_dimensions INT UNSIGNED NOT NULL DEFAULT 5,
    max_column_dimensions INT UNSIGNED NOT NULL DEFAULT 3,
    max_measures INT UNSIGNED NOT NULL DEFAULT 10,

    max_result_rows INT UNSIGNED NOT NULL DEFAULT 10000,
    max_result_columns INT UNSIGNED NOT NULL DEFAULT 500,
    max_result_cells INT UNSIGNED NOT NULL DEFAULT 200000,

    allow_drilldown TINYINT(1) NOT NULL DEFAULT 1,
    allow_export TINYINT(1) NOT NULL DEFAULT 1,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_pivot_dataset_code (code)
) ENGINE=InnoDB;
```

### 6.2 `pivot_dataset_fields`

Important logical columns:

``` text
id
dataset_id
field_code
field_name
description
field_role
data_type
field_group
key_column
label_column
value_column
default_aggregation
default_format
allow_row
allow_column
allow_filter
allow_value
allow_drilldown
allow_export
high_cardinality
estimated_cardinality
display_order
is_active
created_at
updated_at
```

### 6.3 Allowed aggregations

``` text
pivot_field_aggregations
- field_id
- aggregation_code
- is_default
```

### 6.4 Allowed date groups

``` text
pivot_field_date_groups
- field_id
- group_code
- is_default
```

### 6.5 Dataset permissions

``` text
pivot_dataset_permissions
- dataset_id
- principal_type
- principal_id
- can_view
- can_create_report
- can_drilldown
- can_export_pivot
- can_export_detail
```

Principal types may include:

``` text
USER
ROLE
DEPARTMENT
GROUP
COMPANY
```

### 6.6 Dataset security policy

``` text
pivot_dataset_security
- dataset_id
- security_mode
- company_field_code
- department_field_code
- owner_field_code
```

Possible modes:

``` text
COMPANY
DEPARTMENT
COMPANY_DEPARTMENT
OWNER
CUSTOM_PROVIDER
```

------------------------------------------------------------------------

## 7. Security Architecture

### 7.1 Filter separation

Every execution effectively contains:

``` text
Mandatory Security Filters
AND
Saved/User Pivot Filters
AND
Runtime Filters
```

Mandatory filters are never editable by the browser.

Example:

``` text
User filter:
Year = 2026

Mandatory security:
company_id = 10
department_id IN (20, 21, 25)
```

Generated logic:

``` sql
WHERE company_id = ?
  AND department_id IN (?, ?, ?)
  AND order_date >= ?
  AND order_date < ?
```

### 7.2 Identifier security

Prepared statements protect values, not SQL identifiers.

All identifiers must originate from trusted dataset metadata and pass
validation such as:

``` text
^[A-Za-z_][A-Za-z0-9_]*$
```

Trusted identifiers should then be safely quoted with backticks.

Never accept from the client:

``` text
table name
column name
SQL function
SQL expression
ORDER BY expression
raw WHERE clause
```

### 7.3 Operator whitelist

Supported operators should be explicitly mapped:

``` text
eq
neq
gt
gte
lt
lte
between
in
not_in
contains
starts_with
ends_with
relative
```

`LIKE` values must escape `%` and `_`.

### 7.4 Date filtering

Prefer:

``` sql
order_date >= '2026-01-01'
AND order_date < '2027-01-01'
```

rather than:

``` sql
YEAR(order_date) = 2026
```

in the `WHERE` clause.

Grouping may still use `YEAR()`, `QUARTER()`, etc.

------------------------------------------------------------------------

## 8. PHP Backend Architecture

Recommended services:

``` text
S3_Pivot_Service
S3_Pivot_DatasetService
S3_Pivot_PermissionService
S3_Pivot_SecurityService
S3_Pivot_DefinitionValidator
S3_Pivot_QueryPlanner
S3_Pivot_QueryBuilder

S3_Pivot_ResultBuilder
S3_Pivot_AxisNode
S3_Pivot_AxisTree
S3_Pivot_AxisTreeBuilder
S3_Pivot_CellStore
S3_Pivot_TotalCalculator
S3_Pivot_ResultSerializer

S3_Pivot_AggregatorFactory
S3_Pivot_SumAggregator
S3_Pivot_CountAggregator
S3_Pivot_AverageAggregator
S3_Pivot_MinAggregator
S3_Pivot_MaxAggregator

S3_Pivot_DrilldownService
S3_Pivot_ReportService
S3_Pivot_ExportService
S3_Pivot_AuditService
```

The service layers should remain independent enough to unit test
individually.

------------------------------------------------------------------------

## 9. Query Planner

The QueryPlanner converts the validated definition into a neutral plan.

Example:

``` text
source: rpt_sales

rowDimensions:
  d0 = department
  d1 = product

columnDimensions:
  d2 = order_date/year
  d3 = order_date/quarter

measures:
  m0 = SUM(sales_amount)
  m1 = SUM(profit_amount)

userFilters:
  year = 2026

securityFilters:
  company = 10

limits:
  maxAggregateRows = ...
```

The planner should not concatenate untrusted SQL.

------------------------------------------------------------------------

## 10. Query Builder

The QueryBuilder maps the neutral plan to MariaDB SQL.

Dimensions should return key and label:

``` sql
department_id   AS d0_key,
department_name AS d0_label
```

Date dimensions may return:

``` sql
YEAR(order_date) AS d2_key,
YEAR(order_date) AS d2_label,

QUARTER(order_date) AS d3_key,
CONCAT('Q', QUARTER(order_date)) AS d3_label
```

Measures:

``` sql
SUM(sales_amount) AS m0
SUM(profit_amount) AS m1
```

------------------------------------------------------------------------

## 11. Correct AVG Handling

Never compute subtotal averages by averaging already-aggregated
averages.

Bad:

``` text
Q1 average = 10
Q2 average = 100

overall average = (10 + 100) / 2
```

This is wrong when group counts differ.

Instead query support state:

``` sql
SUM(value)   AS m0_sum,
COUNT(value) AS m0_count
```

Internal state:

``` json
{
  "sum": 15000,
  "count": 25
}
```

Finalize:

``` text
average = sum / count
```

Totals merge states:

``` text
SUM totals:
sum + sum

AVG totals:
(sum1 + sum2) / (count1 + count2)
```

------------------------------------------------------------------------

## 12. Aggregation Categories

``` text
Additive
- SUM
- COUNT (when groups are non-overlapping)

Roll-up friendly
- MIN
- MAX

State-based
- AVG = SUM + COUNT

Non-additive
- DISTINCT_COUNT
- MEDIAN
- PERCENTILE
```

DISTINCT COUNT should not be treated as additive. Correct subtotal
support should be added separately.

------------------------------------------------------------------------

## 13. Generic Pivot Result Engine

The engine is based on:

``` text
Row Axis Tree
+
Column Axis Tree
+
Sparse Cell Store
```

Example row hierarchy:

``` text
ROOT
├── Sales
│   ├── Product A
│   └── Product B
├── Marketing
└── Support
```

Column hierarchy:

``` text
ROOT
└── 2026
    ├── Q1
    ├── Q2
    ├── Q3
    └── Q4
```

Measures remain separate:

``` text
Revenue
Profit
```

------------------------------------------------------------------------

## 14. Axis Nodes

Each node should contain:

``` text
id
key
label
fieldCode
level
parentId
children
```

Node identity must be based on the full key path, not the display label.

Example path:

``` text
department=10
product=100
```

can be hashed into a stable execution-local node ID.

------------------------------------------------------------------------

## 15. Empty Axes

The engine must support:

``` text
Rows: Department
Columns: none
Values: Revenue
```

and:

``` text
Rows: none
Columns: Quarter
Values: Revenue
```

The axis root acts as the leaf when an axis has no configured
dimensions.

------------------------------------------------------------------------

## 16. Sparse Cell Store

Conceptually:

``` text
rowId
  → columnId
      → measureId
          → aggregation state
```

Do not allocate a full dense matrix when most cells are empty.

Preserve:

``` text
null = no data
0    = actual zero
```

They are not equivalent.

------------------------------------------------------------------------

## 17. Subtotals and Grand Totals

A leaf tuple such as:

``` text
Sales
→ Product A

2026
→ Q1

Revenue = 10000
```

is propagated to row and column ancestors.

Row ancestors:

``` text
Product A
Sales
ROOT
```

Column ancestors:

``` text
Q1
2026
ROOT
```

Merge the measure state for every ancestor combination.

This automatically generates:

``` text
leaf values
row subtotals
column subtotals
row grand totals
column grand totals
overall grand total
```

The SQL query must return only leaf-level aggregate combinations; it
should not simultaneously return SQL-generated subtotal rows.

------------------------------------------------------------------------

## 18. Generic Result Response

Example structure:

``` json
{
  "success": true,
  "data": {
    "meta": {
      "executionId": "PVT-...",
      "dataset": "SALES",
      "durationMs": 187
    },

    "rows": {
      "id": "r:root",
      "children": []
    },

    "columns": {
      "id": "c:root",
      "children": []
    },

    "measures": [
      {
        "id": "revenue",
        "label": "Revenue"
      },
      {
        "id": "profit",
        "label": "Profit"
      }
    ],

    "cells": []
  }
}
```

Internal aggregation support state must not be exposed unless required.

------------------------------------------------------------------------

## 19. DHTMLX 3.5 Frontend Architecture

``` text
DHTMLX Frontend
├── dhtmlxLayout
├── dhtmlxToolbar
├── dhtmlxGrid
├── dhtmlxForm
├── dhtmlxWindows
├── custom Field Panel
└── custom Pivot Designer
        ↓
Pivot Definition JSON
        ↓
PHP Pivot API
```

Recommended JavaScript modules:

``` text
S3PivotController.js
S3PivotDesigner.js
S3PivotFieldPanel.js
S3PivotApi.js
S3PivotGridAdapter.js
S3PivotHeaderBuilder.js
S3PivotHistory.js
S3PivotDialogs.js
S3PivotUtils.js
S3PivotChartAdapter.js
```

Use ES5-compatible JavaScript for maximum compatibility with the
existing DHTMLX environment.

------------------------------------------------------------------------

## 20. Pivot Designer UI

Recommended layout:

``` text
┌─────────────────────────────────────────────────────────────┐
│ [New] [Open] [Save] [Save As] [Run] [Undo] [Redo] [Export]│
├─────────────────┬───────────────────────────────────────────┤
│ PIVOT FIELDS    │ FILTERS                                   │
│ Search [...]    │ [Year = 2026]                             │
│                 ├───────────────────────────────────────────┤
│ Department      │ COLUMNS                                   │
│ Product         │ [Year] [Quarter]                          │
│ Region          ├───────────────────────────────────────────┤
│ Order Date      │ ROWS                                      │
│ Quantity        │ [Department] [Product]                    │
│ Sales Amount    ├───────────────────────────────────────────┤
│ Profit          │ VALUES                                    │
│                 │ [SUM Revenue] [SUM Profit]                │
│                 ├───────────────────────────────────────────┤
│                 │ PIVOT GRID                                │
└─────────────────┴───────────────────────────────────────────┘
```

Custom HTML is preferable for field zones because it provides easier
control over:

``` text
chips
drag/drop
drop indicators
reordering
configuration menus
remove buttons
validation
```

------------------------------------------------------------------------

## 21. Field Panel

Group fields by business category:

``` text
ORGANIZATION
  ABC Company
  ABC Department

PRODUCT
  ABC Product
  ABC Category

CUSTOMER
  ABC Customer
  ABC Region

DATE
  DATE Order Date

MEASURES
  123 Quantity
  123 Sales Amount
  123 Profit
```

Use `.text()` or equivalent safe DOM insertion for metadata labels
rather than injecting raw HTML.

------------------------------------------------------------------------

## 22. Menu First, Drag-and-Drop Second

Initial field interaction should support:

``` text
Add to Rows
Add to Columns
Add to Filters
Add to Values
```

This makes the full designer functional before drag/drop complexity is
introduced.

Later drag/drop should simply call controller methods:

``` text
controller.addField(fieldCode, 'rows')
controller.moveItem(...)
```

The drag/drop layer must contain no analytical business logic.

------------------------------------------------------------------------

## 23. Date Group Dialog

When a date field is added:

``` text
Year
Quarter
Month
Week
Day
Exact Date
```

The same date field can appear more than once:

``` text
Columns:
Order Date / Year
Order Date / Quarter
```

Therefore field-code duplication cannot be globally prohibited.

------------------------------------------------------------------------

## 24. Value Settings

Example dialog:

``` text
Field: Sales Amount

Summarize values by:
[SUM]

Custom Name:
[Revenue]

Number Format:
[Currency]

Decimal Places:
[2]
```

Aggregation options must come from server metadata.

A measure instance needs its own ID because the same field can appear
as:

``` text
SUM(Sales Amount)
AVG(Sales Amount)
```

------------------------------------------------------------------------

## 25. Filter UI

String:

``` text
Region
Operator: Is one of
WEST
NORTH
```

Numeric:

``` text
Sales Amount
Operator: Greater than or equal
10000
```

Date:

``` text
Order Date
Group: Year
Operator: Equals
2026
```

High-cardinality fields must use server-side search rather than
downloading every distinct value.

------------------------------------------------------------------------

## 26. Distinct Filter Values Security

A distinct-values endpoint must apply the same security scope.

Flow:

``` text
Authentication
↓
Dataset authorization
↓
Field authorization
↓
Mandatory row security
↓
SELECT DISTINCT
```

Otherwise filter metadata itself can leak restricted information.

------------------------------------------------------------------------

## 27. Explicit Run

V1 should use:

``` text
Configure
Configure
Configure
Run
```

rather than executing after every change.

This protects MariaDB when a user adds a high-cardinality dimension.

Auto Run can be introduced later for small datasets with debounce.

------------------------------------------------------------------------

## 28. Client and Server Validation

Client validation improves usability, for example:

``` text
At least one measure required.
At least one row or column dimension required.
```

But server validation remains authoritative.

Structured error codes should include:

``` text
FIELD_NOT_FOUND
FIELD_NOT_ALLOWED
AGGREGATION_NOT_ALLOWED
INVALID_FILTER
RESULT_TOO_LARGE
PERMISSION_DENIED
DATASET_CHANGED
```

------------------------------------------------------------------------

## 29. Undo/Redo

Because the definition is JSON-friendly, history can store snapshots:

``` text
undo stack
redo stack
```

Every analytical modification pushes a snapshot.

UI-only state should not enter the history unless specifically desired.

------------------------------------------------------------------------

## 30. DHTMLX Grid Rendering

The Grid Adapter:

``` text
Pivot Result
↓
flatten visible row hierarchy
↓
flatten column leaves
↓
build multi-level headers
↓
look up cells
↓
format values
↓
render dhtmlxGrid
```

The grid must not calculate totals or security rules.

For the first implementation, recreating the grid after execution is
acceptable and simpler than dynamically mutating complex columns.

------------------------------------------------------------------------

## 31. Multi-Level Headers

For:

``` text
2026
├── Q1
└── Q2

Measures:
Revenue
Profit
```

display:

``` text
                    2026
          Q1                     Q2
    Revenue Profit         Revenue Profit
```

The Header Builder should produce a deterministic `leafColumns` array:

``` text
Q1 / Revenue
Q1 / Profit
Q2 / Revenue
Q2 / Profit
```

Every rendered row must use this exact ordering.

------------------------------------------------------------------------

## 32. Expand/Collapse

For V1, the server can return the complete hierarchy and the client can
manage visibility:

``` text
[+] Sales
[+] Marketing
[+] Support
```

After expansion:

``` text
[-] Sales
      Product A
      Product B
[+] Marketing
[+] Support
```

Expansion state is UI state, not analytical definition.

For very large hierarchies, lazy server expansion can be added later.

------------------------------------------------------------------------

## 33. Drill-Down Architecture

A user double-clicking a Pivot value should request:

``` json
{
  "executionId": "PVT-...",
  "cellId": "CELL-...",
  "page": 1,
  "pageSize": 50
}
```

Never send reconstructed SQL predicates or trust displayed labels.

Server flow:

``` text
Load execution
↓
verify execution
↓
load dataset
↓
recheck current permissions
↓
rebuild current security scope
↓
resolve cell context
↓
build parameterized detail query
↓
paginate
↓
return authorized records
```

------------------------------------------------------------------------

## 34. Execution and Cell Context

A cell may represent:

``` text
Rows:
Department = 10

Columns:
Year = 2026
Quarter = 1

Measure:
Revenue
```

The internal context stores structured field/key information, not SQL.

Execution contexts should expire after a configurable period.

------------------------------------------------------------------------

## 35. Drill-Down Permissions

Keep these separate:

``` text
can_view
can_drilldown
can_export_pivot
can_export_detail
```

A user may be authorized to see an aggregate but not its underlying
transaction records.

Security must be rechecked at drill-down time because permissions may
have changed since the Pivot execution.

------------------------------------------------------------------------

## 36. Drill-Down Pagination

Never return tens of thousands of records on double-click.

Start with:

``` text
25
50
100
200
```

as controlled page-size options.

Sorting requests should use structured field codes and directions, never
raw `ORDER BY` SQL.

------------------------------------------------------------------------

## 37. Saved Reports

A saved report stores:

``` text
dataset
definition JSON
dataset version
owner
name
description
visibility
current report version
```

It does not store generated SQL.

Recommended table:

``` text
pivot_reports
```

Important fields:

``` text
id
dataset_id
owner_user_id
name
description
configuration_json
dataset_version
visibility
current_version
is_active
created_at
updated_at
```

------------------------------------------------------------------------

## 38. Sharing Rule

The most important rule:

``` text
Shared report
≠
Shared authorization
```

Alice and Bob can run the same saved definition and receive different
results because their row-security scopes differ.

The recipient's current dataset and field permissions always win.

------------------------------------------------------------------------

## 39. Report Permissions

Use:

``` text
pivot_report_permissions
```

with:

``` text
principal_type
principal_id
permission
```

Initial permissions:

``` text
VIEW
EDIT
```

Possible later permissions:

``` text
SHARE
CERTIFY
ADMIN
```

Sharing with a department or company should use one principal permission
rather than inserting thousands of individual user rows.

------------------------------------------------------------------------

## 40. Report Version History

Every meaningful save creates a new version.

``` text
pivot_report_versions
- report_id
- version_number
- configuration_json
- changed_by
- change_note
- created_at
```

Restoring Version 2 while current is Version 5 should create Version 6
containing Version 2's definition. Do not erase Versions 3--5.

------------------------------------------------------------------------

## 41. Optimistic Concurrency

When saving a shared report:

``` json
{
  "expectedVersion": 5,
  "definition": {}
}
```

If current version is 6, reject with:

``` text
REPORT_VERSION_CONFLICT
```

Do not hold database locks for long editing sessions.

------------------------------------------------------------------------

## 42. Certified Reports

Organizations need to distinguish official reports from personal
reports.

Example:

``` text
✓ Monthly Revenue
✓ Product Margin
✓ Quarterly Sales
```

Certification should be version-specific.

A robust model is:

``` text
pivot_report_certifications
- report_id
- version_number
- certified_by
- certification_note
- created_at
- revoked_by
- revoked_at
- revoke_reason
```

Editing a certified report creates a new version that is not
automatically certified.

------------------------------------------------------------------------

## 43. Report Library

Useful categories:

``` text
MY REPORTS
SHARED WITH ME
CERTIFIED REPORTS
DEPARTMENT REPORTS
RECENTLY USED
FAVORITES
```

Search/filter by:

``` text
name
dataset
owner
department
certified status
updated date
visibility
```

Favorites can use a simple `(user_id, report_id)` mapping table.

------------------------------------------------------------------------

## 44. Background Jobs

Normal Pivot executions should remain synchronous when small.

Large work should use:

``` text
pivot_jobs
```

Job types:

``` text
PIVOT
EXPORT_PIVOT
EXPORT_DETAIL
SCHEDULED_REPORT
SNAPSHOT
```

States:

``` text
QUEUED
RUNNING
COMPLETED
FAILED
CANCELLED
```

------------------------------------------------------------------------

## 45. PHP Worker

Initial architecture:

``` text
Web/API
↓
pivot_jobs
↓
PHP Worker
↓
Pivot Engine
↓
MariaDB
↓
result/file
↓
job completed
↓
notification
```

A simple MariaDB-backed queue is sufficient initially. A separate broker
is not required for V1.

------------------------------------------------------------------------

## 46. Safe Job Claiming

Workers should:

``` text
begin short transaction
↓
select one QUEUED job for update
↓
change to RUNNING
↓
commit immediately
↓
perform long work outside transaction
```

Never hold the database row lock throughout report generation.

Multiple workers may be introduced deliberately, but concurrency must be
controlled to protect MariaDB.

------------------------------------------------------------------------

## 47. Export

Support initially:

``` text
CSV
XLSX
PDF
```

Priority:

1.  CSV
2.  XLSX
3.  PDF

CSV should flatten multi-level headers.

XLSX should preserve:

``` text
report title
filters
multi-level headers
hierarchy
subtotals
grand totals
number formats
generated timestamp
```

V1 does not need to create a native Excel PivotTable/pivot cache.

PDF should reject extremely wide results rather than generate unreadable
output.

------------------------------------------------------------------------

## 48. Secure Downloads

Generated files must not be exposed through public/direct filesystem
URLs.

Use:

``` text
GET /api/pivot/jobs/{jobKey}/download
```

and verify:

``` text
current user
job access
COMPLETED state
not expired
```

Then stream the file.

------------------------------------------------------------------------

## 49. Scheduled Reports

Schedule configuration should be structured, for example:

``` json
{
  "type": "WEEKLY",
  "days": ["MON"],
  "time": "08:00"
}
```

Avoid arbitrary user-provided cron expressions in V1.

Schedule table should include:

``` text
report_id
owner_user_id
name
schedule_type
schedule_config_json
timezone
output_format
is_enabled
last_run_at
next_run_at
```

------------------------------------------------------------------------

## 50. Relative Date Filters

Scheduled reports need filters such as:

``` text
this_month
last_month
this_quarter
last_quarter
this_year
last_year
last_30_days
```

Store the relative expression in the report. Resolve it at execution
time.

Do not convert `last_month` into fixed dates when the report is saved.

------------------------------------------------------------------------

## 51. Scheduled Security

For personal schedules:

``` text
run as current schedule owner
```

At execution time:

``` text
verify owner active
verify report access
verify dataset access
recalculate current security scope
execute
```

Do not preserve an old authorization snapshot.

For V1, scheduled results should be available only to the schedule
owner. Controlled organization-wide publication can be designed
separately.

------------------------------------------------------------------------

## 52. Notifications

Useful events:

``` text
Pivot completed
Export completed
Scheduled report completed
Scheduled report failed
Schedule disabled
Certified report updated
```

Notifications should reference an internal job/report object and avoid
embedding sensitive report data.

------------------------------------------------------------------------

## 53. Reporting Database Strategy

Long-term:

``` text
Operational MariaDB
↓
Incremental Sync / ETL
↓
Reporting MariaDB
↓
Pivot Engine
```

This protects transactional applications from heavy analytical queries.

A denormalized reporting table such as `rpt_sales` should contain both
stable keys and labels.

Example fields:

``` text
source_row_id
order_id
order_number
order_date
company_id
department_id
department_name
customer_id
customer_name
region_id
region_name
product_id
product_name
quantity
sales_amount
cost_amount
profit_amount
source_updated_at
```

------------------------------------------------------------------------

## 54. Reporting Index Strategy

Useful starting indexes:

``` text
(company_id, order_date)
(company_id, department_id, order_date)
(company_id, product_id, order_date)
(company_id, region_id, order_date)
(customer_id, order_date)
```

Do not create indexes for every possible Pivot combination.

Use execution logs and `EXPLAIN` to identify real workload patterns.

Mandatory security predicates should influence index order. If every
query begins with `company_id = ?`, company-first composite indexes are
often valuable.

------------------------------------------------------------------------

## 55. Incremental Refresh

Use a checkpoint:

``` text
last successful source timestamp
```

Flow:

``` text
read using old checkpoint
↓
load/update reporting rows
↓
commit successfully
↓
advance checkpoint
```

Never advance the checkpoint before the load succeeds.

Handle source changes using stable source-row identity and upsert logic.

Deletes/cancellations require a defined strategy such as soft-delete
status, change logs, CDC, or reconciliation.

------------------------------------------------------------------------

## 56. Data Freshness

Show users:

``` text
Data updated: Oct 6, 2026 12:05 PM
```

Keep:

``` text
dataset_version
```

separate from:

``` text
data_version
```

Dataset version means metadata/schema semantics changed.

Data version means business data changed.

This distinction is also useful for caching.

------------------------------------------------------------------------

## 57. Result Cache

A safe cache key should include:

``` text
dataset version
data version
normalized definition hash
security scope hash
```

Conceptually:

``` text
SHA256(
  datasetVersion
  + dataVersion
  + normalizedDefinition
  + securityScopeHash
)
```

Security scope must be part of the key to prevent cross-user data
leakage.

------------------------------------------------------------------------

## 58. Pre-Aggregation

For very large data, create summaries such as:

``` text
rpt_sales_daily
```

containing:

``` text
sales_date
company_id
department_id
product_id
region_id
quantity
sales_amount
cost_amount
profit_amount
order_count
```

The QueryPlanner can choose:

``` text
raw reporting table
or
daily summary
```

depending on required dimensions/measures.

Do not use preaggregated DISTINCT COUNT unless the mathematical
semantics are correct.

------------------------------------------------------------------------

## 59. High-Cardinality Fields

Metadata should identify fields such as:

``` text
Customer
Order Number
Transaction ID
```

with:

``` text
high_cardinality = 1
estimated_cardinality = ...
filter_mode = SEARCH
```

Such fields may be allowed for:

``` text
search filter
drill-down
detail export
```

while being disallowed as Pivot columns.

------------------------------------------------------------------------

## 60. Result Limits

Maintain separate limits for:

``` text
max aggregate tuples
max visible rows
max visible columns
max final cells
max detail-export rows
max runtime
max memory/file size
```

A query can return a manageable number of aggregate tuples but still
produce an unusably large final matrix.

SQL can use:

``` text
configured maximum + 1
```

to detect overflow without loading unlimited results.

------------------------------------------------------------------------

## 61. Performance Logging

Execution logs should capture:

``` text
dataset
report
source table
definition hash
security hash
cache hit
aggregate rows
result rows
result columns
result cells
query duration
PHP build duration
total duration
status/error
```

Separating DB time from PHP time is critical for optimization.

------------------------------------------------------------------------

## 62. Pivot Charts

Charts reuse the Pivot result.

Architecture:

``` text
Pivot Engine
├── Grid Adapter
├── Chart Adapter
├── Export
└── Dashboard
```

Do not create a separate chart SQL engine.

Initial chart types:

``` text
Column
Bar
Line
Area
Pie
```

All chart libraries/resources must be hosted locally.

------------------------------------------------------------------------

## 63. Generic Chart Model

Example:

``` json
{
  "categories": [
    "Sales",
    "Marketing",
    "Support"
  ],
  "series": [
    {
      "name": "Q1",
      "data": [28000, 12000, 9000]
    },
    {
      "name": "Q2",
      "data": [31000, 15000, 11000]
    }
  ]
}
```

`S3PivotChartAdapter` converts Pivot result structures into this neutral
chart model.

------------------------------------------------------------------------

## 64. Visualization Modes

Support:

``` text
GRID
CHART
GRID_CHART
```

Chart presentation settings are saved with the report but remain
conceptually separate from analytical calculations.

Charts should enforce stricter category limits than grids; thousands of
bars are not useful.

------------------------------------------------------------------------

## 65. Chart Hierarchy

For:

``` text
Department
→ Product
```

initial chart can show departments.

Click Sales:

``` text
Sales
→ Product A
→ Product B
→ Product C
```

Use the existing Pivot hierarchy and breadcrumb navigation.

No new analytical engine is required.

------------------------------------------------------------------------

## 66. Dashboards

Dashboards combine saved reports into widgets.

Initial widget types:

``` text
KPI
GRID
CHART
```

Recommended 12-column layout:

``` json
{
  "x": 0,
  "y": 0,
  "w": 6,
  "h": 4
}
```

Avoid arbitrary pixel-position layouts initially.

------------------------------------------------------------------------

## 67. Dashboard Security

Dashboard permission only determines whether the user can open the
dashboard.

Every widget still checks:

``` text
report permission
dataset permission
field permission
current row security
```

Dashboard sharing must never grant access to otherwise unauthorized
reports.

------------------------------------------------------------------------

## 68. Dashboard Performance

Do not launch 20--30 expensive queries simultaneously.

Use:

``` text
cache
small client request queue
progressive loading
```

Suggested priority:

``` text
1. KPI widgets
2. visible charts
3. visible grids
4. below-the-fold widgets
```

One failed widget must not break the whole dashboard.

------------------------------------------------------------------------

## 69. Dashboard Filters

Global filters can include:

``` text
Year
Region
Department
```

Effective execution:

``` text
Mandatory Security Filters
AND
Saved Report Filters
AND
Dashboard Filters
AND
Runtime Cross-Filters
```

A dashboard must never remove mandatory security.

For advanced use, reports can declare parameterized filters that
dashboards supply.

------------------------------------------------------------------------

## 70. Dashboard Certification and Snapshots

A certified dashboard should pin exact report versions where governance
requires stable official content.

A live dashboard means:

``` text
current report definitions
+
current data
+
current permissions
```

A snapshot means:

``` text
specific report versions
+
specific data version
+
specific execution time
+
frozen result
```

These are distinct concepts and should remain so.

------------------------------------------------------------------------

## 71. Audit Logging

Important actions include:

``` text
PIVOT_EXECUTE
DRILLDOWN
EXPORT_PIVOT
EXPORT_DETAIL

REPORT_CREATE
REPORT_UPDATE
REPORT_DELETE
REPORT_RESTORE
REPORT_CLONE
REPORT_SHARE
REPORT_UNSHARE
REPORT_CERTIFY
REPORT_UNCERTIFY
REPORT_OWNER_TRANSFER

DASHBOARD_CREATE
DASHBOARD_UPDATE
DASHBOARD_DELETE
DASHBOARD_SHARE
DASHBOARD_VIEW
DASHBOARD_EXPORT
DASHBOARD_CERTIFY
```

Avoid storing unnecessary sensitive business data in audit payloads.

------------------------------------------------------------------------

## 72. API Overview

Recommended endpoints include:

``` text
GET  /api/pivot/dataset/{code}
GET  /api/pivot/dataset/{code}/field/{field}/values

POST /api/pivot/execute
POST /api/pivot/drilldown

GET    /api/pivot/reports
GET    /api/pivot/reports/{id}
POST   /api/pivot/reports
PUT    /api/pivot/reports/{id}
DELETE /api/pivot/reports/{id}

POST /api/pivot/reports/{id}/clone
POST /api/pivot/reports/{id}/execute

GET  /api/pivot/reports/{id}/versions
GET  /api/pivot/reports/{id}/versions/{version}
POST /api/pivot/reports/{id}/restore/{version}

GET    /api/pivot/reports/{id}/permissions
POST   /api/pivot/reports/{id}/permissions
DELETE /api/pivot/reports/{id}/permissions/{permissionId}

POST /api/pivot/reports/{id}/certify
POST /api/pivot/reports/{id}/uncertify

POST /api/pivot/export
POST /api/pivot/export-detail

GET /api/pivot/jobs/{jobKey}
GET /api/pivot/jobs/{jobKey}/download
```

Dashboard APIs can follow the same REST conventions.

------------------------------------------------------------------------

## 73. Testing Strategy

### 73.1 Analytical tests

Test:

``` text
single row dimension
multiple row dimensions
single column dimension
multiple column dimensions
multiple measures
empty row axis
empty column axis
SUM
COUNT
AVG
MIN
MAX
row subtotal
column subtotal
grand total
null cell
zero value
negative value
duplicate labels with different keys
```

### 73.2 Security tests

Test:

``` text
unauthorized dataset
unauthorized field
invalid field code
invalid aggregation
invalid operator
tampered execution ID
cell from another execution
expired execution
permissions changed after execution
drilldown denied
detail export denied
shared report under different user scopes
dashboard widget without report access
filter-value metadata leakage
```

### 73.3 Performance tests

Measure:

``` text
query time
result-build time
memory
aggregate tuple count
final cell count
cache behavior
background-job concurrency
large export streaming
```

------------------------------------------------------------------------

## 74. Financial Precision

MariaDB `DECIMAL` should remain the primary aggregation mechanism for
financial values.

PHP floating-point arithmetic can introduce precision problems.

Before production financial reporting, choose one controlled strategy
for PHP-side state calculations:

``` text
BCMath decimal arithmetic
integer minor units where applicable
a validated decimal library
additional DB-side totals
```

A prototype may use PHP numeric operations, but this must be addressed
before financial-grade production use.

------------------------------------------------------------------------

## 75. Offline/Intranet Requirements

The production module must not depend on Internet access.

All resources must be local:

``` text
DHTMLX JS/CSS
jQuery if used
Pivot JavaScript
Pivot CSS
chart library
icons
images
fonts where required
export libraries
```

Do not use:

``` text
CDNs
Google Fonts
remote chart APIs
telemetry
runtime npm installation
external license checks requiring Internet
```

Build/install dependencies should be prepared offline according to
company deployment policy.

------------------------------------------------------------------------

## 76. Recommended Implementation Milestones

### Milestone 1 --- Basic End-to-End Pivot

Implement:

``` text
Department × Quarter × SUM Sales Amount
Year = 2026
```

Deliver:

``` text
metadata
validation
query planning
MariaDB aggregation
basic result builder
DHTMLX grid
```

### Milestone 2 --- Generic Multi-Level Engine

Implement:

``` text
multiple rows
multiple columns
multiple values
AxisTree
CellStore
aggregator states
subtotals
grand totals
multi-level headers
expand/collapse
```

Completion target:

``` text
Department → Product
×
Year → Quarter
×
Revenue + Profit
```

### Milestone 3 --- Interactive Pivot Designer

Implement:

``` text
field panel
Rows/Columns/Values/Filters zones
settings dialogs
date grouping
filter dialogs
undo/redo
Run
Save/Open UI
drag/drop after menu workflow works
```

### Milestone 4 --- Secure Drill-Down

Implement:

``` text
executionId + cellId
current permission recheck
current security scope
paginated detail grid
detail sorting
detail export
audit
```

### Milestone 5 --- Report Governance

Implement:

``` text
saved reports
sharing
version history
optimistic concurrency
favorites
report library
certified reports
```

### Milestone 6 --- Jobs, Export, Scheduling

Implement:

``` text
pivot_jobs
PHP worker
CSV/XLSX/PDF export
secure downloads
relative dates
scheduled reports
notifications
```

### Milestone 7 --- Performance

Implement progressively:

``` text
reporting tables
incremental refresh
data freshness
separate reporting DB
result cache
high-cardinality controls
preaggregated summaries
planner source selection
performance dashboard
```

### Milestone 8 --- Charts and Dashboards

Implement:

``` text
Chart Adapter
Grid/Chart/Grid+Chart
bar/column/line/area/pie
chart hierarchy navigation

dashboard tables
KPI/chart/grid widgets
12-column layout
permissions
cache-aware loading
global filters
```

### Milestone 9 --- Advanced Analytics

Future work:

``` text
Show Values As
% of row
% of column
% of grand total
running totals
ranking
calculated measures
conditional formatting
advanced Top N
slicers
advanced charts
```

------------------------------------------------------------------------

## 77. Recommended First Production Scope

Do not attempt every milestone before releasing useful functionality.

A strong first production version should include:

``` text
Dataset metadata/security layer
Generic multi-level Pivot engine
Department/Product/Date dimensions
SUM/COUNT/AVG/MIN/MAX
Explicit Run
Rows/Columns/Values/Filters designer
Subtotals/grand totals
Expand/collapse
Saved private reports
Secure drill-down
Pivot CSV/XLSX export
Execution limits
Execution/audit logs
Reporting table
Targeted MariaDB indexes
High-cardinality field controls
All-local resources
```

Then add enterprise sharing, certification, scheduling, caching, charts,
and dashboards in controlled releases.

------------------------------------------------------------------------

## 78. Final Architecture

``` text
                        INTERNAL BUSINESS SYSTEM
                                  │
                                  ▼
                         DHTMLX Pivot UI
                                  │
                Pivot Definition │
                                  ▼
                         PHP Pivot REST API
                                  │
             ┌────────────────────┼────────────────────┐
             │                    │                    │
             ▼                    ▼                    ▼
       Dataset Metadata      Authorization       Report Service
             │                    │                    │
             └──────────┬─────────┘                    │
                        ▼                              │
                 Security Scope                       │
                        │                              │
                        ▼                              │
                  Query Planner                       │
                        │                              │
                        ▼                              │
                  Query Builder                       │
                        │                              │
                        ▼                              │
                Reporting MariaDB                     │
                        │                              │
                 Aggregate Tuples                     │
                        │                              │
                        ▼                              │
               Generic Pivot Engine                   │
          ┌─────────────┼──────────────┐               │
          │             │              │               │
          ▼             ▼              ▼               │
       Row Tree     Column Tree     Cell Store          │
          └─────────────┼──────────────┘               │
                        ▼                              │
                  Pivot Result                         │
          ┌─────────────┼──────────────┬───────────────┤
          │             │              │               │
          ▼             ▼              ▼               ▼
        Grid          Chart         Drilldown        Export
          │             │              │               │
          └─────────────┼──────────────┴───────┐       │
                        ▼                      ▼       ▼
                  Saved Reports            Jobs/Schedule
                        │                      │
                        ▼                      ▼
                    Dashboards            Notifications
```

This architecture keeps analytical logic centralized while allowing the
same trusted Pivot result to power grids, charts, exports, drill-down,
scheduled reports, and dashboards.

------------------------------------------------------------------------

## 79. Conclusion

The proposed Pivot system should be treated as a reusable internal
analytics platform rather than as a large DHTMLX grid feature.

The most important architectural boundaries are:

``` text
Dataset metadata controls what can be analyzed.
SecurityService controls what data the user can see.
QueryPlanner controls what should be queried.
QueryBuilder controls how trusted metadata becomes SQL.
MariaDB performs source aggregation.
Pivot Result Engine controls hierarchy and totals.
DHTMLX controls presentation.
Report Service controls persistence/governance.
Job system controls expensive asynchronous work.
Reporting DB/caching controls scalability.
```

Keeping these responsibilities separate allows the system to start with
a relatively small Pivot implementation while providing a clear path
toward a secure enterprise BI platform for a large intranet environment.

The immediate implementation priority should remain:

``` text
Milestone 1
    ↓
Milestone 2 generic engine
    ↓
Milestone 3 designer
    ↓
Milestone 4 drill-down
```

before adding the larger governance, scheduling, optimization, and
dashboard layers.

That sequence delivers usable business value early without sacrificing
the long-term architecture.
