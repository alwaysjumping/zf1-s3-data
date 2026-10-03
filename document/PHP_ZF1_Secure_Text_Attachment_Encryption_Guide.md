# Secure Text and Attachment Encryption for PHP/ZF1

## 1. Purpose

This document describes a practical architecture for encrypting
sensitive text and attached files in a PHP application before storing
them in MariaDB or the filesystem, and decrypting them only for
authorized client viewing.

The target application environment is:

-   PHP 7.4
-   Zend Framework 1 (ZF1)
-   MariaDB
-   Server-side filesystem storage for attachments
-   HTTPS for browser/server communication
-   OpenSSL with AES-256-GCM support

The main goals are:

1.  Protect sensitive data at rest.
2.  Detect unauthorized modification of encrypted data.
3.  Keep encryption keys separate from encrypted data.
4.  Support key rotation.
5.  Avoid permanent plaintext attachment files.
6.  Support large attachments without loading the entire file into PHP
    memory.
7.  Authenticate each large-file chunk before releasing its plaintext.
8.  Keep the encrypted-file format versioned so it can evolve later.

------------------------------------------------------------------------

## 2. High-Level Architecture

The basic write path is:

``` text
                    SERVER
                      |
        +-------------+-------------+
        |                           |
     Text input                 Uploaded file
        |                           |
        +-------------+-------------+
                      |
                      v
                Validate input
                      |
                      v
            Authorization/context
                      |
                      v
          Authenticated encryption
              AES-256-GCM
                      |
             +--------+--------+
             |                 |
             v                 v
      Encrypted text      Encrypted file
             |                 |
             v                 v
          MariaDB          Filesystem
```

The viewing path is:

``` text
Client request
      |
      v
Authenticate user
      |
      v
Load metadata
      |
      v
Authorize access
      |
      v
Load ciphertext
      |
      v
Resolve encryption key
      |
      v
Authenticate + decrypt
      |
      v
HTTPS response
      |
      v
Browser
```

A critical rule is:

> Authorization must happen before decryption.

------------------------------------------------------------------------

## 3. Encryption at Rest Does Not Replace HTTPS

Application encryption protects stored data.

HTTPS protects data traveling between the browser and server.

Both are required.

``` text
Browser
   |
   | HTTPS / TLS
   v
PHP application
   |
   | Application-level encryption
   v
+----------+----------+
|                     |
MariaDB            Filesystem
```

------------------------------------------------------------------------

## 4. Recommended Encryption Algorithm

Use:

``` text
AES-256-GCM
```

AES-GCM provides both:

-   Confidentiality --- an attacker cannot read the plaintext without
    the key.
-   Authentication/integrity --- unauthorized ciphertext modification is
    detected.

For each encryption operation, retain:

``` text
ciphertext
nonce / IV
authentication tag
key identifier
format/version
```

The encryption key must not be stored beside the encrypted data.

------------------------------------------------------------------------

## 5. AES-256 Key Requirements

AES-256 requires a 256-bit key:

``` text
256 bits = 32 bytes
```

Generate keys with a cryptographically secure random generator:

``` php
$key = random_bytes(32);

echo base64_encode($key);
```

The Base64 representation is only a convenient storage representation.
After decoding, the key must contain exactly 32 bytes.

Do not use a human password directly as the AES key.

Bad:

``` php
$key = $_POST['password'];
```

Better:

``` text
Secure random 32-byte key
        |
        v
Protected key storage
        |
        v
Crypto service
```

------------------------------------------------------------------------

## 6. Nonce / IV Requirements

AES-GCM requires unique nonces for encryption under a given key.

A common nonce length is:

``` text
12 bytes / 96 bits
```

For independent encryption operations:

``` php
$iv = random_bytes(12);
```

The dangerous condition is:

``` text
same key + same nonce
```

Nonce reuse with GCM can seriously compromise security.

For the chunked file design described later, every chunk receives its
own nonce.

------------------------------------------------------------------------

## 7. Authentication Tag

A typical AES-GCM authentication tag length is:

``` text
16 bytes
```

Example:

``` php
$tag = '';

$ciphertext = openssl_encrypt(
    $plaintext,
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $iv,
    $tag,
    $aad,
    16
);
```

During decryption, OpenSSL verifies the tag.

If authentication fails:

``` php
$plaintext === false
```

The application must reject the data.

------------------------------------------------------------------------

## 8. Use Binary-Safe Database Columns

With:

``` php
OPENSSL_RAW_DATA
```

ciphertext, IVs, and tags are binary.

Recommended MariaDB column types include:

``` text
BLOB
LONGBLOB
VARBINARY
```

There is normally no need to Base64-encode ciphertext before storing it
in MariaDB if binary-safe prepared statements are used.

------------------------------------------------------------------------

# Part II --- Key Management

## 9. Key Provider Architecture

Application business logic should not directly know where encryption
keys are stored.

Use a key-provider interface:

``` php
<?php

interface S3_Crypto_KeyProviderInterface
{
    /**
     * Return exactly 32 raw bytes.
     *
     * @param string $keyId
     * @return string
     */
    public function getKey($keyId);
}
```

Conceptually:

``` text
Crypto Service
      |
      | key_id
      v
Key Provider
      |
      +-- protected key file
      +-- operating-system secret store
      +-- key-management service
      +-- HSM
```

This separation allows key storage to improve later without rewriting
the encryption logic.

------------------------------------------------------------------------

## 10. Simple File Key Provider

An initial implementation can use protected files outside the
application/database backup.

``` php
<?php

class S3_Crypto_FileKeyProvider
    implements S3_Crypto_KeyProviderInterface
{
    private $keyDirectory;

    public function __construct($keyDirectory)
    {
        $this->keyDirectory = rtrim($keyDirectory, '/\\');
    }

    public function getKey($keyId)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $keyId)) {
            throw new InvalidArgumentException('Invalid key ID.');
        }

        $file = $this->keyDirectory
            . DIRECTORY_SEPARATOR
            . $keyId
            . '.key';

        if (!is_file($file)) {
            throw new RuntimeException(
                'Encryption key was not found.'
            );
        }

        $encoded = trim(file_get_contents($file));

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException(
                'Invalid AES-256 encryption key.'
            );
        }

        return $key;
    }
}
```

Example:

``` text
D:\SecureKeys\
    company-data-2026-01.key
    company-data-2027-01.key
```

The key directory should not be part of the normal application/database
backup.

------------------------------------------------------------------------

## 11. Key IDs

Encrypted records should store a key identifier:

``` text
key_id
```

Example:

``` text
company-data-2026-01
```

The key ID is not secret.

It tells the key provider which key is needed to decrypt the record.

Do not store the actual AES key in the database row.

