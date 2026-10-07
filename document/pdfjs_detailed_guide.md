# PDF.js --- Detailed Guide

## 1. What Is PDF.js?

PDF.js is an open-source JavaScript PDF parsing and rendering project
supported by Mozilla.

Its purpose is to allow a web application to read and display PDF
documents using normal web technologies such as:

-   JavaScript
-   HTML
-   CSS
-   Canvas
-   Web Workers

A useful mental model is:

``` text
PDF file
   |
   v
PDF.js
   |
   +--> Parse PDF structure
   +--> Read pages
   +--> Read fonts and images
   +--> Interpret page drawing commands
   +--> Extract text information
   +--> Read annotations
   |
   v
Browser
   |
   +--> Canvas layer
   +--> Text layer
   +--> Annotation layer
   +--> Custom application layers
```

PDF.js is not simply a `<iframe>` PDF viewer. It is a PDF engine
implemented for the web.

The official project describes PDF.js as a general-purpose,
web-standards-based platform for parsing and rendering PDFs.

------------------------------------------------------------------------

## 2. Why Use PDF.js?

Without PDF.js, a web application may display a PDF using the browser's
built-in PDF viewer:

``` html
<iframe src="report.pdf"></iframe>
```

or:

``` html
<embed src="report.pdf" type="application/pdf">
```

This is easy, but the application has limited control over the viewer.

For example, it is difficult to build application-specific features such
as:

-   custom page navigation
-   custom zoom controls
-   custom thumbnails
-   review drawings
-   application annotations
-   page-specific comments
-   drawing selection
-   drawing editing
-   workflow controls
-   document metadata panels
-   custom read-only/edit modes

PDF.js gives the application much more control.

``` text
Browser built-in PDF viewer

Application
    |
    v
<iframe>
    |
    v
Browser PDF viewer

Application control: limited
```

With PDF.js:

``` text
Application
    |
    v
PDF.js API
    |
    +--> PDF page
    +--> Canvas
    +--> Text layer
    +--> Annotation layer
    +--> Custom drawing layer
    |
    v
Application-controlled viewer
```

------------------------------------------------------------------------

## 3. PDF.js Architecture

PDF.js can be understood as three major layers:

``` text
+-----------------------------------+
| Viewer Layer                      |
|                                   |
| Toolbar                           |
| Page selector                     |
| Zoom controls                     |
| Thumbnails                        |
| Search                            |
| Print                             |
| Download                          |
+----------------+------------------+
                 |
                 v
+-----------------------------------+
| Display Layer                     |
|                                   |
| getDocument()                     |
| PDFDocumentProxy                  |
| PDFPageProxy                      |
| getViewport()                     |
| render()                          |
| getTextContent()                  |
| getAnnotations()                  |
+----------------+------------------+
                 |
                 v
+-----------------------------------+
| Core Layer                        |
|                                   |
| PDF parser                        |
| XRef parser                       |
| Fonts                             |
| Images                            |
| Streams                           |
| Page content                      |
| PDF operators                     |
+-----------------------------------+
```

### Core Layer

The core layer understands the internal PDF format.

It handles things such as:

``` text
PDF header
PDF objects
cross-reference data
page tree
content streams
fonts
images
graphics
annotations
metadata
```

Normally, an application should not directly work with the core layer.

### Display Layer

The Display API is the normal programming interface used by
applications.

Typical calls include:

``` javascript
pdfjsLib.getDocument(...)
pdf.getPage(...)
page.getViewport(...)
page.render(...)
page.getTextContent(...)
page.getAnnotations(...)
```

### Viewer Layer

PDF.js also provides a complete generic viewer.

It contains features such as:

``` text
toolbar
page navigation
zoom
rotation
search
thumbnail panel
print
download
document properties
```

For a normal document-review application, it is often better to build a
custom viewer using the Display API rather than modifying every part of
the generic viewer.

------------------------------------------------------------------------

## 4. PDF.js and Firefox

PDF.js has been integrated into Firefox for many years.

Conceptually:

``` text
Firefox
   |
   v
PDF.js-based PDF viewer
   |
   v
PDF document
```

However, this does not mean an application should copy files from the
Firefox installation.

For a web application, use a separately distributed/self-hosted PDF.js
build.

------------------------------------------------------------------------

## 5. Modern PDF.js File Structure

Current PDF.js distributions use modules.

A simplified current prebuilt layout looks like:

``` text
pdfjs/
|
+-- build/
|   +-- pdf.mjs
|   +-- pdf.worker.mjs
|
+-- web/
    +-- viewer.html
    +-- viewer.mjs
    +-- viewer.css
    +-- images/
    +-- locale/
```

The important concepts are:

``` text
pdf.mjs
    |
    +--> Display API

pdf.worker.mjs
    |
    +--> PDF parsing worker

viewer.mjs
    |
    +--> Full viewer application
```

The exact filenames and packaging vary between PDF.js generations, so
code written for an old PDF.js release should not be assumed to work
unchanged with a current release.

------------------------------------------------------------------------

## 6. The Web Worker

Parsing and interpreting a PDF can require substantial CPU work.

If everything happens on the main browser thread:

``` text
Main Browser Thread
|
+-- UI
+-- mouse events
+-- scrolling
+-- PDF parsing
+-- font processing
+-- image decoding
```

the interface may freeze.

PDF.js therefore uses a Web Worker architecture:

``` text
                 Browser

+-----------------------------+
| Main Thread                 |
|                             |
| UI                          |
| scrolling                   |
| buttons                     |
| canvas                      |
+-------------+---------------+
              |
              | messages
              v
+-----------------------------+
| PDF.js Worker               |
|                             |
| PDF parsing                 |
| page processing             |
| fonts                       |
| images                      |
| PDF operators               |
+-----------------------------+
```

This keeps expensive PDF processing away from the UI thread where
possible.

A worker file therefore must normally be deployed together with the
PDF.js library.

------------------------------------------------------------------------

## 7. Basic PDF Loading Flow

Conceptually:

``` text
getDocument()
     |
     v
PDFDocumentLoadingTask
     |
     v
PDFDocumentProxy
     |
     +--> getPage(1)
     |
     v
PDFPageProxy
     |
     +--> getViewport()
     |
     +--> render()
     |
     v
Canvas
```

A classic-style example looks like:

``` javascript
var loadingTask = pdfjsLib.getDocument('sample.pdf');

loadingTask.promise.then(function (pdf) {

    console.log(
        'Pages: ' + pdf.numPages
    );

    return pdf.getPage(1);

}).then(function (page) {

    var scale = 1.5;

    var viewport = page.getViewport({
        scale: scale
    });

    var canvas =
        document.getElementById('pdfCanvas');

    var context =
        canvas.getContext('2d');

    canvas.width =
        viewport.width;

    canvas.height =
        viewport.height;

    var renderContext = {
        canvasContext: context,
        viewport: viewport
    };

    return page.render(
        renderContext
    ).promise;

}).then(function () {

    console.log(
        'Page rendered.'
    );

});
```

HTML:

``` html
<canvas id="pdfCanvas"></canvas>
```

The exact API syntax depends on the PDF.js version being used.

------------------------------------------------------------------------

## 8. What Is `PDFDocumentLoadingTask`?

`getDocument()` does not immediately return the finished PDF document.

Instead:

``` text
getDocument()
      |
      v
PDFDocumentLoadingTask
      |
      | promise
      v
PDFDocumentProxy
```

Example:

``` javascript
var loadingTask =
    pdfjsLib.getDocument('report.pdf');

loadingTask.promise.then(
    function (pdf) {

        console.log(
            pdf.numPages
        );
    }
);
```

This asynchronous design is important because PDF data may need to be:

-   downloaded
-   parsed
-   validated
-   partially loaded
-   processed by a worker

------------------------------------------------------------------------

## 9. `PDFDocumentProxy`

After the PDF finishes loading, the application receives a document
proxy.

Think of it as:

``` text
PDFDocumentProxy
|
+-- numPages
+-- getPage()
+-- getMetadata()
+-- getOutline()
+-- getAttachments()
+-- other document APIs
```

Example:

``` javascript
loadingTask.promise.then(
    function (pdf) {

        console.log(
            'Page count: ' +
            pdf.numPages
        );

        pdf.getPage(1).then(
            function (page) {

                // Work with page 1.
            }
        );
    }
);
```

------------------------------------------------------------------------

## 10. `PDFPageProxy`

A page returned by `getPage()` represents one PDF page.

``` text
PDFDocumentProxy
      |
      | getPage(3)
      v
PDFPageProxy
      |
      +--> viewport
      +--> rendering
      +--> text
      +--> annotations
```

Example:

``` javascript
pdf.getPage(3).then(
    function (page) {

        console.log(
            'Page loaded'
        );
    }
);
```

PDF page numbering starts at 1 in this API.

------------------------------------------------------------------------

## 11. What Is a Viewport?

A PDF page has its own coordinate system and dimensions.

The viewport tells PDF.js how the PDF page should be transformed into
display coordinates.

Example:

``` javascript
var viewport =
    page.getViewport({
        scale: 1.5
    });
```

Conceptually:

``` text
PDF page
   |
   | scale = 1.5
   v
Viewport
   |
   +--> width
   +--> height
   +--> transform
   |
   v
Canvas
```

Increasing the scale produces a larger rendered page.

------------------------------------------------------------------------

## 12. Rendering to Canvas

The Canvas layer contains the visual PDF page.

``` text
PDF page instructions
        |
        v
PDF.js renderer
        |
        v
HTML Canvas
        |
        v
Visible page
```

Example:

``` javascript
var canvas =
    document.createElement('canvas');

var context =
    canvas.getContext('2d');

canvas.width =
    viewport.width;

canvas.height =
    viewport.height;

page.render({
    canvasContext: context,
    viewport: viewport
});
```

The canvas contains the visual result of the PDF page, including:

-   text appearance
-   vector graphics
-   lines
-   fills
-   images
-   page graphics

However, Canvas pixels alone do not give the normal HTML text-selection
behavior.

That is one reason PDF viewers may use additional layers.

------------------------------------------------------------------------

## 13. Page Layers

A useful PDF.js page model is:

``` text
+----------------------------------+
| Custom Review Drawing Layer      |
|                                  |
| rectangles                       |
| arrows                           |
| lines                            |
| comments                         |
+----------------------------------+
| Annotation Layer                 |
|                                  |
| links                            |
| PDF annotations                  |
| form-related elements            |
+----------------------------------+
| Text Layer                       |
|                                  |
| selectable/searchable text       |
+----------------------------------+
| Canvas Layer                     |
|                                  |
| rendered PDF page                |
+----------------------------------+
```

The layers occupy the same page area.

CSS normally uses absolute positioning to place them on top of each
other.

------------------------------------------------------------------------

## 14. Canvas Layer

The Canvas layer is the visual foundation.

Example DOM structure:

``` html
<div class="pdf-page">
    <canvas class="pdf-canvas"></canvas>
</div>
```

CSS:

``` css
.pdf-page {
    position: relative;
}

.pdf-canvas {
    display: block;
}
```

------------------------------------------------------------------------

## 15. Text Layer

If a page is only rendered to Canvas, the browser sees pixels rather
than normal selectable text.

The Text layer places invisible or transparent positioned text over the
visual page.

Conceptually:

``` text
        User sees
            |
            v
+------------------------+
| Text Layer             |
| "Annual Report"        |
+------------------------+
| Canvas                 |
| pixels representing    |
| "Annual Report"        |
+------------------------+
```

The two layers line up.

This enables features such as:

-   text selection
-   copy
-   search highlighting
-   accessibility-related behavior

Text information can be obtained through:

``` javascript
page.getTextContent()
```

Example:

``` javascript
page.getTextContent().then(
    function (textContent) {

        console.log(
            textContent.items
        );
    }
);
```

------------------------------------------------------------------------

## 16. Annotation Layer

A PDF can contain interactive objects such as:

-   hyperlinks
-   form widgets
-   annotations

These may need HTML elements positioned above the Canvas.

Conceptually:

``` text
Annotation Layer
      |
      +--> links
      +--> widgets
      +--> interactive objects
```

Do not confuse PDF-native annotations with your application's own review
drawings.

For a review system, keeping application review data separate is usually
cleaner.

------------------------------------------------------------------------

## 17. Custom Review Drawing Layer

For an application that lets users draw over PDF pages, add a separate
application layer.

``` text
+--------------------------------+
| Application Drawing Layer      |
+--------------------------------+
| PDF.js Annotation Layer        |
+--------------------------------+
| PDF.js Text Layer              |
+--------------------------------+
| PDF.js Canvas                  |
+--------------------------------+
```

Example:

``` html
<div class="pdf-page">

    <canvas class="pdf-canvas"></canvas>

    <div class="text-layer"></div>

    <div class="annotation-layer"></div>

    <canvas class="drawing-layer"></canvas>

</div>
```

CSS:

``` css
.pdf-page {
    position: relative;
}

.pdf-canvas,
.text-layer,
.annotation-layer,
.drawing-layer {
    position: absolute;
    left: 0;
    top: 0;
}

.drawing-layer {
    pointer-events: none;
}

.viewer.drawing-mode
.drawing-layer {
    pointer-events: auto;
}
```

This design allows normal PDF interaction when drawing mode is disabled
and drawing interaction when edit mode is enabled.

------------------------------------------------------------------------

## 18. Recommended Drawing Architecture

Do not permanently bake application review drawings into the PDF Canvas.

Instead:

``` text
PDF document
     |
     v
PDF.js
     |
     v
PDF Canvas
     |
     +--------------------+
                          |
Review data               |
from database             |
     |                    |
     v                    v
Drawing Layer ----------> Display
```

Benefits:

-   drawings can be edited
-   drawings can be deleted
-   drawing colors can change
-   line width can change
-   arrows can change direction
-   comments can be associated with drawings
-   original PDF remains unchanged
-   review data can be versioned separately

------------------------------------------------------------------------

## 19. Drawing Coordinates

Saving raw screen pixels is usually a mistake.

Suppose a rectangle is saved as:

``` json
{
    "x": 300,
    "y": 450,
    "width": 200,
    "height": 100
}
```

At another zoom level, those values may no longer line up correctly.

A better approach is normalized coordinates.

For example:

``` json
{
    "page": 3,
    "type": "rectangle",
    "x": 0.25,
    "y": 0.30,
    "width": 0.20,
    "height": 0.10
}
```

Interpretation:

``` text
x      = 25% of page width
y      = 30% of page height
width  = 20% of page width
height = 10% of page height
```

To draw:

``` javascript
var x =
    drawing.x * pageWidth;

var y =
    drawing.y * pageHeight;

var width =
    drawing.width * pageWidth;

var height =
    drawing.height * pageHeight;
```

This makes drawings much easier to restore after zooming.

------------------------------------------------------------------------

## 20. Zoom

A basic zoom model:

``` text
100%
 |
 | user clicks +
 v
125%
 |
 v
Create new viewport
 |
 v
Resize canvas/page layers
 |
 v
Render PDF again
 |
 v
Redraw application drawings
```

Pseudo-code:

``` javascript
function setZoom(newScale)
{
    currentScale =
        newScale;

    renderVisiblePages();
}
```

For every page:

``` javascript
var viewport =
    page.getViewport({
        scale: currentScale
    });
```

Then resize all page layers to match the viewport.

Application drawings stored in normalized coordinates can be
recalculated for the new page size.

------------------------------------------------------------------------

## 21. Rotation

PDF.js viewports can account for page rotation.

Conceptually:

``` text
Original Page
    |
    | rotate 90 degrees
    v
Viewport
    |
    v
Rendered Page
```

For a custom drawing system, define one canonical coordinate system for
saved drawings.

A good strategy is:

``` text
Database
   |
   +--> save drawing against canonical page coordinates
           |
           v
Viewer transformation
           |
           +--> zoom
           +--> rotation
           |
           v
Screen coordinates
```

This avoids permanently modifying drawing geometry every time the user
rotates the viewer.

------------------------------------------------------------------------

## 22. Multi-Page Viewer

A continuous document viewer can use one page container per PDF page:

``` text
Viewer
|
+-- Page 1
|   +-- canvas
|   +-- text
|   +-- annotation
|   +-- drawing
|
+-- Page 2
|   +-- canvas
|   +-- text
|   +-- annotation
|   +-- drawing
|
+-- Page 3
    +-- canvas
    +-- text
    +-- annotation
    +-- drawing
```

Example HTML:

``` html
<div id="pdfViewer">

    <div
        class="pdf-page"
        data-page="1">
    </div>

    <div
        class="pdf-page"
        data-page="2">
    </div>

</div>
```

------------------------------------------------------------------------

## 23. Do Not Render Every Large Page Immediately

Suppose a document contains:

``` text
500 pages
```

Rendering all 500 pages immediately can consume large amounts of:

-   memory
-   CPU
-   browser resources

A better approach is lazy rendering.

``` text
Pages 1-2     render now
Pages 3-4     prepare soon
Pages 5-500   wait
```

When the user scrolls:

``` text
User reaches page 10
       |
       v
Render pages 9-12
       |
       v
Optionally release distant canvases
```

Modern applications can use `IntersectionObserver`, but an application
targeting very old browsers may need a scroll-based fallback or
polyfill.

------------------------------------------------------------------------

## 24. Thumbnail Panel

Thumbnails are simply smaller page renderings.

``` text
PDF Page
   |
   | scale = small
   v
Thumbnail Canvas
```

Example architecture:

``` text
+----------------+----------------------+
| Thumbnails     | Main PDF Viewer      |
|                |                      |
| [Page 1]       |                      |
| [Page 2]       |     Current page     |
| [Page 3]       |                      |
| [Page 4]       |                      |
+----------------+----------------------+
```

Clicking a thumbnail can scroll the corresponding page into view.

------------------------------------------------------------------------

## 25. Page Selector

A page selector might contain:

``` text
[ 5 ] / 120
```

When the user enters:

``` text
25
```

the viewer locates page 25:

``` javascript
function goToPage(pageNumber)
{
    var element =
        document.querySelector(
            '[data-page="' +
            pageNumber +
            '"]'
        );

    if (element) {
        element.scrollIntoView();
    }
}
```

For very old browser support, check the exact `scrollIntoView` behavior
required by the target browsers.

------------------------------------------------------------------------

## 26. Loading a PDF Through a Server API

A PDF does not need to be exposed as:

``` text
/public/pdfs/report.pdf
```

The application can request it through an authenticated endpoint:

``` text
GET /api/document/pdf/id/123
```

Architecture:

``` text
Browser
   |
   | authenticated request
   v
Application Server
   |
   +--> authenticate user
   +--> check authorization
   +--> check document permission
   |
   v
PDF response
   |
   v
PDF.js
```

The server might return:

``` http
Content-Type: application/pdf
```

PDF.js then receives the bytes and renders them.

------------------------------------------------------------------------

## 27. Important Security Reality

If PDF.js renders the original PDF in the browser, the browser must
receive enough PDF data to render it.

Therefore:

``` text
Server
   |
   | PDF bytes
   v
Browser
   |
   v
PDF.js
```

This has an important consequence:

> Removing the Download button does not make the PDF impossible to
> obtain.

A technically capable authorized user may still:

-   inspect network traffic
-   access browser memory/cache
-   capture rendered content
-   take screenshots
-   use developer tools or external tools

Blocking right-click or trying to block browser inspection is not a
reliable document-security boundary.

If the browser must never receive the original PDF bytes, the
architecture must change.

For example:

``` text
Original PDF
     |
     v
Server-side renderer
     |
     v
Page image
     |
     v
Browser
```

But even page images can still be captured by the user.

The goal should therefore be risk reduction and authorization rather
than claiming absolute prevention of copying.

------------------------------------------------------------------------

## 28. HTTP Range Requests

PDF.js can benefit from partial/range loading depending on the server
and document.

Conceptually:

``` text
Browser
   |
   | bytes 0-65535
   v
Server

Browser
   |
   | bytes 65536-131071
   v
Server
```

This can help avoid downloading an entire large PDF before useful work
begins.

If a custom authenticated PDF endpoint is used, its HTTP behavior should
be tested with the selected PDF.js version and deployment architecture.

------------------------------------------------------------------------

## 29. Same-Origin and CORS

