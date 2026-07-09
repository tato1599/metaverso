import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import CupoPuntos from '../../Components/CupoPuntos';
import EmptyState from '../../Components/EmptyState';
import EventoFormModal from '../../Components/EventoFormModal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const TONO_ESTATUS = { programado: 'muted', en_curso: 'ok', cancelado: 'danger', finalizado: 'muted' };

function fechaBonita(local) {
    // `local` viene del servidor en la TZ del campus (YYYY-MM-DDTHH:mm);
    // se reconstruye por componentes para que la TZ del navegador no la mueva.
    const [fecha, hora] = local.split('T');
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const dow = new Date(anio, mes - 1, dia).toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
    return `${dow}, ${hora}`;
}

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

function diaBonito(fecha) {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function diaCorto(local) {
    // Día corto ('sáb') del datetime local del campus; se reconstruye por
    // componentes para que la TZ del navegador no la mueva (patrón diaBonito).
    const [anio, mes, dia] = local.slice(0, 10).split('-').map(Number);
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-MX', { weekday: 'short' });
}

export default function EventoDetalle({ evento, reservas, espacios }) {
    const [editando, setEditando] = useState(false);
    const cancelado = evento.estatus === 'cancelado';
    // Si la ventana cruza días, la hora sola del slot es ambigua: se antepone el día corto.
    const cruzaDias = evento.inicio_local.slice(0, 10) !== evento.fin_local.slice(0, 10);

    function cancelarEvento() {
        if (confirm('¿Cancelar este evento? Los alumnos verán la práctica como cancelada.')) {
            router.delete(`/panel/eventos/${evento.id_evento}`);
        }
    }

    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/panel/agenda" className="text-[13px] text-tinta-3 hover:text-tinta">
                        ‹ Agenda
                    </Link>
                    <h1 className="mt-1 font-display text-xl font-bold text-tinta">{evento.practica}</h1>
                    <p className="text-[13px] text-tinta-2">
                        {evento.grupo} · {evento.materia}
                        {evento.espacio ? ` · ${evento.espacio}` : ''}
                    </p>
                </div>
                {!cancelado && (
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={() => setEditando(true)}>
                            Reprogramar
                        </Button>
                        <Button variant="danger" onClick={cancelarEvento}>
                            Cancelar evento
                        </Button>
                    </div>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="space-y-4">
                    <Card title="Detalles">
                        <dl className="space-y-3 text-[13px]">
                            <div>
                                <dt className="text-tinta-3">Horario</dt>
                                <dd className="mt-0.5 font-mono text-sm tabular-nums text-tinta">
                                    {fechaBonita(evento.inicio_local)} — {evento.fin_local.slice(11, 16)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-tinta-3">Cupo</dt>
                                <dd className="mt-1 flex items-center gap-2">
                                    <CupoPuntos ocupados={evento.reservas_activas} cupo={evento.cupo_maximo} />
                                    <span className="font-mono text-xs tabular-nums text-tinta-2">
                                        {evento.reservas_activas}/{evento.cupo_maximo}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-tinta-3">Estatus</dt>
                                <dd className="mt-1">
                                    <Badge tone={TONO_ESTATUS[evento.estatus] ?? 'muted'}>
                                        {evento.estatus.replace('_', ' ')}
                                    </Badge>
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    {evento.multi_slot && (
                        <Card title="Ocupación por horario">
                            <div className="space-y-3">
                                {agruparPorDia(evento.ocupacion).map(([dia, slots]) => (
                                    <div key={dia}>
                                        <p className="mb-1 text-[11px] font-semibold tracking-wide text-tinta-3 uppercase">
                                            {diaBonito(dia)}
                                        </p>
                                        <div className="space-y-1.5">
                                            {slots.map((o) => (
                                                <div
                                                    key={o.inicio_local}
                                                    className="flex items-center justify-between gap-3"
                                                >
                                                    <span className="font-mono text-xs tabular-nums text-tinta-2">
                                                        {o.inicio_local.slice(11, 16)}–{o.fin_local.slice(11, 16)}
                                                    </span>
                                                    <CupoPuntos ocupados={o.ocupados} cupo={evento.cupo_maximo} />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>

                <div className="lg:col-span-2">
                    {reservas.length === 0 ? (
                        <EmptyState
                            title="Aún no hay reservas"
                            hint="Cuando los alumnos reserven su lugar en este slot aparecerán aquí."
                        />
                    ) : (
                        <Table head={['Alumno', 'Matrícula', 'Horario', 'Reservó el']}>
                            {reservas.map((r) => (
                                <tr key={r.id_reserva}>
                                    <td className="px-4 py-2.5 text-tinta">{r.nombre}</td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{r.matricula}</td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">
                                        {r.inicio_slot_local &&
                                            (cruzaDias
                                                ? `${diaCorto(r.inicio_slot_local)} ${r.inicio_slot_local.slice(11, 16)}`
                                                : r.inicio_slot_local.slice(11, 16))}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{r.fecha}</td>
                                </tr>
                            ))}
                        </Table>
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
