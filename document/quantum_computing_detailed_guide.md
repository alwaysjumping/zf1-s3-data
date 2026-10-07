# Quantum Computing --- Detailed Practical Guide

## 1. Introduction

Quantum computing is a different model of computation that uses
principles of quantum mechanics.

It is important to understand one point immediately:

> A quantum computer is not simply a much faster version of a normal
> computer.

Quantum computers are specialized machines that can provide major
advantages for **certain algorithms and problem structures**. Classical
computers remain better and more practical for most ordinary computing
tasks.

A likely long-term model is therefore:

``` text
                    Computing System
                         |
          +--------------+--------------+
          |              |              |
          v              v              v
         CPU            GPU            QPU
          |              |              |
     General work    Parallel/AI    Specialized
                                    quantum work
```

Where:

``` text
CPU = Central Processing Unit
GPU = Graphics Processing Unit
QPU = Quantum Processing Unit
```

------------------------------------------------------------------------

# Part I --- Classical Computing

## 2. Classical Bits

Traditional computers represent information using bits.

A bit has one of two states:

``` text
0
```

or:

``` text
1
```

Large amounts of information are represented by combinations of bits.

For example:

``` text
00000000
00000001
00000010
00000011
...
```

CPUs manipulate these bits using electronic logic gates.

------------------------------------------------------------------------

## 3. Classical Logic Gates

Traditional computers use operations such as:

``` text
AND
OR
NOT
XOR
```

Example:

``` text
A = 1
B = 1

A AND B = 1
```

Billions of transistors implement these operations at extremely high
speed.

Operating systems, browsers, databases, PHP, games, spreadsheets and
almost all normal applications ultimately execute through classical
operations.

------------------------------------------------------------------------

# Part II --- Qubits

## 4. What Is a Qubit?

A quantum computer uses **quantum bits**, normally called **qubits**.

A classical bit is:

``` text
0
or
1
```

A qubit can exist in a quantum state represented mathematically as:

``` text
|psi> = alpha|0> + beta|1>
```

where `alpha` and `beta` are complex amplitudes satisfying:

``` text
|alpha|^2 + |beta|^2 = 1
```

The probabilities of observing the basis states are related to the
squared magnitudes of those amplitudes.

------------------------------------------------------------------------

## 5. Superposition

A qubit can be prepared in a superposition of basis states.

Conceptually:

``` text
          Qubit
            |
      +-----+-----+
      |           |
     |0>         |1>
```

However, this does **not** mean that a quantum computer can simply
calculate every possible answer and then read all of them.

Quantum algorithms must manipulate probability amplitudes using
interference so that useful answers become more likely when measurement
occurs.

This distinction is extremely important.

------------------------------------------------------------------------

# Part III --- Measurement

## 6. Measuring a Qubit

Quantum information cannot simply be inspected like ordinary RAM without
affecting the quantum state.

When a qubit is measured in the computational basis, the result is
classical:

``` text
0
```

or:

``` text
1
```

For example:

``` text
Quantum state
     |
     v
 Measurement
     |
 +---+---+
 |       |
 0       1
```

The probabilities depend on the state before measurement.

Quantum algorithms therefore need to arrange the computation so that
measurement is likely to produce useful information.

------------------------------------------------------------------------

# Part IV --- Entanglement

## 7. What Is Entanglement?

Multiple qubits can have a joint quantum state that cannot be described
as independent states for each qubit.

This phenomenon is called **entanglement**.

For example, a two-qubit entangled state may be conceptually represented
as:

``` text
(|00> + |11>) / sqrt(2)
```

Measurements of the two qubits can exhibit strong correlations.

Entanglement is an important computational resource in many quantum
algorithms.

------------------------------------------------------------------------

## 8. Entanglement Does Not Mean Faster-Than-Light Messaging

A common misunderstanding is:

``` text
Entanglement
    =
instant communication
```

That is not correct.

