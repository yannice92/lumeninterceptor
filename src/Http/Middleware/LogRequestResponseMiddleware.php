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
    private $url = '';
    private $customerId;
    private $status;
    private $message;
    private $method;

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $factory = new PsrHttpFactory(
            new ServerRequestFactory(),
            new StreamFactory(),
            new UploadedFileFactory(),
            new ResponseFactory()
        );
        if ($request->hasHeader('x-request-id')) {
            $data['correlation_id'] = $request->header('x-request-id');
        } else {
            $data['correlation_id'] = Uuid::uuid();
        }
        $response->header('x-request-id', $data['correlation_id']);
        $psrServerRequest = $factory->createRequest($request);
        $psrServerResponse = $factory->createResponse($response);

        $data['request'] = $this->str($psrServerRequest, $data['correlation_id']);
        $data['response'] = $this->str($psrServerResponse, $data['correlation_id']);
        $time_end = microtime(true);
        $data['method'] = $this->method;
        $data['url'] = $this->url;
        $data['customer_id'] = $this->getCustomerId();
        $data['status'] = $this->status;
        $data['message'] = $this->message;
        $data['response_time'] = ($time_end - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000;
        Log::info('', $data);
        return $response;
    }

    /**
     * @param MessageInterface $message
     * @return array
     */
    private function str(MessageInterface $message, $logId)
    {
        //$data['correlation_id'] = $logId;
        if ($message instanceof RequestInterface) {
            //$data['type'] = 'request';
            $this->method = $message->getMethod();
            //$data['url'] =
            $this->url = $message->getRequestTarget();
            if (!$message->hasHeader('host')) {
                $data['host'] = $message->getUri()->getHost();
            }
        } elseif ($message instanceof ResponseInterface) {
            //$data['type'] = 'response';
            //$data['url'] = $this->url;
            $this->status = $message->getStatusCode();
            $this->message = $message->getReasonPhrase();
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
                        $this->setCustomerId($jwtDecode->getClaim('sub'));
                    } catch (\Exception $e) {
                    }
                }
            } else {
                $headers[$name] = implode(', ', $values);
            }
        }
        //$data['customer_id'] = $this->getCustomerId();
        $data['headers'] = json_encode($headers);
        $data['data'] = preg_replace("/\r|\n|\t/", "", $message->getBody());
        return $data;
    }

    /**
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * @param mixed $customerId
     */
    public function setCustomerId($customerId): void
    {
        $this->customerId = $customerId;
    }
}
