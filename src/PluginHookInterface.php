<?php

namespace Doctrine\Composer;

use Composer\Installer\PackageEvent;

/**
 *
 */
interface PluginHookInterface
{
    public function getPackageName(): string;

    public function onPackageChange(PackageEvent $event);
    public function onPackageInstall(PackageEvent $event);
    public function onPackageUpdate(PackageEvent $event);

    public function onPackageRemove(PackageEvent $event);
}
