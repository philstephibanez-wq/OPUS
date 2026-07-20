# CHANGELOG P7A0J_CLEAN_CLONE_I18N_SMTP_GATES

## 2026-07-20 — OPUS_ROUTER_FSM_FIRST

- Updated `Opus/Routing/Router.php` so localized routes resolve to FSM signals.
- Removed direct URL-to-page routing from the runtime router.
- Added mandatory FSM transition resolution from `source_state + signal`.
- MVC rendering is now selected from the transition `target_state`.
- Added application `initial_state` validation.
- Added validation that `routes.php` maps localized URLs to non-empty FSM signals.
- Updated the FSM-first engine contract to document the routing authority.
- API dispatch remains separate and unchanged by this routing patch.

## P7A0J_CLEAN_CLONE_I18N_SMTP_GATES

- Added P7A0J clean-clone validation smoke for P7A0I I18N/SMTP contract.
- Added root runner `.cmd` for VS Code terminal usage.
- Added tracked root hygiene guard against accidental capture/archive artifacts.
- No runtime feature change.
- No framework refactor.
- No SMTP behavior change.
- No I18N behavior change.
