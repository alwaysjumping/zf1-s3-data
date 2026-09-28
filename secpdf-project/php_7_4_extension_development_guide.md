# Developing a Native PHP Extension for PHP 7.4

## 1. Overview

A native PHP extension is compiled C/C++ code loaded by PHP as a shared
library:

``` text
Linux:   myext.so
Windows: php_myext.dll
```

For example, a PHP application might call:

``` php
$result = myext_encrypt($data, $key);
```

The architecture is:

``` text
PHP application
     │
     │ myext_encrypt(...)
     ▼
PHP Extension API / Zend Engine
     │
     ▼
myext.so
     │
     ▼
Native C implementation
```

For PHP 7.4, Linux/CentOS is a convenient environment for learning and
building extensions.

------------------------------------------------------------------------

## 2. Basic Project Structure

A minimal extension can contain:

``` text
myext/
├── config.m4
├── php_myext.h
└── myext.c
```

A larger project can use:

``` text
myext/
├── config.m4
├── php_myext.h
├── myext.c
├── src/
│   ├── crypto.c
│   ├── crypto.h
│   ├── key_manager.c
│   └── key_manager.h
└── tests/
    ├── 001-load.phpt
    ├── 002-encrypt.phpt
    └── 003-decrypt.phpt
```

Keep the PHP/Zend-facing layer thin and put most native business logic
in separate C source files.

------------------------------------------------------------------------

## 3. Development Requirements

You normally need:

``` text
gcc
make
autoconf
phpize
php-config
PHP development headers
```

Verify the environment:

``` bash
php -v
phpize --version
php-config --version
gcc --version
make --version
```

Also check:

``` bash
which php
which phpize
which php-config
```

The development headers and `php-config` should correspond to the PHP
7.4 installation you are targeting.

------------------------------------------------------------------------

## 4. Create a Simple Extension

Create the project:

``` bash
mkdir myext
cd myext
```

We will implement:

``` php
myext_hello();
```

which returns:

``` text
Hello from my PHP extension!
```

------------------------------------------------------------------------

## 5. Create `config.m4`

``` m4
PHP_ARG_ENABLE(
    myext,
    whether to enable myext support,
    [ --enable-myext   Enable myext support]
)

if test "$PHP_MYEXT" != "no"; then
    PHP_NEW_EXTENSION(
        myext,
        myext.c,
        $ext_shared
    )
fi
```

`config.m4` tells the Unix PHP build system how to build the extension.

The important part is:

``` m4
PHP_NEW_EXTENSION(
    myext,
    myext.c,
    $ext_shared
)
```

The resulting Linux extension will normally be:

``` text
myext.so
```

------------------------------------------------------------------------

## 6. Create `php_myext.h`

``` c
#ifndef PHP_MYEXT_H
#define PHP_MYEXT_H

extern zend_module_entry myext_module_entry;

#define phpext_myext_ptr &myext_module_entry
#define PHP_MYEXT_VERSION "1.0.0"

PHP_FUNCTION(myext_hello);

#endif
```

This declares the PHP function:

``` php
myext_hello();
```

------------------------------------------------------------------------

## 7. Create `myext.c`

``` c
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_myext.h"

PHP_FUNCTION(myext_hello)
{
    RETURN_STRING("Hello from my PHP extension!");
}

static const zend_function_entry myext_functions[] = {
    PHP_FE(myext_hello, NULL)
    PHP_FE_END
};

zend_module_entry myext_module_entry = {
    STANDARD_MODULE_HEADER,

    "myext",
    myext_functions,

    NULL,
    NULL,
    NULL,
    NULL,
    NULL,

    PHP_MYEXT_VERSION,

    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_MYEXT
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif

ZEND_GET_MODULE(myext)

#endif
```

------------------------------------------------------------------------

## 8. Understanding `PHP_FUNCTION`

This code:

``` c
PHP_FUNCTION(myext_hello)
{
    RETURN_STRING("Hello from my PHP extension!");
}
```

implements the PHP function:

``` php
myext_hello();
```

Conceptually:

``` text
PHP
myext_hello()
     │
     ▼
Zend Engine
     │
     ▼
PHP_FUNCTION(myext_hello)
     │
     ▼
C implementation
```

------------------------------------------------------------------------

## 9. Registering Functions

Functions exposed to PHP are registered in a `zend_function_entry`
array:

``` c
static const zend_function_entry myext_functions[] = {
    PHP_FE(myext_hello, NULL)
    PHP_FE_END
};
```

If the extension later contains:

``` text
myext_encrypt()
myext_decrypt()
myext_verify()
```

the table could become:

``` c
static const zend_function_entry myext_functions[] = {
    PHP_FE(myext_hello, NULL)
    PHP_FE(myext_encrypt, NULL)
    PHP_FE(myext_decrypt, NULL)
    PHP_FE(myext_verify, NULL)
    PHP_FE_END
};
```

------------------------------------------------------------------------

## 10. Module Entry

The module entry describes the extension to PHP:

``` c
zend_module_entry myext_module_entry = {
    STANDARD_MODULE_HEADER,

    "myext",
    myext_functions,

    NULL,
    NULL,
    NULL,
    NULL,
    NULL,

    PHP_MYEXT_VERSION,

    STANDARD_MODULE_PROPERTIES
};
```

It provides information such as the extension name, exposed functions,
lifecycle callbacks, and version.

------------------------------------------------------------------------

## 11. Prepare the Build with `phpize`

Run:

``` bash
phpize
```

This generates the extension build infrastructure, including the
`configure` script.

------------------------------------------------------------------------

## 12. Configure the Build

Run:

``` bash
./configure --enable-myext
```

When multiple PHP installations exist, explicitly select the target
`php-config`:

``` bash
./configure \
    --enable-myext \
    --with-php-config=/usr/bin/php-config
```

This helps prevent accidentally compiling against the wrong PHP
installation.

------------------------------------------------------------------------

## 13. Compile

Run:

``` bash
make
```

The compiled extension will normally be generated as:

``` text
modules/myext.so
```

Check it:

``` bash
ls -l modules/
```

------------------------------------------------------------------------

## 14. Test Without Installing

You can test the extension directly:

``` bash
php \
    -d extension="$(pwd)/modules/myext.so" \
    -r 'echo myext_hello(), PHP_EOL;'
```

Expected output:

``` text
Hello from my PHP extension!
```

This is useful during development because you do not need to install the
extension after every build.

------------------------------------------------------------------------

## 15. Install the Extension

After testing:

``` bash
sudo make install
```

Check PHP's extension directory:

``` bash
php-config --extension-dir
```

or:

``` bash
php -i | grep extension_dir
```

------------------------------------------------------------------------

## 16. Enable the Extension

Depending on the PHP installation, create an INI file such as:

``` text
/etc/php.d/40-myext.ini
```

with:

``` ini
extension=myext.so
```

Verify:

``` bash
php -m | grep myext
```

and:

``` bash
php --ri myext
```

Test again:

``` bash
php -r 'echo myext_hello(), PHP_EOL;'
```

------------------------------------------------------------------------

## 17. Accepting Parameters

Example PHP:

``` php
myext_hello("Samol");
```

C implementation:

``` c
PHP_FUNCTION(myext_hello)
{
    char *name;
    size_t name_len;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(name, name_len)
    ZEND_PARSE_PARAMETERS_END();

    php_printf("Hello, %s", name);

    RETURN_TRUE;
}
```

This section:

``` c
ZEND_PARSE_PARAMETERS_START(1, 1)
    Z_PARAM_STRING(name, name_len)
ZEND_PARSE_PARAMETERS_END();
```

means that the function requires exactly one string parameter.

------------------------------------------------------------------------

## 18. Multiple Parameters

PHP:

``` php
$result = myext_add(10, 20);
```

C:

``` c
PHP_FUNCTION(myext_add)
{
    zend_long a;
    zend_long b;

    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_LONG(a)
        Z_PARAM_LONG(b)
    ZEND_PARSE_PARAMETERS_END();

    RETURN_LONG(a + b);
}
```

PHP:

``` php
echo myext_add(10, 20);
```

Output:

``` text
30
```

------------------------------------------------------------------------

