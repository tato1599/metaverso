/**
 * La lámina de vidrio: el contenedor del sistema (ver DESIGN.md → "El Instrumento").
 * Sustituye a la tarjeta blanca con sombra suave.
 *
 * Son tres piezas apiladas, no una caja con borde:
 *   derrame (z0) — la luz que la lámina deja caer sobre la placa
 *   canto   (z1) — el grosor real del vidrio, asomando bajo la cara
 *   cara    (z2) — el vidrio con su contenido
 *
 * Van en dos envoltorios a propósito: el de fuera lleva la entrada escalonada
 * (que termina en `transform: none`) y el de dentro el paralaje de la lámpara
 * (que vive en `transform`). Compartir elemento haría que el relleno de la
 * animación pisara el paralaje para siempre.
 *
 * `data-pane` la registra ante el observador de la lámpara (GuestLayout), que le
 * escribe --ig, --sx y --py. `depth` gradúa el paralaje: dos láminas a distinta
 * profundidad no se mueven igual, y eso es lo que las separa.
 */
export default function Lamina({ depth = 0, retardo, className = '', children, ...rest }) {
    return (
        <div
            className={className}
            data-entra
            style={retardo === undefined ? undefined : { '--retardo': `${retardo}ms` }}
        >
            <div className="lamina" data-pane data-depth={depth}>
                <span className="derrame" aria-hidden="true" />
                <section className="lamina-cara" {...rest}>
                    {children}
                </section>
                <span className="canto" aria-hidden="true" />
            </div>
        </div>
    );
}
