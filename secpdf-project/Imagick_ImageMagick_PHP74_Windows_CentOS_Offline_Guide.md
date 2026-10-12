# ImageMagick and Imagick: PHP 7.4 Installation and Practical Guide

**Version:** 1.0  
**Scope:** Windows 11/XAMPP development; CentOS offline production; PHP 7.4; Poppler integration  
**Status:** Technical reference and example code. Verify compatibility against the exact OS and PHP builds before deployment.

## 1. Overview

**ImageMagick** is an image-processing library and command-line toolkit. **Imagick** is a PHP extension exposing ImageMagick operations. They are separate installations. ImageMagick can process PNG/JPEG/TIFF and many other formats; the exact formats depend on build options, installed delegates, and security policy.

```text
PHP 7.4 application → Imagick extension (imagick.so / php_imagick.dll)
                    → ImageMagick shared libraries → image operations

Encrypted SEC PDF → authorized server-side decryption → Poppler PDF rendering
                  → PNG → Imagick watermark/mask → authorized image response
```

For the Concheron SEC viewer, prefer **Poppler for PDF parsing, page rendering and text-coordinate extraction**, and **Imagick for manipulating the rendered image**. Imagick alone is not an access-control or anti-download solution.

## 2. Imagick vs. GD vs. Poppler

| Tool | Primary role | Example |
|---|---|---|
| PHP GD | Simple image operations | Thumbnails |
| ImageMagick CLI | Shell-based image processing | `magick input.png -resize 800x600 output.png` (ImageMagick 7) |
| PHP Imagick | ImageMagick operations in PHP | `new Imagick('input.png')` |
| Poppler | PDF inspection, rasterization and text extraction | `pdftoppm`, `pdftotext -bbox-layout` |

ImageMagick 6 typically exposes `convert`, `identify`, and `mogrify`; ImageMagick 7 primarily exposes `magick`. On Windows, prefer `magick` or a full path rather than `convert`, which may refer to a Windows system utility.

## 3. Prerequisites and compatibility

Before choosing binaries, record:

- Windows: PHP version, x86/x64 architecture, TS/NTS, compiler build, extension directory, and DLL dependencies.
- Linux: distribution/version, architecture, PHP module API, PHP `php-config`/`phpize`, ImageMagick library ABI, GLIBC and library dependencies.
- ImageMagick's **Q8/Q16** and **HDRI** variants affect ABI; avoid mixing incompatible builds.
- PHP 7.4 reached upstream end of life. Treat it as a legacy compatibility constraint and isolate the deployment.

**Do not assume a current Imagick release or Windows DLL supports PHP 7.4.** Select an explicitly compatible historical build and verify it in a test environment.

## 4. Windows 11 / XAMPP installation

### 4.1 Inspect PHP

In PowerShell (adjust XAMPP location):

```powershell
& 'C:\xampp\php\php.exe' -v
& 'C:\xampp\php\php.exe' -i | Select-String 'Architecture|Thread Safety|Compiler|extension_dir'
& 'C:\xampp\php\php.exe' --ini
```

Also inspect PHP through an Apache-served `phpinfo()` page: CLI PHP and Apache PHP may differ.

### 4.2 Obtain compatible software

1. Download ImageMagick from the official Windows downloads page, choosing a runtime compatible with your Imagick build.
2. Download a **PHP 7.4-compatible** Imagick DLL package from the PHP Windows PECL release archive.
3. Match PHP 7.4, architecture, thread-safety, compiler/runtime and ImageMagick ABI. Do not combine random DLLs from unrelated releases.
4. Install ImageMagick and ensure its runtime DLLs are available to the Apache/PHP process using a documented DLL search path.
5. Copy `php_imagick.dll` to the PHP extension directory, e.g. `C:\xampp\php\ext`.

Reference downloads:

- ImageMagick: https://imagemagick.org/script/download.php#windows
- Windows PECL archives: https://windows.php.net/downloads/pecl/releases/imagick/
- PHP extension documentation: https://www.php.net/manual/en/book.imagick.php

### 4.3 Enable and verify

Add **one** line to the `php.ini` loaded by Apache:

```ini
extension=imagick
```

Restart Apache and test:

```powershell
& 'C:\xampp\php\php.exe' -m | Select-String -Pattern '^imagick$'
& 'C:\xampp\php\php.exe' --ri imagick
```

Web test (`imagick-status.php`, remove after verification):

```php
<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP: ', PHP_VERSION, "\n";
echo 'Imagick loaded: ', extension_loaded('imagick') ? 'yes' : 'no', "\n";
if (extension_loaded('imagick')) {
    $v = Imagick::getVersion();
    echo 'ImageMagick: ', $v['versionString'], "\n";
    echo 'PNG available: ', count(Imagick::queryFormats('PNG')) ? 'yes' : 'no', "\n";
}
```

If Apache fails to load the extension, inspect the Apache error log, DLL dependency compatibility, PATH and Visual C++ runtime. A successful CLI check alone does not prove the web SAPI loads it.

## 5. CentOS online installation (reference)

**First identify the exact OS and PHP packages**:

```bash
cat /etc/os-release
uname -m
php -v
php --ini
php -i | grep -E 'Thread Safety|extension_dir'
command -v phpize && phpize --version
command -v php-config && php-config --version
```

If your compatible repositories provide the packages:

```bash
# CentOS Stream / other DNF-based systems
sudo dnf install ImageMagick ImageMagick-devel
sudo dnf install php-pecl-imagick

# Legacy Yum-based systems, if compatible repositories exist
sudo yum install ImageMagick ImageMagick-devel
sudo yum install php-pecl-imagick
```

**Caution:** Repository packages might target a different PHP version. Check RPM dependencies and PHP ABI first. PHP development headers are needed for compiling, not ordinarily for production runtime.

Verify:

```bash
php --ri imagick
php -r 'var_export(Imagick::getVersion()); echo PHP_EOL;'
convert -version  # ImageMagick 6
magick -version   # ImageMagick 7, if installed
```

## 6. Offline CentOS installation using RPMs (preferred)

### 6.1 Build/download host

Use an Internet-connected VM matching the **target OS release, architecture and repository configuration**. Do not assume packages from a newer distribution will run on an older one.

```bash
sudo dnf install -y dnf-plugins-core createrepo_c
mkdir -p "$HOME/imagick-offline"
dnf download --resolve --alldeps \
  --destdir="$HOME/imagick-offline" \
  ImageMagick php-pecl-imagick
createrepo_c "$HOME/imagick-offline"
```

The `php-pecl-imagick` package **must match PHP 7.4**. If it does not, use the source-build method below. On CentOS 7, use a matching Yum-era download workflow such as `repotrack` and `createrepo`, with repositories appropriate to that EOL release; prefer a supported OS for security-sensitive deployments.

Record RPM names, versions and checksums:

```bash
find "$HOME/imagick-offline" -name '*.rpm' -print | sort
(cd "$HOME/imagick-offline" && sha256sum ./*.rpm > SHA256SUMS)
```

Verify upstream package signatures using trusted RPM signing keys. A checksum file transferred alongside an archive is not by itself an authenticated provenance check.

### 6.2 Transfer and install on the offline server

Transfer via the organization's approved removable-media/dongle procedure. Put the directory at `/opt/offline-repos/imagick` and configure:

```ini
# /etc/yum.repos.d/imagick-offline.repo
[imagick-offline]
name=Imagick Offline Repository
baseurl=file:///opt/offline-repos/imagick
enabled=1
gpgcheck=1
```

Import only trusted signing keys validated through a separate trusted channel, then:

```bash
sudo dnf --disablerepo='*' --enablerepo='imagick-offline' \
  install ImageMagick php-pecl-imagick
php --ri imagick
```

Use `yum` instead of `dnf` where appropriate. If the solver reports missing dependencies, add the **matching** RPMs; do not use `rpm --nodeps` or globally disable signature verification.

## 7. Build Imagick from source for PHP 7.4

Use this route if compatible binary RPMs are unavailable. Build on a **matching nonproduction Linux VM**, preferably with the same PHP 7.4 runtime and ImageMagick packages as the offline server.