Entanglement creates quantum correlations, but it does not allow
ordinary controllable information to be transmitted faster than light.

------------------------------------------------------------------------

# Part V --- Quantum Interference

## 9. Why Interference Matters

Quantum amplitudes can interfere.

Very roughly:

``` text
Possible computational paths
        |
        +---- path A ----+
        |                |
        +---- path B ----+----> interference
        |                |
        +---- path C ----+
```

A well-designed quantum algorithm attempts to:

``` text
increase amplitudes
for useful outcomes
```

and:

``` text
reduce/cancel amplitudes
for unwanted outcomes
```

This is a more accurate mental model than saying a quantum computer
simply "tries every answer simultaneously."

------------------------------------------------------------------------

# Part VI --- Quantum Gates

## 10. Quantum Circuits

Quantum programs are often described as circuits containing quantum
gates.

Common gates include:

``` text
X
H
Z
CNOT
phase/rotation gates
```

A simplified circuit might look like:

``` text
|0> ---- H ----●---- Measure
               |
|0> -----------X---- Measure
```

The `H` gate can create superposition, while controlled operations such
as CNOT can create entanglement under appropriate conditions.

------------------------------------------------------------------------

## 11. Hadamard Gate

A Hadamard gate is often written:

``` text
H
```

Applying it to `|0>` produces:

``` text
H|0>
 =
(|0> + |1>) / sqrt(2)
```

This creates an equal superposition in the computational basis.

------------------------------------------------------------------------

# Part VII --- Quantum vs Classical Parallelism

## 12. A Common Misunderstanding

Suppose there are `n` qubits.

Their joint state is described using:

``` text
2^n
```

complex amplitudes.

For example:

``` text
1 qubit   -> 2 amplitudes
2 qubits  -> 4 amplitudes
3 qubits  -> 8 amplitudes
10 qubits -> 1024 amplitudes
```

It is tempting to conclude:

> A quantum computer calculates 2\^n normal answers at once.

That is misleading.

Measurement does not reveal all `2^n` amplitudes.

Quantum algorithms need interference and problem structure to extract
useful information.

------------------------------------------------------------------------

# Part VIII --- QPU

## 13. What Is a QPU?

QPU means:

> Quantum Processing Unit

A QPU is the component that performs quantum operations.

Conceptually:

``` text
Classical Computer
       |
       | prepare/control job
       v
      QPU
       |
       | quantum operations
       v
Measurement
       |
       v
Classical results
       |
       v
CPU processes results
```

A QPU normally depends heavily on classical computers for:

-   control
-   scheduling
-   calibration
-   error processing
-   compiling circuits
-   interpreting results
-   user interfaces
-   networking

------------------------------------------------------------------------

# Part IX --- Physical Qubits

## 14. How Can a Qubit Be Built?

There are multiple approaches to physical quantum hardware.

Examples include:

``` text
superconducting circuits
trapped ions
neutral atoms
photonic systems
spin-based systems
```

Different approaches have different tradeoffs in:

-   gate speed
-   coherence
-   fidelity
-   connectivity
-   cooling requirements
-   scalability
-   error rates

There is no universal physical qubit technology that has already won
every use case.

------------------------------------------------------------------------

# Part X --- Why Quantum Computers Are Difficult

## 15. Quantum States Are Fragile

Quantum information is sensitive to environmental noise.

Problems include:

``` text
decoherence
gate errors
measurement errors
control errors
cross-talk
thermal/environmental noise
```

This makes building large reliable quantum computers extremely
difficult.

------------------------------------------------------------------------

## 16. Quantum Error Correction

Classical computers can use redundancy for error correction.

Quantum computing also requires error correction, but quantum
information cannot simply be copied arbitrarily because of constraints
such as the no-cloning theorem.

Quantum error-correcting codes encode logical information across
multiple physical qubits.

Conceptually:

``` text
Many physical qubits
        |
        v
Error-correction code
        |
        v
Logical qubit
```

