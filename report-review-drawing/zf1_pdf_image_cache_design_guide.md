# ZF1 PDF-to-Image Cache Design and Management Guide

**Environment:** Zend Framework 1, PHP 7.4, MariaDB, CentOS/Linux  
**Purpose:** Secure server-side PDF-to-image rendering with reusable cache, concurrency control, automatic invalidation, and scheduled cleanup.

---

## 1. Overview

The application converts protected PDF pages into images for browser viewing instead of exposing the original PDF directly.

Each PDF version has its own cache directory identified by a SHA-256 hash.

```text
Protected PDF
     |
     v
ZF1 authorization
     |
     v
PDF Cache Service
     |
     +-- cached page exists -> return it
     |
     +-- page missing -> render -> cache -> return
```

The cache should be stored **outside the public web root**.

```text
data/
└── pdf_cache/
    ├── <sha256-A>/
    │   ├── .cache.lock
    │   ├── metadata.json
    │   ├── page_0001.jpg
    │   ├── page_0001.jpg.lock
    │   └── page_0002.jpg
    └── <sha256-B>/
        ├── .cache.lock
        ├── metadata.json
        └── page_0001.jpg
```

The hash directory is an internal cache identifier. It is **not an authorization mechanism**.

## 2. Main Goals

The cache design should provide:

1. No direct browser access to the original PDF.
2. One isolated cache directory for each PDF version.
3. Automatic invalidation when PDF content changes.
4. On-demand page rendering.
5. Protection against duplicate simultaneous rendering.
6. Atomic publication of completed images.
7. Safe cache cleanup.
8. Expiration of unused caches.
9. Maximum disk-space control.
10. Cleanup of abandoned temporary files.
11. Separation of normal HTTP rendering from maintenance work.
12. Authorization before every protected page is served.

## 3. PDF Hash Strategy

Use a SHA-256 hash of the PDF content:

```php
$hash = hash_file('sha256', $pdfPath);
```

When the PDF changes, its hash changes and it naturally receives a new cache directory.

### Important optimization

Do **not** calculate `hash_file()` on every page request for large PDFs. Calculate the hash once when the PDF is imported, uploaded, created, or replaced, and store it with the document record.

```sql
ALTER TABLE documents
ADD COLUMN pdf_hash CHAR(64) NULL
COMMENT 'SHA-256 hash of the current PDF content';
```

Then page requests reuse the stored hash.

## 4. On-Demand Rendering

Do not necessarily convert every page when the document is opened.

```text
Request page 1
      |
      v
Is page_0001.jpg cached?
      |
   +--+--+
   |     |
  YES    NO
   |     |
return   render
           |
         cache
           |
         return
```

This reduces CPU usage, first-page latency, and disk consumption for large PDFs.

## 5. Two Levels of Locking

Use a **cache-level lock** and **page-level render locks**.

### Cache-level lock

`.cache.lock` coordinates normal cache use with cleanup. Normal page use obtains a shared lock:

```php
flock($cacheLock, LOCK_SH);
```

Multiple viewers can hold shared locks simultaneously. Cleanup attempts an exclusive non-blocking lock:

```php
flock($cacheLock, LOCK_EX | LOCK_NB);
```

If the cache is active, cleanup skips it.

### Page-level lock

Each page can have a lock such as:

```text
page_0005.jpg.lock
```

This prevents two PHP processes from rendering the same page simultaneously while still allowing different pages to render concurrently.

## 6. Duplicate Rendering Protection

Use `flock()` and always check the cache again after acquiring the page lock:

```php
$lockPath = $imagePath . '.lock';
$fp = fopen($lockPath, 'c');

if ($fp === false) {
    throw new RuntimeException('Unable to create page lock.');
}

try {
    if (!flock($fp, LOCK_EX)) {
        throw new RuntimeException('Unable to acquire page lock.');
    }

    // Another request may have rendered the page while we waited.
    if (!is_file($imagePath)) {
        $this->renderPage($pdfPath, $page, $imagePath);
    }

    flock($fp, LOCK_UN);
} finally {
    fclose($fp);
}
```

Flow:

```text
Request A                     Request B
    |                             |
not cached                    not cached
    |                             |
gets lock                     waits
    |
renders
    |
publishes image
    |
releases lock
                                  |
                              gets lock
                                  |
                            CHECK AGAIN
                                  |
                              now cached
                                  |
                            do not render
```

## 7. Atomic Image Publication

Do not render directly into the final cache filename. Render to a temporary file first and publish it with `rename()`.

```text
page_0005.jpg.tmp.<pid>
          |
          v
     render fully
          |
          v
       rename()
          |
          v
page_0005.jpg
```

