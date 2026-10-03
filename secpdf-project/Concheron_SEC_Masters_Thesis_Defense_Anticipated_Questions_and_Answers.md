# Master's Thesis Defense Preparation

## Anticipated Questions and Suggested Answers

**Research title:**\
*A Secure Cryptographic Architecture for Controlled Distribution and
Viewing of Confidential PDF Documents in Isolated Enterprise Networks*

> **Purpose of this document:** This guide is for oral-defense
> preparation. The suggested answers are intentionally concise,
> academically cautious, and suitable for speaking. Adapt them to your
> actual implementation and experimental results before the final
> defense.

------------------------------------------------------------------------

# 1. Core Research Questions

## Q1. What is your research about?

**Suggested answer:**

My research proposes a secure architecture for distributing and viewing
confidential PDF documents across isolated enterprise networks.

The main problem is that confidential documents must move from a central
organization to subsidiaries that may not have continuous connectivity,
while preventing unauthorized subsidiaries or users from accessing them.

My architecture combines encrypted document packages, digital
signatures, PKI, hardware-assisted authentication, server-side
re-encryption, document-level authorization, server-side PDF rendering,
personalized watermarking, and auditing.

The main idea is to protect the document throughout its lifecycle rather
than protecting only the file-transfer stage.

------------------------------------------------------------------------

## Q2. What is your main research question?

**Suggested answer:**

My main research question is:

> How can confidential documents be securely distributed, decrypted,
> stored, viewed, and audited across isolated organizational networks
> while minimizing exposure of plaintext documents and cryptographic
> keys?

------------------------------------------------------------------------

## Q3. What problem are you trying to solve?

**Suggested answer:**

Traditional document systems often protect documents during transmission
or while stored on a server. However, once an authorized user downloads
the original PDF, the server loses much of its control over that
document.

My research addresses the complete lifecycle: secure distribution,
recipient verification, import, encrypted local storage, authorization,
controlled viewing, watermarking, and auditing.

It also specifically considers subsidiaries that cannot rely on
continuous communication with the central system.

------------------------------------------------------------------------

## Q4. What is novel about your research?

**Suggested answer:**

The novelty is not a new cryptographic algorithm.

AES-GCM, PKI, digital signatures, hardware tokens, server-side
rendering, and watermarking already exist.

My contribution is their systematic integration into a
lifecycle-oriented architecture for confidential document distribution
across isolated enterprise networks.

In particular, the architecture separates distribution keys, local
storage keys, and staff authentication credentials; combines
recipient-bound encrypted packages with local re-encryption; and extends
protection into the viewing stage so that the browser does not normally
receive the original PDF.

I also provide threat-to-requirement-to-control traceability and an
experimental framework for evaluating the architecture.

------------------------------------------------------------------------

# 2. Architecture Questions

## Q5. Can you explain the architecture simply?

**Suggested answer:**

There are two main security domains: the central organization and the
subsidiary.

The central organization creates a secure package containing an
encrypted PDF. The package is digitally signed and restricted to an
authorized recipient.

The authorized subsidiary imports that package using the required
cryptographic capability.

After import, the subsidiary does not keep the original PDF in
plaintext. It encrypts it again using a local document key.

When an authorized staff member wants to view the document, the server
decrypts it in a controlled environment, renders individual pages, adds
a personalized watermark, and sends page images to the browser.

Therefore, the original PDF is not normally delivered to the user's
browser.

------------------------------------------------------------------------

## Q6. Why not simply use HTTPS?

**Suggested answer:**

HTTPS protects communication between two endpoints while the connection
is active.

My problem also involves documents that may be downloaded, stored,
transferred through controlled offline mechanisms, and later imported
into another network.

Therefore, the document package itself needs cryptographic protection.

HTTPS is still necessary for online communication, but it does not
replace package-level encryption and signatures.

------------------------------------------------------------------------

## Q7. Why not just password-protect the PDF?

**Suggested answer:**

A PDF password would couple access control directly to a shared document
secret and creates difficult password-distribution, rotation, and
revocation problems.

My design separates document encryption from user authentication and
authorization.

The server can revoke a user's access without changing the encryption of
every document, and different cryptographic keys have different
responsibilities.

------------------------------------------------------------------------

## Q8. Why don't you simply encrypt the PDF with the recipient's RSA public key?

