# GrapesJS Core — Detailed Reference Guide

**Audience:** PHP 7.4 / MariaDB developers building a fully offline visual page designer  
**Edition:** October 2026  
**Scope:** GrapesJS **Core**, not the commercial Grapes Studio SDK.

> **Version note:** GrapesJS APIs evolve. The examples use the modern Core API (`getProjectData`, `loadProjectData`, Pages, Components, etc.). Confirm options against the documentation for the exact `grapes.min.js` version installed. This guide is an independently written reference, not a copy of the official manual.

## Contents

1. What GrapesJS is and is not
2. Architecture and terminology
3. Offline installation
4. First editor
5. Editor initialization options
6. Components and component models
7. Block Manager
8. Component types and Traits
9. CSS, selectors and Style Manager
10. Canvas and device previews
11. Layer Manager and drag-and-drop
12. Asset Manager
13. Pages
14. Commands, keyboard shortcuts and events
15. Undo/redo and change tracking
16. Storage, project JSON and publishing
17. PHP 7.4 + MariaDB complete persistence example
18. Building Visual, Developer and Split modes
19. Templates, reusable components and design tokens
20. Import/export
21. Security and offline deployment
22. Troubleshooting
23. API cheat sheet
24. Learning exercises
25. Official references

---

## 1. What GrapesJS is and is not

GrapesJS is an embeddable JavaScript framework for visual editing of HTML/CSS-based templates. You embed it in your own application and control its interface, data storage, blocks, components and publishing process.

**GrapesJS Core provides** a visual canvas, components, blocks, CSS management, selectors, layers, assets, device previews, events, commands, undo/redo and project serialization.

**It does not automatically provide** your PHP API, MariaDB schema, user permissions, upload validation, server-side sanitization, business widgets, page routing or a finished Flatsome-like UI. Those are application responsibilities.

**Core vs Studio SDK:** GrapesJS Core is open-source; Grapes Studio SDK is a separate commercial offering. Do not assume SDK-specific APIs or UI are available in Core.

### Recommended design for your project

```
Browser (GrapesJS Core)
  ├─ Visual Builder UI
  ├─ Developer Designer UI
  ├─ Split code inspector
  ├─ Components / CSS / Assets
  └─ Project JSON
            │ HTTPS or trusted local HTTP
            ▼
PHP 7.4 REST API
  ├─ Validate request / CSRF
  ├─ Validate content and permissions
  ├─ Save editable JSON
  ├─ Save generated HTML/CSS
  ├─ Store assets
  └─ Manage page revisions
            │
            ▼
MariaDB
```

**Critical principle:** The canonical editable source is **GrapesJS project JSON**. Generated HTML and CSS are output artifacts for rendering/publishing, not an equivalent replacement for project JSON.

## 2. Architecture and terminology

| Term | Meaning | Typical API |
|---|---|---|
| Editor | Main GrapesJS instance | `grapesjs.init(...)` |
| Canvas | Visual rendering/editing area | `editor.Canvas` |
| Component | Structured HTML-like element model | `editor.Components` |
| Block | Palette item that inserts component content | `editor.Blocks` / `editor.BlockManager` |
| Trait | Editable component attribute/property | `editor.Traits` |
| Style Manager | UI for CSS properties | `editor.StyleManager` |
| CSS Composer | Underlying CSS rules | `editor.Css` |
| Selector Manager | CSS classes/selectors | `editor.Selectors` |
| Layers | Component hierarchy UI | `editor.Layers` |
| Assets | Images and other assets | `editor.Assets` |
| Devices | Responsive preview definitions | `editor.Devices` |
| Pages | Pages inside one GrapesJS project | `editor.Pages` |
| Storage | Save/load project state | `editor.Storage` |
| Commands | Reusable editor actions | `editor.Commands` |
| Undo Manager | Undo/redo changes | `editor.UndoManager` |

**Component model vs DOM:** The component model is the editable source of truth. Avoid directly editing DOM nodes inside the canvas iframe: such changes can disappear or fail to serialize. Use component APIs.

