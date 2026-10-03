# Beginner's Guide to Cryptography, PKI, Certificates, and HTTPS

## Purpose

This guide summarizes the cryptography concepts we discussed in
beginner-friendly language. The goal is to understand how the
technologies fit together before studying the mathematics.

## 1. The Big Picture

``` text
Cryptography
|
+-- Symmetric cryptography (AES)
|
+-- Public-key cryptography
|     +-- RSA
|     +-- ECC
|
+-- Certificates (X.509)
|     +-- Certificate Authorities
|     +-- Root CA
|     +-- Intermediate CA
|
+-- PKI
|     +-- issuance
|     +-- validation
|     +-- renewal
|     +-- revocation
|
+-- TLS / HTTPS
|
+-- Private-key storage
      +-- PKCS#12
      +-- Smart card
      +-- USB crypto token
      +-- HSM
```

------------------------------------------------------------------------

## 2. Symmetric Encryption

Symmetric encryption uses the same secret key for encryption and
decryption.

``` text
Plaintext
   |
   | Secret Key
   v
Encrypt
   |
   v
Ciphertext
   |
   | Same Secret Key
   v
Decrypt
   |
   v
Plaintext
```

**AES** is a common symmetric encryption algorithm. Symmetric encryption
is fast, so it is suitable for files, backups, and network traffic.

Its key-management problem is simple to state: both sides need the
secret key, so how do they establish it securely?

------------------------------------------------------------------------

## 3. Public-Key Cryptography

Public-key cryptography uses a mathematically related pair of keys:

``` text
             Key Pair
                |
        +-------+-------+
        |               |
   Public Key       Private Key
    shareable          secret
```

The public key is designed to be distributed. The private key must be
protected.

A useful analogy is a locked mailbox: people can use the public-facing
mechanism to deliver protected information, while only the owner
controls the private key needed for the intended private operation. The
analogy is simplified, but it helps explain why public information can
be shared while the private key remains secret.

Secure algorithms are designed so that deriving the private key from the
public information is computationally infeasible.

------------------------------------------------------------------------

## 4. Encryption and Digital Signatures

These solve different problems.

### Encryption

Encryption is mainly about **confidentiality**:

> Who should be able to read this information?

Conceptually, data can be protected for Bob using Bob's public-key
mechanism, while Bob uses the corresponding private key for the intended
recovery operation.

``` text
Data + Bob's Public Key
          |
          v
       Protect
          |
          v
      Ciphertext
          |
          | Bob's Private Key
          v
       Recover
          |
          v
         Data
```

### Digital signatures

Digital signatures are about **authenticity and integrity**:

> Can I verify who signed this data and whether the signed data changed?

``` text
Document + Private Signing Key
              |
              v
       Signature Algorithm
              |
              v
       Digital Signature
```

Verification uses the corresponding public key:

``` text
Document + Signature + Public Key
              |
              v
            Verify
          /        \
       Valid      Invalid
```

A digital signature should not simply be described as "encrypting with
the private key." Modern signature algorithms have their own
constructions.

------------------------------------------------------------------------

## 5. Private Key vs. Private Signing Key

**Private key** is the general term.

A **private signing key** is a private key intended for creating digital
signatures.

For learning, it is useful to imagine separate key pairs:

``` text
Bob
|
+-- Encryption / key-establishment pair
|     +-- Public Key
|     +-- Private Key
|
+-- Signing pair
      +-- Public Verification Key
      +-- Private Signing Key
```

Separating keys by purpose is often desirable. Some modern algorithms
are explicitly purpose-specific; for example, Ed25519 is for signatures
and X25519 is for key agreement.

------------------------------------------------------------------------

## 6. RSA and ECC

**RSA** is a well-known public-key algorithm. Depending on the scheme,
RSA can be used for digital signatures and for protecting small
cryptographic values.

``` text
RSA Key Pair
+-- Public Key
+-- Private Key
```

**ECC** means Elliptic Curve Cryptography. It is a family of public-key
cryptographic techniques based on elliptic curves.

