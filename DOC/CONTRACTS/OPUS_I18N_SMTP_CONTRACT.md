# OPUS I18N / SMTP Contract

Milestone: `P7A0I_I18N_SMTP_CONTRACT`

## Purpose

This document locks the runtime contract for user-visible language and mail delivery in OPUS.

## I18N contract

I18N is mandatory even when an application uses only one language.

A single-language site still has:

- one explicit default locale;
- stable I18N keys;
- explicit fallback behavior;
- a future-safe translation surface.

### Mandatory I18N scope

Every user-visible text emitted by a public application path must pass through I18N:

- page titles;
- menus;
- buttons;
- labels;
- form text;
- public validation messages;
- public error messages;
- mail subjects;
- mail HTML bodies;
- mail text bodies;
- notification messages.

### I18N exceptions

The following technical strings are not user-facing I18N content and may remain raw:

- internal logs;
- class names;
- constants;
- machine identifiers;
- smoke/test messages;
- raw profiler traces;
- low-level internal exception codes.

## SMTP contract

SMTP is optional only for sites that send no email.

As soon as an application sends email, the official OPUS SMTP/mailer service is mandatory.

### Forbidden mail patterns

Application/controller/template code must not perform direct mail delivery:

- no direct `mail(...)` from controllers, templates, actions, view models, or business code;
- no direct ad-hoc SMTP socket code;
- no random PHPMailer construction outside the official OPUS mailer service;
- no SMTP host/user/password hardcoded in source code;
- no silent fallback from SMTP to PHP `mail()`.

### Required mail path

The official path is:

```text
Controller / Action
  -> business service / official mailer
  -> I18N mail subject/body templates
  -> configured OPUS SMTP service
  -> Logger / Diagnostics / Profiler when enabled
```

### Failure behavior

If mail sending is requested and SMTP is missing or invalid, OPUS must fail explicitly.

No silent send failure.
No silent local fallback.
No hidden credentials in logs/traces.

## Page contract extension

The OPUS public page contract includes I18N:

```text
Page = Route + Controller/Action + FSM + ACL + ViewModel + Layout + I18N
```

SMTP is not part of every page, but it is mandatory for every mail-sending workflow.
