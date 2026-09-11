import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import CupoPuntos from '../../Components/CupoPuntos';
import EmptyState from '../../Components/EmptyState';
import EventoFormModal from '../../Components/EventoFormModal';
import Lamina from '../../Components/Lamina';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';
import { diaLargo, plural } from '../../fechas';

const TONO_ESTATUS = { programado: 'muted', en_curso: 'ok', cancelado: 'danger', finalizado: 'muted' };

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

/** Día corto ('sáb') para desambiguar horarios cuando la ventana cruza días. */
function diaCorto(local) {
    const [anio, mes, dia] = local.slice(0, 10).split('-').map(Number);

    return new Date(anio, mes - 1, dia).toLocaleDateString('es-MX', { weekday: 'short' });
}

/** Una lectura del panel: rótulo mono arriba, valor abajo. */
function Lectura({ rotulo, children, className = '' }) {
    return (
        <div className={`px-5 py-3.5 ${className}`}>
            <p className="rotulo text-tinta-3">{rotulo}</p>
            <div className="mt-1.5">{children}</div>
        </div>
    );
}

export default function EventoDetalle({ evento, reservas, espacios }) {
    const [editando, setEditando] = useState(false);
    const cancelado = evento.estatus === 'cancelado';
    // Si la ventana cruza días, la hora sola del slot es ambigua.
    const cruzaDias = evento.inicio_local.slice(0, 10) !== evento.fin_local.slice(0, 10);
    const libres = evento.cupo_maximo - evento.reservas_activas;

    function cancelarEvento() {
        if (confirm('¿Cancelar este evento? Los alumnos verán la práctica como cancelada.')) {
            router.delete(`/panel/eventos/${evento.id_evento}`);
        }
    }

    return (
        <AppLayout>
            <Head title={evento.practica} />

            <div data-entra className="mb-7 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div>
                    <Link
                        href="/panel/agenda"
                        className="rotulo rounded-ctl text-tinta-3 outline-offset-2 transition-colors hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                    >
                        ‹ Agenda
                    </Link>
                    <h1 className="grabado mt-3 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                        {evento.practica}
                    </h1>
                    <p className="mt-2 text-[13px] text-tinta-2">
                        {evento.grupo} · {evento.materia}
                        {evento.espacio ? ` · ${evento.espacio}` : ''}
                    </p>
                </div>
                {!cancelado && (
                    <div className="flex flex-wrap gap-3">
                        <Button variant="secondary" onClick={() => setEditando(true)}>
                            Reprogramar
                        </Button>
                        <Button variant="danger" onClick={cancelarEvento}>
                            Cancelar evento
                        </Button>
                    </div>
                )}
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                {/* ── El panel de lecturas ─────────────────────────────── */}
                <div className="space-y-6">
                    <Lamina depth={0} retardo={100}>
                        <div className="divide-y divide-regla-suave">
                            <Lectura rotulo="Horario">
                                <p className="text-[15px] font-semibold text-tinta">{diaLargo(evento.inicio_local)}</p>
                                <p className="dato mt-0.5 text-[13px] text-tinta-2">
                                    {evento.inicio_local.slice(11, 16)}–{evento.fin_local.slice(11, 16)}
                                </p>
                            </Lectura>

                            <Lectura rotulo="Cupo">
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <CupoPuntos ocupados={evento.reservas_activas} cupo={evento.cupo_maximo} />
                                    <span className="dato text-[13px] font-medium text-tinta">
                                        {evento.reservas_activas}/{evento.cupo_maximo}
                                    </span>
                                    <span className={`rotulo ${libres === 0 ? 'text-alerta' : 'text-tinta-3'}`}>
                                        {libres === 0 ? 'lleno' : `${libres} ${plural(libres, 'libre')}`}
                                    </span>
                                </div>
                            </Lectura>

                            <Lectura rotulo="Estatus">
                                <Badge tone={TONO_ESTATUS[evento.estatus] ?? 'muted'}>
                                    {evento.estatus.replace('_', ' ')}
                                </Badge>
                            </Lectura>
                        </div>
                    </Lamina>

                    {evento.multi_slot && (
                        <Lamina depth={0} retardo={200}>
                            <header className="border-b border-regla-suave px-5 py-3.5">
                                <p className="rotulo text-tinta-2">Ocupación por horario</p>
                            </header>
                            {agruparPorDia(evento.ocupacion).map(([dia, slots]) => (
                                <section key={dia}>
                                    <p className="rotulo border-b border-regla-suave bg-hueco/45 px-5 py-2 text-tinta-2">
                                        {diaLargo(dia)}
                                    </p>
                                    <ul>
                                        {slots.map((o) => (
                                            <li
                                                key={o.inicio_local}
                                                className="flex items-center justify-between gap-3 border-b border-regla-suave px-5 py-2.5 last:border-b-0"
                                            >
                                                <span className="dato text-[13px] text-tinta-2">
                                                    {o.inicio_local.slice(11, 16)}–{o.fin_local.slice(11, 16)}
                                                </span>
                                                <CupoPuntos ocupados={o.ocupados} cupo={evento.cupo_maximo} />
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </Lamina>
                    )}
                </div>

                {/* ── Quién viene ──────────────────────────────────────── */}
                <div data-entra style={{ '--retardo': '160ms' }}>
                    {reservas.length === 0 ? (
                        <EmptyState
                            title="Aún no hay reservas"
                            hint="Cuando los alumnos aparten su lugar en este horario los verás aquí, con su matrícula y la hora que eligieron."
                        />
                    ) : (
                        <Lamina depth={0}>
                            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                                <p className="rotulo text-tinta-2">Alumnos con reserva</p>
                                <p className="rotulo text-tinta-3">
                                    <span className="dato">{reservas.length}</span>{' '}
                                    {plural(reservas.length, 'reserva')}
                                </p>
                            </header>
                            <Table head={['Alumno', 'Matrícula', 'Horario', 'Reservó el']}>
                                {reservas.map((r) => (
                                    <tr key={r.id_reserva}>
                                        <td className="px-4 py-3 text-tinta">{r.nombre}</td>
                                        <td className="dato px-4 py-3 text-xs text-tinta-2">{r.matricula}</td>
                                        <td className="dato px-4 py-3 text-xs text-tinta-2">
                                            {r.inicio_slot_local &&
                                                (cruzaDias
                                                    ? `${diaCorto(r.inicio_slot_local)} ${r.inicio_slot_local.slice(11, 16)}`
                                                    : r.inicio_slot_local.slice(11, 16))}
                                        </td>
                                        <td className="dato px-4 py-3 text-xs text-tinta-3">{r.fecha}</td>
                                    </tr>
                                ))}
                            </Table>
                        </Lamina>
                    )}
                </div>
            </div>

            {/* Montado condicional: useForm se reinicializa con el evento vigente en cada apertura. */}
            {editando && (
                <EventoFormModal
                    open
                    onClose={() => setEditando(false)}
                    grupos={[]}
                    espacios={espacios}
                    cupoDefault={evento.cupo_maximo}
                    evento={evento}
                />
            )}
        </AppLayout>
    );
}
