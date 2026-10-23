<?php

namespace Google\Auth\Credentials;

use Google\Auth\CacheTrait;
use Google\Auth\GetUniverseDomainInterface;
use Google\Auth\HttpHandler\HttpClientCache;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * Trait for implementing Regional Access Boundaries (RAB) in Credentials.
 * @internal
 */
trait RegionalAccessBoundaryTrait
{
    use CacheTrait;

    private bool $enableRegionalAccessBoundary = false;

    /**
     * @param array<mixed> $headers
     * @return null|array{locations: array<string>, encodedLocations: string}
     */
    private function getRegionalAccessBoundary(
        string $universeDomain,
        callable $httpHandler,
        string $regionalAccessBoundaryUrl,
        array $headers,
    ): array|null {
        if (!$this->enableRegionalAccessBoundary) {
            
            return null;
        }

        if ($universeDomain !== GetUniverseDomainInterface::DEFAULT_UNIVERSE_DOMAIN) {
            
            return null;
        }

        if (array_key_exists('x-allowed-locations', $headers)) {
            
            return null;
        }

        
        if ($cached = $this->getCachedValue($this->getCacheKey() . ':rab')) {
            return $cached;
        }
        if (!array_key_exists('authorization', $headers)) {
            
            return null;
        }

        if ($this->getCachedValue($this->getCacheKey() . ':rab:cooldown')) {
            
            return null;
        }

        $regionalAccessBoundary = $this->lookupRegionalAccessBoundary(
            $httpHandler,
            $regionalAccessBoundaryUrl,
            $headers['authorization']
        );

        if (null === $regionalAccessBoundary) {
            
            return null;
        }

        
        $tbLifetime = 6 * 60 * 60; 
        $this->setCachedValue($this->getCacheKey() . ':rab', $regionalAccessBoundary, $tbLifetime);

        return $regionalAccessBoundary;
    }

    /**
     * @param array<mixed> $headers
     * @return array<mixed>
     */
    private function updateRegionalAccessBoundaryMetadata(
        array $headers,
        string $regionalAccessBoundaryUrl,
        string $universeDomain,
        ?callable $httpHandler,
    ): array {
        $httpHandler = $httpHandler
            ?: HttpHandlerFactory::build(HttpClientCache::getHttpClient());

        $regionalAccessBoundaryInfo = $this->getRegionalAccessBoundary(
            $universeDomain,
            $httpHandler,
            $regionalAccessBoundaryUrl,
            $headers
        );

        if ($regionalAccessBoundaryInfo) {
            $headers['x-allowed-locations'] = $regionalAccessBoundaryInfo['encodedLocations'];
        }

        return $headers;
    }

    /**
     * Return the RAB lookup URL.
     */
    private function buildRegionalAccessBoundaryLookupUrl(
        ?string $serviceAccountEmail = null,
        ?string $poolId = null,
        ?string $projectNumber = null,
    ): string {
        $baseUrl = 'https://iamcredentials.googleapis.com/v1';
        if ($serviceAccountEmail) {
            if (is_null($projectNumber) && is_null($poolId)) {
                return sprintf(
                    '%s/projects/-/serviceAccounts/%s/allowedLocations',
                    $baseUrl,
                    $serviceAccountEmail
                );
            }
        } elseif ($poolId) {
            if (is_null($projectNumber)) {
                
                return sprintf(
                    '%s/locations/global/workforcePools/%s/allowedLocations',
                    $baseUrl,
                    $poolId
                );
            }
            
            return sprintf(
                '%s/projects/%s/locations/global/workloadIdentityPools/%s/allowedLocations',
                $baseUrl,
                $projectNumber,
                $poolId
            );
        }

        throw new InvalidArgumentException('Must supply $serviceAccountEmail, $poolId, or both $poolId and $projectId');
    }

    /**
     * @param array<string> $authHeader
     * @return null|array{locations: array<string>, encodedLocations: string}
     */
    private function lookupRegionalAccessBoundary(
        callable $httpHandler,
        string $regionalAccessBoundaryUrl,
        array $authHeader
    ): array|null {
        $request = new Request('GET', $regionalAccessBoundaryUrl);
        $request = $request->withHeader('authorization', $authHeader);
        try {
            $response = $httpHandler($request);
        } catch (RequestException | NetworkExceptionInterface $e) {
            
            
            
            
            
            $this->initiateCooldown();
            return null;
        }

        $regionalAccessBoundary = json_decode((string) $response->getBody(), true);
        if (null === $regionalAccessBoundary) {
            
            
            
            
            $this->initiateCooldown();
            return null;
        }

        if (!array_key_exists('encodedLocations', $regionalAccessBoundary)) {
            
            
            
            
            $this->initiateCooldown();
            return null;
        }

        /** @var array{locations: array<string>, encodedLocations: string} $regionalAccessBoundary */
        return $regionalAccessBoundary;
    }

    private function initiateCooldown(): void
    {
        $cooldownKey = $this->getCacheKey() . ':rab:cooldown';
        $attempt = $this->getCachedValue($cooldownKey . ':attempt') ?? 0;

        $cooldownBackoff = 15 * 60; 
        $cooldownMax = 6 * 60 * 60; 
        $cooldownPeriod = min(++$attempt * $cooldownBackoff, $cooldownMax);
        $this->setCachedValue(
            $cooldownKey,
            true,
            (int) $cooldownPeriod
        );
        $this->setCachedValue(
            $cooldownKey . ':attempt',
            $attempt,
            (int) $cooldownPeriod * 2
        );
    }
}