**Block vs Component:** A Block is a reusable palette entry; dropping it creates Components. Editing one dropped instance does not usually modify the original Block.

## 3. Offline installation

Download the prebuilt, version-matched Core distribution while online and copy it to the offline server:

```
project/
├── public/
│   ├── index.html
│   └── assets/
│       └── grapesjs/
│           ├── grapes.min.js
│           └── grapes.min.css
└── ...
```

The filenames/locations can differ in a published distribution; check the release package. **No npm or Node.js is needed to run the prebuilt browser bundle.** npm is useful if you decide to build GrapesJS or plugins from source.

**Offline audit:** Check HTML `<script>`, `<link>`, CSS `@import`, `url(...)`, plugin dependencies, canvas styles/scripts and image URLs. Disable remote fonts, icons, analytics, CDNs and third-party asset endpoints. Host all required dependencies locally.

### Minimum HTML page

```html
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Offline GrapesJS</title>
  <link rel="stylesheet" href="assets/grapesjs/grapes.min.css">
  <style>html,body{height:100%;margin:0}#gjs{height:100vh}</style>
</head>
<body>
  <div id="gjs"><h1>Welcome</h1></div>
  <script src="assets/grapesjs/grapes.min.js"></script>
  <script>
    var editor = grapesjs.init({
      container: '#gjs',
      fromElement: true,
      height: '100%',
      storageManager: false
    });
  </script>
</body>
</html>
```

If `grapesjs is not defined`, verify the path and inspect the browser Network/Console tabs.

## 4. First editor: step by step

1. Load the CSS and JavaScript locally.
2. Create a container element, such as `<div id="gjs"></div>`.
3. Call `grapesjs.init` once.
4. Add Blocks for the content your users should create.
5. Allow users to select Components and modify CSS/Traits.
6. Save project JSON to the server.
7. Generate HTML/CSS only for preview/publishing.

```javascript
var editor = grapesjs.init({
  container: '#gjs',
  height: '100vh',
  storageManager: false,
  components: '<section class="hero"><h1>Hello</h1><p>Edit me</p></section>',
  style: '.hero { padding: 48px; background: #f4f7fb; }'
});

console.log(editor.getHtml());
console.log(editor.getCss());
console.log(editor.getProjectData());
```

`getHtml()` and `getCss()` generate strings. `getProjectData()` returns a structured JavaScript object.

## 5. Editor initialization options

Common options (consult your installed version for the complete schema):

```javascript
var editor = grapesjs.init({
  container: '#gjs',
  height: '100%',
  width: 'auto',
  fromElement: false,
  components: '<main><h1>Page title</h1></main>',
  style: 'main{padding:24px}',
  storageManager: false,
  panels: { defaults: [] },
  deviceManager: {
    devices: [
      { name: 'Desktop', width: '' },
      { name: 'Tablet', width: '768px', widthMedia: '992px' },
      { name: 'Mobile', width: '375px', widthMedia: '767px' }
    ]
  }
});
```

- `container`: selector or DOM element that hosts the editor.
- `fromElement`: parse existing container HTML.
- `components`: initial component HTML or model data.
- `style`: initial CSS string or CSS rules.
- `storageManager`: built-in persistence configuration; `false` means the application controls saving.
- `panels`: configure/hide default editor toolbar panels.
- `deviceManager`: define device previews and media widths.

**Do not mix** initialization data with later `loadProjectData()` carelessly. For a persisted project, load its JSON after initializing the editor; the project replaces the relevant editor state.

## 6. Components and component models

A Component models a rendered element and its editable metadata.

### Add HTML/components

```javascript
editor.addComponents('<div class="notice">New notice</div>');

editor.addComponents({
  tagName: 'section',
  attributes: { class: 'feature-section' },
  components: [
    { tagName: 'h2', type: 'text', content: 'Feature title' },
    { tagName: 'p', type: 'text', content: 'Feature description' }
  ]
});
```

### Get and edit the selected component

