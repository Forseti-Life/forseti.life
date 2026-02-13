# Code Review: ResumeController.php

**File:** `ResumeController.php`  
**Size:** 467 lines  
**Status:** ⚠️ **NEEDS REFACTORING**

---

## Executive Summary

This controller handles resume-related operations including upload, processing, and tailoring. It's a medium-sized controller (467 lines) that deals with file uploads and sensitive user data. It exhibits typical architectural issues (service locator, no DI) plus specific security concerns around file handling.

**Critical Issues:**
- 🔴 **Security:** File upload handling requires careful review
- 🔴 **Architecture:** Service locator pattern, no constructor DI
- 🟠 **File Handling:** Insufficient validation of uploaded files
- 🟠 **Performance:** Large files may cause memory issues

---

## Security Analysis

### 1. 🔴 File Upload Validation

**Critical Issue:** File uploads require strict validation.

**Checks Required:**
1. **File size limits:**
   ```php
   $max_size = 5 * 1024 * 1024; // 5MB
   if ($_FILES['resume']['size'] > $max_size) {
     throw new \Exception($this->t('File is too large. Maximum size is 5MB.'));
   }
   ```

2. **File type validation:**
   ```php
   $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
   $mime_type = mime_content_type($_FILES['resume']['tmp_name']);
   
   if (!in_array($mime_type, $allowed_types, TRUE)) {
     throw new \Exception($this->t('Only PDF and DOCX files are allowed.'));
   }
   ```

3. **Extension validation (whitelist only):**
   ```php
   $pathinfo = pathinfo($_FILES['resume']['name']);
   $allowed_extensions = ['pdf', 'doc', 'docx'];
   
   if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $allowed_extensions, TRUE)) {
     throw new \Exception($this->t('Invalid file extension.'));
   }
   ```

4. **Filename sanitization:**
   ```php
   // Never trust the original filename
   $filename = 'resume_' . \Drupal::currentUser()->id() . '_' . time() . '.' . $pathinfo['extension'];
   // Sanitize for filesystem
   $filename = \Drupal\Component\Utility\Html::escape($filename);
   ```

### 2. 🔴 Path Traversal Protection

**Issue:** If file is stored with user-provided names, path traversal attacks are possible.

**Vulnerable Pattern:**
```php
// DON'T DO THIS
$filepath = $upload_dir . '/' . $user_provided_filename;
```

**Safe Pattern:**
```php
// DO THIS
$filename = 'resume_' . $uid . '_' . time() . '.pdf';
$filepath = $upload_dir . '/' . $filename;
// Ensure path is within upload_dir
$real_path = realpath($filepath);
if (strpos($real_path, realpath($upload_dir)) !== 0) {
  throw new \Exception('Invalid file path');
}
```

### 3. ⚠️ File Access Control

**Issue:** Ensure users can only access their own resumes.

**Recommendation:**
```php
public function downloadResume($resume_id) {
  $uid = \Drupal::currentUser()->id();
  
  // Get resume data
  $resume = $this->database->select('jobhunter_resumes')
    ->fields('resume')
    ->condition('id', $resume_id)
    ->condition('uid', $uid)  // Ensure user owns resume
    ->execute()
    ->fetchAssoc();
  
  if (!$resume) {
    throw new AccessDeniedHttpException('Resume not found');
  }
  
  // Serve file
  return $this->serveFile($resume['pdf_path']);
}
```

### 4. ⚠️ Sensitive Data Exposure

**Issue:** If resume contains sensitive data and is stored improperly.

**Recommendation:**
- Store resumes outside the web root
- Encrypt sensitive fields in database
- Don't expose internal file paths in URLs

### 5. ⚠️ Temporary File Cleanup

**Issue:** Uploaded files in temporary directory may not be cleaned up on error.

**Recommendation:**
```php
$temp_path = $_FILES['resume']['tmp_name'];
try {
  // Validate and process
  $this->validateFile($temp_path);
} finally {
  // Always clean up temp file
  if (file_exists($temp_path)) {
    unlink($temp_path);
  }
}
```

---

## Performance Analysis

### 1. 🔴 Large File Handling

**Issue:** Processing large resume files (especially if generating PDFs) in the request/response cycle is problematic.