### 7.1 Install matching build dependencies

```bash
sudo dnf install -y gcc make autoconf automake libtool \
  pkgconf-pkg-config ImageMagick ImageMagick-devel php-devel
phpize --version
php-config --version
MagickWand-config --version 2>/dev/null || true
pkg-config --modversion MagickWand 2>/dev/null || true
```

The PHP development package **must correspond to the exact target PHP 7.4 installation**. PHP 7.4's Zend module API is `20190902`; check the actual installed headers and binary.

### 7.2 Build

Download and verify a **release explicitly compatible with PHP 7.4** from https://pecl.php.net/package/imagick or https://github.com/Imagick/imagick . Replace `VERSION` below:

```bash
tar -xzf imagick-VERSION.tgz
cd imagick-VERSION
phpize
./configure --with-php-config="$(command -v php-config)"
make -j"$(nproc)"
make test
```

Tests may need interactive answers and suitable test resources; review failures. Locate the built module:

```bash
find . -path '*/modules/imagick.so' -type f -print
file modules/imagick.so
ldd modules/imagick.so
```

On the build VM, `sudo make install` is acceptable for testing, but for offline deployment **package the module and compatible ImageMagick runtime libraries** instead of copying it blindly. Build an RPM if possible; preserve ownership, SELinux labeling and uninstall/upgrade records.

### 7.3 Enable on target

After the module and required runtime libraries are installed in compatible locations:

```bash
php --ini
php-config --extension-dir
# In the appropriate conf.d location, e.g. /etc/php.d/40-imagick.ini:
# extension=imagick.so
php --ri imagick
```

Restart the actual PHP SAPI (`httpd`, `php-fpm`, or a versioned PHP-FPM service). Confirm CLI **and** web PHP load the extension.

### 7.4 Runtime compatibility checks

```bash
file "$(php-config --extension-dir)/imagick.so"
ldd "$(php-config --extension-dir)/imagick.so"
php --ri imagick
```

Check for `not found`, mismatched `libMagickWand`/`libMagickCore`, incorrect GLIBC, or wrong PHP module API. `ldd` is diagnostic, not a complete dependency audit; test in a clean target-equivalent environment.

## 8. Optional: Build ImageMagick itself from source

Only needed if distribution RPMs cannot provide a compatible runtime. Source build complexity increases because delegate libraries (PNG/JPEG/TIFF, fonts, color management) must also be provisioned.

```bash
# Illustrative ImageMagick 7 Autotools workflow; consult chosen release's INSTALL
./configure --prefix=/opt/concheron/imagemagick \
  --with-modules=no --with-perl=no
make -j4
make check
sudo make install
```

**Do not copy these options blindly**: configure options differ by ImageMagick release, and the extension must be built against this same installation. For a custom prefix, point `pkg-config`, compiler and runtime linker to the matching libraries. Prefer package-managed builds on a matched OS when possible.

## 9. Command-line ImageMagick examples

```bash
# Version and supported formats
magick -version
magick -list format
magick identify input.png

# Resize while preserving aspect ratio
magick input.png -resize 1200x output.png

# Crop 500x300 starting at x=100, y=200
magick input.png -crop 500x300+100+200 +repage crop.png

# Rotate with white background
magick input.png -background white -rotate 30 rotated.png

# Convert PNG to JPEG (flatten transparency)
magick input.png -background white -alpha remove -alpha off -quality 85 output.jpg

# Add a basic label; font must be available
magick input.png -gravity southeast -pointsize 24 \
  -fill 'rgba(80,80,80,0.25)' -annotate +20+20 'CONFIDENTIAL' marked.png
```

For ImageMagick 6, use `convert` instead of `magick` for many operations, after checking available CLI options. Avoid putting confidential PDFs/images in public directories.

## 10. PHP 7.4 examples

### 10.1 Create an image in memory

```php
<?php
$image = new Imagick();
try {
    $image->newImage(800, 500, new ImagickPixel('white'), 'png');
    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('#1a365d'));
    $draw->setFontSize(36);
    $draw->annotation(60, 120, 'Imagick is working');
    $image->drawImage($draw);
    $image->writeImage(__DIR__ . '/created.png');
} finally {
    $image->clear();
}
```

