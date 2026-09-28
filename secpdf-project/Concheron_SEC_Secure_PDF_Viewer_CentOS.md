# Concheron SEC Secure PDF Viewer --- Detailed Sample Implementation

**Target:** PHP 7.4, Zend Framework 1, MariaDB, Apache\
**Viewer:** JavaScript Canvas, continuous scrolling, lazy loading\
**Rendering:** Server-side Poppler (`pdftoppm`) + Imagick watermarking\
**Security model:** Original PDF is never intentionally returned to the
browser.

> **Important:** Browser restrictions cannot prevent screenshots, screen
> recording, cameras, or a compromised endpoint. The meaningful controls
> are server-side authorization, no direct PDF endpoint, protected
> storage, short-lived view sessions, personalized watermarks, auditing,
> and rate limiting.

------------------------------------------------------------------------

# CentOS Server Setup

The following examples assume a CentOS-family server with Apache and PHP
7.4 already available from your approved/offline repository or internal
package source.

Because package names vary between CentOS versions and PHP repositories,
verify the exact names in your environment before deployment. Typical
required components are:

``` text
Apache/httpd
PHP 7.4
PHP 7.4 development headers
PHP CLI
PHP process manager/module appropriate to your Apache deployment
MariaDB client/driver for PHP
Imagick PHP extension
ImageMagick
Poppler utilities
OpenSSL development libraries
C compiler/build tools
```

Verify the important commands:

``` bash
php -v
phpize --version
httpd -v
pdftoppm -v
convert -version
openssl version
```

Check the PHP modules:

``` bash
php -m | grep -Ei 'imagick|openssl|pdo|mysqli'
```

For the Concheron native extension:

``` bash
php --ri concheron_sec
```

## CentOS SEC directories

Create storage outside the Apache document root:

``` bash
sudo mkdir -p /secure/sec/documents
sudo mkdir -p /secure/sec/pages
sudo mkdir -p /secure/sec/temp
sudo mkdir -p /secure/sec/trust
```

Use the actual Apache/PHP service account used by your installation. On
many CentOS Apache installations this is `apache`.

Example:

``` bash
sudo chown -R root:apache /secure/sec
sudo chmod 0750 /secure/sec
sudo chmod 0750 /secure/sec/documents
sudo chmod 0750 /secure/sec/pages
sudo chmod 0750 /secure/sec/temp
sudo chmod 0750 /secure/sec/trust
```

Individual encrypted documents, keys, and trust files should use
stricter permissions where appropriate.

For example:

``` bash
sudo chmod 0640 /secure/sec/trust/sec-package-signing-public.pem
```

Do not make `/secure/sec` world-readable.

## PHP configuration

Example:

``` ini
extension=concheron_sec.so

concheron_sec.company_id=COMPANY-A
concheron_sec.trusted_signing_key=/secure/sec/trust/sec-package-signing-public.pem

sec.storageRoot="/secure/sec"
sec.pageCacheRoot="/secure/sec/pages"
sec.tempRoot="/secure/sec/temp"
sec.pdftoppmPath="/usr/bin/pdftoppm"

sec.viewSessionLifetime=900
sec.viewIdleTimeout=300
sec.render.normalDpi=130
sec.render.highDpi=200
sec.maxPageRequestsPerMinute=120
```

After configuration changes, restart the relevant PHP/Apache service
according to your deployment.

For a traditional Apache module deployment this commonly includes:

``` bash
sudo systemctl restart httpd
```

If PHP-FPM is used, restart its actual installed service as well.

## Apache protection

The SEC storage should not be under the web root at all. As defense in
depth:

``` apache
<Directory "/secure/sec">
    Require all denied
</Directory>
```

Do not create an Apache `Alias` pointing to:

``` text
/secure/sec/documents
/secure/sec/pages
/secure/sec/temp
```

The browser must obtain pages only through the authenticated ZF1 page
endpoint.

## SELinux

Do **not** disable SELinux merely to make the viewer work.

First inspect its state:

``` bash
getenforce
sestatus
```

Keep SELinux enforcing where possible.

The exact policy depends on your CentOS version, Apache/PHP execution
model, filesystem layout, and organizational security policy. Give the
web application only the access it actually requires.

When access is denied, inspect the audit log rather than immediately
weakening SELinux:

``` bash
sudo ausearch -m AVC -ts recent
```

A production deployment should define appropriate persistent file
contexts and, when necessary, a narrowly scoped local policy for the SEC
application.

Avoid broad solutions such as making arbitrary protected directories
universally writable by the web server.

## Firewall/network

The local SEC viewer should be exposed only on the approved internal
interface/network.

Inspect active firewall configuration:

``` bash
sudo firewall-cmd --list-all
```

Do not expose the SEC application publicly merely to simplify testing.

Use HTTPS even on the internal network.

## Log locations

Depending on the CentOS/Apache configuration, useful logs commonly
include locations under:

``` text
/var/log/httpd/
/var/log/php-fpm/
/var/log/audit/
```

Application security logs should be separate from user-visible output
and protected against unauthorized modification.

Never log:

``` text
USB PIN
staff password
raw view token
PHP session cookie
CSRF token
document CEK
server private key
USB private key
plaintext PDF content
```

## Temporary-file protection

The example uses:

``` text
/secure/sec/temp
```

for controlled temporary plaintext during rendering.

Production requirements:

``` text
outside web root
restricted Unix permissions
SELinux restrictions
short plaintext lifetime
random filenames
cleanup in finally/error paths
no directory listing
no backup of temporary plaintext
no sharing through SMB/NFS unless explicitly designed and protected
```

Where practical, place temporary storage on an encrypted filesystem or
other approved protected storage.

## Poppler execution

Use the fixed executable:

``` text
/usr/bin/pdftoppm
```

Verify it:

``` bash
command -v pdftoppm
pdftoppm -v
```

Never accept a command path, filename, output prefix, DPI, or shell
fragment directly from an HTTP request.

The application should convert the requested page to a validated integer
and map the resolution profile to server-defined DPI values.

## Imagick

Verify the PHP extension:

``` bash
php -r 'var_dump(extension_loaded("imagick"));'
```

Expected:

``` text
bool(true)
```

Verify WebP support through your installed ImageMagick/Imagick build
before deployment.

A simple PHP check is:

``` bash
php -r '$i=new Imagick(); print_r($i->queryFormats("WEBP"));'
```

If WebP is unavailable in the approved CentOS build, PNG can be used for
delivered pages until WebP support is installed.

## Concheron extension build on CentOS

From the extension source directory:

``` bash
phpize
./configure --enable-concheron-sec
make
make test
sudo make install
```

Find the PHP extension directory:

``` bash
php -i | grep extension_dir
```

Enable the module using the PHP configuration convention used by your
CentOS installation, then verify:

``` bash
php -m | grep concheron_sec
php --ri concheron_sec
```

The extension must be compiled against the exact PHP 7.4 ABI/build used
by the server.

## Service-account test

Before deployment, verify that the actual Apache/PHP account can:

``` text
read encrypted document storage when required
read approved public trust material
create protected page-cache files
create/delete controlled temporary files
execute pdftoppm
```

It should **not** receive unnecessary access to unrelated system files,
private CA keys, USB private keys, or other subsidiaries' data.

# 1. Architecture

