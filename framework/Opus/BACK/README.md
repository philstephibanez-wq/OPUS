# OPUS BACK boundary

`OPUS\\BACK` owns business logic, data treatment, runners, jobs, workers and external integrations.

Allowed:

- business modules
- actions
- services
- repositories
- validators
- policies
- DTO builders
- view model builders
- database access
- runners, jobs and workers
- external adapters for PHP, Lua, Python, C++, CLI or other runtimes

Forbidden:

- HTML rendering
- views, layouts, sections and components
- frontend navigation
- direct public routing
- bypassing MIDDLE/FSM

BACK only executes operations already authorized by the FSM path in MIDDLE.
