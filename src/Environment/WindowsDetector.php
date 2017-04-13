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

/**
 * Class WindowsDetector
 * @package Zephir\Composer\Environment
 */
class WindowsDetector implements DetectorInterface
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * WindowsDetector constructor.
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Windows';
    }

    /**
     * @return array
     * @throws EnvironmentException
     */
    public function check(): array
    {
        return [
            // Compiler
            'cl'        => $this->hasBinary('cl.exe'),

            // PHP Sources
            'buildconf' => $this->hasBinary('buildconf.bat'),
            'configure' => $this->hasBinary('configure.bat'),

            // PHP SDK
            'link'      => $this->hasBinary('link.exe'),
            'nmake'     => $this->hasBinary('nmake.exe'),
            'lib'       => $this->hasBinary('lib.exe'),
            'bison'     => $this->hasBinary('bison.exe'),
            'sed'       => $this->hasBinary('sed.exe'),
            're2c'      => $this->hasBinary('re2c.exe'),
            'zip'       => $this->hasBinary('zip.exe'),

            // PHP devpack
            'phpize'    => $this->hasBinary('phpize.bat'),

            // Zephir
            'zephir'    => $this->hasBinary('zephir.bat'),
        ];
    }

    /**
     * @param string $binary
     * @return bool
     */
    private function hasBinary(string $binary): bool
    {
        foreach ($this->getPaths() as $path) {
            if (is_file($path . DIRECTORY_SEPARATOR . $binary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    private function getPaths(): array
    {
        return array_merge(
            explode(
                ';',
                $_SERVER['PATH'] ?? $_SERVER['Path'] ?? ''
            ),
            [$_SERVER['PHP_SDK'] ?? $_SERVER['php_sdk'] ?? ''],
            [$_SERVER['PHP_DEVPACK'] ?? $_SERVER['php_devpack'] ?? '']
        );
    }
}