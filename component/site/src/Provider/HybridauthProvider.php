<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Provider;

\defined('_JEXEC') or die;

class HybridauthProvider
{
    public function authenticate(array $provider, string $redirectUri, bool $fresh = false): array
    {
        $this->loadAutoloader();

        $adapterClass = $this->adapterClass($provider);

        if (!class_exists($adapterClass)) {
            throw new \RuntimeException('Hybridauth adapter not found: ' . $adapterClass);
        }

        $storage = new JoomlaStorage((string) $provider['alias']);
        if ($fresh) { $storage->clear(); }
        $adapter = new $adapterClass($this->config($provider, $redirectUri), null, $storage);
        $adapter->authenticate();

        $profile = $adapter->getUserProfile();
        $token = $adapter->getAccessToken();

        // A public GitHub email is not evidence of verified ownership.
        if ($adapter instanceof \Hybridauth\Provider\GitHub) {
            $profile->emailVerified = '';
            foreach ((array) $adapter->apiRequest('user/emails') as $email) {
                if (!empty($email->primary) && !empty($email->verified)) {
                    $profile->email = $profile->emailVerified = (string) $email->email;
                    break;
                }
            }
        }
        $storage->clear();

        return [
            'profile' => [
                'provider_user_id' => (string) ($profile->identifier ?? ''),
                'email' => (string) ($profile->email ?: $profile->emailVerified ?: ''),
                'email_verified' => (bool) ($profile->emailVerified ?? false),
                'display_name' => (string) ($profile->displayName ?? ''),
                'username' => '',
                'avatar_url' => (string) ($profile->photoURL ?? ''),
                'raw' => json_decode(json_encode($profile), true) ?: [],
            ],
            'token' => [
                'access_token' => (string) ($token['access_token'] ?? $token['token'] ?? ''),
                'refresh_token' => (string) ($token['refresh_token'] ?? ''),
                'expires_in' => (int) ($token['expires_in'] ?? 0),
                'raw' => $token,
            ],
        ];
    }

    private function adapterClass(array $provider): string
    {
        $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        $adapter = (string) ($options['adapter_class'] ?? $options['adapter'] ?? '');

        if ($adapter === '') {
            $adapter = (string) ($provider['hybridauth_adapter'] ?? $provider['alias'] ?? '');
        }

        $adapter = str_replace('Hybridauth\\Provider\\', '', $adapter);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $adapter)) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_ADAPTER');
        }

        return 'Hybridauth\\Provider\\' . preg_replace('/[^A-Za-z0-9_]/', '', $adapter);
    }

    private function config(array $provider, string $redirectUri): array
    {
        $options = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        $keys = [
            'id' => (string) ($provider['client_id'] ?? ''),
            'key' => (string) ($provider['client_id'] ?? ''),
            'secret' => (string) ($provider['client_secret'] ?? ''),
        ];

        $config = [
            'callback' => $redirectUri,
            'keys' => $keys,
        ];
        if (!empty($provider['scope'])) { $config['scope'] = $provider['scope']; }

        if (!empty($options['hybridauth_config']) && is_array($options['hybridauth_config'])) {
            $config = array_replace_recursive($config, $options['hybridauth_config']);
        }

        unset($config['_ui']);
        $config['callback'] = $redirectUri;
        $config['keys'] = $keys;
        $config['debug_mode'] = false;
        unset($config['debug_file']);
        $config['curl_options'][CURLOPT_SSL_VERIFYPEER] = true;
        $config['curl_options'][CURLOPT_SSL_VERIFYHOST] = 2;

        return $config;
    }

    private function loadAutoloader(): void
    {
        $autoloaders = [
            JPATH_SITE . '/components/com_identitymanager/vendor/autoload.php',
            JPATH_SITE . '/components/com_identitymanager/site/vendor/autoload.php',
            JPATH_LIBRARIES . '/vendor/autoload.php',
        ];

        foreach ($autoloaders as $autoloader) {
            if (is_file($autoloader)) {
                require_once $autoloader;
                return;
            }
        }

        throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_HYBRIDAUTH_IS_NOT_INSTALLED_RUN_COMPOSER_FOR_COM_IDENTI_23DF7'));
    }
}
