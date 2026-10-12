# Poppler: Offline Build, Installation, and PHP 7.4 Integration Guide

**Project:** Concheron SEC confidential PDF viewing system  
**Document type:** Technical reference and deployment guide  
**Revision:** 1.1 — 12 October 2026 (expanded command-line and PHP examples)  
**Target:** Offline CentOS server; PHP 7.4; Apache/Zend Framework 1; Firefox SEC viewer  
**Status:** Version-specific installation commands must be validated after confirming the target CentOS release.

---

## 1. Executive summary

Poppler is an open-source PDF processing library and suite of command-line utilities. The Concheron SEC server can use Poppler to inspect PDF metadata, render individual pages to images, and extract text bounding boxes for protected-name-aware watermarking. Poppler does **not** implement SEC package cryptography, access control, watermark composition, or OCR.

The preferred offline deployment is to build a **pinned, verified Poppler release on a dedicated machine matching the production OS and architecture**, test the runtime dependency closure in a clean target-equivalent environment, package the installed files and dependencies, and transfer the package through the approved offline process. The production SEC server should not require compilers.

An alternative, usually easier to maintain, is an offline RPM repository containing `poppler-utils` and its complete dependencies. Source compilation is appropriate when the organization requires a controlled build or needs a feature/version unavailable through supported RPMs.

> **Unresolved prerequisite:** The precise CentOS release, CPU architecture, glibc version, and build-tool versions have not been supplied. The examples below assume **CentOS Stream 9, x86_64** for illustration, not as a statement about the production server. Modern Poppler may require a newer compiler/CMake/dependencies than the target OS provides. Do not run the sample build unchanged without checking the chosen release's `INSTALL` and `CMakeLists.txt`.

## 2. Role in the Concheron SEC architecture

The established design keeps original confidential PDFs encrypted and server-side. The viewer receives only authorized, personalized, watermarked page images.

```text
Encrypted SEC package / encrypted-at-rest PDF
                    |
          Authorization + certificate check
                    |
          Server-side decryption (PHP crypto service)
                    |
          Protected PDF input in isolated worker
                    |
      +-------------+-----------------+
      |             |                 |
   pdfinfo       pdftotext         pdftoppm / pdftocairo
   metadata      -bbox-layout      page rasterization
      |             |                 |
      |       phrase detection        |
      |       and word rectangles     |
      +-------------+-----------------+
                    |
       PHP/Imagick watermark layer composition
       (avoid configured protected text regions)
                    |
        Final personalized page image
                    |
       Optional AES-256-GCM response encryption
                    |
       Authenticated HTTPS + audit logging
                    |
           Managed Firefox SEC viewer
```

Poppler only performs the PDF-specific stages. PHP coordinates permissions, key management, secure temporary files, rendering, watermark placement, auditing, and responses. Optional browser-side AES does not prevent a sufficiently capable authorized user from capturing decrypted pixels; it is an additional casual-copying deterrent, not a secure-display guarantee.

## 3. Important Poppler utilities

| Utility | Purpose | SEC usage |
|---|---|---|
| `pdfinfo` | PDF metadata, page count, dimensions | Validate requested document/page |
| `pdftoppm` | Rasterize pages to PNG/JPEG/PPM | Primary page renderer |
| `pdftocairo` | Alternative Cairo-based renderer | Benchmark/compatibility option |
| `pdftotext` | Extract text, optionally bounding boxes | Detect protected words and names |
| `pdffonts` | Inspect embedded and referenced fonts | Troubleshooting |
| `pdfimages` | Extract embedded images | Diagnostics, not full-page rendering |
| `pdfdetach` | Inspect/extract embedded attachments | Security screening if needed |
| `pdfseparate`, `pdfunite` | Split/merge PDFs | Optional internal tooling |

**Poppler is not PDF.js:** PDF.js renders PDFs in JavaScript. In this architecture, Poppler runs server-side; no original PDF is sent to the browser. **Poppler is not Imagick:** Imagick is useful for image compositing and watermark operations. **Poppler is not OCR:** scanned PDFs without a text layer require a separate OCR strategy or a safe fallback.

## 4. Reference sites and source acquisition

- Official project: https://poppler.freedesktop.org/
- Official source repository: https://gitlab.freedesktop.org/poppler/poppler
- Obtain a **specific release archive** and available signature/checksum information from the official project. Do not build the moving development branch for production.
- Record source archive name, version, SHA-256 digest, signing-key verification outcome, acquisition date, and approved transfer identifier.
- Review the release's `INSTALL`, `CMakeLists.txt`, and licensing information. Poppler's licensing and third-party dependency licenses require review before distribution.
- `poppler-data` may be useful for character mappings; it is distributed separately in some packaging arrangements. Check your document corpus and the selected release.

Do not trust a checksum stored on the same removable media as the archive without comparing it to a trusted value obtained separately. Signature verification requires an independently verified signing key.

## 5. Inventory the offline CentOS server first

Run on the **offline target server**:

```bash
cat /etc/os-release
uname -m
ldd --version | head -n 1
rpm -qa | grep -Ei '^(poppler|glibc|gcc|cmake|ninja)' || true
command -v yum || true
command -v dnf || true
command -v pdfinfo || true
command -v pdftoppm || true
command -v pdftotext || true
```

If available:

```bash
gcc --version | head -n 1
c++ --version | head -n 1
cmake --version | head -n 1
```

Capture the full package inventory and repository configuration for dependency planning. Do not assume a binary compiled on a newer distribution will run on an older CentOS target: glibc, libstdc++, and other ABI requirements matter. CentOS Linux 7 and CentOS Linux 8 are end-of-life; for sensitive production processing, an actively supported OS is strongly preferable.

