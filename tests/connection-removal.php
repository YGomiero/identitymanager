<?php
/** Integration scenarios included inside the staging transaction in joomla.php. */
use Yann\Component\IdentityManager\Site\Service\ConnectionRemovalService;
use Yann\Component\IdentityManager\Administrator\Service\JoomlaSetup;

$removal = new ConnectionRemovalService();
$app->loadIdentity($member);
// Isolate these scenarios from earlier tests that linked several identities.
$db->setQuery('UPDATE #__identitymanager_accounts SET revoked=1 WHERE user_id='.(int)$user->id)->execute();
$db->setQuery('UPDATE #__identitymanager_accounts SET revoked=0 WHERE id='.(int)$account->id)->execute();
$rejectKey = function(callable $fn, string $expected, string $label) {
    try { $fn(); } catch (RuntimeException $e) { check($e->getMessage() === $expected, $label); return; }
    check(false, $label);
};
$rejectKey(fn() => $removal->revoke($account->id, $user->id), 'COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION', 'Last external login is kept without local password proof');
$rejectKey(fn() => $removal->revoke($account->id, $admin->id), 'COM_IDENTITYMANAGER_ERROR_CONNECTION_NOT_FOUND', 'Another user cannot unlink this identity');
check((bool)$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']), 'Failed unlink leaves the connection active');
$password = 'Integration-' . bin2hex(random_bytes(16));
$db->setQuery('UPDATE #__users SET password='.$db->quote(Joomla\CMS\User\UserHelper::hashPassword($password)).' WHERE id='.(int)$user->id)->execute();
$rejectKey(fn() => $removal->revoke($account->id, $user->id, 'wrong'), 'COM_IDENTITYMANAGER_ERROR_LOCAL_PASSWORD', 'Wrong local password cannot unlink last provider');
$db->setQuery("UPDATE #__extensions SET enabled=0 WHERE type='plugin' AND folder='authentication' AND element='joomla'")->execute();
$rejectKey(fn() => $removal->revoke($account->id, $user->id, $password), 'COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION', 'A disabled password login is not a fallback');
$db->setQuery("UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND folder='authentication' AND element='joomla'")->execute();
$other = (object)['name'=>'Alternative Test','alias'=>'im-alt-'.$nonce,'type'=>'oidc','enabled'=>0,'created_at'=>Joomla\CMS\Factory::getDate()->toSql()];
$db->insertObject('#__identitymanager_providers',$other,'id');
$alternativeId = $linker->link($user->id, (array)$other, $profile, []);
$rejectKey(fn() => $removal->revoke($account->id, $user->id), 'COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION', 'Disabled alternative provider does not allow unlinking');
$db->setQuery('UPDATE #__identitymanager_providers SET enabled=1 WHERE id='.(int)$other->id)->execute();
$removal->revoke($account->id,$user->id);
check(!$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']), 'An active alternative allows unlinking without a password');
$rejectKey(fn() => $removal->revoke($alternativeId, $user->id), 'COM_IDENTITYMANAGER_ERROR_LAST_CONNECTION', 'Next removal rechecks the remaining provider');
$removal->revoke($alternativeId,$user->id,$password);
check(!$linker->findLinkedAccount($other->id,$profile['provider_user_id']), 'Verified local password allows last provider removal');
$db->setQuery('UPDATE #__identitymanager_accounts SET revoked=0 WHERE id='.(int)$account->id)->execute();
for ($attempt=0;$attempt<5;$attempt++) {
    $rejectKey(fn() => $removal->revoke($account->id,$user->id,'wrong'), 'COM_IDENTITYMANAGER_ERROR_LOCAL_PASSWORD', 'Invalid local password is rejected: '.($attempt+1));
}
$rejectKey(fn() => $removal->revoke($account->id,$user->id,$password), 'COM_IDENTITYMANAGER_ERROR_UNLINK_WAIT', 'Password proof is rate limited after five failed attempts');
$app->getSession()->clear('identitymanager.unlink.attempts.'.$user->id);
$probe->loadIdentity($member);Joomla\CMS\Factory::$application=$probe;
$probe->input->server->set('REQUEST_METHOD','GET');
$probe->input->post->set(Joomla\CMS\Session\Session::getFormToken(),1);
rejects(fn()=>$relinkController->revoke(),'GET unlink is rejected even with a token');
$probe->input->server->set('REQUEST_METHOD','POST');
$probe->input->post->set(Joomla\CMS\Session\Session::getFormToken(),0);
rejects(fn()=>$relinkController->revoke(),'POST unlink without CSRF is rejected');
$probe->input->post->set(Joomla\CMS\Session\Session::getFormToken(),1);
$probe->input->post->set('id',$account->id);
$probe->input->post->set('local_password',$password);
$relinkController->revoke();
check(!$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']),'Authenticated POST with password proof unlinks successfully');
Joomla\CMS\Factory::$application=$app;

// Guided setup must produce the same HTTPS OIDC settings used by the real flow.
$generated=JoomlaSetup::providerFields('https://master.example/sub/', ['ca_file'=>'local-ca.pem','icon_url'=>'/media/icon.svg']);
check($generated['issuer']==='https://master.example/sub' && $generated['token_url']==='https://master.example/sub/?option=com_identitymanager&task=idp.token','Joomla preset handles a subdirectory and canonical issuer');
$options=json_decode($generated['options_json'],true);
check($generated['type']==='oidc' && $options['id_token_alg']==='HS256' && $options['ca_file']==='local-ca.pem','Joomla preset preserves existing options and fixes the signing algorithm');
check(JoomlaSetup::siteForProvider((object)$generated)==='https://master.example/sub','Known Joomla configuration opens in the guided editor');
$custom=$generated;$custom['token_url']='https://master.example/custom';
check(JoomlaSetup::siteForProvider((object)$custom)===null,'Custom OIDC endpoints retain the advanced editor');
foreach(['http://master.example','https://user:secret@master.example','https://master.example/?x=1','https://master.example/#fragment'] as $url) {
    $rejectKey(fn()=>JoomlaSetup::providerFields($url),'COM_IDENTITYMANAGER_ERROR_JOOMLA_URL','Unsafe or ambiguous master address is rejected');
}

$app->loadIdentity($admin);

$return='https://client.example/index.php?option=com_identitymanager&task=auth.resumeRelink';
$app->setUserState('users.login.form.data',['return'=>$return,'username'=>'preserved']);
$app->setUserState('users.login.form.return',$return);
$auth->clearRelinkReturn();
check($app->getUserState('users.login.form.data')===['username'=>'preserved'] && !$app->getUserState('users.login.form.return'),'Completed relink return cannot contaminate a later logout');
$app->setUserState('users.login.form.data',['return'=>'index.php?option=com_content&view=article&id=1']);
$app->setUserState('users.login.form.return','index.php?option=com_users&view=profile');
$auth->clearRelinkReturn();
check($app->getUserState('users.login.form.data')['return']==='index.php?option=com_content&view=article&id=1' && $app->getUserState('users.login.form.return')==='index.php?option=com_users&view=profile','Unrelated Joomla return settings are preserved');
$app->input->post->set(Joomla\CMS\Session\Session::getFormToken(),1);
foreach(['id'=>0,'client_id'=>'','client_secret'=>'','name'=>'Generated test','redirect_uris'=>'https://client.example/callback','enabled'=>1,'trusted'=>0,'scope'=>'openid profile email'] as $key=>$value) { $app->input->set($key,$value); }
$clientController=new Yann\Component\IdentityManager\Administrator\Controller\ClientController(['base_path'=>JPATH_ADMINISTRATOR.'/components/com_identitymanager'],null,$app,$app->input);
$clientController->save();
$created=$app->getSession()->get('identitymanager.client.created');
check(is_array($created) && strlen($created['secret'])===64 && $created['user_id']===$admin->id,'Blank credentials generate a strong secret for the current administrator');
$db->setQuery('SELECT client_secret_encrypted FROM #__identitymanager_clients WHERE client_id='.$db->quote($created['client_id']));
$stored=$db->loadResult();
check($stored!==$created['secret'] && $crypto->decrypt($stored)===$created['secret'],'Generated client secret is stored encrypted');
ob_start();require JPATH_ADMINISTRATOR.'/components/com_identitymanager/tmpl/client/credentials.php';$rendered=ob_get_clean();
check(str_contains($rendered,$created['secret']) && !$app->getSession()->get('identitymanager.client.created'),'Generated credentials are shown once and removed from session');
ob_start();require JPATH_ADMINISTRATOR.'/components/com_identitymanager/tmpl/client/credentials.php';$renderedAgain=ob_get_clean();
check(!str_contains($renderedAgain,$created['secret']),'Reload does not reveal generated credentials again');

// Exercise the actual admin save path, including preserving a locally trusted CA.
foreach(['id'=>0,'setup'=>'joomla','alias'=>'im-guided-'.$nonce,'name'=>'Guided Joomla','joomla_site'=>'https://master.example/sub/','client_id'=>$created['client_id'],'client_secret'=>$created['secret'],'icon_url'=>'','enabled'=>0] as $key=>$value) { $app->input->set($key,$value); }
$providerController=new Yann\Component\IdentityManager\Administrator\Controller\ProviderController(['base_path'=>JPATH_ADMINISTRATOR.'/components/com_identitymanager'],null,$app,$app->input);
$providerController->save();
$db->setQuery('SELECT * FROM #__identitymanager_providers WHERE alias='.$db->quote('im-guided-'.$nonce));$saved=$db->loadObject();
check($saved->type==='oidc' && $saved->issuer==='https://master.example/sub' && $crypto->decrypt($saved->client_secret_encrypted)===$created['secret'],'Guided provider form saves the generated OIDC settings and encrypted secret');
$savedOptions=json_decode($saved->options_json,true);$savedOptions['ca_file']='private-ca.pem';
$db->setQuery('UPDATE #__identitymanager_providers SET options_json='.$db->quote(json_encode($savedOptions)).' WHERE id='.(int)$saved->id)->execute();
$app->input->set('id',$saved->id);$app->input->set('client_secret','');$providerController->save();
$db->setQuery('SELECT * FROM #__identitymanager_providers WHERE id='.(int)$saved->id);$updated=$db->loadObject();
check($updated->client_secret_encrypted===$saved->client_secret_encrypted && json_decode($updated->options_json,true)['ca_file']==='private-ca.pem','Guided edit preserves the existing secret and private CA configuration');
$app->loadIdentity($member);rejects(fn()=>$providerController->save(),'Ordinary users cannot configure Joomla providers');
$app->loadIdentity($admin);
