<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use GuzzleHttp\ProxyOptions;
use GuzzleHttp\ProxySelection;
use GuzzleHttp\Psr7;
use Psr\Http\Message\UriInterface;

/**
 * Resolves proxy configuration from the process environment with the same
 * semantics libcurl applies, shared by the built-in handlers: the cURL
 * handlers pin CURLOPT_PROXY and CURLOPT_NOPROXY explicitly so libcurl never
 * reads the environment itself, and the stream handler resolves the same way.
 *
 * @internal
 */
final class ProxyEnv
{
    private function __construct()
    {
    }

    /**
     * Resolves the proxy selection for a request, falling back to the proxy
     * environment variables when the proxy request option makes no decision.
     *
     * The environment no_proxy list is tokenized the way libcurl tokenizes
     * it and matched here with the same rules as the proxy option's "no"
     * list, so behavior does not depend on the installed libcurl's matcher.
     *
     * @param mixed $proxyOption
     */
    public static function resolveProxySelection(
        UriInterface $uri,
        #[\SensitiveParameter]
        $proxyOption
    ): ProxySelection {
        $selection = ProxyOptions::resolve($uri, $proxyOption);

        
        
        if ($selection->hasProxy() || $selection->shouldDisableProxy()) {
            return $selection;
        }

        $envProxy = self::getProxyForScheme($uri->getScheme());
        if ($envProxy === null) {
            return $selection;
        }

        $noProxy = self::getNoProxy();
        if ($noProxy !== null && ProxyOptions::isUriInNoProxy($uri, self::splitNoProxy($noProxy))) {
            return ProxySelection::bypassed();
        }

        
        return ProxySelection::proxy($envProxy);
    }

    /**
     * Resolves the proxy to use for the given request scheme.
     *
     * The lookup mirrors libcurl for the http and https schemes the handlers
     * accept: the lowercase scheme-specific variable first, its uppercase
     * variant next (except for "http", where uppercase HTTP_PROXY is never
     * read), then all_proxy/ALL_PROXY.
     *
     * @return string|null The proxy to use; null when the environment
     *                     configures none.
     */
    public static function getProxyForScheme(string $scheme): ?string
    {
        $scheme = Psr7\Utils::asciiToLower($scheme);
        $candidates = [$scheme.'_proxy'];
        if ($scheme !== 'http') {
            
            
            
            $candidates[] = Psr7\Utils::asciiToUpper($scheme).'_PROXY';
        }
        $candidates[] = 'all_proxy';
        $candidates[] = 'ALL_PROXY';

        foreach ($candidates as $name) {
            $value = self::getenv($name);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return string|null The no-proxy list; null when nothing is set.
     */
    public static function getNoProxy(): ?string
    {
        foreach (['no_proxy', 'NO_PROXY'] as $name) {
            $value = self::getenv($name);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Splits a no_proxy environment value into matchable entries.
     *
     * Entries may be separated by commas or blanks, the way libcurl tokenizes
     * the value.
     *
     * @return string[]
     */
    public static function splitNoProxy(string $noProxy): array
    {
        $entries = [];

        $split = \preg_split('/[\s,]+/', $noProxy);

        if ($split === false) {
            throw new \RuntimeException('Unable to split the no_proxy value: '.\preg_last_error_msg());
        }

        foreach ($split as $entry) {
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private static function getenv(string $name): ?string
    {
        
        
        
        
        if (\PHP_OS_FAMILY === 'Windows' && \PHP_SAPI !== 'cli') {
            return null;
        }

        
        
        
        $value = \getenv($name, true);

        
        
        
        return $value === false || $value === '' ? null : $value;
    }
}
