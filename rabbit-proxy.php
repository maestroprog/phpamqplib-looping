<?php

use Esockets\Base\Configurator;
use Esockets\Client;
use Esockets\Debug\Log;
use Esockets\Socket\Ipv4Address;

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

Log::setEnv('RABBIT-PROXY');

$configurator = new Configurator(require 'rabbit-proxy-config.php');
$server       = $configurator->makeServer();
$server->connect(new Ipv4Address('127.0.0.1', 5673));

/** @var Client[] $rabbitClients */
$rabbitClients = [];
$proxyClients  = [];

$server->onFound(function (Client $client) use ($configurator, &$rabbitClients, &$proxyClients) {
    Log::log('Client connected ' . $client->getPeerAddress());
    $proxyClients[(string)$client->getPeerAddress()] = $client;
    $rabbitClient                                    = $configurator->makeClient();
    $rabbitClient->connect(new Ipv4Address('127.0.0.1', 5672));
    $rabbitClient->onReceive(function ($data) use ($client) {
        $client->send($data);
    });
    $rabbitClients[] = $rabbitClient;
    $client->onReceive(function ($data) use ($rabbitClient) {
        $rabbitClient->send($data);
    });
    $client->onDisconnect(function () use ($client, &$proxyClients) {
        unset($proxyClients[(string)$client->getPeerAddress()]);
        Log::log('Close connection with client ' . $client->getPeerAddress());
    });
});

$start = time();
$work  = true;

if (extension_loaded('pcntl')) {
    pcntl_signal(SIGINT, function (int $signo) use (&$work) {
        $work = false;
    }, false);
}

$tick = 0;
while ($work) {
    if (extension_loaded('pcntl')) {
        pcntl_signal_dispatch();
    }
    $server->find();
    foreach ($rabbitClients as $client) {
        $client->read();
    }
    $workTime = time() - $start;
    // break connection with rabbit, but connection with proxy is still alive
    if ($workTime > 10) {
        if (!($send ?? false)) {
            $server->sendToAll(' '); // send fake data, emulate broken socket
            $send = true;
        }
        foreach ($rabbitClients as $i => $client) {
            $client->disconnect();
            unset($rabbitClients[$i]);
        }
    }
    if ($tick++ % 10 === 0) {
        if ($workTime <= 40) {
            Log::log('Connection with rabbit is broken, ' . (40 - $workTime) . ' seconds left to problem reproducing...');
        }
        if ($workTime > 40) {
            if (\count($proxyClients)) {
                Log::log('DETECT PROBLEM! Proxy connection with rabbit has dead, but phpamqplib client is in looping: infinite reading from proxy...');
            } else {
                Log::log('GOOD WORK! Proxy connection with rabbit has dead, and phpamqplib client throws exception and normal exited: infinite reading from proxy problem is resolved...');
            }
        }
    }

    if ($workTime >= 119) {
        Log::log('Will stopped.');
        exit;
    }
}