A future fault-tolerant quantum computer may require many physical
qubits to implement a much smaller number of high-quality logical
qubits.

The exact overhead depends on hardware quality, code, architecture and
target computation.

------------------------------------------------------------------------

# Part XI --- NISQ and Fault-Tolerant Quantum Computing

## 17. Current/Intermediate Quantum Machines

The term **NISQ** has been used for:

> Noisy Intermediate-Scale Quantum

These machines have limited qubit counts and significant noise.

They are different from a large-scale fault-tolerant quantum computer
capable of executing extremely deep cryptographically relevant
algorithms reliably.

------------------------------------------------------------------------

## 18. Fault-Tolerant Quantum Computer

A fault-tolerant system uses quantum error correction so computations
can continue reliably despite physical errors.

Conceptually:

``` text
Physical Qubits
      |
      v
Quantum Error Correction
      |
      v
Logical Qubits
      |
      v
Large Reliable Quantum Algorithm
```

This distinction matters greatly when discussing attacks on
cryptography.

Saying:

``` text
"A quantum computer exists"
```

does not automatically mean:

``` text
"RSA can now be broken."
```

The required machine must have sufficient logical qubits, fidelity,
error correction, runtime and overall resources.

------------------------------------------------------------------------

# Part XII --- Important Quantum Algorithms

## 19. Shor's Algorithm

Shor's algorithm is one of the most important quantum algorithms for
cryptography.

It can efficiently solve mathematical problems including:

``` text
integer factorization
discrete logarithms
```

These problems underpin major traditional public-key systems.

Therefore:

``` text
RSA
 |
 v
integer factorization
 |
 v
Shor
 |
 v
fundamental quantum threat
```

and:

``` text
ECC
 |
 v
elliptic-curve discrete logarithm
 |
 v
Shor
 |
 v
fundamental quantum threat
```

------------------------------------------------------------------------

## 20. Grover's Algorithm

Grover's algorithm provides a quadratic speedup for unstructured search.

A simplified comparison is:

``` text
Classical search:
O(N)

Quantum Grover search:
O(sqrt(N))
```

For an idealized `n`-bit symmetric key search:

``` text
Classical scale:
2^n

Grover scale:
~2^(n/2)
```

Therefore the common simplified intuition is:

``` text
AES-128 -> ~64-bit generic quantum-search scale

AES-256 -> ~128-bit generic quantum-search scale
```

Actual quantum resource requirements are much more complicated than
these simple exponents.

------------------------------------------------------------------------

# Part XIII --- Quantum Computing and Cryptography

## 21. RSA

RSA relies on integer-factorization hardness.

A sufficiently capable fault-tolerant quantum computer running Shor's
algorithm fundamentally threatens RSA.

Making RSA keys simply larger is not the long-term post-quantum
solution.

------------------------------------------------------------------------

## 22. ECC

ECC relies on elliptic-curve discrete-logarithm problems.

Shor's algorithm also threatens ECC.

Therefore:

``` text
RSA -> vulnerable to sufficiently capable quantum computers

ECC -> vulnerable to sufficiently capable quantum computers
```

------------------------------------------------------------------------

## 23. AES

AES is different.

Shor's algorithm does not directly break AES.

The main generic quantum consideration is Grover-style key search.

Therefore AES-256 retains a very large security margin in the usual
simplified post-quantum analysis.

``` text
AES-256
   |
   v
2^256 classical key space
   |
   v
~2^128 idealized Grover search scale
```

For this reason AES-256 remains an important symmetric-encryption choice
for long-lived systems.

------------------------------------------------------------------------

# Part XIV --- Post-Quantum Cryptography

## 24. What Is PQC?

Post-quantum cryptography (PQC) consists of algorithms designed to
resist known classical and quantum attacks.

An important point:

> PQC runs on normal classical computers.

You do not need a quantum computer to use post-quantum cryptography.

``` text
Normal CPU
    |
    v
PQC algorithm
```

------------------------------------------------------------------------

