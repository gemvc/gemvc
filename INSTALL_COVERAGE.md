# Installing Code Coverage Driver (PCOV/Xdebug) for Windows/XAMPP

## Quick Installation Guide

### Option 1: PCOV (Recommended - Faster for Coverage)

#### Step 1: Download PCOV DLL

**Official Download Sources (choose one):**

1. **Windows PHP Downloads (Recommended):**
   - URL: https://windows.php.net/downloads/pecl/releases/pcov/
   - Look for: `php_pcov-X.X.X-8.2-ts-vs16-x64.zip`
   - This is the official Windows PHP extension repository

2. **GitHub Releases (Alternative):**
   - URL: https://github.com/krakjoe/pcov/releases
   - Look for precompiled Windows binaries

3. **PECL Package Page:**
   - URL: https://pecl.php.net/package/pcov
   - Source code and release information

#### Step 2: Extract and Copy DLL
1. Extract the ZIP file
2. Copy `php_pcov.dll` to your PHP extensions directory:
   - Usually: `C:\xampp\php\ext\`

#### Step 3: Enable in php.ini
1. Open `C:\xampp\php\php.ini` in a text editor
2. Find the `[Extensions]` section or add at the end:
   ```ini
   extension=pcov
   pcov.enabled=1
   pcov.directory=.
   ```
3. Save the file

#### Step 4: Verify Installation
```bash
php -m | findstr pcov
php -r "echo extension_loaded('pcov') ? 'PCOV loaded' : 'PCOV not loaded';"
```

### Option 2: Xdebug (Alternative - Also provides debugging)

#### Step 1: Download Xdebug DLL
1. Visit: https://xdebug.org/download
2. Use the wizard: https://xdebug.org/wizard
3. Download PHP 8.2 ZTS Windows x64 DLL

#### Step 2: Extract and Copy DLL
1. Extract the ZIP file
2. Rename to `php_xdebug.dll` if needed
3. Copy to `C:\xampp\php\ext\`

#### Step 3: Enable in php.ini
```ini
zend_extension=xdebug
xdebug.mode=coverage
```

#### Step 4: Verify Installation
```bash
php -m | findstr xdebug
php -v  # Should show Xdebug version
```

## After Installation

### Enable Coverage in phpunit.xml
Uncomment the coverage section in `phpunit.xml`:

```xml
<coverage>
    <include>
        <directory suffix=".php">src</directory>
    </include>
    <exclude>
        <directory>src/stubs</directory>
        <directory>src/startup</directory>
    </exclude>
    <report>
        <html outputDirectory="coverage/html"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
</coverage>
```

### Generate Coverage Report
```bash
# HTML report
vendor/bin/phpunit --coverage-html coverage/html

# Clover XML (for CI/CD)
vendor/bin/phpunit --coverage-clover coverage/clover.xml

# Text summary
vendor/bin/phpunit --coverage-text
```

## Troubleshooting

### DLL Not Found
- Ensure DLL is in `C:\xampp\php\ext\`
- Check file name matches exactly (case-sensitive)
- Verify PHP architecture (x64 vs x86)

### Extension Not Loading
- Check `php.ini` syntax
- Restart web server if running
- Check PHP error log: `C:\xampp\php\logs\php_error_log`

### Coverage Not Working
- Verify extension is loaded: `php -m`
- Check PHPUnit can detect driver: `vendor/bin/phpunit --version`
- Ensure coverage section is uncommented in `phpunit.xml`

## Notes

- **PCOV vs Xdebug**: PCOV is faster for coverage-only use. Xdebug provides debugging features too.
- **Mutual Exclusivity**: Don't enable both PCOV and Xdebug simultaneously
- **Performance**: PCOV is 2-3x faster than Xdebug for code coverage

