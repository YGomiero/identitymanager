<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Yann\Component\IdentityManager\Administrator\Service\ProviderDefinitionService;

$definitionService = new ProviderDefinitionService();
$presets = $definitionService->getDefinitions();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADD_PROVIDER_EB385'); ?></h2>
    <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_identitymanager&view=providers'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_BACK_TO_PROVIDERS_514F2'); ?></a>
</div>

<div class="card mb-3">
    <div class="card-header"><strong><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_MANUAL_PROVIDER_BB12E'); ?></strong></div>
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <strong><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_GENERIC_OAUTH2_OIDC_7FF29'); ?></strong>
            <div class="text-muted"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_USE_THIS_WHEN_THE_PROVIDER_IS_NOT_COVERED_BY_HYBRIDAUTH_BA712'); ?></div>
        </div>
        <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&task=provider.add'); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_CONFIGURE_792C8'); ?></a>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_HYBRIDAUTH_PROVIDERS_A1FA3'); ?></strong></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_PROVIDER_7CEEE'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADAPTER_E6B46'); ?></th><th><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_DEFAULT_SCOPE_AC15C'); ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($presets as $preset) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($preset['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><code><?php echo htmlspecialchars($preset['alias'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td><?php echo htmlspecialchars($preset['scope'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_identitymanager&task=provider.add&template=' . rawurlencode($preset['alias'])); ?>"><?php echo \Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_ADD_61CC5'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
