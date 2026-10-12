# qpdf Guide: Downloads, Installation, and PHP PDF Encryption

## 1. What is qpdf?

**qpdf** is a free, open-source command-line utility for working with
PDF files. It runs on Windows and Linux, including compatible CentOS
systems.

Common uses include:

-   Encrypting and decrypting PDFs with passwords
-   Splitting PDFs and extracting selected pages
-   Combining PDFs
-   Inspecting PDF structure and encryption settings
-   Linearizing PDFs for faster web viewing
-   Optimizing PDF object streams

Official project: <https://github.com/qpdf/qpdf>\
Official documentation: <https://qpdf.readthedocs.io/en/latest/>

> **Important distinction:** qpdf's standard `--encrypt` command
> provides password-based PDF encryption. It is not, by itself, a
> general-purpose certificate/public-key PDF encryption system or a
> USB-token authentication system.

## 2. Official qpdf 12.4.2 release page

Release page:

<https://github.com/qpdf/qpdf/releases/tag/v12.4.2>

Always check the **Assets** section on the official release page for the
exact filenames available for that release. Do not assume a filename
exists unless it appears in the release assets.

## 3. Download choices for Windows and CentOS/Linux

The correct package depends on the operating system version, CPU
architecture, and required runtime libraries.

  ------------------------------------------------------------------------------------
  Platform                Filename or package type discussed   Notes
  ----------------------- ------------------------------------ -----------------------
  Linux x86-64            `qpdf-12.4.2-bin-linux-x86_64.zip`   Portable binary ZIP;
                                                               **not** an RPM. May
                                                               work on CentOS if its
                                                               runtime requirements
                                                               are compatible.

  Windows 64-bit          `qpdf-12.4.2-msvc64.exe`             Expected Windows
                                                               installer naming
                                                               pattern; verify the
                                                               exact asset on the
                                                               release page.

  Windows 64-bit portable `qpdf-12.4.2-msvc64.zip`             Expected ZIP naming
                                                               pattern; verify the
                                                               exact asset on the
                                                               release page.

  CentOS                  RPM built for the matching CentOS    Prefer this for a
                          release and architecture             conventional
                                                               package-managed
                                                               installation, when
                                                               available.
  ------------------------------------------------------------------------------------

Official release assets:\
<https://github.com/qpdf/qpdf/releases/tag/v12.4.2>

Package search (check compatibility and dependencies carefully):\
<https://www.rpmfind.net/linux/rpm2html/search.php?query=qpdf>

### About the Linux ZIP

The file `qpdf-12.4.2-bin-linux-x86_64.zip` is a Linux 64-bit binary
distribution. It is not a CentOS-specific RPM. A binary built for one
Linux environment can fail on another if required libraries or GLIBC
versions are missing or incompatible.

The ZIP is useful if you want to test a binary without installing an
RPM, but it is not automatically guaranteed to work on every CentOS
version.

## 4. Identify the CentOS version and architecture

Run these commands on the target server:

``` bash
cat /etc/centos-release
uname -m
```

Typical architecture output:

-   `x86_64`: 64-bit Intel/AMD
-   `aarch64`: 64-bit ARM

Choose a package intended for the correct OS generation and
architecture. CentOS 7, CentOS 8, CentOS Stream 9, and CentOS Stream 10
do not necessarily use the same package or dependency versions. Older
CentOS releases may also be end-of-life.

## 5. Install qpdf on Windows

### Option A: Installer

1.  Open the official release page:
    <https://github.com/qpdf/qpdf/releases/tag/v12.4.2>
2.  In **Assets**, find the Windows 64-bit installer for the release, if
    available. The expected filename pattern is
    `qpdf-12.4.2-msvc64.exe`; confirm the actual asset before
    downloading.
3.  Run the installer and follow its instructions.
4.  Open a **new** Command Prompt window.
5.  Verify:

``` bat
qpdf --version
```

If Windows says that `qpdf` is not recognized, run `qpdf.exe` by its
full path or add the directory containing it to the Windows `PATH`, then
open a new terminal.

### Option B: ZIP package

1.  Download the Windows ZIP asset if it is provided in the release.
2.  Extract it to a directory such as `C:\\Tools\\qpdf`.
3.  Find `qpdf.exe` in the extracted folders.
4.  Test it with its full path, for example:

``` bat
"C:\\Tools\\qpdf\\bin\\qpdf.exe" --version
```

The actual folder layout can differ, so adjust the path to match the
extracted files.

## 6. Install qpdf on CentOS

### Option A: Install from configured repositories

First check whether qpdf is available in your enabled repositories.

For systems using DNF:

``` bash
sudo dnf info qpdf
sudo dnf install qpdf
```

For older systems using YUM:

``` bash
sudo yum info qpdf
sudo yum install qpdf
```

If the package is unavailable, check which repositories are enabled and
consult the official qpdf installation documentation:

<https://qpdf.readthedocs.io/en/latest/installation.html>

Repository availability depends on the CentOS version and configuration.

### Option B: Install a local RPM

If you downloaded an RPM built for your exact CentOS release and
architecture, install it with the package manager so it can resolve
dependencies:

``` bash
sudo dnf install ./qpdf-package-name.rpm
```

On an older YUM-based system, use the corresponding `yum localinstall`
workflow if supported:

``` bash
sudo yum localinstall ./qpdf-package-name.rpm
```

Replace the example filename with the real RPM filename. **Do not
install an RPM built for a different CentOS generation without checking
compatibility.**

### Option C: Test the Linux ZIP

If you already downloaded `qpdf-12.4.2-bin-linux-x86_64.zip`:

1.  Ensure `unzip` is available.
2.  Extract it into a dedicated directory.
3.  Locate the `qpdf` executable.
4.  Run the version check.

Example:

``` bash
unzip qpdf-12.4.2-bin-linux-x86_64.zip -d qpdf-12.4.2
find qpdf-12.4.2 -type f -name qpdf
```

Use the path returned by `find` in the next command, for example:

``` bash
./path/to/qpdf --version
```

The path above is a placeholder---replace it with the actual path
returned by `find`. If needed, make the executable runnable:

``` bash
chmod +x ./path/to/qpdf
```

If execution fails, inspect missing shared-library requirements:

``` bash
ldd ./path/to/qpdf
```

If `ldd` reports missing libraries or the executable reports a GLIBC
version error, use a compatible build or an RPM for the server's OS
version. Do not assume that every Linux ZIP will run on every CentOS
release.

### Verify qpdf after installation

``` bash
qpdf --version
which qpdf
```

`which qpdf` is typically available on Linux and shows the executable
resolved through `PATH`.

## 7. Basic qpdf commands

### Inspect PDF encryption

``` bash
qpdf --show-encryption encrypted.pdf
```

### Encrypt a PDF with a user and owner password

``` bash
qpdf --encrypt userpassword ownerpassword 256 -- input.pdf encrypted.pdf
```

-   `userpassword`: password required to open the PDF.
-   `ownerpassword`: password used for owner-level PDF operations and
    permission settings.
-   `256`: requests 256-bit PDF encryption.
-   `--`: separates encryption options from the input and output
    filenames.

Use strong, unique passwords. Do not copy the example passwords into
production.

### Decrypt a PDF when you have the required password

``` bash
qpdf --password=userpassword --decrypt encrypted.pdf decrypted.pdf
```

You must have the credentials required to open the encrypted PDF.

### Extract pages 1 through 5

``` bash
qpdf input.pdf --pages . 1-5 -- output.pdf
```

### Check the installed version

``` bash
qpdf --version
```

## 8. Use qpdf from PHP

qpdf is an external command-line program. PHP can invoke it from the
server, so a separate PHP wrapper library is not required for basic
tasks. The PHP process must have permission to run qpdf and access the
relevant files.

### Example: Encrypt a PDF

Save as `encrypt.php`:

``` php
<?php

$input = __DIR__ . '/input.pdf';
$output = __DIR__ . '/encrypted.pdf';

// Use 'qpdf' if it is available in PATH.
// Otherwise, set the full path to qpdf.exe or the Linux qpdf executable.
$qpdf = 'qpdf';

if (!is_file($input)) {
    exit("Input PDF not found.\\n");
}

// Demo values only. In production, load secrets from a secure secret store.
$userPassword = 'ReplaceWithAStrongUserPassword';
$ownerPassword = 'ReplaceWithAStrongOwnerPassword';

$command = escapeshellarg($qpdf)
    . ' --encrypt '
    . escapeshellarg($userPassword) . ' '
    . escapeshellarg($ownerPassword)
    . ' 256 -- '
    . escapeshellarg($input) . ' '
    . escapeshellarg($output)
    . ' 2>&1';

$outputLines = [];
$exitCode = 1;
exec($command, $outputLines, $exitCode);

if ($exitCode === 0 && is_file($output)) {
    echo "PDF encrypted successfully.\\n";
} else {
    echo "PDF encryption failed.\\n";
    echo implode("\\n", $outputLines);
}
```

