# Integración con Moodle (LTI) — cómo conectarlo con el mínimo de trámites

Contexto: el TecNM es **institución pública**, ya usa **Moodle**, y queremos que
**los maestros no batallen** y que haya **el mínimo de papeleo/burocracia**.

Este documento explica las opciones reales, sus trámites, y **mi recomendación**.

---

## TL;DR (la recomendación)

| Etapa | Qué usar | Trámite con el admin de Moodle | Calificación regresa sola a Moodle |
|-------|----------|-------------------------------|-----------------------------------|
| **Hoy / piloto** | **Magic link pegado en Moodle** | ❌ Ninguno | ❌ No (el maestro la consulta en nuestro sistema) |
| **Producción** | **LTI 1.3** | ✅ **Un solo trámite, una vez** | ✅ Sí, automático |

> **La verdad sin adornos:** para que la calificación regrese sola a Moodle y el
> alumno se identifique automáticamente, **hay que tocar al admin de Moodle una
> sola vez** (registrar la herramienta). No hay forma de evitar ese único paso.
> Pero es **una vez para toda la escuela**: después, cada maestro la usa sin
> configurar nada.

---

## Opción A — Magic link pegado en Moodle (cero trámites, ya funciona)

**Cómo:** el maestro genera el magic link (hoy con `POST /api/links`, en Fase 2
con un botón en un panel) y lo **pega en Moodle** como un recurso tipo "URL" o
un enlace en la actividad.

- ✅ **No requiere NADA del admin de Moodle.** Funciona hoy mismo.
- ✅ Bueno para un **piloto** con uno o dos grupos.
- ⚠️ La calificación **no** regresa a Moodle automáticamente: el maestro la ve en
  nuestro sistema.
- ⚠️ Hoy el link es **por alumno** (un solo uso). Para un grupo de 30 hay que
  generar 30 links. *Mejora fácil de Fase 2: un botón "generar links de todo el
  grupo".*

**Veredicto:** ideal para **empezar ya** sin pedir permisos. Suficiente para
demostrar que funciona ante la academia/dirección.

---

## Opción B — LTI 1.3 (recomendado para producción)

**LTI** (*Learning Tools Interoperability*) es el estándar con el que Moodle se
conecta a herramientas externas. La versión moderna es **1.3**.

### El único trámite (una vez)
El **admin de Moodle** registra nuestra herramienta como *External Tool* a nivel
sitio. Le damos 4 datos (URLs que genera nuestro backend):
- URL de inicio de login
- URL de lanzamiento (launch)
- URL de claves públicas (JWKS)
- (y él nos da a nosotros un `client_id` y el `deployment_id`)

Esto le toma **~10 minutos, una sola vez**, para toda la institución.

### Lo que gana el maestro (por esto "no batallan")
Después del registro, **cualquier maestro**:
1. En su curso de Moodle agrega una actividad **"Herramienta externa"**.
2. Elige "Metaverso TecNM" de una lista. **No teclea claves ni URLs.**
3. (Opcional) elige *qué práctica* enlazar (esto se llama *Deep Linking*).

### Lo que pasa cuando el alumno entra
1. El alumno hace clic en la actividad dentro de Moodle.
2. Moodle manda a nuestro backend un **mensaje firmado (JWT)** con: **quién es el
   alumno**, su curso/grupo, y un **"line item"** (la casilla de calificación).
3. Nuestro backend **valida la firma**, identifica o crea al alumno
   automáticamente (sin que nadie capture nada) y lo lanza al juego de Unreal.
4. Al terminar la práctica, el backend **devuelve la calificación a Moodle**
   automáticamente usando **AGS** (*Assignment and Grade Services*). El maestro la
   ve en su libro de calificaciones de Moodle, **sin copiar nada**.

- ✅ Identidad del alumno **automática** (no más generar 30 links).
- ✅ Calificación **de regreso a Moodle, sola**.
- ✅ Para el maestro es **plug-and-play**.
- ⚠️ Cuesta **un** trámite con el admin (una vez) + desarrollo en nuestro lado.

### Truco para reducir aún más la burocracia
Si pedir al admin del sitio es complicado, en muchas instalaciones de Moodle se
puede dar la capacidad **"Administrar herramientas"** a un **rol de Coordinador**
(Manager), y que *esa persona* haga el registro sin ser admin global. Vale la
pena preguntarlo al área de sistemas.

---

## Opción C — LTI 1.1 (legacy, NO recomendado)

La versión vieja usa solo una **clave + secreto** compartidos. Técnicamente un
maestro podría configurarla a nivel actividad **sin admin** (si el sitio lo
permite), pegando URL + clave + secreto.

- ⚠️ Está **deprecada**; Moodle moderno empuja a 1.3.
- ⚠️ Menos segura (secreto compartido).
- ⚠️ El maestro **sí** tiene que pegar claves (más fricción que en 1.3).

**Veredicto:** evitarla salvo que el Moodle de la escuela sea muy viejo y no
soporte 1.3.

---

## Qué implica en NUESTRO backend (Fase 2)

Para LTI 1.3 habría que agregar (no existe aún):
- Tablas para guardar la(s) plataforma(s) Moodle registradas: `lti_platforms`
  (issuer, client_id, JWKS URL, auth URL) y `lti_deployments`.
- Almacén de *nonces* para evitar repetición de mensajes.
- Endpoints: `/lti/login` (OIDC login), `/lti/launch` (recibe y valida el JWT),
  `/lti/jwks` (publica nuestras llaves públicas).
- Validación de JWT (librería estándar; p. ej. `firebase/php-jwt` o un paquete
  LTI de Laravel).
- **Mapeo de calificación**: cuando una `sesiones_practica` se completa, mandar la
  nota al *line item* de Moodle vía AGS.

> El diagrama actual ya contempla `TOKEN_JUEGO.plataforma` y el modelo de
> `SESION_PRACTICA.calificacion`, así que LTI **encaja** sobre lo que ya hay: el
> launch de Moodle reemplaza/alimenta el magic link, y la calificación que ya
> guardamos es la que se devuelve.

---

## Plan recomendado (camino de menor fricción)

1. **Ahora:** piloto con **magic links** pegados en Moodle. Cero permisos.
   Demostrar que funciona con un grupo real.
2. **Si el piloto convence:** pedir al área de sistemas **un** registro LTI 1.3
   (una vez). Implementar el lado LTI en el backend.
3. **Resultado:** los maestros agregan la actividad y las calificaciones llegan
   solas a Moodle. Sin papeleo recurrente.

> Este enfoque por etapas es a propósito: arrancas **sin trámites** para probar la
> idea, y solo inviertes en el (único) trámite de LTI cuando ya hay respaldo para
> hacerlo.