PDF.js follows normal browser security rules.

If the application is:

``` text
https://app.example.com
```

and the PDF is:

``` text
https://files.example.net/report.pdf
```

the browser's cross-origin rules apply.

Possible solutions include:

``` text
same-origin hosting

or

proper CORS configuration

or

an authenticated application-server endpoint/proxy
```

For protected business documents, serving the PDF through the
application's authorized server endpoint is often easier to control.

------------------------------------------------------------------------

## 30. Full Generic Viewer vs Custom Viewer

### Generic Viewer

The generic PDF.js viewer already contains many features.

``` text
PDF.js Generic Viewer
|
+-- toolbar
+-- thumbnails
+-- page selector
+-- zoom
+-- rotation
+-- search
+-- print
+-- download
+-- document properties
```

Advantages:

-   many features already implemented
-   useful for general PDF viewing
-   good reference implementation

Disadvantages:

-   large
-   more difficult to deeply customize
-   contains features that may not fit the application workflow

### Custom Viewer

A custom viewer uses the Display API:

``` text
Application UI
      |
      v
PDF.js Display API
      |
      v
Custom page viewer
```

Advantages:

-   complete UI control
-   easier workflow integration
-   easier custom drawing layer
-   easier Edit/Done mode
-   easier custom header/footer
-   easier application-specific permissions

For a specialized review/drawing application, a custom viewer is often
the cleaner architecture.

------------------------------------------------------------------------

## 31. Example Review Viewer Layout

A review application might use:

``` text
+------------------------------------------------------+
| Header                                               |
|                                                      |
| [Thumbnails] [Page 2/20] [-] [100%] [+] [Rotate]   |
|                                      [Edit] [Done]   |
+----------------------+-------------------------------+
| Document Information | PDF Viewer                    |
|                      |                               |
| Title                |   Page 1                     |
| Author               |   +----------------------+    |
|                      |   | PDF Canvas           |    |
| [Save]               |   | Text Layer           |    |
|                      |   | Annotation Layer     |    |
|                      |   | Drawing Layer        |    |
|                      |   +----------------------+    |
|                      |                               |
|                      |   Page 2                     |
+----------------------+-------------------------------+
| Drawing Toolbar                                      |
| [Select] [Line] [Arrow] [Rectangle] [Text] [Color] |
+------------------------------------------------------+
```

The drawing toolbar can remain hidden until Edit mode is enabled.

------------------------------------------------------------------------

## 32. Edit and Read-Only Modes

A single viewer component can support both modes.

### Read-only

``` text
PDF interaction     enabled
Drawing display     enabled
Drawing editing     disabled
Drawing toolbar     hidden
```

### Edit

``` text
PDF display         enabled
Drawing display     enabled
Drawing editing     enabled
Drawing toolbar     visible
```

Example:

``` javascript
function setEditMode(enabled)
{
    if (enabled) {
        viewer.className =
            'viewer drawing-mode';
    } else {
        viewer.className =
            'viewer';
    }
}
```

------------------------------------------------------------------------

## 33. Saving Drawings

The PDF and review data should normally be stored separately.

``` text
PDF
 |
 +--> original document

Database
 |
 +--> review session
 +--> page number
 +--> drawing type
 +--> geometry
 +--> style
 +--> comment
 +--> creator
 +--> timestamps
```

Example drawing JSON:

``` json
{
    "documentId": 123,
    "page": 2,
    "type": "arrow",
    "geometry": {
        "x1": 0.20,
        "y1": 0.30,
        "x2": 0.55,
        "y2": 0.45
    },
    "style": {
        "color": "#ff0000",
        "width": 3
    }
}
```

------------------------------------------------------------------------

## 34. Bind Drawings to a PDF Revision

This is important.

Suppose review drawings were created against:

``` text
report-v1.pdf
```

Then someone replaces the PDF with:

``` text
report-v2.pdf
```

Page content may move.

Old drawings could point to the wrong content.

Therefore associate review data with something like:

``` text
document_id
document_revision_id
```

or an immutable PDF content hash.

Example:

``` text
Document 123
|
+-- Revision 1
|      |
|      +-- PDF hash AAA...
|      +-- Review drawings
|
+-- Revision 2
       |
       +-- PDF hash BBB...
       +-- Review drawings
```

------------------------------------------------------------------------

## 35. Suggested Drawing Database Fields

A possible table design:

``` text
review_drawings

id
document_id
document_revision_id
review_id
page_number
type
geometry_json
style_json
comment_text
created_by
created_at
updated_by
updated_at
version
deleted_at
```

Store frequently searched relational information in normal columns and
flexible geometry/style data in JSON/text fields as appropriate for the
database version.

------------------------------------------------------------------------

## 36. Drawing Types

Useful review drawing types include:

``` text
line
arrow
rectangle
ellipse
freehand
text
highlight
stamp
comment marker
```

Geometry varies by type.

Rectangle:

``` json
{
    "x": 0.20,
    "y": 0.30,
    "width": 0.25,
    "height": 0.10
}
```

Line:

``` json
{
    "x1": 0.10,
    "y1": 0.20,
    "x2": 0.50,
    "y2": 0.40
}
```

Freehand:

``` json
{
    "points": [
        [0.10, 0.20],
        [0.11, 0.21],
        [0.13, 0.24]
    ]
}
```

------------------------------------------------------------------------

## 37. Drawing Selection and Editing

When the user selects a drawing:

``` text
Select Drawing
     |
     +--> Change color
     +--> Change width
     +--> Change length
     +--> Change direction
     +--> Move
     +--> Resize
     +--> Delete
```

A drawing library can simplify these interactions, but the library
should be treated as a separate application layer from PDF.js.

``` text
PDF.js
   |
   +--> PDF rendering

Drawing Library
   |
   +--> drawing/editing

Application
   |
   +--> coordinates
   +--> persistence
   +--> permissions
   +--> workflow
```

------------------------------------------------------------------------

## 38. High-DPI Displays

On high-DPI displays, a canvas can look blurry if its internal pixel
dimensions are exactly equal to its CSS dimensions.

Conceptually:

``` text
CSS page size
800 x 1000

Device pixel ratio
2

Canvas backing size
1600 x 2000
```

while CSS still displays:

``` text
800 x 1000
```

The exact implementation should follow the PDF.js version's recommended
rendering examples.

------------------------------------------------------------------------

## 39. Memory Management

Canvas memory can become substantial.

For example:

``` text
2000 x 3000 canvas
=
6,000,000 pixels
```

At roughly four bytes per RGBA pixel:

``` text
~24 MB
```

for just one raw pixel buffer, before considering other browser
resources.

Large documents can therefore consume significant memory if many
high-resolution pages remain rendered simultaneously.

Possible strategy:

``` text
Visible pages
    |
    +--> full rendering

Nearby pages
    |
    +--> prepared/rendered

Distant pages
    |
    +--> release large canvas resources
```

------------------------------------------------------------------------

## 40. Error Handling

PDF loading can fail because of:

-   network failure
-   authorization failure
-   missing document
-   malformed PDF
-   password-protected PDF
-   worker configuration
-   CORS
-   unsupported browser capabilities

Always handle rejected loading/rendering promises.

Example:

``` javascript
loadingTask.promise.then(
    function (pdf) {

        // Success.

    },
    function (error) {

        console.error(
            'PDF load failed:',
            error
        );
    }
);
```

------------------------------------------------------------------------

## 41. Password-Protected PDFs

PDF.js can encounter PDFs requiring a password.

A production viewer should define what the application wants to do:

``` text
Password protected PDF
       |
       +--> allow password prompt
       |
       or
       |
       +--> reject document
       |
       or
       |
       +--> server handles protected document
```

This should be an application/security decision rather than an
accidental viewer behavior.

------------------------------------------------------------------------

## 42. PDF.js Does Not Replace Server Authorization

PDF.js runs in the browser.

It should never be the authority deciding whether a user is allowed to
access a confidential document.

Incorrect:

``` text
Browser JavaScript
    |
    +--> "user can view PDF"
```

Correct:

``` text
Browser
    |
    v
Server
    |
    +--> authenticate
    +--> authorize
    +--> check document permission
    |
    v
Return PDF data
```

The server is the security boundary.

------------------------------------------------------------------------

## 43. Self-Hosting PDF.js

For controlled/offline environments, self-host PDF.js resources.

Conceptually:

``` text
/site-resources/
|
+-- pdfjs/
|   +-- build/
|   +-- web/
|   +-- cmaps/
|   +-- standard_fonts/
|   +-- images/
|
+-- js/
+-- css/
```

Benefits:

-   no CDN dependency
-   controlled version
-   predictable deployment
-   works in isolated networks
-   easier security review
-   easier reproducible builds

Do not mix files from different PDF.js releases.

Keep the selected distribution together as one versioned package.

------------------------------------------------------------------------

## 44. Browser Compatibility --- Very Important

Current PDF.js releases target modern browsers.

Even the current build called `legacy` does **not** mean that extremely
old browsers such as Chrome 49 and Firefox 52 are supported.

As of the current PDF.js documentation checked for this guide, the
current legacy bundle targets much newer browsers than Chrome 49.

Therefore:

``` text
Current PDF.js 6.x
      |
      +--> modern browser build
      |
      +--> legacy build
             |
             +--> still much newer than Chrome 49
```

If your application has a hard requirement for:

``` text
Chrome 49
Firefox 52
```

do **not** simply download the latest PDF.js and assume the legacy
bundle solves the problem.

Instead, browser compatibility must be treated as a separate
architecture/version-selection task.

Possible strategies include:

1.  Select and freeze an older PDF.js generation that actually runs on
    the required browsers.
2.  Test that exact build against all required PDF features.
3.  Self-host that exact version.
4.  Avoid mixing its examples with current PDF.js 6.x examples.
5.  Maintain security controls around the application because an old
    PDF.js/browser stack may no longer receive modern security
    improvements.
6.  Consider server-rendered page images if the legacy browser
    requirement is more important than client-side PDF parsing.

An older archived PDF.js compatibility document showed much broader
old-browser support in historical releases, but that does not make
current PDF.js compatible with those browsers.

------------------------------------------------------------------------

## 45. Version Selection Strategy for Legacy Browsers