```javascript
var selected = editor.getSelected();
if (selected) {
  selected.addAttributes({ title: 'Tooltip' });
  selected.addStyle({ padding: '20px', color: '#123456' });
  console.log(selected.toHTML());
}
```

### Work with component hierarchy

```javascript
var wrapper = editor.Components.getWrapper();
var matches = wrapper.find('.feature-section');
if (matches.length) {
  var section = matches[0];
  section.append('<p>Another paragraph</p>');
  editor.select(section);
}
```

### Remove / clone

```javascript
var selected = editor.getSelected();
if (selected) {
  var copy = selected.clone();
  selected.parent().append(copy);
  // To remove the original instead: selected.remove();
}
```

**Practical cautions:** Check `selected.parent()` exists. Restrict droppable/draggable targets when designing Sections → Rows → Columns. Avoid relying on unstable autogenerated component IDs as long-lived business identifiers.

## 7. Block Manager

Blocks define items visible in a palette. Their `content` is inserted into the canvas.

```javascript
editor.BlockManager.add('company-hero', {
  label: 'Company Hero',
  category: 'Sections',
  content: {
    tagName: 'section',
    attributes: { class: 'company-hero' },
    components: [
      { tagName: 'h1', type: 'text', content: 'Welcome' },
      { tagName: 'p', type: 'text', content: 'Company introduction' }
    ]
  }
});
```

Useful Block Manager calls:

```javascript
var block = editor.BlockManager.get('company-hero');
var allBlocks = editor.BlockManager.getAll();
editor.BlockManager.remove('company-hero');
```

**Design advice:** Create distinct block categories: Layout, Typography, Media, Buttons, Business Components and Templates. Keep Block definitions declarative, and register them before project loading if custom component types are involved.

## 8. Custom component types and Traits

Create a custom type when an element needs special behavior, identification or property controls.

```javascript
editor.DomComponents.addType('company-button', {
  isComponent: function(el) {
    if (el.tagName === 'A' && el.classList.contains('company-button')) {
      return { type: 'company-button' };
    }
  },
  model: {
    defaults: {
      tagName: 'a',
      attributes: { class: 'company-button', href: '#' },
      components: 'Click here',
      traits: [
        { type: 'text', name: 'href', label: 'URL' },
        { type: 'text', name: 'title', label: 'Tooltip' },
        { type: 'select', name: 'target', label: 'Target', options: [
          { id: '_self', label: 'Same tab' },
          { id: '_blank', label: 'New tab' }
        ] }
      ]
    }
  }
});

editor.BlockManager.add('company-button-block', {
  label: 'Company Button',
  category: 'Buttons',
  content: { type: 'company-button' }
});
```

**Traits vs CSS:** Traits commonly edit HTML attributes (`href`, `alt`, `title`, `placeholder`); the Style Manager edits CSS properties (`padding`, `color`, `display`). Traits can be extended to update custom component properties, but must be wired explicitly.

**Dynamic PHP/ZF1 widgets:** In the editor, show a safe placeholder with a widget identifier and allowlisted options. At render time, resolve that identifier on the server. Never accept arbitrary PHP code, filesystem paths or controller names from the browser.

## 9. CSS, selectors and Style Manager

GrapesJS can apply CSS to a component or a class/selector. Shared classes are preferable for reusable styles; direct per-component styles are convenient but may create many rules.

### Apply styles to a component

```javascript
var cmp = editor.getSelected();
if (cmp) {
  cmp.addStyle({
    display: 'flex',
    'justify-content': 'space-between',
    gap: '16px',
    padding: '24px'
  });
}
```

### CSS class assignment

```javascript
var cmp = editor.getSelected();
if (cmp) {
  cmp.addClass('feature-card');
}
```

### Configure a Style Manager

```javascript
var editor = grapesjs.init({
  container: '#gjs',
  storageManager: false,
  styleManager: {
    sectors: [
      {
        name: 'Layout',
        open: true,
        buildProps: ['display', 'position', 'width', 'height', 'margin', 'padding']
      },
      {
        name: 'Typography',
        open: false,
        buildProps: ['font-family', 'font-size', 'font-weight', 'line-height', 'color']
      },
      {
        name: 'Decoration',
        open: false,
        buildProps: ['background-color', 'border-radius', 'box-shadow']
      }
    ]
  }
});
```

