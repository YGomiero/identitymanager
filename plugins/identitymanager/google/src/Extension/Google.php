<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Plugin\IdentityManager\Google\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class Google extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onIdentityManagerProviderDefinitions' => 'onIdentityManagerProviderDefinitions'];
    }

    public function onIdentityManagerProviderDefinitions(\Joomla\Event\Event $event): void
    {
        $definitions = [[
            'name' => 'Google',
            'alias' => 'google',
            'type' => 'hybridauth',
            'enabled' => 0,
            'scope' => 'openid email profile',
            'options_json' => '{"adapter":"Google"}',
        ]];
        $event->setArgument('result', array_merge((array) $event->getArgument('result', []), [$definitions]));
    }
}
