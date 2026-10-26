<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserHelper;

/** Self-service removal must leave a usable way to sign in. */
class ConnectionRemovalService
{
    public function status(int $id, int $userId, bool $lock = false): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->select('a.*, p.enabled AS provider_enabled')
            ->from($db->quoteName('#__identitymanager_accounts', 'a'))
            ->leftJoin($db->quoteName('#__identitymanager_providers', 'p') . ' ON p.id = a.provider_id')
            ->where('a.user_id = ' . $userId)->where('a.revoked = 0');
        $db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));
        $account = null;
        $alternatives = 0;
        foreach ($db->loadObjectList() as $row) {
            if ((int) $row->id === $id) {
                $account = $row;
            } elseif ((int) $row->provider_enabled === 1) {
                $alternatives++;
            }
        }
        if (!$account || $userId <= 0) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_CONNECTION_NOT_FOUND', 404);
        }
        $query = $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__extensions'))
            ->where("type = 'plugin'")->where("folder = 'authentication'")->where("element = 'joomla'")->where('enabled = 1');
        $db->setQuery($query);
        return ['account' => $account, 'alternatives' => $alternatives, 'local_password_enabled' => (bool) $db->loadResult()];
    }

    public function revoke(int $id, int $userId, string $password = ''): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->transactionStart(true);
        try {
            // Serialize two removals for this user; read current rows, not an old snapshot.
            $query = $db->createQuery()->select('id, password, block')->from($db->quoteName('#__users'))->where('id = ' . $userId);
            $db->setQuery((string) $query . ' FOR UPDATE');
            $user = $db->loadObject();
            if (!$user || $user->block) {
                throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_CONNECTION_NOT_FOUND', 403);
            }
            $status = $this->status($id, $userId, true);
            if (!$status['alternatives']) {
                // Social-created accounts also have a hash: only knowledge of the
                // local password proves that it is a usable replacement login.
                if (!$status['local_password_enabled'] || $password === '') {
                    throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION');
                }
                $session = Factory::getApplication()->getSession();
                $key = 'identitymanager.unlink.attempts.' . $userId;
                $attempts = $session->get($key, ['count' => 0, 'until' => 0]);
                if ($attempts['until'] <= time()) { $attempts = ['count' => 0, 'until' => time() + 300]; }
                if ($attempts['count'] >= 5) { throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_UNLINK_WAIT'); }
                if (!UserHelper::verifyPassword($password, $user->password)) {
                    $attempts['count']++;
                    $session->set($key, $attempts);
                    throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_LOCAL_PASSWORD');
                }
                $session->clear($key);
            }
            (new AccountLinkService())->revoke($id, $userId);
            $db->transactionCommit(true);
        } catch (\Throwable $e) {
            $db->transactionRollback(true);
            throw $e;
        }
    }
}
