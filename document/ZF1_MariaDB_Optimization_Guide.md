# MariaDB 10.11 Optimization Guide for the ZF1 Business Platform

**Environment:** Zend Framework 1 (ZF1), PHP 7.4, MariaDB 10.11, DHTMLX 3.5, jQuery, 16 GB RAM dedicated database server, mechanical HDD, 100–500 concurrent application users.  
**Scope:** Email, Notifications, Messenger, Blog, CRM, Product, and other business modules.  
**Status:** Proposed baseline; validate before production rollout.  
**Date:** 2026-10-08

## 1. Executive summary

The highest-value improvements are (1) measure the slowest SQL and API endpoints; (2) fix inefficient SQL and composite indexes; (3) use server-side pagination and avoid excessive count/polling requests; (4) tune InnoDB memory and HDD flushing conservatively; (5) monitor connections, disk latency, locks, and temporary tables; and (6) consider SSD migration if physical I/O remains the limiting factor.

**Important:** 100–500 concurrent *application users* do not imply 100–500 simultaneously active MariaDB connections. HDD throughput, query duration, PHP worker counts, and request bursts determine actual DB concurrency.

## 2. Architecture and performance path

```text
Browser (DHTMLX 3.5 / jQuery)
    -> ZF1 REST controller (/api/email/getlist etc.)
    -> Service / repository / Zend_Db
    -> MariaDB optimizer + indexes + InnoDB buffer pool
    -> HDD storage
```

Measure browser request latency, PHP processing time, database-call latency, query execution, lock waits, and disk I/O separately. SQL call timing from PHP includes network and result-transfer overhead, not just server execution.

## 3. Proposed MariaDB 10.11 baseline

Use this as a **starting configuration**, not a universal performance optimum. Check the effective configuration, existing includes, storage/filesystem compatibility, and available memory first. A single HDD may support fewer IOPS than a RAID array. InnoDB settings and naming can differ across MariaDB releases; verify against the installed 10.11 build.

```ini
[mysqld]
# Connection management
max_connections = 150
thread_cache_size = 50
wait_timeout = 300
interactive_timeout = 600
max_allowed_packet = 64M

# InnoDB cache / logging
innodb_buffer_pool_size = 11G
innodb_log_buffer_size = 32M
innodb_log_file_size = 1G
innodb_flush_log_at_trx_commit = 1

# HDD-oriented flushing baseline
innodb_io_capacity = 100
innodb_io_capacity_max = 200
innodb_flush_method = O_DIRECT
innodb_file_per_table = ON

# Temporary tables: limits, not preallocated memory
tmp_table_size = 64M
max_heap_table_size = 64M

# Table caches
table_open_cache = 4000
table_definition_cache = 2000

# Slow query instrumentation (confirm names in installed build)
log_output = FILE
slow_query_log = ON
long_query_time = 0.5
log_queries_not_using_indexes = OFF

# Only enable after checking all host grants
# skip_name_resolve = ON
```

**Configuration notes:**

- **11G buffer pool:** an initial choice for a dedicated 16 GB server. Leave headroom for the OS, page cache, MariaDB global/per-connection memory, and monitoring. Reduce to 10G if memory pressure or swap appears.
- **150 max connections:** a safety ceiling, not a goal. More active HDD-bound queries may *decrease* throughput.
- **100/200 I/O capacity:** conservative illustrative values for HDD. Adjust using actual IOPS, read/write latency, and InnoDB flushing behavior.
- **Flush at commit = 1:** appropriate for durable business transactions. If binary logging is enabled for replication or point-in-time recovery, evaluate `sync_binlog=1` and the durability of the storage stack.
- **O_DIRECT:** test filesystem/storage support and behavior; do not apply blindly.
- **1G redo log:** can reduce checkpoint pressure at the cost of potentially longer recovery; monitor checkpoint activity.
- **64M temp-table limits:** can multiply with concurrent sessions; disk temporary tables have multiple causes and are not always eliminated by raising these values.
- **Table caches:** measure table-open misses and file descriptor limits before increasing further.
- **DNS resolution:** enabling `skip_name_resolve` may invalidate hostname-based grants; check accounts before changing.
- **No `innodb_buffer_pool_instances`:** not applicable to MariaDB 10.11.
- **Slow-log variable aliases:** MariaDB 10.11 supports the conventional `slow_query_log`/`long_query_time` names. Check `SHOW VARIABLES` for available names rather than mixing old and new variants.

## 4. Safe rollout

