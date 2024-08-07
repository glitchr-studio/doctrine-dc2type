<?php

namespace Doctrine\Composer\PluginHook\Common;

use Doctrine\Composer\PluginHookInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;

/**
 *
 */
abstract class AbstractPluginHook implements PluginHookInterface
{
    public static IOInterface $io;

    public function Print(string $msg)
    {
        self::$io->write("    \033[0;35m* " . $this->getPackageName() . "\033[0m " . $msg);
    }

    /**
     * @param PackageEvent $event
     * @return mixed
     */
    public function onPackageChange(PackageEvent $event)
    {
        throw new \UnexpectedValueException('Please override ' . static::class . '::' . __METHOD__);
    }

    /**
     * @param PackageEvent $event
     * @return mixed
     */
    public function onPackageInstall(PackageEvent $event)
    {
        $this->onPackageChange($event);
    }

    /**
     * @param PackageEvent $event
     * @return mixed
     */
    public function onPackageUpdate(PackageEvent $event)
    {
        $this->onPackageChange($event);
    }

    /**
     * @param PackageEvent $event
     * @return mixed
     */
    public function onPackageRemove(PackageEvent $event)
    {
    }

    protected function getProjectDir(): string
    {
        return dirname(realpath(Factory::getComposerFile()));
    }

    protected function getBundleDir(): string
    {
        return $this->getVendorDir() . '/' . $this->getPackageName();
    }

    protected function getVendorDir(): string
    {
        $composerFile = Factory::getComposerFile();
        $composerJson = json_decode(file_get_contents($composerFile), associative: true, flags: JSON_THROW_ON_ERROR);

        return $composerJson['config']['vendor-dir'] ?? $this->getProjectDir() . '/vendor';
    }

    protected function getBundlePHPFiles(): iterable
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getBundleDir(), \FilesystemIterator::SKIP_DOTS)) as $filePath) {
            if (is_dir($filePath) || !str_ends_with($filePath, '.php')) {
                continue;
            }

            yield $filePath;
        }
    }
}
