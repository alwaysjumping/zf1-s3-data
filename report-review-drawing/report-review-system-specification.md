# Report Review System - Project Specification

**Project:** ZF1 Report Review and Approval System\
**Target stack:** Zend Framework 1, PHP 7.4, MariaDB, DHTMLX 3.5,
jQuery/AJAX, PDF.js, Fabric.js\
**Document version:** 1.0\
**Status:** Baseline specification for implementation

------------------------------------------------------------------------

## 1. Purpose

The Report Review System manages the complete lifecycle of internal
reports from staff submission through management review, correction,
resubmission, and final approval.

The system extends the existing text-comment review process with PDF
drawing annotations. Reviewers must be able to review a PDF directly in
the browser, leave normal text comments, draw on individual PDF pages,
request corrections, and approve a revision.

The system must preserve a reliable historical record of every submitted
revision, reviewer comment, drawing annotation, review action, and
approval.

------------------------------------------------------------------------

## 2. Project Goals

The system shall:

1.  Allow staff to create and submit reports for review.
2.  Assign one or more related bosses/reviewers.
3.  Allow reviewers to read the complete PDF in the browser.
4.  Support continuous multi-page PDF scrolling.
5.  Support PDF zoom in and zoom out.
6.  Support normal text comments.
7.  Support drawing annotations on PDF pages.
8.  Keep drawing positions correct while zooming, resizing, and
    scrolling.
9.  Allow reviewers to request changes or approve a report revision.
10. Allow staff to create a new revision after corrections.
11. Preserve all previous revisions as read-only history.
12. Determine final approval only after all required reviewers have
    approved the applicable revision.
13. Record important actions in an audit trail.
14. Hide normal PDF download, print, and open controls in Version 1.
15. Prevent casual use of common save/print/context-menu/Developer Tools
    shortcuts as a deterrent.

------------------------------------------------------------------------

## 3. Scope

### 3.1 Included in Version 1

-   Report record management
-   PDF-based report revisions
-   Reviewer assignment
-   Parallel reviewer support
-   Text comments
-   Drawing annotations
-   Revision history
-   Request-changes workflow
-   Resubmission workflow
-   Reviewer approval
-   Final approval
-   PDF.js viewer
-   Fabric.js drawing layer
-   Zoom synchronization
-   Continuous page scrolling
-   Annotation JSON persistence
-   ZF1 authorization checks
-   Protected PDF endpoint
-   Audit logging
-   Basic PDF download/print UI deterrence

### 3.2 Not Guaranteed by Version 1

Version 1 does **not** provide absolute prevention of PDF extraction
after the complete PDF has been delivered to an authorized browser.

Removing download controls, blocking keyboard shortcuts, disabling the
context menu, and discouraging Developer Tools are user-interface
deterrents only.

A sufficiently technical authorized user may still be able to recover
PDF bytes from browser/network mechanisms or capture displayed content.

A later high-security version may replace full-PDF delivery with
server-side page rendering.

------------------------------------------------------------------------

## 4. User Roles

### 4.1 Staff / Report Author

The report author can:

-   Create a report.
-   Upload or generate the initial PDF revision.
-   Select or receive assigned reviewers according to business rules.
-   Submit a revision.
-   Read reviewer text comments.
-   View reviewer drawing annotations.
-   Respond to requested changes.
-   Create a new revision.
-   Resubmit the report.
-   View revision history.
-   View approval status.

The author must not modify a previously submitted historical revision.

### 4.2 Reviewer / Boss

A reviewer can:

-   Open reports assigned to them.
-   Read the submitted PDF.
-   Zoom the PDF.
-   Scroll through all pages.
-   Add text comments.
-   Add drawing annotations.
-   Edit/delete their own editable annotations while the review is
    active.
-   Request changes.
-   Approve the current revision.
-   View previous revisions and historical review information.

### 4.3 Administrator

