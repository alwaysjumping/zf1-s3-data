# MariaDB Index Optimization and `EXPLAIN` Guide

## For Zend Framework 1 + DHTMLX 3.5 + PHP + MariaDB Applications

## 1. Purpose

This document summarizes the index-performance concepts discussed for a
legacy application using:

-   Zend Framework 1 (ZF1)
-   DHTMLX 3.5
-   PHP
-   MariaDB
-   InnoDB tables in the usual case

The primary use case is optimizing DHTMLX grid/list queries that
commonly combine:

``` sql
WHERE ...
JOIN ...
GROUP BY ...
ORDER BY ...
LIMIT ...
```

The goal is **not to create as many indexes as possible**. The goal is
to choose a small, justified set of indexes that improves important read
queries without creating unnecessary write, storage, backup, and
maintenance costs.

------------------------------------------------------------------------

# 2. Why Index Design Must Consider the Whole Workload

Indexes can improve:

-   `SELECT`
-   `WHERE`
-   `JOIN`
-   `ORDER BY`
-   `GROUP BY`
-   `LIMIT` queries, especially when MariaDB can stop early

But every additional index also has a cost.

An index may increase the cost of:

-   `INSERT`
-   `UPDATE`
-   `DELETE`
-   disk/storage usage
-   InnoDB buffer-pool usage
-   backups and restores
-   schema changes
-   index maintenance

Therefore:

> Do not add an index merely because a column appears in a `WHERE`
> clause.

Always consider the actual queries, frequency, table size, data
distribution, selectivity, write volume, and `EXPLAIN` output.

------------------------------------------------------------------------

# 3. Single-Column Indexes vs Composite Indexes

Suppose the `tickets` table has these indexes:

``` sql
INDEX idx_customer (customer_id),
INDEX idx_status   (status),
INDEX idx_created  (created_at)
```

These are three independent B-tree indexes.

Compare that with:

``` sql
INDEX idx_customer_status_created
    (customer_id, status, created_at)
```

This is one composite index.

They are **not equivalent**.

## 3.1 Separate indexes

Separate indexes are flexible because different queries can
independently use:

``` sql
WHERE customer_id = ?
```

``` sql
WHERE status = ?
```

``` sql
WHERE created_at >= ?
```

MariaDB may also sometimes combine multiple separate indexes through
`index_merge`.

However, separate indexes do not create one combined ordering of:

``` text
customer_id
    ↓
status
    ↓
created_at
```

## 3.2 Composite index

A composite index:

``` sql
INDEX(customer_id, status, created_at)
```

is ordered primarily by `customer_id`, then by `status` within each
customer, and then by `created_at` within each customer/status
combination.

Conceptually:

``` text
customer_id   status    created_at
-----------   -------   -------------------
100           CLOSED    ...
100           OPEN      2026-09-30 18:00
100           OPEN      2026-09-30 17:30
100           OPEN      2026-09-30 17:00
101           CLOSED    ...
101           OPEN      ...
```

This can be extremely useful for:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

MariaDB may be able to:

``` text
find customer_id = 100
        ↓
find status = OPEN
        ↓
read created_at in index order
        ↓
return first 50
        ↓
STOP
```

This can avoid scanning and sorting a much larger candidate set.

------------------------------------------------------------------------

# 4. The Leftmost-Prefix Principle

For:

``` sql
INDEX(customer_id, status, created_at)
```

useful leftmost prefixes include:

``` text
(customer_id)

(customer_id, status)

(customer_id, status, created_at)
```

The index does **not generally replace** independent indexes for:

``` text
(status)

(created_at)

(status, created_at)
```

For example:

``` sql
WHERE status = 'OPEN'
```

does not provide the leading `customer_id` value.

Therefore, adding a composite index does not automatically mean all
related single-column indexes should be removed.

------------------------------------------------------------------------

# 5. What Is `index_merge`?

MariaDB normally chooses an access path for each table. When several
independent indexes can help one table, MariaDB may sometimes use
**Index Merge optimization** and combine results from multiple index
scans.

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

MariaDB might use only:

``` text
idx_customer
```

or only:

``` text
idx_status
```

or it may combine both through `index_merge`.

You do not normally need to instruct MariaDB to do this. The optimizer
estimates the available execution strategies and chooses the plan it
expects to be cheapest.

------------------------------------------------------------------------

# 6. Index Merge Intersection

Intersection is associated with `AND` conditions.

Example:

``` sql
WHERE customer_id = 100
  AND status = 'OPEN'
```

Imagine the indexes identify:

``` text
customer_id = 100
{1, 5, 8, 12, 20}

status = OPEN
{2, 5, 9, 12, 30}
```

The intersection is:

``` text
{5, 12}
```

`EXPLAIN` may show approximately:

``` text
type: index_merge
key: idx_customer,idx_status
Extra: Using intersect(idx_customer,idx_status); Using where
```

