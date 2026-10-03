# TinyMCE WYSIWYG Editor Integration Guide for ZF1

## 1. Project Context and Goals

This document summarizes the planned use of **TinyMCE** in an existing **Zend Framework 1 (ZF1) + DHTMLX 3.5 + PHP 7.4 + MariaDB** project.

The main goals are:

- Self-host TinyMCE so it can work in an offline environment.
- Keep TinyMCE as the rich HTML editor while ZF1 remains responsible for security and persistence.
- Customize toolbar buttons and menus.
- Add a reusable multi-language **Code Snippet** feature.
- Add reusable template-variable insertion.
- Support secure image uploads through ZF1.
- Store sanitized HTML as the master document format.
- Use the stored HTML as the basis for HTML, PDF, Word, CHM, and related outputs.
- Export structured/table data to Excel when appropriate.

---

## 2. Recommended Architecture

```text
DHTMLX / Existing Page
        |
        v
TinyMCE
        |
        +-- Rich HTML
        +-- Images
        +-- Links
        +-- Tables
        +-- Code snippets
        +-- Template variables
        |
        v
JavaScript / AJAX
        |
        +-- Session
        +-- CSRF token
        |
        v
ZF1 Controller
        |
        +-- Authentication
        +-- Authorization
        +-- CSRF validation
        +-- Request validation
        |
        v
Service Layer
        |
        +-- HTML sanitization
        +-- Business rules
        +-- Image handling
        |
        v
MariaDB / File Storage
```

The core design principle is:

> **TinyMCE controls editing; ZF1 controls trust, security, validation, persistence, and file management.**

---

## 3. Self-Hosted / Offline Installation

For an offline-capable application, keep TinyMCE locally instead of depending on a CDN.

Recommended structure:

```text
public/
├── vendor/
│   └── tinymce/
│       ├── tinymce.min.js
│       ├── icons/
│       ├── models/
│       ├── plugins/
│       ├── skins/
│       └── themes/
│
├── js/
│   └── tinymce/
│       ├── config.js
│       ├── code-snippet.js
│       └── variables.js
│
└── css/
    └── tinymce/
        └── content.css
```

Keep the vendor directory unchanged. Put project-specific code in separate JavaScript and CSS files so TinyMCE can be upgraded more easily.

TinyMCE 8 self-hosted deployments require an appropriate license configuration. Use the license mode/key that applies to the project rather than hard-coding a value without checking the applicable license.

---

## 4. Basic TinyMCE Setup

ZF1 view example:

```html
<textarea id="html-editor" name="content"></textarea>

<script src="/vendor/tinymce/tinymce.min.js"></script>

<script>
tinymce.init({
    selector: '#html-editor',
    height: 500,

    plugins: [
        'lists',
        'link',
        'image',
        'table',
        'code',
        'fullscreen',
        'searchreplace',
        'visualblocks'
    ],

    toolbar:
        'undo redo | ' +
        'blocks | ' +
        'bold italic underline | ' +
        'alignleft aligncenter alignright | ' +
        'bullist numlist | ' +
        'link image table | ' +
        'code fullscreen'
});
</script>
```

Get editor HTML:

```javascript
var content = tinymce
    .get('html-editor')
    .getContent();
```

Set existing HTML:

```javascript
tinymce
    .get('html-editor')
    .setContent(initialContent);
```

When passing existing HTML from PHP to JavaScript, JSON-encode it rather than concatenating it directly into a quoted JavaScript string.

---

## 5. ZF1 Save Architecture

```text
TinyMCE
   |
   | getContent()
   v
AJAX + CSRF
   |
   v
ZF1 Controller
   |
   +-- Authentication
   +-- Authorization
   +-- CSRF
   +-- Request validation
   +-- HTML sanitization
   |
   v
Service
   |
   v
MariaDB
```

Do not trust HTML simply because TinyMCE generated it. A client can bypass the editor and submit arbitrary HTML directly to the endpoint.

The server should use a proper HTML sanitizer with an explicit allow-list of elements, attributes, and URL protocols.

---

## 6. Toolbar Customization

TinyMCE lets the application choose which toolbar controls appear and in which order.

A useful layout for this project is:

```text
Undo/Redo | Format | Font | Size | Bold/Italic/Underline | Color
Align | Lists | Link | Image | Table
Code Snippet | Variables | Source | Fullscreen
```

Example multi-row toolbar:

```javascript
tinymce.init({
    selector: '#html-editor',
    height: 600,

    plugins: [
        'lists',
        'link',
        'image',
        'table',
        'code',
        'fullscreen',
        'searchreplace',
        'visualblocks'
    ],

    toolbar: [
        'undo redo | blocks fontfamily fontsize | bold italic underline',
        'forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist',
        'link image table | codesnippet variables | code fullscreen'
    ],

    setup: function (editor) {
        registerCodeSnippetButton(editor);
        registerVariablesButton(editor);
    }
});
```

### Custom Button

```javascript
function registerCustomButtons(editor)
{
    editor.ui.registry.addButton('mybutton', {
        text: 'My Button',
        tooltip: 'My custom function',

        onAction: function () {
            editor.insertContent(
                '<strong>Hello World</strong>'
            );
        }
    });
}
```

Then include `mybutton` in the toolbar definition.

### Custom Icon Button

```javascript
editor.ui.registry.addButton('insertcode', {
    icon: 'sourcecode',
    tooltip: 'Insert Code Snippet',

    onAction: function () {
        openCodeSnippetDialog(editor);
    }
});
```

---

## 7. Multi-Language Code Snippet Feature

The recommended design is one **Code Snippet** menu rather than a separate toolbar button for every programming language.

```text
Code Snippet
├── Web
│   ├── HTML
│   ├── CSS
│   ├── JavaScript
│   └── TypeScript
├── Backend
│   ├── PHP
│   ├── Python
│   └── Java
├── C / C++
│   ├── C
│   └── C++
├── Data
│   ├── JSON
│   ├── XML
│   └── SQL
├── Shell
│   ├── PowerShell
│   └── Bash / Shell
└── Plain Text
```

Additional languages can be added later without changing the storage format.

### Storage Format

Use a consistent semantic structure:

```html
<pre><code class="language-php">...</code></pre>
```

```html
<pre><code class="language-javascript">...</code></pre>
```

```html
<pre><code class="language-typescript">...</code></pre>
```

```html
<pre><code class="language-python">...</code></pre>
```

```html
<pre><code class="language-c">...</code></pre>
```

```html
<pre><code class="language-cpp">...</code></pre>
```

```html
<pre><code class="language-java">...</code></pre>
```

```html
<pre><code class="language-json">...</code></pre>
```

```html
<pre><code class="language-sql">...</code></pre>
```

```html
<pre><code class="language-powershell">...</code></pre>
```

Code must be escaped before insertion so `<`, `>`, and `&` are displayed as source code rather than interpreted as document markup.

### Escape Function

```javascript
function escapeCode(code)
{
    return code
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
```

### Simple Insertion Function

```javascript
function insertCodeSnippet(editor, language, code)
{
    var html =
        '<pre><code class="language-' +
        language +
        '">' +
        escapeCode(code) +
        '</code></pre>' +
        '<p><br></p>';

    editor.insertContent(html);
}
```

Never execute PHP, JavaScript, PowerShell, or other code entered through this documentation feature. It is display content only.

---

## 8. Recommended Code Snippet Dialog

Instead of `window.prompt()`, use a TinyMCE dialog:

```text
+-------------------------------------------------------+
| Insert Code Snippet                              X    |
+-------------------------------------------------------+
|                                                       |
| Language:  [ PHP                              v ]     |
|                                                       |
| Code:                                                 |
| +---------------------------------------------------+ |
| | <?php                                             | |
| |                                                   | |
| | class UserService                                 | |
| | {                                                 | |
| |     public function find($id)                     | |
| |     {                                             | |
| |         return $id;                               | |
| |     }                                             | |
| | }                                                 | |
| +---------------------------------------------------+ |
|                                                       |
|                         [ Cancel ] [ Insert ]          |
+-------------------------------------------------------+
```

A mature implementation should support:

- Selecting a language.
- Entering multi-line source code.
- Preserving whitespace and indentation.
- Inserting at the current cursor position.
- Editing an existing code block.
- Changing the language of an existing code block.
- Optional syntax highlighting.

The `<pre><code class="language-...">` structure works well with syntax-highlighting libraries such as Prism.js or highlight.js.

---

## 9. Custom Code Snippet Menu Button

Conceptual registration:

```javascript
editor.ui.registry.addMenuButton('codesnippet', {
    text: 'Code Snippet',
    tooltip: 'Insert source code',

    fetch: function (callback) {
        callback([
            {
                type: 'menuitem',
                text: 'HTML',
                onAction: function () {
                    openCodeDialog(editor, 'html');
                }
            },
            {
                type: 'menuitem',
                text: 'CSS',
                onAction: function () {
                    openCodeDialog(editor, 'css');
                }
            },
            {
                type: 'menuitem',
                text: 'JavaScript',
                onAction: function () {
                    openCodeDialog(editor, 'javascript');
                }
            },
            {
                type: 'menuitem',
                text: 'PHP',
                onAction: function () {
                    openCodeDialog(editor, 'php');
                }
            }
        ]);
    }
});
```

