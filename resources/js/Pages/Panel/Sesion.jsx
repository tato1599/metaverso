import { Link } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Card from '../../Components/Card';
import AppLayout from '../../Layouts/AppLayout';

const TONO_SESION = { completada: 'ok', en_progreso: 'warn', abandonada: 'danger' };

export default function Sesion({ sesion }) {
    const backHref = sesion.id_grupo ? `/panel/grupos/${sesion.id_grupo}/resultados` : '/panel';

    return (
        <AppLayout>
            <div className="mb-6">
                <Link href={backHref} className="text-[13px] text-tinta-3 hover:text-tinta">
                    ‹ Resultados
                </Link>
                <h1 className="mt-1 font-display text-xl font-bold text-tinta">
                    Sesión <span className="font-mono tabular-nums">#{sesion.id_sesion}</span>
                </h1>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card title="Datos">
                    <dl className="space-y-3 text-[13px]">
                        <div>
                            <dt className="text-tinta-3">Alumno</dt>
                            <dd className="mt-0.5 font-medium text-tinta">{sesion.alumno}</dd>
                        </div>
                        <div>
                            <dt className="text-tinta-3">Práctica</dt>
                            <dd className="mt-0.5 text-tinta">{sesion.practica}</dd>
                        </div>
                        <div>
                            <dt className="text-tinta-3">Inicio — Fin</dt>
                            <dd className="mt-0.5 font-mono text-xs tabular-nums text-tinta-2">
                                {sesion.inicio ?? '—'} — {sesion.fin ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-tinta-3">Estatus</dt>
                            <dd className="mt-1">
                                <Badge tone={TONO_SESION[sesion.estatus] ?? 'muted'}>
                                    {sesion.estatus.replace('_', ' ')}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-tinta-3">Calificación</dt>
                            <dd className="mt-0.5 font-mono text-2xl font-semibold tabular-nums text-tinta">
                                {sesion.calificacion ?? '—'}
                            </dd>
                        </div>
                    </dl>
                </Card>

                <div className="lg:col-span-2">
                    <Card title="Telemetría del juego">
                        {sesion.datos_resultado ? (
                            <pre className="overflow-x-auto rounded-ctl bg-hueco p-3 font-mono text-xs leading-relaxed text-tinta-2">
                                {JSON.stringify(sesion.datos_resultado, null, 2)}
                            </pre>
                        ) : (
                            <p className="text-[13px] text-tinta-3">Sin datos de telemetría.</p>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
