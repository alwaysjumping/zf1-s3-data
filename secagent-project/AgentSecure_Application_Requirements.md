# AgentSecure — Detailed Application Requirements Specification

**Document status:** Draft baseline  
**Target:** Secure offline Windows desktop application  
**Primary platforms:** Windows XP, Windows 7, Windows 8/8.1, Windows 10  
**Architecture:** Portable native desktop application  
**Preferred implementation:** C++

---

## 1. Purpose

AgentSecure shall provide a secure desktop system for authorized agents to create, maintain, search, exchange, and synchronize sensitive information about persons of interest.

The system shall protect data against unauthorized access when application files, databases, removable media, or encrypted exchange packages are copied or stolen.

The security model shall be based on:

```text
Device Authorization
        +
Agent Authentication
        +
Agent ↔ Device Authorization
        +
Geographical Area Authorization
        +
Cryptographic Key Authorization
        =
Data Access
```

The user interface shall **not** be considered the security boundary. Access to protected information shall ultimately require possession of the appropriate cryptographic key material.

---

## 2. Operating-System Requirements

The application shall support:

```text
Windows XP       Mandatory
Windows 7        Mandatory
Windows 8/8.1    Mandatory
Windows 10       Mandatory
```

Windows 11 compatibility should be maintained where practical.

Because Windows XP is mandatory, security components shall not depend exclusively on APIs introduced in Windows Vista or later.

Modern Windows-specific security enhancements may be used when available, provided that they do not weaken or break the defined XP-compatible security architecture.

---

## 3. Portable Application

AgentSecure shall operate as a portable Windows application without requiring a traditional installer.

Example:

```text
AgentSecure\
│
├── AgentSecure.exe
├── runtime\
├── vault\
│    ├── vault.db
│    ├── config.dat
│    ├── security.dat
│    └── blobs\
│
└── exchange\
```

The application shall:

- use relative paths where practical;
- avoid mandatory Windows Registry dependencies;
- avoid requiring a Windows service;
- normally operate without Administrator privileges;
- support execution from an authorized HDD, SSD, or approved removable medium.

Copying the portable application folder to an unauthorized computer shall **not** be sufficient to access protected data.

---

## 4. Recommended Software Architecture

The application should use a layered architecture:

```text
UI
 │
Application Services
 │
Domain Services
 ├── Person Service
 ├── Area Service
 ├── Sharing Service
 ├── Synchronization Service
 └── Audit Service
 │
Security Core
 ├── Authentication
 ├── Crypto Engine
 ├── Key Manager
 ├── Identity Manager
 ├── Authorization Manager
 ├── Device Manager
 └── Secure Memory
 │
Storage
 ├── SQLite
 └── Encrypted Blob Storage
```

Security primitives shall not be implemented directly throughout UI/business code.

A dedicated cryptographic abstraction shall be used.

---

## 5. Geographical Area Model

The system shall support a hierarchical geographical structure consisting of:

```text
Medium Area
    │
    ├── Small Area
    ├── Small Area
    └── Small Area
```

Example:

```text
Area A
├── Area A-1
├── Area A-2
└── Area A-3

Area B
├── Area B-1
├── Area B-2
└── Area B-3
```

Every area shall have a globally unique immutable internal ID.

Human-readable area codes/names may be changed without changing the area's cryptographic identity.

For example:

```text
Internal ID: UUID
Area Code:   AREA-A-01
Name:        Area A-1
```

---

## 6. Area Roles

The system shall support at least:

```text
Administrator
Senior Agent
Agent
```

### Administrator

The Administrator manages:

- agents;
- devices;
- Agent ↔ Device assignments;
- medium areas;
- small areas;
- agent area assignments;
- authorization packages;
- area-key grants;
- key rotation;
- revocation;
- recovery;
- security state.

### Senior Agent

A Senior Agent may be authorized for one or more medium areas.

Example:

```text
Senior A
├── Area A
├── Area B
└── Area C
```

Medium-area authorization may automatically inherit access to applicable child small areas.

### Agent

An Agent may be assigned specific small areas:

```text
Agent 15
├── A-1
└── A-2
```

---

## 7. Hierarchical Authorization

The system shall distinguish between:

```text
HIERARCHICAL assignment

SA-001 → AREA-A

meaning:
all applicable children of Area A
```

and:

```text
DIRECT assignment

AG-015 → AREA-A-01
AG-015 → AREA-A-02
```

This distinction shall be preserved in the database.

When a new small area is created under a medium area, hierarchical authorization may automatically apply to it according to organizational policy.

---

## 8. Agent Identity

Every agent shall have a unique identity containing at least:

```text
Agent ID
Display Name
Role
Status
Public Key
Certificate information
Created Date
Identity Version
```

Agent status shall support at least:

```text
ACTIVE
SUSPENDED
REVOKED
```

Agent identity and area authorization shall remain separate concepts.

---

## 9. PKCS#12 Identity

Each agent shall have one primary PKCS#12 identity package:

```text
SA-001.p12
```

It may contain:

```text
Agent private key
Agent X.509 certificate
Administrator/CA certificate chain
```

The PKCS#12 identity establishes:

> Who is this agent?

It shall **not normally contain changing area assignments**.

This avoids replacing the agent's private identity every time geographical responsibility changes.

---

## 10. Authorization Object

Area authorization shall be maintained separately from the `.p12`.

Example:

```text
Agent: SA-001

Role:
Senior Agent

Authorized Areas:
AREA-A
AREA-B
AREA-C

Authorization Version:
28

Status:
ACTIVE

Valid From:
...

Valid Until:
...

Administrator Signature:
...
```

The authorization shall be digitally signed by the Administrator's trusted signing authority.

Modification of authorization data shall invalidate its signature.

---

## 11. Authorization Versioning

Authorization shall have a monotonically increasing version/generation.

Example:

```text
Version 27
A, B

        ↓

Version 28
A, B, C
```

An application that has accepted version 28 shall reject an attempt to replace it with version 27.

Protected local state shall maintain the highest trusted authorization version.

This mechanism shall help detect authorization rollback.

---

## 12. Small-Area Cryptographic Keys

Each **small area** shall have an independent symmetric Area Key.

Example:

```text
A-1 → AK-A1
A-2 → AK-A2
A-3 → AK-A3

B-1 → AK-B1
B-2 → AK-B2
```

Small areas shall therefore serve as primary cryptographic authorization domains.

Area keys shall be generated using a cryptographically secure random-number generator.

Raw Area Keys shall never be displayed to ordinary users.

---

## 13. Area-Key Versioning

Every Area Key shall have a version:

```text
AREA-A-01

AK-A01-v1
AK-A01-v2
AK-A01-v3
```

Only one version should normally be considered current for new operations.

Key metadata shall include:

```text
Area ID
Key Version
Algorithm Version
Created Date
Status
```

Possible statuses:

```text
ACTIVE
RETIRED
COMPROMISED
```

---

## 14. Area-Key Grants

Area keys shall not be distributed to agents as plaintext.

Instead, the Administrator shall generate **Area-Key Grants** protected for a specific agent.

Conceptually:

```text
Area Key
    ↓
Protect for Agent Public Key
    ↓
Area-Key Grant
```

Example:

```text
Agent:
SA-001

Area:
AREA-A-01

Area Key Version:
7

Encrypted/Wrapped Area Key:
...

Administrator Signature:
...
```

Only the intended agent possessing the corresponding private identity key shall be able to recover the protected key material.

---

## 15. Person Encryption

Every person shall have an independent random Data Encryption Key:

```text
Person P1001
       │
       ▼
DEK-P1001
       │
       ▼
Encrypted Person Data
```

The Person DEK shall then be protected through the applicable area-key/key-grant architecture.

The system shall **not** encrypt every person's large data payload directly using an Area Key.

This envelope-encryption architecture shall allow key relationships to change without re-encrypting all large data.

---

## 16. Optional Section-Level Encryption

The architecture should permit future use of separate keys for:

```text
Profile
Photos
Career History
Family Information
Attachments
Confidential Notes
```

This would permit fine-grained sharing where required.

For the initial implementation, a single Person DEK may be used where policy permits.

---

## 17. Person Record Requirements

A person record shall support at least:

```text
Basic information
Name
Aliases
Date of birth
Nationality
Identification information
Addresses
Occupation
Organizations
Career history
Family information
Notes
Classification/status
Area assignment
Photos
Attachments
```

Every person shall have a globally unique immutable UUID.

SQLite auto-increment IDs may additionally be used internally for performance but shall not serve as global synchronization identities.

---

## 18. Photo Requirements

Each person shall support approximately:

```text
1–3 photos
```

Photos shall be encrypted.

Large photos should preferably be stored as encrypted external blobs rather than large SQLite BLOBs.

Example:

```text
vault\
└── blobs\
     ├── 00\
     ├── 01\
     ├── 02\
     └── ...
```

Blob filenames shall not expose personal information.

Use identifiers such as:

```text
A7F194C85D91F83A.dat
```

rather than:

```text
John_Smith.jpg
```

The application should decrypt photos in memory for display and avoid plaintext temporary files.

---

## 19. Database

SQLite is the recommended local database.

The initial design should comfortably support at least:

```text
100,000+ person records
```

with hundreds of thousands of associated photos where storage capacity permits.

The application shall use:

- indexes;
- pagination;
- lazy loading;
- bounded memory usage.

It shall not load the entire database or all photos into memory.

---

## 20. Proposed Core Tables

The logical data model shall include at least:

```text
agents
devices
agent_devices

areas
agent_area_assignments

identity_certificates

authorizations
authorization_versions

area_keys
area_key_versions
area_key_grants

persons
person_keys

person_names
person_addresses
career_history
family_members

photos
attachments

search_index

change_log
sync_state

share_packages
received_packages

revocations
security_state

audit_log

crypto_metadata
schema_metadata
```

Exact normalization may be refined during database design.

---

## 21. Device Licensing

Every authorized computer shall possess an independent device identity and signed Device License.

Example:

```text
Device ID:
DEV-001

License ID:
LIC-000001

Status:
ACTIVE

Issued:
...

Expires:
...

Administrator Signature:
...
```

The application shall verify the Device License before allowing agent authentication.

An arbitrary valid public certificate shall not be accepted as a Device License.

---

## 22. Device Secret

Each device shall possess an independently generated cryptographically random Device Secret:

```text
DEV-001 → DS-001
DEV-002 → DS-002
```

The Device Secret shall be protected locally.

Device hardware identifiers may contribute to device recognition, but values such as:

```text
MAC address
CPU ID
disk serial
computer name
BIOS information
```

shall **not themselves be treated as encryption keys**.

Where appropriate, Windows DPAPI may participate in protecting device-local secrets.

---

## 23. Cryptographic Device Binding

Device licensing shall not merely be:

```cpp
if (licenseValid)
    allowApplication();
```

Device-specific secret material shall participate in protection/unlocking of local sensitive key material.

Therefore copying:

```text
AgentSecure.exe
vault.db
blobs\
device.license
```

to another computer shall not automatically make the vault usable.

---

## 24. Many-to-Many Agent ↔ Device Model

The application shall support:

```text
One Agent → Multiple Devices
One Device → Multiple Agents
```

Example:

```text
SA-001 → DEV-001
SA-001 → DEV-002
SA-001 → DEV-003

AG-002 → DEV-001
AG-002 → DEV-004
```

The database shall therefore use an association such as:

```text
agent_devices

assignment_id
agent_id
device_id
status
assignment_version
assigned_at
expires_at
revoked_at
```

---

## 25. Agent ↔ Device Authorization

Possession of:

```text
Valid Agent Identity
+
Valid Device
```

shall not automatically permit login.

The Administrator must explicitly authorize the relationship:

```text
Agent SA-001
        ↕
Device DEV-001
```

This assignment shall be protected/authenticated by Administrator-issued trusted state.

Possible status:

```text
ACTIVE
SUSPENDED
REVOKED
```

---

## 26. Revocation Independence

The system shall distinguish:

```text
DEVICE REVOCATION
DEV-001 revoked
→ nobody may use that device.


AGENT REVOCATION
SA-001 revoked
→ agent may not use any device.


AGENT ↔ DEVICE REVOCATION
SA-001 ↔ DEV-002 revoked
→ agent can still use other devices.


AREA REVOCATION
SA-001 loses AREA-C
→ agent may still use authorized devices,
  but loses future/current Area C authorization.
```

These events shall remain independent.

---

## 27. Shared Computer Security

A single authorized computer may contain multiple enrolled agents.

Example:

```text
DEV-001
│
├── SA-001
└── AG-002
```

Each agent shall have separate:

```text
Identity
Application password credential
Authorization
Area-key grants
Session state
```

One agent shall not inherit another agent's decrypted key material merely because they use the same device.

---

## 28. Concurrent Sessions

Initial implementation should support:

```text
Multiple enrolled agents:        YES
Multiple simultaneous sessions: NO
```

Only one active AgentSecure security session should exist per application instance/device initially.

When switching users:

```text
Logout current agent
        ↓
Clear sensitive session state
        ↓
Authenticate new agent
```

---

## 29. Application Password Authentication

Each Agent ↔ Device enrollment shall have a personal **Application Password**.

Example:

```text
DEV-001
│
├── SA-001
│      └── Application Password A
│
└── AG-002
       └── Application Password B
```

The password shall never be stored in plaintext.

A reviewed salted password KDF shall be used.

Stored metadata shall include:

```text
Agent ID
Device ID
Random Salt
KDF Algorithm ID
KDF Version
KDF Parameters
Password Verifier / Protected Unlock Material
Credential Version
```

---

## 30. Application Password Management

Authenticated users shall be able to change their Application Password.

Required workflow:

```text
Current Password
       ↓
New Password
       ↓
Confirm Password
       ↓
Policy validation
       ↓
Generate new salt
       ↓
Derive new protection key
       ↓
Re-protect local unlock material
       ↓
Audit event
```

Changing the Application Password shall **not require re-encryption of all person data/photos**.

Forgotten passwords shall use a controlled recovery procedure rather than recoverable plaintext password storage.

