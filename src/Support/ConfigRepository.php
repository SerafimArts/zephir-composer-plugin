<?php
/**
 * This file is part of zephir-composer-plugin package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Zephir\Composer\Support;

use Composer\Package\PackageInterface;

/**
 * Class ConfigRepository
 * @package Zephir\Composer\Support
 */
class ConfigRepository
{
    /**
     * @var array
     */
    private $configs;

    /**
     * @var PackageInterface
     */
    private $package;

    /**
     * ConfigRepository constructor.
     * @param array $configs
     */
    public function __construct(PackageInterface $package, array $configs)
    {
        $this->configs = $configs;
        $this->package = $package;
    }

    /**
     * @return \Traversable
     */
    public function getLibraries(): \Traversable
    {
        foreach ($this->configs as $library) {
            yield $library;
        }
    }
}
