# Demo "Recolecta" — Godot 4

Mini-juego 3D del laboratorio virtual: te mueves con **WASD** y recoges monedas.
Puede correr **conectado al backend** (envía la calificación a Moodle) o **sin conexión**.

## Correrlo

1. Abre **Godot 4.x**.
2. Gestor de proyectos → **Importar** → `godot-demo/project.godot` → **Importar y editar**.
3. **Play (F5)**. Aparece una pantalla de inicio.

## Modo sin conexión (lo más rápido de mostrar)

En la pantalla de inicio pulsa **"Jugar sin conexión"**. Recoge las 6 monedas y sale
la calificación en pantalla. No necesitas servidor.

## Modo conectado (envía la calificación al backend)

1. Levanta el backend: en la raíz del repo, `composer dev` (o `php artisan serve`).
2. Consigue un **token**: en el panel, un maestro genera los magic links de un grupo
   (Grupo → evento → *Generar links del grupo*). Cada alumno tiene una URL
   `…/jugar/<token>`; copia el `<token>` del final.
3. En la pantalla de inicio del juego: pon la **URL del servidor** (ej. `http://127.0.0.1:8000`),
   pega el **token**, y pulsa **"Conectar y jugar"**.
4. Recoge las 6 monedas: al terminar, el juego hace `redeem → sessions → complete` y
   manda la calificación **100** de vuelta. En el panel de resultados la verás registrada.

## Variables de entorno (opcionales)

Para prellenar o automatizar (útil en un kiosco o para probar):

| Variable     | Efecto                                                       |
|--------------|--------------------------------------------------------------|
| `DEMO_URL`   | Prellena la URL del servidor.                                |
| `DEMO_TOKEN` | Prellena el token.                                           |
| `DEMO_AUTO=1`| Conecta y completa solo (sin jugar) y cierra. Para probar.   |

## Archivos

- `project.godot` — config del proyecto (escena principal = `Main.tscn`).
- `Main.tscn` — escena vacía que carga el script.
- `Main.gd` — todo: mundo 3D por código, pantalla de inicio y flujo HTTP al backend.

El contrato de la API está en `docs/api/flujo-del-juego.md` y `docs/api/tipos-de-juego.md`.
