<?php

namespace Hhxsv5\LaravelS\Swoole\Socket;

abstract class TcpSocket implements PortInterface, TcpInterface
{
    /**
     * @var  \swoole_server_port
     */
    protected $swoolePort;

    public function __construct(\swoole_server_port $port)
    {
        $this->swoolePort = $port;
    }

    public function onConnect(\swoole_server $server, $fd, $reactorId)
    {
    }

    public function onClose(\swoole_server $server, $fd, $reactorId)
    {
    }

    abstract public function onReceive(\swoole_server $server, $fd, $reactorId, $data);
}