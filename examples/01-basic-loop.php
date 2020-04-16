<?php

declare(strict_types=1);

namespace Mitra\React\ProcessManager\Example;

use Mitra\React\ProcessManager\ReactProcessManager;
use React\EventLoop\Factory as ReactEventLoopFactory;

require __DIR__ . '/../vendor/autoload.php';

$loop = ReactEventLoopFactory::create();
$pm = new ReactProcessManager(2, $loop);

$loop->addPeriodicTimer(2.0, function () use ($pm) {
    echo sprintf('hello from process with pid %d! (parent: %d)' . PHP_EOL, $pm->getCurrentProcess()->getPid());
});

$pm->run();