## 25. Important Post-Quantum Algorithms

Important standardized post-quantum algorithms include:

``` text
ML-KEM
ML-DSA
SLH-DSA
```

Their roles differ:

``` text
ML-KEM
 -> key establishment

ML-DSA
 -> digital signatures

SLH-DSA
 -> digital signatures
```

------------------------------------------------------------------------

## 26. Replacing RSA and ECC

A simplified migration mapping is:

  Current technology   Role                           Post-quantum direction
  -------------------- ------------------------------ ------------------------
  RSA key transport    Key protection/establishment   ML-KEM
  ECDH / X25519        Key agreement                  ML-KEM
  RSA signatures       Signature                      ML-DSA / SLH-DSA
  ECDSA / EdDSA        Signature                      ML-DSA / SLH-DSA
  AES-256              Bulk encryption                Keep AES-256

The general architecture becomes:

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
AES-256 Key
   |
   v
AES-256-GCM
   |
   v
Encrypted Data
```

------------------------------------------------------------------------

# Part XV --- Will Quantum Computers Replace Normal PCs?

## 27. Short Answer

Probably not.

Quantum computers are unlikely to replace ordinary PCs in the way PCs
replaced older computing equipment.

They are much more likely to become **specialized computing accelerators
used together with classical computers**.

A useful analogy is the GPU.

------------------------------------------------------------------------

## 28. CPU + GPU + QPU

Historically, many computers primarily depended on the CPU:

``` text
CPU
 |
 +---- general computation
```

Modern systems increasingly use specialized processors:

``` text
                 Computer
                    |
       +------------+------------+
       |            |            |
       v            v            v
      CPU          GPU          QPU
       |            |            |
   General       Graphics/    Quantum
   computing       AI        algorithms
```

A future computer or datacenter could therefore contain or access all
three.

------------------------------------------------------------------------

## 29. Why a QPU Does Not Replace a CPU

Normal computers perform huge amounts of classical work:

``` text
Operating systems
Browsers
PHP
MariaDB
Web servers
Email
Word processing
File systems
Networking
Business applications
Games
User interfaces
```

There is generally no advantage in sending a normal SQL query such as:

``` sql
SELECT *
FROM users
WHERE id = 100;
```

to a quantum processor.

A classical CPU performs such work efficiently and cheaply.

------------------------------------------------------------------------

## 30. Quantum Computer as an Accelerator

A more realistic model is:

``` text
Application
    |
    v
Classical CPU
    |
    +------------------------+
    |                        |
    v                        v
normal computation      special problem
                             |
                             v
                            QPU
                             |
                             v
                      quantum computation
                             |
                             v
                         result
                             |
                             v
                            CPU
```

The CPU remains responsible for the application and delegates only
suitable computations to the QPU.

------------------------------------------------------------------------

## 31. Similarity to GPUs

GPUs did not eliminate CPUs.

Instead:

``` text
CPU
 -> operating system
 -> application logic
 -> general computation

GPU
 -> graphics
 -> massively parallel workloads
 -> AI/ML acceleration
```

Likewise:

``` text
QPU
 -> selected quantum algorithms
 -> quantum simulation
 -> specialized search/optimization
 -> other problems with demonstrated
    quantum advantage
```

The QPU complements rather than universally replaces the CPU.

------------------------------------------------------------------------

# Part XVI --- Will Laptops Have QPUs?

## 32. Possible Models

It is difficult to predict exactly how future consumer quantum computing
will be delivered.

Several possibilities exist.

### Model A --- Remote Quantum Datacenter

``` text
Laptop
  |
  | secure network/API
  v
Quantum Datacenter
  |
  v
QPU
```

This is particularly plausible because many quantum technologies require
highly specialized infrastructure.

### Model B --- Specialized Enterprise System

``` text
Enterprise Datacenter
       |
       +---- CPU servers
       +---- GPU servers
       +---- QPU system