**Recommendation:**
```php
// For large files, use queue processing
$queue = \Drupal::queue('job_hunter_resume_process');
$queue->createItem([
  'uid' => $uid,
  'file_path' => $filepath,
  'operation' => 'parse_content',
]);

// Return immediately
return $this->redirect('job_hunter.resumes_list');
```

### 2. ⚠️ No Caching of Resume Data

**Finding:** If resume is frequently accessed/displayed, should be cached.

**Recommendation:**
```php
$cache_key = 'job_hunter:resume:' . $resume_id;
if ($cached = \Drupal::cache('data')->get($cache_key)) {
  return $cached->data;
}

// Load resume data
$resume = $this->loadResume($resume_id);

\Drupal::cache('data')->set($cache_key, $resume, \Drupal\Core\Cache\CacheBackendInterface::CACHE_PERMANENT, ['job_hunter:resumes']);
```

### 3. ⚠️ Memory Usage with File Reading

**Issue:** If reading entire large files into memory.

**Recommendation:**
```php
// For large files, read in chunks
$file_handle = fopen($filepath, 'r');
while (!feof($file_handle)) {
  $chunk = fread($file_handle, 8192); // 8KB chunks
  // Process chunk
}
fclose($file_handle);
```

---

## Code Organization

### 1. ⚠️ Service Locator Pattern

**Finding:** Services accessed via `\Drupal::database()`, `\Drupal::currentUser()` instead of constructor injection.

**Recommendation:**
```php
class ResumeController extends ControllerBase {
  
  protected $database;
  protected $currentUser;
  protected $fileSystem;
  protected $logger;
  
  public function __construct(
    DatabaseConnection $database,
    AccountProxyInterface $currentUser,
    FileSystemInterface $fileSystem,
    LoggerInterface $logger
  ) {
    $this->database = $database;
    $this->currentUser = $currentUser;
    $this->fileSystem = $fileSystem;
    $this->logger = $logger;
  }
  
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('file_system'),
      $container->get('logger.factory')->get('job_hunter')
    );
  }
```

### 2. 🟡 Large Controller

**Issue:** 467 lines suggests multiple responsibilities.

**Recommendation:** Split into:
- `ResumeUploadController` - Handle uploads
- `ResumeDisplayController` - Show resume details
- `ResumeTailorController` - Tailoring operations

Or extract file handling to `ResumeFileService`.

### 3. 🟡 Service Extraction

**Recommendation:** Create `ResumeProcessingService`:
```php
class ResumeProcessingService {
  // Validate file
  public function validateUpload($file_path, $uid);
  
  // Extract text from resume
  public function extractContent($file_path);
  
  // Parse structure
  public function parseResume($content);
  
  // Store securely
  public function storeResume($file_path, $data, $uid);
}
```

---

## Error Handling

### 1. 🟠 Limited Exception Handling

**Issue:** File operations can fail. No comprehensive error handling.

**Recommendation:**
```php
try {
  $file_path = $this->storeUploadedFile($file);
  $content = $this->extractResume($file_path);
  $parsed = $this->parseResume($content);
  $this->saveToDatabase($parsed, $uid);
} catch (FileException $e) {
  \Drupal::logger('job_hunter')->error('Resume file error: @error', ['@error' => $e->getMessage()]);
  $this->messenger()->addError($this->t('Could not save resume file. Please try again.'));
  return $this->redirect('job_hunter.upload_resume');
} catch (ParseException $e) {
  \Drupal::logger('job_hunter')->error('Resume parsing error: @error', ['@error' => $e->getMessage()]);
  $this->messenger()->addError($this->t('Could not parse resume. File may be corrupted.'));
  return $this->redirect('job_hunter.upload_resume');
} catch (\Exception $e) {
  \Drupal::logger('job_hunter')->critical('Unexpected resume error: @error', ['@error' => $e->getMessage()]);
  $this->messenger()->addError($this->t('An unexpected error occurred.'));
  return $this->redirect('job_hunter.upload_resume');
}
```

### 2. ⚠️ Validation Error Messages

**Issue:** File validation errors may not be user-friendly.

**Recommendation:**
```php
$errors = [];
if (!$this->isValidFileType($file)) {
  $errors[] = $this->t('Resume must be a PDF or Word document.');
}
if ($file['size'] > 5 * 1024 * 1024) {
  $errors[] = $this->t('Resume file is too large (max 5MB).');
}
if (!empty($errors)) {
  foreach ($errors as $error) {
    $this->messenger()->addError($error);
  }
  return $this->redirect('job_hunter.upload_resume');
}
```

