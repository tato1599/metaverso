import { Head, Link, router, usePage } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';
import { diaLargo, plural } from '../../fechas';

/*
 * CONTRATO DE DIRECCIÓN — Mi práctica · Metaverso
 *
 * THESIS: una sola pantalla que dice en qué punto estás con esta práctica.
 * No un modal dentro de un chip dentro de una celda del calendario, ni cuatro
 * bloques compitiendo: el estado manda y solo se ve el que aplica.
 * OWN-WORLD: el mundo del Instrumento — láminas de vidrio sobre la placa, tinta
 * navy TecNM, azul institucional para la acción, verde para lo confirmado, rojo
 * solo para escasez. Horas y cupos en mono tabular.
 * STORY: llegue desde el calendario o desde Moodle, el alumno ve la misma
 * lectura y una sola acción disponible: reservar, o entrar a jugar.
 * FIRST VIEWPORT: cabecera con la práctica y su ventana; debajo, LA lámina de
 * estado; al pie, otras fechas de la misma práctica si el maestro agendó varias.
 * FORM: mundo fijado por el usuario (04-instrumento, registro claro).
 */

function agruparPorDia(slots) {
    const grupos = new Map();
    for (const s of slots) {
        const dia = s.inicio_local.slice(0, 10);
        if (!grupos.has(dia)) {
            grupos.set(dia, []);
        }
        grupos.get(dia).push(s);
    }

    return [...grupos.entries()];
}

/** Un horario reservable: hora, ocupación medida y su acción. */
function Horario({ slot, cupo, onReservar, etiquetaAccion = 'Reservar' }) {
    const restantes = cupo - slot.ocupados;

    return (
        <li className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-regla-suave px-5 py-3 first:border-t-0">
            <span className="dato text-[13px] font-medium text-tinta">
                {slot.inicio_local.slice(11, 16)}
                <span className="text-tinta-3">–{slot.fin_local.slice(11, 16)}</span>
            </span>

            <span className="flex items-center gap-4">
                {slot.es_mio ? (
                    <Badge tone="ok">tu horario</Badge>
                ) : slot.lleno ? (
                    <Badge tone="danger">sin cupo</Badge>
                ) : (
                    <span className="rotulo text-tinta-3">
                        <span className="dato">{restantes}</span> {plural(restantes, 'lugar', 'es')}
                    </span>
                )}

                {slot.puede_reservar && (
                    <Button
                        variant="secondary"
                        className="h-8 px-3"
                        /* Sin esto, un lector de pantalla oye seis botones "Reservar" iguales. */
                        aria-label={`${etiquetaAccion} de ${slot.inicio_local.slice(11, 16)} a ${slot.fin_local.slice(11, 16)}`}
                        onClick={onReservar}
                    >
                        {etiquetaAccion}
                    </Button>
                )}
            </span>
        </li>
    );
}

/**
 * EL bloque de estado. Es lo único que cambia entre un alumno y otro, y por eso
 * es lo único que ocupa el centro de la pantalla.
 */
