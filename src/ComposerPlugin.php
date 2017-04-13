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
use Symfony\Component\Process\Process;
use Zephir\Composer\Environment\DetectorFactory;
use Zephir\Composer\Environment\Requirement;
use Zephir\Composer\Exceptions\EnvironmentException;
use Zephir\Composer\Exceptions\NotAllowedException;
use Zephir\Composer\Exceptions\NotFoundException;
use Zephir\Composer\Support\Commands;

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
        $extensions = [];

        foreach ($this->getZephirConfigs() as $packageName => $config) {
            $status = $this->detectEnvironment();

            if (!$status) {
                throw new EnvironmentException(
                    'Can not compile ' . $packageName . ' sources. ' .
                    'Broken environment configuration.'
                );

                return;
            }

            $extension = $this->compileZephirExtension($config);
            $extensions[] = $this->saveExtension($extension);
        }

        $this->createIniFile($extensions);

        $this->io->write('Do not forget include ' . self::EXTENSIONS_INI . ' and restart server.');
    }

    /**
     * @return \Generator
     * @throws \RuntimeException
     */
    private function getZephirConfigs(): \Generator
    {
        $vendor  = $this->getVendorDir();
        $message = '  - Zephir <info>%s</info> (<comment>%s</comment>)';

        foreach ($this->collectRootPackageSources() as $package => $config) {
            $this->io->write(sprintf($message, $package, '~/' . $config));

            yield $package => $config;
        }

        foreach ($this->collectPackagesSources() as $package => $config) {
            $path = $package . '/' . $config;

            $this->io->write(sprintf($message, $package, '~/vendor/' . $path));

            yield $package => $vendor . '/' . $path;
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
     * @throws EnvironmentException
     */
    private function detectEnvironment(): bool
    {
        if ($this->checkEnvironment === false) {
            $status = true;

            $detector = DetectorFactory::create($this->composer);

            $this->io->write('<info>Checking ' . $detector->getName() . ' environment...</info>');

            /** @var Requirement $requirement */
            foreach ($detector->getRequirements() as $requirement) {
                $this->io->write('  - <comment>' . $requirement->getName() . '</comment>: ', false);

                $checked = $requirement->check($this->commands, $this->io);

                $this->io->overwrite(
                    '  - <comment>' . $requirement->getName() . '</comment>: ' .
                        ($checked ? '<info>OK</info>' : '<error>Fail</error>')
                );

                if (!$checked) {
                    $status = false;
                }
            }

            $this->checkEnvironment = true;

            return $status;
        }

        return true;
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
        $this->io->write('<info>Compiling...</info>');

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

        $this->io->write(
            '<info>' .
                'Copying extension ' .
                '<comment>' . $name . '</comment>' .
                    ' into ' .
                '<comment>' . $dest . '</comment>' .
            '</info>'
        );

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

        $this->io->write('<info>Creating (<comment>' . $ini . '</comment>)</info>');
    }
}
