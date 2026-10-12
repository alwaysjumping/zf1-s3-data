# Bitcoin Fundamentals — From Blockchain and Mining to Wallet Security

## 1. Introduction

Bitcoin is a decentralized digital money system. It allows people to transfer value without requiring a central bank or a single central server to maintain everyone's balances.

A useful simplified picture is:

```text
Bitcoin
  |
  +-- Blockchain   -> transaction history
  +-- Nodes        -> verify and enforce rules
  +-- Miners       -> perform Proof of Work and produce blocks
  +-- Wallets      -> manage keys and create/sign transactions
  +-- Cryptography -> protects authorization and data integrity
```

Bitcoin is not just a cryptocurrency token. It is a combination of cryptography, networking, distributed validation, economic incentives, and a public ledger.

---

## 2. What Is the Blockchain?

The blockchain is a chronological sequence of blocks containing Bitcoin transactions.

```text
Block 1
   |
   v
Block 2
   |
   v
Block 3
   |
   v
...
```

Each block commits to the previous block through cryptographic hashing. This links the blocks together.

A simplified block relationship is:

```text
Block N
  |
  +-- Transactions
  +-- Previous block hash
  +-- Other header information
  +-- Proof of Work
```

Changing an old transaction changes cryptographic commitments associated with that block. This breaks the expected relationship with later blocks and means an attacker would need to redo enormous amounts of Proof of Work to construct a competing history.

### Blockchain size

The Bitcoin blockchain has grown to hundreds of gigabytes and continues to grow as new blocks are added.

Not every Bitcoin user needs to store the complete blockchain. Different participants can use different storage strategies.

```text
Archival full node
    -> validates blocks
    -> keeps complete historical block data

Pruned full node
    -> validates blocks
    -> maintains necessary current state
    -> deletes older block files after validation

Lightweight wallet/client
    -> stores much less information
    -> relies on other infrastructure for some blockchain information
```

---

## 3. What Is a Bitcoin Node?

A Bitcoin full node independently verifies Bitcoin's consensus rules.

When it receives a transaction, it checks whether the transaction is valid according to the rules it implements.

```text
Transaction
    |
    v
Full Node
    |
    +-- Check transaction structure
    +-- Check spending authorization/scripts
    +-- Check referenced outputs
    +-- Check consensus rules
    |
    +-- Valid   -> accept/relay as appropriate
    +-- Invalid -> reject
```

Nodes also validate blocks produced by miners.

```text
Miner
  |
  v
Candidate block
  |
  v
Full Node
  |
  +-- Verify Proof of Work
  +-- Verify transactions
  +-- Verify block structure
  +-- Verify issuance limits
  +-- Verify consensus rules
  |
  v
Accept or reject
```

### Do nodes earn Bitcoin?

Normally, simply operating a full node does **not** earn Bitcoin.

```text
Full Node
   -> verifies and relays
   -> no automatic Bitcoin reward

Miner
   -> performs Proof of Work
   -> may earn block subsidy + transaction fees
```

People and organizations still operate nodes because independent validation is valuable. A business accepting Bitcoin, for example, may prefer to verify its own transactions rather than depend entirely on a third-party service.

### Nodes do not vote by simple node count

Bitcoin is not based on:

```text
1 node = 1 vote
```

Each node independently applies the rules it is configured to enforce.

A miner proposes a block; nodes decide whether that block is valid according to their own consensus rules.

```text
Miner: "Here is a new block."

Node:
  valid   -> accept
  invalid -> reject
```

Creating many fake node identities is relatively cheap. This is related to the Sybil problem, which is one reason Bitcoin does not use simple node-count voting to choose blockchain history.

---

## 4. What Happens If Nobody Keeps the Complete Blockchain History?

A useful distinction is between an **archival full node** and a **pruned full node**.

A pruned node can fully validate the blockchain while deleting old block files after they are no longer needed for its local operation.

If every archival copy of very old blocks disappeared but validated current state and functioning nodes remained, Bitcoin could potentially continue processing new transactions and blocks.

```text
Current valid state
      |
      + New transactions
      |
      + New blocks
      v
Continue validating and updating state
```

However, this would create a serious historical/bootstrap problem. A brand-new node could no longer download the missing history from peers and independently reconstruct the state from Genesis.

So archival history is extremely valuable even though every operating node does not need to keep every old block file forever.

If all functioning nodes, usable state, blockchain copies, and backups somehow disappeared permanently, the existing Bitcoin ledger could not simply be reconstructed from nothing.

---

## 5. What Is Bitcoin Mining?

Mining performs two major functions:

1. It participates in producing new blocks.
2. It provides Proof of Work that makes competing blockchain histories expensive to construct.

A miner creates a candidate block containing transactions and repeatedly hashes block-header candidates.

