# Practical File Encryption Guide for PHP 7.4

## 1. Purpose

This document summarizes a practical design for securely encrypting
files and application data in a PHP 7.4 / ZF1 environment.

It focuses on:

-   AES-256 and symmetric encryption
-   Why public-key cryptography is not used for bulk file encryption
-   AES-256-GCM
-   Nonce / IV
-   Authentication tags
-   KDFs
-   Secure key storage
-   DEK and KEK
-   Envelope encryption
-   MariaDB and filesystem storage
-   PHP 7.4 examples
-   Recommended encrypted-file structure

Quantum computing and post-quantum cryptography are intentionally
excluded.

------------------------------------------------------------------------

# 2. Symmetric Encryption and AES

AES is a **symmetric encryption algorithm**.

This means the same secret key is used for encryption and decryption.

``` text
Plaintext
   +
AES Key
   |
   v
AES Encryption
   |
   v
Ciphertext
```

Decryption:

``` text
Ciphertext
   +
Same AES Key
   |
   v
AES Decryption
   |
   v
Plaintext
```

The AES key must remain secret.

------------------------------------------------------------------------

# 3. Why AES Is Good for File Encryption

AES is designed for efficiently encrypting large amounts of data.

Typical examples include:

-   PDFs
-   Images
-   ZIP files
-   Database fields
-   Backups
-   Large binary files

For AES-256, the key is:

``` text
256 bits
= 32 bytes
```

A secure random AES-256 key can be generated in PHP:

``` php
$key = random_bytes(32);
```

Do not create encryption keys from predictable strings.

Bad:

``` php
$key = 'my-secret-key';
```

Better:

``` php
$key = random_bytes(32);
```

------------------------------------------------------------------------

# 4. Why Not Use Public-Key Cryptography for the Whole File?

Public-key algorithms and symmetric algorithms solve different problems.

AES is excellent for bulk encryption.

Public-key cryptography is useful for operations such as:

-   key establishment
-   protecting small secrets
-   digital signatures
-   authentication

A typical design therefore uses hybrid encryption:

``` text
Large PDF
   |
   v
AES-256
   |
   v
Encrypted PDF
```

The small AES key is separately protected by an appropriate
key-management or public-key mechanism.

Do not think of the design as:

``` text
AES vs public-key cryptography
```

Instead:

``` text
Public-key / key-management mechanism
              |
              v
        Protect key material
              |
              v
             AES
              |
              v
       Encrypt large data
```

------------------------------------------------------------------------

# 5. AES Modes

AES itself is a block cipher. Applications use AES through a mode of
operation.

Examples include:

-   AES-CBC
-   AES-CTR
-   AES-GCM

For new application designs, an authenticated-encryption mode such as
**AES-GCM** is generally preferable when supported and correctly
implemented.

This guide uses:

``` text
AES-256-GCM
```

It provides both:

1.  confidentiality
2.  authentication/integrity

------------------------------------------------------------------------

# 6. Confidentiality vs Integrity

These are different security properties.

## Confidentiality

Confidentiality means:

> An unauthorized person should not be able to read the plaintext.

AES provides encryption for confidentiality.

## Integrity / Authentication

Integrity means:

> The application should be able to detect unauthorized modification of
> encrypted data.

AES-GCM provides this through an **authentication tag**.

A useful mental model is:

``` text
Encryption
    |
    +--> "Can an attacker read the data?"

Authentication Tag
    |
    +--> "Has the encrypted data been changed?"
```

------------------------------------------------------------------------

# 7. Why Encryption Alone Is Not Enough

Assume ciphertext contains:

``` text
8F A2 19 7C 31 55 90 ...
```

An attacker changes one byte:

``` text
8F A2 FF 7C 31 55 90 ...
      ^^
```

With an encryption mode that does not authenticate the ciphertext,
decryption may still execute.

The result may simply be corrupted or manipulated plaintext.

