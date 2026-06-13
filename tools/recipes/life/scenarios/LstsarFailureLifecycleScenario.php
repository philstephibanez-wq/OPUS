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

/** PUBLIC LIFE RECIPE: invalid data fails LSTSAR without final target mutation. */
final class LstsarFailureLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_lstsar_failure'; }
    public function scenarioName(): string { return 'LSTSAR_FAILURE'; }
    public function actor(): RobotActor { return new RobotActor('admin_lstsar_fail_robot', 'admin', 'fr', ['lstsa.schedule']); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('schedule_invalid_job', function (RecipeContext $context, RobotSession $session): void {
            $sandbox = $context->sandbox('life_lstsar_failure');
            $sourceDb = $sandbox . DIRECTORY_SEPARATOR . 'source.sqlite';
            $targetDb = $sandbox . DIRECTORY_SEPARATOR . 'target.sqlite';
            foreach ([$sourceDb, $sourceDb . '-wal', $sourceDb . '-shm', $targetDb, $targetDb . '-wal', $targetDb . '-shm'] as $file) { @unlink($file); }
            $source = new \PDO('sqlite:' . $sourceDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $source->exec('CREATE TABLE raw_users (email TEXT NOT NULL, status TEXT NOT NULL)');
            $source->exec("INSERT INTO raw_users (email, status) VALUES ('not-an-email', 'active')");
            $store = new \ASAP\Lstsa\LstsaRunStore($context->rootPath());
            $scheduler = new \ASAP\Lstsa\LstsaScheduler($store);
            $scheduler->enqueueDatabaseStagingSmokeRun($sourceDb, $targetDb);
            $failed = (new \ASAP\Lstsa\LstsaRunner($store))->runOnce('life_lstsar_failure_runner');
            $context->assert(is_array($failed) && $failed['status'] === \ASAP\Lstsa\LstsaRunStatus::FAILED, 'OPUS_LIFE_LSTSAR_FAILURE_NOT_FAILED');
            if (is_file($targetDb)) {
                $target = new \PDO('sqlite:' . $targetDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $tables = $target->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll(\PDO::FETCH_COLUMN);
                $context->assert($tables === [], 'OPUS_LIFE_LSTSAR_FAILURE_FINAL_TABLE_CREATED');
            }
            $events = $failed['artifacts']['events'] ?? [];
            $context->assert(is_array($events) && isset($events[0]) && is_file((string)$events[0]), 'OPUS_LIFE_LSTSAR_FAIL_EVENT_MISSING');
        })];
    }
}
