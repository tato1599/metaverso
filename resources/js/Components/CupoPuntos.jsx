/**
 * Signature del design system: el cupo como puntos llenables.
 * Con más de 12 lugares degrada a fracción en mono.
 */
export default function CupoPuntos({ ocupados, cupo }) {
    const etiqueta = `${ocupados} de ${cupo} lugares ocupados`;

    if (cupo > 12) {
        return (
            <span className="font-mono text-xs tabular-nums text-tinta-2" aria-label={etiqueta}>
                {ocupados}/{cupo}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1" role="img" aria-label={etiqueta} title={etiqueta}>
            {Array.from({ length: cupo }, (_, i) => (
                <span
                    key={i}
                    className={`size-1.5 rounded-full ${i < ocupados ? 'bg-portal' : 'bg-borde-fuerte'}`}
                />
            ))}
        </span>
    );
}