1. Record `SELECT VERSION();`, `SHOW GLOBAL VARIABLES;`, and key status counters.
2. Save existing configuration; verify restorable backups and recovery procedure.
3. Review `/etc/my.cnf`, `/etc/my.cnf.d/`, or distribution-specific include paths.
4. Test changes on staging with representative data and queries.
5. Check configuration compatibility and schedule a maintenance window.
6. Restart only if required, verify MariaDB health and logs, then measure again.
7. Roll back changes if latency, memory pressure, or error rates deteriorate.

```bash
sudo systemctl restart mariadb
sudo systemctl status mariadb
sudo journalctl -u mariadb -n 100 --no-pager
iostat -x 5
```

`iostat` is commonly supplied by the `sysstat` package. Disk indicators include `r/s`, `w/s`, `r_await`, `w_await`, `aqu-sz`, and `%util`. Look for sustained latency and queues; `%util` alone does not prove saturation.

## 5. Connection and memory monitoring

```sql
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Threads_connected','Threads_running','Max_used_connections',
  'Connections','Aborted_connects','Connection_errors_max_connections'
);
SHOW GLOBAL VARIABLES WHERE Variable_name IN (
  'max_connections','innodb_buffer_pool_size',
  'tmp_table_size','max_heap_table_size'
);
```

Example: 500 logged-in users might produce only 60 database-using requests at a given instant. Measure actual active connections and request concurrency. Avoid persistent ZF1 connections initially unless measurements justify them. Consider PHP worker capacity and Workerman's long-lived connections separately.

## 6. InnoDB and HDD monitoring

```sql
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Innodb_buffer_pool_read_requests','Innodb_buffer_pool_reads',
  'Innodb_buffer_pool_pages_free','Innodb_buffer_pool_wait_free',
  'Innodb_data_reads','Innodb_data_writes','Innodb_data_fsyncs',
  'Innodb_log_waits'
);
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Innodb_row_lock_waits','Innodb_row_lock_time',
  'Innodb_row_lock_time_avg','Innodb_row_lock_time_max'
);
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Created_tmp_tables','Created_tmp_disk_tables','Slow_queries'
);
```

Most status values are cumulative counters. Sample at fixed intervals and compute **deltas per second or minute**; account for server restarts. Investigate a high physical-read rate, sustained row-lock waits, disk temporary tables correlated with slow queries, and increasing log waits. A high buffer-pool hit ratio does not by itself prove queries are efficient.

## 7. Slow-query logging and investigation

Start with a threshold of 0.5 seconds, then adjust based on log volume and performance objectives. The slow log may expose sensitive query literals: restrict permissions and apply retention.

```sql
SHOW VARIABLES LIKE 'slow_query_log_file';
SHOW VARIABLES LIKE 'long_query_time';
SHOW GLOBAL STATUS LIKE 'Slow_queries';
```

```bash
mariadb-dumpslow -s t -t 20 /path/to/slow.log
```

Use `EXPLAIN` to inspect access paths, chosen index, estimated rows, and `Extra`. For actual execution statistics, MariaDB supports `ANALYZE FORMAT=JSON SELECT ...`; **ANALYZE executes the query**, so exercise caution with costly or modifying statements. Prioritize queries by *total load* (frequency × cost), not only single-query duration.

## 8. Composite indexes for business grids

Example schema (simplified):

```sql
CREATE TABLE emails (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  user_id INT NOT NULL,
  folder_id INT NOT NULL,
  subject VARCHAR(255),
  is_read TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;
```

Frequent inbox query:

```sql
SELECT id, subject, is_read, created_at
FROM emails
WHERE company_id = 10 AND user_id = 100 AND folder_id = 1
ORDER BY created_at DESC, id DESC
LIMIT 50;
```

Candidate index:

```sql
CREATE INDEX idx_email_list
ON emails (company_id, user_id, folder_id, created_at, id);
```

Equality columns lead, followed by sorting columns; InnoDB can scan in reverse order for descending results. Compare with existing indexes before adding: every extra index consumes HDD space and slows writes. A query filtering only `company_id` and `is_read` may need a different index; do not assume one composite index solves all queries. Validate with representative parameter values and `EXPLAIN`.

```sql
EXPLAIN SELECT id, subject, is_read, created_at
FROM emails
WHERE company_id=10 AND user_id=100 AND folder_id=1
ORDER BY created_at DESC, id DESC LIMIT 50;
```

Review `type`, `key`, `rows`, and `Extra`; `Using filesort` is not always unacceptable, but expensive repeated sorts merit attention.

## 9. DHTMLX 3.5 pagination and API design

Avoid `SELECT *` and loading entire result sets. For smart rendering, use a server endpoint such as:

```text
/api/email/getlist?posStart=0&count=50
```

Example JSON shape (adapt to your configured DHTMLX data loader):

