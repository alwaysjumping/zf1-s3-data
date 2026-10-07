# Cryptographic Hash Algorithms --- Detailed Practical Guide

## 1. Purpose

This guide explains cryptographic hash algorithms and how they relate to
SHA-256, SHA-512, passwords, salts, HMAC, AES, ECC, digital signatures,
file integrity, and PHP/ZF1 applications.

The core distinction is:

``` text
SHA        = cryptographic fingerprint
AES        = symmetric encryption
ECC        = public-key cryptography
Argon2id   = password hashing
bcrypt     = password hashing
HMAC       = keyed message authentication
Base64     = encoding
```

These technologies belong to the broader security/cryptography area, but
they solve different problems.

## 2. What Is a Cryptographic Hash?

A cryptographic hash function accepts input of almost any size and
produces a fixed-size output called a hash, digest, hash value, or
message digest.

``` text
Any input
   |
   v
Hash Algorithm
   |
   v
Fixed-size digest
```

Example:

``` text
"Hello"
   |
SHA-256
   |
185f8db32271fe25f561a6fc938b2e264306ec304eda518007d1764826381969
```

The input may be text, JSON, an image, PDF, ZIP, executable, database
content, or a multi-gigabyte file.

The output length depends on the algorithm, not the input size.

  Algorithm     Bits   Bytes   Hex characters
  ----------- ------ ------- ----------------
  SHA-1          160      20               40
  SHA-224        224      28               56
  SHA-256        256      32               64
  SHA-384        384      48               96
  SHA-512        512      64              128

One hexadecimal character represents four bits, so SHA-256 needs 64
hexadecimal characters and SHA-512 needs 128.

## 3. Hashing Is Not Encryption

Encryption is designed to be reversible with the correct key:

``` text
Plaintext
   |
AES + key
   |
Ciphertext
   |
AES + key
   |
Plaintext
```

Hashing is deliberately one-way:

``` text
Original data
   |
SHA-256
   |
Digest
```

There is no `SHA-256 decrypt` operation.

Therefore:

``` text
AES -> encryption/decryption
SHA -> hashing
```

## 4. Deterministic Behavior and Avalanche Effect

The same bytes always produce the same digest:

``` text
"Hello" -> SHA-256 -> Hash A
"Hello" -> SHA-256 -> Hash A
```

A tiny change should produce a dramatically different digest:

``` text
"Hello" -> SHA-256 -> Hash A
"hello" -> SHA-256 -> Hash B
```

This is called the avalanche effect. It is useful for detecting
modifications to data.

## 5. One-Way Does Not Mean an Input Cannot Be Guessed

A SHA digest cannot simply be decoded back into the original input.
However, an attacker can guess inputs:

``` text
Guess "Apple"    -> SHA-256 -> compare
Guess "password" -> SHA-256 -> compare
Guess "Hello"    -> SHA-256 -> MATCH
```

This is not reversing SHA. It is:

``` text
guess + hash + compare
```

This distinction is extremely important for passwords.

## 6. Security Properties

### Preimage Resistance

Given a digest `H`, it should be computationally infeasible to find an
input `X` such that:

``` text
Hash(X) = H
```

### Second-Preimage Resistance

Given an existing message `A`, it should be infeasible to find a
different message `B` such that:

``` text
A != B
Hash(A) = Hash(B)
```

### Collision Resistance

It should be infeasible to find any two different inputs `A` and `B`
such that:

``` text
Hash(A) = Hash(B)
```

Such a pair is called a collision.

Collisions must mathematically exist because the set of possible inputs
is much larger than the fixed set of digest values. The security goal is
to make useful collisions computationally infeasible to find.

For an ideal n-bit hash, generic collision search is roughly on the
order of:

``` text
2^(n/2)
```

Thus an ideal 256-bit hash has roughly 2\^128 generic collision-search
complexity.

## 7. The SHA Family

SHA means Secure Hash Algorithm.

``` text
SHA
 |
 +-- SHA-1
 |
 +-- SHA-2
 |    +-- SHA-224
 |    +-- SHA-256
 |    +-- SHA-384
 |    +-- SHA-512
 |
 +-- SHA-3
      +-- SHA3-224
      +-- SHA3-256
      +-- SHA3-384
      +-- SHA3-512
```

