# 🎮 Arcade & PlayStation Management System (Backend API)

Enterprise-grade, Multi-Tenant Backend API developed with **Laravel 12** and **PHP 8.2+**. This system is specifically architected to handle high-concurrency, real-time tracking, and multi-tenant isolation required for scaling PlayStation and Arcade lounge networks.

---

## 🚀 Tech Stack & Core Dependencies

The system leverages a bleeding-edge backend ecosystem optimized for micro-latency, solid data auditing, and dynamic query filtering:

* **Core Framework:** `Laravel ^12.0` & `PHP ^8.2` (Utilizing the latest PHP 8+ Declarative Attributes, Enums, and Performance optimization).
* **Authentication & Security:** `Laravel Sanctum ^4.0` (Stateless, token-based API authentication securely guarded via `auth:sanctum`).
* **Real-time Infrastructure:** `Laravel/Reverb ^1.0` + `Laravel Echo` (High-performance, pure PHP WebSocket server for asynchronous bidirectional broadcasting).
* **Access Control:** `Spatie Laravel Permission ^6.21` (Granular Role-Based Access Control - RBAC).
* **Audit Trail:** `Spatie Laravel Activity Log ^4.10` (Automated tracking of critical application state changes like session updates, pause/resume lifecycles, and financial workflows).
* **API Query Hydration:** `Spatie Laravel Query Builder ^6.3` (Declarative on-demand filtering, sorting, and relationship inclusion directly from frontend URI query strings).

---

## 🛠️ System Architecture & Multi-Tenancy Flow

The platform implements an advanced **Multi-Database Tenant Isolation** strategy to guarantee 100% data safety, security, and effortless horizontal scaling.

```text
                       ┌─────────────────────────┐
                       │   Main Database (mysql) │
                       │  (Users, Tenants, Auth) │
                       └────────────┬────────────┘
                                    │  Auth Success
                                    ▼
                     ┌─────────────────────────────┐
                     │      Tenant Middleware      │
                     │  Dynamically Purges & Alters│
                     │    Default Connection       │
                     └──────────────┬──────────────┘
                                    │
            ┌───────────────────────┼───────────────────────┐
            ▼                       ▼                       ▼
┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────────┐
│  Tenant DB: Arcade A  │ │  Tenant DB: Arcade B  │ │  Tenant DB: Arcade C  │
│ (Devices, Orders, DL) │ │ (Devices, Orders, DL) │ │ (Devices, Orders, DL) │
└───────────────────────┘ └───────────────────────┘ └───────────────────────┘
- Authentication Phase: The client authenticates against the central mysql database where the global users and tokens reside.

- Context Switching Phase: Upon successful - - authentication, the custom TenantMiddleware extracts the tenant's database credentials (database_name, database_username, database_password) from the logged-in user record.

- Connection Re-hydration: The middleware dynamically alters Laravel's runtime config, purges the existing tenant connection buffer, and forces the application to route subsequent operations into that tenant's dedicated isolated schema.

⚡ Key Architectural Challenges & Solutions (المشاكل التي تم حلها)
1. Database Multitenancy & Schema Separation
The Problem: Traditional single-database architectures risk data leakage between different arcade lounges and suffer major performance degradation as records scale into the millions.

The Solution: Implemented strict structural separation via a dual-migration directory system (database/migrations/Main vs database/migrations/Tenant). The core engine shifts connections dynamically during the lifecycle of a single HTTP request, shielding tenant data in completely isolated databases.

2. High-Concurrency Real-Time Timer Monitoring
The Problem: In arcade management, thousands of seconds pass across active gaming rooms. Relying on frontend polling (setInterval) triggers an avalanche of server requests, while long-polling lags critical countdown notifications.

The Solution: Integrated Laravel Reverb WebSocket server to push state changes down to the frontend using native PHP generators via asynchronous events (ShouldBroadcastNow). The dashboard receives device-expire-time and booked-device-change-status notifications immediately with zero frontend polling overhead.

3. Server Memory Exhaustion during Massive Audit Logs & Pruning
The Problem: Game sessions generate huge volumes of logs, intervals, and notifications. Querying and deleting thousands of historical logs via standard Eloquent causes PHP memory exhaustion (Fatal error: Allowed memory size exhausted).

The Solution: Utilized Laravel 12's MassPrunable traits on high-volume analytical logging models. Pruning queries are compiled into singular, native database commands (DELETE FROM ... WHERE) executed directly by the DB engine, achieving near-zero RAM consumption and thousands-of-times faster execution.

4. Mathematical Precision in Concurrent Shifts & Billing
The Problem: Tracking gaming timers with pauses, switches, multi-device groupings, and overlapping internal café orders frequently yields precision drift and billing rounding errors.

-The Solution: Implemented atomic transaction operations using saveOrFail() and specialized mathematical service layers (DeviceTimerService, DailyService). Active shifts (Dailies) strictly enforce single-open state policies: a daily shift cannot be closed if there are active or paused timers hanging in the database, protecting owner profit from employee gaps.

💻 Getting Started for Developers
Clone the repository and install PHP dependencies:
```
composer install

Setup your local Environment (.env) matching your central database, then run the primary migration:
```
php artisan migrate --path=database/migrations/Main
```
Boot up the high-performance WebSocket broadcasting server:
```
php artisan reverb:start --port=8080
```
Run the background queue worker for system tasks:
```
php artisan queue:work
```