### 10.2 Resize without stretching

```php
<?php
$image = new Imagick(__DIR__ . '/input.png');
try {
    $image->resizeImage(1200, 0, Imagick::FILTER_LANCZOS, 1);
    $image->setImageFormat('png');
    $image->writeImage(__DIR__ . '/resized.png');
} finally {
    $image->clear();
}
```

### 10.3 Crop and reset page geometry

```php
<?php
$image = new Imagick(__DIR__ . '/input.png');
try {
    $image->cropImage(500, 300, 100, 200);
    $image->setImagePage(0, 0, 0, 0);
    $image->writeImage(__DIR__ . '/cropped.png');
} finally {
    $image->clear();
}
```

### 10.4 Transparent watermark overlay

```php
<?php
$page = new Imagick(__DIR__ . '/page1.png');
$overlay = new Imagick();
try {
    $w = $page->getImageWidth();
    $h = $page->getImageHeight();
    $overlay->newImage($w, $h, new ImagickPixel('transparent'), 'png');
    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('rgba(100,100,100,0.16)'));
    $draw->setFontSize(44);
    $draw->setGravity(Imagick::GRAVITY_CENTER);
    // Use an explicitly installed font in production:
    // $draw->setFont('/usr/share/fonts/.../SomeFont.ttf');
    $overlay->annotateImage($draw, 0, 0, -30, 'CONFIDENTIAL');
    $page->compositeImage($overlay, Imagick::COMPOSITE_OVER, 0, 0);
    $page->setImageFormat('png');
    $page->writeImage(__DIR__ . '/watermarked.png');
} finally {
    $overlay->clear();
    $page->clear();
}
```

### 10.5 Personalized watermark

```php
<?php
// In production, username and timestamp must come from trusted server-side session/audit data.
$username = 'Authorized User';
$timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
$label = "CONFIDENTIAL\nUser: {$username}\n{$timestamp}";
// Use the overlay approach above, replacing the annotation text with $label.
// Select a known font and test multiline layout across deployed versions.
```

### 10.6 Exclude protected text rectangles from the watermark

For a production SEC viewer, use Poppler `pdftotext -bbox-layout` to locate protected phrases. Convert PDF coordinates to raster pixels **only after accounting for rotation, crop boxes and rendering geometry**. Add padding around each protected region.

A reliable conceptual method is to create the watermark overlay and then clear rectangles using a destination-out composition. The following example uses a **separate transparent eraser image**:

```php
<?php
$page = new Imagick(__DIR__ . '/page1.png');
$overlay = new Imagick();
$eraser = new Imagick();
try {
    $w = $page->getImageWidth();
    $h = $page->getImageHeight();
    $overlay->newImage($w, $h, new ImagickPixel('transparent'), 'png');
    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('rgba(100,100,100,0.16)'));
    $draw->setFontSize(48);
    $draw->setGravity(Imagick::GRAVITY_CENTER);
    $overlay->annotateImage($draw, 0, 0, -30, 'CONFIDENTIAL');

    // EXAMPLE ONLY: coordinates are pixels, not raw PDF points.
    // Production rectangles must be calculated and validated from Poppler.
    $protected = array(array('x' => 240, 'y' => 400, 'w' => 180, 'h' => 30));
    $eraser->newImage($w, $h, new ImagickPixel('transparent'), 'png');
    $maskDraw = new ImagickDraw();
    $maskDraw->setFillColor(new ImagickPixel('white'));
    $maskDraw->setStrokeColor(new ImagickPixel('transparent'));
    foreach ($protected as $r) {
        $pad = 8;
        $x1 = max(0, $r['x'] - $pad);
        $y1 = max(0, $r['y'] - $pad);
        $x2 = min($w - 1, $r['x'] + $r['w'] + $pad);
        $y2 = min($h - 1, $r['y'] + $r['h'] + $pad);
        $maskDraw->rectangle($x1, $y1, $x2, $y2);
    }
    $eraser->drawImage($maskDraw);
    $overlay->compositeImage($eraser, Imagick::COMPOSITE_DSTOUT, 0, 0);
    $page->compositeImage($overlay, Imagick::COMPOSITE_OVER, 0, 0);
    $page->writeImage(__DIR__ . '/protected-watermark.png');
} finally {
    $eraser->clear();
    $overlay->clear();
    $page->clear();
}
```

