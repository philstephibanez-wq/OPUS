# P112Q3E5A — Router legacy vs Routing canonical

## Type

Documentation + smoke anti-regression.

## Files

- `framework/Asap/Routing/README.md`
- `framework/Asap/Router/README.md`
- `tools/smoke/p112q3e5a_router_legacy_vs_routing_canonical_smoke.php`
- `tools/smoke/run_p112q3e5a_router_legacy_vs_routing_canonical_smoke.cmd`
- `tools/patches/P112Q3E5A_ROUTER_LEGACY_VS_ROUTING_CANONICAL/patch_global_recipe.php`
- `APPLY_P112Q3E5A.cmd`
- `VERIFY_P112Q3E5A.cmd`

## Contract

`ASAP\Routing` is the canonical runtime routing domain.
`ASAP\Router` is preserved as a legacy/public lightweight route registry.

No runtime behavior change.
No deletion.
No fallback.
