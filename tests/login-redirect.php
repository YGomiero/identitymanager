<?php
/** Staging-only integration tests. Module settings are restored by transaction rollback. */
define('_JEXEC', 1);
define('JPATH_BASE', realpath($argv[1] ?? ''));
if (!JPATH_BASE) { throw new RuntimeException('Usage: php tests/login-redirect.php /staging/site'); }
$_SERVER['HTTP_HOST'] = 'demofr.test';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_METHOD'] = 'GET';
chdir(JPATH_BASE);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Yann\Component\IdentityManager\Site\Service\LoginRedirect;
$container = Factory::getContainer();
$container->alias('session', 'session.cli')->alias(Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(Joomla\CMS\Application\SiteApplication::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();
$app->loadIdentity(new User());
$app->loadLanguage(Joomla\CMS\Language\Language::getInstance('en-GB'));
$app->loadDocument(new Joomla\CMS\Document\HtmlDocument());
$db = $container->get(Joomla\Database\DatabaseInterface::class);
$count = 0;
function check($ok, $label) { global $count; if (!$ok) { throw new RuntimeException('FAIL: ' . $label); } $count++; echo 'PASS: ' . $label . "\n"; }
$home = Joomla\CMS\Uri\Uri::root();
$service = new LoginRedirect();
foreach (['//evil.example', 'https://evil.example', 'https://demofr.test.evil.example', 'https://user@demofr.test/', 'javascript:alert(1)', 'data:text/html,x', '/%0d%0aLocation:evil', '/%5cevil', '%2f%2fevil', '/%252f%252fevil', " /bad\npath", 'https://demofr.test:444/', '../other', 'javascript%3aalert(1)'] as $url) {
    check(LoginRedirect::localUrl($url, $home) === null, 'Unsafe or off-site destination refused');
}
foreach (['/', '/mon-espace?tab=profil#details', 'index.php?Itemid=101', '/Mon%20espace', $home . 'mon-espace'] as $url) {
    check(str_starts_with(LoginRedirect::localUrl($url, $home) ?? '', $home), 'Local destination accepted: ' . json_encode([$url, $home, LoginRedirect::localUrl($url, $home)]));
}
$db->transactionStart();
try {
    $module = $db->setQuery("SELECT * FROM #__modules WHERE module='mod_login' AND client_id=0 AND published=1 ORDER BY id")->loadObject();
    if (!$module) { throw new RuntimeException('A staging login module is required'); }
    $context = ['login' => true, 'module_id' => (int) $module->id, 'expires' => time() + 600];
    $set = function(array $params) use ($db, $module) {
        $db->setQuery('UPDATE #__modules SET params=' . $db->quote(json_encode($params)) . ' WHERE id=' . (int) $module->id)->execute();
    };
    $set([]);
    check($service->resolve($context) === $home, 'Module defaults to home after external login');
    $set(['identitymanager_redirect' => 'url', 'identitymanager_url' => '/mon-espace?test=1#profil']);
    check($service->resolve($context) === $home . 'mon-espace?test=1#profil', 'Stored module URL controls destination');
    $guestLevels = (new User())->getAuthorisedViewLevels();
    $guestOnly = array_values(array_diff($guestLevels, [1]));
    if ($guestOnly) {
        $db->setQuery('UPDATE #__modules SET access=' . (int) $guestOnly[0] . ' WHERE id=' . (int) $module->id)->execute();
        $signedIn = new class extends User { public function getAuthorisedViewLevels() { return [1, 2]; } };
        $signedIn->id = 123; $signedIn->guest = false; $app->loadIdentity($signedIn);
        check($service->resolve($context) === $home . 'mon-espace?test=1#profil', 'Guest-only login module keeps its destination after authentication');
        $app->loadIdentity(new User());
    }
    $db->setQuery('UPDATE #__modules SET access=999999 WHERE id=' . (int) $module->id)->execute();
    check($service->resolve($context) === $home, 'Module unavailable to guests cannot supply a login destination');
    $db->setQuery('UPDATE #__modules SET access=' . (int) $module->access . ' WHERE id=' . (int) $module->id)->execute();
    $app->input->set('return', base64_encode('https://evil.example'));
    $app->input->set('redirect', 'https://evil.example');
    $service->remember('github', $module->id);
    check($service->finish('github') === $home . 'mon-espace?test=1#profil', 'Untrusted return parameters cannot replace module destination');
    check($service->take('github') === null, 'Completed flow consumes its destination');
    $service->remember('github', $module->id);
    $service->remember('joomla', 0);
    check($service->finish('joomla') === $home && $service->take('github')['module_id'] === (int) $module->id, 'Providers keep independent flow destinations');
    $service->remember('github', $module->id);
    $pending = $service->take('github');
    check($service->resolve($pending) === $home . 'mon-espace?test=1#profil', 'Local account confirmation retains the destination');
    $expired = $context; $expired['expires'] = time() - 1;
    check($service->resolve($expired) === $home, 'Expired destination falls back to home');
    $set(['identitymanager_redirect' => 'url', 'identitymanager_url' => 'https://evil.example']);
    check($service->resolve($context) === $home, 'Off-site URL in settings falls back to home');
    $set(['identitymanager_redirect' => 'menu', 'identitymanager_menu' => 999999999]);
    check($service->resolve($context) === $home, 'Removed menu item falls back to home');
    $menu = $db->setQuery("SELECT id FROM #__menu WHERE client_id=0 AND published=1 AND type='component' AND access=1 AND home=0 ORDER BY id")->loadResult();
    if (!$menu) { throw new RuntimeException('A public non-home menu item is required'); }
    $set(['identitymanager_redirect' => 'menu', 'identitymanager_menu' => (int) $menu]);
    check($service->resolve($context) !== $home && str_starts_with($service->resolve($context), $home), 'Existing menu item resolves to a page of this site');
    $db->setQuery('UPDATE #__modules SET published=0 WHERE id=' . (int) $module->id)->execute();
    check($service->resolve($context) === $home, 'Unpublished module is not used for a redirect');
    $db->setQuery('UPDATE #__modules SET published=1 WHERE id=' . (int) $module->id)->execute();
    $member = new User(); $member->id = 123; $member->guest = false; $app->loadIdentity($member);
    $service->remember('github', $module->id);
    check(str_contains($service->finish('github'), 'identitymanager'), 'Signed-in provider linking stays in account management');
    $app->loadIdentity(new User());
    $plugin = $app->bootPlugin('identitymanager', 'system');
    $form = new Joomla\CMS\Form\Form('com_modules.module');
    $form->load('<form><field name="module" type="hidden" /></form>');
    $plugin->prepareLoginModule(new Joomla\CMS\Event\Model\PrepareFormEvent('onContentPrepareForm', ['subject' => $form, 'data' => (object) ['module' => 'mod_login']]));
    foreach (['identitymanager_redirect', 'identitymanager_menu', 'identitymanager_url'] as $field) {
        check((bool) $form->getField($field, 'params'), 'Native module edit form exposes ' . $field);
    }
    $app->input->post->set('jform', ['id' => (int) $module->id, 'module' => 'mod_login']);
    $saveForm = new Joomla\CMS\Form\Form('com_modules.module');
    $saveForm->load('<form><field name="module" type="hidden" /></form>');
    $plugin->prepareLoginModule(new Joomla\CMS\Event\Model\PrepareFormEvent('onContentPrepareForm', ['subject' => $saveForm, 'data' => []]));
    $filtered = $saveForm->filter(['params' => ['identitymanager_redirect' => 'menu', 'identitymanager_menu' => $menu, 'identitymanager_url' => '/mon-espace']]);
    check(($filtered['params']['identitymanager_redirect'] ?? null) === 'menu'
        && ($filtered['params']['identitymanager_menu'] ?? null) === (int) $menu,
        'POST validation keeps redirect fields when Joomla rebuilds an empty form');
    foreach ([111, 222] as $id) {
        $rendered = (object) ['id' => $id, 'module' => 'mod_login', 'content' => '<form class="mod-login"><input name="username"><input name="password"></form>'];
        $event = new Joomla\CMS\Event\Module\AfterRenderModuleEvent('onAfterRenderModule', ['subject' => $rendered, 'attributes' => []]);
        $plugin->renderLoginModule($event); $plugin->renderLoginModule($event);
        check(substr_count($rendered->content, 'class="identitymanager-social"') === 1 && str_contains($rendered->content, 'login_module=' . $id), 'Each login module carries its own context without duplicate buttons');
    }
    echo "$count redirect integration checks passed; transaction rolled back.\n";
} finally { $db->transactionRollback(); }