```text
Candidate block header
        |
        v
     SHA-256
        |
        v
     Hash value
        |
        +-- Meets target? YES -> candidate Proof of Work found
        |
        +-- NO -> modify candidate and try again
```

Bitcoin mining uses SHA-256-based Proof of Work.

---

## 6. Why Do Miners Calculate So Many Hashes?

The resulting block hash must satisfy the network's Proof-of-Work target.

Conceptually:

```text
block_hash <= target
```

People sometimes explain this as requiring a hash with many leading zeros. That is a convenient visual simplification; technically the hash interpreted as a number must be at or below the target.

SHA-256 output is unpredictable enough for this purpose that miners cannot calculate the winning nonce directly. They must repeatedly try candidates.

```text
Nonce 1 -> hash -> fail
Nonce 2 -> hash -> fail
Nonce 3 -> hash -> fail
...
Nonce X -> hash -> success
```

There is no global maximum number of attempts.

A miner stops working on a particular candidate when, for example:

- it finds a valid Proof of Work, or
- another valid block changes the chain tip and the miner constructs a new candidate.

### The nonce is not the only thing that can change

The block header contains a 32-bit nonce, giving 2^32 possible nonce values:

```text
4,294,967,296 values
```

Modern ASICs can exhaust this range extremely quickly. Mining software therefore changes other candidate-block information, such as coinbase-related data, which changes the Merkle root and creates a new header search space.

---

## 7. Difficulty and the 10-Minute Block Target

Bitcoin targets an average block interval of approximately 10 minutes.

It does **not** mean every block takes exactly 10 minutes.

Mining is probabilistic:

```text
Sometimes:  1 minute
Sometimes:  8 minutes
Sometimes: 20 minutes
...
Long-term average targets roughly 10 minutes
```

Bitcoin periodically adjusts mining difficulty. The adjustment occurs every 2,016 blocks, approximately two weeks if blocks have been arriving near the target rate.

```text
More total hash power
        |
        v
Blocks tend to arrive faster
        |
        v
Difficulty eventually increases

Less total hash power
        |
        v
Blocks tend to arrive slower
        |
        v
Difficulty eventually decreases
```

This feedback mechanism helps keep long-term block production near the intended average.

---

## 8. CPU, GPU, FPGA, and ASIC Mining

Bitcoin mining evolved through increasingly specialized hardware:

```text
CPU
 |
 v
GPU
 |
 v
FPGA
 |
 v
ASIC
```

### CPU

A CPU is a general-purpose processor. It can run operating systems, browsers, PHP, databases, compression, cryptography, and many other workloads.

This flexibility makes it inefficient compared with specialized hardware for modern Bitcoin mining.

### GPU

A GPU contains many processing units suited to parallel computation. GPUs historically provided a large mining advantage over CPUs.

### FPGA

An FPGA is configurable hardware that can implement specialized computation more efficiently than general-purpose processors.

### ASIC

ASIC means **Application-Specific Integrated Circuit**.

A Bitcoin mining ASIC is built specifically for SHA-256 mining work.

```text
CPU:
"I can execute many kinds of programs."

GPU:
"I can perform many parallel calculations."

Bitcoin ASIC:
"I am specialized for SHA-256 mining."
```

ASICs therefore dominate modern Bitcoin mining.

### Hash rate

Mining performance is expressed as hashes per second.

```text
H/s  = hashes per second
kH/s = thousands
MH/s = millions
GH/s = billions
TH/s = trillions
EH/s = quintillions
```

For example:

```text
1 TH/s = 1,000,000,000,000 hashes/second
```

### Energy efficiency

Hash rate alone is not enough. Electricity consumption is a major mining cost.

ASIC efficiency is often discussed using joules per terahash (J/TH).

```text
Lower J/TH = better energy efficiency
```

Mining profitability depends on factors such as:

- ASIC hash rate
- ASIC efficiency
- electricity price
- Bitcoin price
- network difficulty
- transaction fees
- pool fees
- hardware purchase cost
- cooling and facility costs

---

## 9. Proof of Work and Blockchain Security

Each block references the previous block.

```text
Block A
  |
  v
Block B
  |
  v
Block C
  |
  v
Block D
```

If an attacker modifies historical data, the associated commitments and block hashes change.

The attacker would need to build a valid competing chain and perform enough Proof of Work to overcome the honest chain's accumulated work while honest miners continue working.

Bitcoin nodes choose among valid competing histories based on accumulated Proof of Work (chainwork), not simply the number of blocks.

### Confirmations

When your transaction first appears in a block:

```text
Block containing transaction -> 1 confirmation
Next block                  -> 2 confirmations
Next block                  -> 3 confirmations
...
```

More confirmations mean more Proof of Work has accumulated after the transaction, making a reorganization affecting it progressively more difficult.

