<?php
declare(strict_types=1);

namespace Opus\Api\Endpoint;

use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiRoute;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Lstsar\JsonFileLstsarStore;
use Opus\Lstsar\LstsarEngine;
use Opus\Security\Access\AccessDecisionInterface;
use Opus\Security\Identity\IdentityContextInterface;

/**
 * OPUS API endpoint that processes one LSTSAR payload.
 */
final class LstsarProcessEndpoint implements ApiEndpointInterface, LstsarProcessEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        $settings = $this->settings($route);
        $projectRoot = $this->projectRoot($context);
        $schema = $this->loadSchema($projectRoot, (string) ($settings['schema'] ?? ''));
        $datasetId = (string) ($settings['dataset'] ?? ($schema['id'] ?? 'default'));

        $accessDecision = $context['access_decision'] ?? null;
        if (!$accessDecision instanceof AccessDecisionInterface) {
            throw new \RuntimeException('OPUS_LSTSAR_API_ACCESS_DECISION_MISSING');
        }

        $engine = new LstsarEngine(new JsonFileLstsarStore($projectRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsar'));
        $result = $engine->process($datasetId, $schema, $request->jsonBody(), $accessDecision);

        return Response::json($result->toArray(), $result->ok() ? 200 : 422);
    }

    /** @return array<string,mixed> */
    private function settings(ApiRoute $route): array
    {
        $settings = $route->meta['lstsar'] ?? null;
        if (!is_array($settings)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_ROUTE_SETTINGS_MISSING: ' . $route->id);
        }

        return $settings;
    }

    /** @param array<string,mixed> $context */
    private function projectRoot(array $context): string
    {
        $projectRoot = rtrim((string) ($context['project_root'] ?? ''), DIRECTORY_SEPARATOR);
        if ($projectRoot === '') {
            throw new \RuntimeException('OPUS_LSTSAR_API_PROJECT_ROOT_MISSING');
        }

        return $projectRoot;
    }

    /** @return array<string,mixed> */
    private function loadSchema(string $projectRoot, string $schemaId): array
    {
        if (!preg_match('/^[a-z0-9_\-]{1,80}$/', $schemaId)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_SCHEMA_ID_INVALID: ' . $schemaId);
        }

        $path = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'lstsar' . DIRECTORY_SEPARATOR . $schemaId . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_SCHEMA_MISSING: ' . $schemaId);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_LSTSAR_API_SCHEMA_JSON_INVALID: ' . $schemaId);
        }

        return $decoded;
    }
}