## 19. Returning Values

### String

``` c
RETURN_STRING("Test successful");
```

### Boolean

``` c
RETURN_TRUE;
```

or:

``` c
RETURN_FALSE;
```

### Integer

``` c
RETURN_LONG(123);
```

------------------------------------------------------------------------

## 20. Returning an Array

``` c
PHP_FUNCTION(myext_info)
{
    array_init(return_value);

    add_assoc_string(
        return_value,
        "name",
        "myext"
    );

    add_assoc_string(
        return_value,
        "version",
        PHP_MYEXT_VERSION
    );

    add_assoc_bool(
        return_value,
        "enabled",
        1
    );
}
```

PHP:

``` php
$info = myext_info();
print_r($info);
```

Possible output:

``` text
Array
(
    [name] => myext
    [version] => 1.0.0
    [enabled] => 1
)
```

------------------------------------------------------------------------

## 21. Argument Information

For production extensions, define argument metadata.

Example:

``` c
ZEND_BEGIN_ARG_INFO_EX(
    arginfo_myext_add,
    0,
    0,
    2
)

    ZEND_ARG_INFO(0, a)
    ZEND_ARG_INFO(0, b)

ZEND_END_ARG_INFO()
```

Register it:

``` c
static const zend_function_entry myext_functions[] = {
    PHP_FE(myext_add, arginfo_myext_add)
    PHP_FE_END
};
```

------------------------------------------------------------------------

## 22. Extension Lifecycle

Important lifecycle callbacks include:

``` text
MINIT
MSHUTDOWN
RINIT
RSHUTDOWN
MINFO
```

Conceptually:

``` text
PHP process starts
       │
       ▼
     MINIT
       │
       ▼
Request starts
       │
       ▼
     RINIT
       │
       ▼
PHP executes request
       │
       ▼
   RSHUTDOWN
       │
       └── next request

PHP process stops
       │
       ▼
   MSHUTDOWN
```

### MINIT

``` c
PHP_MINIT_FUNCTION(myext)
{
    return SUCCESS;
}
```

Possible uses include registering constants, classes, INI settings, and
initializing native libraries.

### RINIT

``` c
PHP_RINIT_FUNCTION(myext)
{
    return SUCCESS;
}
```

Use it for request-specific initialization.

### RSHUTDOWN

``` c
PHP_RSHUTDOWN_FUNCTION(myext)
{
    return SUCCESS;
}
```

Use it for request-specific cleanup.

### MSHUTDOWN

``` c
PHP_MSHUTDOWN_FUNCTION(myext)
{
    return SUCCESS;
}
```

Use it for module/process shutdown cleanup.

------------------------------------------------------------------------

## 23. `MINFO`

`MINFO` controls extension information displayed by:

``` bash
php --ri myext
```

or:

``` php
phpinfo();
```

Include:

``` c
#include "ext/standard/info.h"
```

Then:

``` c
PHP_MINFO_FUNCTION(myext)
{
    php_info_print_table_start();

    php_info_print_table_header(
        2,
        "myext support",
        "enabled"
    );

    php_info_print_table_row(
        2,
        "Version",
        PHP_MYEXT_VERSION
    );

    php_info_print_table_end();
}
```

------------------------------------------------------------------------

## 24. Register Lifecycle Functions

The module entry can become:

``` c
zend_module_entry myext_module_entry = {
    STANDARD_MODULE_HEADER,

    "myext",
    myext_functions,

    PHP_MINIT(myext),
    PHP_MSHUTDOWN(myext),
    PHP_RINIT(myext),
    PHP_RSHUTDOWN(myext),
    PHP_MINFO(myext),

    PHP_MYEXT_VERSION,

    STANDARD_MODULE_PROPERTIES
};
```

------------------------------------------------------------------------

## 25. Recommended Architecture for a Larger Security Extension

Do not put all implementation logic directly inside `PHP_FUNCTION()`.

A cleaner project is:

``` text
sec_extension/
├── config.m4
├── php_sec.h
├── sec.c
└── src/
    ├── sec_crypto.c
    ├── sec_crypto.h
    ├── sec_key.c
    ├── sec_key.h
    ├── sec_certificate.c
    ├── sec_certificate.h
    ├── sec_document.c
    └── sec_document.h
```

