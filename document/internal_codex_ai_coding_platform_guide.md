# Building an Internal Codex-Like AI Coding Platform

## 1. Purpose

This document consolidates the architecture and implementation guidance
for building an internal, offline AI platform and, in particular, a
**Codex-like coding agent** for a large company.

The main goal is not to train a foundation model from scratch. Instead,
the recommended approach is to:

1.  Run an approved downloadable/open-weight AI model on
    company-controlled GPU infrastructure.
2.  Build a secure coding-agent platform around that model.
3.  Give the agent controlled tools for repository inspection, file
    editing, Git, builds, linting, and tests.
4.  Execute agent work inside isolated sandboxes.
5.  Use RAG to provide company coding standards, architecture documents,
    and repository knowledge.
6.  Require human review before changes reach production.

------------------------------------------------------------------------

## 2. GPU --- Detailed Guide

### 2.1 What Is a GPU?

GPU means **Graphics Processing Unit**.

A CPU and GPU are both processors, but they are optimized for different
types of work.

``` text
CPU
 |
 +-- relatively few powerful general-purpose cores
 +-- complex control flow
 +-- operating system
 +-- application logic
 +-- database work
 +-- sequential and branch-heavy processing

GPU
 |
 +-- very large amount of parallel arithmetic capability
 +-- graphics
 +-- matrix operations
 +-- neural networks
 +-- image/video processing
 +-- scientific computing
```

A useful analogy is:

``` text
CPU = a small team of highly capable senior workers

GPU = a very large workforce optimized to perform
      many similar calculations at the same time
```

This does not mean that a GPU replaces the CPU. Modern AI systems
normally use both.

``` text
Application
    |
    v
   CPU
    |
    +-- control/orchestration
    +-- networking
    +-- file/database operations
    +-- tokenization
    |
    +------ parallel mathematical workload ------> GPU
```

------------------------------------------------------------------------

### 2.2 Why GPUs Were Originally Created

Computer graphics requires enormous numbers of similar calculations.

For a 1920 × 1080 display:

``` text
1920 × 1080 = 2,073,600 pixels
```

At 60 frames per second:

``` text
2,073,600 × 60
=
124,416,000 pixel positions per second
```

And rendering involves much more than simply writing pixels:

-   geometry transformation
-   lighting
-   textures
-   shading
-   shadows
-   rasterization
-   post-processing
-   frame composition

Because many of these operations can be calculated independently,
parallel hardware is extremely effective.

------------------------------------------------------------------------

### 2.3 Simplified GPU Architecture

A GPU can be viewed conceptually as:

``` text
+--------------------------------------------------+
|                     GPU                          |
|                                                  |
|  +----------+ +----------+ +----------+          |
|  | Compute  | | Compute  | | Compute  |   ...    |
|  | Unit     | | Unit     | | Unit     |          |
|  +----------+ +----------+ +----------+          |
|                                                  |
|          Cache / Memory Infrastructure           |
|                                                  |
|  +--------------------------------------------+  |
|  |                    VRAM                    |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

The exact terminology depends on the vendor.

For example, NVIDIA documentation commonly refers to:

-   Streaming Multiprocessors
-   CUDA cores
-   Tensor Cores
-   RT Cores on graphics-oriented products

AMD uses different terminology such as Compute Units.

The important concept is that a GPU contains a large amount of hardware
for parallel arithmetic.

------------------------------------------------------------------------

### 2.4 GPU Threads and Parallelism

GPU programs can execute very large numbers of lightweight threads.

Conceptually:

``` text
Data:
A1 A2 A3 A4 A5 A6 A7 A8 ...

CPU approach:
Core 1 -> A1 -> A2 -> A3 ...
Core 2 -> A4 -> A5 -> A6 ...

GPU approach:
Thread 1 -> A1
Thread 2 -> A2
Thread 3 -> A3
Thread 4 -> A4
Thread 5 -> A5
...
```

This works especially well when the same or similar mathematical
operation must be applied to large arrays, vectors, matrices, pixels, or
tensors.

GPUs are less ideal for workloads containing large amounts of
unpredictable branching and sequential dependencies.

------------------------------------------------------------------------

### 2.5 CPU vs GPU

  Area                         CPU            GPU
  ---------------------------- -------------- ---------------------
  General application logic    Excellent      Poor fit
  Operating system             Excellent      Not primary purpose
  PHP application              Excellent      Usually unnecessary
  MariaDB query execution      CPU-oriented   Usually unnecessary
  Complex branching            Excellent      Less efficient
  Matrix multiplication        Moderate       Excellent
  Neural networks              Possible       Excellent
  Image processing             Possible       Excellent
  3D graphics                  Limited        Excellent
  Large parallel simulations   Moderate       Excellent

For a traditional web application:

``` text
Browser
   |
   v
Apache/Nginx
   |
   v
PHP
   |
   v
MariaDB
```

the normal workload remains CPU-oriented.

When AI is added:

``` text
                    +--> PHP / MariaDB
                    |
Browser -> Web App -+
                    |
                    +--> AI Service -> GPU
```

------------------------------------------------------------------------

### 2.6 Integrated and Discrete GPUs

An **integrated GPU** is generally part of the CPU/package and commonly
shares system memory.

``` text
CPU + Integrated GPU
        |
        v
   Shared System RAM
```

A **discrete GPU** is a separate device with dedicated GPU memory.

``` text
CPU / System RAM
       |
      PCIe
       |
       v
Discrete GPU
       |
       v
