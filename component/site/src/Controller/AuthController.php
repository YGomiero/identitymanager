<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Yann\Component\IdentityManager\Site\Service\AccountLinkService;
use Yann\Component\IdentityManager\Site\Service\AuthService;
use Yann\Component\IdentityManager\Site\Service\LogService;
use Yann\Component\IdentityManager\Site\Provider\HybridauthProvider;

class AuthController extends BaseController
{
    public function start(): void
    {
        $app = Factory::getApplication();
        $alias = preg_replace('/[^a-z0-9_\-]/', '', strtolower($this->input->getCmd('provider')));
        $auth = new AuthService();
        $provider = $auth->getProvider($alias);
        $logger = new LogService();

        if (!$provider || !(int) $provider['enabled']) {
            $logger->write('provider_disabled', 'warning', 'COM_IDENTITYMANAGER_UI_PROVIDER_DISABLED_OR_MISSING_1E6BA', null, $provider ? (int) $provider['id'] : null, $provider['name'] ?? $alias);
            $this->redirectError('COM_IDENTITYMANAGER_ERROR_PROVIDER_DISABLED');
            return;
        }

        // A second click must not overwrite the flow awaiting local confirmation.
        if ($app->getIdentity()->guest && $auth->pendingRelink($alias)) {
            $this->redirectRelinkLogin();
            return;
        }

        if (($provider['type'] ?? '') === 'hybridauth') {
            try {
                $session = $app->getSession();
                $session->set('identitymanager.hybridauth.started.' . $alias, time());
                $session->set('identitymanager.flow.user.' . $alias, (int) $app->getIdentity()->id);
                $redirectUri = $auth->buildRedirectUri($alias);
                (new HybridauthProvider())->authenticate($provider, $redirectUri, true);
            } catch (\Throwable $e) {
                $logger->write('login_failed', 'error', $e->getMessage(), null, (int) $provider['id'], $provider['name']);
                $this->redirectError($e->getMessage());
            }
            return;
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(16));
        $session = $app->getSession();
        $session->set('identitymanager.oauth.state.' . $alias, $state);
        $session->set('identitymanager.oauth.nonce.' . $alias, $nonce);
        $session->set('identitymanager.flow.user.' . $alias, (int) $app->getIdentity()->id);
        $session->set('identitymanager.flow.started.' . $alias, time());
        $provider['code_verifier'] = bin2hex(random_bytes(32));
        $session->set('identitymanager.oauth.verifier.' . $alias, $provider['code_verifier']);

        $redirectUri = $auth->buildRedirectUri($alias);
        $url = $auth->providerAdapter($provider)->getAuthorizationUrl($provider, $state, $nonce, $redirectUri);
        $app->redirect($url);
    }