An administrator can manage system configuration and, according to
organizational policy:

-   Review system records.
-   Manage permissions.
-   Correct reviewer assignments where authorized.
-   Inspect audit history.
-   Configure report/reviewer rules.

Administrative override behavior should be separately
permission-controlled and audited.

------------------------------------------------------------------------

## 5. Report Lifecycle

Recommended report states:

-   `DRAFT`
-   `UNDER_REVIEW`
-   `CHANGES_REQUESTED`
-   `APPROVED`
-   `COMPLETED`
-   `CANCELLED` (optional)

Basic flow:

``` text
DRAFT
  |
  | Submit
  v
UNDER_REVIEW
  |
  +---- reviewer requests changes ----> CHANGES_REQUESTED
  |                                      |
  |                                      | staff creates new revision
  |                                      v
  +<--------------------------------- UNDER_REVIEW
  |
  | all required reviewers approve
  v
APPROVED
  |
  v
COMPLETED
```

The application may combine `APPROVED` and `COMPLETED` if the business
does not require a separate post-approval completion step.

------------------------------------------------------------------------

## 6. Revision Model

Revision history is a core requirement.

Example:

``` text
Report R-2026-001
|
+-- Revision 1
|   +-- PDF
|   +-- Boss A comments
|   +-- Boss A drawings
|   +-- Boss B comments
|   +-- Boss B drawings
|   +-- Changes requested
|
+-- Revision 2
|   +-- Corrected PDF
|   +-- Boss A approved
|   +-- Boss B requested another change
|
+-- Revision 3
    +-- Corrected PDF
    +-- Boss A approved
    +-- Boss B approved
    +-- Final approval
```

### 6.1 Revision Rules

-   Every submitted PDF belongs to one revision.
-   A submitted revision must not be overwritten.
-   When changes are required, staff creates a new revision.
-   Old revisions become read-only.
-   Text comments remain attached to the revision on which they were
    created.
-   Drawing annotations remain attached to the revision and PDF page on
    which they were created.
-   Review actions remain attached to their revision.
-   Approval of one revision must not silently become approval of a
    later revision unless an explicit business rule says otherwise.
-   Drawing annotations should not automatically move from an old
    revision to a new revision because document layout may have changed.

------------------------------------------------------------------------

## 7. Reviewer Model

The data model should support one or more reviewers.

Version 1 should support parallel review:

``` text
                  +--> Boss A
Staff -> Submit --+--> Boss B
                  +--> Boss C
```

Each reviewer has an independent review status.

Suggested reviewer states:

-   `PENDING`
-   `REVIEWING`
-   `CHANGES_REQUESTED`
-   `APPROVED`

Final approval occurs when every required reviewer for the current
revision has approved it.

The database design should leave room for future sequential approval
through a `review_order` or equivalent field.

------------------------------------------------------------------------

## 8. Text Comments

The existing text-comment feature remains part of the system.

Each text comment should include:

-   Report ID
-   Revision ID
-   Reviewer/user ID
-   Comment text
-   Status
-   Created timestamp
-   Resolved timestamp
-   Resolved-by user where applicable

Suggested statuses:

-   `OPEN`
-   `RESOLVED`
-   `REOPENED`

Text comments must remain visible in historical revisions.

------------------------------------------------------------------------

## 9. PDF Viewer

### 9.1 Library

Use **PDF.js** for client-side PDF rendering.

### 9.2 Required Viewer Features

The custom viewer shall support:

-   Full PDF loading
-   Multi-page rendering
-   Continuous vertical scrolling
-   Zoom in
-   Zoom out
-   Current zoom indicator
-   Page identification
-   Annotation overlay per page
-   Read-only historical mode

### 9.3 Page Architecture

Use one PDF rendering canvas and one Fabric.js annotation layer for each
PDF page.

