<?php

use Esockets\Base\AbstractProtocol;
use Esockets\Base\CallbackEventListener;
use Esockets\Base\Event;
use Esockets\Base\IoAwareInterface;

/**
 * Фейковый протокол, использующийся как обёртка поверх TCP или UDP.
 */
final class Proxy extends AbstractProtocol
{
    protected $eventReceive;

    /**
     * @inheritdoc
     */
    public function __construct(IoAwareInterface $provider)
    {
        parent::__construct($provider);

        $this->eventReceive = new Event();
    }

    /**
     * @inheritdoc
     */
    public function read(): bool
    {
        if (null !== ($data = $this->provider->read($this->provider->getMaxPacketSizeForWriting() ?: 1024, false))) {
            $this->eventReceive->call($data);

            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function send($data): bool
    {
        $maxSize = $this->provider->getMaxPacketSizeForWriting();
        if (strlen($data) > $maxSize && $maxSize > 0) {
            $packets = str_split($data, $maxSize);
            array_walk($packets, function (string $packet) {
                $this->provider->send($packet);
            });
        }

        return $this->provider->send($data);
    }

    /**
     * @inheritdoc
     */
    public function returnRead()
    {
        return $this->provider->read($this->provider->getMaxPacketSizeForWriting() ?: 1024, false);
    }

    /**
     * @inheritdoc
     */
    public function onReceive(callable $callback): CallbackEventListener
    {
        return $this->eventReceive->attachCallbackListener($callback);
    }
}
