<?php
/*
 * Copyright 2018 Google LLC
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *     * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *     * Neither the name of Google Inc. nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
namespace Google\ApiCore\Middleware;

use Google\ApiCore\ApiException;
use Google\ApiCore\ApiStatus;
use Google\ApiCore\Call;
use Google\ApiCore\RetrySettings;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Middleware that adds retry functionality.
 *
 * @internal
 */
class RetryMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $nextHandler;
    private RetrySettings $retrySettings;
    private ?float $deadlineMs;
    /** @var callable */
    private $delayHandler;

    



    private int $retryAttempts;

    public function __construct(
        callable $nextHandler,
        RetrySettings $retrySettings,
        $deadlineMs = null,
        $retryAttempts = 0,
        ?callable $delayHandler = null
    ) {
        $this->nextHandler = $nextHandler;
        $this->retrySettings = $retrySettings;
        $this->deadlineMs = $deadlineMs;
        $this->retryAttempts = $retryAttempts;
        $this->delayHandler = ($delayHandler ?? [$this, 'sleepMillis']);
    }

    /**
     * @param Call $call
     * @param array $options
     *
     * @return PromiseInterface
     */
    public function __invoke(Call $call, array $options)
    {
        $nextHandler = $this->nextHandler;

        if (!isset($options['timeoutMillis'])) {
            
            if (!$this->retrySettings->retriesEnabled() && $this->retrySettings->getNoRetriesRpcTimeoutMillis() > 0) {
                $options['timeoutMillis'] = $this->retrySettings->getNoRetriesRpcTimeoutMillis();
            } elseif ($this->retrySettings->getInitialRpcTimeoutMillis() > 0) {
                $options['timeoutMillis'] = $this->retrySettings->getInitialRpcTimeoutMillis();
            }
        }

        
        if ($this->retryAttempts > 0) {
            $options['retryAttempt'] = $this->retryAttempts;
        }

        
        if (!$this->retrySettings->retriesEnabled()) {
            return $nextHandler($call, $options);
        }

        
        if (is_null($this->deadlineMs)) {
            $this->deadlineMs = $this->getCurrentTimeMs() + $this->retrySettings->getTotalTimeoutMillis();
        }

        return $nextHandler($call, $options)->then(null, function ($e) use ($call, $options) {
            $retryFunction = $this->getRetryFunction();

            
            
            
            if (0 !== $this->retrySettings->getMaxRetries()
                && $this->retryAttempts >= $this->retrySettings->getMaxRetries()
            ) {
                throw $e;
            }
            
            
            if (!$retryFunction($e, $options)) {
                throw $e;
            }

            
            return $this->retry($call, $options, $e->getStatus());
        });
    }

    /**
     * @param Call $call
     * @param array $options
     * @param string $status
     *
     * @return PromiseInterface
     * @throws ApiException
     */
    private function retry(Call $call, array $options, string $status)
    {
        $delayMult = $this->retrySettings->getRetryDelayMultiplier();
        $maxDelayMs = $this->retrySettings->getMaxRetryDelayMillis();
        $timeoutMult = $this->retrySettings->getRpcTimeoutMultiplier();
        $maxTimeoutMs = $this->retrySettings->getMaxRpcTimeoutMillis();

        $delayMs = $this->retrySettings->getInitialRetryDelayMillis();
        $timeoutMs = $options['timeoutMillis'];
        $currentTimeMs = $this->getCurrentTimeMs();

        if ($currentTimeMs >= $this->deadlineMs) {
            throw new ApiException(
                'Retry total timeout exceeded.',
                \Google\Rpc\Code::DEADLINE_EXCEEDED,
                ApiStatus::DEADLINE_EXCEEDED
            );
        }

        $nextDelayMs = min($delayMs * $delayMult, $maxDelayMs);
        $timeoutMs = (int) min(
            $timeoutMs * $timeoutMult,
            $maxTimeoutMs,
            $this->deadlineMs - $this->getCurrentTimeMs()
        );

        $nextHandler = new RetryMiddleware(
            $this->nextHandler,
            $this->retrySettings->with([
                'initialRetryDelayMillis' => $nextDelayMs,
            ]),
            $this->deadlineMs,
            $this->retryAttempts + 1,
            $this->delayHandler,
        );

        
        $options['timeoutMillis'] = $timeoutMs;

        
        ($this->delayHandler)((int) $delayMs);

        return $nextHandler(
            $call,
            $options
        );
    }

    protected function getCurrentTimeMs()
    {
        return microtime(true) * 1000.0;
    }

    /**
     * This is the default retry behaviour.
     */
    private function getRetryFunction()
    {
        return $this->retrySettings->getRetryFunction() ??
            function (\Throwable $e, array $options): bool {
                
                
                
                if (!$e instanceof ApiException) {
                    return false;
                }

                if (!in_array($e->getStatus(), $this->retrySettings->getRetryableCodes())) {
                    return false;
                }

                return true;
            };
    }

    /**
     * @param int $millis
     */
    private function sleepMillis(int $millis)
    {
        usleep($millis * 1000);
    }
}
