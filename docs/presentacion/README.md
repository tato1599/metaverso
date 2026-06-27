# Presentación — Metaverso Escolar (TecNM × ITCJ)

Presentación para la junta de demostración. Estilo propio (azul TecNM + guinda/rojo
ITCJ + acento ámbar), tipografía Avenir Next.

## Archivos

| Archivo | Para qué |
|---|---|
| `presentacion.pdf` | **Diapositivas limpias** — esto es lo que se proyecta. |
| `presentacion-notas.pdf` | **Diapositiva + guion** al lado (qué decir en cada una). Para quien presente. |

Copias listas también en `~/Documents/`:
- `Presentacion - Metaverso Escolar TecNM.pdf`
- `Presentacion CON GUION - Metaverso Escolar TecNM.pdf`

## Cómo presentar (sin el autor)

1. Quien exponga **abre `presentacion-notas.pdf`** para leer el guion (1 página = 1 slide + sus notas).
2. En la sala **se proyecta `presentacion.pdf`** (las diapositivas limpias).
3. La diapositiva **17 ("La demo de hoy")** trae los 4 pasos exactos de la demo en su guion.
   Si la demo en vivo falla, usar capturas de respaldo y dejar las dudas técnicas para correo.

## Recompilar

Requiere [tectonic](https://tectonic-typesetting.github.io/) y las tipografías Avenir Next (vienen en macOS).

```bash
tectonic presentacion.tex          # diapositivas limpias
tectonic presentacion-notas.tex    # con guion del presentador
```

## Estructura

- `preambulo.tex` — tema, colores y tipografías (compartido).
- `cuerpo.tex` — todas las diapositivas y el guion (`\note`) de cada una.
- `presentacion.tex` / `presentacion-notas.tex` — los dos puntos de entrada.

Para editar texto o guion, toca **`cuerpo.tex`** y recompila.