------------------------------------------------------------------------

## 12. Key Rotation

Suppose the current key is:

``` text
company-data-2026-01
```

Later a new key is introduced:

``` text
company-data-2027-01
```

New records use the new key.

Existing records continue storing their old `key_id`, so they remain
decryptable.

Migration can later be performed:

``` text
Read old ciphertext
      |
      v
Decrypt using old key
      |
      v
Encrypt using new key
      |
      v
Update ciphertext + key_id
```

Never delete an old key while data still depends on it.

------------------------------------------------------------------------

## 13. Envelope Encryption --- Future Improvement

For more sensitive systems, consider envelope encryption.

``` text
             KEK
      Key Encryption Key
             |
             | protects
             v
             DEK
      Data Encryption Key
             |
             | AES-256-GCM
             v
        Message/File
```

Each object can have a random DEK:

``` php
$dek = random_bytes(32);
```

The DEK encrypts the object.

The KEK protects the DEK.

The database can then store:

``` text
ciphertext
nonce
tag
wrapped_DEK
KEK_ID
version
```

This design works particularly well with centralized key management or
HSMs.

A simpler server-managed key architecture can be used initially,
provided `key_id` and versioning are included from the beginning.

------------------------------------------------------------------------

# Part III --- Encrypting Text

## 14. Example MariaDB Table

``` sql
CREATE TABLE secure_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    ciphertext LONGBLOB NOT NULL,

    encryption_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    algorithm VARCHAR(32) NOT NULL DEFAULT 'AES-256-GCM',
    key_id VARCHAR(64) NOT NULL,

    iv VARBINARY(32) NOT NULL,
    auth_tag VARBINARY(32) NOT NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,

    PRIMARY KEY (id),
    KEY idx_company_id (company_id),
    KEY idx_user_id (user_id),
    KEY idx_key_id (key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

------------------------------------------------------------------------

## 15. Encryption Result Object

``` php
<?php

class S3_Crypto_Result
{
    public $ciphertext;
    public $iv;
    public $tag;
    public $algorithm;
    public $keyId;
    public $version;
}
```

------------------------------------------------------------------------

## 16. Basic Text Crypto Service

``` php
<?php

class S3_Crypto_Service
{
    const ALGORITHM = 'aes-256-gcm';
    const VERSION = 1;
    const IV_LENGTH = 12;
    const TAG_LENGTH = 16;

    private $keyProvider;
    private $currentKeyId;

    public function __construct(
        S3_Crypto_KeyProviderInterface $keyProvider,
        $currentKeyId
    ) {
        $this->keyProvider = $keyProvider;
        $this->currentKeyId = $currentKeyId;
    }

    public function encrypt($plaintext, $aad = '')
    {
        $key = $this->keyProvider->getKey(
            $this->currentKeyId
        );

        if (strlen($key) !== 32) {
            throw new RuntimeException(
                'AES-256 key must be exactly 32 bytes.'
            );
        }

        $iv = random_bytes(self::IV_LENGTH);

        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException(
                'Invalid authentication tag.'
            );
        }

        $result = new S3_Crypto_Result();

        $result->ciphertext = $ciphertext;
        $result->iv = $iv;
        $result->tag = $tag;
        $result->algorithm = self::ALGORITHM;
        $result->keyId = $this->currentKeyId;
        $result->version = self::VERSION;

        return $result;
    }

    public function decrypt(
        $ciphertext,
        $iv,
        $tag,
        $keyId,
        $aad = ''
    ) {
        if (strlen($iv) !== self::IV_LENGTH) {
            throw new RuntimeException('Invalid IV.');
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException(
                'Invalid authentication tag.'
            );
        }

        $key = $this->keyProvider->getKey($keyId);

        if (strlen($key) !== 32) {
            throw new RuntimeException(
                'AES-256 key must be exactly 32 bytes.'
            );
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption or authentication failed.'
            );
        }

        return $plaintext;
    }
}
```

------------------------------------------------------------------------

## 17. Additional Authenticated Data (AAD)

AAD is not encrypted, but it is authenticated by GCM.

It can bind ciphertext to application context.

Example conceptual fields:

``` text
type = message
company = 10
record = 532
```

A canonical representation might be:

``` text
type=message&company=10&record=532
```

Encryption:

``` php
$aad = sprintf(
    'type=message&company=%d&record=%d',
    $companyId,
    $messageId
);

$result = $cryptoService->encrypt(
    $messageText,
    $aad
);
```

Decryption must reconstruct exactly the same AAD bytes.

Important:

> Do not casually include mutable fields in AAD.

If an authenticated field changes, decryption fails unless the data is
re-encrypted with the new AAD.

For security-critical formats, prefer a precisely defined binary or
otherwise canonical serialization rather than ambiguous string
concatenation.

------------------------------------------------------------------------

## 18. Saving Encrypted Text

Example:

``` php
$data = array(
    'ciphertext'         => $result->ciphertext,
    'iv'                 => $result->iv,
    'auth_tag'           => $result->tag,
    'algorithm'          => $result->algorithm,
    'key_id'             => $result->keyId,
    'encryption_version' => $result->version
);

$db->update(
    'secure_messages',
    $data,
    $db->quoteInto('id = ?', $messageId)
);
```

------------------------------------------------------------------------

## 19. Reading Encrypted Text

The correct order is:

``` text
Authenticate
    |
    v
Load metadata
    |
    v
Check company/tenant boundary
    |
    v
Check record permission
    |
    v
Decrypt
```

Example:

``` php
$message = $messageTable->find($messageId)->current();

if (!$message) {
    throw new RuntimeException('Message not found.');
}

if (!$authorizationService->canView(
    $currentUser,
    $message
)) {
    throw new Zend_Controller_Action_Exception(
        'Forbidden',
        403
    );
}

$aad = sprintf(
    'type=message&company=%d&record=%d',
    $message->company_id,
    $message->id
);