---

## 10. What Can a 51% Attacker Do?

An entity controlling a majority of mining hash power could potentially cause serious problems, including:

- reorganizing recent blockchain history
- attempting double-spends involving its own transactions
- censoring or delaying selected transactions
- disrupting normal block production

But majority hash power does **not** automatically give the attacker other people's private keys.

It does not mean:

```text
51% hash rate -> know everyone's private keys
```

Nor can the attacker simply create arbitrary coins that violate the consensus rules and force ordinary validating nodes to accept them.

Mining power and validation authority are different kinds of power.

---

## 11. How New Bitcoin Is Created

When a miner successfully creates a valid block, that block contains a special transaction called the **coinbase transaction**.

This term is unrelated to the Coinbase company.

The coinbase transaction can claim:

```text
Block subsidy
     +
Transaction fees
```

For the current subsidy era that began with the 2024 halving:

```text
Block subsidy = 3.125 BTC
```

Example:

```text
Block subsidy       3.125 BTC
Transaction fees    0.150 BTC
                   ---------
Maximum reward      3.275 BTC
```

The miner specifies output(s) for the reward according to its mining/pool setup.

```text
Coinbase transaction
        |
        v
Mining reward output
        |
        v
Address/script controlled by miner or pool
```

Nodes verify that the miner has not claimed more than permitted. A miner attempting to create an excessive reward produces an invalid block that normal nodes reject.

### Coinbase maturity

New coinbase outputs cannot be spent immediately. They must mature for 100 additional blocks before spending.

At roughly 10 minutes per block, that corresponds to approximately 16–17 hours on average.

---

## 12. Bitcoin Halving

The Bitcoin block subsidy is reduced approximately every 210,000 blocks.

Historically:

```text
2009 -> 50 BTC
2012 -> 25 BTC
2016 -> 12.5 BTC
2020 -> 6.25 BTC
2024 -> 3.125 BTC
2028 -> expected 1.5625 BTC
...
```

The geometric reduction causes total Bitcoin issuance to approach approximately 21 million BTC.

The final satoshis are expected to be issued around the year 2140 under the current protocol schedule.

After the block subsidy eventually reaches zero, miners are intended to rely on transaction fees for revenue.

---

## 13. Mining Pools

Solo mining has highly variable results because finding a block is probabilistic.

A miner could work for a long time without finding a block.

Mining pools combine the work of many miners:

```text
Miner A --\
Miner B ----> Mining Pool -> attempts to find blocks
Miner C --/
```

The pool measures contributed work and distributes rewards according to its payout system.

Therefore, when an individual ASIC participates in a pool, the successful block's coinbase payment is handled according to the pool's configuration; individual miners normally receive pool payouts rather than personally receiving the entire block reward.

---

## 14. What Is a Bitcoin Wallet?

A Bitcoin wallet does not literally contain Bitcoin coins.

A wallet primarily manages cryptographic key material and helps construct and sign transactions.

```text
Wallet
  |
  +-- Recovery information
  +-- Private keys
  +-- Public keys
  +-- Addresses
  +-- Transaction creation
  +-- Digital signatures
  +-- Balance/UTXO tracking
```

The actual ledger state exists in the Bitcoin network/blockchain data.

---

## 15. Creating a Bitcoin Wallet

Creating a wallet does not require registering an account with a central Bitcoin server.

Conceptually:

```text
Secure randomness
       |
       v
Seed / private key material
       |
       v
Public keys
       |
       v
Bitcoin addresses
```

A wallet can even be generated offline.

```text
Offline computer/device
       |
       v
Generate secure randomness
       |
       v
Create wallet key material
       |
       v
Derive Bitcoin addresses
```

No central server has to approve the new address.

---

## 16. Private Keys

A private key is secret cryptographic information that enables authorization of Bitcoin spending under the relevant script conditions.

Bitcoin uses elliptic-curve cryptography based on **secp256k1**.

Conceptually:

```text
Private Key
     |
     | elliptic-curve operation
     v
Public Key
```

A simplified mathematical representation is:

```text
PublicKey = PrivateKey x G
```

where `G` is a predefined generator point on the secp256k1 curve.

It is computationally practical to derive the public key from the private key, but computationally infeasible to recover the private key from the public key with known classical methods.

---

## 17. Bitcoin Addresses

Bitcoin addresses are derived from public-key/script information using cryptographic transformations and encoding rules appropriate to the address type.

You may encounter addresses beginning with:

```text
1...
3...
bc1q...
bc1p...
```

These represent different Bitcoin address/script formats.

The important security distinction is:

```text
Private key -> SECRET
Public key  -> can be public when revealed/required
Address     -> designed to be shared
```

You give someone your Bitcoin receiving address so they can pay you. You do **not** give them your private key.

