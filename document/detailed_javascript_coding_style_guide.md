# Detailed JavaScript Coding Style Guide

## For Modern JavaScript, Legacy DHTMLX 3.5, and Gradual Frontend Modernization

This guide defines a detailed JavaScript coding style for a project that
contains both modern JavaScript and legacy frontend code such as DHTMLX
3.5.

The main goals are:

``` text
Readable
   +
Consistent
   +
Predictable
   +
Testable
   +
Secure
   +
Maintainable
```

> **Core rule:** Apply the modern coding standard consistently to new
> JavaScript, but modernize stable legacy JavaScript gradually rather
> than rewriting the entire frontend at once.

------------------------------------------------------------------------

# 1. Main Goals

A good JavaScript coding standard should make code easy for another
developer to understand and maintain.

This works:

``` javascript
if(u&&u.a){send(u);}
```

But this is much easier to understand:

``` javascript
if (user && user.isActive) {
    sendEmail(user);
}
```

Write code for developers to understand first and for the JavaScript
engine second.

------------------------------------------------------------------------

# 2. Indentation

Use **4 spaces** for indentation in this project.

Many JavaScript projects use 2 spaces, but using 4 spaces keeps the
JavaScript style visually consistent with the project's PHP/PSR-12
convention.

Good:

``` javascript
if (user.isActive) {
    if (user.hasPermission) {
        sendEmail(user);
    }
}
```

The important point is consistency.

------------------------------------------------------------------------

# 3. Always Use Braces

Use braces for control structures.

Good:

``` javascript
if (user === null) {
    return;
}
```

Avoid:

``` javascript
if (user === null)
    return;
```

Also avoid:

``` javascript
if (user === null) return;
```

Braces make later modifications safer.

------------------------------------------------------------------------

# 4. Brace Position

For JavaScript, put the opening brace on the **same line**.

``` javascript
function getUser(userId) {
    // ...
}
```

``` javascript
if (user.isActive) {
    // ...
}
```

``` javascript
for (let i = 0; i < users.length; i++) {
    // ...
}
```

This differs from PHP PSR-12 method formatting.

PHP:

``` php
public function getUser($userId)
{
}
```

JavaScript:

``` javascript
function getUser(userId) {
}
```

Remember:

``` text
PHP method:
    opening brace on next line

JavaScript function:
    opening brace on same line
```

------------------------------------------------------------------------

# 5. Semicolons

Use semicolons consistently.

``` javascript
const userId = 100;
const userName = 'John';

sendEmail(userId);
```

Avoid mixing semicolon and semicolon-free styles.

JavaScript has Automatic Semicolon Insertion (ASI), but explicit
semicolons provide a clear convention, particularly in a mixed
legacy/modern codebase.

------------------------------------------------------------------------

# 6. One Statement Per Line

Good:

``` javascript
const userId = 100;
const userName = 'John';
const isActive = true;
```

Avoid:

``` javascript
const userId = 100; const userName = 'John'; const isActive = true;
```

------------------------------------------------------------------------

# 7. Prefer `const`

For modern JavaScript, use `const` by default.

``` javascript
const userId = 100;
const userName = 'John';
```

Use `let` when reassignment is necessary:

``` javascript
let retryCount = 0;

retryCount++;
```

Avoid `var` in new code:

``` javascript
var userId = 100;
```

A useful rule:

``` text
Need a variable?
      │
      ▼
Use const
      │
      ├── Never reassigned → keep const
      │
      └── Must reassign
              ↓
            use let
```

For legacy DHTMLX code that must support older browsers, `var` may still
be necessary. Do not blindly replace all legacy `var` declarations
without considering browser compatibility.

------------------------------------------------------------------------

# 8. `const` Does Not Make Objects Immutable

This is important:

``` javascript
const user = {
    name: 'John'
};
```

You cannot reassign the variable:

``` javascript
user = anotherUser;
```

But you can modify the object:

``` javascript
user.name = 'Peter';
```

`const` prevents reassignment of the variable binding. It does not make
the object read-only.

------------------------------------------------------------------------

# 9. Variable Naming

Use `camelCase`.

Good:

``` javascript
const userId = 100;
const emailAddress = 'user@example.com';
const documentId = 500;
const accessToken = '...';
const recipientIds = [];
```

Avoid unclear names:

``` javascript
const userid = 100;
const docid = 500;
const x = '...';
```

unless the scope is extremely small and the meaning is obvious.

------------------------------------------------------------------------

# 10. Boolean Names

Boolean names should clearly communicate that they represent a
true/false condition.

Good:

``` javascript
const isActive = true;
const isAdmin = false;
const hasPermission = true;
const canDelete = false;
const shouldNotify = true;
```

Then conditions read naturally:

``` javascript
if (isActive && hasPermission) {
    sendEmail();
}
```

Avoid:

``` javascript
const flag = true;
const status = false;
```

when a clearer boolean name is possible.

------------------------------------------------------------------------

