import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import Badge from '../../Components/Badge';
import CupoPuntos from '../../Components/CupoPuntos';
import Lamina from '../../Components/Lamina';
import PasoSemana from '../../Components/PasoSemana';
import WeekCalendar from '../../Components/WeekCalendar';
import AppLayout from '../../Layouts/AppLayout';
import { diaLargo, plural, rangoSemana } from '../../fechas';

/**
 * El chip del calendario NO decide nada: enlaza a la vista de la práctica, que
 * es donde vive el estado. Antes traía dentro un modal con el selector de
 * horarios, tres botones y un confirm(), metido en una celda de la rejilla.
 */
function ChipEvento({ e }) {
    const cancelado = e.estatus === 'cancelado';

    return (
        <Link
            href={`/mi/eventos/${e.id_evento}`}
            className={`block rounded-ctl bg-vidrio-2 p-2.5 shadow-[0_0_0_1px_var(--color-regla-suave)_inset] transition-[background-color,box-shadow] outline-offset-2 hover:bg-white/70 hover:shadow-[0_0_0_1px_var(--color-borde-fuerte)_inset] focus-visible:outline-2 focus-visible:outline-portal ${
                cancelado ? 'opacity-55' : ''
            }`}
        >
            <p className="dato text-[13px] font-medium text-tinta">
                {e.inicio_local.slice(11, 16)}
                <span className="text-tinta-3">–{e.fin_local.slice(11, 16)}</span>
            </p>
            <p className="mt-1 text-[13px] leading-snug font-semibold text-tinta">{e.practica}</p>
            <p className="mt-0.5 text-[11px] text-tinta-3">
                {e.grupo} · {e.materia}
            </p>
            <div className="mt-2 flex flex-wrap items-center justify-between gap-x-2 gap-y-1 border-t border-regla-suave pt-2">
                {e.multi_slot ? (
                    <span className="rotulo text-tinta-3">
                        <span className="dato">{e.slots.length}</span> horarios
                    </span>
                ) : (
                    <CupoPuntos ocupados={e.reservas_activas} cupo={e.cupo_maximo} />
                )}
                {cancelado ? (
                    <Badge tone="danger">cancelado</Badge>
                ) : e.puede_jugar ? (
                    <Badge tone="ok">en curso</Badge>
                ) : e.mi_reserva ? (
                    <Badge tone="ok">reservado</Badge>
                ) : e.lleno ? (
                    <Badge tone="danger">sin cupo</Badge>
                ) : e.finalizado ? (
                    <Badge tone="muted">finalizado</Badge>
                ) : e.puede_reservar ? (
                    <Badge tone="warn">por reservar</Badge>
                ) : null}
            </div>
        </Link>
    );
}

/** Una lectura del resumen: rótulo, cifra y su lista — o el hueco dicho en claro. */
function Resumen({ rotulo, contador, destacado = false, vacio, depth, children }) {
    return (
        <Lamina depth={depth}>
            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                <p className="rotulo text-tinta-2">{rotulo}</p>
                {/* Clase literal, no construida: Tailwind solo genera las que ve escritas. */}
                <p className={`dato text-[15px] font-semibold ${destacado ? 'text-senal' : 'text-tinta'}`}>
                    {contador}
                </p>
            </header>
            {contador === 0 ? (
                <p className="px-5 py-6 text-center text-[13px] text-tinta-3">{vacio}</p>
            ) : (
                <ul>{children}</ul>
            )}
        </Lamina>
    );
}

