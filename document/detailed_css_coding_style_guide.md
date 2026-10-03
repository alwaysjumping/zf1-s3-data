# Detailed CSS Coding Style Guide

## For Maintainable CSS, DHTMLX 3.5, and Gradual Frontend Modernization

This guide defines a detailed CSS coding style for a project that
contains both application CSS and legacy frontend code such as DHTMLX
3.5.

The main goals are:

``` text
Readable
   +
Consistent
   +
Reusable
   +
Predictable
   +
Responsive
   +
Maintainable
```

> **Core rule:** Keep CSS selectors simple, component-oriented,
> predictable, and easy to override without continually increasing
> specificity.

------------------------------------------------------------------------

# 1. Main Goals

CSS is easy to write initially but can become difficult to maintain
because rules interact through the cascade, inheritance, specificity,
and source order.

Avoid accumulating selectors such as:

``` css
#main #content div.box ul li a {
    color: blue;
}
```

Prefer meaningful component selectors:

``` css
.menu-link {
    color: blue;
}
```

A maintainable CSS architecture should make it easy to answer:

> Which CSS rule controls this element, and why?

------------------------------------------------------------------------

# 2. Basic Formatting

Write readable source CSS:

``` css
.email-card {
    padding: 16px;
    border: 1px solid #ccc;
}
```

Avoid compressed source code:

``` css
.email-card{padding:16px;border:1px solid #ccc;}
```

Minification belongs in the build/deployment process, not in
developer-edited source files.

------------------------------------------------------------------------

# 3. Indentation

Use **4 spaces** for indentation in this project.

``` css
.email-card {
    padding: 16px;
    border: 1px solid #ccc;
}
```

For nested structures:

``` css
@media (max-width: 768px) {
    .email-card {
        padding: 12px;
    }
}
```

This keeps CSS visually consistent with the project's PHP and JavaScript
standards.

------------------------------------------------------------------------

# 4. One Declaration Per Line

Good:

``` css
.email-card {
    display: flex;
    padding: 16px;
    margin-bottom: 12px;
    border: 1px solid #ccc;
}
```

Avoid:

``` css
.email-card {
    display: flex; padding: 16px; margin-bottom: 12px;
}
```

One declaration per line improves readability, debugging, and Git diffs.

------------------------------------------------------------------------

# 5. Always Use Semicolons

Use:

``` css
.email-card {
    color: #333;
    background-color: #fff;
}
```

Avoid:

``` css
.email-card {
    color: #333;
    background-color: #fff
}
```

Even when the final semicolon is technically optional, keeping it makes
declarations easier to reorder and extend.

------------------------------------------------------------------------

# 6. Space After the Colon

Good:

``` css
color: #333;
padding: 16px;
display: flex;
```

Avoid:

``` css
color:#333;
padding:16px;
```

------------------------------------------------------------------------

# 7. Space Before Opening Brace

Good:

``` css
.email-card {
}
```

Avoid:

``` css
.email-card{
}
```

------------------------------------------------------------------------

# 8. Closing Brace on Its Own Line

Use:

``` css
.email-card {
    padding: 16px;
}
```

Avoid:

``` css
.email-card {
    padding: 16px; }
```

------------------------------------------------------------------------

# 9. Blank Lines Between Rule Sets

Good:

``` css
.email-card {
    padding: 16px;
}

.email-card-title {
    font-weight: 600;
}

.email-card-body {
    margin-top: 8px;
}
```

Blank lines make individual components easier to scan.

------------------------------------------------------------------------

# 10. Selector Naming

Use lowercase names with hyphens.

Good:

``` css
.email-card {
}

.email-list {
}

.notification-count {
}

.document-viewer {
}
```

Avoid inconsistent naming:

``` css
.emailCard {
}

.EmailCard {
}

.email_card {
}
```

Use **kebab-case** as the normal CSS class convention.

------------------------------------------------------------------------

# 11. Use Meaningful Names

Avoid names based only on appearance:

``` css
.red-box {
}

.big-text {
}

.left-panel {
}
```

These names become misleading when the design changes.

Prefer purpose-based names:

``` css
.error-message {
}

.page-title {
}

.navigation-panel {
}
```

Names should describe what an element represents rather than merely its
current appearance.

------------------------------------------------------------------------

# 12. Component-Oriented Naming

Think in reusable components:

``` css
.email-card {
}

.email-card-title {
}

.email-card-body {
}

.email-card-footer {
}
```

HTML:

``` html
<div class="email-card">
    <h2 class="email-card-title">
        Meeting
    </h2>

    <div class="email-card-body">
        Meeting tomorrow at 10:00.
    </div>

    <div class="email-card-footer">
        ...
    </div>
</div>
```

Structure:

``` text
email-card
    ├── email-card-title
    ├── email-card-body
    └── email-card-footer
```

------------------------------------------------------------------------

# 13. Consider a BEM-Like Convention

BEM means:

``` text
Block
Element
Modifier
```

Example:

``` css
.email-card {
}

.email-card__title {
}

.email-card__body {
}

.email-card--unread {
}
```

HTML:

``` html
<div class="email-card email-card--unread">
    <h2 class="email-card__title">
        Meeting
    </h2>

    <div class="email-card__body">
        ...
    </div>
</div>
```

Strict BEM is not required. The important goal is predictable component
naming.

Either:

``` css
.email-card-title
```

or:

``` css
.email-card__title
```

can work. Choose one convention and use it consistently.

------------------------------------------------------------------------

# 14. Avoid IDs for Styling

Avoid:

``` css
#emailForm {
    padding: 20px;
}
```

Prefer:

``` css
.email-form {
    padding: 20px;
}
```

IDs have high specificity and are harder to override.

Use IDs primarily for needs such as:

``` text
JavaScript hooks
Fragment navigation
Accessibility relationships
Unique document identifiers
```

Use classes for normal styling.

------------------------------------------------------------------------

# 15. Keep Specificity Low

Avoid:

``` css
#main #content .email-list ul li a.email-link {
    color: #333;
}
```

Prefer:

``` css
.email-link {
    color: #333;
}
```

High specificity creates escalation:

``` text
Original selector
      ↓
Too specific
      ↓
Override needs more specificity
      ↓
Next override needs even more
      ↓
!important
      ↓
CSS becomes difficult to maintain
```

Keep selectors as simple as practical.

------------------------------------------------------------------------

# 16. Avoid Deep Selector Nesting

Avoid:

``` css
.page .content .email-list .email-row .email-title a {
    text-decoration: none;
}
```

Prefer:

``` css
.email-title-link {
    text-decoration: none;
}
```

A practical goal is usually shallow selectors rather than long DOM
chains.

------------------------------------------------------------------------

# 17. Avoid Styling by DOM Structure Unnecessarily

Fragile:

``` css
.email-card div span {
    color: #666;
}
```

Better:

``` css
.email-card-date {
    color: #666;
}
```

CSS should not depend unnecessarily on exact DOM hierarchy.

------------------------------------------------------------------------

# 18. Avoid `!important`

Avoid:

``` css
.email-title {
    color: red !important;
}
```

`!important` often indicates a specificity or architecture problem.

Prefer fixing:

``` text
Selector specificity
Source order
Component boundaries
Vendor overrides
```

There are legitimate cases, especially with difficult third-party
styles, but `!important` should have a clear reason.

------------------------------------------------------------------------

# 19. Separate Vendor CSS

For a DHTMLX application:

``` text
public/
└── css/
    ├── vendor/
    │   └── dhtmlx/
    │
    └── app/
        ├── base.css
        ├── layout.css
        ├── components.css
        └── pages/
```

Do not modify the original DHTMLX stylesheet unless absolutely
necessary.

Create application override files instead.

This makes upgrades and debugging much easier.

------------------------------------------------------------------------

# 20. Separate Vendor Overrides

Recommended:

``` text
css/
├── vendor/
│   └── dhtmlx/
│
├── app/
│   ├── base.css
│   ├── layout.css
│   ├── components.css
│   └── utilities.css
│
└── overrides/
    └── dhtmlx.css
```

Responsibilities:

``` text
Vendor CSS
      ↓
Original library styling

Application CSS
      ↓
Your components

Override CSS
      ↓
Intentional third-party adjustments
```

------------------------------------------------------------------------

# 21. Organize CSS by Responsibility

Example:

``` text
css/
├── base/
│   ├── reset.css
│   ├── typography.css
│   └── variables.css
│
├── layout/
│   ├── page.css
│   ├── header.css
│   └── sidebar.css
│
├── components/
│   ├── button.css
│   ├── dialog.css
│   ├── email-card.css
│   └── notification.css
│
├── pages/
│   ├── email.css
│   └── document-viewer.css
│
├── overrides/
│   └── dhtmlx.css
│
└── utilities/
    └── utilities.css
```

This is easier to maintain than a single huge stylesheet.

------------------------------------------------------------------------

# 22. Base Styles

Base CSS contains broad application defaults.

``` css
html {
    box-sizing: border-box;
}

*,
*::before,
*::after {
    box-sizing: inherit;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
}
```

Keep page-specific rules out of the base layer.

------------------------------------------------------------------------

# 23. Use `box-sizing: border-box`

Recommended baseline:

``` css
html {
    box-sizing: border-box;
}

*,
*::before,
*::after {
    box-sizing: inherit;
}
```