Architecture:

``` text
PHP 7.4 / Zend Framework 1
          │
          ▼
       sec.c
          │
    ┌─────┼─────────────┐
    ▼     ▼             ▼
 Crypto  Key      Certificate/
 logic   logic    Document logic
```

------------------------------------------------------------------------

## 26. Keep the Public PHP API Small

Avoid unnecessarily exposing low-level operations such as:

``` php
sec_get_private_key();
sec_read_raw_key();
```

Prefer higher-level business operations such as:

``` php
sec_import_document(...);
sec_decrypt_document(...);
sec_verify_certificate(...);
sec_get_document_info(...);
```

This keeps internal implementation details away from normal PHP
application code.

------------------------------------------------------------------------

## 27. Cryptographic Implementation

Do not implement cryptographic primitives such as AES or RSA yourself.
Use a well-established cryptographic library.

Architecture:

``` text
PHP 7.4
   │
   ▼
sec.so
   │
   ▼
Application-specific native logic
   │
   ▼
Established cryptographic library
```

A compiled extension can hide ordinary implementation details from PHP
source code, but compilation alone is not a cryptographic security
boundary. Native binaries can be reverse-engineered. Secure key
management, authorization, authenticated encryption, protocol design,
and secure storage are still required.

------------------------------------------------------------------------

## 28. Testing with `.phpt`

PHP extensions use `.phpt` tests.

Example:

``` text
tests/001-hello.phpt
```

``` text
--TEST--
Test myext_hello()

--SKIPIF--
<?php
if (!extension_loaded('myext')) {
    die('skip myext not loaded');
}
?>

--FILE--
<?php
echo myext_hello();
?>

--EXPECT--
Hello from my PHP extension!
```

Run extension tests with:

``` bash
make test
```

A security-sensitive extension should test cases such as:

``` text
Valid data
Invalid data
Empty input
Corrupted ciphertext
Wrong key
Wrong certificate
Expired certificate
Oversized input
Malformed binary input
Repeated calls
Concurrent requests
```

------------------------------------------------------------------------

## 29. Debugging Native Extensions

Native extension bugs can crash the PHP process with a segmentation
fault.

On Linux, GDB is useful:

``` bash
gdb --args php \
    -d extension=/path/to/myext.so \
    test.php
```

Inside GDB:

``` text
(gdb) run
```

If PHP crashes:

``` text
(gdb) bt
```

`bt` displays a native backtrace.

------------------------------------------------------------------------

## 30. Memory Management

C extension development requires understanding:

``` text
allocation
ownership
lifetime
pointers
bounds
cleanup
```

Mistakes can cause:

``` text
memory leaks
buffer overflows
use-after-free
double-free
segmentation faults
security vulnerabilities
```

Before developing a security-sensitive extension, become comfortable
with C pointers, arrays, structures, memory allocation, binary buffers,
string lengths, const correctness, and error handling.

------------------------------------------------------------------------

## 31. Binary Data

Cryptographic data is binary and can contain zero bytes.

Do not generally determine ciphertext size using:

``` c
strlen(ciphertext)
```

Instead maintain a pointer and explicit length:

``` c
unsigned char *data;
size_t data_len;
```

The same principle is important for:

``` text
PDF data
ciphertext
IVs/nonces
authentication tags
certificates
keys
hashes
signatures
```

------------------------------------------------------------------------

## 32. Recommended Learning Path

Build the extension incrementally:

``` text
Stage 1
myext_hello()
    ↓
Learn compilation and loading

Stage 2
myext_add()
    ↓
Learn parameter parsing

Stage 3
myext_info()
    ↓
Learn PHP arrays

Stage 4
myext_hash()
    ↓
Learn binary input/output

Stage 5
Integrate an established native library
    ↓
Learn external library integration

Stage 6
Classes and exceptions
    ↓
Build a clean PHP-facing API

Stage 7
Application-specific security operations
    ↓
Production extension
```

