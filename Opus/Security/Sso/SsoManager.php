<?php
declare(strict_types=1);
namespace Opus\Security\Sso;
use RuntimeException;
final class SsoManager { private array $providers=[]; public function __construct(iterable $providers){foreach($providers as $p)$this->providers[$p->id()]=$p;} public function authenticate(string $id,array $credentials):SsoIdentity{$p=$this->providers[$id]??null;if(!$p instanceof SsoProviderInterface)throw new RuntimeException('OPUS_SSO_PROVIDER_UNKNOWN:'.$id);$i=$p->authenticate($credentials);if(!$i instanceof SsoIdentity)throw new RuntimeException('OPUS_SSO_AUTHENTICATION_FAILED');return $i;} }