With `border-box`, declared width includes padding and borders, making
layouts easier to reason about.

------------------------------------------------------------------------

# 24. Property Ordering

Choose a predictable property order.

A useful convention:

``` text
1. Positioning
2. Display / layout
3. Box model
4. Typography
5. Visual appearance
6. Animation / transition
7. Miscellaneous
```

Example:

``` css
.email-card {
    position: relative;

    display: flex;
    align-items: center;

    width: 100%;
    padding: 16px;
    margin-bottom: 12px;

    font-size: 14px;
    line-height: 1.5;

    color: #333;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;

    transition: background-color 0.2s ease;
}
```

The exact ordering scheme matters less than consistent use.

------------------------------------------------------------------------

# 25. Group Related Properties

Keep related declarations together:

``` css
.dialog {
    width: 600px;
    max-width: 100%;
    padding: 24px;
    margin: 0 auto;
}
```

Avoid random property ordering.

------------------------------------------------------------------------

# 26. Use Shorthand Carefully

Useful:

``` css
margin: 0 auto;
padding: 8px 16px;
```

But shorthand can reset related values.

For example:

``` css
background: #fff;
```

can reset other background properties.

If only the color should change:

``` css
background-color: #fff;
```

is more precise.

------------------------------------------------------------------------

# 27. Zero Values

Prefer:

``` css
margin: 0;
padding: 0;
```

instead of:

``` css
margin: 0px;
padding: 0px;
```

Zero normally does not need a unit.

------------------------------------------------------------------------

# 28. Decimal Values

Prefer:

``` css
opacity: 0.5;
```

rather than:

``` css
opacity: .5;
```

A leading zero improves readability.

------------------------------------------------------------------------

# 29. Colors

Use consistent color notation.

Example:

``` css
color: #333;
background-color: #fff;
border-color: #ddd;
```

As the application grows, centralize repeated values:

``` css
:root {
    --color-text: #333;
    --color-background: #fff;
    --color-border: #ddd;
    --color-danger: #c00;
}
```

Usage:

``` css
.email-card {
    color: var(--color-text);
    background-color: var(--color-background);
    border-color: var(--color-border);
}
```

------------------------------------------------------------------------

# 30. CSS Custom Properties

Custom properties can define design tokens:

``` css
:root {
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;

    --font-size-sm: 12px;
    --font-size-md: 14px;
    --font-size-lg: 18px;

    --border-radius-sm: 4px;
    --border-radius-md: 8px;
}
```

Usage:

``` css
.email-card {
    padding: var(--spacing-md);
    border-radius: var(--border-radius-sm);
}
```

For legacy applications, check browser compatibility before relying on
custom properties without fallbacks.

------------------------------------------------------------------------

# 31. Avoid Magic Values

Hard to understand:

``` css
.notification {
    top: 73px;
    right: 17px;
}
```

If these are meaningful layout constants:

``` css
:root {
    --header-height: 72px;
    --page-gutter: 16px;
}
```

Then:

``` css
.notification {
    top: var(--header-height);
    right: var(--page-gutter);
}
```

------------------------------------------------------------------------

# 32. Use a Spacing Scale

Avoid random spacing values throughout the application.

A possible scale:

``` text
4px
8px
12px
16px
24px
32px
```

Example:

``` css
.form-field {
    margin-bottom: 16px;
}

.form-help {
    margin-top: 4px;
}

.page-section {
    margin-bottom: 32px;
}
```

A spacing system gives the UI visual consistency.

------------------------------------------------------------------------

# 33. Typography

Define common typography centrally.

``` css
body {
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
}
```

Component heading:

``` css
.page-title {
    margin: 0 0 16px;

    font-size: 24px;
    font-weight: 600;
    line-height: 1.2;
}
```

Avoid repeatedly redefining the same font family.

------------------------------------------------------------------------

# 34. Prefer Unitless `line-height`

Good:

``` css
body {
    line-height: 1.5;
}
```

A unitless line height scales naturally when font sizes change.

------------------------------------------------------------------------

# 35. Flexbox

Use Flexbox for appropriate one-dimensional layouts.

``` css
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
```

Conceptually:

``` text
Flexbox
    ↓
Row or column layout
```

------------------------------------------------------------------------

# 36. CSS Grid

Grid is useful for two-dimensional layouts.

``` css
.dashboard {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 16px;
}
```

Conceptually:

``` text
Flexbox
    ↓
One-dimensional

Grid
    ↓
Rows + columns
```

Check compatibility before replacing legacy layout techniques.

------------------------------------------------------------------------

# 37. Avoid Floats for New Layouts

Legacy:

``` css
.sidebar {
    float: left;
    width: 240px;
}

.content {
    margin-left: 260px;
}
```

