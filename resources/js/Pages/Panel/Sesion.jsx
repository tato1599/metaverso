import { Head } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';

const TONO_SESION = { completada: 'ok', en_progreso: 'warn', abandonada: 'danger' };

function Lectura({ rotulo, children }) {
    return (
        <div className="px-5 py-3.5">
            <p className="rotulo text-tinta-3">{rotulo}</p>
            <div className="mt-1.5">{children}</div>
        </div>
    );
}

export default function Sesion({ sesion }) {
    const backHref = sesion.id_grupo ? `/panel/grupos/${sesion.id_grupo}/resultados` : '/panel';
    const calificada = sesion.calificacion !== null && sesion.calificacion !== undefined;

    return (
        <AppLayout>
            <Head title={`Sesión #${sesion.id_sesion}`} />

            <Encabezado
                volver={{ href: backHref, label: 'Resultados' }}
                titulo={
                    <>
                        Sesión <span className="dato">#{sesion.id_sesion}</span>
                    </>
                }
                meta={
                    <>
                        {sesion.alumno} · {sesion.practica}
                    </>
                }
            />

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                <div className="space-y-6">
                    {/*
                     * La calificación es LA lectura de esta pantalla: va sola, grande y
                     * en mono tabular. Todo lo demás la acompaña.
                     */}
                    <Lamina depth={0} retardo={100}>
                        <div className="px-5 py-5">
                            <p className="rotulo text-tinta-3">Calificación</p>
                            <p
                                className={`dato mt-2 text-[clamp(2.5rem,6vw,3.5rem)] leading-none font-semibold ${
                                    calificada ? 'text-tinta' : 'text-tinta-3'
                                }`}
                            >
                                {calificada ? sesion.calificacion : '—'}
                            </p>
                            {!calificada && (
                                <p className="mt-2 text-[13px] text-tinta-3">
                                    El juego aún no ha reportado un resultado para esta sesión.
                                </p>
                            )}
                        </div>

                        <div className="divide-y divide-regla-suave border-t border-regla-suave">
                            <Lectura rotulo="Estatus">
                                <Badge tone={TONO_SESION[sesion.estatus] ?? 'muted'}>
                                    {sesion.estatus.replace('_', ' ')}
                                </Badge>
                            </Lectura>
                            <Lectura rotulo="Inicio">
                                <p className="dato text-[13px] text-tinta-2">{sesion.inicio ?? '—'}</p>
                            </Lectura>
                            <Lectura rotulo="Fin">
                                <p className="dato text-[13px] text-tinta-2">{sesion.fin ?? '—'}</p>
                            </Lectura>
                        </div>
                    </Lamina>
                </div>

                <div data-entra style={{ '--retardo': '160ms' }}>
                    <Lamina depth={0}>
                        <header className="border-b border-regla-suave px-5 py-3.5">
                            <p className="rotulo text-tinta-2">Telemetría del juego</p>
                        </header>
                        <div className="p-5">
                            {sesion.datos_resultado ? (
                                /* Aquí la mono no es disfraz: es el volcado crudo del motor. */
                                <pre className="overflow-x-auto rounded-ctl bg-hueco/70 p-4 font-mono text-xs leading-relaxed text-tinta-2">
                                    {JSON.stringify(sesion.datos_resultado, null, 2)}
                                </pre>
                            ) : (
                                <p className="text-[13px] text-tinta-3">
                                    Sin datos de telemetría. El juego los envía al cerrar la sesión.
                                </p>
                            )}
                        </div>
                    </Lamina>
                </div>
            </div>
        </AppLayout>
    );
}
