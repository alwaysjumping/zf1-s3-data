# Intro.js Reference Guide

**Purpose:** Offline guided tours and onboarding for a PHP 7.4 / Zend Framework 1 / DHTMLX 3.5 / jQuery intranet application.  
**Compatibility target:** Chrome 49 and Firefox 52 (must be validated against the selected Intro.js release).  
**Scope:** Practical reference and integration patterns. API examples use the classic `introJs()` interface; confirm each option and callback against your pinned release.

## 1. What is Intro.js?

Intro.js is a browser-side library for interactive product tours. It can dim the page, highlight a target element, and show an explanatory dialog with navigation controls. Typical uses include first-login onboarding, module introductions, feature announcements, and contextual assistance.

Intro.js does **not** provide user accounts, persistent progress, tour authorization, or a backend by itself. Your application implements those features.

### Tour vs. hints

- **Tour:** A sequence of spotlighted steps with Next, Back, Skip/Exit, and Done.
- **Hint:** A contextual marker or help affordance, where supported by the selected release. Verify hint APIs for your exact version.

## 2. Offline operation and deployment

Intro.js works without Internet access when its JavaScript, CSS, and any referenced assets are served locally. It does not require jQuery or a Node.js runtime in production.

Suggested deployment layout:

```text
public/
  vendor/introjs/
    intro.min.js
    introjs.min.css
    LICENSE-or-license-notice.txt
  js/s3/onboarding.js
  js/modules/email/onboarding.js
  css/onboarding.css
```

File names and distribution layout vary by release. Obtain the licensed distribution through the appropriate official channel and keep the version and license notice with your deployment artifacts. **Do not assume that free download implies permission to redistribute or use commercially.** Review the licensing terms for your exact version and usage.

Include local assets:

```html
<link rel="stylesheet" href="/vendor/introjs/introjs.min.css">
<script src="/vendor/introjs/intro.min.js"></script>
```

No CDN, external font, image, or telemetry URL should be referenced by the application or custom tour content. Verify with browser network tools while disconnected from the Internet.

## 3. Browser compatibility

**Do not assume the current Intro.js release supports Chrome 49 or Firefox 52.** These are obsolete browsers, and Babel alone cannot fix missing DOM APIs, CSS behavior, or runtime features.

For a candidate release:

1. Pin an exact version and inspect its official release notes and browser requirements.
2. Load its minified JS and CSS in real Chrome 49 and Firefox 52 test environments.
3. Test overlay stacking, positioning, scrolling, keyboard behavior, Next/Back, Skip/Done, and dynamic targets.
4. Check the browser console for syntax errors and missing APIs.
5. Record the validated version and hashes in your deployment documentation.

If no appropriately licensed and compatible release is available, consider a custom ES5 tour renderer or another library with verified compatibility.

## 4. Basic HTML and JavaScript example

```html
<button id="btnCompose" type="button">Compose</button>
<input id="txtSearch" type="text" placeholder="Search mail">
<button id="btnHelp" type="button">Show Tutorial</button>

<script>
(function () {
    'use strict';
    document.getElementById('btnHelp').onclick = function () {
        if (typeof window.introJs !== 'function') {
            alert('Tutorial is unavailable.');
            return;
        }
        var tour = introJs();
        tour.setOptions({
            steps: [
                {
                    element: document.getElementById('btnCompose'),
                    intro: 'Create a new email message.',
                    position: 'bottom'
                },
                {
                    element: document.getElementById('txtSearch'),
                    intro: 'Find messages by keyword.',
                    position: 'bottom'
                }
            ],
            showProgress: true,
            showBullets: false,
            exitOnOverlayClick: false,
            nextLabel: 'Next',
            prevLabel: 'Back',
            doneLabel: 'Finish'
        });
        tour.start();
    };
}());
</script>
```

The exact supported step properties, button labels, and options vary by version. Prefer DOM elements rather than ambiguous selectors when the controls already exist.

## 5. Common configuration concepts

| Concept | Purpose | Caution |
|---|---|---|
| `steps` | Ordered tour descriptions | Validate target existence before starting. |
| `element` | Highlight target | May be a DOM node or selector depending on version. |
| `intro` | Explanation content | Never inject untrusted HTML. |
| `position` | Preferred dialog placement | Placement may change near viewport edges. |
| `showProgress` | Step count/progress indicator | Confirm supported release. |
| `showBullets` | Step-navigation bullets | Consider disabling for controlled workflows. |
| `exitOnOverlayClick` | Control dismissal via overlay | Decide accessibility and escape behavior. |
| `nextLabel`, `prevLabel`, `doneLabel` | Button text | Localize as needed. |