The complete implementation can generate these items from a single language configuration array rather than duplicating code.

---

## 10. Template Variables

A second custom menu can insert controlled template placeholders:

```text
Variables
├── Customer
│   ├── Customer Name
│   └── Customer Email
├── Company
│   ├── Company Name
│   └── Company Address
├── Document
│   ├── Document Number
│   └── Document Date
└── System
    └── Current Date
```

Examples:

```text
{{customer_name}}
{{customer_email}}
{{company_name}}
{{company_address}}
{{document_number}}
{{document_date}}
{{current_date}}
```

Example document:

```html
<p>Dear {{customer_name}},</p>

<p>
    Your document
    <strong>{{document_number}}</strong>
    is now available.
</p>

<p>
    Regards,<br>
    {{company_name}}
</p>
```

The backend should resolve only known/approved variables.

---

## 11. Image Uploads

TinyMCE can send image uploads to a ZF1 endpoint.

Conceptual configuration:

```javascript
tinymce.init({
    selector: '#html-editor',
    plugins: 'image',
    toolbar: 'image',
    images_upload_url: '/email/image/upload'
});
```

For production, the upload endpoint should additionally integrate authentication, authorization, CSRF, validation, and image tracking.

Recommended flow:

```text
TinyMCE
   |
   | multipart/form-data
   v
POST /email/image/upload
   |
   v
ZF1 Image Controller
   |
   +-- Authentication
   +-- Authorization
   +-- CSRF
   +-- Upload status validation
   +-- Size validation
   +-- Actual MIME validation
   +-- Actual image validation
   +-- Random server filename
   |
   v
Image Service
   |
   +-- Save file
   +-- Insert image DB record
   |
   v
Return image URL
   |
   v
TinyMCE inserts <img>
```

Do not trust the original filename or client-provided MIME type. For sensitive images, store files outside the public web root and serve them through an authenticated/authorized endpoint.

---

## 12. Image Lifecycle and Database Tracking

A useful image table can contain:

```text
editor_image
--------------------------------
id
filename
original_name
mime_type
file_size
user_id
template_id
created_at
deleted_at
```

Deleting an `<img>` from the editor should not blindly delete an arbitrary path supplied by the client. A controlled delete endpoint should verify ownership, authorization, CSRF, image identity, and whether the file is still referenced.

---

## 13. PDF Compatibility

TinyMCE is an HTML editor, not a PDF editor. PDF is therefore an **export/rendering target**.

Recommended architecture:

```text
TinyMCE
   |
   | HTML
   v
Sanitized HTML
   |
   v
MariaDB
   |
   v
HTML document/template
   |
   v
PDF rendering engine
   |
   v
document.pdf
```

Headings, paragraphs, tables, images, lists, links, and code snippets can all be represented naturally in the HTML source. The PDF renderer is responsible for translating that HTML/CSS into the final PDF.

Code blocks such as:

```html
<pre><code class="language-php">...</code></pre>
```

can also be styled for PDF output.

---

## 14. Microsoft Word Compatibility

TinyMCE does not directly edit `.docx`; Word should be treated as another export target:

```text
TinyMCE HTML
      |
      v
HTML -> DOCX conversion
      |
      v
manual.docx
```

Basic structures map reasonably well:

| TinyMCE / HTML | Word |
|---|---|
| Heading | Heading |
| Paragraph | Paragraph |
| Bold | Bold |
| Italic | Italic |
| Lists | Lists |
| Tables | Tables |
| Images | Images |
| Links | Hyperlinks |

Complex CSS may not map perfectly to Word. If DOCX export is important, use a controlled set of document styles rather than arbitrary HTML/CSS.

---

## 15. Microsoft Excel Compatibility

Excel is a spreadsheet rather than a rich-document format, so exporting an entire TinyMCE document to `.xlsx` is usually not the best design.

If the editor contains a table:

```html
<table>
    <tr>
        <th>Product</th>
        <th>Quantity</th>
        <th>Total</th>
    </tr>
</table>
```

it can conceptually be exported as spreadsheet data:

```text
TinyMCE document
   |
   +-- Paragraphs/text ---> HTML / PDF / Word
   |
   +-- Tables ------------> XLSX
```

