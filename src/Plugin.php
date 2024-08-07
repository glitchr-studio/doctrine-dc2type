<?php

namespace Doctrine\Composer;

use Doctrine\Composer\Common\AbstractPluginHook;
use Composer\Autoload\ClassMapGenerator;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

include_once dirname(__FILE__) . '/../bootstrap.php';

/**
 *
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @return string
     */
    public static function getPackageName()
    {
        return 'glitchr/doctrine-dc2type';
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

    public function activate(Composer $composer, IOInterface $io)
    {
        AbstractPluginHook::$io = $io;
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

            if (!in_array(PluginHookInterface::class, class_implements($className))) {
                continue;
            }

            try {
                $class = new $className();
            } catch (\Error $e) {
                continue;
            }

            if (!InstalledVersions::isInstalled($class->getPackageName())) {
                continue;
            }
            if ($class->getPackageName() != $packageName && $this->getPackageName() != $packageName) {
                continue;
            }

            $class->onPackageInstall($event);
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

            if (!in_array(PluginHookInterface::class, class_implements($className))) {
                continue;
            }

            try {
                $class = new $className();
            } catch (\Error $e) {
                continue;
            }

            if (!InstalledVersions::isInstalled($class->getPackageName())) {
                continue;
            }
            if ($class->getPackageName() != $packageName && $this->getPackageName() != $packageName) {
                continue;
            }

            $class->onPackageUpdate($event);
        }
    }
}
