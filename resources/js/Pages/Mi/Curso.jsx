import { Head, Link } from '@inertiajs/react';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';
import { diaLargo, plural } from '../../fechas';

/*
 * CONTRATO DE DIRECCIÓN — Un curso · Metaverso
 *
 * THESIS: la secuencia de prácticas de un curso, numerada, con el estado del
 * alumno en cada una. Es la única pantalla del producto que contesta "¿en qué
 * voy?" de arriba abajo.
 * OWN-WORLD: el mundo del Instrumento. Estado por punto de color y rótulo mono;
 * la calificación en mono tabular a la derecha, como toda medición.
 * STORY: el alumno recorre las prácticas en orden y ve dónde se quedó.
 * FIRST VIEWPORT: vuelta al índice, nombre del curso con su clave y maestro, y
 * la lista completa de prácticas.
 * FORM: solo lectura. Reservar y jugar viven en /mi/eventos/{id}.
 */

/**
 * Los estados de una práctica. El punto es la señal; el rótulo la nombra.
 * Ninguno lleva pastilla de fondo: el estado se lee por el punto.
 */
const ESTADOS = {
    completada: { punto: 'bg-telemetria', texto: 'text-telemetria' },
    intentada: { punto: 'bg-senal', texto: 'text-senal' },
    reservada: { punto: 'bg-live', texto: 'text-tinta-2' },
    'por reservar': { punto: 'bg-senal', texto: 'text-senal' },
    perdida: { punto: 'bg-alerta', texto: 'text-alerta' },
    'sin agendar': { punto: 'bg-borde-fuerte', texto: 'text-tinta-3' },
};

/** Qué dice la fecha según el estado: no es lo mismo "te toca" que "fue". */
function pieDeFecha(p) {
    if (!p.fecha_local) {
        return 'Tu maestro aún no la agenda';
    }
    const cuando = `${diaLargo(p.fecha_local)} · ${p.fecha_local.slice(11, 16)}`;

    if (p.estado === 'reservada') {
        return `Tu horario: ${cuando}`;
    }
    if (p.estado === 'por reservar') {
        return `Próxima fecha: ${cuando}`;
    }

    return cuando;
}

function FilaPractica({ p }) {
    const estado = ESTADOS[p.estado] ?? ESTADOS['sin agendar'];
    const calificada = p.calificacion !== null && p.calificacion !== undefined;

    const contenido = (
        <>
            <span className="dato mt-0.5 w-6 shrink-0 text-[13px] text-tinta-3">{p.orden}</span>

            <span className="min-w-0">
                <span className="block text-[15px] font-semibold text-tinta">{p.titulo}</span>
                <span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="inline-flex items-center gap-2">
                        <span className={`size-1.5 shrink-0 rounded-full ${estado.punto}`} aria-hidden="true" />
                        <span className={`rotulo ${estado.texto}`}>{p.estado}</span>
                    </span>
                    <span className="text-[13px] text-tinta-3">{pieDeFecha(p)}</span>
                </span>
            </span>

            <span className="shrink-0 text-right">
                {calificada ? (
                    <>
                        <span className="dato block text-[15px] font-semibold text-tinta">{p.calificacion}</span>
                        <span className="rotulo text-tinta-3">calif.</span>
                    </>
                ) : (
                    <span className="dato text-[15px] text-borde-fuerte" aria-hidden="true">
                        —
                    </span>
                )}
            </span>
        </>
    );

    const clases =
        'grid grid-cols-[auto_minmax(0,1fr)_auto] items-baseline gap-x-4 border-t border-regla-suave px-5 py-3.5 first:border-t-0';

    // Sin evento al que llevar, la fila es texto: un enlace muerto miente.
    return (
        <li>
            {p.id_evento ? (
                <Link
                    href={`/mi/eventos/${p.id_evento}`}
                    className={`${clases} outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal`}
                >
                    {contenido}
                </Link>
            ) : (
                <div className={clases}>{contenido}</div>
            )}
        </li>
    );
}

export default function Curso({ curso, practicas }) {
    const completadas = practicas.filter((p) => p.estado === 'completada').length;

    return (
        <AppLayout>
            <Head title={curso.materia} />

            <Encabezado
                volver={{ href: '/mi/cursos', label: 'Mis cursos' }}
                titulo={curso.materia}
                meta={
                    <span className="flex flex-wrap items-baseline gap-x-2">
                        <span className="dato">{curso.clave}</span>
                        {curso.ciclo && <span className="dato text-tinta-3">· {curso.ciclo}</span>}
                        {curso.maestro && <span className="text-tinta-3">· {curso.maestro}</span>}
                    </span>
                }
            />

            <Lamina retardo={120} className="max-w-[56rem]">
                <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                    <p className="rotulo text-tinta-2">Prácticas del curso</p>
                    <p className="rotulo text-tinta-3">
                        <span className="dato">{completadas}</span> de{' '}
                        <span className="dato">{practicas.length}</span>{' '}
                        {plural(practicas.length, 'completada')}
                    </p>
                </header>

                {practicas.length === 0 ? (
                    <p className="px-5 py-8 text-center text-[13px] text-tinta-3">
                        Este curso todavía no tiene prácticas registradas.
                    </p>
                ) : (
                    <ul>
                        {practicas.map((p) => (
                            <FilaPractica key={p.id_practica} p={p} />
                        ))}
                    </ul>
                )}
            </Lamina>
        </AppLayout>
    );
}
