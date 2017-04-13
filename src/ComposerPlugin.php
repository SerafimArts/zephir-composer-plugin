<?php
/**
 * This file is part of zephir-composer-plugin package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Zephir\Composer;

use Composer\Composer;
use Composer\EventDispatcher\Event as BaseEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;
use Zephir\Composer\Environment\DetectorFactory;
use Zephir\Composer\Environment\EnvironmentException;

/**
 * Class ComposerPlugin
 * @package Zephir\Composer
 */
class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    const EXTENSIONS_INI = 'zephir_extensions.ini';

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var bool
     */
    private $checkEnvironment = false;

    /**
     * @var string
     */
    private $ext;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'init'                         => 'onInit',
            ScriptEvents::POST_INSTALL_CMD => 'afterInstall',
            ScriptEvents::POST_UPDATE_CMD  => 'afterInstall',
        ];
    }

    /**
     * @param BaseEvent $event
     * @throws EnvironmentException
     * @throws \RuntimeException
     */
    public function onInit(BaseEvent $event)
    {
        $this->ext = $this->getVendorDir() . DIRECTORY_SEPARATOR . 'ext';

        if (! @mkdir($this->ext) && ! is_dir($this->ext)) {
            throw new EnvironmentException('Can not create ' . $this->ext . ' directory.');
        }
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    private function getVendorDir(): string
    {
        return $this->composer->getConfig()->get('vendor-dir');
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @param ScriptEvent $event
     * @throws EnvironmentException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \InvalidArgumentException
     */
    public function afterInstall(ScriptEvent $event)
    {
        $extensions = [];

        foreach ($this->getZephirConfigs() as $packageName => $config) {
            $status = $this->detectEnvironment();

            if (! $status) {
                $this->io->writeError(
                    'Can not compile ' . $packageName . ' sources. ' .
                    'Broken environment configuration.'
                );

                return;
            }

            $this->io->write('Compile ' . $packageName . ' sources...');

            $extension = $this->compileZephirExtension($config);
            $extensions[] = $this->saveExtension($extension);
        }


        $ini = $this->ext . '/' . self::EXTENSIONS_INI;
        if (! @unlink($ini) && is_file($ini)) {
            throw new EnvironmentException('Could not delete previous version of ' . self::EXTENSIONS_INI);
        }

        $this->io->write('Compiled extensions: ' . implode(', ', $extensions));

        $iniBody = array_map(function (string $name) {
            return 'extension=./' . $name;
        }, $extensions);
        file_put_contents($ini, implode("\n", $iniBody));

        $this->io->write('Adding a new extensions in ' . $ini);
        $this->io->write('Do not forget include ' . self::EXTENSIONS_INI . ' and restart server.');
    }

    /**
     * @return \Generator
     * @throws \RuntimeException
     */
    private function getZephirConfigs(): \Generator
    {
        $vendor = $this->getVendorDir();

        foreach ($this->collectRootPackageSources() as $package => $config) {
            $this->io->write($package . ' provides "' . $config . '"" zephir configuration.');
            yield $package => $config;
        }

        foreach ($this->collectPackagesSources() as $package => $config) {
            $this->io->write($package . ' provides "' . $config . '" zephir configuration.');
            yield $package => $vendor . '/' . $package . '/' . $config;
        }
    }

    /**
     * @return \Generator
     */
    private function collectRootPackageSources(): \Generator
    {
        yield from $this->extractExtraSection($this->composer->getPackage());
    }

    /**
     * @param PackageInterface $package
     * @return \Generator
     */
    private function extractExtraSection(PackageInterface $package): \Generator
    {
        $extra = $package->getExtra();

        if (isset($extra['zephir'])) {
            foreach ((array)$extra['zephir'] as $config) {
                yield $package->getName() => $config;
            }
        }
    }

    /**
     * @return \Generator
     */
    private function collectPackagesSources(): \Generator
    {
        $repo = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($repo->getPackages() as $package) {
            yield from $this->extractExtraSection($package);
        }
    }

    /**
     * @return bool
     * @throws Environment\EnvironmentException
     */
    private function detectEnvironment(): bool
    {
        if ($this->checkEnvironment === false) {
            $status = true;

            $detector = DetectorFactory::create($this->composer);

            $this->io->write('Checking environment for ' . $detector->getName() . '...');

            foreach ($detector->check() as $item => $exists) {
                $this->io->write(' - ' . $item . ': ' . ($exists ? 'OK' : 'Fail'));

                if (! $exists) {
                    $status = false;
                }
            }

            $this->checkEnvironment = true;

            return $status;
        }

        return false;
    }

    /**
     * @param string $config
     * @return string
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    private function compileZephirExtension(string $config)
    {
        if ($code = $this->run('zephir fullclean', $config)) {
            exit($code);
        }

        if ($code = $this->run('zephir compile', $config)) {
            exit($code);
        }


        $dir = dirname($config) . '/ext/modules';

        return $this->findExtension($dir);
    }

    /**
     * @param string $process
     * @param string $config
     * @return int
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     */
    private function run(string $process, string $config): int
    {
        $this->io->write($process);

        return (new Process($process, dirname($config)))
            ->run(function (string $type, string $stdout) {
                switch ($type) {
                    case Process::OUT:
                        return $this->io->write($stdout);
                    case Process::ERR:
                        return $this->io->writeError($stdout);
                }
            });
    }

    /**
     * @param string $dir
     * @return string
     * @throws \InvalidArgumentException
     */
    private function findExtension(string $dir): string
    {
        $items = array_merge(glob($dir . '/*.so'), glob($dir . '/*.dll'));

        if (! count($items)) {
            throw new \InvalidArgumentException('Could not find extension in ' . $dir);
        }

        return reset($items);
    }

    /**
     * @param string $ext
     * @return string
     * @throws EnvironmentException
     */
    private function saveExtension(string $ext): string
    {
        $name = basename($ext);
        $dest = $this->ext . '/' . $name;

        $this->io->write('Copying extension ' . $name . ' into ' . $dest . '...');

        if (! @unlink($dest) && is_file($dest)) {
            throw new EnvironmentException('Could not delete previous version of ' . $name);
        }

        if (! copy($ext, $dest)) {
            throw new EnvironmentException('Could not create a new version of ' . $name);
        }

        return $name;
    }
}