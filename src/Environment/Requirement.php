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

/**
 * Class Requirement
 * @package Zephir\Composer\Environment
 */
class Requirement
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var \Closure
     */
    private $fallback;

    /**
     * @var \Closure
     */
    private $detector;

    /**
     * Requirement constructor.
     * @param string $name
     * @param \Closure $detector
     */
    public function __construct(string $name, \Closure $detector)
    {
        $this->name = $name;
        $this->detector = $detector;

        $this->fallback = function (IOInterface $io) {
            return false;
        };
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param \Closure $then
     * @return Requirement
     */
    public function onError(\Closure $then): Requirement
    {
        $this->fallback = $then;

        return $this;
    }

    /**
     * @param Commands $commands
     * @param IOInterface $io
     * @return bool
     */
    public function check(Commands $commands, IOInterface $io): bool
    {
        $status = (bool)(($this->detector)($io, $commands));

        if (!$status) {
            $status = (bool)(($this->fallback)($io, $commands));
        }

        return $status;
    }
}
