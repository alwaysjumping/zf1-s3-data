# Workerman Networking and WebSocket Architecture Guide

## 1. Introduction

This document consolidates the discussion about:

- Whether pure PHP sockets can handle multiple connections
- How Workerman achieves high-concurrency networking
- How Workerman's event loop works
- How `TcpConnection` manages connections
- How Workerman handles partial reads and writes
- How send and receive buffers work
- How WebSocket is built on TCP
- How Workerman handles WebSocket handshakes and frames
- How Workerman manages worker processes
- How heartbeats, timeouts, errors, shutdown, and reconnection should be handled
- Which responsibilities belong to Workerman, the PHP application, and the browser client
- How to build a production-style Workerman WebSocket server

The main conclusion is:

> Pure PHP can handle many simultaneous socket connections, but Workerman provides the reusable networking framework that solves most of the difficult low-level engineering problems.

---

# 2. Can Pure PHP Handle Multiple Socket Connections?

Yes.

Pure PHP can handle many socket connections by using an event-driven design instead of blocking on one client at a time.

A simple blocking TCP server might look like:

```php
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

socket_bind($server, '0.0.0.0', 9000);
socket_listen($server);

while (true) {
    $client = socket_accept($server);

    $data = socket_read($client, 2048);

    // Process client...
}
```

The problem is that:

```php
socket_read($client, 2048);
```

can block.

While PHP waits for one client:

```text
Client A ───► Server
              │
              │ waiting...
              │
Client B ─────┤
Client C ─────┤
Client D ─────┘
```

other clients may not be processed promptly.

---

# 3. Using `socket_select()` or `stream_select()`

PHP can monitor many sockets simultaneously.

Conceptually:

```text
Client A ─────┐
Client B ─────┤
Client C ─────┼──► select()
Client D ─────┤
Client E ─────┘
                    │
                    ▼
             Which sockets are ready?
```

A simplified example:

```php
$clients = [];

while (true) {

    $read = $clients;
    $read[] = $server;

    $write = null;
    $except = null;

    $changed = socket_select(
        $read,
        $write,
        $except,
        null
    );

    if ($changed === false) {
        break;
    }

    if (in_array($server, $read, true)) {

        $client = socket_accept($server);

        $clients[] = $client;

        $key = array_search($server, $read, true);
        unset($read[$key]);
    }

    foreach ($read as $client) {

        $data = @socket_read(
            $client,
            2048,
            PHP_BINARY_READ
        );

        if ($data === false || $data === '') {

            socket_close($client);

            $key = array_search(
                $client,
                $clients,
                true
            );

            if ($key !== false) {
                unset($clients[$key]);
            }

            continue;
        }

        socket_write(
            $client,
            "Server received: " . $data
        );
    }
}
```

This allows one PHP process to monitor many clients.

The important concept is:

```text
500 clients
    │
    ▼
one event loop
    │
    ▼
process only ready sockets
```

---

# 4. Why Workerman Exists

Pure PHP already has the low-level networking features.

Workerman adds the framework around them.

Conceptually:

```text
Pure PHP
   ↓
You build everything yourself


Workerman
   ↓
Networking framework
already implementing
the difficult infrastructure
```

Without Workerman, the developer would need to implement:

- connection management
- non-blocking I/O
- event loops
- partial reads
- partial writes
- receive buffering
- send buffering
- backpressure
- disconnect detection
- timers
- heartbeat handling
- error handling
- worker processes
- graceful shutdown
- Unix signals
- WebSocket handshake
- WebSocket framing
- masking
- fragmentation
- ping/pong
- close frames

Workerman handles much of this.

---

# 5. Main Workerman Source Components

Important source files include:

```text
src/
├── Worker.php
├── Connection/
│   ├── TcpConnection.php
│   └── AsyncTcpConnection.php
├── Events/
│   ├── EventInterface.php
│   ├── Select.php
│   ├── Event.php
│   ├── Swoole.php
│   └── Swow.php
└── Protocols/
    ├── Websocket.php
    └── ...
```

These layers can be understood as:

```text
Your Application
      │
      ▼
Worker
      │
      ▼
TcpConnection
      │
      ▼
Protocol
      │
      ▼
Event Loop
      │
      ▼
Operating System Socket
```

---

# 6. Workerman Event Loop

The fallback event loop is implemented in:

```text
src/Events/Select.php
```

Its purpose is to answer:

```text
Which socket can I read?
Which socket can I write?
Which timer should run?
```

Conceptually it maintains:

```text
readFds
writeFds
exceptFds

readEvents
writeEvents
exceptEvents
```

The basic idea is:

```php
while ($this->running) {

    $read = $this->readFds;
    $write = $this->writeFds;
    $except = $this->exceptFds;

    stream_select(
        $read,
        $write,
        $except,
        0,
        $timeout
    );

    // Process ready sockets.
}
```

This is the core of asynchronous networking.

---

# 7. `onReadable()`

