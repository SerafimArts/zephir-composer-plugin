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
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;
use Zephir\Composer\Environment\DetectorFactory;
use Zephir\Composer\Environment\Requirement;
use Zephir\Composer\Exceptions\EnvironmentException;
use Zephir\Composer\Exceptions\NotAllowedException;
use Zephir\Composer\Exceptions\NotFoundException;
use Zephir\Composer\Support\Commands;
use Zephir\Composer\Support\ConfigRepository;

/**
 * Class ComposerPlugin
 * @package Zephir\Composer
 */
class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    const EXTRA_CONFIG_KEY = 'zephir';

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
     * @var string
     */
    private $ext;

    /**
     * @var Commands
     */
    private $commands;

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
     * @throws NotAllowedException
     */
    public function onInit(BaseEvent $event)
    {
        $this->commands = new Commands($this->io);

        $this->ext = $this->getVendorDir() . DIRECTORY_SEPARATOR . 'ext';

        if (!@mkdir($this->ext) && !is_dir($this->ext)) {
            throw new NotAllowedException('Can not create ' . $this->ext . ' directory.');
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
     * @throws LogicException
     * @throws RuntimeException
     * @throws NotFoundException
     * @throws NotAllowedException
     */
    public function afterInstall(ScriptEvent $event)
    {
        $status = $this->detectEnvironment();

        if (!$status) {
            throw new EnvironmentException('Broken environment configuration.');
        }


        $extensions = [];

        foreach ($this->getZephirConfigs() as $package => $config) {
            $extension = $this->compileZephirExtension($config);
            $extensions[] = $this->saveExtension($extension);
        }

        $this->createIniFile($extensions);
    }

    /**
     * @return bool
     * @throws \LogicException
     * @throws EnvironmentException
     */
    private function detectEnvironment(): bool
    {
        $status = true;

        $detector = DetectorFactory::create($this->composer);

        $this->io->write('<info>Building dependencies for ' . $detector->getName() . '...</info>');

        /** @var Requirement $requirement */
        foreach ($detector->getRequirements() as $requirement) {
            $message = '  - Dependency <info>' . $requirement->getName() . '</info>: ';

            $this->io->overwrite($message, false);

            $checked = $requirement->check($this->commands, $this->io);

            if ($checked) {
                $this->io->overwrite($message . '<info>OK</info>', false);
            } else {
                $this->io->write('<error>Fail</error>');
            }

            if (!$checked) {
                $status = false;
            }

            usleep(50000);
        }

        $this->io->overwrite('', false);

        return $status;
    }

    /**
     * @return \Generator
     * @throws \RuntimeException
     */
    private function getZephirConfigs(): \Generator
    {
        $vendor = $this->getVendorDir();
        $message = '  - Zephir <info>%s</info> (<comment>%s</comment>)';

        foreach ($this->collectRootPackageSources() as $package => $config) {
            $this->io->write(sprintf($message, $package->getName(), '~/' . $config));

            yield $package => $config;
        }

        foreach ($this->collectPackagesSources() as $package => $config) {
            $path = $package->getName() . '/' . $config;

            $this->io->write(sprintf($message, $package->getName(), '~/vendor/' . $path));

            yield $package => $vendor . '/' . $path;
        }
    }

    /**
     * @return \Generator
     */
    private function collectRootPackageSources(): \Generator
    {
        $package = $this->composer->getPackage();
        $config = $this->extractExtraSection($package);

        if ($config !== null) {
            foreach ($config->getLibraries() as $library) {
                yield $package => $library;
            }
        }
    }

    /**
     * @param PackageInterface $package
     * @return ConfigRepository|null
     */
    private function extractExtraSection(PackageInterface $package)
    {
        $extra = $package->getExtra();

        if (isset($extra['zephir'])) {
            return new ConfigRepository($package, (array)$extra['zephir']);
        }

        return null;
    }

    /**
     * @return \Generator
     */
    private function collectPackagesSources(): \Generator
    {
        $repo = $this->composer->getRepositoryManager()->getLocalRepository();

        foreach ($repo->getPackages() as $package) {
            $config = $this->extractExtraSection($package);

            if ($config !== null) {
                foreach ($config->getLibraries() as $library) {
                    yield $package => $library;
                }
            }
        }
    }

    /**
     * @param string $config
     * @return string
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws LogicException
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
     * @throws LogicException
     * @throws RuntimeException
     */
    public function run(string $process, string $config): int
    {
        return $this->commands->run($process, dirname($config));
    }

    /**
     * @param string $dir
     * @return string
     * @throws NotFoundException
     */
    private function findExtension(string $dir): string
    {
        $items = array_merge(glob($dir . '/*.so'), glob($dir . '/*.dll'));

        if (!count($items)) {
            throw new NotFoundException('Could not find extension in ' . $dir);
        }

        return reset($items);
    }

    /**
     * @param string $ext
     * @return string
     * @throws NotAllowedException
     */
    private function saveExtension(string $ext): string
    {
        $name = basename($ext);
        $dest = $this->ext . '/' . $name;

        if (!@unlink($dest) && is_file($dest)) {
            throw new NotAllowedException('Could not delete previous version of ' . $name);
        }

        if (!copy($ext, $dest)) {
            throw new NotAllowedException('Could not create a new version of ' . $name);
        }

        return $name;
    }

    /**
     * @param array $extensions
     * @throws NotAllowedException
     */
    private function createIniFile(array $extensions)
    {
        $ini = $this->ext . '/' . self::EXTENSIONS_INI;

        if (!@unlink($ini) && is_file($ini)) {
            throw new NotAllowedException('Could not delete previous version of ' . self::EXTENSIONS_INI);
        }

        $iniBody = array_map(function (string $name) {
            return 'extension=./' . $name;
        }, $extensions);

        file_put_contents($ini, implode("\n", $iniBody));
    }
}
