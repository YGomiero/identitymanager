<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class AccountController extends BaseController
{
    public function revoke(): void
    {
        $this->checkToken('request');
        $this->assertAdmin();

        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->update($db->quoteName('#__identitymanager_accounts'))
            ->set($db->quoteName('revoked') . ' = 1')->set($db->quoteName('access_token_encrypted') . ' = NULL')->set($db->quoteName('refresh_token_encrypted') . ' = NULL')
            ->set($db->quoteName('revoked_at') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();

        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=accounts', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_IDENTITY_REVOKED_2EE4A'));
    }

    public function delete(): void
    {
        $this->checkToken('request');
        $this->assertAdmin();

        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select('a.id, a.revoked, u.id AS existing_user_id')
            ->from($db->quoteName('#__identitymanager_accounts', 'a'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.user_id')
            ->where($db->quoteName('a.id') . ' = ' . $id);
        $db->setQuery($query);
        $account = $db->loadObject();

        if (!$account) {
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=accounts', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_IDENTITY_NOT_FOUND_E4005'), 'warning');
            return;
        }

        if (!(int) $account->revoked && (int) $account->existing_user_id > 0) {
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=accounts', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REVOKE_THIS_ACTIVE_IDENTITY_BEFORE_DELETING_IT_00944'), 'warning');
            return;
        }

        $query = $db->createQuery()
            ->delete($db->quoteName('#__identitymanager_accounts'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();

        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=accounts', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_IDENTITY_DELETED_95DD1'));
    }

    private function assertAdmin(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
