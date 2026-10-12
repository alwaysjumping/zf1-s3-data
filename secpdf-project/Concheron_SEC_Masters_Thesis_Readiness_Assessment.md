# Master's Thesis Readiness Assessment and Improvement Plan

**Research:** *A Secure Cryptographic Architecture for Controlled Distribution and Viewing of Confidential PDF Documents in Isolated Enterprise Networks*

**Status:** Advisory assessment, not an official university or supervisor evaluation.

## 1. Overall assessment

The research has the potential to support a master's thesis, but a degree cannot be guaranteed from the current draft. The topic is relevant, the research problem is clearly framed, and the architecture brings together recipient-bound encrypted distribution, CEK/DEK/KEK separation, certificate and hardware-token authentication, local re-encryption, server-side rendering, watermarking, and auditing.

The major gap is **empirical validation**. The existing thesis defines an experimental program, but unexecuted or blocked tests are not results. Some prototype cryptographic operations are also incomplete. Submission readiness depends on the university's standards, supervisor feedback, academic integrity rules, and successful defense.

## 2. Qualitative readiness assessment

| Area | Assessment | Recommended improvement |
|---|---|---|
| Research topic | Strong and relevant | Establish its significance using academic sources |
| Problem and questions | Clearly framed | Map each question to evidence and conclusions |
| Literature review | Good foundation | Add closely comparable peer-reviewed studies |
| Threat model | Detailed | Check assumptions and control coverage |
| Proposed architecture | Substantial conceptual design | Specify interfaces, invariants, and trust boundaries precisely |
| Cryptographic design | Good foundation | Validate protocol details and implementations |
| Mathematical models | Improved | Define all variables and assumptions; connect equations to experiments |
| Prototype | Incomplete | Complete and test the end-to-end import and key lifecycle |
| Experiments | Major gap | Execute predefined tests and publish real observations |
| Security analysis | Substantial but mainly theoretical | Distinguish formal reasoning, testing, and unverified claims |
| Originality | Plausible architectural contribution | Demonstrate why the integration addresses a research gap |
| Submission readiness | Not yet established | Seek supervisor review after validation |

## 3. Most important weakness: experimental evidence

A professor may ask: **“How do you demonstrate that your architecture works?”**

Complete and document tests for:

1. Successful import and rejection of tampered, forged, expired, and wrong-recipient packages.
2. Correct authenticated encryption/decryption and fail-closed behavior.
3. CEK/DEK/KEK separation, storage protection, and key rotation.
4. Certificate validation, offline revocation freshness, and authorization changes.
5. Direct-PDF denial, page-level IDOR protection, and view-token expiration.
6. Server-side rendering and watermark overhead.
7. CPU, memory, throughput, median latency, and P95 latency.
8. Concurrency at the planned workloads of 1, 10, 25, 50, and 100 users.
9. Cold-cache versus warm-cache performance.
10. Comparison against well-defined simpler document-delivery baselines.

For every experiment, record the environment, procedure, input, expected outcome, observed outcome, repeat count, and limitations. Preserve raw measurements and report negative results. **Never represent planned or simulated values as observed results.**

## 4. Defending originality

A likely question is: **“Aren't AES-GCM, RSA, PKI, and watermarking existing technologies?”**

Suggested answer:

> Yes. The contribution is not a new cryptographic primitive. It is a threat-driven lifecycle architecture for isolated enterprise networks, integrating recipient-restricted encrypted packages, independent distribution and storage keys, offline trust updates, staff authorization, controlled server-side viewing, and auditing. The research value depends on clear design rationale, comparison with alternatives, and empirical evaluation of the combined system.

To strengthen this claim, explicitly compare related academic systems, identify which combined constraints they do not address, and state what your experiments can and cannot demonstrate.

## 5. Distinguishing research from software development

The thesis should demonstrate a research sequence:

**Research question → literature and research gap → threat model → requirements → architectural choices → implementation → experiments → analysis → limitations → conclusion.**