Example:

```php
$tempPath = $finalPath . '.tmp.' . getmypid() . '.jpg';

$this->renderPageToFile($pdfPath, $page, $tempPath);

if (!is_file($tempPath) || filesize($tempPath) === 0) {
    throw new RuntimeException('Rendered page is empty.');
}

if (!rename($tempPath, $finalPath)) {
    @unlink($tempPath);
    throw new RuntimeException('Unable to publish cache image.');
}
```

Keep the temporary and final files on the same filesystem.

## 8. Example Imagick Rendering

Imagick uses zero-based PDF page indexes while the application can expose one-based page numbers.

```php
protected function renderPage($pdfPath, $page, $finalPath)
{
    $imagickPage = $page - 1;
    $tempPath = $finalPath . '.tmp.' . getmypid() . '.jpg';
    $imagick = new Imagick();

    try {
        $imagick->setResolution(150, 150);
        $imagick->readImage($pdfPath . '[' . $imagickPage . ']');
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
        $imagick->setImageCompressionQuality(85);

        if (!$imagick->writeImage($tempPath)) {
            throw new RuntimeException('Unable to render PDF page.');
        }

        if (!is_file($tempPath) || filesize($tempPath) === 0) {
            throw new RuntimeException('Rendered page is invalid.');
        }

        if (!rename($tempPath, $finalPath)) {
            throw new RuntimeException('Unable to publish page image.');
        }
    } finally {
        $imagick->clear();
        $imagick->destroy();

        if (is_file($tempPath)) {
            @unlink($tempPath);
        }
    }
}
```

## 9. Metadata

Each PDF cache can contain `metadata.json`:

```json
{
    "version": 1,
    "hash": "a8f31c72e940...",
    "created_at": 1791100000,
    "last_access_at": 1791103600,
    "page_count": 25
}
```

Avoid storing unnecessary sensitive information such as usernames, document titles, or original filenames in cache metadata.

## 10. Access-Time Updates

Do not rewrite metadata on every page request. Update `last_access_at` periodically, for example once per hour.

```php
protected function touchCacheAccess($cacheDir)
{
    $metadataFile = $cacheDir . DIRECTORY_SEPARATOR . 'metadata.json';
    $now = time();
    $metadata = $this->readMetadata($cacheDir);

    $lastAccess = isset($metadata['last_access_at'])
        ? (int) $metadata['last_access_at']
        : 0;

    if (($now - $lastAccess) < 3600) {
        return;
    }

    $metadata['last_access_at'] = $now;

    if (!isset($metadata['created_at'])) {
        $metadata['created_at'] = $now;
    }

    $this->writeJsonAtomic($metadataFile, $metadata);
}
```

Metadata should also use temporary-file + `rename()` publication.

## 11. Cache Expiration

A reasonable initial policy is:

```text
Cache unused for 7 days
        |
        v
eligible for deletion
```

Make the period configurable. Do not rely only on directory `filemtime()` as the last-access record; reading an image does not reliably update the directory modification time.

## 12. Safe Cleanup Locking

Cleanup must never delete a cache currently being rendered or served.

```text
Normal page access:
.cache.lock -> LOCK_SH

Cleanup:
.cache.lock -> LOCK_EX | LOCK_NB
```

If the exclusive lock cannot be acquired, cleanup skips that cache and tries again during a later maintenance run.

## 13. Rename-to-Trash Cleanup

A production cleanup process can atomically move an expired cache out of the active namespace before recursively deleting it:

```text
pdf_cache/
    abc123/

       |
       | rename while safely coordinated
       v

pdf_cache/
    .trash/
        abc123.<unique-name>/
```

Then the slow recursive deletion occurs from `.trash`. The implementation must carefully coordinate lock-file lifetime and possible recreation of the original hash directory.

## 14. Size-Based Cleanup

Age-based cleanup alone is not enough. Example policy:

```text
Maximum cache size: 20 GB
Cleanup target:     16 GB
```

When the cache exceeds 20 GB:

```text
Scan caches
    |
calculate sizes
    |
read last_access_at
    |
total > 20 GB?
    |
   YES
    |
sort oldest first
    |
delete oldest inactive caches
    |
continue until <= 16 GB
```

Using a lower cleanup target prevents repeated cleanup every time the cache barely crosses the maximum.

## 15. Temporary File Cleanup

A crashed PHP process can leave files such as:

```text
page_0003.jpg.tmp.1234
metadata.json.tmp.1234
```

The maintenance job can delete abandoned temporary files only after a safe age, for example six hours:

```text
*.tmp.*
    |
older than 6 hours?
    |
   YES
    |
 delete
```

Do not delete recent temporary files because they may belong to active rendering.

## 16. Keep Cleanup Out of HTTP Requests

Do not do this:

```php
public function pageAction()
{
    $cache->cleanupExpiredCaches(...);
    // serve page
}
```

Separate responsibilities:

```text
HTTP / ZF1
    |
    +-- authorization
    +-- cache lookup
    +-- page rendering
    +-- page response

CLI / Cron
    |
    +-- expiration
    +-- size enforcement
    +-- orphan cleanup
    +-- temp cleanup
```

## 17. CentOS Cron Cleanup

Create:

```text
scripts/cleanup-pdf-cache.php
```

Example:

```php
<?php

define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));

require_once APPLICATION_PATH . '/../library/S3/PdfCacheService.php';

$cache = new S3_PdfCacheService(
    APPLICATION_PATH . '/../data/pdf_cache'
);

$sevenDays = 7 * 24 * 60 * 60;
$deleted = $cache->cleanupExpiredCaches($sevenDays);

echo sprintf(
    "[%s] Deleted %d expired PDF caches.\n",
    date('Y-m-d H:i:s'),
    $deleted
);
```

Example cron entry:

```text
30 2 * * * /usr/bin/php /var/www/myapp/scripts/cleanup-pdf-cache.php >> /var/log/pdf-cache-cleanup.log 2>&1
```

## 18. Security Architecture

The browser should never directly request:

```text
/data/pdf_cache/<hash>/page_0001.jpg
```

Instead use an authenticated endpoint such as:

```text
GET /api/document/123/page/1
```

ZF1 should perform:

```text
1. Authentication
2. Authorization
3. Document validation
4. Resolve PDF path internally
5. Resolve stored PDF hash internally
6. Get/render cached page
7. Return image
```

Knowing the cache hash must never grant access.

## 19. Example ZF1 Controller

```php
public function pageAction()
{
    $documentId = (int) $this->_getParam('document_id');
    $page = (int) $this->_getParam('page', 1);
    $userId = $this->getCurrentUserId();

    if (!$this->documentService->canView($userId, $documentId)) {
        throw new Zend_Controller_Action_Exception('Forbidden', 403);
    }

    $document = $this->documentService->getDocument($documentId);

    $cache = new S3_PdfCacheService(
        APPLICATION_PATH . '/../data/pdf_cache'
    );

    $imagePath = $cache->getPage(
        $document['pdf_path'],
        $document['pdf_hash'],
        $page
    );

    $this->_helper->viewRenderer->setNoRender(true);
    $this->_helper->layout->disableLayout();

    $response = $this->getResponse();
    $response->setHeader('Content-Type', 'image/jpeg', true);
    $response->setHeader('Content-Length', filesize($imagePath), true);
    $response->setHeader('Cache-Control', 'private, no-store', true);

    readfile($imagePath);
}
```

The filesystem path and cache hash remain server-side.

## 20. Recommended Class Separation

### `S3_PdfCacheService`

Responsible for normal page requests:

```text
getPage()
getPagePath()
hasPage()
renderPage()
createCache()
touchCacheAccess()
locking
atomic publication
```

### `S3_PdfCacheCleaner`

Responsible for maintenance:

```text
cleanupExpiredCaches()
cleanupBySize()
cleanupTemporaryFiles()
calculateCacheSize()
findOldestCaches()
removeOrphanCaches()
```

This keeps expensive maintenance logic out of normal HTTP request handling.

## 21. Suggested Service API

```php
class S3_PdfCacheService
{
    public function getPage($pdfPath, $pdfHash, $page);
    public function hasPage($pdfHash, $page);
    public function getPagePath($pdfHash, $page);
    public function deleteCache($pdfHash);
}
```

Cleaner:

```php
class S3_PdfCacheCleaner
{
    public function cleanupExpiredCaches($maxAgeSeconds);
    public function cleanupBySize($maxBytes, $targetBytes);
    public function cleanupTemporaryFiles($maxAgeSeconds);
}
```

## 22. PDF Replacement Flow

```text
Existing PDF
    |
hash = AAA
    |
cache/AAA/

New PDF uploaded/replaced
    |
calculate SHA-256 once
    |
hash = BBB
    |
update MariaDB document record
    |
cache/BBB/
```

The old `cache/AAA/` becomes eligible for later orphan/lifecycle cleanup. Expensive recursive deletion does not need to happen during the upload request.

## 23. Cache Is Not Permanent Storage

The PDF image cache must be considered disposable and rebuildable:

```text
Protected PDF = authoritative source
Cache images  = temporary/rebuildable data
```

Do not store unique business information only in the cache.

## 24. Error Handling

If rendering fails:

```text
request
   |
render
   |
ERROR
   |
delete temporary file
   |
release locks
   |
log error
   |
return controlled error
```

Never publish an incomplete image as a valid cache entry.

## 25. Logging

Useful events include:

```text
PDF render started
PDF render completed
PDF render failed
cache directory created
expired cache removed
size-based cache removed
temporary file removed
cleanup skipped because cache is active
cleanup job summary
```

Avoid logging sensitive document content.

## 26. Configuration

Example ZF1 configuration:

```ini
pdfCache.path = APPLICATION_PATH "/../data/pdf_cache"
pdfCache.resolution = 150
pdfCache.jpegQuality = 85
pdfCache.expireDays = 7
pdfCache.maxSizeGB = 20
pdfCache.targetSizeGB = 16
pdfCache.tempExpireHours = 6
pdfCache.accessTouchSeconds = 3600
```

Reasonable initial values:

```text
Resolution:                 150 DPI
JPEG quality:               85
Unused cache expiration:    7 days
Maximum cache size:         20 GB
Cleanup target:             16 GB
Temporary-file expiration:  6 hours
Access metadata update:     once per hour
```

Tune these values according to traffic, PDF size, storage capacity, CPU, and required image quality.

## 27. Important Security Rules

1. Store original PDFs outside the public web root.
2. Store cache images outside the public web root.
3. Never use the hash as authorization.
4. Never accept a filesystem path from the browser.
5. Resolve document paths server-side.
6. Authenticate before serving a page.
7. Authorize access to the specific document.
8. Validate page numbers.
9. Do not expose internal cache paths in API responses.
10. Use controlled API endpoints for page delivery.
11. Any image delivered to an authorized browser can potentially be saved or captured; hiding a download button is not absolute copy prevention.
12. For sensitive documents, consider personalized visible watermarks on rendered pages.

## 28. Important Performance Rules

1. Calculate the PDF hash once, not on every page request.
2. Render pages on demand.
3. Cache completed images.
4. Use one render lock per page.
5. Re-check the cache after acquiring the page lock.
6. Use atomic temporary-to-final publication.
7. Keep cleanup out of HTTP requests.
8. Use scheduled CLI maintenance.
9. Monitor disk usage and render latency.

## 29. Complete Architecture

```text
                     PDF IMPORT / REPLACE
                              |
                              v
                     calculate SHA-256
                              |
                              v
                         MariaDB
                    documents.pdf_hash
                              |
                              v
Browser -------------> ZF1 Controller
                              |
                       authentication
                              |
                        authorization
                              |
                     resolve PDF + hash
                              |
                              v
                    S3_PdfCacheService
                              |
                       shared cache lock
                              |
                         page cached?
                         /         \
                       YES         NO
                        |           |
                        |      page render lock
                        |           |
                        |       check again
                        |           |
                        |         Imagick
                        |           |
                        |      temporary image
                        |           |
                        |      atomic rename
                        |           |
                        +-----------+
                              |
                              v
                         image response
                              |
                              v
                           Browser


                             CRON
                              |
                              v
                    S3_PdfCacheCleaner
                              |
                +-------------+-------------+
                |             |             |
             expiration    size limit    temp/orphan
                |             |             |
                +-------------+-------------+
                              |
                    exclusive cache lock
                              |
                       cache active?
                        /        \
                      YES        NO
                       |          |
                      skip     remove/move
```

## 30. Final Recommendation

Use this architecture:

```text
Protected PDF
    |
    +-- SHA-256 calculated once
    +-- hash stored in MariaDB
    |
    v
S3_PdfCacheService
    |
    +-- one hash folder per PDF version
    +-- render pages on demand
    +-- per-page render locks
    +-- cache-level shared lock
    +-- atomic temp -> final publication
    |
    v
Secure ZF1 Page API
    |
    v
Browser

Separate scheduled process:

S3_PdfCacheCleaner
    |
    +-- expired cache cleanup
    +-- maximum-size enforcement
    +-- temporary-file cleanup
    +-- orphan cleanup
    +-- skip active caches
```

Core principles:

> **The PDF is authoritative; the image cache is disposable.**

> **The cache hash identifies a PDF version, but it does not authorize access.**

> **Rendering and cleanup must coordinate through locks.**

> **Normal web requests render and serve pages; scheduled CLI jobs perform maintenance.**

> **Calculate expensive content hashes once when the PDF changes, not every time a page is viewed.**
