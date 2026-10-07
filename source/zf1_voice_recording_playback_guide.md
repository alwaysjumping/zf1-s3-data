# ZF1 Voice Recording and Playback Guide

## PHP 7.4 + Zend Framework 1 + JavaScript MediaRecorder

This guide describes a practical architecture for adding browser-based voice recording and secure audio playback to an existing Zend Framework 1 (ZF1) application.

The examples target:

- PHP 7.4
- Zend Framework 1
- JavaScript / jQuery
- MariaDB
- Modern Chrome, Firefox, and Safari
- Existing DHTMLX 3.5 applications

---

## 1. Architecture Overview

PHP/ZF1 does not directly record the user's microphone. Recording happens in the browser with the JavaScript MediaRecorder API.

```text
Microphone
    |
    v
Browser
getUserMedia() + MediaRecorder
    |
    v
Audio Blob
    |
    | AJAX / FormData
    v
ZF1 VoiceController::uploadAction()
    |
    +----> Filesystem: audio file
    |
    +----> MariaDB: metadata

Playback:

<audio>
    |
    | GET /voice/play/id/123
    v
ZF1 VoiceController::playAction()
    |
    +----> authentication / authorization
    +----> MariaDB metadata lookup
    +----> protected audio file
    |
    v
Browser audio player
```

Recommended ownership:

- Browser: microphone access, recording, preview and upload.
- ZF1: authentication, authorization, validation, metadata and secure delivery.
- Filesystem: actual audio files.
- MariaDB: recording metadata and business relationships.

Do not normally store large audio files directly as MariaDB BLOBs unless there is a specific requirement.

---

## 2. HTTPS and Microphone Permission

`navigator.mediaDevices.getUserMedia()` normally requires a secure browser context. Production applications should use HTTPS.

The browser asks the user for microphone permission. PHP cannot bypass this permission dialog.

```javascript
navigator.mediaDevices.getUserMedia({
    audio: true
});
```

If permission is denied, handle the error and tell the user that microphone permission is required to record.

---

## 3. Browser Format Compatibility

Do not assume every browser records exactly the same container/codec.

Use `MediaRecorder.isTypeSupported()` at runtime.

```javascript
function getSupportedAudioType()
{
    var types = [
        'audio/webm;codecs=opus',
        'audio/ogg;codecs=opus',
        'audio/mp4'
    ];

    for (var i = 0; i < types.length; i++) {
        if (MediaRecorder.isTypeSupported(types[i])) {
            return types[i];
        }
    }

    return '';
}
```

The exact supported formats depend on the browser and OS version. Therefore, feature detection is more reliable than browser-name detection.

A possible result is:

```text
Chrome/Firefox -> WebM/Opus where supported
Safari         -> MP4-family recording where supported
```

Do not hard-code every uploaded file as `.webm`.

---

## 4. Reusable Browser Recorder

### HTML

```html
<div id="voice-recorder">
    <button type="button" id="voice-start">Record</button>
    <button type="button" id="voice-pause" disabled>Pause</button>
    <button type="button" id="voice-resume" disabled>Resume</button>
    <button type="button" id="voice-stop" disabled>Stop</button>

    <div id="voice-status">Ready</div>

    <audio id="voice-preview" controls></audio>

    <button type="button" id="voice-upload" disabled>Save Recording</button>
    <button type="button" id="voice-reset" disabled>Re-record</button>
</div>
```

### JavaScript