function BloqueEstado({ e, otraFecha, reservar, cambiarA, cancelar }) {
    if (e.estatus === 'cancelado') {
        return (
            <Aviso tono="danger" titulo="Esta práctica fue cancelada">
                Tu maestro canceló esta fecha. Si agendó otra, la verás abajo.
            </Aviso>
        );
    }

    if (e.puede_jugar) {
        return (
            <div className="p-6">
                <p className="rotulo text-telemetria">Tu horario está en curso</p>
                <p className="mt-2 text-[15px] text-tinta-2">
                    Reservaste{' '}
                    <span className="dato font-medium text-tinta">
                        {e.mi_reserva.inicio_slot_local.slice(11, 16)}–
                        {e.mi_reserva.fin_slot_local.slice(11, 16)}
                    </span>
                    . Entra ahora: al terminar, tu calificación se registra sola.
                </p>
                <Button
                    className="mt-5 h-11 w-full bg-telemetria text-[15px] hover:opacity-90 focus-visible:outline-telemetria"
                    onClick={() => router.post(`/mi/eventos/${e.id_evento}/jugar`)}
                >
                    Entrar a la práctica
                </Button>
            </div>
        );
    }

    // El orden importa: una fecha que ya pasó no "te espera", aunque hayas
    // reservado. finalizado tiene que ganarle a mi_reserva.
    if (e.finalizado) {
        return e.mi_reserva ? (
            <Aviso tono="muted" titulo="Tu horario ya pasó">
                Reservaste el {diaLargo(e.mi_reserva.inicio_slot_local)} a las{' '}
                <span className="dato">{e.mi_reserva.inicio_slot_local.slice(11, 16)}</span>. Esta fecha ya
                terminó; si tu maestro agendó otra, la verás abajo.
            </Aviso>
        ) : (
            <Aviso tono="muted" titulo="Esta práctica ya terminó">
                No alcanzaste a reservar esta fecha. Si tu maestro agendó otra, la verás abajo.
            </Aviso>
        );
    }

    if (e.mi_reserva) {
        return (
            <div className="p-6">
                <p className="rotulo text-telemetria">Tu lugar está reservado</p>
                <p className="mt-2 text-[15px] text-tinta-2">
                    Te esperamos el{' '}
                    <span className="font-medium text-tinta">{diaLargo(e.mi_reserva.inicio_slot_local)}</span> a las{' '}
                    <span className="dato font-medium text-tinta">
                        {e.mi_reserva.inicio_slot_local.slice(11, 16)}
                    </span>
                    . El botón para entrar aparece aquí cuando empiece tu horario.
                </p>
                {e.puede_cancelar ? (
                    <Button
                        variant="ghost"
                        className="mt-4 h-9 px-0"
                        onClick={() => {
                            if (confirm('¿Cancelar tu reserva en este horario?')) {
                                cancelar(e.mi_reserva.id_reserva);
                            }
                        }}
                    >
                        Cancelar mi reserva
                    </Button>
                ) : (
                    <p className="mt-4 text-[13px] text-tinta-3">
                        Ya no se puede cancelar: tu horario está por empezar o ya empezó.
                    </p>
                )}
            </div>
        );
    }

    /*
     * Ya reservó esta práctica en otra fecha. Una práctica se cursa una vez, así
     * que aquí no se ofrece reservar: se ofrece MOVER la reserva, que el backend
     * resuelve en una sola transacción.
     */
    if (otraFecha) {
        return (
            <div className="p-6">
                <p className="rotulo text-telemetria">Ya reservaste esta práctica</p>
                <p className="mt-2 text-[15px] text-tinta-2">
                    Tu lugar está el{' '}
                    <span className="font-medium text-tinta">{diaLargo(otraFecha.inicio_slot_local)}</span> a las{' '}
                    <span className="dato font-medium text-tinta">
                        {otraFecha.inicio_slot_local.slice(11, 16)}
                    </span>
                    . Solo puedes tener una fecha reservada de esta práctica.
                </p>

                {e.puede_reservar ? (
                    <>
                        <p className="mt-4 text-[13px] text-tinta-3">
                            Si prefieres esta fecha, tu reserva se mueve aquí: la otra se libera en la
                            misma operación.
                        </p>
                        {e.multi_slot ? (
                            <div className="mt-4">
                                <p className="rotulo text-tinta-2">Cambiarme a un horario de esta fecha</p>
                                <ul className="mt-2 -mx-6 border-y border-regla-suave">
                                    {e.slots
                                        .filter((s) => s.puede_reservar)
                                        .map((s) => (
                                            <Horario
                                                key={s.inicio_local}
                                                slot={s}
                                                cupo={e.cupo_maximo}
                                                etiquetaAccion="Cambiarme"
                                                onReservar={() => cambiarA(s.inicio_local)}
                                            />
                                        ))}
                                </ul>
                            </div>
                        ) : (
                            <Button className="mt-4 h-11 w-full text-[15px]" onClick={() => cambiarA()}>
                                Cambiar mi reserva a esta fecha
                            </Button>
                        )}
                    </>
                ) : (
                    <p className="mt-4 text-[13px] text-tinta-3">
                        Esta fecha ya no acepta reservas, así que no puedes cambiarte a ella.
                    </p>
                )}

                <Link
                    href={`/mi/eventos/${otraFecha.id_evento}`}
                    className="rotulo mt-5 inline-block rounded-ctl text-portal outline-offset-2 transition-colors hover:text-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                >
                    Ver mi fecha reservada →
                </Link>
            </div>
        );
    }

    if (e.lleno) {
        return (
            <Aviso tono="danger" titulo="Sin cupo en esta fecha">
                Todos los horarios de esta fecha están llenos. Revisa abajo si hay otra fecha con
                lugares.
            </Aviso>
        );
    }

    if (!e.puede_reservar) {
        return (
            <Aviso tono="muted" titulo="No hay horarios disponibles">
                Esta fecha ya no acepta reservas.
            </Aviso>
        );
    }

    // Caso principal: hay que elegir. Un solo horario reserva directo.
    if (!e.multi_slot) {
        return (
            <div className="p-6">
                <p className="rotulo text-tinta-2">Aparta tu lugar</p>
                <p className="mt-2 text-[15px] text-tinta-2">
                    Un solo horario: <span className="font-medium text-tinta">{diaLargo(e.inicio_local)}</span>{' '}
                    de{' '}
                    <span className="dato font-medium text-tinta">
                        {e.inicio_local.slice(11, 16)}–{e.fin_local.slice(11, 16)}
                    </span>
                    .
                </p>
                <Button className="mt-5 h-11 w-full text-[15px]" onClick={() => reservar()}>
                    Reservar mi lugar
                </Button>
            </div>
        );
    }

    return (
        <>
            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3.5">
                <p className="rotulo text-tinta-2">Elige tu horario</p>
                <p className="rotulo text-tinta-3">
                    <span className="dato">{e.cupo_maximo}</span> {plural(e.cupo_maximo, 'lugar', 'es')} por horario
                </p>
            </header>

            {agruparPorDia(e.slots).map(([dia, slots]) => (
                <section key={dia} className="border-t border-regla-suave">
                    <p className="rotulo bg-hueco/45 px-5 py-2 text-tinta-2">{diaLargo(`${dia}T00:00`)}</p>
                    <ul>
                        {slots.map((s) => (
                            <Horario
                                key={s.inicio_local}
                                slot={s}
                                cupo={e.cupo_maximo}
                                onReservar={() => reservar(s.inicio_local)}
                            />
                        ))}
                    </ul>
                </section>
            ))}
        </>
    );
}