------------------------------------------------------------------------

# 7. Index Merge Union

Union is commonly relevant to `OR` conditions.

Example:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
   OR status = 'URGENT';
```

Conceptually:

``` text
customer_id = 100
{1, 5, 8}

status = URGENT
{3, 5, 10}

Union
{1, 3, 5, 8, 10}
```

`EXPLAIN` may indicate:

``` text
type: index_merge
key: idx_customer,idx_status
Extra: Using union(...)
```

------------------------------------------------------------------------

# 8. Index Merge Sort-Union

MariaDB may also use a sort-union strategy.

You may see:

``` text
Using sort_union(...)
```

Conceptually MariaDB obtains row identifiers from multiple index scans,
sorts/merges them, removes duplicates where required, and then retrieves
the corresponding rows.

This involves additional work compared with a straightforward targeted
index lookup.

------------------------------------------------------------------------

# 9. MariaDB Automatically Chooses Between Single Index and Index Merge

If `tickets` has:

``` sql
INDEX(customer_id),
INDEX(status),
INDEX(created_at)
```

MariaDB does **not automatically use all three**.

It may choose:

``` text
one index
```

or:

``` text
index_merge of two or more indexes
```

or:

``` text
another available composite index
```

or even:

``` text
a table scan
```

if its cost estimates indicate that is cheaper.

Example:

``` text
Total tickets:       1,000,000
customer_id = 100:         100
status = OPEN:         500,000
```

For:

``` sql
WHERE customer_id = 100
  AND status = 'OPEN'
```

using `idx_customer` to find approximately 100 rows and checking their
status may be cheaper than scanning a huge part of `idx_status` and
intersecting the results.

Therefore MariaDB might correctly choose:

``` text
type: ref
key: idx_customer
```

instead of:

``` text
type: index_merge
```

------------------------------------------------------------------------

# 10. `index_merge` Is Not Equivalent to a Composite Index

Consider:

``` sql
INDEX(customer_id);
INDEX(status);
INDEX(created_at);
```

and:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

Index Merge may help with:

``` text
customer_id = 100
AND
status = OPEN
```

but it does not automatically provide the result in:

``` text
created_at DESC
```

order.

MariaDB may still need:

``` text
Using filesort
```

A composite index:

``` sql
INDEX(customer_id, status, created_at)
```

may support:

``` text
WHERE
+
ORDER BY
+
LIMIT
```

as one access strategy.

This is particularly important for DHTMLX grids.

------------------------------------------------------------------------

# 11. Why `LIMIT` Makes Composite Indexes Important

Suppose a query finds 20,000 matching rows but displays only 20:

``` sql
ORDER BY created_at DESC
LIMIT 20;
```

Without a suitable ordering index, the execution can resemble:

``` text
find 20,000 matching rows
        ↓
sort
        ↓
return 20
```

With an appropriate composite index:

``` text
navigate to matching range
        ↓
read in requested order
        ↓
20 rows found
        ↓
STOP
```

This early-stop behavior can provide a major benefit for grid/list
screens.

------------------------------------------------------------------------

# 12. Index Merge Is Not Automatically Bad

Seeing:

``` text
type = index_merge
```

does not mean the query is poorly optimized.

Index Merge can be very useful for independent or dynamic search
conditions.

For example:

``` sql
WHERE phone = ?
   OR email = ?
```

with:

``` sql
INDEX(phone);
INDEX(email);
```

can reasonably benefit from separate indexes.

Creating:

``` sql
INDEX(phone, email)
```

is not an equivalent solution, because the composite index's leftmost
structure does not provide an independent lookup by `email` alone.

Index Merge is therefore often useful in advanced-search screens where
many optional filters can appear in different combinations.

------------------------------------------------------------------------

# 13. Selectivity Matters

Suppose `status` has only:

``` text
OPEN
CLOSED
```

and a 10-million-row table contains:

``` text
OPEN = 6,000,000 rows
```

Then:

``` sql
INDEX(status)
```

has low selectivity for `status='OPEN'`.

MariaDB may decide that using that index is not worthwhile.

Likewise, for:

``` sql
WHERE customer_id = 100
  AND status = 'OPEN'
```

if:

``` text
customer_id = 100 → 1,000 rows
status = OPEN     → 6,000,000 rows
```

MariaDB may prefer:

``` text
use customer_id index
        ↓
retrieve ~1,000 candidates
        ↓
check status
```

rather than scanning millions of `status` index entries and performing
an intersection.

------------------------------------------------------------------------

# 14. Composite Index Column Order

Column order matters.

For:

``` sql
WHERE customer_id = ?
  AND status = ?
ORDER BY created_at DESC
LIMIT 50;
```

a strong candidate is:

``` sql
INDEX(customer_id, status, created_at)
```

A useful starting principle is:

``` text
Equality predicates
        ↓
Range predicates
        ↓
