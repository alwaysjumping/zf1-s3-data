# Checksum --- Detailed Practical Guide

## 1. Introduction

A **checksum** is a value calculated from data so that the data can
later be checked for changes or corruption.

``` text
Original Data -> Checksum Algorithm -> Checksum Value
```

Later:

``` text
Received/Stored Data -> Calculate Again -> Compare
```

If the values differ, the data has changed.

The central distinction is:

``` text
Checksum           -> primarily error detection
Cryptographic Hash -> cryptographic fingerprint
HMAC               -> shared-secret authentication
Digital Signature  -> public-key signature/authentication
Encryption         -> confidentiality
```

People often casually call a SHA-256 digest a "checksum", especially for
file downloads. Technically, SHA-256 is a cryptographic hash, while
mechanisms such as CRC are designed primarily for error detection.

## 2. Why Checksums Are Useful

Checksums help detect accidental changes caused by network errors,
storage corruption, incomplete transfers, damaged archives, hardware
problems, memory errors, or software bugs.

``` text
Server A -> File Transfer -> Server B
```

Calculate a value before and after transfer:

``` text
Original checksum == New checksum
    -> verification passed

Original checksum != New checksum
    -> data definitely differs
```

## 3. Simple Checksum Example

Imagine a primitive checksum that adds numbers:

``` text
10 + 20 + 30 + 40 = 100
```

If one value changes:

``` text
10 + 20 + 31 + 40 = 101
```

the mismatch detects the change.

But different data can produce the same value:

``` text
10 + 20 + 30 + 40 = 100
11 + 19 + 30 + 40 = 100
```

This is a **collision**. Real checksum algorithms are more
sophisticated, but every fixed-size checksum has a limited output space.

## 4. Common Checksum/Error-Detection Algorithms

Common mechanisms include:

-   simple additive checksums
-   CRC-16
-   CRC-32
-   Adler-32
-   Fletcher checksums

Cryptographic hashes are also commonly presented as file "checksums":

-   MD5
-   SHA-1
-   SHA-256
-   SHA-512

A useful distinction is:

``` text
Checksum
    -> broad error/change-detection idea

CRC
    -> error-detection code optimized for accidental corruption

Cryptographic Hash
    -> cryptographic fingerprint with stronger security properties
```

## 5. CRC --- Cyclic Redundancy Check

CRC means **Cyclic Redundancy Check**.

Common variants include CRC-16 and CRC-32.

``` text
Data -> CRC Algorithm -> CRC Value
```

A sender may send data plus a CRC. The receiver calculates the CRC again
and compares it. A mismatch indicates an error.

CRC is designed for efficient accidental-error detection, not
malicious-attacker resistance.

## 6. CRC-32

CRC-32 produces 32 bits:

``` text
32 bits = 4 bytes = 8 hexadecimal characters
```

There are:

``` text
2^32 = 4,294,967,296
```

possible values.

Because there are vastly more possible files than CRC-32 values,
different files must share CRC values. CRC is therefore not a
cryptographic collision-resistant primitive.

## 7. Where CRC Is Used

CRC techniques appear in communication protocols, storage/file formats,
embedded systems, hardware interfaces, Ethernet error detection, and ZIP
archives.

For example, ZIP traditionally stores CRC-32 information for file
entries. During extraction, software can calculate the CRC and detect
corruption.

The purpose is:

``` text
Detect accidental errors efficiently
```

not:

``` text
Authenticate the sender
```

## 8. CRC-32 vs SHA-256

  Property                                         CRC-32    SHA-256
  --------------------------------------------- --------- ----------
  Detect accidental corruption                        Yes        Yes
  Efficient                                           Yes        Yes
  Cryptographic design                                 No        Yes
  Designed for malicious-tampering resistance          No        Yes
  Cryptographic collision resistance                   No        Yes
  Output                                          32 bits   256 bits

Memory rule:

``` text
CRC -> error detection
SHA -> cryptographic hashing
```

## 9. Why CRC Does Not Protect Against an Attacker

Suppose a package contains:

``` text
document.pdf
CRC32(document.pdf)
```

An attacker can modify the document, calculate the CRC of the modified
document, and replace the old CRC.

Therefore CRC does not authenticate the data or sender.

## 10. SHA-256 Alone Does Not Authenticate the Sender Either

Suppose:

``` text
document.pdf
document.sha256
```

If an attacker can replace both, the attacker can modify the document,
calculate a new SHA-256 digest, and replace the expected digest.

SHA-256 has strong cryptographic properties, but a plain digest does not
prove who supplied that digest.

For authentication, use an appropriate mechanism such as HMAC or a
digital signature.

## 11. Checksum, Hash, HMAC and Signature

``` text
Checksum / CRC
    "Was the data accidentally corrupted?"

SHA-256
    "What is the cryptographic fingerprint of these bytes?"

HMAC
    "Was the message authenticated by someone with our shared secret?"

Digital Signature
    "Can I verify that the holder of the private key signed this data?"
```

These are different security properties.

## 12. Checksum Is Not Encryption

A checksum does not hide information:

``` text
Data -> CRC-32 -> Checksum
```