---

## 31. Failed Authentication Protection

Repeated incorrect Application Password attempts shall trigger progressive throttling.

The design should avoid an easily exploitable permanent offline lockout that could create a denial-of-service attack.

Failed attempts shall generate appropriate audit events.

---

## 32. PKCS#12 Authentication

After successful Application Password authentication, the application shall require the user to select/import their `.p12`.

Workflow:

```text
Application Password
        ✓
        ↓
Select .p12
        ↓
Enter .p12 Password
        ↓
Open PKCS#12
        ↓
Verify certificate
        ↓
Verify private key
        ↓
Continue
```

The PKCS#12 password and Application Password are separate credentials.

---

## 33. Certificate Verification

Successfully opening the `.p12` shall **not** be sufficient.

The application shall verify at least:

```text
PKCS#12 integrity
Certificate structure
Expected certificate usage/policy
Trusted Administrator/CA chain
Certificate identity
Agent ID
Certificate validity policy
Known revocation/security state
Corresponding private key
Agent status
Agent ↔ Device authorization
```

Certificates issued by unrelated authorities or self-signed attacker certificates shall not be accepted.

---

## 34. Private-Key Possession Verification

The application should verify that the user possesses the private key corresponding to the certificate.

Conceptually:

```text
Generate fresh random challenge
          ↓
Sign challenge with private key
          ↓
Verify signature using certificate public key
          ↓
Authentication succeeds
```

This prevents treating certificate metadata alone as proof of identity.

---

## 35. PKCS#12 Handling

Under the currently selected authentication model, the user shall provide the `.p12` during each fresh login.

The original `.p12` should not automatically be permanently copied into the portable application directory.

The `.p12` password shall remain in memory only as long as required for authentication and key processing.

---

## 36. Complete Login Sequence

The required login sequence shall be:

```text
START
  │
  ▼
Verify application/security state
  │
  ▼
Verify Device License
  │
  ▼
Recover/verify Device Secret
  │
  ▼
Select Agent
  │
  ▼
Enter Application Password
  │
  ▼
Verify password
  │
  ▼
Unlock agent-local security profile
  │
  ▼
Select .p12
  │
  ▼
Enter .p12 Password
  │
  ▼
Verify PKCS#12
  │
  ▼
Verify trusted certificate chain
  │
  ▼
Verify Agent ID
  │
  ▼
Verify private-key possession
  │
  ▼
Verify Agent status
  │
  ▼
Verify Agent ↔ Device assignment
  │
  ▼
Verify signed authorization
  │
  ▼
Load authorized small-area key grants
  │
  ▼
Create isolated agent session
  │
  ▼
ACCESS
```

---

## 37. Effective Access Rule

Access to a protected record shall effectively require:

```text
Valid Device
       ∩
Correct Application Password
       ∩
Valid PKCS#12 Identity
       ∩
Private-Key Possession
       ∩
Valid Agent
       ∩
Valid Agent ↔ Device Assignment
       ∩
Valid Area Authorization
       ∩
Required Current Area-Key Grant
       ∩
Valid Ciphertext Authentication
```

Failure of any required condition shall prevent access.

---

## 38. Certificate Expiration

Identity-certificate lifetime and operational authorization lifetime shall remain separate.

Example:

```text
Identity Certificate
SA-001.p12

Validity:
2026–2029
```

while:

```text
Authorization

Version:
38

Areas:
A, B, C

Validity:
shorter operational period
```

Changing area assignments shall therefore not normally require issuing a new `.p12`.

---

## 39. Offline Expiration Limitation

Because the system may operate completely offline, especially on Windows XP, the local Windows clock cannot be considered a fully trusted time source against an attacker controlling the computer.

The application shall use defense-in-depth mechanisms such as:

```text
Certificate validity
Authorization validity
Authorization generations
Highest accepted security generation
Last trusted time/state
Area-key versions
Administrator-signed security state
```

However, requirements shall acknowledge that **strong trusted-time enforcement cannot be guaranteed by software alone on a fully attacker-controlled offline legacy computer**.

Where stronger guarantees are required, an external trusted authority or hardware mechanism will be necessary.

---

## 40. Signed Security State

The Administrator should periodically issue signed security state containing information such as:

```text
Security State Version
Authorization Generation
Issued Date
Validity Information

Revoked Agents
Revoked Devices
Revoked Assignments

Key/security generation

Administrator Signature
```

Clients shall reject invalid signatures and older state according to rollback-protection policy.

---

## 41. Key Rotation

Area keys shall be rotated when appropriate, including:

```text
Area-key compromise
Agent removal from sensitive area
Agent/private-key compromise
Relevant device/security compromise
Administrator-directed security event
```

Routine authorization renewal shall **not automatically require area-key rotation**.

---

## 42. Revocation Limitation

If an agent previously possessed an Area Key or decrypted plaintext, later revocation cannot cryptographically make the agent forget that information.

Therefore key rotation primarily protects:

```text
future information
future updates
future key grants
```

This limitation shall be explicitly included in the threat model.

---

## 43. Moving a Person Between Areas

A person may move from one small area to another.

Example:

```text
AREA-A-02
     ↓
AREA-B-03
```

The system shall support changing protection of the Person DEK from the old area authorization domain to the new area domain without unnecessarily re-encrypting large payloads.

The area transition itself shall be authenticated and audited.

---

## 44. Encrypted Search

Ordinary plaintext SQL search over encrypted fields shall not be used.

For approved searchable fields, the system shall use keyed search indexes.

Conceptually:

```text
"John Smith"
     ↓
normalize
     ↓
"john smith"
     ↓
HMAC(SearchKey, value)
     ↓
opaque search token
```

The database stores the token, not the plaintext search value.

---

## 45. Search-Key Isolation

Search capability shall respect area authorization.

An Agent authorized for:

```text
A-1
A-2
```

shall not gain search capability for:

```text
A-3
B-*
C-*
```

merely because ciphertext/search indexes exist on the same device.

Search keys should therefore be scoped appropriately to cryptographic/area security domains.

---

## 46. Search Types

The system may support:

```text
Exact search
Token search
Controlled prefix search
```

For example:

```text
John Smith

Tokens:
john
smith
```

Prefix indexes may optionally support:

```text
smi
smit
smith
```

with a sensible minimum prefix length.

---

## 47. Searchable Fields

Candidate searchable fields include:

```text
Name
Alias
Date of Birth
Identification/Passport Number
Phone
Nationality
Occupation
Organization
```

Not every field should be searchable.

Highly sensitive free-form notes may remain encryption-only to reduce information leakage from searchable indexes.

---

## 48. Agent-to-Agent Data Sharing

Agents shall be able to exchange protected data using encrypted packages.

A custom format may be used:

```text
.agsp
```

Example:

```text
Area_C_Transfer.agsp
```

A package shall contain authenticated metadata such as:

```text
Format Version
Package ID
Sender Agent ID
Recipient Agent ID
Area IDs
Creation information
Encrypted key information
Encrypted records
Encrypted photos
Encrypted attachments
Digital signature
```

---

## 49. Recipient-Specific Sharing

