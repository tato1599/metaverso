import { Head, Link } from '@inertiajs/react';
import EmptyState from '../../Components/EmptyState';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';
import { plural } from '../../fechas';

/*
 * CONTRATO DE DIRECCIÓN — Mis cursos · Metaverso
 *
 * THESIS: el índice del expediente. Una fila por curso con su avance medido;
 * las prácticas viven en el detalle. Rechaza volcar todas las prácticas de todos
 * los cursos aquí, que obliga a leerlo todo para encontrar una cosa.
 * OWN-WORLD: el mundo del Instrumento. Una lámina, filas separadas por reglas
 * capilares, el avance en mono tabular y lo pendiente en ámbar.
 * STORY: "¿cuántos cursos llevo y en cuál me falta algo?" se contesta sin abrir
 * nada; entrar es para el detalle.
 * FIRST VIEWPORT: título, conteo, y la lista completa de cursos.
 * FORM: solo lectura.
 */

/**
 * El avance como medición. La fracción siempre lleva su unidad —si el rótulo se
 * sustituye por "sin reservar", el 0/3 se queda sin decir de qué es— y lo
 * pendiente se dice aparte, en ámbar, porque es lo único que pide una acción.
 */
function Avance({ c }) {
    return (
        <span className="shrink-0 text-right">
            <span className="dato block text-[15px] font-semibold text-tinta">
                {c.completadas}/{c.total}
            </span>
            <span className="rotulo block text-tinta-3">completadas</span>
            {c.por_reservar > 0 && (
                <span className="rotulo mt-1.5 block text-senal">
                    <span className="dato">{c.por_reservar}</span> sin reservar
                </span>
            )}
        </span>
    );
}

export default function Cursos({ cursos }) {
    const practicas = cursos.reduce((n, c) => n + c.total, 0);

    return (
        <AppLayout>
            <Head title="Mis cursos" />

            <Encabezado
                rotulo="Mis cursos"
                titulo="Tu expediente"
                meta={
                    cursos.length > 0 && (
                        <>
                            <span className="dato">{cursos.length}</span> {plural(cursos.length, 'curso')} ·{' '}
                            <span className="dato">{practicas}</span> {plural(practicas, 'práctica')} en total
                        </>
                    )
                }
            />

            {cursos.length === 0 ? (
                <EmptyState
                    title="No estás inscrito en ningún curso"
                    hint="Cuando coordinación te inscriba a un grupo, aquí verás sus prácticas y cómo vas en cada una."
                />
            ) : (
                <Lamina retardo={120} className="max-w-[56rem]">
                    <header className="border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Cursos inscritos</p>
                    </header>

                    <ul>
                        {cursos.map((c) => (
                            <li key={c.id_grupo} className="border-t border-regla-suave first:border-t-0">
                                <Link
                                    href={`/mi/cursos/${c.id_grupo}`}
                                    className="group flex items-baseline justify-between gap-x-6 px-5 py-4 outline-offset-[-2px] transition-colors hover:bg-vidrio-2 focus-visible:outline-2 focus-visible:outline-portal"
                                >
                                    <span className="min-w-0">
                                        <span className="block text-[15px] font-semibold text-tinta group-hover:text-portal">
                                            {c.materia}
                                        </span>
                                        <span className="mt-1 flex flex-wrap items-baseline gap-x-2 text-[13px] text-tinta-2">
                                            <span className="dato">{c.clave}</span>
                                            {c.ciclo && <span className="dato text-tinta-3">· {c.ciclo}</span>}
                                            {c.maestro && <span className="text-tinta-3">· {c.maestro}</span>}
                                        </span>
                                    </span>

                                    <Avance c={c} />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Lamina>
            )}
        </AppLayout>
    );
}