```javascript
(function () {
    'use strict';

    var recorder = null;
    var stream = null;
    var chunks = [];
    var recordedBlob = null;
    var previewUrl = null;

    function getSupportedAudioType()
    {
        var types = [
            'audio/webm;codecs=opus',
            'audio/ogg;codecs=opus',
            'audio/mp4'
        ];

        for (var i = 0; i < types.length; i++) {
            if (MediaRecorder.isTypeSupported(types[i])) {
                return types[i];
            }
        }

        return '';
    }

    function setStatus(text)
    {
        document.getElementById('voice-status').textContent = text;
    }

    async function startRecording()
    {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: true
            });

            chunks = [];
            recordedBlob = null;

            var mimeType = getSupportedAudioType();
            var options = {};

            if (mimeType) {
                options.mimeType = mimeType;
            }

            recorder = new MediaRecorder(stream, options);

            recorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) {
                    chunks.push(event.data);
                }
            };

            recorder.onstop = function () {
                recordedBlob = new Blob(chunks, {
                    type: recorder.mimeType
                });

                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                }

                previewUrl = URL.createObjectURL(recordedBlob);
                document.getElementById('voice-preview').src = previewUrl;

                document.getElementById('voice-upload').disabled = false;
                document.getElementById('voice-reset').disabled = false;

                stopMicrophone();
                setStatus('Recording complete');
            };

            recorder.start();

            document.getElementById('voice-start').disabled = true;
            document.getElementById('voice-stop').disabled = false;
            document.getElementById('voice-pause').disabled = false;

            setStatus('Recording...');
        } catch (error) {
            console.error(error);
            setStatus('Unable to access microphone');
        }
    }

    function pauseRecording()
    {
        if (recorder && recorder.state === 'recording') {
            recorder.pause();
            document.getElementById('voice-pause').disabled = true;
            document.getElementById('voice-resume').disabled = false;
            setStatus('Paused');
        }
    }

    function resumeRecording()
    {
        if (recorder && recorder.state === 'paused') {
            recorder.resume();
            document.getElementById('voice-pause').disabled = false;
            document.getElementById('voice-resume').disabled = true;
            setStatus('Recording...');
        }
    }

    function stopRecording()
    {
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }

        document.getElementById('voice-stop').disabled = true;
        document.getElementById('voice-pause').disabled = true;
        document.getElementById('voice-resume').disabled = true;
        document.getElementById('voice-start').disabled = false;
    }

    function stopMicrophone()
    {
        if (!stream) {
            return;
        }

        stream.getTracks().forEach(function (track) {
            track.stop();
        });

        stream = null;
    }

    function extensionForMimeType(mimeType)
    {
        mimeType = (mimeType || '').toLowerCase();

        if (mimeType.indexOf('webm') !== -1) {
            return 'webm';
        }

        if (mimeType.indexOf('ogg') !== -1) {
            return 'ogg';
        }

        if (mimeType.indexOf('mp4') !== -1) {
            return 'mp4';
        }

        return 'bin';
    }

    function uploadRecording()
    {
        if (!recordedBlob) {
            return;
        }

        var extension = extensionForMimeType(recordedBlob.type);
        var formData = new FormData();

        formData.append(
            'audio',
            recordedBlob,
            'recording.' + extension
        );

        // Add your existing ZF1 CSRF token here.
        // formData.append('csrf_token', csrfToken);

        $.ajax({
            url: '/voice/upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                console.log(response);
                setStatus('Saved');
            },
            error: function (xhr) {
                console.error(xhr);
                setStatus('Upload failed');
            }
        });
    }

    function resetRecording()
    {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        recordedBlob = null;
        chunks = [];

        var player = document.getElementById('voice-preview');
        player.removeAttribute('src');
        player.load();

        document.getElementById('voice-upload').disabled = true;
        document.getElementById('voice-reset').disabled = true;

        setStatus('Ready');
    }

    document.getElementById('voice-start').onclick = startRecording;
    document.getElementById('voice-pause').onclick = pauseRecording;
    document.getElementById('voice-resume').onclick = resumeRecording;
    document.getElementById('voice-stop').onclick = stopRecording;
    document.getElementById('voice-upload').onclick = uploadRecording;
    document.getElementById('voice-reset').onclick = resetRecording;
}());
```

---

## 5. Recommended User Flow