A component can tell the event loop:

```text
When this stream becomes readable,
run this callback.
```

A simplified conceptual implementation:

```php
public function onReadable(
    $socket,
    callable $callback
) {
    $id = (int)$socket;

    $this->readSockets[$id] = $socket;
    $this->callbacks[$id] = $callback;
}
```

Then:

```php
foreach ($read as $socket) {

    $id = (int)$socket;

    $callback =
        $this->callbacks[$id];

    $callback($socket);
}
```

This is the foundation of Workerman's event-driven design.

---

# 8. Minimal Event Loop Example

A small Workerman-like event loop could look like:

```php
class SimpleEventLoop
{
    private $readSockets = [];
    private $callbacks = [];

    public function onReadable(
        $socket,
        callable $callback
    ) {
        $id = (int)$socket;

        $this->readSockets[$id] = $socket;
        $this->callbacks[$id] = $callback;
    }

    public function run()
    {
        while (true) {

            $read =
                $this->readSockets;

            $write = null;
            $except = null;

            stream_select(
                $read,
                $write,
                $except,
                null
            );

            foreach ($read as $socket) {

                $id = (int)$socket;

                $callback =
                    $this->callbacks[$id];

                $callback($socket);
            }
        }
    }
}
```

The important point is:

> Many sockets can be managed by one event loop.

---

# 9. Workerman Event Backends

Workerman can use different event-loop implementations.

Conceptually:

```text
Workerman
   │
   ├── Event extension
   │
   └── Select fallback
```

The Select backend is simple and portable, but select-based event loops have practical descriptor limitations.

For large connection counts on Linux, more scalable event backends are preferable.

---

# 10. `TcpConnection`

`TcpConnection.php` represents one accepted client connection.

Conceptually:

```text
TcpConnection
│
├── socket
├── recvBuffer
├── sendBuffer
├── protocol
├── status
├── onMessage
├── onClose
├── onError
├── remoteAddress
└── eventLoop
```

The `$connection` used in Workerman callbacks is essentially a `TcpConnection`.

Example:

```php
$worker->onMessage =
    function ($connection, $data) {

        $connection->send(
            "Hello"
        );
    };
```

---

# 11. Non-Blocking I/O

Workerman makes accepted sockets non-blocking.

Conceptually:

```php
stream_set_blocking(
    $socket,
    false
);
```

Then Workerman registers the socket with the event loop.

This means Workerman does not call a blocking `fread()` and wait for one client.

Instead:

```text
Client A ──────┐
Client B ──────┼──► Event Loop
Client C ──────┘
                   │
                   ▼
              B is readable
                   │
                   ▼
              baseRead()
```

---

# 12. `baseRead()`

When the event loop detects readable data, Workerman calls the connection's read handler.

Conceptually:

```php
public function baseRead($socket)
{
    $buffer = fread(
        $socket,
        8192
    );

    // Process incoming bytes.
}
```

Because the stream is non-blocking and is only read after the event loop reports it as readable, the server avoids blocking on idle clients.

---

# 13. TCP Does Not Preserve Message Boundaries

This is extremely important.

Suppose the client sends:

```text
HELLO
```

The server may receive:

```text
HE
```

then:

```text
LLO
```

Or two application messages may arrive in one TCP read.

Therefore:

```text
TCP read
   ≠
application message
```

This is why receive buffering and protocol parsing are necessary.

---

# 14. Receive Buffer

Workerman keeps incoming bytes in a receive buffer.

Conceptually:

```text
Network
   │
   ▼
fread()
   │
   ▼
recvBuffer
   │
   ▼
Protocol parser
   │
   ▼
Complete message
   │
   ▼
onMessage()
```

Example:

```text
Read #1:
"HEL"

recvBuffer = "HEL"


Read #2:
"LO"

recvBuffer = "HELLO"
```

Once the protocol determines the message is complete, Workerman passes it to the application.

---

# 15. Protocol Layer

A protocol determines how raw bytes become application messages.

Conceptually:

```text
Raw TCP bytes
      │
      ▼
protocol::input()
      │
      ▼
Complete message?
      │
      ▼
protocol::decode()
      │
      ▼
Application data
      │
      ▼
onMessage()
```

Workerman can therefore support:

```text
TcpConnection
      │
      ▼
   Protocol
      │
      ├── WebSocket
      ├── HTTP
      ├── Text
      └── custom protocols
```

---

# 16. Outgoing Data and `send()`

The application normally calls:

```php
$connection->send(
    "Hello"
);
```

If a protocol is configured:

```text
Application data
      │
      ▼
protocol::encode()
      │
      ▼
Network bytes
      │
      ▼
fwrite()
```

For WebSocket, the plain string is converted into a WebSocket frame before transmission.

---

# 17. Partial Writes

`fwrite()` does not guarantee that all requested bytes will be written.

Example:

```text
Application wants to send:
100 KB

Operating system accepts:
20 KB

Remaining:
80 KB
```