## 6. Choose a deployment strategy

| Method | Advantages | Risks/limitations | Recommendation |
|---|---|---|---|
| Offline RPM repository | Dependency resolution, package records, updates | Requires compatible repositories and package closure | Best default |
| Build on production | Simple path for experiments | Adds compilers/development attack surface, dependency complexity | Avoid in production |
| Build on matching dedicated VM | Repeatable, isolates build tooling | Must package/test runtime dependencies | Preferred source-build method |
| Fully static binaries | May simplify some deployment aspects | Static dependency chain and licensing/compatibility complexities | Not first choice |

### 6.1 Alternative: offline RPM repository

On a compatible **Internet-connected** RPM environment, obtain `poppler-utils` and all dependencies. A DNF-based example is:

```bash
sudo dnf install -y dnf-plugins-core createrepo_c
mkdir -p "$HOME/poppler-offline"
dnf download --resolve --alldeps --destdir="$HOME/poppler-offline" poppler-utils
createrepo_c "$HOME/poppler-offline"
```

For CentOS 7-era tooling, `repotrack` may be available through `yum-utils`; its availability and repository state must be checked. On the offline server, after verifying package signatures and importing the correct trusted RPM keys, create a `file://` repository and install with **all network repositories disabled**:

```ini
[poppler-offline]
name=Poppler Offline
baseurl=file:///opt/offline-repos/poppler
enabled=1
gpgcheck=1
```

```bash
sudo dnf --disablerepo='*' --enablerepo='poppler-offline' install poppler-utils
# Or use yum on a yum-based target.
```

If a dependency is missing, return to the acquisition machine and complete the dependency set; do not use `rpm --nodeps` or disable signature checks as a workaround.

## 7. Source-build prerequisites

### 7.1 Dedicated build environment

Prepare an Internet-connected VM or build host that matches the **target release and architecture**. Prefer a clean VM snapshot for repeatability. If exact matching is impossible, prove runtime compatibility in a clean target-equivalent VM before deployment.

### 7.2 Typical development packages

For a DNF-based example:

```bash
sudo dnf groupinstall -y 'Development Tools'
sudo dnf install -y \
  gcc gcc-c++ cmake ninja-build pkgconf-pkg-config \
  freetype-devel fontconfig-devel \
  libjpeg-turbo-devel libpng-devel openjpeg2-devel \
  lcms2-devel zlib-devel libtiff-devel
```

This is **not** a guaranteed complete dependency list. Requirements vary by Poppler release and enabled features; some recent releases require a newer CMake and compiler than a stock CentOS installation supplies. Check the pinned source tree and CMake diagnostics before proceeding. If the build machine is also offline, prepare a separate offline RPM repository for the toolchain and dependencies.

### 7.3 Decide required features

Concheron requires `pdfinfo`, `pdftoppm`, `pdftotext -bbox-layout`, and optionally `pdftocairo`. Confirm PNG and JPEG output support as needed, and text/font handling. GUI bindings (Qt, GLib) and language wrappers are unnecessary unless explicitly required by another application.

## 8. Build Poppler from source

Replace `VERSION` with a release **verified to support the chosen OS/toolchain**.

### 8.1 Extract and inspect

```bash
tar -xf poppler-VERSION.tar.xz
cd poppler-VERSION
cat INSTALL
cmake --version
c++ --version | head -n 1
```

### 8.2 Configure

Example **subject to release-specific option validation**:

```bash
cmake -S . -B build -G Ninja \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_INSTALL_PREFIX=/opt/concheron/poppler \
  -DENABLE_UTILS=ON \
  -DENABLE_QT5=OFF \
  -DENABLE_QT6=OFF \
  -DENABLE_GLIB=OFF \
  -DENABLE_CPP=OFF
```

If a flag is unrecognized or ignored, inspect `CMakeLists.txt` and the CMake configure summary. Verify required image codecs and tools were enabled. Do not assume disabling optional bindings disables all their transitive dependencies.

### 8.3 Compile

```bash
cmake --build build --parallel 4
```

Choose parallelism according to RAM and CPU. Capture the full configure and build logs in the build record.

### 8.4 Stage installation

```bash
mkdir -p /tmp/concheron-poppler-stage
DESTDIR=/tmp/concheron-poppler-stage cmake --install build
find /tmp/concheron-poppler-stage -type f | sort
```

Expected layout (actual library folder may be `lib` or `lib64`):

```text
/tmp/concheron-poppler-stage/opt/concheron/poppler/
    bin/pdfinfo
    bin/pdftoppm
    bin/pdftotext
    bin/pdftocairo  (if enabled)
    lib*/...
    share/...
```

`DESTDIR` stages the intended absolute prefix without modifying the production machine. **Staging does not automatically bundle system shared libraries.**

## 9. Runtime dependency audit and packaging

### 9.1 Inspect ELF dependencies

On the build machine:

```bash
ldd /tmp/concheron-poppler-stage/opt/concheron/poppler/bin/pdfinfo
ldd /tmp/concheron-poppler-stage/opt/concheron/poppler/bin/pdftoppm
ldd /tmp/concheron-poppler-stage/opt/concheron/poppler/bin/pdftotext
readelf -d /tmp/concheron-poppler-stage/opt/concheron/poppler/bin/pdfinfo | grep -E 'NEEDED|RPATH|RUNPATH' || true
```

Check **all** included Poppler shared libraries and utilities, not just `pdfinfo`. `ldd` is appropriate only for trusted binaries; it is not proof that runtime-loaded plugins, fonts, character maps, or data files are complete.

