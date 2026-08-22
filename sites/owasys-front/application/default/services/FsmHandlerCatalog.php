<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\Definition\FsmHandlerSourceEditor;

/**
 * Exposes EFSM handlers that are actually registered by OWASYS PHP.
 *
 * Application-programmed handlers are sourced from the managed PHP source
 * file. ACL guards remain dynamic. The catalog never invents executable
 * handlers from JSON descriptions.
 */
final class OwasysFsmHandlerCatalog
{
    public const CONTRACT = 'OWASYS_EFSM_HANDLER_CATALOG_V1';
    private const MANAGED_SOURCE =
        'application/default/services/FsmDeveloperHandlers.php';

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $fsm = StructuredFileLoader::instance()->read(
            $this->siteRoot . '/config/fsm.json'
        );
        $identity = $this->session->user();
        $identity = is_array($identity) ? $identity : null;

        $guardNames = (
            new OwasysFsmGuardHandlers($this->security)
        )->handlerNamesForConfig($fsm, $identity);

        $actionNames = (
            new OwasysFsmActionHandlers(
                $this->session,
                $this->security,
                new OwasysRegistryModel($this->siteRoot)
            )
        )->handlerNames();

        $guardNames = $this->uniqueNames($guardNames, 'GUARD');
        $actionNames = $this->uniqueNames($actionNames, 'ACTION');

        $guardSet = array_fill_keys($guardNames, true);
        $actionSet = array_fill_keys($actionNames, true);
        $this->assertDefinitionReferences(
            $fsm,
            $guardSet,
            $actionSet
        );

        $source = File::instance()->read(
            $this->siteRoot . '/' . self::MANAGED_SOURCE,
            1048576
        );
        $sourceSha256 = hash('sha256', $source);
        $managedCatalog = (new FsmHandlerSourceEditor())->catalog($source);
        $managedGuards = $this->managedMap($managedCatalog['guard']);
        $managedActions = $this->managedMap($managedCatalog['action']);

        $this->assertManagedRegistered(
            $managedGuards,
            $guardSet,
            'GUARD'
        );
        $this->assertManagedRegistered(
            $managedActions,
            $actionSet,
            'ACTION'
        );

        $guardDescriptions = is_array(
            $fsm['guards'] ?? null
        ) ? $fsm['guards'] : [];
        $actionDescriptions = is_array(
            $fsm['actions'] ?? null
        ) ? $fsm['actions'] : [];

        return [
            'contract' => self::CONTRACT,
            'managed_source_path' => self::MANAGED_SOURCE,
            'managed_source_sha256' => $sourceSha256,
            'guards' => $this->entries(
                $guardNames,
                $guardDescriptions,
                $managedGuards,
                true,
                $sourceSha256
            ),
            'actions' => $this->entries(
                $actionNames,
                $actionDescriptions,
                $managedActions,
                false,
                $sourceSha256
            ),
        ];
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function uniqueNames(
        array $names,
        string $kind
    ): array {
        $set = [];
        foreach ($names as $name) {
            $id = trim((string) $name);
            if (preg_match(
                '/^[a-z][a-z0-9_:-]{0,127}$/D',
                $id
            ) !== 1) {
                throw new RuntimeException(
                    'OWASYS_EFSM_' . $kind
                    . '_HANDLER_ID_INVALID:' . $id
                );
            }
            if (isset($set[$id])) {
                throw new RuntimeException(
                    'OWASYS_EFSM_' . $kind
                    . '_HANDLER_ID_DUPLICATE:' . $id
                );
            }
            $set[$id] = true;
        }

        $result = array_keys($set);
        sort($result, SORT_STRING);
        return $result;
    }

    /**
     * @param list<array{id:string,code:string,sha256:string}> $entries
     * @return array<string,array{id:string,code:string,sha256:string}>
     */
    private function managedMap(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id === '' || isset($result[$id])) {
                throw new RuntimeException(
                    'OWASYS_EFSM_MANAGED_HANDLER_CATALOG_INVALID'
                );
            }
            $result[$id] = $entry;
        }
        return $result;
    }

    /**
     * @param list<string> $names
     * @param array<string,mixed> $descriptions
     * @param array<string,array{id:string,code:string,sha256:string}> $managed
     * @return list<array<string,mixed>>
     */
    private function entries(
        array $names,
        array $descriptions,
        array $managed,
        bool $guard,
        string $sourceSha256
    ): array {
        $entries = [];
        foreach ($names as $name) {
            $description = $descriptions[$name] ?? '';
            $managedEntry = $managed[$name] ?? null;
            $isManaged = is_array($managedEntry);
            $entries[] = [
                'id' => $name,
                'description' => is_string($description)
                    ? trim($description)
                    : '',
                'source' => $isManaged
                    ? self::MANAGED_SOURCE
                    : 'application/default/services/FsmGuardHandlers.php',
                'dynamic' => $guard
                    && str_starts_with($name, 'acl:'),
                'managed' => $isManaged,
                'code' => $isManaged
                    ? (string) ($managedEntry['code'] ?? '')
                    : '',
                'handler_sha256' => $isManaged
                    ? (string) ($managedEntry['sha256'] ?? '')
                    : '',
                'source_sha256' => $isManaged ? $sourceSha256 : '',
            ];
        }
        return $entries;
    }

    /**
     * @param array<string,array{id:string,code:string,sha256:string}> $managed
     * @param array<string,true> $registered
     */
    private function assertManagedRegistered(
        array $managed,
        array $registered,
        string $kind
    ): void {
        foreach (array_keys($managed) as $id) {
            if (!isset($registered[$id])) {
                throw new RuntimeException(
                    'OWASYS_EFSM_' . $kind
                    . '_MANAGED_HANDLER_UNREGISTERED:' . $id
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $fsm
     * @param array<string,true> $guardSet
     * @param array<string,true> $actionSet
     */
    private function assertDefinitionReferences(
        array $fsm,
        array $guardSet,
        array $actionSet
    ): void {
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }

            foreach ($this->references(
                $transition['guards']
                    ?? ($transition['guard'] ?? [])
            ) as $guard) {
                if (!isset($guardSet[$guard])) {
                    throw new RuntimeException(
                        'OWASYS_EFSM_GUARD_HANDLER_UNREGISTERED:'
                        . $guard
                    );
                }
            }

            foreach ($this->references(
                $transition['actions']
                    ?? ($transition['action'] ?? [])
            ) as $action) {
                if (!isset($actionSet[$action])) {
                    throw new RuntimeException(
                        'OWASYS_EFSM_ACTION_HANDLER_UNREGISTERED:'
                        . $action
                    );
                }
            }
        }
    }

    /** @return list<string> */
    private function references(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $item): string =>
                    is_string($item) ? trim($item) : '',
                $value
            ),
            static fn (string $item): bool =>
                $item !== ''
        ));
    }
}