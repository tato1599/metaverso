import { Link } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import EmptyState from '../../Components/EmptyState';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const TONO_SESION = { completada: 'ok', en_progreso: 'warn', abandonada: 'danger' };

export default function Resultados({ grupo, sesiones }) {
    return (
        <AppLayout>
            <div className="mb-6">
                <Link href={`/panel/grupos/${grupo.id_grupo}`} className="text-[13px] text-tinta-3 hover:text-tinta">
                    ‹ Grupo {grupo.clave}
                </Link>
                <h1 className="mt-1 font-display text-xl font-bold text-tinta">Resultados</h1>
            </div>

            {sesiones.length === 0 ? (
                <EmptyState
                    title="Aún no hay sesiones"
                    hint="Cuando los alumnos jueguen sus prácticas, las sesiones y calificaciones aparecerán aquí."
                />
            ) : (
                <Table head={['Alumno', 'Práctica', 'Inicio', 'Estatus', 'Calificación', '']}>
                    {sesiones.map((s) => (
                        <tr key={s.id_sesion}>
                            <td className="px-4 py-2.5 text-tinta">{s.alumno}</td>
                            <td className="px-4 py-2.5 text-tinta-2">{s.practica}</td>
                            <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{s.inicio}</td>
                            <td className="px-4 py-2.5">
                                <Badge tone={TONO_SESION[s.estatus] ?? 'muted'}>{s.estatus.replace('_', ' ')}</Badge>
                            </td>
                            <td className="px-4 py-2.5 font-mono text-sm font-semibold tabular-nums text-tinta">
                                {s.calificacion ?? '—'}
                            </td>
                            <td className="px-4 py-2.5 text-right">
                                <Link
                                    href={`/panel/sesiones/${s.id_sesion}`}
                                    className="text-xs font-medium text-portal hover:underline"
                                >
                                    Ver detalle
                                </Link>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}
        </AppLayout>
    );
}