``` text
Encrypted Master PDF
        |
        | authorized server-side decrypt
        v
Protected temporary PDF
        |
        | Poppler / pdftoppm
        v
Clean rendered WebP/PNG cache
        |
        | secure page request
        v
Authorization + view-token validation
        |
        | Imagick
        v
Personalized watermarked WebP
        |
        v
HTTP response (no-store)
        |
        v
JavaScript Image -> Canvas
```

The browser never receives the original PDF URL or filesystem path.

------------------------------------------------------------------------

# 2. Suggested Project Layout

``` text
application/
  controllers/
    SecViewerController.php

  models/
    DbTable/
      SecDocuments.php
      SecDocumentVersions.php
      SecDocumentPermissions.php
      SecViewSessions.php
      SecAuditLog.php

  services/
    SecAuthorizationService.php
    SecCryptoService.php
    SecPdfRenderService.php
    SecWatermarkService.php
    SecViewSessionService.php
    SecAuditService.php

  views/
    scripts/
      sec-viewer/
        view.phtml

public/
  js/
    sec-viewer.js

/secure/sec/                 # OUTSIDE Apache document root
  documents/
  pages/
  temp/
```

------------------------------------------------------------------------

# 3. Database Tables

The examples assume tables similar to these.

``` sql
CREATE TABLE sec_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_code VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    classification VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_document_code (document_code)
) ENGINE=InnoDB;

CREATE TABLE sec_document_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    version_no VARCHAR(30) NOT NULL,
    encrypted_storage_path VARCHAR(1000) NOT NULL,
    storage_key_id VARCHAR(100) NOT NULL,
    page_count INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    imported_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sec_version_document (document_id)
) ENGINE=InnoDB;

CREATE TABLE sec_document_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_permission (document_id, staff_id)
) ENGINE=InnoDB;

CREATE TABLE sec_view_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_version_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    certificate_id VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    display_code VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    client_ip VARCHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_view_token_hash (token_hash),
    KEY idx_sec_view_lookup
        (document_version_id, staff_id, status)
) ENGINE=InnoDB;

CREATE TABLE sec_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    document_id BIGINT UNSIGNED NULL,
    view_session_id BIGINT UNSIGNED NULL,
    page_number INT NULL,
    result VARCHAR(20) NOT NULL,
    reason_code VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sec_audit_document (document_id, created_at),
    KEY idx_sec_audit_staff (staff_id, created_at)
) ENGINE=InnoDB;
```

------------------------------------------------------------------------

# 4. Configuration

Example `application.ini`:

``` ini
sec.storageRoot = "/secure/sec"
sec.pageCacheRoot = "/secure/sec/pages"
sec.tempRoot = "/secure/sec/temp"

; Do not accept this path from HTTP input.
sec.pdftoppmPath = "/usr/bin/pdftoppm"

sec.viewSessionLifetime = 900
sec.viewIdleTimeout = 300

sec.render.normalDpi = 130
sec.render.highDpi = 200
sec.maxPageRequestsPerMinute = 120
```

On CentOS, configure a fixed administrator-controlled Poppler executable
path, for example:

``` ini
sec.pdftoppmPath = "C:\Concheron\Poppler\bin\pdftoppm.exe"
```

Do not let a request parameter choose the executable.

------------------------------------------------------------------------

# 5. Authorization Service

`application/services/SecAuthorizationService.php`

``` php
<?php

class Application_Service_SecAuthorizationService
{
    /** @var Zend_Db_Adapter_Abstract */
    private $db;

    public function __construct()
    {
        $this->db = Zend_Db_Table::getDefaultAdapter();
    }

    public function canView($staffId, $documentId)
    {
        $sql = "
            SELECT COUNT(*)
            FROM sec_document_permissions
            WHERE document_id = ?
              AND staff_id = ?
              AND can_view = 1
              AND (valid_from IS NULL OR valid_from <= NOW())
              AND (valid_until IS NULL OR valid_until >= NOW())
        ";

        return (int)$this->db->fetchOne(
            $sql,
            array((int)$documentId, (int)$staffId)
        ) > 0;
    }
}
```

This is only the document-permission check. Your production
implementation must also verify that the staff account is active and
that Mode-A staff certificate authentication has completed successfully.

------------------------------------------------------------------------

# 6. View Session Service

`application/services/SecViewSessionService.php`

``` php
<?php

class Application_Service_SecViewSessionService
{
    /** @var Zend_Db_Adapter_Abstract */
    private $db;

    private $maxLifetime = 900;
    private $idleTimeout = 300;

    public function __construct()
    {
        $this->db = Zend_Db_Table::getDefaultAdapter();

        $config = Zend_Registry::get('config');

        if (isset($config->sec->viewSessionLifetime)) {
            $this->maxLifetime =
                (int)$config->sec->viewSessionLifetime;
        }

        if (isset($config->sec->viewIdleTimeout)) {
            $this->idleTimeout =
                (int)$config->sec->viewIdleTimeout;
        }
    }

    public function create(
        $documentVersionId,
        $staffId,
        $certificateId,
        $clientIp,
        $userAgent
    ) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // This is safe for display in the watermark.
        $displayCode = strtoupper(
            substr(bin2hex(random_bytes(6)), 0, 10)
        );

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expires = $now->modify(
            '+' . $this->maxLifetime . ' seconds'
        );

        $this->db->insert('sec_view_sessions', array(
            'document_version_id' => (int)$documentVersionId,
            'staff_id'            => (int)$staffId,
            'certificate_id'      => (string)$certificateId,
            'token_hash'          => $tokenHash,
            'display_code'        => $displayCode,
            'created_at'          => $now->format('Y-m-d H:i:s'),
            'expires_at'          => $expires->format('Y-m-d H:i:s'),
            'last_activity_at'    => $now->format('Y-m-d H:i:s'),
            'status'              => 'ACTIVE',
            'client_ip'           => (string)$clientIp,
            'user_agent_hash'     => hash(
                'sha256',
                (string)$userAgent
            ),
        ));

        return array(
            'id'           => (int)$this->db->lastInsertId(),
            'token'        => $rawToken,
            'display_code' => $displayCode,
            'expires_at'   => $expires->format(DateTime::ATOM),
        );
    }

    public function validate(
        $rawToken,
        $documentVersionId,
        $staffId
    ) {
        if (!is_string($rawToken) ||
            !preg_match('/^[a-f0-9]{64}$/D', $rawToken)) {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        $row = $this->db->fetchRow(
            "
            SELECT *
            FROM sec_view_sessions
            WHERE token_hash = ?
              AND document_version_id = ?
              AND staff_id = ?
              AND status = 'ACTIVE'
            LIMIT 1
            ",
            array(
                $tokenHash,
                (int)$documentVersionId,
                (int)$staffId
            )
        );

        if (!$row) {
            return false;
        }

        $now = time();

        if (strtotime($row['expires_at'] . ' UTC') <= $now) {
            $this->expire((int)$row['id']);
            return false;
        }

        if (strtotime(
                $row['last_activity_at'] . ' UTC'
            ) + $this->idleTimeout <= $now) {
            $this->expire((int)$row['id']);
            return false;
        }

        $this->db->update(
            'sec_view_sessions',
            array(
                'last_activity_at' => gmdate('Y-m-d H:i:s')
            ),
            array(
                'id = ?' => (int)$row['id']
            )
        );

        return $row;
    }

    public function expire($id)
    {
        $this->db->update(
            'sec_view_sessions',
            array('status' => 'EXPIRED'),
            array('id = ?' => (int)$id)
        );
    }
}
```