Other options, including scroll behavior, keyboard navigation, tooltip styling, and highlight classes, are release-specific; consult the pinned version's documentation.

## 6. Tour lifecycle

A typical lifecycle is:

```text
Page/module opens
    -> UI finishes rendering
    -> check user's tour status
    -> offer welcome / start tour
    -> show steps
    -> complete, skip, or dismiss
    -> save status
```

Classic Intro.js releases provide callbacks such as `oncomplete` and `onexit`. **Do not equate `onexit` with an explicit Skip action without verifying the exact release and dismissal path.** Escape, exit buttons, overlay clicks, and programmatic exits may share callbacks.

A robust application should distinguish:

- `not_started`: no record exists;
- `in_progress`: user began the tour;
- `completed`: all required steps finished;
- `skipped`: user explicitly declined;
- `dismissed`: tour closed without a final decision.

If the renderer cannot reliably distinguish Skip from Exit, use a single `exited` status or add an explicit application-controlled Skip button. Do not falsely record completion.

## 7. Centralized onboarding facade

Business modules should depend on your own facade, **not** directly on Intro.js:

```javascript
S3.Onboarding.register('email.basic', {
    version: 1,
    steps: [
        { element: '#btnCompose', intro: 'Create a message.' },
        { element: '#txtSearch', intro: 'Search messages.' }
    ]
});

S3.Onboarding.start('email.basic');       // manual start
S3.Onboarding.autoStart('email.basic');   // checks server status first
```

`S3.Onboarding` is a proposed application API, **not** an Intro.js API. The facade should own tour registration, target resolution, renderer adaptation, error handling, and progress persistence.

### Safe step resolution

```javascript
function resolveSteps(definition) {
    var result = [];
    var i, step, node;
    for (i = 0; i < definition.steps.length; i++) {
        step = definition.steps[i];
        node = document.querySelector(step.element);
        if (!node) { continue; }
        result.push({ element: node, intro: step.intro,
                      position: step.position || 'bottom' });
    }
    return result;
}
```

For hidden, unauthorized, or unloaded controls, skip the step or display a safe fallback. Never expose an unauthorized feature merely to satisfy a tutorial.

## 8. Automatic first-use tours

Do not start a tour until the UI is ready. Query server-side progress before automatic startup:

```text
GET /api/onboarding/status?tour=email.basic
```

Illustrative response:

```json
{
  "success": true,
  "data": {
    "tour": "email.basic",
    "version": 1,
    "status": "not_started",
    "auto_start": true
  }
}
```

Manual restart should work even after completion. Automatic restart policy must be defined separately for new versions and feature announcements. Avoid repeatedly interrupting users.

## 9. MariaDB persistence

Minimal schema:

```sql
CREATE TABLE s3_onboarding_tours (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tour_key VARCHAR(100) NOT NULL,
  module VARCHAR(50) NOT NULL,
  title VARCHAR(200) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  auto_start TINYINT(1) NOT NULL DEFAULT 1,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tour_key (tour_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE s3_onboarding_progress (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  tour_id INT UNSIGNED NOT NULL,
  tour_version INT UNSIGNED NOT NULL,
  status ENUM('in_progress','completed','skipped','dismissed')
    NOT NULL DEFAULT 'in_progress',
  last_step INT UNSIGNED NOT NULL DEFAULT 0,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tour_version (user_id, tour_id, tour_version),
  KEY idx_user_status (user_id, status),
  CONSTRAINT fk_onboarding_progress_tour
    FOREIGN KEY (tour_id) REFERENCES s3_onboarding_tours(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A missing progress row means `not_started`. Use an authenticated user ID from the server session/token, not a client-supplied user ID.

## 10. ZF1 REST API contract

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/onboarding/status?tour=...` | Fetch the authenticated user's current status. |
| GET | `/api/onboarding/list` | List permitted and enabled tours. |
| POST | `/api/onboarding/progress` | Record an allowed state transition. |
| POST | `/api/onboarding/reset` | Reset current user's tour if permitted. |

Progress payload (illustrative):

```json
{ "tour": "email.basic", "version": 1,
  "status": "completed", "last_step": 4 }
```

