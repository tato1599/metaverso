import { Link } from '@inertiajs/react';
import Card from '../../Components/Card';
import EmptyState from '../../Components/EmptyState';
import AppLayout from '../../Layouts/AppLayout';

export default function Dashboard({ grupos }) {
    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="font-display text-xl font-bold text-tinta">Grupos</h1>
                <Link href="/panel/agenda" className="text-[13px] text-tinta-3 hover:text-tinta">
                    Ver agenda ›
                </Link>
            </div>

            {grupos.length === 0 ? (
                <EmptyState
                    title="No tienes grupos asignados"
                    hint="Cuando coordinación te asigne grupos aparecerán aquí."
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {grupos.map((g) => (
                        <Card key={g.id_grupo}>
                            <Link
                                href={`/panel/grupos/${g.id_grupo}`}
                                className="text-[15px] font-semibold text-tinta outline-offset-2 hover:text-portal focus-visible:outline-2 focus-visible:outline-portal"
                            >
                                {g.clave} — {g.materia}
                            </Link>
                            <p className="mt-1 text-[13px] text-tinta-3">
                                Ciclo {g.ciclo} ·{' '}
                                <span className="font-mono tabular-nums">{g.alumnos}</span> alumnos
                            </p>
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