Do not begin with the complete security extension. First prove that the
PHP 7.4 native-extension build and test toolchain works correctly.

------------------------------------------------------------------------

## 33. Target Architecture

A larger extension can eventually follow this pattern:

``` text
                 Zend Framework 1
                        │
                        ▼
                    PHP 7.4
                        │
                        ▼
                 Application API
                        │
               ┌────────┼────────┐
               ▼        ▼        ▼
          certificate document  status
          verification operations API
               │        │
               └────┬───┘
                    ▼
                  sec.so
                    │
          ┌─────────┼──────────┐
          ▼         ▼          ▼
       Crypto    Key logic   Document
       module                processing
          │
          ▼
   Established crypto
        library
```

## 34. Suggested Next Exercise --- Complete PHP 7.4 Extension

The next exercise is a complete, compilable extension named `myext`.

It exposes:

``` php
myext_hello();
myext_add(10, 20);
myext_info();
```

Expected behavior:

``` php
<?php
echo myext_hello();
echo PHP_EOL;

echo myext_add(10, 20);
echo PHP_EOL;

print_r(myext_info());
```

The exercise teaches returning strings, parsing PHP parameters,
returning integers, creating PHP arrays, module/request lifecycle hooks,
`phpinfo()` integration, and PHPT testing.

### 34.1 Project Structure

``` text
myext/
├── config.m4
├── php_myext.h
├── myext.c
└── tests/
    ├── 001-load.phpt
    ├── 002-hello.phpt
    ├── 003-add.phpt
    └── 004-info.phpt
```

Create it:

``` bash
mkdir -p myext/tests
cd myext
```

### 34.2 `config.m4`

``` m4
PHP_ARG_ENABLE(
    myext,
    whether to enable myext support,
    [  --enable-myext       Enable myext support],
    no
)

if test "$PHP_MYEXT" != "no"; then
    PHP_NEW_EXTENSION(
        myext,
        myext.c,
        $ext_shared
    )
fi
```

This defines a shared extension named `myext`, built from `myext.c`. On
Linux the result will normally be `myext.so`.

### 34.3 `php_myext.h`

``` c
#ifndef PHP_MYEXT_H
#define PHP_MYEXT_H

extern zend_module_entry myext_module_entry;

#define phpext_myext_ptr &myext_module_entry
#define PHP_MYEXT_VERSION "1.0.0"

PHP_FUNCTION(myext_hello);
PHP_FUNCTION(myext_add);
PHP_FUNCTION(myext_info);

#endif
```

### 34.4 Complete `myext.c`

``` c
#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_myext.h"

/* Argument information */

ZEND_BEGIN_ARG_INFO_EX(
    arginfo_myext_hello,
    0,
    0,
    0
)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(
    arginfo_myext_add,
    0,
    0,
    2
)
    ZEND_ARG_INFO(0, a)
    ZEND_ARG_INFO(0, b)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(
    arginfo_myext_info,
    0,
    0,
    0
)
ZEND_END_ARG_INFO()

/* PHP functions */

PHP_FUNCTION(myext_hello)
{
    RETURN_STRING("Hello from my PHP extension!");
}

PHP_FUNCTION(myext_add)
{
    zend_long a;
    zend_long b;

    ZEND_PARSE_PARAMETERS_START(2, 2)
        Z_PARAM_LONG(a)
        Z_PARAM_LONG(b)
    ZEND_PARSE_PARAMETERS_END();

    RETURN_LONG(a + b);
}

PHP_FUNCTION(myext_info)
{
    array_init(return_value);

    add_assoc_string(
        return_value,
        "name",
        "myext"
    );

    add_assoc_string(
        return_value,
        "version",
        PHP_MYEXT_VERSION
    );

    add_assoc_string(
        return_value,
        "php_version",
        PHP_VERSION
    );
}

/* Function registration */

static const zend_function_entry myext_functions[] = {
    PHP_FE(myext_hello, arginfo_myext_hello)
    PHP_FE(myext_add, arginfo_myext_add)
    PHP_FE(myext_info, arginfo_myext_info)
    PHP_FE_END
};

/* Module lifecycle */

PHP_MINIT_FUNCTION(myext)
{
    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(myext)
{
    return SUCCESS;
}

PHP_RINIT_FUNCTION(myext)
{
#if defined(ZTS) && defined(COMPILE_DL_MYEXT)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(myext)
{
    return SUCCESS;
}

/* phpinfo() */

PHP_MINFO_FUNCTION(myext)
{
    php_info_print_table_start();

    php_info_print_table_header(
        2,
        "myext support",
        "enabled"
    );

    php_info_print_table_row(
        2,
        "Version",
        PHP_MYEXT_VERSION
    );

    php_info_print_table_end();
}

/* Module definition */

zend_module_entry myext_module_entry = {
    STANDARD_MODULE_HEADER,

    "myext",
    myext_functions,

    PHP_MINIT(myext),
    PHP_MSHUTDOWN(myext),
    PHP_RINIT(myext),
    PHP_RSHUTDOWN(myext),
    PHP_MINFO(myext),

    PHP_MYEXT_VERSION,

    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_MYEXT

#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif

ZEND_GET_MODULE(myext)

#endif
```

