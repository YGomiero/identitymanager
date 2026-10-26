<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$db = Factory::getContainer()->get('DatabaseDriver');
$id = $app->input->getInt('id');
$client = null;

if ($id) {
    $query = $db->createQuery()->select('*')->from($db->quoteName('#__identitymanager_clients'))->where($db->quoteName('id') . ' = ' . $id);
    $db->setQuery($query);
    $client = $db->loadObject();
}

$client = $client ?: (object) [
    'id' => 0,
    'name' => '',
    'client_id' => '',
    'enabled' => 1,
    'redirect_uris_json' => '[]',
    'scope' => 'openid profile email',
    'trusted' => 0,
];

$redirectUris = implode("\n", json_decode((string) $client->redirect_uris_json, true) ?: []);
?>
<form method="post" action="<?php echo Route::_('index.php?option=com_identitymanager&task=client.save'); ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo $client->id ? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EDIT_CLIENT_APPLICATION_60300') : \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NEW_CLIENT_APPLICATION_29AE1'); ?></strong>
            <label class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" <?php echo (int) $client->enabled ? 'checked' : ''; ?>>
                <span class="form-check-label"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ENABLED_DF174'); ?></span>
            </label>
        </div>
        <div class="card-body">
            <p><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_MASTER_SETUP_HELP'); ?></p>
            <?php if (!\Joomla\CMS\Component\ComponentHelper::getParams('com_identitymanager')->get('idp_enabled', 0)) : ?>
                <div class="alert alert-warning"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_MASTER_DISABLED_HELP'); ?></div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-client-name"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NAME_709A2'); ?></label>
                    <input class="form-control" id="identitymanager-client-name" name="name" required value="<?php echo htmlspecialchars($client->name, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="identitymanager-client-redirects"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_REDIRECT_URIS_9842C'); ?></label>
                    <textarea class="form-control" id="identitymanager-client-redirects" name="redirect_uris" rows="5" required><?php echo htmlspecialchars($redirectUris, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class="form-text"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ONE_EXACT_REDIRECT_URI_PER_LINE_BFAED'); ?></div>
                </div>
                <div class="col-12"><details <?php echo $client->id ? 'open' : ''; ?>>
                    <summary><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_CLIENT_ADVANCED'); ?></summary>
                    <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-client-id"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_ID_A766C'); ?></label>
                    <input class="form-control" id="identitymanager-client-id" name="client_id" value="<?php echo htmlspecialchars($client->client_id, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LEAVE_BLANK_TO_GENERATE_C16DE'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-client-secret"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_SECRET_4B468'); ?></label>
                    <input class="form-control" id="identitymanager-client-secret" type="password" name="client_secret" autocomplete="new-password" placeholder="<?php echo $client->id ? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LEAVE_BLANK_TO_KEEP_EXISTING_SECRET_3876C') : \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LEAVE_BLANK_TO_GENERATE_C16DE'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-client-scope"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ALLOWED_SCOPE_B33D2'); ?></label>
                    <input class="form-control" id="identitymanager-client-scope" name="scope" value="<?php echo htmlspecialchars($client->scope, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                    </div>
                </details></div>
                <div class="col-12">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="trusted" value="1" <?php echo (int) $client->trusted ? 'checked' : ''; ?>>
                        <span class="form-check-label"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_TRUSTED_APPLICATION_SKIP_CONSENT_SCREEN_IN_V1_9FAFF'); ?></span>
                    </label>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=clients'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CANCEL_77DFD'); ?></a>
            <div>
                <input type="hidden" name="id" value="<?php echo (int) $client->id; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button class="btn btn-primary" type="submit"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_SAVE_CLIENT_APPLICATION_632EB'); ?></button>
            </div>
        </div>
    </div>
</form>
