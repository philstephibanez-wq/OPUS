# P117SITE25D Physical boundary migration real

This document defines the real physical framework tree target:

```text
framework/Opus/
  FRONT/
  MIDDLE/
  BACK/
  COMMON/
```

Mermaid diagrams are mandatory for architecture documentation and FSM transition documentation.

```mermaid
flowchart LR
  FRONT --> MIDDLE
  MIDDLE --> BACK
  COMMON --- FRONT
  COMMON --- MIDDLE
  COMMON --- BACK
```