function Aviso({ tono, titulo, children }) {
    const color = { danger: 'text-alerta', muted: 'text-tinta-2', ok: 'text-telemetria' }[tono];

    return (
        <div className="p-6">
            <p className={`rotulo ${color}`}>{titulo}</p>
            <p className="mt-2 text-[15px] text-tinta-2">{children}</p>
        </div>
    );
}

/**
 * Una fecha hermana. `yaReservada` dice si el alumno ya tiene ESTA PRÁCTICA
 * apartada en algún lado: si es así, las demás fechas no ofrecen lugares —
 * ofrecerlos era lo que dejaba reservar dos veces la misma práctica.
 */
function OtraFecha({ e, yaReservada }) {
    return (
        <li className="border-t border-regla-suave first:border-t-0">
            <Link
                href={`/mi/eventos/${e.id_evento}`}
                className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3.5 outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal"
            >
                <span>
                    <span className="block text-[15px] font-semibold text-tinta">{diaLargo(e.inicio_local)}</span>
                    <span className="dato mt-0.5 block text-[13px] text-tinta-3">
                        {e.inicio_local.slice(11, 16)}–{e.fin_local.slice(11, 16)}
                    </span>
                </span>
                <span className="flex items-center gap-3">
                    {e.mi_reserva ? (
                        <Badge tone="ok">tu fecha</Badge>
                    ) : e.lleno ? (
                        <Badge tone="danger">sin cupo</Badge>
                    ) : !e.puede_reservar ? (
                        <Badge tone="muted">cerrada</Badge>
                    ) : yaReservada ? (
                        <Badge tone="muted">cambiarse aquí</Badge>
                    ) : (
                        <Badge tone="muted">con lugares</Badge>
                    )}
                </span>
            </Link>
        </li>
    );
}

