# OPUS ScoreTemplate Contract Final

## Milestone

`P7_SCORETEMPLATE_CONTRACT_FINAL`

## Contract

ScoreTemplate is the native OPUS view template renderer.

It is dependency-free, explicit and reusable. It must not silently delegate to another renderer or another template grammar.

## Covered syntax

- escaped interpolation: `{{ path.to.value }}`
- raw interpolation: `{{{ path.to.html }}}`
- include directive: `[[ include:partials/file.score ]]`
- conditional directive: `[[ if: condition ]]`, `[[ else ]]`, `[[ endif ]]`
- loop directive: `[[ foreach: items as item ]]`, `[[ foreach: items as key, item ]]`, `[[ endforeach ]]`
- internal ignored block: `[[ ignore ]]`, `[[ ignore: note ]]`, `[[ endignore ]]`

## Ignore block contract

Ignored blocks are removed from rendered output and their internal expressions/directives are not evaluated.

Nested ignored blocks are supported.

Explicit diagnostics:

- `OPUS_SCORE_TEMPLATE_UNEXPECTED_ENDIGNORE`
- `OPUS_SCORE_TEMPLATE_UNCLOSED_IGNORE`
- `Opus\Contract\ContractException::because()` is the explicit framework contract exception factory.

## Smoke

```cmd
cd /d H:\OPUS
php tools\smokes\smoke_p7_scoretemplate_contract_final.php
```
