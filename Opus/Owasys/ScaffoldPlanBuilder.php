<?php
declare(strict_types=1);

namespace Opus\Owasys;

use InvalidArgumentException;

/** Builds a typed, state-first OWASYS scaffold plan. */
final class ScaffoldPlanBuilder
{
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    private const PLAN_CONTRACT = 'OWASYS_SCAFFOLD_PLAN_V1';
    private const PROFILER_CONTRACT = 'OPUS_GENERATED_PROFILER_V1';
    private const ALLOWED_KINDS = ['fullstack', 'frontend', 'backend', 'package'];
    private const FORBIDDEN_SEGMENTS = ['..', 'public', 'src', 'resources'];

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function build(array $request): array
    {
        $id = $this->requiredPattern($request, 'id', '/^[a-z0-9][a-z0-9_-]*$/', 3, 64);
        $slug = $this->requiredPattern($request, 'slug', '/^[a-z0-9][a-z0-9-]*$/', 3, 96);
        $name = $this->requiredString($request, 'name', 3, 160);
        $kind = $this->requiredEnum($request, 'kind', self::ALLOWED_KINDS);
        $rootPath = $this->requiredPath($request, 'root_path', 3, 260);
        $this->assertSiteRootMatchesSiteId($id, $rootPath);
        $blueprint = $this->requiredString($request, 'blueprint', 3, 96);
        $defaultLocale = $this->requiredPattern($request, 'default_locale', '/^[a-z]{2}$/', 2, 2);
        $theme = $this->requiredPattern($request, 'theme', '/^[a-z0-9][a-z0-9_-]*$/', 3, 96);
        $states = $this->requiredStringList($request, 'controllers', 1, 32, '/^[a-z0-9][a-z0-9_-]*$/');
        $routes = $this->requiredArray($request, 'routes');
        $datasources = $this->requiredArray($request, 'datasources');
        $securityProfiles = $this->requiredArray($request, 'security_profiles');
        $workflows = $this->requiredArray($request, 'workflows');

        if (array_key_exists('profiler', $request) && $request['profiler'] !== true) {
            throw new InvalidArgumentException('OWASYS_PROFILER_MANDATORY');
        }
        if (!in_array('home', $states, true)) {
            throw new InvalidArgumentException('OWASYS_PLAN_HOME_STATE_REQUIRED');
        }

        return [
            'contract' => self::SITE_CONTRACT,
            'owasys_contract' => self::PLAN_CONTRACT,
            'site_id' => $id,
            'slug' => $slug,
            'name' => $name,
            'kind' => $kind,
            'blueprint' => $blueprint,
            'site_root' => $rootPath,
            'default_locale' => $defaultLocale,
            'theme' => $theme,
            'states' => $states,
            'controllers' => $states,
            'controller_field' => 'legacy_alias',
            'dispatch_model' => 'state-first',
            'routes' => $routes,
            'datasources' => $datasources,
            'security_profiles' => $securityProfiles,
            'workflows' => $workflows,
            'profiler' => [
                'enabled' => true,
                'mandatory' => true,
                'contract' => self::PROFILER_CONTRACT,
                'environment' => 'dev-only',
                'production_available' => false,
                'query_enable' => 'profiler=1',
                'query_disable' => 'profiler=0',
            ],
            'directories' => $this->directories($rootPath, $states, $theme, $defaultLocale),
            'files' => $this->files($rootPath, $states, $theme, $defaultLocale),
            'validation_commands' => [
                'php tools/smoke_opus_site_contract_eternal.php',
                'php tools/smoke_generated_opus_profiler.php',
                'php bin/opus validate:site ' . $id,
            ],
            'forbidden_output_roots' => ['public', 'src', 'resources'],
        ];
    }

    private function assertSiteRootMatchesSiteId(string $siteId, string $rootPath): void
    {
        $expected = 'sites/' . $siteId;
        if ($rootPath !== $expected) {
            throw new InvalidArgumentException('OWASYS_SITE_ROOT_MUST_MATCH_SITE_ID: ' . $expected);
        }
    }

    /** @param list<string> $states @return list<string> */
    private function directories(string $rootPath, array $states, string $theme, string $defaultLocale): array
    {
        $directories = [
            $rootPath, $rootPath . '/config', $rootPath . '/application',
            $rootPath . '/application/default', $rootPath . '/application/default/acl',
            $rootPath . '/application/default/helpers', $rootPath . '/application/default/css',
            $rootPath . '/application/default/javascript', $rootPath . '/application/default/local',
            $rootPath . '/application/default/local/' . $defaultLocale,
            $rootPath . '/application/default/models', $rootPath . '/application/default/templates',
            $rootPath . '/application/default/templates/components', $rootPath . '/application/default/views',
            $rootPath . '/application/states', $rootPath . '/www', $rootPath . '/www/asset',
            $rootPath . '/www/asset/css', $rootPath . '/www/asset/js', $rootPath . '/www/asset/themes',
            $rootPath . '/www/asset/themes/' . $theme, $rootPath . '/www/asset/themes/' . $theme . '/css',
            $rootPath . '/www/asset/themes/' . $theme . '/js', $rootPath . '/www/asset/themes/' . $theme . '/img',
        ];
        foreach ($states as $state) {
            foreach (['', '/acl', '/helpers', '/css', '/javascript', '/local', '/local/' . $defaultLocale, '/models', '/templates', '/views'] as $suffix) {
                $directories[] = $rootPath . '/application/states/' . $state . $suffix;
            }
        }
        return array_values(array_unique($directories));
    }

