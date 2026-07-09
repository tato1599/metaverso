import { Link } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Card from '../../Components/Card';
import EmptyState from '../../Components/EmptyState';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const TONO_ESTATUS = { programado: 'muted', en_curso: 'ok', cancelado: 'danger', finalizado: 'muted' };

export default function Grupo({ grupo, alumnos, eventos, csrf }) {
    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/panel" className="text-[13px] text-tinta-3 hover:text-tinta">
                        ‹ Mis grupos
                    </Link>
                    <h1 className="mt-1 font-display text-xl font-bold text-tinta">
                        Grupo {grupo.clave} — {grupo.materia}
                    </h1>
                    <p className="text-[13px] text-tinta-2">Ciclo {grupo.ciclo}</p>
                </div>
                <Link
                    href={`/panel/grupos/${grupo.id_grupo}/resultados`}
                    className="inline-flex h-9 items-center justify-center gap-2 rounded-ctl bg-superficie px-4 text-sm font-medium text-tinta ring-1 ring-borde-fuerte transition-colors ring-inset outline-offset-2 hover:bg-hueco focus-visible:outline-2 focus-visible:outline-portal"
                >
                    Ver resultados
                </Link>
            </div>

            <div className="space-y-4">
                <Card title={`Alumnos inscritos (${alumnos.length})`}>
                    {alumnos.length === 0 ? (
                        <EmptyState
                            title="Sin alumnos inscritos"
                            hint="Los alumnos que coordinación inscriba al grupo aparecerán aquí."
                        />
                    ) : (
                        <Table head={['Matrícula', 'Nombre']}>
                            {alumnos.map((a) => (
                                <tr key={a.id_inscripcion}>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">
                                        {a.matricula}
                                    </td>
                                    <td className="px-4 py-2.5 text-tinta">{a.nombre}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>

                <Card title="Eventos / prácticas">
                    {eventos.length === 0 ? (
                        <EmptyState
                            title="Sin prácticas agendadas"
                            hint="Agenda una práctica para este grupo desde la Agenda."
                        />
                    ) : (
                        <Table head={['Práctica', 'Inicio', 'Estatus', '']}>
                            {eventos.map((e) => (
                                <tr key={e.id_evento}>
                                    <td className="px-4 py-2.5">
                                        <Link
                                            href={`/panel/eventos/${e.id_evento}`}
                                            className="font-medium text-tinta outline-offset-2 hover:text-portal focus-visible:outline-2 focus-visible:outline-portal"
                                        >
                                            {e.practica}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">
                                        {e.inicio_local.replace('T', ' ')}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <Badge tone={TONO_ESTATUS[e.estatus] ?? 'muted'}>
                                            {e.estatus.replace('_', ' ')}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2.5 text-right">
                                        {/* Form nativo (no router.post): el endpoint de links aún
                                            responde Blade hasta que Task B lo migre a Inertia. */}
                                        <form
                                            method="post"
                                            action={`/panel/grupos/${grupo.id_grupo}/eventos/${e.id_evento}/links`}
                                            className="inline"
                                        >
                                            <input type="hidden" name="_token" value={csrf} />
                                            <Button type="submit" variant="secondary" className="h-8 px-3 text-xs">
                                                Generar links del grupo
                                            </Button>
                                        </form>
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
