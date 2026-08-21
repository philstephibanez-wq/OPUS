<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

/**
 * Exposes only EFSM handlers that are actually registered by OWASYS PHP.
 *
 * Descriptions remain optional metadata from fsm.json. Registration authority
 * comes from the real guard/action handler maps, never from the JSON labels.
 */
final class OwasysFsmHandlerCatalog
{
    public const CONTRACT = 'OWASYS_EFSM_HANDLER_CATALOG_V1';

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

        $guardNames = $this->uniqueNames(
            $guardNames,
            'GUARD'
        );
        $actionNames = $this->uniqueNames(
            $actionNames,
            'ACTION'
        );

        $guardSet = array_fill_keys($guardNames, true);
        $actionSet = array_fill_keys($actionNames, true);
        $this->assertDefinitionReferences(
            $fsm,
            $guardSet,
            $actionSet
        );

        $guardDescriptions = is_array(
            $fsm['guards'] ?? null
        ) ? $fsm['guards'] : [];
        $actionDescriptions = is_array(
            $fsm['actions'] ?? null
        ) ? $fsm['actions'] : [];

        return [
            'contract' => self::CONTRACT,
            'guards' => $this->entries(
                $guardNames,
                $guardDescriptions,
                'application/default/services/FsmGuardHandlers.php',
                true
            ),
            'actions' => $this->entries(
                $actionNames,
                $actionDescriptions,
                'application/default/services/FsmActionHandlers.php',
                false
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
     * @param list<string> $names
     * @param array<string,mixed> $descriptions
     * @return list<array{
     *   id:string,
     *   description:string,
     *   source:string,
     *   dynamic:bool
     * }>
     */
    private function entries(
        array $names,
        array $descriptions,
        string $source,
        bool $guard
    ): array {
        $entries = [];
        foreach ($names as $name) {
            $description = $descriptions[$name] ?? '';
            $entries[] = [
                'id' => $name,
                'description' => is_string($description)
                    ? trim($description)
                    : '',
                'source' => $source,
                'dynamic' => $guard
                    && str_starts_with($name, 'acl:'),
            ];
        }
        return $entries;
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