**Suggested answer:**

Public-key cryptography is not appropriate for encrypting a large PDF
directly.

Instead, I use hybrid encryption.

A random symmetric content-encryption key encrypts the PDF using
AES-GCM. The much smaller symmetric key is then protected for the
intended recipient using an appropriate public-key mechanism.

This provides efficient bulk encryption together with recipient-specific
key protection.

------------------------------------------------------------------------

# 3. CEK, DEK, and KEK Questions

## Q9. What is a CEK?

**Suggested answer:**

CEK means Content-Encryption Key.

In my architecture, a fresh CEK encrypts the PDF inside the distributed
SEC package using AES-256-GCM.

The CEK is then protected for the intended recipient.

------------------------------------------------------------------------

## Q10. What is a DEK?

**Suggested answer:**

DEK means Data-Encryption Key.

After the subsidiary successfully imports the package, it generates a
new local DEK.

That DEK encrypts the locally stored master PDF.

Therefore, the package CEK is not reused as the long-term local storage
key.

------------------------------------------------------------------------

## Q11. What is a KEK?

**Suggested answer:**

KEK means Key-Encryption Key.

The subsidiary's KEK protects the per-document DEKs.

This allows the system to rotate a KEK by re-protecting relatively small
DEKs instead of decrypting and re-encrypting every large PDF.

------------------------------------------------------------------------

## Q12. Why do you need CEK, DEK, and KEK? Isn't that unnecessarily complicated?

**Suggested answer:**

The separation is intentional.

The CEK belongs to the distribution lifecycle. The DEK belongs to local
document storage. The KEK protects local DEKs.

If one key is compromised, the security impact can therefore be more
limited.

It also makes key rotation and lifecycle management more practical.

The additional complexity provides compartmentalization and separation
of cryptographic responsibilities.

**Simple diagram:**

``` text
Distribution

PDF
 |
 v
CEK
 |
 v
Encrypted SEC package


After import

PDF
 |
 v
DEK
 |
 v
Encrypted local master

DEK
 |
 v
KEK
 |
 v
Protected DEK
```

------------------------------------------------------------------------

# 4. AES-GCM Questions

## Q13. Why did you choose AES-256-GCM?

**Suggested answer:**

AES-GCM provides authenticated encryption.

That means it protects both confidentiality and integrity.

If ciphertext or authenticated metadata is modified, authentication
should fail rather than silently returning modified plaintext.

AES-GCM is also standardized and widely supported by mature
cryptographic libraries.

------------------------------------------------------------------------

## Q14. Why not AES-CBC?

**Suggested answer:**

AES-CBC provides encryption but does not itself provide authentication.

It therefore requires a separate integrity mechanism and careful
composition.

AES-GCM is an AEAD construction, so confidentiality and integrity are
provided together through a standardized authenticated-encryption
interface.

------------------------------------------------------------------------

## Q15. What is one important danger when using AES-GCM?

**Suggested answer:**

Nonce reuse.

A GCM nonce must not be reused with the same encryption key.

Reusing a nonce with the same key can seriously compromise
confidentiality and authentication.

Therefore, nonce generation and key lifecycle management are explicit
parts of my cryptographic design.

------------------------------------------------------------------------

## Q16. What is AAD?

**Suggested answer:**

AAD means Additional Authenticated Data.

It is metadata that does not need to be encrypted but must be protected
against modification.

For example, security-relevant package metadata can be cryptographically
bound to the encrypted content so that unauthorized modification is
detected.

------------------------------------------------------------------------

# 5. Digital Signature Questions

## Q17. Why do you need a digital signature if AES-GCM already provides integrity?

**Suggested answer:**

AES-GCM proves that ciphertext was not modified relative to the
encryption key, but it does not by itself establish the publisher's
identity to an independent recipient.

The digital signature provides publisher authenticity and package
integrity using the central organization's signing identity.

Therefore, encryption and signatures address different security
properties.

------------------------------------------------------------------------

## Q18. What happens if the signing private key is stolen?

**Suggested answer:**

That is a serious compromise because an attacker may be able to create
packages that appear to come from the central organization.

The response would require revoking or retiring the compromised signing
certificate, issuing a new signing key, distributing updated trust
information, investigating packages signed during the affected period,
and auditing the incident.

