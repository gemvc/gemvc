# Code Coverage Commands

## HTTP Folder Coverage

### Full HTTP Test Suite with Coverage
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter Http
```

### HTTP Coverage Summary (Methods and Lines Only)
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter Http 2>&1 | Select-String -Pattern "Gemvc\\Http|Summary|Methods|Lines" -Context 0,1
```

### HTTP Coverage with Details
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter Http 2>&1 | Select-String -Pattern "Gemvc\\Http" -Context 0,0
```

### Generate HTML Coverage Report for HTTP Classes
```bash
vendor/bin/phpunit --testsuite Unit --coverage-html coverage/html --filter Http
```
Then open `coverage/html/index.html` in your browser.

## Overall Coverage

### Full Unit Test Suite Coverage
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text
```

### Coverage Summary Only
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text 2>&1 | Select-String -Pattern "Summary|Classes|Methods|Lines" -Context 0,2
```

### Coverage by Class (Top 20)
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text 2>&1 | Select-String -Pattern "Gemvc\\" | Select-Object -First 20
```

## Specific Class Coverage

### Single Class Coverage
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter RequestTest
```

### Multiple Classes
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter "RequestTest|JsonResponseTest|JWTTokenTest"
```

## Quick Coverage Check

### HTTP Classes Only (Quick)
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text --filter Http 2>&1 | Select-String -Pattern "Methods:|Lines:" | Select-String -Pattern "Gemvc\\Http"
```

### All Classes Above 80% Coverage
```bash
vendor/bin/phpunit --testsuite Unit --coverage-text 2>&1 | Select-String -Pattern "Methods:\s+8[0-9]|Methods:\s+9[0-9]|Methods:\s+100"
```

