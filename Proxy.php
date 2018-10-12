<?php

use Esockets\Base\AbstractProtocol;
use Esockets\Base\CallbackEventListener;
use Esockets\Base\Event;
use Esockets\Base\IoAwareInterface;
use Esockets\Base\PingPacket;

/**
 * Фейковый протокол, использующийся как обёртка поверх TCP или UDP.
 */
final class Proxy extends AbstractProtocol implements \Esockets\Base\PingSupportInterface
{
    protected $eventReceive;
    private $pingReceived;
    private $pongReceived;

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

    /**
     * Выполняет команду "пинг",
     * т.е. отправляет пинг-пакет удаленному сервису.
     * Функция не ждёт ответа от удаленного сервиса,
     * и ничего не возвращает.
     * Но класс, реализующий данный интерфейс,
     * при принятии Pong пакета должен вызывать специальный callback,
     * который передаётся в функцию @see PingSupportInterface::pong.
     *
     * @param PingPacket $pingPacket
     *
     * @return void
     */
    public function ping(PingPacket $pingPacket): void
    {
        \call_user_func($this->pingReceived, $pingPacket);
        \call_user_func($this->pongReceived, PingPacket::response($pingPacket->getValue()));
    }

    /**
     * Назначает кастомный обработчик для получения ping пакета.
     *
     * @param callable $pingReceived
     *
     * @return void
     */
    public function onPingReceived(callable $pingReceived): void
    {
        $this->pingReceived = $pingReceived;
    }

    /**
     * Назначает специальный callback-обработчик пакетов "понг" от удаленного сервиса.
     * В данный callback будет передан один параметр типа @see PingPacket
     *
     * @param callable $pongReceived
     *
     * @return void
     */
    public function pong(callable $pongReceived): void
    {
        $this->pongReceived = $pongReceived;
    }
}