### 9.2 Test on a clean target-equivalent VM

Install only the intended runtime dependency set. Verify that binaries run without relying on development packages accidentally present on the build machine. Exercise real PDFs with fonts, transparencies, rotations, scans, and different encodings. Prefer distribution RPMs for system dependencies rather than copying arbitrary system libraries into `/opt`.

### 9.3 Package

```bash
cd /tmp/concheron-poppler-stage
tar -czf /tmp/concheron-poppler.tar.gz opt/
cd /tmp
sha256sum concheron-poppler.tar.gz > concheron-poppler.tar.gz.sha256
```

Keep a manifest listing: Poppler version, source digest, compiler and CMake versions, dependency RPM versions, compile flags, test results, installed file list, licensing notes, and expected SHA-256 digest. Include any required runtime RPMs as a **separate verified offline repository**.

## 10. Offline transfer and installation

Transfer only through the organization's authorized USB/dongle process. Validate the archive against a digest obtained from a separate trusted channel and inspect archive paths before privileged extraction.

```bash
sha256sum -c concheron-poppler.tar.gz.sha256
tar -tzf concheron-poppler.tar.gz | head -n 30
sudo tar -xzf concheron-poppler.tar.gz -C /
```

Install any required signed runtime RPMs through the approved local repository. If the loader cannot find Poppler's own libraries, configure a tested RPATH/RUNPATH or an appropriate `/etc/ld.so.conf.d/` entry and run `ldconfig`. Avoid broad global `LD_LIBRARY_PATH` changes for Apache/PHP. Do not overwrite system `/usr/bin` utilities with an untracked custom build.

Verify:

```bash
/opt/concheron/poppler/bin/pdfinfo -v
/opt/concheron/poppler/bin/pdftoppm -v
/opt/concheron/poppler/bin/pdftotext -v
```

Run tests under the **same restricted service account** used by the SEC rendering worker, not only as root.

## 11. Functional acceptance tests

Use a benign test PDF at `/tmp/test.pdf` (never a real SEC document in a public temp directory).

### 11.1 Metadata

```bash
/opt/concheron/poppler/bin/pdfinfo /tmp/test.pdf
```

Verify page count, encryption status, page dimensions, and return code.

### 11.2 Render one page at 144 DPI

```bash
/opt/concheron/poppler/bin/pdftoppm \
  -f 1 -l 1 -singlefile -r 144 -png \
  /tmp/test.pdf /tmp/page1
```

Expect `/tmp/page1.png`. At 144 DPI, a 595 × 842-point page is approximately 1190 × 1684 pixels, before accounting for crop/rotation. Test a variety of real document structures.

### 11.3 Extract word boxes

```bash
/opt/concheron/poppler/bin/pdftotext \
  -f 1 -l 1 -bbox-layout \
  /tmp/test.pdf /tmp/words.html
```

The XHTML contains page/flow/block/line/word elements with word bounding boxes (`xMin`, `yMin`, `xMax`, `yMax`). Treat its page dimensions and coordinate space as authoritative for that extraction output.

### 11.4 Optional alternate renderer

```bash
/opt/concheron/poppler/bin/pdftocairo \
  -f 1 -l 1 -singlefile -r 144 -png \
  /tmp/test.pdf /tmp/page1-cairo
```

Benchmark `pdftoppm` and `pdftocairo` on the actual SEC PDF corpus; neither is universally superior.

### 11.5 Failure-path tests

Test malformed PDF, zero-length input, invalid page number, password-protected PDF, missing fonts, oversized dimensions, scanned page with no text, rotated/cropped pages, worker timeout, insufficient disk space, and renderer crash. Ensure no unwatermarked image reaches the client in any failure mode.

## 12. Protected-text watermarking

### 12.1 Policy

Concheron selected a **server-composited low-opacity watermark** (illustrative defaults: light gray, opacity `0.12`, rotation `-30°`). Protected names/phrases are configured **only on the server**. The watermark layer is cleared over protected text rectangles before compositing; original document pixels are never erased. Keep an attribution watermark in other regions, including a non-secret footer where appropriate.

### 12.2 Extract, match, and union word rectangles

For example, extracted words:

```xml
<word xMin="100" yMin="200" xMax="140" yMax="215">Daniel</word>
<word xMin="145" yMin="200" xMax="210" yMax="215">Peterson</word>
```

For the phrase `Daniel Peterson`, the union rectangle is `(100, 200, 210, 215)`. Add policy-defined padding (e.g., 5 PDF-coordinate units) to protect the text from antialiasing and watermark stroke overlap. Search all occurrences and support case-insensitive matching, whitespace variation, and appropriate Unicode normalization. Word extraction order may differ from visual reading order, so validate multi-line phrases and unusual layouts.

### 12.3 Convert to rendered-image coordinates

For an extraction page size `(W_pdf, H_pdf)` and rendered image `(W_img, H_img)`:

```text
scaleX = W_img / W_pdf
scaleY = H_img / H_pdf
x_img = x_pdf * scaleX
y_img = y_pdf * scaleY
```

This simple formula works only when the extraction and rendering coordinate spaces refer to the same page bounds/orientation. For rotated pages, crop boxes, or different renderer options, implement and test the necessary transform. Never assume every page is A4 or unrotated.

### 12.4 Failure policy

A scanned PDF may yield no words. Text extraction may also fail on malformed fonts, encodings, or complex layouts. Define a **fail-safe policy**: e.g., watermark in a verified margin/footer, request manual review, or deny viewing where protected-text masking is mandatory. Do not silently assume no protected phrase exists. OCR is a separate optional capability; Poppler does not perform OCR.

