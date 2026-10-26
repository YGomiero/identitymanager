<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Plugin\IdentityManager\Hybridauth\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class Hybridauth extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onIdentityManagerProviderDefinitions' => 'onIdentityManagerProviderDefinitions'];
    }

    public function onIdentityManagerProviderDefinitions(\Joomla\Event\Event $event): void
    {
        $definitions = [];

        foreach ($this->discoverAdapters() as $adapter) {
            $meta = $this->adapterMeta($adapter);
            $options = [
                'adapter' => $adapter,
                '_ui' => [
                    'documentation_url' => $meta['documentation_url'],
                    'credential_hint' => $meta['credential_hint'],
                ],
            ];

            $definitions[] = [
                'name' => $this->humanName($adapter),
                'alias' => 'hybridauth-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $adapter)),
                'type' => 'hybridauth',
                'enabled' => 0,
                'scope' => $meta['scope'],
                'options_json' => json_encode($options, JSON_UNESCAPED_SLASHES),
            ];
        }

        $event->setArgument('result', array_merge((array) $event->getArgument('result', []), [$definitions]));
    }

    private function discoverAdapters(): array
    {
        $providerDir = JPATH_SITE . '/components/com_identitymanager/vendor/hybridauth/hybridauth/src/Provider';

        if (!is_dir($providerDir)) {
            return [];
        }

        $adapters = [];

        foreach (glob($providerDir . '/*.php') ?: [] as $file) {
            $adapter = basename($file, '.php');

            if ($adapter !== '') {
                $adapters[] = $adapter;
            }
        }

        sort($adapters, SORT_NATURAL | SORT_FLAG_CASE);

        return $adapters;
    }

    private function adapterMeta(string $adapter): array
    {
        $class = 'Hybridauth\\Provider\\' . $adapter;
        $this->loadAutoloader();
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

    private function loadAutoloader(): void
    {
        $autoload = JPATH_SITE . '/components/com_identitymanager/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }
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