---

## 18. UTXO — Where Is the Bitcoin Really Stored?

Bitcoin does not use a simple account balance field like a traditional bank database.

It uses the **UTXO model**.

UTXO means:

> Unspent Transaction Output

Suppose your wallet controls:

```text
UTXO A = 0.4 BTC
UTXO B = 0.6 BTC
UTXO C = 0.5 BTC
```

Your wallet can display:

```text
0.4 + 0.6 + 0.5 = 1.5 BTC
```

So the wallet's displayed balance is calculated from spendable outputs it can identify/control.

---

## 19. Spending a UTXO

Suppose you have:

```text
UTXO = 0.5 BTC
```

and want to send Bob:

```text
0.3 BTC
```

The transaction may look conceptually like:

```text
INPUT
  0.5 BTC previous UTXO

OUTPUTS
  Bob          0.3000 BTC
  Your change  0.1999 BTC

Difference     0.0001 BTC transaction fee
```

The old UTXO is consumed entirely. New UTXOs are created by the transaction outputs.

The fee is:

```text
Total inputs - Total outputs = transaction fee
```

---

## 20. Digital Signatures

When spending Bitcoin, the wallet does **not** send the private key to the network.

Instead:

```text
Transaction data
       +
Private key
       |
       v
Digital signature
```

The network receives the transaction and the information needed to verify its authorization.

```text
Sent to network:
  Transaction       YES
  Signature         YES
  Required public/script information YES
  Private key       NO
  Recovery seed     NO
```

Nodes can verify the signature/authorization without learning the private key.

This principle is fundamental:

> You prove control of the required private key by producing a valid signature, not by revealing the private key.

---

## 21. Recovery Seeds and HD Wallets

Modern wallets commonly use hierarchical deterministic (HD) key derivation.

Instead of backing up every individual private key, the wallet can derive many keys from common root secret material.

Conceptually:

```text
Recovery Seed
      |
      v
Master key material
      |
      +-- Private Key 1 -> Address 1
      +-- Private Key 2 -> Address 2
      +-- Private Key 3 -> Address 3
      +-- Private Key 4 -> Address 4
      +-- ...
```

This is why one wallet can manage many receiving addresses.

The wallet can calculate its balance across many relevant UTXOs associated with those derived keys.

---

## 22. Recovering a Lost Wallet

Suppose your computer or hardware wallet is lost or destroyed, but you still have the correct recovery information.

```text
Old wallet/device
       X lost

Recovery seed
       |
       v
New compatible wallet
       |
       v
Derive same key hierarchy
       |
       v
Recover corresponding addresses
       |
       v
Scan/synchronize blockchain information
       |
       v
Find relevant UTXOs
```

Your Bitcoin was not physically stored inside the lost laptop. The ledger still contains the relevant outputs.

The recovery seed allows compatible wallet software to recreate the key material needed to control them.

### Important recovery details

The seed words alone are not necessarily sufficient for every possible wallet configuration. Recovery can also depend on items such as:

- wallet standard
- derivation path
- address type
- optional seed passphrase, if deliberately configured

Therefore, it is useful to know which wallet/software/standard was used when creating important wallets.

---

## 23. Seed vs Private Key

This distinction is extremely important.

A single private key normally controls only the outputs associated with that key/script relationship.

A recovery seed may derive many private keys.

Example:

```text
Recovery Seed
     |
     +-- Key A -> 0.5 BTC
     +-- Key B -> 1.0 BTC
     +-- Key C -> 0.2 BTC
```

If an attacker steals only Key B, the attacker may be able to spend the BTC controlled by Key B, but not necessarily the funds controlled only by Keys A and C.

If the attacker steals the recovery seed, the attacker may be able to derive all three keys.

```text
One private key stolen
       -> limited to that key's authority

Seed stolen
       -> potentially entire HD wallet
```

For this reason, the recovery seed is often the most important wallet secret to protect.

---

## 24. Wallet Password vs Seed/Private Key

A wallet password and a Bitcoin private key are different things.

A wallet password commonly protects the wallet application's local encrypted data.

```text
Wallet password
       |
       v
Protect local wallet file/device access
```

The private key provides cryptographic spending authority.

```text
Private key
       |
       v
Create valid spending signature
```

If an attacker obtains the recovery seed, changing the password on one copy of your wallet generally does not invalidate the underlying Bitcoin keys.

The attacker can potentially restore the seed elsewhere.

---

## 25. What Happens If a Wallet Is Stolen?

The answer depends on what was stolen.

### Case A — Phone/computer stolen

If the wallet data is strongly encrypted and protected by a strong password, the attacker may still need to defeat that protection.

However, malware or an already-unlocked wallet can present additional risks.

### Case B — One private key stolen

