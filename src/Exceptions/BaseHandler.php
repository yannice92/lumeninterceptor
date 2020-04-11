<?php


namespace Yannice92\LumenInterceptor\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LoggerInterface;

class BaseHandler extends ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param \Exception $e
     * @return void
     */
    public function report(Exception $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            return $e->report();
        }

        try {
            $logger = app(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e; // throw the original exception
        }
        $request = Request::createFromGlobals();
        $requestId = '';
        if ($request->hasHeader('X-Request-ID')) {
            $requestId = $request->header('X-Request-ID');
        }
        $logger->error($e, ['trx_id' => $requestId, 'exception' => $e]);
    }
}
