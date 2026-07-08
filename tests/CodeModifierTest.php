<?php

declare(strict_types=1);

namespace Doctrine\Composer\Tests;

use Doctrine\Composer\CodeModifier;
use Doctrine\Composer\Exception\CodeModifierException;
use PHPUnit\Framework\TestCase;

/**
 * The DC2Type restoration is a token-based in-place source patcher. These tests
 * pin the behaviours that matter: it must apply against real (differently
 * whitespaced) upstream source, it must be idempotent, and — most importantly —
 * it must FAIL LOUDLY when its target anchor is gone rather than silently
 * no-op'ing (the failure mode that let the SQLite hook quietly stop working).
 */
class CodeModifierTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @unlink($f);
            @unlink($f . '.bak');
        }
        $this->tempFiles = [];
    }

    private function tempPhp(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cm') . '.php';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private const SEARCH = '$type = $this->platform->getDoctrineTypeMapping($dbType);';
    private const BLOCK = '$type = $this->extractDoctrineTypeFromComment($tableColumn[\'comment\'] ?? null, $type);';

    public function testAppendToLineInjectsTheBlockAndWrapsTheOriginalInMarkers(): void
    {
        $path = $this->tempPhp("<?php\nclass X {\n    public function f() {\n        \$type = \$this->platform->getDoctrineTypeMapping(\$dbType);\n        return \$type;\n    }\n}\n");

        $applied = (new CodeModifier($path, 'test'))->appendToLine('dc2', self::SEARCH, self::BLOCK);

        $this->assertTrue($applied);
        $out = file_get_contents($path);
        $this->assertStringContainsString('extractDoctrineTypeFromComment', $out);
        $this->assertStringContainsString('[bootstrap:dc2@', $out);
        $this->assertSame('', trim((string) shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1 >/dev/null')), 'patched file must stay syntactically valid');
    }

    /**
     * The SQLite hook silently missed because Doctrine aligns its assignment
     * ("$type    =") while the hook searches for a single space ("$type =").
     * A single-space search must now match aligned source.
     */
    public function testAppendToLineMatchesAlignedWhitespaceInTheSource(): void
    {
        $path = $this->tempPhp("<?php\nclass X {\n    public function f() {\n        \$type    = \$this->platform->getDoctrineTypeMapping(\$dbType);\n        return \$type;\n    }\n}\n");

        $applied = (new CodeModifier($path, 'test'))->appendToLine('dc2', self::SEARCH, self::BLOCK);

        $this->assertTrue($applied);
        $this->assertStringContainsString('extractDoctrineTypeFromComment', file_get_contents($path));
    }

    public function testAppendToLineIsIdempotent(): void
    {
        $path = $this->tempPhp("<?php\nclass X {\n    public function f() {\n        \$type    = \$this->platform->getDoctrineTypeMapping(\$dbType);\n    }\n}\n");

        $modifier = new CodeModifier($path, 'test');
        $this->assertTrue($modifier->appendToLine('dc2', self::SEARCH, self::BLOCK));
        $afterFirst = file_get_contents($path);

        // Second application: already-patched → quiet no-op, no throw, no change.
        $this->assertFalse($modifier->appendToLine('dc2', self::SEARCH, self::BLOCK));
        $this->assertSame($afterFirst, file_get_contents($path));
        $this->assertSame(1, substr_count(file_get_contents($path), 'extractDoctrineTypeFromComment(') - 0, 'the call must not be appended twice');
    }

    public function testAppendToLineThrowsWhenTheAnchorIsMissing(): void
    {
        $path = $this->tempPhp("<?php\nclass X {\n    public function f() {\n        \$type = \$this->somethingCompletelyDifferent();\n    }\n}\n");

        $this->expectException(CodeModifierException::class);
        $this->expectExceptionMessageMatches('/could not be applied/');

        (new CodeModifier($path, 'test'))->appendToLine('dc2', self::SEARCH, self::BLOCK);
    }

    public function testAppendToThrowsWhenTheTargetMethodIsMissing(): void
    {
        $path = $this->tempPhp("<?php\nnamespace Doctrine\\DBAL\\Schema;\nclass AbstractSchemaManager {\n    public function somethingElse() {}\n}\n");

        $this->expectException(CodeModifierException::class);

        (new CodeModifier($path, 'test'))->appendTo('dc2', '\\Doctrine\\DBAL\\Schema\\AbstractSchemaManager::listTableIndexes', 'public function injected() {}');
    }

    public function testRestoreReturnsTheFileToItsPristineState(): void
    {
        $original = "<?php\nclass X {\n    public function f() {\n        \$type    = \$this->platform->getDoctrineTypeMapping(\$dbType);\n    }\n}\n";
        $path = $this->tempPhp($original);

        $modifier = new CodeModifier($path, 'test');
        $modifier->appendToLine('dc2', self::SEARCH, self::BLOCK);
        $this->assertNotSame($original, file_get_contents($path));

        $modifier->restore();
        $this->assertSame($original, file_get_contents($path), 'restore() must byte-for-byte reproduce the original');
    }

    public function testHasReportsWhetherATagIsApplied(): void
    {
        $path = $this->tempPhp("<?php\nclass X {\n    public function f() {\n        \$type = \$this->platform->getDoctrineTypeMapping(\$dbType);\n    }\n}\n");

        $modifier = new CodeModifier($path, 'test');
        $this->assertFalse($modifier->has('dc2'));

        $modifier->appendToLine('dc2', self::SEARCH, self::BLOCK);
        $this->assertTrue($modifier->has('dc2'));
    }
}
