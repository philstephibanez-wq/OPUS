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

/** PUBLIC LIFE RECIPE: two runner robots cannot process the same LSTSAR job twice. */
final class LstsarConcurrencyLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_lstsar_concurrency'; }
    public function scenarioName(): string { return 'LSTSAR_CONCURRENCY'; }
    public function actor(): RobotActor { return new RobotActor('runner_supervisor', 'system', 'fr'); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('runner_a_then_runner_b', function (RecipeContext $context, RobotSession $session): void {
            $sandbox = $context->sandbox('life_lstsar_concurrency');
            $sourceDb = $sandbox . DIRECTORY_SEPARATOR . 'source.sqlite';
            $targetDb = $sandbox . DIRECTORY_SEPARATOR . 'target.sqlite';
            foreach ([$sourceDb, $sourceDb . '-wal', $sourceDb . '-shm', $targetDb, $targetDb . '-wal', $targetDb . '-shm'] as $file) { @unlink($file); }
            $source = new \PDO('sqlite:' . $sourceDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $source->exec('CREATE TABLE raw_users (email TEXT NOT NULL, status TEXT NOT NULL)');
            $source->exec("INSERT INTO raw_users (email, status) VALUES ('one@example.org', 'active')");
            $store = new \ASAP\Lstsa\LstsaRunStore($context->rootPath());
            (new \ASAP\Lstsa\LstsaScheduler($store))->enqueueDatabaseStagingSmokeRun($sourceDb, $targetDb);
            $runnerA = new \ASAP\Lstsa\LstsaRunner($store);
            $runnerB = new \ASAP\Lstsa\LstsaRunner($store);
            $first = $runnerA->runOnce('life_runner_a');
            $second = $runnerB->runOnce('life_runner_b');
            $context->assert(is_array($first) && $first['status'] === \ASAP\Lstsa\LstsaRunStatus::DONE, 'OPUS_LIFE_LSTSAR_CONCURRENCY_FIRST_NOT_DONE');
            $context->assert($second === null, 'OPUS_LIFE_LSTSAR_CONCURRENCY_DOUBLE_RUN_DETECTED');
            $target = new \PDO('sqlite:' . $targetDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $context->assert((int)$target->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'OPUS_LIFE_LSTSAR_CONCURRENCY_DOUBLE_COMMIT_DETECTED');
        })];
    }
}
