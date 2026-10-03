---
author: \[Student Name\]
date: \[Submission Date\]
title: A Secure Cryptographic Architecture for Controlled Distribution
  and Viewing of Confidential PDF Documents in Isolated Enterprise
  Networks
---

# A Secure Cryptographic Architecture for Controlled Distribution and Viewing of Confidential PDF Documents in Isolated Enterprise Networks

**Master's Thesis**

**Author:** \[Student Name\]\
**Institution:** \[University Name\]\
**Department / Program:** \[Department or Master's Program\]\
**Supervisor:** \[Supervisor Name\]\
**Submission Date:** \[Submission Date\]

> **Research status note:** This consolidated document contains the
> completed architecture, threat model, implementation design, security
> analysis, and experimental protocol. Empirical Chapter 8 measurements
> have not yet been executed in full. Values marked `TBD`,
> `NOT EXECUTED`, or `BLOCKED` must be replaced with observed evidence
> before final academic submission.

# Abstract

Organizations operating isolated or intermittently connected subsidiary
networks face a difficult document-security problem: confidential files
must cross organizational and network boundaries while remaining
restricted to intended recipients, protected at rest, controlled during
viewing, and auditable after delivery. This thesis proposes a
lifecycle-oriented architecture for controlled distribution and viewing
of confidential PDF documents in such an environment.

The architecture combines authenticated encryption, digitally signed
secure packages, recipient-bound cryptographic key protection,
public-key infrastructure, hardware-assisted authentication, server-side
re-encryption, resource-level authorization, protected server-side PDF
rendering, personalized watermarking, short-lived view sessions, and
audit logging. Distribution protection is separated from long-term
subsidiary storage through a double-envelope model: a fresh package
content-encryption key protects the distributed PDF, while a separate
per-document local data-encryption key protects the imported master and
is itself protected by a versioned subsidiary key-encryption key.

A prototype architecture is mapped to a legacy enterprise environment
using CentOS, Apache, PHP 7.4, Zend Framework 1, MariaDB, OpenSSL, a
custom native PHP security extension, Poppler, ImageMagick, and an HTML5
Canvas viewer. The thesis develops a threat model, 105 security
requirements, 25 threat scenarios, implementation traceability, and a
reproducible experimental methodology covering application security,
secure viewing, concurrency, rendering performance, cryptographic
negative tests, and key-management behavior.

The work does not claim absolute prevention of information capture after
authorized display. Screenshots, endpoint compromise, delayed revocation
in disconnected networks, privileged server compromise, and other
residual risks are explicitly identified. The research contribution is
the systematic integration and evaluation framework of established
security mechanisms for confidential document lifecycles across isolated
enterprise networks. Final quantitative conclusions remain dependent on
completion of the native cryptographic pipeline and execution of the
defined experiments.

**Keywords:** secure document distribution; authenticated encryption;
PKI; hardware token; key management; isolated networks; server-side
rendering; digital watermarking; access control; auditability

# Abbreviations

  Abbreviation   Meaning
  -------------- ----------------------------------------------------------
  AAD            Additional Authenticated Data
  AEAD           Authenticated Encryption with Associated Data
  AES            Advanced Encryption Standard
  CA             Certificate Authority
  CEK            Content-Encryption Key
  CRL            Certificate Revocation List
  CSPRNG         Cryptographically Secure Pseudorandom Number Generator
  DEK            Data-Encryption Key
  GCM            Galois/Counter Mode
  HSM            Hardware Security Module
  IDOR           Insecure Direct Object Reference
  KEK            Key-Encryption Key
  PKI            Public Key Infrastructure
  RSA            Rivest-Shamir-Adleman public-key cryptosystem
  SEC            Secure document system designation used in this research
  TPM            Trusted Platform Module
  VPN            Virtual Private Network
  XSS            Cross-Site Scripting
  ZF1            Zend Framework 1

# Chapter 1 --- Introduction

## 1.1 Background

Confidential enterprise documents frequently move through several
security domains during their lifetime. A document may be created by a
central organization, distributed to a subsidiary, imported into a local
business system, stored for an extended period, viewed by multiple
authorized staff members, and later audited. Protecting only one
stage---for example, encrypting a file while it is being
transferred---does not protect the complete lifecycle.

The problem becomes more difficult when subsidiaries are isolated from
one another and cannot depend on continuous internet connectivity to a
central service. In such environments, conventional assumptions about
online authorization, certificate revocation, centralized document
delivery, and real-time policy synchronization may not hold.

This research considers an enterprise scenario in which a parent
organization, Concheron, distributes confidential PDF documents to
multiple subsidiaries. A central SEC portal is reachable through a
private VPN for authorized distribution activities, while subsidiary
business systems operate locally. Secure packages may be transferred
into those environments through controlled mechanisms, and local staff
subsequently require document access through existing business
applications.

## 1.2 Problem Statement

A secure solution must address more than encrypted transport. It must
answer several connected questions:

-   How can a copied package remain unreadable to an unintended
    subsidiary?
-   How can a subsidiary determine that a package was genuinely
    published by the central organization and was not modified?
-   How can recipient credentials be protected from simple copying?
-   How can imported documents remain encrypted without permanently
    depending on the distribution credential?
-   How can staff be authenticated and authorized without giving every
    viewer a document-decryption key?
-   How can a browser display a confidential document without routinely
    receiving the original PDF?
-   How can viewing activity be attributed and audited?
-   How should revocation work when a subsidiary may be disconnected
    from central PKI services?
-   What performance and operational cost is introduced by these
    controls?

A system that solves only file encryption, authentication, or browser
viewing in isolation does not fully address this problem.

## 1.3 Research Aim

The aim of this research is to design and evaluate a secure
cryptographic architecture for controlled distribution, import, storage,
viewing, and auditing of confidential PDF documents across isolated
enterprise networks while minimizing unnecessary exposure of plaintext
documents and cryptographic keys.

## 1.4 Research Objectives

The research objectives are to:

1.  define the assets, actors, trust boundaries, attacker capabilities,
    and residual threats of the target environment;
2.  derive explicit security requirements from the threat model;
3.  design a signed and encrypted document-package format suitable for
    controlled offline transfer;
4.  bind package-key recovery to an authorized recipient cryptographic
    capability;
5.  separate distribution keys, local storage keys, and staff
    authentication credentials;
6.  design server-side re-encryption for imported master documents;
7.  design certificate validation and revocation procedures suitable for
    partially disconnected networks;
8.  design a secure browser-viewing architecture that does not routinely
    deliver the original PDF;
9.  integrate personalized watermarking, short-lived view sessions, and
    audit logging;
10. map the design to the existing CentOS, PHP 7.4, ZF1, MariaDB
    enterprise environment;
11. analyze the architecture against identified threats; and
12. define reproducible security and performance experiments for
    empirical evaluation.

## 1.5 Research Questions

The primary research question is:

> **RQ1:** How can confidential documents be securely distributed,
> decrypted, stored, viewed, and audited across isolated organizational
> networks while minimizing exposure of plaintext documents and
> cryptographic keys?

Supporting questions are:

> **RQ2:** How can hybrid encryption and recipient-specific key
> protection restrict an encrypted package to its intended cryptographic
> recipient?

> **RQ3:** How does separation of package, storage, signing, and
> authentication keys limit compromise scope and improve key-management
> operations?

> **RQ4:** How can server-side re-encryption protect imported documents
> while minimizing persistent plaintext exposure?

> **RQ5:** How can server-side rendering, short-lived view sessions,
> authorization checks, and personalized watermarking reduce
> original-PDF exposure and improve accountability?

> **RQ6:** What performance overhead is introduced by cryptographic
> processing, server-side rendering, watermarking, protected caching,
> and concurrent viewing?

> **RQ7:** Which residual security risks remain after the proposed
> controls are applied?

## 1.6 Scope

The research focuses on confidential PDF documents distributed from a
central enterprise environment to isolated or partially disconnected
subsidiary environments.

The implementation context includes:

``` text
CentOS / Linux
Apache HTTP Server
PHP 7.4
Zend Framework 1
MariaDB
OpenSSL
Custom native PHP extension
Poppler
ImageMagick / Imagick
JavaScript / HTML5 Canvas
```

The research includes package cryptography, PKI, authentication,
authorization, server storage, rendering, watermarking, auditability,
and web-application controls.

## 1.7 Out of Scope

The research does not claim to prevent all information capture after
authorized display. In particular, it does not guarantee prevention of:

-   screenshots;
-   camera photography;
-   screen recording;
-   malware operating with the privileges of an authorized endpoint;
-   disclosure after a complete trusted-server compromise;
-   coercion of an authorized user;
-   unknown future cryptographic breaks;
-   all hardware or supply-chain compromise.

These cases are treated as residual risks or future-work areas.

## 1.8 Proposed Approach

The proposed architecture uses defense in depth.

At distribution time, the PDF is encrypted with a fresh symmetric
package key. That key is protected for the intended recipient, while a
central digital signature authenticates the package and
security-relevant metadata.

After authorized import, the package key is not reused as the permanent
local storage key. A new per-document local DEK encrypts the master PDF,
and a subsidiary KEK protects that DEK.

For viewing, staff authentication is separated from document decryption.
The server verifies current authorization, creates a short-lived view
session, renders protected pages on the server, applies a personalized
watermark, and returns page images to an HTML5 Canvas viewer rather than
routinely sending the original PDF.

## 1.9 Research Methodology

The methodology contains four broad stages.

### Stage 1 --- Security Analysis

The research identifies assets, actors, trust boundaries, attacker
capabilities, security objectives, and threat scenarios.

### Stage 2 --- Architecture and Cryptographic Design

Security requirements are translated into package, PKI, key-management,
storage, viewing, and audit controls.

### Stage 3 --- Prototype Implementation

The design is mapped to the existing enterprise technology stack.
Implementation status is explicitly separated into implemented,
prototype/partial, designed, and future functions.

### Stage 4 --- Experimental Evaluation

Predefined security and performance experiments evaluate implemented
behavior. Unexecuted experiments are not treated as results.

## 1.10 Expected Contributions

The research contribution is not a new cryptographic primitive. Instead,
it is the systematic integration of established mechanisms into a
coherent lifecycle architecture for isolated enterprise document
distribution.

Expected contributions include:

-   a threat-driven secure document architecture;
-   a recipient-bound package model;
-   a CEK/DEK/KEK key-separation model;
-   an offline PKI/revocation approach;
-   a controlled server-side viewing architecture;
-   personalized viewing accountability;
-   threat-to-requirement-to-control traceability;
-   a legacy-system integration design; and
-   a reproducible experimental framework.

## 1.11 Thesis Organization

Chapter 2 reviews relevant literature and standards. Chapter 3 defines
the threat model and security requirements. Chapter 4 presents the
proposed architecture. Chapter 5 specifies cryptographic and
key-management design. Chapter 6 maps the design to the prototype
implementation. Chapter 7 performs architectural security analysis.
Chapter 8 defines the experimental evaluation. Chapter 9 discusses
implications and limitations. Chapter 10 summarizes the research and
future work.

------------------------------------------------------------------------

# Chapter 2 --- Literature Review

## 2.1 Introduction

Secure distribution of confidential documents is a multidisciplinary
problem involving cryptography, public-key infrastructure (PKI),
identity management, access control, secure storage, rendering,
watermarking, and auditing. The problem becomes more difficult when
documents must move from a central authority into isolated or
intermittently connected subsidiary networks, where continuously
available certificate-status, identity, cloud, or policy services cannot
always be assumed.

This chapter reviews the foundations relevant to the proposed secure PDF
distribution architecture: authenticated and hybrid encryption, digital
signatures, key management, PKI and revocation, hardware-backed
authentication, resource-level authorization, protected document
storage, server-side rendering, watermarking, and security in isolated
enterprise networks. The purpose is not to claim novelty for these
established mechanisms. The research instead investigates their
integration across the complete confidential-document lifecycle and
evaluates the resulting security and performance tradeoffs.

## 2.2 Document Security as a Lifecycle Problem

Encryption protects confidentiality while data remains encrypted, but
confidential-document security does not end after successful delivery.
Once authorized decryption occurs, plaintext may become susceptible to
copying or redistribution. Su, Hartung, and Girod \[9\] describe digital
watermarking as a complementary "last line" of protection after
encryption or copy protection, while also recognizing that watermarking
itself cannot prevent copying.

Accordingly, this research treats document protection as a lifecycle:

1.  publication;
2.  encrypted distribution;
3.  recipient authentication;
4.  package verification;
5.  authorized decryption;
6.  protected local storage;
7.  staff authentication and authorization;
8.  controlled viewing;
9.  auditing and accountability; and
10. revocation, expiration, and key lifecycle management.

This perspective separates distribution security, local-storage
security, and viewing security instead of assuming that encrypted
transport solves the entire problem.

## 2.3 Authenticated Encryption

Confidentiality without integrity is insufficient for a
security-sensitive document package. An adversary who cannot read
ciphertext may still attempt to modify ciphertext or security-relevant
metadata.

NIST SP 800-38D specifies Galois/Counter Mode (GCM), an
authenticated-encryption mode that provides confidentiality and
authentication \[1\]. RFC 5116 formalizes Authenticated Encryption with
Associated Data (AEAD), in which plaintext receives confidentiality and
authentication while selected associated data can remain unencrypted but
authenticated \[2\].

This property is useful for a secure package because protocol metadata
may need to be processed without being secret while still requiring
tamper detection.

GCM security critically depends on nonce uniqueness. RFC 5116 requires
distinct nonces for separate encryption operations under the same key
and explains that reuse with GCM can undermine both confidentiality and
integrity \[2\]. The proposed architecture must therefore define
nonce-generation rules explicitly.

For the proposed package, a fresh content-encryption key (CEK) can
protect the PDF with AES-256-GCM. Authenticated decryption must fail
closed: unauthenticated plaintext must not be released.

## 2.4 Hybrid Encryption

Large documents are efficiently protected with symmetric encryption,
while public-key cryptography provides recipient-specific key
protection. A hybrid construction combines these properties:

``` text
Random CEK -> AES-256-GCM -> Encrypted PDF
     |
     +-> Recipient public-key mechanism -> Protected CEK
```

RFC 8017 specifies RSA mechanisms including RSAES-OAEP and RSASSA-PSS
\[3\]. The final algorithm and parameters must follow the capabilities
of the selected hardware token and applicable cryptographic policy.

The architectural point is that the PDF itself is not directly
RSA-encrypted. Instead, a random symmetric key encrypts the bulk
document and a public-key mechanism protects access to that key.

## 2.5 Digital Signatures and Package Authenticity

Encryption determines who can recover protected content; it does not
independently prove who published the package. The system therefore
requires a separate publisher-authenticity mechanism.

A central signing identity should sign all security-relevant package
information, including the manifest and encrypted payload or their
defined canonical representations. RSA-PSS, standardized by RFC 8017, is
one possible signature mechanism \[3\].

Importantly, a subsidiary should not trust fields such as target
organization, document version, recipient identifier, or cryptographic
suite until package authenticity has been established. This prevents
unsigned metadata from becoming an input to sensitive security
decisions.

## 2.6 Cryptographic Key Management and Separation

NIST SP 800-57 Part 1 Rev. 5 provides general key-management guidance
covering key types, required protection, lifecycle functions, and
security services \[4\]. This supports treating keys with different
responsibilities as distinct security assets.

The proposed architecture separates at least four roles:

  ---------------------------------------------------------------------
  Role                               Purpose
  ---------------------------------- ----------------------------------
  Package-signing key                Publisher authenticity and package
                                     integrity

  SEC token private key              Holder authentication and
                                     recipient cryptographic operation

  Subsidiary server key              Protection of imported master
                                     documents at rest

  Staff authentication key           Staff authentication supporting
                                     authorization
  ---------------------------------------------------------------------

This avoids one credential controlling every stage of the lifecycle.
Compromise of a staff credential, for example, should not automatically
reveal the subsidiary master-storage key.

Key generation, storage, rotation, backup, recovery, revocation, and
destruction must therefore be included in the architecture rather than
treated as deployment details.

## 2.7 PKI and X.509 Certificates

RFC 5280 defines the Internet X.509 certificate and Certificate
Revocation List (CRL) profile and specifies certification-path
validation \[5\]. Path validation establishes a chain between a target
certificate and a trusted anchor while applying validity, constraints,
and policy.

A private enterprise PKI can logically separate purposes:

``` text
Enterprise Root CA
├── Secure Device / USB Issuing CA
├── Staff Authentication Issuing CA
├── Server Issuing CA
└── Package-Signing Identity
```

The exact hierarchy is a governance choice, but separate purposes
simplify policy and incident response. Certificate verification must go
beyond checking whether a certificate file exists: trust path,
signature, validity, intended usage, organization binding, and
revocation status all matter.

## 2.8 Revocation in Isolated Networks

Revocation becomes difficult when a subsidiary cannot continuously reach
central services. RFC 5280 standardizes CRLs, providing a mechanism
suitable for distributing signed revocation information into
disconnected environments \[5\].

The proposed architecture can periodically import a signed
revocation/policy package. The subsidiary then performs validation using
locally available trusted information.

This creates an explicit security tradeoff: offline revocation is only
as current as the most recently imported update. Research evaluation
should therefore consider CRL update interval, maximum acceptable
revocation delay, behavior when revocation information expires,
emergency revocation, and recovery from missed updates.

## 2.9 Hardware-Backed Authentication

Possession of a certificate file is not equivalent to possession of a
hardware-protected private key.

NIST SP 800-63B distinguishes exportable and non-exportable
cryptographic authentication keys. Its cryptographic-authenticator model
includes hardware-protected non-exportable keys and challenge-based
proof of possession \[6\].

A weak implementation could place a `.p12` private key on ordinary flash
storage. If copied, the identity can potentially be cloned. A stronger
cryptographic token keeps the private key inside protected hardware and
performs private-key operations without exporting the key.

Conceptually:

``` text
Portal -> fresh challenge -> cryptographic token
Token  -> private-key operation -> response
Portal -> verify with public key -> authentication decision
```

A PIN or activation factor can additionally control use of the
authenticator. The thesis must not generalize this protection to
arbitrary USB drives; security depends on the actual hardware, firmware,
key-export policy, activation controls, and certification.

## 2.10 Authentication Versus Authorization

Authentication establishes identity. Authorization decides whether that
identity may perform an operation.

OWASP recommends validating permissions on every request and warns
against relying on hard-to-guess object identifiers \[7\]. This is
directly relevant to a secure page API.

A valid login alone must therefore not authorize every document page.
Each request should combine:

``` text
authenticated identity
+ verified authentication state
+ document permission
+ document/version binding
+ valid viewing session
+ page bounds
+ current policy
```

Changing a `document_id` in an HTTP request must not provide access to
another protected object. Per-request checks also allow a permission
change to affect subsequent page requests.

## 2.11 Resource-Centric Security

NIST SP 800-207 describes zero trust as avoiding implicit trust based
solely on network location and focusing security decisions on resources
\[8\]. The proposed system is not claimed to be a complete zero-trust
implementation, but the principle is applicable.

Presence on a subsidiary LAN or VPN should not automatically grant
confidential-document access. Network segmentation remains valuable, but
application-level identity and authorization remain necessary.

## 2.12 Protected File Storage

OWASP recommends storing uploaded files outside the web root where
feasible and mediating access through application handlers \[10\]. It
also identifies malicious parser inputs and public file retrieval as
security risks.

This supports the CentOS layout proposed for the prototype:

``` text
/secure/sec/
├── documents/
├── pages/
└── temp/
```

These locations have no intentional direct HTTP URL. Original encrypted
masters and clean rendered-page caches are accessed through controlled
application logic.

This control does not protect against complete host compromise, but it
reduces accidental exposure and enables application authorization to
mediate delivery.

## 2.13 Server-Side Re-encryption

The cryptographic identity used for central distribution need not remain
responsible for local at-rest storage. After authorized package import,
persistent plaintext storage would weaken the protections obtained
during distribution.

The proposed lifecycle is:

``` text
Encrypted package
-> authenticated recipient operation
-> temporary validated plaintext
-> subsidiary server-key encryption
-> protected local master
```

This also permits staff certificates to be used for authentication and
authorization without giving each staff member the master-document
decryption key.

Temporary plaintext remains a residual risk. Its lifetime, location,
permissions, cleanup behavior, and exposure during failures must be
minimized and evaluated.

## 2.14 Server-Side Rendering

If the research objective includes avoiding routine delivery of original
PDFs, document rendering can occur on the trusted server.

``` text
Encrypted master
-> authorized server decryption
-> PDF renderer
-> clean page image
-> personalized watermark
-> authorized page API
-> Canvas viewer
```

The client receives a page representation rather than the original PDF.
This reduces direct-PDF exposure but is not absolute DRM. A user who can
see content may still reproduce it with screenshots, cameras, OCR,
screen recording, or a compromised endpoint.

The research should therefore claim measurable exposure reduction and
access mediation---not impossibility of copying.

## 2.15 PDF Processing as an Attack Surface

Moving PDF rendering to the server introduces a parser attack surface.
OWASP notes that uploaded files can exploit vulnerabilities in file
parsers or processing modules \[10\].

Relevant controls include strict package validation, size limits,
patched rendering libraries, resource limits, fixed executable paths,
validated command arguments, least privilege, protected temporary
directories, timeouts, denial-of-service controls, and process isolation
or sandboxing where practical.

Server-side rendering changes the attack surface; it does not eliminate
it.

## 2.16 Digital Watermarking

Su, Hartung, and Girod \[9\] explain that watermarking can complement
encryption and copy protection by embedding information useful for
ownership or recipient tracing. More recent research continues to
examine robustness, imperceptibility, and resistance to transformation
\[11\].

The proposed prototype uses a visible personalized watermark rather than
claiming an invisible forensic watermark:

``` text
CONFIDENTIAL
Subsidiary A
Employee: EMP-0041
Document: SEC-2026-0184
View: A72F91
Timestamp
```

Its goals are deterrence, viewing-session attribution, and assistance
with leak investigation. Sensitive values such as raw tokens, session
IDs, PINs, passwords, private keys, or CEKs must never be placed in the
watermark.

Watermarking is not an access-control mechanism and cannot guarantee
that visible content cannot be copied or altered.

## 2.17 Auditing and Viewing Sessions

Accountability requires a reliable relationship between security events
and authenticated identities. The proposed system creates short-lived
viewing sessions. A random token authorizes the browser context, while a
hash can be stored server-side. A separate non-secret display code can
appear in the watermark and map to an audit record.

Relevant events include authentication, package download/import,
signature and recipient verification, document open, page access,
denial, session expiration, revocation updates, key rotation, and
permission changes.

Audit logs must not contain passwords, PINs, private keys, CEKs, session
cookies, or raw view tokens.

## 2.18 Isolated Enterprise Networks

Isolation reduces some remote attack paths but creates operational
difficulties. A disconnected subsidiary may lack real-time access to
certificate-status services, identity providers, update repositories,
cloud scanning, centralized logging, or policy distribution.

The proposed system therefore relies on locally available trust anchors,
signed revocation updates, local authorization, encrypted local storage,
and local audit data. Nevertheless, isolation must not be interpreted as
automatic trust. Resource-level authentication and authorization remain
necessary.

## 2.19 Defense in Depth

The literature supports a layered model rather than reliance on one
cryptographic control.

  Layer                       Primary objective
  --------------------------- ---------------------------------------
  Private network/VPN         Reduce network exposure
  Hardware authenticator      Strong proof of possession
  PKI                         Trusted cryptographic identities
  AES-GCM                     Payload confidentiality and integrity
  Digital signature           Publisher/package authenticity
  Recipient key protection    Recipient restriction
  Server-key encryption       Local at-rest protection
  Staff authentication        Establish user identity
  Per-request authorization   Enforce resource access
  Server-side rendering       Avoid routine original-PDF delivery
  Personalized watermark      Attribution and deterrence
  View session                Temporary viewing authorization
  Audit logging               Accountability
  Network isolation           Reduce cross-network exposure

The research interest lies in how these layers interact throughout the
document lifecycle.

## 2.20 Synthesis and Research Gap

Existing standards provide strong foundations for individual mechanisms.
NIST SP 800-38D and RFC 5116 address authenticated encryption \[1\],
\[2\]; RFC 8017 specifies RSA mechanisms \[3\]; NIST SP 800-57 addresses
key management \[4\]; RFC 5280 provides certificate and CRL mechanisms
\[5\]; NIST SP 800-63B addresses cryptographic authenticators \[6\];
OWASP provides application-security guidance for authorization and file
handling \[7\], \[10\]; NIST SP 800-207 provides resource-centric
zero-trust principles \[8\]; and watermarking research addresses
attribution after content is rendered \[9\], \[11\].

The proposed research does **not** claim these mechanisms are
individually novel.

The practical research gap is the integration and evaluation of these
controls when the following constraints occur together:

1.  a central authority publishes confidential documents;
2.  destination organizations operate isolated/restricted networks;
3.  packages are recipient-restricted;
4.  online revocation services may be unavailable;
5.  distribution and local-storage keys have different responsibilities;
6.  staff authentication does not distribute master decryption keys;
7.  original PDFs are not routinely delivered to browsers;
8.  authorization remains enforceable after import;
9.  displayed pages are attributable to viewing sessions; and
10. the architecture must remain operationally practical and measurable.

The research contribution is therefore best framed as **architectural
integration and empirical evaluation**, rather than invention of a new
cryptographic primitive.

## 2.21 Conceptual Framework

The literature suggests the following conceptual framework:

``` text
                  Confidentiality
                        |
Authentication --- Secure Document --- Integrity
                        |
Authorization ----- Accountability
                        |
                  Key Management
```

Network isolation is an environmental constraint around this model, not
its primary security guarantee.

This leads to a testable research proposition:

> A lifecycle-oriented architecture combining recipient-bound
> authenticated encryption, separated cryptographic roles,
> hardware-assisted authentication, local server re-encryption,
> per-request authorization, controlled rendering, personalized
> watermarking, and auditing can reduce document exposure and improve
> accountability in isolated enterprise networks, while introducing
> measurable performance and operational costs.

Subsequent chapters can transform this proposition into explicit
security requirements, threat scenarios, experimental variables, and
measurable outcomes.

## 2.22 Chapter Summary

The literature establishes the foundations for the proposed
architecture. Authenticated encryption protects document confidentiality
and integrity. Hybrid cryptography supports efficient bulk encryption
and recipient-specific key protection. Signatures establish publisher
authenticity. PKI supports trusted identities and revocation. Hardware
cryptographic authenticators reduce the risk of credential copying.
Key-management guidance motivates separation of cryptographic
responsibilities. Per-request authorization prevents authentication or
network location from becoming blanket access. Protected storage and
server-side rendering reduce routine exposure of original PDFs, while
personalized watermarking and auditing support accountability.

The literature also establishes limitations. Hardware authentication
cannot secure a fully compromised endpoint. Network isolation does not
replace authorization. Server-side rendering cannot make visible
information impossible to reproduce. Watermarking does not replace
encryption. Offline revocation introduces freshness limitations.

These findings establish the basis for **Chapter 3: Threat Model and
Security Requirements**.

# References

\[1\] M. Dworkin, *Recommendation for Block Cipher Modes of Operation:
Galois/Counter Mode (GCM) and GMAC*, NIST SP 800-38D, National Institute
of Standards and Technology, 2007. doi:10.6028/NIST.SP.800-38D.

\[2\] D. McGrew, *An Interface and Algorithms for Authenticated
Encryption*, RFC 5116, IETF, January 2008.

\[3\] K. Moriarty, B. Kaliski, J. Jonsson, and A. Rusch, *PKCS #1: RSA
Cryptography Specifications Version 2.2*, RFC 8017, IETF, November 2016.

\[4\] E. Barker, *Recommendation for Key Management: Part 1 ---
General*, NIST SP 800-57 Part 1 Rev. 5, National Institute of Standards
and Technology, May 2020.

\[5\] D. Cooper et al., *Internet X.509 Public Key Infrastructure
Certificate and Certificate Revocation List (CRL) Profile*, RFC 5280,
IETF, May 2008.

\[6\] National Institute of Standards and Technology, *Digital Identity
Guidelines: Authentication and Authenticator Management*, NIST SP
800-63B, current NIST Digital Identity Guidelines.

\[7\] OWASP Foundation, *Authorization Cheat Sheet*, OWASP Cheat Sheet
Series.

\[8\] S. Rose, O. Borchert, S. Mitchell, and S. Connelly, *Zero Trust
Architecture*, NIST SP 800-207, National Institute of Standards and
Technology, August 2020.

\[9\] J. K. Su, F. Hartung, and B. Girod, "Digital watermarking of text,
image, and video documents," *Computers & Graphics*, vol. 22, no. 6,
pp. 687--695, 1998. doi:10.1016/S0097-8493(98)00089-2.

\[10\] OWASP Foundation, *File Upload Cheat Sheet*, OWASP Cheat Sheet
Series.

\[11\] S. Ben Jabra and M. Ben Farah, "Deep Learning-Based Watermarking
Techniques Challenges: A Review of Current and Future Trends,"
*Circuits, Systems, and Signal Processing*, vol. 43, pp. 4339--4368,
2024.

## Thesis Development Note

Before final submission, the bibliography should be converted to the
university's required style (e.g., IEEE or APA 7), and bibliographic
metadata should be normalized in a reference manager. The final
literature review should also add more peer-reviewed studies
specifically on enterprise DRM/IRM, offline PKI/revocation,
hardware-token security, PDF security, forensic watermarking, and
empirical evaluation of protected document systems.

------------------------------------------------------------------------

# Chapter 3 --- Threat Model and Security Requirements

## 3.1 Introduction

This chapter defines the threat model and security requirements for the
proposed secure document distribution and viewing architecture. The
objective is to identify the assets that require protection, the actors
that interact with the system, the trust boundaries across which
sensitive data moves, the attacker capabilities considered by the
research, and the security properties that the prototype must provide.

The threat model is deliberately broader than protection of an encrypted
PDF file. The complete document lifecycle is considered:

``` text
Central publication
        ↓
Secure package generation
        ↓
Recipient-bound distribution
        ↓
Controlled transfer
        ↓
Subsidiary import
        ↓
Temporary plaintext processing
        ↓
Server-key re-encryption
        ↓
Protected local storage
        ↓
Staff authentication
        ↓
Document authorization
        ↓
Server-side rendering
        ↓
Personalized page delivery
        ↓
Auditing
```

Threats can occur at every stage. The architecture therefore applies
defense in depth rather than relying on a single encryption mechanism.

The requirements defined in this chapter are assigned stable identifiers
such as `SR-001`. These identifiers will later be mapped to
architectural controls, implementation components, and experimental
security tests.

------------------------------------------------------------------------

## 3.2 Security Objectives

The proposed architecture has seven primary security objectives.

### SO-01 --- Confidentiality

Unauthorized parties must not be able to recover confidential document
content from distributed packages or protected server storage under the
stated threat model.

### SO-02 --- Integrity

Unauthorized modification of protected document content or
security-relevant package information must be detected.

### SO-03 --- Authenticity

A subsidiary must be able to establish whether a package originated from
an authorized central publisher.

### SO-04 --- Authentication

Sensitive operations must be associated with appropriately authenticated
users or cryptographic devices.

### SO-05 --- Authorization

Authentication alone must not grant access to every document. Access
must be evaluated according to the requested resource and current
policy.

### SO-06 --- Accountability

Security-relevant actions must be attributable through protected audit
records and, where appropriate, personalized document representations.

### SO-07 --- Availability

Security controls should resist reasonable resource-exhaustion attacks
and should not make legitimate document access operationally
impractical.

------------------------------------------------------------------------

## 3.3 System Model

The system consists of two major security environments.

### 3.3.1 Central Environment

The central environment contains:

-   the central SEC document portal;
-   authorized publishers;
-   central authentication services;
-   PKI and trust-management components;
-   SEC cryptographic USB/token identities;
-   package-generation services;
-   package-signing capability;
-   encrypted package storage;
-   package-download auditing; and
-   the private VPN/access network.

### 3.3.2 Subsidiary Environment

Each subsidiary environment contains:

-   the local ZF1 business application;
-   Apache and PHP 7.4;
-   MariaDB;
-   the Concheron native PHP security extension;
-   local trust material;
-   server-side cryptographic keys;
-   encrypted master-document storage;
-   Poppler/PDF rendering;
-   ImageMagick/Imagick watermark generation;
-   the protected page cache;
-   temporary processing storage;
-   staff certificate authentication;
-   document permissions;
-   view-session management; and
-   local audit records.

Subsidiary networks are treated as logically separated security domains.

------------------------------------------------------------------------

## 3.4 Protected Assets

The following assets require protection.

### A-01 --- Original PDF Content

The confidential source document is the primary information asset.

### A-02 --- Encrypted SEC Package

Although encrypted, the package contains confidential ciphertext,
recipient information, signed metadata, and security-relevant protocol
information.

### A-03 --- Content-Encryption Key

The CEK protects the package payload and must remain secret.

### A-04 --- Package-Signing Private Key

Compromise could allow an attacker to create packages that appear to
originate from the central authority.

### A-05 --- SEC USB/Token Private Key

Compromise could permit unauthorized authentication or recipient-key
operations.

### A-06 --- Subsidiary Server Private/Storage Key

Compromise could expose locally stored protected master documents.

### A-07 --- Staff Authentication Private Key

Compromise could enable staff impersonation.

### A-08 --- Trust Anchors and Revocation Data

Modification could alter which identities the subsidiary trusts.

### A-09 --- Authorization Data

Document permissions determine which authenticated staff may access
confidential content.

### A-10 --- View Tokens

A valid view token represents temporary authorization within a
particular viewing context.

### A-11 --- Clean Rendered Pages

Although not original PDFs, unwatermarked rendered pages contain
confidential document information.

### A-12 --- Personalized Rendered Pages

These contain confidential content and attribution information.

### A-13 --- Audit Records

Audit records provide evidence for accountability and incident
investigation.

### A-14 --- Application and Native Extension

Modification of the application or cryptographic extension could bypass
security controls.

### A-15 --- Cryptographic Configuration

Algorithm suites, trusted signing keys, company identity, and security
policy settings influence trust decisions.

------------------------------------------------------------------------

## 3.5 Actors

### 3.5.1 Central SEC Administrator

Manages central security policy and selected SEC system administration
functions.

### 3.5.2 Central Publisher

Creates or approves confidential documents for distribution.

### 3.5.3 PKI Administrator

Manages certificate issuance, revocation, renewal, and trust
infrastructure.

### 3.5.4 Subsidiary SEC Manager / Importer

Imports authorized secure packages into the subsidiary system.

### 3.5.5 Authorized Staff Viewer

Views documents for which explicit authorization exists.

### 3.5.6 Local System Administrator

Maintains subsidiary infrastructure. Administrative operating-system
access is considered powerful and is not automatically equivalent to
business authorization to view SEC documents.

### 3.5.7 Security Auditor

Reviews security events and audit information according to assigned
permissions.

### 3.5.8 External Attacker

An unauthorized actor attempting remote or physical compromise.

### 3.5.9 Malicious Insider

A person with legitimate organizational access who attempts to exceed
assigned privileges or redistribute confidential information.

### 3.5.10 Malware / Compromised Endpoint

Malicious software executing on an authorized client or server.

------------------------------------------------------------------------

## 3.6 Trust Boundaries

Security-sensitive information crosses several trust boundaries.

### TB-01 --- User Device to Central Portal

``` text
SEC staff workstation
        |
        | VPN + authenticated application channel
        v
Central SEC portal
```

Threats include credential theft, replay, session hijacking, compromised
endpoints, and impersonation.

### TB-02 --- SEC Token to Host

``` text
Hardware token
      |
      | cryptographic operation
      v
User workstation
```

The private key should not be exported to the host.

### TB-03 --- Central Portal to Downloaded Package

The system transitions from centrally controlled information to a
portable encrypted object.

### TB-04 --- Transfer Medium to Subsidiary Import

The package may pass through removable media or another approved
transfer mechanism. The transfer medium itself is not assumed to provide
confidentiality or authenticity.

### TB-05 --- Web Application to Native Security Extension

``` text
ZF1 / PHP
    |
    | high-level security API
    v
concheron_sec.so
```

Raw private keys and CEKs should not be returned to ordinary PHP
application code.

### TB-06 --- Native Extension to Protected Storage

Sensitive decryption and re-encryption operations cross the boundary
between application logic and protected cryptographic/storage
components.

### TB-07 --- Server to PDF Renderer

Temporary plaintext may be supplied to the PDF rendering process.

### TB-08 --- Server to Browser

The server returns only authorized rendered page representations rather
than the original PDF.

### TB-09 --- Application to MariaDB

Authorization, document metadata, view-session state, and audit
information cross this boundary.

------------------------------------------------------------------------

## 3.7 Attacker Capabilities

The threat model assumes that an attacker may be capable of:

-   obtaining a copy of an encrypted `.sec` package;
-   copying or modifying files on removable transfer media;
-   modifying package bytes;
-   attempting replay of previously valid packages;
-   guessing document identifiers;
-   modifying HTTP request parameters;
-   attempting to reuse a stolen view token;
-   sending malformed or malicious PDF/package data;
-   performing automated page requests;
-   attempting SQL injection, XSS, CSRF, path traversal, or command
    injection;
-   obtaining an expired or revoked certificate;
-   obtaining a legitimate username/password;
-   obtaining a copyable software certificate;
-   attempting to access protected filesystem paths through HTTP;
-   attempting to read browser caches;
-   observing watermarked pages;
-   taking screenshots or photographs of visible content; and
-   exploiting software vulnerabilities.

The model also considers malicious insiders who possess legitimate
access to some documents but attempt to access other documents or
redistribute information.

------------------------------------------------------------------------

## 3.8 Explicit Non-Assumptions

The architecture does **not** assume that:

-   an internal network is automatically trusted;
-   possession of a username implies document authorization;
-   a file extension proves that an uploaded file is valid;
-   a certificate file proves possession of its private key;
-   browser JavaScript can prevent screenshots;
-   disabling right-click prevents copying;
-   a watermark prevents redistribution;
-   an encrypted package is authentic merely because it is encrypted;
-   a system administrator necessarily has business permission to read
    all documents; or
-   secure deletion of plaintext from SSD storage can always be
    physically guaranteed.

These non-assumptions are important because they prevent security claims
from depending on weak controls.

------------------------------------------------------------------------

## 3.9 Out-of-Scope or Residual Threats

Some threats cannot be fully prevented by the proposed architecture.

### 3.9.1 Authorized Screen Capture

An authorized viewer may photograph or capture information displayed on
the screen.

The architecture mitigates this through attribution, watermarking, audit
logging, and limited delivery of original files, but cannot eliminate
the threat.

### 3.9.2 Fully Compromised Authorized Endpoint

Malware with sufficient control over an authorized endpoint may capture
rendered information.

### 3.9.3 Fully Compromised Privileged Server

An attacker with unrestricted root-level control of a subsidiary server
may be able to access plaintext during authorized processing or tamper
with application behavior.

Hardware-backed keys, process isolation, SELinux, auditing, and least
privilege can reduce risk, but a completely compromised trusted
computing base remains a major residual threat.

### 3.9.4 Physical Coercion or Social Engineering

Cryptographic controls do not fully address coercion of authorized
personnel.

------------------------------------------------------------------------

## 3.10 Threat Classification

A STRIDE-inspired classification is used to organize threats.

  ---------------------------------------------------------------------
  Category                           Meaning in this research
  ---------------------------------- ----------------------------------
  Spoofing                           Impersonating a legitimate user,
                                     device, server, or publisher

  Tampering                          Unauthorized modification of
                                     packages, metadata, permissions,
                                     files, or audit records

  Repudiation                        Denying an action when reliable
                                     accountability is required

  Information Disclosure             Unauthorized recovery or exposure
                                     of confidential content or keys

  Denial of Service                  Preventing legitimate document
                                     processing or viewing

  Elevation of Privilege             Obtaining permissions beyond those
                                     assigned
  ---------------------------------------------------------------------

STRIDE is used as a systematic analysis aid rather than as the only
security methodology.

------------------------------------------------------------------------

# 3.11 Threat Scenarios

## T-001 --- Stolen Encrypted Package

**Scenario:** An attacker obtains a `.sec` package from removable media,
storage, or an intercepted transfer.

**Target assets:** A-01, A-02, A-03.

**Potential impact:** Disclosure of the confidential PDF.

**Required controls:**

-   authenticated payload encryption;
-   strong random CEK;
-   recipient-specific CEK protection;
-   protected recipient private key;
-   no plaintext PDF inside the package.

**Expected result:** Possession of the package alone is insufficient to
recover the PDF.

------------------------------------------------------------------------

## T-002 --- Package Sent to Wrong Subsidiary

**Scenario:** A package intended for Subsidiary A is copied into
Subsidiary B.

**Potential impact:** Cross-subsidiary information disclosure.

**Required controls:**

-   signed target-subsidiary identifier;
-   recipient-specific key protection;
-   local company-identity validation;
-   import rejection before document creation.

**Expected result:** Subsidiary B cannot successfully import the
document.

------------------------------------------------------------------------

## T-003 --- Package Tampering

**Scenario:** An attacker modifies the encrypted payload, manifest,
recipient data, or signature block.

**Potential impact:** Corruption, policy bypass, malicious substitution,
or unauthorized metadata modification.

**Required controls:**

-   digital signature verification;
-   authenticated encryption;
-   strict package parsing;
-   fail-closed import.

------------------------------------------------------------------------

## T-004 --- Forged Package

**Scenario:** An attacker constructs a package that claims to originate
from the central organization.

**Required control:** Verification using a configured trusted
package-signing identity.

**Expected result:** A package without a valid trusted signature is
rejected.

------------------------------------------------------------------------

## T-005 --- Replay of an Old Package

**Scenario:** A previously valid package is imported again or an older
document version is reintroduced.

**Potential impact:** Duplicate records, rollback, or version downgrade.

**Required controls:**

-   unique package identifier;
-   document/version tracking;
-   import history;
-   signed version metadata;
-   replay/downgrade policy.

------------------------------------------------------------------------

## T-006 --- Copied Software Private Key

**Scenario:** An attacker copies a `.p12` file from ordinary USB
storage.

**Potential impact:** Device/user impersonation.

**Mitigation:** Prefer a hardware cryptographic token with a
non-exportable private key and activation factor.

------------------------------------------------------------------------

## T-007 --- Authentication Replay

**Scenario:** An attacker captures an authentication response and
attempts to replay it.

**Required controls:**

-   fresh server challenge;
-   cryptographic proof of possession;
-   single-use or freshness-bound challenge;
-   authenticated transport.

------------------------------------------------------------------------

## T-008 --- Revoked Certificate Used Offline

**Scenario:** A certificate has been revoked centrally, but an isolated
subsidiary has stale revocation information.

**Potential impact:** A revoked user remains temporarily accepted.

**Required controls:**

-   signed offline CRL/revocation updates;
-   update freshness policy;
-   expiration policy;
-   emergency revocation procedure.

**Residual risk:** Revocation delay cannot be zero when the environment
is disconnected.

------------------------------------------------------------------------

## T-009 --- Server Storage Theft

**Scenario:** An attacker copies encrypted master-document files from
subsidiary storage.

**Required controls:**

-   server-key encryption at rest;
-   key separated from ordinary document storage;
-   restrictive filesystem permissions;
-   server-key protection.

------------------------------------------------------------------------

## T-010 --- Temporary Plaintext Exposure

**Scenario:** A temporary PDF remains on disk after rendering or
failure.

**Required controls:**

-   restricted temporary directory;
-   random filename;
-   minimum lifetime;
-   cleanup on success and error;
-   encrypted/protected temporary storage where practical;
-   no web exposure;
-   no backup of plaintext temporary files.

------------------------------------------------------------------------

## T-011 --- IDOR / Document Identifier Manipulation

**Scenario:** An authorized user changes the requested document ID to
another document.

**Required control:** Server-side authorization on every protected
request.

**Expected result:** Access is denied unless the user independently has
permission for the new document.

------------------------------------------------------------------------

## T-012 --- View Token Theft

**Scenario:** An attacker obtains a valid temporary view token.

**Required controls:**

-   high-entropy token;
-   short expiration;
-   inactivity timeout;
-   binding to user/document/version;
-   server-side token hashing;
-   TLS;
-   XSS defenses.

------------------------------------------------------------------------

## T-013 --- Direct Original-PDF Request

**Scenario:** A user guesses a filesystem or URL path to the original
PDF.

**Required controls:**

-   no original PDF in web root;
-   no public static URL;
-   Apache deny rules as defense in depth;
-   application-mediated access only.

------------------------------------------------------------------------

## T-014 --- Direct Clean-Page Cache Access

**Scenario:** An attacker tries to bypass watermarking by requesting a
clean rendered page.

**Required controls:**

-   page cache outside web root;
-   no Apache alias;
-   filesystem restrictions;
-   personalized response generated through the protected API.

------------------------------------------------------------------------

## T-015 --- Cross-Site Scripting

**Scenario:** Malicious script executes in the viewer origin and steals
a view token or requests pages as the user.

**Required controls:**

-   context-sensitive output encoding;
-   input validation;
-   Content Security Policy;
-   avoidance of unsafe DOM APIs;
-   secure cookies;
-   minimal token exposure;
-   dependency security.

XSS is especially important because the browser necessarily possesses
temporary viewing authorization.

------------------------------------------------------------------------

## T-016 --- CSRF

**Scenario:** A malicious origin causes an authenticated browser to
perform a state-changing request.

**Required controls:**

-   CSRF protection for state-changing requests;
-   SameSite cookies where compatible;
-   Origin/Referer validation where appropriate;
-   multi-tab-safe session CSRF strategy.

Read-only confidential page APIs still require strong authorization and
token binding even when CSRF exposure differs from ordinary
state-changing operations.

------------------------------------------------------------------------

## T-017 --- SQL Injection

**Scenario:** Untrusted request data alters a database query.

**Required controls:**

-   parameterized queries;
-   strict validation;
-   least-privileged database account;
-   generic user-facing errors.

------------------------------------------------------------------------

## T-018 --- Renderer Command Injection

**Scenario:** User-controlled input becomes part of a shell command used
to execute Poppler.

**Required controls:**

-   administrator-controlled executable path;
-   integer page validation;
-   server-defined resolution profiles;
-   safe process invocation;
-   no arbitrary shell fragments or user-supplied paths.

------------------------------------------------------------------------

## T-019 --- Malicious PDF Against Renderer

**Scenario:** A crafted PDF exploits Poppler/ImageMagick or consumes
excessive resources.

**Required controls:**

-   patched renderer;
-   process isolation;
-   resource limits;
-   timeout;
-   input size/page limits;
-   least-privileged service account;
-   monitoring.

------------------------------------------------------------------------

## T-020 --- Bulk Page Extraction

**Scenario:** A legitimate or compromised account automatically
downloads every page at high speed.

**Required controls:**

-   per-user/view/document rate limiting;
-   burst-aware limits compatible with scrolling;
-   audit detection;
-   short-lived sessions;
-   anomaly monitoring where practical.

------------------------------------------------------------------------

## T-021 --- Watermark Removal

**Scenario:** A recipient crops or modifies a visible watermark.

**Residual risk:** Visible watermarking cannot guarantee removal
resistance.

**Mitigations:**

-   repeated watermark pattern;
-   footer attribution;
-   multiple placement regions;
-   audit linkage;
-   optional future forensic watermarking.

------------------------------------------------------------------------

## T-022 --- Audit Log Tampering

**Scenario:** An attacker modifies or deletes access records.

**Required controls:**

-   restricted log permissions;
-   separation between application users and audit administration;
-   append-oriented design where practical;
-   centralized or signed log export where feasible;
-   timestamps and event identifiers.

------------------------------------------------------------------------

## T-023 --- Key Disclosure Through Logs or Errors

**Scenario:** Secrets appear in debug logs, stack traces, audit records,
or API responses.

**Required controls:**

Never log or return:

``` text
private keys
CEKs
passwords
PINs
raw view tokens
session cookies
CSRF secrets
plaintext PDF content
```

------------------------------------------------------------------------

## T-024 --- Security Extension Replacement

**Scenario:** An attacker replaces `concheron_sec.so` with a modified
binary.

**Required controls:**

-   controlled installation;
-   restrictive filesystem ownership;
-   signed/reproducibly identified releases;
-   version validation;
-   package/checksum verification;
-   change auditing.

------------------------------------------------------------------------

## T-025 --- Permission Revocation During Viewing

**Scenario:** A user begins an authorized session and permission is then
removed.

**Required control:** Re-evaluate document authorization during
subsequent page requests rather than relying solely on authorization
performed when the viewer opened.

------------------------------------------------------------------------

# 3.12 Security Requirements

## 3.12.1 Package Cryptography

**SR-001:** Each secure document package shall protect its PDF payload
using an approved authenticated-encryption algorithm.

**SR-002:** The prototype shall use AES-256-GCM for package payload
protection unless an explicitly documented alternative is evaluated.

**SR-003:** A fresh cryptographically secure content-encryption key
shall be generated for each package.

**SR-004:** GCM nonce/IV values shall never be reused with the same
encryption key.

**SR-005:** Authentication failure during AEAD decryption shall cause
the import to fail without accepting document plaintext.

**SR-006:** The CEK shall be protected using an approved recipient
public-key/key-establishment mechanism.

**SR-007:** Ordinary PHP application code shall not receive the
recipient private key.

**SR-008:** Ordinary PHP application code should not receive raw CEKs
when the native security boundary can perform the operation internally.

------------------------------------------------------------------------

## 3.12.2 Package Authenticity and Integrity

**SR-009:** Security-relevant package data shall be digitally signed by
an authorized central signing identity.

**SR-010:** A subsidiary shall verify the package signature before
trusting signed manifest fields.

**SR-011:** Package-signing trust shall be based on
administrator-controlled trusted configuration, not a public key
supplied by the uploaded package or HTTP request.

**SR-012:** Modified signed metadata shall cause verification failure.

**SR-013:** Modified protected payload data shall cause
verification/authentication failure.

**SR-014:** The package parser shall enforce strict format version,
length, and size limits.

------------------------------------------------------------------------

## 3.12.3 Recipient and Subsidiary Restriction

**SR-015:** Each package shall identify its intended subsidiary in
authenticated/signed metadata.

**SR-016:** The importer shall compare the package target against
locally configured subsidiary identity.

**SR-017:** A package intended for another subsidiary shall be rejected.

**SR-018:** Recipient-specific cryptographic information shall bind
package-key recovery to the authorized recipient mechanism.

**SR-019:** Package recipient information shall not be trusted before
package authenticity has been established.

------------------------------------------------------------------------

## 3.12.4 Replay and Version Control

**SR-020:** Every package shall contain a unique package identifier.

**SR-021:** Import history shall record successfully imported package
identifiers.

**SR-022:** Duplicate package import shall be detected according to
policy.

**SR-023:** Document version information shall be authenticated.

**SR-024:** The system shall reject prohibited document-version
downgrades.

------------------------------------------------------------------------

## 3.12.5 Hardware Authentication

**SR-025:** Sensitive central authentication should use a cryptographic
authenticator rather than USB presence alone.

**SR-026:** Production token private keys should be non-exportable.

**SR-027:** Token use shall require an approved activation factor such
as a PIN when supported by the selected authenticator.

**SR-028:** Authentication shall prove possession of the private key
through a fresh challenge-response or equivalent approved protocol.

**SR-029:** Captured authentication responses shall not be reusable as
valid authentication for independent future challenges.

------------------------------------------------------------------------

## 3.12.6 Certificate Validation

**SR-030:** Certificates shall chain to an approved trust anchor.

**SR-031:** Certificate validity periods shall be checked.

**SR-032:** Certificate purpose/key usage shall be checked according to
its role.

**SR-033:** Staff/device certificates shall be mapped to the correct
organizational identity.

**SR-034:** Revocation status shall be checked using the freshest
locally approved revocation information available.

**SR-035:** The system shall define behavior for expired or stale
offline revocation information.

------------------------------------------------------------------------

## 3.12.7 Server-Side Storage

**SR-036:** Imported original documents shall not be persistently stored
in plaintext.

**SR-037:** Retained master documents shall be encrypted using a
subsidiary server-side protection key.

**SR-038:** The local server-storage key shall be separate from staff
authentication keys.

**SR-039:** Protected master documents shall be stored outside the web
root.

**SR-040:** Server private/storage keys shall not be stored in ordinary
unprotected database fields.

------------------------------------------------------------------------

## 3.12.8 Temporary Plaintext

**SR-041:** Temporary plaintext shall exist only when necessary for
authorized processing.

**SR-042:** Temporary files shall use unpredictable names and
restrictive permissions.

**SR-043:** Temporary plaintext shall not be reachable through HTTP.

**SR-044:** Cleanup shall execute on both success and failure paths.

**SR-045:** Temporary plaintext locations shall be excluded from
ordinary backup processes.

**SR-046:** The deployment shall document limitations of physical secure
deletion on SSD/journaled storage.

------------------------------------------------------------------------

## 3.12.9 Staff Authentication and Authorization

**SR-047:** Staff authentication shall be distinct from document
authorization.

**SR-048:** Staff certificates shall be used for
authentication/authorization and shall not directly expose
master-document decryption keys to staff.

**SR-049:** Every protected page request shall perform server-side
authorization.

**SR-050:** Authorization shall bind the requesting staff identity to
the requested document.

**SR-051:** Authorization shall consider document version where
relevant.

**SR-052:** Permission revocation shall affect subsequent protected
requests.

**SR-053:** Object identifiers shall not be treated as authorization
secrets.

------------------------------------------------------------------------

## 3.12.10 View Sessions

**SR-054:** View tokens shall be generated using a cryptographically
secure random generator.

**SR-055:** View tokens shall contain sufficient entropy to resist
guessing.

**SR-056:** The server should store a cryptographic hash of the raw
token rather than the raw token.

**SR-057:** A view session shall be bound to a staff identity and
document/version.

**SR-058:** View sessions shall have an absolute expiration time.

**SR-059:** View sessions should have an inactivity timeout.

**SR-060:** Raw view tokens shall never appear in watermarks or audit
logs.

------------------------------------------------------------------------

## 3.12.11 Secure Rendering

**SR-061:** The original PDF shall not have a public browser URL.

**SR-062:** Clean rendered pages shall not have public static URLs.

**SR-063:** PDF rendering shall occur only after authorization or
through a protected pre-render workflow.

**SR-064:** Renderer executable paths shall be administrator controlled.

**SR-065:** Page numbers and rendering profiles shall be strictly
validated.

**SR-066:** User input shall not be permitted to inject arbitrary
renderer command arguments.

**SR-067:** Rendering processes should execute with least privilege and
resource limits.

------------------------------------------------------------------------

## 3.12.12 Personalized Watermarking

**SR-068:** Delivered pages shall support personalized watermarking
according to document policy.

**SR-069:** Watermarks shall identify a safe staff/document/view context
without exposing authentication secrets.

**SR-070:** Personalized watermarked output shall not overwrite the
clean protected page cache.

**SR-071:** The watermark view code shall be traceable to an internal
audit/view-session record.

------------------------------------------------------------------------

## 3.12.13 Web Application Security

**SR-072:** All SEC web traffic shall use HTTPS/TLS.

**SR-073:** State-changing requests shall use CSRF protection.

**SR-074:** CSRF design shall support normal multi-tab application use.

**SR-075:** Session cookies shall use appropriate Secure, HttpOnly, and
SameSite attributes.

**SR-076:** Database operations shall use parameterized queries.

**SR-077:** Output shall be contextually encoded to reduce XSS risk.

**SR-078:** A restrictive Content Security Policy should be deployed
where compatible.

**SR-079:** File uploads shall be validated by package structure rather
than filename extension alone.

**SR-080:** Uploaded filenames shall not directly determine protected
storage paths.

**SR-081:** User-facing errors shall not reveal private keys, filesystem
secrets, stack traces, or cryptographic internals.

------------------------------------------------------------------------

## 3.12.14 Rate Limiting and Availability

**SR-082:** Protected page delivery shall implement rate limiting.

**SR-083:** Rate limits shall allow legitimate continuous scrolling and
reasonable bursts.

**SR-084:** Renderer execution shall use time/resource limits where
supported.

**SR-085:** Package and PDF size limits shall be configurable.

**SR-086:** Excessive or malformed requests shall fail without
destabilizing the service.

------------------------------------------------------------------------

## 3.12.15 Audit and Accountability

**SR-087:** Security-relevant events shall be recorded in an audit log.

**SR-088:** Audit records shall include a unique event identifier and
timestamp.

**SR-089:** Document-view events shall be attributable to an
authenticated staff identity and viewing context.

**SR-090:** Package import success and failure shall be audited.

**SR-091:** Authorization denials shall be auditable where appropriate.

**SR-092:** Certificate/revocation updates and key-management events
shall be audited.

**SR-093:** Audit logs shall not contain private keys, CEKs, PINs,
passwords, raw view tokens, or session cookies.

**SR-094:** Audit storage shall be protected against unauthorized
modification.

------------------------------------------------------------------------

## 3.12.16 Native Security Extension

**SR-095:** Cryptographic operations exposed to PHP shall use high-level
APIs where practical.

**SR-096:** The native extension shall not provide APIs whose normal
purpose is exporting server or token private keys.

**SR-097:** Extension version and approved minimum version shall be
identifiable.

**SR-098:** Extension binaries shall be distributed through an
authenticated software-release process.

**SR-099:** Production cryptographic implementation shall use
established cryptographic libraries rather than custom AES/RSA/ECC
primitives.

------------------------------------------------------------------------

## 3.12.17 CentOS Host Security

**SR-100:** Protected SEC storage shall be outside the Apache document
root.

**SR-101:** Filesystem permissions shall follow least privilege and
shall not use broad world-writable permissions.

**SR-102:** SELinux should remain enforcing, with narrowly scoped policy
for required SEC operations.

**SR-103:** The PDF renderer and application components shall run under
restricted service identities.

**SR-104:** Security updates for cryptographic and PDF-processing
dependencies shall follow the organization's approved update process.

**SR-105:** Plaintext temporary storage and clean page caches shall not
be exported through unintended network shares.

------------------------------------------------------------------------

# 3.13 Threat-to-Requirement Traceability

The following table provides an initial traceability mapping.

  Threat                         Primary requirements
  ------------------------------ ----------------------------------------
  T-001 Stolen package           SR-001--SR-008, SR-018
  T-002 Wrong subsidiary         SR-015--SR-019
  T-003 Package tampering        SR-001, SR-005, SR-009--SR-014
  T-004 Forged package           SR-009--SR-011
  T-005 Replay/downgrade         SR-020--SR-024
  T-006 Copied private key       SR-025--SR-028
  T-007 Authentication replay    SR-028--SR-029
  T-008 Stale revocation         SR-030--SR-035
  T-009 Storage theft            SR-036--SR-040
  T-010 Temporary plaintext      SR-041--SR-046
  T-011 IDOR                     SR-047--SR-053
  T-012 View-token theft         SR-054--SR-060, SR-072, SR-077
  T-013 Direct PDF request       SR-039, SR-061, SR-100
  T-014 Clean-cache access       SR-062, SR-070, SR-100
  T-015 XSS                      SR-060, SR-075, SR-077--SR-078
  T-016 CSRF                     SR-073--SR-075
  T-017 SQL injection            SR-076
  T-018 Command injection        SR-064--SR-066
  T-019 Malicious PDF            SR-067, SR-084--SR-086, SR-103--SR-104
  T-020 Bulk extraction          SR-082--SR-083, SR-087--SR-091
  T-021 Watermark removal        SR-068--SR-071
  T-022 Audit tampering          SR-087--SR-094
  T-023 Secret leakage           SR-060, SR-081, SR-093
  T-024 Extension replacement    SR-097--SR-099
  T-025 Mid-session revocation   SR-049, SR-052

This matrix will be refined when implementation and experimental test
cases are finalized.

------------------------------------------------------------------------

# 3.14 Security Test Mapping for Experimental Evaluation

The requirements allow Chapter 8 to evaluate concrete outcomes rather
than relying only on architectural claims.

  -----------------------------------------------------------------------
  Test ID                 Experiment              Expected outcome
  ----------------------- ----------------------- -----------------------
  ST-01                   Attempt decryption of   Failure
                          stolen package without  
                          intended recipient      
                          capability              

  ST-02                   Import package into     Rejected
                          wrong subsidiary        

  ST-03                   Modify signed manifest  Signature verification
                                                  failure

  ST-04                   Modify encrypted        AEAD/signature
                          payload                 validation failure

  ST-05                   Replay already imported Detected/rejected
                          package                 according to policy

  ST-06                   Attempt authentication  Rejected
                          replay                  

  ST-07                   Use expired/revoked     Rejected according to
                          certificate             locally available
                                                  policy

  ST-08                   Guess/change document   Unauthorized resource
                          ID                      denied

  ST-09                   Reuse view token for    Rejected
                          another user/document   

  ST-10                   Use expired view token  Rejected

  ST-11                   Request original PDF    No accessible resource
                          URL                     

  ST-12                   Request clean cached    No accessible resource
                          page URL                

  ST-13                   Remove permission       Subsequent request
                          during active view      denied

  ST-14                   Inject shell syntax     Input rejected/no
                          through page/profile    command execution
                          input                   

  ST-15                   Cause renderer failure  Temporary plaintext
                                                  cleaned

  ST-16                   Excessive page requests Rate-control behavior
                                                  observed

  ST-17                   Inspect audit records   Required events
                                                  present; prohibited
                                                  secrets absent

  ST-18                   Modify package target   Verification/import
                          company                 failure

  ST-19                   Attempt                 Rejected according to
                          document-version        policy
                          downgrade               

  ST-20                   Inspect browser         Original PDF not
                          response                delivered during normal
                                                  viewing
  -----------------------------------------------------------------------

Quantitative tests will additionally measure the performance cost of
these controls.

------------------------------------------------------------------------

# 3.15 Security Assumptions

The research relies on several explicit assumptions:

1.  Standard cryptographic algorithms are correctly implemented by
    established cryptographic libraries.
2.  Random-number generation provided by the operating system/approved
    library is cryptographically secure.
3.  Trusted root/signing material is provisioned through an authorized
    process.
4.  Hardware-token claims apply only when a genuine non-exportable
    cryptographic authenticator is deployed.
5.  CentOS, Apache, PHP, OpenSSL, Poppler, ImageMagick, MariaDB, and
    related dependencies are maintained according to organizational
    policy.
6.  The central package-signing environment is strongly protected.
7.  The prototype's performance results apply to the documented test
    hardware/software configuration and are not universal benchmarks.
8.  The security architecture reduces risk but does not guarantee
    protection after complete compromise of the trusted server or
    authorized display endpoint.

Explicit assumptions make the thesis claims falsifiable and prevent the
evaluation from implying protection outside the studied threat model.

------------------------------------------------------------------------

# 3.16 Privacy and Data-Minimization Considerations

Accountability mechanisms can themselves create privacy risks.

Audit records and personalized watermarks should contain only
information necessary for legitimate security purposes. A watermark may
use a staff code and non-secret view code rather than unnecessary
personal information.

Audit retention periods, access permissions, and investigation
procedures should be defined by organizational policy.

The research prototype should use synthetic staff identities and
non-confidential test documents whenever possible.

------------------------------------------------------------------------

# 3.17 Security Requirement Priorities

For implementation planning, requirements can be grouped into three
priorities.

### Critical

A failure would undermine the primary confidentiality, integrity,
authenticity, or authorization claims.

Examples include:

-   authenticated encryption;
-   package-signature verification;
-   recipient/subsidiary binding;
-   server-side encrypted master storage;
-   per-request authorization;
-   protected private keys;
-   no public original-PDF path.

### High

A failure materially increases exploitation or accountability risk.

Examples include:

-   short-lived view sessions;
-   XSS/SQL/command-injection defenses;
-   revocation checking;
-   protected temporary files;
-   rate limiting;
-   audit protection;
-   secure host configuration.

### Supporting

Controls improve defense in depth, operational resilience, or forensic
capability.

Examples include:

-   personalized watermark layout;
-   extended audit metadata;
-   anomaly detection;
-   advanced process isolation;
-   future forensic watermarking.

This prioritization does not remove requirements; it supports
implementation and evaluation planning.

------------------------------------------------------------------------

# 3.18 Relationship to the Next Chapters

Chapter 4 will transform these security requirements into a concrete
architecture.

For example:

``` text
Threat T-003: Package tampering
        ↓
Requirements SR-009–SR-014
        ↓
Chapter 4 control:
Signed package structure + strict verification
        ↓
Chapter 6 implementation:
concheron_sec_verify_package()
        ↓
Chapter 8 experiment:
ST-03 / ST-04
```

This traceability is important because it connects the research problem
to the implemented prototype and finally to empirical evidence.

The same method will be used throughout the thesis:

``` text
Research question
    ↓
Threat
    ↓
Security requirement
    ↓
Architecture control
    ↓
Implementation
    ↓
Experiment
    ↓
Measured result
    ↓
Research conclusion
```

------------------------------------------------------------------------

# 3.19 Chapter Summary

This chapter defined a lifecycle-oriented threat model for confidential
PDF distribution across isolated enterprise networks. It identified the
protected assets, legitimate and malicious actors, trust boundaries,
attacker capabilities, explicit non-assumptions, residual threats, and
twenty-five representative attack scenarios.

The analysis produced 105 numbered security requirements covering
package cryptography, authenticity, recipient restriction, replay
prevention, hardware authentication, certificate validation, server-side
storage, temporary plaintext, staff authorization, view sessions,
rendering, watermarking, web security, availability, auditing, the
native security extension, and CentOS host security.

A threat-to-requirement matrix and an initial security-test matrix
establish traceability from the threat model to later implementation and
experimental evaluation.

The next chapter, **Chapter 4 --- Proposed Secure Document
Architecture**, will describe how system components and protocols
satisfy these requirements.

------------------------------------------------------------------------

# Chapter 4 --- Proposed Secure Document Architecture

## 4.1 Introduction

This chapter presents the proposed architecture for secure distribution,
import, storage, and controlled viewing of confidential PDF documents
across isolated enterprise networks.

The architecture is derived from the threat model and security
requirements defined in Chapter 3. It does not depend on a single
protective mechanism. Instead, it combines authenticated encryption,
digital signatures, public-key infrastructure (PKI), hardware-assisted
authentication, key separation, server-side re-encryption,
resource-level authorization, protected rendering, personalized
watermarking, and audit logging.

The architecture follows a central principle:

> A confidential document should remain protected throughout its
> lifecycle, and each transition from one security domain to another
> should require explicit cryptographic or authorization validation.

The complete lifecycle is:

``` text
Central document creation
        ↓
Secure package generation
        ↓
Authorized recipient download
        ↓
Controlled package transfer
        ↓
Subsidiary package import
        ↓
Recipient cryptographic operation
        ↓
Temporary authenticated decryption
        ↓
Subsidiary server-key re-encryption
        ↓
Encrypted master storage
        ↓
Staff authentication
        ↓
Document authorization
        ↓
Server-side page rendering
        ↓
Personalized watermark
        ↓
Secure page API
        ↓
Canvas-based viewer
        ↓
Audit trail
```

------------------------------------------------------------------------

# 4.2 Architectural Design Principles

The architecture is based on the following principles.

## 4.2.1 Defense in Depth

No single control is assumed to be sufficient.

Encryption protects document confidentiality, but it does not replace
authentication. Authentication does not replace authorization.
Authorization does not prevent an authorized viewer from photographing a
screen. Watermarking provides attribution but does not replace
encryption.

Security therefore results from multiple complementary controls.

## 4.2.2 Least Privilege

Each component receives only the privileges required for its function.

Examples include:

-   ordinary staff do not receive server-storage private keys;
-   PHP application code does not receive hardware-token private keys;
-   the browser does not receive the original PDF;
-   the PDF renderer does not receive database-administration
    privileges;
-   the local system administrator is not automatically assigned
    business document-viewing permission.

## 4.2.3 Separation of Cryptographic Roles

Different cryptographic responsibilities use logically distinct keys.

``` text
Package signing        -> central signing key
Package recipient      -> SEC token/device key
Local document storage -> subsidiary server key
Staff authentication   -> staff certificate/key
```

This limits the impact of compromise and clarifies key lifecycle
responsibilities.

## 4.2.4 Fail-Closed Validation

If a critical validation fails, the document is not imported or
delivered.

Examples include:

-   invalid package signature;
-   wrong target subsidiary;
-   wrong recipient;
-   AEAD authentication failure;
-   expired/revoked certificate;
-   missing document permission;
-   expired view session.

## 4.2.5 Minimize Plaintext Exposure

The architecture attempts to minimize where and for how long plaintext
PDF data exists.

Persistent storage uses encryption. Plaintext is produced only in a
controlled processing context when required for rendering or import
validation.

## 4.2.6 Resource-Level Authorization

Network position, successful login, or knowledge of a document
identifier does not imply permission to view a document.

Authorization is evaluated for protected resources.

## 4.2.7 Auditability

Security-relevant actions should produce protected audit events without
exposing secrets.

------------------------------------------------------------------------

# 4.3 High-Level System Architecture

The proposed system consists of a central SEC environment and multiple
isolated subsidiary environments.

``` text
                         CENTRAL ORGANIZATION

+---------------------------------------------------------------+
|                                                               |
|  +----------------------+                                     |
|  | Central SEC Portal   |                                     |
|  +----------+-----------+                                     |
|             |                                                 |
|  +----------v-----------+       +--------------------------+  |
|  | Package Generation   |------>| Package Signing Service  |  |
|  +----------+-----------+       +--------------------------+  |
|             |                                                 |
|  +----------v-----------+       +--------------------------+  |
|  | Encrypted Packages   |       | Central Audit           |  |
|  +----------------------+       +--------------------------+  |
|                                                               |
|               Enterprise PKI / Trust Services                 |
+----------------------------+----------------------------------+
                             |
                        Private VPN
                             |
                    SEC Staff Workstation
                             |
                    Hardware SEC Token
                             |
                    Encrypted .sec package
                             |
                   Controlled transfer
                             |
                             v

                       SUBSIDIARY A

+---------------------------------------------------------------+
| Local isolated network                                        |
|                                                               |
| +----------------------+                                      |
| | ZF1 SEC Application  |                                      |
| +----------+-----------+                                      |
|            |                                                  |
| +----------v-----------+     +-----------------------------+  |
| | concheron_sec.so     |<--->| Local Trust / Server Keys   |  |
| +----------+-----------+     +-----------------------------+  |
|            |                                                  |
| +----------v-----------+     +-----------------------------+  |
| | Encrypted Master     |     | MariaDB                     |  |
| | Document Storage     |     | permissions/audit/sessions  |  |
| +----------+-----------+     +-----------------------------+  |
|            |                                                  |
| +----------v-----------+                                      |
| | Poppler Renderer     |                                      |
| +----------+-----------+                                      |
|            |                                                  |
| +----------v-----------+                                      |
| | Imagick Watermarking |                                      |
| +----------+-----------+                                      |
|            |                                                  |
| +----------v-----------+                                      |
| | Secure Page API      |                                      |
| +----------+-----------+                                      |
|            |                                                  |
| +----------v-----------+                                      |
| | Browser Canvas       |                                      |
| +----------------------+                                      |
+---------------------------------------------------------------+
```

Subsidiary B, Subsidiary C, and additional subsidiaries use the same
architectural pattern but possess different organizational identities
and cryptographic material.

------------------------------------------------------------------------

# 4.4 Trust Domains

The architecture separates trust into distinct domains.

## TD-01 --- Central Publishing Domain

Responsible for document preparation, package creation, package signing,
and distribution policy.

## TD-02 --- Central Authentication Domain

Responsible for verifying authorized SEC users/devices before package
download.

## TD-03 --- Portable Package Domain

The `.sec` file may exist on storage or transfer media that is not
inherently trusted.

The package must therefore protect itself cryptographically.

## TD-04 --- Subsidiary Import Domain

Responsible for verifying and importing packages intended for that
subsidiary.

## TD-05 --- Subsidiary Storage Domain

Responsible for protecting imported master documents using local
server-side encryption.

## TD-06 --- Staff Access Domain

Responsible for authenticating staff and evaluating document
permissions.

## TD-07 --- Rendering Domain

Responsible for converting authorized PDF pages into protected image
representations.

## TD-08 --- Browser Domain

Treated as a less-trusted presentation environment. It receives only
authorized rendered representations and temporary view authorization.

------------------------------------------------------------------------

# 4.5 PKI Architecture

A private enterprise PKI provides cryptographic identities.

A conceptual hierarchy is:

``` text
                   Concheron Root CA
                          |
          +---------------+----------------+
          |               |                |
          v               v                v
   Device/USB CA       Staff CA        Server CA
          |               |                |
          v               v                v
    SEC Token Cert   Staff Certs     Subsidiary Certs

             Separate Package-Signing Identity
```

The precise production hierarchy may use additional intermediate CAs or
separate offline roots depending on organizational policy.

## 4.5.1 Root CA

The root CA is a high-value trust anchor.

Recommended characteristics:

-   strongly protected;
-   normally offline;
-   limited administrative access;
-   used primarily to authorize subordinate issuing authorities;
-   subject to documented backup and recovery procedures.

## 4.5.2 Device/USB Certificates

Identify approved SEC cryptographic devices or their assigned holders.

Their private keys should preferably be non-exportable.

## 4.5.3 Staff Certificates

Used for staff authentication and authorization support.

In the selected architecture, staff certificates do **not** directly
decrypt master documents.

## 4.5.4 Server Certificates/Keys

Used for approved subsidiary server cryptographic functions.

The local server-storage protection key may be represented by a
certificate/private-key arrangement or another approved protected
key-management mechanism.

## 4.5.5 Package-Signing Identity

Used by the central publishing system to sign `.sec` packages.

Package signing is separate from recipient encryption.

------------------------------------------------------------------------

# 4.6 Key Responsibility Matrix

  ------------------------------------------------------------------------------
  Key               Location            Purpose          Must not be exposed to
  ----------------- ------------------- ---------------- -----------------------
  Root CA private   Highly              PKI trust        Application
  key               protected/offline                    servers/users

  Package-signing   Central protected   Sign `.sec`      Subsidiaries/users
  private key       service             packages         

  SEC token private Hardware token      Authentication / PHP/server filesystem
  key                                   recipient        
                                        operation        

  Subsidiary server Protected           Local master     Staff/browser
  key               subsidiary          protection       
                    environment                          

  Staff private key Staff authenticator Staff            Server/database
                                        authentication   

  Package CEK       Ephemeral/package   AES-GCM PDF      Browser/logs/database
                    operation           protection       
  ------------------------------------------------------------------------------

This matrix implements the key-separation requirements from Chapter 3.

------------------------------------------------------------------------

# 4.7 SEC Package Architecture

The secure package is a portable cryptographically protected container.

Example filename:

``` text
SEC-2026-0042-COMPANY-A.sec
```

The filename is for usability only and is not trusted for security
decisions.

A conceptual package is:

``` text
+--------------------------------------------------+
| Fixed Header                                     |
+--------------------------------------------------+
| Signed Manifest                                  |
+--------------------------------------------------+
| Recipient Information                            |
+--------------------------------------------------+
| Encrypted PDF Payload                            |
+--------------------------------------------------+
| Digital Signature                                |
+--------------------------------------------------+
```

## 4.7.1 Header

The header identifies the package format and structural lengths.

Example conceptual fields:

``` text
magic
format_version
flags
manifest_length
recipient_length
payload_length
signature_length
```

The parser validates lengths before using offsets.

## 4.7.2 Manifest

The signed manifest contains security-relevant metadata.

Example:

``` json
{
  "format": "CONCHERON-SEC",
  "format_version": 1,
  "package_id": "PKG-2026-000042",
  "document_id": "SEC-2026-0042",
  "document_version": "3",
  "classification": "CONFIDENTIAL",
  "publisher": "CONCHERON",
  "target_company_id": "COMPANY-A",
  "recipient_usb_certificate_id": "USB-CERT-000184",
  "created_at": "2026-10-01T12:00:00Z",
  "expires_at": "2026-11-01T12:00:00Z",
  "crypto_suite": "SEC-SUITE-1",
  "payload_type": "application/pdf"
}
```

The exact canonical representation must be specified before production
interoperability is claimed.

## 4.7.3 Encrypted Payload

The PDF is encrypted using a fresh CEK:

``` text
CEK = secure_random(256 bits)

ciphertext, authentication_tag =
    AES-256-GCM(CEK, nonce, PDF, authenticated_metadata)
```

Nonce uniqueness is mandatory.

## 4.7.4 Recipient Block

The recipient block contains the information needed for the authorized
recipient mechanism to recover or derive access to the CEK.

Conceptually:

``` text
recipient certificate/device identifier
key-protection algorithm
protected CEK
algorithm parameters
```

The final encoding depends on the hardware token and selected public-key
mechanism.

## 4.7.5 Digital Signature

The package-signing identity signs the defined package representation.

Conceptually:

``` text
signature =
    Sign(
        package_header
        || manifest
        || recipient_block
        || encrypted_payload
    )
```

The exact canonical signed bytes must be unambiguous.

------------------------------------------------------------------------

# 4.8 Package Generation Protocol

The central publisher performs:

``` text
1. Receive approved PDF
2. Assign document ID/version
3. Select target subsidiary
4. Select authorized recipient/token
5. Generate unique package ID
6. Generate random CEK
7. Generate valid unique GCM nonce
8. Encrypt PDF with AES-256-GCM
9. Protect CEK for recipient
10. Build manifest
11. Build package structure
12. Sign package
13. Store package
14. Record audit event
15. Make package available to authorized recipient
```

Critical failures terminate package creation.

------------------------------------------------------------------------

# 4.9 Central SEC USB Authentication

The SEC USB is modeled as a cryptographic authenticator, not ordinary
removable storage.

## 4.9.1 Authentication Sequence

``` text
SEC Staff            Portal              SEC Token
   |                    |                    |
   |---- login -------->|                    |
   |                    |                    |
   |<--- challenge -----|                    |
   |                    |                    |
   |------ challenge ----------------------->|
   |                                         |
   |             PIN activates private key   |
   |                                         |
   |<----- signed response ------------------|
   |                    |                    |
   |---- response ----->|                    |
   |                    |                    |
   | verify certificate |                    |
   | verify signature   |                    |
   | verify challenge   |                    |
   | verify policy      |                    |
   |                    |                    |
   |<--- authenticated -|                    |
```

The private key remains within the token.

## 4.9.2 Portal Checks

The portal verifies:

-   trusted certificate chain;
-   certificate validity;
-   revocation status;
-   device/staff assignment;
-   organization;
-   challenge freshness;
-   proof of private-key possession;
-   portal authorization.

Successful authentication does not automatically authorize every
package.

------------------------------------------------------------------------

# 4.10 Package Download Authorization

After authentication, the portal determines which documents the staff
member may download.

The decision can include:

``` text
staff identity
+ subsidiary identity
+ staff role
+ package/document policy
+ token/certificate status
+ package expiration
```

Download is audited with:

-   package ID;
-   document ID/version;
-   authenticated staff/device identity;
-   subsidiary;
-   timestamp;
-   result.

Secrets are excluded from logs.

------------------------------------------------------------------------

# 4.11 Controlled Transfer

After download, the `.sec` package may be transferred through approved
removable media or another controlled mechanism.

The transfer medium is not relied upon for document confidentiality.

This is important because the package should remain protected even if:

-   the USB storage is lost;
-   the file is copied;
-   the package is accidentally sent to another subsidiary.

The cryptographic package is therefore self-protecting within the stated
threat model.

------------------------------------------------------------------------

# 4.12 Subsidiary Import Architecture

The local import path is:

``` text
Browser upload
      |
      v
ZF1 Import Controller
      |
      v
Strict package handling
      |
      v
concheron_sec.so
      |
      +--> package signature verification
      +--> manifest validation
      +--> company/recipient policy
      +--> token operation
      +--> authenticated decryption
      |
      v
Controlled temporary PDF
      |
      v
Validation / metadata extraction
      |
      v
Server-key re-encryption
      |
      v
Encrypted master storage
      |
      v
MariaDB metadata + audit
```

------------------------------------------------------------------------

# 4.13 Import Verification Sequence

A package must pass the following sequence.

``` text
Step 1   Receive upload
Step 2   Enforce maximum size
Step 3   Store with randomized internal name
Step 4   Parse fixed package header
Step 5   Validate structural lengths
Step 6   Verify package format/version
Step 7   Verify trusted digital signature
Step 8   Parse trusted signed manifest
Step 9   Verify target subsidiary
Step 10  Verify package/document version policy
Step 11  Verify recipient identity
Step 12  Require authorized SEC token operation
Step 13  Recover CEK internally
Step 14  AES-GCM authenticate/decrypt payload
Step 15  Validate PDF/content policy
Step 16  Re-encrypt with subsidiary server key
Step 17  Commit document metadata
Step 18  Record import history/audit
Step 19  Remove temporary plaintext
```

The sequence is deliberately ordered so untrusted manifest fields are
not treated as trusted policy inputs before signature verification.

------------------------------------------------------------------------

# 4.14 Import Transaction Safety

Import should behave atomically from the application's perspective.

If a critical step fails:

``` text
no active document record
no persistent plaintext PDF
no exposed CEK
no partially authorized document
```

Database transactions should be used for metadata changes where
practical.

Filesystem operations require additional cleanup because database
rollback cannot automatically undo filesystem writes.

------------------------------------------------------------------------

# 4.15 Native PHP Security Extension Boundary

The `concheron_sec.so` extension provides a narrow native boundary
around sensitive cryptographic operations.

The ZF1 application should call high-level operations.

Examples:

``` php
concheron_sec_version();

concheron_sec_capabilities();

concheron_sec_inspect_package($packagePath);

concheron_sec_verify_package($packagePath);

concheron_sec_import($packagePath, $options);

concheron_sec_verify_staff_certificate($certificateData);

concheron_sec_decrypt_for_render($documentReference);
```

The exact production API will evolve during implementation.

The important rule is what should **not** be exposed:

``` php
// Dangerous conceptual APIs — avoid:
concheron_sec_get_private_key();
concheron_sec_get_server_key();
concheron_sec_get_document_key();
```

The extension should use established cryptographic libraries such as
OpenSSL rather than custom implementations of AES, RSA, or ECC.

------------------------------------------------------------------------

# 4.16 Server-Side Re-encryption

After package decryption, the original PDF is not persistently retained
in plaintext.

``` text
Package CEK
    |
    v
Temporary authenticated plaintext
    |
    +--> validation
    +--> optional initial rendering
    |
    v
Subsidiary server encryption
    |
    v
Encrypted master document
```

The local server-storage key is independent from the SEC USB key.

This means:

-   loss of a staff certificate does not require re-encryption of every
    document;
-   normal staff do not possess document-storage keys;
-   local at-rest protection remains under server control.

------------------------------------------------------------------------

# 4.17 Protected Storage Architecture on CentOS

Example:

``` text
/secure/sec/
├── documents/
│   ├── 000001/
│   │   └── master.enc
│   └── ...
├── pages/
│   ├── version-000991/
│   │   ├── normal/
│   │   └── high/
│   └── ...
├── temp/
└── trust/
```

These directories are outside Apache's document root.

Important properties:

-   restrictive Unix ownership and permissions;
-   no public Apache alias;
-   SELinux remains enabled;
-   only required services receive access;
-   temporary plaintext excluded from backup;
-   trust/key material receives stricter access than ordinary cache
    data.

------------------------------------------------------------------------

# 4.18 Staff Authentication Architecture

The selected staff-certificate model is:

> Staff certificates authenticate and identify staff; they do not
> directly decrypt the encrypted master PDF.

Conceptual sequence:

``` text
Staff
  |
  v
Application login
  |
  v
Certificate presented
  |
  v
Trust-chain validation
  |
  v
Validity check
  |
  v
Revocation check
  |
  v
Proof of private-key possession
  |
  v
Certificate -> staff mapping
  |
  v
Authenticated staff session
```

Document authorization occurs separately.

------------------------------------------------------------------------

# 4.19 Offline Certificate Revocation

Because a subsidiary may not have continuous internet access, revocation
data can be distributed through a signed update package.

Example:

``` text
concheron-cert-update.sec
|
├── metadata
├── trusted issuer updates
├── revoked-certificates.crl
├── certificate-policy.json
└── central digital signature
```

The subsidiary verifies the update before importing it.

Local policy defines:

-   update interval;
-   maximum acceptable age;
-   behavior when revocation information expires;
-   emergency procedure.

The architecture explicitly recognizes that disconnected revocation
cannot provide zero-delay central revocation.

------------------------------------------------------------------------

# 4.20 Document Authorization Model

Authentication establishes identity. Authorization determines document
access.

Conceptual database relationship:

``` text
staff
  |
  +------< document_permissions >------ document
```

A permission can include:

``` text
staff_id
document_id
can_view
valid_from
valid_until
role/group context
```

The page API checks authorization for every protected request.

------------------------------------------------------------------------

# 4.21 Secure View Session

Opening a document creates a temporary view session.

``` text
Authorized document open
        |
        v
Generate 256-bit random token
        |
        +--> raw token -> browser
        |
        +--> SHA-256(token) -> database
        |
        v
Bind:
staff + document + version + expiry
```

The view session contains:

-   staff ID;
-   document/version ID;
-   token hash;
-   non-secret display/view code;
-   creation time;
-   absolute expiration;
-   last activity;
-   status.

The raw token is not logged or watermarked.

------------------------------------------------------------------------

# 4.22 Secure Rendering Architecture

The original PDF is never intentionally served directly to the browser.

``` text
Browser page request
        |
        v
Secure Page API
        |
        +--> authenticate staff
        +--> validate view token
        +--> check document permission
        +--> validate page/profile
        +--> apply rate limit
        |
        v
Check protected clean-page cache
        |
        +---- cache hit --------------------+
        |                                   |
        | cache miss                        |
        v                                   |
Decrypt master temporarily                  |
        |                                   |
        v                                   |
Poppler / pdftoppm                          |
        |                                   |
        v                                   |
Clean protected page -----------------------+
        |
        v
Imagick personalized watermark
        |
        v
WebP/PNG response
        |
        v
Browser Canvas
```

------------------------------------------------------------------------

# 4.23 Clean Page Cache

Rendering large PDFs repeatedly is expensive.

The architecture therefore allows a protected clean page cache.

Example:

``` text
/secure/sec/pages/
    version-991/
        normal/
            page-000001.png
        high/
            page-000001.png
```

The cache is:

-   outside web root;
-   not directly accessible by URL;
-   keyed by immutable document version;
-   unpersonalized;
-   protected by filesystem permissions.

Personalized output is generated at response time and should not
overwrite the clean cache.

------------------------------------------------------------------------

# 4.24 Personalized Watermarking

A delivered page receives a server-side watermark.

Example:

``` text
CONCHERON — SEC
Company A
Employee: EMP-0041
Document: SEC-2026-000184
View: A72F91
2026-10-01 12:15:31 UTC
```

A repeated translucent watermark can be combined with a footer.

The watermark must not contain:

``` text
password
PIN
PHP session ID
CSRF token
raw view token
private key
CEK
```

The display/view code maps internally to the view session.

------------------------------------------------------------------------

# 4.25 Secure Page API

Example endpoint:

``` text
POST /sec-viewer/page
```

Example request:

``` json
{
  "document_id": 184,
  "version_id": 991,
  "page": 5,
  "profile": "normal",
  "view_token": "<temporary-token>"
}
```

Server validation:

``` text
1. authenticated staff session
2. verified staff certificate state
3. valid document/version
4. current document permission
5. valid view token
6. token bound to staff/document/version
7. token not expired
8. page in range
9. approved rendering profile
10. rate limit
```

Only then is a page returned.

------------------------------------------------------------------------

# 4.26 Browser Canvas Viewer

The browser uses a custom Canvas-based viewer.

The viewer supports:

-   continuous vertical scrolling;
-   placeholders for document pages;
-   lazy page loading;
-   zoom;
-   fit-to-width;
-   current-page tracking;
-   unloading distant pages;
-   re-fetching pages when needed.

Conceptually:

``` text
Page placeholder enters viewport
          |
          v
POST secure page request
          |
          v
Receive WebP/PNG
          |
          v
createImageBitmap()
          |
          v
Draw into Canvas
```

The browser does not require the original PDF.

Right-click blocking, Ctrl+S blocking, and similar JavaScript controls
may be implemented as deterrents but are not treated as security
boundaries.

------------------------------------------------------------------------

# 4.27 Rate Limiting and Extraction Resistance

The page API must support legitimate scrolling while limiting automated
bulk extraction.

A policy may consider:

``` text
staff identity
view session
document
time window
request rate
burst size
```

The purpose is not to guarantee that an authorized user can never
capture every page. Rather, it reduces high-speed automated extraction
and creates observable events.

------------------------------------------------------------------------

# 4.28 Audit Architecture

Central audit events include:

``` text
portal authentication
token authentication result
package creation
package download
document/version
recipient/subsidiary
timestamp
result
```

Subsidiary audit events include:

``` text
package upload
signature verification
recipient verification
import result
server re-encryption
certificate verification
document open
page request
permission denial
view-session creation/expiration
watermark view code
revocation update
key rotation
security-extension version change
```

Sensitive secrets are excluded.

------------------------------------------------------------------------

# 4.29 Database Architecture

Core tables include:

## `sec_documents`

``` text
id
document_code
title
classification
current_version
status
created_at
updated_at
```

## `sec_document_versions`

``` text
id
document_id
version_no
encrypted_storage_path
storage_key_id
page_count
file_size
file_hash
source_package_id
imported_by
imported_at
status
```

## `sec_document_permissions`

``` text
id
document_id
staff_id
can_view
valid_from
valid_until
```

## `sec_staff_certificates`

``` text
certificate_id
serial_number
staff_id
company_id
issuer
subject
valid_from
valid_until
status
thumbprint
```

## `sec_view_sessions`

``` text
id
document_version_id
staff_id
certificate_id
token_hash
display_code
created_at
expires_at
last_activity_at
status
client_ip
user_agent_hash
```

## `sec_audit_log`

``` text
id
event_id
event_type
document_id
staff_id
view_session_id
page_number
result
reason_code
created_at
```

Additional tables may include package history, server-key metadata, CRL
metadata, device registrations, and approved extension versions.

Private keys are not stored in ordinary database fields.

------------------------------------------------------------------------

# 4.30 End-to-End Package Distribution Sequence

``` text
Publisher       Central Portal       Token       Subsidiary
   |                  |                |              |
   |-- publish PDF -->|                |              |
   |                  | generate CEK   |              |
   |                  | encrypt PDF    |              |
   |                  | protect CEK    |              |
   |                  | sign package   |              |
   |                  |                |              |
Staff authenticates   |<---- challenge/sign -------->|
   |                  |                |              |
   |<-- .sec package -|                |              |
   |                  |                |              |
   |========== controlled transfer =================>|
   |                  |                |              |
   |                                   |<-- auth ---->|
   |                                   |              |
   |                                   |   verify pkg |
   |                                   |   unwrap CEK |
   |                                   |   decrypt    |
   |                                   |   re-encrypt |
   |                                   |   store      |
```

The diagram is conceptual: private-key operations remain inside the
appropriate protected component.

------------------------------------------------------------------------

# 4.31 End-to-End Viewing Sequence

``` text
Staff Browser       ZF1 App       MariaDB       Crypto       Renderer
     |                 |              |             |             |
     |-- open doc ---->|              |             |             |
     |                 |-- authz ---->|             |             |
     |                 |<-- allow ----|             |             |
     |                 | create view  |             |             |
     |<-- viewer/token-|              |             |             |
     |                 |              |             |             |
     |-- page 1 ------>|              |             |             |
     |                 |-- authz ---->|             |             |
     |                 |-- token ---->|             |             |
     |                 |<-- valid ----|             |             |
     |                 |--------------------------->| decrypt      |
     |                 |<---------------------------| temp PDF     |
     |                 |----------------------------------------->|
     |                 |<-----------------------------------------|
     |                 | watermark                                |
     |<-- WebP page ---|                                          |
     | draw Canvas     |                                          |
```

For cached pages, master decryption and PDF rendering can be skipped.

------------------------------------------------------------------------

# 4.32 Failure Handling

Security-sensitive failures return generic user-facing errors.

Examples:

``` text
Document cannot be opened.
Package verification failed.
Access denied.
Viewing session expired.
```

Protected diagnostic logs may contain technical reason codes but not
secrets.

Import failures must clean:

-   temporary upload files;
-   temporary plaintext;
-   partial rendered files;
-   uncommitted metadata.

------------------------------------------------------------------------

# 4.33 CentOS Security Boundary

The subsidiary prototype uses CentOS.

Conceptual deployment:

``` text
CentOS
├── Apache
├── PHP 7.4
│   ├── Zend Framework 1
│   └── concheron_sec.so
├── MariaDB
├── OpenSSL
├── Poppler / pdftoppm
├── ImageMagick / Imagick
└── /secure/sec/
    ├── documents/
    ├── pages/
    ├── temp/
    └── trust/
```

Important host controls include:

-   SELinux enforcing;
-   restrictive Unix permissions;
-   no `777` protected directories;
-   least-privilege service accounts;
-   no public `/secure/sec` alias;
-   controlled software updates;
-   protected logs;
-   fixed renderer path;
-   resource limits.

------------------------------------------------------------------------

# 4.34 Security Requirement Traceability

The architecture directly addresses the Chapter 3 requirements.

  Architectural control                 Principal requirements
  ------------------------------------- ------------------------
  AES-256-GCM package payload           SR-001--SR-005
  Recipient CEK protection              SR-006--SR-008, SR-018
  Package digital signature             SR-009--SR-014
  Target-subsidiary binding             SR-015--SR-019
  Package/version history               SR-020--SR-024
  Hardware token authentication         SR-025--SR-029
  PKI validation / offline revocation   SR-030--SR-035
  Server-key master encryption          SR-036--SR-040
  Controlled plaintext processing       SR-041--SR-046
  Per-page staff authorization          SR-047--SR-053
  Hashed short-lived view token         SR-054--SR-060
  Protected server-side rendering       SR-061--SR-067
  Personalized watermark                SR-068--SR-071
  Web application security              SR-072--SR-081
  Rate/resource controls                SR-082--SR-086
  Audit architecture                    SR-087--SR-094
  Native extension boundary             SR-095--SR-099
  CentOS host controls                  SR-100--SR-105

This mapping will later be extended to implementation modules and
experimental test results.

------------------------------------------------------------------------

# 4.35 Architectural Security Analysis

## 4.35.1 Stolen Package

An attacker who obtains only the `.sec` file receives ciphertext and
recipient-bound protected key material. The design intends that package
possession alone is insufficient to recover plaintext.

## 4.35.2 Wrong Subsidiary

The signed target-company identity is checked against local
configuration, while recipient-specific cryptographic protection
provides an additional boundary.

## 4.35.3 Tampered Package

The digital signature protects publisher-authenticated package data and
AES-GCM authenticates protected payload processing. Failed verification
stops import.

## 4.35.4 Stolen Staff Credential

A staff credential can threaten staff authentication, but it is not
itself the server master-document storage key. Document authorization
and certificate/revocation policy still apply.

## 4.35.5 Stolen Server Storage

Encrypted master files remain protected by the server-storage key,
subject to the security of that key and cryptographic implementation.

## 4.35.6 Browser Compromise

A compromised authorized browser may capture rendered pages or temporary
view authorization. Short session lifetime, XSS controls, server
authorization, watermarking, and auditing reduce but do not eliminate
this risk.

## 4.35.7 Root-Level Server Compromise

A complete privileged server compromise is a major residual risk because
the server must process plaintext for legitimate rendering.
Hardware-backed server keys and stronger isolation can reduce risk but
cannot make a fully compromised trusted processing environment harmless.

------------------------------------------------------------------------

# 4.36 Performance Considerations

The strongest performance costs are expected in:

-   package encryption/decryption;
-   PDF rendering;
-   high-resolution page generation;
-   watermarking;
-   concurrent page delivery.

Several architectural choices reduce repeated cost:

### Protected Clean-Page Cache

Render a document version once per resolution profile and reuse the
protected clean image.

### Lazy Loading

Load only pages near the browser viewport.

### Canvas Unloading

Discard distant client-side canvases to reduce browser memory.

### Resolution Profiles

Use normal resolution for common zoom levels and high resolution only
when needed.

### Server-Side Cache by Immutable Version

A new document version receives a new cache namespace, preventing stale
page reuse.

These optimizations will be evaluated experimentally rather than assumed
to be cost-free.

------------------------------------------------------------------------

# 4.37 Architectural Limitations

The architecture has explicit limitations.

It cannot guarantee prevention of:

-   screenshots;
-   photographs;
-   screen recording;
-   manual transcription;
-   extraction by a fully compromised authorized endpoint;
-   plaintext observation by a fully compromised trusted rendering
    server.

It also cannot provide instantaneous certificate revocation in a
completely disconnected subsidiary unless an alternative real-time
communication path exists.

Therefore, the architecture should be described as reducing exposure,
enforcing controlled access, separating cryptographic responsibilities,
and improving accountability---not as providing absolute DRM.

------------------------------------------------------------------------

# 4.38 Expected Research Contribution

The architectural contribution is the integration of established
security mechanisms into a complete document lifecycle for an isolated
enterprise environment.

The proposed combination includes:

``` text
recipient-bound encrypted package
        +
central digital signature
        +
hardware-assisted authentication
        +
offline-capable PKI validation
        +
separated local storage key
        +
staff certificate authentication
        +
per-resource authorization
        +
server-side rendering
        +
personalized watermarking
        +
auditable short-lived viewing sessions
```

The novelty claim of the thesis should remain conservative. The research
contribution is primarily the architecture, its systematic
threat-to-control traceability, prototype implementation, and empirical
evaluation.

------------------------------------------------------------------------

# 4.39 Chapter Summary

This chapter presented the proposed secure document architecture derived
from the threat model and 105 security requirements established in
Chapter 3.

The architecture separates central publishing, portable package
protection, subsidiary import, local encrypted storage, staff
authentication, authorization, rendering, browser presentation, and
auditing into explicit trust domains.

A `.sec` package protects the PDF using authenticated symmetric
encryption, binds key access to an intended recipient mechanism, and
uses a central digital signature to establish package authenticity.
Authorized import produces only temporary plaintext before the document
is re-encrypted under a subsidiary server key.

Staff certificates are used for authentication and authorization rather
than direct master-document decryption. During viewing, the server
re-evaluates document permission, validates a short-lived view session,
renders pages server-side, applies personalized watermarks, and sends
page representations to a Canvas-based browser viewer rather than
routinely sending the original PDF.

The next chapter, **Chapter 5 --- Cryptographic and Key-Management
Design**, will specify the cryptographic protocol in greater detail,
including algorithm choices, CEK generation, nonce handling, package
signatures, recipient key protection, certificate validation, key
lifecycle, rotation, revocation, recovery, and cryptographic failure
behavior.

------------------------------------------------------------------------

# Chapter 5 --- Cryptographic and Key-Management Design

## 5.1 Introduction

This chapter specifies the cryptographic and key-management design for
the proposed secure document architecture. The design uses established
cryptographic primitives and separates distribution protection from
subsidiary storage protection. It does not introduce new cryptographic
algorithms.

The principal design is:

``` text
Distribution:
PDF + random CEK -> AES-256-GCM -> ciphertext
CEK -> recipient public-key mechanism -> protected CEK
package -> central signing key -> digital signature

After authorized import:
authenticated plaintext PDF + random local DEK -> AES-256-GCM -> encrypted master
local DEK -> subsidiary KEK/server key -> protected DEK
package CEK -> destroyed when no longer required
```

This double-envelope model gives each document independent symmetric key
material while permitting higher-level keys to be rotated without
necessarily re-encrypting every large PDF.

## 5.2 Cryptographic Design Goals

The cryptographic subsystem shall provide confidentiality, integrity,
publisher authenticity, recipient restriction, key separation,
replay/downgrade resistance, manageable key rotation, and fail-closed
processing.

Cryptographic keys shall not be exposed to components that do not
require them. In particular, token private keys shall remain inside the
cryptographic token where supported; staff authentication keys shall not
decrypt document masters; and browsers shall never receive document
encryption keys.

## 5.3 Cryptographic Roles

  ------------------------------------------------------------------------
  Key                   Symbol           Scope            Purpose
  --------------------- ---------------- ---------------- ----------------
  Root CA key           K_ROOT           PKI              Establish trust
                                                          hierarchy

  Package-signing key   K_SIGN           Central          Sign secure
                                                          packages

  SEC token private key K_TOKEN          Device/user      Authentication
                                                          and recipient
                                                          operation

  Package               K_CEK            One package      Encrypt
  content-encryption                                      distributed PDF
  key                                                     

  Subsidiary            K_KEK            Subsidiary/key   Protect local
  key-encryption key                     version          document DEKs

  Local                 K_DEK            One document     Encrypt local
  document-encryption                    version          master
  key                                                     

  Staff authentication  K_STAFF          Staff identity   Authenticate
  key                                                     staff
  ------------------------------------------------------------------------

No key should silently assume another role.

## 5.4 Package Content Encryption

For each package, the central system generates a fresh 256-bit CEK using
an approved cryptographically secure random-number generator.

Let:

-   `P` be the PDF plaintext;
-   `K_CEK` be the 256-bit package key;
-   `N` be the GCM nonce;
-   `A` be authenticated associated data;
-   `C` be ciphertext; and
-   `T` be the authentication tag.

The conceptual operation is:

``` text
(C, T) = AES-256-GCM-ENC(K_CEK, N, P, A)
```

Authenticated decryption is:

``` text
P = AES-256-GCM-DEC(K_CEK, N, C, A, T)
```

If authentication fails, `P` must not be accepted.

## 5.5 GCM Nonce Management

Nonce uniqueness is mandatory for GCM security. The prototype should use
the conventional 96-bit nonce length unless the selected cryptographic
library and documented protocol specify otherwise.

Because each package receives a fresh random CEK, the architecture
already reduces the possibility of nonce reuse under the same key.
Nevertheless, nonce generation remains explicit protocol state.

A nonce shall never be deliberately reused with the same CEK.

The nonce is not required to be secret and may be stored in the package.

## 5.6 Associated Data

AEAD associated data allows selected metadata to be authenticated
without encrypting it.

The final package specification must define exactly which bytes are
supplied as AAD. The definition must be deterministic and versioned.

No security decision should depend on unsigned or unauthenticated
metadata.

## 5.7 Recipient Key Protection

The CEK is protected for the intended recipient using an approved
public-key/key-establishment mechanism supported by the selected
cryptographic token.

One candidate, where RSA hardware and policy permit it, is RSAES-OAEP
with SHA-256:

``` text
W = RSA-OAEP-ENC(PK_TOKEN, K_CEK)
```

where `W` is the protected CEK.

However, the thesis architecture does not require RSA specifically. A
production token may support an approved ECC/KEM/key-agreement mechanism
instead. The deployed algorithm must be explicitly identified by the
package cryptographic suite.

The important security property is:

> Recovery of the package CEK requires the authorized recipient
> cryptographic capability.

## 5.8 Hardware Token Private-Key Boundary

A production SEC token should use a non-exportable private key.

Conceptually:

``` text
Host provides protected-key operation input
            |
            v
       SEC hardware token
            |
       private-key operation
            |
            v
Host receives operation result
```

The host application should not receive `K_TOKEN`.

A PIN or approved activation factor controls token use when supported.

## 5.9 Package Digital Signature

The package is digitally signed by a central signing identity.

Where RSA is selected, RSASSA-PSS with SHA-256 is an appropriate
standards-based candidate. Another approved signature scheme may be used
if documented by the cryptographic suite.

Conceptually:

``` text
S = SIGN(K_SIGN, H(SignedPackageBytes))
```

The verifier checks:

``` text
VERIFY(PK_SIGN, SignedPackageBytes, S)
```

Signature failure terminates import.

## 5.10 Signed Representation

The exact signed representation must be unambiguous.

A conceptual construction is:

``` text
SignedPackageBytes =
    FixedHeader
    || CanonicalManifest
    || RecipientBlock
    || PayloadCryptoMetadata
    || Ciphertext
    || AuthenticationTag
```

Lengths, encoding, integer representation, field order, character
encoding, and canonicalization rules must be formally specified.

This prevents two implementations from interpreting the same signed
bytes differently.

## 5.11 Canonical Manifest

JSON is convenient for metadata but ordinary JSON permits representation
differences such as whitespace and field ordering. Some parsers may also
behave unexpectedly with duplicate member names.

The production package format must therefore define a deterministic
representation.

Possible approaches include:

1.  a formally specified canonical JSON profile;
2.  an established JSON canonicalization standard; or
3.  a deterministic binary serialization.

The prototype must reject ambiguous or duplicate security-critical
fields.

The earlier PHP-extension prototype must not claim full
canonical-manifest support until this behavior is implemented and
tested.

## 5.12 Cryptographic Suite Identifier

Packages shall identify an approved cryptographic suite.

Example:

``` text
SEC-SUITE-1
```

A suite definition should specify at least:

``` text
payload encryption
AEAD nonce length
authentication tag length
recipient-key mechanism
hash algorithm
signature mechanism
manifest encoding
key sizes
```

Unknown or disabled suites are rejected.

Algorithm agility must not permit downgrade to insecure algorithms.

## 5.13 Package Key Lifecycle

The CEK lifecycle is intentionally short:

``` text
Generate CEK
    ↓
Encrypt PDF
    ↓
Protect CEK for recipient
    ↓
Package distribution
    ↓
Authorized recipient recovery
    ↓
Authenticated payload decryption
    ↓
Local re-encryption
    ↓
Destroy CEK when no longer required
```

The CEK shall not be placed in ordinary database records, logs,
watermarks, browser responses, or application diagnostics.

## 5.14 Local Storage Envelope Encryption

After successful package import, the distribution CEK is not reused as
the long-term subsidiary storage key.

Instead, the subsidiary generates a fresh random document-encryption
key:

``` text
K_DEK = CSPRNG(256 bits)
```

The authenticated PDF is encrypted:

``` text
(C_local, T_local) =
    AES-256-GCM-ENC(K_DEK, N_local, PDF, A_local)
```

The DEK is then protected by the subsidiary KEK:

``` text
W_local = WRAP(K_KEK, K_DEK)
```

Persistent storage contains the encrypted master, GCM parameters,
protected DEK, and key-version metadata---not plaintext PDF or an
unprotected DEK.

## 5.15 Benefits of the DEK/KEK Model

A unique DEK per document version provides isolation between stored
documents.

The long-lived subsidiary KEK protects small DEKs rather than directly
encrypting every large PDF.

When rotating the KEK, the system can, where the selected wrapping
design permits:

``` text
old KEK -> unwrap DEK -> new KEK -> re-wrap DEK
```

The large encrypted PDF does not necessarily need to be decrypted and
encrypted again.

This reduces rotation cost and plaintext exposure.

## 5.16 Server-Key Versioning

Each protected DEK must identify the KEK version needed to recover it.

Conceptual metadata:

``` text
storage_key_id: "COMPANY-A-KEK-2026-02"
algorithm: "..."
created_at: "..."
status: "ACTIVE"
```

The system may retain old KEKs in protected decrypt-only state until all
associated DEKs have been migrated.

## 5.17 KEK Rotation

A planned rotation can follow:

``` text
1. Generate/provision new KEK
2. Assign new key version
3. Mark new key ACTIVE for encryption/wrapping
4. Mark previous key DECRYPT_ONLY
5. Re-wrap document DEKs
6. Verify migrated DEKs
7. Confirm no remaining dependency
8. Retire old KEK according to policy
9. Audit the operation
```

Rotation must be resumable and auditable.

## 5.18 Emergency Key Compromise

A suspected KEK compromise requires a different procedure from ordinary
rotation.

Possible actions include:

-   immediately stop new use of the affected key;
-   identify all document versions protected by it;
-   provision a replacement;
-   re-wrap or re-encrypt affected material as required by the
    compromise scenario;
-   preserve incident evidence;
-   revoke related certificates if applicable;
-   audit all actions.

If an attacker obtained both encrypted master files and the
corresponding usable KEK, simple future key rotation cannot
retroactively restore confidentiality for already copied material.

## 5.19 Package-Signing Key Lifecycle

The package-signing private key is a high-value central asset.

Controls should include:

-   protected generation;
-   limited signing-service access;
-   key identifier/version;
-   backup/recovery according to policy;
-   certificate validity;
-   rotation;
-   compromise-response procedure;
-   audit logging.

Subsidiaries may need to trust both current and previous signing
certificates during a controlled transition.

## 5.20 PKI Validation

Certificate validation includes:

``` text
certificate parsing
        ↓
trusted path construction
        ↓
signature validation
        ↓
validity period
        ↓
key usage / extended key usage
        ↓
organizational binding
        ↓
revocation state
        ↓
application policy
```

A certificate is not trusted merely because it is syntactically valid.

## 5.21 Certificate Purpose Separation

Certificates should be issued with purposes appropriate to their roles.

Conceptually:

``` text
SEC token certificate -> authentication / recipient operation
staff certificate     -> staff authentication
server certificate    -> approved server operation
signing certificate   -> package signing
```

The verifier must enforce expected purpose rather than accepting any
certificate from the enterprise PKI for every operation.

## 5.22 Offline Revocation

Isolated subsidiaries use locally available trusted revocation
information.

Conceptually:

``` text
Central PKI
    |
    v
Signed revocation update
    |
controlled transfer
    |
    v
Subsidiary trust store
```

The local system records:

``` text
CRL/update version
thisUpdate
nextUpdate / policy expiry
import time
issuer
signature verification result
```

Policy defines what happens when revocation information becomes stale.

## 5.23 Challenge-Response Authentication

A fresh unpredictable challenge prevents simple replay.

Conceptually:

``` text
R = secure_random(challenge_length)

response = TOKEN_SIGN(K_TOKEN, Context || R)

VERIFY(PK_TOKEN, Context || R, response)
```

`Context` should bind the operation to the intended protocol/domain
where appropriate.

The challenge is short-lived and cannot be successfully reused for a
separate authentication event.

## 5.24 Staff Authentication Key

The staff private key authenticates the staff identity.

It does not become:

``` text
package CEK
local document DEK
subsidiary KEK
package-signing key
```

After authentication, application authorization determines which
documents the staff member may view.

## 5.25 Cryptographic Failure Behavior

Cryptographic processing must fail closed.

The following conditions cause rejection:

-   invalid package signature;
-   unknown cryptographic suite;
-   malformed cryptographic metadata;
-   recipient mismatch;
-   wrong key;
-   OAEP/KEM/key-recovery failure;
-   GCM authentication failure;
-   invalid certificate path;
-   expired certificate where policy forbids use;
-   revoked certificate;
-   stale revocation data where policy requires failure;
-   prohibited package/document downgrade.

User-facing messages should remain generic while protected logs record
safe reason codes.

## 5.26 Plaintext Handling

Successful authenticated decryption creates a temporary confidentiality
boundary.

Plaintext must not be:

-   written to the web root;
-   included in logs;
-   returned in diagnostic errors;
-   backed up unintentionally;
-   retained longer than necessary.

Where possible, temporary data should use protected storage and
restrictive permissions. The thesis recognizes that guaranteed physical
erasure from SSDs and journaled filesystems is difficult; encrypted
temporary storage and cryptographic erasure can provide stronger
operational properties.

## 5.27 Random-Number Generation

All cryptographic random values shall originate from an approved CSPRNG.

This includes:

-   CEKs;
-   DEKs;
-   GCM nonces when randomly generated;
-   authentication challenges;
-   view tokens;
-   cryptographic salts where applicable;
-   safe randomized internal filenames where security depends on
    unpredictability.

Application-level pseudo-random generators intended for simulation or
ordinary programming must not generate cryptographic keys.

## 5.28 Key Storage

Key storage is role-dependent.

  Key                   Preferred storage
  --------------------- -----------------------------------------------------
  Root CA               Offline/HSM or equivalent strongly protected system
  Package-signing key   HSM/protected signing service where practical
  Token key             Non-exportable hardware token
  Staff key             Approved authenticator/token
  Subsidiary KEK        Protected server key store/HSM/TPM where practical
  CEK                   Ephemeral protected process context
  DEK                   Persisted only in wrapped/protected form

The prototype may use software-protected server keys where hardware is
unavailable, but this limitation must be documented in the evaluation.

## 5.29 Backup and Recovery

Availability requires controlled recovery of long-lived keys.

Backup is generally relevant to:

-   CA keys;
-   package-signing keys;
-   subsidiary KEKs.

Ephemeral CEKs do not require long-term backup after successful import,
provided the package/local storage design no longer depends on them.

Recovery procedures must themselves be protected and tested. An
encrypted document whose only usable KEK is permanently lost may become
unrecoverable.

## 5.30 Key Destruction

Key destruction occurs only after confirming that no required data still
depends on the key.

For KEK retirement:

``` text
enumerate dependencies
verify re-wrapping/migration
test document recovery
approve retirement
destroy retired key according to policy
audit destruction
```

Deleting metadata before completing migration can create permanent data
loss.

## 5.31 Cryptographic Metadata Database Model

A conceptual storage-key table is:

``` text
sec_server_keys
---------------
id
key_identifier
key_version
algorithm
status
created_at
activated_at
retired_at
```

The database stores identifiers and lifecycle metadata, not plaintext
private key material.

A document version may contain:

``` text
storage_key_id
wrapped_dek
nonce
authentication_tag
encryption_algorithm
```

Sensitive binary values require appropriate encoding/storage types and
access controls.

## 5.32 Cryptographic API Boundary

The native PHP extension should expose high-level operations.

Preferred conceptual interface:

``` php
concheron_sec_verify_package($packagePath);
concheron_sec_import($packagePath, $options);
concheron_sec_reencrypt_document($documentReference);
concheron_sec_decrypt_for_render($documentReference);
```

Avoid APIs such as:

``` php
concheron_sec_get_cek();
concheron_sec_export_token_private_key();
concheron_sec_get_server_private_key();
```

Keeping sensitive key operations below the PHP application boundary
reduces accidental exposure.

## 5.33 Cryptographic Audit Events

Audit events include:

-   signing-key version used;
-   package cryptographic suite;
-   package verification result;
-   certificate-validation result;
-   revocation-data version;
-   local KEK version used;
-   key rotation;
-   key retirement;
-   cryptographic failure reason code.

Audit records exclude key material.

## 5.34 Requirement Traceability

  Cryptographic control               Chapter 3 requirements
  ----------------------------------- ------------------------
  AES-256-GCM payload                 SR-001--SR-005
  Recipient CEK protection            SR-006--SR-008, SR-018
  Digital signature                   SR-009--SR-014
  Signed company/recipient data       SR-015--SR-019
  Authenticated version metadata      SR-020--SR-024
  Hardware token                      SR-025--SR-029
  Certificate/revocation validation   SR-030--SR-035
  Local DEK/KEK storage               SR-036--SR-040
  Plaintext minimization              SR-041--SR-046
  Staff key separation                SR-047--SR-048
  Native cryptographic boundary       SR-095--SR-099

## 5.35 Cryptographic Test Plan

The experimental evaluation should include negative as well as positive
tests.

  -----------------------------------------------------------------------
  Test                   Modification/condition   Expected result
  ---------------------- ------------------------ -----------------------
  CT-01                  Correct package and      Successful
                         recipient                authenticated import

  CT-02                  One-byte ciphertext      Reject
                         modification             

  CT-03                  Authentication-tag       Reject
                         modification             

  CT-04                  Signed manifest          Reject
                         modification             

  CT-05                  Signature modification   Reject

  CT-06                  Wrong recipient private  CEK recovery/import
                         key                      fails

  CT-07                  Wrong subsidiary         Reject
                         identifier               

  CT-08                  Unknown crypto suite     Reject

  CT-09                  Malformed nonce metadata Reject

  CT-10                  Expired certificate      Reject according to
                                                  policy

  CT-11                  Revoked certificate      Reject using available
                                                  revocation data

  CT-12                  Stale CRL                Apply documented
                                                  stale-data policy

  CT-13                  Duplicate package ID     Detect/reject according
                                                  to policy

  CT-14                  Prohibited old document  Reject
                         version                  

  CT-15                  Wrong local KEK          Local master recovery
                                                  fails

  CT-16                  Corrupted wrapped DEK    Recovery fails

  CT-17                  Rotate KEK and re-wrap   Document remains
                         DEK                      recoverable

  CT-18                  Retire old KEK after     No active document
                         migration                depends on old key

  CT-19                  Inspect PHP/application  No raw keys/CEKs/DEKs
                         logs                     

  CT-20                  Inspect browser traffic  No document encryption
                                                  keys
  -----------------------------------------------------------------------

## 5.36 Performance Measurements

Cryptographic evaluation should measure:

-   package encryption time;
-   package signature time;
-   package verification time;
-   recipient-key operation time;
-   AES-GCM decryption time;
-   local AES-GCM re-encryption time;
-   DEK wrapping/unwrapping time;
-   KEK rotation/re-wrapping time.

Measurements should be performed for documented document sizes and
hardware configurations. Results must be reported as observed
measurements rather than assumed performance.

## 5.37 Security Limitations

Strong cryptography cannot protect plaintext after a fully compromised
trusted endpoint obtains legitimate access.

Similarly:

-   a compromised signing key can create apparently authentic packages;
-   a compromised KEK plus copied ciphertext can expose local masters;
-   a compromised token plus its activation factor may enable
    unauthorized recipient operations;
-   poor randomness can undermine otherwise strong algorithms;
-   implementation bugs can invalidate protocol assumptions.

Consequently, key protection, software security, operational controls,
and monitoring remain necessary.

## 5.38 Design Summary

The proposed cryptographic architecture uses two independent
envelope-encryption stages.

### Distribution envelope

``` text
PDF
 |
AES-256-GCM with random K_CEK
 |
Encrypted payload
 |
K_CEK protected for intended recipient
 |
Central digital signature
```

### Subsidiary storage envelope

``` text
Authenticated imported PDF
 |
AES-256-GCM with random K_DEK
 |
Encrypted local master

K_DEK
 |
protected by versioned subsidiary K_KEK
 |
Wrapped DEK
```

This design separates recipient distribution credentials from persistent
local-storage protection and enables manageable local key rotation.

## 5.39 Chapter Summary

This chapter specified the cryptographic and key-management design of
the proposed system. The architecture uses established authenticated
encryption, public-key protection, digital signatures, PKI validation,
and envelope encryption rather than custom cryptographic primitives.

Each distributed package receives a fresh CEK. The PDF payload is
protected using AES-256-GCM, while the CEK is protected for the intended
recipient. A central signing identity authenticates the package and its
security-relevant metadata.

After successful import, the distribution CEK is not reused for
long-term storage. The subsidiary generates a fresh per-document-version
DEK, encrypts the local master using authenticated encryption, and
protects that DEK under a versioned subsidiary KEK. This additional
envelope layer improves key isolation and simplifies planned KEK
rotation.

The design also defines certificate validation, offline revocation,
challenge-response authentication, cryptographic failure behavior, key
backup and recovery, key destruction, audit requirements, and a
twenty-test cryptographic evaluation plan.

The next chapter, **Chapter 6 --- Prototype Implementation**, should map
this architecture to the actual CentOS, PHP 7.4, Zend Framework 1,
MariaDB, OpenSSL, `concheron_sec.so`, Poppler, Imagick, and JavaScript
implementation.

------------------------------------------------------------------------

# Chapter 6 --- Prototype Implementation

## 6.1 Introduction

This chapter describes the prototype implementation of the secure
document architecture developed in Chapters 3--5. The implementation is
based on the organization's legacy enterprise stack and is intentionally
designed to demonstrate that the proposed security architecture can be
integrated without replacing the complete business application.

The prototype environment is based on:

``` text
Operating system:       CentOS / Linux
Web server:             Apache HTTP Server
Application language:   PHP 7.4
Framework:              Zend Framework 1 (ZF1)
Database:               MariaDB
Native security layer:  concheron_sec.so prototype
Cryptographic library:  OpenSSL
PDF renderer:           Poppler / pdftoppm
Image processing:       ImageMagick / Imagick
Browser viewer:         JavaScript + HTML5 Canvas
```

A critical distinction is maintained throughout this chapter between:

1.  architecture implemented in the application prototype;
2.  partially implemented native-extension functionality; and
3.  production cryptographic functions that remain future implementation
    work.

This distinction prevents the thesis from claiming experimental
implementation of security functions that have only been designed.

------------------------------------------------------------------------

# 6.2 Prototype Objectives

The prototype has the following implementation objectives:

-   demonstrate integration with an existing ZF1 application;
-   implement the secure document data model;
-   implement document authorization;
-   implement short-lived view sessions;
-   implement protected server-side page delivery;
-   implement server-side PDF rendering;
-   implement personalized watermarking;
-   implement a Canvas-based lazy-loading viewer;
-   establish the native PHP-extension API boundary;
-   demonstrate package-signature/policy verification design;
-   prepare interfaces for hardware-token and authenticated-decryption
    operations;
-   create an implementation suitable for later security and performance
    evaluation.

The prototype is not presented as a production-certified cryptographic
product.

------------------------------------------------------------------------

# 6.3 Implementation Status Model

To make implementation claims precise, the following status terminology
is used.

  ---------------------------------------------------------------------
  Status                             Meaning
  ---------------------------------- ----------------------------------
  Implemented                        Function exists in the prototype
                                     design/code and can be evaluated
                                     when deployed

  Prototype / Partial                Some implementation exists, but
                                     important security or
                                     interoperability work remains

  Designed                           Architecture and interface are
                                     defined but complete
                                     implementation is not yet
                                     available

  Future                             Outside the current prototype
                                     scope
  ---------------------------------------------------------------------

This status model is particularly important for the native security
extension.

------------------------------------------------------------------------

# 6.4 Prototype Component Status

  ---------------------------------------------------------------------
  Component                          Status
  ---------------------------------- ----------------------------------
  ZF1 application integration        Implemented/prototype
  architecture                       

  MariaDB security schema            Designed/prototype

  Document authorization service     Implemented design

  View-session design                Implemented design

  Secure page controller             Implemented design

  Poppler rendering integration      Implemented sample

  Imagick personalized watermark     Implemented sample

  Canvas lazy-loading viewer         Implemented sample

  Package structural verification    Partial native prototype

  Trusted package-signing-key        Partial native prototype
  configuration                      

  Signed manifest/policy checks      Partial native prototype

  Hardware SEC token discovery       Designed

  Non-exportable token private-key   Designed
  operation                          

  Recipient CEK recovery             Designed

  AES-GCM package payload decryption Designed

  Local DEK/KEK envelope             Designed
  implementation                     

  Production canonical package       Designed
  encoding                           

  Production extension               Not yet experimentally
  compilation/deployment validation  demonstrated
  ---------------------------------------------------------------------

The thesis evaluation must use only actually implemented functions for
measured claims.

------------------------------------------------------------------------

# 6.5 CentOS Deployment Layout

The subsidiary prototype is organized so protected document material is
separated from the public web directory.

Example:

``` text
/var/www/
└── application/
    ├── application/
    ├── library/
    └── public/

/secure/sec/
├── documents/
├── pages/
├── temp/
├── packages/
├── trust/
└── keys/

/etc/php.d/
└── 50-concheron-sec.ini
```

The `/secure/sec` hierarchy is not exposed as an Apache document
directory.

The exact production paths may vary, but the security property remains
constant: protected masters, clean page caches, packages, trust
material, and temporary plaintext must not receive direct public URLs.

------------------------------------------------------------------------

# 6.6 Unix Ownership and Permissions

A conceptual deployment uses restricted service ownership.

Example:

``` text
/secure/sec/documents   application-readable/writable as required
/secure/sec/pages       renderer/application controlled
/secure/sec/temp        application controlled, restrictive
/secure/sec/trust       application-readable, admin-writable
/secure/sec/keys        highly restricted
```

World-writable permissions such as `0777` are not appropriate for
protected SEC storage.

The exact Unix account names depend on the CentOS/Apache/PHP-FPM
deployment.

SELinux should remain enforcing. Required application access should be
granted through appropriately scoped contexts/policy rather than
disabling SELinux.

------------------------------------------------------------------------

# 6.7 Apache Boundary

Apache serves the application but does not directly serve the protected
storage hierarchy.

Conceptually:

``` apache
# Public application directory only.
DocumentRoot "/var/www/application/public"
```

No alias should expose:

``` text
/secure/sec/documents
/secure/sec/pages
/secure/sec/temp
/secure/sec/keys
```

The application page controller becomes the only intended HTTP path for
protected rendered content.

------------------------------------------------------------------------

# 6.8 PHP 7.4 Native Extension Integration

The native extension is loaded through PHP configuration.

Conceptually:

``` ini
extension=concheron_sec.so

concheron_sec.company_id=COMPANY-A
concheron_sec.trusted_signing_key=/etc/concheron/trust/sec-package-signing-public.pem
concheron_sec.max_package_size=1073741824
```

Security-sensitive values such as the local company identity and trusted
package-signing key path use administrator-controlled configuration.

An HTTP request must not be allowed to replace the trusted signing-key
path.

------------------------------------------------------------------------

# 6.9 Native Extension API

The native extension is intended to provide high-level cryptographic
operations.

Current/proposed API surface includes:

``` php
concheron_sec_version();

concheron_sec_capabilities();

concheron_sec_inspect_package($packagePath);

concheron_sec_verify_package($packagePath);

concheron_sec_import($packagePath, $options);

concheron_sec_verify_staff_certificate($certificateData);

concheron_sec_decrypt_for_render($documentReference);

concheron_sec_reencrypt_document($documentReference);
```

Not all operations are fully implemented in the current native
prototype.

Dangerous low-level interfaces are intentionally avoided:

``` php
// Not part of the intended API.
concheron_sec_get_private_key();
concheron_sec_get_document_key();
concheron_sec_export_server_key();
```

The objective is to keep sensitive key operations below the ordinary PHP
application boundary.

------------------------------------------------------------------------

# 6.10 Current Native Extension Prototype

The most recent native-extension prototype establishes several important
design properties:

-   trusted signing-key path comes from system configuration;
-   subsidiary/company identity comes from system configuration;
-   maximum package size is configurable;
-   package verification accepts the package path rather than an
    arbitrary signing key from PHP;
-   package structure is validated before policy processing;
-   signature verification precedes trust in signed manifest data;
-   required manifest fields are checked;
-   target-company policy is checked;
-   cryptographic suite and payload type are checked;
-   extension minimum-version policy is considered.

The intended verification flow is:

``` text
Package path
    ↓
Size / structural validation
    ↓
Configured trusted signing key
    ↓
Package signature verification
    ↓
Signed manifest parse
    ↓
Required fields
    ↓
Target company
    ↓
Suite / payload policy
    ↓
Expiration / minimum extension version
    ↓
Verified result
```

------------------------------------------------------------------------

# 6.11 Known Native Extension Gaps

The prototype must not be described as complete.

Important remaining work includes:

### 6.11.1 Canonical Manifest Handling

The current JSON approach does not yet provide a fully specified
canonical representation suitable for production interoperability.

Duplicate security-critical JSON keys also require explicit rejection.

### 6.11.2 Complete Recipient Block

The production binary format for recipient information remains to be
finalized.

### 6.11.3 Hardware Token Integration

The prototype has not yet implemented:

-   token discovery;
-   PIN activation;
-   private-key operation;
-   recipient identity matching;
-   non-exportable key enforcement.

### 6.11.4 CEK Recovery

Recipient-bound CEK recovery remains a designed function rather than a
completed prototype capability.

### 6.11.5 AES-GCM Payload Decryption

The complete authenticated package-decryption path remains to be
implemented in the native extension.

### 6.11.6 Local DEK/KEK Envelope

The improved local-storage envelope described in Chapter 5 remains a
design requirement for the next implementation stage.

### 6.11.7 Compilation Validation

Prototype source generation is not equivalent to successful compilation.
The thesis must report compilation/testing only after it is actually
performed in the target PHP 7.4/CentOS environment.

One earlier prototype implementation also requires correction where
PHP-style version comparison was conceptually referenced from C code. A
production implementation must use an appropriate C-level
semantic-version comparison routine or supported PHP internal API.

------------------------------------------------------------------------

# 6.12 ZF1 Application Architecture

The SEC functionality can be organized into dedicated ZF1 components.

Example:

``` text
application/
├── controllers/
│   ├── SecImportController.php
│   └── SecViewerController.php
├── models/
│   ├── SecDocument.php
│   ├── SecDocumentVersion.php
│   ├── SecPermission.php
│   ├── SecViewSession.php
│   └── SecAudit.php
├── services/
│   ├── SecAuthorizationService.php
│   ├── SecCryptoService.php
│   ├── SecRendererService.php
│   ├── SecWatermarkService.php
│   ├── SecViewSessionService.php
│   └── SecAuditService.php
└── views/
    └── scripts/
        └── sec-viewer/
            └── view.phtml
```

Separating responsibilities makes security controls easier to test than
embedding all logic in one controller.

------------------------------------------------------------------------

# 6.13 MariaDB Schema

The prototype database stores document metadata, permissions,
certificate metadata, viewing sessions, and audit events.

## 6.13.1 Documents

``` sql
CREATE TABLE sec_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_code VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    classification VARCHAR(50) NOT NULL,
    current_version INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_documents_code (document_code)
) ENGINE=InnoDB;
```

## 6.13.2 Document Versions

``` sql
CREATE TABLE sec_document_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    encrypted_storage_path VARCHAR(500) NOT NULL,
    storage_key_id VARCHAR(100) NOT NULL,
    page_count INT UNSIGNED DEFAULT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    file_hash CHAR(64) NOT NULL,
    source_package_id VARCHAR(100) NOT NULL,
    imported_by BIGINT UNSIGNED NOT NULL,
    imported_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_doc_version (document_id, version_no),
    UNIQUE KEY uq_sec_package (source_package_id),
    KEY idx_sec_version_document (document_id)
) ENGINE=InnoDB;
```

## 6.13.3 Permissions

``` sql
CREATE TABLE sec_document_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_permission (document_id, staff_id),
    KEY idx_sec_permission_staff (staff_id)
) ENGINE=InnoDB;
```

## 6.13.4 View Sessions

``` sql
CREATE TABLE sec_view_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_version_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    certificate_id VARCHAR(150) DEFAULT NULL,
    token_hash CHAR(64) NOT NULL,
    display_code VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    client_ip VARCHAR(45) DEFAULT NULL,
    user_agent_hash CHAR(64) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_view_token_hash (token_hash),
    KEY idx_sec_view_staff (staff_id),
    KEY idx_sec_view_version (document_version_id)
) ENGINE=InnoDB;
```

## 6.13.5 Audit Log

``` sql
CREATE TABLE sec_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    document_id BIGINT UNSIGNED DEFAULT NULL,
    staff_id BIGINT UNSIGNED DEFAULT NULL,
    view_session_id BIGINT UNSIGNED DEFAULT NULL,
    page_number INT UNSIGNED DEFAULT NULL,
    result VARCHAR(20) NOT NULL,
    reason_code VARCHAR(80) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sec_audit_event (event_id),
    KEY idx_sec_audit_document (document_id),
    KEY idx_sec_audit_staff (staff_id),
    KEY idx_sec_audit_created (created_at)
) ENGINE=InnoDB;
```

Foreign keys can be added according to the existing application's
database conventions and operational requirements.

------------------------------------------------------------------------

# 6.14 Authorization Service

Authorization is centralized in a service rather than duplicated
throughout controllers.

Conceptual PHP 7.4 implementation:

``` php
class SecAuthorizationService
{
    public function canView($staffId, $documentId)
    {
        $db = Zend_Db_Table::getDefaultAdapter();

        $sql = "
            SELECT 1
            FROM sec_document_permissions
            WHERE document_id = ?
              AND staff_id = ?
              AND can_view = 1
              AND (valid_from IS NULL OR valid_from <= NOW())
              AND (valid_until IS NULL OR valid_until >= NOW())
            LIMIT 1
        ";

        $result = $db->fetchOne(
            $sql,
            array((int) $documentId, (int) $staffId)
        );

        return (bool) $result;
    }
}
```

The query uses parameter binding and evaluates current permission.

The same service is called again for protected page requests rather than
assuming that authorization performed when the viewer first opened
remains permanently valid.

------------------------------------------------------------------------

# 6.15 View-Session Generation

A view token uses a cryptographically secure random value.

Conceptually:

``` php
$rawToken = random_bytes(32);
$token = rtrim(
    strtr(base64_encode($rawToken), '+/', '-_'),
    '='
);

$tokenHash = hash('sha256', $token);
```

Only the hash is stored in MariaDB.

The raw token is returned to the authorized viewer and must not appear
in audit logs or watermarks.

A separate display code can be generated for watermark attribution.

------------------------------------------------------------------------

# 6.16 View-Session Policy

A prototype policy may use:

``` text
absolute lifetime: 900 seconds
idle timeout:      300 seconds
```

These are prototype parameters rather than universal security constants.

Validation includes:

-   session status;
-   token hash;
-   staff binding;
-   document-version binding;
-   absolute expiration;
-   idle expiration.

Successful use updates `last_activity_at`.

------------------------------------------------------------------------

# 6.17 Secure Viewer Controller

A ZF1 controller exposes the viewer and protected page endpoint.

Conceptually:

``` php
class SecViewerController extends Zend_Controller_Action
{
    public function viewAction()
    {
        // 1. Require authenticated user.
        // 2. Validate document/version.
        // 3. Verify staff certificate state.
        // 4. Check document permission.
        // 5. Create short-lived view session.
        // 6. Pass safe viewer metadata to view.phtml.
    }

    public function pageAction()
    {
        // 1. Require POST.
        // 2. Parse JSON safely.
        // 3. Require authenticated staff session.
        // 4. Validate view token.
        // 5. Re-check document authorization.
        // 6. Validate page/profile.
        // 7. Apply rate limiting.
        // 8. Obtain clean rendered page.
        // 9. Apply personalized watermark.
        // 10. Return WebP/PNG with no-store headers.
    }
}
```

The page action is the primary browser-facing security boundary.

------------------------------------------------------------------------

# 6.18 Page Request Validation

A request such as:

``` json
{
  "document_id": 184,
  "version_id": 991,
  "page": 5,
  "profile": "normal",
  "view_token": "..."
}
```

is never trusted directly.

The server verifies:

``` text
HTTP method
JSON syntax
authenticated session
staff identity
certificate state
view-token hash
view-token binding
view-token expiration
document/version relationship
current permission
page range
profile allowlist
rate limit
```

Only after validation does rendering or cache access occur.

------------------------------------------------------------------------

# 6.19 Response Headers

Protected pages should use restrictive cache behavior.

Conceptually:

``` php
$response = $this->getResponse();

$response->setHeader('Content-Type', 'image/webp', true);
$response->setHeader(
    'Cache-Control',
    'private, no-store, no-cache, must-revalidate',
    true
);
$response->setHeader('Pragma', 'no-cache', true);
$response->setHeader('X-Content-Type-Options', 'nosniff', true);
```

HTTPS is required for production deployment.

------------------------------------------------------------------------

# 6.20 Crypto Service Boundary

The PHP crypto service wraps native-extension operations.

Conceptually:

``` php
class SecCryptoService
{
    public function verifyPackage($packagePath)
    {
        if (!function_exists('concheron_sec_verify_package')) {
            throw new RuntimeException(
                'SEC security extension is unavailable.'
            );
        }

        $result = concheron_sec_verify_package($packagePath);

        if (!is_array($result) || empty($result['verified'])) {
            throw new RuntimeException(
                'Package verification failed.'
            );
        }

        return $result;
    }
}
```

The application does not supply an arbitrary trusted signing key through
the HTTP request.

------------------------------------------------------------------------

# 6.21 Future Import Service

Once native token/decryption functions are complete, the import service
will coordinate:

``` text
upload validation
    ↓
native package verification
    ↓
recipient/token operation
    ↓
authenticated payload decryption
    ↓
PDF validation
    ↓
local DEK generation
    ↓
AES-GCM local encryption
    ↓
DEK protection with KEK
    ↓
database transaction
    ↓
audit
    ↓
plaintext cleanup
```

Until those operations are implemented and tested, they remain designed
behavior rather than measured prototype functionality.

------------------------------------------------------------------------

# 6.22 PDF Rendering Service

The rendering service uses a fixed executable path.

Example:

``` php
class SecRendererService
{
    private $pdftoppm = '/usr/bin/pdftoppm';

    public function renderPage($pdfPath, $page, $profile, $outputPrefix)
    {
        $page = (int) $page;

        if ($page < 1) {
            throw new InvalidArgumentException('Invalid page.');
        }

        $profiles = array(
            'normal' => 120,
            'high'   => 200,
        );

        if (!isset($profiles[$profile])) {
            throw new InvalidArgumentException(
                'Invalid render profile.'
            );
        }

        $dpi = $profiles[$profile];

        // Production code should prefer a safe process API.
        // If a shell is used, every path must be application-controlled
        // and escaped correctly.
        $cmd = sprintf(
            '%s -f %d -singlefile -r %d -png %s %s',
            escapeshellcmd($this->pdftoppm),
            $page,
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('PDF rendering failed.');
        }
    }
}
```

A production implementation should use a process-execution API that
avoids shell interpretation where practical.

The user never supplies the executable path or arbitrary command
arguments.

------------------------------------------------------------------------

# 6.23 Rendering Profiles

The prototype supports two server-controlled profiles:

``` text
normal -> common viewing/scrolling
high   -> higher zoom levels
```

The exact DPI values are configuration parameters and should be included
in the experimental environment description.

The browser requests only an allowed profile name, not an arbitrary DPI.

------------------------------------------------------------------------

# 6.24 Clean Page Cache

The cache is keyed by immutable document version and rendering profile.

Example:

``` text
/secure/sec/pages/
└── version-991/
    ├── normal/
    │   ├── page-000001.png
    │   └── page-000002.png
    └── high/
        └── page-000001.png
```

A new document version receives a separate cache namespace.

The cache remains unwatermarked so one clean render can be personalized
for multiple authorized viewers.

It is never intentionally served directly by Apache.

------------------------------------------------------------------------

# 6.25 Watermark Service

Imagick applies the personalized watermark on the server.

Conceptual implementation:

``` php
class SecWatermarkService
{
    public function createWatermarkedPage(
        $cleanPagePath,
        array $context
    ) {
        $image = new Imagick($cleanPagePath);

        $draw = new ImagickDraw();
        $draw->setFontSize(24);
        $draw->setFillOpacity(0.18);

        $text = sprintf(
            "CONCHERON — SEC\n%s\nEmployee: %s\nDocument: %s\nView: %s",
            $context['company'],
            $context['employee_code'],
            $context['document_code'],
            $context['display_code']
        );

        // Repeated diagonal placement is used in the full implementation.
        // Footer attribution may be added separately.

        $image->stripImage();
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality(82);

        return $image->getImagesBlob();
    }
}
```

The exact visual placement should be tested for readability and
resistance to simple cropping.

------------------------------------------------------------------------

# 6.26 Watermark Data Minimization

The watermark may contain:

``` text
organization/subsidiary
staff code
document code
non-secret view display code
timestamp
```

It must not contain:

``` text
session ID
raw view token
CSRF token
password
PIN
private key
CEK
DEK
KEK
```

The display code maps to internal audit data.

------------------------------------------------------------------------

# 6.27 Canvas Viewer

The browser viewer does not load a PDF file.

A simplified page structure is:

``` html
<div id="secViewer">
    <div class="page-placeholder" data-page="1">
        <canvas></canvas>
    </div>
    <div class="page-placeholder" data-page="2">
        <canvas></canvas>
    </div>
</div>
```

JavaScript requests individual page images through the protected API.

------------------------------------------------------------------------

# 6.28 Lazy Loading

`IntersectionObserver` detects pages near the viewport.

Conceptually:

``` javascript
var observer = new IntersectionObserver(
    function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                loadPage(entry.target);
            }
        });
    },
    {
        root: null,
        rootMargin: '800px 0px',
        threshold: 0.01
    }
);
```

The margin allows pages to begin loading shortly before they become
visible.

------------------------------------------------------------------------

# 6.29 Page Fetch

Conceptually:

``` javascript
function requestPage(pageNo, profile) {
    return fetch('/sec-viewer/page', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            document_id: viewer.documentId,
            version_id: viewer.versionId,
            page: pageNo,
            profile: profile,
            view_token: viewer.viewToken
        })
    }).then(function (response) {
        if (!response.ok) {
            throw new Error('Page request failed.');
        }

        return response.blob();
    });
}
```

The production request also follows the application's CSRF and
session-security policy as appropriate.

------------------------------------------------------------------------

# 6.30 Canvas Rendering

Conceptually:

``` javascript
requestPage(pageNo, profile)
    .then(function (blob) {
        return createImageBitmap(blob);
    })
    .then(function (bitmap) {
        var canvas = getCanvas(pageNo);
        var context = canvas.getContext('2d');

        canvas.width = bitmap.width;
        canvas.height = bitmap.height;

        context.drawImage(bitmap, 0, 0);
        bitmap.close();
    });
```

This provides an application-controlled viewing interface without giving
the browser the original PDF.

------------------------------------------------------------------------

# 6.31 Browser Memory Management

Large documents can contain hundreds of pages.

Keeping every decoded page in browser memory is inefficient.

The viewer therefore keeps only a neighborhood around the current page,
for example:

``` text
current page ± 3 pages
```

Distant canvases are cleared.

When the user returns, the page is requested again.

This creates a tradeoff between browser memory, server requests, and
user experience that can be measured experimentally.

------------------------------------------------------------------------

# 6.32 Zoom Strategy

Normal profile pages are used for ordinary zoom.

When zoom exceeds a configured threshold, such as approximately 150%,
the viewer can request the high-resolution profile.

Example:

``` text
25%–150%   -> normal profile
>150%      -> high profile
```

These thresholds are prototype parameters and can be tuned using
measured rendering cost and visual quality.

------------------------------------------------------------------------

# 6.33 Browser Restrictions

The prototype may suppress:

-   right-click context menu;
-   common save shortcuts;
-   print shortcuts;
-   drag behavior.

These controls are only deterrents.

They are not counted as cryptographic or access-control security because
a user can still capture visible content.

------------------------------------------------------------------------

# 6.34 CSRF Integration

State-changing SEC operations require CSRF protection.

For the existing ZF1 application, a session-stable token is preferable
to rotating the token after every form submission because the
application must support multiple browser tabs.

Conceptually:

``` php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}
```

Validation uses constant-time comparison:

``` php
if (!hash_equals(
    $_SESSION['csrf_token'],
    $submittedToken
)) {
    throw new RuntimeException('Invalid request.');
}
```

The token should rotate on security-relevant session lifecycle events
rather than every request.

------------------------------------------------------------------------

# 6.35 XSS Protection

XSS is particularly serious in the viewer because JavaScript temporarily
possesses the raw view token.

Controls include:

-   contextual output encoding;
-   avoiding unsafe `innerHTML`;
-   validating server-generated metadata;
-   Content Security Policy;
-   Secure/HttpOnly session cookies;
-   avoiding inline script where practical;
-   minimizing third-party JavaScript;
-   dependency control.

A browser token cannot be protected from script that is already
executing with the same origin and sufficient privileges; therefore XSS
prevention is critical.

------------------------------------------------------------------------

# 6.36 SQL Injection Protection

ZF1 database operations use bound parameters rather than concatenating
untrusted request values into SQL.

The database account should have only required privileges.

Application errors returned to users should not expose SQL text,
credentials, or stack traces.

------------------------------------------------------------------------

# 6.37 Rate Limiting

The page endpoint needs rate limiting that does not break normal
scrolling.

A policy can evaluate:

``` text
staff ID
view-session ID
document ID
requests per interval
burst rate
concurrent rendering jobs
```

Rate limiting should be evaluated with realistic viewer behavior rather
than selecting an arbitrary value and assuming usability.

------------------------------------------------------------------------

# 6.38 Audit Service

A centralized application service writes structured audit events.

Conceptually:

``` php
$audit->write(array(
    'event_type'  => 'SEC_PAGE_VIEW',
    'document_id' => $documentId,
    'staff_id'    => $staffId,
    'view_session_id' => $viewSessionId,
    'page_number' => $page,
    'result'      => 'SUCCESS'
));
```

The audit service explicitly excludes secrets.

------------------------------------------------------------------------

# 6.39 Import Error Handling

A failed import should clean up all transient state.

Conceptual pattern:

``` php
$temporaryFiles = array();

try {
    // verify
    // decrypt
    // validate
    // local encrypt
    // database transaction
} catch (Exception $e) {
    // rollback database transaction
    // write safe audit failure
    throw $e;
} finally {
    // remove temporary plaintext and temporary upload data
}
```

PHP 7.4 supports `finally`, allowing cleanup to be centralized.

Filesystem cleanup and database rollback are both necessary.

------------------------------------------------------------------------

# 6.40 Exception Handling

Security-sensitive internal exceptions should be translated into generic
user-facing responses.

Example:

``` text
Internal:
SEC_ERR_PACKAGE_SIGNATURE_INVALID

User:
Package verification failed.
```

Logs may contain the safe internal reason code but not cryptographic
secrets.

This separation supports debugging without leaking implementation
details.

------------------------------------------------------------------------

# 6.41 Prototype Data Flow

The implemented/design flow can be summarized as:

``` text
                    CURRENTLY DEMONSTRABLE

Staff
 |
ZF1 authentication
 |
document permission
 |
view-session generation
 |
secure page endpoint
 |
protected cache / rendering
 |
Imagick watermark
 |
WebP
 |
Canvas viewer
 |
audit


              DESIGNED NEXT CRYPTOGRAPHIC STAGE

.sec package
 |
native signature/policy verification
 |
hardware token
 |
CEK recovery
 |
AES-GCM authenticated decryption
 |
random local DEK
 |
local AES-GCM encryption
 |
KEK-protected DEK
 |
encrypted master
```

This distinction must remain visible in the thesis.

------------------------------------------------------------------------

# 6.42 Implementation-to-Requirement Traceability

  Implementation component          Security requirements
  --------------------------------- --------------------------------
  Native package verifier           SR-009--SR-019
  Future token/CEK implementation   SR-001--SR-008, SR-025--SR-029
  MariaDB permission service        SR-047--SR-053
  View-session service              SR-054--SR-060
  Protected storage layout          SR-036--SR-046, SR-100--SR-105
  Poppler rendering service         SR-061--SR-067
  Imagick watermark service         SR-068--SR-071
  ZF1 web controls                  SR-072--SR-081
  Rate limiting                     SR-082--SR-086
  Audit service                     SR-087--SR-094
  Native extension API boundary     SR-095--SR-099

------------------------------------------------------------------------

# 6.43 Prototype Evaluation Readiness

The following parts can be evaluated after deployment of the application
prototype:

-   authorization bypass attempts;
-   view-token binding and expiration;
-   IDOR resistance;
-   original-PDF URL exposure;
-   clean-cache exposure;
-   watermark generation;
-   page-rendering latency;
-   viewer lazy-loading behavior;
-   browser memory behavior;
-   concurrent page delivery;
-   command-injection input validation;
-   temporary-file cleanup;
-   audit completeness;
-   rate-limiting behavior.

The following require completion of the native cryptographic stage
before empirical claims can be made:

-   recipient-bound package decryption;
-   real hardware-token private-key operations;
-   CEK recovery;
-   AES-GCM package authentication/decryption;
-   local DEK/KEK storage envelope;
-   key rotation/re-wrapping tests.

------------------------------------------------------------------------

# 6.44 Reproducibility Information

The experimental chapter should record:

``` text
CentOS version
kernel version
CPU model
CPU core count
RAM
storage type
Apache version
PHP 7.4 patch version
ZF1 version
MariaDB version
OpenSSL version
Poppler version
ImageMagick version
browser/version
native extension version
compiler/version
cryptographic configuration
rendering DPI profiles
test document characteristics
```

Without this information, performance measurements cannot be
meaningfully reproduced.

------------------------------------------------------------------------

# 6.45 Implementation Limitations

The prototype has several known limitations.

First, the complete native cryptographic package-import path is not yet
implemented.

Second, a software-based prototype server key does not provide the same
protection as an HSM- or TPM-backed production key.

Third, server-side rendering necessarily creates plaintext within a
trusted processing context.

Fourth, Canvas rendering and JavaScript restrictions cannot prevent
screenshots or a compromised authorized endpoint.

Fifth, performance results from the prototype environment cannot
automatically be generalized to all enterprise deployments.

These limitations are included as part of the research rather than
hidden.

------------------------------------------------------------------------

# 6.46 Chapter Summary

This chapter translated the proposed architecture into a concrete
prototype design based on CentOS, Apache, PHP 7.4, Zend Framework 1,
MariaDB, a native PHP security extension, OpenSSL, Poppler, Imagick, and
JavaScript Canvas.

The application prototype separates authorization, view-session
management, cryptographic integration, rendering, watermarking, and
auditing into dedicated components. Protected document material and
clean rendered pages remain outside the Apache web root. The page API
performs server-side authorization and token validation before returning
personalized page representations.

The chapter also clearly identified the current limitations of the
native `concheron_sec.so` prototype. Package structure/signature/policy
verification has been partially developed, while hardware-token
integration, recipient CEK recovery, authenticated payload decryption,
and the local DEK/KEK storage envelope remain implementation work. These
functions must not be reported as experimentally validated until they
are completed and tested on the target CentOS/PHP 7.4 environment.

The next chapter should be **Chapter 7 --- Security Analysis**, in which
the proposed architecture is systematically evaluated against the
threats identified in Chapter 3. Chapter 8 can then define and report
the empirical performance and security experiments.

------------------------------------------------------------------------

# Chapter 7 --- Security Analysis

## 7.1 Introduction

This chapter analyzes the security properties of the proposed
confidential-document architecture against the threat model defined in
Chapter 3.

The purpose is to determine whether the architecture contains
appropriate controls for the identified threats and to identify residual
risks that remain even when those controls operate as designed.

This chapter is an architectural security analysis, not a report of
experimental results. Statements such as "the design rejects" describe
intended security behavior. Actual prototype behavior must be
demonstrated through the experiments defined in Chapter 8.

The analysis uses the following structure:

``` text
Threat
  ↓
Protected asset
  ↓
Attack path
  ↓
Preventive/detective controls
  ↓
Expected security property
  ↓
Residual risk
  ↓
Experimental verification
```

------------------------------------------------------------------------

# 7.2 Security Analysis Method

Each threat is evaluated using four questions:

1.  **What is the attacker attempting to achieve?**
2.  **Which architectural controls are intended to prevent or detect the
    attack?**
3.  **What security property should hold if those controls operate
    correctly?**
4.  **What residual risk remains?**

Residual risk is categorized descriptively as:

-   **Architecturally reduced** --- the design contains direct controls
    against the attack;
-   **Partially reduced** --- meaningful controls exist, but the attack
    cannot be completely prevented;
-   **Operationally dependent** --- effectiveness strongly depends on
    deployment or administrative practice;
-   **Residual / unavoidable within scope** --- the architecture cannot
    fully prevent the threat.

These labels are not quantitative risk scores.

------------------------------------------------------------------------

# 7.3 Security Properties

The architecture seeks to establish the following properties.

## SP-01 --- Package Confidentiality

Possession of a `.sec` package alone should not reveal the original PDF.

## SP-02 --- Package Integrity

Unauthorized modification of protected package data should be detected.

## SP-03 --- Publisher Authenticity

A subsidiary should accept a package only when it verifies against an
authorized central signing identity.

## SP-04 --- Recipient Restriction

Package-key recovery should require the intended recipient cryptographic
capability.

## SP-05 --- Subsidiary Isolation

A package targeted to one subsidiary should not be accepted by another
subsidiary merely because the file was copied there.

## SP-06 --- Local At-Rest Confidentiality

Copying encrypted master files without the required local cryptographic
key material should not reveal the PDF.

## SP-07 --- Authentication

Sensitive operations should require proof of an approved
identity/authenticator.

## SP-08 --- Resource Authorization

An authenticated user should access only documents for which current
authorization exists.

## SP-09 --- Controlled Presentation

Normal browser viewing should not require routine delivery of the
original PDF.

## SP-10 --- Accountability

Security-relevant actions should be attributable through audit records
and personalized viewing context.

------------------------------------------------------------------------

# 7.4 T-001 --- Stolen Encrypted Package

## Attack

An attacker obtains a `.sec` file from removable media, download
storage, backup, or another location.

## Controls

The design uses:

-   a fresh package CEK;
-   AES-256-GCM payload protection;
-   recipient-specific CEK protection;
-   protected recipient private-key operations;
-   no plaintext PDF inside the package.

## Expected Property

The attacker should obtain ciphertext and metadata but should not
recover the PDF without the intended cryptographic capability.

## Residual Risk

The protection fails if the attacker also compromises the recipient
private key/token and any required activation factor, or if the
cryptographic implementation is defective.

**Assessment:** Architecturally reduced.

**Experimental verification:** CT-01, CT-06 and package-confidentiality
tests.

------------------------------------------------------------------------

# 7.5 T-002 --- Package Delivered to Wrong Subsidiary

## Attack

A package for Company A is copied into Company B.

## Controls

-   signed `target_company_id`;
-   locally configured company identity;
-   recipient-specific key protection;
-   import policy check.

## Expected Property

Company B should reject the package even if it possesses the file.

## Residual Risk

Incorrect provisioning of local company identity or deliberate
administrative reconfiguration could undermine the policy layer.
Recipient cryptographic binding provides an additional independent
control.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.6 T-003 --- Package Tampering

## Attack

An attacker modifies ciphertext, manifest fields, recipient data, or
other protected package bytes.

## Controls

-   central digital signature;
-   AES-GCM authentication;
-   strict package parsing;
-   fail-closed processing.

## Expected Property

Modification of signed data should invalidate the signature;
modification of authenticated ciphertext/tag/AAD should cause
authenticated decryption failure.

## Residual Risk

Security depends on unambiguous signed-byte representation, correct
canonicalization, parser correctness, and correct cryptographic-library
use.

The current prototype's canonical-manifest handling remains incomplete
and must be resolved before production claims are made.

**Assessment:** Architecturally reduced; implementation-dependent.

------------------------------------------------------------------------

# 7.7 T-004 --- Forged Package

## Attack

An attacker creates a malicious package and claims it was published by
the central authority.

## Controls

The subsidiary verifies the package using an administrator-configured
trusted signing identity. The package cannot choose its own trust key.

## Expected Property

A forged package without a valid central signature should be rejected.

## Residual Risk

Compromise of the central signing private key would undermine publisher
authenticity until the compromise is detected and trust is updated.

**Assessment:** Architecturally reduced; high dependency on signing-key
protection.

------------------------------------------------------------------------

# 7.8 T-005 --- Replay or Version Downgrade

## Attack

An attacker reimports an old valid package or replaces a newer document
with an older signed version.

## Controls

-   unique package ID;
-   import history;
-   authenticated document/version metadata;
-   local current-version state;
-   downgrade policy.

## Expected Property

Duplicate packages and prohibited version regressions should be
detected.

## Residual Risk

A poorly defined version policy could permit legitimate but undesirable
historical packages. Replay protection therefore requires both
cryptographic authenticity and application state.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.9 T-006 --- Copied Software Private Key

## Attack

A private key stored as a copyable `.p12` file is stolen and duplicated.

## Controls

The production architecture prefers a hardware cryptographic
authenticator with a non-exportable private key and PIN/activation
factor.

## Expected Property

Copying ordinary host or removable-storage files should not clone the
token's private key.

## Residual Risk

The security property does not apply if the final deployment uses an
ordinary flash drive containing an exportable `.p12`. Hardware
implementation quality also matters.

The current hardware-token integration remains designed rather than
experimentally implemented.

**Assessment:** Partially reduced; hardware-dependent.

------------------------------------------------------------------------

# 7.10 T-007 --- Authentication Replay

## Attack

An attacker records a previous authentication response and submits it
later.

## Controls

-   fresh random challenge;
-   challenge expiration;
-   proof of private-key possession;
-   protocol-context binding where appropriate;
-   TLS.

## Expected Property

A response to one challenge should not authenticate an independent new
challenge.

## Residual Risk

Weak challenge generation, accidental challenge reuse, or incorrect
challenge-state handling can undermine replay resistance.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.11 T-008 --- Revoked Certificate in an Offline Network

## Attack

A user whose certificate has been revoked centrally attempts access
before the isolated subsidiary receives the updated revocation
information.

## Controls

-   signed CRL/revocation update packages;
-   update-age tracking;
-   certificate validity checks;
-   stale-revocation policy;
-   emergency update procedure.

## Expected Property

Once trusted revocation information reaches the subsidiary, the revoked
certificate should be rejected.

## Residual Risk

A disconnected system cannot know about a revocation that it has not yet
received. A non-zero revocation window is therefore inherent in the
offline model.

**Assessment:** Partially reduced; residual freshness risk.

------------------------------------------------------------------------

# 7.12 T-009 --- Theft of Server Storage

## Attack

An attacker copies `master.enc` files or steals storage media.

## Controls

-   per-document DEK;
-   AES-GCM local master encryption;
-   DEK protected by subsidiary KEK;
-   protected KEK storage;
-   filesystem permissions.

## Expected Property

Encrypted master files alone should not disclose PDF content.

## Residual Risk

If the attacker also obtains a usable KEK and wrapped DEKs,
confidentiality may be lost. Software-stored KEKs are weaker than
appropriately deployed HSM/TPM-backed keys.

**Assessment:** Architecturally reduced; key-storage dependent.

------------------------------------------------------------------------

# 7.13 T-010 --- Temporary Plaintext Exposure

## Attack

A decrypted PDF remains on disk, becomes backed up, or is accessible
after a rendering/import failure.

## Controls

-   dedicated protected temporary directory;
-   restrictive permissions;
-   randomized filenames;
-   short plaintext lifetime;
-   `finally`-style cleanup;
-   backup exclusion;
-   optional encrypted temporary storage.

## Expected Property

Plaintext should exist only during required processing and should not be
publicly accessible.

## Residual Risk

Reliable physical erasure from SSDs, journaling filesystems, snapshots,
or compromised privileged hosts cannot be guaranteed by ordinary file
deletion.

**Assessment:** Partially reduced.

------------------------------------------------------------------------

# 7.14 T-011 --- IDOR / Document Identifier Manipulation

## Attack

An authenticated user changes a document or version identifier in a
request.

## Controls

-   server-side authorization for every protected page request;
-   document/version relationship validation;
-   staff identity binding;
-   view-session binding.

## Expected Property

Knowledge or modification of an object identifier should not grant
access.

## Residual Risk

An authorization defect in one endpoint can bypass the model.
Authorization therefore needs centralized implementation and negative
testing.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.15 T-012 --- View Token Theft

## Attack

An attacker obtains a valid raw view token.

## Controls

-   256-bit random token material;
-   server-side token hash;
-   short absolute lifetime;
-   inactivity timeout;
-   staff/document/version binding;
-   TLS;
-   XSS defenses.

## Expected Property

A token should be difficult to guess and limited in scope and lifetime.

## Residual Risk

A same-origin XSS payload or fully compromised authorized browser may
use the token while it remains valid. Hashing the database token does
not protect a token already stolen from the browser.

**Assessment:** Partially reduced.

------------------------------------------------------------------------

# 7.16 T-013 --- Direct Original-PDF Access

## Attack

A user attempts to guess or discover a direct URL to the original
document.

## Controls

-   encrypted master outside web root;
-   no Apache alias;
-   no public PDF endpoint;
-   application-mediated rendering.

## Expected Property

Normal HTTP requests should not retrieve the original PDF.

## Residual Risk

Misconfiguration, unintended aliases, backup exposure, or privileged
host compromise can bypass this property.

**Assessment:** Architecturally reduced; deployment-dependent.

------------------------------------------------------------------------

# 7.17 T-014 --- Direct Clean-Page Cache Access

## Attack

An attacker bypasses personalized watermarking by requesting clean
cached images.

## Controls

-   clean cache outside web root;
-   no public static URL;
-   protected filesystem permissions;
-   watermark applied through page API.

## Expected Property

Browser clients should receive personalized output rather than directly
retrieving clean cache files.

## Residual Risk

Server misconfiguration or local filesystem compromise can expose clean
pages.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.18 T-015 --- Cross-Site Scripting

## Attack

Malicious JavaScript executes in the application origin and accesses
viewer data or performs authorized page requests.

## Controls

-   contextual output encoding;
-   safe DOM APIs;
-   Content Security Policy;
-   dependency control;
-   Secure/HttpOnly session cookies;
-   minimal token exposure.

## Expected Property

Untrusted data should not become executable script.

## Residual Risk

XSS is particularly severe because a same-origin script may access the
in-memory view token and page responses. Browser-side authorization
secrets cannot remain secret from malicious script executing in the same
trusted origin.

**Assessment:** Architecturally reduced but high-impact if prevention
fails.

------------------------------------------------------------------------

# 7.19 T-016 --- Cross-Site Request Forgery

## Attack

A malicious site attempts to cause a logged-in user's browser to perform
state-changing SEC operations.

## Controls

-   session CSRF token;
-   SameSite cookies;
-   method restrictions;
-   Origin/Referer checks where appropriate;
-   session lifecycle controls.

## Expected Property

Requests without the valid anti-CSRF context should be rejected.

## Residual Risk

XSS can often bypass CSRF defenses because malicious same-origin script
can operate within the trusted application context.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.20 T-017 --- SQL Injection

## Attack

An attacker supplies input intended to modify SQL query structure.

## Controls

-   parameterized queries;
-   input validation;
-   least-privileged database account;
-   generic errors.

## Expected Property

Request data should be treated as data rather than executable SQL
syntax.

## Residual Risk

Any endpoint that bypasses the parameterized-query convention may
reintroduce injection risk.

**Assessment:** Architecturally reduced.

------------------------------------------------------------------------

# 7.21 T-018 --- Renderer Command Injection

## Attack

An attacker manipulates page, profile, filename, or other request data
to execute shell commands.

## Controls

-   fixed renderer executable;
-   integer page conversion;
-   server-side profile allowlist;
-   application-controlled paths;
-   escaping;
-   preference for process APIs without shell interpretation.

## Expected Property

User input should not become arbitrary command syntax.

## Residual Risk

Shell-based process invocation remains more fragile than direct
argument-vector process execution. Implementation review and negative
testing are required.

**Assessment:** Architecturally reduced; implementation-dependent.

------------------------------------------------------------------------

# 7.22 T-019 --- Malicious PDF Against Renderer

## Attack

A crafted PDF exploits a Poppler/ImageMagick vulnerability or consumes
excessive CPU/memory.

## Controls

-   signed/controlled document workflow;
-   patched dependencies;
-   least privilege;
-   process isolation;
-   size limits;
-   timeouts;
-   resource limits;
-   SELinux.

## Expected Property

A malformed document should not provide unrestricted host compromise or
indefinitely consume server resources.

## Residual Risk

Unknown parser vulnerabilities may exist. Server-side rendering
increases the importance of patching and sandboxing because the server
processes complex untrusted file formats.

**Assessment:** Partially reduced.

------------------------------------------------------------------------

# 7.23 T-020 --- Automated Bulk Page Extraction

## Attack

An authorized or compromised account automatically requests pages at
high speed.

## Controls

-   view-session binding;
-   per-user/document rate limiting;
-   burst limits;
-   auditing;
-   anomaly detection where implemented;
-   no direct PDF download.

## Expected Property

High-speed automated extraction should become more difficult and
observable.

## Residual Risk

A sufficiently patient authorized user can still capture displayed
information. Rate limiting cannot prevent all extraction without also
preventing legitimate reading.

**Assessment:** Partially reduced.

------------------------------------------------------------------------

# 7.24 T-021 --- Watermark Removal

## Attack

A recipient crops, covers, transforms, or attempts to remove the visible
watermark.

## Controls

-   repeated diagonal watermark;
-   footer attribution;
-   multiple placements;
-   view-code audit linkage;
-   possible future forensic watermark.

## Expected Property

Casual redistribution should retain visible attribution or require
deliberate manipulation.

## Residual Risk

Visible watermarking cannot guarantee resistance to determined removal.

**Assessment:** Residual/partially reduced.

------------------------------------------------------------------------

# 7.25 T-022 --- Audit Log Tampering

## Attack

An attacker modifies or deletes security records.

## Controls

-   restricted database/log permissions;
-   separation of duties;
-   event identifiers;
-   protected audit access;
-   possible signed or remote log export.

## Expected Property

Ordinary application users should not be able to alter audit history.

## Residual Risk

A fully privileged database/host administrator may be able to modify
local logs unless stronger append-only, remote, or cryptographically
protected logging is deployed.

**Assessment:** Operationally dependent.

------------------------------------------------------------------------

# 7.26 T-023 --- Key Disclosure Through Logs or Errors

## Attack

A private key, CEK, DEK, token, password, or PIN is accidentally written
to logs or returned in an error.

## Controls

-   high-level cryptographic APIs;
-   secret-exclusion logging policy;
-   generic user-facing errors;
-   safe internal reason codes;
-   code review.

## Expected Property

Operational logs should contain identifiers and outcomes, not
cryptographic secrets.

## Residual Risk

Debugging code or third-party libraries may accidentally record
sensitive values. Automated secret-scanning tests are desirable.

**Assessment:** Architecturally reduced; implementation-dependent.

------------------------------------------------------------------------

# 7.27 T-024 --- Native Extension Replacement

## Attack

An attacker replaces `concheron_sec.so` with a malicious binary.

## Controls

-   root/admin-controlled installation;
-   restrictive ownership;
-   authenticated release artifacts;
-   version reporting;
-   checksum/signature verification;
-   change audit.

## Expected Property

Ordinary application users should not be able to replace the security
extension.

## Residual Risk

A root-level attacker can normally replace binaries or alter the host
unless additional measured boot, secure boot, attestation, or immutable
infrastructure is deployed.

**Assessment:** Operationally dependent.

------------------------------------------------------------------------

# 7.28 T-025 --- Permission Revocation During Active Viewing

## Attack

A staff member opens a document legitimately. Permission is then
removed, but the existing view session continues to retrieve pages.

## Controls

The page API rechecks document authorization on subsequent protected
requests.

## Expected Property

Permission revocation should affect future page requests even when a
view token has not yet expired.

## Residual Risk

Already displayed or cached information cannot be withdrawn from the
user's memory, screenshots, or compromised endpoint.

**Assessment:** Architecturally reduced for future requests.

------------------------------------------------------------------------

# 7.29 Cryptographic Security Analysis

## 7.29.1 Distribution Envelope

The distribution layer combines:

``` text
fresh CEK
+ AES-256-GCM
+ recipient key protection
+ central digital signature
```

These mechanisms address different properties.

AES-GCM protects payload confidentiality and authenticated integrity.
Recipient key protection restricts access to the CEK. The central
signature establishes publisher authenticity and protects signed package
metadata.

None should be removed merely because another exists.

## 7.29.2 Local Storage Envelope

The local layer combines:

``` text
per-document DEK
+ AES-256-GCM
+ KEK-protected DEK
+ KEK versioning
```

The design reduces the amount of data directly dependent on a long-lived
KEK and permits controlled key rotation.

## 7.29.3 Key Separation

The compromise scope differs by key.

  ---------------------------------------------------------------------
  Compromised key                    Primary consequence
  ---------------------------------- ----------------------------------
  Package-signing key                Attacker may forge apparently
                                     authentic packages

  Recipient/token key                Attacker may perform recipient
                                     operations for that identity

  Package CEK                        One package payload may be exposed

  Local DEK                          One local document version may be
                                     exposed

  Subsidiary KEK                     DEKs protected by that KEK may be
                                     exposed

  Staff authentication key           Staff impersonation risk
  ---------------------------------------------------------------------

This separation prevents one credential from automatically becoming
every other security credential.

------------------------------------------------------------------------

# 7.30 Authentication Security Analysis

Hardware-backed authentication improves resistance to credential copying
when private keys are genuinely non-exportable.

The challenge-response protocol additionally demonstrates current
possession rather than relying on certificate presentation alone.

However, authentication security still depends on:

-   token quality;
-   PIN policy;
-   host security;
-   certificate validation;
-   revocation freshness;
-   challenge randomness;
-   server session security.

Hardware authentication should therefore be treated as one layer rather
than a complete endpoint-security solution.

------------------------------------------------------------------------

# 7.31 Authorization Security Analysis

The architecture intentionally separates:

``` text
Who are you?
      ↓
Authentication

What may you access?
      ↓
Authorization
```

Per-page authorization is stronger than checking permission only when a
viewer is initially opened because it supports:

-   mid-session permission revocation;
-   IDOR resistance;
-   document/version binding;
-   short-lived view sessions.

The principal implementation risk is inconsistent enforcement across
endpoints. A centralized authorization service reduces this risk.

------------------------------------------------------------------------

# 7.32 Browser Security Analysis

The browser is treated as a presentation environment rather than a
trusted document repository.

It receives:

-   rendered page images;
-   temporary view authorization;
-   viewer metadata.

It does not intentionally receive:

-   original PDF;
-   CEK;
-   DEK;
-   KEK;
-   server private key.

This reduces the sensitivity of ordinary browser storage, but rendered
content remains confidential information.

The browser remains vulnerable to:

-   screenshots;
-   malware;
-   same-origin XSS;
-   memory inspection by a compromised endpoint;
-   manual copying.

Therefore, "original PDF not delivered" must not be interpreted as
"document information cannot be extracted."

------------------------------------------------------------------------

# 7.33 Watermark Security Analysis

Personalized watermarking provides accountability and deterrence.

It is strongest when:

-   repeated across the page;
-   combined with footer attribution;
-   generated server-side;
-   linked to an audit record;
-   unique to the viewing context.

It does not provide cryptographic access control.

A determined authorized user may still alter or remove visible marks.
Future research could compare visible watermarking with
forensic/invisible techniques.

------------------------------------------------------------------------

# 7.34 Network Isolation Analysis

Network isolation reduces attack connectivity but creates revocation and
update challenges.

Benefits include:

-   reduced direct cross-subsidiary exposure;
-   reduced internet-facing attack surface;
-   local containment.

Costs include:

-   delayed revocation information;
-   more difficult patch distribution;
-   local audit fragmentation;
-   operational transfer procedures.

The architecture therefore treats isolation as an environmental
property, not as an authentication mechanism.

------------------------------------------------------------------------

# 7.35 Native Extension Security Boundary

Moving sensitive cryptographic operations into a compiled PHP extension
has several architectural benefits:

-   narrower API;
-   less accidental key exposure in PHP;
-   centralized cryptographic implementation;
-   controlled algorithm policy.

However, compilation does not itself create security.

A native extension can contain vulnerabilities and can be reverse
engineered. The architecture therefore relies on established
cryptographic algorithms and protected keys rather than secrecy of the
binary.

The extension's implementation secrecy may protect intellectual property
but is not considered a cryptographic security property.

------------------------------------------------------------------------

# 7.36 Security of Server-Side Rendering

Server-side rendering reduces direct PDF exposure but creates a trusted
plaintext-processing point.

The server must therefore protect:

-   temporary plaintext;
-   renderer process;
-   clean page cache;
-   server key material;
-   page authorization.

A complete server compromise remains a major residual threat.

The architecture could be strengthened in future using:

-   hardware-backed KEKs;
-   isolated rendering containers;
-   sandboxing;
-   dedicated rendering hosts;
-   measured boot/attestation;
-   encrypted memory technologies where available.

These are future enhancements rather than current prototype claims.

------------------------------------------------------------------------

# 7.37 Availability Analysis

Security controls can themselves create denial-of-service opportunities.

Expensive operations include:

-   package verification;
-   public-key operations;
-   PDF decryption;
-   rendering;
-   high-resolution conversion;
-   watermarking.

The architecture therefore uses:

-   input size limits;
-   page/profile validation;
-   rendering cache;
-   lazy loading;
-   rate limiting;
-   process timeouts;
-   resource limits.

Chapter 8 should measure whether these controls preserve acceptable
legitimate-user performance.

------------------------------------------------------------------------

# 7.38 Residual Risk Matrix

  Area                       Residual risk
  -------------------------- ---------------------------------------------
  Stolen encrypted package   Recipient/key compromise
  Offline revocation         Delay before subsidiary receives revocation
  Temporary plaintext        Privileged host access / storage remnants
  Browser viewing            Screenshot, camera, malware
  Watermarking               Deliberate removal/transformation
  Server storage             KEK/server compromise
  Rendering                  Parser vulnerabilities
  Audit                      Privileged local tampering
  Native extension           Binary/software vulnerabilities
  Central signing            Signing-key compromise
  Hardware token             Hardware/firmware/PIN compromise
  Availability               Resource exhaustion

The presence of residual risk does not invalidate the architecture; it
defines the boundary of the security claim.

------------------------------------------------------------------------

# 7.39 Requirement Coverage Analysis

The architecture contains controls corresponding to all requirement
groups defined in Chapter 3:

``` text
SR-001–SR-024   package cryptography/authenticity/replay
SR-025–SR-035   hardware authentication and PKI
SR-036–SR-046   protected storage/plaintext handling
SR-047–SR-060   authorization/view sessions
SR-061–SR-071   rendering/watermarking
SR-072–SR-086   web security/availability
SR-087–SR-094   audit/accountability
SR-095–SR-099   native extension
SR-100–SR-105   CentOS host security
```

Coverage means an architectural control has been defined. It does not
mean every control has already been experimentally validated.

------------------------------------------------------------------------

# 7.40 Security Claim Boundaries

The thesis can reasonably investigate claims such as:

-   stolen package files do not reveal plaintext without required
    cryptographic capability;
-   package modification is detectable;
-   wrong-subsidiary imports are rejected;
-   original PDFs are not routinely delivered to browsers;
-   document authorization is enforced on protected page requests;
-   local masters are encrypted at rest;
-   personalized pages can be linked to viewing context;
-   key roles are separated.

The thesis should **not** claim:

``` text
"Documents can never be copied."
"Screenshots are impossible."
"The system is unhackable."
"Watermarks cannot be removed."
"Network isolation guarantees security."
"Compiled cryptography cannot be reverse engineered."
"A root-compromised server cannot expose plaintext."
```

Explicit claim boundaries improve the scientific quality of the
evaluation.

------------------------------------------------------------------------

# 7.41 Relationship to Experimental Evaluation

Chapter 8 will test the architecture empirically.

Examples:

``` text
T-003 Package tampering
   ↓
SR-009–SR-014
   ↓
Package signature + AEAD
   ↓
CT-02 / CT-03 / CT-04 / CT-05
   ↓
Observed accept/reject result
```

and:

``` text
T-011 IDOR
   ↓
SR-049–SR-053
   ↓
Per-page authorization
   ↓
ST-08
   ↓
Observed HTTP/application result
```

This prevents security conclusions from being based only on design
intent.

------------------------------------------------------------------------

# 7.42 Chapter Summary

This chapter systematically analyzed the proposed architecture against
the twenty-five threats defined in Chapter 3.

The architecture provides direct controls against stolen packages,
cross-subsidiary import, package tampering, forgery, replay,
authentication replay, storage theft, IDOR, direct PDF access,
clean-cache access, injection attacks, and mid-session permission
revocation.

Other threats can only be partially reduced. Offline certificate
revocation necessarily creates a freshness window. Temporary plaintext
cannot be guaranteed to disappear physically from all storage
technologies. Server-side rendering cannot prevent screenshots or
compromised endpoints from capturing visible information. Visible
watermarking cannot guarantee resistance to deliberate removal. A fully
privileged server compromise remains a major residual risk because
legitimate rendering requires access to plaintext.

The analysis also demonstrates the importance of key separation,
per-request authorization, protected server-side rendering, and
conservative security claims.

The next chapter, **Chapter 8 --- Experimental Evaluation**, will define
the test environment, datasets, security experiments, performance
measurements, concurrency tests, evaluation metrics, and
result-reporting structure. Experimental results will be recorded only
after the prototype tests have actually been executed.

------------------------------------------------------------------------

# Chapter 8 --- Experimental Evaluation

## 8.1 Introduction

This chapter defines the experimental methodology used to evaluate the
proposed secure document architecture and prototype.

The evaluation is designed to answer two different questions:

1.  **Security correctness:** Does the implemented prototype enforce the
    security behavior specified in Chapters 3--7?
2.  **Operational performance:** What overhead is introduced by secure
    rendering, watermarking, authorization, cryptographic processing,
    and concurrent document viewing?

No experimental result is assumed in advance. Tables in this chapter use
`TBD` for measurements that must be obtained from actual execution.

The evaluation follows the principle:

``` text
Research Question
       ↓
Security Requirement
       ↓
Implemented Control
       ↓
Test Procedure
       ↓
Observed Result
       ↓
Analysis
       ↓
Conclusion
```

------------------------------------------------------------------------

# 8.2 Evaluation Objectives

The experimental evaluation has the following objectives:

-   verify authorization enforcement;
-   verify view-session binding and expiration;
-   test resistance to IDOR-style identifier manipulation;
-   verify that original PDFs and clean cached pages are not publicly
    accessible;
-   evaluate personalized watermark generation;
-   evaluate temporary-file cleanup;
-   evaluate renderer input validation;
-   evaluate audit-event generation;
-   measure page-rendering latency;
-   measure watermarking overhead;
-   measure cache effectiveness;
-   measure browser/viewer behavior for large documents;
-   evaluate concurrent page delivery;
-   measure cryptographic operations after the complete cryptographic
    prototype is implemented;
-   validate negative cryptographic cases such as modified ciphertext,
    signatures, and recipient identity.

------------------------------------------------------------------------

# 8.3 Evaluation Categories

Experiments are divided into five categories.

``` text
Category A — Application security tests
Category B — Secure viewer tests
Category C — Performance tests
Category D — Concurrency and resource tests
Category E — Cryptographic tests
```

Categories A--D can largely be executed after deployment of the current
application prototype.

Category E requires completion of the native cryptographic
package-import implementation.

------------------------------------------------------------------------

# 8.4 Test Environment

All results must identify the exact environment.

## 8.4.1 Server Environment

Record:

  Item                      Value
  ------------------------- ---------------------------
  Operating system          TBD
  Kernel                    TBD
  CPU                       TBD
  Physical/logical cores    TBD
  RAM                       TBD
  Storage type              TBD
  Filesystem                TBD
  SELinux mode              TBD
  Apache version            TBD
  PHP version               7.4.x --- exact patch TBD
  Zend Framework version    TBD
  MariaDB version           TBD
  OpenSSL version           TBD
  Poppler version           TBD
  ImageMagick version       TBD
  `concheron_sec` version   TBD
  Compiler/version          TBD

## 8.4.2 Client Environment

  Item                             Value
  -------------------------------- -------
  Operating system                 TBD
  Browser                          TBD
  Browser version                  TBD
  CPU                              TBD
  RAM                              TBD
  Display resolution               TBD
  Network relationship to server   TBD

## 8.4.3 Network Environment

Record:

``` text
network type
link speed
measured latency
VPN presence/absence
packet loss if relevant
```

Tests should distinguish server-processing time from
network/browser-observed latency where possible.

------------------------------------------------------------------------

# 8.5 Test Document Dataset

A controlled dataset should include multiple document sizes and page
counts.

Recommended classes:

  Dataset     Approximate size   Page count Purpose
  --------- ------------------ ------------ -----------------
  D1                   1--5 MB          TBD Small document
  D2                     10 MB          TBD Common document
  D3                     50 MB          TBD Medium-large
  D4                    100 MB          TBD Large
  D5                    500 MB          TBD Stress case

Exact generated files, hashes, page counts, image complexity, and
compression characteristics must be recorded.

File size alone is insufficient because PDF rendering cost depends
strongly on page content.

------------------------------------------------------------------------

# 8.6 Dataset Characterization

For each PDF record:

``` text
dataset ID
SHA-256 hash
file size
page count
average page size
text-heavy / image-heavy / mixed
embedded images
resolution
compression characteristics
```

Where practical, use both text-heavy and image-heavy samples.

Example:

  ID     Type            Size   Pages SHA-256
  ------ ------------- ------ ------- ---------
  D1-T   Text-heavy       TBD     TBD TBD
  D1-I   Image-heavy      TBD     TBD TBD
  D3-M   Mixed            TBD     TBD TBD

------------------------------------------------------------------------

# 8.7 Measurement Method

Performance measurements should use monotonic/high-resolution timing
where available.

For a measured operation:

``` text
t_start = monotonic clock
execute operation
t_end = monotonic clock

duration = t_end - t_start
```

Measurements should exclude unrelated setup work unless that setup is
intentionally part of the measured user operation.

------------------------------------------------------------------------

# 8.8 Repetition Strategy

A single timing is insufficient.

Recommended procedure:

``` text
Warm-up runs: 3–5
Measured runs: at least 20 per condition
```

For variable or concurrency-sensitive tests, more repetitions may be
appropriate.

Report:

-   sample count `n`;
-   median;
-   arithmetic mean where useful;
-   standard deviation;
-   minimum;
-   maximum;
-   95th percentile where latency distribution matters.

The median and percentile values are particularly useful for
page-delivery latency.

------------------------------------------------------------------------

# 8.9 Cache State

Rendering tests must identify cache state.

Two conditions are required:

## Cold Cache

The requested rendered page does not already exist in the protected
clean-page cache.

Path:

``` text
decrypt/open source
→ Poppler rendering
→ cache creation
→ watermark
→ response
```

## Warm Cache

The clean rendered page already exists.

Path:

``` text
cache lookup
→ watermark
→ response
```

Mixing these cases would make latency results difficult to interpret.

------------------------------------------------------------------------

# 8.10 Application Security Test Matrix

The following tests evaluate application-level security behavior.

  ----------------------------------------------------------------------
  ID                     Test                   Expected behavior
  ---------------------- ---------------------- ------------------------
  ST-01                  Unauthenticated viewer Reject
                         request                

  ST-02                  Authenticated but      Reject
                         unauthorized document  

  ST-03                  Valid authorized       Permit
                         document               

  ST-04                  Expired view token     Reject

  ST-05                  Invalid/random view    Reject
                         token                  

  ST-06                  Token belonging to     Reject
                         another staff member   

  ST-07                  Token belonging to     Reject
                         another document       

  ST-08                  Manipulate document ID Reject unauthorized
                                                target

  ST-09                  Manipulate version ID  Reject
                                                invalid/unauthorized
                                                target

  ST-10                  Request page 0         Reject

  ST-11                  Request page \> page   Reject
                         count                  

  ST-12                  Invalid render profile Reject

  ST-13                  Permission revoked     Future page request
                         during viewing         rejected

  ST-14                  Direct original-PDF    No public PDF access
                         URL attempt            

  ST-15                  Direct clean-cache URL No public cache access
                         attempt                

  ST-16                  SQL-injection payload  No SQL execution

  ST-17                  Renderer               No command execution
                         command-injection      
                         payload                

  ST-18                  CSRF-invalid           Reject
                         state-changing request 

  ST-19                  XSS payload in         Encoded/non-executable
                         displayed metadata     

  ST-20                  Oversized upload       Reject according to
                                                policy
  ----------------------------------------------------------------------

------------------------------------------------------------------------

# 8.11 Security Test Recording Template

Each test should record:

``` text
Test ID:
Date/time:
Prototype version:
Tester:
Preconditions:
Input:
Procedure:
Expected result:
Observed HTTP status:
Observed application result:
Relevant audit event:
Server-side evidence:
Pass/Fail:
Notes:
```

A test passes only if the observed behavior matches the defined expected
result.

------------------------------------------------------------------------

# 8.12 IDOR Evaluation

IDOR testing should include multiple identities.

Example:

``` text
Staff A -> authorized for Document 100
Staff B -> not authorized for Document 100
```

Procedure:

``` text
1. Staff A opens Document 100.
2. Record normal authorized behavior.
3. Staff B obtains/guesses document ID 100.
4. Staff B requests viewer endpoint.
5. Staff B requests page endpoint.
6. Attempt version-ID manipulation.
7. Attempt reuse of Staff A's view token.
8. Record responses and audit events.
```

Expected result:

``` text
Staff B receives no protected page.
```

The experiment should verify both HTTP behavior and absence of
unintended data in the response body.

------------------------------------------------------------------------

# 8.13 View-Token Evaluation

Test token properties independently.

## VT-01 --- Randomness/Length

Confirm token generation uses 32 random bytes.

## VT-02 --- Database Storage

Confirm only SHA-256 token hashes are persisted.

## VT-03 --- Expiration

Wait beyond absolute lifetime and retry.

## VT-04 --- Idle Timeout

Wait beyond inactivity timeout and retry.

## VT-05 --- Staff Binding

Use a valid token under another authenticated staff session.

## VT-06 --- Document Binding

Change document/version identifiers while retaining the token.

## VT-07 --- Revoked Permission

Remove document permission while the token remains active and request
another page.

------------------------------------------------------------------------

# 8.14 Protected Storage Evaluation

Verify filesystem properties.

Tests include:

``` text
PS-01 original encrypted master outside web root
PS-02 clean page cache outside web root
PS-03 temporary directory outside web root
PS-04 key/trust directory not publicly reachable
PS-05 restrictive Unix permissions
PS-06 SELinux remains enabled
PS-07 no Apache alias exposes protected storage
PS-08 backup policy excludes temporary plaintext
```

Evidence can include configuration files, filesystem permissions, and
HTTP requests.

------------------------------------------------------------------------

# 8.15 Temporary Plaintext Cleanup Test

Procedure:

``` text
1. Start import/render operation.
2. Record temporary directory state.
3. Complete operation successfully.
4. Inspect temporary directory.
5. Trigger controlled failure.
6. Inspect temporary directory again.
7. Restart/terminate process during controlled test if safe.
8. Inspect recovery behavior.
```

Record whether temporary plaintext remains after each condition.

The test cannot prove physical SSD-sector erasure. It evaluates
application-visible cleanup behavior.

------------------------------------------------------------------------

# 8.16 Watermark Evaluation

Watermark testing has functional and performance components.

Functional checks:

``` text
correct subsidiary
correct employee/staff code
correct document code
correct display/view code
timestamp where configured
repeated watermark placement
footer placement
no secret values
```

Verify explicitly that the watermark does not contain:

``` text
PHP session ID
raw view token
CSRF token
password
PIN
CEK
DEK
KEK
```

------------------------------------------------------------------------

# 8.17 Watermark Performance Test

For each rendering profile and representative page:

``` text
clean image -> Imagick load
            -> watermark operations
            -> metadata stripping
            -> WebP encoding
            -> output
```

Measure watermark-processing time separately from PDF rendering.

Result table:

  Dataset   Profile     Page   Median ms   Mean ms   P95 ms
  --------- --------- ------ ----------- --------- --------
  D1        normal         1         TBD       TBD      TBD
  D3        normal         1         TBD       TBD      TBD
  D3        high           1         TBD       TBD      TBD

------------------------------------------------------------------------

# 8.18 PDF Rendering Performance

Measure Poppler rendering independently.

For each dataset/profile:

``` text
render page 1
render middle page
render final page
```

Result template:

  Dataset   Profile   Page position     Median ms   P95 ms   Output size
  --------- --------- --------------- ----------- -------- -------------
  D1        normal    first                   TBD      TBD           TBD
  D1        high      first                   TBD      TBD           TBD
  D3        normal    middle                  TBD      TBD           TBD
  D5        normal    final                   TBD      TBD           TBD

------------------------------------------------------------------------

# 8.19 End-to-End Page Latency

Measure from the protected page API request until the response is ready.

Separate:

``` text
server processing time
network transfer time
browser decode/draw time
```

where instrumentation permits.

## Cold Path

``` text
authorization
+ token validation
+ source access/decryption
+ Poppler
+ cache write
+ watermark
+ WebP encode
```

## Warm Path

``` text
authorization
+ token validation
+ cache read
+ watermark
+ WebP encode
```

Result table:

  Dataset   Profile   Cache     Median   P95   Max
  --------- --------- ------- -------- ----- -----
  D1        normal    cold         TBD   TBD   TBD
  D1        normal    warm         TBD   TBD   TBD
  D3        normal    cold         TBD   TBD   TBD
  D3        normal    warm         TBD   TBD   TBD
  D3        high      warm         TBD   TBD   TBD

------------------------------------------------------------------------

# 8.20 Cache Benefit

Cache improvement can be calculated after measurement:

``` text
Improvement (%) =
    ((ColdMedian - WarmMedian) / ColdMedian) × 100
```

Do not report a percentage until both measurements exist.

------------------------------------------------------------------------

# 8.21 Viewer Large-Document Test

Use a document with a large page count, ideally approaching the intended
upper operational range.

Measure:

-   initial viewer load;
-   time to first visible page;
-   browser memory after initial load;
-   browser memory after scrolling 50 pages;
-   browser memory after scrolling 100 pages;
-   number of active canvases;
-   page re-fetch behavior;
-   UI responsiveness.

Result template:

  Position     Active canvases   Browser memory Notes
  ---------- ----------------- ---------------- -------
  Initial                  TBD              TBD TBD
  Page 50                  TBD              TBD TBD
  Page 100                 TBD              TBD TBD
  Page 500                 TBD              TBD TBD

The experiment should verify that unloading distant canvases prevents
unbounded client memory growth.

------------------------------------------------------------------------

# 8.22 Zoom/Profile Test

Test:

``` text
50%
100%
150%
200%
300%
```

Record:

-   selected server profile;
-   page response size;
-   visual quality;
-   response latency;
-   browser drawing time.

This evaluates the normal/high-resolution switching strategy.

------------------------------------------------------------------------

# 8.23 Concurrency Test Design

Suggested concurrency levels:

``` text
1 user
10 users
25 users
50 users
100 users
```

These are test levels, not assumed production capacity.

Each simulated user should perform a realistic pattern rather than
continuously request the same page at maximum speed.

Example:

``` text
open viewer
request first page
pause/read
scroll
request next pages
occasional zoom
```

------------------------------------------------------------------------

# 8.24 Concurrency Metrics

Record:

-   requests per second;
-   median latency;
-   P95 latency;
-   error rate;
-   CPU utilization;
-   RAM utilization;
-   disk I/O;
-   PHP worker utilization;
-   rendering-process count;
-   MariaDB utilization where relevant.

Template:

    Users   RPS   Median ms   P95 ms   Error %   CPU %   RAM
  ------- ----- ----------- -------- --------- ------- -----
        1   TBD         TBD      TBD       TBD     TBD   TBD
       10   TBD         TBD      TBD       TBD     TBD   TBD
       25   TBD         TBD      TBD       TBD     TBD   TBD
       50   TBD         TBD      TBD       TBD     TBD   TBD
      100   TBD         TBD      TBD       TBD     TBD   TBD

------------------------------------------------------------------------

# 8.25 Cold-Cache Concurrency

A separate stress experiment should intentionally request uncached
pages.

This tests the expensive path:

``` text
multiple concurrent users
        ↓
multiple cache misses
        ↓
Poppler processes
        ↓
CPU/RAM/I/O pressure
```

Resource limits should prevent uncontrolled process creation.

Record queueing behavior and failures.

------------------------------------------------------------------------

# 8.26 Warm-Cache Concurrency

Repeat concurrency tests using already-rendered pages.

This isolates:

-   authorization;
-   token validation;
-   cache I/O;
-   watermarking;
-   WebP encoding;
-   network delivery.

Comparing cold and warm concurrency identifies the renderer's
contribution to system load.

------------------------------------------------------------------------

# 8.27 Rate-Limit Evaluation

Test legitimate and abusive request patterns.

## Normal pattern

Simulate ordinary scrolling.

Expected:

``` text
No inappropriate throttling.
```

## Burst pattern

Request many pages in a short period.

Expected:

``` text
Rate-limit policy activates according to configuration.
```

Record:

-   threshold;
-   response status;
-   delay/rejection behavior;
-   audit event;
-   recovery period.

Thresholds must be tuned from measured legitimate behavior rather than
selected solely for convenience.

------------------------------------------------------------------------

# 8.28 Audit Completeness Test

For each important operation, verify an audit event exists.

Examples:

  Operation                  Expected audit
  -------------------------- -------------------------------------
  Successful viewer open     Yes
  Permission denial          Yes
  Invalid view token         Yes
  Page request               According to configured granularity
  Package verification       Yes
  Import success/failure     Yes
  Revocation update          Yes
  Key rotation               Yes
  Extension-version change   Yes

Also verify secrets are absent.

------------------------------------------------------------------------

# 8.29 Log Secret-Exposure Test

Search protected application, Apache, PHP, and audit logs for
test-secret markers.

Create unique non-production test values for:

``` text
view token
CSRF token
test password
test CEK marker
test DEK marker
```

After the controlled test, search logs for those markers.

Expected:

``` text
No secret marker appears where policy prohibits it.
```

Real production secrets should never be deliberately exposed for this
experiment.

------------------------------------------------------------------------

# 8.30 Cryptographic Test Prerequisite

The following tests must not be reported as completed until the native
cryptographic implementation supports:

``` text
recipient token operation
CEK recovery
AES-GCM payload authentication/decryption
local DEK generation
local master encryption
KEK-based DEK protection
```

Until then, their result fields remain `NOT EXECUTED`.

------------------------------------------------------------------------

# 8.31 Cryptographic Negative Tests

  ID      Condition                      Expected result
  ------- ------------------------------ ------------------------------
  CT-01   Valid package/recipient        Import succeeds
  CT-02   Ciphertext modified            Reject
  CT-03   GCM tag modified               Reject
  CT-04   Signed manifest modified       Reject
  CT-05   Signature modified             Reject
  CT-06   Wrong recipient key/token      Reject
  CT-07   Wrong subsidiary               Reject
  CT-08   Unknown suite                  Reject
  CT-09   Invalid nonce metadata         Reject
  CT-10   Expired certificate            Policy rejection
  CT-11   Revoked certificate            Reject
  CT-12   Stale revocation information   Apply documented policy
  CT-13   Duplicate package              Reject/detect
  CT-14   Prohibited older version       Reject
  CT-15   Wrong local KEK                Decryption fails
  CT-16   Corrupted wrapped DEK          Decryption fails
  CT-17   KEK rotation/re-wrap           Document remains recoverable
  CT-18   Old KEK retirement             No active dependency
  CT-19   Inspect logs                   No raw keys
  CT-20   Inspect browser traffic        No document keys

------------------------------------------------------------------------

# 8.32 Cryptographic Performance Tests

After implementation, measure:

``` text
AES-GCM package encryption
package signing
signature verification
recipient key operation
CEK recovery
AES-GCM package decryption
local DEK generation
local master encryption
DEK wrap
DEK unwrap
KEK rotation/re-wrap
```

For large PDFs, report throughput as well as elapsed time:

``` text
Throughput (MB/s) =
    Input size (MB) / elapsed time (s)
```

------------------------------------------------------------------------

# 8.33 Cryptographic Result Template

  Operation          Dataset       n   Median ms   Mean ms   P95 ms   MB/s
  ------------------ --------- ----- ----------- --------- -------- ------
  AES-GCM encrypt    D1          TBD         TBD       TBD      TBD    TBD
  AES-GCM encrypt    D3          TBD         TBD       TBD      TBD    TBD
  AES-GCM decrypt    D3          TBD         TBD       TBD      TBD    TBD
  Sign               D3          TBD         TBD       TBD      TBD    N/A
  Verify             D3          TBD         TBD       TBD      TBD    N/A
  Local re-encrypt   D3          TBD         TBD       TBD      TBD    TBD

------------------------------------------------------------------------

# 8.34 Baseline Comparison

Where practical, compare the proposed architecture against two
controlled baselines.

## Baseline A --- Direct PDF Delivery

``` text
authenticated request
→ original PDF response
```

This baseline represents minimal application-layer protection.

## Baseline B --- Encrypted At Rest, Original PDF Delivered After Authorization

``` text
encrypted storage
→ authorization
→ server decrypt
→ original PDF response
```

## Proposed System

``` text
encrypted storage
→ authorization
→ page rendering
→ personalized watermark
→ protected page response
```

The comparison is intended to quantify overhead and exposure
differences, not to claim that the baselines are recommended
architectures.

------------------------------------------------------------------------

# 8.35 Baseline Metrics

Compare:

-   original PDF exposure to browser;
-   first-view latency;
-   page-view latency;
-   server CPU;
-   memory;
-   network bytes;
-   storage/cache overhead;
-   concurrency;
-   audit granularity.

Template:

  Metric                            Baseline A   Baseline B                    Proposed
  ------------------------------- ------------ ------------ ---------------------------
  Original PDF sent to browser             Yes          Yes   No in normal viewing path
  First-view latency                       TBD          TBD                         TBD
  Server CPU                               TBD          TBD                         TBD
  Network bytes                            TBD          TBD                         TBD
  Personalized page attribution             No           No                         Yes

The first row is an architectural property; performance rows require
measurement.

------------------------------------------------------------------------

# 8.36 Statistical Reporting

For latency results, report distributions rather than only averages.

Recommended:

``` text
n
median
mean
standard deviation
minimum
maximum
P95
```

Where comparing repeated paired conditions such as cold versus warm
cache, use appropriate statistical analysis if required by the
university's research methodology standards.

Statistical significance should not substitute for practical
significance.

------------------------------------------------------------------------

# 8.37 Experimental Validity

## 8.37.1 Internal Validity

Potential confounders include:

-   background server processes;
-   filesystem cache;
-   JIT/opcode cache state;
-   database cache;
-   CPU frequency changes;
-   concurrent unrelated workload;
-   network variability.

The test procedure should control or record these factors.

## 8.37.2 External Validity

Results from one CentOS server do not automatically represent:

-   different CPUs;
-   cloud environments;
-   other PDF workloads;
-   other network conditions;
-   larger organizations.

## 8.37.3 Construct Validity

"Security" cannot be represented by one number.

The evaluation therefore tests specific properties such as authorization
enforcement, tamper rejection, key exposure, and original-PDF exposure.

------------------------------------------------------------------------

# 8.38 Reproducibility

Each experiment should retain:

``` text
test script version
application commit/version
native extension version
configuration snapshot
dataset hash
test timestamp
environment details
raw measurements
processed results
```

Raw experimental data should be preserved separately from the thesis
text.

------------------------------------------------------------------------

# 8.39 Result Integrity

Experimental results must not be manually adjusted to fit expectations.

Failed tests are research results.

If a security test fails:

``` text
1. record the original failure;
2. identify root cause;
3. modify the implementation;
4. record the fix/version;
5. repeat the test;
6. report both the discovered weakness and remediation where academically relevant.
```

This process strengthens the thesis because it demonstrates iterative
security engineering.

------------------------------------------------------------------------

# 8.40 Research Question Mapping

The evaluation should map directly to the thesis research questions.

  Research area              Principal experiments
  -------------------------- -----------------------------------------
  Secure distribution        CT-01--CT-14
  Recipient binding          CT-06, token tests
  Key separation/storage     CT-15--CT-18
  Controlled viewing         ST-01--ST-15, viewer tests
  Watermark/accountability   Watermark + audit tests
  Web security               ST-16--ST-20
  Performance overhead       Rendering/crypto/end-to-end tests
  Scalability                Concurrency tests
  Residual limitations       Screenshot/endpoint analysis, Chapter 7

------------------------------------------------------------------------

# 8.41 Pass/Fail Criteria

Security tests use explicit binary expected behavior where possible.

Example:

``` text
Test: unauthorized page request
PASS: no protected page bytes returned
FAIL: protected page content returned
```

Performance tests do not automatically use pass/fail unless a threshold
is defined **before** testing.

For example:

``` text
Target P95 warm-page latency: < [predefined value]
```

If no defensible requirement exists, report the measured value without
inventing a success threshold after observing the result.

------------------------------------------------------------------------

# 8.42 Proposed Experiment Execution Order

Recommended sequence:

``` text
Phase 1
Environment verification

Phase 2
Authorization and view-token security

Phase 3
Protected-storage and direct-access tests

Phase 4
Renderer/input security

Phase 5
Watermark and audit tests

Phase 6
Single-user performance

Phase 7
Large-document browser tests

Phase 8
Concurrency and rate limiting

Phase 9
Complete native cryptographic implementation

Phase 10
Cryptographic negative tests

Phase 11
Cryptographic performance

Phase 12
Baseline comparison and analysis
```

This order allows the application/viewer evaluation to proceed while
cryptographic implementation is being completed.

------------------------------------------------------------------------

# 8.43 Master Results Table

A consolidated table can be populated during experimentation.

  Test ID   Requirement/Threat   Status                               Result   Evidence
  --------- -------------------- ------------------------------------ -------- ----------
  ST-01     Authentication       NOT EXECUTED                         TBD      TBD
  ST-08     T-011 / IDOR         NOT EXECUTED                         TBD      TBD
  ST-13     T-025                NOT EXECUTED                         TBD      TBD
  ST-14     T-013                NOT EXECUTED                         TBD      TBD
  ST-17     T-018                NOT EXECUTED                         TBD      TBD
  CT-02     T-003                BLOCKED --- crypto implementation    TBD      TBD
  CT-06     T-001/T-002          BLOCKED --- token implementation     TBD      TBD
  CT-17     Key rotation         BLOCKED --- DEK/KEK implementation   TBD      TBD

The full experimental workbook should contain every test case.

------------------------------------------------------------------------

# 8.44 Expected Output Artifacts

The experimental process should produce:

``` text
environment.md
dataset-manifest.csv
security-test-results.csv
viewer-performance.csv
render-performance.csv
watermark-performance.csv
concurrency-results.csv
crypto-performance.csv
audit-validation.csv
raw logs/evidence
charts
```

Sensitive operational information should be sanitized before inclusion
in the submitted thesis.

------------------------------------------------------------------------

# 8.45 Planned Figures

Useful figures for the final thesis include:

1.  Cold vs. warm page latency.
2.  Normal vs. high-resolution rendering latency.
3.  Page latency by concurrent-user level.
4.  Server CPU utilization by concurrent-user level.
5.  Server memory utilization by concurrent-user level.
6.  Cryptographic throughput by document size.
7.  Package import time by document size.
8.  Browser memory while scrolling a large document.

Charts should be generated only from actual collected measurements.

------------------------------------------------------------------------

# 8.46 Interpretation Framework

The final analysis should answer:

``` text
Did the security controls behave as specified?

Which controls failed or required modification?

What performance cost did server-side protection introduce?

How much did caching reduce repeated rendering cost?

At what workload did resource contention become significant?

Did large-document lazy loading control browser memory?

What cryptographic operations dominated import time?

Which residual risks remained outside technical prevention?
```

These questions connect implementation measurements to the research
objectives.

------------------------------------------------------------------------

# 8.47 Ethical and Operational Safety During Testing

Testing should use authorized systems and non-production test
identities.

Security experiments must not:

-   use real employee passwords;
-   expose real private keys unnecessarily;
-   damage production records;
-   intentionally overload a production server;
-   include confidential PDF content in published datasets;
-   disclose operational secrets in the thesis.

Synthetic documents and test credentials are preferred.

------------------------------------------------------------------------

# 8.48 Evaluation Limitations

The experimental evaluation will have limitations.

A prototype environment cannot prove security against every unknown
vulnerability.

Negative security tests demonstrate behavior for tested attack cases,
not mathematical proof of complete application security.

Performance results are environment-specific.

Hardware-token results will depend on the actual selected token and
driver/interface.

Offline revocation tests can measure local policy behavior but cannot
eliminate the inherent delay between central revocation and disconnected
subsidiary update.

These limitations should be reported with the results.

------------------------------------------------------------------------

# 8.49 Chapter Summary

This chapter defined a reproducible experimental framework for
evaluating the proposed secure document architecture.

The methodology separates application security, secure viewing,
performance, concurrency, and cryptographic testing. Security
experiments use predefined expected behavior, while performance
experiments report measured distributions rather than invented success
claims.

The chapter also separates experiments that can be performed with the
current application/viewer prototype from tests blocked by incomplete
hardware-token and native cryptographic functionality.

The proposed methodology measures authorization enforcement, view-token
security, direct-resource exposure, temporary plaintext cleanup,
watermarking, rendering latency, cache benefit, browser memory behavior,
concurrent-user performance, rate limiting, audit completeness,
cryptographic tamper detection, key separation, and key rotation.

All result fields remain `TBD`, `NOT EXECUTED`, or `BLOCKED` until
actual experiments are performed.

After the experiments are executed, this chapter can be updated with
observed data, statistical summaries, charts, failed-test analysis,
remediation, and evidence. The following chapter can then present the
broader **Discussion, Limitations, and Implications** of the measured
results.

------------------------------------------------------------------------

# Chapter 9 --- Discussion, Limitations, and Research Implications

## 9.1 Introduction

This chapter discusses the implications of the proposed secure document
architecture, relates the design to the research questions, identifies
limitations, and defines how the eventual experimental results should be
interpreted.

At the current stage, the architecture, threat model, security
requirements, prototype design, and experimental methodology have been
established. However, the complete Chapter 8 experimental dataset has
not yet been collected. Consequently, this chapter distinguishes
between:

1.  conclusions that follow from the architecture and threat analysis;
2.  observations about the prototype design and implementation status;
    and
3.  conclusions that must remain pending until empirical measurements
    are available.

No unexecuted experiment is presented as a research result.

------------------------------------------------------------------------

# 9.2 Research Problem Revisited

The central research problem is:

> How can confidential documents be securely distributed, decrypted,
> viewed, and audited across isolated organizational networks while
> minimizing exposure of plaintext documents and cryptographic keys?

The proposed solution treats document security as a lifecycle problem
rather than a file-encryption problem.

The lifecycle includes:

``` text
central publication
        ↓
cryptographic packaging
        ↓
recipient-bound distribution
        ↓
controlled transfer
        ↓
subsidiary import
        ↓
local encrypted storage
        ↓
staff authentication
        ↓
resource authorization
        ↓
server-side rendering
        ↓
personalized viewing
        ↓
auditing
```

This end-to-end perspective is one of the principal architectural
contributions of the research.

------------------------------------------------------------------------

# 9.3 Discussion of RQ1 --- Secure Architecture Across Isolated Networks

The first research question concerns how confidential documents can be
distributed between organizational environments that do not maintain
ordinary direct network connectivity.

The proposed architecture does not require the transfer medium itself to
provide confidentiality.

Instead:

``` text
untrusted/controlled transfer medium
                +
self-protecting encrypted package
                +
recipient binding
                +
digital signature
```

allows the security of the document to travel with the package.

This is significant for isolated subsidiaries because removable media or
controlled file-transfer procedures can be used without treating the
physical transfer medium as the primary confidentiality mechanism.

The architecture nevertheless retains operational controls around
transfer because cryptographic protection does not eliminate risks such
as package loss, replay, or unauthorized copying.

### Result-dependent discussion

After Chapter 8 tests are executed, this section should report:

-   whether wrong-subsidiary packages were rejected;
-   whether modified packages were rejected;
-   whether duplicate/version-downgrade packages were detected;
-   observed package-processing overhead.

------------------------------------------------------------------------

# 9.4 Discussion of RQ2 --- Hybrid Encryption and Recipient Binding

Large documents are protected using hybrid encryption.

The PDF is encrypted with a random symmetric CEK, while the CEK is
protected using the intended recipient's cryptographic mechanism.

This separates:

``` text
bulk encryption
      from
recipient-specific key protection
```

The design avoids direct public-key encryption of large PDF payloads.

Recipient binding also adds a cryptographic control beyond
organizational metadata. A package may state that it belongs to Company
A, but the CEK protection is intended to require the corresponding
authorized recipient capability.

### Result-dependent discussion

The final thesis should insert:

-   CEK recovery success with authorized token;
-   failure with unauthorized token;
-   token-operation latency;
-   package decryption throughput.

These claims must wait until the hardware-token path is implemented.

------------------------------------------------------------------------

# 9.5 Discussion of RQ3 --- Key Separation

The architecture deliberately avoids one universal cryptographic key.

It separates:

``` text
central package signing
recipient/token identity
package CEK
subsidiary storage KEK
per-document local DEK
staff authentication
```

This provides compartmentalization.

For example:

-   compromise of one package CEK primarily threatens one package;
-   compromise of a staff authentication credential does not directly
    reveal the local storage KEK;
-   package-signing compromise threatens authenticity but is not
    automatically the same as local document-storage compromise;
-   local DEK compromise can be scoped to one document version.

The improved DEK/KEK model also reduces dependence on a single
long-lived server key directly encrypting every large document.

------------------------------------------------------------------------

# 9.6 Discussion of RQ4 --- Server Re-encryption and Plaintext Minimization

After authorized package import, the distribution CEK is not intended to
become the permanent subsidiary storage key.

Instead:

``` text
authenticated package plaintext
        ↓
new local DEK
        ↓
AES-GCM encrypted master
        ↓
DEK protected by subsidiary KEK
```

This creates a new local security domain.

The design has several implications.

First, the original distribution credential can be retired from normal
document viewing.

Second, ordinary staff authentication credentials do not need to decrypt
the master document.

Third, local KEK rotation can potentially be implemented through DEK
re-wrapping rather than re-encryption of every PDF.

However, the design cannot completely eliminate plaintext. Rendering and
initial re-encryption require legitimate plaintext processing.

The research claim is therefore **plaintext minimization**, not
plaintext elimination.

------------------------------------------------------------------------

# 9.7 Discussion of RQ5 --- Secure Viewing and Accountability

The proposed viewer changes the browser's role.

Traditional authenticated PDF delivery often follows:

``` text
authenticate
   ↓
authorize
   ↓
send original PDF
```

The proposed design instead uses:

``` text
authenticate
   ↓
authorize
   ↓
create short-lived view session
   ↓
authorize protected page request
   ↓
server-side render
   ↓
personalized watermark
   ↓
send page representation
```

The original PDF is therefore not required in the normal browser-viewing
path.

This reduces direct exposure of the original file and enables per-page
authorization, watermarking, and auditing.

It does not create perfect digital-rights management. A user who is
allowed to see information can potentially capture that information.

The value of the architecture is therefore controlled presentation and
accountability rather than absolute copy prevention.

------------------------------------------------------------------------

# 9.8 Discussion of RQ6 --- Performance Overhead

The proposed architecture introduces additional processing compared with
direct PDF delivery.

Potential costs include:

-   signature verification;
-   recipient key operations;
-   AES-GCM processing;
-   server-side rendering;
-   image encoding;
-   watermark generation;
-   authorization checks;
-   audit writes.

The architecture attempts to manage these costs through:

-   protected clean-page caching;
-   lazy loading;
-   limited active canvases;
-   rendering profiles;
-   per-document immutable cache namespaces;
-   local DEK/KEK envelope encryption.

### Pending empirical findings

The following values must be inserted after Chapter 8:

``` text
Cold page median latency:          TBD
Warm page median latency:          TBD
Cold/warm improvement:             TBD
Watermark median overhead:         TBD
Normal render median:              TBD
High-resolution render median:     TBD
P95 at 10 concurrent users:        TBD
P95 at 25 concurrent users:        TBD
P95 at 50 concurrent users:        TBD
P95 at 100 concurrent users:       TBD
```

No statement that performance is "acceptable" should be made until an
acceptance criterion or contextual comparison has been defined.

------------------------------------------------------------------------

# 9.9 Discussion of RQ7 --- Residual Security Limitations

Several risks remain even if the architecture operates as designed.

These include:

``` text
screenshots
camera photography
screen recording
malware on authorized endpoints
root-level server compromise
signing-key compromise
hardware-token compromise
delayed offline revocation
renderer zero-day vulnerabilities
deliberate watermark removal
privileged audit tampering
```

The architecture reduces selected attack paths but does not claim to
eliminate all information leakage.

This is an important boundary of the research contribution.

------------------------------------------------------------------------

# 9.10 Significance of Offline Revocation

Certificate revocation is especially challenging in isolated
environments.

In a continuously connected system, a relying party may retrieve current
revocation information through online mechanisms.

An isolated subsidiary cannot necessarily do so.

The proposed signed offline update mechanism provides a controlled
alternative:

``` text
central revocation state
       ↓
signed update
       ↓
controlled transfer
       ↓
local validation
       ↓
local enforcement
```

However, this introduces a freshness interval.

A certificate revoked centrally may remain usable locally until the new
revocation information arrives.

This is not solely an implementation defect; it is a consequence of
disconnected operation.

The organization must therefore define a maximum acceptable
revocation-data age.

------------------------------------------------------------------------

# 9.11 Significance of Hardware-Backed Authentication

A hardware SEC token can improve resistance to credential copying when
its private key is non-exportable.

This property is materially different from storing an exportable `.p12`
file on an ordinary USB flash drive.

However, hardware does not eliminate all authentication risks.

Possible threats remain:

-   stolen token plus PIN;
-   compromised workstation;
-   malicious token middleware;
-   firmware vulnerabilities;
-   incorrect certificate validation;
-   session theft after successful authentication.

The token is therefore one component in a layered authentication
architecture.

------------------------------------------------------------------------

# 9.12 Native Extension as a Security Boundary

The custom `concheron_sec.so` extension serves two purposes.

The first is engineering encapsulation: subsidiary PHP code interacts
with a controlled high-level interface.

The second is reduction of accidental secret exposure: PHP does not need
APIs that return raw CEKs or private keys.

However, compilation is not treated as cryptographic protection.

A compiled extension:

-   can contain vulnerabilities;
-   can be replaced by a privileged attacker;
-   can potentially be reverse engineered.

Therefore, the research does not rely on algorithm secrecy.

Established cryptographic algorithms, key protection, signature
verification, access control, and host security remain the actual
security foundations.

------------------------------------------------------------------------

# 9.13 Legacy-System Integration

An important practical aspect of the research is integration with a
legacy enterprise stack.

The prototype does not assume replacement of:

``` text
Zend Framework 1
PHP 7.4
MariaDB
existing business application
```

Instead, new security functions are isolated into:

-   dedicated controllers;
-   security services;
-   new database tables;
-   a native extension;
-   protected filesystem storage;
-   a custom viewer.

This approach may reduce migration cost for organizations that cannot
immediately replace mature legacy applications.

However, continued use of legacy software also introduces maintenance
and security risks. Unsupported components should be isolated, patched
where possible, and ultimately included in a modernization plan.

------------------------------------------------------------------------

# 9.14 Security Versus Usability

Strong controls can reduce usability.

Examples include:

``` text
hardware-token authentication
short view-session lifetime
frequent authorization checks
no direct PDF download
server-side rendering latency
rate limiting
watermarks
```

The architecture therefore contains several usability-oriented
mechanisms:

-   continuous scrolling;
-   lazy loading;
-   protected rendering cache;
-   zoom profiles;
-   short-term view-session reuse;
-   background page loading near the viewport.

The experimental evaluation should determine whether these mechanisms
adequately compensate for the security overhead.

------------------------------------------------------------------------

# 9.15 Security Versus Performance

Server-side rendering illustrates a direct tradeoff.

Direct PDF delivery is operationally simple:

``` text
server -> PDF -> browser
```

The proposed model is more expensive:

``` text
server
 -> authorization
 -> decrypt/access source
 -> render
 -> cache
 -> watermark
 -> encode
 -> page response
```

The additional processing buys:

-   reduced direct original-PDF exposure;
-   personalized page attribution;
-   per-page authorization;
-   more granular auditability.

Chapter 8 should quantify the cost of these benefits.

------------------------------------------------------------------------

# 9.16 Security Versus Availability

Key protection can conflict with recoverability.

If a subsidiary KEK is lost permanently, documents depending on it may
become unrecoverable.

Consequently, strong key-management design must include both:

``` text
confidentiality controls
        and
controlled recovery
```

Backup copies of long-lived keys are themselves sensitive assets and
must receive equivalent protection.

This demonstrates that key management is not simply an encryption
problem; it is also an availability and governance problem.

------------------------------------------------------------------------

# 9.17 Watermarking Implications

The personalized watermark creates an accountability layer.

A visible page can include:

``` text
subsidiary
staff identifier
document identifier
view display code
timestamp
```

The display code can be mapped to audit data without placing a raw
security token on the page.

This allows leaked screenshots or photographs to contain contextual
attribution.

However, the watermark must not be described as preventing leakage.

Its contribution is primarily:

-   deterrence;
-   attribution;
-   investigation support.

Future research could evaluate forensic watermarking in addition to
visible marks.

------------------------------------------------------------------------

# 9.18 Auditability and Non-Repudiation

The audit architecture records security-relevant application events.

However, an ordinary database audit log should not automatically be
described as providing cryptographic non-repudiation.

A privileged administrator may be able to alter local records unless
stronger controls are used.

Potential improvements include:

-   append-only logging;
-   remote audit replication;
-   hash chaining;
-   periodic digital signatures;
-   write-once storage;
-   separation of audit administration.

The current research therefore uses the more conservative term
**accountability**.

------------------------------------------------------------------------

# 9.19 Centralized Versus Subsidiary Trust

The architecture intentionally distributes trust.

The central environment controls:

-   publication;
-   package signing;
-   recipient authorization;
-   PKI;
-   central download audit.

The subsidiary controls:

-   local import;
-   local encrypted storage;
-   staff permissions;
-   local viewing;
-   local audit.

This allows subsidiaries to operate after package transfer without
requiring continuous central connectivity.

The cost is more complex trust synchronization, especially for
certificate revocation and policy updates.

------------------------------------------------------------------------

# 9.20 Failure Isolation

The architecture attempts to limit the consequences of individual
failures.

Examples:

``` text
Staff credential compromise
    != package-signing key compromise

One document DEK compromise
    != all document DEKs

Package CEK compromise
    != subsidiary KEK compromise

Browser view-token compromise
    != server storage-key compromise
```

This is a direct consequence of key and role separation.

Failure isolation is therefore an important design characteristic even
when absolute prevention is impossible.

------------------------------------------------------------------------

# 9.21 Comparison with Direct PDF Delivery

A conventional system may authenticate a user and return the original
PDF.

Advantages include:

-   implementation simplicity;
-   low server rendering cost;
-   native browser PDF functionality.

The proposed architecture accepts greater server complexity in exchange
for:

-   no routine original-PDF delivery;
-   server-controlled rendering;
-   personalized watermarking;
-   per-page authorization;
-   finer-grained audit events.

Whether this tradeoff is operationally justified depends on the
confidentiality requirements and measured overhead.

------------------------------------------------------------------------

# 9.22 Comparison with Simple Encryption at Rest

Encryption at rest protects storage media but may still produce this
flow:

``` text
encrypted PDF
   ↓
authorized server decrypt
   ↓
original PDF sent to browser
```

This protects server storage but does not address original-PDF exposure
after authorization.

The proposed architecture extends protection into the viewing stage.

Therefore, storage encryption and controlled viewing solve different
portions of the lifecycle.

------------------------------------------------------------------------

# 9.23 Threat Model Limitations

The threat model is intentionally bounded.

It does not claim complete protection against:

-   fully compromised trusted server operating systems;
-   hardware implants;
-   physical surveillance;
-   coercion of authorized users;
-   unknown cryptographic breaks;
-   all supply-chain compromise;
-   every browser/OS zero-day.

A useful security architecture must state these boundaries rather than
silently assume them away.

------------------------------------------------------------------------

# 9.24 Prototype Limitations

The current prototype has additional practical limitations.

## 9.24.1 Incomplete Native Cryptographic Import

The complete token → CEK → AES-GCM → local DEK/KEK path remains
implementation work.

## 9.24.2 Canonical Package Representation

The production package format requires deterministic signed
serialization and duplicate-key rejection.

## 9.24.3 Hardware Token Selection

The exact token, API, key type, and recipient key-protection mechanism
remain dependent on final hardware selection.

## 9.24.4 Server Key Protection

A software-based prototype KEK cannot be assumed to provide
HSM-equivalent protection.

## 9.24.5 Experimental Results

The final performance and security results remain pending execution of
Chapter 8.

------------------------------------------------------------------------

# 9.25 Generalizability

Although the case study focuses on confidential PDF documents and
isolated subsidiaries, several architectural ideas may apply more
broadly:

-   engineering drawings;
-   legal documents;
-   financial reports;
-   internal investigation files;
-   regulated records;
-   offline field environments.

However, generalization requires validation against each domain's threat
model and operational constraints.

The architecture should not be assumed to satisfy unrelated regulatory
requirements without separate analysis.

------------------------------------------------------------------------

# 9.26 Research Contribution

The research contribution should be stated conservatively.

It does not claim invention of:

-   AES-GCM;
-   PKI;
-   digital signatures;
-   hardware tokens;
-   server-side rendering;
-   watermarking.

Instead, the contribution is the systematic integration of these
mechanisms into a lifecycle architecture designed for controlled
document distribution between isolated enterprise environments.

Specific contributions include:

1.  a lifecycle-oriented secure document architecture;
2.  explicit central/subsidiary trust-domain separation;
3.  recipient-bound encrypted package distribution;
4.  separation of distribution keys from local-storage keys;
5.  a double-envelope CEK/DEK/KEK model;
6.  staff authentication separated from document decryption;
7.  server-side controlled viewing without routine original-PDF
    delivery;
8.  personalized viewing attribution;
9.  threat-to-requirement-to-control traceability;
10. a reproducible experimental evaluation framework.

------------------------------------------------------------------------

# 9.27 Practical Implications

For organizations with isolated sites, the architecture suggests that
secure document distribution does not necessarily require direct
site-to-site connectivity.

Instead, security can be embedded in the document package and reinforced
by local authorization and encrypted storage.

However, successful deployment requires more than application code.

Organizations also need:

-   PKI governance;
-   token lifecycle management;
-   key backup/recovery;
-   revocation procedures;
-   security-extension deployment controls;
-   operating-system hardening;
-   audit review;
-   incident response;
-   staff training.

The architecture is therefore socio-technical rather than purely
cryptographic.

------------------------------------------------------------------------

# 9.28 Future Work

Several improvements are appropriate for future research.

## 9.28.1 Complete Hardware Token Integration

Implement and evaluate the selected production SEC token.

## 9.28.2 Complete Native Cryptographic Pipeline

Implement:

``` text
recipient validation
CEK recovery
AES-GCM package decryption
local DEK generation
local master encryption
KEK protection
```

## 9.28.3 HSM/TPM Integration

Evaluate hardware-backed server KEKs.

## 9.28.4 Rendering Isolation

Run PDF rendering inside a stronger sandbox/container or dedicated
rendering service.

## 9.28.5 Tamper-Evident Audit

Evaluate hash-chained or signed audit records.

## 9.28.6 Forensic Watermarking

Investigate invisible or content-adaptive watermarking.

## 9.28.7 Automated Revocation Transfer

Evaluate secure mechanisms for reducing offline revocation delay.

## 9.28.8 Modernized Application Framework

Investigate migration from the legacy ZF1 application while preserving
the security architecture.

## 9.28.9 Formal Protocol Analysis

A future study could model the package protocol using a formal
security-analysis tool.

------------------------------------------------------------------------

# 9.29 Result-Dependent Discussion Template

After Chapter 8 is executed, the following section should be completed.

## Security Results

``` text
Application tests passed:     TBD / TBD
Application tests failed:     TBD
Crypto tests passed:          TBD / TBD
Crypto tests blocked:         TBD
Unexpected vulnerabilities:   TBD
```

## Performance Results

``` text
Warm-page median:             TBD
Warm-page P95:                TBD
Cold-page median:             TBD
Watermark overhead:           TBD
Maximum tested concurrency:   TBD
Observed bottleneck:          TBD
```

## Interpretation

Questions to answer:

``` text
Were all unauthorized page requests rejected?

Did any identifier manipulation expose content?

Did permission revocation stop subsequent page access?

Was original PDF exposure observed?

Did clean-page caching materially reduce latency?

At what concurrency did latency increase significantly?

Did browser memory remain bounded during long-document scrolling?

Which cryptographic operation dominated package import?

Did any security test reveal an implementation defect?
```

Any discovered weakness should be discussed rather than omitted.

------------------------------------------------------------------------

# 9.30 Validity of Conclusions

Final conclusions should be proportional to the evidence.

If an experiment demonstrates that 20 defined attack cases were
rejected, the conclusion should be:

> The prototype rejected the tested attack cases under the documented
> environment and conditions.

It should not become:

> The system is secure against all attacks.

Likewise, a performance result from one server configuration should be
reported as an observed result for that environment, not a universal
performance guarantee.

------------------------------------------------------------------------

# 9.31 Chapter Summary

This chapter discussed the implications, tradeoffs, limitations, and
potential contributions of the proposed secure document architecture.

The design treats confidential document protection as a lifecycle
problem spanning publication, cryptographic distribution, recipient
authentication, subsidiary import, encrypted local storage, staff
authorization, controlled rendering, personalized viewing, and auditing.

Key separation and the CEK/DEK/KEK model provide failure isolation and
manageable storage-key rotation. Server-side rendering reduces routine
original-PDF exposure but introduces performance cost and a trusted
plaintext-processing point. Hardware-backed authentication can reduce
credential-copying risk but does not replace endpoint security. Offline
operation supports organizational isolation while necessarily creating
challenges for revocation freshness and software maintenance.

The research contribution is not a new cryptographic primitive. It is
the integration and evaluation of established mechanisms within a
coherent architecture for isolated enterprise document distribution.

Several conclusions remain intentionally pending until the Chapter 8
experiments are executed. The final thesis should update this chapter
with measured performance, observed security-test outcomes, discovered
implementation weaknesses, and evidence-based interpretation.

The final chapter, **Chapter 10 --- Conclusion and Future Work**, can
summarize the research problem, proposed solution, contributions,
limitations, and future directions while carefully distinguishing
demonstrated results from proposed capabilities.

------------------------------------------------------------------------

# Chapter 10 --- Conclusion and Future Work

## 10.1 Introduction

This thesis investigated the design of a secure architecture for
distributing, importing, storing, viewing, and auditing confidential PDF
documents across organizational environments with limited or isolated
network connectivity.

The research was motivated by an enterprise scenario in which a central
organization must distribute sensitive documents to multiple
subsidiaries while preventing unintended subsidiaries or users from
accessing those documents. The environment also requires integration
with an existing legacy application stack rather than assuming a
complete system replacement.

The proposed architecture addresses the complete document lifecycle:

``` text
Central publication
        ↓
Cryptographically protected package
        ↓
Authorized recipient download
        ↓
Controlled transfer
        ↓
Subsidiary verification and import
        ↓
Local server-side re-encryption
        ↓
Encrypted master storage
        ↓
Staff authentication and authorization
        ↓
Server-side page rendering
        ↓
Personalized watermarking
        ↓
Secure browser viewing
        ↓
Audit and accountability
```

The principal conclusion at the current stage is architectural rather
than empirical: the research has defined a coherent, threat-driven
design and prototype framework that integrates established cryptographic
and application-security mechanisms while explicitly recognizing the
limitations of disconnected operation and authorized viewing.

Final empirical conclusions must be added after the experiments defined
in Chapter 8 have been executed.

------------------------------------------------------------------------

# 10.2 Research Aim Revisited

The research aim was to investigate how confidential documents can be
securely distributed and viewed across isolated enterprise networks
while minimizing exposure of plaintext documents and cryptographic keys.

This aim was addressed through several complementary design decisions:

-   cryptographic protection travels with the distributed package;
-   packages are signed by a central trusted identity;
-   package-key access is bound to an authorized recipient cryptographic
    capability;
-   distribution keys are separated from subsidiary storage keys;
-   imported documents are re-encrypted locally;
-   staff certificates authenticate users but do not directly decrypt
    master documents;
-   document authorization is evaluated independently from
    authentication;
-   browsers normally receive rendered page representations rather than
    original PDFs;
-   personalized watermarks provide viewing-context attribution;
-   security-relevant actions are auditable.

These mechanisms form a layered architecture rather than relying on one
security control.

------------------------------------------------------------------------

# 10.3 Summary of the Proposed Architecture

The system is divided into central and subsidiary trust domains.

## Central Domain

The central organization is responsible for:

``` text
document publication
package generation
package signing
recipient selection
SEC token authentication
PKI management
download authorization
central auditing
```

## Transfer Domain

The `.sec` package may travel through a medium that is not inherently
trusted for confidentiality.

The package therefore protects the document cryptographically.

## Subsidiary Domain

The subsidiary is responsible for:

``` text
package verification
recipient/token operation
authenticated package decryption
local re-encryption
encrypted master storage
staff authentication
document authorization
secure rendering
watermarking
local auditing
```

This separation supports operation without permanent direct connectivity
between subsidiaries.

------------------------------------------------------------------------

# 10.4 Cryptographic Design Conclusion

The proposed cryptographic design uses two envelope-encryption stages.

## Distribution Envelope

``` text
Random package CEK
        |
        +--> AES-256-GCM --> encrypted PDF payload
        |
        +--> recipient-specific protection
```

The package is additionally authenticated by a central digital
signature.

## Local Storage Envelope

After successful authorized import:

``` text
Random local DEK
        |
        +--> AES-256-GCM --> encrypted local master

Local DEK
        |
        +--> protected by versioned subsidiary KEK
```

The distribution CEK is not reused as the long-term local storage key.

This separation provides clearer key responsibilities and reduces the
consequences of individual key compromise.

------------------------------------------------------------------------

# 10.5 Key-Separation Conclusion

A major design principle of the research is that different security
functions should not silently share one key.

The architecture distinguishes:

``` text
Root/issuing CA keys
Package-signing key
SEC token private key
Package CEK
Local document DEK
Subsidiary KEK
Staff authentication key
```

This separation improves failure isolation.

For example, compromise of a staff authentication key does not
automatically reveal the subsidiary KEK, and compromise of one local DEK
need not expose every other document.

The architecture therefore treats key management as a system-design
problem rather than merely an encryption-algorithm choice.

------------------------------------------------------------------------

# 10.6 Authentication and Authorization Conclusion

The research separates authentication from authorization.

``` text
Authentication:
Who is the user?

Authorization:
Which document may that user access?
```

Hardware-backed SEC token authentication is proposed for sensitive
central operations, with cryptographic challenge-response used to
demonstrate private-key possession.

Within subsidiaries, staff certificates provide authentication and
identity binding.

Document access is then determined through resource-specific
authorization.

This separation is particularly important because possession of a valid
organizational certificate should not automatically imply permission to
view every confidential document.

------------------------------------------------------------------------

# 10.7 Offline PKI Conclusion

Disconnected environments create a fundamental certificate-revocation
challenge.

The proposed architecture addresses this through signed offline
revocation and policy updates.

However, the design cannot eliminate the interval between:

``` text
central revocation
        and
subsidiary receipt of updated revocation state
```

The thesis therefore recognizes revocation freshness as an operational
security parameter.

A disconnected architecture must define a maximum acceptable
revocation-data age and a procedure for emergency updates.

------------------------------------------------------------------------

# 10.8 Secure Viewing Conclusion

The proposed viewer avoids routine delivery of the original PDF to the
browser.

Instead:

``` text
authorized page request
        ↓
server authorization
        ↓
protected source/cache access
        ↓
server-side rendering
        ↓
personalized watermark
        ↓
WebP/PNG page
        ↓
HTML5 Canvas
```

This approach provides several capabilities unavailable in simple
direct-PDF delivery:

-   per-page authorization;
-   personalized page attribution;
-   granular viewing audit;
-   controlled rendering profiles;
-   no normal public URL for the original PDF.

The design does not claim that visible information cannot be copied.

Screenshots, photographs, screen recording, and compromised endpoints
remain residual risks.

------------------------------------------------------------------------

# 10.9 Watermarking Conclusion

Personalized watermarking contributes primarily to accountability and
deterrence.

A rendered page can contain:

``` text
subsidiary identity
staff identifier
document identifier
view display code
timestamp
```

The display code can be mapped to internal audit information without
exposing raw authentication or view tokens.

Visible watermarking should not be characterized as an access-control
mechanism or an absolute copy-prevention technology.

------------------------------------------------------------------------

# 10.10 Prototype Implementation Conclusion

The proposed architecture was mapped to a prototype environment based
on:

``` text
CentOS
Apache
PHP 7.4
Zend Framework 1
MariaDB
OpenSSL
concheron_sec.so
Poppler
ImageMagick / Imagick
JavaScript / HTML5 Canvas
```

The prototype demonstrates how a legacy enterprise application can be
extended with dedicated security services rather than requiring
immediate replacement of the entire system.

Application-level components include:

-   authorization service;
-   view-session service;
-   secure page controller;
-   renderer service;
-   watermark service;
-   audit service;
-   protected filesystem layout;
-   MariaDB security tables;
-   Canvas viewer.

------------------------------------------------------------------------

# 10.11 Native Security Extension Conclusion

The custom `concheron_sec.so` extension defines a narrow native boundary
for sensitive cryptographic operations.

The intended design avoids returning raw private keys or document keys
to ordinary PHP application code.

The current prototype establishes part of the package-verification
architecture, including trusted signing-key configuration and
signed-policy validation.

However, the complete native cryptographic pipeline remains unfinished.

Specifically, production implementation and testing are still required
for:

``` text
hardware-token discovery
PIN/token activation
recipient private-key operation
CEK recovery
AES-GCM payload authentication/decryption
local DEK generation
local master encryption
KEK-based DEK protection
production canonical package representation
```

The thesis must not describe these unfinished functions as
experimentally validated.

------------------------------------------------------------------------

# 10.12 Security Analysis Conclusion

Chapter 7 evaluated the architecture against twenty-five identified
threats.

The design contains direct controls for threats including:

-   stolen package files;
-   wrong-subsidiary import;
-   package tampering;
-   forged packages;
-   replay/version downgrade;
-   authentication replay;
-   server-storage theft;
-   IDOR;
-   direct original-PDF access;
-   direct clean-cache access;
-   SQL injection;
-   renderer command injection;
-   permission revocation during viewing.

Other threats can only be partially reduced.

Examples include:

-   delayed offline certificate revocation;
-   temporary plaintext exposure;
-   malicious PDF parser vulnerabilities;
-   bulk extraction by authorized users;
-   watermark removal;
-   privileged audit tampering;
-   fully compromised servers.

This distinction prevents the architecture from being presented as
providing absolute security.

------------------------------------------------------------------------

# 10.13 Experimental Evaluation Status

Chapter 8 defines a reproducible experimental framework.

The evaluation includes:

``` text
20 application security tests
view-token tests
IDOR tests
protected-storage tests
temporary plaintext cleanup
watermark tests
rendering benchmarks
cold/warm cache comparison
large-document browser tests
concurrency tests
rate-limit tests
audit validation
20 cryptographic tests
cryptographic performance tests
```

At the time of this provisional conclusion, result-dependent values
remain unreported until the experiments are executed.

The final thesis must replace relevant `TBD`, `NOT EXECUTED`, and
`BLOCKED` markers with actual evidence.

------------------------------------------------------------------------

# 10.14 Research Contributions

The thesis makes the following proposed contributions.

## 10.14.1 Lifecycle-Oriented Security Architecture

The research treats document protection as a lifecycle spanning
publication through viewing and auditing.

## 10.14.2 Secure Distribution Across Isolated Networks

The design allows cryptographically protected packages to cross
disconnected organizational boundaries without relying solely on the
transfer medium for confidentiality.

## 10.14.3 Recipient-Bound Package Protection

The package CEK is intended to require the authorized recipient
cryptographic capability.

## 10.14.4 Distribution/Storage Key Separation

The distribution CEK is separated from the local DEK and subsidiary KEK.

## 10.14.5 Authentication/Decryption Separation

Staff authentication certificates do not directly become
document-storage decryption keys.

## 10.14.6 Controlled Server-Side Viewing

The browser normally receives authorized rendered pages rather than the
original PDF.

## 10.14.7 Personalized Accountability

Watermarks and view-session identifiers connect delivered pages to
viewing context.

## 10.14.8 Threat-to-Control Traceability

Threats, security requirements, architecture controls, implementation
components, and experiments are explicitly linked.

## 10.14.9 Legacy-System Integration

The architecture demonstrates a path for introducing stronger document
security into a ZF1/PHP/MariaDB enterprise application.

## 10.14.10 Reproducible Evaluation Framework

The research defines security and performance tests that can be executed
and reported without relying solely on architectural reasoning.

------------------------------------------------------------------------

# 10.15 Research Contribution Boundary

The thesis does not claim invention of the underlying cryptographic
primitives.

It does not introduce a new:

-   symmetric cipher;
-   public-key algorithm;
-   digital-signature algorithm;
-   PKI standard;
-   watermarking primitive.

The contribution is the integration, adaptation, threat analysis,
prototype implementation, and evaluation of established mechanisms for a
particular enterprise security problem.

This distinction is important for an academically defensible novelty
claim.

------------------------------------------------------------------------

# 10.16 Practical Contributions

In addition to academic analysis, the research produces practical
engineering artifacts.

These include:

``` text
security requirements
threat model
architecture specification
.sec package design
PKI design
key-management model
native PHP-extension interface
MariaDB data model
secure page API
server-side rendering architecture
watermarking architecture
Canvas viewer design
security-test plan
performance-test plan
deployment guidance
```

These artifacts can support future implementation beyond the thesis
prototype.

------------------------------------------------------------------------

# 10.17 Limitations

The research has several limitations.

## 10.17.1 Incomplete Cryptographic Prototype

The complete recipient-bound decryption pipeline remains unfinished.

## 10.17.2 Hardware Dependency

The final recipient-key mechanism depends on the selected hardware SEC
token.

## 10.17.3 Offline Revocation Delay

Immediate central revocation cannot be guaranteed in a fully
disconnected subsidiary.

## 10.17.4 Trusted Server Requirement

The server must process plaintext during legitimate import and
rendering.

A fully compromised trusted server may therefore expose document
content.

## 10.17.5 Authorized Endpoint Leakage

The architecture cannot prevent all screenshots, photographs, screen
recording, or malware-based capture.

## 10.17.6 Visible Watermark Removal

A determined recipient may alter or remove visible watermarking.

## 10.17.7 Prototype Generalizability

Performance measurements from one environment cannot automatically be
generalized to every deployment.

## 10.17.8 Legacy Platform

PHP 7.4 and ZF1 introduce lifecycle and maintenance considerations that
must be addressed operationally or through future modernization.

------------------------------------------------------------------------

# 10.18 Future Work

Future research and engineering should address the following areas.

## 10.18.1 Complete Hardware Token Integration

Select a production token and implement:

-   device discovery;
-   PIN handling;
-   challenge-response;
-   recipient key operation;
-   certificate/device identity validation.

## 10.18.2 Complete Cryptographic Package Import

Implement and test:

``` text
CEK recovery
AES-GCM authenticated decryption
canonical package processing
strict duplicate-field rejection
local DEK generation
local master encryption
KEK protection
```

## 10.18.3 Hardware-Backed Server Keys

Evaluate HSM or TPM protection for subsidiary KEKs.

## 10.18.4 Renderer Sandboxing

Isolate Poppler/ImageMagick processing through:

-   containers;
-   sandboxing;
-   dedicated service accounts;
-   seccomp or equivalent mechanisms where appropriate;
-   dedicated rendering hosts.

## 10.18.5 Tamper-Evident Auditing

Investigate:

-   hash chains;
-   signed audit batches;
-   remote log replication;
-   append-only storage.

## 10.18.6 Forensic Watermarking

Evaluate invisible watermarking techniques in addition to visible
attribution.

## 10.18.7 Revocation Synchronization

Investigate mechanisms that reduce revocation delay while preserving
network isolation requirements.

## 10.18.8 Framework Modernization

Evaluate migration from Zend Framework 1 to a supported application
architecture while retaining the security model.

## 10.18.9 Formal Security Verification

Model critical protocol elements using formal methods to identify
authentication, replay, or key-binding errors.

## 10.18.10 Broader Document Types

Evaluate whether the architecture can safely support:

-   office documents;
-   engineering files;
-   images;
-   structured confidential reports.

Each format requires separate parser/rendering risk analysis.

------------------------------------------------------------------------

# 10.19 Recommended Implementation Roadmap

The practical next steps are:

``` text
Phase 1
Finalize .sec canonical format

Phase 2
Select hardware SEC token

Phase 3
Implement token interface

Phase 4
Implement CEK recovery

Phase 5
Implement AES-GCM package decryption

Phase 6
Implement local DEK/KEK envelope

Phase 7
Compile/test concheron_sec.so on target CentOS/PHP 7.4

Phase 8
Integrate complete import path into ZF1

Phase 9
Execute Chapter 8 application/viewer tests

Phase 10
Execute Chapter 8 cryptographic tests

Phase 11
Collect performance/concurrency measurements

Phase 12
Update Chapters 8–10 with observed results
```

This roadmap also separates engineering completion from thesis-result
reporting.

------------------------------------------------------------------------

# 10.20 Final Result Placeholder

After experimentation, this section should be replaced with a concise
evidence-based summary.

Example structure:

``` text
Application security tests:
    [actual passed]/[actual executed]

Cryptographic tests:
    [actual passed]/[actual executed]

Median warm-page latency:
    [actual value]

P95 warm-page latency:
    [actual value]

Maximum evaluated concurrency:
    [actual value]

Principal performance bottleneck:
    [observed component]

Security defects discovered:
    [actual findings]

Remediations:
    [actual changes]
```

No values should be inserted until measured.

------------------------------------------------------------------------

# 10.21 Final Conclusion

This research proposes a secure cryptographic architecture for
controlled distribution and viewing of confidential PDF documents across
isolated enterprise networks.

The architecture combines recipient-bound encrypted packages, digital
signatures, PKI, hardware-assisted authentication, key separation,
server-side re-encryption, resource-level authorization, protected
server-side rendering, personalized watermarking, short-lived viewing
sessions, and auditing.

A central design principle is that security should persist across the
complete document lifecycle rather than ending when a file reaches the
subsidiary or when a user successfully authenticates.

The research also demonstrates that confidentiality and accountability
involve unavoidable tradeoffs. A document that can be legitimately
viewed can potentially be captured. An isolated network cannot receive
instantaneous revocation information without some communication
mechanism. A server that renders confidential content must temporarily
process plaintext. A key that protects long-term data must also have a
carefully controlled recovery strategy.

Consequently, the architecture does not claim absolute document-control
security.

Instead, it seeks to:

``` text
reduce unnecessary plaintext exposure
restrict cryptographic key access
separate security responsibilities
detect unauthorized modification
enforce document-level authorization
avoid routine original-PDF delivery
increase viewing accountability
provide auditable security decisions
```

These properties define a practical and testable security architecture
for the target enterprise environment.

The final strength of the research conclusions will depend on completing
the cryptographic prototype and executing the experimental methodology
defined in Chapter 8. Once those measurements are available, the thesis
can replace provisional statements with observed evidence and provide a
final quantitative assessment of security behavior, performance
overhead, and operational feasibility.

------------------------------------------------------------------------

# Consolidated References

The following standards and sources form the principal technical
reference set used throughout the thesis. Before final university
submission, the citation style and bibliographic metadata should be
normalized to the institution's required format, and any
chapter-specific sources should be reconciled with this master list.

1.  National Institute of Standards and Technology (NIST),
    *Recommendation for Block Cipher Modes of Operation: Galois/Counter
    Mode (GCM) and GMAC*, NIST Special Publication 800-38D.
2.  D. McGrew, *An Interface and Algorithms for Authenticated
    Encryption*, RFC 5116, Internet Engineering Task Force.
3.  K. Moriarty et al., *PKCS #1: RSA Cryptography Specifications
    Version 2.2*, RFC 8017, Internet Engineering Task Force.
4.  National Institute of Standards and Technology (NIST),
    *Recommendation for Key Management: Part 1 --- General*, NIST
    Special Publication 800-57 Part 1, Revision 5.
5.  D. Cooper et al., *Internet X.509 Public Key Infrastructure
    Certificate and Certificate Revocation List (CRL) Profile*, RFC
    5280, Internet Engineering Task Force.
6.  National Institute of Standards and Technology (NIST), *Digital
    Identity Guidelines --- Authentication and Lifecycle Management*,
    NIST Special Publication 800-63B.
7.  National Institute of Standards and Technology (NIST), *Zero Trust
    Architecture*, NIST Special Publication 800-207.
8.  OWASP Foundation, *Authorization Cheat Sheet*.
9.  OWASP Foundation, *File Upload Cheat Sheet*.
10. R. Su, F. Hartung, and B. Girod, research on digital watermarking
    for multimedia/document protection, 1998.
11. Relevant contemporary survey literature on digital watermarking,
    including the watermarking review discussed in Chapter 2.
12. A. Rundgren, B. Jordan, and S. Erdtman, *JSON Canonicalization
    Scheme (JCS)*, RFC 8785, Internet Engineering Task Force.
13. National Institute of Standards and Technology (NIST),
    *Recommendation for Random Number Generation Using Deterministic
    Random Bit Generators*, NIST Special Publication 800-90A Revision 1.

## Final Bibliography Preparation Note

The final submitted thesis should verify every bibliographic entry
against the exact edition used, add DOI/URL/access-date fields only
where required by the university style, remove duplicates, and ensure
every in-text citation has a matching reference entry. Experimental
software versions should be reported in Chapter 8 rather than treated as
bibliographic references unless the institution requires otherwise.
