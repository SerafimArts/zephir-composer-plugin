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
 * Class AbstractDetector
 * @package Zephir\Composer\Environment
 */
abstract class AbstractDetector implements DetectorInterface
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * AbstractDetector constructor.
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
        return static::class;
    }

    /**
     * @param string $binary
     * @return bool
     */
    public function hasBinary(string $binary): bool
    {
        foreach ($this->getPaths() as $path) {
            if (is_file($path . DIRECTORY_SEPARATOR . $binary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Traversable
     */
    abstract protected function getPaths(): \Traversable;

    /**
     * @param string $name
     * @param string|null $splitBy
     * @return \Generator
     */
    protected function pathEnvironment(string $name, string $splitBy = null): \Generator
    {
        $name   = strtolower($name);
        $server = array_change_key_case($_SERVER, CASE_LOWER);

        $paths = $server[$name] ?? '';

        if ($paths) {
            if ($splitBy !== null) {
                foreach (explode($splitBy, $paths) as $path) {
                    yield $path;
                }
            } else {
                yield $paths;
            }
        }
    }
}
