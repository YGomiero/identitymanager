<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

$hybridauthPlugin = PluginHelper::getPlugin('identitymanager', 'hybridauth');
$hybridauthEnabled = !empty($hybridauthPlugin);
$providerDir = JPATH_SITE . '/components/com_identitymanager/vendor/hybridauth/hybridauth/src/Provider';
$adapterFiles = is_dir($providerDir) ? glob($providerDir . '/*.php') : [];
$adapterCount = count($adapterFiles ?: []);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADAPTERS_E07A4'); ?></h2>
    <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=providers'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_BACK_TO_PROVIDERS_514F2'); ?></a>
</div>

<table class="table">
    <thead><tr><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADAPTER_FAMILY_92995'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_STATUS_BAE7D'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USED_BY_F5060'); ?></th></tr></thead>
    <tbody>
        <tr>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_HYBRIDAUTH_PROVIDER_ADAPTERS_0C4C7'); ?></td>
            <td><?php echo $hybridauthEnabled && $adapterCount ? '<span class="badge bg-success">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIVE_A733B') . '</span>' : '<span class="badge bg-danger">' . \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_INACTIVE_09AF5') . '</span>'; ?></td>
            <td><?php echo (int) $adapterCount; ?> <?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDERS_FROM_17FFB'); ?> <code>hybridauth/hybridauth/src/Provider</code></td>
        </tr>
        <tr>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_OAUTH2_E9F4A'); ?></td>
            <td><span class="badge bg-success"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIVE_A733B'); ?></span></td>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_GENERIC_OAUTH2_AND_CUSTOM_ENDPOINTS_A43C3'); ?></td>
        </tr>
        <tr>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_OIDC_A93E8'); ?></td>
            <td><span class="badge bg-success"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ACTIVE_A733B'); ?></span></td>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_GENERIC_OPENID_CONNECT_WITH_ISSUER_JWKS_FIELDS_04F8C'); ?></td>
        </tr>
        <tr>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_OAUTH1_060E8'); ?></td>
            <td><span class="badge bg-secondary"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_VIA_HYBRIDAUTH_0A5F5'); ?></span></td>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OAUTH1_PROVIDERS_SUCH_AS_TWITTER_ARE_HANDLED_BY_HYBRIDA_DCA6D'); ?></td>
        </tr>
        <tr>
            <td>OpenID</td>
            <td><span class="badge bg-secondary"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_VIA_HYBRIDAUTH_0A5F5'); ?></span></td>
            <td><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_OPENID_ADAPTERS_SUCH_AS_AOLOPENID_LINKEDINOPENID_OR_PAY_90413'); ?></td>
        </tr>
    </tbody>
</table>