### 12.5 Data isolation

Do not send protected phrase lists, word coordinates, or watermark-layer masks to JavaScript. The SEC client should receive only the final composited image.

## 13. PHP 7.4 integration

Poppler command-line tools do **not** require a PHP Poppler extension. PHP can invoke fixed executable paths. This simplified example demonstrates page rendering; it is **not** a complete hardened worker implementation.

```php
<?php

declare(strict_types=1);

function renderPdfPage(string $pdfPath, int $page, string $outputPrefix, int $dpi = 144): string
{
    if (!is_file($pdfPath) || !is_readable($pdfPath)) {
        throw new RuntimeException('PDF input unavailable');
    }
    if ($page < 1 || $dpi < 72 || $dpi > 200) {
        throw new InvalidArgumentException('Invalid rendering parameters');
    }

    $binary = '/opt/concheron/poppler/bin/pdftoppm';
    $outputFile = $outputPrefix . '.png';
    $command = escapeshellarg($binary)
        . ' -f ' . $page
        . ' -l ' . $page
        . ' -singlefile -r ' . $dpi . ' -png '
        . escapeshellarg($pdfPath) . ' '
        . escapeshellarg($outputPrefix);

    $lines = array();
    $exitCode = 0;
    exec($command . ' 2>&1', $lines, $exitCode);

    if ($exitCode !== 0 || !is_file($outputFile)) {
        throw new RuntimeException('PDF page rendering failed');
    }
    return $outputFile;
}
```

**Production requirements beyond this example:** invoke via a restricted worker with an enforceable timeout and memory/CPU/file-size limits; use per-request private temporary directories and unpredictable names; validate document/page access before rendering; ensure atomic publication of *only watermarked* outputs; remove decrypted PDF intermediates and temporary images; restrict log contents; and avoid shell error leakage. `exec()` alone does not provide a reliable timeout. Validate PHP `disable_functions`, SELinux, filesystem permissions, and Apache/service-account execution policies.

Suggested responsibilities:

| PHP service | Responsibility |
|---|---|
| `SecDocumentService` | Metadata, ownership, access policy |
| `SecCryptoService` | SEC package and storage decryption |
| `PdfInfoService` | Metadata/page count |
| `PdfRenderService` | Controlled Poppler worker invocation |
| `PdfTextService` | Text and word boxes |
| `ProtectedTextService` | Match names, compute rectangles |
| `WatermarkService` | Create/mask/composite watermark layer |
| `SecPageService` | Orchestrate authorized page delivery |
| `SecAuditService` | Audit viewing events |
| `SecPageEncryptionService` | Optional AES-GCM transport payload |

## 14. Interaction with SEC encryption and Firefox viewer

Poppler cannot directly interpret Concheron's **outer AES-256-GCM SEC package**. The authorized server decrypts it first, supplies PDF content to a restricted rendering worker, then returns only final page images. A PDF's own password encryption is separate from the SEC package encryption.

A managed Firefox ESR extension may display the server-watermarked page. If an additional AES-256-GCM page-response layer is used, the extension can decrypt using Web Crypto, including from ES5-style JavaScript with `Promise.then()` and jQuery AJAX. But an authorized client ultimately obtains plaintext pixels. Browser policy, obfuscation, and response encryption deter casual copying; they cannot guarantee protection from endpoint administrators, malware, screenshots, or external cameras. Never embed a permanent secret in JavaScript.

## 15. Performance and operations

- Render **on demand**, not all pages at login.
- Use a bounded worker pool; never spawn unlimited Poppler processes from concurrent HTTP requests.
- Set a maximum page count, DPI, pixel count, input size, and render time.
- A 1190 × 1684 RGBA image consumes approximately 7.64 MiB of decoded pixel storage, **before** renderer intermediates and additional copies.
- Personalize watermarks server-side. Shared unwatermarked caches are sensitive; avoid them unless explicitly approved and strongly protected.
- Measure p50/p95 rendering time, CPU, RSS, failed renders, queue depth, and disk usage.
- Track Poppler and dependency security updates. Schedule repeatable offline update bundles with signed artifacts and rollback plans.
- Run Poppler with least privilege, a restricted filesystem, resource limits, and preferably a sandboxed worker. PDF signatures/encryption do not imply parser safety.

## 16. Common build and runtime errors

| Symptom | Likely cause | Action |
|---|---|---|
| `CMake ... too old` | Poppler release needs newer CMake | Pin compatible release or install supported newer toolchain |
| `C++ compiler ... too old` | Insufficient C++ standard/toolchain | Upgrade build toolchain; verify target ABI |
| Missing `freetype`, `fontconfig`, `openjpeg`, etc. | Missing development package or incompatible version | Install matching `-devel` RPMs and reconfigure |
| PNG renderer missing | Image codec support not built | Inspect configure summary and install PNG dependency |
| `libpoppler.so: cannot open` | Runtime library search path not configured | Validate RUNPATH/ldconfig and installed libraries |
| `GLIBC_x.y not found` | Built against newer runtime than target | Rebuild on target-equivalent OS; do not replace glibc casually |
| `Permission denied` from PHP | Worker user, SELinux, directory permissions | Inspect service context and allow only necessary access |
| `pdftotext` returns empty | Scanned PDF/no text layer or extraction issue | Use approved fallback/OCR/manual review |
| Watermark covers protected name | Coordinate mismatch or matching failure | Test page dimensions, crop/rotation, union/padding |
| Renderer hangs or uses excessive RAM | Malformed/complex PDF or unbounded concurrency | Enforce sandboxed timeouts, memory limits, queues |

## 17. Deployment acceptance checklist

