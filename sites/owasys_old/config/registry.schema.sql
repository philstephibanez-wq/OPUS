-- OWASYS registry schema.
-- Runtime SQLite database is var/registry/owasys.sqlite and must not be committed.

CREATE TABLE IF NOT EXISTS owasys_applications (
    id TEXT PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    kind TEXT NOT NULL CHECK (kind IN ('fullstack', 'frontend', 'backend', 'package')),
    root_path TEXT NOT NULL,
    public_root TEXT NOT NULL DEFAULT 'www',
    default_locale TEXT NOT NULL DEFAULT 'fr',
    theme TEXT NOT NULL DEFAULT 'starter',
    local_url TEXT,
    git_remote TEXT,
    git_branch TEXT,
    composer_package TEXT,
    status TEXT NOT NULL CHECK (status IN ('draft', 'configured', 'validated', 'generated', 'exported', 'archived')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS owasys_application_datasources (
    id TEXT PRIMARY KEY,
    application_id TEXT NOT NULL,
    label TEXT NOT NULL,
    engine TEXT NOT NULL,
    access TEXT NOT NULL,
    dsn TEXT,
    database_path TEXT,
    model_required INTEGER NOT NULL DEFAULT 1,
    silent_fallback INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(application_id) REFERENCES owasys_applications(id)
);

CREATE TABLE IF NOT EXISTS owasys_workflows (
    id TEXT PRIMARY KEY,
    application_id TEXT NOT NULL,
    label TEXT NOT NULL,
    states_json TEXT NOT NULL,
    transitions_json TEXT NOT NULL,
    FOREIGN KEY(application_id) REFERENCES owasys_applications(id)
);

CREATE TABLE IF NOT EXISTS owasys_security_profiles (
    id TEXT PRIMARY KEY,
    application_id TEXT NOT NULL,
    profile TEXT NOT NULL,
    permissions_json TEXT NOT NULL,
    FOREIGN KEY(application_id) REFERENCES owasys_applications(id)
);

CREATE TABLE IF NOT EXISTS owasys_runtime_context (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    current_state TEXT NOT NULL,
    current_application_id TEXT,
    context_json TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS owasys_transition_history (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    from_state TEXT NOT NULL,
    event TEXT NOT NULL,
    to_state TEXT NOT NULL,
    application_id TEXT,
    guards_json TEXT NOT NULL,
    actions_json TEXT NOT NULL,
    result TEXT NOT NULL CHECK (result IN ('accepted', 'rejected', 'error')),
    message TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS owasys_transition_drafts (
    id TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    source_contract TEXT NOT NULL DEFAULT 'OWASYS_NAVIGATION_FSM_V1',
    draft_json TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'review', 'promoted', 'rejected')),
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