```text
Private key stolen
       |
       v
Attacker can sign for outputs controlled by that key
       |
       v
Bitcoin can potentially be stolen
```

The attacker does not need the recovery seed if the stolen private key itself provides the required spending authority.

### Case C — Recovery seed stolen

This is usually much more serious:

```text
Seed stolen
    |
    v
Attacker restores compatible wallet
    |
    v
Derives private keys
    |
    v
Can potentially spend wallet funds
```

If a seed/private key is believed to be compromised, the usual defensive response is to create a completely new wallet with new secret material and transfer funds to the new wallet as safely and promptly as possible.

---

## 26. Paying an Online Shop With Bitcoin

You should **never provide your recovery seed or private key to an online shop**.

A normal payment looks like this:

```text
Online Shop
    |
    | provides Bitcoin payment address/request
    v
Your Wallet
    |
    | constructs transaction
    | signs locally
    v
Signed transaction
    |
    v
Bitcoin Network
    |
    v
Merchant detects/receives payment
```

The merchant may give you an address such as:

```text
bc1q...
```

You enter or scan that address in your wallet and approve the transaction.

The private key remains secret.

### Never give a merchant

```text
Recovery seed      NO
Private key        NO
Hardware wallet PIN NO
Wallet password    NO
```

A website asking for your recovery seed or private key in order to receive an ordinary Bitcoin payment should be treated as extremely suspicious.

---

## 27. Does Wallet Software Know the Private Key?

It depends on the wallet architecture.

### Software wallet

A conventional software wallet generally has access to the key material necessary to sign transactions.

```text
Computer
   |
   +-- Wallet application
          |
          +-- encrypted key/seed data
          +-- derives/uses private keys
          +-- signs transactions
```

This creates risk if the computer is compromised by malware.

### Hardware wallet

A hardware wallet attempts to isolate private keys from the general-purpose computer.

```text
Computer
   |
   | unsigned transaction
   v
Hardware Wallet
   |
   +-- private key remains inside
   +-- user verifies/approves
   +-- transaction signed internally
   |
   v
Signature / signed transaction
   |
   v
Computer
   |
   v
Bitcoin Network
```

This is conceptually similar to an HSM, smart card, or cryptographic USB token.

The host computer does not need direct access to the private key.

---

## 28. Hardware Wallets Do Not Make the Seed Unimportant

Even if the hardware device securely protects its internal keys, the recovery seed can recreate them.

```text
Hardware Wallet
      |
      +-- Private keys protected internally

Recovery Seed
      |
      v
Can regenerate wallet keys elsewhere
```

Therefore:

```text
Hardware wallet stolen
    -> device security/PIN may protect keys

Recovery seed stolen
    -> attacker may restore wallet elsewhere
```

The seed backup must therefore be protected extremely carefully.

---

## 29. Hot Wallet vs Cold Wallet

### Hot wallet

A hot wallet is connected to an Internet-connected environment.

```text
Phone / PC
    |
    +-- Internet
    +-- Wallet
```

Advantages:

- convenient
- fast payments
- easy everyday use

Risks:

- malware
- compromised operating system
- phishing
- malicious wallet software

### Cold storage

Cold storage keeps signing secrets away from ordinary Internet-connected systems.

Hardware wallets are a common approach.

```text
Internet-connected computer
          |
          | transaction information
          v
Isolated signing device
          |
          | signature
          v
Internet-connected computer
```

For significant amounts, stronger key isolation is generally preferable to keeping all signing secrets directly in an everyday computer environment.

---

## 30. Bitcoin Wallet Security Rules

Important rules to remember:

1. **Never share your recovery seed.**
2. **Never share your private key.**
3. A legitimate Bitcoin payment does not require giving the merchant your seed/private key.
4. Treat anyone asking for your seed as potentially malicious.
5. Do not store real seed words in email, chat, screenshots, ordinary cloud notes, or unsecured text files.
6. Verify wallet software carefully before entering real recovery information.
7. For significant funds, consider hardware-based signing/key isolation.
8. Keep reliable backups of recovery information.
9. If a seed/private key is exposed, treat it as compromised rather than merely changing an application password.
10. Test recovery procedures carefully before relying on a wallet for significant value.

---

## 31. Wallet, Node, and Miner — Final Comparison

| Component | Main purpose | Holds/uses private keys? | Validates blockchain? | Can receive protocol mining reward? |
|---|---|---:|---:|---:|
| Wallet | Manage keys and transactions | Yes, or delegates signing to hardware | Not necessarily | No |
| Full node | Independently verify Bitcoin rules | Not required | Yes | No |
| Miner | Perform Proof of Work and produce candidate blocks | Has payout setup/keys as needed | Usually relies on validation infrastructure | Yes, if successful/pool payout |
| Hardware wallet | Isolate signing keys | Yes, internally | No | No |