This is why the signing key should be strongly protected and separated
from ordinary application keys.

------------------------------------------------------------------------

# 6. Hardware Token Questions

## Q19. Why use a hardware token?

**Suggested answer:**

The main benefit is protection of the private key.

With an ordinary `.p12` file stored on a USB flash drive, the private
key may be copied.

With an appropriate hardware cryptographic token, the private key can be
non-exportable. Cryptographic operations occur inside the device.

This makes simple credential copying significantly more difficult.

------------------------------------------------------------------------

## Q20. What happens if somebody steals the USB token?

**Suggested answer:**

Possession of the token alone should not be sufficient.

The user must also provide the token PIN or password, and the server
validates the certificate, authorization state, and cryptographic proof
of private-key possession.

However, if an attacker obtains both the token and its PIN, the risk
becomes much greater.

Hardware tokens reduce credential-copying risk; they do not eliminate
every authentication threat.

------------------------------------------------------------------------

## Q21. Why use challenge-response?

**Suggested answer:**

The server generates a fresh random challenge.

The token signs that challenge using its private key, and the server
verifies the signature using the corresponding public key.

This demonstrates possession of the private key without sending the
private key to the server.

Fresh challenges also help prevent replay of an old authentication
response.

------------------------------------------------------------------------

# 7. Certificate and PKI Questions

## Q22. What does PKI do in your architecture?

**Suggested answer:**

PKI establishes trusted relationships between identities and public
keys.

My architecture uses certificates for purposes such as hardware-token
identity, staff authentication, server identity, and package signing.

Certificate purposes are separated so that one certificate is not
automatically trusted for every cryptographic operation.

------------------------------------------------------------------------

## Q23. Why separate certificate purposes?

**Suggested answer:**

Separation reduces unintended privilege and compromise scope.

For example, a staff authentication certificate should not automatically
be accepted as a package-signing certificate.

The system validates certificate purpose, issuer, validity period,
revocation state, and organizational identity according to the operation
being performed.

------------------------------------------------------------------------

## Q24. How do you revoke certificates when a subsidiary is offline?

**Suggested answer:**

This is one of the important limitations of isolated networks.

The central organization can produce signed revocation or
certificate-update packages that are transferred to the subsidiary.

The subsidiary verifies the update and updates its local revocation
state.

However, there is inevitably a delay between central revocation and
receipt by an offline subsidiary.

Therefore, my architecture treats maximum acceptable revocation-data age
as an explicit security policy.

------------------------------------------------------------------------

# 8. Server-Side Re-encryption Questions

## Q25. Why decrypt and then encrypt the PDF again after import?

**Suggested answer:**

The distribution key and the local storage key have different security
responsibilities.

The package CEK exists to protect document distribution.

After successful import, the subsidiary creates a new local DEK and
encrypts the master document using that key.

This means ordinary future viewing does not require the original package
recipient credential or package CEK.

------------------------------------------------------------------------

## Q26. Isn't the PDF temporarily plaintext during this process?

**Suggested answer:**

Yes.

The architecture minimizes plaintext exposure but cannot completely
eliminate it.

Legitimate decryption, validation, rendering, and re-encryption require
plaintext processing somewhere.

Therefore, plaintext exists only within a controlled processing context
for as little time as practical, and temporary storage is protected and
cleaned up.

A fully compromised trusted server remains a residual risk.

------------------------------------------------------------------------

# 9. Secure Viewer Questions

## Q27. Why don't you send the original PDF to the browser?

**Suggested answer:**

Once the original PDF reaches the browser, the application loses much of
its ability to control the file.

My architecture instead renders individual pages on the server and sends
authorized page representations.

This supports per-page authorization, personalized watermarking,
short-lived view sessions, and more granular auditing while reducing
routine exposure of the original PDF.

------------------------------------------------------------------------

## Q28. Does that mean users cannot copy the document?

**Suggested answer:**

No.

If an authorized user can see information, they can potentially capture
it using screenshots, cameras, screen recording, or compromised
software.

My architecture reduces original-file exposure and increases
accountability, but it does not claim absolute copy prevention.

------------------------------------------------------------------------

## Q29. Why use Canvas?

**Suggested answer:**

Canvas gives the application control over page presentation and
integrates well with lazy loading, zoom behavior, page navigation, and
dynamic image delivery.

