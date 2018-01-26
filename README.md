# LaravelS
Speed up Laravel/Lumen by Swoole, 'S' means Swoole, Speed, High performance.

## Features

- High performance Swoole

- Built-in Http Server

- Memory resident

- Gracefully reload

- Simple & Out of the box

## Requirements

| Dependency | Requirement |
| -------- | -------- |
| [PHP](https://secure.php.net/manual/en/install.php) | `>= 5.5.9` |
| [Swoole](https://www.swoole.com/) | `>= 1.7.7` |
| [Laravel](https://laravel.com/)/[Lumen](https://lumen.laravel.com/) | `>= 5.1` |

## Install

1.Require package via Composer([packagist](https://packagist.org/packages/hhxsv5/laravel-s))

```Bash
composer require "hhxsv5/laravel-s:~1.0" -vvv
```

2.Add service provider

- `Laravel`: in `config/app.php` file
```PHP
'providers' => [
    //...
    Hhxsv5\LaravelS\Illuminate\LaravelSServiceProvider::class,
],
```

- `Lumen`: in `bootstrap/app.php` file
```PHP
$app->register(Hhxsv5\LaravelS\Illuminate\LaravelSServiceProvider::class);
```

3.Publish Configuration
```Bash
php artisan laravels:publish
```

`Special for Lumen`: you need to load this configuration manually in `bootstrap/app.php` file.
```PHP
$app->configure('laravels');
```

4.Change `config/laravels.php`: listen_ip, listen_port, [swoole's settings](https://wiki.swoole.com/wiki/page/274.html) ...

## Run Demo

| Command | Description |
| ---------------------------- |  ---------------------------- |
| `php artisan laravels start` | Start LaravelS |
| `php artisan laravels stop` | Stop LaravelS |
| `php artisan laravels restart` | Restart LaravelS |
| `php artisan laravels reload` | Reload all worker process(Contain your business & Laravel/Lumen codes), exclude master/manger process |
| `php artisan laravels publish` | Publish configuration file `laravels.php` of LaravelS into folder `config` |

```

## Listen Events

- `laravels.received_request` After LaravelS parsed `swoole_http_request` to `Illuminate\Http\Request`, before Laravel's Kernel handles this request.

```PHP
// Edit file `app/Providers/EventServiceProvider.php`, add the following code into method `boot`
$events->listen('laravels.received_request', function (\Illuminate\Http\Request $req) {
    $req->query->set('get_key', 'hhxsv5');// Change query of request
    $req->request->set('post_key', 'hhxsv5'); // Change post of request
    \Log::info('Received Request', [$req->getRequestUri(), $req->query->all(), $req->request->all()]);
});
```

- `laravels.generated_response` After Laravel's Kernel handled the request, before LaravelS parses `Illuminate\Http\Response` to `swoole_http_response`.

```PHP
$events->listen('laravels.generated_response', function (\Illuminate\Http\Request $req, \Symfony\Component\HttpFoundation\Response $rsp) {
    $rsp->header('header-key', 'hhxsv5');// Change header of response
    \Log::info('Generated Response', [$req->getRequestUri(), $rsp->getContent()]);
});
```

## Important Notices

- `global`, `static` variables which you declared are need to destroy(reset) manually.

- Never use `superglobal` variables, like $GLOBALS, $_SERVER, $_GET, $_POST, $_FILES, $_COOKIE, $_SESSION, $_REQUEST, $_ENV

- Infinitely appending element into `static`/`global` variable will lead to memory leak.
```PHP
// Some class
class Test
{
    public static $array = [];
    public static $string = '';
}
// In Controller
public function test(Request $req)
{
    // Memory leak
    Test::$array[] = $req->input('param1');
    Test::$string .= $req->input('param2');
}
```

## TODO

- gzip

## License

[MIT](https://github.com/hhxsv5/laravel-s/blob/master/LICENSE)
