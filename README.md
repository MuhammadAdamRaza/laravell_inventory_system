# Inventory Management System

A Laravel and Vue application for inventory, purchasing, warehouse operations, sales, returns, notifications, and reporting.

## Technology

- Laravel 13 and PHP 8.3+
- Vue 3, Vue Router, Pinia, Vite, and Tailwind CSS 4
- MySQL or SQLite for application data
- Redis for the queue and cache in the supplied development configuration
- Pest/PHPUnit feature and unit tests

## Main modules

- User authentication, password reset, roles, and permissions
- Products, categories, suppliers, warehouses, stock balances, and movement history
- Purchase orders, approval, receiving, and supplier payments
- Sales orders and customer records
- Warehouse transfers and stock adjustments
- Customer returns and restocking
- Dashboards, reports, CSV exports, and notifications

## Role overview

- **Administrator:** full system access, including users, roles, system settings, reports, and failed jobs.
- **Manager:** operational catalog, purchasing, sales, stock, transfers, returns, and reports. Managers cannot manage users/roles or system settings, and cannot approve their own purchases.
- **Warehouse Staff:** stock and fulfilment actions limited to assigned warehouse(s); can receive eligible purchases, confirm eligible sales orders, dispatch/receive assigned transfers, and complete eligible returns.
- **Viewer:** read-only access to permitted screens. No create, edit, approval, stock-changing, cancellation, or export actions.

## Requirements

Install PHP 8.3+, Composer, Node.js/npm, Git, and either MySQL or SQLite. Redis must be running for the default queue/cache settings used by `composer run dev`.

## Local setup on Windows

1. Copy `.env.example` to `.env`:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Install PHP and JavaScript dependencies:

   ```powershell
   composer install
   npm install
   ```

3. Configure `.env`. The example starts with SQLite. To use MySQL, create an empty database first and set values similar to:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=inventory_management
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Keep the real `.env` file private. It is excluded from this GitHub copy. If password-reset or notification emails should be sent, add your own SMTP settings to `.env`; never put mail credentials in GitHub.

4. Generate the application key, create the schema, and seed local demo roles/data:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

5. Start Redis, then run the application and its development queue worker:

   ```powershell
   composer run dev
   ```

   Keep that terminal open. Open <http://localhost:8000> in a browser. The development command starts Laravel, Vite, and the queue listener; Redis itself must already be running.

6. For a production frontend bundle instead of Vite development mode:

   ```powershell
   npm run build
   ```

## Local demo sign-ins

The local/testing seeder creates these accounts. They all use the shared password `password`.

| Role | Email | Scope |
|---|---|---|
| Administrator | `admin@example.com` | All warehouses |
| Manager | `manager@example.com` | Operational access |
| Manager | `manager2@example.com` | Operational access |
| Warehouse Staff | `staffa@example.com` | Warehouse A |
| Warehouse Staff | `staffb@example.com` | Warehouse B |
| Warehouse Staff | `staff@example.com` | Main Central Warehouse |
| Viewer | `viewer@example.com` | Read-only |
| Inactive Viewer | `inactive@example.com` | Disabled; cannot sign in |

**These are local demo credentials only.** Do not use the shared password on a public or production deployment. Production seeding requires a separately configured initial administrator and does not create the local demo accounts.

## Tests

Run the test suite with:

```powershell
php artisan test --compact
```

## Publish this folder to GitHub

Create an empty repository on GitHub, then from this folder run:

```powershell
git init
git add .
git commit -m "Initial inventory management system"
git branch -M main
git remote add origin https://github.com/YOUR_ACCOUNT/YOUR_REPOSITORY.git
git push -u origin main
```

Replace the remote URL with your own repository address. Review the staged files before the first push. `.env`, local SQLite database files, dependency folders, generated bundles, logs, and private report exports must stay out of GitHub.

## Included source assets

Application source, migrations, seeders, tests, Composer/npm lockfiles, `.env.example`, and public product image assets are included. `vendor`, `node_modules`, compiled Vite output, private/runtime files, and local secrets are intentionally omitted; Composer/npm and the setup steps recreate generated dependencies.