Workerman handles this automatically.

Conceptually:

```text
100 KB
  │
  ▼
fwrite()
  │
  ├── 20 KB sent
  │
  └── 80 KB
         │
         ▼
      sendBuffer
         │
         ▼
wait for writable event
         │
         ▼
      baseWrite()
```

This is a major advantage of using Workerman.

---

# 18. Send Buffer

If all outgoing data cannot be written immediately, Workerman stores the remainder.

Conceptually:

```text
TcpConnection
      │
      ├── recvBuffer
      └── sendBuffer
```

When the socket becomes writable again:

```text
Event Loop
    │
    ▼
baseWrite()
    │
    ▼
fwrite()
```

Once the send buffer becomes empty, the writable watcher is removed.

---

# 19. Backpressure

A slow client may receive data more slowly than the server produces it.

Example:

```text
Server:
100 MB/sec

Client:
1 MB/sec
```

If nothing limits the queue:

```text
sendBuffer
1 MB
10 MB
100 MB
1 GB
...
```

Eventually the server may exhaust memory.

Workerman provides send-buffer limits and callbacks such as:

```php
$worker->onBufferFull
```

and:

```php
$worker->onBufferDrain
```

Example:

```php
$worker->onBufferFull =
    function ($connection) {

        $connection->slowClient =
            true;

        error_log(
            "Slow client: " .
            $connection->id
        );
    };

$worker->onBufferDrain =
    function ($connection) {

        $connection->slowClient =
            false;
    };
```

Workerman detects the condition.

The application decides the policy:

- pause sending
- drop non-critical messages
- disconnect the client
- log the condition
- retry later

---

# 20. Receive Flow Control

Workerman supports:

```php
$connection->pauseRecv();
```

and:

```php
$connection->resumeRecv();
```

Example:

```php
$worker->onMessage =
    function ($connection, $data) {

        $connection->messageCount++;

        if (
            $connection->messageCount >
            1000
        ) {
            $connection->pauseRecv();

            Timer::add(
                5,
                function ()
                use ($connection) {

                    $connection
                        ->messageCount = 0;

                    $connection
                        ->resumeRecv();
                },
                [],
                false
            );
        }
    };
```

Workerman provides the mechanism.

The developer decides when to use it.

---

# 21. Worker Class

`Worker.php` is the main orchestrator.

A normal Workerman application:

```php
use Workerman\Worker;

$worker =
    new Worker(
        'websocket://0.0.0.0:8080'
    );

$worker->onMessage =
    function ($connection, $data) {

        $connection->send(
            "Hello"
        );
    };

Worker::runAll();
```

Internally:

```text
new Worker()
    │
    ▼
Worker configuration
    │
    ▼
Worker::runAll()
    │
    ├── initialize
    ├── create listening sockets
    ├── create worker processes
    ├── initialize event loops
    └── accept clients
```

---

# 22. Listening Socket

The main socket is not a client connection.

It is a listening socket:

```text
Worker
  │
  ▼
mainSocket
  │
  ▼
LISTENING
```

When a client connects:

```text
mainSocket
   │
   ▼
accept()
   │
   ▼
client socket
   │
   ▼
TcpConnection
```

The listening socket itself is monitored by the event loop.

---

# 23. Workerman Callbacks

Common callbacks include:

```php
$worker->onConnect
$worker->onMessage
$worker->onClose
$worker->onError
$worker->onBufferFull
$worker->onBufferDrain
$worker->onWorkerStart
$worker->onWorkerStop
$worker->onWorkerReload
$worker->onWebSocketConnect
$worker->onWebSocketPing
$worker->onWebSocketPong
```

These expose important lifecycle points to application code.

---

# 24. Multi-Process Architecture

Workerman supports multiple worker processes:

```php
$worker->count = 4;
```

Conceptually:

```text
Master Process
      │
      ├── Worker Process 1
      ├── Worker Process 2
      ├── Worker Process 3
      └── Worker Process 4
```

Each worker process has its own:

- event loop
- socket connections
- PHP memory
- application objects

This lets Workerman use multiple CPU cores.

---

# 25. Event-Driven I/O Plus Multi-Process

These are separate ideas.

Workerman combines:

```text
event-driven I/O
+
multiple processes
```

Example:

```text
Master
  │
  ├── Worker 1
  │      └── Event Loop
  │           ├── 1000 clients
  │           └── ...
  │
  ├── Worker 2
  │      └── Event Loop
  │           ├── 1000 clients
  │           └── ...
  │
  └── Worker 3
         └── Event Loop
              ├── 1000 clients
              └── ...
```

This does **not** mean:

```text
1 client
=
1 process
```

---

# 26. Worker Memory Is Not Shared

Each worker process has its own PHP memory.

Example:

```text
Worker 1:
connections A, B

Worker 2:
connections C, D
```

Inside Worker 1:

```php
foreach (
    $worker->connections
    as $connection
) {
    $connection->send(
        "Hello"
    );
}
```

will only reach clients owned by Worker 1.

It will not automatically reach clients in another worker process.

Cross-worker communication requires an application architecture such as:

- IPC
- message queues
- external pub/sub
- database
- another internal communication mechanism

---

# 27. WebSocket Runs on TCP

The browser API:

```javascript
const ws =
    new WebSocket(
        "ws://example.com:8080"
    );
```

uses:

```text
WebSocket
   ↓
TCP
   ↓
IP
```

So:

```text
Browser JavaScript WebSocket
        │
        ▼
WebSocket protocol
        │
        ▼
TCP
        │
        ▼
Workerman TcpConnection
```

The browser does not expose arbitrary TCP sockets to ordinary JavaScript.

---

# 28. WebSocket Starts with HTTP

A WebSocket connection begins as an HTTP Upgrade request.

Example:

```http
GET /ws HTTP/1.1
Host: example.com:8080
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: ...
Sec-WebSocket-Version: 13
```

The server replies with:

```http
HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: ...
```

After that:

```text
TCP
 └── WebSocket
```

The TCP connection remains open.

---

# 29. WebSocket Handshake

The server calculates:

```text
Sec-WebSocket-Key
      +
fixed WebSocket GUID
      ↓
SHA-1
      ↓
Base64
      ↓
Sec-WebSocket-Accept
```

The fixed GUID is:

```text
258EAFA5-E914-47DA-95CA-C5AB0DC85B11
```

Workerman performs this automatically.

The developer does not manually build the handshake.

---

# 30. WebSocket Frames

After the handshake, messages are transmitted as WebSocket frames.

A simplified frame contains:

```text
FIN
RSV bits
opcode
MASK
payload length
masking key
payload
```

Important opcodes include:

```text
0x0  continuation
0x1  text
0x2  binary
0x8  close
0x9  ping
0xA  pong
```

Workerman parses these automatically.

---

# 31. Client Masking

Browser-to-server WebSocket frames are masked.

Conceptually:

```text
payload
  XOR
masking key
  ↓
masked payload
```

Workerman automatically unmasks the frame before delivering application data.

Server-to-client frames are normally not masked.

---

# 32. WebSocket Fragmentation

One WebSocket message can be split across multiple frames.

Example:

```text
Frame 1:
"Hel"

Frame 2:
"lo "

Frame 3:
"World"
```

Logical application message:

```text
Hello World
```

Workerman handles frame parsing and message reconstruction according to the protocol.

---

# 33. `input()` and `decode()`

Conceptually:

```text
recvBuffer
    │
    ▼
Websocket::input()
    │
    ▼
Complete frame?
    │
    ├── No → wait
    │
    └── Yes
           │
           ▼
    Websocket::decode()
           │
           ▼
       application data
           │
           ▼
       onMessage()
```

This is why application code does not need to understand raw WebSocket bytes.

---

# 34. Outgoing WebSocket Encoding

When the application calls:

```php
$connection->send(
    "Hello"
);
```

the path is:

```text
"Hello"
   │
   ▼
Websocket::encode()
   │
   ▼
WebSocket frame
   │
   ▼
TcpConnection::send()
   │
   ▼
TCP
   │
   ▼
Browser
```

---

# 35. Disconnect Detection

Workerman detects normal disconnects automatically.

Example:

```php
$worker->onClose =
    function ($connection) {

        error_log(
            "Disconnected: " .
            $connection->id
        );
    };
```

However, a dead peer may not be detected immediately if:

- Wi-Fi disappears
- the laptop loses power
- NAT state is removed
- a firewall silently drops the connection

In those situations, heartbeat logic is needed.

---

# 36. Heartbeats

Workerman provides the necessary features:

- timers
- WebSocket ping/pong support
- connection objects
- close handling

But the heartbeat policy is an application responsibility.

A simple application-level heartbeat:

```php
const CLIENT_TIMEOUT = 90;

$worker->onConnect =
    function ($connection) {

        $connection->lastSeen =
            time();
    };

$worker->onMessage =
    function (
        $connection,
        $message
    ) {

        $connection->lastSeen =
            time();

        $data =
            json_decode(
                $message,
                true
            );

        if (
            ($data['type'] ?? null)
            === 'ping'
        ) {
            $connection->send(
                json_encode([
                    'type' => 'pong',
                    'time' => time(),
                ])
            );

            return;
        }
    };

$worker->onWorkerStart =
    function ($worker) {

        Timer::add(
            30,
            function ()
            use ($worker) {

                $now = time();

                foreach (
                    $worker->connections
                    as $connection
                ) {
                    if (
                        $now -
                        $connection->lastSeen
                        >
                        CLIENT_TIMEOUT
                    ) {
                        $connection
                            ->close();
                    }
                }
            }
        );
    };
```

---

# 37. WebSocket Ping/Pong

WebSocket itself has protocol-level Ping and Pong frames.

