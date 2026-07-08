import { router, usePage } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import CupoPuntos from '../../Components/CupoPuntos';
import EmptyState from '../../Components/EmptyState';
import Table from '../../Components/Table';
import WeekCalendar from '../../Components/WeekCalendar';
import AppLayout from '../../Layouts/AppLayout';

function sumarDias(fecha, dias) {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const d = new Date(anio, mes - 1, dia + dias);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function ChipEvento({ e }) {
    const cancelado = e.estatus === 'cancelado';

    return (
        <div className={`rounded-ctl bg-lienzo p-2 ring-1 ring-borde ${cancelado ? 'opacity-55' : ''}`}>
            <p className="font-mono text-[11px] tabular-nums text-tinta-2">
                {e.inicio_local.slice(11, 16)}–{e.fin_local.slice(11, 16)}
            </p>
            <p className="mt-0.5 text-[13px] leading-snug font-semibold text-tinta">{e.practica}</p>
            <p className="text-[11px] text-tinta-3">
                {e.grupo} · {e.materia}
                {e.espacio ? ` · ${e.espacio}` : ''}
            </p>
            <div className="mt-1.5 flex items-center justify-between gap-2">
                <CupoPuntos ocupados={e.reservas_activas} cupo={e.cupo_maximo} />
                {cancelado && <Badge tone="danger">cancelado</Badge>}
                {!cancelado && e.mi_reserva && !e.puede_jugar && <Badge tone="ok">reservado</Badge>}
                {!cancelado && !e.mi_reserva && !e.puede_reservar && e.estatus === 'programado' && (
                    <Badge tone="warn">lleno</Badge>
                )}
                {e.estatus === 'finalizado' && <Badge tone="muted">finalizado</Badge>}
            </div>
            <div className="mt-2 space-y-1.5">
                {e.puede_jugar && (
                    <Button
                        className="h-8 w-full bg-telemetria hover:opacity-90 focus-visible:outline-telemetria"
                        onClick={() => router.post(`/mi/eventos/${e.id_evento}/jugar`)}
                    >
                        Jugar ahora
                    </Button>
                )}
                {e.puede_reservar && (
                    <Button
                        variant="secondary"
                        className="h-8 w-full"
                        onClick={() => router.post('/mi/reservas', { id_evento: e.id_evento })}
                    >
                        Reservar lugar
                    </Button>
                )}
                {e.puede_cancelar && (
                    <Button
                        variant="ghost"
                        className="h-8 w-full text-xs"
                        onClick={() => {
                            if (confirm('¿Cancelar tu reserva en este slot?')) {
                                router.delete(`/mi/reservas/${e.mi_reserva.id_reserva}`);
                            }
                        }}
                    >
                        Cancelar reserva
                    </Button>
                )}
            </div>
        </div>
    );
}

const TONO_RESERVA = { activa: 'ok', cancelada: 'muted' };

export default function Calendario({ semana, eventos, misReservas }) {
    const { errors } = usePage().props;
    const errorNegocio = errors?.evento || errors?.reserva;

    function irASemana(fecha) {
        router.get('/mi/calendario', fecha ? { semana: fecha } : {}, { preserveState: false });
    }

    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-4">
                    <h1 className="font-display text-xl font-bold text-tinta">Mi calendario</h1>
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
                        <span className="ml-2 font-mono text-xs tabular-nums text-tinta-3">semana del {semana}</span>
                    </div>
                </div>
            </div>

            {errorNegocio && (
                <p className="mb-4 rounded-ctl bg-alerta-tinte px-4 py-2.5 text-[13px] font-medium text-alerta">
                    {errorNegocio}
                </p>
            )}

            {eventos.length === 0 ? (
                <EmptyState
                    title="Sin prácticas esta semana"
                    hint="Cuando tus docentes agenden prácticas para tus grupos aparecerán aquí. Prueba navegar a otras semanas."
                />
            ) : (
                <WeekCalendar semana={semana} eventos={eventos} renderEvento={(e) => <ChipEvento key={e.id_evento} e={e} />} />
            )}

            {misReservas.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-3 text-[15px] font-semibold text-tinta">Mis reservas</h2>
                    <Table head={['Práctica', 'Grupo', 'Fecha', 'Reserva', 'Evento']}>
                        {misReservas.map((r) => (
                            <tr key={r.id_reserva}>
                                <td className="px-4 py-2.5 text-tinta">{r.practica}</td>
                                <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{r.grupo}</td>
                                <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">
                                    {r.inicio_local.replace('T', ' ')}
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge tone={TONO_RESERVA[r.estatus] ?? 'muted'}>{r.estatus}</Badge>
                                </td>
                                <td className="px-4 py-2.5">
                                    <Badge tone={r.estatus_evento === 'cancelado' ? 'danger' : 'muted'}>
                                        {r.estatus_evento.replace('_', ' ')}
                                    </Badge>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </section>
            )}
        </AppLayout>
    );
}
