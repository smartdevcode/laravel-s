<?php


namespace Hhxsv5\LaravelS\Swoole;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class Response implements ResponseInterface
{
    protected $swooleResponse;
    protected $laravelResponse;

    public function __construct(\swoole_http_response $swooleResponse, SymfonyResponse $laravelResponse)
    {
        $this->swooleResponse = $swooleResponse;
        $this->laravelResponse = $laravelResponse;
    }

    public function sendStatusCode()
    {
        $this->swooleResponse->status($this->laravelResponse->getStatusCode());
    }

    public function sendHeaders()
    {
        $this->swooleResponse->header('Server', 'LaravelS');
        foreach ($this->laravelResponse->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $this->swooleResponse->header($name, $value);
            }
        }
    }

    public function sendCookies()
    {
        foreach ($this->laravelResponse->headers->getCookies() as $cookie) {
            $this->swooleResponse->cookie(
                $cookie->getName(),
                urlencode($cookie->getValue()),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
    }

    public function gzip()
    {
        //TODO
    }

    public function send($acceptGzip = true)
    {
        $this->sendStatusCode();
        $this->sendHeaders();
        $this->sendCookies();
        $this->gzip();
        $this->sendContent();
    }
}