# Understanding Asynchronous JavaScript

**From Callbacks to Promises, Async/Await, and the Event Loop**

- **Level:** Beginner to advanced
- **Focus:** Browser JavaScript, AJAX, scheduling, concurrency, and practical debugging
- **Legacy browser targets:** Chrome 49 and Firefox 52 (transpilation and polyfills required for newer features)

## 1. Introduction

Asynchronous programming lets a JavaScript application initiate work—such as HTTP requests, timers, or user-driven events—without synchronously waiting for that work to finish. It is essential for responsive browser interfaces, but it is often misunderstood. In particular, asynchronous code is not necessarily parallel, and `async`/`await` does not move CPU-intensive work off the main thread.

This article explains the underlying execution model and demonstrates callbacks, Promises, async/await, concurrent requests, error handling, and legacy-browser integration.

## 2. Synchronous execution and blocking

Synchronous statements run in order:

```javascript
console.log('Step 1');
console.log('Step 2');
console.log('Step 3');
```

Output:

```text
Step 1
Step 2
Step 3
```

A long-running synchronous operation blocks subsequent JavaScript on the same thread:

```javascript
function expensiveCalculation() {
    var started = Date.now();
    while (Date.now() - started < 3000) {
        // Deliberately occupy the main thread for about 3 seconds.
    }
    return 'Completed';
}

console.log('Start');
console.log(expensiveCalculation());
console.log('End');
```

While this loop runs, the browser may be unable to respond promptly to input or paint updates. Use Web Workers or break heavy computations into smaller chunks when appropriate; simply declaring a function `async` does not fix CPU blocking.

## 3. Asynchronous execution with timers

```javascript
console.log('Start');

setTimeout(function () {
    console.log('Timer completed');
}, 2000);

console.log('End');
```

Typical output:

```text
Start
End
Timer completed
```

The timer schedules a callback for a future task. The surrounding code keeps running. The callback cannot execute until the event loop is able to run it; a timer delay is not an exact execution deadline.

## 4. Single-threaded JavaScript and browser services

Ordinary JavaScript on a browser page's main thread executes one stack frame at a time. The browser, however, can manage timers, networking, rendering, and other work outside that stack. Web Workers provide separate JavaScript execution contexts when actual parallel computation is needed.

```text
Main JavaScript thread           Browser facilities
----------------------           ------------------
Run synchronous code   ------->  Start timer / network request
Continue other work              Operation progresses
Finish current task    <-------  Queue callback when ready
Run scheduled callback
```

The diagram is conceptual; browser scheduling has multiple task sources and rendering steps.

## 5. The call stack

The call stack records the chain of active function calls:

```javascript
function first() { second(); }
function second() { third(); }
function third() { console.log('Hello'); }
first();
```

At the deepest point:

```text
+----------------+
| third()        |
+----------------+
| second()       |
+----------------+
| first()        |
+----------------+
| Global script  |
+----------------+
```

Calls return in last-in, first-out order. A long-running function keeps the main thread occupied, delaying timer callbacks, Promise reactions, and often rendering.

## 6. The event loop: tasks and microtasks

A simplified browser event-loop cycle is:

1. Run a task (for example, initial script execution or a timer callback).
2. When the stack becomes empty, perform a microtask checkpoint: run queued microtasks until the queue is empty.
3. The browser may perform rendering work.
4. Continue with another task when one is available.

Promise reaction handlers (`.then`, `.catch`, `.finally`) are scheduled as microtasks. Timer callbacks run as tasks.

### Example: a zero-delay timer

```javascript
console.log('1');
setTimeout(function () { console.log('2'); }, 0);
console.log('3');
```

Output:

```text
1
3
2
```

`setTimeout(..., 0)` does not mean “execute now”; it schedules work for a later task.

### Example: Promise versus timer

```javascript
console.log('Start');

setTimeout(function () {
    console.log('Timer');
}, 0);

Promise.resolve().then(function () {
    console.log('Promise');
});

console.log('End');
```

Output:

```text
Start
End
Promise
Timer
```

The synchronous script finishes first, then the Promise microtask runs before the timer task. Excessive recursive microtask scheduling can delay rendering and other tasks.

## 7. Callback-based asynchronous programming

Callbacks are functions invoked after an operation completes:

```javascript
function loadData(callback) {
    setTimeout(function () {
        callback({ id: 100, name: 'John' });
    }, 1000);
}

loadData(function (user) {
    console.log(user.name);
});
```

