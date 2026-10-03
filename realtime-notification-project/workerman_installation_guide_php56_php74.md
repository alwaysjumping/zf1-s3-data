# Workerman Installation Guide for PHP 5.6 and PHP 7.4

**Target:** Windows development and Linux/CentOS production\
**Project:** Zend Framework 1 real-time notification system

## 1. Version Selection

Use an explicitly pinned Workerman major version for legacy PHP:

  ---------------------------------------------------------------------------------------------
  PHP                     Workerman               Composer command
  ----------------------- ----------------------- ---------------------------------------------
  PHP 5.6                 Workerman 3.x           `composer require workerman/workerman:^3.5`

  PHP 7.4                 Workerman 4.x           `composer require workerman/workerman:^4.0`

  PHP 8.1+                Workerman 5.x           Use a current compatible release
  ---------------------------------------------------------------------------------------------

For our current ZF1 project, use **PHP 7.4 + Workerman 4.x**. PHP 5.6 +
Workerman 3.x should be treated as a legacy-maintenance environment.

Do not use an unconstrained `composer require workerman/workerman` on
PHP 5.6/7.4. Pin the compatible branch.

## 2. Workerman Uses PHP CLI

Workerman is a long-running PHP CLI process, not a normal Apache
request.

``` text
Normal ZF1:
Browser -> Apache -> PHP -> ZF1 -> request ends

Workerman:
Shell -> PHP CLI -> Workerman -> remains running
```

Always verify CLI PHP:

``` bash
php -v
php --ini
```

Linux:

``` bash
which php
```

Windows:

``` powershell
where.exe php
```

## 3. Linux Extensions

For Workerman on Linux, `pcntl` and `posix` are normal
process-management extensions.

Check them:

``` bash
php -m | grep pcntl
php -m | grep posix
```

Or:

``` bash
php -r "var_dump(extension_loaded('pcntl'));"
php -r "var_dump(extension_loaded('posix'));"
```

The `event` extension is optional but recommended for high-concurrency
production use:

``` bash
php -m | grep event
```

A desirable production setup is:

``` text
PHP 7.4 CLI
+-- pcntl    YES
+-- posix    YES
+-- event    YES (recommended)
+-- Workerman 4.x
```

The PHP `sockets` extension is separate from Workerman's WebSocket
protocol support and is not normally required merely to create a basic
Workerman WebSocket server.

## 4. Windows Extensions

`posix` and `pcntl` are Unix-oriented extensions and are not normally
available on native Windows PHP.

Do not download unofficial `php_posix.dll` or `php_pcntl.dll` files.

Workerman can run on Windows without these extensions, but Linux is the
preferred production environment.

``` text
Windows 11 + PHP 7.4 + Workerman 4.x
        -> development/testing

CentOS/Linux + PHP 7.4 + pcntl + posix + event + Workerman 4.x
        -> production
```


## 5. Important Workerman Limitations on Windows

Workerman can run on Windows, but the official Workerman documentation lists several important limitations that developers should understand before choosing Windows for anything beyond development and testing.

### 5.1 Single-process connection capacity is limited

On Windows, Workerman documents support for only **200+ connections per single process**.

This is a major difference from Linux, where Workerman is designed for much higher concurrency when the operating system, file-descriptor limits, memory, event loop, and server configuration are tuned correctly.

Conceptually:

```text
Windows Workerman
   |
   +-- single process
   |
   +-- roughly 200+ connections documented
   |
   +-- suitable for development/testing
```

Compared with Linux:

```text
Linux Workerman
   |
   +-- multiple worker processes
   +-- scalable event loop
   +-- high file-descriptor limits
   +-- event extension available
   |
   +-- suitable for production
```

For our real-time notification system, this is one of the main reasons Linux/CentOS should be used in production.

### 5.2 `Worker::$count` cannot create multiple processes

On Linux, Workerman can use:

```php
$worker->count = 4;
```

to start multiple worker processes.

Example:

```text
Master Process
    |
    +-- Worker 1
    +-- Worker 2
    +-- Worker 3
    +-- Worker 4
```

This helps Workerman use multiple CPU cores and support larger workloads.

On Windows, the `count` property cannot be used to create multiple worker processes.

Therefore:

```php
$worker->count = 4;
```

does **not** provide the same multi-process behavior on Windows as it does on Linux.

### 5.3 Management commands are not available

The official Workerman documentation states that several normal Linux management commands are unavailable on Windows.

