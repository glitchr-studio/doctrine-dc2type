<?php

namespace Doctrine\Composer\Package\Hook;

use Composer\Installer\PackageEvent;
use Doctrine\Composer\Package\AbstractHook;
use Doctrine\Composer\CodeModifier;

final class AbstractSchemaManager extends AbstractHook
{
    public function getPackageName(): string
    {
        return 'doctrine/dbal';
    }

    public function getPackageRequirements(): string
    {
        return ">=4";
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

        $this->print('Reintroducing comment extract in `AbstractSchemaManager.php`.');
        $codeModifier = new CodeModifier($this->getBundleDir() . '/src/Schema/AbstractSchemaManager.php', $this->getAuthor());
        $codeModifier->appendTo("extractDoctrineComments", "\Doctrine\DBAL\Schema\AbstractSchemaManager::listTableIndexes", $block);
    }
}