Style Manager property configuration has changed over time; verify `buildProps` and property definitions for the installed Core version.

### Responsive CSS

Device preview width and CSS media rules are related but not identical. A preview can change canvas width without changing the final page's CSS. Ensure responsive edits create or modify the appropriate media rules.

```javascript
editor.setDevice('Mobile');
console.log('Active device:', editor.getDevice());
```

Use the built-in Style Manager's device-aware behavior where possible. If implementing your own responsive inspector, create/update media-scoped CSS rules explicitly; do not merely set inline component styles and assume they are mobile-only.

## 10. Canvas and device previews

The Canvas renders components inside its own document context (commonly an iframe). This is **not the same** as using iframes to compose your entire legacy application's UI.

```javascript
editor.setDevice('Desktop');
editor.setDevice('Tablet');
editor.setDevice('Mobile');

var canvas = editor.Canvas;
var frameDocument = canvas.getDocument();
```

Canvas scripts/styles must also be local for an offline deployment. The Canvas preview is not a security boundary for untrusted HTML/JS.

**Responsive testing:** Preview all three device widths, then inspect the published HTML/CSS in an ordinary browser viewport. Test overflow, font scaling, images and Grid/Flexbox breakpoints.

## 11. Layers and drag-and-drop

The Layer Manager displays the component tree. Example:

```
Page
└─ Section
   └─ Row
      ├─ Column
      │  ├─ Heading
      │  └─ Paragraph
      └─ Column
         └─ Image
```

The UI can select and reorder Components. For a Flatsome-style editor, enforce layout constraints:

- Sections contain Rows (and optional section-specific content).
- Rows contain Columns.
- Columns contain content elements and nested layout structures only when supported.
- Prevent invalid drops, such as placing a Row inside a Heading.

**Do not assume** every draggable element can be moved into every container. Define and test custom component `draggable`/`droppable` rules.

## 12. Asset Manager

GrapesJS Asset Manager can present images to the editor; your PHP API is responsible for upload, validation and storage.

```javascript
editor.AssetManager.add({
  src: '/uploads/example.jpg',
  name: 'Example image'
});

var assets = editor.AssetManager.getAll();
editor.AssetManager.open();
```

Typical upload architecture:

```
Browser file input
  → POST /api/assets
  → PHP validates size, MIME and decoded image
  → PHP generates a random safe filename
  → Store under restricted uploads folder
  → Return local URL
  → editor.AssetManager.add({ src: localUrl })
```

Recommended checks: enforce size limits, check MIME using `finfo`, validate image content (for raster formats), randomize filenames, reject executable formats, and prevent script execution in uploads. SVG requires separate sanitization or should be disabled. Uploaded image URLs should not point to untrusted external hosts.

## 13. Pages

GrapesJS Pages allow multiple pages inside **one editor project**. Your application may instead store one project per database page. Decide deliberately; the two models are not interchangeable.

```javascript
var pages = editor.Pages;

pages.add({
  id: 'about-page',
  name: 'About',
  component: '<main><h1>About us</h1></main>'
});

var about = pages.get('about-page');
if (about) pages.select(about);

var currentPage = pages.getSelected();
console.log(currentPage && currentPage.get('id'));
```

**Suggested approach for your current PHP project:** Keep **one MariaDB `pages` record per editable GrapesJS project** unless you intentionally need a multi-page project with shared assets/styles. This makes routing, permissions, revisions and publishing easier to reason about.

## 14. Commands, shortcuts and events

Commands encapsulate user actions and make toolbar buttons easier to maintain.

```javascript
editor.Commands.add('app:save', {
  run: function(ed) {
    console.log('Save project', ed.getProjectData());
  }
});

editor.runCommand('app:save');
```

Listen to events:

```javascript
editor.on('load', function() {
  console.log('Editor ready');
});

editor.on('component:selected', function(component) {
  console.log('Selected:', component.get('type'));
});

editor.on('component:add', function(component) {
  console.log('Added:', component.get('type'));
});

editor.on('component:remove', function(component) {
  console.log('Removed:', component.get('type'));
});

editor.on('update', function() {
  console.log('Project changed');
});
```

Common events include `load`, `update`, `component:add`, `component:remove`, `component:selected`, `component:update`, `style:...` and `storage:...`. Check event signatures against your installed version.

### Keyboard shortcuts

```javascript
editor.Keymaps.add('app:save', 'ctrl+s', 'app:save');
```

On macOS, also consider the Command/Meta key. Browser-reserved shortcuts can behave differently; test them in target browsers.

## 15. Undo/redo and change tracking

```javascript
editor.UndoManager.undo();
editor.UndoManager.redo();

console.log('Unsaved changes:', editor.getDirtyCount());
editor.clearDirtyCount();
```

Only call `clearDirtyCount()` after a confirmed successful save. An undo stack is **not** a substitute for durable MariaDB page revisions. Reloading the browser may lose in-memory undo history.

## 16. Storage, project JSON and publishing

### Correct persistence model

```
Editable JSON → save/load → GrapesJS editor
HTML + CSS   → publish   → browser rendering
```

```javascript
var projectData = editor.getProjectData();
var html = editor.getHtml();
var css = editor.getCss();

// Later, after retrieving JSON from the server:
editor.loadProjectData(projectData);
```

**Why not store only HTML/CSS?** Component type metadata, page state, editor-specific attributes and other structured information can be lost when reparsing exported HTML/CSS.

### Built-in Storage Manager vs manual API

- `storageManager: false`: Your code explicitly sends/receives project JSON. Often easiest with a legacy PHP backend.
- `storageManager` with `type: 'remote'`: GrapesJS handles remote store/load calls, but you still implement the backend and account for version-specific request/response contracts.

Manual persistence is used below because it is explicit and straightforward to debug.

## 17. PHP 7.4 + MariaDB persistence example

This is a **small standalone educational implementation**, not a production-ready application. It uses one page per project and includes a simple CSRF token. Since your prototype has no login, **restrict it to localhost or an access-controlled intranet**. CSRF does not replace authentication or authorization.

### 17.1 Schema (`schema.sql`)

```sql
CREATE DATABASE IF NOT EXISTS visual_designer
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE visual_designer;

CREATE TABLE IF NOT EXISTS pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  project_json LONGTEXT NOT NULL,
  html LONGTEXT NOT NULL,
  css LONGTEXT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

This example schema is independent of your V11 project's existing `schema.sql`; **do not import it over V11 without checking the actual schema**.

### 17.2 Connection (`db.php`)

```php
<?php
// PHP 7.4
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=visual_designer;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
```

### 17.3 Save API (`save.php`)

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 2 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['project']) || !is_array($data['project'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project data']);
    exit;
}

$title = isset($data['title']) ? trim((string)$data['title']) : 'Untitled';
$slug = isset($data['slug']) ? trim((string)$data['slug']) : '';
if ($title === '' || strlen($title) > 200 || !preg_match('/^[a-z0-9-]{1,200}$/', $slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid title or slug']);
    exit;
}

$project = json_encode($data['project'], JSON_UNESCAPED_UNICODE);
if ($project === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Project encoding failed']);
    exit;
}

// For demonstration, HTML/CSS are saved as drafts, NOT published directly.
// A real publishing endpoint must validate/sanitize these outputs.
$html = isset($data['html']) ? (string)$data['html'] : '';
$css = isset($data['css']) ? (string)$data['css'] : '';
if (strlen($html) > 2 * 1024 * 1024 || strlen($css) > 2 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Generated output too large']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO pages
      (title, slug, project_json, html, css)
      VALUES (:title, :slug, :project, :html, :css)
      ON DUPLICATE KEY UPDATE
      title = VALUES(title),
      project_json = VALUES(project_json),
      html = VALUES(html),
      css = VALUES(css)');
    $stmt->execute([
        ':title' => $title,
        ':slug' => $slug,
        ':project' => $project,
        ':html' => $html,
        ':css' => $css,
    ]);
    echo json_encode(['ok' => true, 'slug' => $slug]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database operation failed']);
}
```