- [ ] Target OS/version, architecture, glibc, and supported lifecycle recorded.
- [ ] Pinned Poppler release and verified source/signature recorded.
- [ ] Exact compiler/CMake/dependency versions documented.
- [ ] Optional components and required image codecs checked.
- [ ] Build logs, file manifest, and SHA-256 hashes retained.
- [ ] Runtime dependency closure tested on clean target-equivalent VM.
- [ ] Offline transfer and signature verification approved.
- [ ] No compilers or unnecessary development packages installed on production.
- [ ] `pdfinfo`, `pdftoppm`, `pdftotext -bbox-layout` tested as service user.
- [ ] Rotated, cropped, scanned, malformed, and font-heavy PDFs tested.
- [ ] No original PDF or unwatermarked page delivered to browser.
- [ ] Protected-name masking verified with all occurrences and failure cases.
- [ ] Rendering timeout, memory, CPU, page, and concurrency limits enforced.
- [ ] Audit, cleanup, patching, and rollback procedures approved.

## 18. Next information needed for exact commands

Run these on the offline CentOS server and supply the output:

```bash
cat /etc/os-release
uname -m
ldd --version | head -n 1
gcc --version | head -n 1
cmake --version | head -n 1
```

Missing GCC or CMake is acceptable: the recommended production architecture does not require either. With the OS information, a **release-pinned build recipe, dependency manifest, offline RPM bundle, installation script, and acceptance-test script** can be prepared and validated.

---

## 19. Expanded command-line cookbook

The following examples use `/opt/concheron/poppler/bin` and a nonconfidential sample file `/srv/sec-test/sample.pdf`. Run acceptance tests on non-sensitive data. Poppler option availability varies by version: check `pdfinfo -h`, `pdftoppm -h`, `pdftotext -h`, and `pdftocairo -h` on your actual build.

### 19.1 Set paths and inspect executable dependencies

```bash
POPPLER_BIN=/opt/concheron/poppler/bin
PDF=/srv/sec-test/sample.pdf
OUT=/srv/sec-test/output
mkdir -p "$OUT"
"$POPPLER_BIN/pdfinfo" -v
"$POPPLER_BIN/pdfinfo" "$PDF"
file "$POPPLER_BIN/pdftoppm"
readelf -d "$POPPLER_BIN/pdftoppm" | grep -E 'NEEDED|RPATH|RUNPATH' || true
ldd "$POPPLER_BIN/pdftoppm"
```

Never run untrusted executables with `ldd`. Use a clean target-equivalent VM to test runtime dependencies, including plugins and fonts that might be loaded later.

### 19.2 Metadata and individual page information

```bash
"$POPPLER_BIN/pdfinfo" -f 2 -l 2 "$PDF"
"$POPPLER_BIN/pdfinfo" -box "$PDF"
"$POPPLER_BIN/pdfinfo" -meta "$PDF"
"$POPPLER_BIN/pdfinfo" -rawdates "$PDF"
```

`-box` reports page boxes. The `-meta` output may be XML metadata and must be treated as untrusted input; do not display it unescaped in HTML.

### 19.3 Render page images

```bash
# Page 3 at 144 DPI, PNG.
"$POPPLER_BIN/pdftoppm" -f 3 -l 3 -singlefile -r 144 -png \
  "$PDF" "$OUT/page-003"

# Page 1 at 96 DPI, JPEG, quality 85.
"$POPPLER_BIN/pdftoppm" -f 1 -l 1 -singlefile -r 96 \
  -jpeg -jpegopt quality=85 "$PDF" "$OUT/page-001"

# Render a range; files are numbered by Poppler.
"$POPPLER_BIN/pdftoppm" -f 1 -l 4 -r 120 -png \
  "$PDF" "$OUT/range"

# Crop in rendered pixel coordinates (test on your PDF rotation/crop boxes).
"$POPPLER_BIN/pdftoppm" -f 1 -l 1 -singlefile -r 144 \
  -x 100 -y 100 -W 400 -H 300 -png "$PDF" "$OUT/crop"

# Alternative Cairo renderer, if built with PNG/Cairo support.
"$POPPLER_BIN/pdftocairo" -f 1 -l 1 -singlefile -r 144 -png \
  "$PDF" "$OUT/cairo-001"
```

**SEC warning:** These examples create **unwatermarked** output and are for isolated testing only. Never serve these files to users; production must composite the personalized watermark first.

### 19.4 Extract text and positions

```bash
# Text for all pages; -layout tries to preserve visual layout.
"$POPPLER_BIN/pdftotext" -layout "$PDF" "$OUT/all.txt"

# Only page 2; UTF-8 text output.
"$POPPLER_BIN/pdftotext" -f 2 -l 2 -enc UTF-8 \
  "$PDF" "$OUT/page-002.txt"

# Word-level XHTML boxes for protected-text detection.
"$POPPLER_BIN/pdftotext" -f 2 -l 2 -bbox-layout \
  "$PDF" "$OUT/page-002-boxes.html"

# Standard bounding-box format (without layout hierarchy).
"$POPPLER_BIN/pdftotext" -f 2 -l 2 -bbox \
  "$PDF" "$OUT/page-002-words.html"
```

A scanned image-only PDF can produce little or no extracted text. Poppler is not an OCR engine. Do not assume missing text means the document contains no protected names.

### 19.5 Fonts, images, attachments, and page extraction

```bash
"$POPPLER_BIN/pdffonts" "$PDF"
"$POPPLER_BIN/pdfimages" -list "$PDF"
"$POPPLER_BIN/pdfdetach" -list "$PDF"
"$POPPLER_BIN/pdfseparate" -f 1 -l 2 "$PDF" "$OUT/split-%d.pdf"
"$POPPLER_BIN/pdfunite" "$OUT/split-1.pdf" "$OUT/split-2.pdf" "$OUT/combined.pdf"
```

