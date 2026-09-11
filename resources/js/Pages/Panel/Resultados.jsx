import { Head, Link } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EmptyState from '../../Components/EmptyState';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const TONO_SESION = { completada: 'ok', en_progreso: 'warn', abandonada: 'danger' };

export default function Resultados({ grupo, sesiones }) {
    const calificadas = sesiones.filter((s) => s.calificacion !== null && s.calificacion !== undefined);
    const promedio = calificadas.length
        ? (calificadas.reduce((n, s) => n + Number(s.calificacion), 0) / calificadas.length).toFixed(1)
        : null;

    return (
        <AppLayout>
            <Head title={`Resultados · ${grupo.clave}`} />

            <Encabezado
                volver={{ href: `/panel/grupos/${grupo.id_grupo}`, label: `Grupo ${grupo.clave}` }}
                titulo="Resultados"
                meta={
                    <>
                        <span className="dato">{sesiones.length}</span>{' '}
                        {/* sesión → sesiones pierde el acento: no es sufijo, es otra palabra. */}
                        {sesiones.length === 1 ? 'sesión registrada' : 'sesiones registradas'}
                    </>
                }
            />

            {sesiones.length === 0 ? (
                <EmptyState
                    title="Aún no hay sesiones"
                    hint="Cuando los alumnos jueguen sus prácticas, cada sesión aparecerá aquí con su calificación y su telemetría."
                />
            ) : (
                <Lamina depth={0.25} retardo={120}>
                    <header className="flex flex-wrap items-baseline justify-between gap-x-5 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Sesiones del grupo</p>
                        <p className="rotulo text-tinta-3">
                            {promedio === null ? (
                                'sin calificaciones'
                            ) : (
                                <>
                                    promedio <span className="dato text-tinta">{promedio}</span> ·{' '}
                                    <span className="dato">{calificadas.length}</span> de{' '}
                                    <span className="dato">{sesiones.length}</span> calificadas
                                </>
                            )}
                        </p>
                    </header>

                    <Table
                        head={['Alumno', 'Práctica', 'Inicio', 'Estatus', 'Calificación', '']}
                        derechas={[4]}
                    >
                        {sesiones.map((s) => (
                            <tr key={s.id_sesion}>
                                <td className="px-4 py-3 text-tinta">{s.alumno}</td>
                                <td className="px-4 py-3 text-tinta-2">{s.practica}</td>
                                <td className="dato px-4 py-3 text-xs text-tinta-3">{s.inicio}</td>
                                <td className="px-4 py-3">
                                    <Badge tone={TONO_SESION[s.estatus] ?? 'muted'}>
                                        {s.estatus.replace('_', ' ')}
                                    </Badge>
                                </td>
                                <td className="dato px-4 py-3 text-right font-semibold text-tinta">{s.calificacion ?? '—'}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/panel/sesiones/${s.id_sesion}`}
                                        aria-label={`Ver detalle de la sesión de ${s.alumno}`}
                                        className="rotulo rounded-ctl text-portal outline-offset-2 transition-colors hover:text-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                                    >
                                        Detalle →
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Lamina>
            )}
        </AppLayout>
    );
}