Dedicated VRAM
```

For serious local LLM inference, discrete GPUs with substantial
dedicated VRAM are generally much more practical.

------------------------------------------------------------------------

### 2.7 What Is VRAM?

VRAM means **Video Random Access Memory**.

Despite the historical graphics-oriented name, it is extremely important
for AI.

For graphics, VRAM can contain:

-   textures
-   frame buffers
-   geometry
-   shader data

For AI, VRAM can contain:

``` text
VRAM
 |
 +-- model weights
 +-- KV cache
 +-- activations
 +-- temporary tensors
 +-- inference-runtime buffers
```

VRAM capacity is often the first hardware limitation encountered when
running a large language model.

------------------------------------------------------------------------

### 2.8 Model Parameters and Memory

Model sizes are commonly described using parameter counts:

``` text
7B  = 7 billion parameters
14B = 14 billion parameters
32B = 32 billion parameters
70B = 70 billion parameters
```

A rough weight-memory formula is:

``` text
Memory ≈ Parameters × Bits Per Parameter / 8
```

Approximate weight-only sizes:

  Model         FP32   FP16/BF16      INT8      4-bit
  ------- ---------- ----------- --------- ----------
  7B         \~28 GB     \~14 GB    \~7 GB   \~3.5 GB
  14B        \~56 GB     \~28 GB   \~14 GB     \~7 GB
  32B       \~128 GB     \~64 GB   \~32 GB    \~16 GB
  70B       \~280 GB    \~140 GB   \~70 GB    \~35 GB

These figures are simplified. Actual inference requires additional
memory.

For example:

``` text
16 GB VRAM GPU

Model weights      10 GB
Runtime buffers     2 GB
KV cache            3 GB
-------------------------
Total               15 GB
```

A larger context could increase the KV cache:

``` text
Model weights      10 GB
Runtime buffers     2 GB
KV cache            6 GB
-------------------------
Total               18 GB
```

The same model would then no longer fit completely in a 16 GB GPU.

------------------------------------------------------------------------

### 2.9 PCI Express and Data Movement

A discrete GPU communicates with the CPU and system memory through an
interconnect, commonly PCI Express.

``` text
CPU
 |
 v
System RAM
 |
 v
PCI Express
 |
 v
GPU
 |
 v
VRAM
```

Moving data between system RAM and VRAM is much slower than accessing
data already available in the appropriate local memory.

Therefore efficient GPU software tries to avoid unnecessary transfers.

This becomes particularly relevant when using CPU offloading for a model
that does not completely fit into VRAM.

------------------------------------------------------------------------

### 2.10 Memory Bandwidth

VRAM capacity answers:

> How much can fit?

Memory bandwidth answers:

> How quickly can the GPU move data?

Bandwidth is commonly expressed in GB/s or TB/s.

A useful analogy:

``` text
VRAM Capacity = warehouse size

Memory Bandwidth = width/speed of roads between
                   warehouse and workers

Compute Units = workers
```

A large warehouse does not guarantee high performance if the roads are
slow.

Similarly, enormous compute capability can be underutilized if data
cannot be supplied quickly enough.

GPU memory technologies can include:

-   GDDR6
-   GDDR6X
-   GDDR7
-   HBM-family memory on many data-center/HPC accelerators

Memory bus width can also differ, such as:

``` text
128-bit
192-bit
256-bit
384-bit
512-bit
```

Bus width alone should not be used to compare GPUs. Effective memory
speed, architecture, cache, compression, and workload all matter.

------------------------------------------------------------------------

### 2.11 Compute Performance and FLOPS

FLOPS means **Floating-Point Operations Per Second**.

Common scales include:

``` text
GFLOPS = billions of operations/sec
TFLOPS = trillions of operations/sec
PFLOPS = quadrillions of operations/sec
```

But a FLOPS number is meaningless without knowing the numerical
precision.

Examples include:

-   FP64
-   FP32
-   TF32
-   FP16
-   BF16
-   FP8
-   INT8

AI models often use lower precision than traditional scientific
computing because lower precision can reduce memory requirements and
greatly increase throughput.

Therefore:

``` text
100 TFLOPS FP32
```

and

``` text
100 TFLOPS FP16
```

should not automatically be treated as equivalent capabilities.

------------------------------------------------------------------------

### 2.12 Tensor Cores

Modern NVIDIA GPUs may contain specialized **Tensor Cores**.

They are designed to accelerate matrix/tensor operations that are
extremely common in neural networks.

Conceptually:

``` text
Normal GPU arithmetic units
        |
        +--> general parallel calculations

Tensor Cores
        |
        +--> specialized matrix/tensor operations
             used heavily by AI
```

This is one reason that raw general-purpose FLOPS is not enough when
comparing GPUs for AI.

------------------------------------------------------------------------

### 2.13 CUDA and the Software Ecosystem

CUDA is NVIDIA's GPU-computing platform and programming ecosystem.

A simplified software stack is:

``` text
AI Application
      |
      v
AI Framework / Inference Engine
      |
      v
GPU Libraries / Runtime
      |
      v
CUDA
      |
      v
NVIDIA Driver
      |
      v
NVIDIA GPU
```

The software ecosystem is one reason NVIDIA hardware is widely used for
AI.

For an offline company, driver/runtime/model compatibility should be
tested and approved before deployment.

------------------------------------------------------------------------

### 2.14 Why AI Works Well on GPUs

Neural networks perform enormous numbers of matrix operations.

A simplified neural-network operation is:

``` text
Y = W × X + B
```

where:

-   `X` is input data
-   `W` contains learned weights
-   `B` is bias
-   `Y` is output

Large matrices contain many calculations that can be performed in
parallel.

That maps naturally to GPU hardware.

------------------------------------------------------------------------

### 2.15 How an LLM Executes on a GPU

A simplified language-model request looks like:

``` text
"What is MariaDB?"
        |
        v