Only the SHA-256 hash of the raw token is stored.

------------------------------------------------------------------------

# 7. Crypto Service Boundary

The application should not contain document keys.

`application/services/SecCryptoService.php`

``` php
<?php

class Application_Service_SecCryptoService
{
    /**
     * Creates a protected temporary plaintext PDF for rendering.
     *
     * Production implementation should delegate decryption to the
     * Concheron extension/server key provider.
     */
    public function createTemporaryPdf(
        $encryptedMasterPath,
        $storageKeyId
    ) {
        $config = Zend_Registry::get('config');

        $tempRoot = realpath($config->sec->tempRoot);

        if ($tempRoot === false) {
            throw new RuntimeException(
                'SEC temporary directory is unavailable.'
            );
        }

        $tmp = $tempRoot
            . DIRECTORY_SEPARATOR
            . 'render-'
            . bin2hex(random_bytes(16))
            . '.pdf';

        /*
         * Replace this sample call with the final native API.
         *
         * Important:
         * - PHP must not receive the raw server private key.
         * - PHP must not receive the raw document data key.
         */
        $result = concheron_sec_decrypt_to_temp(
            $encryptedMasterPath,
            $storageKeyId,
            $tmp
        );

        if (!$result || empty($result['success'])) {
            @unlink($tmp);
            throw new RuntimeException(
                'SEC document decryption failed.'
            );
        }

        @chmod($tmp, 0600);

        return $tmp;
    }

    public function destroyTemporaryPdf($path)
    {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
```

`concheron_sec_decrypt_to_temp()` is shown as the intended future native
boundary; it must be implemented by your Concheron extension before
using this sample in production.

------------------------------------------------------------------------

# 8. Safe PDF Rendering Service

`application/services/SecPdfRenderService.php`

``` php
<?php

class Application_Service_SecPdfRenderService
{
    private $pdftoppm;
    private $cacheRoot;
    private $normalDpi = 130;
    private $highDpi = 200;

    public function __construct()
    {
        $config = Zend_Registry::get('config');

        $this->pdftoppm =
            (string)$config->sec->pdftoppmPath;

        $this->cacheRoot =
            (string)$config->sec->pageCacheRoot;

        if (isset($config->sec->render->normalDpi)) {
            $this->normalDpi =
                (int)$config->sec->render->normalDpi;
        }

        if (isset($config->sec->render->highDpi)) {
            $this->highDpi =
                (int)$config->sec->render->highDpi;
        }
    }

    public function getCleanPage(
        $documentVersionId,
        $page,
        $profile,
        callable $temporaryPdfFactory
    ) {
        $documentVersionId = (int)$documentVersionId;
        $page = (int)$page;

        if ($documentVersionId <= 0 || $page <= 0) {
            throw new InvalidArgumentException(
                'Invalid document or page.'
            );
        }

        if (!in_array(
            $profile,
            array('normal', 'high'),
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid render profile.'
            );
        }

        $dpi = ($profile === 'high')
            ? $this->highDpi
            : $this->normalDpi;

        $dir = $this->cacheDirectory(
            $documentVersionId,
            $profile
        );

        $final = $dir
            . DIRECTORY_SEPARATOR
            . sprintf('page-%06d.png', $page);

        if (is_file($final)) {
            return $final;
        }

        if (!is_dir($dir) &&
            !mkdir($dir, 0700, true) &&
            !is_dir($dir)) {
            throw new RuntimeException(
                'Unable to create SEC page cache.'
            );
        }

        $tmpPdf = null;
        $prefix = $dir
            . DIRECTORY_SEPARATOR
            . 'render-'
            . bin2hex(random_bytes(8));

        try {
            $tmpPdf = $temporaryPdfFactory();

            /*
             * Every command argument is either:
             * - administrator configuration,
             * - validated integer,
             * - internally generated path.
             *
             * escapeshellarg() is still used.
             */
            $command =
                escapeshellarg($this->pdftoppm)
                . ' -f ' . $page
                . ' -singlefile'
                . ' -r ' . $dpi
                . ' -png '
                . escapeshellarg($tmpPdf)
                . ' '
                . escapeshellarg($prefix)
                . ' 2>&1';

            $output = array();
            $exitCode = 0;

            exec($command, $output, $exitCode);

            $generated = $prefix . '.png';

            if ($exitCode !== 0 ||
                !is_file($generated)) {
                throw new RuntimeException(
                    'PDF page rendering failed.'
                );
            }

            @chmod($generated, 0600);

            /*
             * Rename into the deterministic clean-cache name only
             * after a successful render.
             */
            if (!@rename($generated, $final)) {
                @unlink($generated);

                // Another request may have created the same page.
                if (!is_file($final)) {
                    throw new RuntimeException(
                        'Unable to commit rendered page.'
                    );
                }
            }

            return $final;
        } finally {
            if ($tmpPdf && is_file($tmpPdf)) {
                @unlink($tmpPdf);
            }

            foreach (glob($prefix . '*') ?: array() as $file) {
                @unlink($file);
            }
        }
    }

    private function cacheDirectory(
        $documentVersionId,
        $profile
    ) {
        /*
         * IDs/profile have already been validated, so they cannot
         * introduce path traversal.
         */
        return rtrim(
            $this->cacheRoot,
            DIRECTORY_SEPARATOR
        )
        . DIRECTORY_SEPARATOR
        . $documentVersionId
        . DIRECTORY_SEPARATOR
        . $profile;
    }
}
```

### Production note

For higher assurance, prefer a process API that passes an argument array
directly rather than constructing a shell command. If your PHP 7.4
deployment only provides `exec()`, keep the executable
administrator-controlled and strictly validate all other values as
shown.

------------------------------------------------------------------------

# 9. Personalized Watermark Service

`application/services/SecWatermarkService.php`

