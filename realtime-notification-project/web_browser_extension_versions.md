# Browser Version Compatibility for Legacy Browser Extensions

## Overview

This document summarizes browser versions relevant to legacy browser
extensions that access Windows functionality such as the Windows
Registry through older browser plug-in technologies.

The exact compatibility depends on the technology used by the extension.
In particular, **NPAPI** and **ActiveX** are different technologies.

------------------------------------------------------------------------

## 1. NPAPI Support

If the extension uses an NPAPI plug-in to access Windows functionality,
the historical compatibility is approximately:

  -------------------------------------------------------------------------
  Browser            Last version with        First version Notes
                         NPAPI support        without NPAPI 
  --------------- -------------------- -------------------- ---------------
  Google Chrome                     44                   45 Chrome 42
                                                            disabled NPAPI
                                                            by default;
                                                            Chrome 45
                                                            removed NPAPI
                                                            completely.

  Firefox                           51                   52 Firefox 52
                                                            removed NPAPI
                                                            support, with a
                                                            temporary Flash
                                                            exception.

  Firefox ESR                   52 ESR   Later ESR releases Firefox 52 ESR
                                                            provided an
                                                            enterprise
                                                            transition
                                                            path.

  Safari macOS                      11                   12 Safari 12
                                                            removed legacy
                                                            NPAPI plug-ins
                                                            other than
                                                            Flash.

  Opera                             36                   37 Opera removed
                                                            NPAPI support
                                                            around version
                                                            37.

  Microsoft Edge         Not supported                  --- Edge Legacy did
  Legacy                                                    not support the
                                                            old NPAPI
                                                            model.

  Internet              Not applicable                  --- Internet
  Explorer                                                  Explorer used
                                                            ActiveX rather
                                                            than NPAPI.
  -------------------------------------------------------------------------

### Approximate NPAPI compatibility range

``` text
Chrome   <= 44
Firefox  <= 51
Safari   <= 11
Opera    <= 36
Edge     Not supported
IE       NPAPI not applicable
```

> These versions describe historical NPAPI support. They should not be
> interpreted as recommendations for running an old browser today.

------------------------------------------------------------------------

## 2. ActiveX

ActiveX is a different technology from NPAPI.

A typical ActiveX-based architecture was:

``` text
Internet Explorer
        |
        v
    ActiveX
        |
        v
Windows component / DLL
        |
        v
Windows Registry
```

Internet Explorer 11 supported ActiveX.

Modern Chrome, Edge, Firefox, and Safari do not provide the old Internet
Explorer ActiveX model.

Therefore, an extension that depends on ActiveX should not be expected
to work in modern Chromium, Firefox, or Safari browsers.

------------------------------------------------------------------------

## 3. Modern Native Messaging Architecture

For a modern browser extension that needs Windows-specific
functionality, the preferred architecture is generally:

``` text
Web Application
       |
       v
Browser Extension
       |
       v
Native Messaging
       |
       v
Windows Native Application (.exe)
       |
       +------------------+
       |                  |
       v                  v
Windows Registry     USB / Smart Card
                         |
                         v
                  Certificate / Private Key
```

The native Windows application can perform operations that normal
browser JavaScript cannot directly perform.

For example:

-   Read Windows Registry values
-   Write Windows Registry values
-   Access Windows certificate stores
-   Communicate with USB security tokens
-   Perform cryptographic signing through supported middleware
-   Return certificate information and signatures to the extension

The private key should normally remain inside the security token when
the token is designed for protected-key operations.

------------------------------------------------------------------------

## 4. Modern Browser Extension Support

For a modern implementation, browser support depends on the extension
API and native-messaging implementation rather than NPAPI.

  -----------------------------------------------------------------------
  Browser                 Modern WebExtension     Native integration
                          support                 
  ----------------------- ----------------------- -----------------------
  Google Chrome           Yes                     Native Messaging

  Microsoft Edge          Yes                     Native Messaging
  (Chromium)                                      

  Firefox                 Yes                     Native Messaging

  Opera                   Yes                     Chromium-based
                                                  extension APIs / Native
                                                  Messaging

  Safari                  Yes, with Safari Web    Safari-specific native
                          Extensions              integration
                                                  architecture
  -----------------------------------------------------------------------

The exact minimum version should be determined from the APIs actually
used by the extension.

------------------------------------------------------------------------

## 5. BroadcastChannel Reference

If the extension or web application also uses the `BroadcastChannel`
API:

  Browser                       First supported version
  --------------------------- -------------------------
  Google Chrome                                      54
  Microsoft Edge (Chromium)                          79
  Firefox                                            38
  Safari                                           15.4
  Opera                                              41

`BroadcastChannel` is not a replacement for Native Messaging. It is
primarily a mechanism for communication between browser contexts
belonging to the same origin.

For communication such as:

``` text
React Web Application
        |
        v
Browser Extension
        |
        v
Native Windows Application
        |
        v
USB Security Token
```

the extension's messaging mechanism and Native Messaging are more
appropriate than using `BroadcastChannel` as the Windows-integration
mechanism.

------------------------------------------------------------------------

## 6. Important Compatibility Question

Before deciding the minimum browser version for an existing extension,
identify how the extension accesses Windows.

Possible technologies include:

-   NPAPI
-   ActiveX
-   IE Browser Helper Object (BHO)
-   Native Messaging
-   Windows native application
-   DLL
-   PKCS#11
-   Windows CryptoAPI / CNG
-   Smart Card APIs
-   USB APIs

For example:

### Legacy NPAPI

``` text
Browser
   |
   v
Extension / Plug-in
   |
   v
NPAPI
   |
   v
Windows component
```

This will not work in modern Chrome, Edge, Firefox, or Safari.

### Modern Native Messaging

``` text
Browser
   |
   v
Extension
   |
   v
Native Messaging
   |
   v
Windows .exe
   |
   +--> Registry
   |
   +--> Certificate Store
   |
   +--> USB / Smart Card
```

This is the architecture to investigate when migrating a legacy
Windows-integrated extension.

------------------------------------------------------------------------

## 7. Recommended Next Step

To determine the **exact last supported browser version for the existing
extension**, inspect the extension source code and identify the
Windows-integration technology.

Useful things to look for include:

``` javascript
NPObject
```

``` javascript
ActiveXObject
```

``` javascript
chrome.runtime.connectNative()
```

``` javascript
chrome.runtime.sendNativeMessage()
```

``` javascript
browser.runtime.connectNative()
```

``` javascript
browser.runtime.sendNativeMessage()
```

Also check whether the extension contains:

``` text
manifest.json
native host manifest
.dll
.exe
.npapi
.ocx
```

Once the technology is identified, the browser-version cutoff can be
determined much more precisely.

------------------------------------------------------------------------

## References

-   Chromium: NPAPI deprecation
-   Mozilla: NPAPI plug-in support
-   Apple: Safari 12 release notes
-   Chrome Extensions: Native Messaging
-   MDN: WebExtensions API differences
-   MDN: BroadcastChannel API