``` text
PDF Viewer
|
+-- Page 1
|   +-- PDF.js canvas
|   +-- Fabric.js annotation canvas
|
+-- Page 2
|   +-- PDF.js canvas
|   +-- Fabric.js annotation canvas
|
+-- Page N
    +-- PDF.js canvas
    +-- Fabric.js annotation canvas
```

Do not use one enormous drawing canvas for the complete document.

------------------------------------------------------------------------

## 10. Drawing Annotation System

### 10.1 Library

Use **Fabric.js** as the object-based annotation/drawing layer.

### 10.2 Required Tools

Version 1 drawing toolbar should provide:

-   Select/Edit
-   Pen/freehand
-   Highlighter
-   Line
-   Arrow
-   Rectangle
-   Circle/Ellipse
-   Text
-   Delete selected object
-   Undo
-   Redo
-   Save

An eraser can initially be implemented as object selection + delete. A
true path eraser may be added later.

### 10.3 Annotation Ownership

Each saved annotation set must be associated with:

-   Report
-   Revision
-   PDF page
-   Reviewer/user
-   Creation/update timestamp

The system should prevent one reviewer from unintentionally modifying
another reviewer's annotations unless permissions explicitly allow it.

------------------------------------------------------------------------

## 11. Annotation Coordinates and Zoom

Raw screen pixels must not be the permanent storage coordinate system.

Annotations shall be persisted in normalized page coordinates or another
stable PDF-page coordinate representation.

Example:

``` json
{
  "page": 2,
  "type": "arrow",
  "x1": 0.25,
  "y1": 0.30,
  "x2": 0.55,
  "y2": 0.42
}
```

Normalized values are relative to the page width and height.

### 11.1 Zoom Behavior

When zoom changes:

1.  Preserve current annotation state.
2.  Re-render the PDF page using the new PDF.js viewport.
3.  Resize/recreate the matching Fabric canvas.
4.  Convert normalized geometry to the new displayed page dimensions.
5.  Restore annotation objects.
6.  Keep annotations visually attached to the same document content.

### 11.2 Scrolling Behavior

Scrolling must not alter annotation coordinates.

The annotation canvas is positioned inside the same page container as
its PDF canvas. Therefore, the PDF and annotation layer scroll together
naturally.

### 11.3 Browser Resize

A browser resize or responsive layout change must not permanently change
stored annotation geometry.

### 11.4 High-DPI Displays

The implementation must distinguish CSS display dimensions from canvas
internal pixel dimensions and account for `devicePixelRatio` where
necessary to avoid blurry output and coordinate drift.

------------------------------------------------------------------------

## 12. Annotation Persistence

Annotation data shall be serialized to JSON and sent to ZF1 through an
authenticated REST/API endpoint.

Example logical structure:

``` json
{
  "report_id": 1001,
  "revision_id": 7,
  "page_no": 2,
  "objects": [
    {
      "type": "rect",
      "left": 0.20,
      "top": 0.35,
      "width": 0.30,
      "height": 0.08
    }
  ]
}
```

The exact JSON schema should be versioned so future Fabric.js or
application changes can be migrated safely.

Recommended metadata:

-   `schema_version`
-   `fabric_version`
-   normalized page width/height model
-   objects
-   saved timestamp

------------------------------------------------------------------------

## 13. PDF Access and Download Deterrence - Version 1

### 13.1 Storage

PDF files must not be placed in a directly accessible public web
directory.

Bad:

``` text
/public/reports/report-1001.pdf
```

Recommended:

``` text
/protected-data/reports/...
```

The actual location must not be directly served by the web server.

### 13.2 Protected Endpoint

PDF.js should obtain the PDF through a ZF1-controlled endpoint.

Example:

``` text
GET /api/report/pdf/view/:revisionId
```

Before returning the PDF, ZF1 must verify:

1.  User authentication.
2.  Report existence.
3.  Revision existence.
4.  User authorization to view/review the report.
5.  Applicable report/reviewer state.

The access event should be auditable.