These include:

```bash
php server.php status
php server.php stop
php server.php reload
php server.php restart
```

On Linux, these commands are important for normal operation and maintenance.

For example:

```bash
php server.php status
```

can display Workerman process status.

```bash
php server.php reload
```

can reload worker processes.

```bash
php server.php stop
```

can stop the service cleanly.

Developers should therefore not expect the same command-line management experience on Windows.

### 5.4 Daemon mode is not supported

Linux can normally run Workerman in the background:

```bash
php server.php start -d
```

This is called daemon mode.

On Windows, Workerman does not support daemon mode in the same way.

If Workerman is started from a Command Prompt or PowerShell window and that window is closed, the Workerman service stops.

Conceptually:

```text
Windows
PowerShell / CMD
      |
      +-- php server.php start
              |
              +-- Workerman running

Close terminal
      |
      v
Workerman stops
```

This is not appropriate for production service operation.

On Linux, a production deployment can use:

```text
systemd
   |
   v
Workerman
```

so the service can:

```text
start automatically at boot
restart after failure
run without an open terminal
be monitored by the operating system
```

### 5.5 Multiple listeners cannot be initialized in one file

On Linux, Workerman can normally define multiple `Worker` instances in the same startup file.

For example:

```php
$wsWorker = new Worker(
    'websocket://0.0.0.0:8080'
);

$internalWorker = new Worker(
    'text://127.0.0.1:8081'
);

Worker::runAll();
```

Conceptually:

```text
server.php
   |
   +-- WebSocket listener :8080
   |
   +-- Internal listener  :8081
```

The official Workerman documentation states that Windows does **not** support initializing multiple Worker listeners in a single file.

This is especially important for our architecture because we have discussed:

```text
Browser
   |
   v
WebSocket listener
   |
Workerman
   ^
   |
Internal signal listener
   ^
   |
ZF1 Dispatcher
```

This design is much more naturally deployed and tested on Linux.

For Windows development, we should simplify the topology or run separate scripts/processes where appropriate.

### 5.6 No `pcntl` or `posix`

Native Windows PHP does not provide the normal Unix-oriented Workerman process extensions:

```text
pcntl
posix
```

That is expected.

Do not try to solve this by downloading unofficial DLL files.

Linux should provide:

```text
pcntl
posix
```

and for higher concurrency we recommend:

```text
event
```

### 5.7 Windows is suitable for development, not our production server

The official Workerman guidance recommends Linux for production because Linux does not have the Windows limitations listed above.

For our project:

```text
Windows 11
   |
PHP 7.4
   |
Workerman 4.x
   |
Development
Debugging
Basic WebSocket testing
JavaScript client testing
```

Production:

```text
CentOS / Linux
   |
PHP 7.4 CLI
   |
pcntl
posix
event
   |
Workerman 4.x
   |
Real production WebSocket service
```

### 5.8 Development workflow recommendation

A practical development workflow is:

```text
Developer PC
Windows 11
    |
    +-- edit PHP / JavaScript
    +-- run simple Workerman tests
    +-- test WebSocket client
    |
    v
Git / deployment package
    |
    v
Linux test/staging server
    |
    +-- test multi-process behavior
    +-- test internal listeners
    +-- test daemon/service operation
    +-- test restart/reload
    +-- load test
    |
    v
Linux production server
```

If developers need Linux-like Workerman behavior on a Windows machine, **WSL2** is also a useful option because it provides a Linux environment where `pcntl`, `posix`, Linux process handling, and Linux Workerman behavior can be tested more realistically.

### 5.9 Windows vs Linux summary

| Feature | Windows | Linux |
|---|---:|---:|
| Basic Workerman WebSocket server | Yes | Yes |
| Suitable for learning/development | Yes | Yes |
| `pcntl` | No | Yes |
| `posix` | No | Yes |
| `event` extension | Not the normal Windows path | Yes, recommended |
| Multiple worker processes using `count` | No | Yes |
| `status` command | No | Yes |
| `stop` command | No | Yes |
| `reload` command | No | Yes |
| `restart` command | No | Yes |
| Daemon mode | No | Yes |
| Multiple Worker listeners in one startup file | No | Yes |
| High-concurrency production deployment | Not recommended | Recommended |
| Recommended for our production notification system | No | **Yes** |

### 5.10 Project rule

For our ZF1 real-time notification project, use the following rule:

```text
Windows
    =
development environment

Linux / CentOS
    =
production Workerman environment
```

Do not use Windows benchmark results or behavior as the final production-capacity reference.

The production system must be tested on Linux with the exact PHP 7.4, Workerman 4.x, extension, reverse-proxy, heartbeat, authentication, and notification workload configuration that will actually be deployed.


## 6. Check Composer

``` bash
composer --version
composer diagnose
```

Make sure Composer is running under the intended PHP interpreter:

``` bash
php -v
composer --version
```

## 7. Install Workerman on PHP 7.4

Confirm:

``` bash
php -v
```

Expected:

``` text
PHP 7.4.x (cli)
```

Create a directory:

``` bash
mkdir realtime
cd realtime
```

Install Workerman 4.x:

``` bash
composer require workerman/workerman:^4.0
```

Verify:

``` bash
composer show workerman/workerman
```

Recommended project structure:

``` text
/project
+-- application/
+-- library/
+-- public/
+-- realtime/
|   +-- composer.json
|   +-- composer.lock
|   +-- server.php
|   +-- vendor/
+-- ...
```

## 8. Install Workerman on PHP 5.6

Confirm:

``` bash
php -v
```

Expected:

``` text
PHP 5.6.x (cli)
```

Then:

``` bash
mkdir realtime
cd realtime
composer require workerman/workerman:^3.5
composer show workerman/workerman
```

Use PHP 5.6 + Workerman 3.x only for legacy applications that cannot yet
be upgraded.

## 9. Multiple PHP Versions

Apache PHP and CLI PHP may be different.

Example:

``` text
Apache -> PHP 7.4
CLI    -> PHP 8.x
```

Workerman uses CLI PHP, so always verify `php -v`.

With a specific PHP executable:

``` bash
/path/to/php74 composer.phar require workerman/workerman:^4.0
```

PHP 5.6:

``` bash
/path/to/php56 composer.phar require workerman/workerman:^3.5
```

Windows examples:

``` powershell
C:\php74\php.exe C:\composer\composer.phar require workerman/workerman:^4.0
```

``` powershell
C:\php56\php.exe C:\composer\composer.phar require workerman/workerman:^3.5
```

## 10. Basic WebSocket Server

Create `realtime/server.php`:

``` php
<?php

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker(
    'websocket://0.0.0.0:8080'
);

$worker->count = 1;

$worker->onConnect = function ($connection) {
    echo "Client connected\n";
};

$worker->onMessage = function ($connection, $data) {
    echo "Received: {$data}\n";

    $connection->send(
        'Server received: ' . $data
    );
};

$worker->onClose = function ($connection) {
    echo "Client disconnected\n";
};

Worker::runAll();
```

## 11. Start Workerman

Start:

``` bash
php server.php start
```

On supported Linux installations, common commands include:

``` bash
php server.php start -d
php server.php status
php server.php connections
php server.php reload
php server.php restart
php server.php stop
```

Native Windows operation has process-management limitations compared
with Linux.

## 12. Browser Test

``` html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Workerman Test</title>
</head>
<body>
<script>
const socket = new WebSocket(
    'ws://127.0.0.1:8080'
);

socket.onopen = function () {
    console.log('Connected');
    socket.send('Hello Workerman');
};

socket.onmessage = function (event) {
    console.log('Message:', event.data);
};

socket.onerror = function (event) {
    console.error('WebSocket error:', event);
};

socket.onclose = function () {
    console.log('Disconnected');
};
</script>
</body>
</html>
```

Expected browser console:

``` text
Connected
Message: Server received: Hello Workerman
```

## 13. Environment Check Script

Create `check.php`:

``` php
<?php

echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'OS: ' . PHP_OS . PHP_EOL;

echo 'POSIX: ';
echo extension_loaded('posix') ? 'YES' : 'NO';
echo PHP_EOL;

echo 'PCNTL: ';
echo extension_loaded('pcntl') ? 'YES' : 'NO';
echo PHP_EOL;

echo 'Event: ';
echo extension_loaded('event') ? 'YES' : 'NO';
echo PHP_EOL;

echo 'Sockets: ';
echo extension_loaded('sockets') ? 'YES' : 'NO';
echo PHP_EOL;
```

Run:

``` bash
php check.php
```

Typical Linux production result:

``` text
PHP Version: 7.4.x
OS: Linux
POSIX: YES
PCNTL: YES
Event: YES
Sockets: YES
```

