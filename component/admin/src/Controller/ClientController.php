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
use Yann\Component\IdentityManager\Site\Service\TokenEncryptionService;

class ClientController extends BaseController
{
    public function add(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=client', false));
    }

    public function save(): void
    {
        $this->checkToken();
        $this->assertAdmin();
        require_once JPATH_SITE . '/components/com_identitymanager/src/Service/TokenEncryptionService.php';

        $db = Factory::getContainer()->get('DatabaseDriver');
        $input = $this->input;
        $id = $input->getInt('id');
        $clientId = trim((string) $input->getString('client_id'));
        $secret = trim((string) $input->get('client_secret', '', 'raw'));
        $redirectUris = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $input->get('redirect_uris', '', 'raw')) ?: [])));

        if ($clientId === '') {
            $clientId = 'joomla-' . bin2hex(random_bytes(8));
        }

        if ($secret !== '' && strlen($secret) < 32) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_CLIENT_SECRET'), 400); }
        if (!$id && $secret === '') { $secret = bin2hex(random_bytes(32)); }
        foreach ($redirectUris as $uri) { if (!filter_var($uri, FILTER_VALIDATE_URL) || !str_starts_with($uri, 'https://') || str_contains($uri, '#') || parse_url($uri, PHP_URL_USER)) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_HTTPS'), 400); } }
        if (!$redirectUris) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_HTTPS'), 400); }
        if ($db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__identitymanager_clients')->where('client_id = ' . $db->quote($clientId))->where('id <> ' . $id))->loadResult()) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_CLIENT_ID'), 409); }
        $fields = [
            'name' => $input->getString('name'),
            'client_id' => $clientId,
            'enabled' => $input->getInt('enabled', 0),
            'redirect_uris_json' => json_encode($redirectUris, JSON_UNESCAPED_SLASHES),
            'scope' => $input->getString('scope', 'openid profile email'),
            'trusted' => $input->getInt('trusted', 0),
            'updated_at' => Factory::getDate()->toSql(),
        ];

        if ($secret !== '') {
            $fields['client_secret_encrypted'] = (new TokenEncryptionService())->encrypt($secret);
        }

        if ($id) {
            $sets = [];
            foreach ($fields as $key => $value) {
                $sets[] = $db->quoteName($key) . ' = ' . $db->quote($value);
            }
            $query = $db->createQuery()->update($db->quoteName('#__identitymanager_clients'))->set($sets)->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query)->execute();
        } else {
            $fields['created_at'] = Factory::getDate()->toSql();
            $columns = array_keys($fields);
            $values = array_map([$db, 'quote'], array_values($fields));
            $query = $db->createQuery()->insert($db->quoteName('#__identitymanager_clients'))->columns($db->quoteName($columns))->values(implode(',', $values));
            $db->setQuery($query)->execute();
        }

        if (!$id) {
            // Display once to the creating administrator, never in a URL or log.
            Factory::getApplication()->getSession()->set('identitymanager.client.created', [
                'user_id' => (int) Factory::getApplication()->getIdentity()->id,
                'client_id' => $clientId, 'secret' => $secret, 'expires' => time() + 300,
            ]);
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=client&layout=credentials', false));
            return;
        }
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=clients', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_APPLICATION_SAVED_D3526'));
    }

    public function delete(): void
    {
        $this->checkToken('request');
        $this->assertAdmin();
        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->delete($db->quoteName('#__identitymanager_clients'))->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=clients', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_APPLICATION_DELETED_202AC'));
    }

    private function assertAdmin(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