``` text
Public-Key Cryptography
       /          \
     RSA          ECC
```

Different ECC algorithms/protocols serve different purposes, including
signatures and key agreement.

RSA and ECC are cryptographic technologies; they are not certificate
formats or hardware devices.

------------------------------------------------------------------------

## 7. Hashing

A cryptographic hash function produces a fixed-size digest from data.

``` text
Document
   |
   v
Hash Function
   |
   v
Digest
```

Hashing is not encryption. Normally, a hash is not something you decrypt
back into the original document.

Hashes are important building blocks in digital signatures and many
integrity mechanisms.

------------------------------------------------------------------------

## 8. Hybrid Encryption

Large files are normally not encrypted directly using public-key
cryptography. Practical systems combine public-key and symmetric
cryptography.

Suppose Alice wants to send Bob a PDF.

First, generate a random AES key and encrypt the PDF:

``` text
PDF + Random AES Key
        |
        v
       AES
        |
        v
Encrypted PDF
```

Then protect the AES key for Bob using an appropriate public-key scheme:

``` text
AES Key + Bob's Public Key
          |
          v
 Public-Key Protection
          |
          v
 Protected AES Key
```

Send both:

``` text
+-----------------------+
| Encrypted PDF         |
| Protected AES Key     |
+-----------------------+
```

Bob uses his corresponding private key to recover the AES key, then uses
AES to decrypt the PDF.

This is **hybrid encryption**.

------------------------------------------------------------------------

## 9. Shared Secrets and Session Keys

TLS establishes shared secret material and derives symmetric traffic
keys.

``` text
Browser <---- TLS Handshake ----> Server
                  |
                  v
          Shared Secret Material
                  |
                  v
          Derive Traffic Keys
                  |
                  v
          Encrypt HTTP Data
```

Modern TLS commonly uses ephemeral Diffie-Hellman key agreement such as
ECDHE. Independent TLS connections normally use fresh ephemeral values,
so their resulting shared secret material and traffic keys differ.

``` text
Connection 1 -> Secret A -> Traffic Keys A
Connection 2 -> Secret B -> Traffic Keys B
```

The server certificate may remain the same across many connections. The
certificate/private key is not the symmetric key used to encrypt all
HTTP traffic.

------------------------------------------------------------------------

## 10. X.509 Certificates

A public key alone does not identify its owner.

**X.509** defines a standard structure for public-key certificates.

A simplified certificate contains:

``` text
X.509 Certificate
+------------------------------+
| Subject                      |
| Public Key                   |
| Issuer                       |
| Serial Number                |
| Valid From / Valid Until     |
| Extensions                   |
| Issuer's Digital Signature   |
+------------------------------+
```

A normal public X.509 certificate contains a public key, not the
corresponding private key.

------------------------------------------------------------------------

## 11. Certificate Authorities

A **Certificate Authority (CA)** issues and signs certificates.

``` text
             Company CA
          /      |       \
       Alice    Bob     Web Server
       Cert     Cert       Cert
```

The CA itself has key material:

``` text
Company CA
|
+-- CA Certificate
|     +-- CA Public Key
|
+-- CA Private Signing Key
      +-- HIGHLY SECRET
```

The CA private signing key signs certificates. Parties that trust the CA
can verify those signatures using the CA public key.

------------------------------------------------------------------------

## 12. Root CA and Intermediate CA

A common hierarchy is:

``` text
             Root CA
                |
                | signs
                v
        Intermediate CA
                |
                | signs
                v
      Employee / Server Cert
```

The **Root CA** is the trust anchor. Its private key is extremely
valuable and should be strongly protected and used infrequently.

An **Intermediate CA** handles more routine certificate issuance:

``` text
Root CA
   |
   v
Intermediate CA
   |
   +-- Alice Certificate
   +-- Bob Certificate
   +-- Server Certificate
```

This reduces how often the Root CA private signing key must be used.

------------------------------------------------------------------------

## 13. PKI

**PKI** means **Public Key Infrastructure**.