Server requirements: authentication, authorization, CSRF protection for session-based POST requests, tour/version validation, permitted state transitions, parameterized SQL, idempotent updates, and appropriate error responses. A stale client must not overwrite a newer tour's progress.

## 11. DHTMLX 3.5 integration

DHTMLX forms, grids, and toolbars create DOM nodes dynamically. Start only after the target widgets render. Where a control lacks a stable ID, implement an adapter that maps a logical target (such as `email.toolbar.compose`) to a current DOM node. Avoid depending on undocumented DHTMLX class names.

When a target is inside a collapsed panel, open the panel first and wait for rendering before advancing. When a DHTMLX window opens, recalculate tour placement. Re-test overlay `z-index` and clipping with DHTMLX windows.

## 12. Iframe integration

**Simplest:** run the tour within the module iframe. Its overlay is limited to that document.

**Advanced:** use a parent-level renderer with a same-origin iframe target resolver. Convert target rectangles from iframe coordinates to parent viewport coordinates, accounting for scrolling, zoom, borders, and resizing. Cross-origin iframes require message passing and cannot be inspected directly.

Begin with per-iframe tours; add cross-frame tours only when necessary.

## 13. Multi-tab behavior

Prevent redundant automatic tours when a user opens the same module in several tabs. Chrome 49 does not support BroadcastChannel, so do not rely on it exclusively. A `localStorage` notification/lease can improve coordination, but it is not an atomic lock. Keep authoritative progress on the server, re-check status before automatic startup, and tolerate races gracefully.

## 14. Security and accessibility

- Treat help content as untrusted unless reviewed; escape text or sanitize allowed HTML.
- Do not put secrets, private user data, or privileged UI explanations into public tour definitions.
- Never let a tutorial bypass authorization checks.
- Use CSRF protection, authentication, and server-side validation on progress APIs.
- Keep keyboard navigation, visible focus, sufficient contrast, and screen-reader announcements in mind; audit the chosen Intro.js release rather than assuming full accessibility.
- Allow users to skip nonmandatory tours and restart them later.
- Avoid interactive tours that send real emails, delete records, or trigger irreversible actions during training.

## 15. Testing checklist

| Area | Test |
|---|---|
| Offline | Disconnect Internet; no external network requests. |
| Compatibility | Real Chrome 49 and Firefox 52 tests. |
| Basic flow | Next, Back, Finish, explicit Skip, Exit. |
| Dynamic UI | Wait for DHTMLX initialization. |
| Missing controls | Tour does not crash or hang. |
| Scroll | Tooltip remains visible after scrolling. |
| Windows | Overlay and tooltip appear above DHTMLX windows. |
| Iframes | Tour targets correct document. |
| Persistence | First-use tour is not repeated after completion. |
| Restart | Manual Help action works after completion. |
| Multi-tab | Avoid repeated automatic prompts where possible. |
| Security | Authentication, CSRF, XSS, and authorization checks. |
| Licensing | License covers intended internal/commercial deployment. |

## 16. Troubleshooting

**`introJs is not defined`** — Check script path, load order, syntax compatibility, and browser console.

**Tooltip appears in wrong position** — Confirm target is visible, rendered, and not transformed/clipped by ancestor CSS; retest scrolling and window resizing.

**Target is not highlighted** — Check selector, iframe document, hidden state, and DHTMLX render timing.

**Tour repeats on every visit** — Inspect status API response, failed POST requests, stale tour versions, and the distinction between skipped/dismissed.

**Works on modern browsers but not Chrome 49** — Check syntax/API compatibility of the actual Intro.js build and CSS; do not assume Babel fixes all issues.

**Tour obscured by DHTMLX window** — Audit stacking contexts and `z-index`; avoid arbitrary global overrides that break dialogs.

## 17. Suggested delivery roadmap

1. **Prototype:** One locally hosted, manually launched Email tour.
2. **Persistence:** MariaDB progress and first-use auto-start.
3. **Integration:** DHTMLX target adapters and iframe handling.
4. **Reliability:** Missing targets, errors, multi-tab behavior, accessibility.
5. **Expansion:** Tour versioning, What's New, localization, and optional administration.

## 18. Official resources

- Intro.js product and documentation: https://introjs.com/
- Intro.js source and release history: https://github.com/usablica/intro.js

**Important:** Consult the exact version's documentation and license before deploying. This guide intentionally does not claim that a specific release is compatible with Chrome 49 / Firefox 52 or that commercial redistribution is free.