```text
Client
   │
   │ PING
   ▼
Server
   │
   │ PONG
   ▼
Client
```

Workerman understands the corresponding WebSocket opcodes.

Callbacks are available for custom handling:

```php
$worker->onWebSocketPing
$worker->onWebSocketPong
```

For browser applications, application-level heartbeat messages such as:

```json
{
  "type": "ping"
}
```

are often easier because browser JavaScript does not expose low-level raw Ping frame generation.

---

# 38. Timeouts

Workerman supplies timers.

The application defines the timeout policy.

Examples:

- authentication timeout
- idle timeout
- heartbeat timeout
- external API timeout
- command timeout
- database operation timeout

Authentication timeout example:

```php
$worker->onConnect =
    function ($connection) {

        $connection->authenticated =
            false;

        $connection->authTimer =
            Timer::add(
                10,
                function ()
                use ($connection) {

                    if (
                        !$connection
                            ->authenticated
                    ) {
                        $connection
                            ->close();
                    }
                },
                [],
                false
            );
    };
```

After successful authentication:

```php
$connection->authenticated =
    true;

Timer::del(
    $connection->authTimer
);
```

---

# 39. Error Handling

Workerman exposes:

```php
$worker->onError =
    function (
        $connection,
        $code,
        $message
    ) {
        error_log(
            "Socket error {$code}: " .
            $message
        );
    };
```

Workerman detects networking problems.

The application decides what to do:

- log
- retry
- discard
- persist
- alert monitoring
- mark user offline
- disconnect client

---

# 40. `close()` vs `destroy()`

Use:

```php
$connection->close();
```

for normal graceful closing.

Conceptually:

```text
flush pending send buffer
        ↓
close socket
        ↓
onClose
```

Use:

```php
$connection->destroy();
```

when the socket should be aborted immediately.

Conceptually:

```text
destroy()
   ↓
close immediately
```

For normal application behavior:

```text
close()
```

is usually preferred.

---

# 41. WebSocket Authentication

Workerman handles the handshake protocol.

The application handles identity and authorization.

Example:

```php
use Workerman\Protocols\Http\Request;

$worker->onWebSocketConnect =
    function (
        $connection,
        Request $request
    ) {

        $origin =
            $request->header(
                'origin'
            );

        if (
            $origin !==
            'https://app.example.com'
        ) {
            $connection->close();
            return;
        }

        $token =
            $request->get('token');

        $user =
            authenticateToken(
                $token
            );

        if (!$user) {
            $connection->close();
            return;
        }

        $connection->authenticated =
            true;

        $connection->userId =
            $user['id'];
    };
```

Workerman handles:

```text
HTTP parsing
Upgrade request
Sec-WebSocket-Key
101 response
WebSocket state
```

The application handles:

```text
authentication
authorization
origin policy
permissions
user identity
```

---

# 42. Process Management

Workerman provides commands such as:

```bash
php start.php start
php start.php start -d
php start.php stop
php start.php restart
php start.php reload
php start.php status
php start.php connections
```

Workerman handles the normal worker process lifecycle.

The developer normally does not need to manually implement:

```php
pcntl_fork()
```

or low-level worker supervision.

---

# 43. Unix Signals

Workerman internally uses Unix signals for operations such as:

- stop
- reload
- restart coordination
- worker management
- status collection

Application code generally does not need to manually register common Workerman lifecycle signals.

---

# 44. Graceful Reload

Workerman can reload worker processes.

Conceptually:

```text
Old Worker 1
    ↓
stop/reload
    ↓
New Worker 1

Old Worker 2
    ↓
stop/reload
    ↓
New Worker 2
```

Important:

> WebSocket connections belong to the worker process that owns them.

If the process exits, those connections disappear.

Therefore important business state should not live only in worker memory.

---

# 45. Shutdown Cleanup

Workerman provides callbacks such as:

```php
$worker->onWorkerStop =
    function ($worker) {

        foreach (
            $worker->connections
            as $connection
        ) {
            $connection->close(
                json_encode([
                    'type' =>
                        'server_shutdown'
                ])
            );
        }

        // Flush queues.
        // Close custom resources.
    };
```

Workerman provides the lifecycle hook.

The application defines the cleanup behavior.

---

# 46. Reconnection Is a Client Responsibility

If the browser connection disappears:

```text
Browser
   X
   │
Server connection gone
```

the server cannot make the browser reconnect.

Only the browser can create a new WebSocket connection.

Therefore JavaScript needs reconnection logic.

---

# 47. Production JavaScript Reconnection

Example:

```javascript
class ReconnectingWebSocketClient {

    constructor(url) {

        this.url = url;

        this.socket = null;

        this.retryAttempt = 0;

        this.maxDelay = 30000;

        this.heartbeatTimer = null;

        this.shouldReconnect = true;

        this.connect();
    }

    connect() {

        this.socket =
            new WebSocket(this.url);

        this.socket.onopen = () => {

            console.log(
                "WebSocket connected"
            );

            this.retryAttempt = 0;

            this.startHeartbeat();
        };

        this.socket.onmessage =
            event => {

                const message =
                    JSON.parse(
                        event.data
                    );

                if (
                    message.type ===
                    "pong"
                ) {
                    return;
                }

                this.handleMessage(
                    message
                );
            };

        this.socket.onerror =
            error => {

                console.error(
                    "WebSocket error",
                    error
                );
            };

        this.socket.onclose =
            event => {

                console.log(
                    "WebSocket closed",
                    event.code,
                    event.reason
                );

                this.stopHeartbeat();

                if (
                    this.shouldReconnect
                ) {
                    this.scheduleReconnect();
                }
            };
    }

    scheduleReconnect() {

        const exponentialDelay =
            Math.min(
                1000 *
                Math.pow(
                    2,
                    this.retryAttempt
                ),
                this.maxDelay
            );

        const jitter =
            Math.random() * 1000;

        const delay =
            exponentialDelay +
            jitter;

        this.retryAttempt++;

        setTimeout(
            () => {
                this.connect();
            },
            delay
        );
    }

    startHeartbeat() {

        this.stopHeartbeat();

        this.heartbeatTimer =
            setInterval(
                () => {

                    if (
                        this.socket &&
                        this.socket
                            .readyState ===
                            WebSocket.OPEN
                    ) {
                        this.socket.send(
                            JSON.stringify({
                                type:
                                    "ping",
                                time:
                                    Date.now()
                            })
                        );
                    }
                },
                30000
            );
    }

    stopHeartbeat() {

        if (
            this.heartbeatTimer
        ) {
            clearInterval(
                this.heartbeatTimer
            );

            this.heartbeatTimer =
                null;
        }
    }

    handleMessage(message) {

        console.log(
            "Message:",
            message
        );
    }

    close() {

        this.shouldReconnect =
            false;

        this.stopHeartbeat();

        if (this.socket) {
            this.socket.close();
        }
    }
}
```

Use:

```javascript
const client =
    new ReconnectingWebSocketClient(
        "wss://example.com/ws"
    );
```

---

# 48. Exponential Backoff

Do not reconnect continuously.

Bad:

```text
disconnect
   ↓
reconnect immediately
   ↓
fail
   ↓
reconnect immediately
   ↓
fail
```

If thousands of clients do this, they can overload the server.

Better:

```text
1 sec
2 sec
4 sec
8 sec
16 sec
30 sec
30 sec
...
```

Add random jitter so all browsers do not reconnect at the exact same time.

---

# 49. Reconnect Does Not Automatically Recover Missed Events

After reconnecting:

```text
New WebSocket connection
        ↓
authenticate again
        ↓
restore subscriptions
        ↓
recover missed events
        ↓
resume realtime operation
```

A reliable event protocol can use sequence IDs:

```json
{
  "event_id": 483921,
  "type": "notification",
  "data": {}
}
```

Browser stores:

```text
lastEventId = 483921
```

After reconnect:

```json
{
  "type": "resume",
  "last_event_id": 483921
}
```

Then the server can provide missed events.

This is application-level reliability logic.

---

# 50. Responsibility Matrix

## Workerman Handles Automatically

Workerman handles:

- listening sockets
- accepting clients
- connection objects
- connection collections
- non-blocking socket mode
- event-loop registration
- readable events
- writable events
- partial TCP reads
- receive buffering
- partial TCP writes
- send buffering
- protocol framing
- WebSocket handshake
- WebSocket masking
- WebSocket frame parsing
- WebSocket encoding
- WebSocket fragmentation
- WebSocket ping/pong protocol
- normal disconnect detection
- timers
- worker process creation
- worker supervision
- standard signal handling
- reload infrastructure
- close lifecycle callbacks

---

## Workerman Partially Handles

Workerman provides mechanisms but the developer must define policy for:

- heartbeat strategy
- heartbeat timeout
- idle timeout
- authentication timeout
- slow clients
- buffer-full behavior
- error recovery
- reconnect semantics
- worker shutdown cleanup
- cross-worker state
- reliable missed-event delivery
- application-level retry logic

---

## Developer Must Handle Explicitly

The application must implement:

- authentication
- authorization
- role/permission checks
- message validation
- JSON/schema validation
- application protocol
- business rules
- database persistence
- subscription model
- cross-worker broadcast architecture
- durable event storage
- audit logs
- message acknowledgement semantics
- missed-event recovery
- rate limiting
- security policy
- origin policy
- monitoring
- alerting
- application-specific timeout policy

---

## Browser / Client Responsibilities

The browser must handle:

- opening WebSocket connection
- detecting `onclose`
- detecting client-side error state
- reconnecting
- exponential backoff
- jitter
- reauthentication
- restoring subscriptions
- heartbeat messages if application heartbeat is used
- missed-event recovery requests
- updating UI connection state
- marking stale realtime data when disconnected

