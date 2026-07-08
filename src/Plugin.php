<?php

namespace Doctrine\Composer;

use Composer\ClassMapGenerator\ClassMapGenerator;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Doctrine\Composer\Exception\CodeModifierException;
use Doctrine\Composer\Package\AbstractHook;
use Doctrine\Composer\Package\HookInterface;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Runs a hook and, if its patch could not be applied (target source
     * drifted), surfaces the failure loudly instead of letting it pass
     * unnoticed. Re-thrown so the composer command itself fails — this
     * restoration is not optional, and a silent miss (the historical
     * SQLiteSchemaManager bug) must never happen again.
     */
    private function runHook(callable $run): void
    {
        try {
            $run();
        } catch (CodeModifierException $e) {
            $this->io->writeError('<error>[doctrine-dc2type] DC2Type comment restoration FAILED:</error>');
            $this->io->writeError('<error>' . $e->getMessage() . '</error>');
            throw $e;
        }
    }

    /**
     * @return string
     */
    public static function getPackageName()
    {
        return basename(dirname(__FILE__, 3))."/".basename(dirname(__FILE__, 2));
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate',
            PackageEvents::PRE_PACKAGE_UNINSTALL => 'onPackageRemove'
        ];
    }

    protected IOInterface $io;
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    private function getPluginName(): string
    {
        $composerFile = dirname(__FILE__) . '/../composer.json';
        $composerJson = json_decode(file_get_contents($composerFile), associative: true, flags: JSON_THROW_ON_ERROR);

        if (array_key_exists('name', $composerJson)) {
            return $composerJson['name'];
        }

        throw new \UnexpectedValueException('No plugin name found in ' . __CLASS__ . '. This is odd.');
    }

    private array $installedPackageNames = [];

    public function onPackageInstall(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $packageName = $operation->getPackage()?->getName();
        if (in_array($packageName, $this->installedPackageNames)) {
            return;
        }

        $this->installedPackageNames[] = $packageName;

        foreach (ClassMapGenerator::createMap(__DIR__) as $className => $_) {

            if (!in_array(HookInterface::class, class_implements($className))) {
                continue;
            }

            try {
                $class = new $className($this->io);
            } catch (\Error $e) {
                continue;
            }

            if (!InstalledVersions::isInstalled($class->getPackageName())) {
                continue;
            }
            if ($class->getPackageName() != $packageName && $this->getPackageName() != $packageName) {
                continue;
            }
            if (!$class->checkValidityVersion($event)) {
                continue;
            }

            $this->runHook(fn() => $class->onPackageInstall($event));
        }
    }

    private array $updatedPackageNames = [];

    public function onPackageUpdate(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $packageName = $operation->getInitialPackage()?->getName();
        if (in_array($packageName, $this->updatedPackageNames)) {
            return;
        }

        $this->updatedPackageNames[] = $packageName;

        foreach (ClassMapGenerator::createMap(__DIR__) as $className => $_) {

            if (!in_array(HookInterface::class, class_implements($className))) {
                continue;
            }

            try {
                $class = new $className($this->io);
            } catch (\Error $e) {
                continue;
            }

            if (!InstalledVersions::isInstalled($class->getPackageName())) {
                continue;
            }
            if ($class->getPackageName() != $packageName && $this->getPackageName() != $packageName) {
                continue;
            }

            if (!$class->checkValidityVersion($event)) {
                continue;
            }

            $this->runHook(fn() => $class->onPackageUpdate($event));
        }
    }

    private array $removedPackageNames = [];

    public function onPackageRemove(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $packageName = $operation->getPackage()?->getName();
        if (in_array($packageName, $this->removedPackageNames)) {
            return;
        }

        $this->removedPackageNames[] = $packageName;

        foreach (ClassMapGenerator::createMap(__DIR__) as $className => $_) {

            if (!in_array(HookInterface::class, class_implements($className))) {
                continue;
            }

            try {
                $class = new $className($this->io);
            } catch (\Error $e) {
                continue;
            }

            if (!InstalledVersions::isInstalled($class->getPackageName())) {
                continue;
            }
            if ($class->getPackageName() != $packageName && $this->getPackageName() != $packageName) {
                continue;
            }
            if (!$class->checkValidityVersion($event)) {
                continue;
            }

            $this->runHook(fn() => $class->onPackageRemove($event));
        }
    }
}
