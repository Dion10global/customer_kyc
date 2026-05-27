<?php
declare(strict_types=1);

/**
 * customers.php
 *
 * Author : Benjamin Kudzai Nyaruviro (BenNyaruz)
 * GitHub : https://github.com/BenNyaruz
 * Email  : bennyaruviro@gmail.com
 * Module : Cloud Application Development
 * Task   : Practical Test 2 - Question 1
 *
 * Lists all customer records from a MySQL "customers" table using PDO with
 * prepared statements and renders the result as a styled HTML table.
 *
 * Marking criteria addressed:
 *   - PDO connection (MySQL driver, utf8mb4 charset)
 *   - Prepared statement (defence in depth)
 *   - Graceful handling of connection / query failures
 *   - Styled HTML output, with XSS-safe escaping
 *   - 12-factor style configuration (credentials from environment variables)
 */

// ---------------------------------------------------------------------------
// 1. Configuration - never hard-code credentials. Read from the environment.
// ---------------------------------------------------------------------------
$host    = getenv('DB_HOST')     ?: 'localhost';
$port    = getenv('DB_PORT')     ?: '3306';
$dbname  = getenv('DB_NAME')     ?: 'companydb';
$user    = getenv('DB_USER')     ?: 'root';
$pass    = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// TLS: managed providers such as Aiven enforce encrypted connections
// (require_secure_transport=ON). When a CA certificate is configured we pin it
// so the server's identity is verified. Left unset for local dev / CI, where
// the connection is plaintext to a local MySQL.
$sslCa = resolve_db_ssl_ca();
if ($sslCa !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
}

// ---------------------------------------------------------------------------
// 2. Connect - log real errors server-side, show user a friendly page.
// ---------------------------------------------------------------------------
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('[customers.php] DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    render_error_page(
        'Service temporarily unavailable',
        'We are unable to reach the customer database right now. Please try again shortly.'
    );
    exit;
}

// ---------------------------------------------------------------------------
// 3. Query - prepared statement (required by the brief; defence-in-depth).
// ---------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare(
        'SELECT id, name, email, created_at
           FROM customers
       ORDER BY created_at DESC'
    );
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[customers.php] Query failed: ' . $e->getMessage());
    http_response_code(500);
    render_error_page(
        'Unable to load customers',
        'An internal error occurred while retrieving customer records.'
    );
    exit;
}

// ---------------------------------------------------------------------------
// 4. Helpers.
// ---------------------------------------------------------------------------
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Resolve the CA certificate used to verify the database's TLS certificate.
 *
 * DB_SSL_CA may be either:
 *   - the PEM contents pasted directly into the env var (e.g. on Vercel), which
 *     is materialised to a temp file because PDO needs a path, not the text; or
 *   - a path, absolute or relative to this app directory (e.g. the bundled
 *     certs/aiven-ca.pem).
 * Returns '' when no CA is configured (local dev / CI use plaintext).
 */
function resolve_db_ssl_ca(): string {
    $ca = getenv('DB_SSL_CA') ?: '';
    if ($ca === '') {
        return '';
    }

    if (str_contains($ca, 'BEGIN CERTIFICATE')) {
        $tmp = sys_get_temp_dir() . '/db-ca-' . md5($ca) . '.pem';
        if (!is_file($tmp)) {
            file_put_contents($tmp, $ca);
        }
        return $tmp;
    }

    if (is_readable($ca)) {
        return $ca;
    }
    $relative = __DIR__ . '/' . ltrim($ca, '/');
    return is_readable($relative) ? $relative : '';
}

function render_error_page(string $title, string $message): void {
    echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\">";
    echo "<title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>";
    echo "<style>body{font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#f5f7fa;padding:3rem;color:#1f2937}";
    echo ".card{max-width:560px;margin:auto;background:#fff;border-radius:8px;padding:2rem;box-shadow:0 1px 3px rgba(0,0,0,.08)}";
    echo "h1{color:#b91c1c;margin-top:0}</style></head><body><div class=\"card\">";
    echo "<h1>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h1>";
    echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</div></body></html>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="author" content="Benjamin Kudzai Nyaruviro">
    <title>Customer Records</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-light: #eef2ff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f5f7fa;
        }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 2rem;
        }
        header { max-width: 1100px; margin: 0 auto 1.5rem; }
        h1 { margin: 0 0 .25rem; color: #0f172a; font-size: 1.6rem; }
        .meta { color: var(--muted); font-size: .9rem; }
        .table-wrap {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); font-size: .95rem; }
        th { background: var(--primary); color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; font-size: .8rem; }
        tr:nth-child(even) td { background: #f9fafb; }
        tr:hover td           { background: var(--primary-light); }
        td.id                 { font-family: ui-monospace, Menlo, Consolas, monospace; color: var(--muted); }
        .empty { padding: 2rem; text-align: center; color: var(--muted); font-style: italic; }
        footer { max-width: 1100px; margin: 1.5rem auto 0; color: var(--muted); font-size: .8rem; text-align: center; }
    </style>
</head>
<body>
    <header>
        <h1>Customer Records</h1>
        <p class="meta">
            Author: Benjamin Kudzai Nyaruviro (<a href="https://github.com/BenNyaruz">@BenNyaruz</a>) &middot;
            Generated <?= e(date('Y-m-d H:i')) ?> &middot;
            <?= (int) count($customers) ?> record(s)
        </p>
    </header>

    <section class="table-wrap">
        <?php if (empty($customers)): ?>
            <div class="empty">No customer records found.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Created At</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $row): ?>
                        <tr>
                            <td class="id"><?= e((string) $row['id']) ?></td>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <footer>
        &copy; <?= e(date('Y')) ?> Benjamin Kudzai Nyaruviro &middot; Cloud Application Development &middot; Practical Test 2
    </footer>
</body>
</html>