```text
[ Record ]
     |
     v
Recording...
     |
     +---- [ Pause ] / [ Resume ]
     |
     v
[ Stop ]
     |
     v
Preview

[Play ---------------- 00:00 / 00:24]

     |
     +----> [ Re-record ]
     |
     +----> [ Save Recording ]
```

Previewing before upload is useful because the user can confirm that the microphone and recording are correct.

---

## 6. MariaDB Schema

Store metadata in MariaDB and the actual audio bytes in protected filesystem storage.

```sql
CREATE TABLE voice_recordings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    duration_ms INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_voice_user_id (user_id),
    KEY idx_voice_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
```

For a real business module, add the relationship to the owning record, for example:

```text
customer_id
message_id
email_id
task_id
document_id
```

Choose the relationship that matches your application's domain rather than making audio files globally accessible.

---

## 7. Protected File Storage

Do not place private voice files in a directly public web directory such as:

```text
/public/voice/
```

Prefer storage outside the document root:

```text
project/
    application/
    public/
    data/
        voice/
            2026/
                10/
                    random-file.webm
```

Then all playback goes through an authenticated/authorized ZF1 endpoint.

---

## 8. ZF1 Upload Action

The server must not trust:

- the client filename,
- the client file extension,
- the browser-provided MIME type,
- arbitrary paths supplied by the browser.

Example controller structure:

```php
<?php

class VoiceController extends Zend_Controller_Action
{
    public function uploadAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        try {
            // 1. Require authenticated user.
            $userId = $this->getAuthenticatedUserId();

            // 2. Validate your CSRF token here.

            if (!isset($_FILES['audio'])) {
                throw new RuntimeException('Audio file is missing.');
            }

            $file = $_FILES['audio'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Audio upload failed.');
            }

            $maxSize = 20 * 1024 * 1024; // Example: 20 MB.

            if ($file['size'] <= 0 || $file['size'] > $maxSize) {
                throw new RuntimeException('Invalid audio file size.');
            }

            $mimeType = $this->detectMimeType($file['tmp_name']);
            $extension = $this->extensionForMimeType($mimeType);

            $allowed = [
                'audio/webm',
                'video/webm',
                'audio/ogg',
                'application/ogg',
                'audio/mp4',
                'video/mp4'
            ];

            if (!in_array($mimeType, $allowed, true)) {
                throw new RuntimeException(
                    'Unsupported audio format: ' . $mimeType
                );
            }

            $storedFilename = bin2hex(random_bytes(16))
                . '.' . $extension;

            $relativeDirectory = date('Y/m');

            $baseDirectory = APPLICATION_PATH
                . '/../data/voice/';

            $directory = $baseDirectory
                . $relativeDirectory
                . '/';

            if (!is_dir($directory)) {
                if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
                    throw new RuntimeException(
                        'Unable to create voice storage directory.'
                    );
                }
            }

            $destination = $directory . $storedFilename;

            if (!move_uploaded_file(
                $file['tmp_name'],
                $destination
            )) {
                throw new RuntimeException(
                    'Unable to store audio file.'
                );
            }

            // Save metadata in MariaDB here.
            // Store a relative path, not an arbitrary user-provided path.
            // Example:
            // 2026/10/abc123.webm

            $relativePath = $relativeDirectory
                . '/'
                . $storedFilename;

            $recordingId = $this->saveRecordingMetadata([
                'user_id'           => $userId,
                'original_filename' => $file['name'],
                'stored_filename'   => $relativePath,
                'mime_type'         => $mimeType,
                'file_size'         => filesize($destination)
            ]);

            $this->jsonResponse([
                'success' => true,
                'id'      => $recordingId
            ]);
        } catch (Throwable $e) {
            $this->getResponse()->setHttpResponseCode(400);

            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function detectMimeType(string $filePath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if ($mimeType === false) {
            throw new RuntimeException(
                'Unable to detect audio MIME type.'
            );
        }

        return $mimeType;
    }

    private function extensionForMimeType(string $mimeType): string
    {
        switch ($mimeType) {
            case 'audio/webm':
            case 'video/webm':
                return 'webm';

            case 'audio/ogg':
            case 'application/ogg':
                return 'ogg';

            case 'audio/mp4':
            case 'video/mp4':
                return 'mp4';

            default:
                throw new RuntimeException(
                    'Unsupported MIME type.'
                );
        }
    }

    private function jsonResponse(array $data): void
    {
        $this->getResponse()
            ->setHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            )
            ->setBody(
                json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE
                )
            );
    }

    // Replace these two methods with your application's real services.
    private function getAuthenticatedUserId(): int
    {
        return 1;
    }

    private function saveRecordingMetadata(array $data): int
    {
        // Insert with your ZF1 model/DbTable/repository.
        return 123;
    }
}
```

