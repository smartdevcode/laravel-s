<?php

namespace Hhxsv5\LaravelS;

class HttpServer
{
    protected $sw;

    public function __construct($ip = '0.0.0.0', $port = 8841)
    {

        $this->sw = new \swoole_http_server($ip, $port);
    }

    public function run(LaravelS $s)
    {
        $this->sw->on('request', function ($request, $response) {
            $response->header('Content-Type', 'text/plain');
            $response->end('abc');
        });

        $this->sw->start();
    }

}