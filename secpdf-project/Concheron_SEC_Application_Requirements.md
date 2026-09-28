# Concheron SEC Document Distribution and Secure Viewing System

## Application Requirements

**Version:** 1.0\
**Status:** Consolidated requirements based on the current agreed
architecture\
**Target subsidiary platform:** PHP 7.4, Zend Framework 1, DHTMLX 3.5,
MariaDB, Apache

------------------------------------------------------------------------

## 1. Purpose

The Concheron SEC Document System provides secure distribution,
transfer, import, storage, and viewing of confidential PDF documents
between the Concheron parent company and its subsidiary companies.

The system must protect SEC documents throughout their lifecycle:

1.  Document preparation at Concheron.
2.  Distribution through the central Concheron SEC portal.
3.  Authentication of subsidiary SEC staff using SEC USB devices.
4.  Encryption of downloaded SEC documents for the specific USB device.
5.  Transfer of encrypted SEC documents to the subsidiary.
6.  Secure import into the subsidiary's local system.
7.  Re-encryption using the subsidiary server key.
8.  Secure storage.
9.  Staff certificate authentication and authorization.
10. Secure server-side document rendering.
11. Protected browser viewing.
12. Watermarking and auditing.

The system must not rely solely on browser restrictions or secrecy of
cryptographic algorithms for document security.

## 2. Organizations

The system consists of Concheron as the parent company and multiple
subsidiary companies, such as Company A, Company B, and Company C.

Each subsidiary operates its own business-management website. The
current subsidiary technology includes PHP 7.4, Zend Framework 1, DHTMLX
3.5, MariaDB, and Apache. Subsidiary systems may operate on isolated
internal networks.

## 3. High-Level Architecture

### 3.1 Concheron Environment

Concheron operates a central SEC portal accessible only through an
authorized private VPN.

The portal is responsible for SEC document management, SEC staff
authentication, SEC USB authentication, USB certificate verification,
document authorization, SEC package generation, document encryption,
digital signing, download management, and download auditing.

### 3.2 Subsidiary Environment

Each subsidiary operates its own local SEC document system integrated
into its business-management website.

The subsidiary system is responsible for SEC package upload, package
verification, USB-assisted package decryption, document import,
server-side re-encryption, secure local storage, staff certificate
authentication, staff authorization, server-side document decryption,
PDF page rendering, dynamic watermarking, secure browser viewing, and
audit logging.

## 4. Overall Document Flow

``` text
Concheron
    |
Original SEC PDF
    |
SEC Document Management
    |
Concheron SEC Portal
    ^
    |
Private VPN
    |
SEC Staff
    |
SEC USB + Password/PIN
    |
USB Certificate Authentication
    |
Authorized Document Download
    |
Generate Random Document Key
    |
Encrypt PDF using AES-256-GCM
    |
Wrap Document Key using SEC USB Public Key
    |
Digitally Sign SEC Package
    |
Encrypted SEC Package
    |
Transfer to Subsidiary
    |
Subsidiary ZF1 Upload
    |
SEC USB + Password/PIN
    |
Concheron PHP Extension
    |
    +-- Verify Package
    +-- Verify Signature
    +-- Verify Recipient
    +-- Use USB Private Key
    +-- Unwrap Document Key
    +-- Decrypt Document
    |
Temporary Plaintext PDF
    |
    +----> Render Pages
    |
Re-encrypt using Server Key
    |
Encrypted Original PDF
    |
Protected Storage
```

## 5. Private VPN Requirement

The Concheron SEC portal must not be publicly accessible for normal SEC
operations. SEC staff must first connect to the authorized private VPN.

VPN access alone must not grant access to SEC documents. Normal user
authentication and SEC USB authentication are also required.

## 6. SEC USB Device

Concheron issues authorized SEC USB devices to designated SEC staff
members at subsidiary companies.

Each SEC USB device must contain or securely provide access to a unique
cryptographic identity, including a certificate, public key,
corresponding private key, certificate serial number, subsidiary
identity, device identifier, assigned staff identity where applicable,
and validity period.

