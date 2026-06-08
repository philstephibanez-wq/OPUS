# P112Q3B2 — ASAP Secure Life Robotized Recipe

- Generated at: `2026-06-08T16:46:44+00:00`
- Mail status: `DELIVERED_TO_MAILPIT`
- Panther status: `SKIPPED`

| User | Lang | Method | Type | Route | Expected | Observed | Result |
|---|---:|---:|---|---|---|---|---|
| guest | FR | GET | navigation | `/asap-secure-life/fr/public` | ALLOWED | ALLOWED | OK |
| editor | ES | GET | navigation | `/asap-secure-life/es/editor` | ALLOWED | ALLOWED | OK |
| admin | EN | GET | navigation | `/asap-secure-life/en/admin` | ALLOWED | ALLOWED | OK |
| guest | FR | GET | navigation | `/asap-secure-life/en/admin` | DENIED | DENIED | OK |
| editor | ES | GET | navigation | `/asap-secure-life/en/admin` | DENIED | DENIED | OK |
| guest | FR | POST | form | `/asap-secure-life/fr/contact` | ALLOWED | ALLOWED | OK |
| editor | ES | POST | form | `/asap-secure-life/es/editor/form` | ALLOWED | ALLOWED | OK |
| admin | EN | POST | form | `/asap-secure-life/en/admin/settings` | ALLOWED | ALLOWED | OK |
| guest | FR | POST | form | `/asap-secure-life/en/admin/settings` | DENIED | DENIED | OK |
| editor | ES | POST | form | `/asap-secure-life/en/admin/settings` | DENIED | DENIED | OK |
| guest | FR | GET | form | `/asap-secure-life/fr/contact` | DENIED | DENIED | OK |
