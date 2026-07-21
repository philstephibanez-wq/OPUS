<?php
declare(strict_types=1);
namespace Opus\Security\Sso;
interface SsoProviderInterface { public function id():string; public function authenticate(array $credentials):?SsoIdentity; }
