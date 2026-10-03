# Workerman Developer Training Guide

## WebSockets, PHP Sockets, Workerman, and ZF1 Real-Time Notification Architecture

**Audience:** Developers new to Workerman\
**Project Context:** PHP 7.4, Zend Framework 1 (ZF1), MariaDB,
JavaScript, Workerman\
**Architecture Decision:** No Redis in the current design\
**Document Type:** Internal Developer Training Manual\
**Date:** October 2026

------------------------------------------------------------------------

# 1. Training Goals

After completing this guide, a developer should be able to:

1.  Explain the difference between a network socket and WebSocket.
2.  Explain the WebSocket HTTP Upgrade handshake and frame-based
    communication.
3.  Build a small educational WebSocket server with standard PHP socket
    functions.
4.  Explain why a raw PHP socket implementation becomes difficult in
    production.
5.  Explain what Workerman provides.
6.  Build and operate a basic Workerman WebSocket server.
7.  Write a JavaScript client that connects, sends, receives, detects
    errors, and reconnects.
8.  Understand Workerman's connection objects and event callbacks.
9.  Authenticate WebSocket connections safely.
10. Integrate Workerman with the existing ZF1 application without moving
    business logic into Workerman.
11. Explain the internal signal path from ZF1 to Workerman.
12. Explain the transactional outbox and Dispatcher.
13. Handle Workerman/internal-signal failures without losing
    authoritative notification state.
14. Support multiple browser tabs and multiple user connections.
15. Explain the recommended production architecture.

------------------------------------------------------------------------

# 2. The Big Picture

Our existing application is a traditional web application:

``` text
Browser
   |
   | HTTP / HTTPS
   v
Apache
   |
   v
PHP / ZF1
   |
   v
MariaDB
```

This works well for normal request/response operations.

For example:

``` text
Browser
   |
   | POST /api/task/create
   v
ZF1
   |
   | INSERT
   v
MariaDB
   |
   v
ZF1
   |
   | JSON response
   v
Browser
```

The problem appears when the server needs to notify a browser **after
the original HTTP request has finished**.

Example:

``` text
Alice assigns a task to Bob.

Alice -> ZF1 -> MariaDB

How does Bob's browser immediately know?
```

A normal HTTP response cannot spontaneously travel to Bob because Bob
did not make that request.

This is the problem solved by our real-time layer.

------------------------------------------------------------------------

# 3. Socket vs. WebSocket

This is the first concept every developer must understand.

A **socket** is a low-level endpoint used for network communication.

A **WebSocket** is an application-level communication protocol normally
transported over TCP.

They are related, but they are not the same thing.

``` text
Application message
       |
       v
WebSocket protocol
       |
       v
TCP
       |
       v
Network socket
       |
       v
Operating system / network
```

A useful analogy is:

``` text
TCP socket = road

WebSocket = traffic rules and vehicle format used on the road
```

Opening a TCP socket does not automatically make it a WebSocket
connection.

------------------------------------------------------------------------

# 4. PHP Sockets and JavaScript WebSocket

A browser provides this high-level JavaScript API:

``` javascript
const socket = new WebSocket('ws://localhost:8080');
```

The browser internally handles:

-   TCP connection establishment;
-   WebSocket HTTP Upgrade handshake;
-   WebSocket frame encoding;
-   client masking;
-   frame decoding;
-   ping/pong protocol behavior;
-   close frames;
-   protocol validation.

PHP's low-level socket API is different:

``` php
$socket = socket_create(
    AF_INET,
    SOCK_STREAM,
    SOL_TCP
);
```

This creates a TCP socket.

It does **not** automatically implement WebSocket.

Therefore this relationship is incorrect:

``` text
JavaScript WebSocket
        |
        | raw text
        v
PHP TCP socket
```

The correct relationship is:

``` text
JavaScript                        PHP server

WebSocket API                 WebSocket implementation
     |                                |
     v                                v
WebSocket protocol <============> WebSocket protocol
     |                                |
     v                                v
    TCP <===========================> TCP
```

Workerman provides the WebSocket implementation on the PHP side.

------------------------------------------------------------------------

# 5. How the WebSocket Protocol Starts

A WebSocket connection begins as HTTP.

Suppose JavaScript executes:

``` javascript
const socket = new WebSocket('ws://example.com:8080');
```

The browser first opens a TCP connection.

Then it sends an HTTP Upgrade request similar to:

``` http
GET / HTTP/1.1
Host: example.com:8080
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Key: RANDOM_VALUE
Sec-WebSocket-Version: 13
```

The server recognizes:

``` text
Upgrade: websocket
```

and calculates a response using the supplied WebSocket key and the
protocol's fixed GUID.

The server returns:

``` http
HTTP/1.1 101 Switching Protocols
Upgrade: websocket
Connection: Upgrade
Sec-WebSocket-Accept: CALCULATED_VALUE
```

The connection has now changed from HTTP handshake traffic to WebSocket
frames.

``` text
Browser
   |
   | TCP connect
   v
Server
   |
   | HTTP Upgrade request
   v
Server validates handshake
   |
   | HTTP 101 Switching Protocols
   v
Browser
   |
   +===============================+
   |      WebSocket connection     |
   +===============================+
```

------------------------------------------------------------------------

# 6. WebSocket Frames

After the handshake, messages are not sent as arbitrary raw text.

WebSocket uses frames.

Conceptually:

``` text
+--------------------------------------+
| FIN / RSV / Opcode                   |
+--------------------------------------+
| Mask flag / Payload length           |
+--------------------------------------+
| Extended length (when necessary)     |
+--------------------------------------+
| Masking key (client -> server)       |
+--------------------------------------+
| Payload                              |
+--------------------------------------+
```

Important concepts include:

-   `FIN` --- whether this is the final fragment;
-   opcode --- text, binary, close, ping, pong, etc.;
-   mask bit;
-   payload length;
-   masking key;
-   payload.

Browser-to-server frames are masked.

The server must unmask them before application code sees the original
payload.

Workerman performs this protocol work for us.

------------------------------------------------------------------------

# 7. WebSocket Communication Flow

Suppose JavaScript sends:

``` javascript
socket.send('Hello PHP');
```

The actual flow is closer to:

``` text
"Hello PHP"
     |
JavaScript WebSocket API
     |
Browser WebSocket encoder
     |
WebSocket frame
     |
TCP
     |
Network
     |
PHP WebSocket server
     |
WebSocket frame decoder
     |
"Hello PHP"
```

The reverse direction is:

``` text
PHP application message
     |
WebSocket frame encoder
     |
TCP
     |
Browser WebSocket decoder
     |
JavaScript onmessage()
```

------------------------------------------------------------------------

# 8. Educational Raw PHP WebSocket Server

The following example is intentionally small and educational.

**Do not treat it as a production WebSocket implementation.**

Its purpose is to demonstrate what a framework such as Workerman saves
us from implementing.

Create:

``` text
raw-websocket-server.php
```

``` php
<?php

$host = '0.0.0.0';
$port = 8080;

$server = socket_create(
    AF_INET,
    SOCK_STREAM,
    SOL_TCP
);

if ($server === false) {
    throw new RuntimeException(
        'socket_create failed: ' .
        socket_strerror(socket_last_error())
    );
}

socket_set_option(
    $server,
    SOL_SOCKET,
    SO_REUSEADDR,
    1
);

if (!socket_bind($server, $host, $port)) {
    throw new RuntimeException(
        'socket_bind failed: ' .
        socket_strerror(socket_last_error($server))
    );
}

if (!socket_listen($server)) {
    throw new RuntimeException(
        'socket_listen failed.'
    );
}

echo "Listening on {$host}:{$port}\n";

while (true) {

    $client = socket_accept($server);

    if ($client === false) {
        continue;
    }

    echo "Client connected\n";

    /*
     * Educational simplification:
     * assumes the full HTTP handshake arrives in one read.
     */
    $request = socket_read(
        $client,
        8192,
        PHP_BINARY_READ
    );

    if (!$request) {
        socket_close($client);
        continue;
    }

    if (!performWebSocketHandshake($client, $request)) {
        socket_close($client);
        continue;
    }

    echo "WebSocket handshake completed\n";

    while (true) {

        /*
         * Educational simplification:
         * a real implementation must buffer TCP streams and
         * handle partial/multiple frames correctly.
         */
        $buffer = @socket_read(
            $client,
            8192,
            PHP_BINARY_READ
        );

        if ($buffer === false || $buffer === '') {
            break;
        }

        $message = decodeSimpleClientFrame($buffer);

        if ($message === null) {
            break;
        }

        echo "Received: {$message}\n";

        $reply = encodeSimpleServerTextFrame(
            'PHP received: ' . $message
        );

        @socket_write(
            $client,
            $reply,
            strlen($reply)
        );
    }

    socket_close($client);

    echo "Client disconnected\n";
}

function performWebSocketHandshake($client, $request)
{
    if (!preg_match(
        '/Sec-WebSocket-Key:\s*(.+)\r\n/i',
        $request,
        $matches
    )) {
        return false;
    }

    $key = trim($matches[1]);

    $guid =
        '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    $accept = base64_encode(
        sha1($key . $guid, true)
    );

    $response =
        "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: {$accept}\r\n" .
        "\r\n";

    socket_write(
        $client,
        $response,
        strlen($response)
    );

    return true;
}

function decodeSimpleClientFrame($frame)
{
    if (strlen($frame) < 6) {
        return null;
    }

    $secondByte = ord($frame[1]);

    $masked = ($secondByte & 0x80) !== 0;

    $length = $secondByte & 0x7F;

    $offset = 2;

    if ($length === 126) {

        if (strlen($frame) < 8) {
            return null;
        }

        $length = unpack(
            'n',
            substr($frame, 2, 2)
        )[1];

        $offset = 4;

    } elseif ($length === 127) {

        /*
         * Deliberately unsupported in this training example.
         */
        return null;
    }

    if (!$masked) {
        /*
         * Browser -> server frames must be masked.
         */
        return null;
    }

    $mask = substr(
        $frame,
        $offset,
        4
    );

    $offset += 4;

    $payload = substr(
        $frame,
        $offset,
        $length
    );

    if (strlen($payload) < $length) {
        return null;
    }

    $decoded = '';

    for ($i = 0; $i < $length; $i++) {

        $decoded .=
            $payload[$i] ^
            $mask[$i % 4];
    }

    return $decoded;
}

function encodeSimpleServerTextFrame($payload)
{
    $length = strlen($payload);

    $frame = chr(0x81);

    if ($length <= 125) {

        $frame .= chr($length);

    } elseif ($length <= 65535) {

        $frame .= chr(126);
        $frame .= pack('n', $length);

    } else {

        throw new RuntimeException(
            'Payload too large for training example.'
        );
    }

    return $frame . $payload;
}
```

Run:

``` bash
php raw-websocket-server.php
```

A JavaScript client can connect to it, but the example is deliberately
incomplete.

------------------------------------------------------------------------

# 9. What Is Missing from the Raw PHP Example?

A real WebSocket server must correctly handle far more than the training
code above.

Examples include:

-   TCP stream buffering;
-   partial HTTP handshakes;
-   multiple frames in one TCP read;
-   a frame split across multiple TCP reads;
-   64-bit payload lengths;
-   fragmented messages;
-   continuation frames;
-   binary messages;
-   UTF-8 validation;
-   ping;
-   pong;
-   close handshake;
-   close status codes;
-   protocol errors;
-   maximum payload limits;
-   slow clients;
-   partial writes;
-   send buffering;
-   non-blocking I/O;
-   thousands of connections;
-   connection timeouts;
-   TLS;
-   process supervision;
-   worker crashes;
-   signals;
-   logging;
-   graceful reload;
-   resource cleanup;
-   authentication;
-   origin validation.

This is the main lesson:

> **Creating a socket is easy. Building and operating a correct
> WebSocket server is much harder.**

------------------------------------------------------------------------

# 10. A TCP Stream Is Not a Message Queue

This is another important reason raw socket programming is difficult.

Suppose one side sends:

``` text
ABC
DEF
```

You must not assume the other side will receive:

``` text
read #1 = ABC
read #2 = DEF
```

TCP is a byte stream.

It might arrive as:

``` text
read #1 = A
read #2 = BCDEF
```

or:

``` text
read #1 = ABCDEF
```

or other valid divisions.

Protocols therefore require framing/buffering logic.

WebSocket defines framing, and Workerman's protocol implementation
handles it.

------------------------------------------------------------------------

# 11. Blocking Server Problem

Our educational server uses:

``` php
$client = socket_accept($server);
```

then handles one client in a loop.

While that client is being handled, a simplistic implementation may not
efficiently serve many other clients.

A production server needs an event-driven/non-blocking architecture or
another concurrency model.

Workerman provides an event loop designed for long-running network
connections.

------------------------------------------------------------------------

# 12. Why Use Workerman?

Instead of implementing:

``` text
TCP listener
+
non-blocking event loop
+
WebSocket handshake
+
frame encoder
+
frame decoder
+
connection lifecycle
+
worker processes
+
timers
+
signals
+
process management
```