Modern:

``` css
.page-layout {
    display: flex;
}

.sidebar {
    width: 240px;
}

.content {
    flex: 1;
}
```

Do not rewrite stable layouts solely for style, but use modern layout
tools for new components where appropriate.

------------------------------------------------------------------------

# 38. Responsive Design

Example:

``` css
.page-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 16px;
}

@media (max-width: 768px) {
    .page-layout {
        grid-template-columns: 1fr;
    }
}
```

Avoid creating many arbitrary breakpoints.

Prefer a small, documented breakpoint set.

------------------------------------------------------------------------

# 39. Mobile-First Approach

For new responsive components:

``` css
.email-list {
    display: block;
}

@media (min-width: 768px) {
    .email-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
```

This can be easier to maintain than repeatedly overriding desktop
styles.

For existing desktop-focused legacy pages, gradual migration may be more
practical.

------------------------------------------------------------------------

# 40. Media Query Organization

A component can keep its responsive rules nearby:

``` css
.email-card {
    padding: 16px;
}

@media (max-width: 768px) {
    .email-card {
        padding: 12px;
    }
}
```

Alternatively, the project may use dedicated responsive files.

Choose one convention and apply it consistently.

------------------------------------------------------------------------

# 41. Avoid Unnecessary Fixed Widths

Fragile:

``` css
.email-form {
    width: 837px;
}
```

More flexible:

``` css
.email-form {
    width: 100%;
    max-width: 840px;
}
```

Use fixed dimensions only when genuinely required.

------------------------------------------------------------------------

# 42. Images

Responsive baseline:

``` css
img {
    max-width: 100%;
    height: auto;
}
```

Component image:

``` css
.user-avatar {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
}
```

------------------------------------------------------------------------

# 43. Buttons

Create reusable button classes:

``` css
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 8px 16px;

    font: inherit;

    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 4px;
}
```

Variants:

``` css
.button--primary {
    /* Primary action appearance. */
}

.button--danger {
    /* Destructive action appearance. */
}
```

Reusable components are easier to maintain than unrelated page-specific
button rules.

------------------------------------------------------------------------

# 44. State Classes

Use recognizable state names:

``` css
.is-active {
}

.is-disabled {
}

.is-loading {
}

.is-hidden {
}

.has-error {
}
```

Example:

``` css
.email-card.is-unread {
    font-weight: 600;
}
```

State classes represent temporary UI state.

------------------------------------------------------------------------

# 45. Separate JavaScript Hooks from Visual Classes

Avoid coupling JavaScript behavior to visual class names.

A useful pattern:

``` html
<button class="button button--primary js-save-email">
    Save
</button>
```

CSS:

``` css
.button {
}

.button--primary {
}
```

JavaScript hook:

``` text
js-save-email
```

Do not style `.js-*` classes.

Conceptually:

``` text
CSS class
    ↓
Appearance

JS hook
    ↓
Behavior
```

------------------------------------------------------------------------

# 46. Accessibility and Focus Styles

Do not remove focus indicators without providing an accessible
replacement.

Avoid:

``` css
button:focus {
    outline: none;
}
```

Better:

``` css
button:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}
```

Keyboard users need a visible indication of focus.

------------------------------------------------------------------------

# 47. Hover Is Not Enough

Important information or behavior should not exist only on hover.

Users may interact using:

``` text
Mouse
Keyboard
Touchscreen
Assistive technology
```

Example:

``` css
.email-link:hover,
.email-link:focus-visible {
    text-decoration: underline;
}
```

------------------------------------------------------------------------

# 48. Disabled States

Example:

``` css
.button:disabled,
.button.is-disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
```

Visual styling alone does not disable functionality. HTML/JavaScript
behavior must enforce the disabled state.

------------------------------------------------------------------------

# 49. Form Fields

Create consistent form components:

``` css
.form-field {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    margin-bottom: 4px;

    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 8px 12px;

    font: inherit;

    border: 1px solid #ccc;
    border-radius: 4px;
}
```

------------------------------------------------------------------------

# 50. Error States

Example:

``` css
.form-control.has-error {
    border-color: #c00;
}

.form-error {
    margin-top: 4px;

    color: #c00;
    font-size: 12px;
}
```

Do not communicate errors through color alone. Text, icons, or another
indicator should also communicate the problem.

------------------------------------------------------------------------

# 51. Avoid Overly Generic Class Names

Avoid in large applications:

``` css
.title {
}

.content {
}

.item {
}

.box {
}
```

Prefer:

``` css
.email-card-title {
}

.notification-item {
}

.document-viewer-toolbar {
}
```

Specific component names reduce collisions and improve searchability.

------------------------------------------------------------------------

# 52. Utility Classes