For a legacy-browser project, treat PDF.js as a pinned application
dependency:

``` text
Requirements
|
+-- Chrome 49
+-- Firefox 52
+-- required PDF features
+-- offline deployment
|
v
Select PDF.js generation
|
v
Compatibility test
|
+-- basic PDF
+-- large PDF
+-- fonts
+-- images
+-- annotations
+-- text selection
+-- worker
+-- zoom
+-- rotation
|
v
Freeze version
|
v
Deploy exact files
```

Do not automatically upgrade PDF.js in production without compatibility
testing.

------------------------------------------------------------------------

## 46. PDF.js and Promises

PDF.js APIs rely heavily on asynchronous operations and Promises.

Typical flow:

``` text
getDocument()
    |
    v
Promise
    |
    v
getPage()
    |
    v
Promise
    |
    v
render()
    |
    v
Promise
```

For old browsers, Promise availability and the exact PDF.js build's
polyfills are part of compatibility testing.

------------------------------------------------------------------------

## 47. Suggested Custom Viewer Components

A maintainable custom viewer can be separated into components:

``` text
PdfViewer
|
+-- PdfDocumentLoader
|
+-- PdfPageManager
|
+-- PdfPageRenderer
|
+-- ThumbnailManager
|
+-- ZoomManager
|
+-- RotationManager
|
+-- DrawingManager
|
+-- ReviewApi
|
+-- ViewerToolbar
```

Responsibilities should stay separated.

For example:

``` text
PdfPageRenderer
    |
    +--> PDF.js rendering

DrawingManager
    |
    +--> review drawings

ReviewApi
    |
    +--> load/save review data
```

This avoids putting all functionality into one very large JavaScript
file.

------------------------------------------------------------------------

## 48. Suggested Page Object

An application-level page object might contain:

``` javascript
var pageView = {
    pageNumber: 1,
    pdfPage: null,
    viewport: null,
    container: null,
    canvas: null,
    textLayer: null,
    annotationLayer: null,
    drawingLayer: null,
    rendered: false
};
```

This object is not a required PDF.js API. It is an application design
pattern for organizing the viewer.

------------------------------------------------------------------------

## 49. Recommended Rendering Lifecycle

``` text
Create page container
       |
       v
Get PDF page
       |
       v
Create viewport
       |
       v
Set page dimensions
       |
       v
Render canvas
       |
       +--> render text layer
       |
       +--> render annotation layer
       |
       +--> load application drawings
       |
       v
Page ready
```

When zoom changes:

``` text
Zoom changed
      |
      v
Create new viewport
      |
      v
Resize layers
      |
      v
Re-render PDF
      |
      v
Recalculate/redraw review drawings
```

------------------------------------------------------------------------

## 50. Recommended Server Responsibilities

The browser should handle presentation and interaction.

The server should handle authority and persistence.

``` text
Server
|
+-- authentication
+-- authorization
+-- document permissions
+-- document revision control
+-- review workflow
+-- drawing CRUD
+-- audit logging
+-- secure PDF delivery
+-- database
```

Browser:

``` text
Browser
|
+-- PDF.js rendering
+-- toolbar
+-- page navigation
+-- zoom
+-- rotation
+-- drawing interaction
+-- temporary UI state
```

------------------------------------------------------------------------

## 51. Example API Design

Secure PDF:

``` text
GET /api/document/123/pdf
```

Load drawings:

``` text
GET /api/document/123/review-drawings
```

Create drawing:

``` text
POST /api/review-drawing
```

Update drawing:

``` text
PUT /api/review-drawing/456
```

Delete drawing:

``` text
DELETE /api/review-drawing/456
```

The server should validate:

-   logged-in user
-   document access
-   review permission
-   page number
-   drawing type
-   coordinate ranges
-   style values
-   maximum freehand points
-   maximum text/comment size

Never trust drawing JSON simply because it came from your own JavaScript
viewer.

------------------------------------------------------------------------

## 52. Suggested Project Structure

``` text
project/
|
+-- public/
|   |
|   +-- resources/
|       |
|       +-- pdfjs/
|       |
|       +-- js/
|       |   +-- pdf-viewer.js
|       |   +-- pdf-page.js
|       |   +-- drawing-manager.js
|       |   +-- review-api.js
|       |
|       +-- css/
|           +-- pdf-viewer.css
|           +-- drawing.css
|
+-- server/
    |
    +-- document API
    +-- review API
    +-- database
```

If all resources must be local, keep PDF.js, JavaScript, CSS, fonts,
CMaps, images, and other dependencies on the site's own resources rather
than depending on public CDNs.

------------------------------------------------------------------------

## 53. Development Stages

A practical implementation order is:

### Stage 1 --- Basic PDF

``` text
load PDF
render one page
```

### Stage 2 --- Multi-page

``` text
render page containers
scroll pages
```

### Stage 3 --- Viewer controls

``` text
page selector
zoom
rotation
```

### Stage 4 --- Thumbnails

``` text
thumbnail panel
show/hide
thumbnail navigation
```

### Stage 5 --- Layers

``` text
text layer
annotation layer
```

### Stage 6 --- Drawing overlay

``` text
line
arrow
rectangle
ellipse
freehand
text
```

