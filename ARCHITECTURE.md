# The Architecture of GEMVC

> **Version:** 5.15.0 (philosophy document)
>
> This document defines the architectural philosophy behind GEMVC.
>
> It does not explain how to use the framework.
>
> It explains **why** the framework exists, **which assumptions it makes**, and **how every major architectural decision follows from those assumptions**.
>
> API documentation explains *how*.
>
> This document explains *why*.
>
> How-to / request flow: [`docs/guides/architecture.md`](docs/guides/architecture.md)

---

# Executive Summary

```mermaid
flowchart TD
    Goal["Maintainable Backend Microservices"]
    Goal --> Microservice["Microservice First"]
    Goal --> Consistency["Architectural Consistency"]
    Microservice --> Database["One Service → One Database"]
    Database --> Runtime["Runtime Independence"]
    Runtime --> Contracts["Contracts"]
    Contracts --> Components["Small Focused Components"]
    Components --> Layers["Four Layer Architecture"]
    Layers --> Security["Security by Principle"]
    Security --> Maintainability["Long-term Maintainability"]
```

GEMVC is an opinionated backend framework.

It intentionally optimizes for a single architectural style:

- Backend Microservices
- One Service owns one Database
- Explicit Layer Separation
- Runtime Independence
- Security by Principle
- Small Focused Components

Rather than maximizing flexibility, GEMVC maximizes architectural consistency.

Every subsystem inside the framework exists because of these principles.

---

# Introduction

Every software framework begins with assumptions.

Some frameworks attempt to support every architectural style.

Others deliberately optimize for one.

GEMVC belongs to the second category.

It is an opinionated PHP framework designed exclusively for backend microservices.

Its architecture is built around carefully selected constraints rather than unlimited flexibility.

These constraints simplify software architecture by removing decisions that every project would otherwise need to make independently.

This document describes those architectural assumptions.

---

# The Architectural Vision

```mermaid
flowchart LR
    General["General Purpose Framework"] --> Flexibility
    Flexibility --> ManyArchitectures["Many Architectural Styles"]
    Opinionated["Opinionated Framework"] --> Consistency
    Consistency --> GEMVC
    GEMVC --> Backend["Backend Microservices"]
```

General-purpose frameworks maximize flexibility.

Opinionated frameworks maximize consistency.

Neither philosophy is universally correct.

GEMVC intentionally chooses consistency.

It is not designed to solve every backend problem.

It is designed to solve one problem exceptionally well:

> **Building maintainable backend microservices.**

---

# Core Philosophy

```mermaid
flowchart TD
    Goal["Maintainable Backend Microservices"]
    Goal --> P1["Microservice First"]
    Goal --> P2["Opinionated Architecture"]
    Goal --> P3["Architectural Constraints"]
    P1 --> Runtime
    P1 --> Database
    P2 --> Layers
    P2 --> Security
    P3 --> ViewTable
    P3 --> SmallORM
    P3 --> Contracts
```

Every major architectural decision inside GEMVC originates from a single question:

> **If a framework existed exclusively for backend microservices, what architecture would naturally emerge?**

Everything else follows from that answer.

---

# Architectural Principles

---

# Principle 1 — Microservice First

```mermaid
flowchart TD
    Application --> Service --> API --> BusinessLogic["Business Logic"] --> Database
```

Every GEMVC application is assumed to be an independent backend microservice.

The framework intentionally does **not** optimize for:

- Monolithic applications
- Full-stack frameworks
- Template rendering
- CMS platforms
- Traditional MVC websites

This assumption simplifies the entire architecture.

---

# Principle 2 — One Service Owns One Database

```mermaid
flowchart LR
    UserService["User Service"] --> UserDB[(User Database)]
    OrderService["Order Service"] --> OrderDB[(Order Database)]
    PaymentService["Payment Service"] --> PaymentDB[(Payment Database)]
```

Each service owns exactly one database.

This is an architectural assumption.

Not a technical limitation.

This principle provides:

- Clear ownership
- Simpler runtime
- Simpler connection management
- Predictable migrations
- Independent deployment
- Strong service boundaries

---

# Principle 3 — Public Service Contracts

```mermaid
flowchart TD
    Frontend --> API --> Controller --> Model --> Table --> Database
```

Frontend applications communicate only with Services.

Controllers, Models and Tables are internal implementation details.

The API layer defines the public service contract.

Everything behind the API layer may evolve independently.

This separation protects clients from internal refactoring.

---

# Principle 4 — Clear Layer Responsibilities

```mermaid
flowchart TD
    Client --> API --> Controller --> Model --> Table --> Database
```

| Layer | Responsibility |
|---------|----------------|
| API | Public Service Contract |
| Controller | Use Case Orchestration |
| Model | Business Logic |
| Table | Persistence |

Each layer has exactly one responsibility.

Responsibilities never overlap.

---

# Principle 5 — Runtime Independence

```mermaid
flowchart TD
    Application --> Contracts
    Contracts --> RuntimeImpl["Runtime Implementation"]
    RuntimeImpl --> PHPFPM["PHP-FPM / Apache / Nginx"]
    RuntimeImpl --> OpenSwoole
    RuntimeImpl --> FrankenPHP
```

Business code never depends on runtime implementations.

Application code depends only on contracts.

Runtime packages implement those contracts.