A single person or organization can operate several of these components together.

---

## 32. Complete Bitcoin Transaction Flow

Putting everything together:

```text
                 RECOVERY SEED
                       |
                       v
                 PRIVATE KEYS
                       |
                       v
                 PUBLIC KEYS
                       |
                       v
                  ADDRESSES
                       |
                       v
                 Receive BTC
                       |
                       v
                    UTXOs
                       |
                       v
Wallet selects UTXOs and creates transaction
                       |
                       v
       Private key signs transaction
                       |
                       v
             Signed transaction
                       |
                       v
                Bitcoin nodes
                       |
                 validate/relay
                       |
                       v
                    Miners
                       |
             build candidate block
                       |
              perform Proof of Work
                       |
                       v
             Valid block discovered
                       |
                       v
               Nodes validate block
                       |
                       v
             Blockchain progresses
                       |
                       v
             Additional confirmations
```

---

## 33. The Most Important Concepts to Remember

### Bitcoin is not an account system

There is no central Bitcoin company maintaining an account under your name.

### Your wallet does not literally store coins

The Bitcoin ledger contains UTXOs. Your wallet manages the cryptographic secrets that allow you to satisfy the spending conditions for relevant UTXOs.

### Private keys authorize spending

```text
Private key -> sign
Public information -> verify
```

The private key should never be revealed during a normal payment.

### The seed is a master recovery secret

```text
Seed
 |
 +-- Key 1
 +-- Key 2
 +-- Key 3
 +-- ...
```

Losing the device is often recoverable if the seed and required recovery information remain safe. Losing the seed to an attacker can be catastrophic.

### Nodes verify; miners work

```text
Miner -> proposes a Proof-of-Work block
Node  -> independently decides whether it follows the rules
```

### Proof of Work is expensive to produce but easy to verify

That asymmetry is central to Bitcoin's security model.

---

## 34. Final Mental Model

A concise way to think about Bitcoin is:

```text
Bitcoin
  |
  +-- Blockchain
  |     "What happened?"
  |
  +-- UTXO set
  |     "What can currently be spent?"
  |
  +-- Wallet
  |     "Which spending authority do I control?"
  |
  +-- Private key
  |     "How do I authorize spending?"
  |
  +-- Node
  |     "Does this follow Bitcoin's rules?"
  |
  +-- Miner
  |     "Can I produce the next valid Proof-of-Work block?"
  |
  +-- Proof of Work
        "How do we make competing history expensive?"
```

These components work together without requiring a single central authority to operate the Bitcoin ledger.

---

## 35. Suggested Next Topics

Once these fundamentals are comfortable, useful next topics are:

1. BIP-39 recovery phrases and entropy
2. BIP-32 hierarchical deterministic wallets
3. BIP-44/49/84/86 derivation paths
4. Legacy, SegWit, Native SegWit, and Taproot addresses
5. ECDSA vs Schnorr signatures
6. Bitcoin Script
7. Transaction serialization
8. Merkle trees and Merkle roots
9. Mempool and fee-rate selection
10. Lightning Network
11. Multisignature wallets
12. Hardware-wallet architecture
13. Running Bitcoin Core
14. Bitcoin testnet/signet/regtest for development
15. Building a small educational wallet step by step


---

## 23. Practical Use Cases for Blockchain Technology

Blockchain is useful beyond Bitcoin, but it is **not automatically a better database**. Its strongest use case is when multiple independent parties need to maintain or verify a shared record and they do not want one party to have unilateral control over the history.

A useful rule of thumb is:

```text
One trusted organization controls everything
                |
                v
      Normal database is usually better

Multiple independent organizations
need a shared record
                |
                v
Can everyone trust one central authority?
          |                 |
         Yes                No
          |                 |
          v                 v
 Database may be       Blockchain or another
 sufficient            distributed-ledger design
                       may be worth evaluating
```

Even when several parties are involved, blockchain is only one option. Digital signatures, PKI, append-only logs, transparency logs, replicated databases, and conventional distributed systems may solve the problem more simply.

### 23.1 Cryptocurrency and digital money

Bitcoin is the clearest blockchain use case. The blockchain records ownership transfers without requiring a central bank database to be the single authoritative ledger.

```text
Alice
  |
  | signs transaction
  v
Bitcoin network
  |
  +-- Nodes verify
  +-- Miners produce blocks
  +-- Blockchain records history
  |
  v
Bob receives spendable Bitcoin
```

The combination of digital signatures, consensus rules, Proof of Work, and the blockchain lets independent participants agree on transaction history.

### 23.2 Cross-border value transfer and settlement

Blockchain-based assets can be transferred between participants in different locations without requiring every transfer to be recorded by one central ledger operator.