However, Canvas itself is not a security boundary.

The actual security decisions occur on the server.

------------------------------------------------------------------------

## Q30. Why use lazy loading?

**Suggested answer:**

Large documents may contain hundreds of pages.

Loading every page immediately would consume unnecessary network
bandwidth, browser memory, and server resources.

Lazy loading requests pages only when they approach the visible area.

------------------------------------------------------------------------

## Q31. Why unload distant canvases?

**Suggested answer:**

Without unloading, a 500-page document could consume a large amount of
browser memory.

The prototype therefore retains only a limited range around the current
page and releases distant rendered canvases.

This is primarily a performance and usability mechanism.

------------------------------------------------------------------------

# 10. Watermark Questions

## Q32. Why use personalized watermarks?

**Suggested answer:**

The watermark provides deterrence and accountability.

It can associate a displayed page with a subsidiary, staff member,
document, view identifier, and time.

If a screenshot is later leaked, the watermark may provide useful
investigation context.

------------------------------------------------------------------------

## Q33. Can a watermark be removed?

**Suggested answer:**

Potentially, yes.

A determined user may crop, modify, obscure, or reconstruct content.

Therefore, my thesis does not claim that watermarking prevents copying.

I treat it as an accountability and deterrence mechanism.

------------------------------------------------------------------------

## Q34. Why not put the session token in the watermark?

**Suggested answer:**

Because the session or view token is an authentication secret.

Displaying it would expose a credential.

Instead, the system generates a non-secret display or view identifier
that can be mapped internally to the corresponding audit record.

------------------------------------------------------------------------

# 11. Web-Security Questions

## Q35. How do you prevent IDOR?

**Suggested answer:**

Every document and page request performs server-side authorization.

The system does not assume that possession of a document ID, version ID,
or page number grants access.

The authenticated identity, document permission, view-session binding,
and requested resource are validated for each protected request.

------------------------------------------------------------------------

## Q36. Why check authorization for every page? Isn't checking once enough?

**Suggested answer:**

Permissions can change after a viewer session is created.

For example, an administrator might revoke access while the document is
open.

Rechecking authorization on protected page requests allows subsequent
access to stop after revocation.

Already displayed information cannot be withdrawn, but future requests
can be denied.

------------------------------------------------------------------------

## Q37. What happens if somebody steals a view token?

**Suggested answer:**

The token is high entropy and short-lived, and only its hash is stored
in the database.

It is also bound to contextual information such as the staff identity
and document/version.

However, token theft from a compromised authorized browser remains a
risk.

This is why XSS prevention is particularly important.

------------------------------------------------------------------------

## Q38. Why is XSS particularly dangerous in your system?

**Suggested answer:**

Because the browser temporarily possesses an active viewing credential
and confidential rendered page content.

Successful same-origin XSS could potentially act with the user's
privileges.

Therefore, output encoding, safe DOM operations, Content Security
Policy, and general XSS prevention are important parts of the viewer's
security.

------------------------------------------------------------------------

# 12. Native PHP Extension Questions

## Q39. Why did you create a PHP extension?

**Suggested answer:**

The extension creates a controlled boundary between the PHP application
and sensitive cryptographic operations.

The application uses high-level operations such as package verification
and secure import rather than manipulating private keys and raw document
keys directly.

It also allows the cryptographic implementation to be distributed as a
compiled component.

------------------------------------------------------------------------

## Q40. Does compiling the extension make the cryptography secure?

**Suggested answer:**

No.

Compilation may protect implementation details to some degree, but
compiled software can still be analyzed or reverse engineered.

My security model does not depend on keeping cryptographic algorithms
secret.

Security depends on established algorithms, correct implementation,
protected keys, authenticated protocols, and system controls.

------------------------------------------------------------------------

## Q41. Why not create your own encryption algorithm so subsidiaries cannot understand it?

**Suggested answer:**

Custom cryptographic algorithms are generally risky because they have
not received the same analysis as established standards.

My architecture uses established cryptographic primitives through mature
libraries such as OpenSSL.

The custom component controls how those primitives are integrated; it
does not attempt to invent a new cipher.

------------------------------------------------------------------------

# 13. Threat-Model Questions

## Q42. What are the most important threats?

**Suggested answer:**