Run from a terminal:

``` bash
php encrypt.php
```

On Windows, use a PHP installation that is available in Command Prompt,
or invoke `php.exe` by its full path. On CentOS, make sure the PHP
runtime and qpdf are installed and accessible to the user running the
script.

**Production security note:** `escapeshellarg()` helps quote individual
shell arguments, but it does not prevent secrets from appearing in
process listings, and command output may contain sensitive information.
For a production system, prefer a controlled process API such as Symfony
Process, limit file paths and permissions, avoid logging passwords, and
use a secure method for handling secrets. Symfony Process does not by
itself eliminate every risk associated with passing secrets as
arguments.

### Example: Decrypt a PDF

``` php
<?php

$qpdf = 'qpdf';
$input = __DIR__ . '/encrypted.pdf';
$output = __DIR__ . '/decrypted.pdf';
$password = 'ReplaceWithTheCorrectPassword';

if (!is_file($input)) {
    exit("Encrypted PDF not found.\\n");
}

$command = escapeshellarg($qpdf)
    . ' --password=' . escapeshellarg($password)
    . ' --decrypt '
    . escapeshellarg($input) . ' '
    . escapeshellarg($output)
    . ' 2>&1';

$outputLines = [];
$exitCode = 1;
exec($command, $outputLines, $exitCode);

if ($exitCode === 0 && is_file($output)) {
    echo "PDF decrypted successfully.\\n";
} else {
    echo "PDF decryption failed.\\n";
    echo implode("\\n", $outputLines);
}
```

Avoid using this simplified `exec()` pattern for untrusted input or a
multi-user production system without additional controls.

## 9. Security considerations for an SEC document platform

If different subsidiaries must not be able to decrypt one another's
documents, distinguish these functions:

1.  **Website authentication:** Determines which staff member can sign
    in. A USB token or certificate can be part of this process.
2.  **Authorization:** Determines which documents that staff member is
    allowed to access.
3.  **File encryption:** Protects document contents when stored or
    transferred.
4.  **Key management:** Controls who can use the keys that decrypt
    documents.

A PDF user password is not the same as a staff certificate, and qpdf's
ordinary password-encryption commands do not implement a
subsidiary-specific public-key encryption scheme.

For strong separation between subsidiaries, consider envelope
encryption: generate a random symmetric key for each document, encrypt
the document data with that key using an authenticated encryption
scheme, and protect the key separately for the intended recipient using
an appropriate public-key or key-management system. The exact design
should be reviewed for the required threat model and operational needs.
If the final deliverable must remain a standard PDF, ensure the chosen
PDF encryption design and reader support meet the requirements.

Do not encrypt a PDF using a USB device's **private signing key** as if
that were a normal file-encryption key. Signing and encryption are
different operations. A common public-key pattern encrypts data for a
recipient using the recipient's public key and permits decryption with
the corresponding private key, often through a hybrid encryption scheme.
Keep private keys protected and use established cryptographic libraries
and formats rather than inventing a scheme.

Also consider:

-   Restricting access to uploaded files and temporary decrypted files
-   Removing temporary files securely when appropriate
-   Protecting backups and server storage
-   Keeping audit logs without recording passwords or private keys
-   Ensuring that users cannot choose arbitrary server-side file paths
-   Preventing unauthorized users from invoking arbitrary command-line
    options
-   Testing recovery and key rotation procedures

## 10. Troubleshooting

### `qpdf: command not found` or Windows says it is not recognized

-   Confirm qpdf is installed or extracted.
-   Run it using the full executable path.
-   Add its directory to `PATH` if appropriate, then open a new
    terminal.

### `Permission denied`

-   Check file and directory permissions.
-   On Linux, verify the executable bit and the PHP service user's
    permissions.
-   Avoid solving permission issues by granting broad write access to
    the application directories.