### Stage 7 --- Drawing editing

``` text
select
move
resize
color
line width
direction
delete
```

### Stage 8 --- Persistence

``` text
save to server
load from server
revision binding
```

### Stage 9 --- Performance

``` text
lazy rendering
canvas cleanup
large-document testing
```

### Stage 10 --- Security

``` text
authorization
protected endpoint
audit
input validation
security headers
```

------------------------------------------------------------------------

## 54. PDF.js vs Server-Side Page Rendering

These are two different architectures.

### PDF.js Architecture

``` text
Server
   |
   | PDF bytes
   v
Browser
   |
   v
PDF.js
   |
   v
Canvas
```

Advantages:

-   rich client-side PDF features
-   text layer
-   PDF-aware navigation
-   fewer server rendering operations

Disadvantage:

-   browser receives PDF data

### Server Image Architecture

``` text
Server
   |
   v
PDF renderer
   |
   v
page image
   |
   v
Browser
```

Advantages:

-   original PDF file itself does not have to be sent to the browser
-   simpler very-old-browser client

Disadvantages:

-   server rendering load
-   no native PDF text layer unless separately implemented
-   page images can still be captured
-   more image/cache management

Choose the architecture according to actual security and browser
requirements.

------------------------------------------------------------------------

## 55. Common Misunderstandings

### "PDF.js prevents downloading."

No.

PDF.js is a renderer. If it receives the PDF bytes, those bytes have
reached the browser.

### "Base64-encoding the PDF protects it."

No.

Base64 is encoding, not encryption.

### "Removing the Download button protects the PDF."

It only removes a convenient UI action.

### "Blocking right-click protects the document."

No. It may discourage casual actions but is not a security boundary.

### "The latest legacy PDF.js supports Chrome 49."

No. Current PDF.js legacy builds target substantially newer browser
environments.

### "Canvas means the PDF text cannot be accessed."

Not necessarily. PDF.js can expose text information separately through
its APIs and text layer.

### "Application review drawings should be written directly into the PDF canvas."

Usually not. Keep review drawings in a separate overlay and persist them
separately unless the business requirement explicitly calls for creating
a new annotated PDF.

------------------------------------------------------------------------

## 56. Recommended Architecture for a Review System

``` text
+------------------------------------------------------+
|                    Browser                           |
|                                                      |
| +--------------------------------------------------+ |
| | Custom Viewer UI                                 | |
| |                                                  | |
| | thumbnails / page / zoom / rotation / edit      | |
| +-------------------------+------------------------+ |
|                           |                          |
|                           v                          |
| +--------------------------------------------------+ |
| | Page                                             | |
| |                                                  | |
| | Drawing Layer                                    | |
| | Annotation Layer                                 | |
| | Text Layer                                       | |
| | PDF Canvas                                       | |
| +--------------------------------------------------+ |
|                           |                          |
+---------------------------|--------------------------+
                            |
                +-----------+-----------+
                |                       |
                v                       v
          PDF endpoint             Review API
                |                       |
                v                       v
         PDF permission            Database
         authorization             drawings
         document data             workflow
```

This keeps:

``` text
PDF rendering
```

separate from:

``` text
review drawing logic
```

and both separate from:

``` text
server authorization/persistence
```

That separation makes the application easier to maintain.

------------------------------------------------------------------------

## 57. Official Resources

PDF.js project:

https://github.com/mozilla/pdf.js

Official documentation:

https://mozilla.github.io/pdf.js/

Getting Started:

https://mozilla.github.io/pdf.js/getting_started/

Examples:

https://mozilla.github.io/pdf.js/examples/

API documentation:

https://mozilla.github.io/pdf.js/api/

Before choosing a production version, check the official browser-support
documentation for that exact PDF.js release.

------------------------------------------------------------------------

## 58. Final Summary

PDF.js is best understood as a browser-side PDF engine:

``` text
PDF
 |
 v
PDF.js Core
 |
 v
Display API
 |
 +-------------------+
 |                   |
 v                   v
Canvas             Text/Annotations
 |
 v
Custom Viewer
 |
 v
Application Drawing Layer
```

For a custom document-review system, a strong architecture is:

``` text
PDF.js
    |
    +--> render PDF

Custom Drawing Layer
    |
    +--> review drawings

Server API
    |
    +--> authentication
    +--> authorization
    +--> PDF delivery
    +--> drawing persistence

Database
    |
    +--> document revisions
    +--> review data
    +--> drawings
```

The most important design rules are:

1.  Treat PDF.js as the PDF rendering engine, not the security boundary.
2.  Keep application review drawings separate from the PDF Canvas.
3.  Save drawing geometry in stable page-relative coordinates.
4.  Bind review data to a specific document revision.
5.  Lazy-render large documents.
6.  Self-host and pin the PDF.js version in controlled/offline
    deployments.
7.  Do not assume the current `legacy` build supports Chrome 49 or
    Firefox 52.
8.  If Chrome 49/Firefox 52 are mandatory, select and test a
    historically compatible PDF.js version as a separate engineering
    task.
9.  Remember that hiding download controls cannot prevent a browser that
    receives PDF bytes from accessing those bytes.
10. Keep authorization and document permissions on the server.
