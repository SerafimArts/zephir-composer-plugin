<?php
/**
 * This file is part of zephir-composer-plugin package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Zephir\Composer\Environment;

use Composer\Composer;
use Zephir\Composer\Exceptions\EnvironmentException;

/**
 * Class DetectorFactory
 * @package Zephir\Composer\Environment
 */
class DetectorFactory
{
    /**
     * @param Composer $composer
     * @return DetectorInterface
     * @throws \LogicException
     * @throws EnvironmentException
     */
    public static function create(Composer $composer): DetectorInterface
    {
        switch (true) {
            case self::isLinux():
                return new LinuxDetector($composer);

            case self::isWindows():
                throw new \LogicException('Windows environment does not support yet.');
                //

            case self::isDarwin():
                throw new \LogicException('MacOS environment does not support yet.');
                //
        }

        $error = sprintf('Can not detect environment. Invalid operation system %s', PHP_OS);
        throw new EnvironmentException($error);
    }

    /**
     * @return bool
     */
    private static function isWindows(): bool
    {
        return 0 === stripos(PHP_OS, 'win') ||
            0 === stripos(PHP_OS, 'cygwin');
    }

    /**
     * @return bool
     */
    private static function isLinux(): bool
    {
        $os = ['linux', 'unix', 'freebsd'];

        return in_array(strtolower(PHP_OS), $os, true);
    }

    /**
     * @return bool
     */
    private static function isDarwin(): bool
    {
        $os = ['darwin', 'mac'];

        return in_array(strtolower(PHP_OS), $os, true);
    }
}