### Missing shared library or GLIBC error on CentOS

-   The binary may have been built for a different Linux environment.
-   Check the OS release, architecture, and required libraries.
-   Prefer a compatible repository package or a build intended for your
    CentOS version.

### RPM dependency errors

-   Check the RPM's target distribution and architecture.
-   Use `dnf install ./package.rpm` or the appropriate YUM command to
    resolve dependencies.
-   Do not force installation of incompatible system packages merely to
    make qpdf run.

### PHP works in the terminal but not through the web server

The web server may use a different `PATH`, run as a different user, or
have different permissions and execution restrictions. Configure the
full qpdf path and verify access under the PHP service account. Avoid
exposing raw command output to end users.

## 11. Official references

-   qpdf project: <https://github.com/qpdf/qpdf>
-   qpdf 12.4.2 release:
    <https://github.com/qpdf/qpdf/releases/tag/v12.4.2>
-   qpdf documentation: <https://qpdf.readthedocs.io/en/latest/>
-   qpdf installation documentation:
    <https://qpdf.readthedocs.io/en/latest/installation.html>
-   qpdf command-line documentation:
    <https://qpdf.readthedocs.io/en/latest/cli.html>
-   RPMfind qpdf search:
    <https://www.rpmfind.net/linux/rpm2html/search.php?query=qpdf>

## 12. Recommended next steps

1.  Run `cat /etc/centos-release` and `uname -m` on the target server.
2.  Check the release page's actual assets and select a Windows or Linux
    package.
3.  If using CentOS, prefer a compatible RPM where available; otherwise
    test the ZIP and its runtime dependencies.
4.  Verify with `qpdf --version`.
5.  Test PDF encryption and decryption on non-sensitive sample
    documents.
6.  Before deploying, design the document access and key-management
    model separately from qpdf's password encryption.


---

# 13. More qpdf command-line examples

The examples below use `input.pdf` as the source file. Replace filenames and paths with your own. Test with copies of non-sensitive PDFs first.

## 13.1 Display PDF information

```bash
qpdf --show-npages input.pdf
qpdf --show-pages input.pdf
qpdf --check input.pdf
```

- `--show-npages` prints the page count.
- `--show-pages` prints page-related information.
- `--check` checks the PDF structure for certain problems. It is not a guarantee that the document is safe or fully valid.

## 13.2 Create a decrypted copy

For a PDF protected by an open password:

```bash
qpdf --password='your-password' --decrypt protected.pdf unlocked.pdf
```

Use only when you are authorized to decrypt the file and know the required password.

## 13.3 Extract a range of pages

Extract pages 2 to 6:

```bash
qpdf input.pdf --pages . 2-6 -- pages-2-to-6.pdf
```

Extract only pages 1 and 4:

```bash
qpdf input.pdf --pages . 1,4 -- selected-pages.pdf
```

## 13.4 Combine pages from multiple PDFs

Combine all pages from two PDFs:

```bash
qpdf --empty --pages first.pdf second.pdf -- combined.pdf
```

Take pages 1–3 from the first PDF and pages 5–8 from the second:

```bash
qpdf --empty --pages first.pdf 1-3 second.pdf 5-8 -- selected-combined.pdf
```

## 13.5 Rotate pages

Rotate all pages by 90 degrees clockwise:

```bash
qpdf input.pdf --rotate=+90 -- rotated.pdf
```

Rotate pages 2–4 only:

```bash
qpdf input.pdf --rotate=+90:2-4 -- rotated-pages.pdf
```

## 13.6 Linearize a PDF

```bash
qpdf --linearize input.pdf linearized.pdf
```

Linearization can help a compatible PDF viewer display a document progressively over a network. It does not replace server-side access control.

## 13.7 Compress / optimize object streams

```bash
qpdf --object-streams=generate input.pdf optimized.pdf
```

The output size may increase or decrease depending on the source PDF. Always compare the result and verify that it meets your requirements.

## 13.8 Check encryption settings

```bash
qpdf --show-encryption encrypted.pdf
```

If the file requires a password to inspect its encryption details:

```bash
qpdf --password='your-password' --show-encryption encrypted.pdf
```

## 13.9 Use full paths on Windows

```bat
"C:\Program Files\qpdf\bin\qpdf.exe" --version
"C:\Program Files\qpdf\bin\qpdf.exe" --check "C:\pdf\input.pdf"
```