Tokenizer
        |
        v
Token IDs
        |
        v
Embeddings
        |
        v
Transformer Layer 1
        |
        v
Transformer Layer 2
        |
       ...
        |
        v
Final Layer
        |
        v
Token Scores / Probabilities
        |
        v
Choose Next Token
        |
        v
Repeat
```

The GPU does not understand the sentence as a human does.

It processes numerical representations:

``` text
Text
  |
Tokens
  |
Vectors
  |
Matrices
  |
GPU arithmetic
```

------------------------------------------------------------------------

### 2.16 Transformer Attention

Transformer models use attention.

A simplified representation is:

``` text
Q = X × Wq
K = X × Wk
V = X × Wv
```

Then conceptually:

``` text
Attention(Q,K,V)
    =
softmax(QK^T / sqrt(d)) V
```

These operations contain large matrix multiplications, making GPUs
extremely useful.

Transformer layers also contain feed-forward/MLP networks that again
involve large matrix operations.

------------------------------------------------------------------------

### 2.17 Autoregressive Generation

Most conversational LLMs generate output one token at a time.

``` text
Prompt
  |
  v
Predict token 1
  |
  v
Prompt + token 1
  |
  v
Predict token 2
  |
  v
Prompt + token 1 + token 2
  |
  v
Predict token 3
```

This is called autoregressive generation.

Because generation is sequential at the token level, inference
optimization involves more than simply adding arithmetic cores.

------------------------------------------------------------------------

### 2.18 Prefill and Decode

LLM inference can be thought of in two important phases.

#### Prefill

The model processes the input prompt/context.

``` text
Large prompt
    |
    v
Process prompt tokens
    |
    v
Build attention state
```

#### Decode

The model generates new tokens sequentially.

``` text
Generate token
     |
     v
Update state
     |
     v
Generate next token
```

These phases can stress hardware differently.

------------------------------------------------------------------------

### 2.19 KV Cache

Transformers use a **KV cache** to preserve attention information from
previous tokens.

Without it, the model would repeatedly recalculate much more of the
previous context.

``` text
Conversation
    |
    v
Transformer
    |
    +--> Key state
    +--> Value state
             |
             v
          KV Cache
```

The benefit is faster token generation.

The cost is memory.

Longer context generally means larger KV-cache requirements.

This is why:

``` text
longer conversations
        |
        v
larger KV cache
        |
        v
more VRAM per active request
        |
        v
lower possible concurrency
```

------------------------------------------------------------------------

### 2.20 Context Length and GPU Capacity

A model may support a very large theoretical context window, but using
that maximum context for every request can be expensive.

For an enterprise coding agent, avoid automatically sending:

``` text
entire repository
+
all company documents
+
full chat history
```

Instead use:

``` text
Task
 |
 v
Repository Search
 |
 v
Relevant Files
 |
 v
Relevant Documentation
 |
 v
Compact Context
 |
 v
LLM
```

This improves:

-   latency
-   VRAM efficiency
-   concurrency
-   relevance
-   cost

------------------------------------------------------------------------

### 2.21 Batching

A GPU can process work for multiple requests together.

Conceptually:

``` text
User A ----\
User B -----\
User C ------+--> Batch --> GPU
User D -----/
```

Batching improves hardware utilization.

Modern inference servers may use continuous batching, allowing new
requests to enter the serving pipeline dynamically.

This is one reason one GPU can serve multiple employees.

------------------------------------------------------------------------

### 2.22 One User Does Not Equal One GPU

For an enterprise AI service:

``` text
WRONG:

1 employee = 1 GPU
```

Instead:

``` text
Employees
    |
    v
AI Gateway
    |
    v
Inference Scheduler
    |
    v
Shared GPU Capacity
```

The real sizing inputs are:

-   model
-   quantization
-   average prompt length
-   average output length
-   context size
-   peak concurrent requests
-   required time to first token
-   required tokens/sec
-   batching efficiency

------------------------------------------------------------------------

### 2.23 Training vs Inference

These are very different workloads.

#### Inference

``` text
Prompt
   |
Trained Model
   |
Output
```

Memory includes approximately:

-   weights
-   KV cache
-   activations
-   temporary/runtime buffers

#### Training

``` text
Training Data
     |
     v
Model
     |
     v
Prediction
     |
     v
Error
     |
     v
Backpropagation
     |
     v
Update Weights
```

Training additionally requires resources for items such as:

-   gradients
-   optimizer states
-   saved activations
-   training buffers

Therefore:

> A GPU that can run a model for inference may be unable to train that
> model.

For an internal coding platform, inference is the first requirement.
Training from scratch is generally unnecessary.

------------------------------------------------------------------------

### 2.24 Fine-Tuning, LoRA, and QLoRA

Full model training is expensive.

Fine-tuning changes an existing model for a narrower purpose.

Techniques such as LoRA and QLoRA can reduce the resources required
compared with full training.

However, for the first company coding agent, prefer:

``` text
Good Base Coding Model
        +
Project Instructions
        +
RAG
        +
Tools
        +
Agent Workflow
```

before investing in fine-tuning.

------------------------------------------------------------------------

### 2.25 Quantization

Quantization reduces the precision used to represent model weights.

Examples include:

``` text
FP16
BF16
INT8
8-bit
Q8
Q5
Q4
4-bit
```

Benefits can include:

-   lower VRAM requirements
-   lower system RAM requirements
-   faster inference on suitable hardware/software
-   ability to run larger models locally

Possible cost:

-   some accuracy/quality loss
-   different performance characteristics
-   compatibility constraints

Example:

``` text
14B model