PKI is the complete organizational and technical system around
certificates and trust.

``` text
PKI
+-- Root CA
+-- Intermediate CAs
+-- Certificates
+-- Public/private keys
+-- Issuance
+-- Validation
+-- Expiration
+-- Renewal
+-- Revocation
+-- Policies
+-- Key protection
+-- Auditing
```

Remember:

``` text
RSA / ECC -> cryptographic technologies
X.509     -> certificate standard
CA        -> certificate issuer
PKI       -> complete trust/certificate-management system
```

------------------------------------------------------------------------

## 14. HTTPS and TLS

**HTTPS = HTTP protected by TLS.**

TLS provides confidentiality, integrity, and authentication.

A simplified connection is:

``` text
Browser                              Server
   |                                   |
   |------ ClientHello --------------->|
   |<----- ServerHello ----------------|
   |<----- Server Certificate ---------|
   |                                   |
   |      Verify Certificate           |
   |                                   |
   |<---- Key Establishment ---------->|
   |                                   |
   |      Derive Traffic Keys          |
   |                                   |
   |====== Encrypted HTTP =============>|
   |<===== Encrypted HTTP ==============|
```

Modern HTTPS does not encrypt every HTTP message directly with the
server's public key. TLS uses certificates/public-key mechanisms for
authentication and key establishment, then uses fast symmetric
encryption for application traffic.

------------------------------------------------------------------------

## 15. Certificate Chains and Browser Trust

Why does a browser trust a website certificate?

A common certificate chain is:

``` text
Trusted Root CA
      |
      | signs
      v
Intermediate CA
      |
      | signs
      v
Server Certificate
```

The browser validates upward:

``` text
example.com
    |
    v
Intermediate CA
    |
    v
Root CA
    |
    v
Already trusted?
    |
   YES
```

The browser also checks the hostname, validity period, certificate
constraints, and other TLS/PKI requirements.

The Root CA is normally already available in a trusted root store rather
than being trusted merely because the server sends it.

------------------------------------------------------------------------

## 16. Adding a Private Root CA on Windows

Suppose a company has:

``` text
Company-Root-CA.crt
```

This should contain the public Root CA certificate, **not its private
key**.

A common Windows flow is:

``` text
Open Company-Root-CA.crt
        |
        v
Install Certificate
        |
        v
Choose Current User or Local Machine
        |
        v
Trusted Root Certification Authorities
```

`Current User` affects the user's certificate stores. `Local Machine` is
machine-level and normally requires administrative control.

For the current user's stores, `certmgr.msc` can be used to inspect
certificates. Administrators can use the Certificates MMC snap-in for
the computer account to inspect machine stores.

In managed organizations, centralized deployment through mechanisms such
as Group Policy or MDM is generally preferable to asking every employee
to install a Root CA manually.

### Critical rule

Distribute:

``` text
Company-Root-CA.crt   YES
```

Never distribute:

``` text
Company-Root-Private.key   NO
```

Installing a Root CA creates a powerful trust relationship, so an
unknown Root CA should never be installed casually.

------------------------------------------------------------------------

## 17. Client Certificates and mTLS

Employees can have individual certificates:

``` text
Company CA
   |
   +-- Alice Certificate
   +-- Bob Certificate
   +-- Carol Certificate
```

Each employee should have their own key pair.

With normal HTTPS, the server presents a certificate. With **mutual TLS
(mTLS)**, the client also presents a certificate and proves possession
of the corresponding private key.

``` text
Employee Browser                  Server
       |                            |
       |<-- Server Certificate -----|
       |                            |
       |--- Client Certificate ---->|
       |                            |
       |   Both sides authenticate  |
```

The employee's private key is not sent to the server.

A server should validate the client certificate chain, validity,
intended use, revocation status as applicable, and the identity/account
mapping.

Certificate authentication answers **who is this?** Application
authorization separately decides **what may this person do?**

------------------------------------------------------------------------

## 18. PKCS#12 (`.p12` / `.pfx`)

PKCS#12 is a container format.

