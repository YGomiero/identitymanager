<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Administrator\Service;

\defined('_JEXEC') or die;

/** Known Identity Manager endpoints require no remote discovery or JSON editing. */
class JoomlaSetup
{
    public static function baseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$parts || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_JOOMLA_URL', 400);
        }
        return $url;
    }

    public static function providerFields(string $url, array $options = []): array
    {
        $base = self::baseUrl($url);
        $endpoint = $base . '/?option=com_identitymanager&task=idp.';
        $options['id_token_alg'] = 'HS256';
        if (!isset($options['_ui']) || !is_array($options['_ui'])) { $options['_ui'] = []; }
        $options['_ui']['joomla_site'] = $base;
        return [
            'type' => 'oidc', 'issuer' => $base, 'authorization_url' => $endpoint . 'authorize',
            'token_url' => $endpoint . 'token', 'userinfo_url' => $endpoint . 'userinfo', 'jwks_url' => '',
            'scope' => 'openid profile email',
            'options_json' => json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    /** Only offer the simplified editor for known endpoints; preserve custom OIDC. */
    public static function siteForProvider(object $provider): ?string
    {
        if ($provider->type !== 'oidc' || !$provider->issuer) { return null; }
        try { $expected = self::providerFields($provider->issuer); } catch (\RuntimeException $e) { return null; }
        $options = json_decode((string) $provider->options_json, true) ?: [];
        if (($options['id_token_alg'] ?? '') !== 'HS256') { return null; }
        $scopes = preg_split('/\s+/', trim((string) $provider->scope));
        sort($scopes);
        if ($scopes !== ['email', 'openid', 'profile']) { return null; }
        foreach (['authorization_url', 'token_url', 'userinfo_url', 'jwks_url'] as $key) {
            if ((string) $provider->$key !== $expected[$key]) { return null; }
        }
        return $expected['issuer'];
    }
}