Checking only a recipient field in the UI shall not be sufficient.

Where a package is intended for a particular recipient, necessary cryptographic key material shall be protected so that the intended recipient's private key is required.

Thus:

```text
Package for SA-001
```

should not become readable by `AG-002` merely by modifying application code or metadata.

---

## 50. Sharing Signature Verification

Before importing a sharing package, the application shall:

```text
Validate package structure
Verify sender identity/signature
Verify recipient
Verify authorization
Verify area authorization
Verify key versions
Verify ciphertext integrity
Check Package ID
Import
Audit
```

Modified packages shall be rejected.

---

## 51. Replay Protection

Every package shall have a globally unique Package ID.

The application shall maintain processed-package state.

Repeated imports shall be detected and rejected or handled explicitly according to package type.

---

## 52. Offline Operation

Core functionality shall not require Internet access.

The application shall support:

```text
Offline authentication
Offline record management
Offline search
Offline encrypted package export/import
Offline synchronization
Offline Administrator updates
```

Internet connectivity shall not be assumed.

---

## 53. Multi-Device Synchronization

Because one agent may use several computers, each authorized device shall be considered an independent encrypted replica.

Example:

```text
SA-001

DEV-001
DEV-002
DEV-003
```

Each may have a different synchronization state.

---

## 54. Record Versioning

Every synchronized object shall have globally unique identifiers and version/change information.

Conceptually:

```text
Person ID
Version ID
Parent Version ID
Change ID
Agent ID
Device ID
Operation
Area ID
Authenticated metadata
```

Important synchronization metadata shall be cryptographically authenticated.

---

## 55. Change Journal

The application shall maintain a change journal containing information such as:

```text
change_id
object_type
object_id
version_id
parent_version_id
area_id
agent_id
device_id
operation
created_at
```

Possible operations:

```text
CREATE
UPDATE
DELETE
MOVE_AREA
ADD_PHOTO
DELETE_PHOTO
ADD_FAMILY_MEMBER
UPDATE_CAREER
```

This shall permit incremental synchronization instead of scanning/exporting the complete database.

---

## 56. Synchronization Packages

Offline device synchronization may use:

```text
.syncpkg
```

containing:

```text
Package ID
Source Agent
Source Device
Sequence information
Changed records
Changed photos
Tombstones
Relevant authenticated metadata
Digital signature/authentication
```

Sensitive package content shall remain encrypted.

---

## 57. Synchronization Conflicts

The application shall detect divergent changes.

Example:

```text
             V17
            /   \
           /     \
       V18A       V18B
      DEV-001    DEV-002
```

The application shall not simply choose the record with the latest local timestamp.

Offline computer clocks are not sufficiently trustworthy for conflict resolution.

Conflicting sensitive changes shall be presented for authorized resolution unless a proven deterministic safe merge rule exists.

---

## 58. Conflict Resolution

A resolved conflict shall produce a new version.

Conceptually:

```text
       V18A     V18B
          \     /
           \   /
            V19
```

The resolution shall be auditable.

Automatic merging may later be supported for independent non-conflicting operations.

---

## 59. Deletion and Tombstones

Synchronized deletion shall use tombstones.

Example:

```text
Person:
P1001

State:
DELETED

Version:
V25

Parent:
V24
```

This prevents an older device from accidentally resurrecting a deleted record.

Physical deletion shall be governed separately by retention and cryptographic-erasure policy.

---

## 60. Device Synchronization Sequence

Each device should maintain a monotonically increasing local change sequence.

Example:

```text
DEV-001

1
2
3
...
938
939
```

Synchronization peers may record the highest processed sequence for each known source device.

Sequence numbers shall supplement—not replace—cryptographic signatures, globally unique change IDs and replay protection.

---

## 61. Audit Logging

Security-sensitive actions shall generate audit records.

At minimum:

```text
LOGIN
LOGIN_FAILED
LOGOUT

PASSWORD_CHANGED

CERTIFICATE_ACCEPTED
CERTIFICATE_REJECTED

PERSON_CREATED
PERSON_VIEWED
PERSON_UPDATED
PERSON_DELETED

PHOTO_VIEWED
ATTACHMENT_VIEWED

DATA_EXPORTED
DATA_SHARED
DATA_RECEIVED

SYNC_EXPORT
SYNC_IMPORT
SYNC_DUPLICATE
SYNC_CONFLICT
SYNC_CONFLICT_RESOLVED

PACKAGE_REJECTED
SIGNATURE_INVALID

AGENT_CREATED
AGENT_SUSPENDED
AGENT_REVOKED

DEVICE_REGISTERED
DEVICE_SUSPENDED
DEVICE_REVOKED

AGENT_DEVICE_ASSIGNED
AGENT_DEVICE_REVOKED

AREA_ASSIGNED
AREA_REMOVED

KEY_ROTATED

RECOVERY_PERFORMED
```

---

## 62. Tamper-Evident Audit Log

Audit entries should be hash-chained:

```text
Entry 1
   ↓ hash
Entry 2 + previous hash
   ↓
Entry 3 + previous hash
```

Periodic signed checkpoints should be considered.

This provides tamper evidence but shall not be represented as making deletion impossible on a fully compromised standalone computer.

---

## 63. Session Locking

The application shall support automatic lock after configurable inactivity.

Example policy:

```text
5 minutes
```

On lock:

```text
Hide sensitive UI
Clear decrypted records
Clear cached Area Keys where appropriate
Clear Person DEKs
Clear temporary sensitive buffers
Require re-authentication
```

Integration with Windows workstation/session locking should be implemented where reliably supported.

---

## 64. Sensitive Memory Handling

Sensitive material shall remain in plaintext memory only when necessary.

Examples:

```text
Application passwords
PKCS#12 passwords
Private-key plaintext
Area Keys
Person DEKs
Decrypted records
Decrypted photos
```

Sensitive buffers shall be cleared as reliably as practical after use.

Plaintext temporary files shall be avoided.

---

## 65. Shared-Device Logout

When an agent logs out on a shared device, AgentSecure shall clear that agent's active security context before another agent authenticates.

The following shall not carry across sessions:

```text
Area Keys
Person DEKs
Search Keys
Decrypted records
Decrypted photos
Private-key working material
Session authorization state
```

---

## 66. Watermarking

Sensitive record/photo views should support visible watermarking containing information such as:

```text
Agent ID
Device ID
Timestamp
Classification
```

Watermarking is a deterrence/accountability mechanism and shall not be represented as preventing an authorized user from photographing or otherwise copying displayed plaintext.

---

## 67. Backup

Backups shall remain encrypted.

A backup should contain the necessary protected forms of:

```text
SQLite database
Encrypted blobs
Metadata
Cryptographic metadata
Protected recovery information
Integrity/authentication information
```

Plaintext backups shall not be generated by normal application workflows.

---

## 68. Recovery

The system shall provide a controlled recovery architecture for cases such as:

```text
Lost device
Destroyed device
Forgotten Application Password
Damaged local key state
Lost operational credentials
```

Recovery authority shall be separated from normal agent credentials where practical.

Highly sensitive recovery keys should preferably be kept offline or hardware protected.