**Validation required:** Composite operator semantics, alpha-channel handling, anti-aliasing, font metrics, and rotation can vary with ImageMagick version. Inspect pixel results with test documents before production deployment. Protected-region masking prevents watermark overlap; it does not hide or redact the underlying text.

### 10.7 Return PNG bytes from an authorized API

```php
<?php
// Illustrative endpoint. Authorize document/page access BEFORE loading image.
// $authService->assertCanView($currentUser, $documentId, $pageNumber);
$image = new Imagick('/var/sec/private/watermarked/page1.png');
try {
    $image->setImageFormat('png');
    header('Content-Type: image/png');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo $image->getImageBlob();
} finally {
    $image->clear();
}
```

A browser can still capture displayed image bytes or pixels. Authentication, short-lived authorization, audit logging, watermarking and endpoint controls are complementary deterrents, not guarantees against copying.

### 10.8 Error handling

```php
<?php
try {
    $image = new Imagick('/var/sec/private/page.png');
    try {
        $image->setImageFormat('png');
        $image->writeImage('/var/sec/private/output.png');
    } finally {
        $image->clear();
    }
} catch (ImagickException $e) {
    error_log('Imagick failure: ' . $e->getMessage());
    http_response_code(500);
    echo 'Image processing failed.';
}
```

## 11. Poppler + Imagick combined workflow

```bash
# Render first page at 144 DPI
pdftoppm -f 1 -l 1 -singlefile -r 144 -png \
  /var/sec/private/report.pdf /var/sec/tmp/page1

# Extract word bounding boxes for the same page
pdftotext -f 1 -l 1 -bbox-layout \
  /var/sec/private/report.pdf /var/sec/tmp/page1-words.html
```

Then PHP:

1. Authorizes the user and page.
2. Obtains the decrypted PDF in a protected, short-lived server-side workspace.
3. Invokes Poppler with fixed executable paths, escaped filenames, bounded page numbers, timeouts and resource limits.
4. Parses `pdftotext` output safely; groups words into protected phrases.
5. Converts bounding boxes to image pixels and validates geometry.
6. Creates a personalized watermark overlay with Imagick.
7. Removes watermark pixels over protected text regions.
8. Encodes the final PNG, returns it via the authorized page API, and audits access.
9. Securely cleans up temporary data according to policy; never expose the original plaintext PDF to the browser.

Do not execute PDF tools with unbounded user-controlled inputs. Consider process isolation, low-privilege worker accounts, filesystem limits, SELinux, CPU/memory limits, and document-size/page-count limits.

## 12. ImageMagick security policy

ImageMagick's `policy.xml` controls resource limits and potentially risky coders/delegates. Paths vary by version/distribution (e.g. `/etc/ImageMagick-6/policy.xml` or `/etc/ImageMagick-7/policy.xml`). Locate the active policy using your installation documentation and `magick identify -list policy` or ImageMagick 6 equivalents.

Recommended SEC controls:

- Restrict ImageMagick to the formats required for raster image handling (e.g. PNG/JPEG), where practical.
- Do **not** enable PDF/PS coders just to bypass an authorization error; use Poppler for PDF rendering.
- Limit width/height, memory, map, disk, threads and execution time.
- Place temporary pixel-cache files in private, quota-limited storage.
- Do not let untrusted users choose arbitrary filesystem paths or delegate commands.
- Patch both ImageMagick and Poppler, and record versions for incident response.

Official policy reference: https://imagemagick.org/script/security-policy.php

## 13. Performance notes