# 11. Function Names

Use verbs that describe the operation.

Good:

``` javascript
getUser();
createEmail();
deleteDocument();
validateToken();
loadNotifications();
saveConfiguration();
```

Avoid vague names:

``` javascript
doIt();
run();
process();
```

unless the surrounding abstraction genuinely makes the meaning clear.

------------------------------------------------------------------------

# 12. Event Handler Names

Make event handlers easy to recognize.

Recommended:

``` javascript
function handleSaveClick(event) {
    // ...
}

function handleEmailChange(event) {
    // ...
}

function handleFormSubmit(event) {
    // ...
}
```

Another acceptable convention is:

``` javascript
function onSaveClick(event) {
}

function onFormSubmit(event) {
}
```

Choose one convention and use it consistently.

------------------------------------------------------------------------

# 13. Class Names

Use `PascalCase`.

``` javascript
class EmailService {
}

class NotificationManager {
}

class ApiClient {
}
```

Avoid:

``` javascript
class emailService {
}
```

------------------------------------------------------------------------

# 14. Constants

Use uppercase snake case for genuine application-wide constants.

``` javascript
const MAX_LOGIN_ATTEMPTS = 5;
const ACCESS_TOKEN_LIFETIME = 900;
const DEFAULT_PAGE_SIZE = 20;
```

Do not uppercase every variable declared with `const`.

This is correct:

``` javascript
const userId = 100;
```

`const` means a binding is not reassigned; it does not automatically
make it an application constant.

------------------------------------------------------------------------

# 15. Strings

Use single quotes as the normal project quote style.

``` javascript
const name = 'John';
const status = 'active';
```

Use template literals when interpolation improves readability:

``` javascript
const message = `Welcome, ${userName}.`;
```

Instead of:

``` javascript
const message = 'Welcome, ' + userName + '.';
```

------------------------------------------------------------------------

# 16. String Construction

Older style:

``` javascript
const url =
    '/api/user/get?id=' + userId +
    '&status=' + status;
```

Modern style:

``` javascript
const url =
    `/api/user/get?id=${userId}&status=${status}`;
```

When values are inserted into URLs, remember that URL encoding may still
be required. Template literals do not automatically make a URL safe.

------------------------------------------------------------------------

# 17. Strict Equality

Prefer:

``` javascript
===
```

and:

``` javascript
!==
```

instead of:

``` javascript
==
```

and:

``` javascript
!=
```

Good:

``` javascript
if (userId === 100) {
}
```

Avoid:

``` javascript
if (userId == 100) {
}
```

Loose equality performs type coercion.

For example:

``` javascript
'100' == 100
```

is true, while:

``` javascript
'100' === 100
```

is false.

Strict equality is generally more predictable.

------------------------------------------------------------------------

# 18. Truthy and Falsy Values

Falsy JavaScript values include:

``` javascript
false
0
''
null
undefined
NaN
```

Therefore:

``` javascript
if (!value) {
}
```

can match several different situations.

If you specifically mean `null`:

``` javascript
if (user === null) {
}
```

If you specifically mean `undefined`:

``` javascript
if (value === undefined) {
}
```

Use broad truthiness checks only when all falsy values should have the
same meaning.

------------------------------------------------------------------------

# 19. Prefer Early Returns

Harder to read:

``` javascript
function sendEmail(user) {
    if (user) {
        if (user.isActive) {
            if (user.email) {
                if (user.canReceiveEmail) {
                    return send(user);
                }
            }
        }
    }

    return false;
}
```

Better:

``` javascript
function sendEmail(user) {
    if (!user) {
        return false;
    }

    if (!user.isActive) {
        return false;
    }

    if (!user.email) {
        return false;
    }

    if (!user.canReceiveEmail) {
        return false;
    }

    return send(user);
}
```

The successful path becomes much easier to understand.

------------------------------------------------------------------------

# 20. Keep Functions Focused

Avoid giant functions that perform many unrelated responsibilities.

For example:

``` javascript
function saveEmail() {
    // Read form.
    // Validate data.
    // Build URL.
    // Send request.
    // Parse response.
    // Update grid.
    // Update notification counter.
    // Display dialog.
    // Write browser storage.
    // Handle all errors.
}
```

Prefer:

``` text
handleSaveClick()
       │
       ▼
validateEmailForm()
       │
       ▼
createEmail()
       │
       ▼
ApiClient
       │
       ▼
ZF1 API
```

Modern example:

``` javascript
async function handleSaveClick() {
    const data = readEmailForm();

    validateEmailForm(data);

    const result = await createEmail(data);

    updateEmailGrid(result);
}
```

------------------------------------------------------------------------

# 21. Function Parameters

Short parameter list:

``` javascript
function getUser(userId) {
}
```

Longer list:

``` javascript
function createEmail(
    userId,
    subject,
    message,
    recipientIds
) {
    // ...
}
```

Too many positional arguments can make a call difficult to understand.

Instead of:

``` javascript
createEmail(
    userId,
    subject,
    message,
    recipientIds,
    priority,
    notify,
    attachments
);
```

consider an options object:

``` javascript
createEmail({
    userId,
    subject,
    message,
    recipientIds,
    priority,
    notify,
    attachments
});
```

The call becomes self-documenting.

------------------------------------------------------------------------

# 22. Default Parameters

Modern JavaScript:

``` javascript
function loadUsers(page = 1, pageSize = 20) {
    // ...
}
```

Older pattern:

``` javascript
function loadUsers(page, pageSize) {
    page = page || 1;
    pageSize = pageSize || 20;
}
```

The older pattern can incorrectly replace legitimate falsy values such
as `0`.

Use default parameters where the browser/transpilation target supports
them.

------------------------------------------------------------------------

# 23. Object Formatting

Good:

``` javascript
const user = {
    id: 100,
    name: 'John',
    email: 'john@example.com',
    isActive: true
};
```

Nested:

``` javascript
const config = {
    api: {
        baseUrl: '/api',
        timeout: 10000
    },
    notification: {
        enabled: true,
        interval: 10000
    }
};
```

------------------------------------------------------------------------

# 24. Object Property Shorthand

Instead of:

``` javascript
const user = {
    userId: userId,
    userName: userName
};
```

modern JavaScript allows:

``` javascript
const user = {
    userId,
    userName
};
```

Use shorthand when the property name and variable name intentionally
match.

------------------------------------------------------------------------

# 25. Destructuring

Instead of:

``` javascript
const userId = user.id;
const userName = user.name;
const email = user.email;
```

you can write:

``` javascript
const {
    id: userId,
    name: userName,
    email
} = user;
```

Simple case:

``` javascript
const { id, name } = user;
```

Use destructuring when it makes the code clearer, not merely because it
is modern syntax.

------------------------------------------------------------------------

# 26. Arrays

Small array:

``` javascript
const roles = ['admin', 'manager', 'user'];
```

Longer array:

``` javascript
const userIds = [
    100,
    101,
    102
];
```

Use multiline formatting when data becomes long or complex.

------------------------------------------------------------------------

# 27. Array Methods

Useful methods include:

``` text
map()
filter()
find()
some()
every()
reduce()
forEach()
```

Traditional loop:

``` javascript
const activeUsers = [];

for (let i = 0; i < users.length; i++) {
    if (users[i].isActive) {
        activeUsers.push(users[i]);
    }
}
```

Modern alternative:

``` javascript
const activeUsers = users.filter(
    (user) => user.isActive
);
```

Do not force array methods when a normal loop is clearer.

------------------------------------------------------------------------

# 28. `for...of`

When you need values rather than indexes:

``` javascript
for (const user of users) {
    processUser(user);
}
```

This is often clearer than:

``` javascript
for (let i = 0; i < users.length; i++) {
    processUser(users[i]);
}
```

when the index is not needed.

------------------------------------------------------------------------

# 29. Be Careful with `for...in`

`for...in` iterates enumerable property names.

``` javascript
for (const key in object) {
    // ...
}
```

Do not normally use it for arrays.

For array values, prefer:

``` javascript
for (const value of values) {
}
```

or an appropriate array method.

------------------------------------------------------------------------

# 30. Arrow Functions

Good for short callbacks:

``` javascript
const activeUsers = users.filter(
    (user) => user.isActive
);
```

``` javascript
const userIds = users.map(
    (user) => user.id
);
```

For important or complex logic, a named function can be clearer:

``` javascript
function isUserAllowedToReceiveEmail(user) {
    return user.isActive &&
        user.hasEmail &&
        !user.isBlocked;
}

const recipients = users.filter(
    isUserAllowedToReceiveEmail
);
```

------------------------------------------------------------------------

# 31. Arrow Functions and `this`

Arrow functions do not have their own `this`.

Normal object method:

``` javascript
const object = {
    name: 'John',

    getName() {
        return this.name;
    }
};
```

Be careful with:

``` javascript
const object = {
    name: 'John',

    getName: () => {
        return this.name;
    }
};
```

Here `this` may not refer to `object`.

This is particularly important when modernizing older event-driven or
DHTMLX code.

------------------------------------------------------------------------

# 32. Prefer Named Functions for Important Event Logic

Avoid hiding substantial logic in an anonymous callback:

``` javascript
button.addEventListener('click', function () {
    // 80 lines...
});
```

Prefer:

``` javascript
button.addEventListener(
    'click',
    handleSaveClick
);

function handleSaveClick(event) {
    // ...
}
```

Benefits:

``` text
Easier testing
Easier debugging
Better stack traces
Reusable logic
Clearer event setup
```

------------------------------------------------------------------------

# 33. Event Listeners

For normal DOM APIs, prefer:

``` javascript
button.addEventListener(
    'click',
    handleSaveClick
);
```

over:

``` javascript
button.onclick = handleSaveClick;
```