### Important MIME note

Container sniffing can sometimes produce MIME values that differ from the JavaScript `MediaRecorder.mimeType`. Test the exact formats generated by the browsers and operating systems you officially support. For stronger validation, inspect the media container with an appropriate media tool instead of relying only on filename or request headers.

---

## 9. Basic Playback

The browser already has a native audio player:

```html
<audio controls src="/voice/play/id/123"></audio>
```

The endpoint should:

1. authenticate the user,
2. load recording metadata,
3. authorize access to the owning business record,
4. resolve the protected file path,
5. send the correct media content type,
6. stream the file.

Do not authorize playback simply because the user knows the recording ID.

---

## 10. Simple ZF1 Playback Action

This version is suitable as a first implementation for short recordings.

```php
public function playAction()
{
    $this->_helper->viewRenderer->setNoRender(true);

    $id = (int) $this->_getParam('id');

    if ($id <= 0) {
        throw new Zend_Controller_Action_Exception(
            'Invalid recording ID.',
            400
        );
    }

    $userId = $this->getAuthenticatedUserId();

    $recording = $this->findRecordingById($id);

    if (!$recording) {
        throw new Zend_Controller_Action_Exception(
            'Recording not found.',
            404
        );
    }

    if (!$this->canPlayRecording($userId, $recording)) {
        throw new Zend_Controller_Action_Exception(
            'Access denied.',
            403
        );
    }

    $baseDirectory = realpath(
        APPLICATION_PATH . '/../data/voice'
    );

    $filePath = realpath(
        $baseDirectory . '/' . $recording['stored_filename']
    );

    if (
        $filePath === false ||
        strpos($filePath, $baseDirectory . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($filePath)
    ) {
        throw new Zend_Controller_Action_Exception(
            'Recording file not found.',
            404
        );
    }

    $this->getResponse()
        ->setHeader(
            'Content-Type',
            $recording['mime_type']
        )
        ->setHeader(
            'Content-Length',
            (string) filesize($filePath)
        )
        ->setHeader(
            'Content-Disposition',
            'inline'
        )
        ->setHeader(
            'X-Content-Type-Options',
            'nosniff'
        );

    readfile($filePath);
}
```

For long recordings or many concurrent listeners, do not assume PHP `readfile()` is the final production architecture. Consider authenticated authorization in PHP followed by efficient web-server delivery such as an internal redirect mechanism supported by your server configuration.

---

## 11. HTTP Range Requests

Native `<audio>` controls may request only a portion of a media file, especially when the user seeks to another time.

Example request:

```http
Range: bytes=100000-199999
```

The server responds with:

```http
HTTP/1.1 206 Partial Content
Accept-Ranges: bytes
Content-Range: bytes 100000-199999/850000
Content-Length: 100000
```

Range support improves seeking and avoids retransmitting the entire recording.

### Simplified PHP 7.4 Range Streaming Helper

