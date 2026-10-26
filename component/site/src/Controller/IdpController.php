<?php
/**
 * @package IdentityManager
 * @copyright (C) 2026 Yann Gomiero
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Yann\Component\IdentityManager\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Yann\Component\IdentityManager\Site\Service\IdpService;

class IdpController extends BaseController
{
    public function authorize(): void
    {
        $app = Factory::getApplication();
        $service = new IdpService();
        $clientId = (string) $this->input->get('client_id', '', 'raw');
        $redirectUri = (string) $this->input->get('redirect_uri', '', 'raw');
        $responseType = (string) $this->input->get('response_type', '', 'cmd');
        $state = (string) $this->input->get('state', '', 'raw');
        $scope = (string) $this->input->get('scope', 'openid profile email', 'raw');
        $nonce = (string) $this->input->get('nonce', '', 'raw');
        $challenge = (string) $this->input->get('code_challenge', '', 'raw');
        if ($this->input->getCmd('code_challenge_method') !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge)) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_PKCE'), 400); }
        $prompt = (string) $this->input->get('prompt', '', 'cmd');
        $client = $service->getClient($clientId);

        if (!$client || $responseType !== 'code' || !$service->validateRedirect($client, $redirectUri)) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_INVALID_AUTHORIZATION_REQUEST_AF252'), 400);
        }

        $user = $app->getIdentity();
        if ($prompt === 'login' && !$user->guest) {
            $requestId = $this->storeAuthorizeRequest($clientId, $redirectUri, $responseType, $state, $scope, $nonce, $challenge);
            $return = base64_encode(Uri::root() . 'index.php?option=com_identitymanager&task=idp.resumeAuthorize&request=' . rawurlencode($requestId));
            $app->logout((int) $user->id);
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . rawurlencode($return), false));
            return;
        }

        if ($user->guest) {
            $requestId = $this->storeAuthorizeRequest($clientId, $redirectUri, $responseType, $state, $scope, $nonce, $challenge);
            $return = base64_encode(Uri::root() . 'index.php?option=com_identitymanager&task=idp.resumeAuthorize&request=' . rawurlencode($requestId));
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . rawurlencode($return), false));
            return;
        }

        $this->redirectWithCode($client, (int) $user->id, $redirectUri, $scope, $nonce, $state, $challenge);
    }

    public function resumeAuthorize(): void
    {
        $app = Factory::getApplication();
        $requestId = preg_replace('/[^a-f0-9]/', '', (string) $this->input->get('request', '', 'raw'));
        $request = $requestId !== '' ? $this->loadAuthorizeRequest($requestId) : null;

        if (!is_array($request)) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_INVALID_AUTHORIZATION_REQUEST_AF252'), 400);
        }

        if ($app->getIdentity()->guest) {
            $return = base64_encode(Uri::root() . 'index.php?option=com_identitymanager&task=idp.resumeAuthorize&request=' . rawurlencode($requestId));
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . rawurlencode($return), false));
            return;
        }

        $service = new IdpService();
        $client = $service->getClient((string) $request['client_id']);

        if (!$client || ($request['response_type'] ?? '') !== 'code' || !$service->validateRedirect($client, (string) $request['redirect_uri'])) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('COM_IDENTITYMANAGER_UI_INVALID_AUTHORIZATION_REQUEST_AF252'), 400);
        }

        $this->redirectWithCode(
            $client,
            (int) $app->getIdentity()->id,
            (string) $request['redirect_uri'],
            (string) $request['scope'],
            (string) $request['nonce'],
            (string) $request['state'],
            (string) $request['challenge']
        );
    }

    public function token(): void
    {
        $app = Factory::getApplication();
        $service = new IdpService();
        $clientId = (string) $this->input->post->get('client_id', '', 'raw');
        $clientSecret = (string) $this->input->post->get('client_secret', '', 'raw');
        $code = (string) $this->input->post->get('code', '', 'raw');
        $redirectUri = (string) $this->input->post->get('redirect_uri', '', 'raw');
        $grantType = (string) $this->input->post->get('grant_type', '', 'cmd');
        $client = $service->getClient($clientId);

        if (!$client || empty($client['client_secret']) || $this->input->getMethod() !== 'POST' || !hash_equals((string) $client['client_secret'], $clientSecret) || $grantType !== 'authorization_code') {
            $this->json(['error' => 'invalid_client'], 401);
            return;
        }

        $row = $service->consumeCode($code, $clientId, $redirectUri, (string) $this->input->post->get('code_verifier', '', 'raw'));
        if (!$row) {
            $this->json(['error' => 'invalid_grant'], 400);
            return;
        }

        $issuer = $service->issuer();
        $now = time();
        try { $claims = $this->scopedClaims($service->userClaims((int) $row->user_id, $issuer), $row->scope); } catch (\Throwable $e) { $this->json(['error' => 'invalid_grant'], 400); return; }
        $idToken = $service->jwt($claims + [
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + 3600,
            'nonce' => $row->nonce,
        ], (string) $client['client_secret']);
        $accessToken = $service->jwt([
            'iss' => $issuer,
            'sub' => (string) $row->user_id,
            'aud' => $clientId,
            'scope' => $row->scope,
            'token_use' => 'access',
            'iat' => $now,
            'exp' => $now + 3600,
        ], $service->accessTokenKey());

        $this->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => $idToken,
        ]);
    }

    public function userinfo(): void
    {
        $app = Factory::getApplication();
        $auth = (string) $app->input->server->get('HTTP_AUTHORIZATION', '', 'raw');

        if ($auth === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $auth = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            $this->json(['error' => 'invalid_token'], 401);
            return;
        }

        $service = new IdpService();
        $payload = $service->verifyAccessToken($matches[1]);

        if (!$payload) {
            $this->json(['error' => 'invalid_token'], 401);
            return;
        }

        try { $claims = $this->scopedClaims($service->userClaims((int) $payload['sub'], $service->issuer()), (string) ($payload['scope'] ?? '')); } catch (\Throwable $e) { $this->json(['error' => 'invalid_token'], 401); return; }
        unset($claims['iss']);
        $this->json($claims);
    }

    public function discovery(): void
    {
        $issuer = (new IdpService())->issuer();
        // Root entry works with and without SEF, avoiding redirects of bearer requests.
        $base = $issuer . '/?option=com_identitymanager&task=idp.';
        $this->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $base . 'authorize',
            'token_endpoint' => $base . 'token',
            'userinfo_endpoint' => $base . 'userinfo',
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post'],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['HS256'],
        ]);
    }

    public function jwks(): void
    {
        $this->json(['keys' => []]);
    }

    private function json(array $data, int $status = 200): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json', true);
        http_response_code($status);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->setHeader('Pragma', 'no-cache', true);
        $app->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        $app->close();
    }

    /** Keep pending authorization bound to the browser that started it. */
    private function storeAuthorizeRequest(string $clientId, string $redirectUri, string $responseType, string $state, string $scope, string $nonce, string $challenge): string
    {
        $id = bin2hex(random_bytes(16));
        Factory::getApplication()->getSession()->set('identitymanager.authorize.' . $id, [
            'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => $responseType,
            'state' => $state, 'scope' => $scope, 'nonce' => $nonce, 'challenge' => $challenge, 'expires' => time() + 600,
        ]);
        return $id;
    }

    private function loadAuthorizeRequest(string $requestId): ?array
    {
        $session = Factory::getApplication()->getSession();
        $payload = $session->get('identitymanager.authorize.' . $requestId);
        if (!is_array($payload) || ($payload['expires'] ?? 0) < time()) { return null; }
        if (!Factory::getApplication()->getIdentity()->guest) { $session->clear('identitymanager.authorize.' . $requestId); }
        return $payload;
    }

    private function redirectWithCode(array $client, int $userId, string $redirectUri, string $scope, string $nonce, string $state, string $challenge, bool $consented = false): void
    {
        if (empty($client['trusted']) && !$consented) {
            $publicClient = ['client_id' => $client['client_id'], 'name' => $client['name'] ?? $client['client_id']];
            Factory::getApplication()->getSession()->set('identitymanager.consent', compact('userId', 'redirectUri', 'scope', 'nonce', 'state', 'challenge') + ['client' => $publicClient, 'expires' => time() + 600]);
            Factory::getApplication()->redirect(Route::_('index.php?option=com_identitymanager&view=consent', false));
            return;
        }
        $code = (new IdpService())->issueCode($client, $userId, $redirectUri, $scope, $nonce, $challenge);
        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        Factory::getApplication()->redirect($redirectUri . $separator . http_build_query(['code' => $code, 'state' => $state], '', '&', PHP_QUERY_RFC3986));
    }

    /** A consent decision requires a Joomla CSRF token and the same signed-in user. */
    public function consent(): void
    {
        $this->checkToken();
        $app = Factory::getApplication();
        $request = $app->getSession()->get('identitymanager.consent');
        $app->getSession()->clear('identitymanager.consent');
        if (!is_array($request) || $request['expires'] < time() || $app->getIdentity()->guest || $request['userId'] !== (int) $app->getIdentity()->id) {
            throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_STATE_INVALID'), 400);
        }
        $client = (new IdpService())->getClient($request['client']['client_id']);
        if (!$client || !(new IdpService())->validateRedirect($client, $request['redirectUri'])) { throw new \RuntimeException(Text::_('COM_IDENTITYMANAGER_ERROR_STATE_INVALID'), 400); }
        if (!$this->input->post->getInt('allow')) {
            $separator = str_contains($request['redirectUri'], '?') ? '&' : '?';
            $app->redirect($request['redirectUri'] . $separator . http_build_query(['error' => 'access_denied', 'state' => $request['state']]));
            return;
        }
        $this->redirectWithCode($client, $request['userId'], $request['redirectUri'], $request['scope'], $request['nonce'], $request['state'], $request['challenge'], true);
    }

    private function scopedClaims(array $claims, string $scope): array
    {
        $scopes = explode(' ', $scope);
        if (!in_array('email', $scopes, true)) { unset($claims['email'], $claims['email_verified']); }
        if (!in_array('profile', $scopes, true)) { unset($claims['name'], $claims['preferred_username']); }
        return $claims;
    }
}
