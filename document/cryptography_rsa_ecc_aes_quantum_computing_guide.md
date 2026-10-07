# Cryptography, RSA, ECC, AES, and Quantum Computing

## 1. Purpose

This guide explains the relationship between **AES, RSA, ECC, quantum
computing, and post-quantum cryptography (PQC)**. It is intended as a
practical reference for application developers, including PHP 7.4
developers.

The central idea is:

``` text
AES       -> symmetric bulk-data encryption
RSA       -> traditional public-key cryptography/signatures
ECC       -> efficient traditional public-key cryptography
ML-KEM    -> post-quantum key establishment
ML-DSA    -> post-quantum signatures
SLH-DSA   -> post-quantum hash-based signatures
```

These algorithms have different jobs. A secure system normally combines
several cryptographic tools rather than choosing one algorithm for
everything.

------------------------------------------------------------------------

## 2. Symmetric vs Asymmetric Cryptography

### Symmetric encryption

Symmetric encryption uses the same secret key for encryption and
decryption.

``` text
                 Secret Key K
                     |
             +-------+-------+
             |               |
             v               v
          Encrypt         Decrypt
             |               |
Plaintext -> Ciphertext -> Plaintext
```

Both sides must protect the same secret.

### Asymmetric cryptography

Public-key cryptography uses a key pair:

``` text
Public Key
Private Key
```

The public key can be distributed. The private key must remain secret.

Public-key systems provide capabilities such as:

-   key establishment
-   digital signatures
-   authentication
-   certificates and PKI

RSA and ECC are major traditional public-key families.

------------------------------------------------------------------------

# Part I --- AES

## 3. What Is AES?

AES means **Advanced Encryption Standard**.

Common key sizes are:

``` text
AES-128
AES-192
AES-256
```

AES is designed for fast bulk encryption and is widely used for:

-   files
-   database fields
-   backups
-   network traffic
-   application data
-   encrypted storage
-   VPN/TLS session data

AES is a symmetric algorithm:

``` text
Ciphertext = AES_Encrypt(secretKey, plaintext)

Plaintext  = AES_Decrypt(secretKey, ciphertext)
```

Anyone who possesses the secret key can normally decrypt the protected
data.

------------------------------------------------------------------------

## 4. Why AES Is Good for Large Data

AES is efficient for large amounts of information:

``` text
1 KB
10 MB
1 GB
100 GB
```

Modern CPUs often provide hardware acceleration for AES.

This makes AES much more appropriate for bulk encryption than RSA or
ECC.

------------------------------------------------------------------------

## 5. Why AES Cannot Simply Be a Public-Key Algorithm

AES has one secret key.

If we tried:

``` text
Public AES key = K
```

then everyone would know `K` and could decrypt the ciphertext.

Public-key cryptography requires a mathematical separation between
public and private operations.

Therefore:

``` text
AES != public-key cryptography
```

AES and RSA/ECC solve different problems.

------------------------------------------------------------------------

# Part II --- RSA

## 6. What Is RSA?

RSA is a public-key cryptosystem introduced by Ron Rivest, Adi Shamir,
and Leonard Adleman.

Its security relies on the practical difficulty, for classical
computers, of factoring appropriately generated very large integers.

A highly simplified idea is:

``` text
Choose large primes:

p
q

Calculate:

n = p * q
```

The public key contains information related to `n`. The private key
depends on secret information derived from the original primes.

For suitable parameters, recovering the private information from the
public key is computationally impractical using known classical methods.

------------------------------------------------------------------------

## 7. RSA Key Pair

``` text
RSA
 |
 +---- Public Key
 |       |
 |       +---- may be distributed
 |
 +---- Private Key
         |
         +---- must remain secret
```

RSA has historically been used for:

-   digital signatures
-   certificates
-   PKI
-   authentication
-   legacy key transport/encryption schemes

Modern RSA signatures should use standardized constructions such as
RSA-PSS. If RSA encryption is required, secure standardized padding such
as RSA-OAEP is necessary; textbook RSA must not be used.

------------------------------------------------------------------------

## 8. Do Not Encrypt Large Files Directly with RSA

Suppose you have:

``` text
1 GB PDF
```

Do not design:

``` text
1 GB PDF
   |
   v
RSA encryption
```

Instead use hybrid encryption:

``` text
1 GB PDF
   |
   v
AES-256
   |
   v
Encrypted PDF

AES key
   |
   v
public-key/key-establishment mechanism
   |
   v
Protected key material
```