The private key should preferably be non-exportable. A cryptographic USB
token or equivalent protected hardware is preferred over an ordinary USB
flash drive containing copyable private-key files.

## 7. USB Password/PIN

Possession of the SEC USB alone must not be sufficient. The user must
enter the USB password or PIN before private-key operations are
permitted.

Authentication requires the USB device, valid certificate, correct
PIN/password, and proof of possession of the corresponding private key.

## 8. USB Certificate Authentication

The system must not authenticate a USB device merely because its public
certificate can be read.

The Concheron server should generate a random challenge. After the user
unlocks the USB credential with the PIN/password, the USB private key
signs the challenge. The server verifies the signature using the
certificate public key.

The USB private key must never be transmitted to the Concheron server.

## 9. SEC Portal Authorization

After authentication, the portal must evaluate user identity, USB
identity, subsidiary identity, account status, USB status, certificate
status, permissions, and document access.

Knowing a document identifier or URL must never be sufficient to
download an SEC document.

## 10. SEC Document Download Encryption

Downloaded SEC documents must not be ordinary plaintext PDFs.

A cryptographically secure random content-encryption key should encrypt
the PDF using authenticated symmetric encryption such as AES-256-GCM.

The document/content key must then be wrapped using the public key
associated with the authenticated SEC USB certificate.

The complete PDF must not be directly encrypted using RSA or another
asymmetric public-key algorithm.

## 11. Per-USB Protection

A package downloaded for SEC USB A must require the corresponding USB A
private key for initial decryption/import.

Another USB device, including a device issued to another subsidiary,
must not be able to unwrap the package's document key unless it was
explicitly made an authorized recipient.

## 12. SEC Package Format

Concheron should define an SEC package/container format, for example:

``` text
SEC-2026-000184.sec
|
+-- manifest
+-- encrypted-document
+-- wrapped-document-key
+-- recipient information
+-- cryptographic metadata
+-- Concheron digital signature
```

The Concheron PHP extension should interpret this package format.

## 13. SEC Manifest

Authenticated package metadata should include the package
format/version, document ID, document version, title, classification,
creation timestamp, publisher, intended subsidiary, intended
USB/certificate identifier, encryption metadata, content hash,
expiration where applicable, and algorithm/version identifiers.

## 14. Concheron Digital Signature

Concheron must digitally sign SEC packages.

Encryption provides confidentiality and controls who can read the
document. The digital signature establishes package authenticity and
integrity and detects unauthorized modification.

A subsidiary must reject a package if required signature verification
fails.

## 15. Package Import

Only authorized SEC staff should be able to import SEC packages.

The SEC manager uploads the `.sec` package through the subsidiary site,
provides the required SEC USB, enters the USB PIN/password, and invokes
the Concheron cryptographic component to verify and decrypt the package.

## 16. Import Validation

Before accepting an SEC package, the system must validate at least:

1.  Package structure.
2.  Package format version.
3.  Concheron digital signature.
4.  Package integrity.
5.  Document metadata.
6.  Intended subsidiary.
7.  Intended USB/recipient where applicable.
8.  USB certificate validity.
9.  Proof of USB private-key possession.
10. Wrapped document-key decryption.
11. Authenticated document decryption.
12. Document identifier and version.
13. Duplicate/replay conditions.

If a critical verification step fails, the document must not be
imported.

## 17. Concheron PHP Extension

Concheron provides subsidiary companies with a compiled PHP extension
for SEC cryptographic operations.

Linux may use `concheron_sec.so`; Windows may use
`php_concheron_sec.dll`.

The extension must match supported PHP versions, operating systems, CPU
architectures, and PHP build configurations. The current target includes
PHP 7.4.

## 18. Cryptographic Implementation Encapsulation

Concheron does not distribute the source implementation of its SEC
cryptographic component to subsidiaries.

Subsidiary applications call high-level functions exposed by the
compiled extension. The interface should avoid unnecessarily returning
raw USB private keys, server private keys, document encryption keys, or
other sensitive key material to ordinary PHP code.

Security must nevertheless rely on strong keys, standard cryptography,
correct protocol design, certificate validation, authenticated
encryption, signatures, authorization, and key management---not solely
on implementation secrecy.