Changing the runtime should not require application changes.

FrankenPHP: classic mode by default (`StandardHttpRequest` + `Bootstrap` + PDO); worker mode is opt-in. Edge path security lives in the **Caddyfile** (not `.htaccess`). See [docs/guides/frankenphp.md](docs/guides/frankenphp.md).

---

# Principle 6 — Security by Principle

```mermaid
flowchart TD
    HTTPRequest["HTTP Request"] --> APIBoundary["API Boundary"]
    APIBoundary --> Authentication
    Authentication --> Authorization
    Authorization --> Validation
    Validation --> RateLimiting["Rate Limiting"]
    RateLimiting --> Controller --> Model
```

Security belongs at the service boundary.

Whenever possible:

- Authentication
- Authorization
- Validation
- Rate Limiting

should execute before business logic.

Secure behaviour should be the default (`ProtectedApiService` / `requireAuth()`, schema validation, rate-limit drivers).

---

# Principle 7 — Explicit Architecture

```mermaid
flowchart LR
    Developer --> Code --> Architecture --> Behaviour
```

Hidden behaviour increases complexity.

GEMVC intentionally favors explicit architecture over excessive framework magic.

Developers should understand application behaviour by reading the source code.

---

# Principle 8 — Small Focused Components

```mermaid
flowchart TD
    GEMVC --> Runtime
    GEMVC --> Database
    GEMVC --> Security
    GEMVC --> HTTP
    GEMVC --> CLI
    GEMVC --> Templates
    GEMVC --> APM
    GEMVC --> Contracts
    GEMVC --> ViewTable
```

Every component solves one problem.

Large abstractions are replaced with focused packages.

Examples include:

- Connection Contracts
- Runtime Implementations
- HTTP Client
- APM
- CLI
- CRUD Generator
- Templates

---

# Runtime Architecture

```mermaid
flowchart TD
    Application --> ConnectionContract["Connection Contract"]
    ConnectionContract --> RuntimePackage["Runtime Package"]
    RuntimePackage --> ConnectionPdo["connection-pdo"]
    RuntimePackage --> ConnectionSwoole["connection-openswoole"]
    RuntimePackage --> FutureRuntime["future-runtime"]
```

The runtime decides **how** connections are managed.

The application never does.

PHP-FPM may use a single reusable PDO connection.

OpenSwoole may use a real coroutine-aware connection pool.

Application code remains identical.

---

# Connection Philosophy

```mermaid
flowchart TD
    OneService["One Service"] --> OneDatabase["One Database"]
    OneDatabase --> Coordinator["One Connection Coordinator"]
    Coordinator --> Runtime
    Runtime --> PDO
    Runtime --> OpenSwoolePool["OpenSwoole Pool"]
```

Connection management follows architectural assumptions.

The framework creates one connection coordinator per application.

The runtime implementation decides whether that coordinator manages:

- a single reusable connection
- or a connection pool

This design keeps application code independent from infrastructure.

---

# Design Constraints

```mermaid
flowchart TD
    Constraints --> BackendOnly["Backend Only"]
    Constraints --> SmallServices["Small Services"]
    Constraints --> SmallORM["Small ORM"]
    Constraints --> ReadModels["Read Models"]
    Constraints --> RuntimeIndependence["Runtime Independence"]
```

GEMVC intentionally accepts constraints.

These constraints reduce complexity.

## Backend Only

The framework does not include frontend rendering.

## Small Services

Large services should be divided into smaller services.

## Small ORM

Persistence should remain simple.

The ORM exists to map data.

Not to become a complete object graph framework.

## Read Models

Database Views belong to ViewTable.

Read operations and write operations are intentionally separated.

---

# Architectural Cause and Effect

```mermaid
flowchart TD
    MicroserviceFirst["Microservice First"] --> OneDatabase["One Database"]
    OneDatabase --> SimpleRuntime["Simple Runtime"]
    SimpleRuntime --> SimpleConnections["Simple Connections"]
    SimpleConnections --> SmallORM["Small ORM"]
    SmallORM --> ViewTable
    ViewTable --> FastCRUD["Fast CRUD Generation"]
    FastCRUD --> Predictable["Predictable Architecture"]
    Predictable --> Maintainability["Long-term Maintainability"]
```

This diagram summarizes the philosophy of GEMVC.

Most architectural decisions are not independent features.

They are consequences of earlier architectural assumptions.

---

# Architecture Before Features

```mermaid
flowchart TD
    Architecture --> Principles --> Decisions --> Features --> Implementation
```

Features do not define GEMVC.

Architecture defines GEMVC.

Features may evolve.

Architectural principles should remain stable.

Before introducing any new feature, one question should always be asked:

> **Does this strengthen the architectural philosophy of GEMVC?**

If the answer is no, the feature should be reconsidered.

---

# Conclusion

```mermaid
flowchart TD
    Philosophy --> Architecture --> Framework --> Application --> Goal["Maintainable Backend Microservices"]
```

GEMVC is not a general-purpose PHP framework.

It is an opinionated architecture for backend microservices.

Every major design decision—including the four-layer architecture, runtime abstraction, contracts, connection management, ViewTable, the lightweight ORM, and Security by Principle—originates from a single architectural philosophy.

Understanding these architectural assumptions is more important than understanding any individual feature.

Everything else in the framework is simply a consequence of them.
