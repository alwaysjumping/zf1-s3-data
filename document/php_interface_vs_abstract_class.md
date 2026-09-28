# PHP Interface vs Abstract Class

## 1. Overview

Both **interfaces** and **abstract classes** help define a common
structure for related PHP classes, but they serve different purposes.

A simple way to remember the difference is:

> **Interface = defines what a class must do.**\
> **Abstract class = can define both what a class must do and how some
> of it is done.**

------------------------------------------------------------------------

## 2. Interface

An interface defines a **contract** that implementing classes must
follow.

``` php
interface LoggerInterface
{
    public function log($message);
}
```

A class implements the interface:

``` php
class FileLogger implements LoggerInterface
{
    public function log($message)
    {
        file_put_contents(
            'app.log',
            $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
```

Another class can implement the same interface differently:

``` php
class DatabaseLogger implements LoggerInterface
{
    public function log($message)
    {
        // Save the log message to the database
        echo "Saving to database: " . $message;
    }
}
```

Both classes guarantee that they provide:

``` php
log($message)
```

This allows other parts of the application to depend on
`LoggerInterface` rather than a particular implementation.

``` php
function saveLog(LoggerInterface $logger, $message)
{
    $logger->log($message);
}
```

------------------------------------------------------------------------

## 3. Abstract Class

An abstract class can contain:

-   Properties
-   Constructors
-   Normal implemented methods
-   Abstract methods that subclasses must implement

Example:

``` php
abstract class Logger
{
    protected $logFile;

    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }

    abstract public function log($message);

    public function getLogFile()
    {
        return $this->logFile;
    }
}
```

A child class extends it:

``` php
class FileLogger extends Logger
{
    public function log($message)
    {
        file_put_contents(
            $this->logFile,
            $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
```

The abstract class provides reusable functionality, while the subclass
provides the implementation of the abstract method.

------------------------------------------------------------------------

## 4. Main Differences

  -----------------------------------------------------------------------
  Feature                 Interface               Abstract Class
  ----------------------- ----------------------- -----------------------
  Main purpose            Define a contract       Provide a common base
                                                  implementation

  Keyword used by child   `implements`            `extends`
  class                                           

  Abstract methods        Yes                     Yes

  Implemented methods     Interface methods       Yes
                          define contracts        

  Instance properties     No                      Yes

  Constructor             No                      Yes
  implementation                                  

  Reusable implementation Generally no            Yes

  Multiple use            A class can implement   A class can extend only
                          many interfaces         one class

  Can instantiate         No                      No
  directly                                        
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 5. Multiple Interfaces

One major advantage of interfaces is that a PHP class can implement
multiple interfaces.

``` php
class DocumentService implements
    LoggerInterface,
    StorageInterface,
    EncryptableInterface
{
    // Implement methods required by all interfaces
}
```

PHP does not allow a class to extend multiple classes.

The following is invalid:

``` php
// INVALID PHP

class DocumentService extends
    Logger,
    Storage,
    Encryption
{
}
```

A PHP class can extend only one parent class:

``` php
class DocumentService extends BaseService
{
}
```

------------------------------------------------------------------------

## 6. When to Use an Interface

Use an interface when different classes need to guarantee the same
**capability or contract**, even when their internal implementations are
completely different.

For example:

``` php
interface EncryptorInterface
{
    public function encrypt($data);

    public function decrypt($data);
}
```

One implementation could use AES:

``` php
class AesEncryptor implements EncryptorInterface
{
    public function encrypt($data)
    {
        // AES encryption implementation
    }

    public function decrypt($data)
    {
        // AES decryption implementation
    }
}
```

Another implementation could call a custom PHP extension:

``` php
class SecureExtensionEncryptor implements EncryptorInterface
{
    public function encrypt($data)
    {
        // Call custom PHP extension
    }

    public function decrypt($data)
    {
        // Call custom PHP extension
    }
}
```

The application can depend on the interface:

``` php
class DocumentService
{
    private $encryptor;

    public function __construct(EncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
    }
}
```

`DocumentService` does not need to know how encryption is implemented.

This produces **loose coupling**.

------------------------------------------------------------------------

## 7. When to Use an Abstract Class

Use an abstract class when related classes need to share actual
implementation, such as:

-   Database connections
-   Properties
-   Constructors
-   Validation logic
-   Logging helpers
-   Common utility methods

