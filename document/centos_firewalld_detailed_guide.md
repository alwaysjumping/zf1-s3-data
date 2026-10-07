# CentOS firewalld --- Detailed Practical Guide

## 1. Overview

On modern CentOS systems, firewall policy is commonly managed by
**firewalld**. The main command is `firewall-cmd`; the firewalld daemon
itself is managed with `systemctl`.

A firewall controls which network traffic can reach services running on
the server.

``` text
Internet / Network
        |
        v
+---------------------+
| firewalld           |
| 22    SSH           |
| 80    HTTP          |
| 443   HTTPS         |
| 3306  MariaDB       |
| 8080  Custom App    |
+---------------------+
        |
        v
+---------------------+
| CentOS Server       |
+---------------------+
```

For a typical public web server, SSH, HTTP, and HTTPS may be allowed
while MariaDB remains inaccessible from the public network.

## 2. Manage the firewalld Service

Check status:

``` bash
sudo systemctl status firewalld
```

Start, stop, restart, and enable it:

``` bash
sudo systemctl start firewalld
sudo systemctl stop firewalld
sudo systemctl restart firewalld
sudo systemctl enable firewalld
sudo systemctl disable firewalld
```

Start immediately and enable at boot:

``` bash
sudo systemctl enable --now firewalld
```

Check firewalld itself:

``` bash
sudo firewall-cmd --state
```

Typical result:

``` text
running
```

`systemctl` manages the daemon. `firewall-cmd` manages firewall policy.

## 3. Initial Inspection

Before changing an unfamiliar server:

``` bash
sudo firewall-cmd --state
sudo firewall-cmd --get-default-zone
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --list-all
sudo ss -lntup
```

These tell you whether firewalld is running, which zone/interface is
active, what is allowed, and what applications are actually listening.

## 4. Zones

A **zone** groups firewall rules according to network trust or purpose.

List zones:

``` bash
sudo firewall-cmd --get-zones
```

Common zones include:

``` text
block dmz drop external home internal public trusted work
```

A normal Internet-facing server often uses `public`, but never assume
it.

Check:

``` bash
sudo firewall-cmd --get-default-zone
sudo firewall-cmd --get-active-zones
```

Example:

``` text
public
  interfaces: ens192
```

This means traffic associated with `ens192` is being handled by the
`public` zone.

Inspect it:

``` bash
sudo firewall-cmd --zone=public --list-all
```

## 5. Interfaces and Zones

List interfaces:

``` bash
ip addr
```

Typical names include `eth0`, `ens192`, and `enp0s3`.

Find an interface's zone:

``` bash
sudo firewall-cmd --get-zone-of-interface=ens192
```

Change it permanently:

``` bash
sudo firewall-cmd --permanent --zone=public --change-interface=ens192
sudo firewall-cmd --reload
```

Be careful when changing the zone of the interface carrying your SSH
connection.

## 6. Services vs Ports

Firewalld can allow a named service:

``` bash
sudo firewall-cmd --add-service=http
```

or an explicit port:

``` bash
sudo firewall-cmd --add-port=8080/tcp
```

Named services are useful for standard protocols. Common examples are:

``` text
ssh    TCP 22
http   TCP 80
https  TCP 443
```

List available definitions:

``` bash
sudo firewall-cmd --get-services
```

Inspect one:

``` bash
sudo firewall-cmd --info-service=http
```

## 7. Runtime vs Permanent Rules

This command changes the **runtime** configuration:

``` bash
sudo firewall-cmd --add-port=8080/tcp
```

It is active immediately but temporary.

This changes the saved **permanent** configuration:

``` bash
sudo firewall-cmd --permanent --add-port=8080/tcp
```

Apply permanent configuration:

``` bash
sudo firewall-cmd --reload
```

Mental model:

``` text
Permanent configuration
        |
        | --reload
        v
Runtime configuration
        |
        v
Active firewall
```

A production change commonly looks like:

``` bash
sudo firewall-cmd --zone=public --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
sudo firewall-cmd --zone=public --query-port=8080/tcp
```

For testing, you can first add the rule without `--permanent`.

You can save the whole runtime configuration with:

``` bash
sudo firewall-cmd --runtime-to-permanent
```

Use that carefully because it may persist other temporary changes too.

## 8. Reloading

Normal reload:

``` bash
sudo firewall-cmd --reload
```

A more disruptive option is:

``` bash
sudo firewall-cmd --complete-reload
```

Normally use `--reload`.

## 9. TCP and UDP

