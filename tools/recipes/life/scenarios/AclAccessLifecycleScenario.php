<?php

declare(strict_types=1);

namespace Opus\Recipe\Life\Scenarios;

use ASAP\Recipe\Life\LifeScenarioRunner;
use ASAP\Recipe\Life\RobotActor;
use ASAP\Recipe\Life\RobotScenario;
use ASAP\Recipe\Life\RobotSession;
use ASAP\Recipe\Life\RobotStep;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/** PUBLIC LIFE RECIPE: robots validate public/admin/denied access rules. */
final class AclAccessLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_acl'; }
    public function scenarioName(): string { return 'ACL'; }
    public function actor(): RobotActor { return new RobotActor('acl_supervisor', 'system', 'fr'); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('simulate_access_matrix', function (RecipeContext $context, RobotSession $session): void {
            $acl = new \ASAP\Acl\AccessControl(
                [new \ASAP\Acl\RoleDefinition('anonymous'), new \ASAP\Acl\RoleDefinition('admin'), new \ASAP\Acl\RoleDefinition('denied')],
                [new \ASAP\Acl\ResourceDefinition('public'), new \ASAP\Acl\ResourceDefinition('admin')],
                [new \ASAP\Acl\PrivilegeDefinition('view')],
                [new \ASAP\Acl\AccessRule('anonymous', 'public', 'view', true), new \ASAP\Acl\AccessRule('admin', 'admin', 'view', true), new \ASAP\Acl\AccessRule('denied', 'admin', 'view', false)]
            );
            $context->assert($acl->decide('anonymous', 'public', 'view')->allowed(), 'OPUS_LIFE_ACL_PUBLIC_DENIED');
            $context->assert(!$acl->decide('anonymous', 'admin', 'view')->allowed(), 'OPUS_LIFE_ACL_ANONYMOUS_ADMIN_ALLOWED');
            $context->assert($acl->decide('admin', 'admin', 'view')->allowed(), 'OPUS_LIFE_ACL_ADMIN_DENIED');
            $context->assert(!$acl->decide('denied', 'admin', 'view')->allowed(), 'OPUS_LIFE_ACL_DENIED_ALLOWED');
        })];
    }
}