## 19. Server Key

Each subsidiary server has a server-side cryptographic key used to
protect imported SEC documents at rest.

The SEC USB private key controls initial package access/import. The
server key controls long-term protection of imported documents.

The server private key must not be accessible to normal staff.

## 20. Server-Side Re-Encryption

After successful package decryption, the original PDF must not remain
permanently in plaintext.

The server must re-encrypt the imported original PDF using the
server-side key and store only the protected master copy for long-term
retention.

## 21. Temporary Plaintext Handling

Plaintext PDF data should exist only for the minimum time required for
processing.

Temporary plaintext must never be placed in the public web directory,
must use restricted permissions and unpredictable paths, must not be
exposed through HTTP, and must be removed promptly after processing.

The design must not assume that ordinary file deletion guarantees
physical erasure on all storage media.

## 22. Server Key Management

Server-key management must support secure storage, key identifiers,
rotation, backup/recovery, access restrictions, versioning, and
auditing.

Each encrypted document should identify which server-key version
protects it.

## 23. Staff Certificate Mode --- Mode A

The selected staff certificate architecture is **Mode A**.

Staff certificates are used for authentication, identity verification,
and authorization. They are **not** used to decrypt SEC documents.

Document decryption remains a controlled server-side operation.

## 24. Separation of Credentials

Three major credential/key classes are required:

  -----------------------------------------------------------------------
  Credential                          Primary Purpose
  ----------------------------------- -----------------------------------
  SEC USB certificate/private key     SEC portal authentication and
                                      downloaded-package
                                      protection/import

  Subsidiary server key               Local SEC document
                                      encryption/decryption at rest

  Staff certificate/private key       Staff authentication and
                                      authorization
  -----------------------------------------------------------------------

These credentials must remain logically and operationally separate.

## 25. Staff Certificate Verification

Before viewing an SEC document, the application must verify the staff
certificate.

Verification should include trusted Concheron certificate chain,
certificate signature, validity dates, revocation/status where
supported, correct subsidiary association, staff identity, certificate
policy/key usage, account mapping, and proof of possession of the
private key.

A public certificate file alone must not be sufficient for
authentication.

## 26. Staff Certificate Challenge-Response

Where supported, staff authentication should use challenge-response.

The subsidiary server generates a random challenge. The staff credential
signs it with the staff private key. The server verifies the signature
using the staff certificate public key.

The staff private key must not be transmitted to the server.

## 27. Staff Authorization

Successful certificate verification does not automatically authorize
access to every document.

The application must separately evaluate account status, SEC
permissions, document permissions, classification permissions, and other
applicable policy.

## 28. Staff Viewing Flow

``` text
Staff Login
    |
Select SEC Document
    |
Staff Certificate Verification
    |
Proof of Private-Key Possession
    |
Map Certificate to Staff Account
    |
Check SEC/Document Permissions
    |
Create Secure View Session
    |
Server Decrypts Document
    |
Render Requested Page
    |
Apply Personalized Watermark
    |
Send Rendered Page
    |
Canvas Viewer
```

## 29. Original PDF Browser Protection

The original PDF must never be directly exposed to the browser.

The browser should receive only authorized rendered pages through
authenticated application endpoints.

## 30. Server-Side PDF Rendering

PDF rendering must occur on the subsidiary server using an approved
renderer such as Poppler, ImageMagick/Imagick where appropriate, or
another approved engine.

The plaintext PDF must never become a publicly accessible web file.

## 31. Protected Render Cache

The system may maintain rendered page images for performance, but they
must remain outside the web root and must not be directly accessible
through predictable public URLs.

## 32. Dynamic Watermark

Before a rendered page is returned, the server must apply a personalized
watermark.

A watermark may include Concheron, subsidiary, employee identifier,
document identifier, view/session display code, timestamp, and
classification.

Sensitive credentials, session IDs, CSRF tokens, PINs, passwords,
private keys, raw viewer tokens, and encryption keys must never appear
in the watermark.

Repeated translucent diagonal watermarking plus an optional footer is
recommended for sensitive SEC pages.