Small utility classes can be useful.

``` css
.u-hidden {
    display: none;
}

.u-text-center {
    text-align: center;
}

.u-margin-bottom-0 {
    margin-bottom: 0;
}
```

A prefix such as `u-` makes utilities recognizable.

Do not create hundreds of one-off utility classes without a deliberate
system.

------------------------------------------------------------------------

# 53. Prefer Semantic Component Classes

Avoid:

``` html
<div class="red bold left">
```

Prefer:

``` html
<div class="validation-message validation-message--error">
```

Classes should communicate UI purpose rather than only presentation.

------------------------------------------------------------------------

# 54. Comments

Useful comments explain non-obvious reasons.

Good:

``` css
/*
 * Keep the toolbar above the document canvas because
 * the canvas uses its own positioned rendering layer.
 */
.document-toolbar {
    z-index: 20;
}
```

Less useful:

``` css
/* Set z-index. */
.document-toolbar {
    z-index: 20;
}
```

------------------------------------------------------------------------

# 55. Section Comments

For larger legacy stylesheets:

``` css
/* ==========================================================================
   Email Form
   ========================================================================== */
```

Section comments can help navigation.

If a file becomes extremely large, splitting it is usually better than
adding many section comments.

------------------------------------------------------------------------

# 56. TODO Comments

Good:

``` css
/*
 * TODO: Remove this DHTMLX grid override after the grid
 * component is migrated to the new layout.
 */
```

Avoid:

``` css
/* TODO fix */
```

Explain what needs to change and, when useful, why.

------------------------------------------------------------------------

# 57. Do Not Keep Dead CSS Commented Out

Avoid:

``` css
/*
.old-panel {
    width: 700px;
    float: left;
}
*/
```

Version control already stores history.

Delete dead CSS when you are confident it is unused.

------------------------------------------------------------------------

# 58. Be Careful When Deleting Legacy CSS

Before deleting a legacy selector, check:

``` text
HTML templates
PHP-generated HTML
JavaScript
DHTMLX-generated elements
Dynamic class names
AJAX-loaded content
```

Unused CSS can be difficult to identify because selectors may be
generated dynamically.

------------------------------------------------------------------------

# 59. Vendor Overrides

Scope DHTMLX overrides as narrowly as practical.

For example:

``` css
.email-page .dhx_some_vendor_class {
    /* Intentional override. */
}
```

This reduces accidental effects on unrelated DHTMLX screens.

------------------------------------------------------------------------

# 60. Document Unusual Overrides

Example:

``` css
/*
 * DHTMLX 3.5 sets a fixed row height that clips the
 * two-line notification text used on this screen.
 */
.notification-grid .some-dhtmlx-row-class {
    min-height: 40px;
}
```

This prevents a future developer from removing a necessary override
without understanding it.

------------------------------------------------------------------------

# 61. Avoid Inline Styles

Avoid:

``` html
<div style="color: red; margin-top: 10px;">
```

Prefer:

``` html
<div class="error-message">
```

CSS:

``` css
.error-message {
    margin-top: 10px;
    color: #c00;
}
```

Inline styles are harder to reuse, override, search, and maintain.

------------------------------------------------------------------------

# 62. JavaScript Should Not Set Presentation Unnecessarily

Avoid:

``` javascript
element.style.color = 'red';
element.style.display = 'none';
element.style.fontWeight = 'bold';
```

Prefer state classes:

``` javascript
element.classList.add('has-error');
element.classList.add('is-hidden');
```

CSS:

``` css
.has-error {
    /* Error appearance. */
}

.is-hidden {
    display: none;
}
```

Keep presentation in CSS and behavior in JavaScript.

------------------------------------------------------------------------

# 63. CSS and JavaScript Responsibilities

Think of the layers as:

``` text
HTML
    ↓
Structure

CSS
    ↓
Appearance / layout

JavaScript
    ↓
Behavior / state changes
```

JavaScript can add/remove state classes, while CSS defines how those
states appear.

------------------------------------------------------------------------

# 64. Z-Index Management

Avoid arbitrary values:

``` css
.dialog {
    z-index: 999999;
}
```

Use a documented scale:

``` text
Base content       0
Sticky content    10
Dropdown          20
Overlay           30
Dialog            40
Notification      50
```

With custom properties where supported:

``` css
:root {
    --z-sticky: 10;
    --z-dropdown: 20;
    --z-overlay: 30;
    --z-dialog: 40;
    --z-notification: 50;
}
```

Usage:

``` css
.dialog {
    z-index: var(--z-dialog);
}
```

------------------------------------------------------------------------

# 65. Transitions

Keep transitions specific.

Avoid:

``` css
.button {
    transition: all 0.3s;
}
```

Prefer:

``` css
.button {
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}
```

`transition: all` may animate properties that were never intended to
animate.

------------------------------------------------------------------------

# 66. Animation

Use animation intentionally.

``` css
.notification {
    animation: notification-enter 0.2s ease;
}

@keyframes notification-enter {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

Animations should support usability rather than distract from it.

------------------------------------------------------------------------

# 67. Reduced Motion

For motion-heavy interfaces, respect reduced-motion preferences:

``` css
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto;
        animation-duration: 0.01ms;
        animation-iteration-count: 1;
        transition-duration: 0.01ms;
    }
}
```

------------------------------------------------------------------------

# 68. Avoid Browser-Specific Hacks Where Possible

Prefer standards-based CSS.

If an old browser requires a compatibility hack, isolate and document it
clearly rather than scattering browser-specific workarounds throughout
the application.

------------------------------------------------------------------------

# 69. Browser Compatibility

Before introducing a modern feature, consider required browsers.

Examples:

``` text
CSS Grid
CSS custom properties
gap
:focus-visible
Modern selectors
Container queries
```

Modern features can be appropriate for new code, but compatibility
should be explicitly decided for a legacy DHTMLX application.

------------------------------------------------------------------------

# 70. Progressive Enhancement

Prefer:

``` text
Basic usable layout
        ↓
Works broadly
        ↓
Modern CSS enhances it
```

when practical.

Do not make an important workflow completely unusable merely because one
optional advanced CSS feature is unsupported.

------------------------------------------------------------------------

# 71. Separate Source and Minified CSS

Recommended:

``` text
css/
├── src/
│   ├── base.css
│   ├── layout.css
│   └── components.css
│
└── dist/
    └── app.min.css
```

Developers edit:

``` text
src/
```

Deployment uses:

``` text
dist/
```

Never manually edit minified CSS.

------------------------------------------------------------------------

# 72. CSS Minification

Source:

``` css
.email-card {
    padding: 16px;
    margin-bottom: 12px;
}
```

A minifier may produce:

``` css
.email-card{padding:16px;margin-bottom:12px}
```

Humans should work with readable source CSS; deployment can serve
optimized/minified output.

------------------------------------------------------------------------

# 73. Source Maps

When CSS is transformed or minified:

``` text
Source CSS
    ↓
Build / minify
    ↓
app.min.css
    +
source map
    ↓
Browser developer tools
    ↓
Original source location
```

Source maps make debugging generated CSS easier.

------------------------------------------------------------------------

# 74. Stylelint

A CSS linter such as Stylelint can enforce many rules automatically.

Conceptually:

``` text
CSS source
    ↓
Stylelint
    │
    ├── Invalid syntax
    ├── Duplicate properties
    ├── Naming problems
    ├── Style inconsistencies
    └── Suspicious patterns
```

For legacy CSS, start with report/check mode rather than automatically
rewriting the whole repository.

------------------------------------------------------------------------

# 75. Formatter vs Linter vs Visual Testing

Different tools solve different problems:

``` text
Formatter
    ↓
Code appearance

Stylelint
    ↓
CSS rules and suspicious patterns

Browser testing
    ↓
Actual rendering behavior

Visual regression tests
    ↓
Unexpected UI changes
```

CSS can be syntactically correct and still visually break a page.

------------------------------------------------------------------------

# 76. Avoid Accidental Duplicate Declarations

Example:

``` css
.email-card {
    padding: 12px;
    margin: 10px;
    padding: 20px;
}
```

Only the later `padding` applies.

A linter can detect many accidental duplicates.

Intentional fallbacks should be clear and documented when necessary.

------------------------------------------------------------------------

# 77. Avoid Scattered Duplicate Selectors

Hard to maintain:

``` css
.email-card {
    padding: 16px;
}

/* Many lines later */

.email-card {
    border: 1px solid #ccc;
}
```

Prefer keeping a component's normal styling together unless there is a
clear architectural reason for an override.

------------------------------------------------------------------------

# 78. Understand the Cascade

When multiple rules match, CSS considers factors including:

``` text
Origin / importance
        ↓
Specificity
        ↓
Source order
```

Example:

``` css
.email-title {
    color: blue;
}

.page .email-title {
    color: red;
}
```

The second selector is more specific.

Understanding the cascade reduces unnecessary `!important` usage.

------------------------------------------------------------------------

# 79. Understand Inheritance

Many typography properties inherit.

Example:

``` css
.email-card {
    color: #333;
}
```

Child elements may inherit this color.

Properties such as margin, padding, and border do not behave the same
way.

Use inheritance intentionally.

------------------------------------------------------------------------

# 80. Component Boundaries

A component should primarily style itself and its own internal elements.

Good:

``` css
.email-card {
}

