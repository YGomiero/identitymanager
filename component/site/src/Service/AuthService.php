<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Yann\Component\IdentityManager\Site\Provider\OAuth2Provider;
use Yann\Component\IdentityManager\Site\Provider\OidcProvider;

class AuthService
{
    public function getProvider(string $alias): ?array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->select('*')->from($db->quoteName('#__identitymanager_providers'))->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
        $db->setQuery($query);
        $row = $db->loadAssoc();

        if (!$row) {
            return null;
        }

        $row['client_secret'] = (new TokenEncryptionService())->decrypt($row['client_secret_encrypted'] ?? '');

        return $row;
    }

    public function buildRedirectUri(string $provider): string
    {
        return \Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_identitymanager&task=auth.callback&provider=' . rawurlencode($provider);
    }

    public function providerAdapter(array $provider): OAuth2Provider
    {
        return ($provider['type'] ?? 'oauth2') === 'oidc' ? new OidcProvider() : new OAuth2Provider();
    }

    public function completeCallback(array $provider, array $profile, array $token): int
    {
        $params = ComponentHelper::getParams('com_identitymanager');
        $linker = new AccountLinkService();
        $logger = new LogService();
        $user = Factory::getApplication()->getIdentity();

        if (empty($profile['provider_user_id']) || empty($profile['email']) || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_PROFILE');
        }

        if ($params->get('force_verified_email', 1) && !$profile['email_verified']) {
            $logger->write('login_failed', 'error', 'COM_IDENTITYMANAGER_UI_EMAIL_IS_NOT_VERIFIED_4A5A0', null, (int) $provider['id'], $provider['name']);
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_EMAIL_NOT_VERIFIED');
        }

        $linked = $linker->findLinkedAccount((int) $provider['id'], (string) $profile['provider_user_id']);
        if ($linked) {
            if (!$user->guest && (int) $linked->user_id !== (int) $user->id) {
                throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_IDENTITY_IN_USE');
            }
            $linker->link((int) $linked->user_id, $provider, $profile, $token);
            $logger->write('login_success', 'success', 'COM_IDENTITYMANAGER_UI_EXTERNAL_LOGIN_SUCCEEDED_2207E', (int) $linked->user_id, (int) $provider['id'], $provider['name']);

            return (int) $linked->user_id;
        }

        $revoked = $linker->findLinkedAccount((int) $provider['id'], (string) $profile['provider_user_id'], true);
        $providerOptions = json_decode((string) ($provider['options_json'] ?? '{}'), true) ?: [];
        if ($revoked && (int) $revoked->revoked && $user->guest && $profile['email_verified'] && $params->get('auto_link_by_email', 0)) {
            $existingUserId = $linker->findUserByEmail((string) $profile['email']);
            if ($existingUserId && (int) $existingUserId === (int) $revoked->user_id) {
                if (!empty($providerOptions['sync_username_on_auto_link'])) {
                    $linker->assertAutoLinkAllowed((int) $revoked->user_id);
                    $linker->syncUserProfile((int) $revoked->user_id, $profile);
                }
                $linker->assertAutoLinkAllowed((int) $revoked->user_id);
                $linker->link((int) $revoked->user_id, $provider, $profile, $token);
                $logger->write('account_linked', 'success', 'COM_IDENTITYMANAGER_UI_REVOKED_EXTERNAL_IDENTITY_LINKED_AGAIN_BY_VERIFIED_EMAI_DF62E', (int) $revoked->user_id, (int) $provider['id'], $provider['name']);
                $logger->write('login_success', 'success', 'COM_IDENTITYMANAGER_UI_EXTERNAL_LOGIN_SUCCEEDED_AFTER_RELINKING_REVOKED_IDENTI_6875A', (int) $revoked->user_id, (int) $provider['id'], $provider['name']);

                return (int) $revoked->user_id;
            }
        }

        if ($revoked && (int) $revoked->revoked && $user->guest) {
            Factory::getApplication()->getSession()->set('identitymanager.relink.pending', [
                'provider_alias' => (string) ($provider['alias'] ?? ''),
                'user_id' => (int) $revoked->user_id,
                'profile' => $profile,
                'token' => $token,
                'expires' => time() + 600,
            ]);
            $logger->write('login_failed', 'warning', 'COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_IS_REVOKED_274A3', (int) $revoked->user_id, (int) $provider['id'], $provider['name']);
            return 0;
        }

        if (!$user->guest) {
            $accountId = $linker->link((int) $user->id, $provider, $profile, $token);
            $logger->write('account_linked', 'success', 'COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_LINKED_D9A51', (int) $user->id, (int) $provider['id'], $provider['name']);

            return (int) $user->id;
        }

        $existingUserId = $linker->findUserByEmail((string) $profile['email']);
        if ($existingUserId && $profile['email_verified'] && $params->get('auto_link_by_email', 0)) {
            $linker->assertAutoLinkAllowed($existingUserId);
            if (!empty($providerOptions['sync_username_on_auto_link'])) {
                $linker->syncUserProfile($existingUserId, $profile);
            }
            $linker->link($existingUserId, $provider, $profile, $token);
            $logger->write('account_linked', 'success', 'COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_LINKED_BY_EMAIL_0FF75', $existingUserId, (int) $provider['id'], $provider['name']);

            return $existingUserId;
        }

        if ($existingUserId) {
            $logger->write('email_mismatch', 'warning', 'COM_IDENTITYMANAGER_UI_EXISTING_EMAIL_REQUIRES_MANUAL_LINKING_27137', $existingUserId, (int) $provider['id'], $provider['name']);
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LINK_LOGIN_REQUIRED');
        }

        if (!RegistrationPolicy::allowsCreation()) {
            $logger->write('login_failed', 'warning', 'COM_IDENTITYMANAGER_UI_AUTOMATIC_ACCOUNT_CREATION_IS_DISABLED_33673', null, (int) $provider['id'], $provider['name']);
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_ACCOUNT_CREATE_DISABLED');
        }

        $userId = $linker->createUser($profile);
        $linker->link($userId, $provider, $profile, $token);
        $logger->write('account_created', 'success', 'COM_IDENTITYMANAGER_UI_JOOMLA_ACCOUNT_CREATED_FROM_EXTERNAL_IDENTITY_8B671', $userId, (int) $provider['id'], $provider['name']);

        return $userId;
    }
}