``` php
<?php

class Application_Service_SecWatermarkService
{
    public function createWatermarkedWebp(
        $cleanPagePath,
        array $watermark
    ) {
        if (!is_file($cleanPagePath)) {
            throw new RuntimeException(
                'Rendered SEC page is unavailable.'
            );
        }

        $image = new Imagick($cleanPagePath);

        try {
            $image->setImageAlphaChannel(
                Imagick::ALPHACHANNEL_ACTIVATE
            );

            $width  = $image->getImageWidth();
            $height = $image->getImageHeight();

            $text = $this->buildText($watermark);

            /*
             * Create a transparent tile and repeat it across
             * the page. The tile contains only non-secret
             * attribution information.
             */
            $tileWidth = 620;
            $tileHeight = 300;

            $tile = new Imagick();
            $tile->newImage(
                $tileWidth,
                $tileHeight,
                new ImagickPixel('transparent')
            );
            $tile->setImageFormat('png');

            $draw = new ImagickDraw();
            $draw->setFillColor(
                new ImagickPixel('rgba(40,40,40,0.16)')
            );
            $draw->setFontSize(22);
            $draw->setTextAlignment(
                Imagick::ALIGN_CENTER
            );
            $draw->setGravity(Imagick::GRAVITY_CENTER);

            /*
             * Rotation is performed on the tile rather than
             * altering the source page.
             */
            $tile->annotateImage(
                $draw,
                0,
                0,
                -28,
                $text
            );

            for ($y = -$tileHeight;
                 $y < $height + $tileHeight;
                 $y += $tileHeight) {

                for ($x = -$tileWidth;
                     $x < $width + $tileWidth;
                     $x += $tileWidth) {

                    $image->compositeImage(
                        $tile,
                        Imagick::COMPOSITE_OVER,
                        $x,
                        $y
                    );
                }
            }

            $tile->clear();
            $tile->destroy();

            // Add a stronger footer attribution.
            $footer = new ImagickDraw();
            $footer->setFillColor(
                new ImagickPixel('rgba(20,20,20,0.55)')
            );
            $footer->setFontSize(16);
            $footer->setGravity(
                Imagick::GRAVITY_SOUTH
            );

            $footerText = sprintf(
                'CONCHERON SEC | %s | %s | View %s | %s UTC',
                $this->safe($watermark['company']),
                $this->safe($watermark['staff']),
                $this->safe($watermark['view_code']),
                gmdate('Y-m-d H:i:s')
            );

            $image->annotateImage(
                $footer,
                0,
                12,
                0,
                $footerText
            );

            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(82);
            $image->stripImage();

            return $image->getImagesBlob();
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function buildText(array $w)
    {
        return implode("\n", array(
            'CONCHERON — SEC',
            $this->safe($w['company']),
            'Employee: ' . $this->safe($w['staff']),
            'Document: ' . $this->safe($w['document']),
            'View: ' . $this->safe($w['view_code']),
        ));
    }

    private function safe($value)
    {
        /*
         * Watermark values come from server-side records.
         * Keep the visible output bounded and single-line.
         */
        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            (string)$value
        );

        return mb_substr($value, 0, 120, 'UTF-8');
    }
}
```

Never put the raw view token, PHP session ID, authentication cookie,
CSRF token, PIN, certificate private key, or encryption key into the
watermark.

------------------------------------------------------------------------

# 10. Audit Service

`application/services/SecAuditService.php`

``` php
<?php

class Application_Service_SecAuditService
{
    private $db;

    public function __construct()
    {
        $this->db = Zend_Db_Table::getDefaultAdapter();
    }

    public function record(
        $eventType,
        $result,
        array $context = array()
    ) {
        $this->db->insert('sec_audit_log', array(
            'event_id' => strtoupper(
                bin2hex(random_bytes(16))
            ),
            'event_type' => (string)$eventType,
            'staff_id' => isset($context['staff_id'])
                ? (int)$context['staff_id']
                : null,
            'document_id' => isset($context['document_id'])
                ? (int)$context['document_id']
                : null,
            'view_session_id' =>
                isset($context['view_session_id'])
                ? (int)$context['view_session_id']
                : null,
            'page_number' => isset($context['page'])
                ? (int)$context['page']
                : null,
            'result' => (string)$result,
            'reason_code' =>
                isset($context['reason_code'])
                ? (string)$context['reason_code']
                : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ));
    }
}
```

------------------------------------------------------------------------

# 11. ZF1 Viewer Controller

`application/controllers/SecViewerController.php`

``` php
<?php

class SecViewerController extends Zend_Controller_Action
{
    private $db;
    private $authz;
    private $sessions;
    private $crypto;
    private $renderer;
    private $watermark;
    private $audit;

    public function init()
    {
        $this->db =
            Zend_Db_Table::getDefaultAdapter();

        $this->authz =
            new Application_Service_SecAuthorizationService();

        $this->sessions =
            new Application_Service_SecViewSessionService();

        $this->crypto =
            new Application_Service_SecCryptoService();

        $this->renderer =
            new Application_Service_SecPdfRenderService();

        $this->watermark =
            new Application_Service_SecWatermarkService();

        $this->audit =
            new Application_Service_SecAuditService();
    }

    /**
     * GET /sec-viewer/view/document/184
     */
    public function viewAction()
    {
        $staff = $this->requireAuthenticatedStaff();

        $documentId = (int)$this->_getParam(
            'document',
            0
        );

        $document = $this->loadDocument($documentId);

        if (!$document ||
            !$this->authz->canView(
                $staff['id'],
                $documentId
            )) {
            throw new Zend_Controller_Action_Exception(
                'Not found.',
                404
            );
        }

        /*
         * IMPORTANT:
         * requireAuthenticatedStaff() must also ensure that the
         * Mode-A certificate authentication state is current.
         */
        $view = $this->sessions->create(
            $document['version_id'],
            $staff['id'],
            $staff['certificate_id'],
            $this->getRequest()->getClientIp(),
            $this->getRequest()->getHeader(
                'User-Agent'
            )
        );

        $this->audit->record(
            'SEC_DOCUMENT_OPEN',
            'SUCCESS',
            array(
                'staff_id' => $staff['id'],
                'document_id' => $documentId,
                'view_session_id' => $view['id'],
            )
        );

        $this->view->document = $document;
        $this->view->viewToken = $view['token'];
        $this->view->viewCode = $view['display_code'];
    }

    /**
     * POST /sec-viewer/page
     *
     * Returns only a watermarked page image.
     */
    public function pageAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();

        $response = $this->getResponse();

        try {
            $staff = $this->requireAuthenticatedStaff();

            if (!$this->getRequest()->isPost()) {
                return $this->jsonError(
                    405,
                    'SEC_E_METHOD'
                );
            }

            $body = json_decode(
                $this->getRequest()->getRawBody(),
                true
            );

            if (!is_array($body)) {
                return $this->jsonError(
                    400,
                    'SEC_E_REQUEST'
                );
            }

            $documentId = isset($body['document_id'])
                ? (int)$body['document_id']
                : 0;

            $versionId = isset($body['version_id'])
                ? (int)$body['version_id']
                : 0;

            $page = isset($body['page'])
                ? (int)$body['page']
                : 0;

            $profile = isset($body['profile'])
                ? (string)$body['profile']
                : 'normal';

            $token = isset($body['view_token'])
                ? (string)$body['view_token']
                : '';

            $document = $this->loadDocument(
                $documentId
            );

            if (!$document ||
                (int)$document['version_id'] !==
                    $versionId) {
                return $this->jsonError(
                    404,
                    'SEC_E_DOCUMENT'
                );
            }

            if ($page < 1 ||
                $page > (int)$document['page_count']) {
                return $this->jsonError(
                    400,
                    'SEC_E_PAGE'
                );
            }

            if (!in_array(
                $profile,
                array('normal', 'high'),
                true
            )) {
                return $this->jsonError(
                    400,
                    'SEC_E_PROFILE'
                );
            }

            /*
             * Re-check authorization on every page request.
             */
            if (!$this->authz->canView(
                $staff['id'],
                $documentId
            )) {
                $this->audit->record(
                    'SEC_ACCESS_DENIED',
                    'DENIED',
                    array(
                        'staff_id' => $staff['id'],
                        'document_id' => $documentId,
                        'page' => $page,
                        'reason_code' =>
                            'SEC_E_NOT_AUTHORIZED'
                    )
                );

                return $this->jsonError(
                    403,
                    'SEC_E_NOT_AUTHORIZED'
                );
            }

            $viewSession =
                $this->sessions->validate(
                    $token,
                    $versionId,
                    $staff['id']
                );

            if (!$viewSession) {
                return $this->jsonError(
                    401,
                    'SEC_E_VIEW_SESSION'
                );
            }

            /*
             * Add your rate limiter here before rendering.
             * It should key primarily on staff/view session and
             * should tolerate normal lazy-loading bursts.
             */

            $crypto = $this->crypto;
            $encryptedPath =
                $document['encrypted_storage_path'];
            $storageKeyId =
                $document['storage_key_id'];

            $cleanPage =
                $this->renderer->getCleanPage(
                    $versionId,
                    $page,
                    $profile,
                    function () use (
                        $crypto,
                        $encryptedPath,
                        $storageKeyId
                    ) {
                        return $crypto
                            ->createTemporaryPdf(
                                $encryptedPath,
                                $storageKeyId
                            );
                    }
                );

            $webp =
                $this->watermark
                    ->createWatermarkedWebp(
                        $cleanPage,
                        array(
                            'company' =>
                                $staff['company_code'],
                            'staff' =>
                                $staff['staff_code'],
                            'document' =>
                                $document['document_code'],
                            'view_code' =>
                                $viewSession[
                                    'display_code'
                                ],
                        )
                    );

            $this->audit->record(
                'SEC_PAGE_VIEW',
                'SUCCESS',
                array(
                    'staff_id' => $staff['id'],
                    'document_id' => $documentId,
                    'view_session_id' =>
                        $viewSession['id'],
                    'page' => $page,
                )
            );

            $response->setHttpResponseCode(200);

            $response->setHeader(
                'Content-Type',
                'image/webp',
                true
            );

            $response->setHeader(
                'Cache-Control',
                'private, no-store, no-cache, '
                . 'must-revalidate, max-age=0',
                true
            );

            $response->setHeader(
                'Pragma',
                'no-cache',
                true
            );

            $response->setHeader(
                'X-Content-Type-Options',
                'nosniff',
                true
            );

            $response->setHeader(
                'Content-Disposition',
                'inline',
                true
            );

            $response->setBody($webp);
        } catch (Exception $e) {
            /*
             * Log detailed diagnostics to a protected server log,
             * not to the HTTP response.
             */
            return $this->jsonError(
                500,
                'SEC_E_INTERNAL'
            );
        }
    }

    private function loadDocument($documentId)
    {
        return $this->db->fetchRow(
            "
            SELECT
                d.id,
                d.document_code,
                d.title,
                d.classification,
                v.id AS version_id,
                v.version_no,
                v.encrypted_storage_path,
                v.storage_key_id,
                v.page_count
            FROM sec_documents d
            INNER JOIN sec_document_versions v
                ON v.document_id = d.id
            WHERE d.id = ?
              AND d.status = 'ACTIVE'
              AND v.status = 'ACTIVE'
            ORDER BY v.id DESC
            LIMIT 1
            ",
            array((int)$documentId)
        );
    }

    private function requireAuthenticatedStaff()
    {
        $auth = Zend_Auth::getInstance();

        if (!$auth->hasIdentity()) {
            throw new Zend_Controller_Action_Exception(
                'Authentication required.',
                401
            );
        }

        $identity = (array)$auth->getIdentity();

        /*
         * Example fields expected from your login/certificate
         * integration. Do not trust request parameters for these.
         */
        if (empty($identity['id']) ||
            empty($identity['staff_code']) ||
            empty($identity['company_code']) ||
            empty($identity['certificate_id']) ||
            empty($identity['sec_certificate_verified'])) {
            throw new Zend_Controller_Action_Exception(
                'SEC authentication required.',
                401
            );
        }

        return $identity;
    }

    private function jsonError($status, $code)
    {
        $this->getResponse()
            ->setHttpResponseCode($status)
            ->setHeader(
                'Content-Type',
                'application/json; charset=utf-8',
                true
            )
            ->setHeader(
                'Cache-Control',
                'no-store',
                true
            )
            ->setBody(json_encode(array(
                'success' => false,
                'error_code' => $code
            )));

        return null;
    }
}
```