`addEventListener()` supports multiple listeners and better separation.

DHTMLX has its own event system, so use the library's required APIs for
DHTMLX components.

------------------------------------------------------------------------

# 34. Avoid Inline JavaScript

Avoid:

``` html
<button onclick="saveEmail();">
    Save
</button>
```

Prefer:

``` html
<button id="saveButton">
    Save
</button>
```

JavaScript:

``` javascript
const saveButton =
    document.getElementById('saveButton');

saveButton.addEventListener(
    'click',
    handleSaveClick
);
```

This separates HTML structure from JavaScript behavior.

------------------------------------------------------------------------

# 35. DOM Element Names

Use descriptive variable names.

Good:

``` javascript
const saveButton =
    document.getElementById('saveButton');

const emailForm =
    document.getElementById('emailForm');

const notificationCount =
    document.getElementById('notificationCount');
```

Avoid vague names such as:

``` javascript
const x = document.getElementById('emailForm');
```

------------------------------------------------------------------------

# 36. DOM Querying

Use:

``` javascript
document.getElementById('saveButton');
```

or:

``` javascript
document.querySelector('#saveButton');
```

For multiple elements:

``` javascript
document.querySelectorAll('.email-row');
```

Store frequently used DOM elements rather than querying them repeatedly.

------------------------------------------------------------------------

# 37. Prefer `textContent` for Text

Avoid:

``` javascript
element.innerHTML = userName;
```

when only text should be displayed.

Prefer:

``` javascript
element.textContent = userName;
```

This is clearer and reduces XSS risk for untrusted text.

Use `innerHTML` only when HTML insertion is genuinely required and the
inserted content has been handled safely.

------------------------------------------------------------------------

# 38. XSS Awareness

Dangerous:

``` javascript
container.innerHTML =
    '<div>' + userInput + '</div>';
```

If `userInput` contains malicious markup, this may create an XSS
vulnerability.

Prefer DOM APIs:

``` javascript
const div = document.createElement('div');

div.textContent = userInput;

container.appendChild(div);
```

Security is not merely a style issue, but secure DOM handling should be
part of the JavaScript project standard.

------------------------------------------------------------------------

# 39. Avoid `eval()`

Avoid:

``` javascript
eval(code);
```

Also avoid constructing executable JavaScript dynamically where
possible.

`eval()` creates security, debugging, optimization, and maintainability
problems.

------------------------------------------------------------------------

# 40. Avoid Global Variables

Bad:

``` javascript
var currentUser = null;
var emailGrid = null;
var token = null;
```

Global variables can collide with other scripts and create hidden
dependencies.

For modern code, use modules.

For legacy code without module support, use a project namespace.

------------------------------------------------------------------------

# 41. Legacy Namespace Pattern

For older browser-compatible code:

``` javascript
var App = App || {};

App.Email = {
    load: function () {
        // ...
    },

    save: function () {
        // ...
    }
};
```

Usage:

``` javascript
App.Email.load();
```

This is safer than creating many unrelated globals.

------------------------------------------------------------------------

# 42. Modern Modules

Modern module:

``` javascript
// email-service.js

export class EmailService {
    async getList() {
        // ...
    }
}
```

Usage:

``` javascript
import { EmailService }
    from './email-service.js';

const emailService = new EmailService();
```

Native module usage depends on the browser/runtime strategy. Babel or
another build process may be required when old browser compatibility
matters.

------------------------------------------------------------------------

# 43. Separate UI, Service, and API Responsibilities

Avoid a single 5,000-line `email.js`.

Prefer:

``` text
js/
├── api/
│   └── email-api.js
├── services/
│   └── email-service.js
├── ui/
│   └── email-grid.js
├── validation/
│   └── email-validator.js
└── pages/
    └── email-page.js
```

Conceptually:

``` text
DHTMLX / DOM
      │
      ▼
UI code
      │
      ▼
Service
      │
      ▼
API client
      │
      ▼
ZF1 API
```

------------------------------------------------------------------------

# 44. Centralize API Code

Avoid scattering networking code across UI files.

Prefer:

``` javascript
class EmailApi {
    async getList() {
        const response = await fetch(
            '/api/email/getlist'
        );

        return response.json();
    }
}
```

Then UI code does not need to know all HTTP details.

------------------------------------------------------------------------

# 45. `async` / `await`

Modern example:

``` javascript
async function loadEmails() {
    const response = await fetch(
        '/api/email/getlist'
    );

    const result = await response.json();

    return result;
}
```

Always consider HTTP errors:

``` javascript
async function loadEmails() {
    const response = await fetch(
        '/api/email/getlist'
    );

    if (!response.ok) {
        throw new Error(
            `Request failed: ${response.status}`
        );
    }

    return response.json();
}
```

`fetch()` does not reject merely because the server returns an HTTP
error status.

------------------------------------------------------------------------

# 46. `try...catch` with Async Code

