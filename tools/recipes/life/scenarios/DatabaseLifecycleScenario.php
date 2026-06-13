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

/** PUBLIC LIFE RECIPE: an admin robot uses two declared database providers. */
final class DatabaseLifecycleScenario implements RecipeInterface, RobotScenario
{
    public function name(): string { return 'life_database'; }
    public function scenarioName(): string { return 'DATABASE'; }
    public function actor(): RobotActor { return new RobotActor('admin_database_robot', 'admin', 'fr', ['database.configure']); }
    public function run(RecipeContext $context): array { return (new LifeScenarioRunner())->run($context, $this); }

    public function steps(): array
    {
        return [new RobotStep('load_source_and_target', function (RecipeContext $context, RobotSession $session): void {
            $sandbox = $context->sandbox('life_database');
            $source = $sandbox . DIRECTORY_SEPARATOR . 'source.sqlite';
            $target = $sandbox . DIRECTORY_SEPARATOR . 'target.sqlite';
            @unlink($source); @unlink($target);
            $xml = simplexml_load_string('<databases default="source"><connection name="source" provider="sqlite"><path>' . htmlspecialchars($source, ENT_XML1) . '</path></connection><connection name="target" provider="sqlite"><path>' . htmlspecialchars($target, ENT_XML1) . '</path></connection></databases>');
            $config = (new \ASAP\Database\DatabaseMultiConfigLoader())->fromXml($xml, 'life_database');
            $connector = new \ASAP\Database\PdoDatabaseConnector();
            $src = $connector->connect($config->get('source'))->pdo();
            $dst = $connector->connect($config->get('target'))->pdo();
            $src->exec('CREATE TABLE source_probe (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
            $dst->exec('CREATE TABLE target_probe (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
            $context->assert($config->defaultName() === 'source', 'OPUS_LIFE_DATABASE_DEFAULT_INVALID');
        })];
    }
}
