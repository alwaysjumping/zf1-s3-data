# Concheron SEC Package Format & PHP Extension API Specification

**Version:** 1.0 Draft\
**System:** Concheron SEC Document Distribution and Secure Viewing
System\
**Target subsidiary runtime:** PHP 7.4 / Zend Framework 1\
**Staff certificate mode:** Mode A --- authentication and authorization
only

------------------------------------------------------------------------

## 1. Purpose

This specification defines two tightly coupled interfaces:

1.  The **Concheron `.sec` package format** used to distribute encrypted
    SEC documents from the parent company to authorized subsidiary SEC
    staff.
2.  The **Concheron PHP extension API** used by subsidiary applications
    to verify, import, re-encrypt, and process SEC documents without
    exposing cryptographic key material to ordinary PHP code.

The package format and extension must be versioned independently but
have an explicit compatibility matrix.

------------------------------------------------------------------------

## 2. Security Model

The selected model is:

``` text
Concheron SEC Portal
        |
SEC staff authenticated by:
VPN + account + SEC USB + PIN + private-key proof
        |
        v
Generate fresh document content key (CEK)
        |
Encrypt PDF using authenticated symmetric encryption
        |
Wrap CEK for authenticated SEC USB public key
        |
Sign complete SEC package
        |
        v
*.sec
        |
        v
Subsidiary ZF1 upload
        |
Concheron PHP Extension
        |
USB private-key operation
        |
Verify + decrypt package
        |
        v
Temporary plaintext processing context
        |
        +------> PDF rendering
        |
        v
Re-encrypt with subsidiary server storage key
        |
        v
Encrypted local master
```

The staff certificate used later for document viewing is Mode A and
therefore does not decrypt document content.

------------------------------------------------------------------------

# Part I --- `.sec` Package Specification

## 3. Package Design Principles

The `.sec` format must provide:

-   Confidentiality.
-   Package integrity.
-   Concheron authenticity.
-   Recipient binding.
-   Subsidiary binding.
-   Document/version binding.
-   Format versioning.
-   Replay detection support.
-   Cryptographic algorithm agility.
-   Safe parsing.
-   Deterministic/canonical signed representation.
-   Explicit rejection of unsupported versions.

The parser must fail closed. Unknown critical fields, unsupported
critical algorithms, malformed lengths, duplicate security fields, or
signature failures must result in rejection.

------------------------------------------------------------------------

## 4. File Extension and MIME Type

Recommended extension:

``` text
.sec
```

Recommended internal media type:

``` text
application/vnd.concheron.sec
```

The operating system association is optional.

A `.sec` file must never be treated as executable content.

------------------------------------------------------------------------

## 5. Binary Container Recommendation

The production format should use a documented binary container rather
than relying on ZIP filenames as security controls.

Logical structure:

``` text
+------------------------------+
| Fixed Header                 |
+------------------------------+
| Manifest Length              |
+------------------------------+
| Canonical Manifest           |
+------------------------------+
| Recipient Block              |
+------------------------------+
| Encrypted Document Payload   |
+------------------------------+
| Signature Block              |
+------------------------------+
```

All integer encodings, byte order, maximum lengths, and canonicalization
rules must be defined in the implementation specification.

For the first implementation, use a conservative maximum package size
configured by policy.

------------------------------------------------------------------------

## 6. Fixed Header

Conceptual header fields:

``` text
magic               8 bytes / fixed identifier
container_version   uint16
flags               uint32
manifest_length     uint32/uint64
recipient_length    uint32/uint64
payload_length      uint64
signature_length    uint32
reserved            fixed bytes
```

Example magic identifier:

``` text
CONSEC01
```

The exact byte-level layout should be frozen only after implementation
review.

The parser must validate lengths before allocation or reading payload
data.

------------------------------------------------------------------------

## 7. Package Manifest

The manifest is security-sensitive and must be canonicalized.

Recommended logical fields:

``` json
{
  "format": "CONCHERON-SEC",
  "format_version": 1,
  "package_id": "PKG-...",
  "document_id": "SEC-2026-000184",
  "document_version": "3",
  "title": "Example SEC Document",
  "classification": "SEC",
  "publisher": "CONCHERON",
  "target_company_id": "COMPANY-A",
  "recipient_usb_certificate_id": "USB-CERT-A-0041",
  "created_at": "2026-09-27T01:00:00Z",
  "expires_at": null,
  "crypto_suite": "SEC-SUITE-1",
  "payload_type": "application/pdf",
  "payload_plaintext_length": 1234567
}
```

The title is descriptive. Authorization and recipient decisions must use
immutable IDs, not titles or display names.

------------------------------------------------------------------------

## 8. Required Manifest Fields

Required security fields:

``` text
format
format_version
package_id
document_id
document_version
classification
publisher
target_company_id
recipient_usb_certificate_id
created_at
crypto_suite
payload_type
```

Optional policy fields may include:

``` text
expires_at
distribution_policy_id
retention_policy_id
supersedes_version
minimum_extension_version
```

Unknown **critical** fields must cause rejection. Unknown non-critical
metadata may be ignored according to version rules.

------------------------------------------------------------------------

## 9. Canonical Manifest Encoding

The signature must never depend on ambiguous JSON formatting.

Two acceptable design directions are:

1.  Define a strict canonical JSON representation.
2.  Use a deterministic binary encoding designed for canonical
    serialization.

Whichever representation is selected must define:

-   Field encoding.
-   Ordering.
-   Unicode normalization policy.
-   Duplicate-key rejection.
-   Number representation.
-   Date/time representation.
-   Null handling.

The extension must parse and verify the canonical representation itself.

------------------------------------------------------------------------

## 10. Crypto Suite Registry

The manifest identifies a cryptographic suite by stable ID.

Example:

``` text
SEC-SUITE-1
```

A suite definition specifies:

``` text
payload encryption algorithm
key size
nonce requirements
authentication-tag size
key-wrapping/KEM mechanism
package signature algorithm
hash algorithm
certificate/key requirements
```

The suite ID allows Concheron to migrate algorithms without changing
application business logic.

The exact asymmetric mechanism must be compatible with the selected USB
hardware/token and approved cryptographic provider.

------------------------------------------------------------------------

## 11. Content Encryption

For `SEC-SUITE-1`, the baseline authenticated content-encryption design
is:

``` text
AES-256-GCM
```

For every package:

1.  Generate a fresh random 256-bit CEK.
2.  Generate the nonce/IV according to the approved cryptographic
    library requirements.
3.  Encrypt the PDF.
4.  Authenticate required package metadata as Additional Authenticated
    Data (AAD), where appropriate.
5.  Store ciphertext and authentication tag.

A CEK must never be reused for another package.

A nonce must never be reused with the same AES-GCM key.

------------------------------------------------------------------------

## 12. Recipient Block

The recipient block binds the CEK to the authenticated SEC USB
credential.

Conceptual fields:

``` text
recipient_type
recipient_certificate_id
recipient_key_id
key_wrap_algorithm
wrapped_cek_length
wrapped_cek
```

The recipient certificate/key identifier must match the identity
recorded in the signed manifest.

A mismatch must cause import failure.

------------------------------------------------------------------------

## 13. Multi-Recipient Policy

Version 1 should preferably use **one intended SEC USB recipient per
downloaded package**.

This simplifies:

-   Audit attribution.
-   Lost USB handling.
-   Recipient validation.
-   Replay tracking.
-   Key wrapping.

If future versions support multiple recipients, each recipient receives
a separately wrapped copy of the same CEK and the recipient list must be
signed.

------------------------------------------------------------------------

## 14. Package Signature

Concheron signs the complete security-relevant representation.

Conceptually:

``` text
SignedData =
    FixedSecurityHeader
    || CanonicalManifest
    || RecipientBlock
    || EncryptedPayloadMetadata
    || EncryptedPayload
```

