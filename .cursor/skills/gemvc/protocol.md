# Mandatory Source Code Ingestion & Deep Learning Protocol for GEMVC

You are strictly prohibited from guessing, hallucinating, or relying on generic PHP framework knowledge (like Laravel or Symfony) when working on GEMVC. You must master the actual source code and internal design of GEMVC.

## 1. Mandatory Codebase Scanning Sequence

Before answering any architectural question, writing any code, or suggesting any refactoring, you MUST examine the following directories in this repository:

**The Critical `vendor/gemvc` Directory:** (Very Important!) Thoroughly examine all packages, libraries, and GEMVC-related source code installed and located in this path to fully learn the actual structure and implementation of the packages.

**Core Engine & Architecture:** `src/` (Examine all layers: API, Controller, Model, Table, and bootstrap loaders for Apache, Nginx, and OpenSwoole).

**In-House Ecosystem Packages:** Check any package directories or similar structures (http-client, apm-contracts, apm-tracekit, connection adapters, CLI).

**Internal AI Guidelines & Docs:** `docs/ai/` and `docs/guides/` to understand the framework's canonical invariants.

## 2. Zero-Assumption Rule

Do not assume standard framework patterns. GEMVC uses Automatic Routing (No Route Files), a strict 4-layer architecture, an in-house small ORM, and automatic internal API documentation generation without Swagger.

Before proposing any modifications, verify the actual implementation in the source code (`src/` and `vendor/gemvc`).

## 3. Strict Quality & Type Constraints (PHPStan Level 9)

Every single line of code you write, review, or suggest must strictly comply with PHPStan Level 9.

Never weaken type hints, never use generic `mixed` types where specific types can be applied, and do not bypass checks with unprincipled ignores.

## 4. Execution Command

Acknowledge this protocol. Confirm that you have examined the source codes located in `vendor/gemvc` and `src/`, understand the server-agnostic nature (Apache / Nginx / OpenSwoole), and are ready to analyze or improve GEMVC strictly based on the actual source code.

---

## Local map (this repo)

This repository is **`gemvc/library`** — engine lives in `src/`. Ecosystem packages are installed under `vendor/gemvc/` (there is no top-level `packages/` directory here).

| Path | Contents |
|------|----------|
| `vendor/gemvc/helper` | TypeChecker, CryptHelper, ProjectHelper, … |
| `vendor/gemvc/http-client` | HttpClient, AsyncHttpClient (`fireAndForget`) |
| `vendor/gemvc/apm-contracts` | ApmInterface, ApmFactory |
| `vendor/gemvc/apm-tracekit` | TraceKit provider |
| `vendor/gemvc/connection-contracts` | DB connection interfaces |
| `vendor/gemvc/connection-pdo` | PDO (Apache/Nginx/CLI) |
| `vendor/gemvc/connection-openswoole` | OpenSwoole pools |
| `vendor/gemvc/cli-base` | CLI Command foundation |
| `vendor/gemvc/cli-dev` | `create:*`, admin/*, extended db:* |
| `src/core` | Bootstrap, SwooleBootstrap, ApiService, Controller, Security, docs |
| `src/http` | Request, Response, JWT, adapters |
| `src/database` | Table ORM, Schema, dialects, query |
| `src/CLI` | init, db:migrate |
| `src/startup` | apache / nginx / swoole scaffolds |
