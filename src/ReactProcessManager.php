<?php

declare(strict_types=1);

namespace Mitra\React\ProcessManager;

use React\EventLoop\LoopInterface;

final class ReactProcessManager
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var int
     */
    private $maxProcesses;

    /**
     * @var array<int>
     */
    private $processes;

    /**
     * @var bool
     */
    private $running = false;

    /**
     * @var Process
     */
    private $processData;

    /**
     * @var callable|null
     */
    private $processInterruptCallable;

    /**
     * @var callable|null
     */
    private $beforeFork;

    /**
     * @var callable|null
     */
    private $onFork;

    /**
     * @param int $maxProcesses The maximum number of processes to spawn
     * @param LoopInterface $loop The loop to run in parallel
     * @param callable|null $onFork Callable to be executed while a fork happens
     * @param callable|null $beforeFork Callable to be executed right before a fork happens
     */
    public function __construct(int $maxProcesses, LoopInterface $loop, ?callable $onFork, ?callable $beforeFork)
    {
        $this->loop = $loop;
        $this->maxProcesses = $maxProcesses;
        $this->onFork = $onFork;
        $this->beforeFork = $beforeFork;
    }

    public function run()
    {
        if ($this->running) {
            throw new \RuntimeException('Process manager is already running');
        }

        for ($i = 1; $i <= $this->maxProcesses; $i++) {
            if (null !== $this->beforeFork) {
                ($this->beforeFork)();
            }

            $this->processes[] = $this->fork(function () {
                if (null !== $this->onFork) {
                    ($this->onFork)();
                }

                // Terminate process if SIGINT received (see line 103)
                $this->loop->addSignal(SIGINT, function () {
                    if (null !== $this->processInterruptCallable) {
                        ($this->processInterruptCallable)($this->processData);
                    }

                    $this->loop->stop();
                });
                $this->loop->run();
            });
        }

        // Terminate all processes by sending an interrupt signal to them..
        $terminateProcesses = function () {
            foreach ($this->processes as $pid) {
                posix_kill($pid, SIGINT);
                $status = 0;
                pcntl_waitpid($pid, $status);
            }

            $this->loop->stop();
        };

        // Terminate child processes on various signals
        $this->loop->addSignal(SIGUSR2, $terminateProcesses);
        $this->loop->addSignal(SIGINT, $terminateProcesses);
        $this->loop->addSignal(SIGTERM, $terminateProcesses);

        $this->loop->run();

        $this->running = true;
    }

    /**
     * Returns the running state of this process manager instance
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Returns information about the current process. There's the possibility to store process related data in this
     * object. (@see Process)
     * @return Process
     */
    public function getCurrentProcess(): Process
    {
        return $this->processData;
    }

    /**
     * Callable to be executed once a process finished running
     * @param callable|null $processInterruptCallable
     */
    public function setProcessInterruptCallable(?callable $processInterruptCallable): void
    {
        $this->processInterruptCallable = $processInterruptCallable;
    }

    private function fork(callable $child)
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Cant fork a process');
        } elseif ($pid > 0) {
            return $pid;
        } else {
            posix_setsid();
            $this->processData = new Process(posix_getpid());
            $child();
            exit(0);
        }
    }
}