The signature block contains:

``` text
signing_certificate_id
signature_algorithm
signature_length
signature
optional certificate-chain reference/data
```

The exact signing certificate chain distribution model must be defined
by Concheron PKI policy.

------------------------------------------------------------------------

## 15. Verification Order

The extension should use an order that rejects malformed/untrusted data
as early as possible without performing unsafe expensive operations.

Recommended sequence:

``` text
1. Validate fixed header.
2. Validate declared lengths against file size and configured limits.
3. Parse canonical manifest safely.
4. Validate supported format version.
5. Validate crypto-suite support.
6. Validate required fields.
7. Validate target subsidiary.
8. Validate recipient identifier.
9. Verify Concheron package signature.
10. Validate package expiry/policy.
11. Check package ID/replay state through application workflow.
12. Request intended USB private-key operation.
13. Unwrap CEK.
14. AES-GCM authenticate/decrypt payload.
15. Validate decrypted payload type/metadata.
16. Continue controlled import.
```

The application must not mark the package imported until all required
steps succeed.

------------------------------------------------------------------------

## 16. Package IDs

`package_id` must be globally unique and unpredictable enough to avoid
accidental collisions.

Do not derive security solely from a sequential package number.

The application stores:

``` text
package_id
package_hash
document_id
document_version
recipient_certificate_id
import_status
```

------------------------------------------------------------------------

## 17. Document Version Rules

The extension verifies package integrity but the application applies
business version policy.

Example:

``` text
Document currently at version 3

Incoming v4 -> allowed
Incoming v3 -> duplicate/re-import policy
Incoming v2 -> downgrade/reject unless explicitly approved
```

The package signature must bind the document version.

------------------------------------------------------------------------

## 18. Package Expiration

If `expires_at` is present, the extension/application must enforce it
according to trusted local time policy.

Expiration of a downloadable/importable package is separate from:

-   Document retention.
-   Staff access expiration.
-   Certificate expiration.

------------------------------------------------------------------------

## 19. Parser Safety

The extension parser must:

-   Check all integer overflows.
-   Check length arithmetic.
-   Reject truncated input.
-   Reject trailing data if prohibited by the version.
-   Reject duplicate critical fields.
-   Limit nesting/metadata size.
-   Limit package size.
-   Avoid unsafe memory copies.
-   Avoid trusting filenames embedded in packages.
-   Never execute package content.
-   Treat document payload as untrusted until validation completes.

------------------------------------------------------------------------

# Part II --- PHP Extension API

## 20. Extension Name

Recommended PHP extension name:

``` text
concheron_sec
```

Linux:

``` text
concheron_sec.so
```

Windows:

``` text
php_concheron_sec.dll
```

------------------------------------------------------------------------

## 21. PHP Namespace Strategy

For PHP 7.4 compatibility and simple C-extension implementation,
functions may initially use a prefix:

``` php
concheron_sec_version()
concheron_sec_capabilities()
concheron_sec_inspect_package()
```

A later object-oriented wrapper can be implemented in PHP/ZF1 without
changing the binary ABI unnecessarily.

------------------------------------------------------------------------

## 22. API Design Rules

The extension API must:

-   Expose high-level SEC operations.
-   Validate PHP argument types.
-   Avoid returning raw private keys.
-   Avoid returning raw CEKs.
-   Avoid accepting arbitrary cryptographic algorithm names from users.
-   Use stable error codes.
-   Support safe logging correlation IDs.
-   Zero sensitive temporary buffers where practical.
-   Close cryptographic handles deterministically.
-   Fail closed.

------------------------------------------------------------------------

## 23. Version API

### Function

``` php
concheron_sec_version(): array
```

Example result:

``` php
[
    'extension_version' => '1.0.0',
    'api_version' => 1,
    'package_versions' => [1],
    'crypto_suites' => ['SEC-SUITE-1']
]
```

