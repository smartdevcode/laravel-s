# LaravelS 配置项

- `listen_ip`：`string` 监听的IP，监听本机`127.0.0.1`(IPv4) `::1`(IPv6)，监听所有地址 `0.0.0.0`(IPv4) `::`(IPv6)， 默认`127.0.0.1`。

- `listen_port`：`int` 监听的端口，如果端口小于1024则需要`root`权限，default `5200`。

- `enable_gzip`：`bool` 当通过LaravelS响应数据时，是否启用gzip压缩响应的内容，依赖库[zlib](https://zlib.net/)，通过命令`php --ri swoole|grep zlib`检查gzip是否可用。如果开启则会自动加上头部`Content-Encoding`，默认`false`。如果存在代理服务器（例如Nginx），建议代理服务器开启gzip，LaravelS关闭gzip，避免重复gzip压缩。

- `server`：`string` 当通过LaravelS响应数据时，设置HTTP头部`Server`的值，若为空则不设置，default `LaravelS`。

- `handle_static`：`bool` 是否开启LaravelS处理静态资源(要求 `Swoole >= 1.7.21`，若`Swoole >= 1.9.17`则由Swoole自己处理)，默认`false`，建议Nginx处理静态资源，LaravelS仅处理动态资源。静态资源的默认路径为`base_path('public')`，可通过修改`swoole.document_root`变更。

- `inotify_reload.enable`：`bool` 是否开启`Inotify Reload`，用于当修改代码后实时Reload所有worker进程，依赖库[inotify](http://pecl.php.net/package/inotify)，通过命令`php --ri inotify`检查是否可用，默认`false`，`建议仅开发环境开启`。
 
- `inotify_reload.file_types`：`array` `Inotify` 监控的文件类型，默认有`.php`。 

- `inotify_reload.log`：`bool` 是否输出Reload的日志，默认`true`。

- `websocket.enable`：`bool` 是否启用Websocket服务器。启用后Websocket服务器监听的IP和端口与Http服务器相同，默认`false`。

- `websocket.handler`：`string` Websocket逻辑处理的类名，需实现接口`WebsocketHandlerInterface`，参考[示例](https://github.com/hhxsv5/laravel-s/blob/master/README-CN.md#启用websocket服务器)

- `swoole`：`array` 请参考[Swoole配置项](https://wiki.swoole.com/wiki/page/274.html)

- `events`：`array` 自定义的异步事件和监听的绑定列表，参考[示例](https://github.com/hhxsv5/laravel-s/blob/master/README-CN.md#自定义的异步事件)