<?php

use Esockets\Debug\Log as _;

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

_::setEnv('server');

$configurator = new \Esockets\Base\Configurator(require 'rabbit-proxy-config.php');
$server       = $configurator->makeServer();
$server->connect(new \Esockets\Socket\Ipv4Address('127.0.0.1', 5673));

/** @var \Esockets\Client[] $rabbitClients */
$rabbitClients = [];

$server->onFound(function (\Esockets\Client $client) use ($configurator, &$rabbitClients) {
    $rabbitClient = $configurator->makeClient();
    $rabbitClient->connect(new \Esockets\Socket\Ipv4Address('127.0.0.1', 5672));
    $rabbitClient->onReceive(function ($data) use ($client) {
        $client->send($data);
    });
    $rabbitClients[] = $rabbitClient;
    $client->onReceive(function ($data) use ($rabbitClient) {
        $rabbitClient->send($data);
    });
});

$start = time();
$work  = true;

if (extension_loaded('pcntl')) {
    pcntl_signal(SIGINT, function (int $signo) use (&$work) {
        $work = false;
    }, false);
}
while ($work) {
    $server->find();
    if (extension_loaded('pcntl')) {
        pcntl_signal_dispatch();
    }
    foreach ($rabbitClients as $client) {
        $client->read();
    }
    $sended = $sended ?? false;
    if (time() - $start > 10) {
        if (!$sended) {
            $server->sendToAll(' ');
            $sended = true;
        }
        foreach ($rabbitClients as $i => $client) {
            $client->disconnect();
            unset($rabbitClients[$i]);
        }
    }
}