A port is paired with a protocol.

``` text
SSH       TCP 22
HTTP      TCP 80
HTTPS     TCP 443
MariaDB   TCP 3306
```

Open TCP:

``` bash
sudo firewall-cmd --permanent --add-port=8080/tcp
```

Open UDP:

``` bash
sudo firewall-cmd --permanent --add-port=8080/udp
```

Opening TCP does not open UDP. Allow only protocols the application
actually needs.

## 10. Open, Query, and Remove Ports

Open TCP 8080 permanently:

``` bash
sudo firewall-cmd --zone=public --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

Check:

``` bash
sudo firewall-cmd --zone=public --query-port=8080/tcp
sudo firewall-cmd --zone=public --list-ports
```

Remove it:

``` bash
sudo firewall-cmd --zone=public --permanent --remove-port=8080/tcp
sudo firewall-cmd --reload
```

Open a range:

``` bash
sudo firewall-cmd --zone=public --permanent --add-port=8000-8100/tcp
sudo firewall-cmd --reload
```

Prefer exact ports rather than unnecessarily broad ranges.

## 11. Standard Services

Allow SSH, HTTP, and HTTPS:

``` bash
sudo firewall-cmd --zone=public --permanent --add-service=ssh
sudo firewall-cmd --zone=public --permanent --add-service=http
sudo firewall-cmd --zone=public --permanent --add-service=https
sudo firewall-cmd --reload
```

Verify:

``` bash
sudo firewall-cmd --zone=public --list-services
```

Remove a service:

``` bash
sudo firewall-cmd --zone=public --permanent --remove-service=http
sudo firewall-cmd --reload
```

Query it:

``` bash
sudo firewall-cmd --zone=public --query-service=http
```

## 12. Typical PHP Web Server

A common architecture is:

``` text
Internet
   |
   +-- TCP 22  --> SSH
   +-- TCP 80  --> Apache/Nginx
   +-- TCP 443 --> Apache/Nginx
                      |
                      v
                     PHP
                      |
                      v
                   MariaDB
                   local
```

Typical rules:

``` bash
sudo firewall-cmd --zone=public --permanent --add-service=ssh
sudo firewall-cmd --zone=public --permanent --add-service=http
sudo firewall-cmd --zone=public --permanent --add-service=https
sudo firewall-cmd --reload
```

If MariaDB is used only locally by PHP, do not expose TCP 3306 publicly.

## 13. MariaDB and Source Restrictions

If another trusted server at `192.168.1.20` must connect to MariaDB,
prefer a source-restricted rule instead of broadly opening 3306:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-rich-rule='rule family="ipv4" source address="192.168.1.20" port port="3306" protocol="tcp" accept'

sudo firewall-cmd --reload
```

List rich rules:

``` bash
sudo firewall-cmd --zone=public --list-rich-rules
```

The rule means:

``` text
IPv4
source = 192.168.1.20
destination port = 3306
protocol = TCP
action = accept
```

## 14. Allow a Network

Permit TCP 8080 from a trusted subnet:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-rich-rule='rule family="ipv4" source address="192.168.1.0/24" port port="8080" protocol="tcp" accept'

sudo firewall-cmd --reload
```

Use only networks you actually trust.

## 15. Remove a Rich Rule

First list rules:

``` bash
sudo firewall-cmd --zone=public --list-rich-rules
```

Then remove the exact rule:

``` bash
sudo firewall-cmd --zone=public --permanent   --remove-rich-rule='rule family="ipv4" source address="192.168.1.20" port port="3306" protocol="tcp" accept'

sudo firewall-cmd --reload
```

## 16. Block an IP

Example:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-rich-rule='rule family="ipv4" source address="203.0.113.50" drop'

sudo firewall-cmd --reload
```

Be very careful with blocking rules on remotely administered servers.

## 17. SSH Safety

Check SSH firewall access:

``` bash
sudo firewall-cmd --zone=public --query-service=ssh
```

Before changing SSH rules:

``` bash
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --zone=public --list-all
sudo ss -lntp | grep :22
```

If possible, keep a provider console or other recovery path available.
An incorrect SSH firewall change can lock you out.

## 18. Firewall Rules Do Not Start Applications

Opening 8080 does not start an application:

``` bash
sudo firewall-cmd --permanent --add-port=8080/tcp
```

Check whether something is actually listening:

``` bash
sudo ss -lntp
sudo ss -lntp | grep :8080
```

The full path is:

``` text
Application running
       |
Application listening on correct address/port
       |
firewalld permits required traffic
       |
External network permits traffic
       |
Client connects
```

## 19. Listening Address Matters

If `ss` shows:

``` text
127.0.0.1:8080
```

the application is generally bound only to local loopback.

If it shows:

``` text
0.0.0.0:8080
```

it is generally listening on all IPv4 interfaces.

Opening firewalld cannot make an application bound only to `127.0.0.1`
remotely accessible.

## 20. Test Connectivity

Local test:

``` bash
curl http://localhost:8080
```

HTTP headers:

``` bash
curl -I http://localhost
```

From another machine:

``` bash
curl http://SERVER_IP:8080
```

If `nc` is installed:

``` bash
nc -vz SERVER_IP 8080
```

A successful localhost test does not prove external connectivity.

## 21. Firewall Logging

View logs:

``` bash
sudo journalctl -u firewalld
sudo journalctl -u firewalld -n 100
sudo journalctl -u firewalld -f
```

Enable denied-packet logging temporarily:

``` bash
sudo firewall-cmd --set-log-denied=all
```

Check:

``` bash
sudo firewall-cmd --get-log-denied
```

Disable when finished:

``` bash
sudo firewall-cmd --set-log-denied=off
```

On busy public servers, denied-packet logging can create substantial log
volume.

## 22. Custom firewalld Services

Custom service definitions are commonly stored in:

``` text
/etc/firewalld/services/
```

Example `myapp.xml`:

``` xml
<?xml version="1.0" encoding="utf-8"?>
<service>
    <short>MyApp</short>
    <description>Company application</description>
    <port protocol="tcp" port="8080"/>
</service>
```

Then:

``` bash
sudo firewall-cmd --reload
sudo firewall-cmd --permanent --add-service=myapp
sudo firewall-cmd --reload
sudo firewall-cmd --info-service=myapp
```

This is useful when the same application is deployed to many servers.

## 23. Masquerading / NAT

Check:

``` bash
sudo firewall-cmd --query-masquerade
```

Enable:

``` bash
sudo firewall-cmd --permanent --add-masquerade
sudo firewall-cmd --reload
```

Disable:

``` bash
sudo firewall-cmd --permanent --remove-masquerade
sudo firewall-cmd --reload
```

Masquerading is generally for routing/NAT scenarios, not an ordinary
standalone web server.

## 24. Port Forwarding

Example: forward TCP 8080 to local TCP 80:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-forward-port=port=8080:proto=tcp:toport=80

sudo firewall-cmd --reload
```

Inspect the zone:

``` bash
sudo firewall-cmd --zone=public --list-all
```

Use forwarding only when the architecture requires it.

## 25. Default Zone

Check:

``` bash
sudo firewall-cmd --get-default-zone
```

Change:

``` bash
sudo firewall-cmd --set-default-zone=public
```

Verify:

``` bash
sudo firewall-cmd --get-default-zone
```

The default zone and active interface-to-zone mappings are related but
not identical concepts. Always inspect `--get-active-zones`.

## 26. SELinux Is Different

Check SELinux:

``` bash
getenforce
sestatus
```

Simplified distinction:

``` text
firewalld
    controls network traffic policy

SELinux
    controls what processes/resources are permitted to do
```

A port can be allowed by firewalld while SELinux policy still affects an
application. Do not disable SELinux as the default troubleshooting
solution.

## 27. External Firewalls

There may be several firewall layers:

``` text
Internet
   |
Cloud / Provider Firewall
   |
Router / Network Firewall
   |
CentOS firewalld
   |
Application
```

Therefore, `firewall-cmd --query-port=8080/tcp` returning `yes` does not
guarantee Internet reachability.

## 28. Detailed Troubleshooting Flow

Suppose TCP 8080 cannot be reached.

Check the application:

``` bash
sudo systemctl status myservice
```

Check the listener:

``` bash
sudo ss -lntp | grep :8080
```

Check firewalld:

``` bash
sudo firewall-cmd --state
```

Find the active zone:

``` bash
sudo firewall-cmd --get-active-zones
```

Inspect that zone:

``` bash
sudo firewall-cmd --zone=public --list-all
```

Query the port:

``` bash
sudo firewall-cmd --zone=public --query-port=8080/tcp
```

Check logs:

``` bash
sudo journalctl -u firewalld -n 100
sudo journalctl -u myservice -n 100
```

Check SELinux:

``` bash
getenforce
```

Test from another computer:

``` bash
curl http://SERVER_IP:8080
```

Finally check routers, VPNs, cloud firewalls, and provider security
rules.

## 29. Apache Example

Check Apache:

``` bash
sudo systemctl status httpd
```

Check listeners:

``` bash
sudo ss -lntp | grep -E ':80|:443'
```

Allow web traffic:

``` bash
sudo firewall-cmd --zone=public --permanent --add-service=http
sudo firewall-cmd --zone=public --permanent --add-service=https
sudo firewall-cmd --reload
```

Verify:

``` bash
sudo firewall-cmd --zone=public --list-services
```

## 30. Internal Application Example

Suppose an internal application listens on TCP 9000 and only
`192.168.1.10` should access it:

``` bash
sudo ss -lntp | grep :9000
```

Then:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-rich-rule='rule family="ipv4" source address="192.168.1.10" port port="9000" protocol="tcp" accept'

sudo firewall-cmd --reload
```

