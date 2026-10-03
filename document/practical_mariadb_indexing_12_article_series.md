# Practical MariaDB Indexing

## A 12-Part Series for Application Developers

**Audience:** PHP/MariaDB developers, application maintainers, and
technical readers who know SQL but are not necessarily database
specialists.

**Series approach:** Each article uses practical application queries,
with a recurring `tickets` example resembling the filtering, sorting,
joining, and pagination patterns found in administrative grids and
business applications.

> **Version note:** MariaDB optimizer behavior can vary by server
> version, storage engine, statistics, data distribution, configuration,
> and query shape. Examples in this series teach principles rather than
> promise one exact plan. Verify production decisions against your
> actual MariaDB version, schema, workload, and `EXPLAIN`/`ANALYZE`
> results.

------------------------------------------------------------------------

# Article 1 --- MariaDB Indexing Fundamentals: Why More Indexes Are Not Always Better

Indexes are among the most effective tools for improving database
performance. They are also one of the easiest things to overuse.

Consider a ticket-management table:

``` sql
CREATE TABLE tickets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    status      VARCHAR(20) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;
```

Without a useful index, this query may require MariaDB to inspect a
large part of the table:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100;
```

Adding an index changes the available access path:

``` sql
CREATE INDEX idx_customer
    ON tickets(customer_id);
```

A B-tree index keeps indexed values in an ordered structure. MariaDB can
navigate to the relevant part of the index instead of treating every row
as an equally likely candidate. For InnoDB, ordinary indexes are B-tree
indexes, and B-tree indexes support equality, range comparisons, and
useful leftmost-prefix behavior.

## The hidden cost of an index

An index is not free.

When the application executes:

``` sql
INSERT INTO tickets (...);
```

MariaDB must update the table and every affected index. The same
principle applies to indexed values changed by `UPDATE`, and index
entries must be removed during `DELETE`.

Indexes therefore trade additional storage and write work for faster
access to selected data.

A table with many unnecessary indexes can suffer from:

-   slower writes;
-   greater storage requirements;
-   more buffer-pool pressure;
-   larger backups;
-   more work during schema maintenance;
-   additional optimizer choices that provide little practical value.

The correct objective is not "index every searchable column." It is to
build a **small, justified set of indexes for the important workload**.

## Primary, unique, and ordinary indexes

A primary key uniquely identifies a row:

``` sql
PRIMARY KEY (id)
```

A unique index enforces uniqueness:

``` sql
UNIQUE INDEX uk_ticket_number(ticket_number)
```

An ordinary index does not require unique values:

``` sql
INDEX idx_status(status)
```

A low-cardinality column such as `status` can still be useful in an
index, but its usefulness depends on data distribution and the complete
query. If nearly every ticket is `OPEN`, an index on `status` alone may
provide little filtering benefit for `status='OPEN'`.

## Start with queries, not columns

Suppose the application frequently executes:

``` sql
SELECT id, subject, created_at
FROM tickets
WHERE customer_id = ?
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

The indexing question is not merely:

> Should `customer_id`, `status`, and `created_at` each be indexed?

The better question is:

> What access path lets MariaDB locate the relevant rows, return them in
> the required order, and stop as early as possible?

That question leads naturally to composite indexes, which are the
subject of the next article.

## Key takeaways

-   Indexes can dramatically improve reads, but they have write and
    storage costs.
-   Index design should start from important queries and workloads.
-   Low selectivity does not automatically make an index useless, but it
    changes its value.
-   Avoid both extremes: no useful indexes and "index everything."
-   Always validate important changes with the real schema and execution
    plan.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Getting Started with Indexes
    Guide**\
    https://mariadb.com/docs/server/mariadb-quickstart-guides/mariadb-indexes-guide
2.  MariaDB Server Documentation --- **Essentials of an Index Guide**\
    https://mariadb.com/docs/server/mariadb-quickstart-guides/essentials-of-an-index-guide