Every recovery event shall be audited.

---

## 69. Device Migration

Moving an agent to a replacement computer shall be explicit.

Conceptually:

```text
Register new device
       ↓
Administrator authorizes device
       ↓
Create Agent ↔ Device assignment
       ↓
Provision identity/authentication
       ↓
Install current authorization
       ↓
Provision appropriate key grants
       ↓
Import/synchronize encrypted data
       ↓
Optionally revoke old device
```

---

## 70. Cryptographic Erasure

Physical secure deletion is difficult to guarantee on SQLite, flash storage and SSDs.

Where possible, deletion of sensitive information should rely on **cryptographic erasure**:

```text
Destroy all recoverable DEKs/key grants
        ↓
Remaining ciphertext becomes unusable
```

Backups and recovery copies must be included in the erasure/retention model.

---

## 71. Cryptographic Algorithms

Only established, publicly reviewed cryptographic algorithms and implementations shall be used.

The design should provide:

```text
Authenticated encryption (AEAD)
Secure hashing
HMAC
Password KDF
Public-key encryption/key agreement
Digital signatures
Cryptographically secure RNG
```

Algorithm choice shall be versioned.

No proprietary/custom encryption algorithm shall be invented.

No silent downgrade to weak algorithms shall occur merely to maintain Windows XP compatibility.

The exact crypto library and versions must be validated against XP/7/8/10 before implementation is finalized.

---

## 72. Cryptographic Metadata

Encrypted objects shall contain or reference sufficient metadata to permit future migration.

For example:

```text
Format Version
Crypto Version
Algorithm ID
Key Version
Nonce/IV
Authentication data/tag
Schema Version
```

This prevents the application from assuming that one algorithm or format will exist forever.

---

## 73. Administrator Update Packages

Administrative changes should be distributed using signed packages.

They may contain:

```text
New authorization
Agent ↔ Device assignment changes
Area assignments
Area-key grants
Revocations
Security state
Certificate information
```

The client shall verify Administrator signatures before accepting changes.

---

## 74. Application Updates

Offline application updates shall be signed.

Example:

```text
AgentSecure_Update_1.5.pkg
```

Workflow:

```text
Read update
   ↓
Verify Administrator/software signing signature
   ↓
Verify version
   ↓
Backup
   ↓
Apply update
   ↓
Perform schema migration
   ↓
Verify
```

Unsigned or improperly signed updates shall be rejected.

---

## 75. Database Migration

The database shall contain:

```text
schema_version
```

and support controlled migrations.

Cryptographic formats and database schema versions shall be treated separately.

For example:

```text
Application Version:  2.1
Schema Version:       14
Crypto Version:       4
Authorization Format: 3
```

---

## 76. Administrator Console

The Administrator interface should provide at least:

```text
Agents
Devices
Agent ↔ Device Assignments
Areas
Area Assignments
Authorizations
Key Management
Revocations
Security State
Recovery
Audit
```

An Agent screen might display:

```text
SA-001
Senior Agent A

Status:
ACTIVE

Identity Certificate:
Valid

Areas:
A
B
C

Devices:
DEV-001 ACTIVE
DEV-002 ACTIVE
DEV-003 ACTIVE

Authorization Version:
38
```

---

## 77. Device Administration

The Administrator shall be able to:

```text
Register Device
Approve Device
Issue Device License
Renew Device License
Suspend Device
Revoke Device
Replace Device
View Assigned Agents
```

Revoking a device shall not automatically revoke the agents who used it.

---

## 78. Agent Administration

The Administrator shall be able to:

```text
Create Agent
Issue/approve identity
Assign Role
Assign Areas
Assign Devices
Renew Authorization
Suspend Agent
Revoke Agent
Rotate affected keys
Review audit information
```

---

## 79. Area Administration

The Administrator shall be able to:

```text
Create Medium Area
Create Small Area
Rename display information
Assign agents
Remove agents
View effective authorization
Create Area Key
Rotate Area Key
View key version/status
```

Area IDs shall remain immutable even if human-readable names change.

---

## 80. Data-Transfer Scenario

The original target scenario shall be explicitly supported.

Initial state:

```text
Senior A
    Area A
    Area B

Senior B
    Area C
    Area D
```

Administrator changes Senior A:

```text
Senior A
    Area A
    Area B
    Area C
```

The Administrator issues an updated signed authorization/key package.

Senior B exports Area C data.

Senior A imports it.

The import succeeds only after validating:

```text
Sender
Recipient
Package signature
Authorization
Area
Key information
Ciphertext integrity
Replay state
```

Afterward Senior A can manage:

```text
A
B
C
```

without requiring a new identity `.p12` solely because Area C was added.

---

## 81. Threat Model

The design shall explicitly address at least:

| Threat | Required protection |
|---|---|
| Stolen SQLite database | Encrypted |
| Stolen photo/blob directory | Encrypted |
| Copied application EXE | No data access |
| Copied portable folder | Device binding prevents normal access |
| Wrong computer | Device authorization/key protection |
| Wrong agent | Agent authentication |
| Correct agent on unauthorized device | Agent↔Device authorization |
| Wrong area | Area authorization + missing Area Key |
| Intercepted share package | Cryptographic confidentiality |
| Modified package | Authentication/signature failure |
| Replayed package | Package/change replay detection |
| Guessed password | KDF + throttling |
| Stolen device | Revocation + key/device protections |
| Revoked area | Authorization change + key rotation where required |
| Modified database metadata | Authenticated cryptographic metadata |
| Old authorization rollback | Generation/version checks |
| Authorized user photographing screen | Cannot fully prevent; watermark/audit/policy |
| Malware on unlocked authorized PC | High residual risk |
| Fully compromised XP system | High residual risk |

---

## 82. Important Security Limitations

The requirements must explicitly recognize that cryptography cannot solve every endpoint-security problem.

**Already viewed information:** An agent cannot be made to cryptographically forget plaintext already legitimately viewed or copied.

**Offline revocation:** A completely offline computer cannot learn about a newly issued revocation until trusted updated state reaches it.

**Trusted time:** A fully attacker-controlled offline legacy PC cannot provide strong trusted-time guarantees using ordinary software alone.

**Endpoint compromise:** Malware with sufficient control of an authorized, unlocked machine may capture plaintext or keys while AgentSecure is legitimately using them.

**Screens:** Software cannot reliably prevent an authorized person from photographing displayed information.

These limitations should influence operational security procedures.

---

## 83. Recommended Development Sequence

```text
Phase 1
C++ application skeleton
XP/7/8/10 compatibility framework

Phase 2
SQLite storage layer
Schema/version management

Phase 3
Cryptographic abstraction layer
Secure RNG
AEAD
Hash/HMAC
Password KDF
Public-key/signature interfaces

Phase 4
Device identity
Device Secret
Device License
Device binding

Phase 5
Agent identity
PKCS#12 parsing
Certificate validation
Private-key possession

Phase 6
Application Password
Agent ↔ Device enrollment
Authentication/session management

Phase 7
Area hierarchy
Medium/small areas
Area authorization

Phase 8
Small-area keys
Key versions
Area-key grants

Phase 9
Person DEKs
Encrypted person records

Phase 10
Encrypted search

Phase 11
Encrypted photos/blobs

Phase 12
Agent-to-agent sharing
.agsp packages

Phase 13
Multi-device synchronization
change journal
.syncpkg

Phase 14
Conflict handling
tombstones
area moves

Phase 15
Audit system
tamper-evident logging

Phase 16
Backup/recovery
device migration
revocation

Phase 17
Signed application updates

Phase 18
Administrator Console

Phase 19
Full UI

Phase 20
Security testing
compatibility testing
recovery testing
penetration/security review
```

