<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class TokenEncryptionService
{
    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_UNABLE_TO_ENCRYPT_SECRET_98029'));
        }

        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $encrypted): string
    {
        if (empty($encrypted) || !str_starts_with($encrypted, 'v1:')) {
            return '';
        }

        $raw = base64_decode(substr($encrypted, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    private function key(): string
    {
        $secret = (string) Factory::getApplication()->get('secret');

        return hash('sha256', 'identitymanager:' . $secret, true);
    }
}
