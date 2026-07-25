# 🎨 GEMVC Customizable Template System

## Overview

GEMVC's template system allows developers to **customize code generation** while still benefiting from CLI commands. After initializing a project, templates are copied to your project root, giving you full control over generated code style and structure.

---

## 🔄 How It Works

### Step 1: Project Initialization (`gemvc init`)

When you run `gemvc init`, GEMVC automatically copies templates to your project:

```
vendor/gemvc/swoole/src/CLI/templates/
    ↓ (copied to)
{projectRoot}/templates/cli/
    ├── service.template
    ├── controller.template
    ├── model.template
    └── table.template
```

**Code Reference**: `AbstractInit::copyTemplatesFolder()` → `FileSystemManager::copyTemplatesFolder()`

---

### Step 2: Customize Templates (Optional)

After initialization, you can edit templates in your project root:

```bash
# Edit with your own coding style
vim templates/cli/service.template
vim templates/cli/controller.template
# etc.
```

**Why customize?**
- Match your team's coding standards
- Add custom helper methods
- Change code structure/patterns
- Add project-specific comments
- Integrate with your existing codebase patterns

---

### Step 3: Code Generation (`gemvc create:crud`)

When generating code, GEMVC uses a **smart template lookup**:

```php
// AbstractBaseGenerator::getTemplate()
1. First checks: {projectRoot}/templates/cli/{templateName}.template ✅ (Custom)
2. Fallback: vendor/gemvc/swoole/src/CLI/templates/cli/{templateName}.template (Default)
```

**Example**: Running `gemvc create:crud Product`
- Uses your custom `templates/cli/service.template` if it exists
- Falls back to vendor template if not found
- Replaces `{$serviceName}` → `Product`, `{$tableName}` → `products`
- Generates: `app/api/Product.php`, `app/controller/ProductController.php`, etc.

---

## 📝 Template Variables

Templates use placeholder variables that get replaced during generation:

| Variable | Description | Example Input | Example Output |
|----------|-------------|---------------|---------------|
| `{$serviceName}` | Class name (PascalCase) | `Product` | `Product` |
| `{$tableName}` | Database table name (snake_case) | `products` | `products` |
| `{$variableName}` | Custom variables (defined per generator) | Various | Various |

### Variable Replacement

**Code Reference**: `AbstractBaseGenerator::replaceTemplateVariables()`

```php
protected function replaceTemplateVariables(string $content, array $variables): string
{
    foreach ($variables as $key => $value) {
        $content = str_replace('{$' . $key . '}', $value, $content);
    }
    return $content;
}
```

---

## 📋 Available Templates

### 1. **service.template** - API Service Layer
- Generates: `app/api/{ServiceName}.php`
- Extends: `ApiService` or `SwooleApiService`
- Methods: `create()`, `read()`, `update()`, `delete()`, `list()`
- Includes: Schema validation, JWT auth, mock responses

**Variables**:
- `{$serviceName}` - Service class name

**Usage**: `gemvc create:service Product`

---

### 2. **controller.template** - Business Logic Layer
- Generates: `app/controller/{ServiceName}Controller.php`
- Extends: `Controller`
- Methods: `create()`, `read()`, `update()`, `delete()`, `list()`
- Includes: Model delegation, error handling

**Variables**:
- `{$serviceName}` - Controller class name

**Usage**: `gemvc create:controller Product`

---

### 3. **model.template** - Data Logic Layer
- Generates: `app/model/{ServiceName}Model.php`
- Extends: `{ServiceName}Table`
- Methods: `createModel()`, `readModel()`, `updateModel()`, `deleteModel()`
- Includes: CRUD operations, error handling

**Variables**:
- `{$serviceName}` - Model class name

**Usage**: `gemvc create:model Product`

---

### 4. **table.template** - Data Access Layer
- Generates: `app/table/{ServiceName}Table.php`
- Extends: `Table`
- Includes: Properties, schema definition, type mapping, query methods

**Variables**:
- `{$serviceName}` - Table class name
- `{$tableName}` - Database table name

**Usage**: `gemvc create:table Product`

---

## 🎯 Customization Examples

### Example 1: Add Custom Comments

**Original template** (`service.template`):
```php
class {$serviceName} extends ApiService
{
    public function create(): JsonResponse
    {
        // ... code
    }
}
```

**Customized template**:
```php
/**
 * {$serviceName} API Service
 * 
 * @custom-note This service handles all {$serviceName} operations
 * @team Backend Team
 * @last-updated 2024
 */
class {$serviceName} extends ApiService
{
    /**
     * Create new {$serviceName}
     * Custom implementation with additional logging
     */
    public function create(): JsonResponse
    {
        // Log the request
        error_log("Creating {$serviceName}: " . json_encode($this->request->post));
        
        // ... original code
    }
}
```

---

### Example 2: Change Code Structure

**Original**: Uses `mapPostToObject()` pattern

**Customized**: Use direct property assignment
```php
public function create(): JsonResponse
{
    $model = new {$serviceName}Model();
    $model->name = $this->request->post['name'] ?? '';
    $model->description = $this->request->post['description'] ?? '';
    
    return $model->createModel();
}
```

---

### Example 3: Add Helper Methods

