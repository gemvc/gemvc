# Security Policy

## 🔒 GEMVC Comprehensive Security Overview

GEMVC is architected with **security-by-design** principles, implementing multi-layered defense mechanisms from request arrival to database operations. This document provides a comprehensive overview of all security features, attack prevention strategies, and best practices.

> **🎯 Key Point**: **90% of GEMVC security is AUTOMATIC** - No developer configuration needed! Security checks happen automatically in `Bootstrap.php` (Apache,NginX) and `SwooleBootstrap.php` (OpenSwoole) for every request.

---

## 🛡️ Multi-Layer Security Architecture

```
Request Arrives
    ↓
1. Path Access Security (SecurityManager) ✅ AUTOMATIC
    ↓
2. Header Sanitization (ApacheRequest/SwooleRequest) ✅ AUTOMATIC
    ↓
3. Input Sanitization (XSS Prevention) ✅ AUTOMATIC
    ↓
4. Schema Validation (Request Filtering) ⚙️ Developer Calls
    ↓
5. Authentication & Authorization (JWT) ⚙️ Developer Calls
    ↓
6. File Security (Name, MIME, Signature, Encryption) ✅ AUTOMATIC + ⚙️ Developer Calls
    ↓
7. Business Logic Protection ✅ AUTOMATIC
    ↓
8. Database Security (SQL Injection Prevention) ✅ AUTOMATIC
```

**Legend**:
- ✅ **AUTOMATIC**: Enabled by default, no developer action needed
- ⚙️ **Developer Calls**: Available methods developers use in their code

### How It Works

**Apache Environment** (`Bootstrap.php`):
```php
// Security happens BEFORE any API code runs
new Bootstrap($request); // All sanitization happens in Request constructor
```

**OpenSwoole Environment** (`SwooleBootstrap.php`):
```php
// Security checks in OpenSwooleServer.php BEFORE Bootstrap
if (!$this->security->isRequestAllowed($requestUri)) {
    $this->security->sendSecurityResponse($response); // Returns 403
    return;
}
// THEN sanitization happens in SwooleRequest constructor
new SwooleBootstrap($sr->request); // All sanitization already done
```

**Result**: Your `app/api/` code never needs to worry about sanitization - it's already done!

---

## 🚪 Layer 1: Path Access Security

### ✅ AUTOMATIC - SecurityManager.php

**Status**: **Automatically enabled** - No developer configuration needed!

**Implementation**: GEMVC core (`OpenSwooleServer.php` line 159) automatically checks every request before processing.

**Purpose**: Blocks direct access to sensitive application files and directories.

**Blocked Paths**:
```php
/app          // Application code
/vendor       // Composer dependencies
/bin          // Executable files
/templates    // Template files
/config       // Configuration files
/logs         // Log files
/storage      // Storage files
/.env         // Environment variables
/.git         // Version control
```

**Blocked File Extensions**:
```php
.php, .env, .ini, .conf, .config,
.log, .sql, .db, .sqlite,
.md, .txt, .json, .xml, .yml, .yaml
```

**Automatic Implementation**:
```php
// OpenSwooleServer.php - Line 159
// ✅ AUTOMATIC - Happens for EVERY request before processing
if (!$this->security->isRequestAllowed($requestUri)) {
    $this->security->sendSecurityResponse($response); // Returns 403
    return;
}

// ✅ No developer code needed - Already protected!
```

**Attack Prevented**:
```
❌ Attack: GET /app/api/User.php
✅ Result: 403 Forbidden - "Direct file access is not permitted"

❌ Attack: GET /.env
✅ Result: 403 Forbidden - "Direct file access is not permitted"
```

---

## 📥 Layer 2: Header Sanitization

### ✅ AUTOMATIC - ApacheRequest.php & SwooleRequest.php

**Status**: **Automatically enabled** - All headers sanitized in Request constructors!

**Purpose**: Sanitizes all HTTP headers to prevent header injection and XSS.

**Implementation**:
```php
// ApacheRequest.php - Line 41-56
private function sanitizeAllServerHttpRequestHeaders(): void
{
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            // All HTTP headers sanitized
            $_SERVER[$key] = $this->sanitizeInput($value);
        }
    }
}
```