## 33. Secure Page API

Rendered pages must be delivered through an authenticated application
endpoint.

For each page request, the server must validate the user session,
certificate-authentication state, document authorization, view session,
temporary token, token expiration, requested page range, rate limits,
and relevant security policy.

## 34. View Sessions

Opening an SEC document should create a temporary view session
containing a view-session ID, document ID, staff ID, certificate
identity, token hash, non-sensitive display code, timestamps, and
appropriate client/network metadata.

Tokens must be cryptographically random and should be stored as hashes
where practical.

View sessions must support maximum lifetime, idle timeout, explicit
close, logout termination, and administrative termination.

## 35. Browser Viewer

The SEC viewer should use a custom Canvas-based viewer rather than the
browser's native PDF viewer.

It should support continuous vertical scrolling, mouse-wheel scrolling,
page number display, direct page navigation, zoom, 100% mode, fit width,
appropriate keyboard controls, lazy page loading, current-page
detection, loading indicators, and error handling.

## 36. Continuous Scrolling and Lazy Loading

Pages should be displayed vertically. Native browser scrolling should be
used for ordinary mouse-wheel movement.

The viewer must not download every page when a large document opens.
Page placeholders should be created and nearby pages loaded lazily, for
example using `IntersectionObserver`.

## 37. Viewer Memory Management

Large documents must not retain hundreds of full-resolution canvases in
browser memory.

Only nearby pages should remain loaded, such as the current page plus
several pages before and after it. Distant pages may be unloaded and
securely requested again when needed.

## 38. Zoom and Navigation

The viewer should support configurable zoom levels, fit-width mode,
automatic current-page detection, and direct page-number navigation.

Higher-resolution rendered pages may be provided only when high zoom
levels require them.

## 39. Browser Deterrents

The application may deter common actions such as right-click, Ctrl+S,
Ctrl+P, image dragging, simple text selection, and basic copy
operations.

These controls are deterrents only and must never be treated as the
primary security boundary.

## 40. Fundamental Browser Limitation

No browser-based system can absolutely prevent an authorized person from
reproducing visible information through screenshots, screen recording,
external cameras, compromised endpoint software, or specialized tools.

The security model therefore focuses on preventing delivery of the
original PDF, strong authentication/authorization, server-side
rendering, personalized watermarking, auditing, session controls, rate
limiting, and leak attribution.

## 41. Audit Logging

Security-relevant events must be audited, including SEC portal login,
USB authentication success/failure, document download, package upload,
package verification, package import, decryption failures, document
open/close, staff certificate authentication success/failure, page
viewing where required, access denial, server-key operations, and
administrative permission changes.

Audit logs must not contain private keys, encryption keys, passwords,
PINs, or raw authentication/view tokens.

## 42. Document Permissions

The authorization model should support subsidiary, staff member,
department, role, security classification, individual document, document
group, effective date, and expiration date as required.

All authorization decisions must be enforced server-side.

## 43. Suggested Core Database Entities

Suggested entities include:

-   `sec_documents`
-   `sec_document_versions`
-   `sec_document_permissions`
-   `sec_staff_certificates`
-   `sec_view_sessions`
-   `sec_audit_log`
-   `sec_import_history`
-   `sec_server_keys`

Private keys must never be stored as ordinary database values.

## 44. Duplicate, Replay, and Version Protection

The import system should identify packages using package ID, document
ID, version, recipient identity, and cryptographic hash.

It should distinguish legitimate re-imports, new versions, duplicate
imports, unauthorized replay, and downgrade attempts.

## 45. Certificate Revocation and Expiration

Concheron must be able to revoke SEC USB certificates and staff
certificates and manage relevant server credentials.

Expired or revoked staff certificates must not continue granting new
access after the system has the required status information.

For isolated subsidiary environments, Concheron must define an approved
method of delivering current trust/revocation information.

Because Mode A is used, staff certificate renewal or revocation does not
require re-encrypting stored SEC documents.

## 46. Lost SEC USB

A lost or stolen SEC USB must be revocable.

The administrative procedure should disable portal authentication,
prevent new downloads, record the event, review download history, and
issue a replacement only when approved.