No sensitive environment details should be exposed unnecessarily.

------------------------------------------------------------------------

## 24. Capability API

### Function

``` php
concheron_sec_capabilities(): array
```

Example:

``` php
[
    'usb_provider' => true,
    'server_key_provider' => true,
    'package_verify' => true,
    'package_import' => true,
    'staff_certificate_verify' => true,
    'supported_package_versions' => [1]
]
```

This helps deployment diagnostics without exposing keys.

------------------------------------------------------------------------

## 25. Package Inspection API

Inspection reads non-secret package metadata but does not decrypt the
payload.

### Function

``` php
concheron_sec_inspect_package(string $packagePath): array
```

Example success:

``` php
[
    'success' => true,
    'package_id' => 'PKG-...',
    'document_id' => 'SEC-2026-000184',
    'document_version' => '3',
    'target_company_id' => 'COMPANY-A',
    'recipient_certificate_id' => 'USB-CERT-A-0041',
    'classification' => 'SEC',
    'format_version' => 1,
    'crypto_suite' => 'SEC-SUITE-1'
]
```

Inspection output is untrusted metadata until signature verification
succeeds.

The application must visually/semantically distinguish unverified
metadata.

------------------------------------------------------------------------

## 26. Package Verification API

### Function

``` php
concheron_sec_verify_package(
    string $packagePath,
    array $context = []
): array
```

Suggested context:

``` php
[
    'expected_company_id' => 'COMPANY-A',
    'check_expiration' => true
]
```

Example result:

``` php
[
    'success' => true,
    'verified' => true,
    'operation_id' => 'OP-...',
    'package_id' => 'PKG-...',
    'document_id' => 'SEC-2026-000184',
    'document_version' => '3',
    'recipient_certificate_id' => 'USB-CERT-A-0041'
]
```

This function verifies package authenticity/integrity but should not
expose the CEK.

------------------------------------------------------------------------

## 27. USB Discovery API

Hardware/token integration is provider-specific.

Conceptual function:

``` php
concheron_sec_usb_list(): array
```

Only non-secret identifiers should be returned.

Example:

``` php
[
    [
        'device_id' => 'USB-A-0041',
        'certificate_id' => 'USB-CERT-A-0041',
        'company_id' => 'COMPANY-A',
        'ready' => true
    ]
]
```

Whether PIN entry occurs through the application, token middleware, or
protected native UI must be decided based on the chosen USB token.

The extension must not log the PIN.

------------------------------------------------------------------------

## 28. USB Challenge API

For portal/local proof-of-possession workflows, prefer a high-level
signing operation rather than exporting a private key.

Conceptual API:

``` php
concheron_sec_usb_sign_challenge(
    string $deviceId,
    string $challenge,
    array $options = []
): array
```

The actual PIN handling interface depends on the hardware/token
architecture.

Result:

``` php
[
    'success' => true,
    'certificate_id' => 'USB-CERT-A-0041',
    'signature' => '<encoded signature>',
    'algorithm_id' => '...'
]
```

For remote portal authentication, the portal verifies this signature
against its issued certificate record.

------------------------------------------------------------------------

## 29. Import API

The preferred import API performs the sensitive cryptographic sequence
internally.

### Function

``` php
concheron_sec_import_package(
    string $packagePath,
    array $context
): array
```

Conceptual context:

``` php
[
    'expected_company_id' => 'COMPANY-A',
    'usb_device_id' => 'USB-A-0041',
    'server_key_id' => 'KEY-A-003',
    'destination_path' => '/secure/sec/documents/184/master.enc'
]
```

The implementation must not permit arbitrary untrusted HTTP input to
directly choose unrestricted filesystem paths or key IDs. The ZF1
service layer must supply validated internal values.

------------------------------------------------------------------------

## 30. Import Internal Sequence

`concheron_sec_import_package()` should conceptually perform:

``` text
Open package safely
        |
Verify structure/version
        |
Verify Concheron signature
        |
Verify company/recipient
        |
Use intended USB private-key operation
        |
Unwrap CEK internally
        |
AES-GCM authenticate/decrypt
        |
Validate PDF/payload constraints
        |
Generate fresh local storage data key
        |
Encrypt PDF for server storage
        |
Wrap/protect storage data key with server key
        |
Write encrypted master atomically
        |
Return safe metadata
```

The CEK and plaintext storage key never leave the native extension as
PHP values.

------------------------------------------------------------------------

## 31. Import Output

Example:

``` php
[
    'success' => true,
    'operation_id' => 'OP-...',
    'package_id' => 'PKG-...',
    'document_id' => 'SEC-2026-000184',
    'document_version' => '3',
    'storage_key_id' => 'KEY-A-003',
    'encrypted_master_hash' => '...',
    'payload_type' => 'application/pdf'
]
```

The application then commits its database transaction.

------------------------------------------------------------------------

## 32. Atomic File Handling

The extension should write to an internal temporary destination first:

``` text
master.enc.tmp-<random>
```

After encryption and validation succeed:

``` text
atomic rename -> master.enc
```

On failure, temporary output must be removed.

Application-provided paths must be restricted to configured SEC storage
roots.

------------------------------------------------------------------------

## 33. Controlled Decryption for Rendering

PHP should not receive the full plaintext PDF as a string.

Preferred designs:

### Option A --- Extension-to-Renderer Pipeline

The extension decrypts the master into a protected temporary file/pipe
and invokes or coordinates with the approved renderer.

### Option B --- Controlled Temporary Handle

The extension returns an opaque operation/handle that an approved native
rendering path can consume.

The first implementation may use a tightly protected temporary file if
required by Poppler, provided cleanup and permissions are strictly
controlled.

Avoid:

``` php
$pdf = concheron_sec_decrypt_document(...);
```

where `$pdf` contains the complete plaintext PDF in PHP memory.

------------------------------------------------------------------------

## 34. Rendering API

A future high-level API may be:

``` php
concheron_sec_render_page(
    string $documentReference,
    int $page,
    array $options
): array
```

Options may include an approved resolution profile, not arbitrary
command-line arguments.

Example:

``` php
[
    'resolution_profile' => 'normal',
    'output_path' => '<validated internal cache path>'
]
```

The extension/service must not permit command injection into Poppler or
other rendering tools.

------------------------------------------------------------------------

## 35. Server Key API

Do not expose:

``` php
concheron_sec_get_server_private_key();
```

Use opaque key IDs/references.

Allowed conceptual operations:

``` php
concheron_sec_server_key_status(string $keyId): array
concheron_sec_rewrap_storage_key(...): array
```

Actual key operations happen through the configured native key provider.

------------------------------------------------------------------------

## 36. Staff Certificate Verification API --- Mode A

### Function

``` php
concheron_sec_verify_staff_certificate(
    string $certificateData,
    array $context
): array
```

Context may include:

``` php
[
    'expected_company_id' => 'COMPANY-A',
    'required_policy' => 'SEC-STAFF'
]
```

Result:

``` php
[
    'success' => true,
    'certificate_id' => 'CERT-STF-A-001004',
    'staff_id' => 'EMP-A-0041',
    'company_id' => 'COMPANY-A',
    'valid_until' => '...',
    'status' => 'VALID'
]
```

Certificate verification alone is not proof of current private-key
possession.

------------------------------------------------------------------------

## 37. Staff Challenge Verification

A Mode-A authentication sequence should include a challenge.

Conceptual functions:

``` php
concheron_sec_create_challenge(array $context): array
concheron_sec_verify_staff_challenge(
    string $certificateData,
    string $challenge,
    string $signature,
    array $context
): array
```

Alternatively, challenge generation may remain in the application using
a cryptographically secure generator while signature/certificate
validation occurs in the extension.

Challenges must be:

-   Random.
-   Single-use.
-   Short-lived.
-   Bound to the intended login/session.
-   Stored/validated server-side.

