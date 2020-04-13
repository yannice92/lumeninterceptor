<?php

namespace Yannice92\LumenInterceptor\Http\Middleware;

use Closure;
use Faker\Provider\Uuid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Http\Factory\Guzzle\ResponseFactory;
use Http\Factory\Guzzle\ServerRequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Http\Factory\Guzzle\UploadedFileFactory;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Lcobucci\JWT\Parser;

class LogRequestResponseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $factory = new PsrHttpFactory(
            new ServerRequestFactory(),
            new StreamFactory(),
            new UploadedFileFactory(),
            new ResponseFactory()
        );
        $response = $next($request);
        if ($request->hasHeader('X-Request-ID')) {
            $data['correlation_id'] = $request->header('X-Request-ID');
        } else {
            $data['correlation_id'] = Uuid::uuid();
        }
        $response->header('X-Request-ID', $data['correlation_id']);
        $psrServerRequest = $factory->createRequest($request);
        $psrServerResponse = $factory->createResponse($response);
        $data['request'] = $this->str($psrServerRequest, $data['correlation_id']);
        $data['response'] = $this->str($psrServerResponse, $data['correlation_id']);
        Log::info('', $data['request']);
        Log::info('', $data['response']);
        return $response;
    }

    /**
     * @param MessageInterface $message
     * @return array
     */
    private function str(MessageInterface $message, $logId)
    {
        $data['correlation_id'] = $logId;
        if ($message instanceof RequestInterface) {
            $data['type'] = 'request';
            $data['method'] = $message->getMethod();
            $data['url'] = $message->getRequestTarget();
            if (!$message->hasHeader('host')) {
                $data['host'] = $message->getUri()->getHost();
            }
        } elseif ($message instanceof ResponseInterface) {
            $data['type'] = 'response';
            $data['status'] = $message->getStatusCode();
            $data['message'] = $message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }
        //$data['headers'] = json_encode($message->getHeaders());
        foreach ($message->getHeaders() as $name => $values) {
            if (strtolower($name) == 'authorization') {
                $token = explode('Bearer ', $values[0]);
                if (isset($token[1])) {
                    //decode
                    $parserJwt = new Parser();
                    try {
                        $jwtDecode = $parserJwt->parse($token[1]);
                        $data['customer_id'] = $jwtDecode->getClaim('sub');
                    } catch (\Exception $e) {
                    }
                }
            } else {
                $headers[$name] = implode(', ', $values);
            }
        }
        $data['headers'] = json_encode($headers);
        $data['data'] = preg_replace("/\r|\n|\t/", "", $message->getBody());
        return $data;
    }
}
