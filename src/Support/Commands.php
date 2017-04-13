<?php
/**
 * This file is part of zephir-composer-plugin package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Zephir\Composer\Support;

use Composer\IO\IOInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Class Commands
 * @package Zephir\Composer\Support
 */
class Commands
{
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * Commands constructor.
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * @param string $process
     * @param string|null $directory
     * @param bool $quiet
     * @return int
     * @throws LogicException
     * @throws RuntimeException
     */
    public function run(string $process, string $directory = null, bool $quiet = false): int
    {
        $this->io->write('      $ <comment>' . $process . '</comment>');

        $result = (int)(new Process($process, $directory))
            ->run(function (string $type, string $stdout) use ($quiet) {
                switch ($type) {
                    case Process::OUT:
                        $this->write($stdout);
                        break;
                    case Process::ERR:
                        if (!$quiet) {
                            $this->io->write('<error>' . $stdout . '</error>', false);
                        }
                        break;
                }
            });

        $this->io->overwrite('', false);

        return $result;
    }

    /**
     * @param string $message
     */
    private function write(string $message)
    {
        $lines = explode("\n", str_replace("\r", '', $message));

        foreach ($lines as $line) {

            if ($line) {
                $this->io->overwrite($line, false);
            }
        }
    }
}
