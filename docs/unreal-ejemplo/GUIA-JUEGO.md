# Mini-juego en Unreal conectado al Metaverso (guía paso a paso)

Construye un juego jugable mínimo (recoger 3 monedas) que se conecta al backend:
al iniciar canjea el token, cada moneda suma un acierto, y al pisar la **Meta**
envía la calificación a Moodle.

Usa el **`MetaversoSubsystem`** (en esta carpeta). Es accesible desde cualquier
Blueprint con el nodo **"Get Game Instance Subsystem" → Metaverso Subsystem**.

## 0. Requisitos
- Backend corriendo: `php artisan serve --host=0.0.0.0 --port=8000`.
- Metal Toolchain de Xcode instalado (`xcodebuild -downloadComponent MetalToolchain`).

## 1. Proyecto + módulos + clase
1. Unreal → **New Project → Games → Third Person → C++** (te da personaje y nivel listos). Nómbralo `MetaversoJuego`.
2. En `Source/MetaversoJuego/MetaversoJuego.Build.cs`, agrega a `PublicDependencyModuleNames`:
   `"HTTP", "Json", "JsonUtilities"`.
3. Copia `MetaversoSubsystem.h` y `.cpp` a `Source/MetaversoJuego/`.
4. **Compila** (Xcode o Live Coding `Ctrl+Alt+F11`).

## 2. La moneda (BP_Moneda)
1. Content Browser → **Add → Blueprint Class → Actor**, nómbralo `BP_Moneda`.
2. Ábrelo → **Add Component → Sphere Collision** (raíz) y un **Static Mesh** (esfera) para verla.
3. En el Sphere Collision, evento **On Component Begin Overlap**:
   - **Cast** `Other Actor` a tu personaje (`BP_ThirdPersonCharacter`). Si el cast tiene éxito:
   - **Get Game Instance Subsystem** (clase: *Metaverso Subsystem*) → **Sumar Acierto**.
   - **DestroyActor (self)**.
4. Compila el Blueprint. Arrastra **3 monedas** al nivel.

## 3. La meta (BP_Meta)
1. Crea `BP_Meta` (Actor) con un **Box Collision** grande (visible con un mesh si quieres).
2. **On Component Begin Overlap** (con el personaje):
   - **Get Game Instance Subsystem** (Metaverso Subsystem) → **Terminar** con `Total Actividades = 3`.
   - (Opcional) muestra un texto "¡Terminado!".
3. Coloca **BP_Meta** al final del recorrido.

## 4. Iniciar la sesión al arrancar el nivel
1. Abre el **Level Blueprint** (Blueprints → Open Level Blueprint).
2. Evento **BeginPlay** → **Get Game Instance Subsystem** (Metaverso Subsystem) → **Iniciar Sesion**.
   - Deja el parámetro `Lti Session Token` **vacío** (usará el token de `-LtiToken=`),
     o pega el token como literal ahí.

## 5. (Opcional) HUD con eventos
En el Level Blueprint, después de obtener el subsystem, puedes **Bind Event** a:
- **On Sesion Lista** → mostrar "Conectado".
- **On Calificacion Enviada** → mostrar "Nota enviada a Moodle".
- **On Error** → mostrar el mensaje de error.
Cada uno entrega un `Mensaje` (string) para pintarlo con *Print String* o un widget.

## 6. Pasar el token al jugar (PIE)
1. **Edit → Editor Preferences → Level Editor → Play → Additional Launch Parameters:**
   pon `-LtiToken=EL_TOKEN_DEL_DEEPLINK`.
   - Consigue el token entrando a la actividad en Moodle (como **alumno inscrito**),
     cayendo en "Abrir juego" y copiando el `lti_session_token` del deeplink.
   - (Recuerda: token de un solo uso, 2 h. Si lo gastas, relanza la actividad para uno nuevo.)
2. **Play** ▶. Recoge las 3 monedas y pisa la Meta.
3. **Window → Output Log**, filtra `[Metaverso]`:
   - `Sesión N lista` (al inicio) · `Aciertos: 1/2/3` · `Calificación 100 enviada a Moodle`.

## 7. Ver la nota en Moodle
Curso **Demo Metaverso → Grades**: la calificación de la práctica aparece (100 si
recogiste las 3 monedas; proporcional si menos). 🎯

---

### Notas
- La nota solo aparece en el gradebook si el launch fue como **alumno inscrito**.
- Para que el deeplink abra Unreal **solo** (sin pegar el token) hay que registrar el
  esquema `tecnm-metaverso://` (Info.plist en Mac) y leer la URL de arranque — es un
  extra de pulido; para la demo, `-LtiToken=` es suficiente.
- Si quieres el flujo "todo de un golpe" sin gameplay, usa el `MetaversoClient.h/.cpp`
  (Actor que canjea y completa en BeginPlay).
