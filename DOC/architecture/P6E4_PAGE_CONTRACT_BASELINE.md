# P6E4 Page Contract Baseline

A public page is valid only when the full chain is explicit:

Route -> Controller/Action -> FSM -> ACL -> ViewModel -> Layout

Rules:

- No public page without a route.
- No public page without a controller/action.
- No public page without an FSM state or transition.
- No public page without an ACL policy, even when public.
- No public page rendering directly from raw data.
- ViewModel prepares render-ready data.
- Layout renders representation only.
- Optional packages may install pages into an application.
- OPUS core must not depend on optional packages.