------------------------------------------------------------------------

## 38. Certificate Status Data

For isolated networks, the extension should load only signed/approved
Concheron trust material.

Conceptual API:

``` php
concheron_sec_trust_status(): array
```

Example:

``` php
[
    'policy_version' => 12,
    'revocation_data_version' => 48,
    'effective_at' => '...',
    'age_seconds' => 12345
]
```

Importing trust updates should be an administrative operation with
separate authorization and auditing.

------------------------------------------------------------------------

## 39. Error Model

Every extension operation should map failures to stable public error
codes.

Recommended families:

``` text
SEC_E_PACKAGE_*
SEC_E_USB_*
SEC_E_CERT_*
SEC_E_CRYPTO_*
SEC_E_STORAGE_*
SEC_E_KEY_*
SEC_E_POLICY_*
SEC_E_SYSTEM_*
```

Examples:

``` text
SEC_E_PACKAGE_FORMAT
SEC_E_PACKAGE_VERSION
SEC_E_PACKAGE_SIGNATURE
SEC_E_PACKAGE_EXPIRED
SEC_E_WRONG_COMPANY
SEC_E_WRONG_RECIPIENT
SEC_E_USB_NOT_FOUND
SEC_E_USB_LOCKED
SEC_E_USB_AUTH
SEC_E_CERT_EXPIRED
SEC_E_CERT_REVOKED
SEC_E_CERT_UNTRUSTED
SEC_E_DECRYPT_AUTH
SEC_E_SERVER_KEY
SEC_E_STORAGE_WRITE
SEC_E_UNSUPPORTED_SUITE
```

------------------------------------------------------------------------

## 40. PHP Error Handling Pattern

ZF1 service layer:

``` php
$result = concheron_sec_import_package($packagePath, $context);

if (!$result['success']) {
    $auditService->recordFailure(
        'SEC_PACKAGE_IMPORT_FAILURE',
        $result['error_code'],
        $result['operation_id']
    );

    throw new SecImportException(
        'The SEC package could not be imported.'
    );
}
```

Do not show raw native error details to normal users.

------------------------------------------------------------------------

## 41. Logging Boundary

The extension may write restricted operational diagnostics but must
never log:

``` text
USB PIN/password
staff private key
USB private key
server private key
CEK
storage data key
raw session token
raw view token
plaintext PDF content
```

Each operation should have a correlation/operation ID usable by PHP
audit logs and native diagnostics.

------------------------------------------------------------------------

## 42. Configuration

Security-sensitive configuration should not be accepted directly from
browser requests.

Native/administrative configuration may define:

``` text
company_id
secure_storage_root
temporary_storage_root
trust_store_path/reference
server_key_provider
active_server_key_id
supported_package_versions
supported_crypto_suites
maximum_package_size
renderer_path/profile
```

The extension should validate configuration at startup or first use.

------------------------------------------------------------------------

## 43. PHP INI Configuration

Possible extension-level settings:

``` ini
extension=concheron_sec.so

concheron_sec.company_id=COMPANY-A
concheron_sec.storage_root=/secure/sec
concheron_sec.max_package_size=...
concheron_sec.active_server_key_id=KEY-A-003
```

Secrets should not be placed directly in `php.ini` when a protected key
provider/reference can be used instead.

------------------------------------------------------------------------

## 44. Thread/Process Safety

The extension must be designed for the PHP SAPI used by the subsidiary.

It must:

-   Avoid unsafe global mutable cryptographic state.
-   Release handles correctly.
-   Be safe for repeated Apache/PHP requests.
-   Account for Windows TS/NTS differences when applicable.
-   Avoid retaining unlocked USB/session key material longer than
    required.

------------------------------------------------------------------------

## 45. Memory Handling

Native code must treat all package data as untrusted.

Use checked allocation and length arithmetic.

Sensitive buffers should be cleared where the underlying
platform/compiler/library provides a reliable secure-clearing primitive.

