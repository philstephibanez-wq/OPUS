# OPUS — P7 LSTSAR destination assignments core

## Status

`P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE` adds destination-field assignments to the LSTSAR transform stage.

## Problem solved

A destination table/model may contain fields that do not exist in the source table/model.

Examples:

- `client_id`
- `site_id`
- `created_at`
- `created_by`
- `source_hash`
- `batch_id`
- `run_label`
- audit or routing fields

These fields must be filled before `Store`, otherwise the final destination record is incomplete.

## Decision

Assignments belong to stage `03_Transform`, not `04_Store`.

`Store` remains responsible for destination-model validation and ODBC storage. It does not compute business values.

## Supported assignment types

- `constant`
- `now`
- `metadata`
- `security`
- `source`
- `destination` / `transformed`
- `hash`
- `concat`
- `hook`

## Hook policy

Hooks are allowed only through an explicit named registry:

- no free PHP code in config;
- no raw SQL;
- no DDL;
- no hidden writes;
- pure calculation preferred;
- hook output is visible in dry-run and report payloads.

## Example

```php
'transform' => [
    'fields' => [
        'code' => ['trim' => true, 'uppercase' => true],
        'amount' => ['cast' => 'float', 'round' => 2],
    ],
    'assignments' => [
        'client_id' => [
            'type' => 'constant',
            'value' => 'CLIENT_001',
        ],
        'created_by' => [
            'type' => 'metadata',
            'path' => 'actor_id',
            'default' => 'lstsar',
        ],
        'source_hash' => [
            'type' => 'hash',
            'source' => 'source',
            'fields' => ['legacy_code', 'legacy_amount'],
        ],
        'label' => [
            'type' => 'hook',
            'hook' => 'orders.compute_label',
        ],
    ],
]
```

## Runtime files added

- `Opus/Lstsar/LstsarTransformHookInterface.php`
- `Opus/Lstsar/LstsarTransformHookContext.php`
- `Opus/Lstsar/LstsarTransformHookRegistry.php`

## Runtime file updated

- `Opus/Lstsar/03_Transform.php`

## Compatibility

Legacy transform declarations remain supported:

```php
'transform' => [
    'code' => ['trim' => true, 'uppercase' => true],
]
```

The new normalized form is preferred:

```php
'transform' => [
    'fields' => [
        'code' => ['trim' => true, 'uppercase' => true],
    ],
    'assignments' => [],
]
```
