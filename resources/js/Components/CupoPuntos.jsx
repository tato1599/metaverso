/**
 * El cupo como lectura de instrumento: puntos llenables.
 *
 * Cada punto es un lugar; los ocupados van en el azul de acción, los libres en
 * la regla. Con más de 12 lugares la fila de puntos deja de ser legible y
 * degrada a fracción en mono tabular, que es la misma medición en otra escala.
 *
 * Lleno se marca en rojo TecNM porque en este sistema el rojo es escasez.
 */
export default function CupoPuntos({ ocupados, cupo }) {
    const lleno = ocupados >= cupo;
    const etiqueta = `${ocupados} de ${cupo} lugares ocupados`;

    if (cupo > 12) {
        return (
            <span
                className={`dato text-[13px] font-medium ${lleno ? 'text-alerta' : 'text-tinta-2'}`}
                aria-label={etiqueta}
            >
                {ocupados}/{cupo}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1" role="img" aria-label={etiqueta} title={etiqueta}>
            {Array.from({ length: cupo }, (_, i) => (
                <span
                    key={i}
                    className={`size-1.5 rounded-full ${
                        i < ocupados ? (lleno ? 'bg-alerta' : 'bg-portal') : 'bg-borde-fuerte'
                    }`}
                />
            ))}
        </span>
    );
}
