# Cómo funciona el Metaverso Escolar TecNM

Documento para entender **todo el flujo** sin tecnicismos. Pensado para
explicarlo a maestros, coordinadores y al área de sistemas.

---

## 1. La idea en una frase

Los alumnos hacen sus **prácticas dentro de un videojuego** (en Unreal Engine).
El juego se conecta a un **servidor web** (este backend) que sabe quién es cada
alumno, qué práctica le toca, y **guarda la calificación** que el alumno obtuvo
al terminar.

---

## 2. Las piezas

| Pieza | Qué es | Quién la usa |
|-------|--------|--------------|
| **Backend (este sistema)** | Servidor web en Laravel + base de datos PostgreSQL. Es el "cerebro": usuarios, prácticas, calificaciones. | Nadie lo usa directo; lo consultan el juego y la web. |
| **Juego Unreal** | El metaverso donde el alumno entra y hace la actividad. | Los alumnos. |
| **Magic link** | Un enlace especial, de **un solo uso**, que mete al alumno a su práctica sin contraseña. | Lo genera el maestro/sistema; lo abre el alumno. |
| **API** | El "idioma" con el que el juego le habla al backend. | El juego Unreal. |

---

## 3. El flujo, paso a paso

> 💡 Versión visual e interactiva: abre `http://localhost:8000/demo` con el
> servidor corriendo. Ahí puedes **ver el diagrama** y **ejecutar el flujo en
> vivo**.

### Paso 1 — El maestro reparte el acceso
El maestro (o el sistema) genera un **magic link** para un alumno y una práctica
concreta. Ese link se le hace llegar al alumno (por ahora pegándolo en Moodle,
ver `INTEGRACION-MOODLE-LTI.md`).

- El link se ve así: `https://servidor/jugar/UNTOKENLARGOYUNICO`
- Internamente el token se guarda **encriptado (hash)** y **caduca a las 2 horas**
  (configurable). Solo sirve **una vez**.

### Paso 2 — El alumno abre el link
Al hacer clic, ve una página con un botón **"Abrir juego"**. Ese botón lanza el
juego de Unreal instalado en su computadora (usando un "deep link"
`tecnm-metaverso://...`).

### Paso 3 — El juego canjea el token
Unreal toma el token y le pregunta al backend: *"¿este token es válido?"*
(`POST /api/game/redeem`). El backend:
1. Verifica que el token exista, no haya caducado y no se haya usado.
2. Lo marca como **usado** (ya no sirve otra vez).
3. Le devuelve al juego: **quién es el alumno**, **qué práctica** le toca, y una
   **llave temporal de sesión** (Bearer) para las siguientes llamadas.

### Paso 4 — El alumno hace la práctica
El juego avisa al backend que la sesión **empezó**
(`POST /api/game/sessions`). El backend confirma que el alumno **está inscrito**
en ese grupo (si no, lo rechaza).

### Paso 5 — El alumno termina → se guarda la calificación
Al completar la actividad, el juego envía el **resultado**
(`POST /api/game/sessions/{id}/complete`): la **calificación (0–100)** y datos de
telemetría (aciertos, errores, tiempo, etc., en formato libre). El backend lo
guarda en la base de datos.

---

## 4. ¿Qué garantiza el sistema? (seguridad en simple)

- **Sin contraseñas para el alumno**: entra con el magic link. Menos soporte.
- **Un token = un acceso**: no se puede reusar ni compartir (caduca y es de un
  solo uso, validado de forma atómica para evitar trampas).
- **Cada quien ve lo suyo**: un alumno no puede cerrar la sesión de otro ni
  calificar prácticas en las que no está inscrito.
- **Datos sensibles protegidos**: las contraseñas (de maestros/admin) nunca
  viajan ni se muestran.

---

## 5. ¿Dónde queda la calificación?

Hoy queda en la tabla `sesiones_practica` (campo `calificacion`) dentro de este
backend. El maestro la puede consultar aquí.

**El siguiente paso natural** es que esa calificación **regrese sola a Moodle**,
para que el maestro no la copie a mano. Eso se logra con **LTI** — explicado en
`INTEGRACION-MOODLE-LTI.md`.

---

## 6. Resumen del recorrido de los datos

```
Maestro  ─genera→  Magic link  ─abre→  Alumno  ─lanza→  Unreal
                                                           │
Unreal  ─canjea token→  Backend  ─responde→  alumno + práctica + llave
Unreal  ─inicia/termina→  Backend  ─guarda→  calificación + telemetría
                                                           │
                                              (Fase 2) ─→ Moodle (vía LTI)
```

---

## 7. ¿Qué ya está construido y qué falta?

**Listo (probado, 22 tests en verde):**
- Base de datos completa, magic link, API de juego, calificaciones, datos demo,
  documentación interactiva (`/docs`) y demo visual (`/demo`).

**Falta (Fase 2):**
- Integración con Moodle vía LTI (identidad automática + calificación de regreso).
- Panel para maestros (ver resultados, generar links por grupo de un clic).
- El proyecto de Unreal en sí (este repo es el backend + la API que el juego usa).