Major threats include stolen packages, wrong-subsidiary access, package
modification, forged packages, replay, copied credentials,
server-storage theft, temporary plaintext exposure, IDOR, stolen view
tokens, direct PDF access, XSS, SQL injection, renderer command
injection, malicious PDFs, bulk extraction, watermark removal, audit
tampering, and key compromise.

------------------------------------------------------------------------

## Q43. What happens if an attacker steals an encrypted `.sec` package?

**Suggested answer:**

The package should not reveal the plaintext merely because the file has
been copied.

The PDF is encrypted using a random CEK, and recovery of that CEK
requires the authorized recipient cryptographic capability.

The precise strength of that property ultimately depends on correct
implementation and protection of the recipient key.

------------------------------------------------------------------------

## Q44. What if the server itself is completely compromised?

**Suggested answer:**

A fully compromised trusted server is one of the architecture's
important residual risks.

Because the server legitimately decrypts and renders documents, a
sufficiently privileged attacker may be able to observe plaintext during
processing or obtain storage keys.

Hardware-backed keys and stronger rendering isolation can reduce the
risk, but the thesis does not claim protection against every root-level
compromise.

------------------------------------------------------------------------

# 14. Research-Methodology Questions

## Q45. How are you evaluating the system?

**Suggested answer:**

I use both security and performance evaluation.

Security experiments test expected fail-closed behavior---for example,
wrong-recipient packages, modified ciphertext, IDOR attempts, invalid
view tokens, direct PDF access, permission revocation, and
command-injection inputs.

Performance experiments measure rendering latency, watermark overhead,
cold versus warm cache performance, browser memory, cryptographic
throughput, and concurrent-user behavior.

Importantly, I define the tests before collecting results.

------------------------------------------------------------------------

## Q46. Why is defining tests before running them important?

**Suggested answer:**

It reduces the risk of changing the evaluation criteria after seeing the
results.

For security tests, expected behavior is defined in advance.

For performance, I report measured distributions and do not invent an
acceptance threshold after observing the data.

------------------------------------------------------------------------

## Q47. Why don't you already have results?

**Suggested answer for the current research stage:**

The architecture, prototype design, threat analysis, and experimental
methodology have been completed, but the complete cryptographic pipeline
and experimental campaign are still being finalized.

Therefore, I deliberately mark unexecuted tests as `NOT EXECUTED`,
`BLOCKED`, or `TBD` rather than presenting expected behavior as
experimental evidence.

> **Important:** Replace this answer with your actual results once
> Chapter 8 experiments have been executed.

------------------------------------------------------------------------

## Q48. How many concurrent users will you test?

**Suggested answer:**

The planned levels are 1, 10, 25, 50, and 100 concurrent users.

These are experimental workload levels, not claims about production
capacity.

I will measure latency distributions, throughput, error rate, CPU,
memory, and relevant I/O at each level.

------------------------------------------------------------------------

## Q49. Why compare cold and warm cache?

**Suggested answer:**

A cold page requires expensive rendering work, while a warm page can
reuse an existing protected rendered representation.

Combining those measurements would hide the cost of rendering.

Separating them allows me to quantify the benefit of caching and
identify the real bottleneck.

------------------------------------------------------------------------

## Q50. Why use P95 instead of just average latency?

**Suggested answer:**

Average latency can hide slow requests.

P95 shows the latency below which approximately 95 percent of measured
requests fall and is useful for understanding the experience of slower
requests.

I also plan to report median and other descriptive statistics rather
than relying on one number.

------------------------------------------------------------------------

# 15. Difficult Questions About the Research Contribution

## Q51. Isn't this just combining existing technologies?

**Suggested answer:**

Yes, the individual technologies are established, and I explicitly
acknowledge that.

However, security architecture research can contribute through the
systematic composition of mechanisms for a particular threat model and
operational environment.

The research question is not whether AES or PKI exists. It is how these
mechanisms should interact across distribution, disconnected import,
local key management, staff authorization, controlled rendering, and
auditing without creating dangerous key dependencies or unnecessary
plaintext exposure.

My contribution is therefore architectural integration, traceability,
prototype realization, and evaluation.

------------------------------------------------------------------------

## Q52. Why is this a master's research project rather than just a software-development project?

**Suggested answer:**

The work goes beyond implementation.

