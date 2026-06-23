# Cliente Unreal de ejemplo (C++) — prueba del flujo LTI

Actor mínimo de Unreal que prueba el flujo completo contra el backend:
canjea el `lti_session_token` (`/api/game/lti-redeem`) y envía la calificación
(`/api/game/sessions/{id}/complete`), lo que dispara el envío de la nota a Moodle (AGS).

## 1. Crear el proyecto
- Unreal Engine → **New Project → Games → Blank → C++** (no Blueprint). Nómbralo p. ej. `MetaversoTest`.

## 2. Agregar los módulos HTTP/JSON
En `Source/MetaversoTest/MetaversoTest.Build.cs`, en `PublicDependencyModuleNames`:
```csharp
PublicDependencyModuleNames.AddRange(new string[] {
    "Core", "CoreUObject", "Engine", "InputCore",
    "HTTP", "Json", "JsonUtilities"   // <-- agrega estos tres
});
```

## 3. Agregar la clase
- Copia `MetaversoClient.h` y `MetaversoClient.cpp` a `Source/MetaversoTest/`.
- (Si tu proyecto se llama distinto, no importa: la clase no depende del nombre del proyecto.)

## 4. Compilar
- Cierra el editor, compila desde tu IDE (Xcode en Mac), **o** usa **Live Coding** (Ctrl+Alt+F11) si el editor está abierto.

## 5. Obtener un `lti_session_token`
- En Moodle, entra a la actividad **"Práctica en el Metaverso"** (idealmente como **alumno** inscrito, para que la nota tenga casilla en el libro de calificaciones).
- Caes en la página **"Abrir juego"**. El botón apunta a
  `tecnm-metaverso://play?lti_session_token=XXXXX`.
  Copia la parte `XXXXX` (clic derecho → copiar enlace, o míralo en el código fuente de la página).
- El token dura 2 horas y es de **un solo uso** (al canjearlo se consume).

## 6. Ejecutar
- Arrastra un actor **MetaversoClient** a la escena.
- En **Details**, pega el token en **Lti Session Token** y ajusta **Calificacion** (0–100).
  `Base Url` ya está en `http://localhost:8000`.
- **Play** (PIE). Abre **Window → Output Log** y filtra por `[Metaverso]`:
  - `redeem HTTP 200` → token canjeado.
  - `complete HTTP 200` → calificación enviada.
- Revisa el **libro de calificaciones** del curso en Moodle: la nota debe aparecer.

> Alternativa sin tocar el actor: pasa el token al ejecutable con
> `-LtiToken=XXXXX` (el actor lo lee en BeginPlay).

## Notas
- Para que la nota **aparezca en el gradebook de Moodle**, el launch debe hacerse
  como **estudiante inscrito** en el curso (Moodle no califica a maestros/admin).
- El registro del esquema `tecnm-metaverso://` para que el deeplink abra Unreal
  automáticamente es un extra (Info.plist en Mac). Para probar, pegar el token a
  mano es suficiente.
- El backend debe estar corriendo (`php artisan serve --host=0.0.0.0 --port=8000`).