    public function callback(): void
    {
        $app = Factory::getApplication();
        $alias = preg_replace('/[^a-z0-9_\-]/', '', strtolower($this->input->getCmd('provider')));
        $state = (string) $this->input->get('state', '', 'raw');
        $code = (string) $this->input->get('code', '', 'raw');
        $logger = new LogService();
        $auth = new AuthService();
        $provider = $auth->getProvider($alias);

        try {
            if (!$provider || !(int) $provider['enabled']) {
                throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_PROVIDER_DISABLED');
            }

            // The callback may be repeated by a double click, history or reload.
            // Resume only the local challenge; never exchange a code or sign in here.
            if ($app->getIdentity()->guest && $auth->pendingRelink($alias)) {
                $this->redirectRelinkLogin();
                return;
            }

            if (($provider['type'] ?? '') === 'hybridauth') {
                $session = $app->getSession();
                if ((int) $session->get('identitymanager.hybridauth.started.' . $alias, 0) < time() - 600 || (int) $session->get('identitymanager.flow.user.' . $alias, -1) !== (int) $app->getIdentity()->id) {
                    $logger->write('state_invalid', 'error', 'COM_IDENTITYMANAGER_UI_HYBRIDAUTH_FLOW_WAS_NOT_STARTED_IN_THIS_SESSION_4360A', null, (int) $provider['id'], $provider['name']);
                    throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_STATE_INVALID');
                }

                $redirectUri = $auth->buildRedirectUri($alias);
                $result = (new HybridauthProvider())->authenticate($provider, $redirectUri);
                $session->clear('identitymanager.hybridauth.started.' . $alias);
                $profile = $result['profile'];
                $token = $result['token'];

                if (empty($profile['provider_user_id'])) {
                    throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EXTERNAL_PROVIDER_DID_NOT_RETURN_A_USER_ID_91F82'));
                }

                $userId = $auth->completeCallback($provider, $profile, $token);
                if ($userId <= 0) {
                    $this->redirectRelinkLogin();
                    return;
                }

                $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($userId);
                $app->getSession()->set('identitymanager.login_ticket', ['user_id' => $userId, 'expires' => time() + 30]);
                if (!$app->login(['username' => $user->username, 'password' => ''], ['identitymanager_user_id' => $userId])) {
                    throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LOGIN_FAILED');
                }
                $auth->clearRelinkReturn();
                $app->redirect(Route::_('index.php?option=com_identitymanager&view=connections', false));
                return;
            }

            $session = $app->getSession();
            $expectedState = (string) $session->get('identitymanager.oauth.state.' . $alias, '');
            $nonce = (string) $session->get('identitymanager.oauth.nonce.' . $alias, '');
            $provider['code_verifier'] = (string) $session->get('identitymanager.oauth.verifier.' . $alias, '');
            $session->clear('identitymanager.oauth.verifier.' . $alias);
            $session->clear('identitymanager.oauth.state.' . $alias);
            $session->clear('identitymanager.oauth.nonce.' . $alias);

            if ($state === '' || !hash_equals($expectedState, $state)
                || (int) $session->get('identitymanager.flow.started.' . $alias, 0) < time() - 600
                || (int) $session->get('identitymanager.flow.user.' . $alias, -1) !== (int) $app->getIdentity()->id) {
                $logger->write('state_invalid', 'error', 'COM_IDENTITYMANAGER_UI_OAUTH_STATE_MISMATCH_C4764', null, (int) $provider['id'], $provider['name']);
                throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_STATE_INVALID');
            }

            if ($code === '') {
                $logger->write('callback_error', 'error', 'COM_IDENTITYMANAGER_UI_CALLBACK_DID_NOT_INCLUDE_AN_AUTHORIZATION_CODE_0D048', null, (int) $provider['id'], $provider['name']);
                throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EXTERNAL_PROVIDER_DID_NOT_RETURN_AN_AUTHORIZATION_CODE_8859B'));
            }

            $provider['expected_nonce'] = $nonce;
            $adapter = $auth->providerAdapter($provider);
            $redirectUri = $auth->buildRedirectUri($alias);
            $token = $adapter->exchangeCode($provider, $code, $redirectUri);
            $profile = $adapter->fetchUser($provider, $token);

            if (empty($profile['provider_user_id'])) {
                throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EXTERNAL_PROVIDER_DID_NOT_RETURN_A_USER_ID_91F82'));
            }

            $userId = $auth->completeCallback($provider, $profile, $token);
            if ($userId <= 0) {
                $this->redirectRelinkLogin();
                return;
            }

            $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($userId);
            $app->getSession()->set('identitymanager.login_ticket', ['user_id' => $userId, 'expires' => time() + 30]);
                if (!$app->login(['username' => $user->username, 'password' => ''], ['identitymanager_user_id' => $userId])) {
                    throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LOGIN_FAILED');
                }
            $auth->clearRelinkReturn();
            $app->redirect(Route::_('index.php?option=com_identitymanager&view=connections', false));
        } catch (\Throwable $e) {
            $app->getSession()->clear('identitymanager.hybridauth.started.' . $alias);
            $app->getSession()->clear('identitymanager.hybridauth.' . $alias);
            $logger->write('callback_error', 'error', $e->getMessage(), null, $provider ? (int) $provider['id'] : null, $provider['name'] ?? $alias);
            $this->redirectError($e->getMessage());
        }
    }

