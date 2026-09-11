import { sumarDias } from '../fechas';

/**
 * El paso de semana: un control segmentado de instrumento, no tres botones
 * sueltos. Lo comparten la agenda del maestro y el calendario del alumno.
 *
 * `limites` acota el recorrido a las semanas que de verdad tienen algo que
 * mostrar (más la actual). Un calendario que avanza al infinito por semanas
 * vacías no informa, solo cansa.
 */
export default function PasoSemana({ semana, limites, irASemana }) {
    const anterior = sumarDias(semana, -7);
    const siguiente = sumarDias(semana, 7);
    const hayAnterior = !limites || anterior >= limites.min;
    const haySiguiente = !limites || siguiente <= limites.max;

    const flecha =
        'flex h-9 w-9 items-center justify-center text-tinta-2 transition-colors outline-offset-[-2px] hover:bg-vidrio-2 hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal disabled:pointer-events-none disabled:text-borde-fuerte';

    return (
        <div className="flex items-stretch rounded-ctl shadow-[0_0_0_1px_var(--color-borde)_inset]">
            <button
                type="button"
                aria-label="Semana anterior"
                disabled={!hayAnterior}
                className={flecha}
                onClick={() => irASemana(anterior, -1)}
            >
                ‹
            </button>
            <button
                type="button"
                className="rotulo border-x border-borde px-3 text-tinta-2 transition-colors outline-offset-[-2px] hover:bg-vidrio-2 hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                onClick={() => irASemana(null, 0)}
            >
                Hoy
            </button>
            <button
                type="button"
                aria-label="Semana siguiente"
                disabled={!haySiguiente}
                className={flecha}
                onClick={() => irASemana(siguiente, 1)}
            >
                ›
            </button>
        </div>
    );
}
