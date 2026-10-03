# ZF1 + jQuery + DHTMLX 3.5 Global API Error Handling

## 1. Purpose

This guide defines a gradual compatibility-layer strategy for
centralized API/authentication error handling in the existing ZF1
application.

The application currently uses:

-   jQuery AJAX
-   DHTMLX 3.5 `dhtmlxAjax`
-   DHTMLX 3.5 DataProcessor (`dataProcessor`)
-   ZF1 API endpoints
-   Existing session/token authentication

The main problem is that many JavaScript pages currently need to detect
and handle authentication failures individually. Rewriting all existing
pages around a new API client would be expensive and risky.

The recommended solution is to preserve existing calls and introduce
centralized adapters.

## 2. Main Problem

Individual pages should not repeatedly need logic for:

``` text
logged out
session expired
token expired
invalid token
authentication required
permission denied
server error
```

With many pages, duplicated error handling becomes difficult to
maintain.

## 3. Target Architecture

``` text
Existing Pages
      |
      +-- jQuery AJAX -----------+
      |                          |
      +-- DHTMLX AJAX -----------+--> Compatibility Adapters
      |                          |             |
      +-- DHTMLX DataProcessor --+             v
                                           S3ApiError
                                               |
                          +--------------------+--------------------+
                          |                    |                    |
                          v                    v                    v
                         401                  403                  500
                          |                    |                    |
                          v                    v                    v
                       S3Auth             Permission            System
                                           Handler              Handler
```

Normal responses should continue to reach existing callbacks.

## 4. Migration Principle

Do not rewrite all existing calls such as:

``` javascript
$.ajax(...);
dhtmlxAjax.get(...);
dhtmlxAjax.post(...);
dp.sendData();
```

Instead, add centralized interception around the request mechanisms.

For existing pages:

``` text
Existing JavaScript
       |
       v
Existing AJAX library
       |
       v
Compatibility adapter
       |
       v
Global error handling
```

For future/new pages, a cleaner `S3Api` abstraction can be introduced
later.

## 5. Standard HTTP Status Codes

ZF1 endpoints should gradually use consistent HTTP status codes:

``` text
200  Success
201  Created
400  Bad request
401  Authentication required/failed
403  Authenticated but not permitted
404  Not found
405  Method not allowed
409  Conflict
422  Validation/business input error
500  Internal server error
```

The key distinction is:

``` text
401 = authentication problem

Examples:
- login required
- session expired
- token missing
- token expired
- invalid token

403 = authorization problem

Example:
- user is authenticated but lacks permission
```

## 6. Standard API Error Contract

Authentication error:

``` json
{
    "success": false,
    "error": {
        "code": "TOKEN_EXPIRED",
        "message": "Your session has expired.",
        "details": null
    }
}
```

Logged-out error:

``` json
{
    "success": false,
    "error": {
        "code": "AUTH_REQUIRED",
        "message": "Authentication is required.",
        "details": null
    }
}
```

Permission error:

``` json
{
    "success": false,
    "error": {
        "code": "ACCESS_DENIED",
        "message": "You do not have permission to perform this operation.",
        "details": null
    }
}
```

Success:

``` json
{
    "success": true,
    "data": {}
}
```

## 7. Three Error Levels

### Level 1 --- Global / Infrastructure

``` text
AUTH_REQUIRED
TOKEN_EXPIRED
INVALID_TOKEN
ACCESS_DENIED
SERVER_ERROR
NETWORK_ERROR
```

Handled centrally by `S3ApiError` / `S3Auth`.

### Level 2 --- Module

Examples:

``` text
EMAIL_TEMPLATE_NOT_FOUND
EMAIL_SEND_FAILED
ATTACHMENT_TOO_LARGE
```

Handled by the Email module where appropriate.

### Level 3 --- Page / Form

Examples:

``` text
SUBJECT_REQUIRED
INVALID_EMAIL_ADDRESS
INVALID_DATE
```

Handled by the current page/form.

Developers should not repeatedly implement Level 1 handling.

## 8. Global Error Handler

Create:

``` text
public/js/s3/s3-api-error.js
```

Example:

``` javascript
var S3ApiError = {

    loginRedirecting: false,

    handle: function (status, responseText) {

        switch (status) {

            case 401:
                this.handleAuthenticationError(responseText);
                return true;

            case 403:
                this.handleAccessDenied(responseText);
                return true;

            case 500:
                this.handleServerError(responseText);
                return true;
        }

        return false;
    },

    handleAuthenticationError: function (responseText) {

        var response = this.parseJson(responseText);
        var code = '';

        if (response && response.error) {
            code = response.error.code || '';
        }

        switch (code) {

            case 'TOKEN_EXPIRED':
            case 'AUTH_REQUIRED':
            case 'INVALID_TOKEN':
            default:
                this.loginRequired();
                break;
        }
    },

    handleAccessDenied: function () {
        alert(
            'You do not have permission ' +
            'to perform this operation.'
        );
    },

    handleServerError: function () {
        alert('A server error occurred.');
    },

    loginRequired: function () {

        if (this.loginRedirecting) {
            return;
        }

        this.loginRedirecting = true;

        alert(
            'Your session has expired. ' +
            'Please log in again.'
        );

        window.top.location.href = '/login';
    },

    parseJson: function (text) {

        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }
};
```

The `loginRedirecting` flag prevents several simultaneous failed
requests from displaying several messages or starting several redirects.

## 9. jQuery Global Integration

Existing pages can remain:

``` javascript
$.ajax({
    url: '/api/email/getlist',
    type: 'GET',

    success: function (data) {
        // Existing page logic.
    }
});
```

Add one global handler:

``` javascript
$(document).ajaxError(
    function (
        event,
        jqXHR,
        ajaxSettings,
        thrownError
    ) {
        S3ApiError.handle(
            jqXHR.status,
            jqXHR.responseText
        );
    }
);
```

Architecture:

``` text
Existing $.ajax()
       |
       v
     jQuery
       |
       +-- normal --> existing callback
       |
       +-- error --> $(document).ajaxError()
                            |
                            v
                        S3ApiError
```

This can cover many existing pages without modifying every `$.ajax()`
call.

## 10. DHTMLX AJAX Integration

The application also uses:

``` javascript
dhtmlxAjax.get(...);
dhtmlxAjax.post(...);
```

Desired behavior:

``` text
Existing dhtmlxAjax
       |
       v
DHTMLX compatibility adapter
       |
       +-- normal --> existing callback
       |
       +-- global error --> S3ApiError
```

Because the project uses DHTMLX 3.5, the adapter should be built against
the actual DHTMLX JavaScript files deployed by the application.

Do not blindly override DHTMLX internals based on another version.

The exact `dhtmlxAjax` implementation should be inspected before adding
a global wrapper.

## 11. DHTMLX DataProcessor Integration

DataProcessor requires extra care because it maintains update state and
expects particular server responses.

Typical code:

``` javascript
var dp = new dataProcessor(
    '/api/email/save'
);

dp.init(grid);
```

Target:

``` text
Grid/Form
    |
    v
DataProcessor
    |
    +-- normal DP response --> existing behavior
    |
    +-- global auth/system error
             |
             v
          DP adapter
             |
             v
          S3ApiError
```

If the application already has a common DataProcessor factory/helper,
integrate there.

Otherwise, inspect the exact DHTMLX 3.5 DataProcessor implementation and
determine whether a safe global event/prototype hook is available.

## 12. Preserve Existing Successful Responses

During Phase 1:

``` text
HTTP 200
   |
   v
Existing callback
```

should continue to work exactly as before.

Initially intercept global conditions such as:

``` text
401
403
500
network failure
```

Do not unnecessarily redesign every successful API response at the same
time.

## 13. Legacy `200 OK` Authentication Errors

Some old endpoints may return authentication failures with HTTP 200:

``` json
{
    "success": false,
    "error": "token_expired"
}
```

New/converted endpoints should instead return HTTP 401 with the standard
error contract.

During migration, a compatibility adapter may temporarily recognize
both:

``` text
                 Response
                    |
             +------+------+
             |             |
          HTTP 401      HTTP 200
             |             |
             |       known legacy
             |       auth response
             |             |
             +------+------+
                    |
                    v
                S3ApiError
```

Legacy-response detection should be transitional.

## 14. ZF1 Server-Side Centralization

Do not repeat authentication checks in every API controller.

Preferred architecture:

``` text
HTTP Request
      |
      v
ZF1 API Authentication Layer
      |
      +-- detect protected API
      +-- read credentials
      +-- validate session/JWT
      +-- load user
      |
      +-- failure --> standardized 401 JSON
      |
      v
API Controller
      |
      v
Service
      |
      v
Model / MariaDB
```

This centralizes authentication on the server while adapters centralize
handling in the browser.

## 15. Do Not Add Automatic Refresh First

A future design can do:

``` text
Request
   |
   v
401 TOKEN_EXPIRED
   |
   v
Refresh token
   |
   v
New access token
   |
   v
Retry request
```

However, do not make this the first migration step.

The application has:

``` text
jQuery AJAX
dhtmlxAjax
DataProcessor
```

Automatic request replay is more complicated for state-changing requests
and DataProcessor state.

The safer first stage is:

``` text
Authentication failure
        |
        v
      HTTP 401
        |
        v
 Global error handler
        |
        v
 Show one message
        |
        v
 Redirect to login
```

## 16. Future Refresh Manager

Later:

``` text
Request A ---+
Request B ---+--> token expired
Request C ---+
                   |
                   v
             Refresh Manager
                   |
             refresh running?
              /          \
            no            yes
            |              |
            v              v
       refresh token    queue request
            |
            v
       new token
            |
            v
       retry requests
       where safe
```

Only one refresh operation should occur at a time.

Conceptual state:

``` javascript
var S3Auth = {
    refreshing: false,
    queue: []
};
```

Do not assume every `POST`, `PUT`, `PATCH`, `DELETE`, or DataProcessor
operation is automatically safe to replay.

## 17. Recommended Files

``` text
public/
└── js/
    └── s3/
        ├── s3-api-error.js
        ├── s3-auth.js
        ├── s3-jquery-adapter.js
        ├── s3-dhtmlx-ajax-adapter.js
        ├── s3-dhtmlx-dp-adapter.js
        └── s3-api.js
```

Responsibilities:

``` text
s3-api-error.js
    Global error classification/handling.

s3-auth.js
    Login/session/token handling.
    Future refresh support.

s3-jquery-adapter.js
    Global jQuery AJAX integration.

s3-dhtmlx-ajax-adapter.js
    dhtmlxAjax compatibility integration.

s3-dhtmlx-dp-adapter.js
    DataProcessor compatibility integration.

s3-api.js
    Cleaner API abstraction for new development.
```

## 18. Migration Stages

### Stage 1 --- ZF1 error standardization

Introduce standard `401`, `403`, and `500` behavior and JSON errors.

### Stage 2 --- `S3ApiError`

Add the global JavaScript error handler.

### Stage 3 --- jQuery

Add `$(document).ajaxError(...)`.

Test existing pages without changing their individual AJAX calls.

### Stage 4 --- DHTMLX AJAX

Inspect the actual DHTMLX 3.5 source and implement a safe global
adapter.

### Stage 5 --- DataProcessor

Inspect the actual DP implementation and add the safest common
integration.

### Stage 6 --- New API client

Use `S3Api` for new pages while legacy pages continue through
compatibility adapters.

### Stage 7 --- Optional token refresh

Only after global handling is stable, consider controlled refresh and
request retry.

## 19. Recommended First Test

Start with one test endpoint and jQuery.

Test:

``` text
200 normal response
401 logged out
401 expired token
401 invalid token
403 permission denied
500 server error
```

Confirm:

-   Existing successful callback still works.
-   Authentication message appears only once.
-   Top-level login redirect works even from iframe-based pages.
-   Existing page-specific business errors still work.
-   No large-scale page changes are required.

Then proceed to DHTMLX AJAX and DataProcessor.

## 20. CSRF Note

Do not exempt a request from CSRF merely because its URL begins with
`/api`.

Protection depends on the authentication mechanism.

Conceptually:

``` text
Session/cookie authentication
        |
        +-- state-changing request
                |
                v
             CSRF check


Bearer-token-only API
        |
        v
Bearer authentication validation
```

## 21. Final Architecture

``` text
                      EXISTING APPLICATION
                              |
            +-----------------+-----------------+
            |                 |                 |
            v                 v                 v
        jQuery AJAX       dhtmlxAjax        DataProcessor
            |                 |                 |
            v                 v                 v
      jQuery Adapter      DHX Adapter        DP Adapter
            |                 |                 |
            +-----------------+-----------------+
                              |
                              v
                         S3ApiError
                              |
                   +----------+----------+
                   |          |          |
                   v          v          v
                  401        403        500
                   |          |          |
                   v          v          v
                S3Auth    Permission   System
                   |
                   v
                 Login

                              ^
                              |
                         ZF1 API Layer
                              |
                  standardized HTTP/JSON errors
```

## 22. Conclusion

The application does not need a large immediate JavaScript rewrite.

Preserve existing:

``` text
$.ajax()
dhtmlxAjax
dataProcessor
```

calls and introduce centralized compatibility adapters.

The core rules are:

1.  Handle authentication/infrastructure failures centrally.
2.  Preserve normal existing responses during the initial migration.
3.  Standardize ZF1 HTTP status codes and JSON errors.
4.  Integrate jQuery first because it is low risk.
5.  Inspect the exact DHTMLX 3.5 source before wrapping `dhtmlxAjax` or
    DataProcessor.
6.  Do not implement automatic retry until basic global handling is
    stable.
7.  Let individual pages focus on business/page-specific errors.
8.  Migrate gradually instead of modifying hundreds of pages at once.