``` javascript
async function handleLoadEmails() {
    try {
        const emails = await loadEmails();

        updateEmailGrid(emails);
    } catch (error) {
        console.error(
            'Unable to load emails.',
            error
        );

        showError(
            'Unable to load email information.'
        );
    }
}
```

Keep developer diagnostics separate from user-facing error messages.

------------------------------------------------------------------------

# 47. Do Not Silently Swallow Errors

Avoid:

``` javascript
try {
    await saveEmail(data);
} catch (error) {
}
```

Instead:

``` javascript
try {
    await saveEmail(data);
} catch (error) {
    console.error(
        'Email save failed.',
        error
    );

    showError(
        'Unable to save the email.'
    );
}
```

An error should be handled, logged where appropriate, translated, or
allowed to propagate.

------------------------------------------------------------------------

# 48. Throw `Error` Objects

Prefer:

``` javascript
throw new Error(
    'Email ID is required.'
);
```

Avoid:

``` javascript
throw 'Email ID is required.';
```

`Error` objects contain useful debugging information such as stack
traces.

------------------------------------------------------------------------

# 49. Custom Errors

For important categories:

``` javascript
class ValidationError extends Error {
    constructor(message) {
        super(message);

        this.name = 'ValidationError';
    }
}
```

Usage:

``` javascript
if (!data.subject) {
    throw new ValidationError(
        'Subject is required.'
    );
}
```

This allows callers to distinguish validation failures from networking
or programming errors.

------------------------------------------------------------------------

# 50. Input Validation

Example:

``` javascript
function validateEmail(data) {
    if (!data.subject) {
        throw new ValidationError(
            'Subject is required.'
        );
    }

    if (data.subject.length > 255) {
        throw new ValidationError(
            'Subject is too long.'
        );
    }
}
```

Remember:

> Client-side validation improves usability. It is not the final
> security boundary.

The PHP/ZF1 backend must independently validate security-relevant input.

------------------------------------------------------------------------

# 51. Never Put Server Secrets in Frontend JavaScript

Never write:

``` javascript
const jwtSecret = 'my-secret-key';
```

Anything delivered to the browser can be inspected.

Frontend JavaScript must never contain:

``` text
Server JWT signing secrets
Database passwords
Private keys
Certificate passwords
Server API credentials
```

------------------------------------------------------------------------

# 52. JSON Handling

Good:

``` javascript
const requestData = {
    subject: 'Meeting',
    message: 'Meeting tomorrow',
    recipientIds: [
        100,
        101
    ]
};
```

Serialize with:

``` javascript
const body = JSON.stringify(requestData);
```

Do not manually construct JSON strings:

``` javascript
const body =
    '{"subject":"' + subject + '"}';
```

Use `JSON.stringify()`.

------------------------------------------------------------------------

# 53. Avoid Unexpected Mutation

Mutation:

``` javascript
function normalizeUser(user) {
    user.name = user.name.trim();

    return user;
}
```

When unexpected mutation would be confusing, create a new object:

``` javascript
function normalizeUser(user) {
    return {
        ...user,
        name: user.name.trim()
    };
}
```

Do not force immutable patterns everywhere if they make legacy code
unnecessarily complicated.

------------------------------------------------------------------------

# 54. Spread Syntax

Objects:

``` javascript
const updatedUser = {
    ...user,
    isActive: true
};
```

Arrays:

``` javascript
const allUsers = [
    ...activeUsers,
    ...inactiveUsers
];
```

Use modern syntax only when the browser/transpilation target supports
it.

------------------------------------------------------------------------

# 55. Optional Chaining

Modern JavaScript:

``` javascript
const city =
    user?.address?.city;
```

Older equivalent:

``` javascript
const city =
    user &&
    user.address &&
    user.address.city;
```

Optional chaining may not work in old browsers without transpilation.
Consider your compatibility requirements before introducing it into
legacy pages.

------------------------------------------------------------------------

# 56. Nullish Coalescing

Modern JavaScript:

``` javascript
const pageSize =
    config.pageSize ?? 20;
```

This differs from:

``` javascript
const pageSize =
    config.pageSize || 20;
```

`??` falls back for `null` and `undefined`.

`||` also falls back for values such as:

``` text
0
''
false
```

Use the operator that matches the intended semantics and browser target.

------------------------------------------------------------------------

# 57. Comments

Bad:

``` javascript
// Increase count.
count++;
```

Better:

``` javascript
// Count starts at 1 because ID 0 is reserved
// for imported legacy records.
count++;
```

Comments should explain non-obvious reasons, constraints, or business
rules.

------------------------------------------------------------------------

# 58. TODO Comments

Good:

``` javascript
// TODO: Replace polling with WebSocket notifications
// after the realtime server is deployed.
```

Avoid:

``` javascript
// TODO fix
```

A TODO should explain what remains and preferably why.

------------------------------------------------------------------------

# 59. JSDoc

JSDoc is useful for documenting JavaScript APIs.

Example:

``` javascript
/**
 * Returns emails visible to a user.
 *
 * @param {number} userId
 *     Authenticated user ID.
 *
 * @returns {Promise<Array<Object>>}
 *     Email records.
 */
async function getEmails(userId) {
    // ...
}
```

Complex parameter:

``` javascript
/**
 * Creates a new email.
 *
 * @param {Object} data
 * @param {string} data.subject
 * @param {string} data.message
 * @param {number[]} data.recipientIds
 *
 * @returns {Promise<number>}
 *     ID of the newly created email.
 */
async function createEmail(data) {
    // ...
}
```

JSDoc improves documentation, IDE autocomplete, and static analysis even
when the project uses plain JavaScript.

------------------------------------------------------------------------

# 60. Do Not Over-Document

Avoid documentation that merely repeats obvious code.

JSDoc is especially valuable for:

``` text
Public APIs
Complex objects
Callbacks
Promises
Important side effects
Non-obvious business rules
Legacy JavaScript
Shared utility functions
```

------------------------------------------------------------------------

# 61. DHTMLX Event Code

Legacy code:

``` javascript
form.attachEvent('onButtonClick', function (name) {
    if (name == 'save') {
        save();
    }
});
```

Cleaner:

``` javascript
form.attachEvent(
    'onButtonClick',
    handleFormButtonClick
);

function handleFormButtonClick(name) {
    if (name !== 'save') {
        return;
    }

    saveEmail();
}
```

This improves naming, strict equality, nesting, readability, and
testability without requiring replacement of DHTMLX.

------------------------------------------------------------------------

# 62. DHTMLX Grid Example

Avoid large anonymous callbacks:

``` javascript
grid.attachEvent('onRowSelect', function (id) {
    // 50 lines...
});
```

Prefer:

``` javascript
grid.attachEvent(
    'onRowSelect',
    handleEmailRowSelect
);

function handleEmailRowSelect(rowId) {
    const email = getEmailFromGrid(rowId);

    showEmailDetails(email);
}
```

Keep DHTMLX-specific behavior close to the UI layer.

------------------------------------------------------------------------

# 63. Separate DHTMLX from Business Logic

Recommended design:

``` text
DHTMLX Grid/Form
       │
       ▼
UI Adapter
       │
       ▼
Email Service
       │
       ▼
API Client
       │
       ▼
ZF1 Backend
```

Example:

``` javascript
function handleSaveButton() {
    const data = readDhtmlxEmailForm();

    emailService
        .create(data)
        .then(handleEmailCreated)
        .catch(handleEmailError);
}
```

The service should not need to know how DHTMLX stores or renders fields.

------------------------------------------------------------------------

# 64. Avoid Deeply Nested Callbacks

Hard to maintain:

``` javascript
loadUser(function (user) {
    loadPermissions(user, function (permissions) {
        loadEmails(user, function (emails) {
            // ...
        });
    });
});
```

Modern alternative:

``` javascript
async function initializePage() {
    const user = await loadUser();

    const permissions =
        await loadPermissions(user);

    const emails =
        await loadEmails(user);

    renderPage(
        user,
        permissions,
        emails
    );
}
```

If older browser compatibility prevents direct use of modern syntax,
consider a transpilation/build step.

------------------------------------------------------------------------

# 65. Separate Rendering and Business Logic

Avoid:

``` javascript
function loadUser(user) {
    if (user.status === 'active') {
        document.getElementById('user').innerHTML =
            '<b>' + user.name + '</b>';

        // Business operations...
    }
}
```

Prefer:

``` javascript
function processUser(user) {
    validateUser(user);

    renderUser(user);
}

function renderUser(user) {
    const element =
        document.getElementById('user');

    element.textContent = user.name;
}
```

------------------------------------------------------------------------

# 66. Centralize API URLs

Avoid scattering strings such as:

``` javascript
'/api/email/getlist'
```

through many files.

Example:

``` javascript
const API_ENDPOINTS = {
    email: {
        list: '/api/email/getlist',
        create: '/api/email/create',
        delete: '/api/email/delete'
    }
};
```

Usage:

``` javascript
fetch(API_ENDPOINTS.email.list);
```

Centralized endpoints make future backend migration easier.

------------------------------------------------------------------------

# 67. Handle HTTP Status Codes

Do not assume every HTTP response is successful.

``` javascript
async function request(url, options = {}) {
    const response = await fetch(
        url,
        options
    );

    if (!response.ok) {
        throw new Error(
            `HTTP ${response.status}`
        );
    }

    return response.json();
}
```

Usage:

``` javascript
const emails = await request(
    API_ENDPOINTS.email.list
);
```

------------------------------------------------------------------------

# 68. Centralize Common Request Behavior

Example:

``` javascript
async function apiRequest(
    url,
    options = {}
) {
    const headers = {
        ...options.headers,
        'Content-Type': 'application/json'
    };

    const response = await fetch(
        url,
        {
            ...options,
            headers
        }
    );

    if (!response.ok) {
        throw new Error(
            `HTTP ${response.status}`
        );
    }

    return response.json();
}
```

