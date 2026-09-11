import { Head, Link } from '@inertiajs/react';
import EmptyState from '../../Components/EmptyState';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';

/** El español no tolera "1 grupos": la unidad se escribe según la cifra. */
function plural(n, singular, sufijo = 's') {
    return n === 1 ? singular : singular + sufijo;
}

/*
 * Los grupos son una lectura, no una colección de tarjetas iguales: clave,
 * materia, ciclo y número de alumnos son datos duros y se leen mejor apilados
 * en filas con reglas capilares, comparables columna contra columna.
 */
function FilaGrupo({ grupo, retardo }) {
    return (
        <li
            data-entra
            style={{ '--retardo': `${retardo}ms` }}
            className="border-t border-regla-suave first:border-t-0"
        >
            <Link
                href={`/panel/grupos/${grupo.id_grupo}`}
                className="group grid grid-cols-[auto_minmax(0,1fr)_auto] items-baseline gap-x-5 px-5 py-4 outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal sm:grid-cols-[3.5rem_minmax(0,1fr)_auto_auto]"
            >
                <span className="dato text-[13px] font-medium text-tinta">{grupo.clave}</span>

                <span className="min-w-0">
                    <span className="block truncate text-[15px] font-semibold text-tinta group-hover:text-portal">
                        {grupo.materia}
                    </span>
                    <span className="dato mt-1 block text-[11px] text-tinta-3 sm:hidden">
                        Ciclo {grupo.ciclo}
                    </span>
                </span>

                <span className="dato hidden text-[13px] text-tinta-3 sm:block">{grupo.ciclo}</span>

                <span className="text-right whitespace-nowrap">
                    <span className="dato text-[13px] font-medium text-tinta">{grupo.alumnos}</span>{' '}
                    <span className="text-[13px] text-tinta-3">{plural(grupo.alumnos, 'alumno')}</span>
                </span>
            </Link>
        </li>
    );
}

export default function Dashboard({ grupos }) {
    const alumnos = grupos.reduce((n, g) => n + g.alumnos, 0);

    return (
        <AppLayout>
            <Head title="Mis grupos" />

            <div
                data-entra
                className="mb-7 flex flex-wrap items-end justify-between gap-x-6 gap-y-3"
            >
                <div>
                    <p className="rotulo grabado text-tinta-3">Panel</p>
                    <h1 className="grabado mt-2 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                        Mis grupos
                    </h1>
                </div>
                <Link
                    href="/panel/agenda"
                    className="rotulo rounded-ctl px-1 py-1.5 text-portal outline-offset-2 transition-colors hover:text-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                >
                    Ver agenda →
                </Link>
            </div>

            {grupos.length === 0 ? (
                <EmptyState
                    title="No tienes grupos asignados"
                    hint="Cuando coordinación te asigne grupos aparecerán aquí, con sus alumnos y sus prácticas."
                />
            ) : (
                <Lamina depth={0.3} retardo={120} className="max-w-[52rem]">
                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Grupos a tu cargo</p>
                        <p className="rotulo text-tinta-3">
                            <span className="dato">{grupos.length}</span>{' '}
                            {plural(grupos.length, 'grupo')} ·{' '}
                            <span className="dato">{alumnos}</span> {plural(alumnos, 'alumno')}
                        </p>
                    </header>

                    <ul>
                        {grupos.map((g, i) => (
                            <FilaGrupo key={g.id_grupo} grupo={g} retardo={200 + i * 60} />
                        ))}
                    </ul>
                </Lamina>
            )}
        </AppLayout>
    );
}