If the values already originate from MariaDB, generate the Excel file directly from structured database data rather than parsing the values back out of TinyMCE HTML.

---

## 16. CHM and Email HTML

Because TinyMCE's native output is HTML, it also fits an HTML-based CHM documentation workflow well:

```text
TinyMCE
   |
   v
Sanitized HTML
   |
   +---> Browser HTML
   +---> CHM source
   +---> PDF source
   +---> DOCX conversion source
```

For email templates, normalize and sanitize the HTML according to the requirements of the target email environment. Email-client CSS support differs from normal browser support.

---

## 17. Recommended Master Format

Use **sanitized HTML as the master document format**:

```text
                         TinyMCE
                            |
                            v
                    Sanitized HTML
                            |
                            v
                         MariaDB
                            |
          +-----------------+-----------------+
          |                 |                 |
          v                 v                 v
        HTML               PDF               DOCX
          |
          v
         CHM

MariaDB structured/table data ----------------------> XLSX
```

This is cleaner than making PDF, DOCX, or XLSX the editable source format.

---

## 18. Recommended JavaScript Organization

```text
public/js/tinymce/
├── config.js
├── code-snippet.js
├── variables.js
└── image-upload.js
```

Responsibilities:

```text
config.js
    -> TinyMCE initialization
    -> Toolbar layout
    -> Standard plugins

code-snippet.js
    -> Language definitions
    -> Code dialog
    -> Code insertion/editing

variables.js
    -> Variable definitions
    -> Variables menu
    -> Placeholder insertion

image-upload.js
    -> Client-side upload integration
    -> Upload errors/events
```

This separation keeps the editor maintainable and avoids modifying TinyMCE vendor files.

---

## 19. Security Checklist

For editor saves:

- Require authentication.
- Verify authorization for the specific document/template.
- Validate CSRF for state-changing requests.
- Limit request size.
- Sanitize HTML server-side.
- Use an explicit allow-list of elements and attributes.
- Validate URL protocols.
- Do not trust record IDs supplied by the browser.
- Do not execute code snippets.

For image uploads:

- Require authentication and authorization.
- Validate CSRF.
- Check PHP upload status.
- Check `is_uploaded_file()`.
- Enforce server-side file-size limits.
- Inspect the actual MIME type.
- Verify actual image content.
- Allow only required image formats.
- Generate a server-controlled filename.
- Prevent script execution in upload directories.
- Keep sensitive files outside the public web root.
- Track uploaded files in MariaDB where practical.
- Re-check authorization before deletion.

---

## 20. Recommended Implementation Order

```text
1. Self-host TinyMCE locally
        |
2. Basic editor test
        |
3. Move settings to config.js
        |
4. Customize toolbar/buttons
        |
5. ZF1 load/save + MariaDB
        |
6. Authentication + Authorization + CSRF
        |
7. Server-side HTML sanitization
        |
8. Template Variables menu
        |
9. Multi-language Code Snippet dialog
        |
10. Syntax highlighting
        |
11. Secure image upload
        |
12. Image DB tracking/deletion/cleanup
        |
13. HTML/PDF/CHM export workflow
        |
14. DOCX export
        |
15. XLSX structured-data export
```

---

## 21. Final Recommended Design

```text
                         TinyMCE
                            |
        +-------------------+-------------------+
        |                   |                   |
 Standard Toolbar      Custom Menus        Rich Content
        |                   |                   |
 Bold/Italic          Code Snippet           Images
 Font/Size            Variables              Tables
 Alignment                                  Links
 Lists                                       HTML
        |                   |                   |
        +-------------------+-------------------+
                            |
                            v
                     JavaScript / AJAX
                            |
                       CSRF + Session
                            |
                            v
                       ZF1 Controllers
                            |
        +-------------------+-------------------+
        |                   |                   |
 Authentication       Authorization           CSRF
        |                   |                   |
        +-------------------+-------------------+
                            |
                            v
                       Service Layer
                            |
        +-------------------+-------------------+
        |                   |                   |
 HTML Sanitizer       Template Service     Image Service
        |                   |                   |
        +-------------------+-------------------+
                            |
                +-----------+-----------+
                |                       |
             MariaDB                File Storage
                |
                v
          Sanitized HTML Master
                |
       +--------+--------+--------+
       |        |        |        |
      HTML     PDF      DOCX      CHM

Structured MariaDB/table data ----------> XLSX
```

The recommended approach is to treat **TinyMCE as the HTML authoring interface** and **sanitized HTML as the canonical document representation**. ZF1 should remain responsible for security, business rules, persistence, image handling, and export orchestration.