Concheron must define how previously downloaded packages encrypted for a
lost USB are recovered or re-downloaded.

## 47. Staff Departure

When an employee leaves, the system should disable the staff account,
revoke the staff certificate, and remove SEC permissions.

Existing documents do not need re-encryption because staff certificates
use Mode A.

## 48. Server Key Rotation and Recovery

The system must support multiple server-key versions.

New documents should use the current active key while older documents
may remain protected by previous approved keys until controlled
migration.

Loss of a server key can make documents encrypted under it inaccessible,
so secure backup and disaster-recovery procedures are mandatory.

## 49. Cryptographic Engineering

The Concheron extension should use mature, reviewed cryptographic
libraries rather than custom implementations of AES, RSA/ECC, hashing,
random generation, or certificate parsing.

All cryptographic keys, challenges, tokens, nonces/IVs, and
unpredictable identifiers must use a cryptographically secure
random-number generator.

AES-GCM nonces must never be reused with the same encryption key.

## 50. Command, Path, and Upload Security

The application must not construct unsafe shell commands from uploaded
filenames, document titles, usernames, package metadata, or HTTP
parameters.

External rendering commands must use strictly controlled arguments.

Uploads must have file-size limits, package validation, random internal
names, controlled temporary directories, restricted permissions,
authentication, authorization, and no public execution or direct public
URL.

## 51. Rate Limiting

The secure page API should detect or restrict abnormal bulk retrieval,
excessive parallel page requests, and automated scraping while
preserving legitimate continuous-scrolling performance.

## 52. Web Session and CSRF Security

The subsidiary website must use secure session IDs, session regeneration
after authentication, appropriate expiration, HttpOnly cookies, Secure
cookies when HTTPS is used, appropriate SameSite policy, and CSRF
protection for state-changing SEC operations.

The CSRF design should support multiple browser tabs without unnecessary
token invalidation.

## 53. HTTPS

SEC web communication should use HTTPS wherever technically possible,
including on private networks. Private-network status alone must not be
treated as transport encryption.

## 54. Extension Version and Integrity

The Concheron PHP extension must expose an identifiable version and the
subsidiary application should enforce a minimum approved version.

Concheron should sign distributed extension binaries where supported and
provide approved release metadata/checksums.

## 55. Extension Error Handling

The extension should return controlled error codes such as:

``` text
SEC_ERR_INVALID_PACKAGE
SEC_ERR_INVALID_SIGNATURE
SEC_ERR_WRONG_RECIPIENT
SEC_ERR_USB_REQUIRED
SEC_ERR_USB_AUTH_FAILED
SEC_ERR_CERT_EXPIRED
SEC_ERR_CERT_REVOKED
SEC_ERR_DECRYPT_FAILED
SEC_ERR_INTEGRITY_FAILED
SEC_ERR_SERVER_KEY
SEC_ERR_UNSUPPORTED_VERSION
```

User-facing errors must not expose cryptographic internals, private
filesystem paths, stack traces, or secret information.

## 56. Failure Safety

A failed import must not leave plaintext PDFs, partially imported
records, exposed encryption keys, public temporary files, or invalid
permissions.

Import processing should behave transactionally where practical.

## 57. Performance

The system should avoid repeatedly decrypting and rendering an entire
PDF for every page request.

A protected rendered-page cache may be used, with personalized
watermarking applied when pages are delivered.

Multiple rendering resolutions may be maintained when necessary for
normal and high-zoom viewing.

## 58. Security Boundaries

The principal security responsibilities are:

-   **Concheron SEC Portal:** controls document distribution.
-   **SEC USB:** controls SEC portal authentication and initial
    downloaded-package access/import.
-   **Concheron PHP Extension:** encapsulates approved SEC cryptographic
    operations.
-   **Subsidiary Server Key:** protects imported documents at rest.
-   **Staff Certificate (Mode A):** authenticates staff and supports
    authorization; it does not decrypt documents.
-   **Authorization System:** determines which documents authenticated
    staff may access.