FP16 weights:
~28 GB

4-bit weights:
~7 GB
```

This is why quantization is so important for consumer/workstation local
AI.

------------------------------------------------------------------------

### 2.26 What Happens When VRAM Is Insufficient?

Several strategies exist.

#### Strategy 1 --- Use a smaller model

``` text
70B -> 32B -> 14B -> 7B
```

#### Strategy 2 --- Quantize more aggressively

``` text
FP16 -> INT8 -> 4-bit
```

#### Strategy 3 --- CPU offload

Part of the model can reside in system RAM.

``` text
System RAM
    |
   PCIe
    |
GPU VRAM
```

This can allow a model to run but usually reduces performance.

#### Strategy 4 --- Use multiple GPUs

Split the workload/model across accelerators.

------------------------------------------------------------------------

### 2.27 Multiple GPUs

Multiple GPUs can increase capacity and/or performance, but:

``` text
2 × 24 GB GPU
```

is not automatically identical to:

``` text
1 × 48 GB GPU
```

because the GPUs must communicate.

Possible approaches include:

-   tensor parallelism
-   pipeline parallelism
-   model sharding
-   independent replicas for throughput

For a model that fits comfortably on one GPU, multiple independent model
replicas can often be simpler for serving many users.

For a model that does not fit on one GPU, model parallelism may be
necessary.

------------------------------------------------------------------------

### 2.28 System RAM Still Matters

Do not focus only on GPU VRAM.

A local AI server also needs substantial system memory for:

-   operating system
-   model loading
-   CPU offload
-   document processing
-   embeddings/indexing
-   application services
-   caches
-   development tools

For example:

``` text
Poorly balanced:

16 GB system RAM
32 GB GPU VRAM
```

may create avoidable limitations.

A more balanced AI workstation/server may use:

``` text
64-128+ GB system RAM
16-32+ GB GPU VRAM
```

depending on workload.

------------------------------------------------------------------------

### 2.29 GPU Uses Beyond AI

GPUs are also used for:

#### Graphics

``` text
3D Model
   |
Vertices
   |
Vertex Processing
   |
Rasterization
   |
Pixel/Fragment Processing
   |
Frame Buffer
   |
Monitor
```

#### Video

Modern GPUs may contain dedicated video encode/decode hardware for
formats such as H.264, H.265/HEVC, and AV1, depending on model.

#### Scientific and Engineering Computing

Examples:

-   simulations
-   computational fluid dynamics
-   weather/climate models
-   molecular simulation
-   computational chemistry
-   image processing
-   signal processing

------------------------------------------------------------------------

### 2.30 When a GPU Is Not Faster

A GPU is not automatically faster for every task.

Examples of poor GPU candidates include:

-   small workloads
-   highly sequential algorithms
-   heavy unpredictable branching
-   ordinary PHP request handling
-   typical MariaDB SQL execution
-   file-management logic
-   operating-system tasks

The overhead of transferring work/data to the GPU can outweigh any
acceleration for small tasks.

------------------------------------------------------------------------

### 2.31 CPU, GPU, and QPU

A useful long-term model is:

  Processor   Primary Role
  ----------- --------------------------------
  CPU         General-purpose computing
  GPU         Massively parallel computing
  QPU         Specialized quantum algorithms

A future system may look like:

``` text
Application
    |
    v
CPU
 |
 +--> normal application logic
 |
 +--> GPU
 |     |
 |     +--> AI
 |     +--> graphics
 |     +--> parallel computation
 |
 +--> QPU
       |
       +--> specialized quantum algorithms
```

Quantum computers are not expected simply to replace ordinary PCs, CPUs,
or GPUs.

------------------------------------------------------------------------

### 2.32 GPU Generations and Product Classes

GPU product names change frequently, so current models and prices should
always be checked at purchasing time.

Conceptually, there are several classes.

#### Consumer GPUs

Examples include gaming/creator families such as:

-   NVIDIA GeForce
-   AMD Radeon
-   Intel Arc

These can be very useful for:

-   AI learning
-   local inference
-   proof-of-concept servers
-   development workstations

#### Professional Workstation GPUs

These generally emphasize:

-   larger VRAM
-   professional drivers
-   ECC support on some products
-   workstation certification
-   enterprise support

#### Data-Center AI Accelerators

These are designed for:

-   large inference clusters
-   model training
-   high-bandwidth interconnects
-   very large memory
-   enterprise/data-center operation

For an internal coding-agent pilot, a workstation/consumer GPU can be
enough. Large data-center accelerators should be considered only after
workload measurement justifies them.

------------------------------------------------------------------------

### 2.33 Practical VRAM Tiers for Local AI

A rough conceptual hierarchy is:

      VRAM Typical Local-AI Position
  -------- -------------------------------------
      8 GB learning and smaller models
     12 GB more flexible small models
     16 GB very useful local-AI starting point
     24 GB serious local inference
     32 GB high-end local AI
    48+ GB professional/server workloads
    80+ GB large enterprise/data-center models

These are not strict model boundaries.

Actual requirements depend on:

-   architecture
-   quantization
-   context
-   KV cache
-   batch size
-   inference engine
-   concurrency

------------------------------------------------------------------------

### 2.34 GPU Selection for the Internal Coding Agent

For this project, evaluate GPUs using:

``` text
1. Can the selected coding model fit?
             |
             v
2. Is the software stack supported?
             |
             v
3. Is memory bandwidth sufficient?
             |
             v
4. Is inference throughput sufficient?
             |
             v
5. Can power/cooling support it?
             |
             v