- A 2000 × 3000 RGBA image requires roughly **22.9 MiB** for one raw pixel buffer; multiple overlays/intermediate buffers increase memory use.
- Prefer 144 DPI as an initial text-document rendering baseline; measure readability and resource use.
- PNG is lossless and generally appropriate for sharp text; JPEG can introduce edge artifacts.
- Bound worker concurrency. Test worst-case page dimensions and complex PDFs.
- Avoid retaining unwatermarked pages in accessible caches. Encrypt retained confidential intermediates and enforce retention policies.

## 14. Troubleshooting

| Symptom | Likely cause | Investigation |
|---|---|---|
| `Class Imagick not found` | Extension not loaded | `php --ini`, `php -m`, web `phpinfo()` |
| `Unable to load dynamic library` | Missing/incompatible DLL/SO | Apache logs; `ldd` on Linux; Windows DLL dependencies |
| PHP module API mismatch | Wrong PHP build | Check PHP 7.4 ABI and `php-config` |
| `undefined symbol` | ImageMagick ABI mismatch | Inspect MagickWand/Core versions |
| `not authorized` | ImageMagick policy restriction | Inspect active `policy.xml`; do not broadly disable security |
| `no decode delegate` | Unsupported/missing coder | `magick -list format` or `convert -list format` |
| CLI works, Apache fails | Different PHP configuration or environment | Compare loaded `php.ini`, PATH, service identity |
| Blank watermark | Font unavailable or alpha issues | Set explicit font; verify output pixel alpha |
| High memory usage | Large images/multiple buffers | Reduce DPI, cap concurrency and resource limits |
| Protected text overlapped | Wrong coordinate conversion | Check rotation/crop, DPI, margins and overlay composition |

## 15. Deployment checklist

- [ ] Record OS release and architecture.
- [ ] Record PHP 7.4 version, thread safety and module API.
- [ ] Select compatible ImageMagick and Imagick versions.
- [ ] Verify downloaded source/RPM signatures and checksums.
- [ ] Build on a matching nonproduction host.
- [ ] Test `imagick.so`/DLL with the actual PHP runtime.
- [ ] Package all runtime dependencies; check for missing libraries.
- [ ] Transfer using approved offline media process.
- [ ] Install without enabling online repositories.
- [ ] Confirm CLI and Apache/PHP-FPM extension loading.
- [ ] Test PNG creation, resize, crop and transparent watermark.
- [ ] Test protected-text watermark masking with real PDFs.
- [ ] Configure restrictive ImageMagick security/resource policies.
- [ ] Ensure only authorized users can request SEC page images.
- [ ] Document rollback, version pinning and security-update procedure.

## 16. Frequently asked questions

**Is Imagick bundled with PHP?** No. Install it separately.

**Does Imagick require ImageMagick?** Yes, the extension uses ImageMagick libraries.

**Can Imagick work without Poppler?** Yes, for ordinary images. Poppler is recommended for the SEC PDF workflow.

**Can Imagick read PDFs directly?** Sometimes, through configured PDF delegates, but this may be blocked by policy. Prefer Poppler in this design.

**Can it run offline?** Yes, after installation of all required runtime dependencies.

**Does watermarking prevent downloading/screenshots?** No. It provides identification and deterrence, not absolute protection.

**Should I use the latest Imagick with PHP 7.4?** Not automatically. Use a verified compatible release or upgrade PHP.

## 17. References

- ImageMagick project: https://imagemagick.org/
- ImageMagick security policy: https://imagemagick.org/script/security-policy.php
- PHP Imagick manual: https://www.php.net/manual/en/book.imagick.php
- PHP Imagick class: https://www.php.net/manual/en/class.imagick.php
- Imagick PECL releases: https://pecl.php.net/package/imagick
- Imagick source: https://github.com/Imagick/imagick
- Windows PECL archives: https://windows.php.net/downloads/pecl/releases/imagick/
- Poppler project: https://poppler.freedesktop.org/

---

**Note:** The commands are detailed templates, not a validated bill of materials for a specific CentOS host. Before production deployment, confirm `cat /etc/os-release`, `uname -m`, `php -v`, `php-config --version`, and your ImageMagick runtime version. This guide deliberately avoids claiming that one binary package works across all PHP 7.4 and CentOS installations.