```

### Model C --- Future Integrated Technology

If quantum hardware changes dramatically, some form of more closely
integrated quantum accelerator could eventually become possible.

However, it should not be assumed that today's cryogenic quantum
hardware will simply shrink into every laptop.

------------------------------------------------------------------------

# Part XVII --- Extreme Operating Conditions

## 33. Why Quantum Hardware Is Different

Some leading quantum-computing technologies require extremely controlled
environments.

Depending on the technology, requirements can include:

``` text
very low temperatures
vacuum systems
laser systems
electromagnetic isolation
precision control electronics
complex calibration
```

For some superconducting quantum systems, operating temperatures are
close to absolute zero.

This is very different from a conventional CPU:

``` text
CPU
 -> room-temperature electronic system

Some QPUs
 -> specialized cryogenic environment
```

This is another reason cloud/datacenter access to quantum processors is
a natural architecture.

------------------------------------------------------------------------

# Part XVIII --- What Problems May Benefit?

## 34. Quantum Simulation

Quantum systems are difficult to simulate classically as their size
grows.

Quantum computers may be particularly useful for simulating quantum
phenomena.

Potential fields include:

``` text
chemistry
materials science
molecular simulation
physics
drug-related research
```

The exact practical advantages depend on future hardware and algorithms.

------------------------------------------------------------------------

## 35. Cryptography

Cryptography is one of the clearest theoretically important areas
because of Shor's algorithm.

``` text
RSA
ECC
 |
 v
quantum threat
```

This is why governments, standards organizations and industry are
migrating toward PQC before a cryptographically relevant quantum
computer is available.

------------------------------------------------------------------------

## 36. Search and Optimization

Some search and optimization problems may benefit from quantum
algorithms.

However:

> A quantum computer does not automatically make every optimization
> problem exponentially faster.

The speedup depends on the specific problem, algorithm, data-access
assumptions and hardware.

Claims of universal quantum speedups should therefore be treated
cautiously.

------------------------------------------------------------------------

## 37. Machine Learning

Quantum machine learning is an active research field.

Possible future uses include quantum subroutines within larger classical
AI systems.

A plausible architecture is:

``` text
AI Application
     |
     v
    CPU
     |
     +---- GPU
     |
     +---- QPU
```

However, GPUs remain vastly more mature and practical for current
mainstream AI workloads.

------------------------------------------------------------------------

# Part XIX --- Quantum Advantage

## 38. What Does Quantum Advantage Mean?

A quantum advantage occurs when a quantum computer performs a task in a
way that provides a meaningful advantage over relevant classical
approaches.

The important question is not simply:

``` text
Can a quantum computer execute the task?
```

but:

``` text
Does it provide a useful advantage
over the best practical classical method?
```

That advantage might involve:

``` text
runtime
memory
energy
accuracy
problem size
```

depending on the task.

------------------------------------------------------------------------

# Part XX --- Quantum Supremacy

## 39. Terminology

The phrase **quantum supremacy** has historically been used for
experiments demonstrating that a quantum processor can perform a
particular computational task beyond practical classical simulation.

The more neutral term:

``` text
quantum computational advantage
```

is also commonly used.

Such demonstrations do not mean quantum computers have become better
than classical computers at all useful tasks.

------------------------------------------------------------------------

# Part XXI --- Hybrid Classical/Quantum Computing

## 40. The Most Important Future Architecture

The most useful mental model is hybrid computing:

``` text
                   User Application
                         |
                         v
                    Classical CPU
                         |
             +-----------+-----------+
             |                       |
             v                       v
            GPU                     QPU
             |                       |
        AI / graphics          Quantum tasks
             |                       |
             +-----------+-----------+
                         |
                         v
                     CPU result
```

Classical software coordinates the workflow.

Quantum processing is invoked only where appropriate.

------------------------------------------------------------------------

# Part XXII --- Programming Quantum Computers

## 41. Quantum Programs Still Need Classical Software

A developer generally does not interact directly with physical qubits.

The software stack can look like:

``` text
Application
    |
    v
