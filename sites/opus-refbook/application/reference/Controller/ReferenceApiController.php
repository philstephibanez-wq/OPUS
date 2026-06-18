<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Http\Request;
use Opus\Http\Response;

/**
 * PUBLIC CONTROLLER
 *
 * Role:
 *   Expose OPUS_REF_BOOK internal read-only JSON API routes.
 *
 * Contract:
 *   API boundary only. Data is prepared by the RefBook FSM runner and catalog services.
 */
final class ReferenceApiController extends AbstractRefBookController
{
    /**
     * @param array<string,string> $params
     */
    public function health(Request $request, array $params): Response
    {
        return Response::json($this->refBookRuntime()->health());
    }

    /**
     * @param array<string,string> $params
     */
    public function snapshot(Request $request, array $params): Response
    {
        return Response::json($this->refBookRuntime()->snapshot());
    }

    /**
     * @param array<string,string> $params
     */
    public function assetIntegrity(Request $request, array $params): Response
    {
        return Response::json([
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
            'asset_integrity' => $this->catalog()->assetDiagnostics(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function domains(Request $request, array $params): Response
    {
        $overview = $this->catalog()->overview();

        return Response::json([
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
            'domains' => $overview['domains'],
            'runtime' => $overview['runtime'],
            'asset_integrity' => $overview['asset_integrity'],
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function classes(Request $request, array $params): Response
    {
        return Response::json([
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
            'classes' => $this->catalog()->allSymbols(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public function classEntry(Request $request, array $params): Response
    {
        $fqcn = trim((string) ($_GET['fqcn'] ?? ''));
        if ($fqcn === '') {
            return Response::json([
                'ok' => false,
                'error' => 'OPUS_REFBOOK_API_CLASS_QUERY_MISSING',
            ], 400);
        }

        $symbol = $this->catalog()->symbolByName($fqcn);
        if ($symbol === null) {
            return Response::json([
                'ok' => false,
                'error' => 'OPUS_REFBOOK_API_CLASS_NOT_FOUND',
                'fqcn' => $fqcn,
            ], 404);
        }

        return Response::json([
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
            'class' => $symbol,
        ]);
    }
}
