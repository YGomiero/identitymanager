<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Provider;

\defined('_JEXEC') or die;

/** Validate the signature, issuer, audience, expiry, nonce and UserInfo subject. */
class OidcProvider extends OAuth2Provider
{
    public function exchangeCode(array $provider, string $code, string $redirectUri): array
    {
        $token = parent::exchangeCode($provider, $code, $redirectUri);

        $token['validated_claims'] = $this->validateIdToken($provider, (string) ($token['id_token'] ?? ''));

        return $token;
    }

    public function validateIdToken(array $provider, string $encoded): array
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        // Algorithm comes from administrator configuration, never from the JWT.
        $algorithm = (string) ($options['id_token_alg'] ?? 'RS256');
        if ($algorithm === 'HS256' && !empty($provider['client_secret'])) {
            $key = new \Firebase\JWT\Key($provider['client_secret'], 'HS256');
        } elseif ($algorithm === 'RS256' && !empty($provider['jwks_url'])) {
            $this->assertHttps($provider['jwks_url']);
            $response = $this->http($provider)->get($provider['jwks_url'], ['Accept' => 'application/json'], 15);
            if ((int) $response->getStatusCode() !== 200) { throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_OIDC'); }
            $jwks = json_decode((string) $response->getBody(), true) ?: [];
            $jwks['keys'] = array_values(array_filter($jwks['keys'] ?? [], static fn($k) => ($k['kty'] ?? '') === 'RSA' && ($k['use'] ?? 'sig') === 'sig' && ($k['alg'] ?? 'RS256') === 'RS256'));
            $key = \Firebase\JWT\JWK::parseKeySet($jwks, 'RS256');
        } else {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_OIDC_CONFIG');
        }
        $claims = (array) \Firebase\JWT\JWT::decode($encoded, $key);
        $audiences = (array) ($claims['aud'] ?? []);
        if (empty($provider['issuer']) || ($claims['iss'] ?? '') !== $provider['issuer']
            || !in_array($provider['client_id'], $audiences, true)
            || (count($audiences) > 1 && ($claims['azp'] ?? '') !== $provider['client_id'])
            || (isset($claims['azp']) && $claims['azp'] !== $provider['client_id'])
            || empty($claims['sub']) || empty($claims['exp']) || empty($claims['iat'])
            || $claims['iat'] > time() + 30 || $claims['exp'] <= time()
            || empty($provider['expected_nonce']) || !hash_equals($provider['expected_nonce'], (string) ($claims['nonce'] ?? ''))) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_OIDC');
        }
        return $claims;
    }

    public function fetchUser(array $provider, array $token): array
    {
        $profile = parent::fetchUser($provider, $token);
        if (($token['validated_claims']['sub'] ?? '') !== $profile['provider_user_id']) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_OIDC');
        }
        return $profile;
    }
}