SHA-2 and SHA-3 are different algorithm families even where their digest
sizes have similar names.

### SHA-1

SHA-1 produces a 160-bit digest. It has known practical collision
weaknesses and should not be selected for new collision-sensitive
cryptographic designs. This does not mean SHA-1 has a normal "decrypt"
operation; the major well-known weakness concerns collision resistance.

### SHA-256

SHA-256 belongs to SHA-2 and produces 256 bits (32 bytes).

Common appropriate uses include file fingerprints, integrity
verification, HMAC constructions, digital-signature constructions,
protocol components, and content fingerprints.

PHP:

``` php
$hash = hash(
    'sha256',
    $data
);
```

Binary result:

``` php
$binaryHash = hash(
    'sha256',
    $data,
    true
);
```

### SHA-512

SHA-512 also belongs to SHA-2 and produces 512 bits (64 bytes).

``` php
$hash = hash(
    'sha512',
    $data
);
```

SHA-512 is a strong general-purpose cryptographic hash for appropriate
purposes, but a strong general-purpose hash is not automatically a good
password-storage algorithm.

## 8. Why Plain SHA-512 Is Not Good Password Storage

Suppose a database stores:

``` text
john -> SHA512(password)
```

If an attacker obtains the database, the attacker can rapidly try
candidate passwords:

``` text
"123456"  -> SHA-512 -> compare
"password" -> SHA-512 -> compare
"john123" -> SHA-512 -> compare
```

SHA-512 is intentionally efficient. That is useful for hashing large
files, but it also makes password guessing efficient.

Password hashing has a different goal: each guess should be deliberately
expensive.

Avoid:

``` php
$passwordHash = hash(
    'sha512',
    $password
);
```

and also avoid manually adding a salt to plain SHA-512 as a new password
scheme.

## 9. Password Hashing

Password hashing is specialized. Modern choices include Argon2id and
bcrypt.

With PHP:

``` php
$hash = password_hash(
    $password,
    PASSWORD_BCRYPT
);
```

If the PHP build supports Argon2id:

``` php
$hash = password_hash(
    $password,
    PASSWORD_ARGON2ID
);
```

Verification:

``` php
if (password_verify(
    $password,
    $storedHash
)) {
    // Correct password.
}
```

The password is not decrypted. PHP verifies whether the supplied
password corresponds to the stored password-hash representation.

### Why Password Hashing Is Deliberately Expensive

For a legitimate login, one verification has an acceptable cost. For an
attacker trying huge numbers of guesses, that cost is paid repeatedly.

Argon2id also incorporates memory cost, which helps make massively
parallel cracking more expensive.

## 10. Salt

A salt is a random value used by a password-hashing scheme.

Without unique salts, two users with the same password can produce the
same naive hash:

``` text
User A: MyPassword123 -> same hash
User B: MyPassword123 -> same hash
```

With different salts:

``` text
Password + Salt A -> Password Hash -> Hash A
Password + Salt B -> Password Hash -> Hash B
```

Even though the passwords are equal:

``` text
Hash A != Hash B
```

A salt normally does not need to be secret. Modern password-hash
representations generally encode the salt and algorithm parameters with
the result. With `password_hash()`, normally let PHP generate/manage the
salt rather than inventing your own scheme.

## 11. Do Not Invent Password Hashing

Avoid home-made constructions such as:

``` php
$hash = hash(
    'sha256',
    hash(
        'sha256',
        $password
    )
);
```

Do not manually repeat SHA thousands of times as your own password
design.

Use:

``` php
password_hash()
password_verify()
password_needs_rehash()
```

A useful upgrade flow is:

``` text
Login
 |
password_verify()
 |
success
 |
password_needs_rehash()
 |
yes -> create new hash -> update MariaDB
```

## 12. PBKDF2-HMAC-SHA-512 Is Different from Plain SHA-512

This:

``` text
PBKDF2-HMAC-SHA-512
```

is not equivalent to:

``` text
SHA-512(password)
```