A file such as:

``` text
alice.p12
```

may contain:

``` text
alice.p12
+-- Alice Private Key
+-- Alice Certificate
|     +-- Alice Public Key
+-- CA Certificates / Chain
```

A simple `.crt` certificate normally contains public certificate
information and no private key.

Because `.p12`/`.pfx` files can contain private keys, they must be
treated as sensitive. Password protection helps protect the package, but
private-key handling still requires careful security design.

------------------------------------------------------------------------

## 19. Smart Cards

A smart card contains secure cryptographic hardware.

``` text
+-------------------------+
| Employee Smart Card     |
|                         |
| Secure Crypto Chip      |
+-------------------------+
```

A strong design allows the private key to remain inside the card.

``` text
Computer
   |
   | "Sign this"
   v
Smart Card
   |
   | Private-key operation
   | happens internally
   v
Signature
```

A PIN is commonly required:

``` text
Something you have: Smart card
        +
Something you know: PIN
```

------------------------------------------------------------------------

## 20. USB Cryptographic Security Tokens

A USB cryptographic token is similar in concept to a smart card but uses
a USB form factor.

``` text
USB Crypto Token
+-- Certificate
+-- Private Key
|     +-- ideally non-exportable
+-- Secure cryptographic hardware
```

The computer asks the token to perform an operation; the private key can
remain inside the hardware.

### Important distinction

``` text
Ordinary USB flash drive:
    alice.p12 can be copied

Cryptographic USB token:
    private key can be designed as non-exportable
```

A flash drive containing a `.p12` file is not automatically a
cryptographic security token.

------------------------------------------------------------------------

## 21. HSMs

**HSM** means **Hardware Security Module**.

An HSM is specialized hardware for protecting important cryptographic
keys and performing cryptographic operations.

``` text
+------------------------------+
|             HSM              |
| Protected Private Keys       |
| Crypto Operations            |
| Access Controls              |
+------------------------------+
```

Instead of exporting a CA private key, software asks the HSM to sign:

``` text
Application
    |
    | "Sign this certificate"
    v
HSM
    |
    | Uses private key internally
    v
Signature
```

HSMs are especially relevant for high-value organizational keys such as
CA signing keys.

------------------------------------------------------------------------

## 22. Smart Card vs USB Token vs HSM

  Technology         Simple Mental Model                  Typical Role
  ------------------ ------------------------------------ ---------------------------
  Smart card         Personal secure crypto card          Employee
  USB crypto token   Personal secure USB crypto device    Employee
  HSM                Organizational cryptographic vault   CA/server/high-value keys

Their shared goal is to keep private keys strongly protected and perform
sensitive operations in controlled hardware.

------------------------------------------------------------------------

## 23. Certificate Lifecycle

Certificates require ongoing management.

``` text
Issue
  |
  v
Enroll / Distribute
  |
  v
Use
  |
  v
Renew
  |
  +----> Revoke when necessary
  |
  v
Expire
```

Revocation may be necessary when an employee leaves, a device/token is
lost, a private key may have been compromised, or a certificate was
issued incorrectly.

------------------------------------------------------------------------

## 24. Complete Company Example

Imagine a company PKI:

``` text
Company Root CA
       |
       v
Company Intermediate CA
       |
       +--------------------+
       |                    |
       v                    v
Alice Certificate     Server Certificate
       |                    |
       v                    v
Alice Public Key      Server Public Key
       ^                    ^
       |                    |
Alice Private Key     Server Private Key
       |
       v
USB Crypto Token
```

The Root CA private signing key could be kept offline or protected by
appropriately controlled hardware such as an HSM.

Alice opens the company website:

``` text
Alice                               Server
  |                                   |
  |----------- HTTPS/TLS ------------>|
  |<------ Server Certificate --------|
  |                                   |
  | Verify server chain               |
  |                                   |
  |------- Client Certificate ------->|
  |                                   |
  | Token proves possession of        |
  | Alice's private key               |
  |                                   |
  |<===== Protected TLS Traffic =====>|
```