On Windows, `POSIX: NO` and `PCNTL: NO` are normal.

## 14. Composer Files

Composer normally creates:

``` text
composer.json
composer.lock
vendor/
```

Do not manually modify files under `vendor/`.

Keep both `composer.json` and `composer.lock` in version control.

For an existing project deployment, use:

``` bash
composer install
```

For production:

``` bash
composer install --no-dev --optimize-autoloader
```

Avoid running `composer update` independently on production unless the
update is intentional and has been tested.

## 15. Do Not Start Workerman Through Apache

Do not start the server by browsing to:

``` text
https://example.com/realtime/server.php
```

Start it using PHP CLI:

``` bash
php server.php start
```

Architecture:

``` text
HTTP/HTTPS -> Apache -> ZF1 -> MariaDB

WebSocket  -> Workerman
```

In our notification architecture, ZF1 and MariaDB remain authoritative;
Workerman provides real-time transport.

## 16. Port and Firewall

The example listens on port 8080.

Linux checks:

``` bash
ss -lntp
```

or:

``` bash
netstat -lntp
```

Development:

``` text
ws://127.0.0.1:8080
```

Production should normally use:

``` text
wss://
```

commonly with TLS terminated by a reverse proxy.

Do not expose an internal signal port publicly.

## 17. Production Process Management

On Linux, use reliable process supervision such as systemd.

Concept:

``` text
systemd
   |
   v
Workerman
   |
   +-- startup at boot
   +-- restart policy
   +-- logging/monitoring
```

A service must use the correct PHP 7.4 CLI binary and project path.
Validate the exact systemd configuration on the target server before
production use.

## 18. Recommended ZF1 Setup

``` text
CentOS/Linux
    |
PHP 7.4 CLI
    |
+-- pcntl
+-- posix
+-- event (recommended)
    |
Workerman 4.x
    |
WebSocket service
```

ZF1 should own:

``` text
authentication
authorization
business rules
MariaDB operations
notification state
REST APIs
transactional outbox
```

Workerman should own:

``` text
WebSocket connections
connection lifecycle
real-time event delivery
heartbeat
connection routing
```

## 19. Quick Commands

### PHP 7.4

``` bash
php -v
mkdir realtime
cd realtime
composer require workerman/workerman:^4.0
composer show workerman/workerman
php server.php start
```

### PHP 5.6

``` bash
php -v
mkdir realtime
cd realtime
composer require workerman/workerman:^3.5
composer show workerman/workerman
php server.php start
```

## 20. Troubleshooting Checklist

``` text
[ ] Does `php -v` show the expected PHP?
[ ] Does `php --ini` show the expected php.ini?
[ ] Is Composer using the same PHP?
[ ] Is the correct Workerman major branch installed?
[ ] Does vendor/autoload.php exist?
[ ] On Linux, are pcntl and posix available?
[ ] Is port 8080 free?
[ ] Does the firewall allow intended traffic?
[ ] Is another Workerman process already running?
[ ] Are there PHP startup warnings?
[ ] Are permissions correct?
```

Useful commands:

``` bash
php -v
php --ini
php -m
php -l server.php
composer show workerman/workerman
```

## 21. Final Recommendation

For the current real-time notification project:

``` text
PHP 7.4
   |
Workerman 4.x
   |
Linux/CentOS production
   |
pcntl + posix
   |
event recommended
```

Install with:

``` bash
composer require workerman/workerman:^4.0
```

For legacy PHP 5.6:

``` text
PHP 5.6
   |
Workerman 3.x
```

Install with:

``` bash
composer require workerman/workerman:^3.5
```

Workerman should remain separate from normal ZF1 HTTP request handling.
ZF1 remains the business/API/database layer; Workerman is the real-time
WebSocket transport layer.

## 22. References

Verify version-specific details before deployment:

-   Workerman official manual: `https://manual.workerman.net/`
-   Workerman package:
    `https://packagist.org/packages/workerman/workerman`
-   PHP POSIX manual: `https://www.php.net/manual/en/book.posix.php`
-   PHP PCNTL manual: `https://www.php.net/manual/en/book.pcntl.php`
-   PHP Event manual: `https://www.php.net/manual/en/book.event.php`

Because PHP 5.6 and PHP 7.4 are legacy PHP branches, pin dependencies
and test the exact Composer lock file in the target environment before
deployment.
