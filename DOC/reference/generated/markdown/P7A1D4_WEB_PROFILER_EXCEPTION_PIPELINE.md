# P7A1D4 Web Profiler exception pipeline

Status: OK

Routes:

- /_opus/profiler
- /_opus/profiler/trace/{trace_id}

FSM runtime config: config/fsm_runtime/

Collectors: request, routing, exception, template, database, config, mail, memory, runtime.