```json
{
  "pos": 0,
  "total_count": 500000,
  "rows": [{"id": 1001, "data": ["Example subject", "2026-10-08"]}]
}
```

Offset pagination is easy to integrate, but deep offsets can scan many rows:

```sql
SELECT id, subject, created_at
FROM emails
WHERE user_id = 100
ORDER BY created_at DESC, id DESC
LIMIT 50 OFFSET 500000;
```

For cursor-based navigation, use the last displayed `(created_at, id)` pair:

```sql
SELECT id, subject, created_at
FROM emails
WHERE user_id = 100
  AND (created_at < '2026-10-08 10:00:00'
       OR (created_at = '2026-10-08 10:00:00' AND id < 5000))
ORDER BY created_at DESC, id DESC
LIMIT 50;
```

Test with `(user_id, created_at, id)` indexing. DHTMLX virtual scrolling conventionally uses offsets, so a cursor design requires an adapter or a different navigation pattern. Count queries can be expensive: avoid recalculating exact totals on every scroll; cache or compute separately when product behavior permits. Ensure tenant/company and user authorization constraints on every API query.

## 10. ZF1 application optimization

### 10.1 Parameter binding and selected columns

```php
$db = Zend_Db_Table::getDefaultAdapter();
$rows = $db->fetchAll(
    'SELECT id, subject, created_at FROM emails WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50',
    array($userId)
);
```

### 10.2 Avoid N+1 queries

Instead of fetching 50 emails and then querying the sender for each one, join the sender table once (with appropriate tenant and access controls):

```sql
SELECT e.id, e.subject, e.created_at, u.username AS sender_name
FROM emails e
LEFT JOIN users u ON u.id = e.sender_id
WHERE e.company_id = ? AND e.user_id = ?
ORDER BY e.created_at DESC, e.id DESC
LIMIT 50;
```

### 10.3 Time DB calls

```php
$start = microtime(true);
$rows = $db->fetchAll($sql, $params);
$elapsedMs = (microtime(true) - $start) * 1000;
error_log(sprintf('[DB] email.getlist: %.2f ms', $elapsedMs));
```

This measures the client-side call, not server-only SQL execution. Record API name, module, duration, and request ID. Do not log passwords, tokens, confidential SQL literals, or sensitive user content.

### 10.4 Zend_Db profiler (development or controlled diagnostics)

```php
$db = Zend_Db_Table::getDefaultAdapter();
$profiler = new Zend_Db_Profiler(true);
$db->setProfiler($profiler);
// Run application queries, then inspect:
$profiles = $profiler->getQueryProfiles();
if ($profiles !== false) {
    foreach ($profiles as $profile) {
        error_log(sprintf('SQL duration: %.4f s', $profile->getElapsedSecs()));
        // SQL text may be sensitive: do not log unredacted production queries.
    }
}
```

Use centrally initialized adapters; avoid opening fresh DB connections repeatedly within one request.

## 11. Transactions and write-heavy modules

Keep transactions short, group related writes atomically, and never hold DB locks while waiting for HTTP, email, or WebSocket operations.

```php
$db->beginTransaction();
try {
    $db->insert('messages', $messageData);
    $messageId = $db->lastInsertId();
    foreach ($recipientIds as $recipientId) {
        $db->insert('message_recipients', array(
            'message_id' => $messageId,
            'user_id' => $recipientId
        ));
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

For large recipient sets, test batched inserts. Avoid unnecessarily large transactions; batch large deletes/archival. For Messenger/Notification, an outbox pattern separates committed DB changes from eventual WebSocket delivery. Workerman should reconnect safely and use bounded DB connections. Redis is not required for this architecture.

## 12. Caching and archival

Good cache candidates: system/module configuration, department lists, relatively stable permissions (with correct invalidation), and carefully maintained summary counters. Dynamic email lists often benefit more from good indexes and pagination than broad caching. APCu is per PHP runtime/server; it is not a cross-server cache. Avoid caching access-controlled content without tenant/user scoping.

Archive old notifications, event/outbox records, audit logs, and other high-volume historical data according to retention and compliance rules. Test bulk deletes in bounded batches to reduce lock and HDD pressure.

## 13. Proposed internal monitoring dashboard

A PHP CLI collector scheduled every minute can record database status counters into a monitoring database or time-series store, while OS collection records HDD latency and queue depth. Avoid making the monitoring workload itself heavy.

```text
MariaDB SHOW GLOBAL STATUS + Linux iostat
        -> scheduled collector (PHP CLI)
        -> metrics storage
        -> administrator dashboard
