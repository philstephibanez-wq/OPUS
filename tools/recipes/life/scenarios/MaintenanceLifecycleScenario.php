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

/** PUBLIC LIFE RECIPE: maintenance robot cleans reports/archives without touching business DBs. */
final class MaintenanceLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_maintenance'; }
    public function scenarioName(): string { return 'MAINTENANCE'; }
    public function actor(): RobotActor { return new RobotActor('maintenance_robot', 'maintenance', 'fr', ['runtime.cleanup']); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('purge_runtime_evidence_only', function (RecipeContext $context, RobotSession $session): void {
            $sandbox = $context->sandbox('life_maintenance');
            $businessDb = $sandbox . DIRECTORY_SEPARATOR . 'business.sqlite';
            $pdo = new \PDO('sqlite:' . $businessDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
            $pdo->exec("DELETE FROM users");
            $pdo->exec("INSERT INTO users (email) VALUES ('keep@example.org')");
            $reports = $sandbox . DIRECTORY_SEPARATOR . 'reports';
            $archives = $sandbox . DIRECTORY_SEPARATOR . 'archives';
            mkdir($reports, 0775, true); mkdir($archives, 0775, true);
            file_put_contents($reports . DIRECTORY_SEPARATOR . 'old.json', '{}');
            file_put_contents($archives . DIRECTORY_SEPARATOR . 'old.json', '{}');
            @unlink($reports . DIRECTORY_SEPARATOR . 'old.json');
            @unlink($archives . DIRECTORY_SEPARATOR . 'old.json');
            $context->assert(!is_file($reports . DIRECTORY_SEPARATOR . 'old.json') && !is_file($archives . DIRECTORY_SEPARATOR . 'old.json'), 'OPUS_LIFE_MAINTENANCE_RUNTIME_PURGE_FAILED');
            $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $context->assert($count === 1, 'OPUS_LIFE_MAINTENANCE_BUSINESS_DATA_TOUCHED');
        })];
    }
}
