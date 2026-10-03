# Froala WYSIWYG Editor 5.4.0 Integration Guide for Zend Framework 1

## 1. Purpose

This guide summarizes our planned integration of **Froala WYSIWYG Editor 5.4.0** into your **Zend Framework 1 (ZF1) + DHTMLX 3.5 + PHP 7.4 + MariaDB** application.

The goals are to use local Froala files for offline operation, integrate rich HTML editing without replacing DHTMLX, save/load HTML through ZF1, customize the editor, add template variables and programming-code snippets, and securely upload images.

## 2. Architecture

```text
DHTMLX / Existing Page
        |
        v
Froala Editor 5.4.0
        |
        +-- Rich HTML
        +-- Links / Tables
        +-- Code snippets
        +-- Template variables
        +-- Images
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
        +-- Business rules
        +-- HTML sanitization
        +-- Image handling
        |
        v
DbTable / Repository
        |
        v
MariaDB / File Storage
```

**Froala controls the editing experience; ZF1 controls trust, security, validation, permissions, persistence, and file management.**

## 3. Recommended Project Structure

```text
myproject/
├── application/
│   └── modules/
│       └── email/
│           ├── controllers/
│           │   ├── IndexController.php
│           │   └── ImageController.php
│           ├── models/DbTable/Template.php
│           ├── services/Template.php
│           └── views/scripts/index/editor.phtml
└── public/
    ├── vendor/froala/          # Do not modify vendor files
    ├── js/froala/
    │   ├── config.js
    │   └── code-snippet.js
    ├── css/froala/
    │   ├── froala-custom.css
    │   └── code-snippet.css
    └── uploads/editor/
```

Keep all custom JavaScript and CSS outside the Froala vendor directory so upgrades remain manageable.

## 4. Basic Offline Setup

Copy the licensed Froala distribution to `public/vendor/froala/` and load it locally:

```html
<link rel="stylesheet" href="/vendor/froala/css/froala_editor.pkgd.min.css">
<textarea id="email-editor"></textarea>
<script src="/vendor/froala/js/froala_editor.pkgd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new FroalaEditor('#email-editor', {
        heightMin: 300
    });
});
</script>
```

## 5. Loading and Reading HTML

Do not concatenate database HTML directly into a JavaScript string. JSON-encode it:

```php
<script>
var initialContent = <?php echo Zend_Json::encode($this->content); ?>;
</script>
```

Initialize the editor:

```javascript
var emailEditor = new FroalaEditor('#email-editor', {
    heightMin: 400
}, function () {
    this.html.set(initialContent);
});
```

Read the current content with:

```javascript
var html = emailEditor.html.get();
```

## 6. MariaDB Storage

```sql
CREATE TABLE email_template (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 7. ZF1 DbTable and Service Layer

```php
<?php
class Email_Model_DbTable_Template extends Zend_Db_Table_Abstract
{
    protected $_name = 'email_template';
    protected $_primary = 'id';
}
```

Keep database/business logic in a service rather than directly in the controller. This also helps a future ZF1-to-Laminas migration.

## 8. AJAX Save Flow

```text
Froala -> editor.html.get()
        -> JavaScript POST + CSRF
        -> ZF1 Controller
        -> Authentication / Authorization / CSRF / Validation
        -> HTML Sanitizer
        -> Service
        -> MariaDB
```

A legacy-friendly request can use `XMLHttpRequest` and send `id`, `name`, `subject`, `content`, and `csrf_token` to `/email/index/save`.

A successful response can be:

```json
{
    "success": true,
    "id": 15
}
```

## 9. CSRF and Multiple Browser Tabs

Continue using the stable per-session CSRF design:

```text
Session
   +-- CSRF token X
          +-- Tab A -> X
          +-- Tab B -> X
          +-- Tab C -> X
```

Do not rotate the token after every editor save if that would invalidate other open tabs. CSRF does not replace authentication or authorization.

## 10. Server-Side HTML Sanitization

Never trust HTML simply because Froala generated it. A malicious client can bypass Froala and POST arbitrary HTML.

Use a real HTML sanitizer with an explicit allow-list. Typical permitted elements may include `p`, `strong`, `em`, `ul`, `li`, controlled tables/links, and `pre`/`code` for source snippets. Reject `script`, dangerous attributes, and dangerous URL protocols.

Do not try to secure rich HTML using only a regular expression that removes `<script>`.

# Froala Customization

## 11. Controlled Toolbar

```javascript
var emailEditor = new FroalaEditor('#email-editor', {
    heightMin: 400,
    heightMax: 700,

    toolbarButtons: [
        'undo', 'redo', '|',
        'bold', 'italic', 'underline', '|',
        'fontFamily', 'fontSize', 'textColor', 'backgroundColor', '|',
        'paragraphFormat', 'alignLeft', 'alignCenter', 'alignRight', '|',
        'formatOL', 'formatUL', '|',
        'insertLink', 'insertImage', 'insertTable', '|',
        'html'
    ],

    paragraphFormat: {
        N: 'Normal',
        H2: 'Heading',
        H3: 'Subheading'
    },

    fontFamily: {
        'Arial,Helvetica,sans-serif': 'Arial',
        'Verdana,Geneva,sans-serif': 'Verdana',
        'Tahoma,Geneva,sans-serif': 'Tahoma',
        "'Times New Roman',Times,serif": 'Times New Roman'
    },

    fontSize: ['10', '12', '14', '16', '18', '20', '24', '28', '32'],

    events: {
        'initialized': function () {
            this.html.set(initialContent);
        },
        'contentChanged': function () {
            window.emailTemplateChanged = true;
        }
    }
});
```

A smaller, controlled toolbar is preferable for standardized business content.

## 12. Custom CSS

Create `public/css/froala/froala-custom.css`:

```css
.email-editor-container {
    max-width: 900px;
}