export default function Calendario({ semana, limites, eventos, proximas, pendientes, grupos }) {
    const { errors } = usePage().props;
    /*
     * La dirección vive en un ref, no en estado: si fuera estado, el clic
     * provocaría un render inmediato, `data-semana` cambiaría y la animación
     * arrancaría CON EL CONTENIDO VIEJO todavía puesto — la rejilla se quedaba en
     * blanco ~85ms esperando la respuesta. Así solo se anima al montar la rejilla
     * nueva, que es cuando el dato ya llegó.
     */
    const direccion = useRef(0);
    const errorNegocio = errors?.evento || errors?.reserva;
    const porReservar = eventos.filter((e) => e.puede_reservar).length;

    /*
     * Recarga PARCIAL: solo `semana` y `eventos`. El componente no se remonta, así
     * que la entrada escalonada de la página no se vuelve a disparar — lo único que
     * se mueve es la rejilla, y `direccion` decide hacia dónde.
     */
    function irASemana(fecha, haciaDonde) {
        direccion.current = haciaDonde;
        router.get('/mi/calendario', fecha ? { semana: fecha } : {}, {
            only: ['semana', 'eventos'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <AppLayout>
            <Head title="Mi calendario" />

            <div data-entra className="mb-7 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div>
                    <p className="rotulo grabado text-tinta-3">Mis prácticas</p>
                    <h1 className="grabado mt-2 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                        {rangoSemana(semana)}
                    </h1>
                </div>
                <PasoSemana semana={semana} limites={limites} irASemana={irASemana} />
            </div>

            <div aria-live="polite">
                {errorNegocio && (
                    <p className="mb-5 flex items-start gap-2.5 rounded-ctl bg-alerta-tinte px-4 py-3 text-[13px] font-medium text-alerta">
                        <span className="mt-[6px] size-1.5 shrink-0 rounded-full bg-alerta" aria-hidden="true" />
                        {errorNegocio}
                    </p>
                )}
            </div>

            <Lamina depth={0.25} retardo={120}>
                <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                    <p className="rotulo text-tinta-2">Semana</p>
                    <p className={`rotulo ${porReservar > 0 ? 'text-senal' : 'text-tinta-3'}`}>
                        {eventos.length === 0 ? (
                            'sin prácticas'
                        ) : porReservar > 0 ? (
                            <>
                                <span className="dato">{porReservar}</span> sin reservar
                            </>
                        ) : (
                            'todo reservado'
                        )}
                    </p>
                </header>

                {/* key por semana: la rejilla se renueva y se anima; el resto no. */}
                <div key={semana} data-semana={direccion.current}>
                    <WeekCalendar
                        semana={semana}
                        eventos={eventos}
                        renderEvento={(e) => <ChipEvento key={e.id_evento} e={e} />}
                    />

                    {eventos.length === 0 && (
                        <p className="border-t border-regla-suave px-5 py-4 text-center text-[13px] text-tinta-3">
                            No tienes prácticas esta semana. Prueba otra con las flechas.
                        </p>
                    )}
                </div>
            </Lamina>

            {/* ── Resumen de estado: qué tengo, qué me falta, dónde estoy ── */}
            <div
                data-entra
                style={{ '--retardo': '280ms' }}
                className="mt-10 grid items-start gap-6 lg:grid-cols-3"
            >
                <Resumen
                    rotulo="Tus próximas prácticas"
                    contador={proximas.length}
                    vacio="No tienes ninguna práctica apartada."
                    depth={0}
                >
                    {proximas.map((r) => (
                        <li key={r.id_reserva} className="border-t border-regla-suave first:border-t-0">
                            <Link
                                href={`/mi/eventos/${r.id_evento}`}
                                className="block px-5 py-3.5 outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal"
                            >
                                <p className="text-[15px] font-semibold text-tinta">{r.practica}</p>
                                <p className="dato mt-1 text-[13px] text-tinta-2">
                                    {diaLargo(r.inicio_slot_local)} · {r.inicio_slot_local.slice(11, 16)}
                                </p>
                                <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span className="rotulo text-tinta-3">
                                        {r.grupo} · {r.materia}
                                    </span>
                                    {r.cancelado && <Badge tone="danger">cancelada</Badge>}
                                </p>
                            </Link>
                        </li>
                    ))}
                </Resumen>

                {/* Lo accionable va en ámbar: es lo único que le pide algo al alumno. */}
                <Resumen
                    rotulo="Te falta reservar"
                    contador={pendientes.length}
                    destacado={pendientes.length > 0}
                    vacio="Nada pendiente: ya apartaste todas tus prácticas."
                    depth={0}
                >
                    {pendientes.map((p) => (
                        <li key={p.id_practica} className="border-t border-regla-suave first:border-t-0">
                            <Link
                                href={`/mi/eventos/${p.id_evento}`}
                                className="block px-5 py-3.5 outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal"
                            >
                                <p className="text-[15px] font-semibold text-tinta">{p.practica}</p>
                                <p className="dato mt-1 text-[13px] text-tinta-2">
                                    próxima: {diaLargo(p.proxima_local)} · {p.proxima_local.slice(11, 16)}
                                </p>
                                <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span className="rotulo text-tinta-3">
                                        {p.grupo} · {p.materia}
                                    </span>
                                    <span className="rotulo text-senal">
                                        <span className="dato">{p.fechas}</span>{' '}
                                        {plural(p.fechas, 'fecha')} disponible{p.fechas === 1 ? '' : 's'}
                                    </span>
                                </p>
                            </Link>
                        </li>
                    ))}
                </Resumen>

                <Resumen
                    rotulo="Tus grupos"
                    contador={grupos.length}
                    vacio="No estás inscrito en ningún grupo."
                    depth={0}
                >
                    {grupos.map((g) => (
                        <li
                            key={g.id_grupo}
                            className="border-t border-regla-suave px-5 py-3.5 first:border-t-0"
                        >
                            <p className="text-[15px] font-semibold text-tinta">{g.materia}</p>
                            <p className="mt-1 flex flex-wrap items-baseline gap-x-2 text-[13px] text-tinta-2">
                                <span className="dato">{g.clave}</span>
                                {g.ciclo && <span className="dato text-tinta-3">· {g.ciclo}</span>}
                            </p>
                            {g.maestro && <p className="mt-1 text-[13px] text-tinta-3">{g.maestro}</p>}
                        </li>
                    ))}
                </Resumen>
            </div>

        </AppLayout>
    );
}
