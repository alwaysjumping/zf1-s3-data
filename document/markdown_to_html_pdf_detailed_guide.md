# Detailed Guide: Converting Markdown (`.md`) to HTML and PDF

## Offline-Friendly Windows Workflow with Pandoc, CSS, PowerShell, and Edge/Chrome

This guide explains how to use Markdown as the source format for
documentation and generate HTML and PDF files from it.

The recommended workflow is:

``` text
Markdown (.md)
      │
      ▼
    Pandoc
      │
      ▼
HTML (.html)
      │
      ▼
Edge / Chrome Headless
      │
      ▼
 PDF (.pdf)
```

This approach is especially useful for offline documentation projects
because, after the required tools are installed, the conversion can be
performed locally without an Internet connection.

------------------------------------------------------------------------

# 1. Why Use Markdown as the Source Format?

Markdown is a good documentation source format because it is:

``` text
Easy to write
    +
Easy to read
    +
Plain text
    +
Git-friendly
    +
Easy to search
    +
Easy to convert
    +
Suitable for offline work
```

For example:

``` markdown
# CSS Coding Style

## Introduction

This document describes our CSS coding standard.

### Example

```css
.email-card {
    padding: 16px;
}
```


    The same Markdown source can be converted into multiple output formats.

    ```text
                     ┌────────── HTML
                     │
    Markdown ────────┼────────── PDF
                     │
                     ├────────── DOCX
                     │
                     └────────── Other formats

This makes Markdown a useful **master documentation format**.

------------------------------------------------------------------------

# 2. Recommended Toolchain

For a Windows documentation environment, a practical toolchain is:

``` text
Markdown
   │
   ▼
Pandoc
   │
   ▼
HTML
   │
   ├── CSS
   │
   ▼
Microsoft Edge / Google Chrome
   │
   ▼
PDF
```

Tools:

``` text
Pandoc
    Markdown → HTML

CSS
    Controls HTML/PDF appearance

Microsoft Edge or Chrome
    HTML → PDF

PowerShell
    Automates the complete process
```

------------------------------------------------------------------------

# 3. Install Pandoc

Pandoc is a document conversion tool.

After installation, open PowerShell and check:

``` powershell
pandoc --version
```

If Pandoc is installed correctly, version information should appear.

If PowerShell reports that `pandoc` is not recognized, either Pandoc is
not installed or its executable directory is not available through
`PATH`.

------------------------------------------------------------------------

# 4. Offline Installation Strategy

For computers without Internet access, prepare the required installers
on an Internet-connected computer.

A typical package set is:

``` text
offline-tools/
├── pandoc-installer
├── edge-or-chrome-installer
└── documentation/
    └── installation-notes.md
```

Copy the installers using an approved removable drive or internal
software-distribution method.

After installation, the conversion workflow can run locally.

------------------------------------------------------------------------

# 5. Basic Markdown to HTML Conversion

Suppose you have:

``` text
C:\docs\document.md
```

Run:

``` powershell
pandoc document.md -o document.html
```

Pandoc reads:

``` text
document.md
```

and creates:

``` text
document.html
```

------------------------------------------------------------------------

# 6. Generate Standalone HTML

For documentation, use Pandoc's standalone mode:

``` powershell
pandoc document.md `
    --standalone `
    -o document.html
```

The PowerShell backtick:

``` text
`
```

continues the command onto the next line.

You can also write it on one line:

``` powershell
pandoc document.md --standalone -o document.html
```

------------------------------------------------------------------------

# 7. Why `--standalone` Is Important

Without standalone mode, Pandoc may produce an HTML fragment intended to
be inserted into another document.

With:

``` text
--standalone
```

Pandoc generates a complete HTML document containing document-level
structure such as:

``` html
<!DOCTYPE html>
<html>
<head>
    ...
</head>
<body>
    ...
</body>
</html>
```

For independent documentation files, standalone HTML is usually
preferable.

------------------------------------------------------------------------

# 8. Add a Document Title

You can supply metadata:

``` powershell
pandoc document.md `
    --standalone `
    --metadata title="CSS Coding Style Guide" `
    -o document.html
```

The title can then be used by the generated HTML document.

------------------------------------------------------------------------

# 9. Metadata Inside Markdown

Markdown can also contain document metadata.

For example:

``` markdown
---
title: CSS Coding Style Guide
author: Development Team
---

# Introduction

This document defines our CSS coding standard.
```

Then run:

``` powershell
pandoc document.md `
    --standalone `
    -o document.html
```

This keeps documentation metadata close to the source document.

------------------------------------------------------------------------

# 10. Add a Table of Contents

For longer documents:

``` powershell
pandoc document.md `
    --standalone `
    --toc `
    -o document.html
```

Conceptually:

``` text
Markdown headings
       │
       ▼
Pandoc
       │
       ├── Table of Contents
       │
       └── Document content
```

This is useful for long coding-style guides.

------------------------------------------------------------------------

# 11. Control Table-of-Contents Depth

For example:

``` powershell
pandoc document.md `
    --standalone `
    --toc `
    --toc-depth=3 `
    -o document.html
```

This includes heading levels through level 3 in the generated table of
contents.

------------------------------------------------------------------------

# 12. Create a CSS File

Create:

``` text
style.css
```

Example:

``` css
body {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 30px;

    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.6;

    color: #333;
    background-color: #fff;
}

h1,
h2,
h3 {
    color: #222;
}

h1 {
    border-bottom: 2px solid #ddd;
}

h2 {
    margin-top: 32px;
    border-bottom: 1px solid #ddd;
}

pre {
    overflow-x: auto;
    padding: 16px;

    background-color: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
}

code {
    font-family: Consolas, "Courier New", monospace;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 8px;

    text-align: left;

    border: 1px solid #ddd;
}
```

------------------------------------------------------------------------

# 13. Apply CSS During HTML Generation

Run:

``` powershell
pandoc document.md `
    --standalone `
    --css style.css `
    -o document.html
```

The generated HTML references the stylesheet.

A typical project may contain:

``` text
docs/
├── document.md
├── document.html
└── style.css
```

------------------------------------------------------------------------

# 14. Keep CSS in an Assets Directory

For multiple documents:

``` text
docs/
├── src/
│   ├── php-style.md
│   ├── javascript-style.md
│   └── css-style.md
│
├── assets/
│   └── documentation.css
│
└── html/
```

All generated documents can use the same stylesheet.

This provides a consistent documentation appearance.

------------------------------------------------------------------------

# 15. Recommended Documentation CSS

Example:

``` css
body {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px;

    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.6;

    color: #333;
}

h1 {
    margin-bottom: 24px;

    font-size: 32px;
}

h2 {
    margin-top: 40px;
    padding-bottom: 8px;

    font-size: 24px;

    border-bottom: 1px solid #ddd;
}

h3 {
    margin-top: 28px;

    font-size: 18px;
}

p {
    margin: 12px 0;
}

pre {
    overflow-x: auto;
    padding: 16px;

    background-color: #f6f8fa;
    border: 1px solid #ddd;
    border-radius: 4px;
}

code {
    font-family: Consolas, "Courier New", monospace;
}

blockquote {
    margin: 16px 0;
    padding: 8px 16px;

    border-left: 4px solid #ccc;
}

table {
    width: 100%;
    margin: 16px 0;

    border-collapse: collapse;
}

th,
td {
    padding: 8px 12px;

    border: 1px solid #ddd;
}

th {
    font-weight: 600;
}
```

------------------------------------------------------------------------

# 16. Markdown Code Blocks

Markdown:

```` markdown
```php
public function getUser($userId)
{
    return $this->userService->getUser($userId);
}
```
````

Pandoc converts fenced code blocks into HTML code structures.

This is particularly useful for technical documentation.

------------------------------------------------------------------------

# 17. Markdown Tables

Example:

``` markdown
| Tool | Purpose |
|---|---|
| Pandoc | Markdown → HTML |
| CSS | Document styling |
| Edge | HTML → PDF |
| PowerShell | Automation |
```

Pandoc can convert the table to HTML.

------------------------------------------------------------------------

# 18. Markdown Images

Markdown:

``` markdown
![Architecture](images/architecture.png)
```

Recommended structure:

``` text
docs/
├── src/
│   └── architecture.md
│
└── images/
    └── architecture.png
```

Be careful with relative paths when generating HTML into a different
directory.

------------------------------------------------------------------------

# 19. Relative Paths

Suppose:

``` text
docs/
├── src/
│   └── document.md
├── assets/
│   └── documentation.css
└── html/
```

If HTML is generated into:

``` text
html/document.html
```

make sure referenced assets are reachable from the generated HTML
location.

Path handling should be tested before automating a large documentation
set.

------------------------------------------------------------------------

# 20. Markdown → HTML Workflow

The basic process is:

``` text
document.md
     │
     ▼
   Pandoc
     │
     ├── Parse Markdown
     ├── Build HTML
     ├── Add metadata
     ├── Add TOC
     └── Reference CSS
     │
     ▼
document.html
```

Example:

``` powershell
pandoc document.md `
    --standalone `
    --toc `
    --toc-depth=3 `
    --css style.css `
    -o document.html
```

------------------------------------------------------------------------

# 21. Open Generated HTML

After conversion:

``` powershell
Start-Process .\document.html
```

Or open the HTML file manually in Edge/Chrome.

Review:

``` text
Headings
Code blocks
Tables
Images
Table of contents
Page width
Fonts
Spacing
```

Always inspect HTML before generating the final PDF.

------------------------------------------------------------------------

# 22. Markdown → PDF Options

There are two common approaches.

## Option A

``` text
Markdown
    ↓
Pandoc
    ↓
HTML
    ↓
Edge / Chrome
    ↓
PDF
```

## Option B

``` text
Markdown
    ↓
Pandoc
    ↓
PDF engine
    ↓
PDF
```

For a Windows/offline documentation workflow, Option A is often simpler
because a browser may already be installed.

------------------------------------------------------------------------

# 23. Recommended PDF Workflow

Recommended:

``` text
source.md
    ↓
Pandoc
    ↓
source.html
    ↓
documentation.css
    ↓
Edge Headless
    ↓
source.pdf
```

Advantages:

``` text
One CSS controls HTML and PDF appearance
Easy browser preview
No large LaTeX installation required
Works well with technical documentation
Can be automated with PowerShell
```

------------------------------------------------------------------------

# 24. Convert HTML to PDF with Microsoft Edge

Example:

``` powershell
& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" `
    --headless `
    --disable-gpu `
    --print-to-pdf="C:\docs\document.pdf" `
    "file:///C:/docs/document.html"
```

The `&` operator tells PowerShell to execute the program whose path
follows.

------------------------------------------------------------------------

# 25. Edge Installation Path

Common paths include:

``` text
C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe
```

or:

``` text
C:\Program Files\Microsoft\Edge\Application\msedge.exe
```

Your script should verify that the executable exists before attempting
conversion.

------------------------------------------------------------------------

# 26. Convert HTML to PDF with Chrome

Example:

``` powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" `
    --headless `
    --disable-gpu `
    --print-to-pdf="C:\docs\document.pdf" `
    "file:///C:/docs/document.html"
```

The exact Chrome path depends on how Chrome was installed.

------------------------------------------------------------------------

# 27. Convert a Windows Path to a `file:///` URL

A browser needs a URL such as:

``` text
file:///C:/docs/document.html
```

rather than:

``` text
C:\docs\document.html
```

In a PowerShell automation script, use a URI conversion rather than
manually replacing characters whenever possible.

For example:

``` powershell
$htmlPath = "C:\docs\document.html"

$htmlUri = ([System.Uri]$htmlPath).AbsoluteUri
```

Then:

``` powershell
$htmlUri
```

may produce:

``` text
file:///C:/docs/document.html
```

------------------------------------------------------------------------

# 28. Basic PowerShell HTML-to-PDF Example

``` powershell
$edgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

$htmlPath = "C:\docs\document.html"
$pdfPath = "C:\docs\document.pdf"

$htmlUri = ([System.Uri]$htmlPath).AbsoluteUri

& $edgePath `
    --headless `
    --disable-gpu `
    "--print-to-pdf=$pdfPath" `
    $htmlUri
```

------------------------------------------------------------------------

# 29. Verify the PDF Was Created

Do not assume browser execution automatically means success.

After conversion:

``` powershell
if (-not (Test-Path $pdfPath)) {
    throw "PDF generation failed: $pdfPath"
}
```

A build process should verify expected output files.

------------------------------------------------------------------------

# 30. Full Markdown → HTML → PDF Process

Conceptually:

``` text
START
  │
  ▼
Check Markdown source
  │
  ▼
Check Pandoc
  │
  ▼
Check CSS
  │
  ▼
Generate HTML
  │
  ▼
Verify HTML exists
  │
  ▼
Check Edge / Chrome
  │
  ▼
Generate PDF
  │
  ▼
Verify PDF exists
  │
  ▼
DONE
```

------------------------------------------------------------------------

# 31. Simple PowerShell Automation

Example:

