<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Plugin\Authentication\IdentityManager\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Event\User\AuthenticationEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

final class IdentityManager extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return ['onUserAuthenticate' => 'onUserAuthenticate'];
    }

    public function onUserAuthenticate(AuthenticationEvent $event): void
    {
        Factory::getApplication()->getLanguage()->load('com_identitymanager', JPATH_SITE . '/components/com_identitymanager');
        $options = method_exists($event, 'getOptions') ? $event->getOptions() : [];
        $userId = (int) ($options['identitymanager_user_id'] ?? 0);

        if ($userId <= 0) {
            return;
        }

        $app = Factory::getApplication();
        $ticket = $app->getSession()->get('identitymanager.login_ticket', []);
        $app->getSession()->clear('identitymanager.login_ticket');
        if (!$app->isClient('site') || (int) ($ticket['user_id'] ?? 0) !== $userId || (int) ($ticket['expires'] ?? 0) < time()) { return; }

        $response = $event->getAuthenticationResponse();
        $response->type = 'IdentityManager';

        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__identitymanager_accounts'))
            ->where($db->quoteName('user_id') . ' = ' . $userId)
            ->where($db->quoteName('revoked') . ' = 0');
        $db->setQuery($query);

        if ((int) $db->loadResult() <= 0) {
            $response->status = Authentication::STATUS_FAILURE;
            $response->error_message = \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EXTERNAL_IDENTITY_IS_NOT_LINKED_OR_HAS_BEEN_REVOKED_F6AA5');
            return;
        }

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
        if (!$user->id || $user->block || $user->activation !== '') {
            $response->status = Authentication::STATUS_FAILURE;
            $response->error_message = \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_JOOMLA_USER_IS_BLOCKED_OR_MISSING_113CC');
            return;
        }

        $response->email = $user->email;
        $response->fullname = $user->name;
        $response->username = $user->username;
        $response->status = Authentication::STATUS_SUCCESS;
        $response->error_message = '';
        $event->stopPropagation();
    }
}
