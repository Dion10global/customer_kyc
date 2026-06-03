# CI/CD Pipeline Design
**Author:** Dion Farley ([@Dion10global](https://github.com/Dion10global))
**Module:** Cloud Application Development
**Task:** Practical Test 2 - Question 2

This document explains the CI/CD pipeline defined in
`.github/workflows/ci.yml`. The pipeline uses **GitHub Actions** as the
orchestrator and **Vercel** as the PaaS that hosts the application.

## 0. Why Vercel needs an external database

Vercel is a serverless platform: it has no native PHP runtime and **no managed
database**. Two adaptations make the Customers app fit:

1. **PHP runtime** - the app runs through the community `vercel-php` runtime,
   configured in `vercel.json`. Every HTTP request is rewritten to
   `api/index.php`, which simply `require`s the unchanged `customers.php`. So
   `customers.php` stays the single source of truth (and the file PHPUnit
   measures coverage against), while Vercel executes it as a serverless
   function.
2. **Database** - production data lives in an **external managed MySQL**
   (Railway or Aiven). Its connection details are stored as Vercel Project
   Environment Variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
   `DB_PASSWORD`) and read by `getenv()` in `customers.php`. Nothing about the
   application code changes.

## 1. Architecture at a glance

```
 Developer push --> GitHub --> Actions runner --> Vercel preview --> Vercel prod
       to main        |             |                 (green)           (blue)
                      +-- trigger   +-- build (composer install + php -l)
                                    +-- test (PHPUnit vs MySQL service container)
                                    +-- OWASP dependency check
                                    +-- deploy green (build once + preview)
                                    +-- promote green -> production (alias swap)
```

Each stage runs in its own GitHub Actions job. Jobs are wired together with
`needs:` so a failure short-circuits the pipeline and nothing downstream of the
failure executes - in particular, no insecure or untested artefact ever reaches
production.

## 2. Stage-by-stage rationale

### 2.1 Trigger (push to main)

The brief asks for "push to main branch". Two extra triggers are included:
**`pull_request`** runs the non-deploying stages against every PR;
**`workflow_dispatch`** allows a manual re-run from the Actions tab.

A `concurrency` group cancels older runs on the same branch so two pushes in
quick succession never produce a deploy race.

### 2.2 Build (install dependencies + lint)

The `build` job checks out the code, sets up PHP 8.2 with `pdo`, `pdo_mysql`
and `mbstring`, caches `~/.composer/cache` keyed on `composer.lock`, runs
`composer install`, and lints every PHP file with `php -l` (parse-error gate).

`php -l` is parse-only; for style enforcement add `phpcs --standard=PSR12` or
`php-cs-fixer fix --dry-run`.

### 2.3 Test (unit + integration against a test database)

The `test` job spins up a real MySQL 8.0 instance as a GitHub Actions
**service container** with a health check, loads `init.sql` into the
`companydb_test` database, then runs PHPUnit's unit and integration suites.

Why a service container rather than a mock? Integration tests must exercise the
real PDO driver, the real prepared-statement path, and real MySQL behaviour
around charsets, collations and NULLs. Mocks would hide all of that.

### 2.4 Security scan (OWASP Dependency-Check)

The `security-scan` job runs `dependency-check/Dependency-Check_Action` with
`--failOnCVSS 7`, which fails the build if any High or Critical CVE is found.
The HTML report is uploaded as an artefact for audit.

Threshold tuning: `--failOnCVSS 7` blocks High and Critical but permits Medium
issues so the build is not held hostage by routine noise. For deeper coverage
add a SAST tool (psalm, semgrep).

### 2.5 Deploy green (Vercel preview)

`deploy-green` runs only on push-to-main (PRs do not deploy). It:

1. installs the Vercel CLI;
2. `vercel deploy` - uploads the source and lets **Vercel build it remotely**,
   producing one **immutable preview deployment** with its own URL. This is the
   **green** environment. (The build runs on Vercel rather than the runner
   because the `vercel-php` runtime's bundled PHP is compiled for Amazon Linux
   and is not glibc-compatible with the GitHub Ubuntu runner.)
3. **smoke-tests** the green URL with `curl --fail`. If the new deployment
   cannot serve a 200, the pipeline stops here and production is never touched.

### 2.6 Promote to production (blue-green swap)

`promote-production` depends on `deploy-green`, so production only ever receives
a build that already booted and passed a smoke test as a live preview.

Blue-green steps:

1. The stable production domain is re-pointed to the **exact same** deployment
   that was just smoke-tested, via the Vercel REST alias API
   (`POST /v2/deployments/{id}/aliases`). This is an **atomic alias switch**:
   the production domain instantly flips from the old (blue) deployment to the
   new (green) one. No rebuild, no cold start of the domain, no dropped requests
   - the zero-downtime cutover. (The API is used instead of `vercel promote`
   because the CLI mis-resolves the team scope in CI when no project is linked
   in the job.)
2. The previous (blue) deployment stays live and addressable, enabling instant
   rollback.
3. A post-promotion smoke test confirms production is healthy.

This is the same conceptual model as a classic two-environment blue-green
deploy: build green alongside blue, verify green, then flip traffic atomically.
On Vercel the "flip" is an alias swap rather than a load-balancer reweight.

### 2.7 Rollback strategy

```bash
vercel rollback            # re-alias production to the previous deployment
# or, deterministically:
vercel promote <previous-deployment-url>
```

Because every deployment is immutable and retained, rollback is an instant
alias switch back to the last-known-good build - no redeploy required.

## 3. Secrets and configuration

| Secret / variable                  | Where set                            | Purpose                                   |
|------------------------------------|--------------------------------------|-------------------------------------------|
| `VERCEL_TOKEN`                     | GitHub repo -> Settings -> Secrets   | Authenticates the Vercel CLI in CI        |
| `VERCEL_ORG_ID`                    | GitHub repo -> Settings -> Secrets   | Identifies the Vercel team/account        |
| `VERCEL_PROJECT_ID`                | GitHub repo -> Settings -> Secrets   | Identifies the Vercel project             |
| `VERCEL_AUTOMATION_BYPASS_SECRET`  | GitHub repo -> Settings -> Secrets   | Lets the smoke tests bypass Vercel Deployment Protection (preview URLs are 401 to anonymous requests). Not needed if protection is disabled. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSL_CA` | Vercel Project Env Vars (external MySQL + TLS CA) | Read by `getenv()` in `customers.php` |

`VERCEL_ORG_ID` and `VERCEL_PROJECT_ID` come from `.vercel/project.json` after
running `vercel link` locally once. No secrets are committed: `.env` and
`.vercel/` are git-ignored, and Vercel encrypts env vars at rest.

## 4. Mapping to the marking criteria

| Criterion (brief)                                | Implementation                                                |
|--------------------------------------------------|---------------------------------------------------------------|
| Trigger: push to main                            | `on.push.branches: [main]` + PR + manual dispatch             |
| Build: install dependencies + lint               | `composer install` + `php -l`                                 |
| Test: unit + integration against test database   | PHPUnit + MySQL 8 service container, seeded with `init.sql`    |
| Security scan: OWASP dependency check            | `dependency-check/Dependency-Check_Action` with CVSS gate     |
| Deploy: zero-downtime blue-green                 | Vercel preview (green) -> smoke test -> `vercel promote` (atomic alias swap) |

## 5. Pitfalls and how the pipeline avoids them

- **Race conditions on rapid pushes** - mitigated by the `concurrency` group.
- **Flaky service-container readiness** - mitigated by `mysqladmin ping`.
- **Leaking secrets to logs** - GitHub Actions masks `secrets.*`.
- **Promoting an unhealthy build** - the green deployment is smoke-tested
  before `vercel promote` ever runs.
- **Rebuild drift** - one immutable deployment is built, smoke-tested, then
  promoted; production serves the exact bits that passed the test, never a
  fresh rebuild.
- **Composer cache poisoning between branches** - cache key includes the
  `composer.lock` hash.
- **Dependency-check noise** - threshold set to CVSS >= 7.