------------------------------------------------------------------------

# 12. Viewer Page

`application/views/scripts/sec-viewer/view.phtml`

``` php
<?php
$this->headMeta()
    ->appendHttpEquiv(
        'Cache-Control',
        'no-store, no-cache, must-revalidate'
    );
?>

<div
    id="secViewer"
    class="sec-viewer"
    data-document-id="<?php
        echo (int)$this->document['id']; ?>"
    data-version-id="<?php
        echo (int)$this->document['version_id']; ?>"
    data-page-count="<?php
        echo (int)$this->document['page_count']; ?>"
    data-view-token="<?php
        echo $this->escape($this->viewToken); ?>"
    data-page-url="<?php
        echo $this->escape(
            $this->url(
                array(
                    'controller' => 'sec-viewer',
                    'action' => 'page'
                ),
                'default',
                true
            )
        ); ?>"
>
    <div class="sec-toolbar">
        <button type="button" id="zoomOut">−</button>

        <span id="zoomLabel">100%</span>

        <button type="button" id="zoomIn">+</button>

        <button type="button" id="zoom100">
            100%
        </button>

        <button type="button" id="fitWidth">
            Fit width
        </button>

        <label>
            Page
            <input
                id="pageInput"
                type="number"
                min="1"
                value="1"
            >
            /
            <span id="pageCount"></span>
        </label>
    </div>

    <div
        id="pageScroller"
        class="sec-page-scroller"
        tabindex="0"
    ></div>
</div>

<script src="/js/sec-viewer.js"></script>
```

The raw view token is present in the authorized page DOM because
JavaScript needs it to request pages. It is temporary and must never be
placed in the watermark or URL.

For stronger browser handling, you can instead bootstrap it through a
JavaScript variable or initial JSON response, but it remains accessible
to code running in the authorized page. Strong XSS prevention is
therefore critical.

------------------------------------------------------------------------

# 13. Basic Viewer CSS

``` css
.sec-viewer {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #ececec;
}

.sec-toolbar {
    flex: 0 0 auto;
    padding: 8px;
    background: #fff;
    border-bottom: 1px solid #ccc;
    position: relative;
    z-index: 10;
}

.sec-page-scroller {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: auto;
    padding: 20px;
}

.sec-page-slot {
    margin: 0 auto 20px auto;
    position: relative;
    background: #fff;
    box-shadow: 0 1px 8px rgba(0,0,0,.20);
    display: flex;
    justify-content: center;
    align-items: center;
}

.sec-page-slot canvas {
    display: block;
}

.sec-page-status {
    position: absolute;
    top: 10px;
    left: 10px;
    font: 13px sans-serif;
}
```

------------------------------------------------------------------------

# 14. Canvas Viewer

`public/js/sec-viewer.js`