Quantum SDK / Framework
    |
    v
Quantum Compiler
    |
    v
Control System
    |
    v
QPU
```

The QPU then returns measurement results to classical software.

------------------------------------------------------------------------

## 42. Quantum Circuit Example

A conceptual two-qubit circuit:

``` text
q0: |0> ---- H ----●---- M
                   |
q1: |0> -----------X---- M
```

This can prepare an entangled Bell state.

The classical application then collects measurement results over
repeated executions.

------------------------------------------------------------------------

# Part XXIII --- Why Repeated Measurements Are Often Needed

## 43. Quantum Results Are Statistical

Many quantum algorithms produce probabilistic measurement outcomes.

Therefore a circuit may be executed many times.

These repetitions are often called:

``` text
shots
```

For example:

``` text
Run circuit 1,000 times

Results:

00 -> approximately 500
11 -> approximately 500
```

for an idealized Bell-state experiment.

The classical computer analyzes these results.

------------------------------------------------------------------------

# Part XXIV --- Quantum Networking

## 44. Quantum Network vs Internet

Quantum networking is another research area involving quantum states and
entanglement distribution.

It should not be confused with ordinary TCP/IP networking.

A future system could use:

``` text
Classical Internet
        +
Quantum communication infrastructure
```

rather than replacing all classical networking.

Classical communication remains necessary in many quantum protocols.

------------------------------------------------------------------------

# Part XXV --- Quantum Key Distribution

## 45. QKD

Quantum Key Distribution (QKD) uses quantum communication principles to
help establish secret keys and detect certain kinds of interception.

A famous example is:

``` text
BB84
```

However:

``` text
QKD != Post-Quantum Cryptography
```

PQC:

``` text
runs on ordinary computers
works through conventional networks
uses new mathematical cryptography
```

QKD:

``` text
requires specialized quantum communication
hardware/channels
```

For ordinary enterprise applications, PQC is generally much easier to
deploy than building a QKD infrastructure.

------------------------------------------------------------------------

# Part XXVI --- Quantum Computing vs Post-Quantum Cryptography

## 46. Do Not Confuse Them

``` text
Quantum Computing
       |
       v
Uses quantum hardware


Post-Quantum Cryptography
       |
       v
Runs on normal classical hardware
but is designed to resist quantum attacks
```

Therefore your existing:

``` text
PHP 7.4
CentOS
MariaDB
Apache
```

environment does not need a QPU merely to use PQC.

The main requirement is suitable cryptographic libraries/protocol
support.

------------------------------------------------------------------------

# Part XXVII --- Harvest Now, Decrypt Later

## 47. Why Migration Starts Before Quantum Attacks Exist

An attacker can potentially capture encrypted information today:

``` text
2026
 |
 +---- capture ciphertext
 |
 +---- store it
 |
 v
Future
 |
 +---- powerful quantum computer
 |
 v