### 13.3 Hidden Viewer Functions

The custom viewer shall not expose:

-   Download button
-   Print button
-   Open-in-external-viewer button
-   Original public PDF URL

### 13.4 Casual Browser Deterrence

Version 1 may suppress:

-   Right-click context menu
-   `Ctrl+S`
-   `Ctrl+P`
-   `F12`
-   Common Developer Tools shortcuts
-   View-source shortcuts

These controls must be documented as deterrents, not security
guarantees.

The system should **not** depend on unreliable logic that attempts to
detect Developer Tools and destroy/hide application content.

------------------------------------------------------------------------

## 14. Security Requirements

### 14.1 Authentication

All review APIs require an authenticated ZF1 user session or the
project's approved authentication mechanism.

### 14.2 Authorization

Authorization must be performed server-side.

Client-side hidden buttons are not sufficient authorization.

Examples:

-   Only permitted users may view a report.
-   Only assigned reviewers may submit review actions.
-   Only the appropriate author/staff role may submit a corrected
    revision.
-   Historical records must not be writable through normal review APIs.

### 14.3 CSRF

State-changing requests must use the project's CSRF protection
mechanism.

This includes:

-   Save annotation
-   Add/edit comment
-   Submit report
-   Create revision
-   Request changes
-   Approve revision

### 14.4 SQL

Use parameterized queries / ZF1 database adapters. Do not concatenate
user input into SQL.

### 14.5 File Validation

Uploaded report files must be validated according to project policy,
including expected PDF type, size limits, and safe storage names.

### 14.6 Auditability

Important security and workflow actions must be attributable to a user
and timestamp.

------------------------------------------------------------------------

## 15. Audit Trail

The review action/audit table should be append-only for normal
application operations.

Suggested actions:

-   `REPORT_CREATED`
-   `REVISION_CREATED`
-   `SUBMITTED`
-   `REVIEW_STARTED`
-   `COMMENT_ADDED`
-   `COMMENT_RESOLVED`
-   `ANNOTATION_SAVED`
-   `CHANGES_REQUESTED`
-   `RESUBMITTED`
-   `APPROVED`
-   `FINAL_APPROVED`
-   `PDF_VIEWED`
-   `REVIEWER_ASSIGNED`
-   `REVIEWER_CHANGED`

Each audit entry should include:

-   Report ID
-   Revision ID where applicable
-   User ID
-   Action
-   Timestamp
-   Optional metadata

------------------------------------------------------------------------

## 16. Recommended MariaDB Tables

### 16.1 `reports`

Suggested fields:

``` text
id
report_no
title
created_by
status
current_revision_id
created_at
updated_at
```

### 16.2 `report_revisions`

``` text
id
report_id
revision_no
pdf_storage_path
original_filename
file_size
file_hash
created_by
created_at
submitted_at
```

A unique constraint should prevent duplicate revision numbers within the
same report.

### 16.3 `report_reviewers`

``` text
id
report_id
revision_id
reviewer_user_id
review_order
is_required
status
assigned_at
approved_at
```

If reviewer assignments apply to the whole report rather than each
revision, the final implementation may separate report-level assignments
from revision-level review states.

### 16.4 `report_comments`

``` text
id
report_id
revision_id
reviewer_user_id
comment_text
status
created_at
resolved_at
resolved_by
```

### 16.5 `report_annotations`

``` text
id
report_id
revision_id
page_no
reviewer_user_id
schema_version
annotation_json
created_at
updated_at
```

Recommended unique logical key:

``` text
revision_id + page_no + reviewer_user_id
```

if annotations are stored as one JSON document per reviewer per page.

### 16.6 `report_review_actions`

``` text
id
report_id
revision_id
user_id
action
action_comment
metadata_json
created_at
```

This table should normally be append-only.

------------------------------------------------------------------------

## 17. Recommended API Surface

Exact routing may be adjusted to the existing ZF1 API conventions.