```php
class {$serviceName}Controller extends Controller
{
    // ... standard CRUD methods ...
    
    /**
     * Custom helper method
     */
    private function validateBusinessRules({$serviceName}Model $model): bool
    {
        // Your custom validation logic
        return true;
    }
    
    /**
     * Custom bulk operation
     */
    public function bulkCreate(): JsonResponse
    {
        // Your bulk operation logic
    }
}
```

---

### Example 4: Integrate with Existing Patterns

If your team uses Repository pattern:
```php
class {$serviceName}Controller extends Controller
{
    private {$serviceName}Repository $repository;
    
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->repository = new {$serviceName}Repository();
    }
    
    public function create(): JsonResponse
    {
        return $this->repository->create($this->request->post);
    }
}
```

---

## 🔍 Template Lookup Flow

```
Developer runs: gemvc create:crud Product
    ↓
AbstractBaseGenerator::getTemplate('service')
    ↓
Check 1: {projectRoot}/templates/cli/service.template
    ├─ ✅ EXISTS → Use custom template
    └─ ❌ NOT FOUND → Continue to Check 2
        ↓
Check 2: vendor/gemvc/swoole/src/CLI/templates/cli/service.template
    ├─ ✅ EXISTS → Use default template (with warning)
    └─ ❌ NOT FOUND → Throw error
        ↓
Load template content
    ↓
AbstractBaseGenerator::replaceTemplateVariables()
    Replace: {$serviceName} → Product
    Replace: {$tableName} → products
    ↓
Write to: app/api/Product.php
```

---

## 💡 Best Practices

### 1. **Version Control Templates**
```bash
# Add templates to git
git add templates/cli/*.template
git commit -m "Customize code generation templates"
```

### 2. **Backup Default Templates**
```bash
# Before customizing, backup originals
cp templates/cli/service.template templates/cli/service.template.backup
```

### 3. **Document Custom Variables**
```php
/**
 * Template Variables:
 * - {$serviceName}: Product
 * - {$tableName}: products
 * - {$author}: John Doe (custom)
 */
```

### 4. **Test Template Changes**
```bash
# Generate test code before committing template changes
gemvc create:crud TestEntity
# Review generated code
# Adjust template if needed
```

### 5. **Use Template Inheritance**
Create base templates and extend them:
```bash
templates/cli/
├── base-service.template    # Common code
├── service.template         # Includes base + specifics
```

---

## 🛠️ Advanced: Custom Template Variables

You can extend generators to add custom variables:

```php
// In your custom generator class
protected function getTemplateVariables(): array
{
    return [
        'serviceName' => $this->serviceName,
        'tableName' => $this->tableName,
        'author' => get_current_user(),  // Custom variable
        'date' => date('Y-m-d'),         // Custom variable
    ];
}

// In template
/**
 * Generated by: {$author}
 * Date: {$date}
 */
class {$serviceName} extends ApiService
{
    // ...
}
```

---

## 📚 Template Structure Reference

### Default Templates Location
- **Vendor**: `vendor/gemvc/swoole/src/CLI/templates/cli/`
- **Project**: `{projectRoot}/templates/cli/`

### Template File Names
- `service.template` - API service layer
- `controller.template` - Controller layer
- `model.template` - Model layer
- `table.template` - Table/data access layer

### Template File Format
- PHP code with placeholder variables
- Variables use `{$variableName}` syntax
- Supports any valid PHP code structure

---

## 🎨 Example: Complete Custom Workflow

```bash
# 1. Initialize project
gemvc init
# Select: Apache

# 2. Review default templates
cat templates/cli/service.template

# 3. Customize templates for your team
vim templates/cli/service.template
# Add custom comments, change structure, etc.

# 4. Commit templates to version control
git add templates/cli/
git commit -m "Customize code generation templates"

# 5. Generate code using custom templates
gemvc create:crud Product
# Generated code matches your custom template!

# 6. Team members get same code style
git pull
gemvc create:crud Category
# Uses same custom templates!
```

---

## 🚨 Troubleshooting

### Template Not Found Error
```
Template not found: service (checked: /path/to/templates/cli/service.template, /vendor/...)
```

**Solution**: 
- Ensure templates were copied during `gemvc init`
- Check `templates/cli/` directory exists
- Verify template file names match exactly

### Using Vendor Template (Warning)
```
Warning: Using vendor template for service - consider copying templates to project root
```

**Solution**: Templates in project root take priority. If you see this warning, your custom templates aren't being used. Check:
- File path: `templates/cli/service.template`
- File permissions
- Template file exists

### Variable Not Replaced
If `{$serviceName}` appears in generated code:

**Solution**: 
- Check variable name spelling (case-sensitive)
- Ensure generator calls `replaceTemplateVariables()`
- Verify variable name matches template placeholder

---

## 📖 Summary

**Key Benefits**:
- ✅ **Customize code style** per project/team
- ✅ **Maintain consistency** across generated code
- ✅ **Version control** your templates
- ✅ **Easy updates** - just edit template files
- ✅ **Team alignment** - shared templates = shared style

**Template Priority**:
1. **Project root** templates (custom) ← Highest priority
2. **Vendor** templates (default) ← Fallback

**Result**: Write code generation templates once, generate consistent code forever! 🎉

