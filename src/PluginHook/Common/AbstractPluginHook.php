<?php

namespace Doctrine\Composer\PluginHook\Common;
use Doctrine\Composer\PluginHookInterface;

use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Semver\VersionParser;
use Composer\DependencyResolver\Operation\UpdateOperation;

/**
 *
 */
abstract class AbstractPluginHook implements PluginHookInterface
{
    public static IOInterface $io;

    public function Print(string $msg)
    {
        self::$io->write("    \033[0;35m* " . $this->getPackageName() . " <- " . $this->getAuthor() . "\033[0m " . $msg);
    }

    /**
     * @param PackageEvent $event
     * @return mixed
     */
    public function onPackageChange(PackageEvent $event)
    {
        $methodName = explode("::", __METHOD__);
        $methodName = last($methodName);
        throw new \UnexpectedValueException('Please override ' . static::class . '::' . $methodName);
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
    
    public function checkValidityVersion(PackageEvent $event): bool
    {
        $versionParser = new VersionParser();
        $currentVersion = $this->getPackageVersion($event);
        $constraint = $this->getPackageRequirements($event);
        try {

            $constraintObject = $versionParser->parseConstraints($constraint);
            return $constraintObject->matches($versionParser->parseConstraints($currentVersion));

        } catch (\Exception $e) {
            
            return false;
        }

        return true;
    }

    public function getPackageVersion(PackageEvent $event): string
    {
        $operation = $event->getOperation();
    
        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage()->getVersion();
        }

        return $operation->getPackage()->getVersion();
    }

    protected function getAuthor(): string
    {
        return basename(dirname(__FILE__, 5))."/".basename(dirname(__FILE__, 4));
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
