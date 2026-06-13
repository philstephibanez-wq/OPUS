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

/** PUBLIC LIFE RECIPE: admin robot schedules LSTSAR, runner robot executes it outside HTTP. */
final class LstsarBackgroundLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_lstsar_background'; }
    public function scenarioName(): string { return 'LSTSAR_BACKGROUND'; }
    public function actor(): RobotActor { return new RobotActor('admin_lstsar_robot', 'admin', 'fr', ['lstsa.schedule']); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('schedule_and_run_background_job', function (RecipeContext $context, RobotSession $session): void {
            $sandbox = $context->sandbox('life_lstsar_background');
            $sourceDb = $sandbox . DIRECTORY_SEPARATOR . 'source.sqlite';
            $targetDb = $sandbox . DIRECTORY_SEPARATOR . 'target.sqlite';
            foreach ([$sourceDb, $sourceDb . '-wal', $sourceDb . '-shm', $targetDb, $targetDb . '-wal', $targetDb . '-shm'] as $file) { @unlink($file); }
            $source = new \PDO('sqlite:' . $sourceDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $source->exec('CREATE TABLE raw_users (email TEXT NOT NULL, status TEXT NOT NULL)');
            $source->exec("INSERT INTO raw_users (email, status) VALUES ('robot@example.org', 'active'), ('bot@example.org', 'inactive')");
            $store = new \ASAP\Lstsa\LstsaRunStore($context->rootPath());
            $scheduler = new \ASAP\Lstsa\LstsaScheduler($store);
            $scheduled = $scheduler->enqueueDatabaseStagingSmokeRun($sourceDb, $targetDb);
            $context->assert($scheduled['status'] === \ASAP\Lstsa\LstsaRunStatus::PENDING, 'OPUS_LIFE_LSTSAR_SCHEDULER_NOT_PENDING');
            $finished = (new \ASAP\Lstsa\LstsaRunner($store))->runOnce('life_lstsar_background_runner');
            $context->assert(is_array($finished) && $finished['status'] === \ASAP\Lstsa\LstsaRunStatus::DONE, 'OPUS_LIFE_LSTSAR_RUNNER_NOT_DONE');
            $target = new \PDO('sqlite:' . $targetDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $count = (int)$target->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $context->assert($count === 2, 'OPUS_LIFE_LSTSAR_TARGET_COUNT_INVALID');
            $events = $finished['artifacts']['events'] ?? [];
            $context->assert(is_array($events) && isset($events[0]) && is_file((string)$events[0]), 'OPUS_LIFE_LSTSAR_OK_EVENT_MISSING');
        })];
    }
}
