<?php

use Esockets\Base\Configurator;
use Esockets\Socket\SocketFactory;

require_once 'Proxy.php';

return [
    Configurator::CONNECTION_TYPE   => Configurator::CONNECTION_TYPE_SOCKET,
    Configurator::CONNECTION_CONFIG => [
        SocketFactory::SOCKET_DOMAIN   => AF_INET,
        SocketFactory::SOCKET_PROTOCOL => SOL_TCP,
        SocketFactory::WAIT_INTERVAL   => 100,
    ],
    Configurator::PROTOCOL_CLASS    => Proxy::class,
    Configurator::PING_INTERVAL     => 30,
];