we can write:

``` php
$worker = new Worker(
    'websocket://0.0.0.0:8080'
);
```

and focus on application behavior.

Workerman does not remove all engineering responsibilities.

We still own:

-   authentication;
-   authorization;
-   event design;
-   connection-to-user mapping;
-   application security;
-   notification semantics;
-   database state;
-   retries;
-   monitoring;
-   deployment.

But it removes a large amount of low-level networking work.

------------------------------------------------------------------------

# 13. Pure PHP Socket vs. Workerman

  Area                       Raw PHP Sockets   Workerman
  -------------------------- ----------------- -----------
  TCP socket access          Manual            Managed
  WebSocket handshake        Manual            Built in
  Frame parsing              Manual            Built in
  Frame creation             Manual            Built in
  Event loop                 Must design       Provided
  Connection object          Must design       Provided
  Worker processes           Must design       Provided
  Timers                     Must design       Provided
  Start/stop/reload/status   Must design       Provided
  Error callbacks            Must design       Provided
  Application auth           Developer         Developer
  Business logic             Developer         Developer
  Database reliability       Developer         Developer

The framework solves transport/runtime problems. It does not replace
application architecture.

------------------------------------------------------------------------

# 14. Workerman Requirements

Workerman runs using **PHP CLI**, independently of Apache/PHP-FPM.

For Linux, the official documentation requires the relevant PHP
environment and identifies `pcntl` and `posix` as important
dependencies. The `event` extension is recommended for
higher-concurrency long-connection workloads but is not mandatory.

For our production environment:

``` text
CentOS/Linux
    |
PHP CLI
    |
pcntl
posix
optional/recommended event extension
    |
Workerman
```

Windows can be useful for development, but Linux is the preferred
production environment.

------------------------------------------------------------------------

# 15. Installing Workerman

The standard Composer installation is:

``` bash
composer require workerman/workerman
```

This produces:

``` text
project/
|
+-- vendor/
|   |
|   +-- workerman/
|
+-- composer.json
+-- composer.lock
```

The application entry point loads:

``` php
require_once __DIR__ . '/vendor/autoload.php';
```

For an offline-controlled environment, dependencies should be acquired
and approved in a connected build/staging environment and then
transferred according to the organization's software-supply process. Pin
and test the exact Workerman version selected for PHP 7.4 rather than
assuming the newest release is compatible with every legacy environment.

------------------------------------------------------------------------

# 16. First Workerman WebSocket Server

Create:

``` text
realtime/server.php
```

``` php
<?php

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

require_once __DIR__ . '/../vendor/autoload.php';

$ws = new Worker(
    'websocket://0.0.0.0:8080'
);

/*
 * Keep one process while learning and while using
 * an in-memory user -> connection map.
 */
$ws->count = 1;

$ws->name = 'S3RealtimeWebSocket';

$ws->onWorkerStart = function (Worker $worker) {
    echo "Worker started: {$worker->id}\n";
};

$ws->onConnect = function (
    TcpConnection $connection
) {
    echo "Connected: {$connection->id}\n";
};

$ws->onMessage = function (
    TcpConnection $connection,
    $data
) {
    echo "Received: {$data}\n";

    $connection->send(
        json_encode(
            array(
                'event' => 'echo',
                'data'  => $data,
            )
        )
    );
};

$ws->onClose = function (
    TcpConnection $connection
) {
    echo "Disconnected: {$connection->id}\n";
};

$ws->onError = function (
    TcpConnection $connection,
    $code,
    $message
) {
    echo "Connection error {$code}: {$message}\n";
};

Worker::runAll();
```

The important difference from Workerman's WebSocket-client syntax is:

``` text
WebSocket server:
websocket://0.0.0.0:8080
```

------------------------------------------------------------------------

# 17. Starting and Stopping Workerman

Run from the command line.

Debug/foreground:

``` bash
php realtime/server.php start
```

Daemon/background:

``` bash
php realtime/server.php start -d
```

Stop:

``` bash
php realtime/server.php stop
```

Restart:

``` bash
php realtime/server.php restart
```

Smooth reload:

``` bash
php realtime/server.php reload
```

Status:

``` bash
php realtime/server.php status
```

Connection status:

``` bash
php realtime/server.php connections
```

During development, foreground/debug mode is useful because `echo`,
warnings and errors are visible in the terminal.

Production should use appropriate process supervision and logging.

------------------------------------------------------------------------

# 18. Workerman Execution Flow

When executing:

``` bash
php realtime/server.php start
```

the conceptual flow is:

``` text
PHP CLI
  |
  v
Load vendor/autoload.php
  |
  v
Create Worker objects
  |
  v
Worker::runAll()
  |
  v
Master/process initialization
  |
  v
Worker process starts
  |
  v
Open listening socket
  |
  v
Event loop
  |
  +---- client connects ------> onConnect
  |
  +---- message arrives ------> onMessage
  |
  +---- error ----------------> onError
  |
  +---- connection closes ----> onClose
  |
  +---- timer expires --------> timer callback
  |
  +---- wait for next event
```

Workerman stays alive instead of ending after one request.

------------------------------------------------------------------------

# 19. Understanding `$connection`

When a browser connects, Workerman represents that connection with a
`TcpConnection` object.

``` php
$ws->onConnect = function (
    TcpConnection $connection
) {
    echo $connection->id;
};
```

Later:

``` php
$connection->send('hello');
```

sends data over that particular client's WebSocket connection.

Application metadata can be associated with a connection after
authentication:

``` php
$connection->userId = 123;
$connection->authenticated = true;
```

This metadata exists in the memory of the worker process that owns the
connection.

------------------------------------------------------------------------

# 20. User-to-Connection Manager

One user may have several connections.

Do not design:

``` text
user 123 -> one socket
```

Design:

``` text
user 123
   |
   +-- connection A
   +-- connection B
   +-- connection C
```

A simple single-worker training implementation:

``` php
<?php

class ConnectionManager
{
    private $users = array();

    public function add(
        $userId,
        TcpConnection $connection
    ) {
        $userId = (int) $userId;

        if (!isset($this->users[$userId])) {
            $this->users[$userId] = array();
        }

        $this->users[$userId][$connection->id] =
            $connection;
    }

    public function remove(
        $userId,
        TcpConnection $connection
    ) {
        $userId = (int) $userId;

        if (!isset($this->users[$userId])) {
            return;
        }

        unset(
            $this->users[$userId][$connection->id]
        );

        if (!$this->users[$userId]) {
            unset($this->users[$userId]);
        }
    }

    public function sendToUser(
        $userId,
        array $message
    ) {
        $userId = (int) $userId;

        if (!isset($this->users[$userId])) {
            return;
        }

        $json = json_encode($message);

        foreach (
            $this->users[$userId] as $connection
        ) {
            $connection->send($json);
        }
    }
}
```

This is suitable only when the relevant connections and manager are in
the same process. PHP arrays are not automatically shared across
multiple Workerman worker processes.

------------------------------------------------------------------------

# 21. JavaScript Client

A basic browser client:

``` javascript
const socket = new WebSocket(
    'ws://127.0.0.1:8080'
);

socket.onopen = function () {
    console.log('WebSocket connected');

    socket.send(JSON.stringify({
        event: 'client.hello',
        data: {
            message: 'Hello Workerman'
        }
    }));
};

socket.onmessage = function (event) {
    console.log(
        'Received:',
        event.data
    );
};

socket.onerror = function (event) {
    console.error(
        'WebSocket error',
        event
    );
};

socket.onclose = function (event) {
    console.log(
        'WebSocket closed',
        event.code,
        event.reason
    );
};
```

Communication:

``` text
JavaScript                      Workerman

new WebSocket()
      |
      | handshake
      |------------------------>
      |<------------------------
      |
    onopen
      |
      | WebSocket frame
      |------------------------>
      |                     onMessage
      |
      |<------------------------
      |
  onmessage
```

------------------------------------------------------------------------

# 22. JSON Message Envelope

Do not invent a completely different format for every event.

Recommended server-to-client format:

``` json
{
    "event_id": "7d861a4f9c514842a36d04fd4cb52e18",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

Potential events:

``` text
notification.created
notification.updated
notification.read
notification.dismissed
notification.reminder
system.ping
system.notice
```

A stable envelope simplifies JavaScript dispatching.

------------------------------------------------------------------------

# 23. JavaScript Message Router

``` javascript
function handleServerMessage(rawData) {
    let message;

    try {
        message = JSON.parse(rawData);
    } catch (error) {
        console.error(
            'Invalid realtime JSON',
            error
        );
        return;
    }

    if (!message.event) {
        return;
    }

    switch (message.event) {

        case 'notification.created':
            handleNotificationCreated(
                message
            );
            break;

        case 'notification.read':
            handleNotificationRead(
                message
            );
            break;

        case 'notification.dismissed':
            handleNotificationDismissed(
                message
            );
            break;

        default:
            console.log(
                'Unknown event:',
                message.event
            );
    }
}
```

Then:

``` javascript
socket.onmessage = function (event) {
    handleServerMessage(event.data);
};
```

------------------------------------------------------------------------

# 24. Signal-Only Design

We do not normally need Workerman to push complete business data.

Prefer:

``` json
{
    "event_id": "E100",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

Then JavaScript calls:

``` text
GET /api/notifications/5001
```

Flow:

``` text
Workerman
    |
    | "notification 5001 changed"
    v
Browser
    |
    | HTTPS REST
    v
ZF1
    |
    | authentication + authorization
    v
MariaDB
```

This keeps Workerman small and keeps authoritative data/security in ZF1.

------------------------------------------------------------------------

# 25. Fetching the Notification

``` javascript
async function loadNotification(
    notificationId
) {
    const response = await fetch(
        '/api/notifications/' +
        encodeURIComponent(notificationId),
        {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        }
    );

    if (!response.ok) {
        throw new Error(
            'Unable to load notification'
        );
    }

    return await response.json();
}
```

Then:

``` javascript
async function handleNotificationCreated(
    message
) {
    try {
        const notification =
            await loadNotification(
                message.data.notification_id
            );

        showNotification(notification);

    } catch (error) {
        console.error(error);
    }
}
```

------------------------------------------------------------------------

# 26. Marking a Notification as Read

Receiving a WebSocket message does **not** automatically mean the user
read it.

When the user opens the notification:

``` text
Browser
   |
   | PUT /api/notifications/5001/read
   v
ZF1
   |
   | authenticated user
   | authorization
   | UPDATE
   v
MariaDB
```

Example JavaScript:

``` javascript
async function markAsRead(
    notificationId,
    csrfToken
) {
    const response = await fetch(
        '/api/notifications/' +
        encodeURIComponent(notificationId) +
        '/read',
        {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type':
                    'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({})
        }
    );

    if (!response.ok) {
        throw new Error(
            'Unable to mark notification read'
        );
    }
}
```

Do not trust a client-provided `user_id`. ZF1 must derive user identity
from the authenticated session/token.

------------------------------------------------------------------------

# 27. Reconnection

WebSocket connections can disappear because of:

-   Wi-Fi/network changes;
-   browser sleep;
-   laptop suspend;
-   proxy timeout;
-   server restart;
-   Workerman restart;
-   firewall/NAT timeout.

A browser should reconnect.

``` javascript
class RealtimeClient {
    constructor(url) {
        this.url = url;
        this.socket = null;
        this.retryAttempt = 0;
        this.maxDelay = 30000;
        this.closedByUser = false;
    }

    connect() {
        this.socket =
            new WebSocket(this.url);

        this.socket.onopen = () => {
            console.log(
                'Realtime connected'
            );

            this.retryAttempt = 0;

            this.synchronize();
        };

        this.socket.onmessage = (event) => {
            handleServerMessage(
                event.data
            );
        };

        this.socket.onerror = (event) => {
            console.error(
                'Realtime error',
                event
            );
        };

        this.socket.onclose = () => {
            console.log(
                'Realtime disconnected'
            );

            if (!this.closedByUser) {
                this.scheduleReconnect();
            }
        };
    }

    scheduleReconnect() {
        this.retryAttempt++;

        const exponential =
            Math.min(
                1000 *
                Math.pow(
                    2,
                    this.retryAttempt - 1
                ),
                this.maxDelay
            );

        const jitter =
            Math.floor(
                Math.random() * 1000
            );

        const delay =
            exponential + jitter;

        console.log(
            'Reconnect in ' +
            delay +
            ' ms'
        );

        setTimeout(
            () => this.connect(),
            delay
        );
    }

    async synchronize() {
        try {
            const response = await fetch(
                '/api/notifications?unread=1',
                {
                    credentials:
                        'same-origin'
                }
            );

            if (!response.ok) {
                return;
            }

            const data =
                await response.json();

            synchronizeNotifications(
                data
            );

        } catch (error) {
            console.error(
                'Synchronization failed',
                error
            );
        }
    }

    close() {
        this.closedByUser = true;

        if (this.socket) {
            this.socket.close();
        }
    }
}
```

The critical detail is:

``` text
reconnect
   |
   v
REST synchronization
```

WebSocket is not authoritative history.

------------------------------------------------------------------------

# 28. Authentication

Never authenticate a connection using only:

``` json
{
    "user_id": 123
}
```

A malicious client could send another user's ID.

A better architecture is:

``` text
Browser
   |
   | existing authenticated HTTPS session
   v
ZF1
   |
   | issue short-lived realtime credential
   v
Browser
   |
   | WebSocket handshake + credential
   v
Workerman
   |
   | validate credential
   v
connection.userId = authenticated user
```

The exact credential mechanism should be implemented according to the
application's authentication design.

Important principles:

1.  Credentials should be short-lived where practical.
2.  Do not log secrets/tokens.
3.  Validate expiry.
4.  Validate signature/authenticity.
5.  Associate identity server-side.
6.  Reject unauthenticated connections.
7.  Continue performing business authorization in ZF1.

------------------------------------------------------------------------

# 29. Origin Validation

A WebSocket server should consider which web origins are permitted to
establish browser connections.

The browser sends an `Origin` header during the handshake.

The server can validate that the connection originates from an approved
application origin.

Origin validation is useful defense-in-depth, but it does not replace
authentication.

Because Workerman's handshake APIs differ across major versions, use the
API appropriate to the exact Workerman release selected for PHP 7.4.

------------------------------------------------------------------------

# 30. WSS

Development may use:

``` text
ws://
```

Production should normally use:

``` text
wss://
```

Conceptually:

``` text
WebSocket
    |
TLS
    |
TCP
```

A common deployment is:

``` text
Browser
   |
   | wss://
   v
Nginx/Apache TLS endpoint
   |
   | WebSocket proxy
   v
Workerman
```

This keeps certificate/TLS handling in the normal web/reverse-proxy
layer.

------------------------------------------------------------------------

# 31. Workerman + ZF1 Responsibility Boundary

Our recommended separation is:

``` text
ZF1
---------------------------------
Authentication/business identity
Authorization
Business rules
MariaDB CRUD
Notification creation
Read/unread
Dismiss
Reminder
REST API
Transactional outbox


Workerman
---------------------------------
WebSocket connections
Realtime credential validation
Connection lifecycle
user -> connections routing
Internal event reception
Signal delivery
Heartbeat
Connection cleanup
```

Do not gradually move all ZF1 business logic into Workerman.

------------------------------------------------------------------------

# 32. Recommended ZF1 Notification Flow

Suppose Alice assigns a task to Bob.

``` text
Alice
   |
   | POST /api/tasks/100/assign
   v
ZF1
   |
   | authenticate Alice
   | authorize operation
   | update task
   | create notification
   | create outbox event
   v
MariaDB
   |
   | COMMIT
   v
ZF1 returns success
```

Separately:

``` text
MariaDB realtime_outbox
        |
        v
    Dispatcher
        |
        | internal event
        v
    Workerman
        |
        | WebSocket
        v
      Bob
        |
        | GET notification
        v
       ZF1
```

------------------------------------------------------------------------

# 33. Why Use a Transactional Outbox?

An unsafe design is:

``` text
ZF1
 |
 | COMMIT notification
 v
MariaDB

ZF1
 |
 | send TCP signal
 X Workerman unavailable
```

The notification exists, but the realtime event may be forgotten unless
another recovery mechanism exists.

A more reliable design stores an outbox event in the same transaction:

``` text
BEGIN

INSERT notification 5001

INSERT realtime_outbox E100

COMMIT
```

Now:

``` text
notification 5001
and
event E100
```

are durable together.

------------------------------------------------------------------------

# 34. Example Outbox Table

``` sql
CREATE TABLE realtime_outbox (
    id              BIGINT UNSIGNED
                    NOT NULL AUTO_INCREMENT,

    event_id        CHAR(32)
                    NOT NULL,

    event_name      VARCHAR(100)
                    NOT NULL,

    user_id         BIGINT UNSIGNED
                    NOT NULL,

    payload         TEXT
                    NOT NULL,

    status          VARCHAR(20)
                    NOT NULL DEFAULT 'pending',

    retry_count     INT UNSIGNED
                    NOT NULL DEFAULT 0,

    created_at      DATETIME
                    NOT NULL,

    processing_at   DATETIME
                    NULL,

    sent_at         DATETIME
                    NULL,

    last_error      TEXT
                    NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uq_event_id (
        event_id
    ),

    KEY idx_status_id (
        status,
        id
    )
);
```

A production implementation should additionally define atomic claiming,
stale-claim recovery, retention, and dead-letter behavior.

------------------------------------------------------------------------

# 35. ZF1 Creates Notification + Outbox Event

Example service code:

``` php
<?php

class S3_Service_Notification
{
    private $db;

    public function __construct(
        Zend_Db_Adapter_Abstract $db
    ) {
        $this->db = $db;
    }

    public function create(
        $userId,
        $type,
        $title,
        $message
    ) {
        $this->db->beginTransaction();

        try {

            $now = date(
                'Y-m-d H:i:s'
            );

            $this->db->insert(
                'notification',
                array(
                    'user_id' =>
                        (int) $userId,

                    'type' =>
                        (string) $type,

                    'title' =>
                        (string) $title,

                    'message' =>
                        (string) $message,

                    'is_read' => 0,

                    'created_at' => $now,
                )
            );

            $notificationId =
                (int)
                $this->db->lastInsertId();

            $eventId =
                bin2hex(
                    random_bytes(16)
                );

            $payload = array(
                'notification_id' =>
                    $notificationId,
            );

            $this->db->insert(
                'realtime_outbox',
                array(
                    'event_id' =>
                        $eventId,

                    'event_name' =>
                        'notification.created',

                    'user_id' =>
                        (int) $userId,

                    'payload' =>
                        json_encode($payload),

                    'status' =>
                        'pending',

                    'retry_count' =>
                        0,

                    'created_at' =>
                        $now,
                )
            );

            $this->db->commit();

            return $notificationId;

        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }
}
```

Notice that this service does **not** need to connect to Workerman
directly.

The Dispatcher performs network delivery separately.

------------------------------------------------------------------------

# 36. Dispatcher

The Dispatcher is a long-running PHP CLI process.

``` text
Outbox
  |
  v
Dispatcher
  |
  | internal TCP
  v
Workerman
```

Basic training skeleton:

``` php
<?php

define(
    'APPLICATION_PATH',
    realpath(__DIR__ . '/../application')
);

define(
    'APPLICATION_ENV',
    getenv('APPLICATION_ENV')
        ?: 'production'
);

require_once 'Zend/Application.php';

$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH .
        '/configs/application.ini'
);

$application->bootstrap();

$db =
    Zend_Db_Table::getDefaultAdapter();

echo "Dispatcher started\n";

while (true) {

    try {
        dispatchBatch($db);
    } catch (Exception $e) {
        error_log(
            'Dispatcher error: ' .
            $e->getMessage()
        );
    }

    usleep(500000);
}
```

The production implementation should include proper claiming, ACK
validation, retry scheduling, stale-claim recovery, logging, and
shutdown handling.

------------------------------------------------------------------------

# 37. Internal Signal Client

A simplified client used by the Dispatcher:

``` php
<?php

class S3_Realtime_InternalClient
{
    private $host;
    private $port;
    private $secret;
    private $timeout;

    public function __construct(
        $host,
        $port,
        $secret,
        $timeout = 0.5
    ) {
        $this->host = $host;
        $this->port = (int) $port;
        $this->secret = $secret;
        $this->timeout = (float) $timeout;
    }

    public function send(array $event)
    {
        $payloadJson = json_encode(
            $event,
            JSON_UNESCAPED_SLASHES
        );

        if ($payloadJson === false) {
            throw new RuntimeException(
                'Unable to encode event.'
            );
        }

        $signature = hash_hmac(
            'sha256',
            $payloadJson,
            $this->secret
        );

        $message = json_encode(
            array(
                'payload' => $event,
                'signature' => $signature,
            ),
            JSON_UNESCAPED_SLASHES
        ) . "\n";

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            'tcp://' .
            $this->host .
            ':' .
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if (!$socket) {
            return false;
        }

        stream_set_timeout(
            $socket,
            1
        );

        if (!$this->writeAll(
            $socket,
            $message
        )) {
            fclose($socket);
            return false;
        }

        $ackLine = fgets($socket);

        fclose($socket);

        if ($ackLine === false) {
            return false;
        }

        $ack = json_decode(
            trim($ackLine),
            true
        );

        return
            is_array($ack) &&
            isset($ack['event_id']) &&
            isset($ack['status']) &&
            $ack['event_id'] ===
                $event['event_id'] &&
            $ack['status'] ===
                'accepted';
    }

    private function writeAll(
        $socket,
        $data
    ) {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {

            $result = fwrite(
                $socket,
                substr(
                    $data,
                    $written
                )
            );

            if (
                $result === false ||
                $result === 0
            ) {
                return false;
            }

            $written += $result;
        }

        return true;
    }
}
```

The internal protocol uses one JSON message per line for simple framing.

------------------------------------------------------------------------

# 38. Workerman Internal Signal Listener

A useful Workerman design is to run the WebSocket listener and an
internal listener in the **same worker process** for the initial
single-process implementation.

Conceptually:

``` text
                   Workerman process

            +-------------------------+
Browser --->| WebSocket listener      |
            |                         |
ZF1/        | Internal TCP listener   |<--- Dispatcher
Dispatcher  |                         |
            | ConnectionManager       |
            +-------------------------+
```

This allows both listeners to access the same in-memory connection
manager.

A conceptual implementation:

``` php
<?php

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

require_once __DIR__ .
    '/../vendor/autoload.php';

$connections = array();

$ws = new Worker(
    'websocket://0.0.0.0:8080'
);

$ws->count = 1;

$ws->onMessage = function (
    TcpConnection $connection,
    $data
) {
    /*
     * Training placeholder.
     * Production authentication must establish
     * userId server-side.
     */
};

$ws->onClose = function (
    TcpConnection $connection
) use (&$connections) {

    if (!isset($connection->userId)) {
        return;
    }

    $userId =
        (int) $connection->userId;

    unset(
        $connections[$userId]
                    [$connection->id]
    );

    if (
        isset($connections[$userId]) &&
        !$connections[$userId]
    ) {
        unset($connections[$userId]);
    }
};

/*
 * Internal listener.
 *
 * Bind to loopback when Dispatcher and Workerman
 * are on the same server.
 */
$internal = new Worker(
    'text://127.0.0.1:8081'
);

/*
 * IMPORTANT:
 * For the initial architecture, keep this listener
 * in the same process context as the connection map.
 * A production multi-process design needs explicit IPC.
 */
$internal->count = 1;

$secret =
    getenv('REALTIME_INTERNAL_SECRET');

$internal->onMessage = function (
    TcpConnection $connection,
    $data
) use (
    &$connections,
    $secret
) {
    $envelope = json_decode(
        trim($data),
        true
    );

    if (
        !is_array($envelope) ||
        !isset($envelope['payload']) ||
        !isset($envelope['signature'])
    ) {
        $connection->send(
            json_encode(
                array(
                    'status' => 'rejected'
                )
            )
        );

        return;
    }

    $payloadJson = json_encode(
        $envelope['payload'],
        JSON_UNESCAPED_SLASHES
    );

    $expected = hash_hmac(
        'sha256',
        $payloadJson,
        $secret
    );

    if (!hash_equals(
        $expected,
        $envelope['signature']
    )) {
        $connection->send(
            json_encode(
                array(
                    'status' => 'rejected'
                )
            )
        );

        return;
    }

    $event = $envelope['payload'];

    if (
        !isset($event['event_id']) ||
        !isset($event['event']) ||
        !isset($event['user_id']) ||
        !isset($event['data'])
    ) {
        $connection->send(
            json_encode(
                array(
                    'status' => 'rejected'
                )
            )
        );

        return;
    }

    $userId =
        (int) $event['user_id'];

    $clientMessage = json_encode(
        array(
            'event_id' =>
                $event['event_id'],

            'event' =>
                $event['event'],

            'data' =>
                $event['data'],
        )
    );

    if (isset($connections[$userId])) {

        foreach (
            $connections[$userId]
            as $client
        ) {
            $client->send(
                $clientMessage
            );
        }
    }

    $connection->send(
        json_encode(
            array(
                'event_id' =>
                    $event['event_id'],

                'status' =>
                    'accepted',
            )
        )
    );
};

Worker::runAll();
```

**Training note:** The exact same-process/listener arrangement must be
verified against the selected Workerman version and process
configuration. Once multiple workers are introduced, do not assume the
PHP `$connections` array is shared.

------------------------------------------------------------------------

# 39. Internal Signal Execution Flow

``` text
ZF1 transaction
     |
     v
realtime_outbox E100
     |
     v
Dispatcher reads/claims E100
     |
     v
Build internal event
     |
     v
HMAC sign
     |
     v
TCP connect 127.0.0.1:8081
     |
     v
Workerman internal listener
     |
     +-- parse
     +-- validate schema
     +-- verify HMAC
     +-- validate timestamp/nonce policy
     +-- find user connections
     +-- send WebSocket signal
     |
     v
Return ACK E100
     |
     v
Dispatcher
     |
     v
mark outbox event sent
```

------------------------------------------------------------------------

# 40. What If WebSocket Works but the Internal Signal Socket Fails?

This is an important partial failure.

``` text
Browser <====== WebSocket ======> Workerman
                                  ^
                                  |
                                  X
                                  |
                              Dispatcher
```

From the user's perspective:

``` text
WebSocket connected        YES
Heartbeat may work         YES
Browser looks online       YES

New ZF1 realtime signals   NO
```

A notification can still be safely created:

``` text
ZF1
 |
 +-- INSERT notification
 +-- INSERT outbox event
 |
 COMMIT
```

But the Dispatcher cannot hand the event to Workerman.

The event remains pending/retryable.

When the internal path recovers:

``` text
Dispatcher
   |
   | retry E100
   v
Workerman
   |
   v
Browser
```

This is why **WebSocket health and internal-signal health must be
monitored separately**.

------------------------------------------------------------------------

# 41. ACK Does Not Mean Exactly Once

Suppose:

``` text
Dispatcher
   |
   | E100
   v
Workerman
   |
   | sends E100 to browser
   |
   | ACK
   v
Dispatcher
   |
   X crashes before DB update
```

The outbox may still require retry.

After restart:

``` text
Dispatcher -> E100 -> Workerman -> Browser
```

The browser can receive E100 twice.

This is normal.

------------------------------------------------------------------------

# 42. At-Least-Once Delivery

Our realtime signaling uses **at-least-once** delivery.

Each event has a unique immutable:

``` text
event_id
```

Example:

``` json
{
    "event_id": "E100",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

Retry:

``` json
{
    "event_id": "E100",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

Do **not** create a new event ID during retry.

Exactly-once network delivery is not required.

------------------------------------------------------------------------

# 43. Client Deduplication

A simple in-memory example:

``` javascript
const processedEventIds =
    new Set();

function processRealtimeMessage(
    message
) {
    if (!message.event_id) {
        return;
    }

    if (
        processedEventIds.has(
            message.event_id
        )
    ) {
        console.log(
            'Duplicate event ignored:',
            message.event_id
        );

        return;
    }

    processedEventIds.add(
        message.event_id
    );

    dispatchRealtimeEvent(
        message
    );
}
```

For long-running clients, the deduplication cache must be bounded rather
than growing forever. Depending on requirements, recent IDs can be kept
with timestamps/TTL, and authoritative REST synchronization still
remains the final source of truth.

------------------------------------------------------------------------

# 44. Retry Strategy

Do not retry continuously without delay.

A conceptual Dispatcher strategy:

``` text
attempt 1
   |
 failure
   |
short delay
   |
attempt 2
   |
 failure
   |
longer delay
   |
attempt 3
```

The outbox can store:

``` text
retry_count
next_attempt_at
last_error
```

Production policy should define:

-   backoff;
-   maximum retry rate;
-   stale processing recovery;
-   alert threshold;
-   dead-letter/failed state;
-   manual replay procedure.

------------------------------------------------------------------------

# 45. Fallback Mechanism

Realtime is not the only way a user learns about notifications.

Fallback:

``` text
Browser refreshes
or
WebSocket reconnects
or
Notification panel opens
       |
       v
GET /api/notifications?unread=1
       |
       v
ZF1
       |
       v
MariaDB
```

Therefore:

``` text
Workerman failure
!=
notification data loss
```

MariaDB is authoritative.

------------------------------------------------------------------------

# 46. Multi-Tab Browser Problem

Suppose a user opens five tabs.

Naively:

``` text
Tab 1 -> WebSocket
Tab 2 -> WebSocket
Tab 3 -> WebSocket
Tab 4 -> WebSocket
Tab 5 -> WebSocket
```

This can create unnecessary connections and duplicate notification
handling.

There are two valid approaches.

## Approach A --- One WebSocket per Tab

Simplest implementation.

Server supports:

``` text
user_id -> connection[]
```

Every tab receives the event and must avoid undesirable duplicate UI
behavior.

## Approach B --- Leader Tab + BroadcastChannel

One tab owns the WebSocket.

``` text
                 Workerman
                     |
                 WebSocket
                     |
                 Leader Tab
                /    |     \
               v     v      v
            Tab 2  Tab 3   Tab 4
              BroadcastChannel
```

The leader receives:

``` text
notification.created
```

and broadcasts it locally:

``` javascript
const channel =
    new BroadcastChannel(
        's3-realtime'
    );

channel.postMessage(message);
```

Other tabs:

``` javascript
const channel =
    new BroadcastChannel(
        's3-realtime'
    );

channel.onmessage = function (event) {
    processRealtimeMessage(
        event.data
    );
};
```

Leader election/failover must be designed carefully because the leader
tab can close.

For the first implementation, one WebSocket per tab may be easier.
BroadcastChannel can be introduced as an optimization.

------------------------------------------------------------------------

# 47. Multi-Device vs. Multi-Tab

Do not confuse them.

`BroadcastChannel` works between compatible browsing contexts on the
same origin/browser environment.

It does not synchronize:

``` text
Office PC
Laptop
Phone
```

Server-side multi-connection routing is still required.

``` text
User 123
   |
   +-- Office PC connection
   +-- Laptop connection
   +-- Phone connection
```

------------------------------------------------------------------------

# 48. Heartbeat

Long-lived TCP connections can become half-dead.

Example:

``` text
Browser thinks connected
Server thinks connected
Network path is actually broken
```

Heartbeat detects stale connections.

Conceptually:

``` text
Server -> ping
Client -> pong
```

or an application-level heartbeat when appropriate.

Workerman timers can be used for heartbeat logic. The exact interval
should be chosen based on proxies, network environment, expected idle
periods, and load.

------------------------------------------------------------------------

# 49. Error Handling Layers

There are several independent error domains.

## Browser Layer

Handle:

``` text
onerror
onclose
reconnect
REST synchronization
```

## Workerman Layer

Handle:

``` text
invalid message
send failure
connection close
authentication failure
internal-event rejection
unexpected exception
```

## Dispatcher Layer

Handle:

``` text
cannot connect
write failure
ACK timeout
invalid ACK
Workerman rejection
database update failure
```

## ZF1 Layer

Handle:

``` text
validation
authorization
database transaction failure
outbox insertion failure
REST errors
```

Do not treat all failures as one generic "WebSocket error."

------------------------------------------------------------------------

# 50. Monitoring Workerman

Useful Workerman commands include:

``` bash
php realtime/server.php status
```

and:

``` bash
php realtime/server.php connections
```

Operational monitoring should additionally track:

``` text
Workerman process alive?
WebSocket listener reachable?
Internal listener reachable?
Dispatcher alive?
Last successful dispatch?
Pending outbox count?
Oldest pending event age?
Retry count?
Worker restarts?
Connection count?
```

A green WebSocket listener alone is insufficient.

------------------------------------------------------------------------

# 51. Recommended Project Structure

``` text
/project
|
+-- application/
|   |
|   +-- controllers/
|   +-- models/
|   +-- services/
|       |
|       +-- Notification.php
|
+-- library/
|
+-- public/
|
+-- realtime/
|   |
|   +-- server.php
|   +-- dispatcher.php
|   +-- config.php
|   |
|   +-- ConnectionManager.php
|   +-- AuthenticationService.php
|   +-- InternalEventServer.php
|   +-- InternalClient.php
|   +-- MessageRouter.php
|   +-- HeartbeatManager.php
|
+-- vendor/
```

Keep ZF1 business services in the existing application layer.

------------------------------------------------------------------------

# 52. Recommended Production Architecture

``` text
                          CLIENTS

                +-----------+-----------+
                |                       |
              HTTPS                    WSS
                |                       |
                v                       v
         +-------------+        +---------------+
         | Apache /    |        | Reverse Proxy |
         | ZF1         |        | (if used)     |
         +------+------+        +-------+-------+
                |                       |
                |                       v
                |                  Workerman
                |                       ^
                v                       |
             MariaDB                    |
                |                       |
                | realtime_outbox       |
                v                       |
            Dispatcher -----------------+
                |
         internal TCP / ACK
```

Responsibility summary:

``` text
ZF1
  = business system

MariaDB
  = authoritative data and durable outbox

Dispatcher
  = reliable event handoff

Workerman
  = realtime connection/router service

WebSocket
  = realtime signal transport

REST
  = authoritative data retrieval and state mutation

JavaScript
  = UI, reconnect, deduplication, synchronization
```

------------------------------------------------------------------------

# 53. End-to-End Example

Alice assigns Task 100 to Bob.

## Step 1 --- Business Request

``` text
Alice Browser
    |
POST /api/tasks/100/assign
    |
    v
ZF1
```

## Step 2 --- ZF1 Transaction

``` text
BEGIN

UPDATE task

INSERT notification 5001

INSERT realtime_outbox E100

COMMIT
```

## Step 3 --- Dispatcher

``` text
E100 pending
    |
claim
    |
send internal event
    v
Workerman
```

## Step 4 --- Workerman

``` text
verify internal event
    |
find Bob's connections
    |
send:
{
  event_id: E100,
  event: notification.created,
  notification_id: 5001
}
```

## Step 5 --- Browser

``` text
onmessage
    |
deduplicate E100
    |
GET /api/notifications/5001
```

## Step 6 --- ZF1

``` text
authenticate Bob
authorize notification 5001
read MariaDB
return JSON
```

## Step 7 --- Bob Reads It

``` text
Bob clicks notification
    |
PUT /api/notifications/5001/read
    |
ZF1
    |
UPDATE notification
INSERT outbox E101
COMMIT
```

## Step 8 --- Other Bob Connections

``` text
Dispatcher
   |
E101
   |
Workerman
   |
+---------+---------+
|         |         |
Office   Laptop    Other
```

All clients can update their UI.

------------------------------------------------------------------------

# 54. Common Mistakes

## Mistake 1

> PHP socket and WebSocket are the same.

Incorrect.

WebSocket is a protocol typically operating over TCP.

## Mistake 2

> Workerman should replace our REST APIs.

Incorrect.

REST remains appropriate for authoritative business operations.

## Mistake 3

> If WebSocket delivered a popup, the notification is read.

Incorrect.

Delivery and read state are different.

## Mistake 4

> The browser can tell Workerman its user ID and we trust it.

Unsafe.

Identity must be established server-side from a validated credential.

## Mistake 5

> If Workerman is down, notification creation should fail.

Incorrect for our design.

The business transaction and outbox remain authoritative.

## Mistake 6

> An ACK guarantees exactly-once delivery.

Incorrect.

The Dispatcher can fail after Workerman accepts the event but before the
outbox is marked sent.

## Mistake 7

> Workerman workers share PHP arrays.

Incorrect.

Worker-process memory is isolated.

## Mistake 8

> Reconnecting WebSocket means the client automatically received
> everything it missed.

Incorrect.

Perform authoritative REST synchronization.

------------------------------------------------------------------------

# 55. Training Exercises

## Exercise 1

Create a Workerman echo server and JavaScript client.

Expected flow:

``` text
Browser -> "hello" -> Workerman
Browser <- "hello" <- Workerman
```

## Exercise 2

Send JSON messages and implement an event router.

## Exercise 3

Associate a test authenticated user with a connection.

## Exercise 4

Support two browser tabs for the same user.

## Exercise 5

Create a test internal signal listener and send:

``` json
{
    "event": "notification.created",
    "user_id": 123,
    "data": {
        "notification_id": 5001
    }
}
```

## Exercise 6

Stop the internal listener while keeping WebSocket connections alive.

Observe that:

``` text
WebSocket = healthy
Internal event delivery = failed
```

Then restore it and retry.

## Exercise 7

Simulate duplicate delivery using the same `event_id` and verify client
deduplication.

## Exercise 8

Stop Workerman, create an outbox event, restart Workerman, and verify
Dispatcher recovery.

## Exercise 9

Disconnect the browser, create notifications, reconnect, and verify REST
synchronization.

------------------------------------------------------------------------

# 56. Recommended Learning Order

Developers should learn in this order:

``` text
1. TCP/socket concept
        |
2. WebSocket protocol
        |
3. Raw PHP socket demonstration
        |
4. Understand raw implementation problems
        |
5. Workerman basics
        |
6. Workerman callbacks/connections
        |
7. JavaScript WebSocket client
        |
8. Authentication
        |
9. ZF1 integration
        |
10. Internal event path
        |
11. Outbox + Dispatcher
        |
12. Failure/retry behavior
        |
13. Multi-tab/multi-device
        |
14. Monitoring/security
```

This order helps developers understand **why** Workerman exists rather
than simply memorizing its API.

------------------------------------------------------------------------

# 57. Final Architecture Rules

Remember these rules:

> **Rule 1: MariaDB is the source of truth.**

> **Rule 2: ZF1 owns business logic and authorization.**

> **Rule 3: Workerman is the realtime transport/router.**

> **Rule 4: WebSocket signals that something happened; REST obtains
> authoritative state.**

> **Rule 5: Business state changes such as read/dismiss/reminder go
> through ZF1.**

> **Rule 6: Realtime events use at-least-once delivery and may be
> duplicated.**

> **Rule 7: Preserve the same immutable `event_id` across retries.**

> **Rule 8: Clients deduplicate and resynchronize after reconnect.**

> **Rule 9: Workerman failure must not destroy committed notification
> data.**

> **Rule 10: WebSocket health and internal-signal health are separate
> concerns.**

------------------------------------------------------------------------

# 58. Final Mental Model

``` text
Socket
    =
low-level network communication endpoint


TCP
    =
reliable byte-stream transport


WebSocket
    =
application protocol for persistent
bidirectional communication


Workerman
    =
PHP networking framework/runtime
that manages WebSocket/TCP connections


ZF1
    =
business application


MariaDB
    =
authoritative state


Outbox
    =
durable record that an event
needs realtime dispatch


Dispatcher
    =
process that moves outbox events
to Workerman


event_id
    =
stable identity for duplicate detection


REST
    =
authoritative application API


WebSocket
    =
realtime signal channel
```

------------------------------------------------------------------------

# 59. Workerman Compared with Other PHP WebSocket Technologies

This section compares Workerman with other PHP technologies that can be used to build WebSocket or real-time servers.

The comparison is especially important for our project because our current application uses:

```text
PHP 7.4
Zend Framework 1
MariaDB
CentOS/Linux
Existing REST APIs
No Redis in the initial realtime design
```

The objective is not to find the theoretically most advanced asynchronous PHP platform. The objective is to understand the architectural differences and select a technology that can be introduced beside our existing ZF1 application with acceptable operational and development complexity.

> **Version warning:** PHP requirements change between major releases. Current Workerman 5.x requires PHP 8.1 or later. Our PHP 7.4 application therefore requires a compatible Workerman 4.x release to be explicitly pinned and tested. Do not run an unrestricted `composer require workerman/workerman` on the PHP 7.4 production environment and assume that the newest major version will install.

## 59.1 Technologies Compared

We will compare:

1. Workerman
2. Ratchet / ReactPHP
3. AMPHP WebSocket Server
4. OpenSwoole
5. Swow
6. Wrench
7. PHP-Socket.IO

These technologies are not identical categories.

For example:

```text
Workerman
    = event-driven network application framework/runtime

Ratchet
    = WebSocket server library built around ReactPHP components

AMPHP WebSocket Server
    = WebSocket server component in the Amp/Revolt async ecosystem

OpenSwoole
    = compiled PHP extension/runtime with event loop and coroutines

Swow
    = compiled coroutine/concurrent-I/O engine

Wrench
    = comparatively small WebSocket implementation using PHP sockets

PHP-Socket.IO
    = Socket.IO-compatible server implementation built on Workerman
```

This difference in abstraction level is important when comparing them.

## 59.2 High-Level Comparison Table

| Technology | Architecture | Current PHP Requirement* | Main Dependencies | Ease for Our Team | Performance Model | Current Project Fit |
|---|---|---:|---|---|---|---|
| **Workerman 4.x** | Long-running event-driven PHP workers | Compatible branch required for PHP 7.4 | PHP CLI; POSIX/PCNTL on Linux; optional event extension | High | Non-blocking event loop, multi-process workers | **Strong fit** |
| **Ratchet / ReactPHP** | Component-oriented WebSocket layer on ReactPHP event loop | Version/fork dependent; RFC6455 component supports PHP >=7.4 | ReactPHP, PSR-7, RFC6455 and Symfony-related components depending package | Medium | ReactPHP event loop | Possible, but more component wiring |
| **AMPHP WebSocket Server 4** | WebSocket handler on Amp HTTP server and Revolt event loop | PHP >=8.1 | Amp 3, HTTP Server, Socket, WebSocket, Revolt, PSR logging | Medium/Low for current team | Fibers + event-driven async I/O | Not compatible with current PHP 7.4 |
| **OpenSwoole 26.x** | Native compiled extension, event loop, coroutine runtime | PHP >=7.4; PHP 8+ recommended | OpenSwoole extension/toolchain | Medium/Low | Native extension + coroutines | Technically possible, but larger operational/model change |
| **Swow 2 alpha** | Native compiled coroutine/concurrent-I/O engine | PHP >=8.0 | Swow extension/runtime plus Composer packages | Low for current environment | Native coroutine engine | Not compatible with PHP 7.4 |
| **Wrench 1.9** | Small PHP WebSocket implementation using sockets | PHP ^7.4.15 or ^8.0.2 | `ext-sockets`, PSR logger, polyfill | Medium | PHP socket-based implementation | Compatible, but less complete operational framework |
| **PHP-Socket.IO 3 beta** | Socket.IO protocol implementation on Workerman | PHP >=7.4; Workerman >=4 <5 | Workerman 4, Workerman Channel | Medium | Workerman underneath | Only if Socket.IO semantics are specifically required |

\* Requirements shown here reflect the researched releases available when this training document was updated. Always re-check the exact version before installation.

## 59.3 Workerman

### Architecture

Workerman is an asynchronous event-driven network application framework.

```text
PHP CLI
   |
Workerman master/process model
   |
worker process
   |
event loop
   |
+-- WebSocket connections
+-- TCP connections
+-- timers
+-- callbacks
```

Its API lets developers work with high-level events such as:

```php
$worker->onConnect
$worker->onMessage
$worker->onClose
$worker->onError
```

rather than manually parsing TCP and WebSocket frames.

### Performance

Workerman is designed for persistent high-concurrency network applications. It uses an event-driven model and can use multiple worker processes. An event extension can improve performance for high-concurrency long-connection workloads.

Actual capacity must be benchmarked on our hardware and application workload. We should not copy connection-count claims from unrelated benchmarks into our production capacity plan.

### Ease of Use

For our team, the programming model is relatively direct:

```php
$worker = new Worker(
    'websocket://0.0.0.0:8080'
);

$worker->onMessage = function (
    $connection,
    $data
) {
    $connection->send($data);
};
```

This is close to the callback/event concepts already introduced in this training guide.

### Maintenance and Community

Workerman is actively maintained. Current Workerman 5 releases continue to receive updates and the package has a substantial install/user base.

However, the current major version has moved beyond our PHP 7.4 runtime.

### PHP Version

Current Workerman 5.2.x requires PHP 8.1 or later.

Our system therefore needs:

```text
PHP 7.4
   |
   v
Workerman 4.x
```

with the exact release pinned and tested.

### Dependencies

Workerman is comparatively self-contained, but Linux operation depends on the appropriate PHP CLI environment. Current releases document POSIX/PCNTL requirements, and an event-oriented extension may be used for improved performance.

### Advantages

- simple event/callback model;
- native WebSocket and TCP server support;
- suitable for a separate realtime service;
- process-management commands are built into the ecosystem;
- supports custom internal TCP protocols;
- good match for our signal-router architecture;
- does not require moving ZF1 business logic into the realtime process.

### Disadvantages

- long-running PHP requires different operational discipline from Apache request PHP;
- worker memory is isolated across processes;
- blocking code can block a worker;
- current Workerman 5.x cannot run on PHP 7.4;
- scaling to multiple processes/servers requires explicit connection-routing/IPC design.

### Suitability for Our ZF1 System

Workerman 4.x remains a strong match for our current architecture because it can be deployed beside ZF1:

```text
ZF1 + MariaDB
      |
 transactional outbox
      |
 Dispatcher
      |
 Workerman
      |
 WebSocket
```

The existing application does not have to become asynchronous.

---

## 59.4 Ratchet / ReactPHP

### Architecture

Ratchet is a PHP WebSocket library traditionally built around ReactPHP's event-driven components.

Conceptually:

```text
Ratchet WebSocket layer
        |
RFC6455 protocol handling
        |
ReactPHP socket/event loop
        |
TCP
```

Ratchet is more component-oriented than the simple Workerman `Worker` abstraction.

A typical Ratchet-style application defines methods such as:

```text
onOpen()
onMessage()
onClose()
onError()
```

and composes the WebSocket server from supporting components.

### Performance

Ratchet benefits from ReactPHP's asynchronous event loop and non-blocking sockets.

For a notification system it can provide adequate performance, but performance should be measured rather than inferred from library architecture alone.

### Ease of Use

Ratchet's basic examples are understandable, but the developer is exposed to more of the ReactPHP/component stack.

For a team new to event-driven PHP:

```text
Workerman
    -> fewer concepts to get first server running

Ratchet/ReactPHP
    -> more explicit composition and ecosystem concepts
```

This is not necessarily bad. ReactPHP is flexible and widely used, but it creates a broader learning surface.

### Maintenance Status

The Ratchet ecosystem requires careful version selection.

The RFC6455 protocol package is active and its current 0.4.1 release supports PHP >=7.4. The broader Ratchet server package has also seen community forks aimed at newer Symfony/PHP compatibility, which is a sign that dependency/version selection needs more attention than simply installing an arbitrary package named `ratchet`.

### Community Support

ReactPHP itself has a large ecosystem and very high package usage. Ratchet has been one of the best-known PHP WebSocket projects for many years.

### PHP Version

The current `ratchet/rfc6455` protocol component supports PHP >=7.4.

The exact complete Ratchet server package/fork and its dependency versions must be checked before adopting it for our PHP 7.4 application.

### Dependencies

A Ratchet deployment commonly involves several components, including:

```text
React event loop
React socket
Ratchet RFC6455
PSR-7 implementation
HTTP/routing components
```

depending on the selected Ratchet package/version.

### Advantages

- established WebSocket concepts and examples;
- based on the mature ReactPHP async ecosystem;
- composable architecture;
- protocol component currently supports PHP 7.4;
- useful if the team already uses ReactPHP.

### Disadvantages

- more dependencies/component wiring;
- package/fork/version landscape needs careful evaluation;
- long-running event-loop programming is still required;
- no automatic solution to our ZF1 outbox/Dispatcher/business architecture;
- multi-process/distributed routing remains an application architecture concern.

### Suitability for Our ZF1 System

Ratchet can implement our realtime service, but it does not provide a compelling simplification over Workerman for our current requirements.

Our service primarily needs:

```text
accept connections
authenticate
map user -> connections
receive internal signals
push small events
heartbeat
```

Workerman gives us a more direct operational model for those tasks.

---

## 59.5 AMPHP WebSocket Server

### Architecture

AMPHP is an asynchronous PHP ecosystem designed around modern concurrency concepts. Current Amp 3 components use PHP fibers and the Revolt event loop.

The WebSocket server is integrated with Amp's HTTP server:

```text
Amp HTTP Server
      |
WebSocket RequestHandler
      |
Amp WebSocket
      |
Amp Socket
      |
Revolt event loop
```

This is a broader asynchronous application stack rather than a single-purpose WebSocket class.

### Performance

AMPHP is designed for non-blocking concurrent I/O and modern asynchronous PHP applications.

Its architecture can be very capable for services that already use Amp.

### Ease of Use

For developers new to asynchronous PHP, the learning curve is higher than our proposed Workerman usage because developers need to understand:

- Amp;
- fibers/concurrency;
- Revolt;
- Amp HTTP server;
- Amp socket abstractions;
- WebSocket handlers.

This can be worthwhile for a new modern async service, but our goal is to add a narrow realtime layer to a legacy ZF1 system.

### Maintenance Status

AMPHP remains an active ecosystem. Its socket package received releases in 2026. The current WebSocket server major release is built for the Amp 3 generation.

### Community Support

AMPHP has a mature open-source ecosystem with substantial package adoption, especially among developers building asynchronous PHP systems.

### PHP Version

Current `amphp/websocket-server` requires:

```text
PHP >= 8.1
```

Therefore it is not compatible with our current PHP 7.4 runtime.

### Dependencies

The current server depends on several Amp packages, including:

```text
amphp/amp
amphp/byte-stream
amphp/http
amphp/http-server
amphp/socket
amphp/websocket
revolt/event-loop
psr/log
```

### Advantages

- modern asynchronous architecture;
- fiber-based programming model;
- strong composable async ecosystem;
- suitable for new PHP 8.1+ asynchronous services;
- integrates naturally with Amp HTTP services.

### Disadvantages

- incompatible with PHP 7.4;
- larger conceptual change for our current staff;
- broader dependency graph;
- unnecessarily large modernization step if all we need is a realtime notification router.

### Suitability for Our ZF1 System

For the current PHP 7.4 deployment:

```text
AMPHP WebSocket Server 4
        X
PHP 7.4
```

It should therefore not be selected for the current implementation.

It could be reconsidered after a future PHP/platform modernization.

---

## 59.6 OpenSwoole

### Architecture

OpenSwoole is fundamentally different from Workerman.

It is a compiled PHP extension that adds a high-performance asynchronous server and coroutine runtime.

```text
PHP application
      |
OpenSwoole API
      |
OpenSwoole native extension
      |
event loop / coroutines / network I/O
      |
operating system
```

A WebSocket server runs on top of this native runtime.

### Performance

OpenSwoole is designed for high-performance network services and coroutine-based concurrent I/O.

Because much of the runtime is implemented as a native extension, it can provide excellent performance characteristics.

However:

> Higher theoretical performance does not automatically make a technology the best architectural fit.

Our notification service is primarily a lightweight signal router, not a high-computation realtime platform.

### Ease of Use

The server API itself can be concise, but adopting OpenSwoole introduces new concepts:

- compiled PHP extension installation;
- coroutine-aware programming;
- long-running application lifecycle;
- coroutine-safe dependency behavior;
- different debugging/operations considerations.

This is a larger change than introducing Workerman as a PHP library/service.

### Maintenance Status

OpenSwoole is actively maintained. Version 26.2.0 was released in 2026 with PHP 8.5 support and other runtime improvements.

### Community Support

OpenSwoole has an established community in the asynchronous/high-performance PHP space, though it represents a more specialized runtime model than ordinary Composer-only PHP libraries.

### PHP Version

Current OpenSwoole documentation lists:

```text
PHP >= 7.4
```

while recommending PHP 8+.

This means it is technically compatible with our PHP 7.4 baseline, subject to testing the exact extension release against our environment.

### Dependencies

Unlike a pure Composer library, OpenSwoole requires installing a native extension and build/runtime prerequisites.

This changes server provisioning:

```text
ordinary PHP deployment
        |
        +-- PHP code

OpenSwoole deployment
        |
        +-- PHP code
        +-- native OpenSwoole extension
        +-- OS/compiler/package compatibility
```

### Advantages

- strong performance potential;
- coroutine model;
- broad async server capabilities;
- native WebSocket/HTTP/network features;
- current documentation still supports PHP 7.4.

### Disadvantages

- native extension deployment;
- greater operational complexity;
- more specialized programming model;
- larger learning curve;
- legacy libraries must be evaluated in a long-running/coroutine environment;
- more technology than our narrow realtime router currently requires.

### Suitability for Our ZF1 System

OpenSwoole is technically viable but is a larger architectural and operational commitment.

For a future platform built heavily around asynchronous services, it deserves consideration.

For our current goal:

```text
Add realtime notifications
without rewriting ZF1
```

Workerman provides a simpler transition.

---

## 59.7 Swow

### Architecture

Swow is a coroutine-based concurrent I/O engine for PHP and includes native components.

Its design is closer to a runtime/concurrency platform than a small WebSocket library.

```text
PHP
 |
Swow coroutine/concurrency engine
 |
native extension/runtime
 |
network I/O
```

### Performance

Swow is designed for concurrent I/O and high-performance asynchronous workloads.

As with OpenSwoole, direct benchmark results depend on workload, server configuration and application design.

### Ease of Use

Adopting Swow requires learning a coroutine-oriented model and deploying the supporting extension/runtime.

For developers currently maintaining ZF1 request/response code, this is a substantial conceptual jump.

### Maintenance Status

Swow is actively developed. The researched 2.0 release line is still marked alpha, so production adoption should consider release maturity in addition to performance/features.

### Community Support

Swow has meaningful adoption in the modern asynchronous PHP ecosystem but a smaller and more specialized audience than conventional PHP request/response frameworks.

### PHP Version

The researched Swow 2.0 alpha package requires:

```text
PHP >= 8.0
```

Our current PHP 7.4 environment therefore cannot use that release.

### Dependencies

Swow involves a native extension/runtime plus Composer packages.

### Advantages

- modern coroutine/concurrency design;
- high-performance I/O focus;
- useful for modern asynchronous PHP services;
- supports broader concurrent programming patterns than a WebSocket-only library.

### Disadvantages

- incompatible with PHP 7.4;
- native-extension operational requirements;
- larger learning curve;
- researched 2.0 line is alpha;
- unnecessary complexity for our current notification signal router.

### Suitability for Our ZF1 System

Not suitable for the current PHP 7.4 implementation.

It is better considered as part of a future PHP/runtime modernization study.

---

## 59.8 Wrench

### Architecture

Wrench describes itself as a simple PHP WebSocket implementation.

Its architecture is much closer to PHP socket programming than Workerman's broader worker/process framework.

```text
Application
    |
Wrench WebSocket implementation
    |
PHP ext-sockets
    |
TCP
```

### Performance

Wrench can remove the need to manually implement RFC6455 framing and handshake behavior, but it should not be confused with a complete Workerman-style application/process runtime.

Its suitability at scale should be established through testing.

### Ease of Use

For developers who want a relatively small WebSocket implementation, Wrench can be easier to understand than a large asynchronous ecosystem.

However, the application still has to solve more of the surrounding operational architecture itself.

### Maintenance Status

Wrench is actively packaged; version 1.9.2 was published in July 2026.

### Community Support

It has millions of historical Packagist installs, but its current ecosystem footprint is much smaller than ReactPHP's and its feature scope is intentionally narrower than Workerman's.

### PHP Version

Current Wrench 1.9.2 supports:

```text
PHP ^7.4.15
or
PHP ^8.0.2
```

This makes it one of the alternatives directly compatible with our PHP 7.4 baseline, assuming our deployed PHP 7.4 patch level satisfies the constraint.

### Dependencies

Important requirements include:

```text
ext-sockets
psr/log
symfony/polyfill-php80
```

### Advantages

- PHP 7.4 compatible;
- relatively small conceptual surface;
- WebSocket protocol work is handled for us;
- useful for applications wanting a lightweight WebSocket layer.

### Disadvantages

- narrower runtime/tooling scope than Workerman;
- application must take greater responsibility for process architecture, timers, worker management and operational patterns;
- fewer built-in concepts for the complete realtime service we want to operate;
- smaller active community footprint.

### Suitability for Our ZF1 System

Wrench is a reasonable educational alternative and could technically support a notification server.

However, our requirements include more than WebSocket framing:

```text
long-running service
connection management
internal signal listener
timers/heartbeat
process operation
monitoring
future worker scaling
```

Workerman gives us a more complete foundation for those requirements.

---

## 59.9 PHP-Socket.IO

### Architecture

PHP-Socket.IO is not simply another raw WebSocket server.

It implements the Socket.IO server protocol on top of Workerman.

```text
Socket.IO JavaScript client
        |
Socket.IO protocol
        |
polling / WebSocket transport
        |
PHP-Socket.IO
        |
Workerman
```

Socket.IO adds semantics beyond native WebSocket, such as its own connection/event protocol and transport behavior.

### Performance

Its network runtime is based on Workerman, so the underlying event-driven behavior comes from Workerman.

There is additional protocol complexity compared with sending our small native WebSocket JSON messages directly.

### Ease of Use

Socket.IO can be convenient when an application explicitly wants Socket.IO's event API and compatible JavaScript client.

Example mental model:

```text
socket.emit('notification', data)
```

instead of designing only native WebSocket messages.

However, this introduces a protocol/ecosystem dependency that our current requirements do not need.

### Maintenance Status

A 3.0 beta release was published in 2026.

### Community Support

The package has a meaningful historical install base and is part of the Workerman ecosystem.

However, compatibility with the JavaScript Socket.IO ecosystem is a major constraint.

### PHP Version

The researched beta supports:

```text
PHP >= 7.4
Workerman >=4.0 <5.0
```

which aligns with the Workerman generation we need for our PHP 7.4 environment.

### Dependencies

It requires:

```text
Workerman 4.x
Workerman Channel
ext-json
```

### Important Client Compatibility Limitation

The researched release supports Socket.IO JavaScript clients:

```text
>= 1.3.0
<= 2.x
```

and does not support Socket.IO client 3.x or 4.x.

That is a major consideration for a new browser implementation.

### Advantages

- familiar Socket.IO event API;
- based on Workerman;
- PHP 7.4 compatible in the researched release;
- supports Socket.IO concepts beyond raw WebSocket.

### Disadvantages

- adds Socket.IO protocol complexity we do not currently need;
- current beta status;
- JavaScript client compatibility limited to old Socket.IO generations;
- depends on Workerman anyway;
- native browser `WebSocket` is sufficient for our proposed signal format.

### Suitability for Our ZF1 System

Our requirement is simple:

```text
server -> browser
"notification 5001 changed"
```

Native WebSocket already provides this.

Adding Socket.IO would introduce an extra protocol layer without solving a current requirement, so direct Workerman WebSocket communication is simpler.

---

## 59.10 Major Architectural Differences

The most useful way to understand the alternatives is to group them by architecture.

### Group A — Full Event-Driven PHP Network Framework

```text
Workerman
```

Provides a broad server/runtime model:

```text
workers
connections
protocols
timers
events
process commands
```

This is close to what our realtime service needs.

### Group B — Composable Async PHP Ecosystems

```text
Ratchet + ReactPHP
AMPHP + Revolt
```

These emphasize composable asynchronous libraries.

They can be excellent when an application is already designed around their async ecosystem, but the developer must understand more supporting components.

### Group C — Native Coroutine Runtimes

```text
OpenSwoole
Swow
```

These change the PHP runtime model more substantially by adding compiled asynchronous/coroutine capabilities.

They are attractive for high-performance modern async services but require more deployment and programming-model change.

### Group D — Smaller WebSocket Implementation

```text
Wrench
```

This solves more of the WebSocket protocol than raw sockets but provides less of the surrounding application/process framework.

### Group E — Higher-Level Socket.IO Protocol

```text
PHP-Socket.IO
```

This adds Socket.IO semantics on top of Workerman rather than replacing Workerman's networking foundation.

---

## 59.11 Dependency Complexity

A simplified comparison:

```text
Workerman 4.x
    |
    +-- relatively direct network framework


Ratchet
    |
    +-- Ratchet
    +-- ReactPHP event loop/socket
    +-- RFC6455
    +-- HTTP/PSR components


AMPHP
    |
    +-- Amp
    +-- HTTP server
    +-- socket
    +-- websocket
    +-- Revolt
    +-- supporting packages


OpenSwoole
    |
    +-- native PHP extension
    +-- OS/build/runtime requirements


Swow
    |
    +-- native extension/runtime
    +-- coroutine ecosystem


Wrench
    |
    +-- ext-sockets
    +-- lightweight supporting packages


PHP-Socket.IO
    |
    +-- Workerman 4.x
    +-- Workerman Channel
    +-- Socket.IO protocol layer
```

For our legacy application, fewer new moving parts generally reduce deployment risk.

---

## 59.12 Programming Model Comparison

### Workerman

```php
$worker->onMessage = function (
    $connection,
    $data
) {
    $connection->send($data);
};
```

Main idea:

```text
callback when event occurs
```

### Ratchet

Main idea:

```text
implement WebSocket component interfaces
+
compose ReactPHP/Ratchet server components
```

### AMPHP

Main idea:

```text
modern async/fiber application
+
Amp HTTP/WebSocket handlers
```

### OpenSwoole / Swow

Main idea:

```text
long-running native async runtime
+
coroutines
```

### Wrench

Main idea:

```text
WebSocket implementation closer to socket/server mechanics
```

### PHP-Socket.IO

Main idea:

```text
Socket.IO event semantics
on top of Workerman
```

For staff moving from ZF1, Workerman's callback model is a relatively small conceptual step.

---

## 59.13 Performance Comparison: How to Interpret It Correctly

It is tempting to create a ranking such as:

```text
Library A = fastest
Library B = second
Library C = slow
```

We should not use such a table without a controlled benchmark.

Performance depends on:

- PHP version;
- event-loop backend;
- native extensions;
- CPU;
- operating system;
- TLS termination;
- number of idle connections;
- message frequency;
- message size;
- application callback work;
- logging;
- database/network calls;
- worker count;
- memory limits.

Architecturally:

```text
OpenSwoole / Swow
    -> native coroutine/runtime focus

Workerman
    -> event-driven worker framework;
       optional faster event backend

ReactPHP / AMPHP
    -> event-driven async ecosystems

Wrench
    -> simpler socket/WebSocket layer
```

For our project, the first benchmark should measure the architecture we actually intend to run:

```text
WebSocket connections
+
small notification signals
+
heartbeat
+
user-to-connection routing
```

not an unrelated HTTP benchmark.

---

## 59.14 Maintenance and Version Compatibility Matter as Much as Speed

For a legacy production application, the fastest benchmark result is not enough.

We also need:

```text
Can it run on PHP 7.4?
Can we install it on CentOS?
Can staff understand it?
Can we operate it offline?
Can we patch/update it safely?
Does it require a native extension?
Does it force a new application architecture?
Can it coexist cleanly with ZF1?
```

This immediately eliminates some current releases:

```text
AMPHP WebSocket Server 4 -> PHP >=8.1
Swow 2 alpha             -> PHP >=8.0
Workerman 5              -> PHP >=8.1
```

Our current Workerman approach therefore specifically means:

```text
Workerman 4.x
+
PHP 7.4
```

until the main platform is upgraded.

---

## 59.15 Suitability for Our ZF1 Real-Time Notification Architecture

Our required realtime service is intentionally narrow:

```text
1. Accept authenticated WebSocket connections.
2. Map user -> active connections.
3. Receive trusted internal events.
4. Route a small signal.
5. Maintain heartbeat/cleanup.
6. Let ZF1 remain authoritative.
```

We do **not** currently need:

```text
a new HTTP application framework
a coroutine-based rewrite of ZF1
Socket.IO compatibility
complex async business workflows
database access inside Workerman
```

That distinction is why Workerman fits the architecture well.

---

## 59.16 Practical Selection Summary

### Workerman 4.x

Best aligned with the current architecture because it is a complete enough realtime network framework while allowing ZF1 to remain unchanged.

### Ratchet / ReactPHP

A credible alternative, especially for teams already familiar with ReactPHP, but it introduces more component/dependency decisions.

### AMPHP

A strong modern async ecosystem, but current releases require PHP 8.1+ and therefore do not fit our PHP 7.4 deployment.

### OpenSwoole

Technically powerful and PHP 7.4-capable according to current prerequisites, but requires a native extension and introduces a larger coroutine/runtime shift than our notification service needs.

### Swow

Modern coroutine technology, but current researched releases require PHP 8+ and introduce a native runtime model.

### Wrench

PHP 7.4-compatible and comparatively lightweight, but narrower than Workerman and leaves more process/runtime architecture to our application.

### PHP-Socket.IO

Useful only if Socket.IO protocol compatibility is a real requirement. It depends on Workerman anyway and its researched release supports only Socket.IO JavaScript clients through the 2.x generation.

---

## 59.17 Architecture Decision for This Training Project

For the current system, the training architecture remains:

```text
PHP 7.4 / ZF1
       |
       v
    MariaDB
       |
transactional outbox
       |
       v
   Dispatcher
       |
       v
Workerman 4.x
       |
       v
native WebSocket
       |
       v
JavaScript client
```

The reasons are practical:

- it can coexist with the existing ZF1 application;
- it keeps business logic in ZF1;
- it supports our internal TCP/event concept;
- its event-driven API is understandable for the team;
- it avoids requiring a native coroutine runtime;
- it does not require Redis for the initial design;
- it provides more server/runtime infrastructure than a minimal WebSocket implementation such as Wrench;
- it does not require the broader async application rewrite implied by AMPHP/OpenSwoole/Swow.

This does **not** mean the other technologies are poor choices generally. They solve somewhat different problems and may become more attractive after a future PHP/platform modernization.

---

## 59.18 Version Pinning Requirement

Because our application currently runs PHP 7.4, dependency installation must be deterministic.

Do not document production installation simply as:

```bash
composer require workerman/workerman
```

without a version constraint.

The project should pin the approved Workerman 4.x version in `composer.json`/`composer.lock`, test it with the exact PHP 7.4 patch release used on the server, and preserve an offline copy of the approved dependency set according to our deployment process.

When PHP is eventually upgraded, Workerman 5.x and the other modern alternatives should be reevaluated rather than automatically carrying forward today's decision.

---

# 60. References

For implementation, always verify behavior against the official
documentation for the **exact Workerman version selected for the PHP 7.4
environment**.

Recommended references:

1.  Workerman Official Manual --- installation and system requirements.
2.  Workerman Official Manual --- simple WebSocket server examples.
3.  Workerman Official Manual --- Worker lifecycle and callbacks.
4.  Workerman Official Manual --- `onMessage`, `onClose`, and `onError`.
5.  Workerman Official Manual --- start, stop, restart, reload, status,
    and connections commands.
6.  Workerman Official Manual --- WebSocket protocol and handshake.
7.  Workerman Official Manual --- timers/heartbeat.
8.  Workerman Official Manual --- Channel, if future multi-process
    routing requires it.
9.  RFC 6455 --- The WebSocket Protocol.
10. OWASP WebSocket Security Cheat Sheet.
11. MariaDB documentation --- transactions, locking, and indexes.

Official sites:

-   Workerman Manual: https://manual.workerman.net/
-   RFC 6455: https://www.rfc-editor.org/rfc/rfc6455
-   OWASP WebSocket Security Cheat Sheet:
    https://cheatsheetseries.owasp.org/cheatsheets/WebSocket_Security_Cheat_Sheet.html
-   MariaDB Documentation: https://mariadb.com/docs/

------------------------------------------------------------------------

## Appendix A --- Staff Quick Reference

### Start

``` bash
php realtime/server.php start
```

### Start in background

``` bash
php realtime/server.php start -d
```

### Stop

``` bash
php realtime/server.php stop
```

### Restart

``` bash
php realtime/server.php restart
```

### Smooth reload

``` bash
php realtime/server.php reload
```

### Status

``` bash
php realtime/server.php status
```

### Connections

``` bash
php realtime/server.php connections
```

### Basic WebSocket Worker

``` php
$worker = new Worker(
    'websocket://0.0.0.0:8080'
);

$worker->onMessage = function (
    $connection,
    $data
) {
    $connection->send($data);
};

Worker::runAll();
```

### Basic Browser Client

``` javascript
const socket =
    new WebSocket(
        'ws://127.0.0.1:8080'
    );

socket.onopen = () =>
    console.log('connected');

socket.onmessage = (event) =>
    console.log(event.data);

socket.onerror = (event) =>
    console.error(event);

socket.onclose = () =>
    console.log('closed');
```

### Architecture Sentence

> **ZF1 changes authoritative business state; MariaDB stores it; the
> outbox and Dispatcher reliably signal Workerman; Workerman pushes a
> small WebSocket event; the browser retrieves current authorized data
> from ZF1.**


# 61. Concurrent WebSocket Connection Capacity

A common question is:

> **How many simultaneous WebSocket clients can this library support?**

There is usually no single correct number.

For most asynchronous WebSocket libraries, the real limit is approximately:

```text
Practical connection capacity
        =
minimum of
(
    library/runtime configuration,
    operating-system file descriptor limit,
    event-loop capability,
    available RAM,
    CPU capacity,
    network capacity,
    TLS cost,
    application memory per connection,
    heartbeat/message frequency,
    process/server architecture
)
```

A server that can maintain 200,000 mostly idle WebSocket connections does **not** necessarily process 200,000 messages per second.

These are different measurements:

```text
Concurrent connections
    =
how many TCP/WebSocket sessions remain open

Connection rate
    =
how many new WebSocket handshakes can be established per second

Message throughput
    =
how many messages/frames can be processed per second

Broadcast throughput
    =
how quickly one message can be delivered to many clients
```

Developers must not use one measurement as a substitute for another.

---

## 61.1 Capacity Comparison Table

The following table combines documented limits, published benchmark information, and practical engineering interpretation.

| Library / Runtime | Hard library connection limit? | Published / practical evidence | Main limiting factors | Capacity interpretation for our project |
|---|---|---|---|---|
| **Workerman 4.x** | Normally no small fixed application-level limit | Workerman documentation estimates about **1.2 million concurrent connections with 24 GB RAM** in an appropriately tuned environment. It recommends the `event` extension and Linux kernel/open-file tuning for high concurrency. | RAM, `ulimit -n`, kernel limits, event-loop backend, CPU, per-connection state, process count, TLS, message rate | Very strong long-connection capacity. For our ZF1 notification system, tens of thousands of mostly idle clients should be treated as a load-testing/configuration problem rather than a framework-design limit. |
| **Ratchet + ReactPHP** | Ratchet itself does not impose a simple small maximum, but the selected ReactPHP event loop can | ReactPHP documents that its default `StreamSelectLoop` uses `select()` and is commonly constrained by `FD_SETSIZE` around **1024**. Native `ext-event`, `ext-ev`, or `ext-uv` loops avoid that particular select limitation. A May 2026 third-party benchmark measured Ratchet at about **6,364 WebSocket upgrade/close operations/sec** with 1,000 test connections on its specific test machine. | Event-loop backend, file descriptors, memory, CPU, PHP streams, application state | Hundreds of connections are straightforward with the default loop. For thousands+, use a scalable native event loop and benchmark. No credible universal maximum should be claimed. |
| **AMPHP WebSocket Server** | Configurable limits exist in the HTTP server, not a fundamental WebSocket ceiling | AMPHP's direct-access `SocketHttpServer` defaults to **1,000 total connections**, but the setting is adjustable. Its behind-proxy configuration imposes no total-connection limit by default. A May 2026 third-party benchmark measured about **3,244 upgrade/close operations/sec** with 1,000 test connections. | Configured server limits, event-loop backend/Revolt, file descriptors, RAM, CPU, Fibers/coroutine workload, TLS | Architecturally capable of high concurrency, but current releases require PHP 8.1+, so it is not a direct fit for our PHP 7.4 deployment. The 1,000 default must not be described as AMPHP's theoretical maximum. |
| **OpenSwoole** | Has an explicit configurable `max_conn`; default is tied to `ulimit -n` | OpenSwoole documents `max_conn` as the maximum TCP connections accepted by the server; its default is `ulimit -n`. Published OpenSwoole performance tests demonstrate high request throughput, but HTTP QPS figures are **not** maximum WebSocket connection counts. A May 2026 third-party WebSocket benchmark measured about **10,158 upgrade/close operations/sec** with 1,000 test connections. | `max_conn`, `ulimit -n`, RAM, reactor/worker configuration, CPU, extension build, kernel, application memory | Designed for high concurrency and likely capable of very large connection counts when tuned. It requires a native extension and changes the runtime/deployment model substantially compared with our ZF1 application. |
| **Swow** | No well-supported universal WebSocket maximum found | Swow is a native coroutine-based concurrent I/O engine using a C core and PHP code. Public documentation emphasizes high-concurrency I/O, but a directly comparable, authoritative maximum persistent-WebSocket connection benchmark was not found for this training update. | File descriptors, libuv/runtime architecture, memory, CPU, coroutine/application state, kernel configuration | Potentially strong high-concurrency architecture, but PHP 8.0+ and native-extension requirements make it unsuitable for our current PHP 7.4 server without a platform upgrade. Capacity must be measured on the target workload. |
| **Wrench** | No reliable fixed library maximum should be assumed | No current authoritative benchmark establishing a maximum persistent connection count was found. Its practical ceiling depends on the server/event-loop arrangement around the WebSocket implementation. | PHP streams/event loop, file descriptors, RAM, CPU, server implementation, maintenance/runtime choices | Do not quote a large connection number without an application-specific test. Its narrower architecture provides less operational guidance for our planned notification service than Workerman. |
| **PHP-Socket.IO** | Inherits most connection/runtime constraints from Workerman 4.x plus Socket.IO overhead | PHP-Socket.IO is implemented on top of Workerman and Workerman Channel. It therefore inherits Workerman's underlying networking capacity characteristics, but Socket.IO adds protocol/session/event abstraction and potentially polling-related overhead. No independent authoritative maximum connection benchmark should be claimed. | Workerman limits plus Socket.IO protocol overhead, Channel, RAM, file descriptors, CPU, client transport mode | It can scale beyond small deployments, but it adds a protocol we do not need. It supports Socket.IO clients only through the older 1.x–2.x generation, making plain Workerman WebSocket a cleaner fit for our browser clients. |

**Important:** These figures are not an apples-to-apples ranking. Hardware, PHP version, TLS, event-loop backend, payload size, connection lifetime, client behavior, and benchmark methodology differ.

---

## 61.2 Workerman Connection Capacity

Workerman's official documentation makes an important distinction between **maintained connections** and **requests per second**.

It gives an approximate example of:

```text
24 GB RAM
    |
    +-- approximately 1,200,000 maintained connections
```

under a suitably configured high-concurrency environment.

This should be understood as an approximate capacity example, **not a guarantee** for every Workerman application.

Our application stores information for each authenticated connection:

```text
TcpConnection object
user_id
authentication state
heartbeat information
send/receive buffers
application metadata
```

Therefore our memory usage per connection may be higher than a minimal benchmark.

Workerman also recommends installing the `event` extension and optimizing Linux for high concurrency. Its documentation specifically calls attention to this once simultaneous connection counts move beyond roughly 1,000.

Conceptually:

```text
Workerman
   |
   +-- PHP connection objects ------> RAM
   |
   +-- TCP sockets -----------------> file descriptors
   |
   +-- event loop ------------------> epoll/libevent/event
   |
   +-- message processing ----------> CPU
   |
   +-- outgoing frames -------------> network bandwidth
```

No one component alone determines the final maximum.

---

## 61.3 File Descriptor Limits

Every TCP connection consumes an operating-system file descriptor.

Check the shell limit:

```bash
ulimit -n
```

Example:

```text
1024
```

A process cannot maintain 50,000 client sockets if its permitted number of open descriptors is only 1,024.

Remember that WebSocket connections are not the only descriptors:

```text
WebSocket clients
+
listening sockets
+
log files
+
internal TCP connections
+
database/network connections
+
other files
=
total file descriptors
```

Therefore the configured limit needs headroom.

The operating system also has system-wide limits in addition to the per-process limit.

---

## 61.4 Why `select()` Can Become a Problem

Some pure-PHP event loops fall back to `select()` / `stream_select()`.

A common platform limit is:

```text
FD_SETSIZE = 1024
```

ReactPHP explicitly documents this issue for its `StreamSelectLoop`.

This explains why developers sometimes observe:

```text
~1024 connections
```

and incorrectly conclude:

> "Ratchet supports only 1,024 clients."

The more accurate statement is:

> The selected `stream_select()` event-loop backend is commonly limited by `FD_SETSIZE`; Ratchet/ReactPHP can use more scalable native event-loop implementations.

For high connection counts, event mechanisms based on technologies such as:

```text
epoll
kqueue
libevent
libev
libuv
```

are generally more appropriate.

---

## 61.5 AMPHP's 1,000-Connection Default

AMPHP provides a useful example of the difference between:

```text
configuration default
```

and:

```text
technical maximum
```

Its direct-access HTTP server configuration defaults to:

```text
10 connections per IP
1000 total connections
1000 concurrent requests
```

Those values are adjustable.

When AMPHP is configured for operation behind a proxy, its documentation states that no total connection limit is imposed by that factory configuration, although concurrent-request limits still have defaults.

Therefore it would be incorrect to write:

> AMPHP supports a maximum of 1,000 WebSockets.

The correct interpretation is:

> One standard AMPHP server configuration starts with a 1,000-connection safety limit that can be changed.

---

## 61.6 OpenSwoole `max_conn`

OpenSwoole exposes an explicit server setting:

```text
max_conn
```

It defines the maximum number of TCP connections the server should accept at one time.

Its documentation states that the default is based on:

```text
ulimit -n
```

Therefore:

```text
OpenSwoole max_conn
       |
       v
configured server ceiling
       |
       +--> must still fit OS file descriptor limits
       +--> must still fit RAM
       +--> must still fit CPU/network workload
```

Increasing `max_conn` does not create capacity by itself.

---

## 61.7 PHP-Socket.IO Capacity

PHP-Socket.IO is not an independent low-level networking runtime.

Its architecture is approximately:

```text
Socket.IO API
     |
PHP-Socket.IO
     |
Workerman
     |
TCP / WebSocket
```

Consequently, much of its connection scalability comes from Workerman.

However, Socket.IO introduces additional behavior:

```text
Socket.IO framing/events
session state
rooms/namespaces
transport management
possibly polling transport
```

This can increase per-client memory and CPU overhead compared with a minimal plain-WebSocket Workerman application.

Therefore it is unsafe to take Workerman's minimal connection estimate and claim the identical number for PHP-Socket.IO.

---

## 61.8 Swow and Wrench: Why We Do Not Invent a Number

For Swow and Wrench, this document does not provide a made-up maximum connection figure.

A framework can describe itself as high performance without publishing a benchmark that answers our exact question:

```text
"How many authenticated, mostly idle WebSocket
notification clients can this server maintain?"
```

HTTP request benchmarks, frame encode/decode microbenchmarks, and coroutine benchmarks do not automatically answer that question.

Where an authoritative, comparable persistent-connection result is unavailable, our engineering document should say:

```text
Maximum not established by reliable comparable evidence.
Load-test on target hardware and workload.
```

This is more useful than quoting an unsupported theoretical number.

---

## 61.9 Recent Cross-Library WebSocket Benchmark

A third-party WebSocket benchmark published results in May 2026 using PHP 8.4.21 on Apple Silicon macOS.

For a server-runtime test using **1,000 connections**, it reported approximately:

| Runtime | WebSocket upgrade/close rate |
|---|---:|
| Workerman 5.2.0 | 10,525 connections/sec |
| OpenSwoole 26.2.0 | 10,158 connections/sec |
| Ratchet 0.4.0 | 6,364 connections/sec |
| AMPHP WebSocket Server 4.0.0 | 3,244 connections/sec |

This benchmark is useful evidence about **connection establishment/teardown throughput**.

It does **not** mean:

```text
Workerman maximum clients = 10,525
```

The number is a rate:

```text
connections established/closed per second
```

not:

```text
simultaneously maintained connections
```

It also uses PHP 8.4 and modern library releases, so it does not directly predict our PHP 7.4 + Workerman 4.x production performance.

---

## 61.10 Idle Connections vs. Active Connections

Consider two systems.

### System A

```text
100,000 clients
each sends one message every 5 minutes
```

### System B

```text
10,000 clients
each sends 20 messages every second
```

System B can require far more CPU and bandwidth despite having one tenth as many connected clients.

Our notification system is closer to System A.

Most connections are expected to be:

```text
connected
authenticated
mostly idle
waiting for notification events
```

This is favorable for event-driven servers such as Workerman.

---

## 61.11 Memory Per Connection

Suppose a load test determines that the complete server consumes an average additional:

```text
20 KB per connected client
```

Then:

```text
10,000 clients
    ~= 200 MB

50,000 clients
    ~= 1 GB

100,000 clients
    ~= 2 GB

500,000 clients
    ~= 10 GB
```

This is only an example calculation.

We must **measure** our actual application.

A practical test is:

```text
1. Start Workerman with no clients.
2. Record RSS memory.
3. Connect 10,000 representative clients.
4. Wait for the system to stabilize.
5. Record RSS again.
6. Calculate approximate incremental bytes/client.
7. Repeat with authentication and normal metadata enabled.
```

Formula:

```text
Approximate memory per connection
=
(memory_after - memory_before)
/
number_of_connections
```

Add significant safety headroom rather than sizing production exactly to the measured minimum.

---

## 61.12 CPU Limits

Idle connections consume relatively little CPU.

CPU becomes important when:

```text
many clients connect simultaneously
many clients send simultaneously
heartbeat interval is too aggressive
large JSON messages are encoded/decoded
authentication is expensive
broadcast fan-out is large
TLS encryption is busy
application callbacks perform blocking work
```

Example:

```text
50,000 clients
heartbeat every 30 seconds

~= 1,667 heartbeat events/second
```

A heartbeat policy therefore becomes part of capacity planning.

---

## 61.13 Network Bandwidth

Suppose a server sends an average 500-byte application message to 50,000 clients at once.

Ignoring protocol/TLS/network overhead:

```text
500 bytes * 50,000
=
25,000,000 bytes
~= 25 MB
```

One broadcast therefore creates roughly 25 MB of application payload.

Frequent large broadcasts can become network-bound even when CPU and memory are available.

Our signal-only architecture helps:

```json
{
    "event_id": "E100",
    "event": "notification.created",
    "data": {
        "notification_id": 5001
    }
}
```

instead of sending a large business object through WebSocket.

---

## 61.14 TLS Cost

Production uses:

```text
wss://
```

TLS adds:

```text
handshake CPU
encryption/decryption CPU
additional memory/state
additional bytes
```

If TLS terminates at Nginx/Apache:

```text
Browser
   |
   | WSS / TLS
   v
Reverse Proxy
   |
   | local WebSocket
   v
Workerman
```

the proxy becomes part of the capacity model.

Its:

```text
worker_connections
file descriptor limits
memory
CPU
timeouts
```

must also be sized.

---

## 61.15 Multi-Process Architecture Changes Capacity

Suppose:

```php
$worker->count = 4;
```

Workerman creates multiple worker processes.

This can improve CPU utilization:

```text
CPU Core 1 <- Worker 1
CPU Core 2 <- Worker 2
CPU Core 3 <- Worker 3
CPU Core 4 <- Worker 4
```

but introduces a routing problem:

```text
Bob connection -> Worker 3

Internal event -> Worker 1
```

Worker 1 cannot simply access Worker 3's PHP connection objects.

Therefore scaling connection count by adding workers requires an IPC/routing design such as an appropriate Workerman/GatewayWorker/Channel architecture.

For our initial implementation, correctness is more important than prematurely increasing worker count.

---

## 61.16 PHP Configuration

Traditional PHP settings such as:

```ini
max_execution_time
```

are not a useful way to size a long-running Workerman server.

Workerman runs as PHP CLI and has a different lifecycle from an Apache/PHP-FPM request.

However, PHP/runtime configuration still matters, including:

```text
memory_limit policy
loaded extensions
event extension
OPcache CLI configuration, if used
garbage collection behavior
error/log configuration
```

The operating-system and event-loop limits are usually more important for raw connection count than normal web-request settings.

---

## 61.17 Practical Capacity Planning for Our ZF1 Notification System

We should **not** design production around the theoretical maximum.

A safer engineering process is:

```text
Expected peak online users
        |
        v
Expected connections per user
        |
        v
Peak connection target
        |
        v
Add growth + failure headroom
        |
        v
Load-test that target
        |
        v
Observe memory / CPU / FD / latency
        |
        v
Set production capacity
```

Example planning scenario:

```text
Expected peak users       5,000
Average connections/user  2
                          ------
Expected connections      10,000

Capacity headroom         2x
                          ------
Load-test target          20,000
```

This does **not** mean Workerman is limited to 20,000.

It means we prove the capacity we actually need.

---

## 61.18 Recommended Load Tests

### Test A — Idle Connection Capacity

```text
Connect N authenticated clients.
Keep them connected for several hours.
Measure memory, file descriptors and CPU.
```

### Test B — Connection Storm

```text
Restart service.
Reconnect thousands of clients.
Measure handshake rate and latency.
```

### Test C — Notification Fan-Out

```text
Send notifications to many different users.
Measure event latency.
```

### Test D — Broadcast

```text
Send one system event to many clients.
Measure completion time and network throughput.
```

### Test E — Heartbeat

```text
Enable production heartbeat interval.
Measure steady-state CPU/network traffic.
```

### Test F — Dispatcher + Workerman

```text
Insert many outbox rows.
Dispatch them.
Measure:
    backlog growth,
    dispatch rate,
    ACK latency,
    retry behavior.
```

### Test G — Failure Recovery

```text
Stop internal listener.
Continue creating notifications.
Restore listener.
Measure time required to drain outbox backlog.
```

---

## 61.19 Metrics During Load Testing

Monitor at least:

```text
Active WebSocket connections

Workerman process RSS

CPU per worker

Open file descriptors

System-wide file descriptor usage

Network TX/RX

WebSocket handshake failures

Message delivery latency

Event-loop delay

Heartbeat timeout count

Outbox pending count

Oldest pending outbox age

Dispatcher throughput

ACK latency

Reconnect rate

Error/retry rate
```

Linux examples include:

```bash
ulimit -n
```

```bash
cat /proc/<PID>/limits
```

```bash
ls /proc/<PID>/fd | wc -l
```

and Workerman:

```bash
php realtime/server.php status
```

```bash
php realtime/server.php connections
```

---

## 61.20 Capacity Guidance for Our Architecture

For our system, the most important conclusion is not:

```text
"Which library has the biggest theoretical number?"
```

The better question is:

```text
"Which architecture can reliably support our measured
peak authenticated notification workload while remaining
maintainable in PHP 7.4 / ZF1?"
```

Workerman remains attractive because:

```text
PHP 7.4
    |
Workerman 4.x
    |
event-driven long connections
    |
simple WebSocket API
    |
separate from ZF1
    |
MariaDB outbox reliability
```

Its official documentation demonstrates that its architecture is intended for connection counts far beyond the likely initial needs of our notification application.

For production approval, however, we should still require a load test using:

```text
our PHP 7.4 build
our pinned Workerman 4.x release
our CentOS/Linux configuration
our authentication metadata
our heartbeat interval
our JSON event size
our reverse proxy/TLS configuration
our expected connections per user
our Dispatcher/outbox workload
```

That result—not a public benchmark—should become our actual supported connection specification.

---

# 62. Concurrency Comparison: Key Lessons

The concurrency comparison teaches five important lessons.

**First**, most WebSocket frameworks do not have a simple built-in maximum. The operating system and runtime usually become limiting factors first.

**Second**, an apparent 1,024-connection limit frequently indicates a `select()` / file-descriptor configuration issue rather than a fundamental WebSocket-library limitation.

**Third**, connection count and message throughput are separate dimensions. A notification server can maintain many mostly idle connections while processing relatively few messages.

**Fourth**, published benchmarks should be treated as evidence about a particular hardware/software/workload combination, not as guarantees.

**Fifth**, our system should define a tested operational capacity:

```text
Supported capacity
    =
capacity successfully demonstrated
under our production-like workload
with acceptable latency
and sufficient safety headroom
```

rather than advertising a theoretical maximum.

---

## Additional Capacity References

The concurrency section should be verified against the selected production versions before deployment.

- Workerman Official Manual — concurrent connection guidance and high-concurrency configuration.
- Workerman Official Manual — installation/event-extension recommendations.
- ReactPHP Event Loop documentation — `StreamSelectLoop` and native event-loop backends.
- AMPHP HTTP Server documentation — configurable total/per-IP connection limits.
- OpenSwoole Server Configuration — `max_conn`.
- `php-websocket-bench` — third-party May 2026 comparative WebSocket runtime benchmark; useful for relative context, not a production capacity guarantee.