.email-card-title {
}

.email-card-body {
}
```

Avoid components making broad assumptions about unrelated descendants.

The more isolated a component is, the easier it is to reuse.

------------------------------------------------------------------------

# 81. Page-Specific CSS

Use page-specific rules when a style genuinely belongs only to one page.

``` css
.email-page {
}

.email-page-toolbar {
}

.email-page-grid {
}
```

Do not put page-specific hacks into generic component files.

------------------------------------------------------------------------

# 82. Reset / Normalization

Browsers provide default styles for headings, lists, buttons, forms, and
other elements.

A small normalization layer can reduce differences.

Simple example:

``` css
body {
    margin: 0;
}
```

Do not aggressively reset everything without understanding accessibility
and browser effects.

------------------------------------------------------------------------

# 83. Avoid Broad Universal Overrides Without Reason

This is useful:

``` css
*,
*::before,
*::after {
    box-sizing: inherit;
}
```

But a rule such as:

``` css
* {
    margin: 0;
    padding: 0;
    border: 0;
    outline: 0;
}
```

can have unintended consequences.

Use global selectors carefully.

------------------------------------------------------------------------

# 84. Print Styles

If pages may be printed:

``` css
@media print {
    .navigation,
    .toolbar,
    .button {
        display: none;
    }

    .document-content {
        width: 100%;
    }
}
```

Do not assume screen CSS automatically produces a good printed result.

------------------------------------------------------------------------

# 85. CSS Is Not a Security Boundary

For example:

``` css
.no-copy {
    user-select: none;
}
```

may discourage casual text selection, but it does not securely prevent
data extraction.

Likewise:

``` css
.hidden {
    display: none;
}
```

does not protect information already delivered to the browser.

Security must be enforced by server-side authentication, authorization,
and data-access controls.

------------------------------------------------------------------------

# 86. `display: none` Does Not Protect Data

If sensitive information exists in the DOM:

``` html
<div class="secret-data">
    Sensitive information
</div>
```

and CSS says:

``` css
.secret-data {
    display: none;
}
```

the information is still available in the browser.

CSS controls presentation, not authorization.

------------------------------------------------------------------------

# 87. Recognizable Naming Categories

A useful naming model:

``` text
Component       .email-card
Element         .email-card-title
State           .is-selected
Utility         .u-hidden
JS hook         .js-email-delete
```

Example:

``` css
.email-card {
}

.email-card-title {
}

.email-card.is-selected {
}

.u-hidden {
}
```

A JavaScript hook such as `.js-email-delete` should normally not receive
visual styling.

------------------------------------------------------------------------

# 88. Recommended CSS Architecture

For this application:

``` text
CSS
 │
 ├── Vendor
 │      DHTMLX original CSS
 │
 ├── Base
 │      box-sizing
 │      typography
 │      common defaults
 │
 ├── Layout
 │      page
 │      header
 │      sidebar
 │
 ├── Components
 │      buttons
 │      forms
 │      dialogs
 │      notifications
 │
 ├── Pages
 │      email
 │      document viewer
 │
 ├── Utilities
 │      small reusable helpers
 │
 └── Overrides
        intentional DHTMLX changes
```

This makes the ownership and purpose of each CSS rule clearer.

------------------------------------------------------------------------

# 89. Recommended Relationship with JavaScript

A clean interaction:

``` text
JavaScript
    │
    │ adds/removes state
    ▼
CSS classes
    │
    ▼
Visual result
```

Example:

``` javascript
emailCard.classList.add('is-selected');
```

CSS:

``` css
.email-card.is-selected {
    /* Selected appearance. */
}
```

JavaScript decides **that the card is selected**.

CSS decides **how a selected card looks**.

------------------------------------------------------------------------

# 90. Legacy Modernization Strategy

Recommended:

``` text
Existing DHTMLX / legacy CSS
          │
          └── Leave stable styles mostly unchanged

CSS currently being modified
          │
          ├── Improve naming locally
          ├── Reduce specificity
          ├── Remove unnecessary !important
          ├── Extract reusable components
          └── Document vendor overrides

New CSS
          │
          ├── Component-oriented
          ├── Low specificity
          ├── Responsive
          ├── Accessible
          ├── Consistent spacing
          └── Stylelint

Future
          │
          ├── Design tokens
          ├── Better component isolation
          ├── Build/minification
          └── Visual regression testing
