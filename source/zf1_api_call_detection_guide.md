# Detecting API Calls in Zend Framework 1 (ZF1)

## 1. API URL Structure

This guide assumes:

``` text
http://example.com/api/email/getlist
http://example.com/api/blog/getlist
```

Convention:

``` text
/api/{controller}/{action}
```

For `/api/email/getlist`:

``` text
module     = api
controller = email
action     = getlist
```

A typical structure is:

``` text
application/
└── modules/
    └── api/
        ├── controllers/
        │   ├── EmailController.php
        │   └── BlogController.php
        ├── models/
        ├── services/
        └── views/
```

## 2. Detect an API Request Inside a Controller

Prefer the ZF1 request object instead of manually parsing `REQUEST_URI`:

``` php
<?php

$request = $this->getRequest();

if (strtolower($request->getModuleName()) === 'api') {
    // This is an API request.
}
```

You can inspect all routing values:

``` php
$request->getModuleName();      // api
$request->getControllerName();  // email
$request->getActionName();      // getlist
```

## 3. Reusable Function

``` php
function isApiRequest(Zend_Controller_Request_Abstract $request)
{
    return strtolower($request->getModuleName()) === 'api';
}
```

Usage:

``` php
$request = $this->getRequest();

if (isApiRequest($request)) {
    // API request
} else {
    // Normal web request
}
```

## 4. Recommended Global Detection with a Controller Plugin

If API detection is used for authentication, CSRF, logging,
authorization, HTTP-method checking, or JSON processing, centralize it
in a ZF1 controller plugin.

Create:

``` text
application/
└── plugins/
    └── ApiRequestPlugin.php
```

``` php
<?php

class Application_Plugin_ApiRequestPlugin
    extends Zend_Controller_Plugin_Abstract
{
    public function routeShutdown(
        Zend_Controller_Request_Abstract $request
    ) {
        if (!$this->isApiRequest($request)) {
            return;
        }

        $module = $request->getModuleName();
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        // API-specific processing here.
    }

    private function isApiRequest(
        Zend_Controller_Request_Abstract $request
    ) {
        return strtolower($request->getModuleName()) === 'api';
    }
}
```

Register it in the bootstrap:

``` php
protected function _initPlugins()
{
    $front = Zend_Controller_Front::getInstance();

    $front->registerPlugin(
        new Application_Plugin_ApiRequestPlugin()
    );
}
```

This detects:

``` text
/api/email/getlist
/api/blog/getlist
/api/email/save
/api/blog/delete
```

while normal routes such as these are not API-module requests:

``` text
/user/login
/document/view
```

## 5. Detect a Specific Controller or Action

Email API:

``` php
if (
    strtolower($request->getModuleName()) === 'api'
    && strtolower($request->getControllerName()) === 'email'
) {
    // Email API
}
```

Specific endpoint:

``` php
if (
    strtolower($request->getModuleName()) === 'api'
    && strtolower($request->getControllerName()) === 'email'
    && strtolower($request->getActionName()) === 'getlist'
) {
    // /api/email/getlist
}
```

## 6. Plain PHP Detection Before ZF1 Routing

If detection is required before ZF1 routing is available:

``` php
function isApiRequest()
{
    if (!isset($_SERVER['REQUEST_URI'])) {
        return false;
    }

    $path = parse_url(
        $_SERVER['REQUEST_URI'],
        PHP_URL_PATH
    );

    return preg_match('#^/api(?:/|$)#i', $path) === 1;
}
```

This detects:

``` text
/api
/api/
/api/email/getlist
/api/blog/getlist?id=100
```

but not:

``` text
/apitest/email
/user/api
/myapi/email
```

## 7. Recommended Architecture

``` text
HTTP Request
     │
     ▼
Zend Framework Router
     │
     ├── Module
     ├── Controller
     └── Action
     │
     ▼
Controller Plugin
     │
     ├── API request?
     │       │
     │       └── module === "api"
     │
     ▼
Controller / Action
```

The recommended ZF1 check is:

``` php
$isApi = (
    strtolower($request->getModuleName()) === 'api'
);
```

This checks what ZF1 actually routed the request to rather than relying
on assumptions about the raw URL.

## 8. API-Specific Security Processing

A global plugin can become the central point for common API processing:

``` php
<?php

class Application_Plugin_ApiRequestPlugin
    extends Zend_Controller_Plugin_Abstract
{
    public function routeShutdown(
        Zend_Controller_Request_Abstract $request
    ) {
        if (!$this->isApiRequest($request)) {
            return;
        }

        $controller = $request->getControllerName();
        $action = $request->getActionName();

        // Possible processing:
        // - authentication
        // - authorization
        // - CSRF validation where appropriate
        // - HTTP method validation
        // - request logging
        // - JSON response configuration
    }

    private function isApiRequest(
        Zend_Controller_Request_Abstract $request
    ) {
        return strtolower(
            $request->getModuleName()
        ) === 'api';
    }
}
```

## 9. Summary

For URLs following:

``` text
/api/{controller}/{action}
```

the simplest ZF1 API detection is:

``` php
if (strtolower($request->getModuleName()) === 'api') {
    // API request
}
```

For application-wide detection, use a `Zend_Controller_Plugin_Abstract`
plugin and perform the check after routing, such as in
`routeShutdown()`.

Use `$_SERVER['REQUEST_URI']` parsing only when detection is required
before ZF1 routing information is available.