Ordering considerations
```

But this is not an absolute formula.

The correct order depends on:

-   actual queries
-   data distribution
-   selectivity
-   joins
-   ordering
-   grouping
-   pagination
-   query frequency
-   write workload

Do not blindly put the "most selective column first" without considering
the complete access pattern.

------------------------------------------------------------------------

# 15. Range Conditions

Example:

``` sql
WHERE customer_id = 100
  AND created_at >= '2026-09-01'
ORDER BY created_at DESC
LIMIT 50;
```

A natural candidate is:

``` sql
INDEX(customer_id, created_at)
```

Here:

``` text
customer_id = equality
created_at  = range/order
```

But if the query also has:

``` sql
status = 'OPEN'
```

we need to evaluate whether:

``` sql
INDEX(customer_id, status, created_at)
```

or:

``` sql
INDEX(customer_id, created_at, status)
```

better matches the real workload.

------------------------------------------------------------------------

# 16. Redundant Indexes

Suppose you have:

``` sql
INDEX idx_customer(customer_id);
```

and later add:

``` sql
INDEX idx_customer_status_created
    (customer_id, status, created_at);
```

The single-column index may become redundant because the composite index
begins with:

``` text
customer_id
```

Similarly:

``` sql
INDEX(a);
INDEX(a,b);
INDEX(a,b,c);
```

contains substantial prefix overlap because:

``` text
(a,b,c)
├── prefix (a)
└── prefix (a,b)
```

However:

``` sql
INDEX(b)
```

is different. `(a,b,c)` does not generally replace an independent index
for:

``` sql
WHERE b = ?
```

## Important DROP INDEX rule

Do **not** drop an apparently redundant index based on one query.

Before a `DROP INDEX`, verify:

-   all important queries
-   actual `EXPLAIN` plans
-   index sizes
-   cardinality/selectivity
-   foreign-key requirements
-   write workload
-   MariaDB version
-   whether other application screens depend on that index

A drop recommendation should clearly state its assumptions and risks.

------------------------------------------------------------------------

# 17. Introduction to `EXPLAIN`

Use:

``` sql
EXPLAIN
SELECT ...
```

to see how MariaDB plans to execute a query.

Example:

``` sql
EXPLAIN
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

Typical columns include:

``` text
id
select_type
table
partitions
type
possible_keys
key
key_len
ref
rows
filtered
Extra
```

Not every MariaDB version or output format shows every field in exactly
the same way.

------------------------------------------------------------------------

# 18. `id`

`id` identifies a query block / `SELECT` operation.

A simple query commonly has:

``` text
id = 1
```

Queries involving subqueries or `UNION` may have multiple IDs.

Different IDs generally represent different query blocks, although
modern optimizer transformations mean `id` should not be treated as a
complete literal execution-order description.

------------------------------------------------------------------------

# 19. `select_type`

`select_type` describes the type of `SELECT`.

Common values include:

  Value                    General meaning
  ------------------------ ------------------------------------------------
  `SIMPLE`                 Simple SELECT without UNION/subquery structure
  `PRIMARY`                Outer/main SELECT
  `UNION`                  SELECT participating in a UNION
  `DEPENDENT UNION`        UNION branch dependent on outer query values
  `UNION RESULT`           Result combining UNION branches
  `SUBQUERY`               Subquery
  `DEPENDENT SUBQUERY`     Subquery dependent on an outer query
  `DERIVED`                Derived table
  `MATERIALIZED`           Materialized subquery
  `UNCACHEABLE SUBQUERY`   Subquery result cannot normally be reused
  `UNCACHEABLE UNION`      Similar situation involving UNION

Most straightforward DHTMLX list queries will usually show:

``` text
SIMPLE
```

------------------------------------------------------------------------

# 20. `table`

This identifies the table, alias, derived table, or intermediate result
described by that `EXPLAIN` row.

Example:

``` sql
SELECT *
FROM tickets t
JOIN users u
    ON u.id = t.assigned_user_id;
```

The plan may contain rows for:

``` text
t
u
```

This helps identify how each table is accessed and the join strategy.

------------------------------------------------------------------------

# 21. `partitions`

For partitioned tables, `partitions` indicates which partitions MariaDB
expects to access.

For ordinary non-partitioned application tables, this field is usually
not important.

------------------------------------------------------------------------

# 22. `type`: Access Method

`type` is one of the most important fields.

It describes how MariaDB accesses the table.

Common values include:

``` text
system
const
eq_ref
ref
fulltext
ref_or_null
index_merge
unique_subquery
index_subquery
range
index
ALL
```

There are specialized cases depending on MariaDB version and optimizer
behavior.

A rough learning hierarchy is:

``` text
More targeted
    │
    ├─ system
    ├─ const
    ├─ eq_ref
    ├─ ref
    ├─ range / specialized access
    ├─ index_merge   ← evaluate in context
    ├─ index
    └─ ALL
       │
Broader scanning
```