```

Do not rewrite every stable DHTMLX stylesheet merely to make it look
modern.

------------------------------------------------------------------------

# 91. Bad Example

``` css
#main #content .box div ul li a {
    color: red !important;
    font-size:14px;
    margin-top:0px;
    padding:5px 10px;
}
```

Problems:

``` text
Very high specificity
Structural selector dependency
!important
Inconsistent whitespace
0px instead of 0
Generic .box name
Hard to reuse
Hard to override
```

------------------------------------------------------------------------

# 92. Better Example

``` css
.email-list-link {
    margin-top: 0;
    padding: 5px 10px;

    color: #c00;
    font-size: 14px;
}
```

The rule is now:

``` text
Easy to find
Easy to understand
Easy to override
Independent of deep DOM structure
Reusable
```

------------------------------------------------------------------------

# 93. Complete Component Example

HTML:

``` html
<article class="email-card">
    <header class="email-card-header">
        <h2 class="email-card-title">
            Meeting tomorrow
        </h2>

        <span class="email-card-date">
            2026-09-29
        </span>
    </header>

    <div class="email-card-body">
        Please attend the meeting.
    </div>

    <footer class="email-card-footer">
        <button class="button button--primary js-email-open">
            Open
        </button>
    </footer>
</article>
```

CSS:

``` css
.email-card {
    padding: 16px;

    color: #333;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.email-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 12px;
}

.email-card-title {
    margin: 0;

    font-size: 18px;
    font-weight: 600;
}

.email-card-date {
    color: #666;
    font-size: 12px;
}

.email-card-body {
    line-height: 1.5;
}

.email-card-footer {
    display: flex;
    justify-content: flex-end;

    margin-top: 16px;
}
```

Structure:

``` text
email-card
    ├── email-card-header
    │      ├── email-card-title
    │      └── email-card-date
    │
    ├── email-card-body
    │
    └── email-card-footer
```

------------------------------------------------------------------------

# 94. DHTMLX Override Example

Do not edit the original DHTMLX stylesheet.

Instead:

``` css
/*
 * DHTMLX override for the email screen.
 *
 * The legacy grid row height is too small for the
 * two-line subject display used by this page.
 */
.email-page .dhx-grid-row {
    min-height: 40px;
}
```

Store intentional overrides in a file such as:

``` text
css/overrides/dhtmlx.css
```

This makes the purpose of the rule explicit.

------------------------------------------------------------------------

# 95. CSS Review Checklist

Before committing CSS, check:

-   Is formatting consistent?
-   Are 4 spaces used for indentation?
-   Is there one declaration per line?
-   Does every declaration use a semicolon?
-   Are selectors lowercase and consistently named?
-   Are classes preferred over IDs for styling?
-   Are selectors reasonably simple?
-   Is specificity kept low?
-   Is `!important` avoided unless justified?
-   Are components clearly named?
-   Are state classes recognizable?
-   Are JavaScript hooks separated from visual classes where useful?
-   Are vendor files left unchanged?
-   Are DHTMLX overrides isolated and documented?
-   Are repeated colors and spacing values centralized where useful?
-   Are responsive layouts considered?
-   Are focus states accessible?
-   Is color not the only indication of an important state?
-   Are inline styles avoided?
-   Does JavaScript use state classes instead of setting presentation
    unnecessarily?
-   Are dead styles removed carefully?
-   Are source and minified CSS separated?
-   Has the affected page been visually tested?
-   Were unrelated stable legacy styles left untouched?

------------------------------------------------------------------------

# 96. Recommended Project Coding Standard

A useful long-term frontend structure is:

``` text
HTML
 │
 └── Structure and semantics

CSS
 │
 ├── Base
 ├── Layout
 ├── Components
 ├── Page styles
 ├── Utilities
 └── Vendor overrides

JavaScript
 │
 ├── Behavior
 ├── State changes
 └── Adds/removes CSS state classes

DHTMLX
 │
 └── Third-party UI library
      kept separate from application CSS
```

Relationship:

``` text
DHTMLX / HTML
       │
       ▼
Application CSS
       │
       ├── Layout
       ├── Components
       └── States
       ▲
       │
JavaScript
adds/removes state classes
```

------------------------------------------------------------------------

# 97. Final Principle

The biggest long-term CSS problems usually come from:

``` text
High specificity
Deep selectors
!important everywhere
Global rules
Unclear naming
Duplicated styles
Vendor modifications
Inline styles
Huge CSS files
Uncontrolled overrides
```

The central rule is:

> **Keep CSS selectors simple, component-oriented, predictable, and easy
> to override without increasing specificity.**

For a legacy application, use gradual modernization:

``` text
Stable legacy CSS
        ↓
Do not rewrite unnecessarily
        ↓
Improve styles when touched
        ↓
Separate DHTMLX vendor styles
        ↓
Create application components
        ↓
Reduce specificity
        ↓
Introduce consistent naming
        ↓
Add responsive/accessibility rules
        ↓
Add linting and minification
```

This provides a safer path toward a clean frontend while protecting
existing DHTMLX 3.5 screens.
