<?php

declare(strict_types=1);

namespace GuzzleHttp\Handler;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
final class CurlVersion
{
    private const MIN_VERSION = '7.34.0';

    private const TLS_13_VERSION = '7.52.0';

    
    
    
    
    private const MULTIPLEX_VERSION = '7.65.2';

    
    
    
    
    
    
    
    private const HTTP_VERSION_REUSE_MATCH_VERSION = '7.77.0';

    private const HTTP_VERSION_REUSE_MATCH_REGRESSION = '8.11.0';

    private const HTTP_VERSION_REUSE_MATCH_RESTORED = '8.13.0';

    
    
    
    
    
    private const REQUIRED_HTTP2_MULTIPLEX_VERSION = '8.14.0';

    
    
    
    
    private const REQUIRED_HTTP3_MULTIPLEX_VERSION = '8.13.0';

    
    
    
    
    
    
    
    private const HTTPS_PROXY_VERSION = '7.54.0';

    
    
    
    private const HTTP_3_VERSION = '7.88.0';

    private const PROTOCOLS_STR_VERSION = '7.85.0';

    private const HANDLER_SHARING_VERSION = '7.35.0';

    private const SSL_SESSION_SHARING_VERSION = '8.6.0';

    private const CONNECTION_SHARING_VERSION = '8.12.0';

    
    
    
    
    
    private const SHARE_CONNECTION_CACHE_VERSION = '7.57.0';

    
    
    
    
    
    public const SHARED_POOL_CONNECTION_CAP_VERSION = '8.22.0';

    
    
    
    private const PROXY_TLS_CREDENTIAL_REUSE_VERSION = '7.83.1';

    
    
    
    private const PROXY_CREDENTIAL_REUSE_VERSION = '8.20.0';

    
    
    
    private const SOCKS_PROXY_CREDENTIAL_REUSE_VERSION = '7.69.0';

    private const PROXY_HEADER_SEPARATION_VERSION = '7.37.0';

    
    
    
    private const PROXY_TUNNEL_VERSION = '7.54.0';

    /**
     * @var array{version: string, features: int}|false|null
     */
    private static $versionInfo;

    private function __construct()
    {
    }

    public static function supportsCurlHandler(): bool
    {
        $versionInfo = self::getVersionInfo();

        return \defined('CURL_VERSION_SSL')
            && \defined('CURL_SSLVERSION_TLSv1_2')
            && \defined('CURLMOPT_MAX_HOST_CONNECTIONS')
            && \defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')
            && null !== $versionInfo
            && version_compare($versionInfo['version'], self::MIN_VERSION, '>=')
            && 0 !== (\CURL_VERSION_SSL & $versionInfo['features']);
    }

    public static function supportsTls13(): bool
    {
        $version = self::get();

        return \defined('CURL_SSLVERSION_TLSv1_3')
            && null !== $version
            && version_compare($version, self::TLS_13_VERSION, '>=');
    }

    public static function supportsMultiplex(): bool
    {
        $version = self::get();

        return \defined('CURLOPT_PIPEWAIT')
            && null !== $version
            && version_compare($version, self::MULTIPLEX_VERSION, '>=');
    }

    public static function supportsHttpVersionReuseMatching(): bool
    {
        $version = self::get();

        if (null === $version || version_compare($version, self::HTTP_VERSION_REUSE_MATCH_VERSION, '<')) {
            return false;
        }

        return version_compare($version, self::HTTP_VERSION_REUSE_MATCH_REGRESSION, '<')
            || version_compare($version, self::HTTP_VERSION_REUSE_MATCH_RESTORED, '>=');
    }

    public static function supportsRequiredHttp2Multiplex(): bool
    {
        $version = self::get();

        return \defined('CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE')
            && null !== $version
            && self::supportsHttp2()
            && version_compare($version, self::REQUIRED_HTTP2_MULTIPLEX_VERSION, '>=');
    }

    public static function supportsHttp2(): bool
    {
        $versionInfo = self::getVersionInfo();

        
        
        return \defined('CURL_VERSION_HTTP2')
            && null !== $versionInfo
            && self::supportsMultiplex()
            && 0 !== (\CURL_VERSION_HTTP2 & $versionInfo['features']);
    }

    public static function supportsHttp3(): bool
    {
        if (!\defined('CURL_VERSION_HTTP3') || !\defined('CURL_HTTP_VERSION_3') || !\defined('CURL_HTTP_VERSION_3ONLY')) {
            return false;
        }

        $versionInfo = self::getVersionInfo();
        if (null === $versionInfo || version_compare($versionInfo['version'], self::HTTP_3_VERSION, '<')) {
            return false;
        }

        return 0 !== ((int) \constant('CURL_VERSION_HTTP3') & $versionInfo['features']);
    }

    public static function supportsRequiredHttp3Multiplex(): bool
    {
        $version = self::get();

        return self::supportsHttp3()
            && null !== $version
            && version_compare($version, self::REQUIRED_HTTP3_MULTIPLEX_VERSION, '>=');
    }

    public static function supportsHttpsProxy(): bool
    {
        $versionInfo = self::getVersionInfo();

        return \defined('CURL_VERSION_HTTPS_PROXY')
            && null !== $versionInfo
            && version_compare($versionInfo['version'], self::HTTPS_PROXY_VERSION, '>=')
            && 0 !== (\CURL_VERSION_HTTPS_PROXY & $versionInfo['features']);
    }

