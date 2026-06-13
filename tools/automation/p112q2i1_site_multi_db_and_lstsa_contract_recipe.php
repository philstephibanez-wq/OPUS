<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Opus\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

use ASAP\Database\DatabaseMultiConfigLoader;
use ASAP\Lstsa\LstsaArchiveWriter;
use ASAP\Lstsa\LstsaConfigLoader;
use ASAP\Lstsa\LstsaReport;

function p112q2i1_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$databaseXml = simplexml_load_string(<<<'XML'
<databases default="main">
  <connection name="main" provider="sqlite">
    <path>var/tmp/p112q2i1_main.sqlite</path>
  </connection>
  <connection name="audit" provider="sqlite">
    <path>var/tmp/p112q2i1_audit.sqlite</path>
  </connection>
</databases>
XML);

$connections = (new DatabaseMultiConfigLoader())->fromXml($databaseXml, 'P112Q2I1_DATABASES');
p112q2i1_assert($connections->count() === 2, 'P112Q2I1_MULTI_DB_COUNT_INVALID');
p112q2i1_assert($connections->defaultName() === 'main', 'P112Q2I1_MULTI_DB_DEFAULT_INVALID');
p112q2i1_assert($connections->has('audit'), 'P112Q2I1_MULTI_DB_AUDIT_MISSING');

$lstsaXmlText = <<<'XML'
<lstsa id="user_email_sync" version="1.0.0">
  <load connection="main" table="raw_users">
    <field name="email" type="email" required="true" min_length="5" max_length="255" max_bytes="512" />
    <field name="status" type="string" required="true" max_length="32" enum="active,inactive,banned" />
  </load>
  <transform>
    <field target="email" source="email" type="email" required="true" max_length="180" max_bytes="360" transform="trim|lower" />
    <field target="is_active" source="status" type="bool" required="true" transform="status_to_bool" />
  </transform>
  <store connection="main" table="users" mode="upsert" />
  <archive mode="append_only" connection="audit" table="lstsa_runs" path="var/lstsa/archives" />
  <runtime max_run_seconds="3600" max_batch_seconds="30" max_rows_per_batch="500" max_memory_mb="256" heartbeat_every_seconds="10" stale_after_seconds="60" />
</lstsa>
XML;

$lstsaXml = simplexml_load_string($lstsaXmlText);
$definition = (new LstsaConfigLoader())->fromXml($lstsaXml, 'P112Q2I1_Lstsa');
$definition->assertConnections($connections);

p112q2i1_assert($definition->id() === 'user_email_sync', 'P112Q2I1_Lstsa_ID_INVALID');
p112q2i1_assert($definition->archiveMode() === 'append_only', 'P112Q2I1_Lstsa_ARCHIVE_MODE_INVALID');
p112q2i1_assert(isset($definition->loadFields()['email']), 'P112Q2I1_Lstsa_EMAIL_FIELD_MISSING');
p112q2i1_assert(isset($definition->mappings()['email']), 'P112Q2I1_Lstsa_EMAIL_MAPPING_MISSING');
p112q2i1_assert(($definition->runtime()['max_run_seconds'] ?? 0) === 3600, 'P112Q2I1_Lstsa_RUNTIME_INVALID');

$emailErrors = $definition->loadFields()['email']->validate('valid@example.org', 'SECURE_INPUT');
p112q2i1_assert($emailErrors === [], 'P112Q2I1_Lstsa_EMAIL_VALIDATION_UNEXPECTED_ERROR');

$tooLong = str_repeat('a', 260) . '@example.org';
$tooLongErrors = $definition->loadFields()['email']->validate($tooLong, 'SECURE_INPUT');
p112q2i1_assert($tooLongErrors !== [], 'P112Q2I1_Lstsa_LENGTH_VALIDATION_MISSING');

$runId = 'P112Q2I1_SMOKE_' . gmdate('Ymd_His');
$report = LstsaReport::create($definition->id(), $definition->version(), $runId, $lstsaXmlText);
$report->addCounter('loaded', 1);
$report->addCounter('accepted', 1);
$report->addCounter('transformed', 1);
$report->addCounter('stored', 1);
$report->addMessage('P112Q2I1 contract smoke test');
$report->finish('OK');

$reportDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsa' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p112q2i1_contract_smoke';
$paths = (new LstsaArchiveWriter())->writeReport($report, $reportDir);
p112q2i1_assert(is_file($paths['json']), 'P112Q2I1_Lstsa_JSON_REPORT_MISSING');
p112q2i1_assert(is_file($paths['markdown']), 'P112Q2I1_Lstsa_MARKDOWN_REPORT_MISSING');

echo 'P112Q2I1_MULTI_DB_CONNECTIONS=' . implode(',', $connections->names()) . PHP_EOL;
echo 'P112Q2I1_Lstsa_ID=' . $definition->id() . PHP_EOL;
echo 'P112Q2I1_REPORT_JSON=' . $paths['json'] . PHP_EOL;
echo 'P112Q2I1_REPORT_MD=' . $paths['markdown'] . PHP_EOL;
echo 'P112Q2I1_SITE_MULTI_DB_AND_Lstsa_CONTRACT_RECIPE_OK' . PHP_EOL;
