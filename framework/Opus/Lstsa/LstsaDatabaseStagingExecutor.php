<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use ASAP\Database\DatabaseMultiConfigLoader;
use ASAP\Database\PdoDatabaseConnector;
use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaDatabaseStagingExecutor belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LSTSAR DATABASE STAGING EXECUTOR
 *
 * @visibility public
 * @role Runs a non-blocking background Lstsa job against declared source and
 *       target database connections, under FSM control and with target-side
 *       staging before final commit.
 * @contract The executor is called by LstsaRunner only. It never runs inside an
 *           HTTP request controller. It writes the final target table only after
 *           Load, Secure input, Transform, Secure output and staging validation
 *           have all succeeded.
 * @sideEffects Reads the source DB, writes the target DB staging/final tables,
 *              writes archives/events/reports through LstsaRunStore.
 */
final class LstsaDatabaseStagingExecutor
{
    private LstsaFsmController $fsm;

    public function __construct(
        private readonly LstsaRunStore $store,
        private readonly PdoDatabaseConnector $connector = new PdoDatabaseConnector()
    ) {
        $this->fsm = new LstsaFsmController();
    }

    /**
     * PUBLIC API
     *
     * @param array<string,mixed> $run Acquired background run.
     * @return array{status:string,counts:array<string,int>,artifacts:array<string,list<string>>}
     */
    public function execute(array &$run): array
    {
        $payload = $run['payload'] ?? [];
        if (!is_array($payload)) {
            throw new \RuntimeException('Lstsa database_staging payload must be an array');
        }

        $context = $this->buildContext($run, $payload);
        $state = LstsaFsmState::ACQUIRED;

        try {
            $state = $this->transition($context, $state, LstsaFsmSignal::START);
            $this->runPhase($context, $state, new LstsaLoadPhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::LOAD_OK);
            $this->runPhase($context, $state, new LstsaSecureInputPhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::SECURE_INPUT_OK);
            $this->runPhase($context, $state, new LstsaTransformPhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::TRANSFORM_OK);
            $this->runPhase($context, $state, new LstsaSecureOutputPhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::SECURE_OUTPUT_OK);
            $this->runPhase($context, $state, new LstsaStorePhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::STORE_OK);
            $this->runPhase($context, $state, new LstsaArchivePhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::ARCHIVE_OK);
            $this->runPhase($context, $state, new LstsaReportPhase());

            $state = $this->transition($context, $state, LstsaFsmSignal::REPORT_OK);
            $context->eventPath = $this->store->writeEventPayload($run, 'done_event.json', [
                'event' => 'OK',
                'state' => $state,
                'counts' => $context->counts,
            ]);

            return [
                'status' => LstsaRunStatus::DONE,
                'counts' => $context->counts,
                'artifacts' => [
                    'archives' => $context->archivePath === null ? [] : [$context->archivePath],
                    'quarantine' => $context->quarantinePath === null ? [] : [$context->quarantinePath],
                    'events' => $context->eventPath === null ? [] : [$context->eventPath],
                ],
            ];
        } catch (\Throwable $exception) {
            $this->fail($context, $state, $exception);
            throw $exception;
        }
    }

    /**
     * @param array<string,mixed> $run
     * @param array<string,mixed> $payload
     */
    private function buildContext(array &$run, array $payload): LstsaPipelineContext
    {
        $definitionXml = (string)($payload['definition_xml'] ?? '');
        if (trim($definitionXml) === '') {
            throw new \RuntimeException('Lstsa database_staging payload missing definition_xml');
        }

        $databaseConfigXml = (string)($payload['database_config_xml'] ?? '');
        if (trim($databaseConfigXml) === '') {
            throw new \RuntimeException('Lstsa database_staging payload missing database_config_xml');
        }

        $definitionNode = simplexml_load_string($definitionXml);
        if (!$definitionNode instanceof SimpleXMLElement) {
            throw new \RuntimeException('Lstsa database_staging definition XML invalid');
        }

        $databaseNode = simplexml_load_string($databaseConfigXml);
        if (!$databaseNode instanceof SimpleXMLElement) {
            throw new \RuntimeException('Lstsa database_staging database config XML invalid');
        }

        $context = new LstsaPipelineContext($run, $payload, $this->store);
        $context->definition = (new LstsaConfigLoader())->fromXml($definitionNode, (string)$run['run_id']);
        $context->connections = (new DatabaseMultiConfigLoader())->fromXml($databaseNode, (string)$run['run_id']);
        $context->definition->assertConnections($context->connections);
        $context->sourcePdo = $this->connector->connect($context->connections->get($context->definition->loadConnection()))->pdo();
        $context->targetPdo = $this->connector->connect($context->connections->get($context->definition->storeConnection()))->pdo();
        $context->sourcePdo->exec('PRAGMA journal_mode=WAL');
        $context->targetPdo->exec('PRAGMA journal_mode=WAL');
        $context->sourcePdo->exec('PRAGMA synchronous=NORMAL');
        $context->targetPdo->exec('PRAGMA synchronous=NORMAL');

        return $context;
    }

    private function transition(LstsaPipelineContext $context, string $state, string $signal): string
    {
        $next = $this->fsm->apply($state, $signal);
        $this->store->heartbeat($context->run, $next, $context->counts);
        return $next;
    }

    private function runPhase(LstsaPipelineContext $context, string $state, LstsaPhaseInterface $phase): void
    {
        $this->store->heartbeat($context->run, $state, $context->counts);
        $phase->execute($context);
        $this->store->heartbeat($context->run, $state, $context->counts);
    }

    private function fail(LstsaPipelineContext $context, string $state, \Throwable $exception): void
    {
        try {
            $failed = $this->fsm->apply($state, LstsaFsmSignal::FAIL);
            $this->store->heartbeat($context->run, $failed, $context->counts);
        } catch (\Throwable) {
            $this->store->heartbeat($context->run, LstsaFsmState::FAILED, $context->counts);
        }

        try {
            $this->store->writeEventPayload($context->run, 'fail_event.json', [
                'event' => 'FAIL',
                'state' => $state,
                'error' => $exception->getMessage(),
                'counts' => $context->counts,
            ]);
        } catch (\Throwable) {
            // The original execution error remains sovereign. Event failure must
            // not hide the real Lstsa failure cause.
        }
    }
}
