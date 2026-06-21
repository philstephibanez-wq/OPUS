# OPUS FRONT boundary

`OPUS\\FRONT` owns representation only.

Allowed:

- views
- layouts
- sections
- components
- form and menu components
- renderers
- themes and assets
- API clients that call MIDDLE contracts

Forbidden:

- business actions
- services and repositories
- database access
- runners, jobs and workers
- direct BACK calls
- direct system commands

FRONT emits intentions. It never processes business work directly.