### 34.5 Understanding `myext_hello()`

``` c
PHP_FUNCTION(myext_hello)
{
    RETURN_STRING("Hello from my PHP extension!");
}
```

This teaches how a native C function returns a PHP string.

``` text
PHP myext_hello()
       ↓
Zend Engine
       ↓
PHP_FUNCTION(myext_hello)
       ↓
RETURN_STRING(...)
       ↓
PHP string
```

### 34.6 Understanding `myext_add()`

``` c
zend_long a;
zend_long b;

ZEND_PARSE_PARAMETERS_START(2, 2)
    Z_PARAM_LONG(a)
    Z_PARAM_LONG(b)
ZEND_PARSE_PARAMETERS_END();

RETURN_LONG(a + b);
```

`ZEND_PARSE_PARAMETERS_START(2, 2)` means the minimum and maximum
parameter counts are both 2.

`Z_PARAM_LONG()` reads PHP integer arguments into `zend_long` values.
Prefer `zend_long` for PHP integers rather than assuming a C `int` has
the appropriate size.

### 34.7 Understanding `myext_info()`

PHP gives an extension a `return_value` variable. To return an array:

``` c
array_init(return_value);
```

Then populate it:

``` c
add_assoc_string(return_value, "name", "myext");
add_assoc_string(return_value, "version", PHP_MYEXT_VERSION);
```

Conceptually this corresponds to:

``` php
$result['name'] = 'myext';
$result['version'] = '1.0.0';
```

### 34.8 Lifecycle

The extension now contains:

``` text
MINIT       Module initialization
MSHUTDOWN   Module shutdown
RINIT       Request initialization
RSHUTDOWN   Request shutdown
MINFO       phpinfo()/php --ri information
```

The lifecycle is approximately:

``` text
PHP process starts
       ↓
     MINIT
       ↓
Request starts
       ↓
     RINIT
       ↓
PHP executes
       ↓
   RSHUTDOWN
       ↓
Next request ...

PHP process stops
       ↓
   MSHUTDOWN
```

### 34.9 Build the Extension

From the `myext` directory:

``` bash
phpize
```

Configure:

``` bash
./configure \
    --enable-myext \
    --with-php-config=/usr/bin/php-config
```

Compile:

``` bash
make
```

Check:

``` bash
ls -l modules/
```

You should normally see:

``` text
myext.so
```

### 34.10 Test Before Installation

Test `myext_hello()`:

``` bash
php \
    -d extension="$(pwd)/modules/myext.so" \
    -r 'echo myext_hello(), PHP_EOL;'
```

Expected:

``` text
Hello from my PHP extension!
```

Test addition:

``` bash
php \
    -d extension="$(pwd)/modules/myext.so" \
    -r 'echo myext_add(10, 20), PHP_EOL;'
```

Expected:

``` text
30
```

Test the information array:

``` bash
php \
    -d extension="$(pwd)/modules/myext.so" \
    -r 'print_r(myext_info());'
```

Expected approximately:

``` text
Array
(
    [name] => myext
    [version] => 1.0.0
    [php_version] => 7.4.x
)
```