---

## 84. Core Security Architecture

```text
                         ADMINISTRATOR
                              │
          ┌───────────────────┼────────────────────┐
          │                   │                    │
          ▼                   ▼                    ▼
       DEVICES              AGENTS               AREAS
          │                   │                    │
          ▼                   ▼                    ▼
   Device License        PKCS#12 Identity     Small-Area Keys
   Device Secret               │                    │
          │                    ▼                    │
          │             Area Authorization          │
          │                    │                    │
          │              Agent↔Device               │
          │               Assignment                │
          │                    │                    │
          └──────────────┬─────┴────────────────────┘
                         ▼
                 Authentication Session
                         │
                         ▼
                  Area-Key Grants
                         │
                         ▼
                     Person DEKs
                         │
              ┌──────────┼───────────┐
              ▼          ▼           ▼
           Profile     Photos     Attachments
              │          │           │
              └──────────┼───────────┘
                         ▼
                 Encrypted Storage
                         │
             ┌───────────┴───────────┐
             ▼                       ▼
        Sharing Packages       Sync Packages
            .agsp                 .syncpkg
```

The login/security path is:

```text
Authorized Device
       ↓
Application Password
       ↓
PKCS#12 + PKCS#12 Password
       ↓
Certificate / Private-Key Verification
       ↓
Agent ↔ Device Authorization
       ↓
Current Agent Authorization
       ↓
Small-Area Key Grants
       ↓
Person DEKs
       ↓
Protected Data
```

---

## Baseline Architectural Principle

**Identity, device authorization, Agent ↔ Device authorization, geographical authorization, key grants, and actual data encryption shall remain separate security layers.**

This separation allows the system to support multiple agents, multiple authorized computers, changing geographical responsibilities, device loss, revocation, offline synchronization, and future cryptographic migration without redesigning the entire security architecture.


---

## 85. Excel Export

This section adds Excel export to the existing specification. All preceding requirements remain in force. Export is an additional disclosure permission and shall not expand the access granted by the security architecture. It supplements the effective access rule (Section 37), audit requirements (Sections 61–62), session and memory protections (Sections 63–65), watermarking (Section 66), and security limitations (Section 82). Native sharing and synchronization remain governed by their existing requirements.

### 85.1. Purpose and scope (EX-01)

AgentSecure shall allow explicitly authorized agents to export approved person-record data to an Excel workbook for authorized offline reporting and analysis. Export shall be a separately controlled disclosure operation, distinct from viewing records, encrypted agent-to-agent exchange, backup, and synchronization.

The initial feature shall produce `.xlsx` workbooks without requiring Microsoft Excel, Microsoft Office automation, internet access, a cloud service, or Administrator privileges. The export implementation shall preserve the application's portable operation and mandatory Windows XP, Windows 7, Windows 8/8.1, and Windows 10 compatibility requirements. Supported spreadsheet readers and encryption profiles shall be documented and tested separately from application operating-system compatibility.

### 85.2. Explicit export authorization (EX-02)

1. Export shall be denied by default. An Administrator-issued, authenticated policy shall explicitly grant export capability to an agent or role and constrain its scope. Being an Administrator, Senior Agent, or record viewer shall not alone imply unrestricted export rights.
2. Authorization shall require a valid device license, active agent identity, active Agent ↔ Device assignment, authenticated session, applicable area authorization, necessary cryptographic grants, and explicit export permission.
3. Policy shall specify permitted areas, record categories, fields, classifications, record/volume limits, output protection, destinations, and whether photos may be included. Export policy shall use the existing signature, versioning, expiration, and rollback-protection mechanisms.
4. The export service shall enforce authorization independently of UI controls. Direct service calls, saved export presets, manipulated selections, and stale search results shall receive the same checks.
5. Before execution, the application shall show the authorized scope, selected fields, expected count when available, classification, protection mode, photo policy, and destination. The agent shall explicitly confirm the export and provide a purpose or reference when policy requires it.
6. Policy may require fresh authentication or an Administrator-issued approval for sensitive exports. Approval shall be bound to scope, protection mode, destination class, validity, and policy version; it shall not authorize broader access.

### 85.3. Area and record enforcement (EX-03)

1. Eligible records shall be the intersection of the requested selection, effective direct/inherited area assignments, current record and field permissions, export policy, and usable authorized decryption grants. Possession of a cached key alone shall not authorize export.
2. Hierarchical assignments shall resolve to applicable small areas using the existing inheritance rules. Selecting a medium area shall never bypass restrictions on its children.
3. Authorization filtering shall occur before counting, previewing, decrypting, or serializing export data. Neither counts, error details, related-record links, nor workbook metadata shall reveal inaccessible records or areas.
4. Related family, career, address, photo, and attachment information shall be checked independently where restrictions apply. A permitted parent record shall not authorize otherwise restricted related information.
5. Policy and security state shall be checked at job creation, during bounded processing intervals, and immediately before publication. Known revocation, expired permission, lost area rights, session lock, logout, or user switching shall stop the job and prevent publication. A retry shall require fresh authorization.
6. The application shall apply all locally available trusted revocation/security updates. It shall explicitly document that an offline installation cannot discover revocations it has not received or guarantee trusted time on an attacker-controlled legacy computer.

### 85.4. Record and field selection (EX-04)

The export dialog shall support:

- Explicitly selected records, with selections retained across visible result pages.
- All authorized records matching the current filters, clearly distinguished from the current page.
- One or more authorized areas, optionally combined with filters.
- All eligible records only when policy expressly permits that scope.

The agent shall be able to select and order exportable fields. Sensitive fields, confidential notes, identification information, and photos shall be excluded by default unless an approved preset requires them. The dialog shall explain mandatory policy fields such as classification and export identity.

Saved presets shall contain field/layout choices and safe filter configuration only; they shall contain no passwords, decrypted data, keys, or reusable permission grants. Every use shall revalidate permissions. Unauthorized fields shall not be hidden inside workbook structures; they shall be omitted entirely.

The preview shall use the same authorization and projection logic as generation. Filters and selected record identities shall be fixed for the job. A consistent read snapshot or equivalent versioned export view shall prevent concurrent edits from producing an internally inconsistent workbook. The snapshot time and consistency method shall be recorded. Before publication, current authorization shall still be checked independently of the data snapshot.

If an explicitly selected item becomes ineligible, the job shall stop with a non-disclosing explanation and require a revised selection. It shall not silently broaden scope or report an incomplete explicit selection as complete. Policy-filtered searches shall report only eligible counts.