Callbacks work in ES5 and remain useful for events. The difficulty appears when operations depend on one another and each level needs error handling:

```javascript
loadUser(100, function (user) {
    loadEmails(user.id, function (emails) {
        loadAttachments(emails[0].id, function (files) {
            console.log(files);
        });
    });
});
```

This deep nesting is often called **callback hell**. The example is schematic: `loadUser`, `loadEmails`, and `loadAttachments` must be supplied by the application.

## 8. Promises

A Promise represents a future result. Its state is **pending**, **fulfilled**, or **rejected**. Once settled, it cannot change state.

```javascript
function loadUser() {
    return new Promise(function (resolve, reject) {
        setTimeout(function () {
            resolve({ id: 100, name: 'John' });
        }, 1000);
    });
}

loadUser()
    .then(function (user) {
        console.log(user.name);
    })
    .catch(function (error) {
        console.error(error);
    });
```

A Promise is not a background thread. The Promise executor itself runs synchronously; the timer in this example makes completion asynchronous.

### Promise chaining

```javascript
loadUser(100)
    .then(function (user) {
        return loadEmails(user.id);
    })
    .then(function (emails) {
        return loadAttachments(emails[0].id);
    })
    .then(function (files) {
        console.log(files);
    })
    .catch(function (error) {
        console.error(error);
    });
```

**Return the next Promise** inside `.then()` so that the chain waits for it and propagates failures. These functions are application-specific placeholders.

## 9. Async/await

`async`/`await` provides a readable syntax for Promise-based workflows:

```javascript
async function showUserEmails() {
    try {
        const user = await loadUser(100);
        const emails = await loadEmails(user.id);
        const files = await loadAttachments(emails[0].id);
        console.log(files);
    } catch (error) {
        console.error(error);
    }
}
```

An `async` function always returns a Promise. `await` suspends that function's continuation until the awaited value settles; it does **not** block the entire browser.

```javascript
async function getNumber() {
    return 100;
}

getNumber().then(function (number) {
    console.log(number); // 100
});
```

An exception thrown in an async function rejects its returned Promise. Handle rejections with `try/catch` around `await`, or `.catch()` on the returned Promise.

## 10. Sequential versus concurrent requests

### Sequential

```javascript
async function loadSequentially() {
    const users = await loadUsers();
    const products = await loadProducts();
    return { users: users, products: products };
}
```

If each request takes roughly two seconds, the total can approach four seconds.

### Concurrent

```javascript
async function loadConcurrently() {
    const results = await Promise.all([
        loadUsers(),
        loadProducts()
    ]);

    return {
        users: results[0],
        products: results[1]
    };
}
```

If both requests start promptly and take roughly two seconds, total time may approach two seconds. `Promise.all()` rejects if any input rejects; it does not automatically cancel the others. Concurrency is not the same as executing JavaScript in parallel on multiple CPU cores. For large batches, limit concurrent requests to protect the server.

## 11. Practical jQuery AJAX integration

A legacy PHP application may use jQuery AJAX:

```javascript
$.ajax({
    url: '/api/email/getlist',
    type: 'GET',
    dataType: 'json',
    success: function (response) {
        console.log(response);
    },
    error: function (xhr) {
        console.error(xhr.status);
    }
});
```

The request is asynchronous: JavaScript can continue while the server processes it. jQuery's `jqXHR` exposes Deferred-style methods such as `.done()` and `.fail()`:

```javascript
$.ajax({ url: '/api/email/getlist', dataType: 'json' })
    .done(function (response) {
        console.log(response);
    })
    .fail(function (xhr) {
        console.error(xhr.status);
    });
```

**Compatibility note:** jQuery Deferred behavior differs across versions, especially older releases. It should not be assumed identical to native Promises.

### Wrap AJAX in a native Promise

```javascript
function getEmailList() {
    return new Promise(function (resolve, reject) {
        $.ajax({
            url: '/api/email/getlist',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                resolve(response);
            },
            error: function (xhr) {
                reject(xhr);
            }
        });
    });
}

async function displayEmails() {
    try {
        const response = await getEmailList();
        console.log(response);
    } catch (error) {
        console.error('Failed to load emails', error);
    }
}
```

This demonstrates Promise interoperability. The wrapper does not expose the underlying `jqXHR.abort()` method; if cancellation is required, design an API that retains the request handle and documents cancellation behavior.

## 12. Legacy browser compatibility: Chrome 49 and Firefox 52

These browsers do not natively support `async`/`await`. A build pipeline can transform newer syntax:

```text
Modern JavaScript source
          |
          v
     Babel transpilation
          |
          v
ES5-compatible output + required runtime support
          |
          v
Chrome 49 / Firefox 52
```

Example `babel.config.json`:

```json
{
  "presets": [
    [
      "@babel/preset-env",
      {
        "targets": {
          "chrome": "49",
          "firefox": "52"
        }
      }
    ]
  ]
}
```

**Important limitations:**

- Syntax transpilation does not automatically provide every missing browser API.
- Async transformations may require runtime helpers or generator support, depending on Babel configuration.
- Promise support and any other required polyfills must be verified for the selected browsers.
- ES modules may need bundling or module transformation if the application loads classic `<script>` files.
- Test the actual generated output in the target browsers; Babel configuration alone is not a compatibility guarantee.

## 13. Common mistakes

### Mistake A: returning from an asynchronous callback

```javascript
function getData() {
    setTimeout(function () {
        return 100;
    }, 1000);
}

console.log(getData()); // undefined
```

The callback's return value is not the return value of `getData()`.

Correct callback-based version:

```javascript
function getData(callback) {
    setTimeout(function () {
        callback(100);
    }, 1000);
}

getData(function (value) {
    console.log(value);
});
```

### Mistake B: forgetting `await`

```javascript
async function test() {
    const userPromise = loadUser(100);
    console.log(userPromise); // Promise, not the resolved user
}
```

Correct:

```javascript
async function test() {
    const user = await loadUser(100);
    console.log(user);
}
```

### Mistake C: accidentally serializing independent work

```javascript
for (const id of ids) {
    await loadUser(id);
}
```

For a reasonably small number of independent requests:

```javascript
const users = await Promise.all(
    ids.map(function (id) {
        return loadUser(id);
    })
);
```

For hundreds or thousands of IDs, use a bounded concurrency pool rather than starting all requests at once.

### Mistake D: assuming `async` makes CPU work nonblocking

```javascript
async function stillBlocks() {
    var start = Date.now();
    while (Date.now() - start < 3000) {}
    return 'done';
}
```

Calling `stillBlocks()` still runs the loop synchronously before its returned Promise can settle. Use a Worker or redesign the computation.

## 14. Practical enterprise use cases

| Feature | Asynchronous activity |
|---|---|
| Email | Load messages and attachments |
| Notifications | Poll APIs or receive WebSocket events |
| Messaging | Send messages and update conversation state |
| Search | Debounced API requests |
| Forms | Remote validation |
| Dashboards | Load independent widgets concurrently |
| File uploads | Monitor progress and completion |
| Reports | Poll background job status |

A centralized AJAX layer can standardize authentication errors, retries, cancellation, and response parsing. However, retries should be safe for the specific operation: automatically retrying a non-idempotent POST may duplicate business effects unless the server supports idempotency keys.

## 15. Advanced questions for further research

1. Why do Promise callbacks generally run before zero-delay timer callbacks?
2. Can microtasks starve rendering?
3. How are exceptions propagated through Promise chains?
4. What happens if a Promise remains pending forever?
5. How can a browser request be cancelled safely?
6. What is the difference between concurrency and parallelism?
7. How do race conditions arise when search responses arrive out of order?
8. How should a large application handle unhandled Promise rejections?
9. How can a frontend enforce request timeouts and concurrency limits?
10. How should older jQuery Deferred code be migrated to native Promises?

## 16. Conclusion

Callbacks, Promises, and async/await are different ways to organize asynchronous workflows; they do not replace the event loop. Understanding the call stack, tasks, microtasks, and browser scheduling is essential for writing responsive and reliable JavaScript applications.

**Key takeaway:** The most useful question is not merely “How do I write `await`?” but “What runs now, what runs later, and what happens if the operation fails, overlaps, or never completes?”

## References and further reading

- MDN Web Docs — *JavaScript execution model*: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Execution_model
- MDN Web Docs — *Using promises*: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Using_promises
- MDN Web Docs — *async function*: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Statements/async_function
- MDN Web Docs — *Promise.all()*: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
- MDN Web Docs — *Microtask guide*: https://developer.mozilla.org/en-US/docs/Web/API/HTML_DOM_API/Microtask_guide
- Babel — *@babel/preset-env*: https://babeljs.io/docs/babel-preset-env
- jQuery API — *jQuery.ajax()*: https://api.jquery.com/jQuery.ajax/

---

*Article prepared as a standalone technical guide with ES5 examples and notes for transpiled modern JavaScript.*