``` javascript
(function () {
    'use strict';

    var root = document.getElementById('secViewer');

    if (!root) {
        return;
    }

    var scroller =
        document.getElementById('pageScroller');

    var pageInput =
        document.getElementById('pageInput');

    var pageCountLabel =
        document.getElementById('pageCount');

    var zoomLabel =
        document.getElementById('zoomLabel');

    var documentId =
        parseInt(root.getAttribute(
            'data-document-id'
        ), 10);

    var versionId =
        parseInt(root.getAttribute(
            'data-version-id'
        ), 10);

    var pageCount =
        parseInt(root.getAttribute(
            'data-page-count'
        ), 10);

    var viewToken =
        root.getAttribute('data-view-token');

    var pageUrl =
        root.getAttribute('data-page-url');

    var zoom = 1.0;
    var fitMode = false;
    var currentPage = 1;

    /*
     * Approximate initial page ratio.
     * After an image loads, its real dimensions are used.
     */
    var baseWidth = 900;
    var baseHeight = 1273;

    var slots = {};
    var loading = {};
    var loaded = {};

    pageCountLabel.textContent =
        String(pageCount);

    pageInput.max = String(pageCount);

    createPlaceholders();

    var observer = new IntersectionObserver(
        onIntersection,
        {
            root: scroller,
            rootMargin: '800px 0px 800px 0px',
            threshold: [0, 0.05, 0.25, 0.5]
        }
    );

    Object.keys(slots).forEach(function (key) {
        observer.observe(slots[key]);
    });

    scroller.addEventListener(
        'scroll',
        debounce(updateCurrentPage, 80)
    );

    document
        .getElementById('zoomIn')
        .addEventListener('click', function () {
            fitMode = false;
            setZoom(Math.min(3.0, zoom + 0.15));
        });

    document
        .getElementById('zoomOut')
        .addEventListener('click', function () {
            fitMode = false;
            setZoom(Math.max(0.25, zoom - 0.15));
        });

    document
        .getElementById('zoom100')
        .addEventListener('click', function () {
            fitMode = false;
            setZoom(1.0);
        });

    document
        .getElementById('fitWidth')
        .addEventListener('click', function () {
            fitMode = true;
            applyFitWidth();
        });

    pageInput.addEventListener(
        'change',
        function () {
            var p = parseInt(
                pageInput.value,
                10
            );

            if (!isFinite(p)) {
                return;
            }

            p = Math.max(
                1,
                Math.min(pageCount, p)
            );

            pageInput.value = String(p);

            slots[p].scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    );

    window.addEventListener(
        'resize',
        debounce(function () {
            if (fitMode) {
                applyFitWidth();
            }
        }, 120)
    );

    /*
     * Ctrl + wheel changes zoom.
     * Normal wheel scrolling is intentionally NOT intercepted.
     */
    scroller.addEventListener(
        'wheel',
        function (event) {
            if (!event.ctrlKey) {
                return;
            }

            event.preventDefault();

            fitMode = false;

            if (event.deltaY < 0) {
                setZoom(
                    Math.min(3.0, zoom + 0.10)
                );
            } else {
                setZoom(
                    Math.max(0.25, zoom - 0.10)
                );
            }
        },
        { passive: false }
    );

    /*
     * Deterrents only. These do not create a security boundary.
     */
    document.addEventListener(
        'contextmenu',
        function (event) {
            if (root.contains(event.target)) {
                event.preventDefault();
            }
        }
    );

    document.addEventListener(
        'keydown',
        function (event) {
            if (!root.contains(
                document.activeElement
            )) {
                return;
            }

            var key =
                String(event.key).toLowerCase();

            if ((event.ctrlKey ||
                 event.metaKey) &&
                (key === 's' ||
                 key === 'p')) {
                event.preventDefault();
            }
        }
    );

    function createPlaceholders() {
        var fragment =
            document.createDocumentFragment();

        for (var p = 1;
             p <= pageCount;
             p++) {

            var slot =
                document.createElement('div');

            slot.className = 'sec-page-slot';
            slot.setAttribute(
                'data-page',
                String(p)
            );

            var status =
                document.createElement('div');

            status.className =
                'sec-page-status';

            status.textContent =
                'Page ' + p;

            slot.appendChild(status);

            slots[p] = slot;
            fragment.appendChild(slot);
        }

        scroller.appendChild(fragment);
        updateSlotSizes();
    }

    function onIntersection(entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }

            var p = parseInt(
                entry.target.getAttribute(
                    'data-page'
                ),
                10
            );

            loadPage(p);
        });

        updateCurrentPage();
        unloadDistantPages();
    }

    function loadPage(page) {
        if (loaded[page] || loading[page]) {
            return;
        }

        loading[page] = true;

        var profile =
            zoom > 1.5 ? 'high' : 'normal';

        fetch(pageUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type':
                    'application/json',
                'Accept': 'image/webp'
            },
            body: JSON.stringify({
                document_id: documentId,
                version_id: versionId,
                page: page,
                profile: profile,
                view_token: viewToken
            })
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error(
                    'Page request failed: '
                    + response.status
                );
            }

            return response.blob();
        })
        .then(function (blob) {
            return createImageBitmap(blob);
        })
        .then(function (bitmap) {
            var slot = slots[page];

            /*
             * The first successfully loaded page gives us a
             * realistic aspect ratio for placeholders.
             */
            if (bitmap.width > 0 &&
                bitmap.height > 0) {
                baseWidth = bitmap.width;
                baseHeight = bitmap.height;
            }

            var canvas =
                document.createElement(
                    'canvas'
                );

            canvas.width = bitmap.width;
            canvas.height = bitmap.height;

            var ctx =
                canvas.getContext(
                    '2d',
                    { alpha: false }
                );

            ctx.drawImage(bitmap, 0, 0);

            if (bitmap.close) {
                bitmap.close();
            }

            slot.innerHTML = '';
            slot.appendChild(canvas);

            loaded[page] = {
                canvas: canvas,
                profile: profile
            };

            delete loading[page];

            applyCanvasDisplaySize(
                page,
                canvas
            );

            updateSlotSizes();
            unloadDistantPages();
        })
        .catch(function () {
            delete loading[page];

            var slot = slots[page];

            if (slot) {
                slot.innerHTML =
                    '<div class="sec-page-status">'
                    + 'Unable to load page.'
                    + '</div>';
            }
        });
    }

    function applyCanvasDisplaySize(
        page,
        canvas
    ) {
        var width =
            Math.round(
                canvas.width * zoom
            );

        var height =
            Math.round(
                canvas.height * zoom
            );

        canvas.style.width =
            width + 'px';

        canvas.style.height =
            height + 'px';

        slots[page].style.width =
            width + 'px';

        slots[page].style.height =
            height + 'px';
    }

    function setZoom(value) {
        zoom = value;

        zoomLabel.textContent =
            Math.round(zoom * 100) + '%';

        updateSlotSizes();

        Object.keys(loaded)
            .forEach(function (key) {
                var p = parseInt(key, 10);

                applyCanvasDisplaySize(
                    p,
                    loaded[p].canvas
                );

                /*
                 * If zoom crossed the high-resolution
                 * threshold, discard nearby pages so they
                 * can be fetched at the appropriate profile.
                 */
                var wanted =
                    zoom > 1.5
                    ? 'high'
                    : 'normal';

                if (loaded[p].profile !==
                    wanted) {
                    unloadPage(p);
                    loadPage(p);
                }
            });
    }

    function applyFitWidth() {
        var available =
            Math.max(
                200,
                scroller.clientWidth - 50
            );

        setZoom(
            Math.max(
                0.25,
                Math.min(
                    3.0,
                    available / baseWidth
                )
            )
        );

        fitMode = true;
    }

    function updateSlotSizes() {
        var width =
            Math.round(baseWidth * zoom);

        var height =
            Math.round(baseHeight * zoom);

        for (var p = 1;
             p <= pageCount;
             p++) {

            if (!loaded[p]) {
                slots[p].style.width =
                    width + 'px';

                slots[p].style.height =
                    height + 'px';
            }
        }
    }

    function updateCurrentPage() {
        var scrollerRect =
            scroller.getBoundingClientRect();

        var center =
            scrollerRect.top
            + scroller.clientHeight / 2;

        var bestPage = currentPage;
        var bestDistance = Infinity;

        for (var p = 1;
             p <= pageCount;
             p++) {

            var rect =
                slots[p].getBoundingClientRect();

            var pageCenter =
                rect.top + rect.height / 2;

            var distance =
                Math.abs(
                    pageCenter - center
                );

            if (distance < bestDistance) {
                bestDistance = distance;
                bestPage = p;
            }
        }

        currentPage = bestPage;
        pageInput.value =
            String(currentPage);

        unloadDistantPages();
    }

    function unloadDistantPages() {
        Object.keys(loaded)
            .forEach(function (key) {
                var p = parseInt(key, 10);

                /*
                 * Keep current page +/- 3 pages.
                 */
                if (Math.abs(
                    p - currentPage
                ) > 3) {
                    unloadPage(p);
                }
            });
    }

    function unloadPage(page) {
        var item = loaded[page];

        if (!item) {
            return;
        }

        var canvas = item.canvas;

        canvas.width = 1;
        canvas.height = 1;

        slots[page].innerHTML =
            '<div class="sec-page-status">'
            + 'Page ' + page
            + '</div>';

        delete loaded[page];

        updateSlotSizes();
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var args = arguments;
            var self = this;

            clearTimeout(timer);

            timer = setTimeout(
                function () {
                    fn.apply(self, args);
                },
                wait
            );
        };
    }
})();
```