3.  MariaDB Server Documentation --- **Storage Engine Index Types**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/storage-engine-index-types
4.  MariaDB Server Documentation --- **CREATE INDEX**\
    https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/create-index

------------------------------------------------------------------------

# Article 2 --- Single-Column vs. Composite Indexes

A common indexing pattern is:

``` sql
INDEX idx_customer(customer_id),
INDEX idx_status(status),
INDEX idx_created(created_at)
```

At first glance, this seems to cover a query using all three columns:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

But three independent indexes are not equivalent to:

``` sql
INDEX idx_customer_status_created
    (customer_id, status, created_at)
```

## Independent indexes

Each single-column index creates its own ordered structure.

`idx_customer` is useful for locating customer values. `idx_status` is
organized by status. `idx_created` is organized by creation time.

MariaDB may choose one of them, and in some cases it may combine
multiple indexes with Index Merge. But the three indexes do not create
one combined ordering of customer, status, and date.

## Composite ordering

A composite index:

``` sql
INDEX(customer_id, status, created_at)
```

is ordered conceptually as:

``` text
customer_id
    └── status
          └── created_at
```

For one customer and one status, the matching index entries are already
organized by `created_at`.

That is particularly useful for:

``` sql
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 50
```

because one index can potentially assist filtering, ordering, and early
termination.

## The leftmost-prefix principle

For:

``` sql
INDEX(customer_id, status, created_at)
```

useful leading prefixes include:

``` text
(customer_id)
(customer_id, status)
(customer_id, status, created_at)
```

It does not generally provide the same direct lookup as:

``` sql
INDEX(status)
```

for:

``` sql
WHERE status = 'OPEN';
```

Nor does it automatically replace:

``` sql
INDEX(created_at)
```

for queries driven solely by date.

This is why index removal requires workload analysis.

## Is `INDEX(customer_id)` now redundant?

Maybe.

If you have both:

``` sql
INDEX idx_customer(customer_id)
```

and:

``` sql
INDEX idx_customer_status_created
    (customer_id, status, created_at)
```

the first index overlaps the leftmost prefix of the second.

That makes `idx_customer` a **redundancy candidate**, not an automatic
drop.

Before removing it, check:

-   other queries;
-   foreign-key and uniqueness requirements;
-   index sizes;
-   actual execution plans;
-   write volume;
-   whether the wider index creates materially different costs.

## The design lesson

Single-column indexes provide flexibility. Composite indexes provide an
intentionally ordered path for a query pattern.

Good production schemas often use a mixture of both rather than choosing
one philosophy exclusively.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
2.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
3.  MariaDB Server Documentation --- **Storage Engine Index Types**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/storage-engine-index-types

------------------------------------------------------------------------

# Article 3 --- How MariaDB Chooses an Index

Developers create indexes, but the MariaDB optimizer normally decides
which access path to use.

Suppose `tickets` has:

``` sql
INDEX idx_customer(customer_id),
INDEX idx_status(status),
INDEX idx_created(created_at)
```

