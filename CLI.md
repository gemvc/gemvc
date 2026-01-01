# 🛠️ GEMVC CLI Commands Documentation

Complete reference guide for all GEMVC command-line interface commands.

---

## 📋 Table of Contents

- [CLI Architecture](#cli-architecture)
- [Installation & Setup](#installation--setup)
- [Command Structure](#command-structure)
- [Project Management](#project-management)
- [Code Generation](#code-generation)
- [Database Commands](#database-commands)
- [Flags & Options](#flags--options)
- [Examples](#examples)
- [Troubleshooting](#troubleshooting)

---

## 🏗️ CLI Architecture

### Command Class Hierarchy

All GEMVC CLI commands follow a structured inheritance pattern:

```
Command (Base Class)
    ↓
├── AbstractInit (Template Method Pattern)
│   ├── InitApache (Apache-specific)
│   ├── InitSwoole (OpenSwoole-specific)
│   └── InitNginx (Nginx-specific - coming soon)
│
├── AbstractBaseGenerator (Code Generation Base)
│   ├── CreateService
│   ├── CreateController
│   ├── CreateModel
│   ├── CreateTable
│   └── CreateCrud
│
└── Direct Commands
    ├── DbInit
    ├── DbMigrate
    ├── DbList
    ├── DbDrop
    └── DbUnique
```

### Core Components

#### 1. **Command.php** - Base Class for All Commands

**Purpose**: Provides common functionality for all CLI commands.

**Features**:
- ✅ Argument and option handling (`$args`, `$options`)
- ✅ Colored output support (ANSI colors, Windows-compatible)
- ✅ Standardized messaging (`info()`, `success()`, `error()`, `warning()`)
- ✅ Abstract `execute()` method (must be implemented)

**Usage**:
```php
abstract class Command
{
    protected array $args;
    protected array $options;
    
    abstract public function execute(): bool;
    
    protected function info(string $message): void { }
    protected function success(string $message): void { }
    protected function error(string $message): void { }
    protected function warning(string $message): void { }
}
```

**All commands extend this**:
```php
class CreateService extends AbstractBaseGenerator  // Which extends Command
class DbInit extends Command  // Directly extends Command
```

---

#### 2. **AbstractInit.php** - Template Method Pattern

**Purpose**: Defines the skeleton of project initialization for all webservers.

**Template Method Pattern**:
```php
final public function execute(): bool
{
    // 1. Initialize (same for all)
    $this->initializeProject();
    
    // 2. Setup structure (same for all)
    $this->setupProjectStructure();
    
    // 3. Copy common files (same for all)
    $this->copyCommonProjectFiles();
    
    // 4. Copy webserver-specific files (DIFFERENT per webserver)
    $this->copyWebserverSpecificFiles();  // Abstract method
    
    // 5. Setup autoload (same for all)
    $this->setupPsr4Autoload();
    
    // ... more steps
}
```

**Abstract Methods** (must be implemented by webserver classes):
- `getWebserverType(): string` - Return 'Apache', 'OpenSwoole', etc.
- `getWebserverSpecificDirectories(): array` - Additional directories
- `copyWebserverSpecificFiles(): void` - Webserver-specific files
- `getStartupTemplatePath(): string` - Path to startup templates
- `getDefaultPort(): int` - Default server port
- `getStartCommand(): string` - Command to start server

**Shared Methods** (used by all webservers):
- `createDirectories()` - Creates all directories
- `copyTemplatesFolder()` - Copies templates to project root
- `setupPsr4Autoload()` - Configures composer.json
- `createEnvFile()` - Creates .env from example
- `offerDockerServices()` - Docker setup wizard
- `offerOptionalTools()` - PHPStan, PHPUnit installation

**Result**: Webserver-specific init classes only implement what's different!

---

#### 3. **InitProject.php** - Orchestrator Pattern

**Purpose**: Main entry point that orchestrates webserver selection and delegates to specific init classes.

**How It Works**:
```php
1. User runs: gemvc init
2. InitProject displays webserver menu
3. User selects: Apache, OpenSwoole, or Nginx
4. InitProject creates appropriate Init class:
   - InitApache for Apache
   - InitSwoole for OpenSwoole
   - InitNginx for Nginx (coming soon)
5. Delegates execution to selected class
```

**Webserver Selection**:
```php
// Interactive mode
gemvc init
// Shows menu:
//   1. OpenSwoole
//   2. Apache
//   3. Nginx

// Non-interactive mode
gemvc init --apache
gemvc init --swoole
gemvc init --server=apache
```

**Delegation**:
```php
// InitProject.php
$initCommand = new $className($this->args, $this->options);
return $initCommand->execute();  // Delegates to InitApache or InitSwoole
```

---

#### 4. **InitApache.php & InitSwoole.php** - Strategy Pattern

**Purpose**: Implement webserver-specific initialization logic.

**Both extend AbstractInit** and implement abstract methods:

**InitApache**:
```php
class InitApache extends AbstractInit
{
    protected function getWebserverType(): string
    {
        return 'Apache';
    }
    
    protected function copyWebserverSpecificFiles(): void
    {
        // Copy Apache-specific files:
        // - .htaccess
        // - public/ directory structure
        // - Apache composer.json
    }
    
    protected function getDefaultPort(): int
    {
        return 80;
    }
}
```

**InitSwoole**:
```php
class InitSwoole extends AbstractInit
{
    protected function getWebserverType(): string
    {
        return 'OpenSwoole';
    }
    
    protected function copyWebserverSpecificFiles(): void
    {
        // Copy OpenSwoole-specific files:
        // - index.php (OpenSwoole bootstrap)
        // - Dockerfile (OpenSwoole container configuration)
        // - Swoole composer.json (with Hyperf dependencies)
    }
    
    protected function getDefaultPort(): int
    {
        return 9501;
    }
}
```

**Key Point**: Only differences are implemented. Common logic stays in `AbstractInit`!

---

#### 5. **CommandCategories.php** - Command Registry

**Purpose**: Central registry for all CLI commands, their categories, and examples.

**Features**:
- ✅ Command categories (Project Management, Code Generation, Database)
- ✅ Command-to-class mapping
- ✅ Command descriptions
- ✅ Usage examples

**Usage**:
```php
// Get command class name
$className = CommandCategories::getCommandClass('create:service');
// Returns: 'CreateService'

// Get command category
$category = CommandCategories::getCategory('create:service');
// Returns: 'Code Generation'

// Get command description
$description = CommandCategories::getDescription('create:service');
// Returns: 'Create a new service with optional components...'

// Get examples
$examples = CommandCategories::getExamples();
// Returns: ['create:service' => ['gemvc create:service User', ...]]
```

---

#### 6. **FileSystemManager.php** - Utility Class

**Purpose**: Centralized file and directory operations for all CLI commands.

**Features**:
- ✅ Directory creation (`createDirectories()`)
- ✅ File copying with overwrite confirmation (`copyFileWithConfirmation()`)
- ✅ Template folder copying (`copyTemplatesFolder()`)
- ✅ File content reading (`getFileContent()`)
- ✅ Non-interactive mode support

**Usage**:
```php
$fileSystem = new FileSystemManager($nonInteractive, $verbose);

// Create directories
$fileSystem->createDirectories(['app/api', 'app/controller']);

// Copy file with confirmation
$fileSystem->copyFileWithConfirmation($source, $target, 'file.php');

// Copy templates folder
$fileSystem->copyTemplatesFolder($packagePath, $basePath);
```

**Benefit**: All CLI commands use the same file operations, ensuring consistency!

---

#### 7. **DockerComposeInit.php** - Docker Services Manager

**Purpose**: Interactive Docker Compose setup wizard integrated into project initialization.

**Features**:
- ✅ Interactive service selection (Redis, phpMyAdmin, MySQL)
- ✅ Dynamic `docker-compose.yml` generation
- ✅ Development/Production mode selection
- ✅ Docker volume cleanup
- ✅ Service dependencies handling
- ✅ Webserver-specific configuration

**How It Works**:
```php
// Called from AbstractInit::execute()
$this->offerDockerServices();

// Inside offerDockerServices():
$dockerInit = new DockerComposeInit($basePath, $nonInteractive, $webserverType, $port);
$dockerInit->offerDockerServices();
```

**Service Selection Flow**:
```
1. Display available services
   - Redis (default: yes)
   - phpMyAdmin (default: yes)
   - MySQL (default: yes)

2. User selects services
   - Press Enter for defaults
   - Or type 'y'/'n' for each

3. Ask for MySQL mode
   - Development Mode (clean logs)
   - Production Mode (verbose logs)

4. Generate docker-compose.yml
   - Webserver service (OpenSwoole/Apache)
   - Selected services
   - Volumes and networks

5. Offer Docker cleanup
   - Clean existing containers/volumes
```

**Usage in AbstractInit**:
```php
// AbstractInit.php - Line 74
protected function offerDockerServices(): void
{
    $webserverType = strtolower($this->getWebserverType());
    $port = $this->getDefaultPort();
    
    $dockerInit = new DockerComposeInit(
        $this->basePath, 
        $this->nonInteractive, 
        $webserverType, 
        $port
    );
    $dockerInit->offerDockerServices();
}
```

**Available Services**:
- **Redis** - Cache and session storage (port 6379)
- **phpMyAdmin** - MySQL administration (port 8080)
- **MySQL** - Database server (port 3306)

**Configuration Modes**:
- **Development Mode**: Clean logs, optimized settings
- **Production Mode**: Verbose logs, full security warnings

**Generated docker-compose.yml Structure**:
```yaml
services:
  openswoole:  # or 'web' for Apache
    build: ...
    ports: ...
    depends_on: [db, redis]  # Auto-added
  
  db:
    image: mysql:8.0
    command: [--optimized-settings]
  
  redis:
    image: redis:latest
  
  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    depends_on: [db]

volumes:
  mysql-data:
  redis-data:

networks:
  backend-network:
```

**Integration**: Called automatically during `gemvc init` process!

---

#### 8. **ProjectHelper.php** - Path & Environment Helper

**Purpose**: Provides path resolution and environment loading for CLI commands.

**Key Methods**:

**`rootDir()`** - Finds project root via `composer.lock`:
```php
// Traverses up directory tree looking for composer.lock
ProjectHelper::rootDir();  // Returns: /path/to/project
```

**`appDir()`** - Gets app directory:
```php
ProjectHelper::appDir();  // Returns: /path/to/project/app
```

**`loadEnv()`** - Loads `.env` file:
```php
// Tries root/.env first, then app/.env
ProjectHelper::loadEnv();  // Loads environment variables
```

**Used By**:
- Database commands (`DbInit`, `DbMigrate`, etc.)
- Code generation commands
- All commands that need project paths

**Example**:
```php
// In DbInit.php
ProjectHelper::loadEnv();
$dbName = $_ENV['DB_NAME'];
```

---

### Architecture Flow

```
User runs: gemvc init
    ↓
bin/gemvc (entry point)
    ↓
CommandCategories::getCommandClass('init')
    Returns: 'InitProject'
    ↓
new InitProject($args, $options)
    ↓
InitProject::execute()
    - Displays webserver menu
    - User selects Apache
    ↓
new InitApache($args, $options)
    ↓
InitApache::execute()  // Inherited from AbstractInit
    ↓
AbstractInit::execute()  // Template Method
    ├─ initializeProject()
    ├─ setupProjectStructure()
    │   └─ FileSystemManager::createDirectories()
    ├─ copyCommonProjectFiles()
    ├─ copyWebserverSpecificFiles()  // Implemented in InitApache
    ├─ setupPsr4Autoload()
    ├─ createEnvFile()
    │   └─ ProjectHelper::rootDir()  // Find project root
    ├─ offerDockerServices()
    │   └─ DockerComposeInit::offerDockerServices()
    │       ├─ Display service selection menu
    │       ├─ Get user selections
    │       ├─ Generate docker-compose.yml
    │       └─ Display Docker instructions
    └─ offerOptionalTools()
```

---

### Design Patterns Used

1. **Template Method** - `AbstractInit::execute()` defines skeleton
2. **Strategy** - `InitApache`, `InitSwoole` are different strategies
3. **Orchestrator** - `InitProject` orchestrates webserver selection
4. **Factory** - `CommandCategories` maps commands to classes
5. **Singleton-like** - `ProjectHelper::rootDir()` caches result
6. **Facade** - `FileSystemManager` simplifies file operations
7. **Builder** - `DockerComposeInit` builds docker-compose.yml dynamically

---

### How Commands Are Discovered

**Command Discovery** (in `bin/gemvc`):
```php
// 1. Parse command from CLI
$command = $argv[1];  // e.g., 'create:service'

// 2. Convert to class name
$className = CommandCategories::getCommandClass($command);
// 'create:service' → 'CreateService'

// 3. Find command class
$commandClass = 'Gemvc\\CLI\\Commands\\' . $className;

// 4. Instantiate and execute
$commandObj = new $commandClass($args);
$commandObj->execute();
```

**Command Naming Convention**:
- `create:service` → `CreateService`
- `db:migrate` → `DbMigrate`
- `init` → `InitProject`

---

### Code Generation Commands Hierarchy

```
AbstractBaseGenerator extends Command
    ↓
├── CreateService
├── CreateController
├── CreateModel
├── CreateTable
└── CreateCrud extends AbstractBaseCrudGenerator extends AbstractBaseGenerator
```

**AbstractBaseGenerator** provides:
- Template loading (`getTemplate()`)
- Variable replacement (`replaceTemplateVariables()`)
- File writing (`writeFile()`)
- Project root detection (`determineProjectRoot()`)

**Result**: All generation commands share common functionality!

---

## 🚀 Installation & Setup

### Install GEMVC

```bash
composer require gemvc/swoole
```

### Run Commands

```bash
# Using vendor binary
php vendor/bin/gemvc <command>

# Or add to composer.json scripts
composer gemvc <command>
```

---

## 📝 Command Structure

### Basic Syntax

```bash
gemvc <command> [arguments] [flags]
```

### Command Categories

GEMVC commands are organized into three categories:

1. **Project Management** - Initialize and configure projects
2. **Code Generation** - Generate Services, Controllers, Models, Tables
3. **Database** - Database management and migrations

---

## 🏗️ Project Management

### `init` - Initialize New Project

Initialize a new GEMVC project with webserver selection.

```bash
gemvc init
```

**Interactive Mode**:
- Prompts you to select webserver (OpenSwoole, Apache, Nginx)
- Offers optional PHPStan installation
- Offers optional testing framework (PHPUnit/Pest)
- Sets up project structure
- Copies templates to project root

**Non-Interactive Mode**:
```bash
# OpenSwoole
gemvc init --swoole

# Apache
gemvc init --apache

# Nginx
gemvc init --nginx

# Or use --server flag
gemvc init --server=swoole
gemvc init --server=apache
```

**What It Does**:
- ✅ Creates project directory structure (`app/api`, `app/controller`, `app/model`, `app/table`)
- ✅ Copies webserver-specific files (`index.php`, `Dockerfile`, etc.)
- ✅ Copies templates (`templates/cli/`) for code generation
- ✅ Sets up `.env` file from `example.env`
- ✅ Installs dependencies (`composer.json`)
- ✅ **Automatically installs OpenSwoole-specific packages** (when OpenSwoole is selected: `gemvc/connection-openswoole` package, which includes all required Hyperf dependencies)
- ✅ **Offers Docker setup** (interactive service selection via `DockerComposeInit`)
- ✅ Offers PHPStan installation (optional)
- ✅ Offers testing framework (optional)

**Note**: The `gemvc/connection-openswoole` package is only installed when OpenSwoole is selected. Apache and Nginx projects do not include this package, reducing default package size.

**Flags**:
- `--swoole` - Initialize for OpenSwoole
- `--apache` - Initialize for Apache
- `--nginx` - Initialize for Nginx
- `--server=<name>` - Specify webserver (`swoole`, `apache`, `nginx`)
- `--non-interactive` or `-n` - Skip prompts, use defaults

**Example**:
```bash
# Interactive initialization
gemvc init

# Non-interactive OpenSwoole initialization
gemvc init --swoole --non-interactive
```

---

## 🎨 Code Generation

### `create:service` - Create API Service

Generate a new API service with optional components.

```bash
gemvc create:service <ServiceName> [flags]
```

**Flags**:
- `-c` - Also create Controller
- `-m` - Also create Model
- `-t` - Also create Table
- Combine flags: `-cmt` creates all components

**Examples**:
```bash
# Create service only
gemvc create:service Product

# Create service + controller
gemvc create:service Product -c

# Create service + controller + model + table
gemvc create:service Product -cmt
```

**Generated Files**:
- `app/api/Product.php` - API endpoint service
- `app/controller/ProductController.php` (if `-c`)
- `app/model/ProductModel.php` (if `-m`)
- `app/table/ProductTable.php` (if `-t`)

---

### `create:controller` - Create Controller

Generate a new controller for business logic.

```bash
gemvc create:controller <ControllerName> [flags]
```

**Flags**:
- `-m` - Also create Model
- `-t` - Also create Table

**Examples**:
```bash
# Create controller only
gemvc create:controller Product

# Create controller + model + table
gemvc create:controller Product -mt
```

**Generated Files**:
- `app/controller/ProductController.php`
- `app/model/ProductModel.php` (if `-m`)
- `app/table/ProductTable.php` (if `-t`)

---

### `create:model` - Create Model

Generate a new model for data logic.

```bash
gemvc create:model <ModelName> [flags]
```

**Flags**:
- `-t` - Also create Table

**Examples**:
```bash
# Create model only
gemvc create:model Product

# Create model + table
gemvc create:model Product -t
```

**Generated Files**:
- `app/model/ProductModel.php`
- `app/table/ProductTable.php` (if `-t`)

---

### `create:table` - Create Table Class

Generate a new table class for database operations.

```bash
gemvc create:table <TableName>
```

**Examples**:
```bash
gemvc create:table Product
```

**Generated Files**:
- `app/table/ProductTable.php`

---

### `create:crud` - Create Complete CRUD

Generate full CRUD operations (Service, Controller, Model, Table).

```bash
gemvc create:crud <ServiceName>
```

**Examples**:
```bash
gemvc create:crud Product
```

**Generated Files**:
- `app/api/Product.php`
- `app/controller/ProductController.php`
- `app/model/ProductModel.php`
- `app/table/ProductTable.php`

**What Gets Generated**:
- ✅ Full CRUD methods: `create()`, `read()`, `update()`, `delete()`, `list()`
- ✅ Schema validation in API layer
- ✅ Business logic in Controller layer
- ✅ Data logic in Model layer
- ✅ Database operations in Table layer
- ✅ Helper methods (`selectById()`, `selectByName()`, etc.)

---

## 🗄️ Database Commands

### `db:init` - Initialize Database

Create the database if it doesn't exist.

```bash
gemvc db:init
```

**What It Does**:
- Reads `DB_NAME` from `.env`
- Connects as root user (`DB_ROOT_USER`, `DB_ROOT_PASSWORD`)
- Creates database if not exists

**Example**:
```bash
gemvc db:init
# Output: ✅ Database 'myapp' initialized successfully!
```

**Environment Variables Required**:
```env
DB_HOST_CLI_DEV=localhost
DB_ROOT_USER=root
DB_ROOT_PASSWORD=password
DB_NAME=myapp
```

---

### `db:migrate` - Run Migration

Create or update database tables based on PHP class definitions.

```bash
gemvc db:migrate <TableClassName> [flags]
```

**Flags**:
- `--force` - Remove columns not in class definition
- `--enforce-not-null` - Enforce NOT NULL constraints
- `--sync-schema` - Sync schema constraints (unique, indexes, foreign keys)
- `--default=<value>` - Set default value for new columns

**Examples**:
```bash
# Create/update table from class
gemvc db:migrate UserTable

# Force sync (remove missing columns)
gemvc db:migrate UserTable --force

# Sync schema constraints
gemvc db:migrate UserTable --sync-schema

# Set default value for new columns
gemvc db:migrate UserTable --default="Active"
```

**What It Does**:
- ✅ Creates table if it doesn't exist
- ✅ Adds new columns for new properties
- ✅ Updates column types if changed
- ✅ Updates nullable status
- ✅ Manages indexes
- ✅ Applies schema constraints (unique, foreign keys)
- ✅ Removes obsolete constraints (with `--sync-schema`)

**How It Works**:
1. Reads your Table class (e.g., `UserTable.php`)
2. Analyzes properties and types
3. Generates SQL schema
4. Compares with existing database
5. Creates/updates as needed

---

### `db:list` - List Tables

Show all tables in the database.

```bash
gemvc db:list
```

**Example Output**:
```
Tables in database 'myapp':
  - users
  - products
  - orders
  - categories
```

---

### `db:describe` - Describe Table Structure

Show detailed structure of a table.

```bash
gemvc db:describe <TableName>
```

**Examples**:
```bash
gemvc db:describe users
```

**Example Output**:
```
Table: users
Columns:
  - id (INT, PRIMARY KEY, AUTO_INCREMENT)
  - name (VARCHAR(255), NOT NULL)
  - email (VARCHAR(320), UNIQUE, NOT NULL)
  - created_at (DATETIME, NULL)
Indexes:
  - PRIMARY (id)
  - UNIQUE (email)
```

---

### `db:drop` - Drop Table

Drop a database table.

```bash
gemvc db:drop <TableName>
```

**Examples**:
```bash
gemvc db:drop users
```

**⚠️ Warning**: This permanently deletes the table and all its data!

---

### `db:unique` - Add Unique Constraint

Add a unique constraint to a table column.

```bash
gemvc db:unique <TableName> <ColumnName>
```

**Examples**:
```bash
gemvc db:unique users email
```

---

## 🎯 Flags & Options

### Code Generation Flags

| Flag | Description | Commands |
|------|-------------|----------|
| `-c` | Create Controller | `create:service` |
| `-m` | Create Model | `create:service`, `create:controller` |
| `-t` | Create Table | `create:service`, `create:controller`, `create:model` |
| `-cmt` | Create all components | `create:service` |

### Migration Flags

| Flag | Description | Commands |
|------|-------------|----------|
| `--force` | Remove columns not in class | `db:migrate` |
| `--enforce-not-null` | Enforce NOT NULL constraints | `db:migrate` |
| `--sync-schema` | Sync schema constraints | `db:migrate` |
| `--default=<value>` | Set default for new columns | `db:migrate` |

### Project Initialization Flags

| Flag | Description | Commands |
|------|-------------|----------|
| `--swoole` | Initialize for OpenSwoole | `init` |
| `--apache` | Initialize for Apache | `init` |
| `--nginx` | Initialize for Nginx | `init` |
| `--server=<name>` | Specify webserver | `init` |
| `--non-interactive` or `-n` | Skip prompts | `init` |

---

## 📚 Examples

### Complete Workflow Example

```bash
# 1. Initialize project
gemvc init --swoole

# 2. Initialize database
gemvc db:init

# 3. Create CRUD for Product
gemvc create:crud Product

# 4. Migrate Product table
gemvc db:migrate ProductTable

# 5. List all tables
gemvc db:list

# 6. Describe Product table
gemvc db:describe products
```

### Code Generation Examples

```bash
# Create service with all components
gemvc create:service User -cmt

# Create controller with model and table
gemvc create:controller Order -mt

# Create model with table
gemvc create:model Category -t

# Create complete CRUD
gemvc create:crud Product
```

### Database Examples

```bash
# Initialize database
gemvc db:init

# Create/update User table
gemvc db:migrate UserTable

# Force sync User table
gemvc db:migrate UserTable --force --sync-schema

# Add unique constraint
gemvc db:unique users email

# List all tables
gemvc db:list

# Describe table
gemvc db:describe users

# Drop table (careful!)
gemvc db:drop test_table
```

---

## 🔧 Troubleshooting

### Command Not Found

**Error**: `Command 'create:service' not found`

**Solution**:
```bash
# Ensure you're in project root
cd /path/to/your/project

# Ensure Composer autoload is up to date
composer dump-autoload

# Try running command
php vendor/bin/gemvc create:service Product
```

---

### Template Not Found

**Error**: `Template not found: service`

**Solution**:
```bash
# Ensure templates are copied during init
gemvc init

# Or manually copy templates
cp -r vendor/gemvc/swoole/src/CLI/templates templates

# Verify templates exist
ls templates/cli/
```

---

### Database Connection Error

**Error**: `Failed to connect to database`

**Solution**:
1. Check `.env` file exists
2. Verify database credentials:
   ```env
   DB_HOST_CLI_DEV=localhost
   DB_ROOT_USER=root
   DB_ROOT_PASSWORD=password
   DB_NAME=myapp
   ```
3. Ensure database server is running
4. Test connection:
   ```bash
   gemvc db:init
   ```

---

### Migration Fails

**Error**: `Failed to migrate table`

**Solution**:
1. Check Table class exists: `app/table/ProductTable.php`
2. Verify class extends `Table`
3. Check `getTable()` method returns table name
4. Check property types are valid PHP types
5. Try with `--force` flag:
   ```bash
   gemvc db:migrate ProductTable --force
   ```

---

### File Already Exists

**Error**: `File already exists: app/api/Product.php`

**Solution**:
- Delete existing file and regenerate
- Or manually edit the existing file
- Or use a different name

---

## 💡 Tips & Best Practices

### 1. Use CRUD Command for Complete Setup

Instead of creating components separately:
```bash
# ✅ Recommended
gemvc create:crud Product

# ❌ Not recommended
gemvc create:service Product -cmt
```

### 2. Migrate After Creating Tables

Always migrate after creating table classes:
```bash
gemvc create:crud Product
gemvc db:migrate ProductTable
```

### 3. Use Custom Templates

Customize templates in `templates/cli/`:
```bash
# Edit templates
vim templates/cli/service.template

# Generate code (uses your custom template)
gemvc create:crud Product
```

### 4. Run PHPStan After Generation

Check code quality:
```bash
gemvc create:crud Product
vendor/bin/phpstan analyse
```

### 5. Use Descriptive Names

Use PascalCase for service/model names:
```bash
# ✅ Good
gemvc create:crud Product
gemvc create:crud UserProfile

# ❌ Avoid
gemvc create:crud product
gemvc create:crud user_profile
```

---

## 📖 Command Reference Quick Guide

| Command | Description | Example |
|---------|-------------|---------|
| `init` | Initialize project | `gemvc init --swoole` |
| `create:service` | Create API service | `gemvc create:service Product -cmt` |
| `create:controller` | Create controller | `gemvc create:controller Product -mt` |
| `create:model` | Create model | `gemvc create:model Product -t` |
| `create:table` | Create table class | `gemvc create:table Product` |
| `create:crud` | Create complete CRUD | `gemvc create:crud Product` |
| `db:init` | Initialize database | `gemvc db:init` |
| `db:migrate` | Migrate table | `gemvc db:migrate ProductTable` |
| `db:list` | List tables | `gemvc db:list` |
| `db:describe` | Describe table | `gemvc db:describe products` |
| `db:drop` | Drop table | `gemvc db:drop products` |
| `db:unique` | Add unique constraint | `gemvc db:unique users email` |

---

## 🎯 Summary

GEMVC CLI provides:

- ✅ **Project Management** - Initialize projects with different webservers
- ✅ **Code Generation** - Generate Services, Controllers, Models, Tables
- ✅ **Database Management** - Migrate, list, describe, drop tables
- ✅ **Template System** - Customizable code generation templates
- ✅ **Non-Interactive Mode** - Suitable for CI/CD pipelines

**Start Building**:
```bash
gemvc init --swoole
gemvc create:crud Product
gemvc db:migrate ProductTable
```

Happy coding! 🚀

---

## 🔍 Architecture Deep Dive

### Creating a Custom Command

Want to create your own CLI command? Follow this pattern:

**1. Create Command Class**:
```php
<?php
namespace App\CLI\Commands;

use Gemvc\CLI\Command;

class MyCustomCommand extends Command
{
    public function execute(): bool
    {
        // Use ProjectHelper for paths
        $rootDir = \Gemvc\Helper\ProjectHelper::rootDir();
        
        // Use inherited methods
        $this->info("Processing...");
        
        // Access arguments
        $name = $this->args[0] ?? 'default';
        
        // Do your work
        $this->success("Done!");
        return true;
    }
}
```

**2. Register in CommandCategories** (or create custom mapping):
```php
// Add to CommandCategories.php
public static function getCommandClass(string $command): string
{
    $commandMappings = [
        // ... existing commands
        'my:command' => 'MyCustomCommand',
    ];
}
```

**3. Use Command**:
```bash
gemvc my:command
```

---

### Docker Services Setup

**During `gemvc init`**, you'll be asked about Docker services:

```bash
gemvc init
# ... webserver selection ...
# Docker Services Setup prompt appears
```

**Interactive Flow**:
1. **Service Selection**:
   ```
   Set up Docker services? (y/N): y
   
   Select services:
   - Redis [Y/n]: y
   - phpMyAdmin [Y/n]: y
   - MySQL [Y/n]: y
   ```

2. **MySQL Mode Selection**:
   ```
   MySQL Configuration Mode:
   [1] Development Mode - Clean logs
   [2] Production Mode - Verbose logs
   ```

3. **Docker Cleanup** (optional):
   ```
   Clean up existing Docker containers? (y/N): y
   ```

**Result**: `docker-compose.yml` is generated with selected services!

**Usage**:
```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f

# Stop services
docker compose down
```

---

### Extending AbstractInit for New Webserver

Want to add support for a new webserver? Follow this pattern:

**1. Create Init Class**:
```php
<?php
namespace Gemvc\CLI\Commands;

use Gemvc\CLI\AbstractInit;

class InitNginx extends AbstractInit
{
    protected function getWebserverType(): string
    {
        return 'Nginx';
    }
    
    protected function getWebserverSpecificDirectories(): array
    {
        return ['nginx-config'];
    }
    
    protected function copyWebserverSpecificFiles(): void
    {
        // Copy Nginx-specific files
        // - nginx.conf
        // - Nginx composer.json
    }
    
    protected function getDefaultPort(): int
    {
        return 80;
    }
    
    protected function getStartCommand(): string
    {
        return 'nginx -g "daemon off;"';
    }
}
```

**2. Register in InitProject**:
```php
// Add to InitProject::WEBSERVER_OPTIONS
'3' => [
    'name' => 'Nginx',
    'class' => InitNginx::class,
    'package' => 'gemvc/nginx',
    'status' => 'available',
]
```

**3. Done!** You've added a new webserver using the Template Method pattern.

---

### How ProjectHelper Works

**Finding Project Root**:
```php
// ProjectHelper::rootDir()
// 1. Starts from current directory
// 2. Walks up directory tree
// 3. Looks for composer.lock file
// 4. Returns directory containing composer.lock
// 5. Caches result for performance
```

**Loading Environment**:
```php
// ProjectHelper::loadEnv()
// 1. Try root/.env first
// 2. Fallback to app/.env
// 3. Uses Symfony Dotenv for parsing
// 4. Throws exception if neither found
```

**Used By**:
- Database commands (find project root, load DB config)
- Code generation (find project root, determine paths)
- All commands that need environment variables

---

### Command Execution Flow

```
CLI: gemvc create:crud Product
    ↓
bin/gemvc parses: command='create:crud', args=['Product']
    ↓
CommandCategories::getCommandClass('create:crud')
    Returns: 'CreateCrud'
    ↓
new CreateCrud(['Product'])
    ↓
CreateCrud extends AbstractBaseCrudGenerator extends AbstractBaseGenerator extends Command
    ↓
CreateCrud::execute()
    ├─ Uses AbstractBaseGenerator::getTemplate()
    │   ├─ Checks: templates/cli/service.template (project root)
    │   └─ Fallback: vendor/.../templates/cli/service.template
    ├─ Uses AbstractBaseGenerator::replaceTemplateVariables()
    │   - Replaces: {$serviceName} → Product
    └─ Uses AbstractBaseGenerator::writeFile()
        └─ FileSystemManager::writeFile()
            └─ Asks for overwrite confirmation
```

---

### Key Architectural Benefits

1. **Code Reuse**: Common functionality in base classes
2. **Consistency**: All commands follow same patterns
3. **Extensibility**: Easy to add new commands or webservers
4. **Maintainability**: Changes in base classes affect all commands
5. **Testability**: Each command is isolated and testable

---

### Summary

**CLI Architecture**:
- ✅ **Command** - Base class for all commands
- ✅ **AbstractInit** - Template Method for project initialization
- ✅ **InitProject** - Orchestrator for webserver selection
- ✅ **InitApache/InitSwoole** - Strategy implementations
- ✅ **CommandCategories** - Command registry and mapping
- ✅ **FileSystemManager** - Centralized file operations
- ✅ **DockerComposeInit** - Docker services setup wizard
- ✅ **ProjectHelper** - Path resolution and environment loading

**Design Patterns**:
- Template Method (AbstractInit)
- Strategy (webserver selection)
- Orchestrator (InitProject)
- Factory (CommandCategories)
- Facade (FileSystemManager)

**Result**: Clean, maintainable, extensible CLI architecture! 🎯