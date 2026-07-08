import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import CupoPuntos from '../../Components/CupoPuntos';
import EmptyState from '../../Components/EmptyState';
import EventoFormModal from '../../Components/EventoFormModal';
import WeekCalendar from '../../Components/WeekCalendar';
import AppLayout from '../../Layouts/AppLayout';

const TONO_ESTATUS = { en_curso: 'ok', cancelado: 'danger', finalizado: 'muted' };

function hora(iso) {
    return new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function sumarDias(fecha, dias) {
    const d = new Date(`${fecha}T00:00:00`);
    d.setDate(d.getDate() + dias);
    return d.toISOString().slice(0, 10);
}

export default function Agenda({ semana, eventos, grupos, espacios, cupoDefault }) {
    const [modalAbierto, setModalAbierto] = useState(false);

    function irASemana(fecha) {
        router.get('/panel/agenda', fecha ? { semana: fecha } : {}, { preserveState: false });
    }

    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-4">
                    <h1 className="font-display text-xl font-bold text-tinta">Agenda</h1>
                    <div className="flex items-center gap-1">
                        <Button variant="ghost" aria-label="Semana anterior" onClick={() => irASemana(sumarDias(semana, -7))}>
                            ‹
                        </Button>
                        <Button variant="ghost" onClick={() => irASemana(null)}>
                            Hoy
                        </Button>
                        <Button variant="ghost" aria-label="Semana siguiente" onClick={() => irASemana(sumarDias(semana, 7))}>
                            ›
                        </Button>
                        <span className="ml-2 font-mono text-xs tabular-nums text-tinta-3">
                            semana del {semana}
                        </span>
                    </div>
                </div>
                {grupos.length > 0 && (
                    <Button onClick={() => setModalAbierto(true)}>Agendar práctica</Button>
                )}
            </div>

            {eventos.length === 0 && grupos.length === 0 ? (
                <EmptyState
                    title="No tienes grupos asignados"
                    hint="Cuando coordinación te asigne grupos podrás agendar prácticas aquí."
                />
            ) : (
                <WeekCalendar
                    semana={semana}
                    eventos={eventos}
                    renderEvento={(e) => (
                        <Link
                            key={e.id_evento}
                            href={`/panel/eventos/${e.id_evento}`}
                            className={`block rounded-ctl bg-lienzo p-2 ring-1 ring-borde transition-colors outline-offset-2 hover:ring-borde-fuerte focus-visible:outline-2 focus-visible:outline-portal ${
                                e.estatus === 'cancelado' ? 'opacity-55' : ''
                            }`}
                        >
                            <p className="font-mono text-[11px] tabular-nums text-tinta-2">
                                {hora(e.inicio)}–{hora(e.fin)}
                            </p>
                            <p className="mt-0.5 text-[13px] leading-snug font-semibold text-tinta">{e.practica}</p>
                            <p className="text-[11px] text-tinta-3">
                                {e.grupo} · {e.materia}
                            </p>
                            <div className="mt-1.5 flex items-center justify-between">
                                <CupoPuntos ocupados={e.reservas_activas} cupo={e.cupo_maximo} />
                                {e.estatus !== 'programado' && (
                                    <Badge tone={TONO_ESTATUS[e.estatus] ?? 'muted'}>{e.estatus.replace('_', ' ')}</Badge>
                                )}
                            </div>
                        </Link>
                    )}
                />
            )}

            <EventoFormModal
                open={modalAbierto}
                onClose={() => setModalAbierto(false)}
                grupos={grupos}
                espacios={espacios}
                cupoDefault={cupoDefault}
            />
        </AppLayout>
    );
}