    public function resumeRelink(): void
    {
        $app = Factory::getApplication();
        $session = $app->getSession();
        $auth = new AuthService();
        $pending = $auth->pendingRelink();
        $user = $app->getIdentity();
        $auth->clearRelinkReturn();

        if (!$pending) {
            $this->redirectError('COM_IDENTITYMANAGER_ERROR_RELINK_EXPIRED');
            return;
        }

        if ($user->guest) {
            $this->redirectRelinkLogin();
            return;
        }

        if ((int) ($pending['user_id'] ?? 0) !== (int) $user->id) {
            $session->clear('identitymanager.relink.pending');
            $this->redirectError('COM_IDENTITYMANAGER_ERROR_RELINK_WRONG_ACCOUNT');
            return;
        }

        $provider = $auth->getProvider((string) $pending['provider_alias']);

        if (!$provider || !(int) $provider['enabled']) {
            $session->clear('identitymanager.relink.pending');
            $this->redirectError('COM_IDENTITYMANAGER_ERROR_PROVIDER_DISABLED');
            return;
        }

        (new AccountLinkService())->link((int) $user->id, $provider, $pending['profile'], $pending['token']);
        $message = ($pending['reason'] ?? '') === 'existing_email'
            ? 'COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_LINKED_D9A51'
            : 'COM_IDENTITYMANAGER_UI_REVOKED_EXTERNAL_IDENTITY_LINKED_AGAIN_AFTER_LOCAL_LOGI_7438D';
        (new LogService())->write('account_linked', 'success', $message, (int) $user->id, (int) $provider['id'], $provider['name']);
        $session->clear('identitymanager.relink.pending');

        $app->enqueueMessage(Text::_('COM_IDENTITYMANAGER_CONNECTION_RELINKED'), 'message');
        $app->redirect(Route::_('index.php?option=com_identitymanager&view=connections', false));
    }

    public function revoke(): void
    {
        if ($this->input->getMethod() !== 'POST') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }
        $this->checkToken('post');
        $user = Factory::getApplication()->getIdentity();

        if ($user->guest) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = $this->input->post->getInt('id');
        try {
            (new \Yann\Component\IdentityManager\Site\Service\ConnectionRemovalService())->revoke(
                $id, (int) $user->id, (string) $this->input->post->get('local_password', '', 'raw')
            );
        } catch (\RuntimeException $e) {
            $allowed = ['COM_IDENTITYMANAGER_ERROR_CONNECTION_NOT_FOUND', 'COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION', 'COM_IDENTITYMANAGER_ERROR_LOCAL_PASSWORD', 'COM_IDENTITYMANAGER_ERROR_UNLINK_WAIT'];
            $key = in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'COM_IDENTITYMANAGER_ERROR_AUTHENTICATION';
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=unlink&id=' . $id, false), Text::_($key), 'warning');
            return;
        }
        (new LogService())->write('account_revoked', 'success', 'COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_REVOKED_BY_USER_A9FB2', (int) $user->id);
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=connections', false), Text::_('COM_IDENTITYMANAGER_CONNECTION_REVOKED'));
    }

    private function redirectError(string $message): void
    {
        $message = preg_match('/^COM_IDENTITYMANAGER_[A-Z0-9_]+$/D', $message) ? $message : 'COM_IDENTITYMANAGER_ERROR_AUTHENTICATION';
        Factory::getApplication()->redirect(Route::_('index.php?option=com_identitymanager&view=error&message=' . rawurlencode($message), false));
    }

    private function redirectRelinkLogin(): void
    {
        $app = Factory::getApplication();
        $return = base64_encode(\Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_identitymanager&task=auth.resumeRelink');
        $pending = (new AuthService())->pendingRelink();
        $message = ($pending['reason'] ?? '') === 'existing_email'
            ? 'COM_IDENTITYMANAGER_CONFIRM_EXISTING_ACCOUNT'
            : 'COM_IDENTITYMANAGER_ERROR_ACCOUNT_REVOKED_LOGIN_REQUIRED';
        $provider = htmlspecialchars($pending['provider_name'] ?? $pending['provider_alias'] ?? '', ENT_QUOTES, 'UTF-8');
        $host = htmlspecialchars(\Joomla\CMS\Uri\Uri::getInstance()->getHost(), ENT_QUOTES, 'UTF-8');
        $app->enqueueMessage(Text::sprintf($message, $provider, $host), 'notice');
        $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . rawurlencode($return), false));
    }
}