AES handles the large data. Public-key cryptography handles identity/key
establishment/key protection.

------------------------------------------------------------------------

# Part III --- ECC

## 9. What Is ECC?

ECC means **Elliptic Curve Cryptography**.

ECC is a family of public-key techniques based on mathematical problems
involving elliptic curves over finite fields.

Technologies include:

``` text
ECDH
ECDSA
EdDSA
X25519
```

They have different purposes, such as key agreement and digital
signatures.

------------------------------------------------------------------------

## 10. ECC vs RSA

ECC can provide strong classical security with much smaller keys than
RSA.

A commonly cited rough comparison is:

``` text
~3072-bit RSA
      ≈
~256-bit ECC
```

This indicates broadly comparable classical security strength, not
mathematical equivalence.

Smaller ECC keys can provide benefits such as:

-   smaller certificates
-   smaller protocol messages
-   lower storage requirements
-   efficient public-key operations

------------------------------------------------------------------------

## 11. ECC vs AES

Do not ask:

``` text
Should I encrypt my file with ECC or AES?
```

as though they serve the same role.

For bulk data:

``` text
AES -> appropriate
```

ECC is generally used for:

``` text
key agreement
signatures
authentication
```

A conventional hybrid design can therefore look like:

``` text
ECC key agreement
       |
       v
Shared secret
       |
       v
Key derivation
       |
       v
AES key
       |
       v
AES encrypted data
```

------------------------------------------------------------------------

# Part IV --- Hybrid Cryptography

## 12. Why Modern Systems Combine Algorithms

Symmetric encryption is fast but requires a shared secret.

Public-key cryptography helps establish/protect keys and authenticate
parties but is not appropriate for large-scale bulk encryption.

Therefore modern systems combine them:

``` text
Public-key mechanism
        |
        v
establish/protect secret
        |
        v
Symmetric key
        |
        v
AES
        |
        v
Large encrypted data
```

This is called a **hybrid cryptosystem**.

------------------------------------------------------------------------

# Part V --- Quantum Computing

## 13. What Is a Quantum Computer?

Classical computers operate with bits:

``` text
0
1
```

Quantum computers use quantum bits, or **qubits**.

Quantum computation makes use of effects including:

-   superposition
-   interference
-   entanglement

A quantum computer does not simply try every possible answer and read
all answers simultaneously. Quantum algorithms manipulate amplitudes so
useful results can be obtained with high probability.

------------------------------------------------------------------------

## 14. Why Quantum Computing Matters to Cryptography

Two important quantum algorithms are:

``` text
Shor's algorithm
Grover's algorithm
```

They affect cryptography very differently.

------------------------------------------------------------------------

# Part VI --- Shor's Algorithm

## 15. RSA and Shor's Algorithm

RSA relies on the classical difficulty of integer factorization.

A sufficiently capable fault-tolerant quantum computer using Shor's
algorithm could solve the relevant factorization problem efficiently
enough to destroy the security assumption underlying RSA.

``` text
RSA
 |
 v
Integer factorization problem
 |
 v
Shor's algorithm
 |
 v
RSA fundamentally threatened
```

Increasing the RSA key size indefinitely is not considered the long-term
post-quantum solution.

------------------------------------------------------------------------

## 16. ECC and Shor's Algorithm

ECC relies on elliptic-curve discrete-logarithm problems.

Shor's algorithm also applies to discrete logarithms.

Therefore:

``` text
ECC
 |
 v
Shor's algorithm
 |
 v
ECC fundamentally threatened
```

So both traditional public-key families face the same high-level
migration problem:

``` text
RSA -> migrate for PQ security
ECC -> migrate for PQ security
```

ECC's smaller keys do not make it quantum-safe.

------------------------------------------------------------------------

# Part VII --- Grover's Algorithm and AES

## 17. AES Is Affected Differently

AES does not depend on factoring or discrete logarithms.

Shor's algorithm therefore does not directly break AES.

A generic quantum search algorithm such as Grover's algorithm gives a
quadratic speedup for brute-force key search.

A simplified security intuition is:

``` text
Classical brute-force:
2^n

Idealized Grover search:
~2^(n/2)
```

Therefore:

``` text
AES-128

classical scale: ~2^128
quantum-search scale: ~2^64
```

and:

``` text
AES-256

classical scale: ~2^256
quantum-search scale: ~2^128
```

