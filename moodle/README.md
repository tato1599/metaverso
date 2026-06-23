# Moodle local (Docker) — para probar la integración LTI

Instancia **desechable** de Moodle para desarrollar y demostrar la integración
LTI con el Metaverso. No es para producción.

## Requisitos
- Docker Desktop corriendo.

## Uso

```bash
cd moodle
docker compose up -d      # arranca (1ª vez ~5-10 min de instalación)
docker compose ps         # estado
docker compose logs -f moodle   # ver el log
docker compose down       # detener (conserva datos en volúmenes)
docker compose down -v    # borrar TODO (instancia limpia)
```

## Acceso
- URL: http://localhost:8080
- Usuario: `admin`
- Contraseña: `Admin12345!`
- Versión: Moodle 5.0.x (imagen `bitnamilegacy/moodle`)

> Nota: se usan las imágenes `bitnamilegacy/*` porque Bitnami archivó sus tags
> gratuitos en Docker Hub. Son imágenes congeladas (sin actualizaciones), lo cual
> es suficiente para una demo local.

## Red (para LTI)
- Moodle corre en el contenedor; nuestro backend Laravel corre en el **host**.
- Desde el contenedor de Moodle, el host se alcanza como `host.docker.internal`.
- Desde el host (y el navegador), Moodle está en `http://localhost:8080`.
- Estos detalles se configuran al implementar LTI (ver
  `../docs/INTEGRACION-MOODLE-LTI.md`).
