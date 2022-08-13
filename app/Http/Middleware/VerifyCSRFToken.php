<?php

declare(strict_types=1);

/**
 * This file is part of MaxPHP.
 *
 * @link     https://github.com/marxphp
 * @license  https://github.com/marxphp/max/blob/master/LICENSE
 */

namespace App\Http\Middleware;

use App\Exception\CSRFException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Max\Utils\collect;

class VerifyCSRFToken implements MiddlewareInterface
{
    /**
     * 排除，不校验CSRF Token.
     */
    protected array $except = [
        //        '/'
    ];

    /**
     * @throws CSRFException
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->isMethod('POST') && !collect($this->except)->first(function ($pattern) use ($request) {
                return $request->is($pattern);
            })) {
            if (is_null($previousToken = $request->getCookieParams()['X-XSRF-TOKEN'] ?? null)) {
                $this->abort();
            }

            $token = $request->getHeaderLine('X-CSRF-TOKEN') ?: $request->getHeaderLine('X-XSRF-TOKEN') ?: ($request->getParsedBody()['_token'] ?? null);

            if (is_null($token) || $token !== $previousToken) {
                $this->abort();
            }
        }

        return $handler->handle($request)->withCookie('X-XSRF-TOKEN', bin2hex(random_bytes(32)), time() + 9 * 3600);
    }

    /**
     * @throws CSRFException
     */
    protected function abort()
    {
        throw new CSRFException('CSRF token is invalid', 419);
    }
}