6. Is the price justified?
```

Do not purchase GPUs before benchmarking candidate coding models.

------------------------------------------------------------------------

### 2.35 Recommended Learning / Deployment Progression

A practical progression is:

``` text
CPU-only experiments
        |
        v
Single GPU workstation/server
        |
        v
16-32+ GB VRAM class
        |
        v
Pilot coding model
        |
        v
Measure real developers
        |
        v
Additional GPU replicas
        |
        v
Multi-GPU / professional GPUs
        |
        v
Enterprise GPU cluster
```

------------------------------------------------------------------------

### 2.36 Key GPU Takeaways

Remember these three questions:

``` text
VRAM Capacity
    |
    +--> How much model/context can fit?

Memory Bandwidth
    |
    +--> How quickly can data move?

Compute Performance
    |
    +--> How quickly can the mathematical work execute?
```

For LLM deployment, all three matter.

And for the internal coding platform:

``` text
GPU
 |
 +--> runs the coding LLM

CPU Sandbox
 |
 +--> Git
 +--> PHP
 +--> PHPUnit
 +--> builds
 +--> repository tools
```

The sandbox usually does **not** need its own GPU.

A centralized GPU inference service can support many developers and many
CPU-based sandboxes.

## 3. Quantization

Quantization represents model parameters with fewer bits.

Approximate weight-only memory:

  Model         FP16      INT8      4-bit
  ------- ---------- --------- ----------
  7B         \~14 GB    \~7 GB   \~3.5 GB
  14B        \~28 GB   \~14 GB     \~7 GB
  32B        \~64 GB   \~32 GB    \~16 GB
  70B       \~140 GB   \~70 GB    \~35 GB

These are only rough weight sizes. Real inference requires additional
memory.

Quantization can make large models practical on smaller hardware, but
may affect accuracy or behavior.

------------------------------------------------------------------------

## 4. Local AI Server Architecture

For an offline company, AI should be a separate internal service rather
than being embedded directly into a PHP application.

``` text
Employees
    |
    v
Internal Business Applications
    |
    v
AI Gateway
    |
    +------------------+
    |                  |
    v                  v
RAG Service        LLM Service
    |                  |
Search/Vector          GPU
    |
Documents
```

The business application can call the AI platform through an internal
HTTP API.

### Example AI server responsibilities

``` text
CPU
- Linux
- API service
- tokenization
- request scheduling
- document processing
- orchestration

System RAM
- model loading
- CPU offload
- datasets
- services
- caches

GPU
- model weights
- KV cache
- transformer inference

NVMe SSD
- models
- indexes
- temporary data
```

------------------------------------------------------------------------

## 5. RAG for Company Knowledge

RAG means **Retrieval-Augmented Generation**.

Instead of training every company document into an LLM:

``` text
Documents
   |
   v
Extract
   |
   v
Chunk
   |
   v
Embedding Model
   |
   v
Search / Vector Index
```

At query time:

``` text
Employee Question
      |
      v
Authentication
      |
      v
Authorization
      |
      v
Hybrid Search
      |
      v
Reranking
      |
      v
Relevant Authorized Chunks
      |
      v
LLM
      |
      v
Answer + Sources
```

### Security rule

The LLM must receive only information that the authenticated employee is
already authorized to access.

Do not retrieve confidential information, send it to the LLM, and
attempt to remove it afterward.

``` text
Authentication
      |
      v
Authorization
      |
      v
Permitted Retrieval
      |
      v
LLM
```

### RAG versus tools

Use RAG for unstructured knowledge:

-   policies
-   manuals
-   reports
-   technical documentation
-   architecture documents
-   knowledge articles

Use controlled tools/APIs for live structured information:

-   current order status
-   customer balance
-   product counts
-   workflow state
-   inventory
-   database-backed business data

``` text
                 AI Assistant
                      |
           +----------+----------+
           |                     |
           v                     v
          RAG                  Tools
           |                     |
           v                     v
Documents/Knowledge        Business APIs
```

------------------------------------------------------------------------

## 6. Scaling for a Large Company

Do not size GPU infrastructure by total employee count.

Use:

``` text
Peak concurrent workload
        x
Prompt/output token sizes
        x
Latency target
        /
Measured GPU throughput
```

A company with 10,000 employees does not require 10,000 GPUs.

A recommended rollout is:

``` text
Pilot
50-100 users
1 serious GPU server
      |
      v
Measure real workload
      |
      v
500-1,000 users
      |
      v
Add GPU capacity
      |
      v
Several thousand users
      |
      v
GPU cluster
      |
      v
Company-wide deployment
```

Measure:

-   requests per hour
-   peak concurrent generation
-   prompt tokens
-   output tokens
-   time to first token
-   tokens per second
-   queue latency
-   GPU utilization
-   VRAM utilization
-   RAG retrieval latency

------------------------------------------------------------------------

# Part II --- Internal Codex-Like Coding Agent

## 7. What a Codex-Like System Really Is

A coding model alone is not a Codex-like platform.

A real coding agent should be able to:

``` text
Developer Task
     |
     v
Understand Request
     |
     v
Inspect Repository
     |
     v
Read Project Instructions
     |
     v
Search Relevant Code
     |
     v
Plan Changes
     |
     v
Edit Files
     |
     v
Run Tests
     |
     +---- Failure ----+
     |                 |
     |                 v
     |            Analyze Error
     |                 |
     |            Modify Code
     |                 |
     +-----------------+
     |
     v
Tests Pass
     |
     v
Git Diff
     |
     v
Human Review
```

This repeated cycle is the **agent loop**:

``` text
PLAN
  |
