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
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

class AccountLinkService
{
    public function findLinkedAccount(int $providerId, string $providerUserId, bool $includeRevoked = false): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__identitymanager_accounts'))
            ->where($db->quoteName('provider_id') . ' = ' . $providerId)
            ->where($db->quoteName('provider_user_id') . ' = ' . $db->quote($providerUserId));

        if (!$includeRevoked) {
            $query->where($db->quoteName('revoked') . ' = 0');
        }

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    public function findUserByEmail(string $email): ?int
    {
        if ($email === '') {
            return null;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = ' . $db->quote($email));
        $db->setQuery($query);
        $id = (int) $db->loadResult();

        return $id > 0 ? $id : null;
    }

    public function createUser(array $profile): int
    {
        // Recheck at the write boundary: an administrator may close registration mid-flow.
        if (!RegistrationPolicy::allowsCreation()) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_ACCOUNT_CREATE_DISABLED');
        }
        // An external identity must not bypass a site's administrator approval policy.
        if ((int) ComponentHelper::getParams('com_users')->get('useractivation', 0) === 2) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_ADMIN_ACTIVATION');
        }
        if ((int) ComponentHelper::getParams('com_users')->get('useractivation', 0) === 1 && empty($profile['email_verified'])) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_EMAIL_NOT_VERIFIED');
        }
        $name = $profile['display_name'] ?: $profile['email'];
        $username = $this->uniqueUsername(($profile['username'] ?? '') ?: ($profile['email'] ?: $profile['provider_user_id']));
        $password = UserHelper::genRandomPassword(32);
        $groupId = (int) ComponentHelper::getParams('com_users')->get('new_usertype', 2);
        $group = $groupId > 0 ? $groupId : 2;
        if (\Joomla\CMS\Access\Access::checkGroup($group, 'core.admin') || \Joomla\CMS\Access\Access::checkGroup($group, 'core.login.admin')) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_PRIVILEGED_GROUP');
        }
        $user = new User();
        $data = [
            'name' => $name,
            'username' => $username,
            'email' => $profile['email'],
            'password' => $password,
            'password2' => $password,
            'groups' => [$groupId > 0 ? $groupId : 2],
        ];

        if (!$user->bind($data) || !$user->save()) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_UNABLE_TO_CREATE_JOOMLA_USER_A3775'));
        }

        return (int) $user->id;
    }

    public function syncUserProfile(int $userId, array $profile): void
    {
        $username = $this->sanitizeUsername((string) ($profile['username'] ?? ''));
        $name = trim((string) ($profile['display_name'] ?? ''));

        if ($username === '' && $name === '') {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $sets = [];

        if ($username !== '' && $this->isUsernameAvailable($username, $userId)) {
            $sets[] = $db->quoteName('username') . ' = ' . $db->quote($username);
        }

        if ($name !== '') {
            $sets[] = $db->quoteName('name') . ' = ' . $db->quote($name);
        }

        if (!$sets) {
            return;
        }

        $query = $db->createQuery()
            ->update($db->quoteName('#__users'))
            ->set($sets)
            ->where($db->quoteName('id') . ' = ' . (int) $userId);
        $db->setQuery($query)->execute();
    }

    public function link(int $userId, array $provider, array $profile, array $token): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $crypto = new TokenEncryptionService();
        $params = ComponentHelper::getParams('com_identitymanager');
        $now = Factory::getDate()->toSql();
        $expires = !empty($token['expires_in']) ? Factory::getDate('+' . (int) $token['expires_in'] . ' seconds')->toSql() : null;
        $refresh = $params->get('store_refresh_tokens', 0) ? ($token['refresh_token'] ?? '') : '';
        $existing = $this->findLinkedAccount((int) $provider['id'], (string) $profile['provider_user_id'], true);

        if ($existing && (int) $existing->user_id !== $userId) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_IDENTITY_IN_USE');
        }

        $fields = [
            'user_id' => $userId,
            'provider_id' => (int) $provider['id'],
            'provider_name' => $provider['name'],
            'provider_user_id' => $profile['provider_user_id'],
            'email' => $profile['email'],
            'email_verified' => $profile['email_verified'] ? 1 : 0,
            'display_name' => $profile['display_name'],
            'avatar_url' => $profile['avatar_url'],
            'access_token_encrypted' => $crypto->encrypt($token['access_token'] ?? ''),
            'refresh_token_encrypted' => $crypto->encrypt($refresh),
            'token_expires_at' => $expires,
            'revoked' => 0,
            'revoked_at' => null,
            'updated_at' => $now,
            'last_login_at' => $now,
        ];

        if ($existing) {
            $sets = [];
            foreach ($fields as $key => $value) {
                $sets[] = $db->quoteName($key) . ' = ' . ($value === null ? 'NULL' : $db->quote($value));
            }
            $query = $db->createQuery()->update($db->quoteName('#__identitymanager_accounts'))->set($sets)->where($db->quoteName('id') . ' = ' . (int) $existing->id);
            $db->setQuery($query)->execute();

            return (int) $existing->id;
        }

        $fields['created_at'] = $now;
        $columns = array_keys($fields);
        $values = [];
        foreach ($fields as $value) {
            $values[] = $value === null ? 'NULL' : $db->quote($value);
        }
        $query = $db->createQuery()->insert($db->quoteName('#__identitymanager_accounts'))->columns($db->quoteName($columns))->values(implode(',', $values));
        $db->setQuery($query)->execute();

        return (int) $db->insertid();
    }

    public function revoke(int $id, int $userId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->update($db->quoteName('#__identitymanager_accounts'))
            ->set($db->quoteName('revoked') . ' = 1')
            ->set($db->quoteName('access_token_encrypted') . ' = NULL')
            ->set($db->quoteName('refresh_token_encrypted') . ' = NULL')
            ->set($db->quoteName('revoked_at') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('user_id') . ' = ' . $userId);
        $db->setQuery($query)->execute();
    }

    /** Email matching must never silently attach an administrator account. */
    public function assertAutoLinkAllowed(int $userId): void
    {
        $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($userId);
        // The activation field also stores password-reset tokens; block is Joomla's account-status flag.
        if (!$user->id || $user->block || $user->authorise('core.admin') || $user->authorise('core.login.admin')) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LINK_LOGIN_REQUIRED');
        }
    }

    private function uniqueUsername(string $seed): string
    {
        $base = $this->sanitizeUsername(strstr($seed, '@', true) ?: $seed) ?: 'identity';
        $db = Factory::getContainer()->get('DatabaseDriver');
        $candidate = $base;
        $i = 1;

        while (true) {
            if ($this->isUsernameAvailable($candidate)) {
                return $candidate;
            }
            $candidate = $base . $i++;
        }
    }

    private function sanitizeUsername(string $username): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($username)) ?: '';
    }

    private function isUsernameAvailable(string $username, int $currentUserId = 0): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('username') . ' = ' . $db->quote($username));

        if ($currentUserId > 0) {
            $query->where($db->quoteName('id') . ' <> ' . (int) $currentUserId);
        }

        $db->setQuery($query);

        return (int) $db->loadResult() === 0;
    }
}