    public static function supportsHandlerSharing(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::HANDLER_SHARING_VERSION, '>=');
    }

    public static function ensureHandlerSharingSupported(): void
    {
        if (!self::supportsHandlerSharing()) {
            throw new InvalidArgumentException(\sprintf(
                'The "transport_sharing" option requires libcurl %s or higher for cURL share handles.',
                self::HANDLER_SHARING_VERSION
            ));
        }
    }

    public static function supportsSslSessionSharing(): bool
    {
        $versionInfo = self::getVersionInfo();

        return \defined('CURL_VERSION_SSL')
            && null !== $versionInfo
            && version_compare($versionInfo['version'], self::SSL_SESSION_SHARING_VERSION, '>=')
            && 0 !== (\CURL_VERSION_SSL & $versionInfo['features']);
    }

    public static function ensureSslSessionSharingSupported(): void
    {
        if (!self::supportsSslSessionSharing()) {
            throw new InvalidArgumentException(\sprintf(
                'The "transport_sharing" option requires libcurl %s or higher with SSL support for SSL session sharing.',
                self::SSL_SESSION_SHARING_VERSION
            ));
        }
    }

    public static function supportsConnectionSharing(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::CONNECTION_SHARING_VERSION, '>=');
    }

    public static function ensureConnectionSharingSupported(): void
    {
        if (!self::supportsConnectionSharing()) {
            throw new InvalidArgumentException(\sprintf(
                'The "transport_sharing" option requires libcurl %s or higher for persistent connection sharing.',
                self::CONNECTION_SHARING_VERSION
            ));
        }
    }

    public static function supportsSharedPoolConnectionCaps(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::SHARED_POOL_CONNECTION_CAP_VERSION, '>=');
    }

    public static function supportsShareConnectionCaches(): bool
    {
        $version = self::get();

        
        
        return null === $version
            || version_compare($version, self::SHARE_CONNECTION_CACHE_VERSION, '>=');
    }

    public static function supportsProxyTlsCredentialAwareConnectionReuse(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::PROXY_TLS_CREDENTIAL_REUSE_VERSION, '>=');
    }

    public static function supportsProxyCredentialAwareConnectionReuse(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::PROXY_CREDENTIAL_REUSE_VERSION, '>=');
    }

    public static function supportsSocksProxyCredentialAwareConnectionReuse(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::SOCKS_PROXY_CREDENTIAL_REUSE_VERSION, '>=');
    }

    public static function supportsProxyHeaderSeparation(): bool
    {
        $version = self::get();

        return null !== $version
            && version_compare($version, self::PROXY_HEADER_SEPARATION_VERSION, '>=')
            && \defined('CURLOPT_PROXYHEADER')
            && \defined('CURLOPT_HEADEROPT')
            && \defined('CURLHEADER_SEPARATE');
    }

    public static function supportsProtocolsStr(): bool
    {
        $version = self::get();

        return \defined('CURLOPT_PROTOCOLS_STR')
            && null !== $version
            && version_compare($version, self::PROTOCOLS_STR_VERSION, '>=');
    }

    public static function supportsProxyTunneling(): bool
    {
        $version = self::get();

        return \defined('CURLOPT_SUPPRESS_CONNECT_HEADERS')
            && null !== $version
            && version_compare($version, self::PROXY_TUNNEL_VERSION, '>=');
    }

    public static function ensureSupported(
        #[\SensitiveParameter]
        RequestInterface $request
    ): void {
        if (self::supportsCurlHandler()) {
            return;
        }

        $version = self::get();

        if (null === $version || version_compare($version, self::MIN_VERSION, '<')) {
            throw new ConnectException(\sprintf(
                'cURL %s or higher is required by the cURL handler; %s is installed.',
                self::MIN_VERSION,
                $version ?? 'an unknown version'
            ), $request);
        }

        if (!\defined('CURL_SSLVERSION_TLSv1_2')) {
            throw new ConnectException(\sprintf(
                'The PHP cURL extension must be built against cURL %s or higher to use the cURL handler.',
                self::MIN_VERSION
            ), $request);
        }

        if (!\defined('CURLMOPT_MAX_HOST_CONNECTIONS') || !\defined('CURLMOPT_MAX_TOTAL_CONNECTIONS')) {
            throw new ConnectException('The PHP cURL extension must expose CURLMOPT_MAX_HOST_CONNECTIONS and CURLMOPT_MAX_TOTAL_CONNECTIONS to use the cURL handler.', $request);
        }

        throw new ConnectException('The cURL handler requires libcurl SSL support.', $request);
    }

    private static function get(): ?string
    {
        $versionInfo = self::getVersionInfo();

        return null === $versionInfo ? null : $versionInfo['version'];
    }

    /**
     * @return array{version: string, features: int}|null
     */
    private static function getVersionInfo(): ?array
    {
        if (null === self::$versionInfo) {
            if (!\function_exists('curl_version')) {
                self::$versionInfo = false;
            } else {
                $versionInfo = \curl_version();
                self::$versionInfo = \is_array($versionInfo)
                    && isset($versionInfo['version'], $versionInfo['features'])
                    && \is_string($versionInfo['version'])
                    && \is_int($versionInfo['features'])
                        ? [
                            'version' => $versionInfo['version'],
                            'features' => $versionInfo['features'],
                        ]
                        : false;
            }
        }

        return false === self::$versionInfo ? null : self::$versionInfo;
    }
}