ACTION
  |
OBSERVATION
  |
REASON
  |
ACTION
  |
OBSERVATION
  |
...
```

------------------------------------------------------------------------

## 8. Recommended Architecture

``` text
                         DEVELOPERS
                             |
                             v
                 +-----------------------+
                 | Coding Agent Web UI   |
                 +-----------------------+
                             |
                             v
                 +-----------------------+
                 | Agent Gateway         |
                 | Auth / RBAC / Audit   |
                 +-----------------------+
                             |
                             v
                 +-----------------------+
                 | Agent Orchestrator    |
                 +-----------------------+
                     |               |
                     v               v
              +------------+   +------------+
              | Coding LLM |   | Code/Docs  |
              | Service    |   | RAG        |
              +------------+   +------------+
                     |
                    GPU

                 Agent Orchestrator
                         |
             +-----------+-----------+
             |           |           |
             v           v           v
         Sandbox A   Sandbox B   Sandbox C
             |           |           |
             v           v           v
          Repo A      Repo B      Repo C
             |
       +-----+-----+-------+
       |           |       |
       v           v       v
      Git         PHP    PHPUnit
```

The GPU can be centralized. Sandboxes generally run ordinary development
tools on CPUs.

------------------------------------------------------------------------

## 9. The Coding Model

You do **not** need to train a foundation model from scratch.

Two practical choices exist:

### Option A --- Download an open-weight model

Examples of model families worth evaluating include:

-   Qwen coding-oriented models
-   Mistral/Devstral coding-agent models
-   DeepSeek coding models
-   OpenAI gpt-oss models
-   other internally approved open-weight coding models

Model availability, exact licenses, model sizes, and commercial-use
terms can change. The company should review the exact model version and
license before deployment.

### Option B --- Use a commercial/proprietary model

A commercial model can provide strong performance without operating the
model infrastructure yourself, but this may conflict with a completely
isolated/no-Internet environment unless an approved on-premises
deployment is available.

For an offline company, self-hosted open-weight models are a natural
starting point.

------------------------------------------------------------------------

## 10. Free Model Does Not Mean Free System

Even when model weights are free, the company still pays for:

-   GPU hardware
-   servers
-   electricity
-   cooling
-   storage
-   network infrastructure
-   administrators
-   monitoring
-   security
-   backups
-   software integration
-   model evaluation and upgrades

The model is only one component.

------------------------------------------------------------------------

## 11. Do Not Fine-Tune First

For Version 1, do not begin by fine-tuning.

Start with:

``` text
Approved Coding Model
        +
Repository Instructions
        +
Company Documentation RAG
        +
Repository Search
        +
Agent Tools
```

Only consider fine-tuning after measuring specific recurring weaknesses
that prompting, RAG, tools, and workflow design cannot solve.

------------------------------------------------------------------------

## 12. Project Agent Instructions

Each repository should contain explicit instructions for the coding
agent.

For example:

``` text
PROJECT_AGENT.md
```

Example:

``` markdown
# Project Instructions

PHP Version:
PHP 7.4

Framework:
Zend Framework 1

Database:
MariaDB

Frontend:
DHTMLX 3.5

Coding Style:
PSR-12

Testing:
PHPUnit

Rules:
- Do not modify vendor/.
- Do not modify production configuration.
- Never store credentials in source code.
- New APIs require authentication.
- Database changes require migration scripts.
- New public methods require PHPDoc.
- Run PHP lint after PHP modifications.
- Run relevant PHPUnit tests.
- Never push or deploy automatically.
```

This gives the model persistent repository-specific guidance without
changing model weights.

------------------------------------------------------------------------

## 13. Company Knowledge RAG

The coding agent should also have access to approved company development
documents:

``` text
Coding Agent
     |
     +-- Coding Model
     |
     +-- Repository
     |
     +-- PROJECT_AGENT.md
     |
     +-- Company RAG
            |
            +-- PHP standards
            +-- JavaScript standards
            +-- CSS standards
            +-- MariaDB standards
            +-- security standards
            +-- API standards
            +-- architecture documents
            +-- unit testing guides
```

This can make a generic coding model behave much more like a
company-specific development assistant.

------------------------------------------------------------------------

## 14. Repository Search

Do not send an entire large repository to the LLM for every request.

Use several retrieval methods:

``` text
Developer Task
      |
      v
Repository Search
      |
      +-- filename search
      +-- exact text search
      +-- symbol search
      +-- class/method references
      +-- semantic search
      +-- dependency map
      |
      v
Relevant Files
      |
      v
LLM Context
```

Traditional code search remains extremely valuable. Semantic/vector
search should complement it rather than replace it.

------------------------------------------------------------------------

## 15. Agent Tools

The model should receive controlled tools such as:

``` text
list_directory()
search_files()
read_file()

patch_file()
write_file()

run_command()

php_lint()
run_phpunit()

git_status()
git_diff()

read_project_documentation()
```

The LLM requests tool actions. The platform decides whether each action
is permitted.

The central security principle is:

> The LLM decides what it wants to do; the platform decides what it is
> allowed to do.

------------------------------------------------------------------------

## 16. Isolated Sandbox

Never allow the agent to edit production source directly.

Use:

``` text
Git Repository
      |
      v
Temporary Branch / Worktree
      |
      v
+-----------------------------+
| Isolated Agent Sandbox      |
|                             |
| repository copy/worktree    |
| PHP                         |
| PHPUnit                     |
| Git                         |
| approved build tools        |
+-----------------------------+
      |
      v
Git Diff
      |
      v