``` powershell
$sourcePath = "C:\docs\document.md"
$cssPath = "C:\docs\style.css"
$htmlPath = "C:\docs\document.html"
$pdfPath = "C:\docs\document.pdf"

$edgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

if (-not (Test-Path $sourcePath)) {
    throw "Markdown file not found: $sourcePath"
}

if (-not (Test-Path $cssPath)) {
    throw "CSS file not found: $cssPath"
}

if (-not (Test-Path $edgePath)) {
    throw "Microsoft Edge not found: $edgePath"
}

& pandoc `
    $sourcePath `
    --standalone `
    --toc `
    --toc-depth=3 `
    --css $cssPath `
    -o $htmlPath

if (-not (Test-Path $htmlPath)) {
    throw "HTML generation failed."
}

$htmlUri = ([System.Uri]$htmlPath).AbsoluteUri

& $edgePath `
    --headless `
    --disable-gpu `
    "--print-to-pdf=$pdfPath" `
    $htmlUri

if (-not (Test-Path $pdfPath)) {
    throw "PDF generation failed."
}

Write-Host "Build completed."
Write-Host "HTML: $htmlPath"
Write-Host "PDF : $pdfPath"
```

------------------------------------------------------------------------

# 32. Verify Pandoc in PowerShell

Before calling Pandoc:

``` powershell
$pandoc = Get-Command pandoc -ErrorAction SilentlyContinue

if ($null -eq $pandoc) {
    throw "Pandoc is not installed or is not available in PATH."
}
```

This produces a clearer error than allowing the build to fail later.

------------------------------------------------------------------------

# 33. Find Edge Automatically

Example:

``` powershell
$edgePaths = @(
    "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    "C:\Program Files\Microsoft\Edge\Application\msedge.exe"
)

$edgePath = $null

foreach ($path in $edgePaths) {
    if (Test-Path $path) {
        $edgePath = $path
        break
    }
}

if ($null -eq $edgePath) {
    throw "Microsoft Edge was not found."
}
```

This is better than assuming one installation path.

------------------------------------------------------------------------

# 34. Generate Multiple Documents

Suppose:

``` text
src/
├── php-coding-style.md
├── javascript-coding-style.md
└── css-coding-style.md
```

PowerShell can process all Markdown files:

``` powershell
$files = Get-ChildItem `
    -Path ".\src" `
    -Filter "*.md"

foreach ($file in $files) {
    Write-Host "Building $($file.Name)"
}
```

------------------------------------------------------------------------

# 35. Generate Matching HTML Filenames

Example:

``` powershell
$baseName = [System.IO.Path]::GetFileNameWithoutExtension(
    $file.Name
)

$htmlPath = Join-Path `
    ".\html" `
    ($baseName + ".html")
```

For:

``` text
css-coding-style.md
```

the output becomes:

``` text
css-coding-style.html
```

------------------------------------------------------------------------

# 36. Generate Matching PDF Filenames

``` powershell
$pdfPath = Join-Path `
    ".\pdf" `
    ($baseName + ".pdf")
```

Result:

``` text
css-coding-style.pdf
```

------------------------------------------------------------------------

# 37. Recommended Directory Structure

A practical documentation project:

``` text
docs/
├── src/
│   ├── php-coding-style.md
│   ├── javascript-coding-style.md
│   └── css-coding-style.md
│
├── assets/
│   ├── documentation.css
│   └── images/
│
├── html/
│
├── pdf/
│
└── scripts/
    └── build-docs.ps1
```

Generated files stay separate from source files.

------------------------------------------------------------------------

# 38. Source vs Generated Files

Use:

``` text
src/
    ↓
Human-edited Markdown

assets/
    ↓
CSS and images

html/
    ↓
Generated HTML

pdf/
    ↓
Generated PDF

scripts/
    ↓
Build automation
```

Do not manually edit generated HTML/PDF when Markdown is the master
source.

If a correction is required:

``` text
Edit Markdown
      ↓
Run build
      ↓
Regenerate HTML
      ↓
Regenerate PDF
```

------------------------------------------------------------------------

# 39. Batch Build Architecture

``` text
                 ┌─────────────────────────┐
                 │          src/           │
                 │                         │
                 │  PHP.md                 │
                 │  JavaScript.md          │
                 │  CSS.md                 │
                 └────────────┬────────────┘
                              │
                              ▼
                           Pandoc
                              │
                 ┌────────────┴────────────┐
                 ▼                         ▼
             HTML files              Shared CSS
                 │
                 └────────────┬────────────┘
                              ▼
                        Edge / Chrome
                              │
                              ▼
                          PDF files
```