Actual quantum attack costs are considerably more complicated, but this
simplified model explains why AES-256 provides a large
symmetric-security margin for post-quantum planning.

------------------------------------------------------------------------

## 18. Quantum Comparison

  -----------------------------------------------------------------------------
  Algorithm         Category          Main quantum      Response
                                      concern           
  ----------------- ----------------- ----------------- -----------------------
  RSA               Public-key        Shor              Migrate

  ECC               Public-key        Shor              Migrate

  AES-128           Symmetric         Grover-style      Assess security
                                      search            lifetime/requirements

  AES-256           Symmetric         Grover-style      Strong symmetric choice
                                      search            
  -----------------------------------------------------------------------------

The important distinction is:

``` text
RSA/ECC
    -> underlying public-key hardness assumption breaks

AES
    -> generic key-search security margin is reduced
```

------------------------------------------------------------------------

# Part VIII --- Post-Quantum Cryptography

## 19. What Is PQC?

Post-quantum cryptography is designed to run on ordinary computers while
resisting attacks from both classical and quantum computers, according
to current cryptanalytic knowledge.

It does not require quantum hardware.

``` text
Ordinary CPU
    |
    v
Post-quantum algorithm
```

------------------------------------------------------------------------

## 20. Important NIST Post-Quantum Algorithms

Important standardized post-quantum technologies include:

``` text
ML-KEM
ML-DSA
SLH-DSA
```

### ML-KEM

ML-KEM is a key-encapsulation mechanism.

Its purpose is to establish a shared secret.

``` text
Public key
    |
    v
ML-KEM encapsulation
    |
    +---- KEM ciphertext
    |
    +---- Shared secret
```

The recipient uses the private key and KEM ciphertext to recover the
corresponding shared secret.

### ML-DSA

ML-DSA is a post-quantum digital-signature algorithm.

### SLH-DSA

SLH-DSA is a stateless hash-based post-quantum digital-signature
algorithm.

Do not confuse these roles:

``` text
ML-KEM   -> key establishment
ML-DSA   -> signatures
SLH-DSA  -> signatures
```

------------------------------------------------------------------------

# Part IX --- Post-Quantum Hybrid Encryption

## 21. ML-KEM + HKDF + AES-256-GCM

A useful conceptual architecture is:

``` text
ML-KEM
   |
   v
Shared Secret
   |
   v
HKDF-SHA-256
   |
   v
AES-256 Key
   |
   v
AES-256-GCM
   |
   v
Encrypted application data
```

Each component has a distinct job.

### ML-KEM

Establishes a post-quantum shared secret.

### HKDF

Derives application-specific key material from the shared secret.

### AES-256-GCM

Provides efficient authenticated encryption for the actual data.

------------------------------------------------------------------------

# Part X --- AES-GCM

## 22. Authenticated Encryption

Encryption should generally protect both:

``` text
Confidentiality
+
Integrity/authenticity
```

AES-GCM is an authenticated-encryption mode.

Conceptually:

``` text
Plaintext
   |
   + AES key
   + nonce
   v
AES-256-GCM
   |
   +---- Ciphertext
   |
   +---- Authentication tag
```

If ciphertext is modified, authentication should fail when GCM is
correctly used.

------------------------------------------------------------------------

## 23. GCM Nonce

A common AES-GCM nonce size is:

``` text
12 bytes / 96 bits
```

The critical rule is:

> Never reuse the same nonce with the same AES-GCM key.

Nonce reuse with the same key can catastrophically compromise GCM
security.

------------------------------------------------------------------------

# Part XI --- PHP 7.4 AES-256-GCM Example

## 24. Encryption

``` php
<?php

function encryptData(
    string $plaintext,
    string $key
): array {
    if (strlen($key) !== 32) {
        throw new InvalidArgumentException(
            'AES-256 key must be exactly 32 bytes.'
        );
    }

    $nonce = random_bytes(12);
    $tag = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    if ($ciphertext === false) {
        throw new RuntimeException(
            'Encryption failed.'
        );
    }

    return [
        'nonce'      => $nonce,
        'tag'        => $tag,
        'ciphertext' => $ciphertext
    ];
}
```

## 25. Decryption

``` php
function decryptData(
    string $ciphertext,
    string $key,
    string $nonce,
    string $tag
): string {
    if (strlen($key) !== 32) {
        throw new InvalidArgumentException(
            'AES-256 key must be exactly 32 bytes.'
        );
    }

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
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
```