    /** @param list<string> $states @return list<array<string,string>> */
    private function files(string $rootPath, array $states, string $theme, string $defaultLocale): array
    {
        $files = [
            $this->file($rootPath . '/config/site.json', 'json', 'generated'),
            $this->file($rootPath . '/config/routes.json', 'json', 'generated'),
            $this->file($rootPath . '/config/menu.json', 'json', 'generated'),
            $this->file($rootPath . '/config/application.fsm.json', 'json', 'generated'),
            $this->file($rootPath . '/config/fsm.json', 'json', 'generated'),
            $this->file($rootPath . '/config/rubrics.json', 'json', 'generated'),
            $this->file($rootPath . '/application/default/templates/layout.score', 'score-template', 'blueprint'),
            $this->file($rootPath . '/application/default/templates/components/header.score', 'score-template', 'blueprint'),
            $this->file($rootPath . '/application/default/templates/components/footer.score', 'score-template', 'blueprint'),
            $this->file($rootPath . '/application/default/css/default.css', 'css', 'blueprint'),
            $this->file($rootPath . '/application/default/javascript/default.js', 'javascript', 'blueprint'),
            $this->file($rootPath . '/application/default/local/' . $defaultLocale . '/i18n.json', 'json', 'generated'),
            $this->file($rootPath . '/www/index.php', 'php-view-model', 'blueprint'),
            $this->file($rootPath . '/www/asset/themes/' . $theme . '/css/theme.css', 'css', 'blueprint'),
            $this->file($rootPath . '/www/asset/themes/' . $theme . '/js/theme.js', 'javascript', 'blueprint'),
        ];
        foreach ($states as $state) {
            $base = $rootPath . '/application/states/' . $state;
            $files[] = $this->file($base . '/templates/index.score', 'score-template', 'blueprint');
            $files[] = $this->file($base . '/views/index.php', 'php-view-model', 'generated');
            $files[] = $this->file($base . '/css/' . $state . '.css', 'css', 'blueprint');
            $files[] = $this->file($base . '/javascript/' . $state . '.js', 'javascript', 'blueprint');
            $files[] = $this->file($base . '/local/' . $defaultLocale . '/i18n.json', 'json', 'generated');
        }
        return $files;
    }

    /** @return array{path:string,kind:string,content_source:string} */
    private function file(string $path, string $kind, string $contentSource): array
    {
        return ['path' => $path, 'kind' => $kind, 'content_source' => $contentSource];
    }

    /** @param array<string,mixed> $source */
    private function requiredString(array $source, string $field, int $minLength, int $maxLength): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException('OWASYS_REQUIRED_STRING_INVALID: ' . $field);
        }
        $length = strlen($value);
        if ($length < $minLength || $length > $maxLength) {
            throw new InvalidArgumentException('OWASYS_STRING_LENGTH_INVALID: ' . $field);
        }
        return $value;
    }

    /** @param array<string,mixed> $source */
    private function requiredPattern(array $source, string $field, string $pattern, int $minLength, int $maxLength): string
    {
        $value = $this->requiredString($source, $field, $minLength, $maxLength);
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException('OWASYS_PATTERN_INVALID: ' . $field);
        }
        return $value;
    }

    /** @param array<string,mixed> $source @param list<string> $allowed */
    private function requiredEnum(array $source, string $field, array $allowed): string
    {
        $value = $this->requiredString($source, $field, 1, 96);
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('OWASYS_ENUM_INVALID: ' . $field);
        }
        return $value;
    }

    /** @param array<string,mixed> $source */
    private function requiredPath(array $source, string $field, int $minLength, int $maxLength): string
    {
        $value = str_replace('\\', '/', $this->requiredString($source, $field, $minLength, $maxLength));
        foreach (explode('/', $value) as $segment) {
            if (in_array($segment, self::FORBIDDEN_SEGMENTS, true)) {
                throw new InvalidArgumentException('OWASYS_PATH_FORBIDDEN_SEGMENT: ' . $field . ':' . $segment);
            }
        }
        return trim($value, '/');
    }

    /** @param array<string,mixed> $source @return array<int,mixed> */
    private function requiredArray(array $source, string $field): array
    {
        $value = $source[$field] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException('OWASYS_REQUIRED_ARRAY_INVALID: ' . $field);
        }
        return array_values($value);
    }

    /** @param array<string,mixed> $source @return list<string> */
    private function requiredStringList(array $source, string $field, int $minItems, int $maxItems, string $pattern): array
    {
        $items = $this->requiredArray($source, $field);
        if (count($items) < $minItems || count($items) > $maxItems) {
            throw new InvalidArgumentException('OWASYS_LIST_COUNT_INVALID: ' . $field);
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_string($item) || preg_match($pattern, $item) !== 1) {
                throw new InvalidArgumentException('OWASYS_LIST_ITEM_INVALID: ' . $field);
            }
            $result[] = $item;
        }
        return array_values(array_unique($result));
    }
}
