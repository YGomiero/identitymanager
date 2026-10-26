<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Provider;

\defined('_JEXEC') or die;

use Joomla\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;

class OAuth2Provider implements ProviderInterface
{
    public function getAuthorizationUrl(array $provider, string $state, string $nonce, string $redirectUri): string
    {
        $this->assertHttps($provider['authorization_url']);
        $query = [
            'client_id' => $provider['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $provider['scope'],
            'state' => $state,
        ];

        if (($provider['type'] ?? 'oauth2') === 'oidc') {
            $query['nonce'] = $nonce;
        }
        if (!empty($provider['code_verifier'])) {
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $provider['code_verifier'], true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        if (!empty($options['prompt'])) {
            $query['prompt'] = preg_replace('/[^a-zA-Z0-9_\- ]/', '', (string) $options['prompt']);
        } elseif (!empty($options['force_prompt_login'])) {
            $query['prompt'] = 'login';
        }

        return $provider['authorization_url'] . (str_contains($provider['authorization_url'], '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(array $provider, string $code, string $redirectUri): array
    {
        $this->assertHttps($provider['token_url']);
        $http = $this->http($provider);
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $provider['client_id'],
            'client_secret' => $provider['client_secret'] ?? '',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ];
        if (!empty($provider['code_verifier'])) { $body['code_verifier'] = $provider['code_verifier']; }
        $response = $http->post($provider['token_url'], http_build_query($body), ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'], 15);
        $data = json_decode((string) $response->getBody(), true) ?: [];

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || empty($data['access_token'])) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_TOKEN_EXCHANGE_FAILED_2E73B'));
        }

        return $data;
    }

    public function fetchUser(array $provider, array $token): array
    {
        $this->assertHttps($provider['userinfo_url']);
        $http = $this->http($provider);
        $headers = [
            'Authorization' => 'Bearer ' . $token['access_token'],
            'Accept' => 'application/json',
        ];
        $response = $http->get($provider['userinfo_url'], $headers, 15);
        $data = json_decode((string) $response->getBody(), true) ?: [];

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USER_INFO_REQUEST_FAILED_90F9E'));
        }

        if (($provider['alias'] ?? '') === 'github' && empty($data['email'])) {
            $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
            if (!empty($options['email_endpoint'])) {
                $this->assertHttps($options['email_endpoint']);
                $emailsResponse = $http->get($options['email_endpoint'], $headers);
                $emails = json_decode((string) $emailsResponse->getBody(), true) ?: [];
                foreach ($emails as $email) {
                    if (!empty($email['primary']) && !empty($email['email'])) {
                        $data['email'] = $email['email'];
                        $data['email_verified'] = !empty($email['verified']);
                        break;
                    }
                }
            }
        }

        $subject = (string) ($data['sub'] ?? $data['id'] ?? '');

        return [
            'provider_user_id' => $subject,
            'email' => (string) ($data['email'] ?? ''),
            'email_verified' => ($data['email_verified'] ?? $data['verified'] ?? false) === true,
            'username' => (string) ($data['preferred_username'] ?? $data['username'] ?? $data['login'] ?? ''),
            'display_name' => (string) ($data['name'] ?? $data['login'] ?? $data['displayName'] ?? ''),
            'avatar_url' => (string) ($data['picture'] ?? $data['avatar_url'] ?? ''),
            'raw' => $data,
        ];
    }

    /** A private CA bundle may be configured without disabling TLS verification. */
    protected function http(array $provider): \Joomla\Http\Http
    {
        $config = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        $options = ['follow_location' => false, 'userAgent' => 'IdentityManager/1.0', 'transport.curl' => [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]];
        if (!empty($config['ca_file'])) {
            $file = realpath((string) $config['ca_file']);
            if (!$file || !is_readable($file) || !is_file($file)) { throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_HTTPS'); }
            $options['curl.certpath'] = $file;
        }
        return (new HttpFactory())->getHttp($options, ['curl']);
    }

    /** Credentials and tokens are sent only to explicitly configured HTTPS URLs. */
    protected function assertHttps(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_HTTPS');
        }
    }
}