``` text
Modified Ciphertext
        |
        v
    Decryption
        |
        v
Corrupted / Modified Plaintext
```

The encryption algorithm does not necessarily know that the ciphertext
was intentionally modified.

This is why authenticated encryption is important.

------------------------------------------------------------------------

# 8. Authentication Tag

AES-GCM generates an authentication tag during encryption.

Conceptually:

``` text
Plaintext
   +
AES Key
   +
Nonce
   |
   v
AES-256-GCM
   |
   +----------+
   |          |
   v          v
Ciphertext   Authentication Tag
```

During decryption, the tag is checked.

``` text
Ciphertext
   +
Nonce
   +
Authentication Tag
   +
AES Key
   |
   v
AES-GCM Verification
   |
   +------------------+
   |                  |
 Valid              Invalid
   |                  |
   v                  v
Decrypt              Reject
```

If the ciphertext is modified, authentication should fail.

The application must reject the data.

------------------------------------------------------------------------

# 9. Authentication Tag Is Not Secret

The authentication tag normally does **not** need to be secret.

It can be stored together with the encrypted data.

For example:

``` text
Encrypted Package
+-------------------------+
| Nonce                   |
| Authentication Tag      |
| Ciphertext              |
+-------------------------+
```

The AES key is the secret value.

------------------------------------------------------------------------

# 10. PHP 7.4 AES-256-GCM Example

PHP's OpenSSL extension supports AES-GCM.

``` php
<?php

$plaintext = 'This is confidential data.';

$key = random_bytes(32);
$iv  = random_bytes(12);

$tag = '';

$ciphertext = openssl_encrypt(
    $plaintext,
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $iv,
    $tag
);

if ($ciphertext === false) {
    throw new RuntimeException('Encryption failed.');
}
```

After encryption:

``` text
$key         -> secret AES key
$iv          -> nonce / IV
$tag         -> authentication tag
$ciphertext  -> encrypted data
```

------------------------------------------------------------------------

# 11. PHP Decryption and Authentication

``` php
$plaintext = openssl_decrypt(
    $ciphertext,
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $iv,
    $tag
);

if ($plaintext === false) {
    throw new RuntimeException(
        'Authentication failed or encrypted data is invalid.'
    );
}
```

Never continue processing when authentication fails.

Bad:

``` php
$plaintext = openssl_decrypt(...);

// continue regardless of result
processDocument($plaintext);
```

Correct:

``` php
$plaintext = openssl_decrypt(...);

if ($plaintext === false) {
    throw new RuntimeException('Decryption/authentication failed.');
}

processDocument($plaintext);
```

------------------------------------------------------------------------

# 12. Demonstrating Tamper Detection

Assume encryption succeeded.

Modify one ciphertext byte:

``` php
$ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);
```

Then try to decrypt:

``` php
$plaintext = openssl_decrypt(
    $ciphertext,
    'aes-256-gcm',
    $key,
    OPENSSL_RAW_DATA,
    $iv,
    $tag
);

var_dump($plaintext);
```

Authentication should fail:

``` text
bool(false)
```

The important idea is:

``` text
Changed Ciphertext
       +
Original Tag
       |
       v
Authentication Failure
```

------------------------------------------------------------------------

# 13. Nonce / IV

**IV** means:

``` text
Initialization Vector
```

**Nonce** means:

``` text
Number Used Once
```

Different cryptographic modes use these terms differently.

For AES-GCM, APIs may call the value either an IV or nonce.

A commonly used nonce length for GCM is:

``` text
96 bits
= 12 bytes
```

In PHP:

``` php
$iv = random_bytes(12);
```

------------------------------------------------------------------------

# 14. Why We Need a Nonce

Suppose we encrypt exactly the same plaintext more than once with the
same key.

We do not want encryption to reveal obvious equality patterns.

A fresh nonce makes each encryption invocation distinct.

Conceptually:

``` text
Same Key
Same Plaintext
Nonce A
    |
    v
Ciphertext A

Same Key
Same Plaintext
Nonce B
    |
    v
Ciphertext B
```

The ciphertexts differ.

------------------------------------------------------------------------

# 15. Critical AES-GCM Nonce Rule

For AES-GCM:

> Never reuse the same nonce with the same AES key.

Bad:

``` text
AES Key K
   |
   +-- Nonce 001 -> File A
   |
   +-- Nonce 001 -> File B    WRONG
```

Correct:

``` text
AES Key K
   |
   +-- Nonce 001 -> File A
   |
   +-- Nonce 002 -> File B
   |
   +-- Nonce 003 -> File C
```

Nonce reuse with GCM can cause severe confidentiality and authentication
failures.

The nonce normally does not need to be secret.

Remember:

``` text
AES Key
    -> SECRET

Nonce
    -> UNIQUE for the key

Authentication Tag
    -> STORED WITH CIPHERTEXT
```

------------------------------------------------------------------------

# 16. What Must Be Stored?

To decrypt AES-GCM data later, the application needs:

``` text
1. Ciphertext
2. AES key
3. Nonce / IV
4. Authentication tag
```

However, these values should not all receive the same protection.

``` text
Ciphertext
    -> Can be stored

Nonce
    -> Can be stored with ciphertext

Authentication Tag
    -> Can be stored with ciphertext

AES Key
    -> Must be separately protected
```

------------------------------------------------------------------------

# 17. Database Storage Example

For small encrypted values, a database could contain:

``` sql
CREATE TABLE encrypted_data (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    ciphertext LONGBLOB NOT NULL,
    nonce VARBINARY(12) NOT NULL,
    auth_tag VARBINARY(16) NOT NULL,

    PRIMARY KEY (id)
);
```

For large files, it is often better to store the encrypted file on the
filesystem and metadata in MariaDB.

Example:

``` sql
CREATE TABLE encrypted_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    encrypted_file_path VARCHAR(500) NOT NULL,

    encrypted_dek BLOB NOT NULL,
    key_id VARCHAR(100) NOT NULL,

    PRIMARY KEY (id)
);
```

The nonce and authentication tag can either be stored in MariaDB or
included inside the encrypted file format.

------------------------------------------------------------------------

# 18. Recommended Encrypted File Container

For encrypted files, keeping cryptographic metadata with the ciphertext
is convenient.

Example:

``` text
document.enc

+----------------------------------+
| Magic / File Type                |
| Version                          |
| Algorithm Identifier             |
| Nonce                            |
| Authentication Tag               |
+----------------------------------+
|                                  |
| Ciphertext                       |
|                                  |
+----------------------------------+
```

For example:

``` text
Magic:       S3ENC
Version:     1
Algorithm:   AES-256-GCM
Nonce:       12 bytes
Tag:         16 bytes
Ciphertext:  ...
```

A version field is strongly recommended.

It makes future migrations easier.

For example:

``` text
Version 1
    -> AES-256-GCM format A

Version 2
    -> future format
```

Application code can select the appropriate decryptor based on the
version.

------------------------------------------------------------------------

# 19. What Is a KDF?

**KDF** means:

``` text
Key Derivation Function
```

A KDF derives cryptographic key material from another secret.

Conceptually:

``` text
Secret
   |
   v
KDF
   |
   v
Cryptographic Key
```

For example:

``` text
Shared Secret
    |
    v
HKDF
    |
    v
AES-256 Key
```

------------------------------------------------------------------------

# 20. Why Use a KDF?

Raw secret material should not automatically be reused for every
cryptographic purpose.

A KDF allows the application to derive purpose-specific keys.

For example:

``` text
                Master Secret
                     |
                    KDF
          +----------+----------+
          |          |          |
          v          v          v
        Key A      Key B      Key C
```

This provides **key separation**.

Conceptually:

``` text
KDF(secret, "document-encryption")
    -> Document Key

KDF(secret, "other-purpose")
    -> Different Key
```