------------------------------------------------------------------------

# 40. Build All Markdown Files

A simplified batch example:

``` powershell
$sourceDirectory = ".\src"
$htmlDirectory = ".\html"
$pdfDirectory = ".\pdf"
$cssPath = ".\assets\documentation.css"

$edgePath = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

$files = Get-ChildItem `
    -Path $sourceDirectory `
    -Filter "*.md"

foreach ($file in $files) {
    $baseName = [System.IO.Path]::GetFileNameWithoutExtension(
        $file.Name
    )

    $htmlPath = Join-Path `
        $htmlDirectory `
        ($baseName + ".html")

    $pdfPath = Join-Path `
        $pdfDirectory `
        ($baseName + ".pdf")

    & pandoc `
        $file.FullName `
        --standalone `
        --toc `
        --toc-depth=3 `
        --css $cssPath `
        -o $htmlPath

    if (-not (Test-Path $htmlPath)) {
        throw "HTML generation failed: $htmlPath"
    }

    $htmlUri = ([System.Uri]$htmlPath).AbsoluteUri

    & $edgePath `
        --headless `
        --disable-gpu `
        "--print-to-pdf=$pdfPath" `
        $htmlUri

    if (-not (Test-Path $pdfPath)) {
        throw "PDF generation failed: $pdfPath"
    }

    Write-Host "Generated:"
    Write-Host "  $htmlPath"
    Write-Host "  $pdfPath"
}
```

------------------------------------------------------------------------

# 41. Create Output Directories Automatically

Before building:

``` powershell
if (-not (Test-Path $htmlDirectory)) {
    New-Item `
        -ItemType Directory `
        -Path $htmlDirectory | Out-Null
}

if (-not (Test-Path $pdfDirectory)) {
    New-Item `
        -ItemType Directory `
        -Path $pdfDirectory | Out-Null
}
```

This prevents failures when output directories do not yet exist.

------------------------------------------------------------------------

# 42. Stop on Errors

A documentation build should fail clearly rather than silently continue
with missing output.

Example:

``` powershell
$ErrorActionPreference = "Stop"
```

Then validate each important step.

------------------------------------------------------------------------

# 43. Direct Markdown → PDF with Pandoc

Pandoc can also be used as:

``` powershell
pandoc document.md -o document.pdf
```

However, PDF generation normally requires a compatible PDF engine.

Depending on the setup, that may involve a TeX/LaTeX distribution or
another supported engine.

------------------------------------------------------------------------

# 44. Why Direct PDF May Require More Setup

The workflow may become:

``` text
Markdown
    ↓
Pandoc
    ↓
LaTeX
    ↓
PDF engine
    ↓
PDF
```

This can provide excellent typesetting, but it introduces additional
dependencies.

For an offline Windows environment, a browser-based PDF workflow can be
simpler.

------------------------------------------------------------------------

# 45. Browser PDF vs LaTeX PDF

A practical comparison:

  Approach                            Main Strength
  ----------------------------------- -------------------------------
  Pandoc → HTML → Edge/Chrome → PDF   Simple CSS-based workflow
  Pandoc → LaTeX → PDF                Advanced document typesetting

For web-oriented technical documentation containing HTML-like styling
and code blocks, the browser approach is often convenient.

For book/publishing-quality typesetting, LaTeX may be worth the
additional setup.

------------------------------------------------------------------------

# 46. HTML and PDF Can Share Styling

One major advantage of the browser approach:

``` text
documentation.css
        │
        ├──────── HTML
        │
        └──────── PDF
```

The HTML preview and PDF can therefore have closely related visual
styling.

------------------------------------------------------------------------

# 47. Add Print-Specific CSS

Screen and print output may need different styling.

Example:

``` css
@media print {
    body {
        max-width: none;
        margin: 0;
        padding: 0;

        font-size: 11pt;
    }

    nav {
        display: none;
    }

    pre {
        white-space: pre-wrap;
    }
}
```

This lets the same HTML work for browser reading and PDF printing.

------------------------------------------------------------------------

# 48. Page Breaks

For PDF-oriented CSS:

``` css
.page-break {
    break-before: page;
}
```

HTML:

``` html
<div class="page-break"></div>
```

For normal Markdown workflows, page-break behavior may require raw HTML
or a custom template/filter strategy.

Use explicit page breaks only when the document genuinely needs them.

