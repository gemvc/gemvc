# 📦 GEMVC Installation Guide

> Complete step-by-step guide to install and run GEMVC framework

---

## 📋 Prerequisites

Before installing GEMVC, ensure you have:

### Required:
- **PHP 8.1+** (PHP 8.2+ recommended)
- **Composer** (latest version)
- **MySQL 8.0+** or **MariaDB 10.6+**

### Optional (Recommended):
- **Docker & Docker Compose** (for containerized setup)
- **OpenSwoole extension** (for high-performance async server)
- **Redis** (for caching and sessions)

### Check Your PHP Version:
```bash
php -v
# Should show: PHP 8.1.0 or higher

composer --version
# Should show: Composer version 2.x
```

---

## 🚀 Installation Steps

### Step 1: Create Project Directory
```bash
mkdir my-gemvc-api
cd my-gemvc-api
```

### Step 2: Install GEMVC via Composer
```bash
composer require gemvc/library
```

**This will:**
- ✅ Install GEMVC framework
- ✅ Install dependencies (JWT, DotEnv, etc.)
- ✅ Create `vendor/` directory

---

### Step 3: Initialize Project (Interactive)
```bash
php vendor/bin/gemvc init
```

**You'll be prompted:**

#### 3.1: Select Server Type
```
Select server type:
1) OpenSwoole (High-performance, async, WebSocket support)
2) Apache (Traditional PHP-FPM)
3) Nginx (High-performance, reverse proxy)

Your choice: 
```

**Choose:**
- `1` for OpenSwoole (recommended for high-performance APIs)
- `2` for Apache (traditional hosting, shared hosting compatible)
- `3` for Nginx (high-performance, production-ready)

#### 3.2: Install PHPStan? (Recommended!)
```
Install PHPStan for static analysis?
1) Yes (Recommended - PHPStan Level 9)
2) No

Your choice: 
```

**Choose:** `1` (Yes) - PHPStan Level 9 catches bugs before runtime!

#### 3.3: Setup Docker?
```
Setup Docker Compose?
1) Yes (Recommended for development)
2) No

Your choice:
```

**Choose:** `1` (Yes) - Docker makes setup easier!

---

### Step 4: What Gets Created

After `gemvc init`, you'll have:

```
my-gemvc-api/
├── .env                    # Environment configuration
├── docker-compose.yml      # Docker setup (if selected)
├── phpstan.neon           # PHPStan config (if selected)
├── index.php              # Entry point
├── app/
│   ├── api/               # Your API endpoints go here
│   │   ├── Index.php      # Framework API (documentation, SPA)
│   │   ├── User.php       # Example User service (CRUD)
│   │   ├── Apm.php        # APM service (delegates to core)
│   │   ├── GemvcAssistant.php    # Developer tools (delegates to core)
│   │   └── GemvcMonitoring.php    # Server monitoring (delegates to core)
│   ├── controller/        # Your controllers go here
│   │   ├── IndexController.php   # Framework controller
│   │   └── UserController.php    # Example User controller
│   ├── model/             # Your models go here
│   │   └── UserModel.php  # Example User model
│   └── table/             # Your tables go here
│       └── UserTable.php  # Example User table
├── vendor/
│   └── gemvc/             # GEMVC framework (includes core services)
```

---

### Step 5: Configure Database

Edit `.env` file:

```bash
nano .env
```

**Configure these settings:**
```env
# Database Configuration
DB_HOST=localhost          # Use 'mysql' if using Docker
DB_NAME=gemvc_db
DB_USER=root
DB_PASSWORD=yourpassword
DB_PORT=3306

# JWT Configuration
TOKEN_SECRET=your-secret-key-change-this-in-production
TOKEN_ISSUER=YourCompany

# Application Settings
APP_ENV=development
QUERY_LIMIT=10

# Server Port (OpenSwoole only)
SERVER_PORT=9501
```

**Important Notes:**
- Change `TOKEN_SECRET` to a random string in production
- If using Docker, set `DB_HOST=mysql` (container name)
- For Apache/Nginx, use `DB_HOST=localhost`

---

## 🐳 Option A: Start with Docker (Recommended)

### Step 6a: Start Docker Containers
```bash
# Start all services (OpenSwoole + MySQL)
docker compose up -d

# Check container status
docker compose ps
```

**Expected Output:**
```
NAME                COMMAND             STATUS          PORTS
my-gemvc-api-swoole-1   php index.php   Up 2 seconds   0.0.0.0:9501->9501/tcp
my-gemvc-api-mysql-1    mysqld          Up 2 seconds   0.0.0.0:3306->3306/tcp
```

