<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

class ProviderDefinitionService
{
    public function getDefinitions(): array
    {
        $definitions = $this->discoverHybridauthDefinitions();

        PluginHelper::importPlugin('identitymanager');
        $results = [];

        try {
            $event = new \Joomla\Event\Event('onIdentityManagerProviderDefinitions');
            Factory::getApplication()->getDispatcher()->dispatch('onIdentityManagerProviderDefinitions', $event);
            $results = (array) $event->getArgument('result', []);
        } catch (\Throwable $e) {
            $results = [];
        }

        foreach ($results as $result) {
            foreach ((array) $result as $definition) {
                if (!is_array($definition) || empty($definition['alias']) || empty($definition['name'])) {
                    continue;
                }

                $definition['alias'] = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $definition['alias']));
                $definitions[$definition['alias']] = $this->normalize($definition);
            }
        }

        return $definitions;
    }

    public function getMissingDefinitions(): array
    {
        $definitions = $this->getDefinitions();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select($db->quoteName('alias'))
            ->from($db->quoteName('#__identitymanager_providers'));
        $db->setQuery($query);
        $existing = array_map('strval', $db->loadColumn() ?: []);

        return array_diff_key($definitions, array_flip($existing));
    }

    public function getDefinition(string $alias): ?array
    {
        $definitions = $this->getDefinitions();

        return $definitions[$alias] ?? null;
    }

    public function uniqueAlias(string $base): string
    {
        $alias = preg_replace('/[^a-z0-9_\-]/', '', strtolower($base)) ?: 'provider';
        $db = Factory::getContainer()->get('DatabaseDriver');
        $candidate = $alias;
        $i = 2;

        while (true) {
            $query = $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName('#__identitymanager_providers'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote($candidate));
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                return $candidate;
            }

            $candidate = $alias . '-' . $i++;
        }
    }

    private function normalize(array $definition): array
    {
        return [
            'name' => (string) $definition['name'],
            'alias' => (string) $definition['alias'],
            'type' => (string) ($definition['type'] ?? 'oauth2'),
            'enabled' => (int) ($definition['enabled'] ?? 0),
            'client_id' => (string) ($definition['client_id'] ?? ''),
            'authorization_url' => (string) ($definition['authorization_url'] ?? ''),
            'token_url' => (string) ($definition['token_url'] ?? ''),
            'userinfo_url' => (string) ($definition['userinfo_url'] ?? ''),
            'jwks_url' => (string) ($definition['jwks_url'] ?? ''),
            'issuer' => (string) ($definition['issuer'] ?? ''),
            'scope' => (string) ($definition['scope'] ?? ''),
            'options_json' => (string) ($definition['options_json'] ?? '{}'),
        ];
    }

    private function discoverHybridauthDefinitions(): array
    {
        if (!PluginHelper::getPlugin('identitymanager', 'hybridauth')) {
            return [];
        }

        $providerDir = JPATH_SITE . '/components/com_identitymanager/vendor/hybridauth/hybridauth/src/Provider';

        if (!is_dir($providerDir)) {
            return [];
        }

        $autoload = JPATH_SITE . '/components/com_identitymanager/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        $definitions = [];

        foreach (glob($providerDir . '/*.php') ?: [] as $file) {
            $adapter = basename($file, '.php');

            if ($adapter === '') {
                continue;
            }

            $meta = $this->hybridauthMeta($adapter);
            $alias = 'hybridauth-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $adapter));

            $definitions[$alias] = [
                'name' => $this->humanName($adapter),
                'alias' => $alias,
                'type' => 'hybridauth',
                'enabled' => 0,
                'client_id' => '',
                'authorization_url' => '',
                'token_url' => '',
                'userinfo_url' => '',
                'jwks_url' => '',
                'issuer' => '',
                'scope' => $meta['scope'],
                'options_json' => json_encode([
                    'adapter' => $adapter,
                    '_ui' => [
                        'documentation_url' => $meta['documentation_url'],
                        'credential_hint' => $meta['credential_hint'],
                    ],
                ], JSON_UNESCAPED_SLASHES),
            ];
        }

        ksort($definitions, SORT_NATURAL | SORT_FLAG_CASE);

        return $definitions;
    }

    private function hybridauthMeta(string $adapter): array
    {
        $class = 'Hybridauth\\Provider\\' . $adapter;
        $scope = '';
        $documentation = 'https://hybridauth.github.io/providers.html';

        if (class_exists($class)) {
            $defaults = (new \ReflectionClass($class))->getDefaultProperties();
            $scope = (string) ($defaults['scope'] ?? '');
            $documentation = (string) ($defaults['apiDocumentation'] ?? $documentation);
        }

        return [
            'scope' => $scope,
            'documentation_url' => $documentation,
            'credential_hint' => $this->credentialHint($adapter),
        ];
    }

    private function credentialHint(string $adapter): string
    {
        $hints = [
            'Twitter' => \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OAUTH_1_APPS_GENERALLY_USE_A_CONSUMER_KEY_AND_CONSUMER__8A21C'),
            'X' => \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DEPENDING_ON_THE_APP_TYPE_X_TWITTER_MAY_REQUIRE_OAUTH_A_D0083'),
            'OpenID' => \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OPENID_PROVIDERS_MAY_NOT_REQUIRE_CLIENT_CREDENTIALS_98FB1'),
            'WordPress' => \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_THIS_PRESET_TARGETS_WORDPRESS_COM_OAUTH_A_SELF_HOSTED_W_ACDDC'),
        ];

        return $hints[$adapter] ?? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CREATE_AN_APPLICATION_IN_THE_PROVIDER_DEVELOPER_CONSOLE_CA25C');
    }

    private function humanName(string $adapter): string
    {
        $names = [
            'AOLOpenID' => 'AOL OpenID',
            'AutoDesk' => 'Autodesk',
            'BitBucket' => 'Bitbucket',
            'BlizzardAPAC' => 'Blizzard APAC',
            'BlizzardEU' => 'Blizzard EU',
            'DeviantArt' => 'DeviantArt',
            'GitHub' => 'GitHub',
            'GitLab' => 'GitLab',
            'LinkedIn' => 'LinkedIn',
            'LinkedInOpenID' => 'LinkedIn OpenID',
            'MicrosoftGraph' => 'Microsoft Graph',
            'OktaOIDC' => 'Okta OIDC',
            'PaypalOpenID' => 'PayPal OpenID',
            'StackExchange' => 'Stack Exchange',
            'StackExchangeOpenID' => 'Stack Exchange OpenID',
            'TwitchTV' => 'Twitch TV',
            'WeChat' => 'WeChat',
            'WeChatChina' => 'WeChat China',
            'WindowsLive' => 'Windows Live',
            'WordPress' => 'WordPress',
        ];

        return $names[$adapter] ?? trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $adapter));
    }
}