### 34.11 PHPT Test: Extension Loading

Create `tests/001-load.phpt`:

``` text
--TEST--
Check whether myext is loaded

--SKIPIF--
<?php
if (!extension_loaded('myext')) {
    die('skip myext not loaded');
}
?>

--FILE--
<?php
echo extension_loaded('myext') ? 'loaded' : 'not loaded';
?>

--EXPECT--
loaded
```

### 34.12 PHPT Test: `myext_hello()`

Create `tests/002-hello.phpt`:

``` text
--TEST--
Test myext_hello()

--SKIPIF--
<?php
if (!extension_loaded('myext')) {
    die('skip myext not loaded');
}
?>

--FILE--
<?php
echo myext_hello();
?>

--EXPECT--
Hello from my PHP extension!
```

### 34.13 PHPT Test: `myext_add()`

Create `tests/003-add.phpt`:

``` text
--TEST--
Test myext_add()

--SKIPIF--
<?php
if (!extension_loaded('myext')) {
    die('skip myext not loaded');
}
?>

--FILE--
<?php
echo myext_add(10, 20);
?>

--EXPECT--
30
```

### 34.14 PHPT Test: `myext_info()`

Create `tests/004-info.phpt`:

``` text
--TEST--
Test myext_info()

--SKIPIF--
<?php
if (!extension_loaded('myext')) {
    die('skip myext not loaded');
}
?>

--FILE--
<?php
$info = myext_info();

echo $info['name'], PHP_EOL;
echo $info['version'], PHP_EOL;
?>

--EXPECT--
myext
1.0.0
```

Run all tests:

``` bash
make test
```

### 34.15 Install After Testing

Once the extension works:

``` bash
sudo make install
```

Find the extension directory:

``` bash
php-config --extension-dir
```

Enable it in the appropriate PHP configuration, for example:

``` text
/etc/php.d/40-myext.ini
```

with:

``` ini
extension=myext.so
```

Verify:

``` bash
php -m | grep myext
php --ri myext
```

### 34.16 Test from an Ordinary PHP Script

Create `test.php`:

``` php
<?php

echo "Extension loaded: ";
var_dump(extension_loaded('myext'));

echo PHP_EOL;

echo "Hello:" . PHP_EOL;
echo myext_hello();

echo PHP_EOL . PHP_EOL;

echo "10 + 20 = ";
echo myext_add(10, 20);

echo PHP_EOL . PHP_EOL;

echo "Extension information:" . PHP_EOL;
print_r(myext_info());
```

Run:

``` bash
php test.php
```

### 34.17 What This Exercise Teaches

``` text
myext_hello()
     ↓
Returning PHP strings

myext_add()
     ↓
Receiving PHP parameters
     ↓
zend_long
     ↓
Returning integers

myext_info()
     ↓
Creating PHP arrays

MINIT / MSHUTDOWN
     ↓
Module lifecycle

RINIT / RSHUTDOWN
     ↓
Request lifecycle

MINFO
     ↓
phpinfo() / php --ri

.phpt
     ↓
Automated extension testing
```

The complete flow is:

``` text
PHP application
      │
      ▼
PHP function call
      │
      ▼
Zend parameter parsing
      │
      ▼
Native C function
      │
      ▼
Native logic
      │
      ▼
return_value / RETURN_*
      │
      ▼
PHP value
```

------------------------------------------------------------------------

## 35. Recommended Next Exercise --- Binary Data and SHA-256

After the basic extension works reliably, the next useful exercise is
binary-safe data handling:

``` text
PHP binary/string data
        │
        ▼
myext_sha256($data)
        │
        ▼
C extension
        │
        ▼
Established cryptographic library
        │
        ▼
SHA-256 result
        │
        ▼
PHP
```

This exercise should cover:

-   binary-safe PHP parameters;
-   `char *` plus explicit lengths;
-   binary buffers containing zero bytes;
-   linking an external native library;
-   native error handling;
-   binary and hexadecimal return values;
-   PHPT tests.

These concepts should be understood before moving to document
encryption/decryption, certificate processing, or other
security-sensitive extension APIs.