There is no CRC decryption operation.

``` text
Checksum          -> error detection
Hash              -> cryptographic fingerprint
Encryption        -> confidentiality
HMAC              -> shared-secret authentication
Digital Signature -> public-key signature/authentication
```

## 13. Checksum Is Not Compression

A 5 GB file can have a 4-byte CRC-32 value, but the CRC does not contain
the file.

``` text
5 GB File -> CRC-32 -> 4 bytes
                         |
                         X
                  Cannot reconstruct file
```

Compression is reversible; a checksum is not.

## 14. Software Download Verification

Software distributors often publish a SHA-256 digest:

``` text
software.zip
SHA-256: 9f86d081...
```

After downloading:

``` text
Downloaded File -> SHA-256 -> Calculated Digest
                              |
                              v
                         Compare with
                         Expected Digest
```

If they match, the bytes match the bytes associated with that expected
digest.

For protection against an attacker, the expected digest itself must be
obtained through a trustworthy/authenticated mechanism.

## 15. PHP CRC-32

PHP provides:

``` php
$crc = crc32(
    $data
);
```

For a consistent hexadecimal representation:

``` php
$crcHex = hash(
    'crc32b',
    $data
);
```

## 16. PHP SHA-256

``` php
$hash = hash(
    'sha256',
    $data
);
```

Conceptually:

``` text
crc32b -> checksum/error-detection-style use
sha256 -> cryptographic hash
```

## 17. File Checksums and Hashes in PHP

CRC:

``` php
$checksum = hash_file(
    'crc32b',
    $filename
);
```

SHA-256:

``` php
$hash = hash_file(
    'sha256',
    $filename
);
```

For application-level cryptographic file fingerprinting, SHA-256 is
generally preferable to inventing a custom checksum.

## 18. Choosing for File Transfer

If the requirement is:

``` text
Did transmission/storage accidentally corrupt the data?
```

a CRC/checksum may be appropriate in the protocol or format.

If the requirement is:

``` text
Do these bytes match a trusted cryptographic fingerprint?
```

use a modern cryptographic hash such as SHA-256.

If the requirement is:

``` text
Was this package authenticated by a trusted party?
```

a plain checksum or hash is insufficient. Use HMAC for a shared-secret
design or a digital signature for an appropriate public-key design.

## 19. HMAC

HMAC combines a cryptographic hash construction with a secret key:

``` text
Message + Secret Key -> HMAC-SHA-256 -> Authentication Tag
```

PHP:

``` php
$tag = hash_hmac(
    'sha256',
    $message,
    $secretKey
);
```

Verification:

``` php
if (hash_equals(
    $expectedTag,
    $receivedTag
)) {
    // Valid.
}
```

An attacker without the shared secret cannot simply generate a valid new
HMAC.

## 20. Digital Signatures

For public-key authentication:

``` text
Document
   |
Signature Scheme + Private Key
   |
Digital Signature
```

The receiver verifies using the trusted corresponding public key:

``` text
Document + Signature
        |
Trusted Public Key
        |
Verification
   |
Valid / Invalid
```

This solves a different problem from a checksum.

## 21. TCP Checksums

TCP includes a checksum to detect corruption affecting a TCP segment and
relevant IP information.

A TCP checksum is not a cryptographic security feature and is not
intended to prevent deliberate malicious modification. Cryptographic
network protection uses mechanisms such as TLS.

## 22. Ethernet CRC

Ethernet frames use a CRC-based Frame Check Sequence (FCS):

``` text
Ethernet Frame
   +-- Header
   +-- Payload
   +-- FCS (CRC-based)
```

Its purpose is transmission-error detection, not sender authentication.

## 23. Database File Integrity

A PHP application can store a SHA-256 file fingerprint:

``` text
documents
--------------------------------
id
filename
file_path
sha256_hash
created_at
```

Calculate:

``` php
$hash = hash_file(
    'sha256',
    $filePath
);
```

Later:

``` php
$currentHash = hash_file(
    'sha256',
    $filePath
);

if (!hash_equals(
    $storedHash,
    $currentHash
)) {
    // File differs.
}
```

If SHA-256 is used, a name such as `sha256_hash` or `content_hash` is
clearer than a vague `checksum` column.

## 24. Store the Algorithm When Needed

If algorithm migration or multiple algorithms are expected:

``` text
hash_algorithm = sha256
content_hash    = ...
```

Then:

``` php
$actualHash = hash_file(
    $row['hash_algorithm'],
    $filePath
);
```

In security-sensitive applications, the server should control the
permitted algorithm set.

## 25. File Upload Workflow

``` text
Browser
   |
Upload
   |
PHP Temporary File
   |
Calculate SHA-256
   |
Store File
   |
Store Hash in MariaDB
```

PHP:

``` php
$tmpFile =
    $_FILES['document']['tmp_name'];

$hash = hash_file(
    'sha256',
    $tmpFile
);
```

The hash can later be recalculated to check whether the stored bytes
changed.

## 26. Large Files

For large files, use:

``` php
$hash = hash_file(
    'sha256',
    $filename
);
```

rather than loading the entire file into one PHP string merely to hash
it.

