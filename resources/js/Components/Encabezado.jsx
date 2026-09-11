import { Link } from '@inertiajs/react';

/**
 * La cabecera de una pantalla del panel: enlace de vuelta, rótulo mono, título
 * grabado y sus metadatos, con las acciones a la derecha. Estaba copiada en
 * siete páginas con siete variaciones accidentales de tamaño y espaciado.
 */
export default function Encabezado({ volver, rotulo, titulo, meta, acciones }) {
    return (
        <div data-entra className="mb-7 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div className="min-w-0">
                {volver && (
                    <Link
                        href={volver.href}
                        className="rotulo rounded-ctl text-tinta-3 outline-offset-2 transition-colors hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                    >
                        ‹ {volver.label}
                    </Link>
                )}
                {rotulo && !volver && <p className="rotulo grabado text-tinta-3">{rotulo}</p>}

                <h1
                    className={`grabado font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta ${
                        volver || rotulo ? 'mt-2.5' : ''
                    }`}
                >
                    {titulo}
                </h1>

                {meta && <div className="mt-2 text-[13px] text-tinta-2">{meta}</div>}
            </div>

            {acciones && <div className="flex flex-wrap items-center gap-3">{acciones}</div>}
        </div>
    );
}
