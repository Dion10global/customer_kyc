# Customers App

**Author:** Benjamin Kudzai Nyaruviro ([@BenNyaruz](https://github.com/BenNyaruz))
**Module:** Cloud Application Development
**Task:** Practical Test 2 (Questions 1 and 2)
**Repository:** <https://github.com/BenNyaruz/test-2>

A PHP application that lists customer records from a MySQL database using PDO
with prepared statements. Deployed on the Vercel PaaS via the `vercel-php`
serverless runtime, backed by an external managed MySQL (Railway / Aiven).

## What it does

`customers.php` connects to a MySQL database, selects all rows from a
`customers` table (`id`, `name`, `email`, `created_at`) and renders them in a
styled, responsive HTML table. Connection failures are caught and replaced
with a user-friendly error page; the underlying exception is logged
server-side via `error_log()` so credentials and stack traces never leak.

## Repository layout

| File             | Purpose                                                 |
|------------------|---------------------------------------------------------|
| `customers.php`  | The application itself                                  |
| `api/index.php`  | Vercel serverless entrypoint (delegates to `customers.php`) |
| `vercel.json`    | Vercel config: `vercel-php` runtime + request rewrites  |
| `Dockerfile`     | PHP 8.2 + Apache image (local/container use; unused by Vercel) |
| `init.sql`       | Schema + seed data for the `customers` table            |
| `composer.json`  | Composer config + PHPUnit dev dependency                |
| `phpunit.xml`    | PHPUnit configuration (unit + integration suites)       |
| `tests/`         | PHPUnit test files                                      |
| `.github/workflows/ci.yml` | GitHub Actions CI/CD pipeline (Q2)             |
| `pipeline-design.md` | Q2 design writeup                                   |
| `.env.example`   | Template for local environment variables                |
| `.gitignore`     | Excludes secrets, OS junk, editor folders               |

## Environment variables

The script reads its credentials from the environment - **never** from
hard-coded literals.

| Variable      | Example       | Notes                            |
|---------------|---------------|----------------------------------|
| `DB_HOST`     | `db.aiven.io` | Host of your MySQL server        |
| `DB_PORT`     | `3306`        | Default MySQL port               |
| `DB_NAME`     | `companydb`   | Database name                    |
| `DB_USER`     | `app_user`    | Read-only user is recommended    |
| `DB_PASSWORD` | `********`    | Strong password / managed secret |
| `DB_SSL_CA`   | `certs/aiven-ca.pem` | CA cert for TLS (Aiven requires it); PEM contents or path. Empty for local/CI |

## Local development

1. Install MySQL locally (or use Docker: `docker run -d --name mysql -e MYSQL_ROOT_PASSWORD=root -p 3306:3306 mysql:8`).
2. Create the database and load the schema (the script is database-agnostic, so
   pick the target DB on the command line):
   `mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS companydb"` then
   `mysql -u root -p companydb < init.sql`.
3. Copy `.env.example` to `.env` and fill in the values.
4. Serve the file: `php -S localhost:8000`.
5. Visit <http://localhost:8000/customers.php>.

## Deploying to Vercel

Vercel has no PHP runtime and no managed database of its own, so two pieces are
needed: the `vercel-php` runtime (declared in `vercel.json`) and an external
managed MySQL.

1. **Provision MySQL** on **Railway** or **Aiven** (both have free tiers) and
   run `init.sql` against it to create + seed the `customers` table.
2. **Link the project:** install the CLI (`npm i -g vercel`), then run
   `vercel link` once. This writes `.vercel/project.json` containing the
   `orgId` and `projectId`.
3. **Set environment variables** in the Vercel dashboard (Project -> Settings
   -> Environment Variables): `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
   `DB_PASSWORD` pointing at your managed MySQL.
4. **Add GitHub secrets** for CI: `VERCEL_TOKEN` (Account Settings -> Tokens),
   `VERCEL_ORG_ID` and `VERCEL_PROJECT_ID` (from `.vercel/project.json`).
5. **Push to `main`** - the pipeline in `.github/workflows/ci.yml` builds,
   tests, security-scans, deploys a green preview, smoke-tests it, then
   promotes it to production (zero-downtime blue-green alias swap).

A first manual deploy can also be done with `vercel --prod` after step 3.

## Security notes

- Credentials live in environment variables, never in source control.
- `htmlspecialchars()` is applied to every value rendered into HTML.
- Prepared statements use server-side preparation
  (`PDO::ATTR_EMULATE_PREPARES => false`).
- Recommended next step: add a read-only MySQL user that can `SELECT` only
  on `customers` and use those credentials in production.