------------------------------------------------------------------------

# 15. Important Viewer Improvement for Large Documents

The example `updateCurrentPage()` scans every placeholder. For a
500-page document this is usually still manageable, but a production
viewer can improve it by maintaining an `IntersectionObserver` for
current-page visibility rather than scanning every page on each
debounced scroll event.

The important memory rule remains:

``` text
500 page placeholders
        |
only nearby images requested
        |
only current +/- 3 canvases retained
```

------------------------------------------------------------------------

# 16. CSRF Consideration

The page endpoint is read-only from a business perspective, but it
exposes confidential content. Its principal defenses are:

-   Authenticated session.
-   Mode-A certificate-authenticated state.
-   View token.
-   Same-origin requests.
-   Authorization on every page.
-   XSS protection.
-   Rate limiting.

For state-changing endpoints such as creating permissions, importing
documents, changing certificates, or administrative actions, use your
ZF1 CSRF protection.

If your policy also requires CSRF on the page endpoint, issue a
multi-tab-safe session CSRF token and send it in a custom header such
as:

``` javascript
headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken
}
```

Do not rotate the only valid CSRF token merely because another browser
tab opened.

------------------------------------------------------------------------

# 17. Recommended Security Headers

Configure headers at Apache/application level as appropriate:

``` text
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
Cache-Control: no-store
```

Use a restrictive Content Security Policy tailored to the real
application.

For example, after removing inline scripts/styles:

``` text
Content-Security-Policy:
  default-src 'self';
  script-src 'self';
  style-src 'self';
  img-src 'self' blob:;
  connect-src 'self';
  object-src 'none';
  frame-ancestors 'self';
  base-uri 'self';
  form-action 'self';
```

Do not copy this blindly if other required application resources need
additional sources. Build the final CSP from the application's actual
dependencies.

------------------------------------------------------------------------

# 18. Apache Storage Protection

The preferred design is to place SEC storage completely outside the
document root.

For defense in depth, if Apache could ever reach a protected directory,
deny access explicitly.

Apache 2.4 example:

``` apache
<Directory "/secure/sec">
    Require all denied
</Directory>
```

Do not expose `/secure/sec/pages` using an `Alias`.

All page delivery must pass through the authorized controller/API.

------------------------------------------------------------------------

# 19. Preventing Direct Page Cache Access

Never do this:

``` text
https://internal-site/rendered/184/page-001.png
```

Instead:

``` text
POST /sec-viewer/page
```

The clean page cache has no public URL.

The API validates the user before loading the clean page and applies a
personalized watermark before returning it.

------------------------------------------------------------------------

# 20. Rate Limiting

A simple database or in-memory limiter can count requests by:

``` text
staff_id
view_session_id
document_id
time bucket
```

For example, allow a configurable number of page requests per minute
with a small burst allowance.

Do not use an extremely low threshold because continuous scrolling may
legitimately request several pages quickly.

Log repeated excessive requests as a security event.

------------------------------------------------------------------------

# 21. Race Conditions in Page Rendering

Two requests may try to render the same uncached page simultaneously.

Production options:

1.  Use a filesystem lock.
2.  Use a database/cache lock.
3.  Render to random temporary names and atomically rename.
4.  If another request already created the final page, discard the
    duplicate.

The sample renderer uses the third/fourth approach.

------------------------------------------------------------------------

# 22. Cache Invalidation

The cache directory should be tied to `document_version_id`, not only
`document_id`.

Example:

``` text
/secure/sec/pages/991/normal/page-000001.png
```

where `991` is the immutable document-version record.

A new SEC document version therefore cannot accidentally reuse pages
from the previous version.

------------------------------------------------------------------------

# 23. Do Not Cache Personalized Pages

Recommended:

``` text
Clean rendered page:
    protected server cache

Personalized watermarked page:
    generated for response
    not persistently cached
```

Otherwise, Staff B could accidentally receive a page watermarked for
Staff A if a cache key is wrong.

------------------------------------------------------------------------

# 24. Watermark Data

Good watermark fields:

``` text
CONCHERON — SEC
Company A
Employee EMP-A-0041
Document SEC-2026-000184
View A72F91C3D2
Timestamp
```

Never watermark:

``` text
PHPSESSID
raw view_token
CSRF token
password
USB PIN
certificate private key
server private key
document encryption key
```

------------------------------------------------------------------------

# 25. Image Format

WebP is useful for reducing bandwidth.

The flow is:

``` text
PDF
 |
server render
 |
PNG clean cache
 |
Imagick watermark
 |
WebP HTTP response
 |
ImageBitmap
 |
Canvas
```

Keeping a lossless clean PNG cache avoids repeatedly degrading a
previously compressed image.

------------------------------------------------------------------------

# 26. Page API Error Policy

Use generic client-facing errors:

``` json
{
  "success": false,
  "error_code": "SEC_E_VIEW_SESSION"
}
```

Do not return:

``` text
/private/sec/keys/server-private.pem
OpenSSL stack traces
SQL statements
filesystem stack traces
raw exception dumps
```

Detailed diagnostics belong in restricted logs.

------------------------------------------------------------------------

# 27. XSS Is Critical

Because the authorized browser temporarily holds a valid view token, an
XSS vulnerability could make confidential page requests in the user's
session.

Therefore:

-   Escape HTML output.
-   Avoid unsafe `innerHTML` with server/user data.
-   Use a restrictive CSP.
-   Avoid third-party JavaScript on the SEC viewer.
-   Keep dependencies local/offline.
-   Validate all application inputs.
-   Do not insert document titles/staff names directly into JavaScript
    source.
