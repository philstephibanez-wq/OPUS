<?php
declare(strict_types=1);
namespace Opus\Security\Sso;
use RuntimeException;
final class LocalPasswordSsoProvider implements SsoProviderInterface {
 public function __construct(private readonly string $storeFile){}
 public function id():string{return 'local-password';}
 public function authenticate(array $c):?SsoIdentity{$u=trim((string)($c['username']??''));$p=(string)($c['password']??'');if($u===''||$p==='')return null;$s=$this->read();$x=$s['users'][$u]??null;if(!is_array($x))return null;$h=(string)($x['password_hash']??'');if($h===''||!password_verify($p,$h))return null;$r=is_array($x['roles']??null)?$x['roles']:[(string)($x['profile']??'viewer')];return new SsoIdentity((string)($x['id']??$u),(string)($x['label']??$u),array_values(array_filter($r,'is_string')),$this->id(),($x['must_change_password']??false)===true);}
 private function read():array{if(!is_file($this->storeFile))throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_MISSING:'.$this->storeFile);$d=json_decode((string)file_get_contents($this->storeFile),true);if(!is_array($d)||($d['contract']??'')!=='OWASYS_LOCAL_USER_STORE_V1')throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_INVALID:'.$this->storeFile);$d['users']=is_array($d['users']??null)?$d['users']:[];return $d;}
}