PBKDF2 is a password-based key derivation construction using a salt and
work/iteration factor. For ordinary new PHP password storage,
`password_hash()` with an appropriate Argon2id or bcrypt setup is
generally simpler and less error-prone.

## 13. File Integrity

Hashing is excellent for file fingerprints.

``` text
Original report.pdf
       |
    SHA-256
       |
Expected digest
```

Later:

``` text
Received report.pdf
       |
    SHA-256
       |
Actual digest
```

Compare:

``` text
Expected == Actual
```

If they match, the bytes are overwhelmingly likely to be identical to
the bytes that produced the expected digest. If they differ, the file
changed, was corrupted, or is not the expected file.

PHP can hash files directly:

``` php
$hash = hash_file(
    'sha256',
    '/path/to/report.pdf'
);
```

For large files, this is preferable to loading the entire file into one
PHP string.

Verification:

``` php
$actualHash = hash_file(
    'sha256',
    $filePath
);

if (hash_equals(
    $expectedHash,
    $actualHash
)) {
    echo 'File matches.';
} else {
    echo 'File changed or corrupted.';
}
```

For security against an attacker, the expected digest itself must come
from a trusted/authenticated source.

## 14. A Plain Hash Does Not Prove Who Sent the File

If an attacker can replace both:

``` text
document.pdf
document.sha256
```

the attacker can modify the document, calculate a new SHA-256 digest,
and replace the expected digest.

Therefore a plain hash does not by itself answer:

``` text
Who created this file?
Was the digest supplied by an authentic sender?
```

Use an appropriate authentication mechanism such as HMAC or a digital
signature.

## 15. HMAC

HMAC provides keyed message authentication.

A normal SHA hash has no secret:

``` text
Message -> SHA-256 -> Digest
```

HMAC uses a shared secret:

``` text
Message
  +
Secret key
  |
HMAC-SHA-256
  |
Authentication tag
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

With plain SHA, an attacker who changes `amount=100` to `amount=10000`
can calculate a new plain hash. With HMAC, the attacker cannot generate
the correct authentication tag without the shared secret.

Therefore:

``` text
SHA  -> unkeyed cryptographic fingerprint
HMAC -> integrity/authentication using a shared secret
```

## 16. Digital Signatures

Digital signatures use public-key cryptography.

Simplified concept:

``` text
Document
   |
Hash according to signature scheme
   |
Signature algorithm + private key
   |
Digital signature
```

Verification uses the corresponding public key.

``` text
Received document
       |
Hash/signature verification
       ^
       |
   Public key
```

The exact operation depends on the signature algorithm and standard.

## 17. SHA and ECC

ECC means Elliptic Curve Cryptography. ECC is not a hash algorithm; it
is a family of public-key cryptographic techniques.

A signature system can combine a hash with an elliptic-curve signature
algorithm:

``` text
Document
   |
SHA-256
   |
Digest
   |
ECDSA + ECC private key
   |
Signature
```

Verification uses the public key.

Thus:

``` text
SHA -> hashing
ECC-based signature -> public/private-key signature capability
```

They solve different problems and can work together.

## 18. SHA and AES

AES is symmetric encryption.

``` text
Plaintext
   |
AES + secret key
   |
Ciphertext
```

AES primarily addresses confidentiality: preventing unauthorized people
from reading data.

SHA produces a fixed-size cryptographic fingerprint.

``` text
AES -> confidentiality/encryption
SHA -> hashing/fingerprinting
```

They are not competing algorithms.

## 19. AES-GCM

Modern authenticated-encryption modes such as AES-GCM can provide
confidentiality plus integrity/authentication when used correctly:

``` text
Plaintext
   |
AES-GCM + key + nonce
   |
Ciphertext + authentication tag
```

Do not invent a cryptographic format merely by combining AES and SHA
yourself. Cryptographic composition is subtle; use established
authenticated-encryption constructions and libraries.

## 20. AES, SHA and ECC in One System

A secure document system may use all three categories:

``` text
                    Document
                       |
             +---------+---------+
             |                   |
           SHA-256             AES-GCM
             |                   |
           Digest          Encrypted data
             |
       Digital signature
       using ECC-based
       signature scheme