-   **Secure Page API:** controls rendered-page delivery.
-   **Watermark + Canvas Viewer:** presents personalized protected
    document pages.

## 59. Recommended Subsidiary Modules

A possible ZF1 organization is:

``` text
application/modules/sec/
|
+-- controllers/
|   +-- DocumentController.php
|   +-- ImportController.php
|   +-- ViewerController.php
|   +-- CertificateController.php
|
+-- models/
|   +-- Document.php
|   +-- DocumentPermission.php
|   +-- StaffCertificate.php
|   +-- ViewSession.php
|   +-- AuditLog.php
|
+-- services/
|   +-- SecExtensionService.php
|   +-- DocumentImportService.php
|   +-- CertificateService.php
|   +-- AuthorizationService.php
|   +-- PdfRenderService.php
|   +-- WatermarkService.php
|   +-- ViewSessionService.php
|   +-- AuditService.php
|
+-- views/
    +-- scripts/
        +-- viewer/
```

## 60. Recommended Implementation Order

### Phase 1 --- PKI and Key Architecture

Define the Concheron CA hierarchy, USB certificates, staff certificates,
server keys, issuance, renewal, revocation, backup, and rotation.

### Phase 2 --- SEC Package Specification

Define package format, manifest, encryption, key wrapping, digital
signatures, package versioning, recipient identification, and replay
protection.

### Phase 3 --- Concheron PHP Extension

Implement extension loading/versioning, package parsing, signature
verification, USB integration, package decryption, server re-encryption,
controlled server decryption, and certificate verification.

### Phase 4 --- Central SEC Portal

Implement SEC staff accounts, VPN access policy, USB authentication,
document permissions, package generation, download encryption, and
download auditing.

### Phase 5 --- Subsidiary SEC Import

Implement upload, USB authentication, package verification/decryption,
server re-encryption, database registration, and audit logging.

### Phase 6 --- Staff Certificate Authentication

Implement certificate registration, verification, challenge-response,
account mapping, and expiration/revocation handling.

### Phase 7 --- Authorization

Implement staff roles, document permissions, classification permissions,
expiration, and access-denied handling.

### Phase 8 --- Secure Document Processing

Implement server-side decryption, PDF rendering, protected page cache,
watermarking, and temporary-file controls.

### Phase 9 --- Secure Viewer

Implement Canvas viewing, continuous scrolling, lazy loading, zoom, page
navigation, memory management, and view sessions.

### Phase 10 --- Security Hardening

Test and harden CSRF, session security, rate limiting, auditing, key
rotation, failure cleanup, certificate revocation, package replay
protection, extension integrity, and overall system security.

## 61. Final Selected Architecture

``` text
PRIVATE VPN
     +
SEC USB
     +
USB PASSWORD/PIN
     +
USB CERTIFICATE
     +
PRIVATE-KEY PROOF
          |
          v
CONCHERON SEC PORTAL
          |
          v
PER-USB ENCRYPTED SEC PACKAGE
          |
          v
SUBSIDIARY SEC IMPORT
          |
          v
CONCHERON PHP EXTENSION
          |
          v
USB-ASSISTED DECRYPTION
          |
          v
SERVER-KEY RE-ENCRYPTION
          |
          v
ENCRYPTED LOCAL STORAGE
          |
          v
STAFF LOGIN
          +
STAFF CERTIFICATE
          |
          v
MODE A CERTIFICATE AUTHENTICATION
          |
          v
DOCUMENT AUTHORIZATION
          |
          v
SERVER-SIDE DECRYPTION
          |
          v
SERVER-SIDE PDF RENDERING
          |
          v
PERSONALIZED WATERMARK
          |
          v
SECURE PAGE API
          |
          v
CANVAS VIEWER
```

The selected security model separates responsibilities deliberately:

-   The SEC USB controls secure distribution and initial import.
-   The subsidiary server key protects imported documents at rest.
-   The staff certificate authenticates staff and supports authorization
    but does not decrypt documents.
-   The Concheron PHP extension encapsulates approved cryptographic
    operations.
-   The server performs document decryption and rendering.
-   The browser receives only authorized, personalized rendered pages
    rather than the original PDF.