I begin with a defined research problem and research questions,
construct a threat model, derive security requirements, justify design
decisions from established security principles, analyze residual risks,
implement a prototype, and define empirical experiments.

The software prototype is an instrument for investigating the research
questions rather than the entire research contribution.

------------------------------------------------------------------------

## Q53. How do you know your system is secure?

**Suggested answer:**

I would not claim that the system is simply "secure" in an absolute
sense.

Security claims must be tied to a threat model and evidence.

My analysis identifies specific security properties and attack
scenarios, maps them to controls, and defines experiments for observable
behavior.

Even successful experiments demonstrate behavior under the tested
conditions; they do not prove the absence of all vulnerabilities.

------------------------------------------------------------------------

# 16. Limitations Questions

## Q54. What is the biggest limitation of your architecture?

**Suggested answer:**

One major architectural limitation is that an authorized server must
eventually process plaintext to render the document.

Therefore, a complete compromise of that trusted server can undermine
confidentiality.

Another important operational limitation is delayed certificate
revocation in disconnected subsidiaries.

------------------------------------------------------------------------

## Q55. What is the biggest limitation of your current prototype?

**Suggested answer:**

The complete hardware-token and native cryptographic import path is not
yet fully implemented and experimentally validated.

In particular, recipient CEK recovery, complete AES-GCM package
decryption, and the local DEK/KEK envelope need to be completed and
tested before I can make empirical claims about those components.

------------------------------------------------------------------------

## Q56. What would you improve if you had more time?

**Suggested answer:**

I would complete hardware-token integration, protect server KEKs with an
HSM or TPM, isolate PDF rendering more strongly, implement
tamper-evident or remotely replicated audit logging, evaluate forensic
watermarking, improve offline revocation synchronization, and modernize
the legacy ZF1/PHP environment.

------------------------------------------------------------------------

# 17. Legacy-Stack Questions

## Q57. Why are you using Zend Framework 1 and PHP 7.4?

**Suggested answer:**

The research is based on an existing enterprise environment, so one
practical objective is to investigate whether stronger document security
can be integrated without immediately replacing the complete legacy
system.

I do not present ZF1 or PHP 7.4 as the preferred platform for a new
system.

Their lifecycle status is a limitation, and modernization is included in
future work.

------------------------------------------------------------------------

## Q58. Would the architecture work with Laravel, Laminas, Java, or .NET?

**Suggested answer:**

Yes, conceptually.

The security architecture is largely independent of ZF1.

Authentication, authorization, package verification, key management,
encrypted storage, rendering, and auditing can be implemented using
another application framework.

ZF1 is the prototype integration environment rather than a fundamental
requirement of the architecture.

------------------------------------------------------------------------

# 18. If a Professor Says: "Draw Your System"

Practice drawing the following architecture from memory:

``` text
                 CONCHERON CENTRAL
        +----------------------------+
        | SEC Portal                 |
        | PKI / Package Publisher    |
        | Package Signing Key        |
        +-------------+--------------+
                      |
               SEC USB / Token
                      |
                      v
               +-------------+
               | .sec file   |
               | encrypted   |
               | + signed    |
               +------+------+
                      |
             Controlled Transfer
                      |
                      v
              SUBSIDIARY SERVER
        +----------------------------+
        | Verify signature           |
        | Verify subsidiary          |
        | Verify recipient           |
        | Token unwraps CEK          |
        | AES-GCM decrypt            |
        | Generate local DEK         |
        | Re-encrypt master          |
        | KEK protects DEK           |
        +-------------+--------------+
                      |
                 Staff login
                      |
                      v
        +----------------------------+
        | Authorization              |
        | Short-lived view session   |
        | Server-side rendering      |
        | Personalized watermark     |
        | Audit                      |
        +-------------+--------------+
                      |
                WebP / PNG pages
                      |
                      v
               Canvas Viewer
```

A clear explanation of this diagram can answer many architecture
questions.

------------------------------------------------------------------------

# 19. Explain the Research in One Minute

**Suggested answer:**

My research addresses confidential document distribution across isolated
enterprise networks.

The central organization creates a digitally signed encrypted package.
The document is encrypted using AES-GCM with a random content-encryption
key, and that key is protected for the intended recipient.

After authorized import, the subsidiary generates a different local
document key and re-encrypts the master document. A server key protects
those local document keys.