-   Protect session cookies with `HttpOnly`, `Secure`, and appropriate
    `SameSite`.

------------------------------------------------------------------------

# 28. Temporary PDF Cleanup

The sample uses:

``` php
try {
    // decrypt/render
} finally {
    @unlink($tmpPdf);
}
```

This ensures cleanup after most application failures.

However, deletion is not guaranteed physical erasure on SSDs and some
filesystems.

For stronger protection:

-   Use encrypted storage for the temp volume.
-   Keep the temp directory outside web root.
-   Restrict service permissions.
-   Minimize plaintext lifetime.
-   Consider RAM-backed encrypted/controlled temporary storage where
    operationally appropriate.
-   Prefer a future native streaming pipeline where the renderer can
    consume decrypted bytes without a persistent plaintext master file.

------------------------------------------------------------------------

# 29. Page Count

Determine `page_count` during the trusted import/render preparation
stage and store it in `sec_document_versions`.

Do not let the browser claim the page count.

The API must reject:

``` text
page < 1
page > stored page_count
```

------------------------------------------------------------------------

# 30. Recommended Import-Time Preparation

For documents where storage/performance permits it:

``` text
SEC import
   |
decrypt package
   |
validate PDF
   |
determine page count
   |
render NORMAL pages
   |
protected clean cache
   |
server-key re-encrypt original
   |
delete plaintext
```

This makes first viewing much faster.

High-resolution pages can be generated lazily.

For very large document collections, render both normal and high
profiles lazily according to policy.

------------------------------------------------------------------------

# 31. Example Request

Browser:

``` http
POST /sec-viewer/page
Content-Type: application/json
Accept: image/webp
Cookie: <secure session cookie>

{
  "document_id": 184,
  "version_id": 991,
  "page": 5,
  "profile": "normal",
  "view_token": "<temporary random token>"
}
```

Server:

``` text
Session valid?
    |
Certificate-authenticated state valid?
    |
View token valid?
    |
Token bound to staff + version?
    |
Permission still valid?
    |
Page valid?
    |
Rate allowed?
    |
Load/generate clean page
    |
Apply staff-specific watermark
    |
Audit
    |
Return image/webp
```

------------------------------------------------------------------------

# 32. Suggested HTTP Response

``` http
HTTP/1.1 200 OK
Content-Type: image/webp
Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
X-Content-Type-Options: nosniff
Content-Disposition: inline
```

Do not send a filesystem path or original PDF filename.

------------------------------------------------------------------------

# 33. Recommended Production Enhancements

Before production deployment, add:

1.  A real `concheron_sec_decrypt_to_temp()` or native render operation.
2.  Hardware-backed server-key integration.
3.  Strong Mode-A staff certificate challenge-response.
4.  Certificate revocation/trust update handling.
5.  Rate limiter.
6.  Render-worker queue for expensive first-time rendering.
7.  Filesystem/process sandboxing for the PDF renderer.
8.  Resource limits for malformed/hostile PDFs.
9.  PDF renderer security updates and regression testing.
10. Audit retention and tamper protection.
11. Content Security Policy.
12. Automated session expiration.
13. Document permission-change invalidation.
14. Administrative termination of active view sessions.
15. Security testing of IDOR, XSS, CSRF, path traversal, command
    injection, and authorization bypass.
16. Load testing with realistic 100-staff concurrency.
17. Large-document testing, including 500+ page PDFs.

------------------------------------------------------------------------

# 34. Security Test Checklist

## Authorization

-   User cannot request a document they do not have permission to view.
-   Changing `document_id` does not bypass authorization.
-   Changing `version_id` does not expose another version.
-   A token from Staff A does not work for Staff B.
-   A token for Document A does not work for Document B.
-   Revoked permission blocks subsequent page requests.

## View Sessions

-   Random invalid token fails.
-   Expired token fails.
-   Idle token fails.
-   Logout invalidates/blocks viewing according to policy.
-   Raw token is not stored in database logs.

## Page API

-   Page 0 fails.
-   Negative page fails.
-   Page above `page_count` fails.
-   Invalid resolution profile fails.
-   Direct cache path is inaccessible.
-   Original PDF has no HTTP endpoint.

## Rendering

-   Filenames cannot inject shell commands.
-   Document title cannot inject shell commands.
-   Page parameter cannot inject shell commands.
-   Renderer failure does not leave public plaintext.
-   Temporary PDF is removed after success/failure.

## Watermark

-   Staff identity is correct.
-   Document code is correct.
-   View display code maps to audit record.
-   Raw authentication tokens never appear.
-   Different staff receive different attribution.

## Browser

-   Normal wheel scroll works.
-   Ctrl+wheel zoom works.
-   Page input scrolls to requested page.
-   Lazy loading works.
-   Distant canvases unload.
-   Returning to an unloaded page reloads it.
-   Original PDF is never visible in browser network requests.

------------------------------------------------------------------------

# 35. Final Recommended Production Flow

``` text
                 ENCRYPTED MASTER PDF
                         |
                         v
               Server authorization
                         |
                         v
             Concheron native extension
                         |
                         v
            Temporary controlled plaintext
                         |
                         v
                 Poppler rendering
                         |
                         v
              CLEAN PROTECTED CACHE
                         |
                secure page request
                         |
        +----------------+----------------+
        |                                 |
        v                                 v
Session + staff cert               View token
        |                                 |
        +----------------+----------------+
                         |
                         v
               Permission re-check
                         |
                         v
                   Rate limit
                         |
                         v
            Personalized watermark
                         |
                         v
                   Audit event
                         |
                         v
               image/webp response
                         |
                         v
                JavaScript Canvas
```

This design does not claim that visible information can be made
impossible to copy. Its purpose is to keep the original SEC PDF and
cryptographic keys away from the browser, strongly control each page
request, minimize plaintext exposure on the server, and make viewed
material attributable through personalized watermarks and audit records.

# CentOS Deployment Checklist

Before enabling the SEC viewer for staff, confirm:

-   CentOS security updates and approved package versions are current.
-   SELinux is enabled and the SEC application has only required access.
-   Apache serves the SEC application through HTTPS.
-   `/secure/sec` is outside the document root.
-   `/secure/sec` is not exposed through Apache aliases.
-   Unix ownership and permissions are restrictive.
-   `pdftoppm` uses a fixed administrator-configured path.
-   Imagick/WebP capability has been tested.
-   `concheron_sec.so` matches the installed PHP 7.4 build.
-   Concheron trust material is readable only by required services.
-   Original encrypted PDFs have no public URL.
-   Temporary plaintext PDFs cannot be accessed over HTTP.
-   Clean page-cache files have no public URL.
-   Personalized pages are generated only after authorization.
-   View tokens are short-lived and stored only as hashes server-side.
-   Permission is rechecked for every page request.
-   Rate limiting is enabled.
-   Audit logging is enabled.
-   Logs contain no passwords, PINs, private keys, CEKs, or raw view
    tokens.
-   XSS and authorization testing has been completed.
-   Temporary-file cleanup has been tested during renderer failures.
-   Large PDFs and concurrent users have been load-tested.
-   Backup procedures do not accidentally archive plaintext temporary
    files.
