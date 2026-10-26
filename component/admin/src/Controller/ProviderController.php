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
use Yann\Component\IdentityManager\Administrator\Service\ProviderDefinitionService;
use Yann\Component\IdentityManager\Site\Service\TokenEncryptionService;

class ProviderController extends BaseController
{
    public function add(): void
    {
        $template = preg_replace('/[^a-z0-9_\-]/', '', strtolower($this->input->getCmd('template')));
        $url = 'index.php?option=com_identitymanager&view=provider';

        if ($template !== '') {
            $url .= '&template=' . rawurlencode($template);
        }

        $this->setRedirect(Route::_($url, false));
    }

    public function save(): void
    {
        $this->checkToken();
        $app = $this->app;

        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        require_once JPATH_SITE . '/components/com_identitymanager/src/Service/TokenEncryptionService.php';

        $input = $this->input;
        $db = Factory::getContainer()->get('DatabaseDriver');
        $id = $input->getInt('id');
        $secret = trim((string) $input->get('client_secret', '', 'raw'));
        $alias = preg_replace('/[^a-z0-9_\-]/', '', strtolower($input->getString('alias')));

        if ($alias === '') {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_ALIAS_IS_REQUIRED_59CB2'), 400);
        }

        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__identitymanager_providers'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->where($db->quoteName('id') . ' <> ' . (int) $id);
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_A_PROVIDER_WITH_THIS_ALIAS_ALREADY_EXISTS_F3F06'), 409);
        }

        $fields = [
            'name' => $input->getString('name'),
            'alias' => $alias,
            'type' => $input->getCmd('type', 'oauth2'),
            'enabled' => $input->getInt('enabled', 0),
            'client_id' => $input->getString('client_id'),
            'authorization_url' => $input->getString('authorization_url'),
            'token_url' => $input->getString('token_url'),
            'userinfo_url' => $input->getString('userinfo_url'),
            'jwks_url' => $input->getString('jwks_url'),
            'issuer' => $input->getString('issuer'),
            'scope' => $input->getString('scope'),
            'options_json' => $input->get('options_json', '{}', 'raw'),
            'updated_at' => Factory::getDate()->toSql(),
        ];

        $decoded = json_decode((string) $fields['options_json']);
        if (!is_object($decoded) || !in_array($fields['type'], ['oauth2', 'oidc', 'hybridauth'], true)) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_PROVIDER_OPTIONS'), 400); }
        $icon = trim((string) $input->get('icon_url', '', 'raw'));
        if ($icon !== '' && !\Yann\Component\IdentityManager\Site\Service\ProviderIcon::validUrl($icon)) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_ICON_URL'), 400); }
        $decoded->icon_url = $icon;
        $fields['options_json'] = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach (['authorization_url', 'token_url', 'userinfo_url', 'jwks_url', 'issuer'] as $endpoint) {
            if ($fields[$endpoint] !== '' && (!filter_var($fields[$endpoint], FILTER_VALIDATE_URL) || !str_starts_with($fields[$endpoint], 'https://') || parse_url($fields[$endpoint], PHP_URL_USER))) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_HTTPS'), 400); }
        }
        if ($secret !== '') {
            $fields['client_secret_encrypted'] = (new TokenEncryptionService())->encrypt($secret);
        }

        if ($id) {
            $sets = [];
            foreach ($fields as $key => $value) {
                $sets[] = $db->quoteName($key) . ' = ' . $db->quote($value);
            }
            $query = $db->createQuery()
                ->update($db->quoteName('#__identitymanager_providers'))
                ->set($sets)
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query)->execute();
        } else {
            $fields['created_at'] = Factory::getDate()->toSql();
            $columns = array_keys($fields);
            $values = array_map([$db, 'quote'], array_values($fields));
            $query = $db->createQuery()
                ->insert($db->quoteName('#__identitymanager_providers'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query)->execute();
        }

        $app->enqueueMessage(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_SAVED_9B91D'));
        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=providers', false));
    }

    public function import(): void
    {
        $this->checkToken('request');
        $this->assertAdmin();

        $alias = preg_replace('/[^a-z0-9_\-]/', '', strtolower($this->input->getCmd('alias')));
        $definition = (new ProviderDefinitionService())->getDefinition($alias);

        if (!$definition) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_DEFINITION_NOT_FOUND_1378C'), 404);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__identitymanager_providers'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=providers', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_ALREADY_EXISTS_FEE20'), 'warning');
            return;
        }

        $definition['created_at'] = Factory::getDate()->toSql();
        $columns = array_keys($definition);
        $values = array_map([$db, 'quote'], array_values($definition));
        $query = $db->createQuery()
            ->insert($db->quoteName('#__identitymanager_providers'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query)->execute();

        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=providers', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_IMPORTED_45312'));
    }

    public function delete(): void
    {
        $this->checkToken('request');
        $this->assertAdmin();

        $id = $this->input->getInt('id');
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__identitymanager_accounts'))
            ->where($db->quoteName('provider_id') . ' = ' . $id)
            ->where($db->quoteName('revoked') . ' = 0');
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=providers', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_THIS_PROVIDER_STILL_HAS_ACTIVE_LINKED_ACCOUNTS_REVOKE_A_0D3B7'), 'warning');
            return;
        }

        $query = $db->createQuery()
            ->delete($db->quoteName('#__identitymanager_accounts'))
            ->where($db->quoteName('provider_id') . ' = ' . $id)
            ->where($db->quoteName('revoked') . ' = 1');
        $db->setQuery($query)->execute();

        $query = $db->createQuery()
            ->delete($db->quoteName('#__identitymanager_providers'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query)->execute();

        $this->setRedirect(Route::_('index.php?option=com_identitymanager&view=providers', false), \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_DELETED_25D90'));
    }

    private function assertAdmin(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_identitymanager')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
