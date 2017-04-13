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
 * Interface DetectorInterface
 * @package Zephir\Composer\Environment
 */
interface DetectorInterface
{
    /**
     * DetectorInterface constructor.
     * @param Composer $composer
     */
    public function __construct(Composer $composer);

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return \Traversable|Requirement[]
     */
    public function getRequirements(): \Traversable;

    /**
     * @param string $binary
     * @return bool
     */
    public function hasBinary(string $binary): bool;
}