and the query is:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN';
```

MariaDB might choose `idx_customer`, `idx_status`, an Index Merge plan,
another suitable index, or even a table scan.

## Why doesn't MariaDB always use every matching index?

Imagine:

``` text
Total tickets:        1,000,000
customer_id = 100:          100
status = OPEN:          500,000
```

`idx_customer` produces a very small candidate set. MariaDB can retrieve
approximately 100 candidates and test their `status`.

Scanning a large part of `idx_status` merely to intersect it with those
100 customer rows may cost more.

So this can be a sensible plan:

``` text
type: ref
key: idx_customer
```

The fact that `idx_status` exists does not mean using it is beneficial.

## Statistics and estimates

The optimizer works from estimates about data distribution and access
costs. This is why `EXPLAIN` fields such as `rows` and `filtered`
matter.

If estimates do not reflect reality, the optimizer can make a choice
that looks surprising when compared with observed runtime.

This is also why performance tuning should not be reduced to "the
optimizer should use index X."

## When a table scan can be correct

Suppose 900,000 of 1,000,000 tickets are `OPEN`:

``` sql
SELECT *
FROM tickets
WHERE status = 'OPEN';
```

An index on `status` exists, but using it may still require MariaDB to
retrieve most of the table through secondary-index lookups.

A table scan can be cheaper.

Therefore:

``` text
possible_keys: idx_status
key: NULL
type: ALL
```

is not automatically evidence of a broken optimizer.

## Avoid index hints as a first response

MariaDB provides `USE INDEX`, `FORCE INDEX`, and related mechanisms, but
forcing a plan should not be the first reaction to a surprising
`EXPLAIN`.

First understand:

-   the data distribution;
-   statistics;
-   the query;
-   the available indexes;
-   why the optimizer estimates one path to be cheaper.

Hints can become fragile as the table grows and data distributions
change.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Optimization and Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes
2.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
3.  MariaDB Server Documentation --- **USE INDEX**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/use-index
4.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain

------------------------------------------------------------------------

# Article 4 --- Understanding MariaDB `index_merge`

Index Merge allows MariaDB to combine more than one index scan for one
table.

Suppose:

``` sql
INDEX idx_customer(customer_id);
INDEX idx_status(status);
```

and:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN';
```

MariaDB may use one index, or it may choose an Index Merge strategy.

## Intersection

For an `AND` condition, imagine:

``` text
customer_id = 100
{1, 5, 8, 12, 20}

status = OPEN
{2, 5, 9, 12, 30}
```

The common row identifiers are:

``` text
{5, 12}
```

An `EXPLAIN` plan can report:

``` text
type: index_merge
key: idx_customer,idx_status
Extra: Using intersect(...); Using where
```

## Union

For:

``` sql
WHERE customer_id = 100
   OR status = 'URGENT'
```

the optimizer may combine qualifying identifiers from separate indexes.

Conceptually:

``` text
customer index → {1, 5, 8}
status index   → {3, 5, 10}

union          → {1, 3, 5, 8, 10}
```

## Sort-union and sort-intersection

MariaDB also documents sort-based Index Merge strategies. Their
availability and use can depend on the optimizer strategy and server
version/configuration. In particular, MariaDB documents
`index_merge_sort_intersection` as an optimizer switch and notes that
sort-intersection has higher overhead while supporting a broader set of
conditions than the original rowid-ordered intersection strategy.

This is a good example of why optimizer details should be verified
against the actual MariaDB version.

## Why Index Merge is not a composite index

For:

``` sql
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

an Index Merge may identify matching rows, but it does not inherently
create the combined ordering:

``` text
customer_id → status → created_at
```

A composite index:

``` sql
INDEX(customer_id, status, created_at)
```

may let MariaDB find the matching range and read rows in the desired
date order.

## When Index Merge is valuable

Index Merge can be useful for dynamic search interfaces.

Imagine optional filters for:

-   customer;
-   status;
-   email;
-   phone;
-   date;
-   priority.

Creating every possible composite combination would cause index
explosion. A carefully selected set of independent indexes plus a few
high-value composites can be a better design.

The goal is not to eliminate `index_merge`. The goal is to recognize
when a frequent query deserves a more purpose-built access path.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
2.  MariaDB Server Documentation --- **index_merge sort_intersection**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/index_merge-sort_intersection
3.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain

------------------------------------------------------------------------

# Article 5 --- Mastering MariaDB `EXPLAIN`

`EXPLAIN` shows how MariaDB plans to execute a query.

``` sql
EXPLAIN
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

The traditional output includes fields such as:

``` text
id
select_type
table
type
possible_keys
key
key_len
ref
rows
filtered
Extra
```

## `id`

Identifies a query block. Simple queries commonly show `1`; subqueries
and unions can introduce additional query blocks.

## `select_type`

Describes the role of the SELECT. Common values include `SIMPLE`,
`PRIMARY`, `UNION`, `SUBQUERY`, `DEPENDENT SUBQUERY`, `DERIVED`, and
`MATERIALIZED`.

