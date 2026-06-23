# Progreso — Backend Metaverso MVP

Plan: docs/superpowers/plans/2026-06-23-backend-metaverso-mvp.md
Rama: feat/backend-metaverso-mvp

## Tareas
- [x] Task 1: Scaffold Laravel + PostgreSQL + Sanctum (commits 82aa4c2..d8d0175, review clean)
- [x] Task 2: Migraciones catálogo académico (commits d8d0175..b809437, review clean)
- [x] Task 3: Migraciones de operación (commits b809437..ec8fc04, review clean)
- [x] Task 4: Modelos Eloquent (commits ec8fc04..HEAD, review clean)
- [x] Task 5: MagicLinkService (commits ed98643..52fb895, review clean)
- [x] Task 6: POST /api/game/redeem (commits 52fb895..0b7d1a9, review clean)
- [x] Task 7: Sesiones de práctica (commits 0b7d1a9..0908d26, review clean)
- [x] Task 8: /api/links + /jugar/{token} (commits 0908d26..cee823b, review clean)
- [x] Task 9: Seeders + comando (commits cee823b..b551d7c, review clean)
- [x] Task 10: README + verificación (commits b551d7c..HEAD, review clean)

## Security Fixes (post-plan)
- [x] Fix 1: Atomic redeem / replay race (commit f9a72f7..64ccbf8)
- [x] Fix 2: Enforce `game` Sanctum ability (commit f9a72f7..64ccbf8)
- [x] Fix 3: Sanctum token expiration 8h default (commit f9a72f7..64ccbf8)

## Revisión final (whole-branch)
- Final review (opus): sin Critical. 3 hardening aplicados (commit 64ccbf8): redeem atómico, enforce ability `game`, expiración Sanctum.
- Scope fixes aplicados (commit af01dab): (a) enrollment check en `start` con 403 para no inscritos; (b) API key guard en `/api/links` con hash_equals.
- Suite: 21 passed (73 assertions).
- Suite: 19 passed (71 assertions).
