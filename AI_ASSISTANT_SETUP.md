# GEMVC AI Assistant Setup Guide

This directory contains comprehensive documentation for AI assistants (Cursor, GitHub Copilot, etc.) to understand the GEMVC framework.

## 🤖 For AI Assistants

**AI Assistants: Read this file first!**
- **`QUICK_START_AI.md`** - Master instructions for AI assistants to read ALL documentation files

**For users**: Visit gemvc.de website for installation instructions

## 📁 Files Created

### For Cursor AI (Primary)
- **`.cursorrules`** (Root) - Main AI context file automatically loaded by Cursor
  - Comprehensive framework rules
  - Code patterns and examples
  - DO's and DON'Ts
  - Key class references

### For GitHub Copilot
- **`COPILOT_INSTRUCTIONS.md`** (Root) - Simple instructions for Copilot
  - Quick reference
  - Essential patterns
  - Common mistakes to avoid

- **`GEMVC_PHPDOC_REFERENCE.php`** (Root) - PHPDoc annotations
  - Proper type hints
  - Method signatures
  - Copilot-friendly documentation

### For AI Assistants (MASTER FILE)
- **`QUICK_START_AI.md`** (Root) - MASTER INSTRUCTIONS for AI assistants
  - Read this file FIRST to understand your role
  - Forces AI to read ALL documentation files
  - Complete architecture understanding
  - Code generation patterns
  - Critical rules and conventions

- **`GEMVC_GUIDE.md`** (Root) - Concise guide
  - Quick start patterns
  - Code generation examples
  - CLI commands reference

- **`AI_CONTEXT.md`** - Quick reference guide
  - Fast lookup
  - Key patterns
  - Common tasks

- **`AI_API_REFERENCE.md`** - Complete API documentation
  - Full class reference
  - Method signatures
  - Parameters and return types

- **`gemvc-api-reference.jsonc`** - Structured data
  - Machine-readable
  - Programmatic access
  - Framework metadata

- **`GEMVC_DOCUMENTATION_DIRECTIVES.md`** - Documentation generator guide
  - PHPDoc directives reference
  - Auto-documentation system
  - How to create beautiful API docs

- **`GEMVC_APM_INTEGRATION.md`** - APM Integration Guide
  - Complete APM integration documentation
  - Automatic tracing setup
  - Controller and database query tracing
  - Environment variable configuration
  - Custom tracing patterns
  - Performance optimization

## 🎯 How AI Assistants Use These Files

### Cursor AI
1. Automatically loads `.cursorrules` from root
2. Uses detailed patterns and rules
3. References examples when generating code
4. **Primary file**: `.cursorrules`

### GitHub Copilot
1. Reads markdown files in root (`.md` files)
2. Uses PHPDoc annotations in code
3. References `COPILOT_INSTRUCTIONS.md` for quick guidance
4. Uses `GEMVC_PHPDOC_REFERENCE.php` for type hints
5. **Primary files**: `GEMVC_GUIDE.md`, `COPILOT_INSTRUCTIONS.md`

### Other AI Tools
1. Read all markdown files in root
2. Parse JSONC for structured data
3. Use PHPDoc for code understanding
4. **Primary files**: `AI_CONTEXT.md`, `AI_API_REFERENCE.md`

## 🚀 Best Practices

### File Organization
```
project-root/
├── .cursorrules                    ← Cursor AI (primary)
├── GEMVC_GUIDE.md                  ← GitHub Copilot (primary)
├── COPILOT_INSTRUCTIONS.md         ← GitHub Copilot
├── GEMVC_PHPDOC_REFERENCE.php      ← AI type hints
├── AI_CONTEXT.md                   ← Quick reference
├── AI_API_REFERENCE.md             ← Complete API docs
├── GEMVC_DOCUMENTATION_DIRECTIVES.md ← Documentation guide
└── gemvc-api-reference.jsonc       ← Structured data
```

### What Each File Contains