Human Review
```

Prefer one sandbox per task.

``` text
Task A -> Sandbox A
Task B -> Sandbox B
Task C -> Sandbox C
```

This prevents tasks from contaminating each other's workspaces.

------------------------------------------------------------------------

## 17. Container Images

Containers are a practical way to create reproducible sandboxes.

Example:

``` text
company-php74-agent

- Linux
- PHP 7.4
- Composer
- PHPUnit
- Git
- MariaDB client
- approved utilities
- company CA certificates
```

Eventually maintain an environment registry:

``` text
company-php74
company-php82
company-laravel
company-node
company-react
company-java
company-python
```

Each repository can specify its required agent environment.

------------------------------------------------------------------------

## 18. Command Security

Commands should be classified.

### Normally permitted in a PHP sandbox

``` text
php -l
phpunit
git diff
git status
grep
find
```

### Restricted or approval-required

``` text
rm
chmod
mysql
git push
ssh
network utilities
package installation
```

### Prohibited for ordinary coding agents

``` text
production SSH
production database modification
production deployment
destructive host commands
```

Do not rely on the model to obey this policy. Enforce it in the tool
layer and sandbox.

------------------------------------------------------------------------

## 19. Network Security

A strong default for an offline coding-agent sandbox is:

``` text
Internet             DENIED
Production Network   DENIED
Internal Network     DENIED by default

Explicitly Allowed:
- Internal Git server
- Test database
- Internal package mirror
- Internal documentation/RAG
- approved development services
```

The sandbox should never have unrestricted access to the corporate
network.

------------------------------------------------------------------------

## 20. Credentials

Never place broad production credentials inside an agent sandbox.

Avoid:

-   production DB passwords
-   administrator Git tokens
-   production SSH private keys
-   deployment secrets

Prefer narrowly scoped, temporary credentials.

``` text
Agent Task
    |
    v
Temporary Credential
    |
    +-- specific repository
    +-- test resources only
    +-- minimal permissions
    +-- short expiration
```

------------------------------------------------------------------------

## 21. Git Workflow

Git should be central to the system.

``` text
Known Base Commit
       |
       v
Temporary Branch / Worktree
       |
       v
Agent Modifications
       |
       v
Lint / Tests
       |
       v
git diff
       |
       v
Developer Review
       |
       +-- Accept
       +-- Request Changes
       +-- Discard
```

Do not initially allow the AI to merge directly into production
branches.

------------------------------------------------------------------------

## 22. Example Coding Task

Developer request:

``` text
Add an API that returns the unread email count.
Follow our coding standards and add PHPUnit tests.
```

Agent flow:

``` text
1. Read PROJECT_AGENT.md
2. Inspect email module
3. Find existing API patterns
4. Find authentication mechanism
5. Find relevant database/service classes
6. Create implementation plan
7. Modify required files
8. Add PHPUnit tests
9. Run PHP lint
10. Run PHPUnit
11. Analyze failures
12. Correct code if needed
13. Run tests again
14. Generate Git diff
15. Summarize changes
16. Wait for developer approval
```

------------------------------------------------------------------------

## 23. Audit Trail

Store an audit record for every agent task:

``` text
task_id
user_id
repository_id
base_commit
model_name
model_version
started_at
completed_at

developer_request
files_read
files_modified
commands_requested
commands_executed
test_results
final_diff
approval_decisions
commit_hash
```

Logs must also have secret detection/redaction and access controls.

------------------------------------------------------------------------

## 24. Model API Abstraction

Do not tightly couple the agent platform to one model.

``` text
                Coding Agent
                     |
                     v
               Model API Layer
                     |
          +----------+----------+
          |          |          |
          v          v          v
       Model A    Model B    Model C
```

This allows the company to replace the model without rebuilding:

-   sandbox infrastructure
-   Git integration
-   repository tools
-   RAG
-   auditing
-   approval workflows
-   UI

------------------------------------------------------------------------

## 25. Model Evaluation

Do not select a model only from public benchmark scores.

Create a private company benchmark from real development tasks.

Example:

``` text
Task 1  Fix a PHP bug
Task 2  Add PHPUnit tests
Task 3  Optimize a MariaDB query
Task 4  Add an authenticated API
Task 5  Refactor a service
...
Task 50 Complex multi-file feature
```

Run the same tasks against candidate models.

Measure:

  Metric                        Purpose
  ----------------------------- ------------------------
  Correct implementation        Primary quality
  Tests passed                  Functional correctness
  Developer acceptance          Practical usefulness
  Unnecessary file changes      Agent discipline
  Security violations           Safety
  Time to completion            Productivity
  GPU VRAM                      Hardware requirement
  Token usage                   Serving load
  Number of repair iterations   Reliability

Choose the model based on performance on **your repositories and coding
standards**.

------------------------------------------------------------------------

## 26. Hardware Strategy

Start with a pilot rather than a large cluster.

Conceptual development server:

``` text
CPU:
16-32 modern cores

System RAM:
64-128 GB or more

GPU:
One GPU with substantial VRAM

Storage:
2-4+ TB NVMe

Network:
Fast internal network

OS:
Linux
```

The exact GPU depends on the selected model and quantization.

Test the model before purchasing many GPUs.

------------------------------------------------------------------------

## 27. Shared GPU, Many Sandboxes

One important architecture is:

``` text
Developers
    |
    v
Agent Platform
    |
    +--------------------+
    |                    |
    v                    v
Shared LLM Service   Agent Sandboxes
    |                    |
   GPU              CPU development tools
                         |
                   PHP / Git / PHPUnit