The application then maps Alice's authenticated certificate identity to
Alice's account and applies roles, permissions, and other authorization
rules.

------------------------------------------------------------------------

## 25. Common Misunderstandings

**"A certificate contains the private key."**\
A normal X.509 certificate does not. A PKCS#12 package can contain one.

**"HTTPS encrypts everything using the server public key."**\
No. Modern TLS derives symmetric traffic keys and uses symmetric
authenticated encryption for application data.

**"A digital signature is just private-key encryption."**\
No. Use the correct signature algorithm and terminology.

**"Employees need the Root CA private key."**\
Absolutely not. They need only the public Root CA certificate when that
private CA must be trusted.

**"A `.p12` is just a certificate."**\
No. It may also contain a private key.

**"A USB flash drive with a `.p12` is the same as a crypto token."**\
No. A real cryptographic token can perform private-key operations
internally and can keep the private key non-exportable.

**"A valid client certificate gives the employee permission to
everything."**\
No. Authentication and authorization are separate.

------------------------------------------------------------------------

## 26. Essential Security Rules

1.  Never distribute a Root CA private key to employees.
2.  Protect CA signing keys extremely carefully.
3.  Give employees unique certificates and keys rather than sharing one
    identity.
4.  Protect employee private keys against export and theft where
    practical.
5.  Consider hardware-backed private keys for higher-security
    environments.
6.  Define expiration, renewal, revocation, loss, and compromise
    procedures.
7.  Keep authentication separate from authorization.
8.  Validate certificate identity and intended usage, not merely the
    presence of a certificate.
9.  Use supported TLS versions, algorithms, and cryptographic libraries.
10. Do not invent custom cryptographic algorithms.

------------------------------------------------------------------------

## 27. Recommended Learning Order

``` text
Symmetric Encryption
        |
        v
Public / Private Keys
        |
        v
Hybrid Encryption
        |
        v
Hashing
        |
        v
Digital Signatures
        |
        v
X.509 Certificates
        |
        v
Certificate Authorities
        |
        v
Certificate Chains
        |
        v
PKI
        |
        v
TLS / HTTPS
        |
        v
Client Certificates / mTLS
        |
        v
PKCS#12
        |
        v
Smart Cards / USB Tokens
        |
        v
HSM and CA Key Protection
```

------------------------------------------------------------------------

## 28. Quick Reference

  -----------------------------------------------------------------------
  Term                                Easy Meaning
  ----------------------------------- -----------------------------------
  AES                                 Fast symmetric encryption algorithm

  Public key                          Shareable part of an asymmetric key
                                      pair

  Private key                         Secret part of an asymmetric key
                                      pair

  RSA                                 Public-key cryptographic algorithm

  ECC                                 Elliptic-curve public-key
                                      cryptography family

  Hash                                Fixed-size digest derived from data

  Digital signature                   Cryptographic signature created
                                      with a signing private key

  X.509                               Standard for public-key
                                      certificates

  CA                                  Entity that issues/signs
                                      certificates

  Root CA                             PKI trust anchor

  Intermediate CA                     CA authorized by another CA

  PKI                                 Infrastructure managing keys,
                                      certificates, trust and lifecycle

  TLS                                 Protocol protecting network
                                      communications

  HTTPS                               HTTP protected by TLS

  mTLS                                TLS where both client and server
                                      authenticate with certificates

  PKCS#12                             Container that can hold private
                                      key, certificate and CA chain

  Smart card                          Personal hardware for protected
                                      cryptographic keys

  USB crypto token                    USB-form cryptographic hardware

  HSM                                 Specialized hardware for high-value
                                      cryptographic keys
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 29. Master Diagram