Some commands above require their corresponding utilities to be built and installed. Avoid extracting embedded attachments on the SEC server unless the business process explicitly requires it; treat them as potentially hostile files.

### 19.6 Offline build, staged install, and checksums

```bash
# Run on a matching, Internet-connected build VM with all prerequisites installed.
tar -xf poppler-VERSION.tar.xz
cd poppler-VERSION
cmake -S . -B build -G Ninja \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_INSTALL_PREFIX=/opt/concheron/poppler \
  -DENABLE_UTILS=ON -DENABLE_QT5=OFF -DENABLE_QT6=OFF \
  -DENABLE_GLIB=OFF -DENABLE_CPP=OFF
cmake --build build --parallel 4
ctest --test-dir build --output-on-failure
mkdir -p /tmp/poppler-stage
DESTDIR=/tmp/poppler-stage cmake --install build
cd /tmp/poppler-stage
tar -czf /tmp/poppler-offline.tar.gz opt/
sha256sum /tmp/poppler-offline.tar.gz > /tmp/poppler-offline.tar.gz.sha256
```

Not every Poppler release includes runnable tests in a basic source checkout; inspect `ctest -N` first. This archive may still require compatible runtime RPMs. It is **not** a self-contained deployment by default.

### 19.7 Offline server verification

```bash
# On offline server, with approved transferred files in the current directory:
sha256sum -c poppler-offline.tar.gz.sha256
sudo tar -xzf poppler-offline.tar.gz -C /
/opt/concheron/poppler/bin/pdfinfo -v
/opt/concheron/poppler/bin/pdftoppm -v
/opt/concheron/poppler/bin/pdftotext -v

# Confirm access as the service account; replace 'apache' as appropriate.
sudo -u apache /opt/concheron/poppler/bin/pdfinfo /srv/sec-test/sample.pdf
```

Checksum verification detects transfer corruption **only if** the expected digest was authenticated independently. For production, verify upstream source signatures and package provenance.

## 20. Expanded PHP 7.4 examples

These examples are compatible with PHP 7.4 syntax. They demonstrate command invocation and data processing; they are **not a complete production sandbox**. All input PDF paths must be server-resolved from authorized document IDs, never accepted directly from client requests.

### 20.1 Reusable process runner with an enforceable timeout

The following uses `proc_open()` with an **array command** (supported in PHP 7.4), avoiding shell interpolation. It writes stdout/stderr to private temporary files rather than risking pipe deadlocks. It terminates the direct process on timeout. For production, also use an OS-level service/cgroup sandbox to terminate descendants and enforce CPU/memory limits.

```php
<?php
// PopplerProcess.php — PHP 7.4

declare(strict_types=1);

final class PopplerProcess
{
    public static function run(array $argv, int $timeoutSeconds = 30): string
    {
        if (!$argv || $timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Invalid command/timeout');
        }
        $stdout = tempnam(sys_get_temp_dir(), 'popout_');
        $stderr = tempnam(sys_get_temp_dir(), 'poperr_');
        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Unable to allocate process logs');
        }
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdout, 'w'],
            2 => ['file', $stderr, 'w'],
        ];
        $proc = null;
        try {
            $proc = proc_open($argv, $spec, $pipes, null, null, [
                'bypass_shell' => true,
            ]);
            if (!is_resource($proc)) {
                throw new RuntimeException('Could not start Poppler');
            }
            fclose($pipes[0]);
            $start = microtime(true);
            $timedOut = false;
            do {
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) - $start > $timeoutSeconds) {
                    $timedOut = true;
                    proc_terminate($proc, 9);
                    break;
                }
                usleep(100000);
            } while (true);
            $exit = $status['exitcode'];
            $closeExit = proc_close($proc);
            $proc = null;
            if ($exit === -1) {
                $exit = $closeExit;
            }
            if ($timedOut || $exit !== 0) {
                // Never return stderr containing confidential paths to the browser.
                throw new RuntimeException($timedOut ? 'Poppler timeout' : 'Poppler failed');
            }
            $data = file_get_contents($stdout);
            if ($data === false) {
                throw new RuntimeException('Cannot read process output');
            }
            return $data;
        } finally {
            if (is_resource($proc)) {
                proc_terminate($proc, 9);
                proc_close($proc);
            }
            @unlink($stdout);
            @unlink($stderr);
        }
    }
}
```

**Security caveats:** Temporary files created by `tempnam()` should be placed in a dedicated `0700` worker directory with restricted access, not a shared world-accessible location. A bounded output size must be enforced externally; large process logs can fill disks. Ensure the worker cannot access the network and can read only its authorized input. The sample's exit-status handling must be validated on your PHP/OS combination.

### 20.2 Read metadata and parse the page count

```php
<?php
require_once __DIR__ . '/PopplerProcess.php';

function getPdfPageCount(string $pdfPath): int
{
    if (!is_file($pdfPath) || !is_readable($pdfPath)) {
        throw new RuntimeException('PDF not readable');
    }
    $output = PopplerProcess::run([
        '/opt/concheron/poppler/bin/pdfinfo',
        $pdfPath,
    ], 10);
    if (!preg_match('/^Pages:\s*(\d+)\s*$/mi', $output, $matches)) {
        throw new RuntimeException('Unable to parse page count');
    }
    $pages = (int)$matches[1];
    if ($pages < 1 || $pages > 10000) {
        throw new RuntimeException('Unsupported page count');
    }
    return $pages;
}

// Example: echo getPdfPageCount('/srv/sec-test/sample.pdf');
```

