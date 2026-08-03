# GEMVC 4-layer examples (ViewTable stack)

Copy these into your app’s `app/` folders. They are **documentation samples**, not autoloaded by the library.

Every PHP file has **inline comments** explaining *why* each piece exists (layers, schemas, money, views, lists, APM).

```
docs/examples/
├── api/           → app/api/            (LAYER 1 — public contract)
├── controller/    → app/controller/     (LAYER 2 — orchestration)
├── model/         → app/model/          (LAYER 3 — business rules)
└── table/         → app/table/          (LAYER 4 — DB / VIEW)
```

| Layer | Auth (public) | User | Order | UserOrderSummary (VIEW) |
|-------|---------------|------|-------|-------------------------|
| API | `api/Auth.php` | `api/User.php` | `api/Order.php` | `api/UserOrderSummary.php` |
| Controller | `controller/AuthController.php` | `controller/UserController.php` | `controller/OrderController.php` | `controller/UserOrderSummaryController.php` |
| Model | *(uses `UserModel`)* | `model/UserModel.php` | `model/OrderModel.php` | `model/UserOrderSummaryModel.php` |
| Table | — | `table/UserTable.php` | `table/OrderTable.php` | `table/UserOrderSummaryTable.php` |

## Request flow (remember this)

```
HTTP → API (schema/auth/list allowlists)
     → Controller (mapPostToObject / createList / createModel)
     → Model (rules, JsonResponse)
     → Table or ViewTable (SQL)
```

## Migrate

```bash
gemvc db:migrate UserTable
gemvc db:migrate OrderTable
gemvc db:migrate UserOrderSummaryTable
# or:
gemvc db:migrate --all
```

## Notes

- **Auth** (`/api/Auth/register`, `/api/Auth/login`) is **public** (`ApiService`) with **`requireRateLimit(1, 'ip')`** — 1 request/second per IP; register forces `role=user`; login returns JWT access/refresh/login via `UserModel`.
- **Own orders:** `GET /api/Order/myOrders` and `GET /api/Order/myOrder/?id=` — scoped to JWT `user_id` only (not client-supplied). Admin uses `list` / `listByUser`.
- **UserOrderSummary** is read-only (`read` / `list` / `topSpenders`). Writes go through **User** and **Order**.
- Order money uses `decimal` as a **string** (`decimal:12,2` in schema) — never `float`.
- Lists use API `findable` / `filterable` / `sortable` + Controller `createList`.
- Prefer `callController()` + `createModel()` for APM.
- Nest 1:n (`$_recent_orders`) in the **Model**, not in the SQL VIEW.

Guide: [database.md — ViewTable](../guides/database.md#sql-views-via-viewtable-recommended)