```

Responsibilities:

``` text
AES -> confidentiality
SHA -> hashing/fingerprint
ECC signature -> public-key authenticity/signature
```

## 21. Encoding Is Not Hashing

Base64 and hexadecimal are encodings.

``` text
Hello
 |
Base64
 |
SGVsbG8=
```

Base64 is reversible:

``` text
SGVsbG8= -> Base64 decode -> Hello
```

SHA is not:

``` text
Hello -> SHA-256 -> digest
```

There is no SHA decode operation.

``` text
Base64 = encoding
SHA-256 = hashing
AES = encryption
```

## 22. Hashing Is Not Compression

Compression is reversible:

``` text
Original -> ZIP -> compressed -> UNZIP -> Original
```

Hashing is not:

``` text
5 GB file -> SHA-256 -> 32-byte digest
                         |
                         X
                Cannot reconstruct file
```

A digest is a fingerprint, not a compressed copy.

## 23. Comparison Table

  -------------------------------------------------------------------------------------------
  Technology     Category        Purpose                         Reversible?             Key?
  -------------- --------------- -------------------------- ---------------- ----------------
  Base64         Encoding        Representation                          Yes               No

  SHA-256        Hash            Fingerprint/integrity                    No               No

  SHA-512        Hash            Fingerprint/integrity                    No               No

  HMAC-SHA-256   MAC             Integrity + shared-secret                No              Yes
                                 authentication                              

  AES            Symmetric       Confidentiality                Yes with key              Yes
                 encryption                                                  

  AES-GCM        Authenticated   Confidentiality +              Yes with key              Yes
                 encryption      authentication/integrity                    

  ECC-based      Public-key      Authenticity/signature        Verification,   Private/public
  signature      signature                                    not decryption             keys

  bcrypt         Password        Password storage                         No        Salt/cost
                 hashing                                                     

  Argon2id       Password        Password storage                         No        Salt/cost
                 hashing                                                           parameters
  -------------------------------------------------------------------------------------------

## 24. Database Hashes

A document table might store:

``` text
id
filename
content_hash
```

Generate:

``` php
$contentHash = hash(
    'sha256',
    $documentContent
);
```

Later, calculate the hash again and compare it to the stored value.

This can detect changes when the stored digest is trustworthy. If an
attacker can modify both the document and its database hash, a plain
hash is not sufficient against malicious tampering. Use an HMAC, digital
signature, or separately protected trusted value where appropriate.

## 25. Password Storage in MariaDB

Example:

``` sql
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;
```

Generate:

``` php
$passwordHash = password_hash(
    $password,
    PASSWORD_BCRYPT
);
```

or Argon2id where supported.

Login:

``` php
if (password_verify(
    $password,
    $row['password_hash']
)) {
    // Authentication succeeded.
}
```

Do not store plaintext passwords and do not use plain
`hash('sha256', $password)` or `hash('sha512', $password)` as the
password-storage scheme.

## 26. ZF1 Hash Service Example

Hashing is a PHP/cryptography concern rather than a ZF1-specific
primitive, but it can be centralized:

``` php
class S3_Hash_Service
{
    public function sha256($data)
    {
        return hash(
            'sha256',
            $data
        );
    }

    public function sha256File($filename)
    {
        return hash_file(
            'sha256',
            $filename
        );
    }

    public function verifyFile(
        $filename,
        $expectedHash
    ) {
        $actualHash =
            $this->sha256File(
                $filename
            );

        return hash_equals(
            $expectedHash,
            $actualHash
        );
    }
}
```

Keep password handling separate because password hashing has different
security requirements.

## 27. Large Files

Avoid loading a huge file completely into memory merely to hash it:

``` php
$data = file_get_contents(
    $hugeFile
);

$hash = hash(
    'sha256',
    $data
);
```

Prefer:

``` php
$hash = hash_file(
    'sha256',
    $hugeFile
);
```

Conceptually:

``` text
Open file
 |
Read chunk
 |
Update hash
 |
Read next chunk
 |
Update hash
 |
...
 |
