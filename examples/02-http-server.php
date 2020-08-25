<?php

declare(strict_types=1);

namespace Mitra\React\ProcessManager\Example;

use Mitra\React\ProcessManager\Process;
use Mitra\React\ProcessManager\ReactProcessManager;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory as ReactEventLoopFactory;
use React\Http\Response as ReactResponse;
use React\Http\Server as ReactHttpServer;
use React\Socket\Server as ReactSocketServer;

require __DIR__ . '/../vendor/autoload.php';

$socketAddress = '0.0.0.0:4242';

$loop = ReactEventLoopFactory::create();
$socket = new ReactSocketServer($socketAddress, $loop);
$pm = new ReactProcessManager(
    2,
    $loop,
    function () use ($socket) {
        $socket->resume();
    },
    function () use ($socket) {
        $socket->pause();
    }
);

$pm->onProcessInterruption(function (Process $processData): void {
    echo printf(
        'Process %d finished running, processed %d requests' . PHP_EOL,
        $processData->getPid(),
        $processData['processedRequests']
    );
});

$server = new ReactHttpServer(
    function (ServerRequestInterface $request) use ($pm) {
        $processData = $pm->getCurrentProcess();

        if (!isset($processData['processedRequests'])) {
            $processData['processedRequests'] = 0;
        }

        $processData['processedRequests'] += 1;
        echo printf('Request handled by process %d' . PHP_EOL, $processData->getPid());

        return new ReactResponse(
            200,
            [],
            sprintf("%s %s\n%s", $request->getMethod(), (string) $request->getUri(), (string) $request->getBody())
        );
    }
);

$server->listen($socket);

echo 'Server running on http://' , $socketAddress , PHP_EOL;

$pm->run();