A real system also needs a defined encrypted-envelope format, secure key
management, access controls, rotation/versioning, backup procedures, and
careful error handling.

------------------------------------------------------------------------

# Part XII --- Envelope Encryption

## 26. DEK and KEK

Large systems often distinguish:

``` text
DEK = Data Encryption Key
KEK = Key Encryption Key
```

The DEK encrypts actual application data:

``` text
File
 |
 v
DEK
 |
 v
AES-256-GCM
 |
 v
Encrypted File
```

The DEK is protected separately:

``` text
DEK
 |
 v
KEK / KEM / key-management mechanism
 |
 v
Protected DEK
```

This is called **envelope encryption**.

------------------------------------------------------------------------

## 27. Why Envelope Encryption Helps

Suppose there are one million files.

A good architecture can use independent DEKs:

``` text
File 1 -> DEK 1
File 2 -> DEK 2
File 3 -> DEK 3
...
```

Higher-level key management protects those DEKs.

This separates:

``` text
bulk data encryption
```

from:

``` text
key protection and rotation
```

and can make large-scale key management more practical.

------------------------------------------------------------------------

# Part XIII --- Digital Signatures

## 28. Encryption Is Not a Signature

Encryption asks:

``` text
Can an unauthorized person read this?
```

A digital signature helps answer:

``` text
Who signed this?

Has the signed data changed?
```

Therefore:

``` text
Encryption != Digital Signature
```

Traditional signature technologies include:

``` text
RSA-PSS
ECDSA
EdDSA
```

Post-quantum signature technologies include:

``` text
ML-DSA
SLH-DSA
```

------------------------------------------------------------------------

# Part XIV --- Hash Functions

## 29. Hashing Is Not Encryption

SHA-256 is a cryptographic hash function.

``` text
Document
   |
   v
SHA-256
   |
   v
256-bit digest
```

A hash is designed to be one-way.

There is no normal operation:

``` text
SHA-256 digest
     |
     v
decrypt
```

Hashing, encryption, and digital signatures are separate cryptographic
concepts.

------------------------------------------------------------------------

# Part XV --- Passwords

## 30. Do Not Store Passwords with AES/RSA/SHA-256

Passwords should normally use a dedicated password-hashing function.

PHP 7.4:

``` php
$hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);
```

Verification:

``` php
if (password_verify($password, $hash)) {
    // Password is correct.
}
```

Do not design password storage as:

``` text
AES(password)
RSA(password)
plain SHA-256(password)
```

Use the language/platform's dedicated password-hashing API and keep its
parameters upgradeable.

------------------------------------------------------------------------

# Part XVI --- Harvest Now, Decrypt Later

## 31. Long-Term Confidentiality

An important quantum threat model is commonly described as:

**Harvest now, decrypt later.**

``` text
Today
 |
 +---- attacker captures encrypted data
 |
 +---- stores ciphertext
 |
 v
Future
 |
 +---- cryptographically relevant
 |     quantum computer becomes available
 |
 v
Attack historical ciphertext
```

Therefore the question is not merely whether such quantum computers
exist today.

If information must remain confidential for many years, migration
planning must also consider whether today's encrypted traffic/data could
be collected and attacked later.

------------------------------------------------------------------------

# Part XVII --- Migration Strategy

## 32. Practical Migration

A reasonable high-level process is:

``` text
1. Inventory existing cryptography
          |
          v
2. Find RSA/ECC dependencies
          |
          v
3. Classify data by confidentiality lifetime
          |
          v
4. Introduce crypto agility
          |
          v
5. Maintain strong symmetric encryption
          |
          v
6. Test standardized PQC implementations
          |
          v
7. Migrate protocols/certificates when appropriate
```

Do not replace cryptographic infrastructure with custom experimental
algorithms.

Use standardized, well-reviewed implementations.

------------------------------------------------------------------------

# Part XVIII --- Crypto Agility

## 33. Avoid Hard-Coding Algorithms Everywhere

Bad architecture:

``` text
Controller
  -> direct RSA calls

Model
  -> direct RSA calls

Service A
  -> direct RSA calls

Service B
  -> direct RSA calls
```

Migration becomes difficult.

Prefer:

``` text
Business Application
        |
        v
Crypto Service Layer
        |
        +---- key establishment
        +---- encryption
        +---- signatures
        +---- hashing
```

Then the implementation can evolve without rewriting business logic.

------------------------------------------------------------------------

## 34. Algorithm/Format Versioning

