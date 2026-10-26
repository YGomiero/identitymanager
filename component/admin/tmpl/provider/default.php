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
use Yann\Component\IdentityManager\Administrator\Service\ProviderDefinitionService;

$app = Factory::getApplication();
$db = Factory::getContainer()->get('DatabaseDriver');
$definitionService = new ProviderDefinitionService();
$id = $app->input->getInt('id');
$templateInput = (string) $app->input->getCmd('template', '');
$template = preg_replace('/[^a-z0-9_\-]/', '', strtolower($templateInput));
$provider = null;
$preset = $template ? $definitionService->getDefinition($template) : null;

if ($id) {
    $query = $db->createQuery()
        ->select('*')
        ->from($db->quoteName('#__identitymanager_providers'))
        ->where($db->quoteName('id') . ' = ' . $id);
    $db->setQuery($query);
    $provider = $db->loadObject();
}

if (!$provider && $preset) {
    $provider = (object) $preset;
    $provider->id = 0;
    $provider->alias = $definitionService->uniqueAlias($preset['alias']);
}

$provider = $provider ?: (object) [
    'id' => 0,
    'name' => '',
    'alias' => '',
    'type' => 'oauth2',
    'enabled' => 0,
    'client_id' => '',
    'authorization_url' => '',
    'token_url' => '',
    'userinfo_url' => '',
    'jwks_url' => '',
    'issuer' => '',
    'scope' => 'openid email profile',
    'options_json' => '{}',
];

$options = json_decode((string) $provider->options_json, true) ?: [];
$adapter = (string) ($options['adapter'] ?? $options['adapter_class'] ?? '');
$ui = (array) ($options['_ui'] ?? []);
$isHybridauth = $provider->type === 'hybridauth';
$title = $provider->id ? \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_EDIT_PROVIDER_C3C1D') : ($preset ? \Joomla\CMS\Language\Text::sprintf('COM_IDENTITYMANAGER_CONFIGURE_PROVIDER', $preset['name']) : \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_NEW_MANUAL_OAUTH_OIDC_PROVIDER_2EC00'));
?>
<form method="post" action="<?php echo Route::_('index.php?option=com_identitymanager&task=provider.save'); ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong>
            <label class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" <?php echo (int) $provider->enabled ? 'checked' : ''; ?>>
                <span class="form-check-label"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ENABLED_DF174'); ?></span>
            </label>
        </div>
        <div class="card-body">
            <?php if ($isHybridauth && $adapter) : ?>
                <div class="alert alert-info">
                    <?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_THIS_PRESET_USES_HYBRIDAUTH_ADAPTER_7E715'); ?> <strong><?php echo htmlspecialchars($adapter, ENT_QUOTES, 'UTF-8'); ?></strong><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USUALLY_YOU_ONLY_NEED_TO_ENTER_THE_CLIENT_ID_KEY_AND_CL_3A7DB'); ?>
                    <?php if (!empty($ui['credential_hint'])) : ?>
                        <br><?php echo htmlspecialchars($ui['credential_hint'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                    <?php if (!empty($ui['documentation_url'])) : ?>
                        <br><a href="<?php echo htmlspecialchars($ui['documentation_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OPEN_PROVIDER_DOCUMENTATION_8EAAA'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-secondary"><strong><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_CALLBACK_URL'); ?></strong><br><code><?php echo htmlspecialchars(\Joomla\CMS\Uri\Uri::root() . 'index.php?option=com_identitymanager&task=auth.callback&provider=' . rawurlencode($provider->alias), ENT_QUOTES, 'UTF-8'); ?></code></div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="identitymanager-provider-icon"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_ICON_URL'); ?></label>
                    <input class="form-control" id="identitymanager-provider-icon" name="icon_url" value="<?php echo htmlspecialchars((string) ($options['icon_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-text"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_ICON_HELP'); ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="identitymanager-provider-name"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DISPLAY_NAME_C7874'); ?></label>
                    <input class="form-control" id="identitymanager-provider-name" name="name" required value="<?php echo htmlspecialchars($provider->name, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="identitymanager-provider-alias"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_INTERNAL_ALIAS_CF5FE'); ?></label>
                    <input class="form-control" id="identitymanager-provider-alias" name="alias" required pattern="[a-z0-9_-]+" value="<?php echo htmlspecialchars($provider->alias, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="identitymanager-provider-type"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ENGINE_C1F65'); ?></label>
                    <select class="form-select" id="identitymanager-provider-type" name="type">
                        <option value="oauth2" <?php echo $provider->type === 'oauth2' ? 'selected' : ''; ?>><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_OAUTH2_E9F4A'); ?></option>
                        <option value="oidc" <?php echo $provider->type === 'oidc' ? 'selected' : ''; ?>><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_OIDC_A93E8'); ?></option>
                        <option value="hybridauth" <?php echo $isHybridauth ? 'selected' : ''; ?>><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_HYBRIDAUTH_PRESET_CE0E0'); ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-provider-client-id"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_ID_KEY_3E9DA'); ?></label>
                    <input class="form-control" id="identitymanager-provider-client-id" name="client_id" value="<?php echo htmlspecialchars($provider->client_id, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="identitymanager-provider-client-secret"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CLIENT_SECRET_4B468'); ?></label>
                    <input class="form-control" id="identitymanager-provider-client-secret" type="password" name="client_secret" value="" autocomplete="new-password" placeholder="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_LEAVE_BLANK_TO_KEEP_EXISTING_SECRET_3876C'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="identitymanager-provider-scope"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_SCOPE_4651A'); ?></label>
                    <input class="form-control" id="identitymanager-provider-scope" name="scope" value="<?php echo htmlspecialchars($provider->scope, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <details class="mt-4" <?php echo $isHybridauth ? '' : 'open'; ?>>
                <summary><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADVANCED_PROVIDER_SETTINGS_226F3'); ?></summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-authorization-url"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_AUTHORIZATION_URL_6B8FE'); ?></label>
                        <input class="form-control" id="identitymanager-provider-authorization-url" name="authorization_url" value="<?php echo htmlspecialchars($provider->authorization_url, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-token-url"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_TOKEN_URL_979BB'); ?></label>
                        <input class="form-control" id="identitymanager-provider-token-url" name="token_url" value="<?php echo htmlspecialchars($provider->token_url, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-userinfo-url"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USERINFO_URL_98EBC'); ?></label>
                        <input class="form-control" id="identitymanager-provider-userinfo-url" name="userinfo_url" value="<?php echo htmlspecialchars($provider->userinfo_url, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-jwks-url"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_JWKS_URL_F47EB'); ?></label>
                        <input class="form-control" id="identitymanager-provider-jwks-url" name="jwks_url" value="<?php echo htmlspecialchars($provider->jwks_url, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-issuer"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ISSUER_2587E'); ?></label>
                        <input class="form-control" id="identitymanager-provider-issuer" name="issuer" value="<?php echo htmlspecialchars($provider->issuer, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identitymanager-provider-options"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OPTIONS_JSON_CEC2F'); ?></label>
                        <textarea class="form-control" id="identitymanager-provider-options" name="options_json" rows="4"><?php echo htmlspecialchars($provider->options_json, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </details>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=providers'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CANCEL_77DFD'); ?></a>
            <div>
                <input type="hidden" name="id" value="<?php echo (int) $provider->id; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button class="btn btn-primary" type="submit"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_SAVE_PROVIDER_BDF21'); ?></button>
            </div>
        </div>
    </div>
</form>