.email-editor-container .fr-box {
    font-family: Arial, Helvetica, sans-serif;
}

.email-editor-container .fr-element {
    min-height: 400px;
    font-size: 14px;
    line-height: 1.6;
}
```

Load this after Froala CSS. Do not directly edit `froala_editor.pkgd.min.css`.

# Template Variables

## 13. Variable Insertion

Useful variables can include:

```text
{{customer_name}}
{{customer_email}}
{{company_name}}
{{company_address}}
{{document_number}}
{{document_date}}
{{current_date}}
```

Example:

```html
<p>Dear {{customer_name}},</p>
<p>Your document <strong>{{document_number}}</strong> is now available.</p>
<p>Regards,<br>{{company_name}}</p>
```

Recommended custom UI:

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

# Programming Code Snippets

## 14. PHP and Other Code

PHP inserted into Froala must be displayed as source code, never executed.

Desired source:

```php
<?php
class EmailService
{
    public function send($email)
    {
        return true;
    }
}
```

Store it as escaped HTML:

```html
<pre><code class="language-php">&lt;?php
class EmailService
{
    // ...
}
</code></pre>
```

## 15. Custom PHP Command

```javascript
FroalaEditor.DefineIcon('phpCode', {
    NAME: 'code',
    SVG_KEY: 'codeView'
});

FroalaEditor.RegisterCommand('phpCode', {
    title: 'Insert PHP Code',
    icon: 'phpCode',
    focus: true,
    undo: true,
    refreshAfterCallback: true,

    callback: function () {
        var code = "<?php\n\nclass Example\n{\n    public function test()\n    {\n        return true;\n    }\n}\n";

        var escaped = code
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        this.html.insert(
            '<pre><code class="language-php">' +
            escaped +
            '</code></pre><p><br></p>'
        );
    }
});
```

## 16. Recommended Multi-Language Code Snippet Feature

Prefer one reusable Code Snippet control:

```text
Code Snippet
├── PHP
├── JavaScript
├── HTML
├── CSS
├── SQL
├── JSON
├── PowerShell
└── Plain Text
```

Store the selected language using classes such as `language-php`, `language-javascript`, and `language-sql`. This also prepares the content for optional syntax highlighting later.

# Image Uploads

## 17. Upload Architecture

```text
User selects/pastes/drops image
          |
          v
       Froala
          |
          v
POST /email/image/upload
          |
          v
ZF1 ImageController
          |
          +-- Authentication
          +-- Authorization
          +-- CSRF
          +-- Upload error check
          +-- Size validation
          +-- Actual MIME validation
          +-- Actual image validation
          +-- Random filename
          +-- Store image
          |
          v
Image Storage
          |
          v
{"link":"/uploads/editor/random.jpg"}
          |
          v
       Froala
```

## 18. Froala Image Configuration

```javascript
var emailEditor = new FroalaEditor('#email-editor', {
    heightMin: 400,

    toolbarButtons: [
        'undo', 'redo', '|',
        'bold', 'italic', 'underline', '|',
        'insertLink', 'insertImage', 'insertTable', '|',
        'html'
    ],

    imageUpload: true,
    imageUploadURL: '/email/image/upload',
    imageUploadMethod: 'POST',
    imageMaxSize: 5 * 1024 * 1024,
    imageAllowedTypes: ['jpeg', 'jpg', 'png', 'gif', 'webp'],

    imageUploadParams: {
        csrf_token: window.csrfToken
    },

    events: {
        'image.uploaded': function (response) {
            console.log('Image uploaded.');
        },
        'image.error': function (error, response) {
            console.error('Image upload failed:', error, response);
        }
    }
});
```

Client-side restrictions improve usability but are not security controls by themselves.

## 19. ZF1 Upload Endpoint

Suggested endpoint:

```text
POST /email/image/upload
```

Basic controller checks:

```php
<?php
class Email_ImageController extends Zend_Controller_Action
{
    public function uploadAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        if (!$this->getRequest()->isPost()) {
            return $this->_jsonError('Invalid request method.', 405);
        }

