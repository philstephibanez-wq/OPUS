# P112Q3B3 — ASAP Recipe Final Status and Panther Binding

## Role

Stabilize the P112Q3B2 robotized evolutive life recipe so the visible report and the e-mail report expose the final mail status instead of a stale `PENDING` badge.

## Scope

- ASAP framework/tools only.
- No Apache/UwAmp change.
- No database change.
- No automatic Composer install.
- No hidden Panther fallback.
- Windows launchers remain `.cmd` only.

## Corrections

### Final mail status in the mailed HTML report

P112Q3B2 generated the first HTML body with `Mail: PENDING`, sent that body, then regenerated the disk report with the final status. Mailpit therefore received the stale report.

P112Q3B3 changes the mail phase so the body sent by SMTP/PHPMailer-compatible `mail()`/EML mode is built with the expected final successful status for the selected transport:

- `DELIVERED_TO_MAILPIT` for local SMTP `127.0.0.1:1025` / `localhost:1025`.
- `SENT` for other accepted SMTP or `phpmail` transport.
- `EML_WRITTEN` for non-delivery EML smoke mode.

If delivery fails, the saved JSON/Markdown/HTML reports are regenerated with the explicit failure status.

### Panther binding remains explicit

The recipe still never fakes Panther success. If `Symfony\Component\Panther\Client` is not available through the configured autoload, the badge remains `Panther: SKIPPED` or fails when `ASAP_P112Q3B2_PANTHER_REQUIRED=1`.

Use this variable when Panther is installed in a non-standard vendor directory:

```cmd
set ASAP_P112Q3B2_PANTHER_AUTOLOAD=H:\path\to\vendor\autoload.php
```

## Validation

Run:

```cmd
cd /d H:\ASAP
tools\smoke\run_p112q3b3_recipe_final_status_smoke.cmd
tools\recipes\run_p112q3b2_secure_life_robotized_recipe_mailpit.cmd
```

Expected smoke:

```text
P112Q3B2_SECURE_LIFE_ROBOTIZED_RECIPE_SMOKE_OK
P112Q3B3_RECIPE_FINAL_STATUS_SMOKE_OK
ExitCode=0
```

Expected Mailpit visual result:

```text
Mail: DELIVERED_TO_MAILPIT
```

not:

```text
Mail: PENDING
```