------------------------------------------------------------------------

# 49. Avoid Breaking Code Blocks

Print CSS can help:

``` css
pre,
blockquote,
table {
    break-inside: avoid;
}
```

Browser print behavior may still depend on available page space.

------------------------------------------------------------------------

# 50. Headings and Page Breaks

For print-oriented documents:

``` css
h1,
h2,
h3 {
    break-after: avoid;
}
```

This can reduce headings being stranded at the bottom of a PDF page.

------------------------------------------------------------------------

# 51. Images in PDF

Use responsive image CSS:

``` css
img {
    max-width: 100%;
    height: auto;
}
```

For printing:

``` css
@media print {
    img {
        max-width: 100%;
    }
}
```

Ensure all image paths resolve correctly from the generated HTML before
starting the PDF conversion.

------------------------------------------------------------------------

# 52. Local Assets and Offline Operation

For a fully offline documentation package, avoid depending on remote
assets such as:

``` text
Online fonts
CDN CSS
CDN JavaScript
Remote images
```

Prefer:

``` text
Local CSS
Local images
System/local fonts
Local scripts when needed
```

Then:

``` text
Internet unavailable
       │
       ▼
Documentation still builds
```

------------------------------------------------------------------------

# 53. Avoid CDN Dependencies

Avoid:

``` html
<link
    rel="stylesheet"
    href="https://example.com/style.css">
```

for an offline documentation system.

Prefer:

``` text
assets/documentation.css
```

All required resources should travel with the documentation project.

------------------------------------------------------------------------

# 54. Code Fonts

For Windows technical documentation:

``` css
code,
pre {
    font-family:
        Consolas,
        "Courier New",
        monospace;
}
```

This uses common local fonts without requiring an Internet font service.

------------------------------------------------------------------------

# 55. UTF-8

Keep Markdown, CSS, and HTML source files in UTF-8.

This is important for:

``` text
English
Khmer
Symbols
Code
International text
```

Avoid mixing encodings across documentation files.

------------------------------------------------------------------------

# 56. Filenames

Prefer predictable filenames:

``` text
php-coding-style.md
javascript-coding-style.md
css-coding-style.md
zf1-jwt-guide.md
```

Avoid names such as:

``` text
New Document final FINAL 2.md
```

Predictable filenames make scripting much easier.

------------------------------------------------------------------------

# 57. Avoid Spaces in Build Filenames Where Practical

This:

``` text
css-coding-style.md
```

is easier to script than:

``` text
CSS Coding Style Guide Final.md
```

PowerShell can handle spaces, but simple filenames reduce quoting
mistakes.

------------------------------------------------------------------------

# 58. Keep the Markdown Source in Git

Recommended:

``` text
Git repository
    │
    ├── Markdown source
    ├── CSS
    ├── images
    └── build scripts
```

Whether generated HTML/PDF should also be committed depends on the
team's deployment and release process.

------------------------------------------------------------------------

# 59. Do Not Manually Edit Generated HTML

If Markdown is the source of truth:

``` text
Wrong:
    Edit document.html directly

Correct:
    Edit document.md
          ↓
    Run build
          ↓
    document.html regenerated
```

Otherwise the next build will overwrite manual HTML changes.

------------------------------------------------------------------------

# 60. Do Not Manually Edit Generated PDF

The PDF is an output artifact.

To change it:

``` text
Edit Markdown or CSS
        ↓
Regenerate HTML
        ↓
Regenerate PDF
```

This keeps all formats synchronized.

------------------------------------------------------------------------

# 61. Recommended Documentation Development Cycle

``` text
Write Markdown
      ↓
Preview Markdown
      ↓
Generate HTML
      ↓
Review HTML
      ↓
Adjust Markdown/CSS
      ↓
Generate PDF
      ↓
Review PDF
      ↓
Commit source
```

------------------------------------------------------------------------

# 62. Troubleshooting: `pandoc` Not Found

If:

``` text
pandoc : The term 'pandoc' is not recognized...
```

check:

``` powershell
Get-Command pandoc
```

If no command is found, verify installation and `PATH`.

You can also call Pandoc by full executable path if necessary.

------------------------------------------------------------------------

# 63. Troubleshooting: CSS Not Applied

Check:

``` text
Is --css specified?
Is --standalone used?
Is the CSS path correct?
Can generated HTML locate the CSS?
Was HTML moved after generation?
```

Open browser developer tools and inspect the stylesheet reference if
needed.