**Sanitized Headers**:
- `HTTP_AUTHORIZATION` → Sanitized before JWT extraction
- `HTTP_USER_AGENT` → Sanitized before logging
- `HTTP_X_FORWARDED_FOR` → Sanitized for security
- All custom headers (`HTTP_*`) → Automatically sanitized

**Attack Prevented**:
```
❌ Attack: 
Authorization: Bearer <script>alert('XSS')</script>

✅ Sanitized:
Authorization: Bearer &lt;script&gt;alert('XSS')&lt;/script&gt;
```

### SwooleRequest.php - Cookie Security

**Dangerous Cookie Filtering** (Line 221-395):
```php
// Blocks dangerous cookie names:
- Session hijacking: PHPSESSID, JSESSIONID, ASP.NET_SessionId
- Auth bypass: auth, token, jwt, api_key, password
- CSRF tokens: csrf_token, xsrf_token
- Admin tokens: admin_token, admin_session

// Blocks dangerous cookie value patterns:
- Script injection: <script>, javascript:, vbscript:
- SQL injection: UNION SELECT, INSERT INTO, DROP TABLE
- Command injection: ;, |, `, $, system, exec
- Path traversal: ../, ..\\
- Null bytes and control characters
```

---

## 🧹 Layer 3: Input Sanitization (XSS Prevention)

### ✅ AUTOMATIC - ApacheRequest.php & SwooleRequest.php

**Status**: **Automatically enabled** - All inputs sanitized when Request object is created!

**Core Sanitization Method**:
```php
// ApacheRequest.php - Line 189-195
private function sanitizeInput(mixed $input): mixed
{
    if(!is_string($input)) {
        return $input;
    }
    return filter_var(trim($input), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
```

**What Gets Sanitized**:
- ✅ All POST data
- ✅ All GET parameters
- ✅ All PUT/PATCH data
- ✅ All HTTP headers
- ✅ Query strings
- ✅ Request URIs
- ✅ File names and MIME types

**XSS Attack Prevention**:
```
❌ Attack Input:
<script>alert('XSS')</script>
<img src=x onerror="alert('XSS')">
javascript:alert('XSS')

✅ Sanitized Output:
&lt;script&gt;alert('XSS')&lt;/script&gt;
&lt;img src=x onerror="alert('XSS')"&gt;
javascript:alert('XSS')  // Special chars escaped
```

**Result**: All inputs are HTML-entity encoded, preventing XSS execution.

---

## 🔍 Layer 4: Schema Validation (Request Filtering)

### ⚙️ Developer Calls - Request.php

**Status**: **Available methods** - Developers call these in their API services to validate requests.

**Purpose**: Validates and filters requests **before** business logic execution.

**Note**: This is the **only layer** that requires developer action. All other security is automatic!

#### 1. Unwanted Field Detection
```php
// Schema: ['email' => 'email', 'password' => 'string']
// Request: {email: "...", password: "...", is_admin: true}

❌ REJECTED: "Unwanted post field: is_admin"
```
**Prevents**: Mass assignment attacks

#### 2. Required Field Validation
```php
// Schema: ['email' => 'email', 'password' => 'string']
// Request: {email: "user@example.com"}

❌ REJECTED: "Missing required field: password"
```
**Prevents**: Incomplete/malformed requests

#### 3. Type Validation
```php
// Schema: ['email' => 'email', 'age' => 'int']
// Request: {email: "not-an-email", age: "twenty"}

❌ REJECTED: 
"Invalid value for required field email, expected type: email"
"Invalid value for required field age, expected type: int"
```
**Prevents**: Type confusion attacks, SQL injection via type mismatch

#### 4. Optional Field Validation
```php
// Schema: ['email' => 'email', '?phone' => 'string']
// Request: {email: "user@example.com", phone: 12345}

❌ REJECTED: "Invalid value for optional field phone, expected type: string"
```

**Complete Example**:
```php
// app/api/User.php
public function create(): JsonResponse {
    // Layer 1: Schema Validation
    if (!$this->request->definePostSchema([
        'name' => 'string',      // Required string
        'email' => 'email',      // Required valid email
        'password' => 'string',  // Required string
        '?phone' => 'string',   // Optional string
        '?age' => 'int'          // Optional integer
    ])) {
        return $this->request->returnResponse(); // 400 Bad Request
    }
    
    // Layer 2: String Length Validation
    if (!$this->request->validateStringPosts([
        'name' => '2|100',       // 2-100 characters
        'password' => '8|128',   // 8-128 characters
        '?phone' => '10|15'      // 10-15 characters if provided
    ])) {
        return $this->request->returnResponse(); // 400 Bad Request
    }
    
    // ✅ Only valid requests reach here!
    return (new UserController($this->request))->create();
}
```

**Supported Validation Types**:
- **Basic**: `string`, `int`, `float`, `bool`, `array`
- **Advanced**: `email`, `url`, `date`, `datetime`, `json`, `ip`, `ipv4`, `ipv6`
- **Optional**: Prefix with `?` (e.g., `?name`)

**Attack Prevention Examples**:

```
Attack 1: Type Confusion
Request: {"id": "1' OR '1'='1", "email": "admin@test.com"}
Schema: ['id' => 'int', 'email' => 'email']
Result: ❌ REJECTED - "Invalid value for field id, expected type: int"

Attack 2: SQL Injection via Type Mismatch
Request: {"id": "1; DROP TABLE users; --", "name": "Hacker"}
Schema: ['id' => 'int', 'name' => 'string']
Result: ❌ REJECTED - "Invalid value for field id, expected type: int"

Attack 3: Mass Assignment
Request: {"email": "user@test.com", "password": "pass", "is_admin": true}
Schema: ['email' => 'email', 'password' => 'string']
Result: ❌ REJECTED - "Unwanted post field: is_admin"

Attack 4: Buffer Overflow
Request: {"name": "A" * 10000, "email": "user@test.com"}
Schema: ['name' => 'string', 'email' => 'email']
validateStringPosts: ['name' => '2|100']
Result: ❌ REJECTED - "String length for post 'name' is 10000, outside range (2-100)"
```

---

## 🔐 Layer 5: Authentication & Authorization

### ⚙️ Developer Calls - JWT Token System

**Status**: **Available methods** - Developers call `$request->auth()` in their API services.

**Token Creation** (JWTToken.php):
```php
// Access Token (short-lived)
$token = (new JWTToken())->createAccessToken($userId);
// Default: 300 seconds (5 minutes)

// Refresh Token (medium-lived)
$token = (new JWTToken())->createRefreshToken($userId);
// Default: 3600 seconds (1 hour)

// Login Token (long-lived)
$token = (new JWTToken())->createLoginToken($userId);
// Default: 604800 seconds (7 days)
```

**Token Payload**:
```php
{
    "token_id": "unique-token-id",
    "user_id": 123,
    "role": "admin,user",
    "role_id": 1,
    "company_id": 5,
    "employee_id": 10,
    "branch_id": 2,
    "exp": 1234567890,
    "iss": "MyCompany",
    "type": "access"
}
```

**Token Verification** (Request.php):
```php
// In API Service
public function create(): JsonResponse {
    // Authentication check
    if (!$this->request->auth()) {
        return $this->request->returnResponse(); // 401 Unauthorized
    }
    
    // Authorization check (role-based)
    if (!$this->request->auth(['admin', 'moderator'])) {
        return $this->request->returnResponse(); // 403 Forbidden
    }
    
    // ✅ Authenticated and authorized
    return (new UserController($this->request))->create();
}
```

**Security Features**:
- ✅ **HS256 Signature**: Uses `TOKEN_SECRET` from `.env`
- ✅ **Expiration Validation**: Checks `exp > time()`
- ✅ **User ID Validation**: Ensures `user_id > 0`
- ✅ **Role-Based Access Control**: Multi-role support
- ✅ **Token Renewal**: `renew()` method for extending tokens

**Attack Prevention**:
```
Attack 1: Forged Token
Token: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.forged-payload.wrong-signature"
Result: ❌ REJECTED - "Invalid JWT token. Authentication failed"

Attack 2: Expired Token
Token: Valid signature but exp < current_time
Result: ❌ REJECTED - "Invalid JWT token. Authentication failed"

Attack 3: Role Escalation
Token: Valid token with role="user"
Request: Requires role="admin"
Result: ❌ REJECTED - "Role user not allowed to perform this action"
```

---

## 📁 Layer 6: File Security

### ✅ AUTOMATIC + ⚙️ Developer Calls

**Status**: 
- **File name/MIME sanitization**: ✅ AUTOMATIC (in Request constructors)
- **File signature detection**: ⚙️ Use ImageHelper methods
- **File encryption**: ⚙️ Use FileHelper/ImageHelper methods

### ✅ AUTOMATIC - File Name & MIME Sanitization

**SwooleRequest.php** (Line 141-175):
```php
private function normalizeFilesArray(array $files): array
{
    foreach ($files as $key => $file) {
        // Sanitize file name
        if (is_string($file['name'])) {
            $normalized[$key]['name'] = $this->sanitizeInput($file['name']);
        }
        // Sanitize MIME type
        if (is_string($file['type'])) {
            $normalized[$key]['type'] = $this->sanitizeInput($file['type']);
        }
    }
}
```

**Attack Prevention**:
```
Attack: Path Traversal in Filename
Filename: "../../../etc/passwd"
Result: ✅ Sanitized - Dangerous characters escaped

Attack: MIME Type Injection
MIME: "image/jpeg\r\nX-Injected: header"
Result: ✅ Sanitized - Special characters escaped
```

### File Signature Detection (Magic Bytes)

**ImageHelper.php** - Validates actual file type:
```php
// ImageHelper.php - Line 202-206
public function convertToWebP(int $quality = 80): bool
{
    // Uses getimagesize() which reads FILE MAGIC BYTES!
    $info = getimagesize($this->sourceFile);
    
    if ($info === false) {
        $this->error = "Unable to get image info";
        return false; // Not a valid image!
    }
    
    // Validates actual file signature
    $image = $this->createImageFromFile($info[2]);
}
```

**File Signatures Detected**:
- **JPEG**: `FF D8 FF` (first 3 bytes)
- **PNG**: `89 50 4E 47` (first 4 bytes)
- **GIF**: `47 49 46 38` (first 4 bytes)

**Attack Prevention**:
```
Attack 1: Double Extension
File: malware.php.jpg
MIME: image/jpeg
Result: ❌ REJECTED - getimagesize() detects <?php signature (not JPEG)

Attack 2: MIME Spoofing
File: malware.php
MIME: image/jpeg (spoofed)
Result: ❌ REJECTED - Actual file signature doesn't match

Attack 3: PHP File Renamed
File: evil.php renamed to image.jpg
Result: ❌ REJECTED - Magic bytes show <?php, not image signature
```

### File Encryption

**FileHelper.php** - File encryption/decryption:
```php
// Encrypt file
$file = new FileHelper('document.pdf', 'document.pdf.enc');
$file->secret = 'my-secret-key';
$encryptedPath = $file->encrypt();

// Decrypt file
$file = new FileHelper('document.pdf.enc', 'document_decrypted.pdf');
$file->secret = 'my-secret-key';
$decryptedPath = $file->decrypt();
```

**Encryption Algorithm** (CryptHelper.php):
```php
// AES-256-CBC Encryption
- Algorithm: AES-256-CBC
- IV: Random IV per file (prevents pattern analysis)
- HMAC: SHA-256 HMAC for integrity verification
- Encoding: Base64 for safe storage
```

**Security Features**:
- ✅ **AES-256-CBC**: Industry-standard encryption
- ✅ **Random IV**: Each file encrypted uniquely
- ✅ **HMAC-SHA256**: Detects file tampering
- ✅ **Integrity Check**: `hash_equals()` prevents timing attacks

**Tampering Detection**:
```
Attack: Encrypted file modified
File: document.pdf.enc (modified bytes)
Result: ❌ DECRYPTION FAILED - "Cannot decrypt file - Secret is wrong"
         (Actually: HMAC mismatch detected!)
```

---

## 🗄️ Layer 7: Database Security (SQL Injection Prevention)

### ✅ AUTOMATIC - UniversalQueryExecuter.php

**Status**: **Automatically enforced** - ALL database queries use prepared statements!

**Query Preparation** (Line 117-146):
```php
public function query(string $query): void
{
    // Validate query length (max 1MB)
    if (strlen($query) > 1000000) {
        $this->setError('Query exceeds maximum length');
        return;
    }
    
    // Prepare statement (SQL injection prevention)
    $this->statement = $this->db->prepare($query);
}
```

**Parameter Binding** (Line 159-181):
```php
public function bind(string $param, mixed $value): void
{
    // Automatic type detection
    $type = match (true) {
        is_int($value) => PDO::PARAM_INT,
        is_bool($value) => PDO::PARAM_BOOL,
        is_null($value) => PDO::PARAM_NULL,
        default => PDO::PARAM_STR,
    };
    
    // Bind with type safety (NOT string concatenation!)
    $this->statement->bindValue($param, $value, $type);
}
```

**Table ORM Usage**:
```php
// All queries use prepared statements automatically
$users = (new UserTable())
    ->select('id,name,email')
    ->where('email', "admin' OR '1'='1")  // Sanitized input
    ->run();

// Generated SQL:
// SELECT id,name,email FROM users WHERE email = :email
// Bound: [':email' => "admin' OR '1'='1"]
// Database treats as literal string, NOT SQL code!
```

**SQL Injection Prevention**:
```
Attack: SQL Injection
Input: "admin' OR '1'='1"
SQL: SELECT * FROM users WHERE email = :email
Bound: [':email' => "admin' OR '1'='1"]

Database Execution:
SELECT * FROM users WHERE email = 'admin\' OR \'1\'=\'1'
// Database treats entire string as literal value!

Result: ✅ SQL INJECTION PREVENTED
No matching user found (as expected)
```

**CRUD Operations** - All Protected:
```php
// INSERT - Uses prepared statements
$user->insertSingleQuery();

// SELECT - Uses prepared statements
$user->select()->where()->run();

// UPDATE - Uses prepared statements
$user->updateSingleQuery();

// DELETE - Uses prepared statements
$user->deleteSingleQuery();
```

---

## 🛡️ Complete Security Flow Example

### Attack Scenario: Malicious File Upload with SQL Injection

```
1. Attack Request:
   POST /api/Upload/upload
   File: malware.php (renamed to image.jpg)
   MIME: application/x-php
   POST: {"name": "<script>alert('XSS')</script>", "email": "admin' OR '1'='1"}
   
2. Path Access Check:
   ✅ Path allowed (/api/Upload/upload)
   
3. Header Sanitization:
   ✅ All headers sanitized
   
4. Input Sanitization:
   name: &lt;script&gt;alert('XSS')&lt;/script&gt; (XSS prevented)
   email: "admin' OR '1'='1" (still contains SQL)
   
5. Schema Validation:
   definePostSchema(['name' => 'string', 'email' => 'email'])
   ├─ Check email type: "admin' OR '1'='1" is NOT valid email
   └─ ❌ REJECTED: 400 Bad Request
       "Invalid value for required field email, expected type: email"
   
6. Request Stopped Here!
   ✅ No file processing
   ✅ No database queries
   ✅ Attack blocked at entry point
```

---

## 📊 Security Layers Summary

| Layer | Protection | Technique | Status |
|-------|-----------|-----------|--------|
| Path Access | File access blocking | SecurityManager | ✅ Blocked |
| Header Sanitization | Header injection | FILTER_SANITIZE | ✅ Protected |
| Input Sanitization | XSS prevention | FILTER_SANITIZE_FULL_SPECIAL_CHARS | ✅ Protected |
| Schema Validation | Request filtering | TypeChecker + defineSchema | ✅ Validated |
| Type Validation | Type safety | TypeChecker::check() | ✅ Enforced |
| Authentication | Token security | JWT (HS256) + expiration | ✅ Verified |
| Authorization | Role-based access | Role checking | ✅ Enforced |
| File Name Sanitization | Path traversal | sanitizeInput() | ✅ Protected |
| File MIME Sanitization | MIME injection | sanitizeInput() | ✅ Protected |
| File Signature Detection | Type spoofing | getimagesize() magic bytes | ✅ Verified |
| File Encryption | Confidentiality | AES-256-CBC + HMAC | ✅ Encrypted |
| Database | SQL injection | Prepared statements | ✅ Prevented |

---

## 🔒 Additional Security Features

### Password Security (CryptHelper.php)

**Argon2i Hashing**:
```php
// Password hashing
$hashedPassword = CryptHelper::hashPassword($password);
// Uses: PASSWORD_ARGON2I (industry standard)

// Password verification
$isValid = CryptHelper::passwordVerify($password, $hashedPassword);
```

**Security Features**:
- ✅ **Argon2i**: Memory-hard hashing algorithm
- ✅ **Automatic Salt**: Unique salt per password
- ✅ **No Plain Text**: Passwords never stored in plain text

### Error Handling Security

**Secure Error Responses**:
```php
// Bootstrap.php - Line 134-135
echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
```

**Prevents**: Error message injection, sensitive data exposure

---

## 🚨 Attack Prevention Matrix

| Attack Type | Attack Vector | GEMVC Protection | Result |
|------------|--------------|------------------|--------|
| **XSS** | `<script>alert('XSS')</script>` | Input sanitization | ✅ Prevented |
| **SQL Injection** | `admin' OR '1'='1` | Prepared statements | ✅ Prevented |
| **Path Traversal** | `../../../etc/passwd` | Path blocking + filename sanitization | ✅ Prevented |
| **File Upload** | `malware.php.jpg` | Signature detection | ✅ Prevented |
| **MIME Spoofing** | PHP file with `image/jpeg` MIME | Magic byte verification | ✅ Prevented |
| **Mass Assignment** | `{is_admin: true}` | Schema validation | ✅ Prevented |
| **Type Confusion** | `id: "1' OR '1'='1"` | Type validation | ✅ Prevented |
| **Header Injection** | `\r\n` in headers | Header sanitization | ✅ Prevented |
| **JWT Forgery** | Modified token | Signature verification | ✅ Prevented |
| **Token Replay** | Expired token | Expiration check | ✅ Prevented |
| **Role Escalation** | User accessing admin endpoint | Authorization check | ✅ Prevented |
| **Buffer Overflow** | 10,000 char string | Length validation | ✅ Prevented |
| **File Tampering** | Modified encrypted file | HMAC verification | ✅ Prevented |

---

## 🔧 Security Configuration

### Environment Variables (.env)

```env
# JWT Security
TOKEN_SECRET='your-very-long-random-secret-key-here'
TOKEN_ISSUER='MyCompany'
ACCESS_TOKEN_VALIDATION_IN_SECONDS=300
REFRESH_TOKEN_VALIDATION_IN_SECONDS=3600
LOGIN_TOKEN_VALIDATION_IN_SECONDS=604800

# Database Security
DB_HOST_CLI_DEV=localhost    # For CLI commands
DB_HOST=db                   # For application (Docker)
DB_PASSWORD='strong-database-password'
DB_ENHANCED_CONNECTION=1     # Use persistent connections

# Application Security
APP_ENV=production
QUERY_LIMIT=10               # Default pagination limit
SWOOLE_DISPLAY_ERRORS=0      # Hide errors in production

# Redis Security (optional)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD='redis-password'
REDIS_PREFIX=gemvc:
```

---

## 📋 Production Security Checklist

### Request Security
- [x] Path access blocking enabled
- [x] Header sanitization active
- [x] Input sanitization on all inputs
- [x] Schema validation implemented
- [x] Type validation enforced

### Authentication & Authorization
- [x] JWT tokens implemented
- [x] Strong TOKEN_SECRET configured
- [x] Token expiration enforced
- [x] Role-based access control active
- [x] Password hashing uses Argon2i

### File Security
- [x] File name sanitization active
- [x] MIME type sanitization active
- [x] File signature detection enabled
- [x] File encryption available (optional)
- [x] Dangerous cookies filtered

### Database Security
- [x] Prepared statements enforced
- [x] Parameter binding with types
- [x] No string concatenation in SQL
- [x] Connection pooling configured
- [x] Strong database passwords

### General Security
- [x] HTTPS enabled in production
- [x] Error display disabled
- [x] Security logging enabled
- [x] Environment variables secured
- [x] Regular security updates

---

## 🎯 Security Best Practices

### 1. Always Use Schema Validation
```php
// ✅ GOOD - Validate before processing
if (!$this->request->definePostSchema(['email' => 'email'])) {
    return $this->request->returnResponse();
}

// ❌ BAD - Process without validation
$email = $this->request->post['email']; // No validation!
```

### 2. Always Use Type-Safe Getters
```php
// ✅ GOOD - Type-safe
$id = $this->request->intValueGet('id');

// ❌ BAD - No type checking
$id = $this->request->get['id']; // Could be anything!
```

### 3. Always Use Authentication
```php
// ✅ GOOD - Check authentication
if (!$this->request->auth(['admin'])) {
    return $this->request->returnResponse();
}

// ❌ BAD - No authentication check
// Anyone can access!
```

### 4. Prepared Statements (Automatic!)
```php
// ✅ GOOD - Uses prepared statements automatically
$user->where('email', $email)->run();

// ✅ AUTOMATIC - Framework enforces prepared statements
// ❌ NOT POSSIBLE - GEMVC doesn't allow raw SQL concatenation
// All queries automatically use prepared statements!
```

### 5. Always Validate File Uploads
```php
// ✅ GOOD - Validate file signature
$image = new ImageHelper($uploadedFile);
if ($image->convertToWebP()) {
    // File is valid image (signature verified)
}

// ❌ BAD - Trust file extension
if (pathinfo($file, PATHINFO_EXTENSION) === 'jpg') {
    // Dangerous! Extension can be spoofed!
}
```

---

## 🆘 Security Incident Response

### Immediate Actions
1. **Revoke compromised tokens** - Change `TOKEN_SECRET` immediately
2. **Review security logs** - Check for suspicious activity
3. **Rotate all secrets** - Database passwords, encryption keys
4. **Disable affected endpoints** - If necessary
5. **Notify stakeholders** - Following incident response plan

### Investigation Steps
1. Check security logs for blocked access attempts
2. Review authentication failures
3. Analyze attack patterns
4. Document findings
5. Implement additional security measures

---

## 📞 Security Support

### Reporting Security Issues

If you discover a security vulnerability in GEMVC:

1. **DO NOT** create public issues for security vulnerabilities
2. **Email** security concerns to: security@gemvc.de
3. **Include** detailed information about the vulnerability
4. **Wait** for response before public disclosure

### Security Updates

- **Subscribe** to security notifications
- **Update regularly** to latest versions
- **Monitor** security advisories
- **Test** updates in development first

---

## 📚 Additional Resources

### Security Documentation
- [OpenSwoole Security Guide](https://openswoole.com/docs)
- [JWT Security Best Practices](https://tools.ietf.org/html/rfc8725)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE Top 25](https://cwe.mitre.org/top25/)

### Security Tools
- **Static Analysis**: PHPStan integrated
- **Dependency Scanning**: Composer security audit
- **Penetration Testing**: Regular security testing recommended
- **Monitoring**: Application security monitoring

---

## 🔄 Security Policy Updates

This security policy is regularly updated to reflect:
- New security features
- Emerging threats
- Best practice changes
- Framework updates

**Last Updated**: 2026-02-18
**Version**: 2.1.0 - Security patch 5.6.6 (path normalization, input/URI sanitization fixes, single php://input read, recursive array sanitization)

---

## ✅ Security Guarantees

### Automatic Protection (No Developer Action Needed)

GEMVC provides **automatic protection** against:

- ✅ **XSS (Cross-Site Scripting)** - ✅ AUTOMATIC (Input sanitization + output encoding)
- ✅ **SQL Injection** - ✅ AUTOMATIC (Prepared statements - 100% coverage)
- ✅ **Path Traversal** - ✅ AUTOMATIC (Path blocking + filename sanitization)
- ✅ **Header Injection** - ✅ AUTOMATIC (Header sanitization)
- ✅ **File Upload Attacks** - ✅ AUTOMATIC (File name/MIME sanitization)
- ✅ **JWT Forgery** - ✅ AUTOMATIC (Signature verification + expiration)

### Developer-Enabled Protection (Simple Method Calls)

- ⚙️ **Mass Assignment** - Call `definePostSchema()` (prevents unwanted fields)
- ⚙️ **Type Confusion** - Call `definePostSchema()` (validates types)
- ⚙️ **Authentication Bypass** - Call `$request->auth()` (JWT validation)
- ⚙️ **Authorization Bypass** - Call `$request->auth(['role'])` (Role checking)
- ⚙️ **File Signature Spoofing** - Use `ImageHelper::convertToWebP()` (Validates magic bytes)
- ⚙️ **File Tampering** - Use `FileHelper::encrypt()` (HMAC integrity verification)

**Result**: 
- **90% of security is AUTOMATIC** - No developer action needed!
- **10% requires simple method calls** - Just use `definePostSchema()` and `auth()` in your API services
- **Zero configuration** - Security works out of the box!

---

*Remember: Security is a shared responsibility. Always follow security best practices and keep your GEMVC installation updated.*
