<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\File\Json;

/**
 * Canonicalizes generated presentation sites so Web Profiler registration is
 * owned by OPUS runtime configuration rather than by site routes/FSM/templates.
 */
final class ProfilerEnvironmentScaffoldPolicy implements ProfilerEnvironmentScaffoldPolicyInterface
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function normalizeDirectory(string $relativePath): string
    {
        if (preg_match(
            '~^(sites/[a-z][a-z0-9-]*)/application/profiler$~D',
            $relativePath,
            $match
        ) === 1) {
            return $match[1] . '/var/profiler';
        }
        return $relativePath;
    }

    public function normalizeFile(string $relativePath, string $content): array
    {
        if (preg_match(
            '~^(sites/[a-z][a-z0-9-]*)/application/default/templates/components/profiler-link\.score$~D',
            $relativePath,
            $match
        ) === 1) {
            return [$match[1] . '/config/environment.yaml', $this->environmentYaml()];
        }

        if (str_ends_with($relativePath, '/config/routes.json')) {
            return [$relativePath, $this->withoutProfilerRoute($content, $relativePath)];
        }
        if (str_ends_with($relativePath, '/config/application.fsm.json')) {
            return [$relativePath, $this->withoutProfilerFsm($content, $relativePath)];
        }
        if (str_ends_with($relativePath, '/config/acl.json')) {
            return [$relativePath, $this->withoutProfilerAcl($content, $relativePath)];
        }
        if (str_ends_with($relativePath, '/config/site.json')) {
            return [$relativePath, $this->normalizeSiteDiagnostics($content, $relativePath)];
        }
        if (preg_match('~/application/default/local/[a-z]{2}\.json$~D', $relativePath) === 1) {
            return [$relativePath, $this->withoutProfilerMessage($content, $relativePath)];
        }
        if (str_ends_with($relativePath, '/application/default/templates/components/footer.score')) {
            return [
                $relativePath,
                '<footer class="opus-footer"><span>{{ site.name }}</span></footer>' . "\n",
            ];
        }
        if (str_ends_with($relativePath, '/application/default/layouts/layout.score')) {
            return [$relativePath, $this->layout($content)];
        }
        if (str_ends_with($relativePath, '/www/asset/css/default.css')) {
            return [
                $relativePath,
                str_replace(
                    '.opus-menu a,.opus-profiler-link{',
                    '.opus-menu a{',
                    $content
                ),
            ];
        }
        if (str_ends_with($relativePath, '/application/default/Application.php')
            && str_contains($content, 'Opus\\Application\\Runtime\\GeneratedSiteRuntime')
            && str_contains($content, 'Opus\\Profiler\\Profiler')) {
            return [$relativePath, $this->application($content)];
        }

        return [$relativePath, $content];
    }

    private function withoutProfilerRoute(string $content, string $source): string
    {
        $data = Json::instance()->parse($content, $source);
        if (!is_array($data['routes'] ?? null)) {
            return $content;
        }
        $data['routes'] = array_values(array_filter(
            $data['routes'],
            static fn (mixed $route): bool => !is_array($route)
                || (string) ($route['id'] ?? '') !== 'profiler.trace'
        ));
        return Json::instance()->encode($data, true);
    }

    private function withoutProfilerFsm(string $content, string $source): string
    {
        $data = Json::instance()->parse($content, $source);

        if (is_array($data['states'] ?? null)) {
            $data['states'] = array_values(array_filter(
                $data['states'],
                static fn (mixed $state): bool => !is_array($state)
                    || (string) ($state['id'] ?? '') !== 'profiler'
            ));
        }

        if (is_array($data['signals'] ?? null)) {
            $data['signals'] = array_values(array_filter(
                $data['signals'],
                static fn (mixed $signal): bool => !is_array($signal)
                    || (string) ($signal['id'] ?? '') !== 'open_profiler'
            ));
        }

        if (is_array($data['transitions'] ?? null)) {
            $transitions = [];
            foreach ($data['transitions'] as $transition) {
                if (!is_array($transition)) {
                    $transitions[] = $transition;
                    continue;
                }

                $from = trim((string) ($transition['from'] ?? ''));
                $to = trim((string) (
                    $transition['next_state']
                    ?? $transition['nextState']
                    ?? ''
                ));
                $signal = trim((string) ($transition['signal'] ?? ''));

                if ($from === 'profiler'
                    || $to === 'profiler'
                    || $signal === 'open_profiler') {
                    continue;
                }

                if (is_array($transition['from_states'] ?? null)) {
                    $sources = array_values(array_filter(
                        $transition['from_states'],
                        static fn (mixed $state): bool =>
                            is_string($state) && trim($state) !== 'profiler'
                    ));
                    if (($transition['scope'] ?? null) === 'global'
                        && $sources === []) {
                        continue;
                    }
                    $transition['from_states'] = $sources;
                }

                $transitions[] = $transition;
            }
            $data['transitions'] = $transitions;
        }

        return Json::instance()->encode($data, true);
    }
    private function withoutProfilerAcl(string $content, string $source): string
    {
        $data = Json::instance()->parse($content, $source);
        if (is_array($data['policies'] ?? null)) {
            unset($data['policies']['profiler:view']);
        }
        return Json::instance()->encode($data, true);
    }

    private function normalizeSiteDiagnostics(string $content, string $source): string
    {
        $data = Json::instance()->parse($content, $source);
        if (is_array($data['diagnostics']['profiler'] ?? null)) {
            unset(
                $data['diagnostics']['profiler']['required'],
                $data['diagnostics']['profiler']['trace_id']
            );
        }
        return Json::instance()->encode($data, true);
    }

    private function withoutProfilerMessage(string $content, string $source): string
    {
        $data = Json::instance()->parse($content, $source);
        if (is_array($data['messages'] ?? null)) {
            unset($data['messages']['profiler.link']);
        }
        return Json::instance()->encode($data, true);
    }

    private function layout(string $content): string
    {
        $block = "[[ if: diagnostics.profiler_available ]]\n"
            . '<a href="{{ diagnostics.profiler_url }}">{{ diagnostics.profiler_label }}</a>' . "\n"
            . "[[ endif ]]\n";
        if (str_contains($content, 'diagnostics.profiler_available')) {
            return $content;
        }
        $needle = "{{{ common.footer }}}\n";
        if (!str_contains($content, $needle)) {
            throw new \RuntimeException('OPUS_SCAFFOLD_LAYOUT_FOOTER_SLOT_MISSING');
        }
        return str_replace($needle, $needle . $block, $content);
    }

    private function application(string $content): string
    {
        if (preg_match(
            '/final class\s+([A-Za-z_][A-Za-z0-9_]*)\s+implements\s+([A-Za-z_][A-Za-z0-9_]*)/',
            $content,
            $match
        ) !== 1) {
            throw new \RuntimeException('OPUS_SCAFFOLD_APPLICATION_CLASS_UNRESOLVED');
        }
        $class = $match[1];
        $interface = $match[2];

        return "<?php\ndeclare(strict_types=1);\n\n"
            . "use Opus\\Application\\Runtime\\GeneratedSiteRuntime;\n"
            . "use Opus\\Http\\Response;\n\n"
            . "final class {$class} implements {$interface}\n{\n"
            . "    private static ?self \$instance = null;\n"
            . "    private readonly GeneratedSiteRuntime \$runtime;\n\n"
            . "    private function __construct(private readonly string \$siteRoot)\n    {\n"
            . "        \$this->runtime = new GeneratedSiteRuntime(\$siteRoot);\n"
            . "    }\n\n"
            . "    public static function instance(string \$siteRoot): self\n    {\n"
            . "        \$siteRoot = rtrim(str_replace('\\\\', '/', \$siteRoot), '/');\n"
            . "        if (self::\$instance instanceof self) {\n"
            . "            if (self::\$instance->siteRoot !== \$siteRoot) {\n"
            . "                throw new RuntimeException('OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH');\n"
            . "            }\n"
            . "            return self::\$instance;\n"
            . "        }\n"
            . "        return self::\$instance = new self(\$siteRoot);\n"
            . "    }\n\n"
            . "    private function __clone()\n    {\n    }\n\n"
            . "    public function __wakeup(): void\n    {\n"
            . "        throw new RuntimeException('OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN');\n"
            . "    }\n\n"
            . "    public function handle(): Response\n    {\n"
            . "        return \$this->runtime->handle();\n"
            . "    }\n\n"
            . "    public function run(): void\n    {\n"
            . "        \$this->handle()->send();\n"
            . "    }\n"
            . "}\n";
    }

    private function environmentYaml(): string
    {
        return <<<'YAML'
# Configuration d'environnement OPUS.
# Cette configuration est lue par File + StructuredFileLoader au bootstrap.
contract: OPUS_PROFILER_ENVIRONMENT_CONFIG_V1

# dev est le seul environnement autorisant le Web Profiler.
# En production, profiler.web.enabled ou profiler.web.links à true est refusé.
environment: dev

profiler:
  # Active la collecte des mesures Profiler.
  # En production, la valeur recommandée est false.
  collect: true

  web:
    # Enregistre la route Web Profiler générique OPUS.
    # Cette option est autorisée uniquement avec environment: dev.
    enabled: true

    # Injecte le lien de la trace courante dans le ViewModel SCORE.
    # En environnement de développement généré, le lien est visible par défaut.
    links: true
YAML;
    }
}