## `table`

Identifies the table, alias, derived table, or intermediate result
described by the row.

## `type`

Describes the access method. Common values include:

``` text
system
const
eq_ref
ref
ref_or_null
range
index_merge
index
ALL
```

A rough learning model is that `const`, `eq_ref`, and targeted `ref`
access are often highly selective, while `index` and `ALL` involve
broader scanning. But never rank a plan by `type` alone.

## `possible_keys`

Indexes that MariaDB considers candidates for the access.

## `key`

The index actually chosen. `NULL` means no index was selected as that
table's access path.

## `key_len`

Shows the length of the index key portion used. This is useful for
composite-index analysis, but its byte value depends on data types,
nullability, character sets, and index definitions.

## `ref`

Shows the value or column used for an index comparison. `const`
indicates a constant; a joined column can appear when the lookup is
driven by another table.

## `rows`

An estimate of rows/index entries MariaDB expects to examine at that
step. It is not simply the final result count.

## `filtered`

An estimated percentage expected to survive additional filtering.

A rough conceptual estimate:

``` text
rows = 1000
filtered = 10%

≈ 100 rows survive
```

## `Extra`

This field exposes important additional behavior.

### `Using where`

Additional filtering is applied. This is normal and not an error.

### `Using index`

Often indicates a covering-index situation: required values can be read
from the index without retrieving the full row.

### `Using index condition`

Indicates Index Condition Pushdown, where conditions can be evaluated at
the storage-engine/index level before unnecessary full-row retrieval.

### `Using filesort`

MariaDB performs an additional sorting operation rather than simply
reading rows in the required order from an index. The name does not
guarantee a physical disk file is used.

### `Using temporary`

An internal temporary table is used for part of processing, often in
queries involving grouping, distinctness, ordering, or other operations.

### Index Merge details

`Using intersect(...)`, `Using union(...)`, and `Using sort_union(...)`
can describe Index Merge processing.

## A practical reading order

For application tuning, read:

``` text
table
  ↓
type
  ↓
possible_keys
  ↓
key
  ↓
key_len
  ↓
ref
  ↓
rows
  ↓
filtered
  ↓
Extra
```

Then compare the plan against the query's `WHERE`, `JOIN`, `GROUP BY`,
`ORDER BY`, and `LIMIT`.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain
2.  MariaDB Server Documentation --- **EXPLAIN FORMAT=JSON**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain-format-json
3.  MariaDB Server Documentation --- **ANALYZE and EXPLAIN Statements**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements
4.  MariaDB Server Documentation --- **SHOW EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/show/show-explain

------------------------------------------------------------------------

# Article 6 --- Designing the Correct Composite Index Column Order

Creating a composite index is easy. Choosing the right column order is
the difficult part.

Consider:

``` sql
SELECT id, subject, created_at
FROM tickets
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 50;
```

A natural candidate is:

``` sql
INDEX(customer_id, status, created_at)
```

Why?

The first two predicates are equalities:

``` text
customer_id = ?
status      = ?
```

After MariaDB narrows the search to one customer/status combination, the
remaining index entries are ordered by `created_at`.

## Equality, range, and ordering

A useful starting heuristic is:

``` text
equality columns
      ↓
range columns
      ↓
ordering/grouping considerations
```

But it is only a starting point.

For:

``` sql
WHERE customer_id = ?
  AND created_at >= ?
ORDER BY created_at DESC
```

this index is natural:

``` sql
INDEX(customer_id, created_at)
```

Now add:

``` sql
AND status = 'OPEN'
```

Should the index be:

``` sql
(customer_id, status, created_at)
```

or:

``` sql
(customer_id, created_at, status)
```

The answer depends on the real workload. The first can narrow by both
equalities before the date range/order. The second may align differently
with a query driven by date. Data distribution and other important
queries matter.

## "Most selective first" is incomplete advice

Selectivity matters, but composite-index ordering is not simply a
contest to put the most selective column first.

