# Quick Start: Enable Code Coverage

## Step 1: Download PCOV DLL

**For PHP 8.2 ZTS (Thread Safe) - Windows x64:**

**Option A: Windows PHP Downloads (Recommended)**
1. Visit: **https://windows.php.net/downloads/pecl/releases/pcov/**
2. Download the latest version matching: `php_pcov-X.X.X-8.2-ts-vs16-x64.zip`
   - Example: `php_pcov-2.0.11-8.2-ts-vs16-x64.zip`
   - Look for files with `8.2-ts` (Thread Safe) and `x64` (64-bit)

**Option B: GitHub Releases (Alternative)**
1. Visit: **https://github.com/krakjoe/pcov/releases**
2. Download precompiled Windows DLL matching your PHP version

**Option C: PECL Package Page**
1. Visit: **https://pecl.php.net/package/pcov**
2. Check for Windows binaries or build from source

3. Extract the ZIP file
4. Copy `php_pcov.dll` to: `C:\xampp\php\ext\`

## Step 2: Enable in php.ini

1. Open `C:\xampp\php\php.ini` in Notepad (as Administrator)
2. Find the `[Extensions]` section (around line 900-1000)
3. Add these lines:
   ```ini
   extension=pcov
   pcov.enabled=1
   pcov.directory=.
   ```
4. Save the file

## Step 3: Verify Installation

```bash
php -m | findstr pcov
```

You should see `pcov` in the list.

## Step 4: Enable Coverage in PHPUnit

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

## Step 5: Generate Coverage Report

```bash
# HTML report (opens in browser)
vendor/bin/phpunit --coverage-html coverage/html

# Text summary in terminal
vendor/bin/phpunit --coverage-text
```

## Alternative: Xdebug

If you prefer Xdebug (also provides debugging features):

1. Visit: https://xdebug.org/wizard
2. Paste output of: `php -i`
3. Download the recommended DLL
4. Copy to `C:\xampp\php\ext\` as `php_xdebug.dll`
5. Add to `php.ini`:
   ```ini
   zend_extension=xdebug
   xdebug.mode=coverage
   ```

## Troubleshooting

**Extension not loading?**
- Check DLL is in correct directory: `C:\xampp\php\ext\`
- Verify file name is exactly `php_pcov.dll`
- Restart command prompt/terminal
- Check for typos in `php.ini`

**Coverage still not working?**
- Run: `php -r "echo extension_loaded('pcov') ? 'OK' : 'FAIL';"`
- Check PHPUnit detects driver: `vendor/bin/phpunit --version`
- Ensure coverage section is uncommented in `phpunit.xml`