### 20.3 Render a single PDF page to PNG

```php
<?php
require_once __DIR__ . '/PopplerProcess.php';

function renderPagePng(string $pdf, int $page, string $outputPrefix, int $dpi = 144): string
{
    if ($page < 1 || $page > 10000 || $dpi < 72 || $dpi > 200) {
        throw new InvalidArgumentException('Page or DPI out of range');
    }
    if (!is_file($pdf) || !is_readable($pdf)) {
        throw new RuntimeException('Input PDF missing');
    }
    $outputFile = $outputPrefix . '.png';
    PopplerProcess::run([
        '/opt/concheron/poppler/bin/pdftoppm',
        '-f', (string)$page,
        '-l', (string)$page,
        '-singlefile', '-r', (string)$dpi,
        '-png', $pdf, $outputPrefix,
    ], 30);
    if (!is_file($outputFile) || filesize($outputFile) === 0) {
        throw new RuntimeException('PNG rendering failed');
    }
    return $outputFile;
}
```

Only call this function with an output prefix inside a unique private directory. Rendering at 200 DPI can use substantial memory; impose page dimension and file-size limits before and after rendering.

### 20.4 Extract text and bounding boxes as XHTML

```php
<?php
require_once __DIR__ . '/PopplerProcess.php';

function extractPageBoxes(string $pdf, int $page, string $htmlOutput): void
{
    if ($page < 1 || $page > 10000) {
        throw new InvalidArgumentException('Invalid page');
    }
    PopplerProcess::run([
        '/opt/concheron/poppler/bin/pdftotext',
        '-f', (string)$page, '-l', (string)$page,
        '-bbox-layout', $pdf, $htmlOutput,
    ], 20);
    if (!is_file($htmlOutput)) {
        throw new RuntimeException('Text extraction failed');
    }
}
```

### 20.5 Parse Poppler word coordinates safely

```php
<?php
function readWordBoxes(string $xhtmlFile): array
{
    $previous = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument();
        // NONET prevents network access; do not enable entity substitution.
        if (!$doc->load($xhtmlFile, LIBXML_NONET)) {
            throw new RuntimeException('Invalid Poppler XHTML');
        }
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*[local-name()="word"]');
        $words = [];
        foreach ($nodes as $node) {
            $xMin = $node->getAttribute('xMin');
            $yMin = $node->getAttribute('yMin');
            $xMax = $node->getAttribute('xMax');
            $yMax = $node->getAttribute('yMax');
            if (!is_numeric($xMin) || !is_numeric($yMin) ||
                !is_numeric($xMax) || !is_numeric($yMax)) {
                continue;
            }
            $words[] = [
                'text' => trim($node->textContent),
                'xMin' => (float)$xMin, 'yMin' => (float)$yMin,
                'xMax' => (float)$xMax, 'yMax' => (float)$yMax,
            ];
        }
        return $words;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
```

Poppler output can vary by PDF, version, and layout. The `word` elements use a namespace in some releases; the XPath expression intentionally ignores the namespace. For a production parser, validate page dimensions, bounding-box ordering, finite numeric values, and XML size before processing.

### 20.6 Locate a protected phrase across adjacent words

This is a basic case-insensitive exact-word matcher. It does not handle every Unicode normalization, punctuation, hyphenation, or text-flow issue. Validate matches against the rendered image.

```php
<?php
function findProtectedPhraseBoxes(array $words, string $phrase): array
{
    $parts = preg_split('/\s+/u', trim($phrase));
    if (!$parts || !$parts[0]) {
        return [];
    }
    $count = count($parts);
    $results = [];
    for ($i = 0; $i + $count <= count($words); $i++) {
        $match = true;
        for ($j = 0; $j < $count; $j++) {
            if (strcasecmp($words[$i + $j]['text'], $parts[$j]) !== 0) {
                $match = false;
                break;
            }
        }
        if (!$match) {
            continue;
        }
        $group = array_slice($words, $i, $count);
        $results[] = [
            'xMin' => min(array_column($group, 'xMin')),
            'yMin' => min(array_column($group, 'yMin')),
            'xMax' => max(array_column($group, 'xMax')),
            'yMax' => max(array_column($group, 'yMax')),
        ];
    }
    return $results;
}
```

**Fail-safe rule:** If the document's protected-name detection is unreliable (e.g., scanned pages, malformed text layer, unexpected rotation), do not claim the watermark will avoid protected text. Route the page to a validated alternative policy, such as review or a different watermark placement method.

### 20.7 Convert word boxes into image pixel coordinates

This simple transform applies **only when the extracted page coordinates and rendered image refer to the same unrotated, uncropped page rectangle**. Cropped or rotated PDFs require an affine transform based on Poppler page boxes and rotation; do not deploy this shortcut without validating the document.

```php
<?php
function boxToPixels(array $box, float $pageWidthPt, float $pageHeightPt,
                     int $imageWidthPx, int $imageHeightPx, int $padding = 8): array
{
    if ($pageWidthPt <= 0 || $pageHeightPt <= 0 ||
        $imageWidthPx <= 0 || $imageHeightPx <= 0) {
        throw new InvalidArgumentException('Invalid page geometry');
    }
    $sx = $imageWidthPx / $pageWidthPt;
    $sy = $imageHeightPx / $pageHeightPt;
    $x1 = max(0, (int)floor($box['xMin'] * $sx) - $padding);
    $y1 = max(0, (int)floor($box['yMin'] * $sy) - $padding);
    $x2 = min($imageWidthPx, (int)ceil($box['xMax'] * $sx) + $padding);
    $y2 = min($imageHeightPx, (int)ceil($box['yMax'] * $sy) + $padding);
    return ['x' => $x1, 'y' => $y1,
            'width' => max(0, $x2 - $x1), 'height' => max(0, $y2 - $y1)];
}
```