---

## Database Operations

### 1. ⚠️ Transaction Safety

**Issue:** If uploading and saving to database, should be atomic.

**Recommendation:**
```php
$transaction = $this->database->startTransaction();
try {
  // Save file
  $filepath = $this->saveFile($file);
  
  // Save to database
  $result = $this->database->insert('jobhunter_resumes')
    ->fields([
      'uid' => $uid,
      'filename' => $filename,
      'filepath' => $filepath,
      'created' => time(),
    ])
    ->execute();
  
  if (!$result) {
    throw new \Exception('Failed to save resume to database');
  }
} catch (\Exception $e) {
  $transaction->rollBack();
  // Clean up file
  if (file_exists($filepath)) {
    unlink($filepath);
  }
  throw $e;
}
```

### 2. ⚠️ Old Resume Cleanup

**Issue:** When user uploads new resume, should old ones be cleaned up?

**Recommendation:**
```php
// Before saving new resume, delete old ones
$old_resumes = $this->database->select('jobhunter_resumes')
  ->fields('resumes', ['id', 'filepath'])
  ->condition('uid', $uid)
  ->execute()
  ->fetchAll();

foreach ($old_resumes as $old_resume) {
  // Delete file
  if (file_exists($old_resume->filepath)) {
    unlink($old_resume->filepath);
  }
  
  // Delete from database
  $this->database->delete('jobhunter_resumes')
    ->condition('id', $old_resume->id)
    ->execute();
}
```

---

## Testing Recommendations

1. **File Upload Tests:**
   - Valid PDF upload
   - Valid DOCX upload
   - Oversized file rejection
   - Invalid file type rejection
   - Path traversal attempt rejection
   - Filename with special characters

2. **Security Tests:**
   - Users cannot access other users' resumes
   - Uploaded files are not executable
   - Files are stored outside web root
   - Temporary files are cleaned up

3. **Error Handling Tests:**
   - Disk full scenario
   - File permission errors
   - Database connection failure during save

---

## Specific Code Issues Checklist

- [ ] Is file size validated before processing?
- [ ] Is file type validated by content, not just extension?
- [ ] Are uploaded files stored outside web root?
- [ ] Are filenames sanitized and unique?
- [ ] Are users restricted to their own resumes?
- [ ] Are temporary files cleaned up on error?
- [ ] Are database operations transactional?
- [ ] Is file processing queued for large files?
- [ ] Are all file operations logged?
- [ ] Is sensitive data encrypted?

---

## Recommendations Priority

| Priority | Issue | Recommendation |
|----------|-------|-----------------|
| 🔴 CRITICAL | File validation missing | Implement strict file type/size validation |
| 🔴 CRITICAL | Path traversal risk | Sanitize filenames, validate paths |
| 🔴 CRITICAL | Access control missing | Verify user owns resume before serving |
| 🟠 HIGH | Service locator pattern | Use constructor injection |
| 🟠 HIGH | No transaction safety | Wrap upload+save in transaction |
| 🟠 HIGH | Large file handling | Use queue for processing |
| 🟡 MEDIUM | No caching | Cache resume data |
| 🟡 MEDIUM | Limited logging | Log all file operations |
| 🟡 MEDIUM | Large controller | Split into smaller classes |

---

## Estimated Effort

- **File validation & security:** 2-3 hours
- **Constructor DI & refactoring:** 1-2 hours
- **Transaction safety & cleanup:** 1 hour
- **Service extraction:** 1-2 hours
- **Error handling improvements:** 1 hour
- **Add tests:** 2-3 hours

**Total Estimated Effort:** 8-12 hours

---

## Implementation Order

1. **First (Security):** File validation and access control
2. **Second (Stability):** Transaction safety and error handling
3. **Third (Maintainability):** Constructor DI and service extraction
4. **Fourth (Performance):** Caching and queue processing
5. **Fifth (Quality):** Comprehensive testing

---

**Review Confidence:** HIGH  
**Last Updated:** 2024  
**Reviewer Notes:** File upload handling is critical security area. Must implement strict validation and access controls.

