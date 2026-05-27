<?php
declare(strict_types=1);

/**
 * api/index.php
 *
 * Vercel serverless entrypoint for the Customers app.
 *
 * Vercel does not run PHP/Apache natively, so the application is executed as a
 * serverless function via the community `vercel-php` runtime (see vercel.json).
 * All HTTP requests are rewritten to this file; it simply delegates to the
 * original application logic in customers.php, which stays the single source of
 * truth (and the file the PHPUnit suite measures coverage against).
 *
 * Database credentials (DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD) are read
 * from the environment. On Vercel they are set as Project Environment Variables
 * and point at an external managed MySQL instance (Railway / Aiven), because
 * Vercel has no managed database of its own.
 */

require __DIR__ . '/../customers.php';