Possible applications include:

- international value transfer;
- settlement between organizations;
- stablecoin transfers;
- programmable payment systems.

However, legal requirements, currency conversion, custody, privacy, transaction fees, and regulatory obligations still exist. Blockchain does not remove these real-world considerations.

### 23.3 Document integrity and timestamp evidence

A useful organizational application is recording a document's cryptographic hash rather than storing the confidential document itself on a public blockchain.

```text
important_report.pdf
        |
        v
      SHA-256
        |
        v
Document hash
        |
        v
Blockchain record
```

Later, the document can be hashed again:

```text
Received document
        |
        v
      SHA-256
        |
        v
Current hash
        |
        +------ compare ------+
                              |
                       Recorded hash
                              |
                 +------------+------------+
                 |                         |
               Match                    Different
                 |                         |
                 v                         v
          Integrity verified       Document changed
```

If even a small part of the document changes, its cryptographic hash changes dramatically.

Important limitation: recording a hash proves that a particular data value was committed at a certain point in the ledger's history. It does **not** prove that the original document contained truthful information.

For confidential documents, generally avoid putting the complete document or sensitive personal information directly on a public blockchain.

### 23.4 Supply-chain tracking

Supply chains commonly involve organizations that maintain separate systems:

```text
Manufacturer
     |
     v
Shipping Company
     |
     v
Importer
     |
     v
Warehouse
     |
     v
Retailer
```

Traditionally, each organization may have its own database. This can make reconciliation difficult when records disagree.

A shared ledger could record important events such as:

```text
Batch #A100 created       -> Manufacturer
Batch #A100 shipped       -> Manufacturer
Batch #A100 received      -> Shipping company
Batch #A100 imported      -> Importer
Batch #A100 warehoused    -> Warehouse
Batch #A100 delivered     -> Retailer
```

Organizations can digitally sign the events for which they are responsible.

Blockchain can therefore help provide a shared, tamper-evident history across organizational boundaries.

But blockchain cannot automatically prove that a physical event really happened. If an employee enters false information such as "1,000 units received," the blockchain can preserve that statement faithfully while the statement itself is still false. Reliable scanners, sensors, audits, organizational controls, and trusted data-entry processes remain necessary.

### 23.5 Certificates and verifiable credentials

A blockchain or similar tamper-evident registry can help verify issued credentials such as:

- educational diplomas;
- professional qualifications;
- training certificates;
- product certificates;
- inspection results;
- compliance certificates.

For example:

```text
University diploma
       |
       v
Document / credential hash
       |
       v
Issuer records commitment
       |
       v
Verification later
```

An employer can later verify whether a presented credential corresponds to the issuer's recorded information.

Again, privacy must be considered carefully. Personally identifiable or confidential information should not casually be written permanently to a public blockchain.

### 23.6 Smart contracts

Some blockchain platforms support programs commonly called **smart contracts**.

A simplified escrow arrangement might be:

```text
Buyer
  |
  | funds
  v
Smart Contract
  |
  +-- Conditions satisfied --> release funds to seller
  |
  +-- Refund condition ------> return funds to buyer
```

Smart contracts can support applications such as:

- decentralized exchanges;
- lending protocols;
- escrow mechanisms;
- token issuance;
- automated settlement;
- programmable ownership rules.

The word "contract" can be misleading. A smart contract is primarily software executed according to blockchain rules; whether it constitutes a legally enforceable contract depends on the surrounding legal arrangement and jurisdiction.

#### The oracle problem

A blockchain can directly understand information already available on-chain, but it cannot independently observe the physical world.

Suppose a smart contract needs to know:

```text
"Did the package arrive?"
```

The blockchain itself cannot see the package. An external system must provide that information.

```text
Physical world
      |
      v
Sensor / company / data provider
      |
      v
Oracle
      |
      v
Smart contract
```

The reliability of the smart contract can therefore depend on the reliability of its oracle.

### 23.7 Asset tokenization

A blockchain token can represent rights associated with an asset.

Potential examples include:

- securities;
- bonds;
- fund interests;
- invoices;
- real-estate interests;
- tickets;
- digital collectibles.

Conceptually:

```text
Real-world asset or right
          |
          v
Legal / contractual framework
          |
          v
Blockchain token
```

There is an important difference between native blockchain assets and tokenized real-world assets.

For Bitcoin:

```text
Bitcoin consensus rules
        |
        v
Define ownership/control of BTC
```

For a token representing a building:

```text
Blockchain
   |
   | says
   v
"Address X owns Token #123"

        +

Legal system / contract
   |
   | must establish
   v
"Token #123 represents specific
rights in this building"
```

Blockchain alone cannot force governments, courts, companies, or physical custodians to recognize the token's real-world meaning.

### 23.8 Shared organizational audit trails

