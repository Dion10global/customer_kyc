<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the customers table.
 * In CI a MySQL 8.0 service container is started and seeded with init.sql.
 */
final class DatabaseTest extends TestCase
{
    private static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db   = getenv('DB_NAME') ?: 'companydb_test';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: 'rootpw';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function testCustomersTableExistsAndHasExpectedColumns(): void
    {
        $stmt = self::$pdo->query("SHOW COLUMNS FROM customers");
        $columns = array_column($stmt->fetchAll(), 'Field');

        self::assertEqualsCanonicalizing(
            ['id', 'name', 'email', 'created_at'],
            $columns
        );
    }

    public function testPreparedStatementReturnsSeededRows(): void
    {
        $stmt = self::$pdo->prepare(
            'SELECT id, name, email, created_at FROM customers ORDER BY created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        self::assertNotEmpty($rows);
        self::assertArrayHasKey('email', $rows[0]);
        self::assertMatchesRegularExpression('/^[^@\s]+@[^@\s]+$/', $rows[0]['email']);
    }

    public function testEmailColumnHasUniqueConstraint(): void
    {
        $insert = self::$pdo->prepare('INSERT INTO customers (name, email) VALUES (?, ?)');
        $email  = 'dupe-' . uniqid() . '@example.com';
        $insert->execute(['First Insert', $email]);

        $this->expectException(\PDOException::class);
        $insert->execute(['Second Insert', $email]);
    }

    public function testCharsetIsUtf8mb4(): void
    {
        $stmt = self::$pdo->query("SHOW VARIABLES LIKE 'character_set_client'");
        $row = $stmt->fetch();
        self::assertSame('utf8mb4', $row['Value']);
    }
}
