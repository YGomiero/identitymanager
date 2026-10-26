<?php
/** Focused regression tests; no Joomla installation or network access is required. */
namespace Joomla\CMS\Component {
    class ComponentHelper {
        public static array $settings = [];
        public static function getParams($name) { return new class(self::$settings[$name] ?? []) {
            public function __construct(private array $values) {}
            public function get($name, $default = null) { return $this->values[$name] ?? $default; }
        }; }
    }
}
namespace Joomla\CMS\MVC\Controller { class BaseController {} }
namespace Joomla\CMS\Language { class Text { public static function _($key) { return $key; } } }
namespace Joomla\CMS\Uri { class Uri { public static function root($path=false) { return $path ? '/joomla' : 'https://client.example/joomla/'; } } }
namespace Joomla\Http {
    class Http {
        public array $requests = [];
        public array $responses = [];
        public function get($url, $headers=[], $timeout=15) { $this->requests[]=[$url,$headers]; return array_shift($this->responses); }
        public function post($url, $body, $headers=[], $timeout=15) { $this->requests[]=[$url,$body,$headers]; return array_shift($this->responses); }
    }
}
namespace {
define('_JEXEC',1);
$root=dirname(__DIR__);
require $root.'/component/site/vendor/autoload.php';
foreach(['ProviderInterface','OAuth2Provider','OidcProvider'] as $name) require $root.'/component/site/src/Provider/'.$name.'.php';
foreach(['RegistrationPolicy','ProviderIcon'] as $name) require $root.'/component/site/src/Service/'.$name.'.php';
require $root.'/component/admin/src/Controller/LogsController.php';
use Joomla\CMS\Component\ComponentHelper;
use Yann\Component\IdentityManager\Site\Service\RegistrationPolicy;
use Yann\Component\IdentityManager\Site\Service\ProviderIcon;
use Yann\Component\IdentityManager\Administrator\Controller\LogsController;
use Firebase\JWT\JWT;
class TestOidc extends Yann\Component\IdentityManager\Site\Provider\OidcProvider {
    public Joomla\Http\Http $transport;
    protected function http(array $provider): Joomla\Http\Http { return $this->transport; }
}
$count=0;
function check($condition,$name) { global $count; if(!$condition)throw new RuntimeException('FAIL: '.$name); $count++; echo "PASS: $name\n"; }
function rejects(callable $call,$name) { try{$call();}catch(Throwable $e){check(true,$name);return;} check(false,$name); }
function response(array $data,int $status=200) { return new class($data,$status) {
    public function __construct(private array $data,private int $status){}
    public function getStatusCode(){return $this->status;}
    public function getBody(){return json_encode($this->data);}
}; }
foreach([0,1] as $joomla)foreach([0,1] as $component){
    ComponentHelper::$settings=['com_users'=>['allowUserRegistration'=>$joomla],'com_identitymanager'=>['auto_create_users'=>$component]];
    check(RegistrationPolicy::allowsCreation()===($joomla===1&&$component===1),"Registration switches $joomla/$component");
}
ComponentHelper::$settings=[];check(!RegistrationPolicy::allowsCreation(),'Registration defaults to closed');
foreach(['=SUM(A1)','+1','-1','@SUM(A1)',"\ttext","\nformula",'  =1'] as $cell)check(str_starts_with(LogsController::safeCell($cell),"'"),'CSV formula protected');
check(LogsController::safeCell('GitHub')==='GitHub','CSV ordinary text unchanged');
foreach(['javascript:alert(1)','//evil.example/x','http://example.org/icon.png','https://user:pass@example.org/a','/../config.php','data:image/svg+xml,foo'] as $url)check(!ProviderIcon::validUrl($url),'Unsafe favicon URL refused');
check(ProviderIcon::validUrl('https://site.example/favicon.ico'),'HTTPS favicon accepted');
check(ProviderIcon::validUrl('/media/favicon.svg'),'Local favicon accepted');
$icon=ProviderIcon::render(['alias'=>'example','options_json'=>'{"icon_url":"https://site.example/a.png?x=1&y=2"}']);
check(str_contains($icon,'&amp;')&&str_contains($icon,'no-referrer')&&str_contains($icon,'alt=""'),'Favicon escaping, referrer and accessibility');
$provider=['client_id'=>'client','client_secret'=>str_repeat('s',64),'issuer'=>'https://issuer.example','expected_nonce'=>'nonce','options_json'=>'{"id_token_alg":"HS256"}','type'=>'oidc','scope'=>'openid email profile','authorization_url'=>'https://issuer.example/authorize','token_url'=>'https://issuer.example/token','userinfo_url'=>'https://issuer.example/userinfo','code_verifier'=>str_repeat('v',64)];
$claims=['iss'=>$provider['issuer'],'sub'=>'user-1','aud'=>'client','iat'=>time(),'exp'=>time()+300,'nonce'=>'nonce'];
$adapter=new TestOidc();$adapter->transport=new Joomla\Http\Http();
$token=JWT::encode($claims,$provider['client_secret'],'HS256');
check($adapter->validateIdToken($provider,$token)['sub']==='user-1','Valid signed OIDC token');
foreach(['iss'=>'https://evil.example','aud'=>'other','nonce'=>'other','sub'=>'','exp'=>time()-1,'iat'=>time()+120,'azp'=>'other'] as $key=>$value){$bad=$claims;$bad[$key]=$value;rejects(fn()=>$adapter->validateIdToken($provider,JWT::encode($bad,$provider['client_secret'],'HS256')),'Reject invalid '.$key);}
rejects(fn()=>$adapter->validateIdToken($provider,JWT::encode($claims,str_repeat('x',64),'HS256')),'Reject invalid signature');
$bad=$claims;unset($bad['exp']);rejects(fn()=>$adapter->validateIdToken($provider,JWT::encode($bad,$provider['client_secret'],'HS256')),'Require expiration');
$bad=$claims;$bad['aud']=['client','other'];rejects(fn()=>$adapter->validateIdToken($provider,JWT::encode($bad,$provider['client_secret'],'HS256')),'Require azp for multiple audiences');
$bad['azp']='client';check($adapter->validateIdToken($provider,JWT::encode($bad,$provider['client_secret'],'HS256'))['azp']==='client','Multiple audiences with matching azp');
parse_str(parse_url($adapter->getAuthorizationUrl($provider,'state','nonce','https://client.example/callback'),PHP_URL_QUERY),$query);
check($query['code_challenge_method']==='S256'&&$query['nonce']==='nonce'&&$query['state']==='state','OIDC authorization binds state nonce and PKCE');
$adapter->transport->responses=[response(['access_token'=>'access','id_token'=>$token])];
$result=$adapter->exchangeCode($provider,'code','https://client.example/callback');
parse_str($adapter->transport->requests[0][1],$body);
check($body['code_verifier']===$provider['code_verifier']&&isset($result['validated_claims']),'Joomla 6 PSR response and PKCE token exchange');
$adapter->transport->responses=[response(['sub'=>'user-1','email'=>'member@example.org','email_verified'=>true])];
check($adapter->fetchUser($provider,$result)['email_verified']===true,'Verified UserInfo with matching subject');
$adapter->transport->responses=[response(['sub'=>'someone-else'])];rejects(fn()=>$adapter->fetchUser($provider,$result),'Reject UserInfo subject substitution');
$adapter->transport->responses=[response(['sub'=>'user-1','email_verified'=>'true'])];check(!$adapter->fetchUser($provider,$result)['email_verified'],'Do not coerce string email verification');
$badProvider=$provider;$badProvider['token_url']='http://issuer.example/token';rejects(fn()=>$adapter->exchangeCode($badProvider,'code','https://client.example'),'Never send credentials over HTTP');
$rsa=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
$details=openssl_pkey_get_details($rsa);openssl_pkey_export($rsa,$private);
$b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
$jwks=['keys'=>[['kty'=>'RSA','kid'=>'test','use'=>'sig','alg'=>'RS256','n'=>$b64($details['rsa']['n']),'e'=>$b64($details['rsa']['e'])]]];
$rsProvider=$provider;$rsProvider['options_json']='{}';$rsProvider['jwks_url']='https://issuer.example/jwks';$adapter->transport->responses=[response($jwks)];
check($adapter->validateIdToken($rsProvider,JWT::encode($claims,$private,'RS256','test'))['sub']==='user-1','RS256 token verified against configured JWKS');
$adapter->transport->responses=[response($jwks)];rejects(fn()=>$adapter->validateIdToken($rsProvider,$token),'Reject HS256/RS256 algorithm confusion');
echo "$count regression checks passed.\n";
}