```php
private function streamFileWithRange(
    string $filePath,
    string $mimeType
): void {
    $size = filesize($filePath);

    if ($size === false || $size <= 0) {
        throw new RuntimeException('Invalid audio file.');
    }

    $start = 0;
    $end = $size - 1;

    $response = $this->getResponse();

    $response->setHeader('Content-Type', $mimeType);
    $response->setHeader('Accept-Ranges', 'bytes');
    $response->setHeader('X-Content-Type-Options', 'nosniff');

    $range = isset($_SERVER['HTTP_RANGE'])
        ? trim($_SERVER['HTTP_RANGE'])
        : '';

    if ($range !== '') {
        if (!preg_match(
            '/^bytes=(\d*)-(\d*)$/',
            $range,
            $matches
        )) {
            $response->setHttpResponseCode(416);
            $response->setHeader(
                'Content-Range',
                'bytes */' . $size
            );
            return;
        }

        $rangeStart = $matches[1];
        $rangeEnd = $matches[2];

        if ($rangeStart === '' && $rangeEnd === '') {
            $response->setHttpResponseCode(416);
            $response->setHeader(
                'Content-Range',
                'bytes */' . $size
            );
            return;
        }

        if ($rangeStart === '') {
            // Suffix range: bytes=-500 means the last 500 bytes.
            $suffixLength = (int) $rangeEnd;

            if ($suffixLength <= 0) {
                $response->setHttpResponseCode(416);
                $response->setHeader(
                    'Content-Range',
                    'bytes */' . $size
                );
                return;
            }

            $suffixLength = min($suffixLength, $size);
            $start = $size - $suffixLength;
            $end = $size - 1;
        } else {
            $start = (int) $rangeStart;

            if ($rangeEnd !== '') {
                $end = (int) $rangeEnd;
            }

            if ($start >= $size || $start > $end) {
                $response->setHttpResponseCode(416);
                $response->setHeader(
                    'Content-Range',
                    'bytes */' . $size
                );
                return;
            }

            $end = min($end, $size - 1);
        }

        $response->setHttpResponseCode(206);
        $response->setHeader(
            'Content-Range',
            'bytes ' . $start . '-' . $end . '/' . $size
        );
    }

    $length = $end - $start + 1;

    $response->setHeader(
        'Content-Length',
        (string) $length
    );

    $handle = fopen($filePath, 'rb');

    if ($handle === false) {
        throw new RuntimeException('Unable to open audio file.');
    }

    try {
        if ($start > 0) {
            fseek($handle, $start);
        }

        $remaining = $length;
        $bufferSize = 64 * 1024;

        while ($remaining > 0 && !feof($handle)) {
            $readLength = min($bufferSize, $remaining);
            $buffer = fread($handle, $readLength);

            if ($buffer === false || $buffer === '') {
                break;
            }

            echo $buffer;

            $remaining -= strlen($buffer);
        }
    } finally {
        fclose($handle);
    }
}
```

Then, after authentication and authorization:

```php
$this->streamFileWithRange(
    $filePath,
    $recording['mime_type']
);
```

### Production note

HTTP Range syntax has additional edge cases, including multiple ranges. If you need full HTTP media-server behavior, delegate byte serving to Apache/Nginx after ZF1 performs authorization, rather than continuously expanding custom PHP streaming code.

---

## 12. Playing a Recording with JavaScript

```html
<audio id="voice-player" controls></audio>

<button type="button" onclick="playRecording(123)">
    Play
</button>
```

```javascript
function playRecording(id)
{
    var player = document.getElementById('voice-player');

    player.src = '/voice/play/id/'
        + encodeURIComponent(id);

    player.play().catch(function (error) {
        console.error('Unable to play recording:', error);
    });
}
```

Browsers can restrict programmatic autoplay. Calling `play()` from an explicit user click is normally the correct design.

---

## 13. DHTMLX 3.5 Grid Integration

For a grid with many recordings, do not create hundreds of audio players unnecessarily.

Use one shared player:

```html
<audio id="voice-player" controls></audio>
```

Conceptually:

```text
ID     User       Date                  Voice
-----------------------------------------------------
101    John       2026-10-04 10:30      [Play]
102    David      2026-10-04 10:45      [Play]
103    Alice      2026-10-04 11:20      [Play]

Shared player:
[Play --------------------------- 00:00 / 00:32]
```

Each row can call:

```javascript
playRecording(recordingId);
```

This is simpler and uses fewer browser resources.

---

## 14. CSRF Protection

The recording upload changes server state, so protect it with your existing ZF1 CSRF mechanism.

```javascript
formData.append(
    'csrf_token',
    csrfToken
);
```

The ZF1 upload action must validate the token before storing the recording.

A playback `GET` normally does not require CSRF protection because it should not modify state, but it still requires authentication and authorization for private recordings.

---

## 15. Authentication and Authorization

Do not implement playback like this:

```text
/voice/play/id/123
        |
        v
Anyone knowing 123 can listen
```

Instead:

```text
GET /voice/play/id/123
        |
        v
Is user authenticated?
        |
        v
Load recording
        |
        v
Can this user access the parent record?
        |
       YES
        |
        v
Stream audio
```

Authorization should follow the same company/module/record permissions as the object to which the voice note belongs.

---

## 16. File Names

Never use a user-controlled filename as the actual server filename.

Bad:

```text
../../../something.php
```

Better:

```php
$storedFilename = bin2hex(random_bytes(16)) . '.webm';
```

Example:

```text
75eab5d823e94d45f11a71923846aa10.webm
```

Keep the original filename only as optional metadata if you need it for display/audit purposes.

---

## 17. Maximum Recording Duration

A maximum duration should be enforced in the browser for usability and on the server through size/policy limits.

Example browser timer:

```javascript
var maxDurationMs = 5 * 60 * 1000;
var recordingTimer = null;

function startMaximumDurationTimer()
{
    recordingTimer = window.setTimeout(function () {
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
    }, maxDurationMs);
}

function clearMaximumDurationTimer()
{
    if (recordingTimer !== null) {
        window.clearTimeout(recordingTimer);
        recordingTimer = null;
    }
}
```

Do not rely only on the JavaScript timer. A malicious or modified client can bypass it.

---

## 18. Upload Size Configuration

PHP configuration may limit uploads before ZF1 sees the file.

Review values such as:

```ini
upload_max_filesize = 25M
post_max_size = 30M
max_file_uploads = 20
```

`post_max_size` must be large enough for the complete HTTP request.

Apache/Nginx may also have request-size limits.

Choose limits according to your actual maximum voice duration and bitrate rather than blindly increasing them.

---

## 19. Optional Server-Side Format Normalization

If you want all stored recordings in one format, accept the browser's supported format first and convert after upload.

```text
Chrome  ---- WebM ----+
Firefox ---- WebM ----+---> ZF1 upload
Safari  ---- MP4 -----+
                           |
                           v
                        FFmpeg
                           |
                           v
                  Standardized output
```

Do not force every browser to produce WebM if it cannot do so reliably.

A background conversion worker is preferable to making the user wait inside the upload request for long conversions.

Possible metadata fields:

```text
original_mime_type
original_path
normalized_mime_type
normalized_path
conversion_status
conversion_error
```

If conversion is not required, keeping the validated original browser format is simpler.

---

## 20. Deleting a Recording

Deletion should be an authenticated state-changing request, preferably POST/DELETE according to your application's conventions, with CSRF protection.

Recommended sequence:

```text
Delete request
     |
     v
Authenticate
     |
     v
Authorize
     |
     v
Mark/delete DB record
     |
     v
Delete physical file
     |
     v
Audit/log result
```

For important business records, consider soft deletion and retention requirements instead of immediate physical deletion.

---

## 21. Security Checklist

Before production deployment, verify:

- HTTPS is enabled.
- Microphone permission is user-controlled.
- Upload endpoint requires authentication.
- Upload endpoint has CSRF protection.
- Playback endpoint requires authentication for private audio.
- Playback authorization is checked against the owning record.
- Client filenames are not trusted.
- MIME type is inspected server-side.
- File size is limited.
- Recording duration is limited according to business policy.
- Files are stored outside the public document root.
- Random server-generated filenames are used.
- Paths are canonicalized before reading files.
- `Content-Type` comes from validated metadata.
- `X-Content-Type-Options: nosniff` is returned.
- Error messages do not expose server filesystem paths.
- Upload/play/delete operations are logged as appropriate.
- Retention/deletion rules are documented.
- Browser formats are tested on supported browser/OS combinations.

---

## 22. Recommended Class Structure for ZF1

As the feature grows, avoid putting all logic in `VoiceController`.

```text
VoiceController
      |
      +---- S3_VoiceService
      |        |
      |        +---- validation
      |        +---- authorization coordination
      |        +---- metadata creation
      |        +---- deletion
      |
      +---- S3_VoiceStorage
      |        |
      |        +---- save file
      |        +---- resolve file
      |        +---- delete file
      |
      +---- Voice_Model_Recording
               |
               +---- MariaDB metadata
```

For example:

```php
class S3_VoiceStorage
{
    private $baseDirectory;

    public function __construct(string $baseDirectory)
    {
        $this->baseDirectory = rtrim(
            $baseDirectory,
            DIRECTORY_SEPARATOR
        );
    }

    public function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16))
            . '.'
            . $extension;
    }
}
```

This makes the controller responsible mainly for HTTP input/output rather than filesystem business logic.

---

## 23. Suggested API Endpoints

A simple ZF1 design could use:

```text
POST /voice/upload
GET  /voice/play/id/123
POST /voice/delete/id/123
GET  /voice/info/id/123
```

If voice recordings belong to another object, stronger domain-oriented endpoints may be preferable, for example:

```text
POST /customer/123/voice
GET  /voice/456/play
```

Use the conventions already established in your ZF1 application.

---

## 24. Recommended Implementation Stages

### Phase 1 - Recording and Preview

Implement:

```text
getUserMedia()
MediaRecorder
Record
Stop
Preview
Re-record
```

No server storage is required initially.

### Phase 2 - Upload

Add:

```text
FormData
AJAX
CSRF
ZF1 uploadAction()
file validation
protected storage
MariaDB metadata
```

### Phase 3 - Playback

Add:

```text
<audio>
ZF1 playAction()
authentication
authorization
correct Content-Type
```

### Phase 4 - Production Playback

Add:

```text
HTTP Range support
or efficient protected web-server delivery
```

### Phase 5 - Application Integration

Add:

```text
DHTMLX form/grid integration
recording ownership
permissions
logging/audit
delete/re-record
maximum duration
upload progress
```

### Phase 6 - Optional Processing

Only if required:

```text
FFmpeg normalization
waveform generation
speech-to-text
background processing
retention/archive rules
```

---

## 25. Final Recommended Architecture

```text
                         Browser
                            |
             +--------------+--------------+
             |                             |
        Microphone                       Playback
             |                             ^
             v                             |
     getUserMedia()                        |
             |                             |
             v                             |
       MediaRecorder                       |
             |                             |
             v                             |
         Audio Blob                        |
             |                             |
             | POST /voice/upload          | GET /voice/play/id/123
             v                             |
                       ZF1
                        |
              +---------+---------+
              |                   |
              v                   v
        VoiceService          Authorization
              |
       +------+------+
       |             |
       v             v
  VoiceStorage     MariaDB
       |          metadata
       v
protected filesystem
/data/voice/...
```

The key design principle is:

```text
Browser records.
ZF1 validates and controls access.
Filesystem stores the media.
MariaDB stores metadata.
Browser <audio> plays the media.
```

This keeps the voice feature compatible with an existing ZF1/PHP 7.4/DHTMLX application while maintaining a clear security boundary around private recordings.