This is **not** a universal good/bad ranking. Row counts and actual work
matter.

------------------------------------------------------------------------

# 23. `type = system`

A special case where a table effectively contains only one row.

This is uncommon in normal application tables.

------------------------------------------------------------------------

# 24. `type = const`

A very targeted lookup.

Example:

``` sql
SELECT *
FROM users
WHERE id = 100;
```

with:

``` sql
PRIMARY KEY(id)
```

may produce approximately:

``` text
type: const
key: PRIMARY
rows: 1
```

MariaDB can resolve the unique value very efficiently.

------------------------------------------------------------------------

# 25. `type = eq_ref`

`eq_ref` commonly appears in joins where a `PRIMARY KEY` or suitable
unique key identifies at most one row for every row from a previous
table.

Example:

``` sql
SELECT t.id, u.name
FROM tickets t
JOIN users u
    ON u.id = t.assigned_user_id;
```

If `users.id` is a primary key:

``` text
ticket
   ↓
assigned_user_id
   ↓
users PRIMARY KEY
   ↓
one user
```

This is normally an efficient join access method.

------------------------------------------------------------------------

# 26. `type = ref`

`ref` is common for indexed equality lookups that may return multiple
rows.

Example:

``` sql
INDEX idx_customer(customer_id);
```

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100;
```

might show:

``` text
type: ref
key: idx_customer
ref: const
```

Many tickets may belong to the same customer, so this is not a unique
lookup.

------------------------------------------------------------------------

# 27. `type = ref_or_null`

This may be used for an indexed equality condition combined with `NULL`.

Example:

``` sql
WHERE assigned_user_id = 100
   OR assigned_user_id IS NULL
```

It is less common than `ref`, `range`, and `ALL`, but is useful to
recognize.

------------------------------------------------------------------------

# 28. `type = range`

`range` means MariaDB is using an index to access a range of key values.

Example:

``` sql
INDEX idx_created(created_at);
```

``` sql
SELECT *
FROM tickets
WHERE created_at >= '2026-09-01';
```

may show:

``` text
type: range
key: idx_created
```

Range access commonly appears with:

``` text
>
>=
<
<=
BETWEEN
IN (...)
```

and certain other indexable range/prefix conditions.

------------------------------------------------------------------------

# 29. `type = index_merge`

This means MariaDB is combining multiple index scans for the same table.

Example:

``` text
type: index_merge
key: idx_customer,idx_status
Extra: Using intersect(...)
```

Do not automatically treat this as a problem.

For important high-frequency queries, however, investigate whether a
suitable composite index could reduce more work, particularly when
`ORDER BY` and `LIMIT` are involved.

------------------------------------------------------------------------

# 30. `type = index`

This is frequently misunderstood.

``` text
type = index
```

generally means MariaDB is scanning an index.

Think:

``` text
ALL   = table scan
index = index scan
```

An index scan can be beneficial, particularly if it avoids a sort or the
index covers the query, but scanning millions of index entries can still
be expensive.

Always examine `rows` and `Extra`.

------------------------------------------------------------------------

# 31. `type = ALL`

`ALL` means a full table scan.

Example:

``` sql
SELECT *
FROM tickets
WHERE description LIKE '%server%';
```

may produce:

``` text
type: ALL
key: NULL
Extra: Using where
```

because a normal B-tree index cannot directly navigate to an arbitrary
substring beginning with `%`.

A full table scan on a large table and highly selective query deserves
investigation, but `ALL` is not automatically bad.

For a tiny table, or a query needing most rows, a table scan may be the
cheapest plan.

------------------------------------------------------------------------

# 32. `possible_keys`

`possible_keys` lists indexes MariaDB considers potentially useful for
table access.

Example:

``` text
possible_keys:
idx_customer,idx_status
```

This does **not** mean MariaDB uses both.

Think:

``` text
possible_keys = candidates
key           = chosen access index
```

------------------------------------------------------------------------

# 33. `key`

`key` shows the index MariaDB selected.

Example:

``` text
possible_keys: idx_customer,idx_status
key: idx_customer
```

means MariaDB considered both but selected `idx_customer`.

With `index_merge`, multiple index names may appear.

If:

``` text
key = NULL
```

no index is being used as that table's selected access path.

Do not immediately add an index; first understand why the optimizer
chose the plan.

------------------------------------------------------------------------

# 34. `key_len`

`key_len` indicates the length of the key portion MariaDB expects to
use.

This is particularly useful for composite indexes.

Suppose:

``` sql
INDEX(customer_id, status, created_at)
```

For:

``` sql
WHERE customer_id = ?
```

MariaDB may use only the first index part.

For:

``` sql
WHERE customer_id = ?
  AND status = ?
