# Arcade & PlayStation Management System Backend API

Multi-tenant backend API for managing arcade and PlayStation lounges. The project is built with Laravel 12 and PHP 8.2+, and focuses on tenant data isolation, timer/session workflows, orders, expenses, reports, notifications, and role-based access control.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum for API authentication
- Laravel Reverb and Echo for real-time events
- Spatie Laravel Permission for roles and permissions
- Spatie Laravel Activitylog for audit logs
- Spatie Laravel Query Builder for filtering and sorting API resources
- PHPUnit for automated testing
- Vite for frontend asset tooling

## Main Features

- Multi-database tenant isolation.
- User authentication with token-based access.
- Role and permission based authorization.
- Device, device type, and device time management.
- Individual and group timer sessions.
- Pause, resume, finish, transfer, and change-time timer operations.
- Daily shift management with financial checks before closing.
- Orders, order items, products, expenses, and maintenance records.
- Financial reports and daily status reports.
- Notifications and real-time timer status broadcasting.
- Activity logging with batch support for related operations.

## Architecture Overview

The system uses a central main database for global users, authentication, tokens, and tenant connection metadata. After authentication, the `TenantMiddleware` switches the request context to the authenticated user's tenant database.

```text
Client
  |
  | login
  v
Main Database
  - users
  - roles and permissions
  - personal access tokens
  - tenant connection metadata
  |
  | authenticated API request
  v
TenantMiddleware
  - validates authenticated user
  - configures tenant connection
  - sets tenant as default connection
  |
  v
Tenant Database
  - devices
  - timers and sessions
  - orders and products
  - expenses
  - dailies
  - reports
  - notifications
  - activity logs
```

Migration files are split into:

- `database/migrations/Main` for the central database.
- `database/migrations/Tenant` for tenant-specific databases.

## API Entry Points

Base prefix:

```text
/api/v1/admin
```

Public routes:

- `POST /api/v1/admin/auth/login`
- `POST /api/v1/admin/forgot-password/send-code`
- `POST /api/v1/admin/forgot-password/verify-code`
- `POST /api/v1/admin/forgot-password/change-password`

Protected routes use:

- `set_locale`
- `auth:sanctum`
- `tenant`
- `throttle:api`

Main protected resources include:

- `/users`
- `/profile`
- `/devices`
- `/timers`
- `/device-types`
- `/device-times`
- `/device-timer`
- `/sessions`
- `/orders`
- `/products`
- `/expenses`
- `/dailies`
- `/reports`
- `/notifications`
- `/media`
- `/maintenances`
- `/parameter/params`
- `/selects`

## Local Setup

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the app key:

```bash
php artisan key:generate
```

Configure the main database and tenant database defaults in `.env`.

Run the main database migrations:

```bash
php artisan migrate --path=database/migrations/Main
```

Run tenant migrations for the required tenant database using the project's tenant migration command or by pointing Laravel to the tenant connection as configured in the codebase.

## Development Commands

Start the Laravel development server:

```bash
php artisan serve
```

Start the queue worker:

```bash
php artisan queue:work
```

Start Reverb for real-time broadcasting:

```bash
php artisan reverb:start --port=8080
```

Start Vite:

```bash
npm run dev
```

The project also includes a combined Composer script:

```bash
composer run dev
```

## Testing

Run the full test suite:

```bash
php artisan test
```

The current tests cover important flows such as:

- Daily shift open and close rules.
- Timer start, pause, resume, and finish behavior.
- Device timer cost calculation.
- Order creation and total price calculation.
- Tenant isolation.
- Permission restrictions.
- Reports.
- API response safety for internal server errors.

## Security Notes

- API authentication is handled with Laravel Sanctum.
- Authorization is handled through Spatie roles and permissions.
- Tenant data is isolated by switching the active database connection per authenticated request.
- Internal server errors are sanitized through `ApiResponse::error()` so exception details are not returned to API clients.
- Sensitive local files such as `.env` are ignored by Git.

## Documentation

Additional project documentation is available in:

- `docs/`
- `docs/Testing_Guide.md`
- `docs/LogBatch_Usage.md`
- `docs/LogBatch_Implementation_Summary.md`
- `docs/BatchedActivity_Routes.md`
- `docs/SQL_Queries.md`

Some older documentation files may still need encoding cleanup. The root README is intentionally kept in plain UTF-8 compatible Markdown with simple ASCII text to avoid terminal and editor encoding issues.