``` text
                       PKI
                        |
                    Root CA
                        |
                        | signs
                        v
                Intermediate CA
                  /           \
                 v             v
       Server Certificate   User Certificate
              |                  |
              v                  v
        Public Key           Public Key
              ^                  ^
              |                  |
        Private Key          Private Key
                                 |
                      +----------+----------+
                      |                     |
                 Smart Card            USB Token

Root/CA private signing keys
              |
              v
      Strong CA key protection
              |
              v
       HSM / controlled offline
          CA environment


PKCS#12
   |
   +-- can package certificate + private key + CA chain

HTTPS / TLS
   |
   +-- authenticates endpoints and establishes key material
   |
   +-- derives symmetric traffic keys
   |
   +-- protects HTTP communication
```

------------------------------------------------------------------------

## 30. Final Summary

The central ideas are straightforward:

**RSA and ECC** are public-key cryptographic technologies.\
**X.509** defines certificates.\
A **Certificate Authority** issues and signs certificates.\
A **Root CA** is a trust anchor, while an **Intermediate CA** performs
operational issuance.\
**PKI** is the complete system that manages certificates, keys, trust,
renewal, and revocation.\
**TLS** uses cryptography and certificates to protect network
connections; **HTTPS** is HTTP protected by TLS.\
**PKCS#12** can package a certificate and private key.\
**Smart cards and USB cryptographic tokens** can protect individual
private keys in hardware.\
An **HSM** provides strong hardware protection for important
organizational keys.

The most important principle is:

> **Public keys may be distributed. Private keys must be protected.
> Trust must be deliberately managed.**

------------------------------------------------------------------------


# 31. Sample Story: Alice Uses the Company Secure Website

The following story connects the major concepts in this guide. The company name and people are fictional.

## 31.1 The company builds its own PKI

Imagine a company called **BlueRiver Corporation**. BlueRiver operates an internal website:

```text
https://secure.blueriver.example
```

The company wants employees to be sure they are connecting to the real BlueRiver server. It also wants the server to authenticate employees using client certificates.

The security team creates this PKI:

```text
BlueRiver Root CA
        |
        | signs
        v
BlueRiver Intermediate CA
        |
        +-------------------------+
        |                         |
        v                         v
Web Server Certificate      Employee Certificates
                                  |
                         +--------+--------+
                         |                 |
                         v                 v
                    Alice Cert         Bob Cert
```

The Root CA is the top-level trust anchor. Its private signing key is kept highly protected and is not distributed to employees.

## 31.2 Alice receives her own key and certificate

Alice joins the company.

Alice has her own public/private key pair:

```text
Alice
|
+-- Public Key
|
+-- Private Key
```

The company CA issues an X.509 certificate containing Alice's public key and identity information:

```text
Alice's X.509 Certificate
|
+-- Subject: Alice
+-- Alice's Public Key
+-- Issuer: BlueRiver Intermediate CA
+-- Validity Period
+-- Certificate Extensions
+-- CA Signature
```

The certificate does **not** contain Alice's private key.

Alice's private key is stored on a USB cryptographic token:

```text
Alice's USB Crypto Token
|
+-- Alice's Certificate
|
+-- Alice's Private Key
      |
      +-- protected / non-exportable
```

Alice also has a PIN for the token.

## 31.3 Alice's computer trusts the company Root CA

BlueRiver administrators install only the public Root CA certificate into the trusted root store on managed employee computers:

```text
Windows Trusted Root Store
|
+-- BlueRiver Root CA
```

They do **not** install the Root CA private key.

```text
Employee PC:

BlueRiver-Root-CA.crt       YES

BlueRiver-Root-Private.key  NEVER
```

Now Alice's computer has a trust anchor for certificates issued under BlueRiver's PKI.

## 31.4 The web server gets its certificate

The web server has its own key pair:

```text
Web Server
|
+-- Server Public Key
|
+-- Server Private Key
```

The BlueRiver Intermediate CA issues an X.509 server certificate for the website.

```text
BlueRiver Root CA
        |
        v
BlueRiver Intermediate CA
        |
        v
secure.blueriver.example
Server Certificate
```

The server keeps its private key secret.

## 31.5 Alice opens the website

Alice opens:

```text
https://secure.blueriver.example
```

The browser begins a TLS handshake.

