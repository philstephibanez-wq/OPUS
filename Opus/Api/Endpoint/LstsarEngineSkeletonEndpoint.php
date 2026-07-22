<?php
declare(strict_types=1);

namespace Opus\Api\Endpoint;

use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiRoute;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Lstsar\Config\LstsarContractRegistry;
use Opus\Lstsar\Contract\LstsarConstraintSet;
use Opus\Lstsar\Contract\LstsarJobDescriptor;
use Opus\Lstsar\Contract\LstsarSourceContract;
use Opus\Lstsar\Contract\LstsarTargetContract;
use Opus\Lstsar\Engine\LstsarPipelineRunner;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * REST endpoint exposing the contract-only LSTSAR engine skeleton.
 */
final class LstsarEngineSkeletonEndpoint implements ApiEndpointInterface, LstsarEngineSkeletonEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        $registry = LstsarContractRegistry::fromProjectRoot((string) ($context['project_root'] ?? ''));
        $pipelineId = (string) ($route->meta['pipeline_id'] ?? 'default');
        $pipeline = $registry->declaredPipeline($pipelineId);

        if ($pipeline === null) {
            return Response::json([
                'ok' => false,
                'error' => 'OPUS_LSTSAR_PIPELINE_CONTRACT_NOT_FOUND',
                'pipeline_id' => $pipelineId,
            ], 404);
        }

        $job = new LstsarJobDescriptor(
            'contract-engine-skeleton',
            $pipeline->id(),
            new LstsarSourceContract(
                'contract.source.demo',
                'json',
                LstsarConstraintSet::fromArray([
                    'type' => 'object',
                    'max_bytes' => 4096,
                    'format' => 'json',
                ]),
                ['execution' => 'not_loaded']
            ),
            new LstsarTargetContract(
                'contract.target.demo',
                'json',
                LstsarConstraintSet::fromArray([
                    'type' => 'object',
                    'max_bytes' => 4096,
                    'format' => 'json',
                ]),
                ['execution' => 'not_stored']
            ),
            ['milestone' => 'P7_LSTSAR_CONTRACT_ENGINE_SKELETON']
        );

        $report = (new LstsarPipelineRunner())->dryRun($pipeline, $job);

        return Response::json([
            'ok' => true,
            'contract' => 'OPUS_LSTSAR_ENGINE_SKELETON_RESPONSE_V1',
            'application' => $application->slug,
            'route_id' => $route->id,
            'identity' => $identity->toArray(),
            'job' => $job->toArray(),
            'pipeline' => $pipeline->describe(),
            'report' => $report->toArray(),
        ]);
    }
}