#### `.cursorrules`
- **Audience**: Cursor AI
- **Format**: Markdown rules
- **Content**: Comprehensive framework understanding
- **Usage**: Automatically loaded

#### `COPILOT_INSTRUCTIONS.md`
- **Audience**: GitHub Copilot
- **Format**: Markdown
- **Content**: Quick instructions and patterns
- **Usage**: Read when coding

#### `GEMVC_PHPDOC_REFERENCE.php`
- **Audience**: All AI (especially Copilot)
- **Format**: PHPDoc annotations
- **Content**: Type hints and method signatures
- **Usage**: Provides code completion hints

#### `GEMVC_GUIDE.md`
- **Audience**: GitHub Copilot, All AI
- **Format**: Markdown
- **Content**: Concise patterns, CLI commands
- **Usage**: Quick reference for code generation

#### `AI_CONTEXT.md`
- **Audience**: All AI assistants
- **Format**: Markdown
- **Content**: Framework overview and patterns
- **Usage**: General reference

#### `AI_API_REFERENCE.md`
- **Audience**: All AI assistants
- **Format**: Markdown
- **Content**: Complete API documentation
- **Usage**: Detailed method reference

#### `gemvc-api-reference.jsonc`
- **Audience**: Programmatic tools
- **Format**: JSON with comments
- **Content**: Structured framework data
- **Usage**: Code generation tools

#### `GEMVC_DOCUMENTATION_DIRECTIVES.md`
- **Audience**: All AI assistants
- **Format**: Markdown
- **Content**: Documentation generator directives, PHPDoc patterns
- **Usage**: Learn how to generate beautiful API documentation

## 🎓 Key Benefits

### For AI Assistants
✅ Understand framework architecture
✅ Generate correct code patterns
✅ Avoid common mistakes
✅ Use proper type hints
✅ Follow security best practices
✅ Create 4-layer architecture correctly

### For Developers
✅ Consistent code generation
✅ Type-safe code (PHPStan Level 9)
✅ Security built-in (90% automatic)
✅ Server-agnostic code
✅ Less boilerplate

## 📊 File Usage Matrix

| File | Cursor | Copilot | Other AI | Purpose |
|------|--------|---------|----------|---------|
| `.cursorrules` | ✅ Primary | ⚠️ May read | ⚠️ May read | Comprehensive rules |
| `GEMVC_GUIDE.md` | ⚠️ May read | ✅ Primary | ⚠️ May read | Code generation patterns |
| `COPILOT_INSTRUCTIONS.md` | ⚠️ May read | ✅ Reference | ⚠️ May read | Quick instructions |
| `GEMVC_PHPDOC_REFERENCE.php` | ⚠️ May use | ✅ Type hints | ⚠️ May use | PHPDoc annotations |
| `AI_CONTEXT.md` | ✅ Reference | ✅ Reference | ✅ Reference | Quick reference |
| `AI_API_REFERENCE.md` | ✅ Reference | ✅ Reference | ✅ Reference | API docs |
| `gemvc-api-reference.jsonc` | ⚠️ May parse | ⚠️ May parse | ⚠️ May parse | Structured data |

## 🛠️ Usage Instructions

### For Cursor Users
1. `.cursorrules` is automatically loaded
2. AI will use it for code completion
3. Follow the rules when generating code

### For GitHub Copilot Users
1. Copilot reads `GEMVC_GUIDE.md` (primary)
2. Uses `GEMVC_PHPDOC_REFERENCE.php` for type hints
3. References `COPILOT_INSTRUCTIONS.md` for additional guidance

### For Other AI Tools
1. Read all markdown files
2. Parse JSONC for structured data
3. Use PHPDoc for type hints

## 🎯 Summary

**For Cursor AI**: Use `.cursorrules` (primary)
**For GitHub Copilot**: Use `GEMVC_GUIDE.md` + `GEMVC_PHPDOC_REFERENCE.php` (primary)
**For All AI**: Read markdown files for reference

All files work together to provide comprehensive GEMVC framework understanding for AI assistants!

