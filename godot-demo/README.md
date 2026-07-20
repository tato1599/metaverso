# Demo "Recolecta" — Godot 4

Mini-juego 3D para enseñar la idea del laboratorio virtual: te mueves y recoges monedas.

## Cómo correrlo

1. Abre **Godot 4.x**.
2. En el gestor de proyectos: **Importar** → elige el archivo `godot-demo/project.godot` → **Importar y editar**.
3. Presiona **Play** (F5). Si te pide escena principal, es `Main.tscn`.

## Cómo se juega

- **WASD** para moverte.
- Recoge las **6 monedas**.
- Al juntarlas todas aparece la calificación (100).

## Qué es cada archivo

- `project.godot` — configuración del proyecto (escena principal = `Main.tscn`).
- `Main.tscn` — escena vacía que solo carga el script.
- `Main.gd` — todo el juego: arma el piso, el jugador, la cámara, las monedas y el HUD por código.

## Conectarlo al backend (después)

El juego está en modo demo (no habla con el servidor). En `Main.gd`, la función `_completar()`
es el gancho: ahí, en vez de solo mostrar el puntaje, un nodo `HTTPRequest` haría el flujo
`redeem → sessions → complete` para mandar la calificación de vuelta.
Ver `docs/api/flujo-del-juego.md` y `docs/api/tipos-de-juego.md` en el repo.