```text
Alice's Browser                    BlueRiver Server
       |                                  |
       |---------- ClientHello ---------->|
       |                                  |
       |<--------- ServerHello -----------|
       |<------ Server Certificate -------|
```

The server sends its certificate and normally the intermediate certificate needed to build the chain.

## 31.6 Alice's browser checks the certificate chain

Alice's browser sees:

```text
secure.blueriver.example
          |
          | signed by
          v
BlueRiver Intermediate CA
          |
          | signed by
          v
BlueRiver Root CA
          |
          v
Windows Trusted Root Store
```

The browser also checks important details such as the hostname and validity period.

If validation succeeds, Alice has cryptographic evidence that the server certificate chains to a Root CA her computer trusts.

## 31.7 TLS establishes fresh traffic keys

The server certificate is important for authentication, but the browser does not simply use the certificate's public key to encrypt every web page.

The TLS handshake establishes shared secret material and derives symmetric traffic keys.

```text
TLS Handshake
     |
     v
Shared Secret Material
     |
     v
Key Derivation
     |
     +-- Client -> Server Traffic Key
     |
     +-- Server -> Client Traffic Key
```

A later independent TLS connection normally derives different traffic keys.

```text
Morning Connection
        |
        +-- Traffic Keys A

Afternoon Connection
        |
        +-- Traffic Keys B
```

This is why the server can keep the same certificate while TLS connections use fresh session-specific cryptographic material.

## 31.8 The server asks Alice for a client certificate

BlueRiver uses mutual TLS (mTLS).

The server therefore asks Alice's browser for a client certificate.

```text
Alice's Browser                    BlueRiver Server
       |                                  |
       |<--- Request Client Certificate --|
       |                                  |
       |---- Alice's Certificate -------->|
```

Alice sends her **certificate**, not her private key.

Her USB token performs the necessary private-key operation to prove possession of the private key corresponding to the public key in Alice's certificate.

```text
TLS needs proof
      |
      v
Alice's USB Token
      |
      | PIN / authorization
      |
      | private-key operation
      v
Cryptographic proof
      |
      v
TLS handshake continues
```

Alice's private key remains protected by the token.

## 31.9 The server validates Alice's certificate

The server checks Alice's certificate:

```text
Alice Certificate
       |
       v
Is the certificate chain trusted?
       |
       v
Is it currently valid?
       |
       v
Is it suitable for client authentication?
       |
       v
Is revocation status acceptable?
       |
       v
Does Alice prove possession of the private key?
       |
       v
Map certificate identity to Alice's account
```

If the checks succeed, the server can authenticate Alice.

## 31.10 Authentication is not authorization

Alice's certificate tells the system who Alice is. It does not automatically make her an administrator.

The application separately checks her permissions:

```text
Alice Certificate
       |
       v
Authentication
"Who is this?"
       |
       v
Alice's Application Account
       |
       v
Authorization
"What may Alice do?"
       |
       +-- Read documents?       YES
       +-- Upload documents?     YES
       +-- Manage employees?     NO
       +-- Manage the CA?        NO
```

This separation is fundamental:

```text
Authentication != Authorization
```

## 31.11 Alice downloads a confidential document

Suppose the application allows Alice to download a confidential document.

The HTTP response travels inside the already protected TLS connection:

```text
Confidential Document
        |
        v
HTTPS / TLS
        |
        v
Symmetric Authenticated Encryption
        |
        v
Network
        |
        v
Alice's Browser
```

TLS protects the data while it travels across the network.

That does not automatically protect a file after Alice intentionally saves it to disk. Data-at-rest protection is a separate security problem.

## 31.12 Alice loses her USB token

One day Alice reports:

> "I lost my USB security token."

BlueRiver should not simply wait for the certificate to expire.

The security team follows its certificate lifecycle process:

```text
Token Lost
    |
    v
Report Incident
    |
    v
Revoke Alice's Old Certificate
    |
    v
Issue New Key / Certificate
    |
    v
Provide New Token
```

The old certificate should no longer be accepted according to the organization's revocation design and policy.