Blockchain concepts can be useful when several independent organizations need a common audit history.

For example:

```text
Parent Company
      |
      +-- Subsidiary A
      +-- Subsidiary B
      +-- Subsidiary C
```

A shared ledger might record:

```text
10:01  Company A created Document X
10:05  Company A approved Document X
10:20  Parent received Document X
11:15  Company B acknowledged Document X
```

An event could contain information such as:

```text
Event ID
Timestamp
Organization ID
Document hash
Previous-record reference
Digital signature
```

This can make unauthorized historical modification easier to detect.

However, if the parent company is already universally trusted to operate the authoritative audit system, a conventional append-only database combined with digital signatures, access controls, backups, and independent audit logs may be simpler and more efficient than introducing a blockchain.

### 23.9 NFTs and unique digital tokens

A blockchain can maintain ownership records for unique or semi-unique tokens. These are commonly called NFTs (non-fungible tokens).

Possible applications include:

- digital collectibles;
- membership tokens;
- event tickets;
- game assets;
- certificates of authenticity.

An NFT normally does not mean that the blockchain stores a complete large media file. Often the token contains or references metadata that points to other data.

Ownership of a token also does not automatically grant copyright or every legal right associated with an image, song, document, or physical object. Those rights depend on the applicable license or legal agreement.

### 23.10 Decentralized finance (DeFi)

Smart-contract blockchains can implement financial applications without a conventional application server controlling every transaction.

Examples include:

```text
Token exchange
Lending
Borrowing
Collateral management
Stablecoins
Liquidity pools
```

Users interact with smart contracts using cryptographic keys.

These systems introduce significant risks, including smart-contract bugs, oracle failures, stolen keys, governance problems, liquidity risks, and economic design failures. "Decentralized" does not mean "risk free."

### 23.11 When blockchain is probably unnecessary

Consider a normal internal business application:

```text
Company
   |
   v
Application Server
   |
   v
MariaDB
```

If the same organization controls the application, database, users, servers, backups, and permissions, introducing blockchain may simply add complexity:

```text
Application
    |
    v
Blockchain layer
    |
    +-- Nodes
    +-- Consensus
    +-- Key management
    +-- Smart contracts
    +-- Additional operations
```

without solving an actual trust problem.

A traditional architecture can often provide strong security using:

```text
MariaDB
  +
Access control
  +
TLS
  +
PKI / digital signatures
  +
Hashing
  +
Append-only audit logs
  +
Backups
  +
Independent monitoring
```

### 23.12 Blockchain is not simply "a secure database"

It is better to think of blockchain as a mechanism for maintaining a shared history under a particular trust and consensus model.

```text
Traditional database

Trusted administrator
       |
       v
Central authoritative database
```

versus a decentralized blockchain model:

```text
Participant A ----+
Participant B ----+
Participant C ----+--> Shared protocol / ledger
Participant D ----+
Participant E ----+
```

The participants follow common validation and consensus rules rather than granting one database administrator unilateral authority over the shared history.

### 23.13 Questions to ask before choosing blockchain

Before adding blockchain to a project, ask:

1. Are multiple independent organizations involved?
2. Do those organizations need to write to or verify the same shared record?
3. Is there a trusted central authority that could operate the database more simply?
4. Is resistance to unilateral historical modification actually required?
5. What information must remain private?
6. Who controls the cryptographic keys?
7. How will lost or compromised keys be handled?
8. How does external real-world information enter the system?
9. Are smart contracts really required?
10. What happens when incorrect data is permanently recorded?
11. What are the performance and storage requirements?
12. What legal or regulatory requirements apply?
13. Would digital signatures plus a conventional append-only database solve the same problem?

### 23.14 Practical decision summary

```text
                  Need shared data?
                       |
             +---------+---------+
             |                   |
            No                  Yes
             |                   |
             v                   v
      Normal database      Multiple independent
      usually enough?      parties involved?
                                  |
                         +--------+--------+
                         |                 |
                        No                Yes
                         |                 |
                         v                 v
                  Normal database    Trusted central
                  usually better     authority acceptable?
                                           |
                                  +--------+--------+
                                  |                 |
                                 Yes                No
                                  |                 |
                                  v                 v
                          Central database      Evaluate blockchain,
                          + signatures /        distributed ledger,
                          audit logs            or other consensus design
```

### 23.15 Key takeaway

Blockchain should not be selected simply because it is modern or because immutability sounds attractive.

The strongest reason to consider it is:

> Multiple parties that do not fully trust one another need to share and verify a common history without giving one participant unilateral control over that history.

Bitcoin is the most important example because it combines a blockchain with digital signatures, decentralized validation, Proof of Work, and economic incentives to maintain a monetary ledger without a central bank operating the transaction database.