Encrypted data should normally contain or be associated with metadata
identifying its format.

For example:

``` json
{
    "version": 1,
    "encryption": "AES-256-GCM",
    "key_method": "ML-KEM",
    "kdf": "HKDF-SHA-256"
}
```

Versioning is important because data encrypted today may need to be
decrypted years later.

------------------------------------------------------------------------

# Part XIX --- Comparison Table

## 35. AES vs RSA vs ECC vs ML-KEM

  ---------------------------------------------------------------------------------
  Property            AES            RSA             ECC            ML-KEM
  ------------------- -------------- --------------- -------------- ---------------
  Category            Symmetric      Public-key      Public-key     PQ KEM

  Public/private keys No             Yes             Yes            Yes

  Bulk encryption     Excellent      No              No             No

  Key-establishment   Requires       Legacy          Classical key  Yes
  role                shared key     constructions   agreement      

  Digital signatures  No             Yes             Yes            No

  Main quantum        Grover         Shor            Shor           Designed for PQ
  concern                                                           security

  Long-term role      Data           Migration       Migration      PQ key
                      encryption     needed          needed         establishment
  ---------------------------------------------------------------------------------

For post-quantum signatures, use an appropriate signature scheme such as
ML-DSA or SLH-DSA rather than ML-KEM.

------------------------------------------------------------------------

# Part XX --- Easy Mental Model

## 36. Different Tools

Think of the algorithms as different tools:

``` text
AES
=
very fast secure bulk-data protection,
provided both sides have the secret key


RSA / ECC
=
traditional public/private-key tools
for key establishment, signatures,
authentication and PKI


ML-KEM
=
post-quantum key-establishment tool


ML-DSA / SLH-DSA
=
post-quantum signature tools
```

The correct question is not:

``` text
Which single algorithm is best?
```

It is:

``` text
What cryptographic function
does this part of the system need?
```

------------------------------------------------------------------------

# Part XXI --- Recommended Conceptual Model

## 37. Traditional Model

``` text
              Public-key mechanism
                       |
                       v
              establish/protect key
                       |
                       v
                   AES key
                       |
                       v
                  AES-GCM
                       |
                       v
                Application Data
```

Historically, the public-key part may involve RSA or ECC-based
technologies.

------------------------------------------------------------------------

## 38. Post-Quantum-Oriented Model

``` text
                  ML-KEM
                     |
                     v
               Shared Secret
                     |
                     v
               HKDF-SHA-256
                     |
                     v
               AES-256 Key
                     |
                     v
               AES-256-GCM
                     |
                     v
             Application Data
```

Signatures are handled separately:

``` text
ML-DSA / SLH-DSA
        |
        v
Digital Signature
```

Exact choices should follow applicable standards, protocols, compliance
requirements, and mature library support.

------------------------------------------------------------------------

# Part XXII --- Key Takeaways

## 39. AES

``` text
Symmetric cryptography
Very fast
Excellent for bulk encryption
AES-256 offers a large margin
against generic quantum key search
```

## 40. RSA

``` text
Traditional public-key cryptography
Historically important for PKI/signatures
Not for bulk data encryption
Fundamentally threatened by Shor's algorithm
Requires post-quantum migration planning
```

## 41. ECC

``` text
Traditional public-key cryptography
Much smaller keys than RSA at
comparable classical security levels
Excellent classical key agreement/signatures
Also fundamentally threatened by Shor
```

## 42. Post-Quantum Cryptography

``` text
Runs on ordinary computers

ML-KEM
 -> key establishment

ML-DSA
 -> signatures

SLH-DSA
 -> signatures
```

## 43. Core Post-Quantum Encryption Pattern

``` text
ML-KEM
   |
   v
Shared Secret
   |
   v
HKDF
   |
   v
AES-256-GCM
   |
   v
Encrypted Data
```

------------------------------------------------------------------------

# 44. Final Perspective

Quantum computing does not mean that all cryptography becomes useless.

Instead, it changes which security assumptions remain appropriate:

``` text
RSA
 -> plan migration

ECC
 -> plan migration

AES-256
 -> remains a strong symmetric choice
    under current understanding

PQC
 -> provides replacements for vulnerable
    public-key functions
```

For long-lived application architecture, one of the most valuable design
principles is **crypto agility**.

Keep application business logic independent of specific algorithms so
that RSA/ECC-era components can be migrated to standardized post-quantum
mechanisms without rewriting the entire application.