**Security:** This API has no user identity and allows updating a page by slug. It is appropriate only for a strictly restricted development environment. Add authentication/authorization before exposing it to other users. Avoid logging full project payloads or database credentials.

### 17.4 Load API (`load.php`)

```php
<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/db.php';

$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
if (!preg_match('/^[a-z0-9-]{1,200}$/', $slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid slug']);
    exit;
}

$stmt = $pdo->prepare('SELECT title, project_json FROM pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Page not found']);
    exit;
}

$project = json_decode($row['project_json'], true);
if (!is_array($project)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stored project is invalid']);
    exit;
}

echo json_encode(['title' => $row['title'], 'project' => $project]);
```

### 17.5 Browser save/load

Generate a session CSRF token in the PHP page that hosts the editor:

```php
<?php
session_start();
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<script>
var csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
</script>
```

Browser code:

```javascript
function savePage(slug, title) {
  return fetch('save.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({
      slug: slug,
      title: title,
      project: editor.getProjectData(),
      html: editor.getHtml(),
      css: editor.getCss()
    })
  }).then(function(response) {
    if (!response.ok) throw new Error('Save failed: HTTP ' + response.status);
    return response.json();
  }).then(function(result) {
    if (result.ok) editor.clearDirtyCount();
    return result;
  });
}

function loadPage(slug) {
  return fetch('load.php?slug=' + encodeURIComponent(slug), {
    credentials: 'same-origin'
  }).then(function(response) {
    if (!response.ok) throw new Error('Load failed: HTTP ' + response.status);
    return response.json();
  }).then(function(result) {
    editor.loadProjectData(result.project);
    return result;
  });
}
```

`fetch()` is supported by modern browsers. For older browsers such as Chrome 49/Firefox 52, check polyfill needs and avoid newer JavaScript syntax; ES5 examples are used here for broad compatibility.

### 17.6 Publishing strategy

Never treat draft HTML/CSS from a browser as automatically safe. Implement a separate publishing operation that:

1. Validates the caller's authorization (or restricts access at the network layer for your no-login prototype).
2. Parses and sanitizes HTML using an allowlist policy.
3. Validates CSS and URL-bearing properties; block unsafe/remote URLs as required.
4. Validates local asset references and resolves approved dynamic widgets.
5. Writes a revision and marks the page as published transactionally.
6. Serves the published output with a suitable CSP and other headers.

For simple trusted content, generated HTML/CSS can be placed in a controlled page template. For untrusted content, use an appropriate sanitizer and isolation strategy; escaping the entire HTML would destroy the intended page structure, while blindly echoing it is unsafe.

## 18. Building Visual, Developer and Split modes

Use **one editor instance** and change only the surrounding UI:

```
Visual Builder:
[Blocks] [Canvas] [Contextual Inspector]

Developer Designer:
[Layers] [Canvas] [CSS / Traits / Selectors]

Split Designer:
[Canvas] [Read-only HTML/CSS inspection]
```

```javascript
var mode = 'visual';

function switchMode(nextMode) {
  if (['visual', 'developer', 'split'].indexOf(nextMode) === -1) return;
  mode = nextMode;
  document.body.setAttribute('data-editor-mode', mode);
  if (mode === 'split') {
    document.getElementById('html-output').value = editor.getHtml();
    document.getElementById('css-output').value = editor.getCss();
  }
}

editor.on('update', function() {
  if (mode === 'split') {
    document.getElementById('html-output').value = editor.getHtml();
    document.getElementById('css-output').value = editor.getCss();
  }
});
```

**Why read-only code inspection first?** Replacing the editor state with arbitrary edited HTML/CSS may lose custom component metadata, undo state and structured project information. A safe two-way source editor needs a controlled parser/import workflow.