---

# 51. Production-Style Workerman Server Example

```php
<?php

declare(strict_types=1);

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

require __DIR__ .
    '/vendor/autoload.php';

const HEARTBEAT_TIMEOUT = 90;

const HEARTBEAT_CHECK_INTERVAL =
    30;


$worker =
    new Worker(
        'websocket://0.0.0.0:8080'
    );


$worker->name =
    'notification-websocket';


$worker->count = 4;


/*
 * TCP connection established.
 */
$worker->onConnect =
    function (
        TcpConnection $connection
    ) {

        $connection->authenticated =
            false;

        $connection->lastSeen =
            time();

        $connection->slowClient =
            false;

        $connection->maxSendBufferSize =
            512 * 1024;
    };


/*
 * WebSocket handshake.
 */
$worker->onWebSocketConnect =
    function (
        TcpConnection $connection,
        Request $request
    ) {

        $origin =
            $request->header(
                'origin'
            );

        if (
            $origin !==
            'https://app.example.com'
        ) {
            $connection->close();
            return;
        }

        $token =
            $request->get(
                'token'
            );

        $user =
            authenticateToken(
                $token
            );

        if (!$user) {

            $connection->close();

            return;
        }

        $connection->authenticated =
            true;

        $connection->userId =
            $user['id'];

        $connection->lastSeen =
            time();
    };


/*
 * Application message.
 */
$worker->onMessage =
    function (
        TcpConnection $connection,
        string $raw
    ) {

        $connection->lastSeen =
            time();

        if (
            !$connection
                ->authenticated
        ) {
            $connection->close();
            return;
        }

        $message =
            json_decode(
                $raw,
                true
            );

        if (
            !is_array($message)
        ) {

            $connection->send(
                json_encode([
                    'type' =>
                        'error',

                    'message' =>
                        'Invalid JSON'
                ])
            );

            return;
        }


        /*
         * Application heartbeat.
         */
        if (
            ($message['type'] ?? '')
            === 'ping'
        ) {

            $connection->send(
                json_encode([
                    'type' =>
                        'pong',

                    'server_time' =>
                        time()
                ])
            );

            return;
        }


        /*
         * Subscription request.
         */
        if (
            ($message['type'] ?? '')
            === 'subscribe'
        ) {

            $channel =
                $message['channel']
                ?? null;

            if (
                !canSubscribe(
                    $connection
                        ->userId,
                    $channel
                )
            ) {

                $connection->send(
                    json_encode([
                        'type' =>
                            'error',

                        'message' =>
                            'Permission denied'
                    ])
                );

                return;
            }

            $connection->channel =
                $channel;

            $connection->send(
                json_encode([
                    'type' =>
                        'subscribed',

                    'channel' =>
                        $channel
                ])
            );

            return;
        }


        /*
         * Unknown message.
         */
        $connection->send(
            json_encode([
                'type' =>
                    'error',

                'message' =>
                    'Unknown message type'
            ])
        );
    };


/*
 * Slow client.
 */
$worker->onBufferFull =
    function (
        TcpConnection $connection
    ) {

        $connection->slowClient =
            true;

        error_log(
            'Send buffer full: ' .
            $connection->id
        );
    };


$worker->onBufferDrain =
    function (
        TcpConnection $connection
    ) {

        $connection->slowClient =
            false;
    };


/*
 * Connection errors.
 */
$worker->onError =
    function (
        TcpConnection $connection,
        int $code,
        string $message
    ) {

        error_log(
            sprintf(
                'WebSocket error ' .
                'id=%s code=%d ' .
                'message=%s',

                $connection->id,
                $code,
                $message
            )
        );
    };


/*
 * Connection closed.
 */
$worker->onClose =
    function (
        TcpConnection $connection
    ) {

        error_log(
            'Client disconnected: ' .
            $connection->id
        );

        /*
         * Remove:
         *
         * user -> connection mapping
         * subscriptions
         * application state
         */
    };


/*
 * Worker process started.
 */
$worker->onWorkerStart =
    function (
        Worker $worker
    ) {

        Timer::add(
            HEARTBEAT_CHECK_INTERVAL,
            function ()
            use ($worker) {

                $now = time();

                foreach (
                    $worker->connections
                    as $connection
                ) {

                    if (
                        $now -
                        $connection
                            ->lastSeen
                        >
                        HEARTBEAT_TIMEOUT
                    ) {

                        error_log(
                            'Heartbeat timeout: ' .
                            $connection->id
                        );

                        $connection
                            ->close();
                    }
                }
            }
        );
    };


/*
 * Worker process stopping.
 */
$worker->onWorkerStop =
    function (
        Worker $worker
    ) {

        error_log(
            'Worker process stopping'
        );
    };


Worker::runAll();


function authenticateToken(
    ?string $token
): ?array {

    if (!$token) {
        return null;
    }

    /*
     * Replace with real
     * authentication.
     */
    return [
        'id' => 1001
    ];
}


function canSubscribe(
    int $userId,
    ?string $channel
): bool {

    return $channel !== null;
}
```