        if (!$this->_isAuthorized()) {
            return $this->_jsonError('Access denied.', 403);
        }

        $csrfToken = (string) $this->_getParam('csrf_token', '');

        if (!$this->_validateCsrfToken($csrfToken)) {
            return $this->_jsonError('Invalid CSRF token.', 403);
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return $this->_jsonError('No image was uploaded.', 400);
        }

        $file = $_FILES['file'];
    }
}
```

## 20. Validate Upload Status and Size

```php
if ($file['error'] !== UPLOAD_ERR_OK) {
    return $this->_jsonError('Image upload failed.', 400);
}

if (!is_uploaded_file($file['tmp_name'])) {
    return $this->_jsonError('Invalid uploaded file.', 400);
}

$maxSize = 5 * 1024 * 1024;

if ($file['size'] <= 0 || $file['size'] > $maxSize) {
    return $this->_jsonError('Image must be 5 MB or smaller.', 400);
}
```

PHP configuration example:

```ini
file_uploads = On
upload_max_filesize = 5M
post_max_size = 6M
```

## 21. Validate Actual File Type

Do not trust the browser MIME type or filename extension.

```php
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedTypes = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
);

if (!isset($allowedTypes[$mimeType])) {
    return $this->_jsonError('Unsupported image type.', 400);
}

$imageInfo = @getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    return $this->_jsonError('Invalid image file.', 400);
}

$extension = $allowedTypes[$mimeType];
```

## 22. Generate a Server-Controlled Filename

```php
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
```

Never use the client-supplied filename as the storage path.

## 23. Save the Image

For ordinary public documentation images:

```php
$uploadDirectory = APPLICATION_PATH . '/../public/uploads/editor';

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0755, true)) {
        return $this->_jsonError('Upload directory is unavailable.', 500);
    }
}

$destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    return $this->_jsonError('Unable to save image.', 500);
}
```

For private/security-sensitive content, store images outside the web root and serve them through an authenticated and authorized endpoint.

## 24. Froala Upload Response

```php
return $this->_json(array(
    'link' => '/uploads/editor/' . $filename,
));
```

Example:

```json
{
    "link": "/uploads/editor/4c1ea37c85590144af56a77361db584f.jpg"
}
```

## 25. Protect the Upload Directory

If public storage is used, uploaded content must not be executable as PHP.

```apache
<Directory "C:/sites/myproject/public/uploads">
    Options -Indexes

    <FilesMatch "\.(php|php3|php4|php5|php7|php8|phtml|phar)$">
        Require all denied
    </FilesMatch>
</Directory>
```

A stricter configuration that only serves explicitly allowed image formats is preferable.

## 26. Image Deletion and Tracking

Use a controlled deletion endpoint such as:

```text
POST /email/image/delete
```

Before deletion, verify authentication, authorization, CSRF, image identity, ownership/scope, and whether the image is still referenced. Never accept an arbitrary filesystem path from the browser.

Recommended conceptual table:

```text
editor_image
────────────────────────────────
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

Database tracking improves cleanup, authorization, auditing, and future migration.

# Security Checklist

## 27. Rich HTML

- Require authentication.
- Check authorization for the specific template/document.
- Validate CSRF for state-changing requests.
- Limit request size.
- Sanitize HTML on the server.
- Use an explicit allow-list of elements and attributes.
- Validate link/image URL protocols.
- Never execute inserted code snippets.
- Never trust a template ID merely because it came from the browser.

## 28. Image Uploads

- Require authentication and authorization.
- Validate CSRF.
- Check `UPLOAD_ERR_OK`.
- Check `is_uploaded_file()`.
- Enforce server-side size limits.
- Inspect actual MIME type with Fileinfo.
- Verify actual image content.
- Allow only required image formats.
- Generate the server filename.
- Prevent script execution in upload directories.
- Keep sensitive images outside the public web root.
- Track images in MariaDB where practical.
- Re-check authorization before deletion.

# Recommended Implementation Order

```text
1. Local Froala 5.4.0 assets
        |
2. Basic editor + controlled toolbar
        |
3. Load/save through ZF1 + MariaDB
        |
4. Authentication + Authorization + CSRF
        |
5. Server-side HTML sanitization
        |
6. Custom CSS + editor events
        |
7. Template Variables feature
        |
8. Multi-language Code Snippet feature
        |
9. Secure image upload
        |
10. Image DB tracking / deletion / cleanup
```

## Final Principle

> **Froala controls editing; ZF1 controls trust and security.**

This separation supports your offline development requirements, keeps the implementation maintainable, and makes a later ZF1-to-Laminas migration easier.