## 19. Templates, reusable components and design tokens

**Templates:** Store full `project_json` for whole-page templates. For section templates, prefer storing structured component data plus explicitly scoped CSS dependencies; copying all page CSS can introduce unrelated rules and conflicts.

**Reusable components:** Decide whether inserts are independent copies or linked instances. GrapesJS Blocks usually produce independent copies. Linked components need additional application-level identifiers, versioning and update rules.

**Design tokens:** Define CSS variables for colors, fonts and spacing:

```css
:root {
  --brand-primary: #2563eb;
  --brand-text: #172033;
  --space-unit: 8px;
  --radius-card: 12px;
}

.btn-primary {
  background: var(--brand-primary);
  border-radius: var(--radius-card);
  padding: calc(var(--space-unit) * 2);
}
```

Store shared token definitions in MariaDB if multiple pages must use the same design system. Ensure published pages load the same approved token CSS; merely saving a token table does not automatically update every published page.

## 20. Import/export

Export a portable **JSON project file**:

```javascript
var exportData = {
  format: 'my-grapesjs-project',
  version: 1,
  project: editor.getProjectData()
};
var blob = new Blob([JSON.stringify(exportData, null, 2)], {
  type: 'application/json'
});
var url = URL.createObjectURL(blob);
var a = document.createElement('a');
a.href = url;
a.download = 'page-project.json';
a.click();
URL.revokeObjectURL(url);
```

Import after parsing and validating the file format and project shape:

```javascript
function importProject(jsonText) {
  var data = JSON.parse(jsonText);
  if (!data || data.format !== 'my-grapesjs-project' || !data.project) {
    throw new Error('Unsupported project file');
  }
  editor.loadProjectData(data.project);
}
```

**Important:** Project JSON may reference images, fonts or custom component types not present on the target system. For portable projects, separately package approved assets and maintain a versioned import format.

## 21. Security and offline deployment

### Main risks

| Risk | Mitigation |
|---|---|
| Stored XSS via HTML | Sanitize/allowlist on server before publishing |
| Dangerous CSS or remote URL loading | Validate CSS and restrict resource origins |
| Unrestricted asset uploads | MIME/content validation, random names, size limits, no executable uploads |
| CSRF | Per-session tokens, SameSite cookies, origin checks |
| No login | Bind to localhost/VPN/restricted network; add authorization before wider deployment |
| Unauthorized PHP execution | Never eval/execute editor-supplied code |
| External dependencies | Self-host JS, CSS, fonts, icons, plugins and images |
| Malicious imported JSON | Validate schema, size, URLs and component types |
| Version mismatch | Pin/test GrapesJS and plugin versions |
| Accidental overwrite | Revisions, optimistic locking, backups |

**Do not attempt to block browser Developer Tools as a security mechanism.** Users control their browsers. Protect server data and permissions independently of the UI.

**Offline audit checklist:** Open browser DevTools → Network, clear cache, reload and ensure no requests go to external domains. Repeat for editor, asset browser, templates and published pages.

## 22. Troubleshooting

| Symptom | Diagnosis |
|---|---|
| `grapesjs is not defined` | Wrong JS path, failed load or script ordering |
| Editor blank | Container missing/zero height, JS error, bad initialization |
| UI unstyled | Missing `grapes.min.css` |
| Changes disappear after refresh | Storage disabled or save/load API not wired |
| HTML loads but components behave differently | Reloaded HTML/CSS instead of project JSON |
| Styles appear on wrong devices | Inline styles vs media-query CSS confusion |
| Image not visible | Broken path, permissions, asset missing or remote URL blocked |
| Saved component becomes generic | Custom type not registered before project load |
| Upload succeeds but cannot display | URL mapping/permissions/MIME problem |
| Drag/drop fails | Component `draggable`/`droppable` constraints or overlay issue |
| CSS class change affects many elements | Shared selector semantics; use a unique class if desired |
| Save API returns 403 | CSRF token/session mismatch or access policy |
| Save API returns 500 | Check PHP error logs, DB schema and credentials |

