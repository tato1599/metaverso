const tonos = {
    ok: 'bg-telemetria-tinte text-telemetria',
    warn: 'bg-senal-tinte text-senal',
    danger: 'bg-alerta-tinte text-alerta',
    muted: 'bg-hueco text-tinta-2',
};

export default function Badge({ tone = 'muted', className = '', children }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${tonos[tone]} ${className}`}
        >
            {children}
        </span>
    );
}