### Step 7a: Test Server is Running ✅

**Open your browser or use curl:**
```bash
curl http://localhost:9501/api
```

**Expected Response:**
```json
{
  "response_code": 200,
  "message": "OK",
  "service_message": "GEMVC server is running"
}
```

**✅ SUCCESS! Server is running (no database needed for this test)**

---

## 🖥️ Option B: Start without Docker

### Step 6b: Install OpenSwoole Extension (if using OpenSwoole)

```bash
# Install OpenSwoole via PECL
pecl install openswoole

# Add to php.ini
echo "extension=openswoole.so" >> /etc/php/8.2/cli/php.ini
```

### Step 7b: Start Server Manually

**For OpenSwoole:**
```bash
php index.php
```

**Expected Output:**
```
[2024-01-15 10:00:00] Swoole HTTP server started on http://0.0.0.0:9501
```

**For Apache:**
- Configure virtual host pointing to your project directory
- Set DocumentRoot to project root
- Restart Apache
- Access via `http://localhost/api`

**For Nginx:**
- Configure server block (see Nginx Setup section below)
- Restart Nginx
- Access via `http://localhost/api`

### Step 8b: Test Server is Running ✅

```bash
curl http://localhost:9501/api
```

**Expected Response:**
```json
{
  "response_code": 200,
  "message": "OK",
  "service_message": "GEMVC server is running"
}
```

---

## 🗄️ Database Setup (Optional - for User endpoints)

**Note:** The basic health check (`/api`) works without database!

### Step 8: Initialize Database
```bash
php vendor/bin/gemvc db:init
```

**This creates the database if it doesn't exist.**

**Expected Output:**
```
Database 'gemvc_db' created successfully!
```

### Step 9: Migrate User Table (Example)
```bash
php vendor/bin/gemvc db:migrate UserTable
```

**Expected Output:**
```
Table 'users' migrated successfully!
- Primary key: id
- Auto increment: id
- Unique: email
- Index: email, description
```

### Step 10: Test User Creation
```bash
curl -X POST http://localhost:9501/api/User/create \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "description": "Test user"
  }'
```

**Expected Response:**
```json
{
  "response_code": 201,
  "message": "created",
  "count": 1,
  "service_message": "User created successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "description": "Test user"
  }
}
```

### Step 11: Test User Read
```bash
curl http://localhost:9501/api/User/read/?id=1
```

**Expected Response:**
```json
{
  "response_code": 200,
  "message": "OK",
  "count": 1,
  "service_message": "User retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "-",
    "description": "Test user"
  }
}
```

---

## 🎯 Generate Your First Service

### Step 12: Create a Product Service
```bash
php vendor/bin/gemvc create:crud Product
```

**This generates 4 files:**
```
✅ app/api/Product.php
✅ app/controller/ProductController.php
✅ app/model/ProductModel.php
✅ app/table/ProductTable.php
```

### Step 13: Edit Product Table

Edit `app/table/ProductTable.php` to define your schema:

```php
<?php
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

class ProductTable extends Table
{
    public int $id;
    public string $name;
    public float $price;
    public ?string $description;
    
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'price' => 'float',
        'description' => 'string',
    ];
    
    public function getTable(): string
    {
        return 'products';
    }
    
    public function defineSchema(): array
    {
        return [
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::index('name'),
        ];
    }
}
```

### Step 14: Migrate Product Table
```bash
php vendor/bin/gemvc db:migrate ProductTable
```

### Step 15: Test Product Creation
```bash
curl -X POST http://localhost:9501/api/Product/create \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop",
    "price": 999.99,
    "description": "High-performance laptop"
  }'
```

---

## ✅ Verification Checklist

After installation, verify everything works:

### 1. Check Server Status
```bash
curl http://localhost:9501/api
# Should return: "GEMVC server is running"
```

### 2. Check CLI Commands
```bash
php vendor/bin/gemvc --version
# Should show: GEMVC version
```

### 3. Check Database Connection
```bash
php vendor/bin/gemvc db:list
# Should list your tables
```

### 4. Check Directory Structure
```bash
ls app/api/
ls app/controller/
ls app/model/
ls app/table/
# Should show generated files
```

### 5. Run PHPStan (if installed)
```bash
vendor/bin/phpstan analyse
# Should show: No errors
```

### 6. Test User Endpoints
```bash
# Create
curl -X POST http://localhost:9501/api/User/create \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","password":"pass123"}'

# Read
curl http://localhost:9501/api/User/read/?id=1

# List
curl http://localhost:9501/api/User/list
```