You must consider:

-   which columns are equality predicates;
-   where a range begins;
-   the required sort order;
-   joins;
-   grouping;
-   leftmost-prefix reuse;
-   other frequent queries.

A column order that is excellent for one query can be poor for another.

## Design indexes for query families

Instead of optimizing one isolated statement, identify families:

``` text
Customer ticket list
Customer + status list
Recent customer tickets
Open-ticket dashboard
Global status report
```

Then select indexes that cover the important families with minimal
overlap.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
2.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
3.  MariaDB Server Documentation --- **Storage Engine Index Types**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/storage-engine-index-types

------------------------------------------------------------------------

# Article 7 --- Indexes for `ORDER BY`, `GROUP BY`, and `LIMIT`

Indexes are not only about finding rows. Their ordering can also reduce
sorting and grouping work.

## `ORDER BY`

Consider:

``` sql
SELECT id, subject, created_at
FROM tickets
WHERE customer_id = 100
ORDER BY created_at DESC
LIMIT 50;
```

With only:

``` sql
INDEX(customer_id)
```

MariaDB can locate the customer's tickets, but may need to sort them by
`created_at`.

`EXPLAIN` may report:

``` text
Using filesort
```

A composite:

``` sql
INDEX(customer_id, created_at)
```

can potentially provide the required ordering directly.

## Why `LIMIT` changes the economics

Suppose the customer has 100,000 tickets but the screen shows only 50.

A sort-oriented plan may conceptually do:

``` text
find many candidates
    ↓
sort
    ↓
return 50
```

An index-ordered plan may do:

``` text
navigate to customer range
    ↓
read newest entries
    ↓
50 rows
    ↓
stop
```

That early stopping can make a major difference.

## `GROUP BY`

Consider:

``` sql
SELECT status, COUNT(*)
FROM tickets
WHERE customer_id = 100
GROUP BY status;
```

An index beginning with:

``` sql
(customer_id, status)
```

places one customer's rows in status order and may reduce grouping work.
MariaDB's `EXPLAIN` documentation also describes
`Using index for group-by` for plans where an index efficiently resolves
grouping or distinctness.

Not every aggregate can avoid temporary work. For example:

``` sql
SELECT customer_id, COUNT(*) AS total
FROM tickets
GROUP BY customer_id
ORDER BY total DESC;
```

orders by a computed aggregate. Extra processing and sorting can be
entirely reasonable.

## Do not fear every filesort

If a query sorts 15 rows once per hour, adding a large index just to
eliminate `Using filesort` may be a net loss.

Always compare the avoided read/sort cost with the permanent
write/storage cost of another index.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain
2.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
3.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes

------------------------------------------------------------------------

# Article 8 --- Indexing JOINs Correctly

Indexes become even more important when a query joins tables.

Suppose:

``` sql
SELECT
    t.id,
    t.subject,
    c.name
FROM tickets t
INNER JOIN customers c
    ON c.id = t.customer_id
WHERE t.status = 'OPEN'
ORDER BY t.created_at DESC
LIMIT 50;
```

Assume:

``` sql
customers:
PRIMARY KEY(id)
```

and `tickets.customer_id` stores the relationship.

## `eq_ref`

If MariaDB starts from qualifying tickets and looks up each customer by
`customers.id`, `EXPLAIN` may show:

``` text
table: c
type: eq_ref
key: PRIMARY
ref: t.customer_id
rows: 1
```

This is a highly targeted join lookup: one matching customer row is
expected for each preceding ticket.

## `ref`

If MariaDB starts from a customer and finds all tickets belonging to
that customer through:

``` sql
INDEX(customer_id)
```

the ticket access may use:

``` text
type: ref
```

because one customer can have many tickets.

## Composite indexes for join + filtering

Suppose another important query is:

``` sql
SELECT ...
FROM customers c
JOIN tickets t
    ON t.customer_id = c.id
WHERE c.id = ?
  AND t.status = 'OPEN';
```

A candidate:

``` sql
INDEX(customer_id, status)
```

can support both the relationship and the ticket filter.

But for a global query starting with `status`, a different order may be
more useful.

This is why join indexes cannot be designed from the `ON` clause alone.

## Read every table's EXPLAIN row

For joins, inspect:

``` text
table
type
key
ref
rows
Extra
```

for every participating table.

A well-indexed first table does not compensate for a huge repeated scan
on the second table.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain
2.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
3.  MariaDB Server Documentation --- **Getting Started with Indexes
    Guide**\
    https://mariadb.com/docs/server/mariadb-quickstart-guides/mariadb-indexes-guide

------------------------------------------------------------------------

# Article 9 --- Pagination and Large DHTMLX-Style Data Grids

Administrative grids often combine filters, sorting, and pagination:

``` sql
SELECT id, subject, status, created_at
FROM tickets
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 0, 50;
```

A well-designed composite index can make the first page fast.

But deep pagination introduces another problem:

``` sql
LIMIT 100000, 50;
```

## Why deep offsets are expensive

Even if MariaDB can read rows in index order, it may still need to
traverse or skip a very large number of entries before reaching the
requested page.

An index helps avoid worse work such as a large sort, but it does not
make a huge offset free.

## Keyset or seek pagination

Where the user experience permits it, pagination can instead use the
last value from the previous page.

For example, with a stable ordering:

``` sql
SELECT id, subject, created_at
FROM tickets
WHERE customer_id = ?
  AND status = ?
  AND created_at < ?
ORDER BY created_at DESC
LIMIT 50;
```

In real systems, a tie-breaker such as `id` may also be required to make
ordering deterministic when several rows share the same timestamp.

A corresponding index might be designed around:

``` sql
(customer_id, status, created_at, id)
```

depending on the exact query and version behavior.

## Grid optimization checklist

For every major grid, capture:

-   base filters;
-   user-selectable filters;
-   sort columns;
-   default sort;
-   page size;
-   typical page depth;
-   total-count query, if any;
-   query frequency.

Do not assume the same index can efficiently support every
user-selectable sort column. Optimizing the most common/default workflow
is often more practical than creating an index for every possible grid
state.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
2.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
3.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain

------------------------------------------------------------------------

# Article 10 --- Finding Redundant and Unnecessary Indexes

As applications evolve, tables often accumulate indexes.

A schema may eventually contain:

``` sql
INDEX idx_a(a),
INDEX idx_ab(a, b),
INDEX idx_abc(a, b, c),
INDEX idx_b(b)
```

The first three overlap heavily.

Because `(a,b,c)` has useful leftmost prefixes beginning with:

``` text
(a)
(a,b)
```

`idx_a` and `idx_ab` deserve review.

But `idx_b` is different. `(a,b,c)` does not generally replace a direct
lookup beginning with `b`.

## Why redundant indexes matter

Every redundant index consumes:

-   disk space;
-   memory/cache resources;
-   write work;
-   backup/restore time;
-   maintenance effort.

Removing a genuinely redundant index can therefore improve write
performance without harming reads.

## Why `DROP INDEX` is risky

An index that looks redundant from one query may be important to:

-   another report;
-   an API endpoint;
-   a background job;
-   a foreign-key relationship;
-   a uniqueness requirement;
-   a query whose optimizer prefers the narrower index.

Before removal, collect:

``` sql
SHOW CREATE TABLE tickets;
SHOW INDEX FROM tickets;
```

and the important query workload.

Then compare `EXPLAIN` plans.

## Safe language for an index review

Instead of:

> Drop `idx_customer`.

A responsible recommendation is:

> `idx_customer` appears to be a redundancy candidate because the wider
> `(customer_id, status, created_at)` index has the same leading column.
> Verify the remaining workload, constraints, and production plans
> before removal.

Index cleanup should be evidence-driven.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
2.  MariaDB Server Documentation --- **Getting Started with Indexes
    Guide**\
    https://mariadb.com/docs/server/mariadb-quickstart-guides/mariadb-indexes-guide