```

You do not need a GPU in every sandbox or one GPU per developer.

The expensive LLM inference service can be centralized and shared.

------------------------------------------------------------------------

## 28. Development Roadmap

### Version 1 --- Proof of Concept

Build:

-   one coding model
-   one GPU server
-   coding-agent API
-   one repository type
-   isolated container sandbox
-   Git integration
-   file read/search/edit tools
-   PHP lint
-   PHPUnit
-   Git diff
-   human approval

``` text
Developer
    |
    v
Coding Agent
    |
    v
Local LLM
    |
    v
Sandbox
    |
    +-- Repository
    +-- PHP 7.4
    +-- PHPUnit
    +-- Git
```

### Version 2 --- Company-Aware Agent

Add:

-   company standards RAG
-   repository semantic search
-   project instructions
-   task history
-   diff viewer
-   better context management
-   streaming progress
-   branch/worktree automation

### Version 3 --- Enterprise Platform

Add:

-   multiple repositories
-   multiple development environments
-   RBAC
-   internal Git integration
-   automatic test selection
-   static analysis
-   security scanning
-   issue-tracker integration
-   centralized audit

### Version 4 --- Advanced Coding Platform

Add carefully:

-   automated branch creation
-   internal pull/merge request generation
-   specialized reviewer agents
-   security review agents
-   test-generation agents
-   controlled multi-agent workflows

Human review should remain a critical boundary.

------------------------------------------------------------------------

## 29. Optional Multi-Agent Future

Do not begin with a complicated multi-agent system, but it can
eventually look like:

``` text
                 Lead Agent
                     |
       +-------------+-------------+
       |             |             |
       v             v             v
   Planning       Coding        Testing
    Agent          Agent         Agent
       |             |             |
       +-------------+-------------+
                     |
                     v
                Review Agent
                     |
                     v
                 Final Diff
                     |
                     v
                Human Review
```

A reliable single-agent loop is more important than having many agents.

------------------------------------------------------------------------

## 30. Offline Software and Model Repository

For an isolated network, maintain controlled internal repositories.

``` text
Internal Development Infrastructure

+-- Git Server
+-- Package Mirrors
|    +-- Composer
|    +-- npm
|    +-- Python packages
|
+-- Model Repository
|    +-- Coding LLM
|    +-- Embedding models
|    +-- Rerankers
|
+-- Documentation Repository
+-- Container Image Registry
```

Each imported model should record:

-   model name
-   exact version
-   checksum
-   source
-   license
-   commercial-use approval
-   security review
-   import date
-   model size
-   required VRAM
-   quantization
-   approved inference runtime

Verify transferred model files with an approved cryptographic checksum
such as SHA-256.

------------------------------------------------------------------------

## 31. Important Design Principles

### Principle 1 --- Model != Coding Agent

``` text
Coding Model
     +
Agent Orchestrator
     +
Tools
     +
Sandbox
     +
Git
     +
Tests
     +
RAG
     +
Security
     =
Codex-like Coding Platform
```

### Principle 2 --- Keep production outside the sandbox

The coding agent should normally have no direct production access.

### Principle 3 --- The platform enforces permissions

Never rely solely on prompts such as:

``` text
"Please do not execute dangerous commands."
```

Enforce restrictions technically.

### Principle 4 --- Keep the model replaceable

New models will appear frequently. The surrounding agent infrastructure
is the long-term investment.

### Principle 5 --- Test on real company tasks

A model that scores highly on a public benchmark may still perform
poorly on the company's actual legacy code, conventions, frameworks, and
requirements.

### Principle 6 --- Start small

Do not begin with:

``` text
10,000 developers/users
many GPUs
many agents
full production automation
```

Begin with:

``` text
1 model
1 GPU server
1 agent
1 sandbox architecture
a few repositories
a small developer pilot
```

Then measure and scale.

------------------------------------------------------------------------

## 32. Recommended Initial Architecture

For the first practical implementation:

``` text
                    Developers
                        |
                        v
                 Coding Agent UI
                        |
                        v
                 Agent Gateway
                 Auth / RBAC
                        |
                        v
                 Agent Orchestrator
                    /         \
                   /           \
                  v             v
          Coding LLM       Code/Docs RAG
              |
             GPU

                  |
                  v
          Isolated Container
                  |
          +-------+-------+
          |       |       |
          v       v       v
         Git     PHP    PHPUnit
                  |
                  v
               Git Diff
                  |
                  v
             Human Review
```

For the user's current PHP environment, a first sandbox image can
target:

``` text
PHP 7.4
Zend Framework 1 project support
MariaDB test access
DHTMLX project source
PHPUnit
Git
Composer where required
company coding/security standards
```

------------------------------------------------------------------------

## 33. Final Recommendation

For an internal Codex-like platform:

1.  **Do not train a foundation model from scratch.**
2.  Evaluate downloadable/open-weight coding models first.
3.  Run the selected model on an internal GPU server.
4.  Keep the model behind a stable internal model API.
5.  Build an agent orchestrator around it.
6.  Give the agent narrowly controlled file, Git, build, and testing
    tools.
7.  Run every task in an isolated sandbox.
8.  Keep production credentials and production systems outside the
    sandbox.
9.  Add company coding standards and architecture through RAG.
10. Use Git diff and human approval as the normal completion boundary.
11. Benchmark several models against real company development tasks.
12. Scale GPU capacity only after measuring actual concurrent workloads.

The most important architectural idea is:

``` text
The LLM provides intelligence.

The agent platform provides capabilities.

The sandbox provides isolation.

RAG provides company knowledge.

Git provides controlled change tracking.

Tests provide verification.

Human review provides the final approval boundary.
```

That combination is the foundation of a secure and useful internal
Codex-like development platform.