Conceptually:

``` text
Open File
   |
Read Chunk
   |
Update Hash
   |
Read Next Chunk
   |
...
   |
Final Digest
```

## 27. Whole-File vs Chunk Hashes

Large transfer systems may calculate hashes per chunk:

``` text
Large File
   +-- Chunk 1 -> Hash 1
   +-- Chunk 2 -> Hash 2
   +-- Chunk 3 -> Hash 3
   +-- Chunk 4 -> Hash 4
```

A mismatching chunk hash identifies the affected block. A final
whole-file hash can also be calculated after reassembly.

## 28. Checksums Detect; They Do Not Normally Repair

A mismatch tells you something is wrong:

``` text
Expected != Actual
```

but a checksum normally does not contain enough information to
reconstruct the original data.

Separate error-correcting codes are designed to recover from certain
errors.

``` text
Checksum / CRC       -> detect errors
Error-Correcting Code -> may detect and correct certain errors
```

## 29. MD5 and SHA-1 "Checksums"

Older sites often say "MD5 checksum" or "SHA-1 checksum". MD5 and SHA-1
are cryptographic hash functions, although both are unsuitable choices
for new collision-sensitive security designs.

For modern cryptographic file fingerprints, SHA-256 is a much better
default.

## 30. Passwords Are Different

Never use CRC for password storage:

``` php
crc32($password);
```

Also do not use plain SHA-256 or SHA-512 as a modern password-storage
scheme:

``` php
hash('sha256', $password);
hash('sha512', $password);
```

Use PHP's password API:

``` php
$passwordHash = password_hash(
    $password,
    PASSWORD_BCRYPT
);
```

or Argon2id where supported:

``` php
$passwordHash = password_hash(
    $password,
    PASSWORD_ARGON2ID
);
```

Verify with:

``` php
password_verify(
    $password,
    $passwordHash
);
```

## 31. Practical Comparison

  Requirement                             Typical Mechanism
  --------------------------------------- ------------------------------------------
  Detect accidental transmission error    CRC/checksum
  Detect accidental file corruption       CRC or hash, depending on system
  Strong cryptographic file fingerprint   SHA-256
  Compare with trusted published digest   SHA-256
  Shared-secret message authentication    HMAC
  Public-key signature/authentication     Digital signature
  Encrypt sensitive data                  Authenticated encryption such as AES-GCM
  Store passwords                         Argon2id/bcrypt

## 32. PHP/ZF1 File Integrity Service

A simple centralized service:

``` php
class S3_FileIntegrity_Service
{
    public function calculateHash(
        $filename
    ) {
        return hash_file(
            'sha256',
            $filename
        );
    }

    public function verify(
        $filename,
        $expectedHash
    ) {
        $actualHash =
            $this->calculateHash(
                $filename
            );

        return hash_equals(
            $expectedHash,
            $actualHash
        );
    }
}
```

Usage:

``` php
$integrity =
    new S3_FileIntegrity_Service();

$hash =
    $integrity->calculateHash(
        '/data/report.pdf'
    );
```

## 33. Secure Package Example

A secure package can use several mechanisms for different jobs:

``` text
                    Original Package
                          |
              +-----------+-----------+
              |                       |
           SHA-256                  AES-GCM
              |                       |
            Digest                Ciphertext
              |
       Digital Signature
```

Responsibilities:

``` text
Checksum / CRC
    -> accidental error detection

SHA-256
    -> cryptographic fingerprint

HMAC
    -> shared-secret authentication

Digital Signature
    -> public-key signature/authentication

AES-GCM
    -> confidentiality + authenticated encryption
```

## 34. The Easiest Way to Remember

``` text
CRC / CHECKSUM
"Did the data accidentally change?"
        |
        v
ERROR DETECTION


SHA-256
"Give me a strong cryptographic fingerprint."
        |
        v
CRYPTOGRAPHIC HASH


HMAC
"Did someone with our shared secret authenticate this?"
        |
        v
MESSAGE AUTHENTICATION


DIGITAL SIGNATURE
"Can I verify that the private-key holder signed this?"
        |
        v
PUBLIC-KEY SIGNATURE


AES / AES-GCM
"Prevent unauthorized people from reading this."
        |
        v
ENCRYPTION
```

## 35. Final Recommendations

For modern PHP applications:

``` text
Protocol/file-format accidental error detection
    -> use the CRC/checksum defined for that protocol or format

Application-level cryptographic file fingerprint
    -> SHA-256

Large-file fingerprint
    -> hash_file('sha256', ...)

Shared-secret authentication
    -> HMAC

Public-key authenticity
    -> established digital-signature scheme

Sensitive-data confidentiality
    -> established authenticated encryption

Password storage
    -> password_hash()
       with Argon2id where supported or
       an appropriate bcrypt configuration
```

The central rule is:

> **First identify what you need to detect or prove, and then choose the
> mechanism designed for that purpose.**

A checksum is excellent for detecting accidental errors. SHA-256
provides a strong cryptographic fingerprint. HMAC and digital signatures
provide authentication, while encryption provides confidentiality. These
mechanisms complement one another rather than replace one another.