------------------------------------------------------------------------

# 21. HKDF

HKDF means:

``` text
HMAC-based Key Derivation Function
```

HKDF-SHA-256 is a common construction for deriving keys from
high-entropy cryptographic secrets.

Conceptually:

``` text
High-Entropy Shared Secret
          |
          v
    HKDF-SHA-256
          |
          v
      AES Key
```

A KDF does not encrypt the document.

Its responsibility is key derivation.

------------------------------------------------------------------------

# 22. Password-Based KDFs

Human passwords are different from randomly generated cryptographic
secrets.

A password might be:

``` text
MyPassword123
```

Passwords generally have much less entropy than a random 256-bit key.

For passwords, use a password-hardening mechanism such as:

``` text
Argon2id
scrypt
PBKDF2
```

Conceptually:

``` text
Password
   +
Salt
   |
   v
Argon2id
   |
   v
Derived Key
```

Do not treat HKDF as a password-hardening replacement.

A useful distinction is:

``` text
High-entropy cryptographic secret
             |
             v
            HKDF


Human password
      |
      v
Argon2id / scrypt / PBKDF2
```

------------------------------------------------------------------------

# 23. Salt

A salt is normally a random or unique value used during password-based
derivation.

It does not normally need to be secret.

Example:

``` text
Password
   +
Random Salt
   |
   v
Password KDF
   |
   v
Derived Key
```

The salt can normally be stored with the encrypted data.

------------------------------------------------------------------------

# 24. The AES Key Storage Problem

After encrypting a document, the biggest question becomes:

> Where should the AES key be stored?

Do not simply store a plaintext AES key beside the encrypted document.

Bad:

``` text
documents table

id
file_path
aes_key       <-- plaintext key
```

If an attacker steals the database and encrypted files, the attacker
also has the keys.

------------------------------------------------------------------------

# 25. DEK --- Data Encryption Key

A better design generates a unique random key for each document.

This key is called a:

``` text
DEK = Data Encryption Key
```

For AES-256:

``` php
$dek = random_bytes(32);
```

The DEK encrypts the actual document.

``` text
PDF
 |
 v
DEK
 |
 v
AES-256-GCM
 |
 v
Encrypted PDF
```

------------------------------------------------------------------------

# 26. KEK --- Key Encryption Key

We still need to protect the DEK.

For this purpose, we use another key:

``` text
KEK = Key Encryption Key
```

The KEK protects/wraps the DEK.

``` text
DEK
 |
 v
KEK
 |
 v
Wrapped / Encrypted DEK
```

The wrapped DEK can then be stored in MariaDB.

------------------------------------------------------------------------

# 27. Envelope Encryption

This architecture is commonly called **envelope encryption**.

``` text
                 Server KEK
                    |
          +---------+---------+
          |         |         |
          v         v         v
       Wrapped   Wrapped   Wrapped
        DEK A     DEK B     DEK C
          |         |         |
          v         v         v
        File A    File B    File C
```

Each document receives its own random DEK.

Example:

``` text
Document A
   -> DEK-A

Document B
   -> DEK-B

Document C
   -> DEK-C
```

The DEKs are protected by the KEK.

------------------------------------------------------------------------

# 28. Why Use a Different DEK for Every File?

Do not use one AES key for every file unless the protocol has been
specifically designed for that model.

Per-file DEKs provide useful isolation and key-management flexibility.

``` text
File A -> DEK A
File B -> DEK B
File C -> DEK C
```

If one DEK is exposed, that does not automatically expose all other
documents.

It also makes operations such as:

-   selective key rotation
-   document revocation
-   migration
-   auditing

easier to manage.

------------------------------------------------------------------------

# 29. Where Should the KEK Be Stored?

The KEK requires stronger protection than the encrypted DEKs.

Do not store it:

-   in the same database row as the encrypted DEK
-   inside the encrypted file
-   in PHP source code
-   in Git
-   in a publicly accessible configuration file
-   under the web root

For a self-managed Linux server without a dedicated KMS/HSM, a simpler
option is a protected key location outside the web root.

Example:

``` text
Application:
/var/www/myapp/

Encrypted data:
/data/myapp/encrypted/

Key material:
/etc/myapp/keys/
```

For example:

``` text
/etc/myapp/keys/document-kek.bin
```

Access should be restricted to the required service/account.

For stronger environments, consider:

-   operating-system protected secret storage
-   a dedicated Key Management Service (KMS)
-   a Hardware Security Module (HSM)
-   another controlled server-side key service

The main architectural requirement is that compromise of the database
alone should not automatically reveal the KEK.

------------------------------------------------------------------------

# 30. Do Not Hard-Code the KEK

Bad:

``` php
class CryptoService
{
    private $masterKey =
        '12345678901234567890123456789012';
}
```

Also avoid committing production secrets into source-controlled
configuration.

The application should obtain the KEK from an appropriately protected
external secret/key source.

------------------------------------------------------------------------

# 31. Recommended Storage Architecture

A practical architecture is:

``` text
MariaDB
|
+-- document_id
+-- encrypted_file_path
+-- encrypted_dek
+-- key_id
```

Encrypted file:

``` text
document.enc
|
+-- version
+-- algorithm
+-- nonce
+-- authentication tag
+-- ciphertext
```

Separately protected:

``` text
KEK
|
+-- OS-protected key storage
    or
+-- KMS
    or
+-- HSM
    or
+-- controlled key service
```

------------------------------------------------------------------------

# 32. Complete Encryption Flow

## Step 1 --- Generate a DEK

``` php
$dek = random_bytes(32);
```

## Step 2 --- Generate a nonce

``` php
$nonce = random_bytes(12);
```

## Step 3 --- Encrypt the document

``` text
PDF
 +
DEK
 +
Nonce
 |
 v
AES-256-GCM
 |
 +-------------------+
 |                   |
 v                   v
Ciphertext          Tag
```

## Step 4 --- Protect the DEK

``` text
DEK
 |
 v
KEK / Key-Wrapping Mechanism
 |
 v
Encrypted DEK
```

## Step 5 --- Store the results

Filesystem:

``` text
version
algorithm
nonce
tag
ciphertext
```

MariaDB:

``` text
document_id
encrypted_file_path
encrypted_dek
key_id
```

The plaintext DEK should not be permanently stored.

------------------------------------------------------------------------

# 33. Complete Decryption Flow

When an authorized user requests a document:

``` text
MariaDB
   |
   v
Encrypted DEK
   |
   v
KEK
   |
   v
DEK
```

Then:

``` text
Encrypted File
   |
   +-- Ciphertext
   +-- Nonce
   +-- Authentication Tag
              |
              v
         AES-256-GCM
              |
              v
      Authentication Check
              |
        +-----+-----+
        |           |
      Valid       Invalid
        |           |
        v           v
      PDF          Reject
```

If authentication fails:

``` text
DO NOT return plaintext
DO NOT process the file
DO NOT ignore the error
```

------------------------------------------------------------------------

# 34. PHP Service Example

The following simplified service demonstrates the AES-GCM portion.

``` php
<?php

class S3_CryptoService
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32;
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function generateDek(): string
    {
        return random_bytes(self::KEY_LENGTH);
    }

    public function encrypt(string $plaintext, string $dek): array
    {
        if (strlen($dek) !== self::KEY_LENGTH) {
            throw new InvalidArgumentException(
                'AES-256 key must be exactly 32 bytes.'
            );
        }

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return [
            'nonce'      => $nonce,
            'tag'        => $tag,
            'ciphertext' => $ciphertext,
        ];
    }

    public function decrypt(
        string $ciphertext,
        string $dek,
        string $nonce,
        string $tag
    ): string {
        if (strlen($dek) !== self::KEY_LENGTH) {
            throw new InvalidArgumentException(
                'AES-256 key must be exactly 32 bytes.'
            );
        }

        if (strlen($nonce) !== self::NONCE_LENGTH) {
            throw new InvalidArgumentException(
                'Invalid AES-GCM nonce length.'
            );
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw new InvalidArgumentException(
                'Invalid AES-GCM authentication tag length.'
            );
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption/authentication failed.'
            );
        }

        return $plaintext;
    }
}
```