Authentication headers, CSRF handling, common error handling, and JSON
behavior can then be centralized instead of duplicated across pages.

------------------------------------------------------------------------

# 69. Avoid Hard-Coded Timing Values

Avoid:

``` javascript
setInterval(loadNotifications, 10000);
```

Prefer:

``` javascript
const NOTIFICATION_POLL_INTERVAL = 10000;

setInterval(
    loadNotifications,
    NOTIFICATION_POLL_INTERVAL
);
```

The name explains the meaning of `10000`.

------------------------------------------------------------------------

# 70. Put Units in Variable Names

Good:

``` javascript
const timeoutMs = 5000;
const tokenLifetimeSeconds = 900;
const fileSizeBytes = file.size;
```

This prevents mistakes such as confusing milliseconds and seconds.

------------------------------------------------------------------------

# 71. Avoid Unclear Boolean Parameters

Unclear:

``` javascript
loadUsers(true, false);
```

Prefer:

``` javascript
loadUsers({
    includeInactive: true,
    includeDeleted: false
});
```

The call explains itself.

------------------------------------------------------------------------

# 72. Prefer Positive Conditions

Harder:

``` javascript
if (!user.isNotActive) {
}
```

Better:

``` javascript
if (user.isActive) {
}
```

Avoid double negatives.

------------------------------------------------------------------------

# 73. Keep Nesting Shallow

Avoid:

``` javascript
if (user) {
    if (user.isActive) {
        if (permission) {
            if (document) {
                // ...
            }
        }
    }
}
```

Prefer guard clauses:

``` javascript
if (!user) {
    return;
}

if (!user.isActive) {
    return;
}

if (!permission) {
    return;
}

if (!document) {
    return;
}

// Main operation.
```

------------------------------------------------------------------------

# 74. Avoid Huge JavaScript Files

Instead of a 4,000-line file, organize by responsibility.

Example:

``` text
public/js/
├── common/
│   ├── api.js
│   ├── errors.js
│   └── validation.js
│
├── email/
│   ├── email-api.js
│   ├── email-service.js
│   ├── email-form.js
│   └── email-grid.js
│
└── notification/
    ├── notification-api.js
    └── notification-ui.js
```

For old non-module code, separate files can still share a controlled
application namespace.

------------------------------------------------------------------------

# 75. Keep Third-Party Code Separate

Do not modify DHTMLX source code directly unless absolutely necessary.

Recommended:

``` text
public/
├── vendor/
│   └── dhtmlx/
│
└── js/
    └── app/
```

Your application code should call or wrap DHTMLX rather than modify the
library itself.

This makes future upgrades and debugging easier.

------------------------------------------------------------------------

# 76. Separate Source and Minified Code

Recommended:

``` text
js/
├── src/
│   ├── email.js
│   └── notification.js
│
└── dist/
    ├── email.min.js
    └── notification.min.js
```

Developers edit:

``` text
src/
```

Deployment uses:

``` text
dist/
```

Never manually edit `.min.js` files.

------------------------------------------------------------------------

# 77. Source Maps

Source maps can make debugging minified or transpiled JavaScript easier.

Conceptually:

``` text
Original source
      ↓
Build / minify
      ↓
Generated JavaScript
      +
Source map
      ↓
Browser debugger
      ↓
Original source location
```

Whether source maps should be deployed to production depends on the
deployment/security policy.

------------------------------------------------------------------------

# 78. ESLint

ESLint can automatically enforce many JavaScript project rules.

Conceptually:

``` text
JavaScript source
       │
       ▼
ESLint
       │
       ├── Style problem
       ├── Suspicious code
       └── Common bug pattern
```

For a legacy repository, first run linting in report/check mode.

Do not automatically rewrite thousands of lines of stable DHTMLX code
immediately.

------------------------------------------------------------------------

# 79. Formatter vs Linter vs Tests

These tools have different responsibilities:

``` text
Formatter
    ↓
Code appearance

Linter
    ↓
Style and suspicious patterns

Tests
    ↓
Program behavior

Type checker / TypeScript
    ↓
Type correctness
```

A mature workflow can use all of them together.

------------------------------------------------------------------------

# 80. Gradual Modernization Strategy

Recommended:

``` text
Existing DHTMLX 3.5 JavaScript
          │
          └── Leave stable code mostly unchanged

Code being modified
          │
          ├── Improve names
          ├── Use strict equality
          ├── Extract functions
          ├── Reduce globals
          ├── Add JSDoc
          └── Add tests where practical

New JavaScript
          │
          ├── Consistent formatting
          ├── const / let
          ├── Modules where supported
          ├── async / await where supported
          ├── API abstraction
          └── ESLint

Future
          │
          ├── Babel if legacy browser support is needed
          ├── TypeScript gradually
          └── Modern frontend architecture
```

------------------------------------------------------------------------