## 31.13 What if Alice used a `.p12` instead?

Suppose BlueRiver did not use hardware tokens.

Alice might instead receive:

```text
alice.p12
|
+-- Alice's Private Key
+-- Alice's Certificate
+-- CA Chain
```

She could import this package into an appropriate certificate/key store.

However, if the private key is exportable, malware or an attacker with sufficient access may be able to copy it.

That is one reason higher-security systems may prefer:

```text
.p12 / software key storage
           |
           | higher assurance requirement
           v
Smart Card / USB Crypto Token
           |
           v
Non-exportable hardware-backed key
```

## 31.14 Where would an HSM fit?

Alice does not normally need an enterprise HSM just to browse the company website.

An HSM is more likely to protect important organizational keys, such as CA signing keys.

```text
                 BlueRiver PKI
                      |
            +---------+---------+
            |                   |
            v                   v
      Root / CA Keys       Employee Keys
            |                   |
            v                   v
       HSM / Offline       Smart Card /
       Protection          USB Token
```

The exact Root CA design depends on the organization's security requirements, but the important idea is that CA keys deserve much stronger protection than ordinary public certificates.

## 31.15 The entire story in one diagram

```text
                    BlueRiver Root CA
                           |
                           | signs
                           v
                 BlueRiver Intermediate CA
                    /                 \
                   /                   \
                  v                     v
        Server Certificate        Alice Certificate
                  |                     |
                  v                     v
        Server Public Key         Alice Public Key
                  ^                     ^
                  |                     |
        Server Private Key        Alice Private Key
                                        |
                                        v
                               USB Crypto Token
                                        |
                                        | proves possession
                                        v
Alice's Browser <======== HTTPS / mTLS ========> Server
      |                                             |
      |                                             |
      +-- verifies server certificate              |
                                                    |
                          verifies Alice certificate+
                                                    |
                                                    v
                                           Application Account
                                                    |
                                                    v
                                               Permissions
```

## 31.16 Technologies used in the story

| Technology | Role in the story |
|---|---|
| RSA / ECC | Public-key cryptographic technologies that may underlie keys/signatures/key agreement |
| X.509 | Format used for Alice's and the server's certificates |
| Root CA | BlueRiver's trust anchor |
| Intermediate CA | Issues operational certificates |
| PKI | The complete certificate and trust-management system |
| TLS | Establishes and protects the network connection |
| HTTPS | HTTP carried through TLS |
| mTLS | Lets the server authenticate Alice with a client certificate |
| Symmetric encryption | Efficiently protects application traffic after TLS derives traffic keys |
| PKCS#12 | Possible software container for Alice's certificate/private key |
| USB crypto token | Protects Alice's private key in hardware |
| HSM | Possible strong protection for important CA keys |
| Revocation | Makes a lost/compromised credential unacceptable according to PKI policy |

## 31.17 The story in one sentence

> Alice trusts the company website because its certificate chains to a trusted company Root CA, the website trusts Alice because her client certificate chains to the company PKI and she proves possession of its private key, and TLS derives symmetric traffic keys to protect their HTTP communication.

---

## References and Further Reading

For production systems, consult current standards and official security
guidance in addition to this introductory document.

-   IETF RFC 8446 --- *The Transport Layer Security (TLS) Protocol
    Version 1.3*
-   IETF RFC 5280 --- *Internet X.509 Public Key Infrastructure
    Certificate and CRL Profile*
-   IETF RFC 7292 --- *PKCS #12: Personal Information Exchange Syntax
    v1.1*
-   NIST SP 800-57 Part 1 --- *Recommendation for Key Management:
    General*
-   NIST SP 800-63 series --- *Digital Identity Guidelines*
-   OWASP --- *Transport Layer Security Cheat Sheet*
-   OpenSSL documentation --- certificate, key, and PKI tooling

------------------------------------------------------------------------

*This document is an educational introduction. Production cryptographic
and PKI systems should use current standards, supported algorithms and
libraries, formal key-management procedures, and appropriate security
review.*
