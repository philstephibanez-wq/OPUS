<?php
declare(strict_types=1);
/* Merge contract for the live RuntimeController:
1 resolve request -> FSM event; 2 authenticate through OwasysRuntimeSecurity; 3 authorize target module through ACL; 4 execute FSM transition; 5 dispatch target state; 6 render only through OwasysScorePageRenderer and .score templates. No route-specific HTML and no PHP layout. This integration marker is not auto-loaded and must be merged after a local diff against the source commit in PATCH_MANIFEST.json. */