3.  MariaDB Server Documentation --- **CREATE INDEX**\
    https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/create-index
4.  MariaDB Server Documentation --- **Optimization and Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes

------------------------------------------------------------------------

# Article 11 --- A Repeatable Workflow for Diagnosing Slow MariaDB Queries

Index tuning becomes much easier when the investigation follows a
repeatable process.

## Step 1: Get the real schema

``` sql
SHOW CREATE TABLE tickets;
```

Do not work from an abbreviated column list. Data types, nullability,
keys, storage engine, and constraints matter.

## Step 2: Get the current indexes

``` sql
SHOW INDEX FROM tickets;
```

You need to know what MariaDB already has before proposing another
index.

## Step 3: Capture the real query

Use the actual production query shape:

``` sql
SELECT ...
FROM ...
JOIN ...
WHERE ...
GROUP BY ...
ORDER BY ...
LIMIT ...;
```

Small changes can alter the best index.

## Step 4: Run `EXPLAIN`

``` sql
EXPLAIN
SELECT ...;
```

Read:

``` text
type
possible_keys
key
key_len
ref
rows
filtered
Extra
```

## Step 5: Understand the workload

Record:

``` text
Approximate row count
Rows added/updated/deleted per day
Query frequency
Slowest observed response time
MariaDB version
Storage engine
```

A query running 100 times per second deserves different treatment from
one running once a week.

## Step 6: Identify the access pattern

Break the query into:

``` text
Equality predicates
Range predicates
JOIN relationships
GROUP BY
ORDER BY
LIMIT
```

Then compare that pattern with the ordered columns of existing indexes.

## Step 7: Consider alternatives

Possible outcomes include:

-   existing index is already good;
-   statistics or query structure need attention;
-   a composite index is justified;
-   separate indexes plus Index Merge are acceptable;
-   a table scan is reasonable;
-   pagination strategy is the real bottleneck;
-   a redundant index can eventually be removed.

## Step 8: Measure, don't guess

`EXPLAIN` is a plan estimate. MariaDB also provides `ANALYZE`-style
facilities for obtaining execution information. Use version-appropriate
tools and test changes under realistic conditions.

A good tuning process ends with evidence, not merely a new
`CREATE INDEX` statement.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain
2.  MariaDB Server Documentation --- **ANALYZE and EXPLAIN Statements**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements
3.  MariaDB Server Documentation --- **EXPLAIN FORMAT=JSON**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain-format-json
4.  MariaDB Server Documentation --- **SHOW EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/show/show-explain
5.  MariaDB Server Documentation --- **Optimization and Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes

------------------------------------------------------------------------

# Article 12 --- Complete Case Study: Optimizing a Ticket Grid

Let us combine the series into one practical example.

Assume:

``` sql
CREATE TABLE tickets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    status      VARCHAR(20) NOT NULL,
    priority    TINYINT UNSIGNED NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_customer(customer_id),
    INDEX idx_status(status),
    INDEX idx_created(created_at)
) ENGINE=InnoDB;
```

The application's main grid runs:

``` sql
SELECT
    id,
    customer_id,
    status,
    priority,
    subject,
    created_at
FROM tickets
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 50;
```

## Stage 1: Existing possibilities

MariaDB can consider:

``` text
idx_customer
idx_status
```

It may use one index and filter the other condition. It may consider
Index Merge. `idx_created` provides date ordering globally, but it does
not inherently group that ordering by the requested customer and status.

An illustrative plan might be:

``` text
type: ref
key: idx_customer
rows: 12000
filtered: 10
Extra: Using where; Using filesort
```

This is not an actual prediction without the real data. It is a
diagnostic example.

The important observation is:

``` text
customer lookup
    ↓
many candidate rows
    ↓
status filtering
    ↓
date sort
    ↓
LIMIT 50
```

## Stage 2: Candidate composite index

The query strongly suggests testing:

``` sql
CREATE INDEX idx_customer_status_created
    ON tickets(customer_id, status, created_at);
```