### Debugging sequence

1. Confirm `grapes.min.js` and `grapes.min.css` load with HTTP 200.
2. Check browser Console for the first exception.
3. Verify the editor container has a nonzero height.
4. Confirm `editor.getProjectData()` returns data.
5. Inspect API request/response and server logs.
6. Confirm MariaDB table names and column types.
7. Test saving, reloading, and publishing separately.

## 23. API cheat sheet

| Task | Example |
|---|---|
| Initialize | `grapesjs.init({container:'#gjs'})` |
| Add HTML | `editor.addComponents('<p>Hello</p>')` |
| Selected component | `editor.getSelected()` |
| Select component | `editor.select(component)` |
| Root component | `editor.Components.getWrapper()` |
| Add block | `editor.BlockManager.add(id, config)` |
| Add custom component type | `editor.DomComponents.addType(id, config)` |
| Add asset | `editor.AssetManager.add({src:'/uploads/a.jpg'})` |
| Open assets | `editor.AssetManager.open()` |
| Switch device | `editor.setDevice('Mobile')` |
| Current device | `editor.getDevice()` |
| Export HTML | `editor.getHtml()` |
| Export CSS | `editor.getCss()` |
| Get editable JSON | `editor.getProjectData()` |
| Load editable JSON | `editor.loadProjectData(json)` |
| Get pages | `editor.Pages.getAll()` |
| Add page | `editor.Pages.add({...})` |
| Run command | `editor.runCommand('command-id')` |
| Undo | `editor.UndoManager.undo()` |
| Redo | `editor.UndoManager.redo()` |
| Dirty count | `editor.getDirtyCount()` |
| Listen for updates | `editor.on('update', fn)` |
| Listen for selection | `editor.on('component:selected', fn)` |

**Compatibility note:** Module aliases and option names can vary across releases; check the exact API documentation for the pinned version.

## 24. Suggested learning exercises

**Exercise A — Basic page:** Add Section, Heading, Paragraph, Image and Button blocks. Edit their text and CSS.

**Exercise B — Layout:** Build a two-column Flexbox layout and preview Desktop, Tablet and Mobile. Add responsive CSS rules.

**Exercise C — Custom component:** Register a `company-button` component with editable URL, title and target traits.

**Exercise D — Persistence:** Save project JSON to MariaDB, refresh the page and restore it with `loadProjectData`.

**Exercise E — Reusability:** Save a selected section as a template and insert it into another page.

**Exercise F — Offline verification:** Disconnect Internet access and confirm all editor features and published assets continue working.

**Exercise G — Production hardening:** Add a publish endpoint with HTML/CSS sanitization, revisions and permission checks.

## 25. Official references

These official URLs are provided as plain text inside this Markdown file for easy use in offline documentation; they can be opened when an Internet connection is available.

- Introduction: https://grapesjs.com/docs/
- Getting started: https://grapesjs.com/docs/getting-started.html
- API index: https://grapesjs.com/docs/api/
- Editor API: https://grapesjs.com/docs/api/editor.html
- Components guide: https://grapesjs.com/docs/modules/Components.html
- Style Manager: https://grapesjs.com/docs/modules/Style-manager.html
- Storage Manager: https://grapesjs.com/docs/modules/Storage.html
- Pages: https://grapesjs.com/docs/modules/Pages.html
- Component scripts: https://grapesjs.com/docs/modules/Components-js.html
- Source/release downloads: https://github.com/GrapesJS/grapesjs

---

## Final recommendations for your Visual Designer

1. **Pin a specific GrapesJS Core release** and test against that version.
2. **Keep one editor instance** for Visual, Developer and Split modes.
3. **Store complete project JSON** as the editable source of truth.
4. **Generate HTML/CSS for publishing** only after validation.
5. **Register custom components before loading saved projects.**
6. **Self-host every runtime dependency** for offline use.
7. **Use server-side validation and restricted access**, especially because your prototype intentionally has no login.
8. **Add automated browser/API tests** before treating the editor as production-ready.

*End of guide.*
