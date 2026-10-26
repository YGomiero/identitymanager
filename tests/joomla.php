<?php
/** Run only on a backed-up staging site. Database changes are rolled back. */
define('_JEXEC',1);
define('JPATH_BASE',realpath(($argv[1]??'').'/administrator'));
if(!JPATH_BASE)throw new RuntimeException('Usage: php tests/joomla.php /staging/site administratorUsername');
$_SERVER['HTTP_HOST']='localhost';$_SERVER['SCRIPT_NAME']='/administrator/index.php';$_SERVER['REQUEST_METHOD']='POST';
chdir(JPATH_BASE);require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Session\Session;
use Yann\Component\IdentityManager\Site\Service\{AccountLinkService,AuthService,IdpService,LogService,TokenEncryptionService};
use Yann\Component\IdentityManager\Administrator\Controller\LogsController;
$container=Factory::getContainer();$container->alias('session','session.cli')->alias(Joomla\Session\SessionInterface::class,'session.cli');
$app=$container->get(Joomla\CMS\Application\AdministratorApplication::class);Factory::$application=$app;$app->createExtensionNamespaceMap();
$db=$container->get(Joomla\Database\DatabaseInterface::class);
$admin=$container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserByUsername($argv[2]??'');
if(!$admin->id||!$admin->authorise('core.admin'))throw new RuntimeException('An existing staging administrator is required');
$app->loadIdentity($admin);$count=0;
function check($ok,$label){global $count;if(!$ok)throw new RuntimeException('FAIL: '.$label);$count++;echo "PASS: $label\n";}
function rejects(callable $fn,$label){try{$fn();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
$db->transactionStart();
try{
 $nonce=bin2hex(random_bytes(12));
 $user=(object)['name'=>'Integration Test','username'=>'im-test-'.$nonce,'email'=>$nonce.'@example.invalid','password'=>'','block'=>0,'sendEmail'=>0,'registerDate'=>Factory::getDate()->toSql(),'activation'=>'','params'=>''];
 $db->insertObject('#__users',$user,'id');$group=(object)['user_id'=>$user->id,'group_id'=>2];$db->insertObject('#__user_usergroup_map',$group);
 $member=$container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($user->id);
 $crypto=new TokenEncryptionService();$cipher=$crypto->encrypt('test-secret');check($cipher!=='test-secret'&&$crypto->decrypt($cipher)==='test-secret','Secrets encrypted and recoverable');
 $provider=(object)['name'=>'Integration Test','alias'=>'im-test-'.$nonce,'type'=>'oidc','enabled'=>1,'created_at'=>Factory::getDate()->toSql()];$db->insertObject('#__identitymanager_providers',$provider,'id');$provider=(array)$provider;
 $profile=['provider_user_id'=>'test-'.$nonce,'email'=>$user->email,'email_verified'=>true,'display_name'=>$user->name,'avatar_url'=>''];
 $params=ComponentHelper::getParams('com_identitymanager');$params->set('auto_create_users',1);$params->set('auto_link_by_email',0);$params->set('force_verified_email',1);$params->set('log_retention_days',90);$params->set('idp_enabled',1);
 ComponentHelper::getParams('com_users')->set('allowUserRegistration',0);
 $app->loadIdentity(new Joomla\CMS\User\User());$auth=new AuthService();$linker=new AccountLinkService();
 $new=$profile;$new['email']='new-'.$nonce.'@example.invalid';
 rejects(fn()=>$auth->completeCallback($provider,$new,[]),'Closed Joomla registration refuses a new external account');
 rejects(fn()=>$linker->createUser($new),'Direct account creation also respects Joomla registration');
 check($auth->completeCallback($provider,$profile,[])===0 && !$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']),'Existing email requests local confirmation without silently linking');
 $firstLink=$auth->pendingRelink();check($firstLink['reason']==='existing_email' && $firstLink['user_id']===$user->id,'First-link challenge targets the existing local account');
 $app->getSession()->clear('identitymanager.relink.pending');
 $params->set('auto_link_by_email',1);$unverified=$profile;$unverified['email_verified']=false;
 rejects(fn()=>$auth->completeCallback($provider,$unverified,[]),'Unverified external email rejected');
 rejects(fn()=>$linker->assertAutoLinkAllowed($admin->id),'Administrator email cannot be automatically linked');
 $id=$auth->completeCallback($provider,$profile,['access_token'=>'fixture-token']);check($id===$user->id,'Existing ordinary account linked with verified email');
 $params->set('auto_link_by_email',0);check($auth->completeCallback($provider,$profile,[])===$user->id,'Existing link signs in while registration is closed');
 $app->loadIdentity($admin);rejects(fn()=>$auth->completeCallback($provider,$profile,[]),'Linked identity cannot switch a signed-in user');
 $account=$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']);$linker->revoke($account->id,$user->id);$revoked=$linker->findLinkedAccount($provider['id'],$profile['provider_user_id'],true);
 check(!$linker->findLinkedAccount($provider['id'],$profile['provider_user_id'])&&empty($revoked->access_token_encrypted)&&empty($revoked->refresh_token_encrypted),'Revocation removes tokens and disables link');
 $app->loadIdentity(new Joomla\CMS\User\User());check($auth->completeCallback($provider,$profile,[])===0,'Revoked identity requires local authentication');
 // Repeated callbacks must resume the challenge, never replay OAuth or restore the link.
 $pending=$auth->pendingRelink($provider['alias']);
 check($pending && $pending['user_id']===$user->id,'Pending relink retains the original local account');
 check($auth->pendingRelink('another-provider')===null && $auth->pendingRelink()!==null,'Another provider cannot resume or erase this challenge');
 $probe=new class(null,clone $app->getConfig(),null,$container) extends Joomla\CMS\Application\CMSApplication {
  protected $name='site';
  protected $clientId=0;
  protected function doExecute(){}
  public function getLanguageFilter(){return false;}
  public string $lastRedirect='';
  public function redirect($url,$status=303){$this->lastRedirect=$url;}
 };
 $probe->setSession($app->getSession());$probe->loadIdentity(new Joomla\CMS\User\User());
 $probe->loadLanguage(Joomla\CMS\Language\Language::getInstance('en-GB'));
 $probe->input->set('provider',$provider['alias']);
 Factory::$application=$probe;
 $relinkController=new Yann\Component\IdentityManager\Site\Controller\AuthController(['base_path'=>JPATH_SITE.'/components/com_identitymanager'],null,$probe,$probe->input);
 foreach(['oidc','hybridauth'] as $type){
  $db->setQuery('UPDATE #__identitymanager_providers SET type='.$db->quote($type).' WHERE id='.(int)$provider['id'])->execute();
  foreach(['start','callback'] as $action){
   $probe->lastRedirect='';$relinkController->$action();
   check(str_contains($probe->lastRedirect,'return=') && !str_contains($probe->lastRedirect,'STATE_INVALID'),'Repeated '.$type.' '.$action.' resumes local confirmation');
   check(!$linker->findLinkedAccount($provider['id'],$profile['provider_user_id']) && $probe->getIdentity()->guest,'Repeated '.$type.' '.$action.' cannot restore link or sign in');
  }
 }
 // The confirmation screen must not send the visitor around the external login loop.
 $probe->loadDocument(new Joomla\CMS\Document\HtmlDocument());
 $probe->input->set('option','com_users');$probe->input->set('view','login');
 $probe->input->set('return',base64_encode('https://client.example/index.php?option=com_identitymanager&task=auth.resumeRelink'));
 $form='<form id="com-users-login__form"><input name="username"><input name="password"></form>';
 $probe->setBody($form);
 $system=$probe->bootPlugin('identitymanager','system');
 $inject=new ReflectionMethod($system,'injectButtons');$inject->invoke($system,$probe);
 check($probe->getBody()===$form,'Relink login form does not offer external sign-in buttons');
 $probe->loadIdentity($member);
 $logout='<form class="mod-login-logout form-vertical"><button>Log out</button></form>';
 $probe->setBody($logout);$inject->invoke($system,$probe);$inject->invoke($system,$probe);
 check(substr_count($probe->getBody(),'identitymanager-manage')===1 && str_contains($probe->getBody(),'identitymanager'),'Signed-in module offers one connections link across both render events');
 $probe->input->set('view','profile');$probe->setBody('<div class="com-users-profile">Profile</div>');$inject->invoke($system,$probe);
 check(str_contains($probe->getBody(),'identitymanager-manage'),'Profile offers connections link when no login module is present');
 $probe->loadIdentity(new Joomla\CMS\User\User());$probe->input->set('view','login');$probe->setBody($form);$inject->invoke($system,$probe);
 check(!str_contains($probe->getBody(),'identitymanager-manage'),'Guest confirmation form does not display account management');
 $probe->loadIdentity($admin);$relinkController->resumeRelink();
 check(str_contains($probe->lastRedirect,'RELINK_WRONG_ACCOUNT') && !$auth->pendingRelink(),'Another local account cannot restore the identity');
 $probe->getSession()->set('identitymanager.relink.pending',$pending);$probe->loadIdentity($member);
 $relinkController->resumeRelink();
 check((int)$linker->findLinkedAccount($provider['id'],$profile['provider_user_id'])->user_id===$user->id && !$auth->pendingRelink(),'Original locally authenticated account restores link and consumes challenge');
 $linker->revoke($account->id,$user->id);
 $pending['expires']=time()-1;$probe->getSession()->set('identitymanager.relink.pending',$pending);$probe->loadIdentity(new Joomla\CMS\User\User());
 check(!$auth->pendingRelink() && !$probe->getSession()->get('identitymanager.relink.pending'),'Expired relink data is removed');
 $relinkController->resumeRelink();check(str_contains($probe->lastRedirect,'RELINK_EXPIRED'),'Expired guest relink does not loop through login');
 $relinkController->callback();check(str_contains($probe->lastRedirect,'STATE_INVALID'),'Callback without an active flow or challenge is still rejected');
 // A new provider identity with a matching local email follows the same confirmation path.
 $firstProfile=$profile;$firstProfile['provider_user_id']='first-link-'.$nonce;
 check($auth->completeCallback($provider,$firstProfile,[])===0,'Existing-account first link starts a local challenge');
 $relinkController->start();check(str_contains($probe->lastRedirect,'return='),'First link opens local login with automatic continuation');
 $probe->loadIdentity($member);$relinkController->resumeRelink();
 check((int)$linker->findLinkedAccount($provider['id'],$firstProfile['provider_user_id'])->user_id===$user->id && !$auth->pendingRelink(),'Local confirmation completes first link without finding a front-end menu');
 $db->setQuery("UPDATE #__identitymanager_providers SET type='oidc' WHERE id=".(int)$provider['id'])->execute();
 Factory::$application=$app;
 $db->setQuery('UPDATE #__users SET block=1 WHERE id='.(int)$user->id)->execute();rejects(fn()=>$linker->assertAutoLinkAllowed($user->id),'Blocked member cannot auto-link');
 $db->setQuery('UPDATE #__users SET block=0 WHERE id='.(int)$user->id)->execute();
 $service=new IdpService();
 // Joomla reuses activation for password resets; only block determines account availability.
 $site=$container->get(Joomla\CMS\Application\SiteApplication::class);
 $site->loadLanguage(Joomla\CMS\Language\Language::getInstance('en-GB'));
 $plugin=$site->bootPlugin('identitymanager','authentication');
 $authenticate=function()use($site,$plugin,$user,$app){
  Factory::$application=$site;
  try{
   $site->getSession()->set('identitymanager.login_ticket',['user_id'=>$user->id,'expires'=>time()+30]);
   $response=new Joomla\CMS\Authentication\AuthenticationResponse();
   $event=new Joomla\CMS\Event\User\AuthenticationEvent('onUserAuthenticate',['subject'=>$response,'credentials'=>['username'=>$user->username,'password'=>''],'options'=>['identitymanager_user_id'=>$user->id]]);
   $plugin->onUserAuthenticate($event);
   return $response->status===Joomla\CMS\Authentication\Authentication::STATUS_SUCCESS;
  }finally{Factory::$application=$app;}
 };
 $db->setQuery('UPDATE #__identitymanager_accounts SET revoked=0 WHERE id='.(int)$account->id)->execute();
 foreach(['empty'=>'','legacy zero'=>'0','password reset'=>'fixture-reset-token'] as $label=>$activation){
  $db->setQuery('UPDATE #__users SET block=0,activation='.$db->quote($activation).' WHERE id='.(int)$user->id)->execute();
  check($service->userClaims($user->id,$service->issuer())['sub']===(string)$user->id,'Active master account accepted: '.$label);
  $linker->assertAutoLinkAllowed($user->id);check(true,'Active member can link: '.$label);
  check($authenticate(),'Active client account authenticates: '.$label);
  $db->setQuery('UPDATE #__users SET block=1 WHERE id='.(int)$user->id)->execute();
  rejects(fn()=>$service->userClaims($user->id,$service->issuer()),'Blocked master account rejected: '.$label);
  rejects(fn()=>$linker->assertAutoLinkAllowed($user->id),'Blocked account cannot link: '.$label);
  check(!$authenticate(),'Blocked client account rejected: '.$label);
 }
 $db->setQuery("UPDATE #__users SET block=0,activation='' WHERE id=".(int)$user->id)->execute();
 $client=['client_id'=>'im-test-'.$nonce,'client_secret'=>str_repeat('s',64),'scope'=>'openid profile email','redirect_uris'=>['https://client.example/callback']];
 check($service->validateRedirect($client,'https://client.example/callback')&&!$service->validateRedirect($client,'https://client.example/callback/evil'),'Exact redirect matching');
 $verifier=str_repeat('v',64);$challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
 rejects(fn()=>$service->issueCode($client,$user->id,$client['redirect_uris'][0],'openid admin','nonce',$challenge),'Unsupported scopes rejected');
 $code=$service->issueCode($client,$user->id,$client['redirect_uris'][0],'openid email','nonce',$challenge);
 check(!$service->consumeCode($code,$client['client_id'],$client['redirect_uris'][0],str_repeat('x',64)),'Wrong PKCE verifier rejected');
 check((bool)$service->consumeCode($code,$client['client_id'],$client['redirect_uris'][0],$verifier),'Valid PKCE code consumed');
 check(!$service->consumeCode($code,$client['client_id'],$client['redirect_uris'][0],$verifier),'Authorization code replay rejected');
 $clientRow=(object)['name'=>'Integration','client_id'=>$client['client_id'],'client_secret_encrypted'=>$crypto->encrypt($client['client_secret']),'enabled'=>1,'redirect_uris_json'=>json_encode($client['redirect_uris'],JSON_UNESCAPED_SLASHES),'created_at'=>Factory::getDate()->toSql()];$db->insertObject('#__identitymanager_clients',$clientRow);
 $claims=['iss'=>$service->issuer(),'sub'=>(string)$user->id,'aud'=>$client['client_id'],'iat'=>time(),'exp'=>time()+60,'token_use'=>'access','scope'=>'openid'];
 check((bool)$service->verifyAccessToken($service->jwt($claims,$service->accessTokenKey())),'Master access token validated');
 check(!$service->verifyAccessToken($service->jwt($claims,$client['client_secret'])),'Client secret cannot forge a master access token');
 $old=(object)['event_type'=>'integration_old','status'=>'info','created_at'=>'2000-01-01 00:00:00'];$db->insertObject('#__identitymanager_logs',$old,'id');(new LogService())->write('integration','info','secret-must-not-be-logged');
 check(!$db->setQuery('SELECT COUNT(*) FROM #__identitymanager_logs WHERE id='.(int)$old->id)->loadResult(),'Log retention removes old rows');
 check($db->setQuery("SELECT message FROM #__identitymanager_logs WHERE event_type='integration' ORDER BY id DESC LIMIT 1")->loadResult()==='COM_IDENTITYMANAGER_ERROR_AUTHENTICATION','Raw exception content never stored in logs');
 $app->loadIdentity($admin);$app->getSession()->set('session.counter',2);$controller=new LogsController(['base_path'=>JPATH_ADMINISTRATOR.'/components/com_identitymanager'],null,$app,$app->input);
 rejects(fn()=>$controller->clear(),'Log purge without CSRF rejected');
 $app->input->post->set(Session::getFormToken(),1);$app->loadIdentity($member);rejects(fn()=>$controller->clear(),'Log purge by ordinary member rejected');
 $app->loadIdentity($admin);$app->input->post->set(Session::getFormToken(),1);$controller->clear();check((int)$db->setQuery('SELECT COUNT(*) FROM #__identitymanager_logs')->loadResult()===0,'Authorized log purge succeeds');
 echo "$count Joomla integration checks passed; transaction rolled back.\n";
}finally{$db->transactionRollback();}
