/**
 * El hueco honesto: una placa de captura vacía con sus marcas de registro en las
 * esquinas. No es una tarjeta con un icono — es un espacio del aparato que
 * todavía no tiene lectura.
 */
export default function EmptyState({ title, hint, action }) {
    return (
        <div className="relative flex flex-col items-center gap-2 rounded-carta border border-dashed border-borde-fuerte px-6 py-12 text-center">
            {[
                'top-2.5 left-2.5 border-r-0 border-b-0',
                'top-2.5 right-2.5 border-l-0 border-b-0',
                'bottom-2.5 left-2.5 border-r-0 border-t-0',
                'bottom-2.5 right-2.5 border-l-0 border-t-0',
            ].map((pos) => (
                <span key={pos} aria-hidden="true" className={`absolute size-3 border border-borde-fuerte ${pos}`} />
            ))}

            <p className="text-[15px] font-semibold text-tinta">{title}</p>
            {hint && <p className="max-w-[46ch] text-[13px] text-tinta-3">{hint}</p>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
