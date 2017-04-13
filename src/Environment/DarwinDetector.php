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
 * Class DarwinDetector
 * @package Zephir\Composer\Environment
 */
class DarwinDetector implements DetectorInterface
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
        return 'Mac OS';
    }

    /**
     * @return array
     * @throws EnvironmentException
     */
    public function check(): array
    {
        throw new EnvironmentException('Not implemented yet');
    }
}