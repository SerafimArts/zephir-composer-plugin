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
 * Class LinuxDetector
 * @package Zephir\Composer\Environment
 */
class LinuxDetector implements DetectorInterface
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
        return 'Linux';
    }

    /**
     * @return array
     * @throws EnvironmentException
     */
    public function check(): array
    {
        return [
            // Compiler
            'gcc'       => $this->hasBinary('gcc'),

            // PHP SDK
            'link'      => $this->hasBinary('link'),
            'make'      => $this->hasBinary('make'),
            'sed'       => $this->hasBinary('sed'),
            're2c'      => $this->hasBinary('re2c'),
            'phpize'    => $this->hasBinary('phpize'),

            // Zephir
            'zephir'    => $this->hasBinary('zephir'),
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
                ':',
                $_SERVER['PATH'] ?? $_SERVER['Path'] ?? ''
            )
        );
    }
}