Example:

``` php
$crypto = new S3_CryptoService();

$dek = $crypto->generateDek();

$result = $crypto->encrypt(
    'Confidential document data',
    $dek
);

$plaintext = $crypto->decrypt(
    $result['ciphertext'],
    $dek,
    $result['nonce'],
    $result['tag']
);

echo $plaintext;
```

This example does **not** implement KEK wrapping. In a production
architecture, the generated DEK must be wrapped/protected before being
stored.

------------------------------------------------------------------------

# 35. Binary vs Base64 Storage

Cryptographic values are binary.

For binary-capable database columns, values can be stored directly using
`BLOB` / `VARBINARY`.

For JSON, configuration-like containers, or text-only transport, Base64
is convenient.

Example:

``` php
$record = [
    'nonce' => base64_encode($nonce),
    'tag' => base64_encode($tag),
    'ciphertext' => base64_encode($ciphertext),
];
```

Restore:

``` php
$nonce = base64_decode($record['nonce'], true);
$tag = base64_decode($record['tag'], true);
$ciphertext = base64_decode($record['ciphertext'], true);
```

Check decoding failures when accepting external data.

Base64 is **not encryption**.

It only converts binary data into text.

------------------------------------------------------------------------

# 36. Key IDs

Instead of assuming that one KEK will exist forever, store a `key_id`.

Example:

``` text
document_id:     1001
encrypted_dek:   ...
key_id:          document-kek-2026-01
```

Later:

``` text
document_id:     2001
encrypted_dek:   ...
key_id:          document-kek-2027-01
```

The application can determine which KEK is required to unwrap a
particular DEK.

This is important for key rotation.

------------------------------------------------------------------------

# 37. KEK Rotation

Suppose documents contain DEKs wrapped with:

``` text
KEK-2026
```

You introduce:

``` text
KEK-2027
```

A well-designed envelope-encryption system can:

``` text
Old Encrypted DEK
       |
       v
Unwrap using KEK-2026
       |
       v
DEK
       |
       v
Wrap using KEK-2027
       |
       v
New Encrypted DEK
```

The large PDF does not necessarily need to be decrypted and re-encrypted
merely to rotate the KEK.

That is one major advantage of envelope encryption.

------------------------------------------------------------------------

# 38. Recommended Security Rules

## Rule 1

Use a cryptographically secure random DEK.

``` php
$dek = random_bytes(32);
```

## Rule 2

Use a fresh nonce for each AES-GCM encryption.

``` php
$nonce = random_bytes(12);
```

## Rule 3

Never reuse the same nonce with the same AES-GCM key.

## Rule 4

Always verify the authentication tag.

## Rule 5

If authentication fails, reject the entire encrypted object.

## Rule 6

Do not hard-code production encryption keys.

## Rule 7

Do not commit production keys to Git.

## Rule 8

Do not store plaintext DEKs beside ciphertext.

## Rule 9

Protect DEKs using a KEK or equivalent key-management mechanism.

## Rule 10

Keep the KEK separate from ordinary application data where practical.

## Rule 11

Use a different random DEK per document/object where appropriate.

## Rule 12

Store an algorithm/version identifier with encrypted data.

## Rule 13

Plan for key rotation.

## Rule 14

Avoid permanently writing decrypted files to disk unless explicitly
required and protected.

## Rule 15

Minimize the lifetime of plaintext keys and plaintext documents in
application processing.

