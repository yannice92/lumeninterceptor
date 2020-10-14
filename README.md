Lumen Interceptor
==================
This library for write log using intercept request and response middleware

Installation
------------

Use [Composer] to install the package:

```
$ composer require yannice92/lumeninterceptor
```
Note
-----------
Make sure if you using php-fpm, add `log_limit = 100000` on your php-fpm pool, to prevent multiline output on stdout

Usage
-----------
1. Change extended class in app/Exceptions/Handler.php from `Laravel\Lumen\Exceptions\Handler as ExceptionHandler` with `use Yannice92\LumenInterceptor\Exceptions\BaseHandler;`
so it should be `class Handler extends BaseHandler`
2. Add `Yannice92\LumenInterceptor\Http\Middleware\LogRequestResponseMiddleware::class` in global middleware on bootstrap/app.php
   ```php
   $app->middleware([
        Yannice92\LumenInterceptor\Http\Middleware\LogRequestResponseMiddleware::class,
        ...
    ]);
   ```
3. Add `X-Request-ID` header for correlation Id

Authors
-------

* [Fernando Yannice]

[Fernando Yannice]: https://github.com/yannice92
