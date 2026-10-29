# SDD ledger - plan: docs/superpowers/plans/2026-10-29-bdms.md
Pre-flight: tasks share helpers only; no conflicts.
Ruling: no git repo, no worktree/commits - folder is not versioned.
Ruling: Apache on :8081, DB user bdms created via root once; root pw not stored.
Ruling: api_test history expected 3 points (older offline point is in range).
Task 1: complete (tests/run.php 14/14)
Task 2: complete (pages lint clean; manual check pending in Task 7)
Task 3: complete (api_test 16/16)
Task 5 API: complete (history.php covered by api_test)
Task 4: complete (dashboard + map.js verified in browser desktop+375px, markers moving)
Task 5: complete (history page verified, 19 points)
Task 6: complete (gradle testDebugUnitTest 6/6, assembleDebug OK, APK 786 KB)
Task 7: complete (emulator install+pair+foreground service+GPS fix reached server; demo data deleted)
Ruling: CARTO dark tiles now need an API key -> OSM tiles + CSS dark filter, source isolated in assets/tiles.js.
Ruling: Kotlin uses plain LocationManager (no Play Services/androidx) to keep build light; compileSdk/targetSdk 33 (installed platform).
Ruling: added .htaccess (403 for lib/tests/docs/config/schema) - tests/ would otherwise be web-runnable.
Final: self-review (no subagent used). Minor (deferred): no login rate limiting; api tokens stored plaintext; release signing key not set up.