The prototype is evidence used to investigate the research questions; it is not the entire academic contribution.

## 6. Limitations that must remain explicit

- An authorized viewer may still take screenshots or photograph the screen.
- A fully compromised trusted rendering server may expose plaintext during legitimate processing.
- Offline certificate revocation introduces freshness and response-delay limitations.
- A compiled PHP extension does not guarantee secrecy or cryptographic correctness.
- Incomplete cryptographic integration cannot be presented as production-validated.
- A passed test supports a specific security claim under tested conditions, not absolute security.
- Legacy PHP 7.4/ZF1 technology creates maintenance and security-lifecycle concerns.

## 7. Recommended improvement plan

### Priority 1 — Establish academic requirements

- Obtain the university's thesis rubric, originality requirements, minimum implementation expectations, and submission format.
- Confirm the acceptable scope of AI-assisted drafting and any disclosure requirements.
- Discuss the intended contribution with the supervisor.

### Priority 2 — Complete the prototype

- Finish recipient key recovery and AES-GCM package decryption.
- Finish local DEK generation, encryption, and KEK wrapping/rotation.
- Integrate or explicitly scope out hardware-token operations.
- Validate canonical package signing, strict parsing, nonce rules, and error handling.
- Maintain an implementation-status table distinguishing implemented, partial, designed, and future features.

### Priority 3 — Run experiments

- Execute predefined security and cryptographic negative tests.
- Run reproducible rendering and concurrency benchmarks.
- Compare the proposed approach with meaningful baselines.
- Collect measurements and produce tables, graphs, and interpretation.

### Priority 4 — Strengthen the thesis argument

- Expand the literature comparison with peer-reviewed research.
- Map each research question to concrete results.
- Explain why each equation matters and define all symbols.
- Revisit conclusions so every quantitative claim is supported by measurements.
- Discuss residual risks and threats not addressed.

### Priority 5 — Prepare the defense

- Rehearse a one-minute overview and a full architecture explanation.
- Practice explaining CEK, DEK, KEK, GCM, signatures, PKI, and challenge-response.
- Prepare for questions on originality, performance, incomplete components, and limitations.
- Obtain feedback from the supervisor and revise.

## 8. Practical readiness checklist

- [ ] University and supervisor requirements confirmed
- [ ] AI-use policy and disclosure requirements checked
- [ ] Research gap supported by comparable literature
- [ ] Research questions mapped to evidence
- [ ] Cryptographic prototype completed or accurately scoped
- [ ] End-to-end import tests executed
- [ ] Security negative tests executed
- [ ] Performance benchmarks executed
- [ ] Raw results and reproducibility details retained
- [ ] Baseline comparison completed
- [ ] Figures, tables, and formulas verified
- [ ] References and citations checked
- [ ] Limitations and future work reviewed
- [ ] Supervisor feedback incorporated
- [ ] Oral defense rehearsed

## 9. Questions to ask the supervisor

1. Is architectural integration plus empirical evaluation an acceptable master's contribution in this program?
2. What minimum level of prototype completion is expected?
3. Which experiments are essential for thesis approval?
4. How many peer-reviewed sources and comparison studies are expected?
5. Are formal proofs expected, or are threat analysis and empirical tests sufficient?
6. What AI-assisted writing and citation-disclosure policies apply?
7. What are the formal assessment and defense criteria?

## 10. Final recommendation

**Do not treat the current draft as submission-ready solely because it is detailed or includes mathematical formulas.** Treat it as a substantial foundation. Prioritize completing the cryptographic pipeline, conducting experiments, substantiating originality, verifying references, and obtaining supervisor feedback.

A defensible final claim is:

> This thesis proposes a lifecycle-oriented security architecture, identifies its assumptions and residual risks, and evaluates defined security properties and operational costs through reproducible experiments.

Use the phrase **“evaluates”** in the final thesis only after the corresponding experiments have actually been performed; until then, say **“defines an evaluation methodology.”**