```

it may use a longer portion.

However, do not interpret `key_len` as a simple fixed number of columns.
Its byte length depends on:

-   data type
-   nullability
-   character set
-   index prefix definitions
-   internal representation

Always interpret it together with `SHOW CREATE TABLE` / `SHOW INDEX`.

------------------------------------------------------------------------

# 35. `ref`

`ref` tells you what value or column is compared against the chosen
index.

For:

``` sql
WHERE customer_id = 100
```

you may see:

``` text
ref: const
```

For:

``` sql
FROM customers c
JOIN tickets t
    ON t.customer_id = c.id
```

you may see a reference corresponding to:

``` text
c.id
```

This is useful for understanding joins.

------------------------------------------------------------------------

# 36. `rows`

`rows` is an optimizer estimate of how many rows/index entries MariaDB
expects to examine or access at that step.

It is **not simply the number of rows returned to the application**.

For example:

``` text
rows = 100000
```

and:

``` sql
LIMIT 50
```

describe different concepts.

Because `rows` is estimated, inaccurate or stale statistics can
contribute to poor optimizer choices.

------------------------------------------------------------------------

# 37. `filtered`

`filtered` estimates the percentage of examined rows expected to survive
additional filtering.

Example:

``` text
rows     = 1000
filtered = 10.00
```

A rough conceptual estimate is:

``` text
1000 × 10% ≈ 100 rows
```

Suppose:

``` text
type: ref
key: idx_customer
rows: 100000
filtered: 1.00
```

for:

``` sql
WHERE customer_id = ?
  AND status = 'OPEN'
```

MariaDB may be retrieving a large customer set and then discarding most
rows based on `status`.

That is a reason to investigate whether a composite index is justified.

------------------------------------------------------------------------

# 38. `Extra`

`Extra` provides important additional execution information.

Common values relevant to this application include:

``` text
Using where
Using index
Using index condition
Using filesort
Using temporary
Using intersect(...)
Using union(...)
Using sort_union(...)
```

Multiple values may appear together.

------------------------------------------------------------------------

# 39. `Extra: Using where`

`Using where` means MariaDB applies a `WHERE` condition to candidate
rows.

This is extremely common and is **not an error**.

Example:

``` sql
WHERE customer_id = 100
  AND status = 'OPEN'
```

If MariaDB accesses rows using only:

``` sql
INDEX(customer_id)
```

it may then check:

``` sql
status = 'OPEN'
```

as additional filtering.

Do not try to eliminate `Using where` merely to make the plan look
cleaner.

------------------------------------------------------------------------

# 40. `Extra: Using index`

This commonly indicates a **covering index**.

Suppose:

``` sql
INDEX idx_customer_status(customer_id, status);
```

and:

``` sql
SELECT customer_id, status
FROM tickets
WHERE customer_id = 100;
```

The index itself contains everything required by the query.

MariaDB may avoid fetching the full table row.

Do not confuse:

``` text
type = index
```

with:

``` text
Extra = Using index
```

They mean different things.

------------------------------------------------------------------------

# 41. `Extra: Using index condition`

This indicates Index Condition Pushdown (ICP).

Conceptually:

``` text
scan index
    ↓
evaluate additional condition at index level
    ↓
discard unsuitable index entries
    ↓
fetch fewer full rows
```

This can reduce unnecessary row access.

It is different from a covering-index `Using index`.

------------------------------------------------------------------------

# 42. `Extra: Using filesort`

`Using filesort` means MariaDB must perform an additional sort rather
than obtaining rows directly in the required order from an index.

Example:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
ORDER BY created_at DESC
LIMIT 50;
```

with only:

``` sql
INDEX(customer_id)
```

may execute conceptually as:

``` text
find customer rows
        ↓
sort by created_at
        ↓
return 50
```

A candidate:

``` sql
INDEX(customer_id, created_at)
```

may allow MariaDB to read the matching customer rows directly in
`created_at` order.

Important:

> `Using filesort` does not necessarily mean MariaDB writes a physical
> file to disk.

It describes an extra sorting operation. Whether the work stays in
memory or requires disk-related processing depends on the situation.

For DHTMLX grids, `Using filesort` becomes especially interesting when
the matching row set is large and the query also uses `LIMIT`.

------------------------------------------------------------------------

# 43. `Extra: Using temporary`

`Using temporary` indicates that MariaDB needs an internal temporary
table for some part of query processing.

It can occur with operations involving:

-   `GROUP BY`
-   `DISTINCT`
-   `ORDER BY`
-   `UNION`
-   other query structures

It is not automatically a problem.

The important questions are:

-   How many rows are involved?
-   How frequently does the query run?
-   How large is the temporary result?
-   Could a useful index naturally support the required grouping/order?

------------------------------------------------------------------------

# 44. `Using temporary; Using filesort`

Both may appear together.

Example:

``` sql
SELECT customer_id, COUNT(*) AS total
FROM tickets
WHERE created_at >= '2026-01-01'
GROUP BY customer_id
ORDER BY total DESC;
```

Because `total` is a calculated aggregate, additional temporary
processing and sorting may be perfectly reasonable.