------------------------------------------------------------------------

# 64. Troubleshooting: Images Missing

Check:

``` text
Image path in Markdown
Generated HTML location
Relative path relationship
Filename case
File existence
```

A common problem is moving HTML to another directory without adjusting
relative asset paths.

------------------------------------------------------------------------

# 65. Troubleshooting: PDF Not Created

Check:

``` text
Browser executable path
HTML file exists
file:/// URL is valid
PDF output directory exists
Permissions allow writing
Browser process completed
```

Always verify output using:

``` powershell
Test-Path $pdfPath
```

------------------------------------------------------------------------

# 66. Troubleshooting: PDF Uses Wrong Styling

First open the generated HTML in the same browser.

If HTML styling is already wrong, fix the HTML/CSS stage first.

If HTML looks correct but print/PDF differs, inspect:

``` text
@media print rules
Page size
Margins
Print color behavior
Page breaks
Overflow
```

------------------------------------------------------------------------

# 67. Troubleshooting: Long Code Lines

For technical documents:

``` css
pre {
    overflow-x: auto;
}
```

For PDF, horizontal scrolling is impossible, so consider:

``` css
@media print {
    pre {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
}
```

Be aware that wrapping source code may reduce readability. Test the
result.

------------------------------------------------------------------------

# 68. Troubleshooting: Tables Too Wide

Example:

``` css
table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    overflow-wrap: anywhere;
}
```

For very wide technical tables, restructuring the source content may be
better than forcing everything onto one page.

------------------------------------------------------------------------

# 69. Build Logging

For automated builds:

``` powershell
Write-Host "Converting Markdown to HTML..."
Write-Host "Generating PDF..."
Write-Host "Build completed."
```

For multiple files:

``` powershell
Write-Host "Building: $($file.Name)"
```

Clear build output helps identify failures.

------------------------------------------------------------------------

# 70. Recommended Error Handling

Use validation before conversion:

``` text
Check source
     ↓
Check Pandoc
     ↓
Check CSS
     ↓
Check browser
     ↓
Create output directories
     ↓
Generate HTML
     ↓
Verify HTML
     ↓
Generate PDF
     ↓
Verify PDF
```

Do not wait until the final step to discover that a required tool is
missing.

------------------------------------------------------------------------

# 71. Example Build Configuration

As the project grows, settings can be placed in a configuration file.

Example `config.json`:

``` json
{
    "sourceDirectory": "src",
    "htmlDirectory": "html",
    "pdfDirectory": "pdf",
    "css": "assets/documentation.css",
    "toc": true,
    "tocDepth": 3
}
```

Then PowerShell can read the configuration rather than hard-coding every
path.

------------------------------------------------------------------------

# 72. Why Configuration Helps

Without configuration:

``` text
build-docs.ps1
    contains every path and setting
```

With configuration:

``` text
config.json
      │
      ▼
build-docs.ps1
      │
      ▼
Build behavior
```

This makes settings easier to modify without changing script logic.

------------------------------------------------------------------------

# 73. Possible Future Project Structure

``` text
documentation/
├── config.json
│
├── src/
│   ├── php/
│   │   ├── php-coding-style.md
│   │   └── phpdoc-guide.md
│   │
│   ├── javascript/
│   │   └── javascript-coding-style.md
│   │
│   └── css/
│       └── css-coding-style.md
│
├── assets/
│   ├── css/
│   │   └── documentation.css
│   │
│   └── images/
│
├── scripts/
│   └── build-docs.ps1
│
├── build/
│   ├── html/
│   └── pdf/
│
└── README.md
```

This scales better as documentation grows.

------------------------------------------------------------------------

# 74. Relationship to CHM Documentation

The same source content can conceptually support multiple documentation
outputs:

``` text
Markdown / HTML source
         │
         ├──────── HTML website
         │
         ├──────── PDF
         │
         └──────── CHM workflow
```

However, CHM has its own project files and compilation requirements,
including `.hhp`, `.hhc`, `.hhk`, and `hhc.exe`.

A shared source strategy should therefore be designed carefully rather
than assuming every output format behaves identically.

------------------------------------------------------------------------

# 75. One Source, Multiple Outputs

The long-term goal can be:

``` text
Documentation Source
        │
        ├──────── HTML
        │
        ├──────── PDF
        │
        └──────── CHM
```

This reduces duplicated writing.

The source format and build scripts become the authoritative
documentation system.

------------------------------------------------------------------------