### Reports

``` text
GET    /api/report/get/:id
POST   /api/report/create
POST   /api/report/submit
GET    /api/report/history/:id
```

### Revisions

``` text
GET    /api/report/revision/get/:id
POST   /api/report/revision/create
GET    /api/report/pdf/view/:revisionId
```

### Comments

``` text
GET    /api/report/comment/list
POST   /api/report/comment/add
POST   /api/report/comment/resolve
POST   /api/report/comment/reopen
```

### Annotations

``` text
GET    /api/report/annotation/list
POST   /api/report/annotation/save
```

The annotation endpoints should identify at least revision, page, and
current reviewer.

### Review Actions

``` text
POST   /api/report/review/request-changes
POST   /api/report/review/approve
GET    /api/report/review/status
```

All state-changing endpoints require authorization and CSRF validation.

------------------------------------------------------------------------

## 18. ZF1 Application Structure

A possible project structure is:

``` text
application/
  controllers/
    ReportController.php

  modules/
    api/
      controllers/
        ReportController.php
        ReportRevisionController.php
        ReportCommentController.php
        ReportAnnotationController.php
        ReportReviewController.php

  models/
    DbTable/
      Reports.php
      ReportRevisions.php
      ReportReviewers.php
      ReportComments.php
      ReportAnnotations.php
      ReportReviewActions.php

library/
  S3/
    Report/
      Service.php
      RevisionService.php
      ReviewService.php
      AnnotationService.php
      AuthorizationService.php

public/
  js/
    report-review/
      viewer.js
      annotation.js
      toolbar.js
      review.js

  css/
    report-review.css
```

The exact naming should follow the existing project conventions.

Business logic should be placed in services rather than duplicated
across controllers.

------------------------------------------------------------------------

## 19. Front-End Architecture

``` text
Review Screen
|
+-- Header
|   +-- Report number/title
|   +-- Revision
|   +-- Review status
|
+-- Drawing Toolbar
|   +-- Select
|   +-- Pen
|   +-- Highlighter
|   +-- Line
|   +-- Arrow
|   +-- Rectangle
|   +-- Circle
|   +-- Text
|   +-- Delete
|   +-- Undo
|   +-- Redo
|   +-- Save
|
+-- PDF Toolbar
|   +-- Zoom Out
|   +-- Zoom %
|   +-- Zoom In
|
+-- Scrollable PDF Area
|   +-- Page 1 PDF + annotation layer
|   +-- Page 2 PDF + annotation layer
|   +-- ...
|
+-- Review Panel
    +-- Text comments
    +-- Add comment
    +-- Request Changes
    +-- Approve
    +-- Revision history
```

The drawing toolbar may be disabled in read-only historical revisions.

------------------------------------------------------------------------

## 20. Review and Approval Rules

### 20.1 Submit

When staff submits a revision:

-   Validate report state.
-   Validate PDF revision.
-   Determine/validate required reviewers.
-   Mark revision submitted.
-   Set report/current review state to `UNDER_REVIEW`.
-   Create audit event.

### 20.2 Request Changes

When a reviewer requests changes:

-   Verify reviewer assignment.
-   Store optional action comment.
-   Preserve text/drawing annotations.
-   Set that reviewer's status to `CHANGES_REQUESTED`.
-   Set report state to `CHANGES_REQUESTED` according to workflow rules.
-   Notify/flag the author.
-   Create audit event.

### 20.3 New Revision

When staff responds:

-   Keep old revision unchanged.
-   Create revision N+1.
-   Store the new PDF.
-   Establish reviewer states for the new revision.
-   Submit/resubmit.
-   Create audit events.

### 20.4 Approve

When a reviewer approves:

-   Verify current revision.
-   Verify reviewer assignment.
-   Set reviewer status to `APPROVED`.
-   Store approval timestamp.
-   Create audit event.
-   Recalculate final approval.

