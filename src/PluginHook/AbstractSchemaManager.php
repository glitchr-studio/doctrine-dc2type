<?php

namespace Doctrine\Composer\PluginHook;

use Composer\Installer\PackageEvent;

/**
 *
 */
final class AbstractSchemaManager extends Common\AbstractPluginHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function onPackageChange(PackageEvent $event)
    {
        $block = '
/**
 * Given a table comment this method tries to extract a typehint for Doctrine Type, or returns
 * the type given as default.
 *
 * @internal This method should be only used from within the AbstractSchemaManager class hierarchy.
 */
public function extractDoctrineTypeFromComment(?string $comment, string $currentType): string
{
    if ($comment !== null && preg_match(\'(\(DC2Type:(((?!\)).)+)\))\', $comment, $match) === 1) {
        return $match[1];
    }

    return $currentType;
}';

        $this->Print('Updating "AbstractSchemaManager.php" file.');
        $codeModifier = new \CodeModifier($this->getBundleDir() . '/src/Schema/AbstractSchemaManager.php', $this->getAuthor());
        $codeModifier->appendTo("comment", "AbstractSchemaManager::listTableIndexes", $block);
    }
}
