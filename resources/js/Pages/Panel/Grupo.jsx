import { Head, Link, router } from "@inertiajs/react";
import { Fragment } from "react";
import Badge from "../../Components/Badge";
import EmptyState from "../../Components/EmptyState";
import Encabezado from "../../Components/Encabezado";
import Lamina from "../../Components/Lamina";
import Table from "../../Components/Table";
import AppLayout from "../../Layouts/AppLayout";
import { plural } from "../../fechas";

const TONO_ESTATUS = {
    programado: "muted",
    en_curso: "ok",
    cancelado: "danger",
    finalizado: "muted",
};

export default function Grupo({ grupo, alumnos, eventos }) {
    // Agrupado POR PRÁCTICA, no por evento: cuando el maestro agenda la misma
    // práctica varias veces esas fechas son alternativas para el alumno, no
    // prácticas distintas. En plano se leían como filas duplicadas.
    const practicas = [];
    for (const e of eventos) {
        const ultima = practicas[practicas.length - 1];
        if (ultima && ultima.id_practica === e.id_practica) {
            ultima.fechas.push(e);
        } else {
            practicas.push({
                id_practica: e.id_practica,
                titulo: e.practica,
                fechas: [e],
            });
        }
    }

    return (
        <AppLayout>
            <Head title={`Grupo ${grupo.clave}`} />

            <Encabezado
                volver={{ href: "/panel", label: "Mis grupos" }}
                titulo={`${grupo.clave} — ${grupo.materia}`}
                meta={<>Ciclo {grupo.ciclo}</>}
                acciones={
                    <Link
                        href={`/panel/grupos/${grupo.id_grupo}/resultados`}
                        className="inline-flex h-[42px] items-center justify-center rounded-ctl bg-vidrio-2 px-4 text-[15px] font-semibold text-tinta shadow-[0_0_0_1px_var(--color-borde)_inset] transition-[background-color,box-shadow] outline-offset-2 hover:bg-white/70 hover:shadow-[0_0_0_1px_var(--color-borde-fuerte)_inset] focus-visible:outline-2 focus-visible:outline-portal"
                    >
                        Ver resultados
                    </Link>
                }
            />

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <Lamina depth={0} retardo={100}>
                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Alumnos inscritos</p>
                        <p className="rotulo text-tinta-3">
                            <span className="dato">{alumnos.length}</span>{" "}
                            {plural(alumnos.length, "alumno")}
                        </p>
                    </header>

                    {alumnos.length === 0 ? (
                        <div className="p-5">
                            <EmptyState
                                title="Sin alumnos inscritos"
                                hint="Los alumnos que coordinación inscriba al grupo aparecerán aquí."
                            />
                        </div>
                    ) : (
                        <Table head={["Matrícula", "Nombre"]}>
                            {alumnos.map((a) => (
                                <tr key={a.id_inscripcion}>
                                    <td className="dato px-4 py-3 text-xs text-tinta-2">
                                        {a.matricula}
                                    </td>
                                    <td className="px-4 py-3 text-tinta">
                                        {a.nombre}
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Lamina>

                <Lamina depth={0} retardo={180}>
                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">
                            Prácticas agendadas
                        </p>
                        <p className="rotulo text-tinta-3">
                            <span className="dato">{practicas.length}</span>{" "}
                            {plural(practicas.length, "práctica")} ·{" "}
                            <span className="dato">{eventos.length}</span>{" "}
                            {plural(eventos.length, "fecha")}
                        </p>
                    </header>

                    {eventos.length === 0 ? (
                        <div className="p-5">
                            <EmptyState
                                title="Sin prácticas agendadas"
                                hint="Agenda una práctica para este grupo desde la Agenda."
                            />
                        </div>
                    ) : (
                        <Table head={["Fecha", "Estatus", ""]}>
                            {practicas.map((p) => (
                                <Fragment key={p.id_practica}>
                                    <tr>
                                        <th
                                            scope="colgroup"
                                            colSpan={3}
                                            className="border-t border-regla-suave bg-vidrio-2/60 px-4 pt-3 pb-2 text-left"
                                        >
                                            <span className="text-[15px] font-semibold text-tinta">
                                                {p.titulo}
                                            </span>
                                            <span className="rotulo ml-2 text-tinta-3">
                                                <span className="dato">
                                                    {p.fechas.length}
                                                </span>{" "}
                                                {plural(
                                                    p.fechas.length,
                                                    "fecha",
                                                )}
                                            </span>
                                        </th>
                                    </tr>
                                    {p.fechas.map((e) => (
                                        <tr key={e.id_evento}>
                                            <td className="px-4 py-3 pl-6">
                                                <Link
                                                    href={`/panel/eventos/${e.id_evento}`}
                                                    className="dato rounded-ctl text-xs text-tinta outline-offset-2 transition-colors hover:text-portal focus-visible:outline-2 focus-visible:outline-portal"
                                                >
                                                    {e.inicio_local.replace(
                                                        "T",
                                                        " ",
                                                    )}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    tone={
                                                        TONO_ESTATUS[
                                                            e.estatus
                                                        ] ?? "muted"
                                                    }
                                                >
                                                    {e.estatus.replace(
                                                        "_",
                                                        " ",
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `/panel/grupos/${grupo.id_grupo}/eventos/${e.id_evento}/links`,
                                                        )
                                                    }
                                                    aria-label={`Generar enlaces de juego para ${p.titulo} del ${e.inicio_local.replace("T", " ")}`}
                                                    className="rotulo rounded-ctl text-portal outline-offset-2 transition-colors hover:text-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                                                >
                                                    Generar links
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </Fragment>
                            ))}
                        </Table>
                    )}
                </Lamina>
            </div>
        </AppLayout>
    );
}