Example:

``` php
abstract class BaseDocumentService
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getDatabase()
    {
        return $this->db;
    }

    abstract public function save($document);
}
```

A subclass can reuse the common implementation:

``` php
class PdfDocumentService extends BaseDocumentService
{
    public function save($document)
    {
        // PDF-specific save implementation
    }
}
```

------------------------------------------------------------------------

## 8. Using an Interface and Abstract Class Together

Interfaces and abstract classes are not competitors. They are often used
together.

``` php
interface DocumentServiceInterface
{
    public function save($document);
}
```

Then create an abstract implementation:

``` php
abstract class AbstractDocumentService
    implements DocumentServiceInterface
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function validate($document)
    {
        // Common validation
    }
}
```

Finally, create the concrete implementation:

``` php
class PdfDocumentService extends AbstractDocumentService
{
    public function save($document)
    {
        $this->validate($document);

        // Save PDF...
    }
}
```

The relationship is:

``` text
DocumentServiceInterface
        |
        | implements
        v
AbstractDocumentService
        |
        | extends
        v
PdfDocumentService
```

The interface defines the contract:

> Any document service must provide `save()`.

The abstract class provides shared functionality:

> All document services share database and validation functionality.

The concrete class provides the specific implementation:

> `PdfDocumentService` defines how PDF documents are saved.

------------------------------------------------------------------------

## 9. Practical Design Example

Consider a document-management application.

Start with a storage interface:

``` php
interface DocumentStorageInterface
{
    public function save($filename, $data);

    public function read($filename);

    public function delete($filename);
}
```

Create an abstract base class:

``` php
abstract class AbstractDocumentStorage
    implements DocumentStorageInterface
{
    protected function validateFilename($filename)
    {
        if (empty($filename)) {
            throw new InvalidArgumentException(
                'Filename cannot be empty.'
            );
        }
    }
}
```

Then implement local storage:

``` php
class LocalDocumentStorage extends AbstractDocumentStorage
{
    public function save($filename, $data)
    {
        $this->validateFilename($filename);

        file_put_contents($filename, $data);
    }

    public function read($filename)
    {
        $this->validateFilename($filename);

        return file_get_contents($filename);
    }

    public function delete($filename)
    {
        $this->validateFilename($filename);

        unlink($filename);
    }
}
```

Later, you could add another implementation:

``` php
class DatabaseDocumentStorage extends AbstractDocumentStorage
{
    public function save($filename, $data)
    {
        $this->validateFilename($filename);

        // Save to MariaDB
    }

    public function read($filename)
    {
        $this->validateFilename($filename);

        // Read from MariaDB
    }

    public function delete($filename)
    {
        $this->validateFilename($filename);

        // Delete from MariaDB
    }
}
```

The rest of the application can depend on:

``` php
DocumentStorageInterface
```

rather than directly depending on:

``` php
LocalDocumentStorage
```

or:

``` php
DatabaseDocumentStorage
```

This makes the application easier to modify and test.

------------------------------------------------------------------------

## 10. Simple Rule to Remember

### Interface

Think:

> **"What must this object be able to do?"**

Example:

``` php
interface EncryptorInterface
{
    public function encrypt($data);
    public function decrypt($data);
}
```

### Abstract Class

Think:

> **"What common code and state should these related classes share?"**

Example:

``` php
abstract class AbstractEncryptor
{
    protected $key;

    public function __construct($key)
    {
        $this->key = $key;
    }

    abstract public function encrypt($data);

    abstract public function decrypt($data);
}
```

------------------------------------------------------------------------

## 11. Recommended Design Approach

For medium and large PHP applications:

1.  Use **interfaces** to define important service contracts.
2.  Type-hint against interfaces where practical.
3.  Use **abstract classes** only when subclasses genuinely share
    implementation.
4.  Keep concrete classes responsible for implementation-specific
    behavior.
5.  Avoid putting unrelated functionality into a large base class.

A common architecture is:

``` text
Interface
   |
   v
Abstract Base Class
   |
   +-------------------+
   |                   |
   v                   v
Implementation A   Implementation B
```

For example:

``` text
DocumentStorageInterface
          |
          v
AbstractDocumentStorage
          |
     +----+----+
     |         |
     v         v
LocalStorage  DatabaseStorage
```

This approach provides a clear contract, reusable common code, loose
coupling, and easier unit testing.
