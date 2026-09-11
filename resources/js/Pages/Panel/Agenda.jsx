import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import CupoPuntos from '../../Components/CupoPuntos';
import EmptyState from '../../Components/EmptyState';
import EventoFormModal from '../../Components/EventoFormModal';
import Lamina from '../../Components/Lamina';
import PasoSemana from '../../Components/PasoSemana';
import WeekCalendar from '../../Components/WeekCalendar';
import AppLayout from '../../Layouts/AppLayout';
import { plural, rangoSemana } from '../../fechas';

const TONO_ESTATUS = { en_curso: 'ok', cancelado: 'danger', finalizado: 'muted' };

function EventoChip({ e }) {
    const cancelado = e.estatus === 'cancelado';

    return (
        <Link
            key={e.id_evento}
            href={`/panel/eventos/${e.id_evento}`}
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
                    <span className="rotulo text-tinta-2">
                        <span className="dato">{e.reservas_activas}</span> {plural(e.reservas_activas, 'reserva')}
                    </span>
                ) : (
                    <CupoPuntos ocupados={e.reservas_activas} cupo={e.cupo_maximo} />
                )}
                {e.estatus !== 'programado' && (
                    <Badge tone={TONO_ESTATUS[e.estatus] ?? 'muted'}>{e.estatus.replace('_', ' ')}</Badge>
                )}
            </div>
        </Link>
    );
}

export default function Agenda({ semana, limites, eventos, grupos, espacios, cupoDefault }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    /*
     * La dirección vive en un ref, no en estado: si fuera estado, el clic
     * provocaría un render inmediato, `data-semana` cambiaría y la animación
     * arrancaría CON EL CONTENIDO VIEJO todavía puesto — la rejilla se quedaba en
     * blanco ~85ms esperando la respuesta. Así solo se anima al montar la rejilla
     * nueva, que es cuando el dato ya llegó.
     */
    const direccion = useRef(0);

    /*
     * Recarga PARCIAL: solo `semana` y `eventos`. El componente no se remonta, así
     * que la entrada escalonada de la página no se vuelve a disparar — lo único que
     * se mueve es la rejilla, y `direccion` decide hacia dónde.
     */
    function irASemana(fecha, haciaDonde) {
        direccion.current = haciaDonde;
        router.get('/panel/agenda', fecha ? { semana: fecha } : {}, {
            only: ['semana', 'eventos'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    const sinGrupos = eventos.length === 0 && grupos.length === 0;

    return (
        <AppLayout>
            <Head title="Agenda" />

            <div data-entra className="mb-7 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div>
                    <p className="rotulo grabado text-tinta-3">Agenda del laboratorio</p>
                    <h1 className="grabado mt-2 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                        {rangoSemana(semana)}
                    </h1>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <PasoSemana semana={semana} limites={limites} irASemana={irASemana} />
                    {grupos.length > 0 && <Button onClick={() => setModalAbierto(true)}>Agendar práctica</Button>}
                </div>
            </div>

            {sinGrupos ? (
                <EmptyState
                    title="No tienes grupos asignados"
                    hint="Cuando coordinación te asigne grupos podrás agendar prácticas aquí."
                />
            ) : (
                <Lamina depth={0.25} retardo={120}>
                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Semana</p>
                        <p className="rotulo text-tinta-3">
                            <span className="dato">{eventos.length}</span>{' '}
                            {plural(eventos.length, 'práctica')} {plural(eventos.length, 'agendada')}
                        </p>
                    </header>

                    {/* key por semana: la rejilla se renueva y se anima; el resto no. */}
                    <div key={semana} data-semana={direccion.current}>
                    <WeekCalendar
                        semana={semana}
                        eventos={eventos}
                        renderEvento={(e) => <EventoChip key={e.id_evento} e={e} />}
                    />

                    {/* Semana en blanco: aquí el trabajo del maestro es agendar. */}
                    {eventos.length === 0 && (
                        <p className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 border-t border-regla-suave px-5 py-4 text-center text-[13px] text-tinta-3">
                            No hay prácticas agendadas esta semana.
                            <button
                                type="button"
                                onClick={() => setModalAbierto(true)}
                                className="rounded-ctl px-1 font-semibold text-portal outline-offset-2 transition-colors hover:text-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                            >
                                Agendar una
                            </button>
                        </p>
                    )}
                    </div>
                </Lamina>
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