The installation path can differ. Confirm the actual location of `qpdf.exe`.

## 13.10 Save output and error messages to a log on Linux

```bash
qpdf --check input.pdf > qpdf-check.log 2>&1
status=$?
echo "qpdf exit code: $status"
```

Avoid logging passwords or confidential document content.

---

# 14. More PHP examples

## 14.1 Recommended approach: Symfony Process

For production PHP applications, consider Symfony Process rather than building a shell command manually. It accepts arguments as an array and handles process invocation more safely. Install it in your PHP project with Composer:

```bash
composer require symfony/process
```

Example using Symfony Process:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$qpdf = '/usr/bin/qpdf'; // Set the correct path for your system.
$input = __DIR__ . '/input.pdf';
$output = __DIR__ . '/encrypted.pdf';

if (!is_file($input) || !is_readable($input)) {
    throw new RuntimeException('Input PDF is missing or unreadable.');
}

$process = new Process([
    $qpdf,
    '--encrypt',
    'replace-with-user-password',
    'replace-with-owner-password',
    '256',
    '--',
    $input,
    $output,
]);

$process->setTimeout(60);
$process->run();

if (!$process->isSuccessful() || !is_file($output)) {
    error_log('qpdf failed: ' . $process->getErrorOutput());
    throw new RuntimeException('PDF encryption failed.');
}

echo 'PDF encrypted successfully.';
```

**Secret-handling note:** Passing passwords as process arguments may expose them to privileged local users or process inspection on some systems. Symfony Process helps avoid shell-quoting mistakes, but it does not automatically hide arguments. For production, evaluate the threat model and use a suitable secret-handling and cryptographic design.

On Windows, `$qpdf` could be set to the actual path to `qpdf.exe`, for example:

```php
$qpdf = 'C:\\Tools\\qpdf\\bin\\qpdf.exe';
```

Adjust the path to match your installation.

## 14.2 PHP function to run qpdf with an argument array

This example uses Symfony Process and centralizes basic checks:

```php
<?php

use Symfony\Component\Process\Process;

function runQpdf(array $arguments, int $timeoutSeconds = 60): string
{
    $qpdf = '/usr/bin/qpdf'; // Configure this for the current host.

    $process = new Process(array_merge([$qpdf], $arguments));
    $process->setTimeout($timeoutSeconds);
    $process->run();

    if (!$process->isSuccessful()) {
        // Do not send raw internal error details to an end user.
        error_log('qpdf error: ' . $process->getErrorOutput());
        throw new RuntimeException('The PDF operation failed.');
    }

    return $process->getOutput();
}

// Example:
runQpdf(['--check', __DIR__ . '/input.pdf']);
echo "PDF check completed.\n";
```

Do not let a web request submit arbitrary qpdf arguments. Build an allowlist of supported operations and validate all input and output paths.

## 14.3 PHP: check a PDF before processing

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$qpdf = '/usr/bin/qpdf';
$input = __DIR__ . '/input.pdf';

if (!is_file($input) || !is_readable($input)) {
    throw new RuntimeException('PDF is missing or unreadable.');
}

$process = new Process([$qpdf, '--check', $input]);
$process->setTimeout(30);
$process->run();

if ($process->isSuccessful()) {
    echo "qpdf check completed without reporting an error.\n";
} else {
    error_log($process->getErrorOutput());
    echo "The PDF could not be checked successfully.\n";
}
```

A successful check does not prove that a PDF is harmless. Continue to treat uploaded PDFs as untrusted input.

## 14.4 PHP: extract pages

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$qpdf = '/usr/bin/qpdf';
$input = __DIR__ . '/input.pdf';
$output = __DIR__ . '/pages-1-to-3.pdf';

$process = new Process([
    $qpdf,
    $input,
    '--pages',
    '.',
    '1-3',
    '--',
    $output,
]);

$process->setTimeout(60);
$process->run();

if (!$process->isSuccessful() || !is_file($output)) {
    error_log($process->getErrorOutput());
    throw new RuntimeException('Could not extract PDF pages.');
}