Finalize digest
```

## 28. Common Mistakes

### Calling SHA Encryption

Incorrect:

``` text
Encrypt the password with SHA-256.
```

Correct terminology:

``` text
Hash data with SHA-256.
```

### Using SHA-512 for Password Storage

Avoid:

``` php
hash('sha512', $password);
```

Use a dedicated password hashing scheme through PHP's password API.

### Assuming Salt Is Secret

Salt normally does not require secrecy. Its role is different from an
encryption key.

### Assuming a Plain Hash Proves the Sender

A plain digest does not authenticate who generated it. Use HMAC or a
digital signature for the appropriate trust model.

### Assuming SHA Can Be Decoded

There is no SHA decoding operation. Guessing predictable inputs is not
the same as reversing SHA.

### Confusing Base64 and SHA

Base64 is reversible encoding. SHA is one-way hashing.

### Inventing Cryptography

Avoid custom combinations such as:

``` text
AES + SHA + custom transformations + Base64
```

as a home-made security protocol. Prefer established constructions and
libraries.

## 29. Practical Decision Guide

``` text
Need a file/data fingerprint?
    -> SHA-256

Need password storage?
    -> Argon2id / bcrypt through password_hash()

Need password verification?
    -> password_verify()

Need shared-secret message authentication?
    -> HMAC

Need confidentiality?
    -> established authenticated encryption such as AES-GCM

Need public-key authenticity/signatures?
    -> established digital-signature scheme

Need text representation of binary data?
    -> Base64 / hexadecimal
```

## 30. Cryptography Map

``` text
                         CRYPTOGRAPHY
                              |
        +---------------------+----------------------+
        |                     |                      |
        v                     v                      v
     Hashing              Symmetric              Public-Key
                           Crypto                  Crypto
        |                     |                      |
 SHA-256/SHA-512             AES                  ECC/RSA
        |                     |                      |
        |                     v                Signatures /
        |              Confidentiality        key establishment
        |
        +-- Fingerprints
        +-- Integrity
        +-- HMAC constructions
        |
        +-- Password hashing is specialized
             |
             +-- Argon2id
             +-- bcrypt
```

The exact capabilities depend on the public-key algorithm/construction;
ECC is a family rather than one single operation.

## 31. Five Questions to Remember

``` text
1. "Has this data changed?"
       -> SHA-256

2. "Can unauthorized people read this data?"
       -> Encryption such as AES-GCM

3. "Who signed this document using public-key cryptography?"
       -> Digital signature, e.g. an ECC-based signature scheme

4. "Is this password correct?"
       -> Argon2id / bcrypt password hashing

5. "Did someone with our shared secret authenticate this message?"
       -> HMAC
```

## 32. Recommended PHP 7.4 Patterns

``` php
// Data fingerprint
$hash = hash(
    'sha256',
    $data
);

// File fingerprint
$fileHash = hash_file(
    'sha256',
    $filename
);

// Password storage
$passwordHash = password_hash(
    $password,
    PASSWORD_BCRYPT
);

// Password verification
$isValid = password_verify(
    $password,
    $passwordHash
);

// HMAC
$mac = hash_hmac(
    'sha256',
    $message,
    $secretKey
);

// Security-sensitive comparison
$isSame = hash_equals(
    $expected,
    $actual
);
```

Where supported by the PHP build:

``` php
$passwordHash = password_hash(
    $password,
    PASSWORD_ARGON2ID
);
```

## 33. Final Summary

``` text
SHA-256 / SHA-512
    -> General cryptographic hashing
    -> Fixed-size fingerprints
    -> One-way
    -> No secret key
    -> Not plain password storage

Argon2id / bcrypt
    -> Password hashing
    -> Salted
    -> Deliberately expensive

HMAC
    -> Shared-secret message authentication

AES
    -> Symmetric encryption
    -> Confidentiality

ECC
    -> Public-key cryptography family
    -> Used in schemes for signatures/key establishment

Base64
    -> Encoding
    -> Reversible
    -> Not cryptographic protection
```

The most important design rule is:

> Choose the cryptographic primitive according to the security property
> you actually need.

Do not ask only, "Which algorithm is strongest?" First identify whether
the requirement is confidentiality, integrity, authentication, digital
signatures, password storage, or a file fingerprint. SHA, AES, ECC,
HMAC, and password-hashing algorithms are different tools rather than
replacements for one another.