export default function Evento({ evento: e, otros, reservaEnOtraFecha }) {
    // El flash de éxito lo pinta AppLayout; aquí solo los errores de validación.
    const { errors } = usePage().props;
    // Esta práctica ya está apartada, aquí o en otra fecha.
    const yaReservada = Boolean(e.mi_reserva || reservaEnOtraFecha);
    const errorNegocio = errors?.evento || errors?.reserva || errors?.inicio_slot;

    function reservar(inicioSlot) {
        router.post('/mi/reservas', {
            id_evento: e.id_evento,
            ...(inicioSlot ? { inicio_slot: inicioSlot } : {}),
        });
    }

    function cambiarA(inicioSlot) {
        router.post('/mi/reservas', {
            id_evento: e.id_evento,
            cambiar_de: reservaEnOtraFecha.id_reserva,
            ...(inicioSlot ? { inicio_slot: inicioSlot } : {}),
        });
    }

    function cancelar(idReserva) {
        router.delete(`/mi/reservas/${idReserva}`);
    }

    return (
        <AppLayout>
            <Head title={e.practica} />

            <div className="mx-auto w-full max-w-[44rem]">
                <div data-entra className="mb-6">
                    <Link
                        href="/mi/calendario"
                        className="rotulo rounded-ctl text-tinta-3 outline-offset-2 transition-colors hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                    >
                        ‹ Mi calendario
                    </Link>
                    <h1 className="grabado mt-3 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                        {e.practica}
                    </h1>
                    <p className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-tinta-2">
                        <span>
                            {e.grupo} · {e.materia}
                        </span>
                        <span className="text-tinta-3">·</span>
                        <span>{diaLargo(e.inicio_local)}</span>
                        {e.espacio && (
                            <>
                                <span className="text-tinta-3">·</span>
                                <span>{e.espacio}</span>
                            </>
                        )}
                    </p>
                </div>

                <div aria-live="polite">
                    {errorNegocio && (
                        <p
                            data-entra
                            className="mb-5 flex items-start gap-2.5 rounded-ctl bg-alerta-tinte px-4 py-3 text-[13px] font-medium text-alerta"
                        >
                            <span className="mt-[6px] size-1.5 shrink-0 rounded-full bg-alerta" aria-hidden="true" />
                            {errorNegocio}
                        </p>
                    )}
                </div>

                <Lamina depth={0.35} retardo={120}>
                    <BloqueEstado
                        e={e}
                        otraFecha={reservaEnOtraFecha}
                        reservar={reservar}
                        cambiarA={cambiarA}
                        cancelar={cancelar}
                    />
                </Lamina>

                {otros.length > 0 && (
                    <div data-entra style={{ '--retardo': '260ms' }} className="mt-9">
                        <p className="rotulo grabado mb-3 text-tinta-2">
                            Otras fechas de esta práctica
                            {yaReservada && <span className="ml-2 text-tinta-3">· solo puedes tener una</span>}
                        </p>
                        <Lamina depth={-0.3}>
                            <ul>
                                {otros.map((o) => (
                                    <OtraFecha key={o.id_evento} e={o} yaReservada={yaReservada} />
                                ))}
                            </ul>
                        </Lamina>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
