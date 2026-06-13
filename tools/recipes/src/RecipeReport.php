<?php

declare(strict_types=1);

namespace Opus\Recipe;

use JsonException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Write Opus global recipe reports.
 *
 * Responsibility:
 *   Persist JSON and Markdown summaries for each recipe run under ignored
 *   runtime directories.
 *
 * Contract:
 *   Reports are evidence artifacts. They must never modify framework code or
 *   business data.
 */
final class RecipeReport
{
    /**
     * PUBLIC API
     *
     * @param RecipeResult[] $results Recipe results.
     *
     * @return array{json:string,md:string,status:string} Report paths and status.
     */
    public function write(RecipeContext $context, array $results): array
    {
        $status = $this->status($results);
        $dir = $context->runtimePath() . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw RecipeAssertionFailedException::because('OPUS_RECIPE_REPORT_DIR_CREATE_FAILED', $dir);
        }

        $jsonPath = $dir . DIRECTORY_SEPARATOR . $context->runId() . '.json';
        $mdPath = $dir . DIRECTORY_SEPARATOR . $context->runId() . '.md';

        $payload = [
            'suite' => 'OPUS_GLOBAL_RECIPE_SUITE',
            'run_id' => $context->runId(),
            'status' => $status,
            'results' => array_map(static fn (RecipeResult $result): array => $result->toArray(), $results),
        ];

        try {
            file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw RecipeAssertionFailedException::because('OPUS_RECIPE_JSON_REPORT_FAILED', $exception->getMessage());
        }

        $lines = ['# Opus Global Recipe Report', '', '- Run: `' . $context->runId() . '`', '- Status: `' . $status . '`', ''];
        foreach ($results as $result) {
            $lines[] = '## ' . $result->name . ' — ' . $result->status;
            $lines[] = '';
            $lines[] = '- Duration: `' . number_format($result->durationSeconds, 4, '.', '') . 's`';
            foreach ($result->markers as $marker) {
                $lines[] = '- Marker: `' . $marker . '`';
            }
            foreach ($result->diagnostics as $diagnostic) {
                $lines[] = '- Diagnostic: `' . str_replace('`', "'", $diagnostic) . '`';
            }
            $lines[] = '';
        }
        file_put_contents($mdPath, implode(PHP_EOL, $lines) . PHP_EOL);

        return ['json' => $jsonPath, 'md' => $mdPath, 'status' => $status];
    }

    /** @param RecipeResult[] $results */
    private function status(array $results): string
    {
        foreach ($results as $result) {
            if ($result->status !== 'OK') {
                return 'FAILED';
            }
        }

        return 'OK';
    }
}