# 81. Recommended Rules for Old and New Code

``` text
Legacy code
    var may remain
    old callbacks may remain
    DHTMLX APIs remain
    avoid massive formatting-only changes

Modified legacy code
    improve locally
    use strict equality
    use clearer naming
    create smaller functions
    reduce globals

New code
    const / let
    modern functions
    clear modules
    async / await where compatible
    JSDoc or TypeScript
    ESLint
```

This minimizes migration risk.

------------------------------------------------------------------------

# 82. Complete Before-and-After Example

Poorly structured:

``` javascript
var data;

function load() {
    var x = new XMLHttpRequest();

    x.onreadystatechange = function () {
        if(x.readyState==4){
            if(x.status==200){
                data=JSON.parse(x.responseText);
                document.getElementById('count').innerHTML=data.length;
            }
        }
    };

    x.open('GET','/api/email/getlist',true);
    x.send();
}
```

Problems:

``` text
Global mutable variable
Unclear variable name
Loose equality
Deep nesting
Formatting inconsistency
Networking mixed with DOM rendering
innerHTML used for text
No useful error handling
Hard-coded endpoint
```

Cleaner modern version:

``` javascript
const API_ENDPOINTS = {
    emailList: '/api/email/getlist'
};

async function loadEmails() {
    const response = await fetch(
        API_ENDPOINTS.emailList
    );

    if (!response.ok) {
        throw new Error(
            `Unable to load emails: HTTP ${response.status}`
        );
    }

    return response.json();
}

function updateEmailCount(emails) {
    const countElement =
        document.getElementById('count');

    countElement.textContent =
        String(emails.length);
}

async function initializeEmailPage() {
    try {
        const emails = await loadEmails();

        updateEmailCount(emails);
    } catch (error) {
        console.error(
            'Email initialization failed.',
            error
        );

        showError(
            'Unable to load email information.'
        );
    }
}
```

Responsibilities:

``` text
loadEmails()
    ↓
API communication

updateEmailCount()
    ↓
UI rendering

initializeEmailPage()
    ↓
Workflow coordination
```

This separation makes the code easier to understand, test, and migrate.

------------------------------------------------------------------------

# 83. Quick Review Checklist

Before committing JavaScript, check:

-   Is indentation consistent?
-   Are braces consistently used?
-   Are semicolons used consistently?
-   Is `const` used by default?
-   Is `let` used only when reassignment is required?
-   Is `var` limited to compatibility-required legacy code?
-   Are variable and function names descriptive?
-   Do booleans use names such as `is`, `has`, `can`, or `should`?
-   Are `===` and `!==` used instead of loose equality?
-   Are functions focused?
-   Is nesting shallow?
-   Are important event callbacks named?
-   Are global variables minimized?
-   Is `textContent` used instead of unnecessary `innerHTML`?
-   Is untrusted content handled safely?
-   Is `eval()` avoided?
-   Are API calls centralized?
-   Are HTTP failures handled?
-   Are errors not silently swallowed?
-   Are `Error` objects used?
-   Are server secrets absent from frontend code?
-   Is JSDoc used where it provides real value?
-   Is DHTMLX-specific code kept near the UI layer?
-   Is third-party library code separate from application code?
-   Are source and minified files separated?
-   Are compatibility requirements considered before using modern
    syntax?
-   Were unrelated stable legacy files left untouched?

------------------------------------------------------------------------

# 84. Recommended Project Standard

A strong long-term JavaScript standard looks like:

``` text
JavaScript Source
       │
       ├── Consistent formatting
       ├── 4-space indentation
       ├── Semicolons
       ├── const by default
       ├── let when reassignment is required
       ├── Strict equality
       ├── Clear naming
       ├── Small focused functions
       ├── Early returns
       ├── Minimal global state
       ├── Safe DOM handling
       ├── Centralized API communication
       ├── JSDoc where valuable
       ├── ESLint
       └── Tests

Architecture
       │
       ├── Page / UI
       │      DHTMLX / DOM interaction
       │
       ├── Service
       │      Application behavior
       │
       ├── API Client
       │      HTTP communication
       │
       └── ZF1 API
              Server-side business/security rules
```

------------------------------------------------------------------------

# 85. Final Principle

For a legacy frontend, distinguish between **new JavaScript** and
**stable legacy DHTMLX JavaScript**.

Use this approach:

``` text
Stable legacy code
        ↓
Do not rewrite merely for style
        ↓
Protect behavior
        ↓
Improve code when it is modified
        ↓
Reduce globals and large callbacks
        ↓
Separate UI, service, and API concerns
        ↓
Use JSDoc and linting
        ↓
Introduce modern syntax carefully
        ↓
Introduce TypeScript gradually if desired
```

The goal is not to make every old JavaScript file look modern
immediately.

The goal is to make the frontend progressively:

``` text
Consistent
    +
Readable
    +
Secure
    +
Testable
    +
Maintainable
    +
Easy to modernize
```