Verify:

``` bash
sudo firewall-cmd --zone=public --list-rich-rules
```

## 31. Separate Web and Database Servers

Example:

``` text
Web Server
192.168.1.20
     |
     | TCP 3306
     v
Database Server
192.168.1.30
```

On the database server:

``` bash
sudo firewall-cmd --zone=public --permanent   --add-rich-rule='rule family="ipv4" source address="192.168.1.20" port port="3306" protocol="tcp" accept'

sudo firewall-cmd --reload
```

MariaDB itself must also listen on the intended address and its database
privileges must allow the intended client. The firewall is only one
layer.

## 32. Production Safety Rules

1.  Allow only required traffic.
2.  Prefer named services for standard protocols.
3.  Source-restrict databases and internal services.
4.  Do not expose MariaDB merely because PHP uses it.
5.  Verify the active zone before editing rules.
6.  Verify that the application is listening.
7.  Make intended production rules permanent.
8.  Remove obsolete rules.
9.  Be especially careful with SSH.
10. Remember SELinux and upstream firewalls.
11. Do not disable firewalld merely to make troubleshooting easier.
12. Do not disable SELinux as a default fix.
13. Document the reason for every nonstandard exposed port.

## 33. Recommended Change Workflow

Inspect first:

``` bash
sudo firewall-cmd --state
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --zone=public --list-all
sudo ss -lntup
```

Test temporarily:

``` bash
sudo firewall-cmd --zone=public --add-port=8080/tcp
```

Test the application. If correct, persist:

``` bash
sudo firewall-cmd --zone=public --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

Verify:

``` bash
sudo firewall-cmd --zone=public --query-port=8080/tcp
sudo firewall-cmd --zone=public --list-all
```

Replace `public` with the actual active zone when necessary.

## 34. Command Cheat Sheet

### Service management

``` bash
sudo systemctl status firewalld
sudo systemctl start firewalld
sudo systemctl stop firewalld
sudo systemctl restart firewalld
sudo systemctl enable --now firewalld
```

### Status and zones

``` bash
sudo firewall-cmd --state
sudo firewall-cmd --get-zones
sudo firewall-cmd --get-default-zone
sudo firewall-cmd --get-active-zones
sudo firewall-cmd --get-zone-of-interface=ens192
```

### Inspect configuration

``` bash
sudo firewall-cmd --list-all
sudo firewall-cmd --list-services
sudo firewall-cmd --list-ports
sudo firewall-cmd --list-rich-rules
```

### Standard services

``` bash
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --remove-service=http
```

### Ports

``` bash
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --permanent --remove-port=8080/tcp
sudo firewall-cmd --query-port=8080/tcp
```

### Apply

``` bash
sudo firewall-cmd --reload
```

### Listening sockets

``` bash
sudo ss -lntup
sudo ss -lntp | grep :8080
```

### Logs

``` bash
sudo journalctl -u firewalld
sudo journalctl -u firewalld -n 100
sudo journalctl -u firewalld -f
```

### SELinux

``` bash
getenforce
sestatus
```

## 35. Final Mental Model

Remember:

``` text
Remote Client
     |
     v
Cloud / Network Firewall
     |
     v
CentOS Network Interface
     |
     v
firewalld Zone
     |
     +-- Services
     +-- Ports
     +-- Rich Rules
     |
     v
Application Listening Socket
     |
     v
Application
```

The four concepts to master first are:

``` text
ZONE
  |
  +-- SERVICE / PORT
  |
  +-- RUNTIME / PERMANENT
  |
  +-- SOURCE RESTRICTION
```

Once these are clear, most day-to-day CentOS firewall administration
becomes straightforward.