### 20.5 Final Approval

If all required reviewers have approved the current revision:

-   Set report status to `APPROVED`.
-   Record final approval event/time.
-   Make the approved revision immutable.
-   Present it as the final approved revision.

------------------------------------------------------------------------

## 21. Concurrency and Data Integrity

The implementation should prevent inconsistent review state when two
reviewers act at nearly the same time.

Recommended techniques:

-   Database transactions for workflow transitions.
-   Re-read current revision/status before state changes.
-   Do not trust revision IDs supplied by the browser without checking
    that they are current and authorized.
-   Use unique constraints where appropriate.
-   Consider optimistic version/state checking for critical transitions.

Annotations from separate reviewers should not overwrite one another.

------------------------------------------------------------------------

## 22. Performance Requirements

For large PDFs:

-   Render pages incrementally/lazily where practical.
-   Avoid a single document-sized Fabric canvas.
-   Maintain one annotation canvas per rendered page.
-   Avoid repeatedly loading annotation data unnecessarily.
-   Debounce frequent annotation-save operations if autosave is later
    added.
-   Release Fabric/PDF page resources when pages are intentionally
    destroyed.
-   Test memory behavior with realistically large reports.

------------------------------------------------------------------------

## 23. Browser Requirements

The application should target the organization's supported modern
browsers, including current Chromium-based browsers and Firefox.

Because current Fabric.js/PDF.js releases are designed for modern
browsers, Internet Explorer should not be treated as a supported target
for this feature.

Exact library versions should be pinned and tested before production
deployment, particularly because the surrounding DHTMLX 3.5 application
is legacy code.

------------------------------------------------------------------------

## 24. External JavaScript Dependencies

Required libraries:

-   PDF.js
-   Fabric.js
-   Existing jQuery used by the ZF1 project

The project should keep local/offline copies of approved production
versions rather than depending on public CDNs if the deployment
environment requires offline installation.

Third-party license notices should be retained according to each
library's license requirements.

------------------------------------------------------------------------

## 25. Error Handling

The client should clearly handle:

-   PDF load failure
-   Authorization failure
-   Expired login/session
-   Annotation load failure
-   Annotation save failure
-   Comment save failure
-   Approval conflict
-   Revision changed while user was reviewing
-   Network failure

A failed annotation save must not falsely display a permanent "saved"
state.

Where possible, unsaved local drawing state should remain visible so the
reviewer can retry.

------------------------------------------------------------------------

## 26. Acceptance Criteria

### PDF Viewer

-   [ ] Authorized reviewer can open the current PDF.
-   [ ] Unauthorized user cannot obtain it through the protected
    endpoint.
-   [ ] Multiple pages render correctly.
-   [ ] User can scroll continuously between pages.
-   [ ] Zoom in works.
-   [ ] Zoom out works.
-   [ ] PDF and annotation layers remain aligned after zoom.

### Drawing

-   [ ] Pen works.
-   [ ] Highlighter works.
-   [ ] Line works.
-   [ ] Arrow works.
-   [ ] Rectangle works.
-   [ ] Circle works.
-   [ ] Text annotation works.
-   [ ] Objects can be selected/edited as permitted.
-   [ ] Selected objects can be deleted.
-   [ ] Undo works.
-   [ ] Redo works.
-   [ ] Drawings can be saved.
-   [ ] Saved drawings reload on the correct page.
-   [ ] Drawings remain in the correct document position after zoom.
-   [ ] Scrolling does not alter annotation position.
-   [ ] One reviewer's annotation data does not overwrite another
    reviewer's data.

### Comments

-   [ ] Reviewer can add a text comment.
-   [ ] Staff can view comments.
-   [ ] Comment belongs to the correct revision.
-   [ ] Comment history remains available after a new revision.

### Revision Workflow

