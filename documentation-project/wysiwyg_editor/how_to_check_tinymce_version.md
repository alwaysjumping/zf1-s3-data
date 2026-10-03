# How to Check the TinyMCE Version in an Existing Project

This guide explains several practical ways to identify the TinyMCE
version already installed in an existing web project.

## 1. Check the Version in the Browser Console

This is usually the easiest method.

1.  Open a page in your application where TinyMCE is loaded.
2.  Press **F12** to open the browser Developer Tools.
3.  Select the **Console** tab.
4.  Run:

``` javascript
tinymce.majorVersion
```

Then run:

``` javascript
tinymce.minorVersion
```

You can also display them together:

``` javascript
tinymce.majorVersion + '.' + tinymce.minorVersion
```

For example, the result might look like:

``` text
5.10.9
```

You can also inspect the complete TinyMCE object:

``` javascript
tinymce
```

This can help you see which version-related properties are available in
your installed release.

------------------------------------------------------------------------

## 2. Print the Version from JavaScript

You can temporarily add debugging code to a page where TinyMCE is
loaded:

``` html
<script>
console.log('TinyMCE major:', tinymce.majorVersion);
console.log('TinyMCE minor:', tinymce.minorVersion);

console.log(
    'TinyMCE version: ' +
    tinymce.majorVersion +
    '.' +
    tinymce.minorVersion
);
</script>
```

Open the browser Developer Tools and check the Console.

Remove this temporary debugging code after you have identified the
version.

------------------------------------------------------------------------

## 3. Check the TinyMCE Source Files

Locate the TinyMCE installation in your project.

For example:

``` text
public/
└── js/
    └── tinymce/
        ├── tinymce.js
        └── tinymce.min.js
```

or:

``` text
public/
└── vendor/
    └── tinymce/
        ├── tinymce.js
        └── tinymce.min.js
```

If `tinymce.js` exists, open it first because the unminified source is
easier to inspect.

Search for terms such as:

``` text
majorVersion
minorVersion
version
```

Some TinyMCE distributions include version information in the source
file or comments.

------------------------------------------------------------------------

## 4. Search the Project for TinyMCE Initialization

Search your project source code for:

``` text
tinymce.init(
```

Also search for the older form:

``` text
tinyMCE.init(
```

The initialization code can provide useful clues about the TinyMCE
generation being used.

An older configuration might look similar to:

``` javascript
tinyMCE.init({
    mode: 'textareas',
    theme: 'advanced',
    plugins: 'table,advimage,advlink'
});
```

A newer configuration commonly looks more like:

``` javascript
tinymce.init({
    selector: '#editor',
    plugins: 'lists link image table code',
    toolbar: 'undo redo | bold italic'
});
```

Do not determine the exact version from configuration syntax alone. Use
it as a clue, then verify the version with the browser console or source
files.

------------------------------------------------------------------------

## 5. Recommended Method

For an existing project, start with the browser console because it does
not require changing the application.

Open:

``` text
F12
  ↓
Developer Tools
  ↓
Console
```

Then execute:

``` javascript
tinymce.majorVersion + '.' + tinymce.minorVersion
```

The process is:

``` text
Existing Application
        │
        ▼
Page containing TinyMCE
        │
        ▼
Browser Developer Tools
        │
        ▼
Console
        │
        ▼
tinymce.majorVersion
tinymce.minorVersion
        │
        ▼
Installed TinyMCE Version
```

------------------------------------------------------------------------

## 6. What to Check After Finding the Version

After identifying the installed version, review the following before
upgrading or adding new features:

-   Whether the version is still appropriate for the application.
-   Compatibility with the project's existing TinyMCE configuration.
-   Existing TinyMCE plugins.
-   Existing custom toolbar buttons.
-   Existing custom plugins.
-   Image upload integration.
-   HTML output expected by the ZF1 backend.
-   CSS used inside the editor.
-   Browser compatibility requirements.
-   Licensing requirements for the version you plan to use.

For a legacy project, do not replace TinyMCE immediately just because
the installed version is old. First identify the version and existing
customizations.

A safer process is:

``` text
Identify current TinyMCE version
              │
              ▼
Review existing configuration
              │
              ▼
Review plugins
              │
              ▼
Review custom toolbar/buttons
              │
              ▼
Review generated HTML
              │
              ▼
Check compatibility requirements
              │
              ▼
Decide whether to keep or upgrade
```

------------------------------------------------------------------------

## 7. Information Useful for the Next Review

After checking the version, collect these items:

``` text
TinyMCE version:
TinyMCE installation directory:
TinyMCE initialization/config file:
Enabled plugins:
Toolbar configuration:
Custom plugins:
Custom buttons:
Image upload implementation:
```

For example:

``` text
TinyMCE version: 5.10.9
Installation: /public/vendor/tinymce/
Configuration: /public/js/tinymce/config.js
Plugins: lists, link, image, table, code
Custom buttons: Yes
Image upload: Yes
```

With this information, the existing TinyMCE integration can be reviewed
before deciding whether an upgrade is necessary.

------------------------------------------------------------------------

## Quick Reference

### Get major version

``` javascript
tinymce.majorVersion
```

### Get minor version

``` javascript
tinymce.minorVersion
```

### Get combined version

``` javascript
tinymce.majorVersion + '.' + tinymce.minorVersion
```

### Inspect TinyMCE

``` javascript
tinymce
```

### Search existing initialization

``` text
tinymce.init(
tinyMCE.init(
```

------------------------------------------------------------------------

## Recommendation

For an existing ZF1 project, identify the current TinyMCE version
**before changing the editor installation**.

The preferred first check is:

``` javascript
tinymce.majorVersion + '.' + tinymce.minorVersion
```

Once the exact version is known, the next step is to evaluate whether
that version supports the desired toolbar customization, custom
code-snippet dialog, syntax highlighting, image handling, and other
editor features, or whether upgrading TinyMCE would be more appropriate.