The goal is not to eliminate every occurrence of:

``` text
Using temporary
Using filesort
```

The goal is to determine whether the work is necessary and whether its
cost is acceptable.

------------------------------------------------------------------------

# 45. Index Merge Information in `Extra`

With:

``` text
type: index_merge
```

`Extra` may indicate:

``` text
Using intersect(...)
```

``` text
Using union(...)
```

or:

``` text
Using sort_union(...)
```

This identifies the Index Merge strategy.

For an important grid query, then ask:

-   How many rows does the plan examine?
-   How selective are the conditions?
-   Is the query frequent?
-   Is there an `ORDER BY`?
-   Is there a `LIMIT`?
-   Is there `Using filesort`?
-   Would a composite index reduce substantially more work?
-   What write/storage cost would that index add?
-   Would it make another index redundant?

------------------------------------------------------------------------

# 46. `possible_keys = NULL`

Example:

``` text
possible_keys: NULL
key: NULL
type: ALL
```

Possible reasons include:

-   no relevant index exists
-   predicate is not index-friendly
-   query structure prevents useful index access
-   expressions/conversions interfere
-   most of the table is needed anyway
-   another access strategy is cheaper

Example:

``` sql
WHERE YEAR(created_at) = 2026
```

can prevent straightforward use of a normal index on `created_at`.

A more index-friendly form is often:

``` sql
WHERE created_at >= '2026-01-01'
  AND created_at <  '2027-01-01'
```

This expresses a direct range over the indexed column.

------------------------------------------------------------------------

# 47. `key = NULL` Even When `possible_keys` Exists

You may see:

``` text
possible_keys: idx_status
key: NULL
type: ALL
```

This can be completely reasonable.

Suppose:

``` text
Total rows: 1,000,000
OPEN rows:    900,000
```

For:

``` sql
SELECT *
FROM tickets
WHERE status = 'OPEN';
```

using `idx_status` may require retrieving most rows through a secondary
index.

A table scan may be cheaper.

So:

``` text
possible_keys != selected key
```

is an important concept.

------------------------------------------------------------------------

# 48. Functions on Indexed Columns

Be careful with expressions such as:

``` sql
WHERE YEAR(created_at) = 2026
```

A normal B-tree index on `created_at` may not be usable as efficiently
as with:

``` sql
WHERE created_at >= '2026-01-01'
  AND created_at <  '2027-01-01'
```

When analyzing a slow query, look not only at indexes but also at
whether the query exposes indexed values in a form the optimizer can use
efficiently.

------------------------------------------------------------------------

# 49. EXPLAIN for Joins

Example:

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

An illustrative plan might look like:

  table   type       key                    ref                 rows Extra
  ------- ---------- ---------------------- ----------------- ------ ---------------
  `t`     `ref`      `idx_status_created`   `const`              500 `Using where`
  `c`     `eq_ref`   `PRIMARY`              `t.customer_id`        1 

Conceptually:

``` text
tickets
   ↓
find OPEN tickets
   ↓
customer_id
   ↓
customers PRIMARY KEY
   ↓
one matching customer
```

For joins, analyze each table's `type`, `key`, `ref`, and `rows`.

------------------------------------------------------------------------

# 50. Interpreting `rows` Across Joins

Suppose:

``` text
tickets:
rows = 1000

customers:
rows = 1
```

MariaDB roughly expects to access around one customer for each candidate
ticket.

A plan with very large estimates on multiple joined tables deserves
investigation.

Do not simply multiply every `rows` value and treat the result as an
exact execution count, but use the estimates to identify potentially
expensive join paths.

------------------------------------------------------------------------

# 51. `EXPLAIN` Is an Estimate

Traditional:

``` sql
EXPLAIN SELECT ...
```

shows the optimizer's planned/estimated strategy.

Fields such as:

``` text
rows
filtered
```

are estimates.

This distinction is important:

``` text
EXPLAIN
   ↓
what MariaDB estimates/plans

Actual execution analysis
   ↓
what actually happened
```

MariaDB versions provide additional analysis facilities, including
`ANALYZE`-style execution analysis. The exact syntax and fields should
be checked against the MariaDB version in use before relying on
version-specific behavior.

------------------------------------------------------------------------

# 52. `EXPLAIN FORMAT=JSON`

For deeper investigation, MariaDB versions supporting the relevant
syntax can provide structured plan information:

``` sql
EXPLAIN FORMAT=JSON
SELECT ...
```

For everyday application tuning, traditional `EXPLAIN` is an excellent
starting point.

First become comfortable with:

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

Then use JSON output when more optimizer detail is necessary.

------------------------------------------------------------------------

# 53. Recommended Reading Order for an EXPLAIN Plan

For a DHTMLX grid query, read `EXPLAIN` approximately in this order:

``` text
1. table
       ↓
Which table is being accessed?

2. type
       ↓
How is MariaDB accessing it?

3. possible_keys
       ↓
Which indexes were candidates?

4. key
       ↓
Which index was selected?

5. key_len
       ↓
How much of that index participates?

6. ref
       ↓
What values/columns drive the lookup?

7. rows
       ↓
How many candidates are estimated?

8. filtered
       ↓
How much additional filtering is expected?

9. Extra
       ↓
Using where?
Using index?
Using index condition?
Using filesort?
Using temporary?
Index Merge?
```

Then compare the plan with:

``` text
WHERE
JOIN
GROUP BY
ORDER BY
LIMIT
```

and the exact index definitions.

------------------------------------------------------------------------

# 54. Example of a Plan Worth Investigating

Query:

``` sql
SELECT *
FROM tickets
WHERE customer_id = 100
  AND status = 'OPEN'
ORDER BY created_at DESC
LIMIT 50;
```

Suppose `EXPLAIN` says:

``` text
table:         tickets
type:          ALL
possible_keys: idx_customer,idx_status
key:           NULL
rows:          4500000
filtered:      0.20
Extra:         Using where; Using filesort
```

Questions to investigate include:

-   Why does MariaDB prefer a scan?
-   How selective are `customer_id` and `status`?
-   Are optimizer statistics representative?
-   Are data types compatible?
-   Are functions or conversions interfering?
-   Would a composite index match the filtering and ordering?
-   How frequently does this query execute?
-   What would another index cost on writes?

Do not immediately add an index until the reason for the plan is
understood.

------------------------------------------------------------------------

# 55. Example of a More Promising Grid Plan

Suppose the table has:

``` sql
INDEX idx_customer_status_created
    (customer_id, status, created_at)
```

and the plan is approximately:

``` text
table:         tickets
type:          ref
possible_keys: idx_customer_status_created
key:           idx_customer_status_created
rows:          85
filtered:      100.00
Extra:         Using where
```

with no:

``` text
Using filesort
```

For a `LIMIT 50` grid, this is a promising pattern because MariaDB may
be able to:

``` text
find customer/status range
        ↓
read created_at in index order
        ↓
obtain 50 rows
        ↓
stop
```

Actual performance should still be measured.

------------------------------------------------------------------------

# 56. Do Not Optimize `Extra` Alone

This:

``` text
Using filesort
```

does not automatically justify another index.

Example:

``` text
rows = 15
Extra = Using filesort
```

Sorting 15 rows may be trivial.

Creating a large composite index that must be maintained on every write
may cost much more than the sort it eliminates.

Always consider query frequency and workload.

------------------------------------------------------------------------

# 57. Do Not Optimize `type` Alone

Likewise:

``` text
type = ALL
rows = 20
```

may be perfectly fine.

And:

``` text
type = ref
rows = 2000000
Extra = Using filesort
```

may still be expensive.

Evaluate:

``` text
access method
+
estimated rows
+
filtering
+
sorting/grouping
+
query frequency
+
actual runtime
+
write cost
```

------------------------------------------------------------------------

# 58. DHTMLX Grid-Specific Optimization Strategy

A DHTMLX grid often combines:

``` text
filtering
+
sorting
+
pagination
```

Therefore, do not optimize only:

``` sql
WHERE ...
```

Analyze the entire query:

``` sql
SELECT ...
FROM ...
WHERE ...
ORDER BY ...
LIMIT ...
```

A composite index that appears only slightly better for filtering may be
much better overall if it also provides the required ordering and allows
MariaDB to stop after a small `LIMIT`.

------------------------------------------------------------------------

# 59. Deep OFFSET Pagination

A query such as:

``` sql
ORDER BY created_at DESC
LIMIT 100000, 50;
```

can remain expensive even with a useful index because MariaDB may still
need to traverse/skip a large number of entries before returning the
requested page.

Index optimization helps, but does not completely eliminate the inherent
cost of deep offset pagination.

Large DHTMLX grids should therefore be reviewed separately for
pagination strategy if deep pages are common.

------------------------------------------------------------------------

# 60. A Practical Index Review Process

For each important table, collect:

``` sql
SHOW CREATE TABLE tickets;
```

``` sql
SHOW INDEX FROM tickets;
```

Then collect the actual important queries:

``` sql
SELECT ...
FROM tickets
WHERE ...
JOIN ...
GROUP BY ...
ORDER BY ...
LIMIT ...;
```

and:

``` sql
EXPLAIN
SELECT ...;
```

Also record:

``` text
Approximate total rows
Rows inserted/updated/deleted per day
Most common DHTMLX screen/query
Slowest observed response time
MariaDB version
Storage engine
```

Then analyze in this order:

``` text
Query semantics
      ↓
WHERE / JOIN predicates
      ↓
Equality vs range predicates
      ↓
ORDER BY / GROUP BY
      ↓
LIMIT / pagination
      ↓
Existing index candidates
      ↓
EXPLAIN access path
      ↓
Estimated rows/selectivity
      ↓
filesort / temporary work
      ↓
Potential composite index
      ↓
Existing-index redundancy
      ↓
Read benefit vs write/storage cost
```

