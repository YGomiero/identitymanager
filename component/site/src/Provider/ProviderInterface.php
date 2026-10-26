<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Provider;

\defined('_JEXEC') or die;

interface ProviderInterface
{
    public function getAuthorizationUrl(array $provider, string $state, string $nonce, string $redirectUri): string;

    public function exchangeCode(array $provider, string $code, string $redirectUri): array;

    public function fetchUser(array $provider, array $token): array;
}
