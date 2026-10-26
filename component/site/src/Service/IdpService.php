<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class IdpService
{
    public function getClient(string $clientId): ?array
    {
        if (!\Joomla\CMS\Component\ComponentHelper::getParams('com_identitymanager')->get('idp_enabled', 0)) { return null; }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()->select('*')->from($db->quoteName('#__identitymanager_clients'))->where($db->quoteName('client_id') . ' = ' . $db->quote($clientId))->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);
        $client = $db->loadAssoc();

        if (!$client) {
            return null;
        }

        $client['client_secret'] = (new TokenEncryptionService())->decrypt($client['client_secret_encrypted'] ?? '');
        $client['redirect_uris'] = json_decode((string) ($client['redirect_uris_json'] ?? '[]'), true) ?: [];

        return $client;
    }

    public function validateRedirect(array $client, string $redirectUri): bool
    {
        return str_starts_with($redirectUri, 'https://') && !str_contains($redirectUri, '#') && in_array($redirectUri, $client['redirect_uris'], true);
    }

    public function issueCode(array $client, int $userId, string $redirectUri, string $scope, string $nonce = '', string $challenge = ''): string
    {
        $this->userClaims($userId, $this->issuer());
        $requested = array_filter(explode(' ', trim($scope)));
        if (array_diff($requested, explode(' ', $client['scope'])) || array_diff($requested, ['openid', 'profile', 'email']) || !in_array('openid', $requested, true)) {
            throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_SCOPE');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge)) { throw new \RuntimeException('COM_IDENTITYMANAGER_ERROR_PKCE'); }
        $code = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $code);
        $db = Factory::getContainer()->get('DatabaseDriver');
        $columns = ['client_id', 'user_id', 'code_hash', 'redirect_uri', 'scope', 'nonce', 'code_challenge', 'expires_at', 'created_at'];
        $values = [
            $client['client_id'],
            $userId,
            $hash,
            $redirectUri,
            $scope,
            $nonce,
            $challenge,
            Factory::getDate('+10 minutes')->toSql(),
            Factory::getDate()->toSql(),
        ];
        $query = $db->createQuery()->insert($db->quoteName('#__identitymanager_oauth_codes'))->columns($db->quoteName($columns))->values(implode(',', array_map([$db, 'quote'], $values)));
        $db->setQuery($query)->execute();

        return $code;
    }

    public function consumeCode(string $code, string $clientId, string $redirectUri, string $verifier = ''): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $hash = hash('sha256', $code);
        $now = Factory::getDate()->toSql();
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__identitymanager_oauth_codes'))
            ->where($db->quoteName('code_hash') . ' = ' . $db->quote($hash))
            ->where($db->quoteName('client_id') . ' = ' . $db->quote($clientId))
            ->where($db->quoteName('redirect_uri') . ' = ' . $db->quote($redirectUri))
            ->where($db->quoteName('used_at') . ' IS NULL')
            ->where($db->quoteName('expires_at') . ' > ' . $db->quote($now));
        $db->setQuery($query);
        $row = $db->loadObject();

        if (!$row || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier) || !hash_equals((string) $row->code_challenge, $this->b64(hash('sha256', $verifier, true)))) {
            return null;
        }

        $query = $db->createQuery()->update($db->quoteName('#__identitymanager_oauth_codes'))->set($db->quoteName('used_at') . ' = ' . $db->quote($now))->where($db->quoteName('id') . ' = ' . (int) $row->id)->where($db->quoteName('used_at') . ' IS NULL')->where($db->quoteName('expires_at') . ' > ' . $db->quote($now));
        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1 ? $row : null;
    }

    public function userClaims(int $userId, string $issuer): array
    {
        $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($userId);

        // Joomla uses block for pending activation. The activation field can also
        // contain a legacy "0" or a password-reset token on an active account.
        if (!$user->id || $user->block) { throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_ERROR_LOGIN_FAILED'), 403); }

        return [
            'sub' => (string) $user->id,
            'name' => (string) $user->name,
            'preferred_username' => (string) $user->username,
            'email' => (string) $user->email,
            'email_verified' => (bool) \Joomla\CMS\Component\ComponentHelper::getParams('com_identitymanager')->get('idp_verified_email', 0),
            'iss' => $issuer,
        ];
    }

    public function verifyAccessToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $header = json_decode($this->b64decode($parts[0]), true);
        $payload = json_decode($this->b64decode($parts[1]), true);
        if (($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') { return null; }

        if (!is_array($payload) || empty($payload['aud']) || empty($payload['sub'])) {
            return null;
        }

        if (empty($payload['exp']) || (int) $payload['exp'] <= time() || ($payload['iss'] ?? '') !== $this->issuer() || ($payload['token_use'] ?? '') !== 'access' || empty($payload['iat']) || $payload['iat'] > time() + 30) {
            return null;
        }

        $client = $this->getClient((string) $payload['aud']);

        if (!$client || empty($client['client_secret'])) {
            return null;
        }

        $expected = $this->b64(hash_hmac('sha256', $parts[0] . '.' . $parts[1], $this->accessTokenKey(), true));

        if (!hash_equals($expected, $parts[2])) {
            return null;
        }

        return $payload;
    }

    /** Access tokens use a server-only key so clients cannot mint them. */
    public function accessTokenKey(): string
    {
        return hash_hmac('sha256', 'identitymanager-access', (string) Factory::getApplication()->get('secret'));
    }

    public function issuer(): string
    {
        return rtrim(Uri::root(), '/');
    }

    public function jwt(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [$this->b64(json_encode($header)), $this->b64(json_encode($payload))];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64decode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