attempt to decrypt historical data
```

This threat model is commonly called:

> Harvest now, decrypt later.

It matters especially for information that must remain confidential for
many years.

------------------------------------------------------------------------

# Part XXVIII --- What Quantum Computing Will Not Do

## 48. Common Myths

### Myth 1

``` text
Quantum computer =
infinitely fast computer
```

False.

### Myth 2

``` text
Quantum computer =
replacement for every CPU
```

Very unlikely.

### Myth 3

``` text
Quantum computer =
all encryption becomes useless
```

False.

RSA and ECC face major quantum threats, while strong symmetric
cryptography and post-quantum algorithms remain available.

### Myth 4

``` text
Qubit =
stores both normal bits and lets
us read both whenever we want
```

Misleading.

Measurement yields classical outcomes, and useful quantum computation
requires carefully designed interference.

### Myth 5

``` text
PQC requires a quantum computer
```

False.

PQC runs on classical computers.

------------------------------------------------------------------------

# Part XXIX --- Practical Future Computer

## 49. A Possible Future Architecture

A future enterprise computing environment may look like:

``` text
+------------------------------------------------+
| Enterprise Computing Platform                  |
|                                                |
|  +------------------+                          |
|  | CPU Servers      |                          |
|  |------------------|                          |
|  | Web applications |                          |
|  | Databases        |                          |
|  | Business logic   |                          |
|  +------------------+                          |
|           |                                    |
|           +----------------+                   |
|           |                |                   |
|           v                v                   |
|  +----------------+  +----------------+        |
|  | GPU Cluster    |  | QPU Service    |        |
|  |----------------|  |----------------|        |
|  | AI             |  | Quantum        |        |
|  | ML             |  | algorithms     |        |
|  | Simulation     |  | Simulation     |        |
|  +----------------+  +----------------+        |
+------------------------------------------------+
```

This resembles today's accelerator model more than a complete
replacement of classical computing.

------------------------------------------------------------------------

# Part XXX --- Practical Advice for Software Architects

## 50. Do Not Redesign Normal Applications Around Quantum Computing

For ordinary applications:

``` text
PHP
MariaDB
Web APIs
WebSockets
business applications
file storage
authentication
```

continue using classical architecture.

Do not attempt to move ordinary application logic onto quantum
processors.

------------------------------------------------------------------------

## 51. Prepare Cryptography Instead

For most enterprise developers, the important quantum-related work is
currently **cryptographic migration and crypto agility**.

For example:

``` text
Current:

RSA / ECC
    |
    v
AES-256
```

Possible PQ-oriented future:

``` text
ML-KEM
   |
   v
HKDF
   |
   v
AES-256-GCM
```

For signatures:

``` text
RSA / ECDSA
      |
      v
ML-DSA / SLH-DSA
```

------------------------------------------------------------------------

## 52. Build Crypto Agility

Do not spread algorithm-specific code throughout the application.

Prefer:

``` text
Application
    |
    v
Crypto Service Layer
    |
    +---- Encryption
    +---- Key Establishment
    +---- Signature
    +---- Hashing
```

This makes future algorithm migration much easier.

------------------------------------------------------------------------

# Part XXXI --- Summary

## 53. Classical vs Quantum

``` text
Classical computer
 -> bits
 -> deterministic classical logic
 -> general-purpose computation

Quantum computer
 -> qubits
 -> superposition
 -> entanglement
 -> interference
 -> measurement
 -> specialized algorithms
```

------------------------------------------------------------------------

## 54. CPU vs GPU vs QPU

``` text
CPU
 -> general-purpose computing

GPU
 -> graphics
 -> massively parallel processing
 -> AI/ML

QPU
 -> specialized quantum algorithms
```

The likely future is:

``` text
CPU + GPU + QPU
```

not:

``` text
QPU replaces everything
```

------------------------------------------------------------------------

## 55. Cryptography Summary

``` text
RSA
 -> threatened by Shor

ECC
 -> threatened by Shor

AES-256
 -> affected by generic quantum search
    but retains a large security margin

ML-KEM
 -> post-quantum key establishment

ML-DSA / SLH-DSA
 -> post-quantum signatures
```

------------------------------------------------------------------------

# 56. Final Perspective

Quantum computing is best understood as a **new specialized
computational capability**, not a universal replacement for classical
computers.

The long-term computing environment is likely to remain heterogeneous:

``` text
                 Computing
                     |
       +-------------+-------------+
       |             |             |
       v             v             v
      CPU           GPU           QPU
       |             |             |
    General        AI /        Quantum
     work         graphics      problems
```

For most software developers, normal applications will continue to run
on classical CPUs and GPUs.

The major near- and medium-term architectural consideration is not
rewriting normal applications for quantum computers. It is ensuring that
systems using long-lived cryptography can migrate away from
quantum-vulnerable public-key algorithms such as RSA and ECC toward
standardized post-quantum cryptography while retaining strong symmetric
encryption such as AES-256.
