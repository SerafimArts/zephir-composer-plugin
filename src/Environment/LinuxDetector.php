<?php
/**
 * This file is part of zephir-composer-plugin package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Zephir\Composer\Environment;

use Composer\IO\IOInterface;
use Zephir\Composer\Support\Commands;
use Zephir\Composer\Exceptions\EnvironmentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Class LinuxDetector
 * @package Zephir\Composer\Environment
 */
class LinuxDetector extends AbstractDetector
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Linux';
    }

    /**
     * @return \Traversable|Requirement[]
     * @throws EnvironmentException
     * @throws RuntimeException
     * @throws LogicException
     * @throws \RuntimeException
     */
    public function getRequirements(): \Traversable
    {
        // Compiler
        yield (new Requirement('GNU Compiler Collection', function () {
            return $this->hasBinary('gcc');
        }))
            ->onError($this->installDependency('gcc'));

        yield new Requirement('link', function () {
            return $this->hasBinary('link');
        });

        yield new Requirement('make', function () {
            return $this->hasBinary('make');
        });

        yield new Requirement('sed', function () {
            return $this->hasBinary('sed');
        });

        yield (new Requirement('re2c', function () {
            return $this->hasBinary('re2c');
        }))
            ->onError($this->installDependency('re2c'));

        // PHP SDK
        yield (new Requirement('phpize', function () {
            return $this->hasBinary('phpize');
        }))

            ->onError($this->installDependency('php-dev'));

        // Zephir
        yield (new Requirement('zephir', function () {
            return $this->hasBinary('zephir');
        }))
            ->onError(function (IOInterface $io) {
                $io->write('Install zephir first:');
                $io->write('  $ <comment>git clone git@github.com:phalcon/zephir.git</comment>');
                $io->write('  $ <comment>cd zephir</comment>');
                $io->write('  $ <comment>./install</comment>');

                return false;
            });
    }

    /**
     * @param Commands $commands
     * @param string $dependency
     * @return bool
     * @throws EnvironmentException
     * @throws RuntimeException
     * @throws LogicException
     */
    private function install(Commands $commands, string $dependency): bool
    {
        return $commands->run($this->installer($dependency)) === 0;
    }

    /**
     * @param string $dependency
     * @return string
     * @throws EnvironmentException
     */
    private function installer(string $dependency): string
    {
        switch (true) {
            case $this->hasBinary('apt-get'):
                return 'sudo apt-get install -y ' . $dependency;

            case $this->hasBinary('aptitude'):
                return 'sudo aptitude install -y ' . $dependency;

            case $this->hasBinary('yum'):
                return 'sudo yum install -y ' . $dependency;
        }

        throw new EnvironmentException('Could not find available installer');
    }

    /**
     * @return \Traversable
     */
    protected function getPaths(): \Traversable
    {
        yield from $this->pathEnvironment('PATH', ':');
    }

    /**
     * @param string $dependency
     * @return \Closure
     * @throws EnvironmentException
     * @throws RuntimeException
     * @throws LogicException
     * @throws \RuntimeException
     */
    private function installDependency(string $dependency): \Closure
    {
        return function (IOInterface $io, Commands $commands) use ($dependency) {
            if (strtolower($io->ask('Try to install the dependency? [Y/n]', 'y')) === 'y') {
                return $this->install($commands, $dependency);
            }

            return false;
        };
    }
}