------------------------------------------------------------------------

# 39. Recommended Architecture for a ZF1 Application

A clean separation could be:

``` text
ZF1 Application
|
+-- DocumentService
|
+-- CryptoService
|      |
|      +-- AES-256-GCM encrypt/decrypt
|
+-- KeyService
|      |
|      +-- Generate DEK
|      +-- Wrap DEK
|      +-- Unwrap DEK
|      +-- Resolve KEK by key_id
|
+-- DocumentRepository
       |
       +-- MariaDB metadata
       +-- encrypted file path
```

Application business logic should not directly manipulate raw
cryptographic primitives throughout controllers.

Prefer:

``` php
$documentService->storeEncryptedDocument($file);
```

instead of spreading calls such as:

``` php
openssl_encrypt(...);
openssl_decrypt(...);
```

through many controllers.

This centralizes security-sensitive behavior.

------------------------------------------------------------------------

# 40. Final Architecture

The recommended high-level model is:

``` text
                    APPLICATION
                         |
                         v
                 Generate Random DEK
                         |
                         v
                +----------------+
                | AES-256-GCM    |
                +----------------+
                  |            |
                  v            v
             Ciphertext       Tag
                  |
                  +---- Nonce
                  |
                  v
             Encrypted File


                    DEK
                     |
                     v
              +-------------+
              | KEK / KMS   |
              +-------------+
                     |
                     v
               Encrypted DEK
                     |
                     v
                  MariaDB
```

For decryption:

``` text
MariaDB
   |
Encrypted DEK
   |
   v
KEK / KMS
   |
   v
DEK
   |
   +-------------------+
                       |
Encrypted File         |
   |                   |
   +-- Nonce           |
   +-- Tag             |
   +-- Ciphertext      |
          |            |
          +-----+------+
                |
                v
          AES-256-GCM
                |
                v
        Verify Authentication
                |
          +-----+-----+
          |           |
        Valid       Invalid
          |           |
          v           v
      Plaintext      Reject
```

------------------------------------------------------------------------

# 41. Quick Reference

  ----------------------------------------------------------------------------------
  Item             Purpose                        Secret? Typical Storage
  ---------------- ---------------- --------------------- --------------------------
  DEK              Encrypt actual                     Yes Store only in
                   document                               wrapped/encrypted form

  KEK              Protect DEKs                       Yes Separate protected key
                                                          system/storage

  Nonce / IV       Make encryption                     No Encrypted file or DB
                   invocation                             
                   unique                                 

  Authentication   Detect tampering                    No Encrypted file or DB
  Tag              / authenticate                         
                   ciphertext                             

  Ciphertext       Encrypted                           No Filesystem / object
                   document                               storage / DB

  `key_id`         Identify                            No Database
                   KEK/version                            

  Salt             Support key                         No Alongside
                   derivation where                       derived/encrypted data
                   required                               

  KDF              Derive                             N/A Algorithm/configuration,
                   cryptographic                          not a secret
                   keys                                   
  ----------------------------------------------------------------------------------

------------------------------------------------------------------------

# 42. Summary

For secure file encryption, the core model is:

``` text
Random per-file DEK
       |
       v
AES-256-GCM
       |
       +--> Ciphertext
       +--> Nonce
       +--> Authentication Tag
```

Then:

``` text
DEK
 |
 v
Protected by KEK
 |
 v
Encrypted DEK
 |
 v
MariaDB
```

The most important principles are:

> **AES protects confidentiality.**

> **The authentication tag detects unauthorized modification.**

> **The nonce makes each AES-GCM encryption invocation unique and must
> not be reused with the same key.**

> **The DEK encrypts the document.**

> **The KEK protects the DEK.**

> **The KEK must receive stronger and separate protection from ordinary
> application data.**

This DEK/KEK envelope-encryption model provides a clean foundation for
secure encrypted-document storage in a PHP 7.4 / Zend Framework 1
application.
