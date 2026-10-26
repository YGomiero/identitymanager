<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
?>
<p><?php echo Text::_('COM_IDENTITYMANAGER_SETTINGS_INTRO'); ?></p>
<p><a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_identitymanager'); ?>"><?php echo Text::_('COM_IDENTITYMANAGER_SETTINGS_OPEN'); ?></a></p>