The desired access path becomes:

``` text
customer_id = ?
      ↓
status = ?
      ↓
created_at order
      ↓
first 50
      ↓
stop
```

A favorable `EXPLAIN` might show the composite as `key`, a much smaller
row estimate, and no `Using filesort`.

But the actual plan must be measured.

## Stage 3: Review existing indexes

After adding and validating the composite, should these be dropped?

``` sql
idx_customer
idx_status
idx_created
```

Not automatically.

### `idx_customer`

This is a strong redundancy candidate because the new index begins with
`customer_id`.

### `idx_status`

The composite does not begin with `status`, so a global query such as:

``` sql
WHERE status = 'OPEN'
```

may still need `idx_status`.

### `idx_created`

The composite does not begin with `created_at`, so global date-range or
global chronological queries may still need `idx_created`.

The correct answer requires the rest of the workload.

## Stage 4: Add another grid

Suppose a dashboard executes:

``` sql
SELECT id, subject, priority, created_at
FROM tickets
WHERE status = 'OPEN'
ORDER BY priority DESC, created_at DESC
LIMIT 100;
```

Now the index requirements differ.

Do not automatically create:

``` sql
INDEX(status, priority, created_at)
```

until you know:

-   how frequently the dashboard runs;
-   how many tickets are open;
-   whether sorting the candidate set is actually expensive;
-   write volume;
-   whether the proposed index overlaps another important index.

The case study demonstrates a central lesson:

> Index design is workload design, not query-by-query index
> accumulation.

## Stage 5: Deep pagination

If the grid eventually requests:

``` sql
LIMIT 100000, 50
```

the composite index still helps, but the large offset remains work.

At that point, consider whether the UI can use seek/keyset pagination
based on the ordering columns.

## Stage 6: Final validation

Before production rollout:

``` sql
SHOW CREATE TABLE tickets;
SHOW INDEX FROM tickets;
EXPLAIN SELECT ...;
```

Compare before and after plans, measure actual response time, and
observe write behavior.

If your MariaDB version supports the appropriate `ANALYZE` facilities,
use them carefully in a safe environment to compare estimates with
execution behavior.

## Final lesson

A high-quality MariaDB index review asks five questions:

1.  What are the important query patterns?
2.  How selective are their predicates?
3.  Can one ordered index support filtering, ordering, and early `LIMIT`
    termination?
4.  Which existing indexes remain necessary for other workloads?
5.  Is the read benefit worth the permanent write and storage cost?

That approach produces fewer, better indexes---and a database that
remains easier to understand as the application grows.

------------------------------------------------------------------------

## References and Source Documents

1.  MariaDB Server Documentation --- **Getting Started with Indexes
    Guide**\
    https://mariadb.com/docs/server/mariadb-quickstart-guides/mariadb-indexes-guide
2.  MariaDB Server Documentation --- **Compound (Composite) Indexes**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/compound-composite-indexes
3.  MariaDB Server Documentation --- **Building the best INDEX for a
    given SELECT**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select
4.  MariaDB Server Documentation --- **EXPLAIN**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain
5.  MariaDB Server Documentation --- **ANALYZE and EXPLAIN Statements**\
    https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements
6.  MariaDB Server Documentation --- **index_merge sort_intersection**\
    https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/index_merge-sort-intersection

------------------------------------------------------------------------

# Series Editorial Checklist

Before publishing each article:

-   Verify examples against the MariaDB version range the publication
    intends to support.
-   Keep optimizer claims conditional where data distribution can change
    the plan.
-   Distinguish estimated `EXPLAIN` behavior from measured execution.
-   Avoid declaring an index redundant from a single query.
-   State assumptions before suggesting `DROP INDEX`.
-   Prefer official MariaDB documentation in the references.
-   When an article describes version-sensitive optimizer behavior,
    identify the relevant version/configuration.
-   Test SQL examples on a representative non-production environment
    before presenting benchmark numbers.