Never rely on a normal optimizing `memset()` alone for guaranteed secret
erasure.

------------------------------------------------------------------------

## 46. File Permissions

Files created by the extension must use restrictive permissions
appropriate to the operating system.

Temporary and encrypted master files must never be created under the
public Apache document root.

The extension should reject output paths outside configured SEC roots.

------------------------------------------------------------------------

## 47. API Authorization Boundary

The PHP extension performs cryptographic verification; it does not
replace application authorization.

Example:

``` text
Extension:
"Certificate EMP-A-0041 is valid."

ZF1:
"Is EMP-A-0041 permitted to view SEC-2026-000184?"
```

Both checks are required.

------------------------------------------------------------------------

## 48. ZF1 Service Wrapper

The ZF1 application should not call native functions throughout
controllers.

Use one service boundary:

``` text
Application_Model_Service_SecCrypto
```

Conceptual PHP wrapper:

``` php
class Application_Model_Service_SecCrypto
{
    public function verifyPackage($path, array $context)
    {
        return concheron_sec_verify_package($path, $context);
    }

    public function importPackage($path, array $context)
    {
        return concheron_sec_import_package($path, $context);
    }

    public function verifyStaffCertificate($certificate, array $context)
    {
        return concheron_sec_verify_staff_certificate(
            $certificate,
            $context
        );
    }
}
```

This isolates controllers from binary API changes.

------------------------------------------------------------------------

## 49. Import Controller Boundary

Recommended controller flow:

``` text
HTTP Upload
   |
ZF1 authentication
   |
CSRF verification
   |
SEC-manager authorization
   |
Move upload to controlled quarantine
   |
Create import-history record
   |
Call SecCrypto service
   |
Commit document/database records
   |
Audit success
   |
Cleanup package according to retention policy
```

The controller must never perform cryptography itself.

------------------------------------------------------------------------

## 50. Extension Compatibility Matrix

Concheron should publish a matrix such as:

  Extension     API   Package PHP      Status
  ----------- ----- --------- -------- -----------
  1.0.x           1         1 7.4      Supported
  1.1.x           1         1 7.4      Supported
  2.x             2       1,2 future   Future

A package may specify `minimum_extension_version`.

The application must reject a package requiring a newer unsupported
extension.

------------------------------------------------------------------------

## 51. Algorithm Migration

Do not hard-code business logic around the literal algorithm name.

Use:

``` text
crypto_suite = SEC-SUITE-1
```

Future:

``` text
SEC-SUITE-2
```

The extension maps suite IDs to approved native implementations.

Old suites can be disabled by policy after documents/packages are
migrated.

------------------------------------------------------------------------

## 52. Package Creation Service at Concheron

The parent-company package generator should use the same canonical
package specification as the subsidiary extension.

Creation sequence:

``` text
Authorize download
       |
Resolve USB certificate/public key
       |
Create package ID
       |
Build canonical manifest
       |
Generate CEK
       |
Encrypt PDF
       |
Wrap CEK for USB
       |
Assemble package
       |
Sign package
       |
Audit download
       |
Deliver .sec
```

The Concheron portal must not trust a public key supplied arbitrarily by
the browser. It must resolve the public key from the authenticated
Concheron-issued USB certificate record.

------------------------------------------------------------------------

## 53. Test Vectors

Before production, Concheron must create fixed non-secret
interoperability test vectors containing:

-   Sample manifest.
-   Sample recipient certificate/public key.
-   Sample plaintext PDF/test payload.
-   Expected package bytes or hashes.
-   Expected signature-verification result.
-   Expected decryption result.
-   Invalid-signature package.
-   Wrong-recipient package.
-   Modified-ciphertext package.
-   Truncated package.
-   Unsupported-version package.

Test vectors prevent the package generator and extension parser from
drifting.

------------------------------------------------------------------------

## 54. Fuzz and Negative Testing

The native parser must be fuzz-tested.

Required negative tests include:

``` text
zero-length package
oversized length fields
integer overflow cases
duplicate fields
invalid UTF-8/encoding where applicable
truncated ciphertext
modified authentication tag
modified manifest
modified recipient ID
invalid signature
unknown critical field
unsupported crypto suite
wrong USB
expired package
wrong company
```

No malformed package should crash Apache/PHP.

------------------------------------------------------------------------

## 55. Release Security

Production extension binaries should be generated by a controlled build
process.

Release artifacts should include:

``` text
binary
version metadata
supported-platform metadata
cryptographic hash
Concheron signature where supported
release notes
security classification
```

Subsidiaries should not install an extension received from an unverified
source.

------------------------------------------------------------------------

## 56. Initial Minimum API Set

For the first implementation, the minimum native API should be kept
small:

``` php
concheron_sec_version();
concheron_sec_capabilities();

concheron_sec_inspect_package($path);
concheron_sec_verify_package($path, $context);
concheron_sec_import_package($path, $context);

concheron_sec_usb_list();
concheron_sec_usb_sign_challenge($deviceId, $challenge, $options);

concheron_sec_verify_staff_certificate($cert, $context);
concheron_sec_verify_staff_challenge(
    $cert,
    $challenge,
    $signature,
    $context
);

concheron_sec_trust_status();
concheron_sec_server_key_status($keyId);
```

Rendering can initially remain in a protected server service and be
moved behind a native API later if needed.

------------------------------------------------------------------------

## 57. Functions Explicitly Prohibited

The public PHP API must not provide functions equivalent to:

``` php
concheron_sec_export_usb_private_key();
concheron_sec_export_server_private_key();
concheron_sec_get_cek();
concheron_sec_get_storage_data_key();
concheron_sec_disable_signature_verification();
concheron_sec_decrypt_without_authentication();
concheron_sec_ignore_recipient();
```

There must be no production "debug bypass" that disables cryptographic
verification.

------------------------------------------------------------------------

## 58. Recommended First Prototype

The first proof-of-concept should implement only:

``` text
1. concheron_sec_version()
2. package header/parser
3. canonical manifest parser
4. package signature verification
5. AES-256-GCM test encryption/decryption
6. test recipient-key wrapping/unwrapping
7. concheron_sec_verify_package()
8. concheron_sec_import_package()
```

Use test software keys first.

After the package format and extension tests are stable, integrate the
selected hardware SEC USB token.

This reduces debugging complexity because USB middleware problems are
separated from package-format/cryptographic implementation problems.

------------------------------------------------------------------------

## 59. Final API Boundary

``` text
                      ZF1 APPLICATION
                            |
              business/auth/audit decisions
                            |
                            v
                 SecCrypto Service Wrapper
                            |
                            v
                 CONCHERON PHP EXTENSION
                /            |             \
               /             |              \
              v              v               v
       SEC Package      USB Provider     Server Key Provider
          Parser             |               |
              \              |              /
               \             |             /
                +------ Crypto Engine -----+
                            |
                            v
                   Protected SEC Storage
```

The key design rule is:

> PHP may request an approved security operation, but it should not
> receive the secret keys required to perform that operation itself.

------------------------------------------------------------------------

## 60. Implementation Decision Checklist

Before freezing `.sec` format version 1, Concheron must finalize:

-   Hardware SEC USB/token model.
-   USB key type and supported key-wrapping/KEM mechanism.
-   Package-signing algorithm.
-   Canonical manifest encoding.
-   Exact binary header layout.
-   Maximum package/manifest sizes.
-   Server-key provider.
-   Trust/revocation update format.
-   Package expiration policy.
-   Package retention policy after import.
-   Exact renderer integration.
-   Windows/Linux deployment targets.
-   PHP 7.4 TS/NTS requirements.
-   Extension build/signing process.
-   Crypto-suite registry governance.

Once these items are fixed, the byte-level `.sec` specification and C
header/API can be frozen as version 1.0.