-   [ ] Initial submission creates/uses Revision 1.
-   [ ] Requesting changes does not modify Revision 1.
-   [ ] Staff can create Revision 2.
-   [ ] Revision 1 becomes historical/read-only.
-   [ ] Revision 2 receives independent review state.
-   [ ] Previous drawings remain attached to their original revision.
-   [ ] Previous comments remain attached to their original revision.

### Approval

-   [ ] Assigned reviewer can approve.
-   [ ] Unauthorized user cannot approve.
-   [ ] Individual reviewer approval is recorded.
-   [ ] Final approval occurs only when all required reviewers approve
    the current revision.
-   [ ] Final approved revision becomes immutable.

### V1 PDF Deterrence

-   [ ] No normal download button is displayed.
-   [ ] No normal print button is displayed.
-   [ ] No open-PDF button is displayed.
-   [ ] PDF is not stored under a public direct URL.
-   [ ] Common save/print/context-menu shortcuts are suppressed as
    configured.
-   [ ] Documentation states that these controls do not guarantee
    prevention of PDF extraction.

### Audit

-   [ ] Submission is logged.
-   [ ] Revision creation is logged.
-   [ ] Request changes is logged.
-   [ ] Approval is logged.
-   [ ] Final approval is logged.
-   [ ] Important events include user and timestamp.

------------------------------------------------------------------------

## 27. Recommended Implementation Phases

### Phase 1 - Foundation

-   MariaDB schema
-   ZF1 models/services
-   Report/revision records
-   Reviewer assignments
-   Protected PDF endpoint

### Phase 2 - PDF Review UI

-   PDF.js integration
-   Continuous scrolling
-   Zoom
-   Page lifecycle
-   Custom viewer controls

### Phase 3 - Drawing Annotations

-   Fabric.js integration
-   Drawing toolbar
-   Normalized coordinate model
-   Save/load JSON
-   Zoom restoration
-   Undo/redo

### Phase 4 - Review Workflow

-   Text comments
-   Request changes
-   Revision creation
-   Resubmission
-   Approval
-   Final approval calculation

### Phase 5 - History and Audit

-   Revision-history screen
-   Read-only historical viewer
-   Audit history
-   Reviewer status display

### Phase 6 - Hardening and Testing

-   Permission testing
-   CSRF testing
-   Concurrency testing
-   Large-PDF testing
-   Browser testing
-   Error/retry testing
-   PHP unit/service tests
-   JavaScript viewer/annotation tests where practical

------------------------------------------------------------------------

## 28. Future Enhancements

Possible later enhancements include:

-   Sequential approval workflows
-   Reviewer groups and dynamic routing
-   Email/realtime notifications
-   Annotation replies/discussions
-   Annotation status such as open/resolved
-   Page thumbnails
-   Search inside PDF
-   Reviewer color coding
-   Autosave annotations
-   Compare Revision N with Revision N-1
-   Digital signatures
-   Approval certificate page
-   Personalized visible watermarks
-   Server-side PDF page rendering for stronger download resistance
-   Export review history to PDF
-   Dashboard/reporting
-   Mobile/tablet pen support

------------------------------------------------------------------------

## 29. Core Architectural Decision Summary

The agreed Version 1 architecture is:

``` text
ZF1 / PHP 7.4
      |
      +-- Authentication / Authorization
      |
      +-- Report + Revision Services
      |
      +-- Review Workflow
      |
      +-- Protected PDF Endpoint
      |
      +-- REST APIs
              |
              v
        Browser Review UI
              |
       +------+------+
       |             |
     PDF.js        Fabric.js
       |             |
 PDF rendering    Drawings
 Zoom/scroll      Annotation JSON
       |             |
       +------+------+
              |
              v
           MariaDB
```

The key design principle is that **the PDF revision is immutable review
evidence**, while comments, drawings, reviewer actions, and approvals
are stored as structured records associated with that exact revision.

This provides a clear history of what was submitted, what management
requested, what staff changed, and who approved the final version.
