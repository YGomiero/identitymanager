<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Plugin\System\IdentityManager\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Application\AfterDispatchEvent;
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Event\Application\BeforeRespondEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class IdentityManager extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterDispatch' => 'onAfterDispatch',
            'onAfterRender' => 'onAfterRender',
            'onBeforeRespond' => 'onBeforeRespond',
        ];
    }

    public function onAfterDispatch(AfterDispatchEvent $event): void
    {
        $app = $event->getApplication();

        if (!$app->isClient('site') || $app->getDocument()->getType() !== 'html') {
            return;
        }

        $app->getDocument()->getWebAssetManager()->registerAndUseStyle(
            'com_identitymanager.site',
            'com_identitymanager/identitymanager.css'
        );
        $app->getDocument()->getWebAssetManager()->registerAndUseScript('com_identitymanager.icons', 'com_identitymanager/icons.js', [], ['defer' => true]);
    }

    public function onAfterRender(AfterRenderEvent $event): void
    {
        $this->injectButtons($event->getApplication());
    }

    public function onBeforeRespond(BeforeRespondEvent $event): void
    {
        $this->injectButtons($event->getApplication());
    }

    private function injectButtons($app): void
    {
        if (!$app->isClient('site') || $app->getDocument()->getType() !== 'html' || !$this->params->get('inject_buttons', 1)) {
            return;
        }

        $input = $app->getInput();
        $option = $input->getCmd('option');
        $view = $input->getCmd('view');
        $body = $app->getBody();

        if (!$app->getIdentity()->guest) {
            $this->injectConnectionsLink($app, $body);
            return;
        }

        // This form asks for local proof. Offering the same external login loops back here.
        $return = base64_decode((string) $input->get('return', '', 'BASE64'), true);
        if ($option === 'com_users' && $view === 'login'
            && is_string($return) && str_contains($return, 'task=auth.resumeRelink')
            && (new \Yann\Component\IdentityManager\Site\Service\AuthService())->pendingRelink()) {
            return;
        }

        if ($body === '' || str_contains($body, 'identitymanager-social')) {
            return;
        }

        $isUserScreen = $option === 'com_users' && in_array($view, ['login', 'registration'], true);
        $hasLoginModule = str_contains($body, 'name="username"') && str_contains($body, 'name="password"');

        if (!$isUserScreen && !$hasLoginModule) {
            return;
        }

        $buttons = $this->renderButtons();
        if ($buttons === '') {
            return;
        }

        $patterns = [
            '~(<form[^>]+id=["\']com-users-login__form["\'][\s\S]*?</form>)~i',
            '~(<form[^>]+class=["\'][^"\']*mod-login[^"\']*["\'][\s\S]*?</form>)~i',
            '~(<form[\s\S]*?<input[^>]+name=["\']option["\'][^>]+value=["\']com_users["\'][^>]*>[\s\S]*?</form>)~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $body = preg_replace_callback($pattern, static fn($match) => $match[1] . $buttons, $body, 1);
                $app->setBody($this->ensureCss($body));
                return;
            }
        }
    }

    /** Keep account management discoverable without requiring a dedicated menu item. */
    private function injectConnectionsLink($app, string $body): void
    {
        if ($body === '' || str_contains($body, 'identitymanager-manage')) {
            return;
        }

        $url = Route::_('index.php?option=com_identitymanager&view=connections');
        $label = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_IDENTITYMANAGER_CONNECTIONS');
        $link = '<p class="identitymanager-manage"><a href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
        $logout = '~(<form\\b[^>]*class=["\'][^"\']*\\b(?:mod-login-logout|com-users-logout__form)\\b[^"\']*["\'][^>]*>[\\s\\S]*?</form>)~i';

        if (preg_match($logout, $body)) {
            $app->setBody(preg_replace_callback($logout, static fn($match) => $match[1] . $link, $body, 1));
        } elseif ($app->input->getCmd('option') === 'com_users' && $app->input->getCmd('view') === 'profile') {
            $profile = '~(<div\\b[^>]*class=["\'][^"\']*\\bcom-users-profile\\b[^"\']*["\'][^>]*>)~i';
            $app->setBody(preg_replace_callback($profile, static fn($match) => $match[1] . $link, $body, 1));
        }
    }

    private function ensureCss(string $body): string
    {
        if (str_contains($body, '/media/com_identitymanager/css/identitymanager.css')) {
            return $body;
        }

        $link = '<link href="' . \Joomla\CMS\Uri\Uri::root(true) . '/media/com_identitymanager/css/identitymanager.css" rel="stylesheet">';

        if (str_contains($body, '</head>')) {
            return str_replace('</head>', $link . '</head>', $body);
        }

        return $body;
    }

    private function renderButtons(): string
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['name', 'alias', 'options_json']))
            ->from($db->quoteName('#__identitymanager_providers'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);
        $providers = $db->loadObjectList();

        if (!$providers) {
            return '';
        }

        $html = '<div class="identitymanager-social" aria-label="' . htmlspecialchars(\Joomla\CMS\Language\Text::_('PLG_SYSTEM_IDENTITYMANAGER_PROVIDERS'), ENT_QUOTES, 'UTF-8') . '">';
        $canRegister = \Yann\Component\IdentityManager\Site\Service\RegistrationPolicy::allowsCreation();
        $title = $canRegister ? 'PLG_SYSTEM_IDENTITYMANAGER_OR' : 'PLG_SYSTEM_IDENTITYMANAGER_LOGIN_ONLY';
        $html .= '<p class="identitymanager-social__title">' . \Joomla\CMS\Language\Text::_($title) . '</p>';
        if ($canRegister && (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_users')->get('useractivation', 0) !== 2) {
            $html .= '<p class="identitymanager-social__hint">' . \Joomla\CMS\Language\Text::_('PLG_SYSTEM_IDENTITYMANAGER_REGISTER_HINT') . '</p>';
        }
        $html .= '<div class="identitymanager-social__buttons">';

        foreach ($providers as $provider) {
            $url = Route::_('index.php?option=com_identitymanager&task=auth.start&provider=' . rawurlencode($provider->alias));
            $label = \Joomla\CMS\Language\Text::sprintf('PLG_SYSTEM_IDENTITYMANAGER_CONTINUE', htmlspecialchars($provider->name, ENT_QUOTES, 'UTF-8'));
            if (strtolower((string) ((json_decode($provider->options_json ?: '{}', true)['adapter'] ?? '') ?: $provider->alias)) === 'google') {
                $label = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_IDENTITYMANAGER_GOOGLE_SIGNIN');
            }
            $label = \Yann\Component\IdentityManager\Site\Service\ProviderIcon::render((array) $provider) . '<span>' . $label . '</span>';
            $html .= '<a class="identitymanager-button identitymanager-button--' . htmlspecialchars($provider->alias, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars(\Joomla\CMS\Language\Text::sprintf('PLG_SYSTEM_IDENTITYMANAGER_SIGNIN', $provider->name), ENT_QUOTES, 'UTF-8') . '" href="' . $url . '">' . $label . '</a>';
        }

        return $html . '</div></div>';
    }
}
