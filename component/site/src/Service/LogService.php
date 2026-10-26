<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class LogService
{
    public function write(string $eventType, string $status, string $message = '', ?int $userId = null, ?int $providerId = null, string $providerName = ''): void
    {
        if (!preg_match('/^COM_IDENTITYMANAGER_[A-Z0-9_]+$/D', $message)) { $message = 'COM_IDENTITYMANAGER_ERROR_AUTHENTICATION'; }
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $columns = ['user_id', 'provider_id', 'provider_name', 'event_type', 'status', 'message', 'ip_address', 'user_agent', 'created_at'];
        $values = [
            $userId ?: null,
            $providerId ?: null,
            $providerName,
            $eventType,
            $status,
            $message,
            (string) $app->input->server->get('REMOTE_ADDR', '', 'string'),
            substr((string) $app->input->server->get('HTTP_USER_AGENT', '', 'string'), 0, 512),
            Factory::getDate()->toSql(),
        ];

        $quoted = [];
        foreach ($values as $value) {
            $quoted[] = $value === null ? 'NULL' : $db->quote($value);
        }

        $query = $db->createQuery()
            ->insert($db->quoteName('#__identitymanager_logs'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $quoted));
        $db->setQuery($query)->execute();
        $days = max(0, min(3650, (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_identitymanager')->get('log_retention_days', 90)));
        if ($days > 0) {
            // Bounded cleanup avoids a large deletion on the authentication request.
            $cutoff = Factory::getDate('-' . $days . ' days')->toSql();
            $cleanup = $db->createQuery()->delete('#__identitymanager_logs')->where('created_at < ' . $db->quote($cutoff))->setLimit(100);
            $db->setQuery($cleanup)->execute();
        }
    }
}