---

## 🔧 Troubleshooting

### Issue: "Connection refused" on port 9501

**Solution:**
```bash
# Check if Swoole is running
docker-compose ps

# Check logs
docker-compose logs swoole

# Restart
docker-compose restart swoole
```

### Issue: "Database connection failed"

**Solution:**
```bash
# Check .env settings
cat .env | grep DB_

# If using Docker, DB_HOST should be 'mysql' not 'localhost'
# Edit .env and change:
DB_HOST=mysql

# Restart containers
docker-compose restart
```

### Issue: "Table already exists"

**Solution:**
```bash
# Drop and recreate table
php vendor/bin/gemvc db:drop TableName
php vendor/bin/gemvc db:migrate TableName --force
```

### Issue: "Class not found"

**Solution:**
```bash
# Regenerate autoloader
composer dump-autoload
```

### Issue: OpenSwoole extension not installed

**Solution:**
```bash
# Install via PECL
pecl install openswoole

# Or use Docker (recommended)
docker-compose up -d
```

### Issue: "Permission denied" on Linux/Mac

**Solution:**
```bash
# Fix permissions
chmod -R 755 app/
chmod -R 755 vendor/
sudo chown -R $USER:$USER .
```

### Issue: Port 9501 already in use

**Solution:**
```bash
# Find process using port
lsof -i :9501

# Kill process
kill -9 [PID]

# Or change port in .env
SERVER_PORT=9502
```

---

## 🐳 Docker Commands Reference

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart specific service
docker-compose restart swoole

# View logs
docker-compose logs -f swoole
docker-compose logs -f mysql

# Execute command in container
docker-compose exec swoole php vendor/bin/gemvc db:list

# Rebuild containers
docker-compose down
docker-compose up -d --build

# Remove volumes (WARNING: deletes database!)
docker-compose down -v
```

---

## 📊 Different Server Setups

### OpenSwoole Setup (Recommended)

**Advantages:**
- ⚡ High performance (async I/O)
- 🔌 WebSocket support
- 🔄 Hot reload during development
- 🚀 Connection pooling

**Start:**
```bash
php index.php
# or
docker-compose up -d
```

**URL:** `http://localhost:9501/api`

---

### Apache Setup

**Advantages:**
- 🏢 Traditional hosting
- 🌐 Shared hosting compatible
- 📁 .htaccess support

**Apache Virtual Host:**
```apache
<VirtualHost *:80>
    ServerName myapi.local
    DocumentRoot /var/www/my-gemvc-api
    
    <Directory /var/www/my-gemvc-api>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Restart Apache:**
```bash
sudo systemctl restart apache2
```

**URL:** `http://localhost/api`

---

### Nginx Setup

**Advantages:**
- 🚀 High performance
- 📈 Good for high traffic
- 🔧 Flexible configuration

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name myapi.local;
    root /var/www/my-gemvc-api;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Restart Nginx:**
```bash
sudo systemctl restart nginx
```

**URL:** `http://localhost/api`

---

## 🎓 Next Steps

After successful installation:

1. **Read Documentation**
   - [README.MD](README.MD) - Framework overview
   - [QUICK_START_AI.md](QUICK_START_AI.md) - For AI assistants
   - [ARCHITECTURE.md](ARCHITECTURE.md) - Deep dive

2. **Study Examples**
   - Check `src/startup/user/` for complete User example
   - See how 4-layer architecture works

3. **Generate Your Services**
   ```bash
   php vendor/bin/gemvc create:crud YourService
   ```

4. **Run PHPStan**
   ```bash
   vendor/bin/phpstan analyse
   ```

5. **Customize Templates** (optional)
   - Copy templates from `vendor/gemvc/swoole/templates/` to `templates/`
   - Edit to match your coding style

---

## 🎉 Installation Complete!

Your GEMVC framework is ready to use!

**Quick Test:**
```bash
# Health check
curl http://localhost:9501/api
# Should return: "GEMVC server is running"
```

**Start building your API! 🚀**

---

## 📞 Need Help?

- 📖 [README.MD](README.MD) - Framework overview
- 🏗️ [ARCHITECTURE.MD](ARCHITECTURE.md) - Architecture details
- 🔒 [SECURITY.MD](SECURITY.md) - Security features
- 🤖 [QUICK_START_AI.md](QUICK_START_AI.md) - For AI assistants

**Happy coding with GEMVC! 🎯**

