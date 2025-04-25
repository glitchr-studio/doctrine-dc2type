<?php

namespace Doctrine\Composer\Package;

use Composer\Installer\PackageEvent;

/**
 *
 */
interface HookInterface
{
    public function getPackageName(): string;
    public function getPackageVersion(PackageEvent $event): string;
    public function getPackageRequirements(): string;

    public function onPackageChange(PackageEvent $event);
    public function onPackageInstall(PackageEvent $event);
    public function onPackageUpdate(PackageEvent $event);

    public function onPackageRemove(PackageEvent $event);
}
