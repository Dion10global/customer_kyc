<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for customers.php helpers.
 */
final class CustomersTest extends TestCase
{
    public function testHtmlSpecialcharsEscapesScriptTags(): void
    {
        $input    = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $actual   = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        self::assertSame($expected, $actual);
    }

    public function testHtmlSpecialcharsPreservesEmoji(): void
    {
        $input  = "Thandi 🇿🇦";
        $actual = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertSame("Thandi 🇿🇦", $actual);
    }

    public function testHtmlSpecialcharsHandlesNullSafely(): void
    {
        $actual = htmlspecialchars((string)(null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        self::assertSame('', $actual);
    }

    public function testPdoMysqlDriverIsAvailable(): void
    {
        self::assertContains('mysql', \PDO::getAvailableDrivers());
    }
}