# 76. Recommended Master Format

For coding guides and technical notes, Markdown is a strong master
format because:

``` text
Readable without special software
Easy to edit offline
Works well with Git
Supports code blocks
Supports headings
Supports tables
Converts well to HTML
Can participate in PDF workflows
```

------------------------------------------------------------------------

# 77. Recommended Build Policy

Use:

``` text
*.md
    ↓
Source / master documentation

*.html
    ↓
Generated browser documentation

*.pdf
    ↓
Generated printable documentation
```

This keeps responsibilities clear.

------------------------------------------------------------------------

# 78. Recommended Offline Workflow

``` text
Internet-connected preparation computer
             │
             ├── Download Pandoc installer
             ├── Prepare Edge/Chrome if required
             └── Prepare project files
             │
             ▼
         Transfer tools
             │
             ▼
Offline development computer
             │
             ├── Install Pandoc
             ├── Use local browser
             ├── Edit Markdown
             ├── Run PowerShell build
             └── Generate HTML/PDF
```

No online conversion service is required.

------------------------------------------------------------------------

# 79. Avoid Online Markdown-to-PDF Services for Internal Documentation

For internal or confidential documentation, a local workflow has an
important advantage:

``` text
Documentation
     │
     ▼
Local machine
     │
     ├── Pandoc
     └── Browser
     │
     ▼
Output
```

The document does not need to be uploaded to a third-party conversion
website.

------------------------------------------------------------------------

# 80. Recommended Security Practice

For confidential documentation:

``` text
Use local conversion tools
Avoid uploading documents to unknown websites
Keep build tools from trusted sources
Verify installers before offline distribution
Control access to generated PDF/HTML files
```

Document conversion itself does not replace normal file-access controls.

------------------------------------------------------------------------

# 81. Example Single-Document Command

A practical HTML command:

``` powershell
pandoc document.md `
    --standalone `
    --toc `
    --toc-depth=3 `
    --css style.css `
    -o document.html
```

Then:

``` powershell
$htmlUri = ([System.Uri]"C:\docs\document.html").AbsoluteUri

& "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" `
    --headless `
    --disable-gpu `
    "--print-to-pdf=C:\docs\document.pdf" `
    $htmlUri
```

------------------------------------------------------------------------

# 82. Recommended Final Architecture

``` text
                     DOCUMENTATION PROJECT
                              │
                              ▼
                       Markdown Source
                              │
                    ┌─────────┴─────────┐
                    │                   │
                 Metadata            Images
                    │                   │
                    └─────────┬─────────┘
                              ▼
                           Pandoc
                              │
                              ▼
                       Standalone HTML
                              │
                              ├──── Documentation CSS
                              │
                              ▼
                     Browser Preview
                              │
                              ▼
                    Edge/Chrome Headless
                              │
                              ▼
                             PDF
```

------------------------------------------------------------------------

# 83. Recommended Tool Responsibilities

``` text
Markdown
    Content

Pandoc
    Document conversion

CSS
    Appearance

Edge / Chrome
    Browser rendering and PDF printing

PowerShell
    Build automation

Git
    Source history
```

Keeping responsibilities separate makes the system easier to maintain.

------------------------------------------------------------------------

# 84. Recommended Development Rules

1.  Treat Markdown as the master source.
2.  Do not manually edit generated HTML.
3.  Do not manually edit generated PDF.
4.  Keep CSS in a shared assets directory.
5.  Keep all required assets local for offline builds.
6.  Verify Pandoc before starting the build.
7.  Verify the browser executable before PDF generation.
8.  Verify every expected output file.
9.  Keep source and generated files in separate directories.
10. Test HTML before debugging PDF output.
11. Keep filenames predictable.
12. Use UTF-8.
13. Keep build scripts in source control.
14. Avoid unnecessary online conversion services.
15. Automate repeated conversion work with PowerShell.

------------------------------------------------------------------------

# 85. Final Recommendation

For an offline Windows documentation environment, use:

``` text
Markdown
    ↓
Pandoc
    ↓
Standalone HTML
    ↓
Shared CSS
    ↓
Microsoft Edge / Chrome
    ↓
PDF
```

Recommended project structure:

``` text
docs/
├── src/
├── assets/
├── scripts/
├── html/
└── pdf/
```

The key principle is:

> **Write and maintain the documentation once in Markdown, then generate
> HTML and PDF automatically.**

This avoids maintaining separate copies of the same documentation and
provides a clean path for future automation.
