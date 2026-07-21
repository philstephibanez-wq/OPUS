<?php
declare(strict_types=1);
namespace Opus\Security\Acl;
use RuntimeException;
final class AclPolicy {
 private array $policy;
 public function __construct(string $file) { if(!is_file($file)) throw new RuntimeException('OPUS_ACL_POLICY_MISSING:'.$file); $d=json_decode((string)file_get_contents($file),true); if(!is_array($d)||($d['contract']??'')!=='OPUS_ACL_POLICY_V1') throw new RuntimeException('OPUS_ACL_POLICY_INVALID:'.$file); $this->policy=$d; }
 public function decide(array $roles,string $resource,string $action='open'): AclDecision { $rules=is_array($this->policy['roles']??null)?$this->policy['roles']:[]; foreach($roles as $role){foreach((array)($rules[$role]??[]) as $grant){if($grant==='*:*'||$grant===$resource.':*'||$grant===$resource.':'.$action)return new AclDecision(true,'OPUS_ACL_ALLOWED',$resource,$action);}} return new AclDecision(false,'OPUS_ACL_DENIED',$resource,$action); }
}
