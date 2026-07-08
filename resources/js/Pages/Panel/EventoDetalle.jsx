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

function fechaHora(iso) {
    return new Date(iso).toLocaleString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

export default function EventoDetalle({ evento, reservas, espacios }) {
    const [editando, setEditando] = useState(false);
    const cancelado = evento.estatus === 'cancelado';

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
                <Card title="Detalles">
                    <dl className="space-y-3 text-[13px]">
                        <div>
                            <dt className="text-tinta-3">Horario</dt>
                            <dd className="mt-0.5 font-mono text-sm tabular-nums text-tinta">
                                {fechaHora(evento.inicio)} — {new Date(evento.fin).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false })}
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
                                <Badge tone={TONO_ESTATUS[evento.estatus] ?? 'muted'}>{evento.estatus.replace('_', ' ')}</Badge>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <div className="lg:col-span-2">
                    {reservas.length === 0 ? (
                        <EmptyState
                            title="Aún no hay reservas"
                            hint="Cuando los alumnos reserven su lugar en este slot aparecerán aquí."
                        />
                    ) : (
                        <Table head={['Alumno', 'Matrícula', 'Reservó el']}>
                            {reservas.map((r) => (
                                <tr key={r.id_reserva}>
                                    <td className="px-4 py-2.5 text-tinta">{r.nombre}</td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{r.matricula}</td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{r.fecha}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </div>
            </div>

            <EventoFormModal
                open={editando}
                onClose={() => setEditando(false)}
                grupos={[]}
                espacios={espacios}
                cupoDefault={evento.cupo_maximo}
                evento={evento}
            />
        </AppLayout>
    );
}