### 85.5. XLSX content and data fidelity (EX-05)

1. Output shall be a valid Office Open XML `.xlsx` workbook. CSV renamed as XLSX, legacy `.xls`, and macro-enabled workbooks shall not satisfy this requirement.
2. The standard layout shall include an `Export Information` sheet and a `Persons` sheet. Repeating information may appear in separate approved sheets, linked by an authorized stable record identifier or an export-local surrogate identifier. Internal identifiers shall not be disclosed merely for implementation convenience.
3. Each data sheet shall have clear column labels, consistent types, readable widths, a frozen header, and filters where applicable. Formatting shall not obscure or overwrite data.
4. Unicode text, line breaks, leading zeros, long identification numbers, approximate/partial dates, null values, and significant numeric precision shall be preserved. Identifiers shall be stored as text. Unknown dates shall not be invented; date/time representation and timezone shall be explicit.
5. User-originated values shall be serialized as literal data, including values beginning with formula-like characters. The workbook shall contain no user-supplied formulas, macros, DDE instructions, external data connections, executable embedded objects, or automatic external hyperlinks. Dangerous text shall not become executable spreadsheet content.
6. Excluded data shall not appear in hidden sheets/columns, comments, defined names, cached results, unused shared strings, embedded objects, document properties, or other package parts.
7. The writer shall enforce the verified limits of the selected XLSX format and supported readers. Oversized rows, columns, cell values, images, or workbooks shall trigger a documented split or explicit failure; silent truncation is prohibited. Any approved continuation-sheet representation shall retain ordering and record linkage.
8. Sheet names, titles, author properties, and other metadata shall be sanitized and minimized. Local usernames, filesystem paths, and unrelated application metadata shall not leak into the workbook.

### 85.6. Encryption and sensitive-data handling (EX-06)

1. Export decrypts authorized vault data for disclosure outside the vault. A normal XLSX file shall be treated as an unencrypted sensitive document. Vault encryption shall not be represented as automatically protecting its export.
2. The default sensitive-data export mode shall require approved encryption. A validated password-to-open encrypted XLSX profile may be used when it meets organizational security requirements and the tested reader compatibility matrix. Worksheet/workbook editing protection, hidden sheets, and password-to-modify restrictions shall never be represented as confidentiality controls.
3. If approved encrypted XLSX cannot be supported on a target platform, an approved encrypted container holding the XLSX may be offered as a clearly labeled separate output mode. Its outer extension shall identify the container; it shall not be disguised as a standalone XLSX file. If no compliant mode is available, export shall be refused without weakening encryption.
4. Plain XLSX export shall require an explicit policy grant for the relevant data classification and destination, plus an in-application acknowledgment that the file is readable outside AgentSecure. This acknowledgment shall not override policy.
5. Export secrets shall be distinct from application passwords, PKCS#12 passwords, device secrets, area keys, and person encryption keys. No cryptographic key material, password verifier, recovery secret, or authorization package shall be included in an export.
6. The application shall enforce the approved secret-strength policy, mask secret entry, avoid persisting secrets in presets/logs/command lines, and clear temporary secret buffers using the security core's supported memory protections. Passwords shall not be placed in filenames, workbook metadata, sidecars, or the same exported container.
7. Decryption shall be limited to selected permitted fields and bounded batches. Plaintext caches and diagnostic output shall be minimized. The application shall not claim immunity from paging, crash dumps, malware, screenshots, or administrator-level access on the host.
8. Once a recipient can decrypt a workbook, AgentSecure cannot enforce its original per-agent/per-area permissions inside that workbook. A combined multi-area export shall require policy permission and an intended audience authorized for its full contents; otherwise separate outputs shall be required. Recipient controls on native exchange packages remain the preferred mechanism when continuing access enforcement is required.

### 85.7. Classification, markings, and provenance (EX-07)

Every workbook shall carry an effective classification and handling restrictions derived from all included content. For non-ordered labels or incompatible compartments, policy shall prescribe a combined marking or prohibit combining the data. The user shall not be able to downgrade classification through formatting or field selection unless the classification rules explicitly permit the resulting projection.

The `Export Information` sheet shall include only approved metadata: unique export ID, application/export-schema version, source snapshot time, creation time with timezone, applicable security/policy versions, classification/handling notice, exporting agent and device identifiers when permitted, selected field summary, eligible area scope when permitted, record/sheet counts, photo policy, and completeness status. Sensitive filter values shall be omitted or protected according to policy.

Classification and export ID shall appear visibly on data sheets and in print headers/footers where supported. A watermark or equivalent visible banner may be used where supported by the selected reader; essential handling notices shall not depend on a watermark rendering correctly. Visible markings and document properties are removable metadata, not access controls, encryption, or proof of authenticity.

Where policy requires verifiable provenance, a digest and signed manifest shall cover the final output bytes and approved provenance fields through the existing signing infrastructure. Signing keys shall never leave the security core. The manifest shall be protected when its metadata is sensitive. Ordinary Excel export shall not be presented as a native authenticated synchronization package.

### 85.8. Audit logging (EX-08)

The existing protected audit service shall record export requests, authorization denials, confirmations, starts, successful publications, cancellations, failures, cleanup failures, and detected interrupted jobs. Events shall share a unique export/job ID.

Audit records shall include agent/device identity, timestamps with timezone and local clock trust limitations, policy/authorization versions, authorized scope or protected scope reference, selected field identifiers, selection mode, requested/processed/exported counts where safe, classification, protection mode, destination category and protected destination reference, output size, final digest when completed, outcome, and a non-disclosing reason code. Record-level manifests, if required, shall be encrypted and access-controlled separately.

Audit logs shall not contain exported values, photo bytes, passwords, raw keys, unrestricted filter text, or unprotected sensitive paths. Audit access and retention shall follow existing policy.

An audit failure before generation or publication shall fail closed. The publication process shall use durable intent and recovery records so a crash between final-file creation and completion logging cannot leave an untracked export. Recovery shall distinguish published, incomplete, failed, and publication-uncertain states; it shall not falsely label an uncertain export as successfully completed.

### 85.9. Filename and destination (EX-09)

1. The agent shall select a destination through a save dialog constrained by export policy. The default filename shall avoid personal/area names, for example `AgentSecure_Export_YYYYMMDD_HHMMSS_<ExportID>.xlsx`, using a documented timestamp convention and a collision-resistant export identifier.
2. Filenames shall be validated against supported Windows naming/path restrictions. The extension shall match the chosen protection/output mode. Existing files shall never be overwritten silently.
3. Before writing, the service shall validate the actual resolved destination, permitted device/media class, available space, write access, and required filesystem protections. It shall recheck at publication to prevent path redirection or destination changes from bypassing policy.
4. The application/vault/key directories shall not be export destinations. Public/shared folders, network shares, removable media, and synchronized folders shall be prohibited by default unless the applicable policy explicitly permits them. The application shall document limits in recognizing externally managed sync folders.
5. Where filesystem access controls are unavailable, the service shall require an approved protection mode that meets policy or refuse the destination. A portable or removable destination shall not imply that it is safe for plaintext.
6. Successful completion shall display the actual file path, counts, classification, and protection status. Opening the workbook or its folder shall require an explicit user action; export shall not automatically launch a spreadsheet application.

