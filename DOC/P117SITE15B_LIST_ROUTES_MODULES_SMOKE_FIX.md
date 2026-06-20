# P117SITE15B — list-routes/list-modules smoke expectation fix

## Status

P117SITE15 commands were created correctly, but the first smoke expected the
route id `home` while the real command output uses the explicit route id
`home.index`.

## Fix

The smoke now checks the real route ids emitted by OPUS:

- `home.index`
- `pages.index`
- `articles.index`
- `rubriques.index`
- `documentation.index`

## Contract

The generated `sites/skeleton` directory remains an artifact and must be deleted
after the smoke.
