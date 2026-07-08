<?php

declare(strict_types=1);

namespace Doctrine\Composer\Tests;

use Composer\InstalledVersions;
use Doctrine\Composer\CodeModifier;
use PHPUnit\Framework\TestCase;

/**
 * The canary the plugin was missing. Each hook patches a specific Doctrine DBAL
 * source file by searching for a specific anchor. When Doctrine refactors that
 * anchor, the patch stops applying — and before the fail-loud change, it did so
 * SILENTLY (that is exactly how SQLiteSchemaManager quietly went unpatched).
 *
 * These tests read the ACTUALLY INSTALLED Doctrine DBAL source and assert the
 * anchors each hook depends on are still present, so a DBAL bump that moves them
 * fails HERE (loudly, at test time) instead of at schema-diff time in production.
 * The anchors mirror the hooks in src/Package/Hook/*; keep them in sync.
 */
class DbalSourceContractTest extends TestCase
{
    /** The shared per-driver anchor line every *SchemaManager hook appends after. */
    private const DRIVER_ANCHOR = '$type = $this->platform->getDoctrineTypeMapping($dbType);';

    /** Driver schema managers with a per-driver DC2Type hook. */
    private const DRIVER_FILES = [
        'MySQLSchemaManager.php',
        'OracleSchemaManager.php',
        'PostgreSQLSchemaManager.php',
        'SQLServerSchemaManager.php',
        'SQLiteSchemaManager.php',
    ];

    private function schemaDir(): string
    {
        if (!InstalledVersions::isInstalled('doctrine/dbal')) {
            $this->markTestSkipped('doctrine/dbal is not installed.');
        }

        $dir = InstalledVersions::getInstallPath('doctrine/dbal') . '/src/Schema';
        if (!is_dir($dir)) {
            $this->markTestSkipped('doctrine/dbal Schema directory not found at ' . $dir);
        }

        return $dir;
    }

    /**
     * Whitespace-insensitive containment — Doctrine aligns some assignments
     * ("$type    =") which a byte-for-byte str_contains would miss.
     */
    private function containsNormalized(string $haystack, string $needle): bool
    {
        $normalize = static fn(string $s): string => preg_replace('/[ \t]+/', ' ', $s);

        return str_contains($normalize($haystack), $normalize($needle));
    }

    public function testEveryDriverSchemaManagerStillCarriesTheAnchorTheHookAppendsAfter(): void
    {
        $dir = $this->schemaDir();

        foreach (self::DRIVER_FILES as $file) {
            $path = $dir . '/' . $file;
            $this->assertFileExists($path, "$file — a driver hook targets this file; if Doctrine removed/renamed it, update the hooks.");
            $this->assertTrue(
                $this->containsNormalized(file_get_contents($path), self::DRIVER_ANCHOR),
                "$file no longer contains the anchor \"" . self::DRIVER_ANCHOR . "\" — the DC2Type hook for this driver would silently stop patching. Update the hook's search string."
            );
        }
    }

    public function testAbstractSchemaManagerStillDeclaresTheListTableIndexesMethodTheAbstractHookAnchorsTo(): void
    {
        $path = $this->schemaDir() . '/AbstractSchemaManager.php';
        $this->assertFileExists($path);
        $this->assertMatchesRegularExpression(
            '/function\s+listTableIndexes\s*\(/',
            file_get_contents($path),
            'AbstractSchemaManager::listTableIndexes is the anchor the AbstractSchemaManager hook appends its extractDoctrineTypeFromComment() method after; it is gone, so update the hook.'
        );
    }

    /**
     * End-to-end against the real source: copy each installed driver file to a
     * temp location and confirm the hook's own appendToLine actually applies
     * (or is already applied) — never that it throws CodeModifierException,
     * which would mean the plugin can no longer patch this DBAL version.
     */
    public function testTheHookCanActuallyPatchEachInstalledDriverSource(): void
    {
        $dir = $this->schemaDir();
        $block = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';

        foreach (self::DRIVER_FILES as $file) {
            $tmp = tempnam(sys_get_temp_dir(), 'dbal') . '.php';
            copy($dir . '/' . $file, $tmp);

            try {
                $modifier = new CodeModifier($tmp, 'contract-test');
                // Returns true (freshly applied) or false (already patched in
                // the installed copy) — either is fine; a throw is the failure.
                $modifier->appendToLine('extractDoctrineComments', self::DRIVER_ANCHOR, $block);
                $out = file_get_contents($tmp);
                $this->assertStringContainsString('extractDoctrineTypeFromComment', $out, "$file: the extraction call is missing after patching.");
                $this->assertSame('', trim((string) shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1 >/dev/null')), "$file: patched source is not valid PHP.");
            } finally {
                @unlink($tmp);
                @unlink($tmp . '.bak');
            }
        }
    }
}