### 85.10. Temporary files and publication (EX-10)

The export writer shall avoid plaintext temporary files, including XML parts, images, caches, compression-library staging files, and fallback spill files. It shall use bounded memory and encrypted scratch storage with a fresh per-job key when disk staging is necessary. Dependencies shall be verified for hidden plaintext staging behavior.

Scratch files shall use unpredictable non-identifying names in a dedicated restricted location. Job keys shall not be stored beside their ciphertext. Shared system temporary directories shall not be assumed secure. An approved plaintext final export remains an explicit disclosure exception; its staging shall stay protected until controlled publication to the authorized destination.

Only a fully generated, validated, authorized, and audit-prepared output shall be published with its final name. The application shall use atomic publication where supported. On media without suitable guarantees, it shall use an explicitly incomplete name and a recoverable publication protocol; incomplete output shall never be presented as a successful workbook.

Cancellation, error, media removal, disk exhaustion, application shutdown, or restart recovery shall close handles, clear sensitive buffers where practical, discard job keys, and remove application-owned incomplete artifacts safely. Cleanup shall not delete pre-existing files or user outputs from unrelated jobs. Failures to clean up shall be reported and audited without disclosing contents.

Deletion/overwriting shall not be described as guaranteed secure erasure on SSDs, flash media, journaling filesystems, snapshots, or backups. Protection shall rely on preventing plaintext scratch creation and disposing of ephemeral encryption keys, with residual host risks documented.

### 85.11. Large exports, progress, and cancellation (EX-11)

The feature shall support the baseline database scale of at least 100,000 person records for approved data-only exports using bounded memory, paged reads, and streaming output. Photos shall have separate limits. Validation shall cover the supported legacy memory/address-space constraints, and release documentation shall state measured performance and resource ceilings.

Before execution, the application shall estimate count, output size, scratch requirements, and applicable limits where feasible. Estimates shall be labeled and shall reveal only eligible data. If safe space/resource requirements cannot be met, it shall explain the limitation and offer authorized scope reduction or an approved split strategy.

Progress shall show preparation, reading, writing, protection/validation, and publication stages; processed eligible records; elapsed time; and remaining work when measurable. Unknown totals shall use an indeterminate indicator rather than an invented percentage. The interface shall remain responsive.

Cancellation shall be available until final publication begins, which shall be a short clearly indicated critical phase. A cancellation request shall stop further record processing promptly, prevent final publication, and trigger cleanup and audit events. Release acceptance shall define and verify a cancellation response budget on minimum supported hardware. A blocked filesystem operation shall be reported honestly rather than falsely marked canceled.

Jobs shall not auto-resume with stale credentials after a crash or user change. Retrying shall start a newly authorized job. Multiple concurrent exports shall be disabled initially or explicitly bounded by policy and resource controls.

### 85.12. Photos and attachments (EX-12)

1. Photos and attachment binary content shall be excluded by default. Authorized metadata such as count, type, and approved display label may be selected separately; filenames and descriptions shall be treated as potentially sensitive.
2. When policy permits photos, the agent shall opt in explicitly. Only supported raster thumbnails at approved resolution/size shall be embedded. Photo access shall be independently authorized, image decoding shall be bounded, and location/device metadata such as EXIF shall be removed from exported image derivatives.
3. Original-resolution photos shall not be included through the initial Excel export feature. Thumbnail limits shall apply per record and per workbook. Missing or invalid authorized images shall produce a disclosed failure or an explicitly approved metadata-only rerun; no silent omission shall be reported as complete.
4. Arbitrary attachment binaries, executable content, OLE objects, PDFs, and Office documents shall not be embedded or extracted alongside the workbook in the initial feature. Authorized attachment metadata may be exported; full attachments shall use the existing approved encrypted exchange workflow.
5. Workbook links shall not reveal vault paths, local blob names, network locations, credentials, or external URLs. Export-local identifiers may describe related content without making it accessible.
6. Image processing and packaging shall follow the same encryption, temporary-file, classification, audit, and cancellation requirements as text data.

### 85.13. Limitations and user-facing guidance (EX-13)

Excel export shall be documented as a point-in-time reporting copy. It shall not substitute for a full backup, native secure exchange, or synchronization; round-trip import, conflict resolution, identity/key migration, and restoration are outside this feature.

The application shall explain that an authorized recipient can copy, modify, print, screenshot, or redistribute decrypted data; markings can be removed; and exported copies cannot be reliably recalled or revoked after disclosure. Revoking an agent or rotating an area key does not retroactively secure a workbook already exported.

Compatibility shall depend on the reader and approved encryption mode. Unsupported readers or legacy operating systems shall not trigger weaker encryption or hidden plaintext fallback. Documented row/cell/image/resource limits and excluded fields/content shall be visible before export where predictable.

### 85.14. Acceptance criteria (EX-14)

The feature shall not be accepted until verification demonstrates that:

1. An agent without export permission cannot export through either the UI or service layer; invalid device, Agent ↔ Device assignment, expired authorization, and known revocation also deny export.
2. Direct and inherited area assignments, newly created children, missing grants, removed areas, mixed-area selections, related records, and restricted fields obey current policy without leaking inaccessible counts or metadata.
3. Selected records across pages, filtered results, selected fields, and concurrent source changes produce the declared consistent scope and correct counts. A permission change during execution prevents publication.
4. Unicode, leading-zero identifiers, long identifiers, partial dates, nulls, multiline text, and formula-like strings retain their intended literal meaning. Inspection of all workbook package parts finds no excluded values or prohibited active/external content.
5. Approved readers open both ordinary and approved encrypted outputs correctly. Required encrypted outputs resist opening without the correct secret, and no plaintext fallback occurs when encryption is unavailable.
6. Classification banners, print markings where supported, provenance, and counts remain correct for single- and multi-sheet output. Sensitive metadata is absent where policy excludes it.
7. Successful, denied, canceled, failed, and interrupted jobs create appropriate protected audit records without logging payloads or secrets. Audit-storage failure blocks publication and crash recovery reconciles uncertain publication states.
8. At least 100,000 authorized data records export within documented resource ceilings on the supported platform matrix. Limit boundaries, insufficient space, invalid paths, duplicate filenames, media removal, malformed data/images, and cancellation fail safely without silent truncation or misleading success.
9. Normal completion, cancellation, crashes, and restart cleanup leave no application-created plaintext scratch data. Output publication does not overwrite unrelated files or allow path redirection to bypass destination policy.
10. Default exports contain no photos or attachment binaries; approved thumbnails enforce permissions, size limits, and metadata removal. The workbook does not expose private storage paths or external references.

### 85.15. Policy configuration required before release (EX-15)

The Administrator-facing configuration shall define export grants, classification/field rules, hierarchical area behavior, output-protection profiles and compatible readers, password rules, allowed destinations/media, permitted photo sizes, resource/splitting limits, audit retention, approval requirements, and any allowed plaintext exceptions. Unset security decisions shall fail closed; they shall not silently enable export.