```

Suggested dashboard tiles: connected threads, running threads, max-used connections, slow queries/min, InnoDB physical reads/sec, redo-log waits, row-lock waits, temporary disk tables/min, HDD read/write latency, and queue depth. Graph time trends and correlate with API endpoint latencies. Label sample/mock data clearly.

### Centralized SQL performance logger design

Use a shared ZF1 database access layer or controlled profiler wrapper to record:

- Timestamp, request ID, module and API endpoint (e.g. `/api/email/getlist`).
- SQL fingerprint/template (avoid raw sensitive values), elapsed time, rows returned if available.
- Correlation with request-level duration and errors.
- Slow-only or sampled capture in production, with bounded storage and retention.

Use least-privilege access for monitoring. Avoid a logging design that writes every SQL call synchronously to the same busy HDD, which can amplify the bottleneck.

## 14. Example monitoring queries

```sql
SHOW GLOBAL STATUS WHERE Variable_name IN (
 'Threads_connected','Threads_running','Max_used_connections','Slow_queries'
);
SHOW GLOBAL STATUS WHERE Variable_name IN (
 'Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests',
 'Innodb_data_reads','Innodb_data_writes','Innodb_data_fsyncs','Innodb_log_waits'
);
SHOW GLOBAL STATUS WHERE Variable_name IN (
 'Innodb_row_lock_waits','Innodb_row_lock_time',
 'Innodb_row_lock_time_avg','Innodb_row_lock_time_max'
);
SHOW GLOBAL STATUS WHERE Variable_name IN (
 'Created_tmp_tables','Created_tmp_disk_tables'
);
```

## 15. Phased implementation roadmap

| Phase | Activities | Acceptance evidence |
|---|---|---|
| 1. Baseline | Collect representative API, SQL, CPU, memory, HDD metrics | Recorded p50/p95 latency and peak workload |
| 2. SQL/indexes | Rank costly queries, inspect plans, add/replace tested indexes | Reduced rows examined and query latency |
| 3. Frontend/API | Paginate grids, reduce counts, eliminate N+1 and needless polling | Lower query rate and faster list loading |
| 4. MariaDB tuning | Test buffer pool, connections, HDD flushing, log settings | Improved throughput without swap or latency regressions |
| 5. Monitoring | Implement counters, dashboards, slow-query attribution | Detectable trends and actionable alerts |
| 6. Load test | Replay realistic 100–500-user behavior | Meets agreed latency/error targets |
| 7. Hardware decision | Review persistent HDD bottlenecks | Evidence-based SSD/NVMe upgrade decision |

## 16. Operational checklist

- [ ] Confirm exact MariaDB 10.11 build, CPU count, HDD layout/RAID, and database size.
- [ ] Confirm backups, restore test, and change rollback.
- [ ] Review active MariaDB settings and duplicated configuration entries.
- [ ] Measure max connections, active queries, swap, HDD latency and queue.
- [ ] Enable controlled slow-query logging with access controls and retention.
- [ ] Optimize top five queries by cumulative cost.
- [ ] Check index redundancy and write overhead.
- [ ] Review DHTMLX list counts, sorting, and deep pagination.
- [ ] Keep transactions short and external operations outside them.
- [ ] Validate security and tenant isolation for every optimized query.
- [ ] Load test before production deployment.
- [ ] Reassess SSD migration if HDD remains saturated.

## 17. Common mistakes

1. Setting `max_connections=500` simply because there are 500 users.
2. Copying SSD I/O settings to a single mechanical HDD.
3. Increasing memory buffers without measuring total memory or swap.
4. Creating one index per filtered column without studying composite access paths.
5. Using `SELECT *` for DHTMLX grids.
6. Repeating exact `COUNT(*)` on every grid scroll.
7. Treating a high buffer-pool hit ratio as proof that SQL is optimized.
8. Enabling full production SQL profiling with sensitive values.
9. Holding transactions open while Workerman or external services respond.
10. Tuning only server variables while ignoring query patterns and disk latency.

## 18. Reference documentation

- MariaDB Server documentation: https://mariadb.com/docs/server/
- MariaDB system variables: https://mariadb.com/kb/en/server-system-variables/
- MariaDB InnoDB system variables: https://mariadb.com/kb/en/innodb-system-variables/
- MariaDB EXPLAIN: https://mariadb.com/kb/en/explain/
- MariaDB ANALYZE statement: https://mariadb.com/kb/en/analyze-statement/
- MariaDB slow query log: https://mariadb.com/kb/en/slow-query-log-overview/
- MariaDB optimization and indexes: https://mariadb.com/kb/en/optimization-and-indexes/

**Final recommendation:** Start with measurement and the slowest real application queries. On HDD, reducing random I/O and excessive concurrent query work will usually yield more reliable improvements than increasing connection limits. Apply configuration changes gradually and validate them against representative production workloads.
