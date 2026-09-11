/**
 * El chip de estado del instrumento: punto de color + rótulo mono.
 * Sin fondo tintado — el estado se lee por el punto, no por una pastilla.
 * La API (`tone`) se conserva: la usan nueve pantallas.
 */
const tonos = {
    ok: { punto: 'bg-telemetria', texto: 'text-telemetria' },
    warn: { punto: 'bg-senal', texto: 'text-senal' },
    danger: { punto: 'bg-alerta', texto: 'text-alerta' },
    muted: { punto: 'bg-tinta-3', texto: 'text-tinta-2' },
};

export default function Badge({ tone = 'muted', className = '', children }) {
    const t = tonos[tone] ?? tonos.muted;

    return (
        <span className={`rotulo inline-flex items-center gap-2 whitespace-nowrap ${t.texto} ${className}`}>
            <span className={`size-1.5 shrink-0 rounded-full ${t.punto}`} aria-hidden="true" />
            {children}
        </span>
    );
}