---

# 52. Recommended Production Message Format

Use a structured protocol.

Example server event:

```json
{
  "id": 493821,
  "type": "notification",
  "timestamp": 1720000000,
  "data": {
    "title": "New message"
  }
}
```

Example heartbeat:

```json
{
  "type": "ping"
}
```

Server response:

```json
{
  "type": "pong",
  "server_time": 1720000000
}
```

Example subscription:

```json
{
  "type": "subscribe",
  "channel": "notifications"
}
```

Example reconnect recovery:

```json
{
  "type": "resume",
  "last_event_id": 493821
}
```

---

# 53. Recommended Server Architecture

For a production realtime application:

```text
                    Browser
                       │
                 WebSocket
                       │
                       ▼
                Workerman Server
                       │
       ┌───────────────┼───────────────┐
       │               │               │
       ▼               ▼               ▼
 Connection       Application       Heartbeat
 Management        Protocol          Manager
       │               │               │
       └───────────────┼───────────────┘
                       │
                       ▼
                Business Services
                       │
                       ▼
                    MariaDB
```

If several Workerman processes are used:

```text
                    Master
                       │
       ┌───────────────┼───────────────┐
       ▼               ▼               ▼
    Worker 1        Worker 2        Worker 3
       │               │               │
   Clients A,B      Clients C,D      Clients E,F
```

Cross-worker communication must be designed separately.

---

# 54. Important Production Rules

## Rule 1

Do not block inside `onMessage()`.

Bad:

```php
sleep(10);
```

A blocked worker cannot process its other connections.

---

## Rule 2

Avoid slow synchronous external calls in the event loop.

Examples:

- slow HTTP API calls
- slow database queries
- long filesystem operations
- CPU-intensive processing

Move expensive work to another service or worker mechanism when necessary.

---

## Rule 3

Use heartbeat logic for persistent connections.

Do not assume `onClose` immediately fires for every network failure.

---

## Rule 4

Use backpressure protection.

A slow client must not be allowed to consume unlimited memory.

---

## Rule 5

Do not store critical business state only in worker memory.

Workers can restart.

---

## Rule 6

Remember that worker memory is not shared.

If multiple processes are used, connection maps are process-local.

---

## Rule 7

Client reconnection is required.

Production browsers must expect the WebSocket connection to disappear.

---

## Rule 8

Reconnect with exponential backoff and jitter.

Do not reconnect in a tight loop.

---

## Rule 9

Reconnect should restore application state.

After reconnect:

```text
authenticate
   ↓
restore subscriptions
   ↓
recover missed events
   ↓
resume realtime operation
```

---

## Rule 10

Use `wss://` in production.

Production WebSocket connections should normally use TLS.

---

# 55. Final Responsibility Model

The cleanest way to understand Workerman is:

```text
                    WORKERMAN
                        │
      ┌─────────────────┼─────────────────┐
      │                 │                 │
      ▼                 ▼                 ▼

   NETWORK          WEBSOCKET          PROCESS
   ENGINE              ENGINE           ENGINE

event loop          handshake         workers
non-blocking I/O    framing           signals
partial reads       masking           monitoring
partial writes      fragmentation     reload
buffers             ping/pong         lifecycle
disconnects         encode/decode

      │
      └─────────────────┬─────────────────┘
                        │
                        ▼

                 YOUR APPLICATION

              authentication
              authorization
              business rules
              heartbeat policy
              timeout policy
              subscriptions
              persistence
              cross-worker state
              missed-event recovery
              logging
              monitoring
```

And on the client:

```text
                   BROWSER
                      │
                      ├── connect
                      ├── heartbeat
                      ├── detect close
                      ├── reconnect
                      ├── backoff
                      ├── reauthenticate
                      ├── restore subscriptions
                      └── recover missed events
```

---

# 56. Core Conclusion

Pure PHP can absolutely build a multi-client TCP or WebSocket server.

However, a production networking server requires much more than simply calling:

```php
stream_socket_server()
```

and:

```php
fread()
```

A robust implementation needs:

```text
event loop
+
non-blocking sockets
+
connection lifecycle
+
receive buffer
+
send buffer
+
partial read handling
+
partial write handling
+
backpressure
+
protocol parsing
+
WebSocket handshake
+
WebSocket framing
+
heartbeats
+
timeouts
+
process management
+
graceful shutdown
+
error handling
```

Workerman already provides most of the generic networking infrastructure.

Therefore the recommended division is:

```text
Workerman
    ↓
transport/networking infrastructure


Application
    ↓
business logic and reliability semantics


Browser
    ↓
connection initiation and reconnection
```

This is why Workerman is normally a much better choice than implementing a production WebSocket server directly with raw PHP sockets.