### 20.8 Composite a watermark with protected rectangles using Imagick

The algorithm draws the watermark onto a transparent overlay, then clears the overlay in protected rectangles before compositing it over the original image. This avoids painting the watermark over protected text without erasing the original PDF content.

```php
<?php
function addMaskedWatermark(string $inputPng, string $outputPng,
                            string $label, array $protectedRects): void
{
    $base = new Imagick($inputPng);
    $base->setImageFormat('png');
    $width = $base->getImageWidth();
    $height = $base->getImageHeight();

    $overlay = new Imagick();
    $overlay->newImage($width, $height, new ImagickPixel('transparent'), 'png');
    $draw = new ImagickDraw();
    $draw->setFillColor(new ImagickPixel('rgba(80,80,80,0.12)'));
    $draw->setFontSize(28);
    // Use a vetted font available in the server's offline font set.
    for ($y = 100; $y < $height + 200; $y += 260) {
        for ($x = -100; $x < $width + 200; $x += 430) {
            $overlay->annotateImage($draw, $x, $y, -30, $label);
        }
    }
    // Clear only the watermark overlay where protected names appear.
    $overlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
    foreach ($protectedRects as $r) {
        $x = max(0, (int)$r['x']);
        $y = max(0, (int)$r['y']);
        $w = max(0, min((int)$r['width'], $width - $x));
        $h = max(0, min((int)$r['height'], $height - $y));
        if ($w === 0 || $h === 0) {
            continue;
        }
        $eraser = new Imagick();
        $eraser->newImage($w, $h, new ImagickPixel('white'), 'png');
        // Clear watermark pixels beneath the protected rectangle.
        $overlay->compositeImage($eraser, Imagick::COMPOSITE_CLEAR, $x, $y);
        $eraser->clear();
    }
    $base->compositeImage($overlay, Imagick::COMPOSITE_OVER, 0, 0);
    if (!$base->writeImage($outputPng)) {
        throw new RuntimeException('Watermark output failed');
    }
    $overlay->clear();
    $base->clear();
}
```

**Implementation note:** `COMPOSITE_CLEAR` behavior can vary with ImageMagick/Imagick versions and image alpha settings. Treat this as a starting example: test the resulting PNG pixel-by-pixel and visually on your exact installed version, and use a separately verified alpha-mask implementation if necessary. Also validate and clip all rectangles and choose a real font before deployment.

### 20.9 Send an already-watermarked PNG from an authorized PHP endpoint

```php
<?php
// The caller must have already authenticated, authorized the document/page,
// produced a personalized WATERMARKED file, and recorded the audit event.
function sendWatermarkedPng(string $finalPng): void
{
    if (!is_file($finalPng) || !is_readable($finalPng)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($finalPng));
    readfile($finalPng);
}
```

Do not use this function with unwatermarked intermediate images. Do not expose paths to the client. HTTPS, authorization, short-lived access, endpoint hardening, and audit controls remain necessary; `Cache-Control` does not prevent screenshots or local capture.

## 21. Example offline deployment directory layout

```text
/opt/concheron/poppler/           # custom Poppler binaries and libraries
/opt/concheron/sec-scripts/       # root-owned deployment/verification scripts
/var/lib/concheron/sec-private/   # encrypted source packages (not web-accessible)
/var/lib/concheron/sec-work/      # private per-job decrypted PDF + intermediates
/var/log/concheron/               # access/audit logs with restricted permissions
/srv/sec-test/                    # synthetic acceptance-test PDFs only
```

Keep the private work directory outside Apache's document root. Use distinct service identities, SELinux confinement, no network access for the PDF worker, restricted file permissions, process and disk quotas, and deletion of intermediates after the authorized job completes. Avoid storing sensitive filenames or extracted text in ordinary error logs.

## 22. Suggested end-to-end acceptance tests

1. Render a one-page PDF, a multi-page PDF, and a PDF containing rotated pages.
2. Verify page counts from `pdfinfo` and inspect actual output dimensions.
3. Extract word boxes from a PDF with two occurrences of a protected full name.
4. Verify the name rectangles are correct on the **actual rendered PNG**.
5. Confirm the watermark appears on the image but not over protected text.
6. Confirm that scanned PDFs and extraction failures follow a defined fail-safe policy.
7. Run a malformed PDF through a sandboxed worker and confirm timeout and resource limits.
8. Confirm the PHP endpoint never serves the source PDF, raw extracted text, or unwatermarked PNG.
9. Confirm logs and temporary directories are inaccessible to ordinary browser users.
10. Repeat acceptance tests on the **offline target-equivalent machine**, not only on the build VM.

---

## References

1. Poppler official project and releases: https://poppler.freedesktop.org/
2. Poppler source code, build configuration, and release-specific instructions: https://gitlab.freedesktop.org/poppler/poppler
3. Poppler utility manual pages (`pdfinfo`, `pdftoppm`, `pdftotext`, `pdftocairo`) included with the selected package/release.
4. CMake documentation (`cmake --build`, `cmake --install`, `DESTDIR`): https://cmake.org/cmake/help/latest/
5. Red Hat package management documentation for RPM/DNF and local repositories: https://docs.redhat.com/

**Note:** These references are navigation aids. Actual build flags, dependencies, and version requirements must be checked against the specific source release and target operating system before production deployment.