Staff certificates are used for authentication and authorization rather
than directly decrypting every document.

When a user views a document, the server renders authorized pages,
applies a personalized watermark, and sends page images to a Canvas
viewer instead of normally sending the original PDF.

The contribution is not a new cryptographic algorithm. It is the
integration and evaluation of these mechanisms as a lifecycle security
architecture for isolated enterprise environments.

------------------------------------------------------------------------

# 20. Explain the Research in One Sentence

> My research designs and evaluates a lifecycle-oriented security
> architecture that combines recipient-bound encrypted distribution,
> separated key management, controlled server-side viewing, and auditing
> for confidential documents across isolated enterprise networks.

------------------------------------------------------------------------

# 21. Five Answers to Memorize First

If preparation time is limited, master these five answers.

## 21.1 What is your contribution?

Integration and evaluation of established security mechanisms into a
lifecycle architecture---not invention of a new cipher.

## 21.2 Why CEK, DEK, and KEK?

To separate distribution, document-storage, and key-protection
responsibilities and limit compromise scope.

## 21.3 Can users still take screenshots?

Yes. The architecture reduces original-file exposure and improves
accountability; it does not claim absolute copy prevention.

## 21.4 What if the server is compromised?

A fully compromised trusted server remains a residual risk because
legitimate processing requires plaintext.

## 21.5 How do you know the system is secure?

Security is evaluated against a defined threat model and explicit tests.
Passing those tests does not prove the absence of every vulnerability.

------------------------------------------------------------------------

# 22. Defense Strategy

During the defense:

1.  **Answer the exact question first.** Then explain if necessary.
2.  **Do not overclaim.** Use phrases such as "under the defined threat
    model" and "under the tested conditions."
3.  **Separate design from experimental evidence.** A control being
    designed does not mean it has already been experimentally validated.
4.  **Acknowledge limitations confidently.** A well-defined limitation
    strengthens a security thesis.
5.  **Do not claim that screenshots or copying are impossible.**
6.  **Do not claim that compiled cryptography cannot be reverse
    engineered.**
7.  **Do not claim that network isolation automatically provides
    security.**
8.  **Do not claim protection against complete root/server compromise.**
9.  **Explain key separation clearly.** CEK/DEK/KEK is likely to be a
    major discussion topic.
10. **Return to the research question.** When a discussion becomes
    highly technical, explain how the technical choice supports the
    research objective.

------------------------------------------------------------------------

# 23. Useful Academic Phrases for the Oral Defense

When you need to qualify an answer:

> "Under the threat model defined in this research..."

> "The architecture is designed to reduce that risk rather than
> eliminate it completely."

> "That is a residual risk identified in the thesis."

> "That component is currently a design/prototype element and has not
> yet been experimentally validated."

> "I would distinguish the architectural security property from the
> experimental result."

> "The individual cryptographic primitive is established; my
> contribution concerns its integration into the complete lifecycle."

> "The experiment can demonstrate behavior under the tested conditions,
> but it cannot prove the absence of every vulnerability."

> "That is an important limitation and also an area for future work."

------------------------------------------------------------------------

# 24. Final Preparation Checklist

Before the defense, make sure you can explain without notes:

-   the research problem;
-   the main research question;
-   the research contribution;
-   the central/subsidiary architecture;
-   hybrid encryption;
-   AES-GCM;
-   digital signatures;
-   CEK, DEK, and KEK;
-   hardware-token challenge-response;
-   PKI and offline revocation;
-   server-side re-encryption;
-   why the original PDF is not normally sent to the browser;
-   watermark purpose and limitations;
-   IDOR protection;
-   view-token protection;
-   native PHP extension purpose;
-   the threat model;
-   experimental methodology;
-   current prototype limitations;
-   residual risks;
-   future work.

Also practice drawing the architecture and the CEK/DEK/KEK relationship
on a whiteboard.

------------------------------------------------------------------------

# 25. Final Reminder

A strong security-research defense does not require claiming that the
proposed system is impossible to attack.

A stronger position is:

> The architecture defines specific security objectives, identifies
> realistic threats, applies established controls, states its
> assumptions and limitations, and evaluates observable security
> behavior using predefined experiments.

That is a defensible academic position and accurately represents the
scope of this research.