$plaintext = $cryptoService->decrypt(
    $message->ciphertext,
    $message->iv,
    $message->auth_tag,
    $message->key_id,
    $aad
);
```

------------------------------------------------------------------------

# Part IV --- Attachment Storage

## 20. Recommended Attachment Table

``` sql
CREATE TABLE secure_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,

    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    original_size BIGINT UNSIGNED NOT NULL,

    encryption_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    algorithm VARCHAR(32) NOT NULL DEFAULT 'AES-256-GCM',
    key_id VARCHAR(64) NOT NULL,

    file_id VARCHAR(64) NULL,
    chunk_size INT UNSIGNED NULL,
    chunk_count BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),
    KEY idx_company_id (company_id),
    KEY idx_message_id (message_id),
    KEY idx_key_id (key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

For the chunked container, per-chunk nonces and authentication tags are
stored inside the encrypted file itself.

------------------------------------------------------------------------

## 21. Filesystem vs Database

A practical approach is:

``` text
MariaDB
    |
    +-- attachment ID
    +-- company/message ID
    +-- original filename
    +-- MIME type
    +-- original size
    +-- storage name
    +-- key ID
    +-- encryption version
    +-- file/container metadata

Filesystem
    |
    +-- encrypted attachment bytes
```

For large attachments, filesystem/object storage is generally more
practical than putting all file contents into `LONGBLOB`.

------------------------------------------------------------------------

## 22. Opaque Storage Names

Do not use the original filename as the physical storage filename.

Instead:

``` php
$storageName =
    bin2hex(random_bytes(32))
    . '.bin';
```

Example:

``` text
7d108cb42fe8a71c...98e1.bin
```

Keep the original filename only as metadata.

------------------------------------------------------------------------

## 23. Store Attachments Outside the Web Root

Avoid:

``` text
htdocs/
    uploads/
        encrypted/
```

Prefer:

``` text
D:\SecureData\
    attachments\
```

or an equivalent protected directory on the server.

Clients must not directly request encrypted storage files.

All access should pass through PHP authentication and authorization.

------------------------------------------------------------------------

# Part V --- Why Large Files Need Chunking

## 24. Problem With `file_get_contents()`

This:

``` php
$plaintext = file_get_contents($uploadedFile);
```

loads the complete file into memory.

For a small file this may be acceptable.

For a 500 MB file it is undesirable because PHP may need memory for:

-   the plaintext,
-   ciphertext,
-   temporary copies,
-   framework/application overhead.

Instead, process the file in chunks.

------------------------------------------------------------------------

## 25. Example Chunk Size

A practical starting value is:

``` php
const CHUNK_SIZE = 4 * 1024 * 1024;
```

That is:

``` text
4 MiB
```

A 500 MB file requires approximately 125 chunks.

The correct value should ultimately be benchmarked for the deployment
environment.

------------------------------------------------------------------------

# Part VI --- S3EF: Versioned Encrypted File Format

## 26. Purpose

The proposed encrypted attachment container is called:

``` text
S3EF
```

Meaning:

``` text
S3 Encrypted File
```

The format is versioned:

``` text
S3EF v1
```

Versioning allows future algorithm or format changes without making old
encrypted files unreadable.

------------------------------------------------------------------------

## 27. Conceptual Format

``` text
+--------------------------------+
| S3EF Header                    |
|                                |
| Magic: S3EF                    |
| Version                        |
| Chunk size                     |
| Key ID                         |
| Random File ID                 |
+--------------------------------+
| Chunk 0                        |
|                                |
| Flags                          |
| Ciphertext length              |
| Nonce                          |
| Authentication tag             |
| Ciphertext                     |
+--------------------------------+
| Chunk 1                        |
|                                |
| Flags                          |
| Ciphertext length              |
| Nonce                          |
| Authentication tag             |
| Ciphertext                     |
+--------------------------------+
| ...                            |
+--------------------------------+
| Final Chunk                    |
+--------------------------------+
```

------------------------------------------------------------------------

## 28. File ID

Each encrypted file receives a random identifier:

``` php
$fileId = random_bytes(16);
```

The file ID helps cryptographically bind chunks to one particular
container.

A chunk copied from another encrypted file should not authenticate
successfully.

------------------------------------------------------------------------

## 29. Independent Chunk Authentication

Each chunk is independently encrypted using AES-256-GCM:

``` text
Plaintext chunk
      +
Unique nonce
      +
AAD
      |
      v
AES-256-GCM
      |
      +-- ciphertext
      +-- authentication tag
```

Never reuse the same nonce with the same AES key.

------------------------------------------------------------------------

## 30. Chunk Ordering

It is not enough to authenticate only the chunk bytes.

Otherwise an attacker might attempt to reorder valid chunks.

Therefore the chunk index is included in AAD:

``` text
chunk 0
chunk 1
chunk 2
...
```

If chunk 10 is moved to position 5, authentication should fail because
the decryptor reconstructs AAD for position 5.

------------------------------------------------------------------------

## 31. Final Chunk Authentication

The final/non-final state is also authenticated.

This is important for detecting truncation.

Example:

``` text
chunk 0 -> non-final
chunk 1 -> non-final
chunk 2 -> final
```

If a non-final chunk is made to appear final without a valid new GCM
tag, authentication fails.

------------------------------------------------------------------------

## 32. Chunk Length Authentication

The chunk length is included in authenticated metadata.

This protects the framing from unauthorized manipulation.

------------------------------------------------------------------------

## 33. Recommended S3EF v1 AAD

Before production, the complete canonical container metadata should be
cryptographically bound to every chunk.

Recommended logical AAD fields:

``` text
magic / format identifier
format version
algorithm identifier
file ID
key ID
configured chunk size
chunk index
final flag
current chunk length
```

Conceptually:

``` text
S3EF
version
AES-256-GCM
file ID
key ID
chunk size
chunk index
final flag
chunk length
```

The actual production implementation should define one exact,
unambiguous binary serialization for these fields.

This is preferable to loosely concatenating text values.

------------------------------------------------------------------------

# Part VII --- `encryptLargeFile()`

## 34. API

Recommended method:

``` php
public function encryptLargeFile(
    $sourceFile,
    $destinationFile,
    $keyId
);
```

Example:

``` php
$result = $crypto->encryptLargeFile(
    'C:\\temp\\phpA91F.tmp',
    'D:\\SecureData\\attachments\\a93f7281.bin',
    'company-data-2026-01'
);
```

------------------------------------------------------------------------

## 35. Encryption Flow

``` text
Open source file
      |
      v
Create temporary encrypted file
      |
      v
Generate random file ID
      |
      v
Resolve AES key
      |
      v
Write S3EF header
      |
      v
Read 4 MiB plaintext
      |
      v
Determine final/non-final state
      |
      v
Generate unique nonce
      |
      v
Build canonical AAD
      |
      v
AES-256-GCM encrypt
      |
      v
Write chunk framing
      |
      v
Next chunk
      |
      v
Flush/close
      |
      v
Atomic finalization
```

------------------------------------------------------------------------

## 36. Temporary File Strategy

Do not write directly to the final attachment name.

Use:

``` text
a93f7281.bin.random.tmp
```

During encryption.

Only after the complete file is encrypted successfully:

``` text
temporary encrypted file
        |
        v
atomic rename
        |
        v
final encrypted file
```

If encryption fails:

``` text
delete temporary file
```

This prevents incomplete encrypted files from being treated as valid
attachments.

The temporary and final paths should be on the same filesystem/volume
when atomic rename semantics are required.

------------------------------------------------------------------------

## 37. Empty Files

An empty file should not consist of only an unauthenticated header.

A safer design is to write one authenticated final chunk containing zero
plaintext bytes.

This proves that encryption completed successfully.

------------------------------------------------------------------------

# Part VIII --- `streamDecryptLargeFile()`

## 38. API

Recommended method:

``` php
public function streamDecryptLargeFile(
    $encryptedFile,
    callable $writer
);
```

Example:

``` php
$crypto->streamDecryptLargeFile(
    $encryptedPath,
    function ($plaintext) {
        echo $plaintext;
        return true;
    }
);
```

------------------------------------------------------------------------

## 39. Streaming Decryption Flow

``` text
Open encrypted file
      |
      v
Read/validate header
      |
      v
Resolve key by key_id
      |
      v
Read chunk framing
      |
      v
Read nonce/tag/ciphertext
      |
      v
Reconstruct canonical AAD
      |
      v
AES-GCM authenticate + decrypt
      |
      +---- failure ----> STOP
      |
    success
      |
      v
Send plaintext chunk
      |
      v
Discard chunk from memory
      |
      v
Read next chunk
```

Only authenticated plaintext is passed to the writer callback.

------------------------------------------------------------------------

## 40. Important Streaming Limitation

Suppose chunks 0--79 authenticate successfully and have already been
sent to the browser.

Then chunk 80 fails authentication.

The server stops immediately, but the browser has already received
chunks 0--79.

This is inherent to progressive authenticated streaming.

If the requirement is:

> The client must receive no plaintext until the complete file has been
> authenticated.

then the server must first authenticate/decrypt the complete file into
protected temporary storage or use another design that delays release
until full verification.

For ordinary large-file viewing, independently authenticated chunks
provide a practical balance.

------------------------------------------------------------------------

# Part IX --- Production-Oriented Large File Service

## 41. Core Constants

``` php
class S3_Crypto_LargeFileService
{
    const MAGIC = 'S3EF';
    const VERSION = 1;

    const ALGORITHM = 'aes-256-gcm';

    const KEY_LENGTH = 32;
    const NONCE_LENGTH = 12;
    const TAG_LENGTH = 16;

    const FILE_ID_LENGTH = 16;

    const DEFAULT_CHUNK_SIZE = 4194304;

    const MAX_CHUNK_SIZE = 67108864;
}
```

`MAX_CHUNK_SIZE` is a defensive parser limit.

The decryptor should never blindly allocate memory based on an arbitrary
chunk size supplied by an untrusted encrypted file.

------------------------------------------------------------------------

## 42. Complete `S3_Crypto_LargeFileService` Implementation

The following PHP 7.4-compatible implementation provides the large-file
encryption and streaming-decryption layer discussed in this guide.

Important characteristics:

-   AES-256-GCM is used independently for every chunk.
-   The default plaintext chunk size is 4 MiB.
-   Every chunk receives a fresh random 12-byte nonce.
-   The file ID, chunk index, final-chunk state, and chunk length are
    authenticated.
-   Plaintext is passed to the writer only after the current chunk has
    authenticated successfully.
-   Encryption writes to a temporary file first.
-   The temporary file is renamed only after encryption completes
    successfully.
-   Empty files are represented by one authenticated empty final chunk.
-   Defensive limits are applied while parsing encrypted containers.
-   Unexpected bytes after the authenticated final chunk are rejected.

> **Production-format note:** Before encrypting production data,
> finalize the exact S3EF v1 binary format. In particular, the complete
> canonical header---including algorithm identifier, key ID, and
> configured chunk size---should be cryptographically bound to each
> chunk's AAD. Once production files exist, do not silently change the
> meaning of S3EF v1; introduce S3EF v2 for incompatible changes.

``` php
<?php

/**
 * Chunked authenticated encryption for large files.
 *
 * PHP: 7.4+
 * OpenSSL: AES-256-GCM support required
 *
 * File format: S3EF v1
 *
 * IMPORTANT:
 * - The encryption key must be exactly 32 random bytes.
 * - Never store the encryption key inside this encrypted file.
 * - Authorization must happen BEFORE streamDecryptLargeFile() is called.
 */
class S3_Crypto_LargeFileService
{
    const MAGIC = 'S3EF';
    const VERSION = 1;

    const ALGORITHM = 'aes-256-gcm';

    const KEY_LENGTH = 32;
    const NONCE_LENGTH = 12;
    const TAG_LENGTH = 16;

    const FILE_ID_LENGTH = 16;

    // 4 MiB
    const DEFAULT_CHUNK_SIZE = 4194304;

    // Defensive upper bound when parsing an untrusted encrypted container.
    const MAX_CHUNK_SIZE = 67108864; // 64 MiB

    /**
     * @var S3_Crypto_KeyProviderInterface
     */
    private $keyProvider;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @param S3_Crypto_KeyProviderInterface $keyProvider
     * @param int $chunkSize
     */
    public function __construct(
        S3_Crypto_KeyProviderInterface $keyProvider,
        $chunkSize = self::DEFAULT_CHUNK_SIZE
    ) {
        if ($chunkSize < 1 || $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new InvalidArgumentException(
                'Invalid encryption chunk size.'
            );
        }

        $this->keyProvider = $keyProvider;
        $this->chunkSize = (int) $chunkSize;
    }

    /**
     * Encrypt a file using independently authenticated GCM chunks.
     *
     * @param string $sourceFile
     * @param string $destinationFile
     * @param string $keyId
     * @return array
     */
    public function encryptLargeFile(
        $sourceFile,
        $destinationFile,
        $keyId
    ) {
        if (!is_file($sourceFile)) {
            throw new RuntimeException(
                'Source file does not exist.'
            );
        }

        $key = $this->getValidatedKey($keyId);

        $fileId = random_bytes(self::FILE_ID_LENGTH);

        $temporaryFile = $destinationFile
            . '.'
            . bin2hex(random_bytes(8))
            . '.tmp';

        $input = null;
        $output = null;

        $chunkIndex = 0;
        $plaintextSize = 0;

        try {
            $input = fopen($sourceFile, 'rb');

            if ($input === false) {
                throw new RuntimeException(
                    'Unable to open source file.'
                );
            }

            $output = fopen($temporaryFile, 'xb');

            if ($output === false) {
                throw new RuntimeException(
                    'Unable to create temporary encrypted file.'
                );
            }

            $this->writeHeader(
                $output,
                $fileId,
                $keyId,
                $this->chunkSize
            );

            /*
             * Use one-chunk lookahead so the final-chunk flag can be
             * authenticated. This allows the decryptor to detect
             * truncation of a normal non-final chunk sequence.
             */
            $current = $this->readPlaintextChunk(
                $input,
                $this->chunkSize
            );

            /*
             * Represent an empty file with one authenticated empty final
             * chunk. A header by itself is not a completed container.
             */
            if ($current === null) {
                $this->writeEncryptedChunk(
                    $output,
                    '',
                    $key,
                    $fileId,
                    0,
                    true
                );

                $chunkIndex = 1;
            } else {
                while (true) {
                    $next = $this->readPlaintextChunk(
                        $input,
                        $this->chunkSize
                    );

                    $isFinal = ($next === null);

                    $this->writeEncryptedChunk(
                        $output,
                        $current,
                        $key,
                        $fileId,
                        $chunkIndex,
                        $isFinal
                    );

                    $plaintextSize += strlen($current);
                    $chunkIndex++;

                    if ($isFinal) {
                        break;
                    }

                    $current = $next;
                }
            }

            if (!fflush($output)) {
                throw new RuntimeException(
                    'Unable to flush encrypted output.'
                );
            }

            fclose($input);
            $input = null;

            fclose($output);
            $output = null;

            /*
             * Do not overwrite an existing encrypted attachment.
             */
            if (file_exists($destinationFile)) {
                throw new RuntimeException(
                    'Destination file already exists.'
                );
            }

            if (!rename($temporaryFile, $destinationFile)) {
                throw new RuntimeException(
                    'Unable to finalize encrypted file.'
                );
            }

            return array(
                'version' => self::VERSION,
                'algorithm' => self::ALGORITHM,
                'key_id' => $keyId,
                'file_id' => bin2hex($fileId),
                'chunk_size' => $this->chunkSize,
                'chunk_count' => $chunkIndex,
                'original_size' => $plaintextSize
            );
        } catch (Exception $e) {
            if (is_resource($input)) {
                fclose($input);
            }

            if (is_resource($output)) {
                fclose($output);
            }

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }

            throw $e;
        }
    }

    /**
     * Stream-decrypt an encrypted S3EF file.
     *
     * The writer callback is called only AFTER the current chunk's GCM
     * authentication succeeds.
     *
     * IMPORTANT:
     * Earlier authenticated chunks may already have been delivered when a
     * later chunk fails authentication.
     *
     * @param string $encryptedFile
     * @param callable $writer
     * @return array
     */
    public function streamDecryptLargeFile(
        $encryptedFile,
        callable $writer
    ) {
        if (!is_file($encryptedFile)) {
            throw new RuntimeException(
                'Encrypted file does not exist.'
            );
        }

        $input = fopen($encryptedFile, 'rb');

        if ($input === false) {
            throw new RuntimeException(
                'Unable to open encrypted file.'
            );
        }

        try {
            $header = $this->readHeader($input);

            $key = $this->getValidatedKey(
                $header['key_id']
            );

            $chunkIndex = 0;
            $plaintextSize = 0;
            $foundFinalChunk = false;

            while (!$foundFinalChunk) {
                $recordHeader = $this->readExact(
                    $input,
                    5,
                    'chunk header'
                );

                $flags = ord($recordHeader[0]);

                if (($flags & 0xFE) !== 0) {
                    throw new RuntimeException(
                        'Unsupported chunk flags.'
                    );
                }

                $isFinal = (($flags & 0x01) === 0x01);

                $ciphertextLength = $this->unpackUint32(
                    substr($recordHeader, 1, 4)
                );

                if ($ciphertextLength > $header['chunk_size']) {
                    throw new RuntimeException(
                        'Invalid encrypted chunk length.'
                    );
                }

                if (!$isFinal &&
                    $ciphertextLength !== $header['chunk_size']) {
                    throw new RuntimeException(
                        'Non-final chunk has invalid length.'
                    );
                }

                $nonce = $this->readExact(
                    $input,
                    self::NONCE_LENGTH,
                    'chunk nonce'
                );

                $tag = $this->readExact(
                    $input,
                    self::TAG_LENGTH,
                    'authentication tag'
                );

                $ciphertext = $this->readExact(
                    $input,
                    $ciphertextLength,
                    'ciphertext'
                );

                $aad = $this->buildChunkAad(
                    $header['file_id'],
                    $chunkIndex,
                    $isFinal,
                    $ciphertextLength
                );

                $plaintext = openssl_decrypt(
                    $ciphertext,
                    self::ALGORITHM,
                    $key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    $aad
                );

                if ($plaintext === false) {
                    throw new RuntimeException(
                        'File authentication failed at chunk '
                        . $chunkIndex
                        . '.'
                    );
                }

                if (strlen($plaintext) !== $ciphertextLength) {
                    throw new RuntimeException(
                        'Unexpected plaintext length.'
                    );
                }

                /*
                 * Authentication succeeded.
                 * Only now may plaintext leave the crypto service.
                 */
                $result = call_user_func(
                    $writer,
                    $plaintext,
                    $chunkIndex,
                    $isFinal
                );

                if ($result === false) {
                    throw new RuntimeException(
                        'Plaintext writer reported a failure.'
                    );
                }

                $plaintextSize += strlen($plaintext);
                $chunkIndex++;

                if ($isFinal) {
                    $foundFinalChunk = true;
                }
            }

            /*
             * No bytes are allowed after the authenticated final chunk.
             */
            $extra = fread($input, 1);

            if ($extra === false) {
                throw new RuntimeException(
                    'Unable to check encrypted file ending.'
                );
            }

            if ($extra !== '') {
                throw new RuntimeException(
                    'Unexpected data exists after final chunk.'
                );
            }

            fclose($input);

            return array(
                'version' => $header['version'],
                'algorithm' => self::ALGORITHM,
                'key_id' => $header['key_id'],
                'file_id' => bin2hex($header['file_id']),
                'chunk_size' => $header['chunk_size'],
                'chunk_count' => $chunkIndex,
                'original_size' => $plaintextSize
            );
        } catch (Exception $e) {
            if (is_resource($input)) {
                fclose($input);
            }

            throw $e;
        }
    }

    /**
     * @param resource $output
     * @param string $fileId
     * @param string $keyId
     * @param int $chunkSize
     */
    private function writeHeader(
        $output,
        $fileId,
        $keyId,
        $chunkSize
    ) {
        $keyIdLength = strlen($keyId);

        if ($keyIdLength < 1 || $keyIdLength > 255) {
            throw new RuntimeException(
                'Invalid key ID length.'
            );
        }

        $header =
            self::MAGIC .
            chr(self::VERSION) .
            $this->packUint32($chunkSize) .
            chr($keyIdLength) .
            $keyId .
            $fileId;

        $this->writeAll($output, $header);
    }

    /**
     * @param resource $input
     * @return array
     */
    private function readHeader($input)
    {
        $fixed = $this->readExact(
            $input,
            10,
            'file header'
        );

        $magic = substr($fixed, 0, 4);

        if ($magic !== self::MAGIC) {
            throw new RuntimeException(
                'Invalid encrypted file signature.'
            );
        }

        $version = ord($fixed[4]);

        if ($version !== self::VERSION) {
            throw new RuntimeException(
                'Unsupported encrypted file version.'
            );
        }

        $chunkSize = $this->unpackUint32(
            substr($fixed, 5, 4)
        );

        if ($chunkSize < 1 ||
            $chunkSize > self::MAX_CHUNK_SIZE) {
            throw new RuntimeException(
                'Invalid encrypted file chunk size.'
            );
        }

        $keyIdLength = ord($fixed[9]);

        if ($keyIdLength < 1) {
            throw new RuntimeException(
                'Invalid encrypted file key ID.'
            );
        }

        $keyId = $this->readExact(
            $input,
            $keyIdLength,
            'key ID'
        );

        $fileId = $this->readExact(
            $input,
            self::FILE_ID_LENGTH,
            'file ID'
        );

        return array(
            'version' => $version,
            'chunk_size' => $chunkSize,
            'key_id' => $keyId,
            'file_id' => $fileId
        );
    }

    /**
     * @param resource $output
     * @param string $plaintext
     * @param string $key
     * @param string $fileId
     * @param int $chunkIndex
     * @param bool $isFinal
     */
    private function writeEncryptedChunk(
        $output,
        $plaintext,
        $key,
        $fileId,
        $chunkIndex,
        $isFinal
    ) {
        $plaintextLength = strlen($plaintext);

        if ($plaintextLength > $this->chunkSize) {
            throw new RuntimeException(
                'Plaintext chunk is too large.'
            );
        }

        $nonce = random_bytes(self::NONCE_LENGTH);

        $aad = $this->buildChunkAad(
            $fileId,
            $chunkIndex,
            $isFinal,
            $plaintextLength
        );

        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException(
                'Chunk encryption failed.'
            );
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException(
                'Invalid GCM authentication tag.'
            );
        }

        /*
         * GCM does not add padding, therefore ciphertext length equals
         * plaintext length.
         */
        $flags = $isFinal ? 0x01 : 0x00;

        $record =
            chr($flags) .
            $this->packUint32(strlen($ciphertext)) .
            $nonce .
            $tag .
            $ciphertext;

        $this->writeAll($output, $record);
    }

    /**
     * AAD binds each chunk to:
     *
     * - container format/version
     * - file identity
     * - exact chunk position
     * - final/non-final state
     * - expected plaintext/ciphertext length
     *
     * Production S3EF v1 should additionally bind the complete canonical
     * header (algorithm ID, key ID, configured chunk size, etc.).
     *
     * @param string $fileId
     * @param int $chunkIndex
     * @param bool $isFinal
     * @param int $length
     * @return string
     */
    private function buildChunkAad(
        $fileId,
        $chunkIndex,
        $isFinal,
        $length
    ) {
        return
            self::MAGIC .
            chr(self::VERSION) .
            $fileId .
            $this->packUint32($chunkIndex) .
            chr($isFinal ? 1 : 0) .
            $this->packUint32($length);
    }

    /**
     * @param resource $input
     * @param int $maximumLength
     * @return string|null
     */
    private function readPlaintextChunk(
        $input,
        $maximumLength
    ) {
        $buffer = '';

        while (strlen($buffer) < $maximumLength) {
            $remaining = $maximumLength - strlen($buffer);

            $data = fread($input, $remaining);

            if ($data === false) {
                throw new RuntimeException(
                    'Unable to read plaintext source file.'
                );
            }

            if ($data === '') {
                if (feof($input)) {
                    break;
                }

                throw new RuntimeException(
                    'Unexpected zero-byte plaintext read.'
                );
            }

            $buffer .= $data;
        }

        if ($buffer === '' && feof($input)) {
            return null;
        }

        return $buffer;
    }

    /**
     * @param resource $input
     * @param int $length
     * @param string $description
     * @return string
     */
    private function readExact(
        $input,
        $length,
        $description
    ) {
        if ($length === 0) {
            return '';
        }

        $buffer = '';

        while (strlen($buffer) < $length) {
            $remaining = $length - strlen($buffer);

            $data = fread($input, $remaining);

            if ($data === false) {
                throw new RuntimeException(
                    'Unable to read ' . $description . '.'
                );
            }

            if ($data === '') {
                throw new RuntimeException(
                    'Encrypted file is truncated while reading '
                    . $description
                    . '.'
                );
            }

            $buffer .= $data;
        }

        return $buffer;
    }

    /**
     * @param resource $output
     * @param string $data
     */
    private function writeAll($output, $data)
    {
        $length = strlen($data);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite(
                $output,
                substr($data, $offset)
            );

            if ($written === false || $written === 0) {
                throw new RuntimeException(
                    'Unable to write encrypted file.'
                );
            }

            $offset += $written;
        }
    }

    /**
     * @param string $keyId
     * @return string
     */
    private function getValidatedKey($keyId)
    {
        $key = $this->keyProvider->getKey($keyId);

        if (!is_string($key) ||
            strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException(
                'AES-256 key must contain exactly 32 bytes.'
            );
        }

        return $key;
    }

    /**
     * Big-endian unsigned 32-bit integer.
     *
     * @param int $value
     * @return string
     */
    private function packUint32($value)
    {
        if ($value < 0 || $value > 4294967295) {
            throw new RuntimeException(
                'Integer exceeds S3EF v1 range.'
            );
        }

        return pack('N', $value);
    }

    /**
     * @param string $data
     * @return int
     */
    private function unpackUint32($data)
    {
        $value = unpack('Nvalue', $data);

        return (int) $value['value'];
    }
}
```

### Example: Encrypt an Uploaded Attachment

``` php
$keyProvider = new S3_Crypto_FileKeyProvider(
    'D:\\SecureKeys'
);

$crypto = new S3_Crypto_LargeFileService(
    $keyProvider
);

$result = $crypto->encryptLargeFile(
    'C:\\temp\\phpA91F.tmp',
    'D:\\SecureData\\attachments\\a93f7281.bin',
    'company-data-2026-01'
);
```

Example result:

``` php
array(
    'version' => 1,
    'algorithm' => 'aes-256-gcm',
    'key_id' => 'company-data-2026-01',
    'file_id' => '...',
    'chunk_size' => 4194304,
    'chunk_count' => 126,
    'original_size' => 524288000
);
```

Store the appropriate metadata in MariaDB.

### Example: Stream Decryption

Perform authentication and authorization first.

Then:

``` php
$this->_helper->layout->disableLayout();
$this->_helper->viewRenderer->setNoRender(true);

$response = $this->getResponse();

$response->setHeader(
    'Content-Type',
    $safeMimeType,
    true
);

$response->setHeader(
    'Content-Disposition',
    'inline; filename="document.pdf"',
    true
);

$response->setHeader(
    'Cache-Control',
    'private, no-store, max-age=0',
    true
);

$response->setHeader(
    'X-Content-Type-Options',
    'nosniff',
    true
);

$response->sendHeaders();

$crypto->streamDecryptLargeFile(
    $encryptedPath,
    function ($plaintext) {
        echo $plaintext;

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();

        return true;
    }
);

exit;
```

The writer receives a plaintext chunk only after that chunk's GCM
authentication succeeds.

## 43. Defensive Parsing

Encrypted files must be treated as untrusted input.

The decryptor should validate:

-   magic/signature,
-   supported version,
-   supported algorithm,
-   key ID length,
-   configured chunk size,
-   chunk ciphertext length,
-   chunk flags,
-   nonce length,
-   tag length,
-   chunk ordering,
-   final-chunk state,
-   no unexpected bytes after the final chunk.

Never allocate arbitrary amounts of memory based only on values read
from the encrypted file.

------------------------------------------------------------------------

# Part X --- ZF1 Client Viewing

## 44. Authorization Before Decryption

Example:

``` php
$attachment = $attachmentTable
    ->find($attachmentId)
    ->current();

if (!$attachment) {
    throw new Zend_Controller_Action_Exception(
        'Not found',
        404
    );
}

if (!$authorizationService->canView(
    $currentUser,
    $attachment
)) {
    throw new Zend_Controller_Action_Exception(
        'Forbidden',
        403
    );
}
```

Only after this check should the application open and decrypt the
encrypted file.

------------------------------------------------------------------------

## 45. Streaming Response

Disable normal ZF1 rendering:

``` php
$this->_helper->layout->disableLayout();
$this->_helper->viewRenderer->setNoRender(true);
```

Set appropriate headers:

``` php
$response = $this->getResponse();

$response->setHeader(
    'Content-Type',
    $safeMimeType,
    true
);

$response->setHeader(
    'Content-Disposition',
    'inline; filename="document.pdf"',
    true
);

$response->setHeader(
    'Cache-Control',
    'private, no-store, max-age=0',
    true
);

$response->setHeader(
    'X-Content-Type-Options',
    'nosniff',
    true
);
```

Then stream authenticated plaintext chunks:

``` php
$response->sendHeaders();

$crypto->streamDecryptLargeFile(
    $encryptedPath,
    function ($plaintext) {
        echo $plaintext;

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();

        return true;
    }
);

exit;
```

Remember:

Once headers and some plaintext have been sent, a later authentication
failure cannot be cleanly converted into a normal HTTP error page.

The connection should be terminated immediately if a later chunk fails
authentication.

------------------------------------------------------------------------

## 46. Filename Safety

Do not blindly place a database filename into an HTTP header.

Bad:

``` php
'inline; filename="' . $originalName . '"'
```

The original filename may contain problematic characters.

Use a dedicated safe filename/header encoding routine.

Also do not trust the MIME type supplied by the browser during upload.

Validate permitted file types server-side.

------------------------------------------------------------------------

# Part XI --- Plaintext Handling

## 47. Avoid Permanent Plaintext Files

Do not do:

``` text
encrypted.bin
      |
      v
decrypt
      |
      v
htdocs/temp/document.pdf
```

A permanent or web-accessible plaintext copy undermines the
encryption-at-rest design.

Prefer:

``` text
encrypted attachment
      |
      v
authorized PHP request
      |
      v
authenticate/decrypt chunk
      |
      v
HTTPS
      |
      v
browser
```

------------------------------------------------------------------------

## 48. Temporary Plaintext When Full Verification Is Required

If a business requirement demands complete-file authentication before
any plaintext is sent, protected temporary storage may be necessary.

If so:

-   keep it outside the web root,
-   restrict filesystem permissions,
-   use unpredictable filenames,
-   delete it immediately after use,
-   account for abnormal process termination,
-   consider encrypted temporary storage,
-   implement cleanup of abandoned temporary files.

------------------------------------------------------------------------

# Part XII --- Suggested Project Structure

## 49. PHP Classes

``` text
library/
└── S3/
    └── Crypto/
        ├── Service.php
        ├── LargeFileService.php
        ├── Result.php
        ├── KeyProviderInterface.php
        ├── FileKeyProvider.php
        └── Exception.php

application/
├── models/
│   ├── SecureMessage.php
│   └── SecureAttachment.php
│
└── services/
    ├── MessageService.php
    └── AttachmentService.php
```

Responsibilities should remain separated.

### `S3_Crypto_Service`

Handles small/text encryption:

``` text
encryptText()
decryptText()
```

### `S3_Crypto_LargeFileService`

Handles chunked file encryption:

``` text
encryptLargeFile()
streamDecryptLargeFile()
```

### `S3_Crypto_KeyProviderInterface`

Resolves keys by key ID.

### `AttachmentService`

Handles business logic:

``` text
authorization
metadata
storage path
upload validation
crypto invocation
database transaction/state
```

------------------------------------------------------------------------

# Part XIII --- Error Handling

## 50. Fail Closed

Cryptographic errors should deny access.

Examples:

``` text
unknown key ID
invalid key length
unsupported version
invalid header
truncated encrypted file
invalid chunk length
authentication failure
unexpected bytes after final chunk
filesystem read/write failure
```

Never return partially decrypted unauthenticated data.

For streaming, already authenticated earlier chunks may have been sent;
no additional plaintext should be sent after the first failure.

------------------------------------------------------------------------

## 51. Do Not Expose Sensitive Crypto Details to Clients

Server logs may record an internal error such as:

``` text
GCM authentication failure at encrypted attachment 481, chunk 72
```

The browser can receive a more general response when possible:

``` text
Unable to open attachment.
```

Avoid exposing:

-   key paths,
-   raw keys,
-   stack traces,
-   sensitive storage paths,
-   internal crypto configuration.

------------------------------------------------------------------------

# Part XIV --- Database and Filesystem Consistency

## 52. Avoid Orphaned State

Attachment creation affects both:

``` text
filesystem
+
MariaDB
```

Plan the sequence carefully.

One possible workflow:

``` text
Validate upload
      |
      v
Generate storage identity
      |
      v
Encrypt to temporary file
      |
      v
Finalize encrypted file
      |
      v
Insert/update database metadata
      |
      v
Success
```

If the database operation fails after the encrypted file is finalized,
delete or queue cleanup of the orphaned encrypted file.

Alternatively, create a database row with an explicit processing state
and transition it only after encryption succeeds.

Example states:

``` text
UPLOADING
ENCRYPTING
READY
FAILED
```

Clients should only be allowed to view:

``` text
READY
```

------------------------------------------------------------------------

# Part XV --- Security Rules

## 53. Mandatory Rules

1.  Use authenticated encryption such as AES-256-GCM.
2.  Use cryptographically random encryption keys.
3.  AES-256 keys must contain exactly 32 bytes.
4.  Never use user passwords directly as AES keys.
5.  Never store encryption keys in the same database rows as ciphertext.
6.  Keep keys separate from normal database/application backups where
    practical.
7.  Generate unique GCM nonces.
8.  Never deliberately reuse the same key/nonce pair.
9.  Store a `key_id` with encrypted objects.
10. Store an encryption/container version.
11. Authenticate before decrypting.
12. Authorize before decrypting.
13. Enforce company/tenant boundaries before releasing plaintext.
14. Keep encrypted attachments outside the web root.
15. Do not keep permanent plaintext attachment copies.
16. Validate uploaded file types server-side.
17. Sanitize/encode download filenames safely.
18. Treat encrypted containers as untrusted input.
19. Enforce parser size limits.
20. Stop immediately on authentication failure.
21. Use HTTPS even though the stored data is encrypted.
22. Test backup and key-recovery procedures.
23. Never delete old keys until all dependent data has been migrated or
    destroyed.
24. Audit access to sensitive decrypted content where appropriate.

------------------------------------------------------------------------

# Part XVI --- Testing

## 54. Unit Tests

At minimum, test:

``` text
text encrypt -> decrypt
empty text
Unicode text
large text
wrong key
wrong IV
wrong authentication tag
modified ciphertext
modified AAD
```

For files:

``` text
empty file
1-byte file
file smaller than one chunk
file exactly one chunk
file one byte larger than one chunk
many-chunk file
large PDF
large binary file
```

------------------------------------------------------------------------

## 55. Tampering Tests

Create tests that deliberately modify:

``` text
S3EF magic
version
algorithm identifier
key ID
file ID
chunk size
chunk flags
chunk length
nonce
authentication tag
ciphertext
chunk order
final flag
```

The decryptor must reject manipulated containers.

Also test:

``` text
remove final chunk
truncate ciphertext
append bytes after final chunk
copy a chunk from another encrypted file
duplicate a chunk
remove a middle chunk
swap two chunks
```

These tests are especially important because the encrypted-file format
is security-sensitive code.

------------------------------------------------------------------------

## 56. Memory Tests

Test large files while measuring PHP memory usage.

The objective is approximately:

``` text
memory usage proportional to chunk size
```

not:

``` text
memory usage proportional to complete attachment size
```

------------------------------------------------------------------------

## 57. Recovery Tests

Test what happens if the process terminates:

``` text
before encryption starts
during chunk encryption
after temporary file creation
after encryption but before rename
after rename but before DB update
```

No incomplete attachment should become visible as a valid document.

------------------------------------------------------------------------

# Part XVII --- Recommended Implementation Sequence

## 58. Phase 1 --- Key Infrastructure

Implement:

``` text
S3_Crypto_KeyProviderInterface
S3_Crypto_FileKeyProvider
key generation procedure
key directory permissions
key backup/recovery procedure
```

------------------------------------------------------------------------

## 59. Phase 2 --- Text Encryption

Implement and test:

``` text
S3_Crypto_Service
encryptText()
decryptText()
AAD rules
MariaDB schema
key_id
version
```

------------------------------------------------------------------------

## 60. Phase 3 --- Attachment Metadata

Implement:

``` text
secure_attachments
opaque storage names
protected storage directory
upload validation
authorization
```

------------------------------------------------------------------------

## 61. Phase 4 --- S3EF v1

Freeze the exact binary specification before production data is
encrypted.

Define:

``` text
header layout
algorithm identifier
integer byte order
file ID size
key ID encoding
chunk size field
chunk record layout
flags
nonce length
tag length
AAD binary serialization
final-chunk behavior
empty-file behavior
parser limits
```

Once production files exist, changing the meaning of version 1 is
dangerous.

If the format later changes, create:

``` text
S3EF v2
```

rather than silently changing v1.

------------------------------------------------------------------------

## 62. Phase 5 --- Streaming

Implement:

``` text
encryptLargeFile()
streamDecryptLargeFile()
ZF1 streaming controller
HTTP headers
failure handling
```

------------------------------------------------------------------------

## 63. Phase 6 --- Security Tests

Add:

``` text
tamper tests
truncation tests
chunk reorder tests
wrong-key tests
authorization tests
cross-company access tests
large-file tests
memory tests
failure/recovery tests
```

------------------------------------------------------------------------

## 64. Phase 7 --- Key Rotation

Implement controlled rotation:

``` text
new active key
old keys retained for decryption
background/administrative migration
verification
retirement only after dependency check
```

------------------------------------------------------------------------

# Part XVIII --- Final Recommended Architecture

``` text
                            CLIENT
                              |
                           HTTPS
                              |
                              v
                       ZF1 Application
                              |
                    +---------+---------+
                    |                   |
               Authentication      Validation
                    |                   |
                    +---------+---------+
                              |
                         Authorization
                              |
                  Company/tenant boundary
                              |
                              v
                       Business Service
                              |
                 +------------+------------+
                 |                         |
                 v                         v
        S3_Crypto_Service       S3_Crypto_LargeFileService
          text/small data             attachments
                 |                         |
                 +------------+------------+
                              |
                              v
                         Key Provider
                              |
                  +-----------+-----------+
                  |                       |
             Current key              Old keys
                  |                 for decryption
                  +-----------+-----------+
                              |
                     Protected key store


         ENCRYPTED STORAGE

      MariaDB                          Filesystem
         |                                |
 encrypted text                    S3EF encrypted files
 metadata                           outside web root
 key_id
 version
```

------------------------------------------------------------------------

# 65. Conclusion

The recommended system uses two related encryption paths:

``` text
Text / small values
    -> AES-256-GCM
    -> MariaDB

Large attachments
    -> S3EF versioned container
    -> chunked AES-256-GCM
    -> protected filesystem
```

Both rely on a separate key provider and `key_id`-based key management.

The most important architectural principles are:

``` text
authenticated encryption
+
strict key management
+
unique nonces
+
authorization before decryption
+
versioned formats
+
no permanent plaintext attachments
+
defensive parsing
+
tamper testing
```

Before production deployment, the exact S3EF v1 binary header and AAD
serialization should be finalized and documented as a stable
specification. After real encrypted files are created, future format
changes should use a new version rather than changing the interpretation
of v1.