echo "Pages extracted.\n";
```

For user-selected page ranges, validate the range and enforce a maximum page count before building the argument list.

## 14.5 PHP: merge PDFs

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$qpdf = '/usr/bin/qpdf';
$first = __DIR__ . '/first.pdf';
$second = __DIR__ . '/second.pdf';
$output = __DIR__ . '/merged.pdf';

foreach ([$first, $second] as $file) {
    if (!is_file($file) || !is_readable($file)) {
        throw new RuntimeException('A source PDF is missing or unreadable.');
    }
}

$process = new Process([
    $qpdf,
    '--empty',
    '--pages',
    $first,
    $second,
    '--',
    $output,
]);

$process->setTimeout(120);
$process->run();

if (!$process->isSuccessful() || !is_file($output)) {
    error_log($process->getErrorOutput());
    throw new RuntimeException('PDF merge failed.');
}

echo "PDFs merged.\n";
```

## 14.6 PHP: decrypt a PDF using a password

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$qpdf = '/usr/bin/qpdf';
$input = __DIR__ . '/encrypted.pdf';
$output = __DIR__ . '/decrypted.pdf';
$password = getenv('PDF_INPUT_PASSWORD');

if ($password === false || $password === '') {
    throw new RuntimeException('PDF password is not configured.');
}

$process = new Process([
    $qpdf,
    '--password=' . $password,
    '--decrypt',
    $input,
    $output,
]);

$process->setTimeout(60);
$process->run();

if (!$process->isSuccessful() || !is_file($output)) {
    error_log('qpdf decrypt failed: ' . $process->getErrorOutput());
    throw new RuntimeException('PDF decryption failed.');
}

echo "PDF decrypted.\n";
```

Environment variables are shown for demonstration and are not always the strongest secret store. Use your deployment's approved secret manager where possible. The password still appears in the process argument list, so consider a design that avoids exposing secrets in arguments when that is required by your threat model.

## 14.7 PHP: create a temporary output file

For a web application, avoid predictable output names shared by multiple users. Use a private temporary directory or securely generated unique filename, ensure the directory is not web-accessible, and remove temporary files when no longer needed.

Simple illustrative pattern:

```php
<?php

$tempDir = sys_get_temp_dir();
$output = tempnam($tempDir, 'qpdf_');

if ($output === false) {
    throw new RuntimeException('Could not create temporary output file.');
}

// Pass $output as the qpdf output path.
// After the application has securely used the file, remove it:
try {
    // Run qpdf and process the output here.
} finally {
    if (is_file($output)) {
        unlink($output);
    }
}
```

If qpdf expects to create the output itself, test how it handles the pre-existing empty file from `tempnam()` in your workflow; an alternative is to generate a unique private directory and output path with appropriate permissions. Never place sensitive temporary PDFs in a publicly served directory.

## 14.8 PHP: capture exit code and output with `exec()`

This is a minimal example for learning. For a production web service, prefer a controlled process API and carefully manage secrets and paths.

```php
<?php

$qpdf = '/usr/bin/qpdf';
$input = __DIR__ . '/input.pdf';

$command = escapeshellarg($qpdf)
    . ' --check '
    . escapeshellarg($input)
    . ' 2>&1';

$lines = [];
$exitCode = 1;
exec($command, $lines, $exitCode);

if ($exitCode === 0) {
    echo "PDF check succeeded.\n";
} else {
    error_log(implode("\n", $lines));
    echo "PDF check failed.\n";
}
```

`escapeshellarg()` should be applied to each individual argument. It does not replace authorization, path validation, resource limits, or secret management.

---

# 15. Production deployment checklist

Before deploying qpdf in a PHP application:

- [ ] Pin and document the qpdf version tested in each environment.
- [ ] Confirm the correct executable path on Windows and CentOS.
- [ ] Run qpdf as a low-privilege service account.
- [ ] Allowlist operations rather than accepting arbitrary command-line options.
- [ ] Validate source files and keep output paths within controlled directories.
- [ ] Apply timeouts and sensible file-size and page-count limits.
- [ ] Keep temporary files outside public web directories.
- [ ] Avoid logging passwords, private keys, or sensitive PDF content.
- [ ] Check process exit codes and verify expected output files exist.
- [ ] Test encrypted PDFs in the actual PDF readers used by staff.
- [ ] Keep authorization checks separate from PDF encryption.
- [ ] Design certificate, USB-token, and key-management flows independently of qpdf.
- [ ] Test backup, recovery, key rotation, and incident-response procedures.