------------------------------------------------------------------------

# 61. Index Recommendation Checklist

Before recommending a new index, ask:

1.  Is this query important and frequent?
2.  How many rows does the table contain?
3.  How selective are the predicates?
4.  Which existing index does MariaDB choose?
5.  Is `index_merge` being used?
6.  How many rows are estimated?
7.  Is there `Using filesort`?
8.  Is there `Using temporary`?
9.  Can one composite index help `WHERE` and `ORDER BY` together?
10. Can `LIMIT` stop early with the proposed index?
11. Does the proposed index duplicate an existing prefix?
12. Will it materially increase write cost?
13. Does another important query need a different column order?

Only then should a new index be proposed.

------------------------------------------------------------------------

# 62. DROP INDEX Safety Checklist

Before recommending:

``` sql
DROP INDEX index_name ON tickets;
```

verify:

-   the exact current table definition
-   all existing indexes
-   all important queries
-   relevant `EXPLAIN` plans
-   foreign-key requirements
-   uniqueness requirements
-   whether the index is used by another screen/report/API
-   whether a larger composite index truly replaces its useful leftmost
    access
-   workload characteristics
-   expected rollback procedure

Prefer statements such as:

> `idx_customer` appears to be a redundancy candidate because
> `(customer_id, status, created_at)` has the same leftmost prefix. Do
> not drop it until the remaining workload and constraints have been
> checked.

rather than blindly issuing a `DROP INDEX`.

------------------------------------------------------------------------

# 63. Compact `EXPLAIN` Reference

  -----------------------------------------------------------------------
  Field                               Main question
  ----------------------------------- -----------------------------------
  `id`                                Which query block?

  `select_type`                       What kind of SELECT is this?

  `table`                             Which table/derived result?

  `partitions`                        Which partitions are accessed?

  `type`                              How is MariaDB accessing this
                                      table?

  `possible_keys`                     Which indexes could potentially
                                      help?

  `key`                               Which index was actually selected?

  `key_len`                           How much of the selected key
                                      participates?

  `ref`                               What value/column drives the index
                                      lookup?

  `rows`                              How many rows/index entries are
                                      estimated?

  `filtered`                          What percentage is expected to
                                      survive additional filtering?

  `Extra`                             What additional work or
                                      optimization is involved?
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 64. Key Rules to Remember

## Rule 1

Three single-column indexes:

``` sql
INDEX(customer_id);
INDEX(status);
INDEX(created_at);
```

are **not equivalent** to:

``` sql
INDEX(customer_id, status, created_at);
```

## Rule 2

MariaDB automatically decides whether to use:

``` text
one index
index_merge
a composite index
an index scan
a table scan
```

based on its cost estimates.

## Rule 3

`index_merge` is not automatically bad.

It is particularly useful for independent or dynamic conditions.

## Rule 4

For important grid queries, a purpose-built composite index can be
better than Index Merge because it may support:

``` text
WHERE
+
ORDER BY
+
LIMIT
```

together.

## Rule 5

`Using filesort` does not automatically mean disk I/O and does not
automatically justify another index.

## Rule 6

`Using temporary` is not automatically bad.

Its importance depends on the amount of work and query frequency.

## Rule 7

`type=ALL` is not automatically bad, and `type=ref` is not automatically
good.

Always consider `rows`, `filtered`, `Extra`, frequency, and actual
runtime.

## Rule 8

Do not drop apparently redundant indexes without checking the complete
workload and constraints.

## Rule 9

For DHTMLX grids, optimize the complete access pattern:

``` text
WHERE + JOIN + ORDER BY/GROUP BY + LIMIT
```

rather than looking at each condition in isolation.

## Rule 10

The best index design is not the design with the most indexes.

It is the smallest practical index set that provides strong performance
for the important workload while keeping write and maintenance costs
acceptable.

------------------------------------------------------------------------

# 65. Recommended Next Step

The next step is to analyze a real table one query at a time.

Provide:

``` sql
SHOW CREATE TABLE tickets;
```

``` sql
SHOW INDEX FROM tickets;
```

the actual DHTMLX query:

``` sql
SELECT ...
```

and:

``` sql
EXPLAIN
SELECT ...;
```

plus approximate table size, write volume, MariaDB version, and storage
engine.

For each query, the analysis should cover:

-   best index candidate
-   composite-index column order
-   existing-index redundancy
-   possibility and usefulness of `index_merge`
-   `WHERE` optimization
-   `JOIN` optimization
-   `ORDER BY` optimization
-   `GROUP BY` optimization
-   `LIMIT` behavior
-   filesort
-   temporary tables
-   expected `EXPLAIN`
-   read benefit
-   write/storage cost
-   assumptions and risks before any `DROP INDEX`
