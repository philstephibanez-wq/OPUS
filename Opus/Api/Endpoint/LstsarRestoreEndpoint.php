<?php
declare(strict_types=1);

namespace Opus\Api\Endpoint;

use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiRoute;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Lstsar\JsonFileLstsarStore;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * OPUS API endpoint that restores one stored LSTSAR record.
 */
final class LstsarRestoreEndpoint implements ApiEndpointInterface, LstsarRestoreEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        $settings = $route->meta['lstsar'] ?? null;
        if (!is_array($settings)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_ROUTE_SETTINGS_MISSING: ' . $route->id);
        }

        $datasetId = (string) ($settings['dataset'] ?? '');
        if (!preg_match('/^[a-z0-9_\-]{1,80}$/', $datasetId)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_DATASET_ID_INVALID: ' . $datasetId);
        }

        $projectRoot = rtrim((string) ($context['project_root'] ?? ''), DIRECTORY_SEPARATOR);
        if ($projectRoot === '') {
            throw new \RuntimeException('OPUS_LSTSAR_API_PROJECT_ROOT_MISSING');
        }

        $body = $request->jsonBody();
        $recordId = (string) ($body['record_id'] ?? '');
        if (!preg_match('/^[a-z0-9_\-]{1,80}$/', $recordId)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_RECORD_ID_INVALID');
        }

        $store = new JsonFileLstsarStore($projectRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsar');

        return Response::json([
            'ok' => true,
            'dataset_id' => $datasetId,
            'record_id' => $recordId,
            'record' => $store->restore($datasetId, $recordId),
        ]);
    }
}
