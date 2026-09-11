import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import CabeceraAdmin, { claseAccion } from '../../Components/CabeceraAdmin';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import EmptyState from '../../Components/EmptyState';
import FormField, { Select, TextInput } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';

function GrupoFormModal({ fila, materias, maestros, ciclos, contextos, cerrar }) {
    const { data, setData, post, put, processing, errors } = useForm({
        id_materia: fila?.id_materia ?? '',
        id_maestro: fila?.id_maestro ?? '',
        id_ciclo: fila?.id_ciclo ?? '',
        clave: fila?.clave ?? '',
        cupo_maximo: fila?.cupo_maximo ?? '',
        id_lti_contexto: fila?.id_lti_contexto ?? '',
    });

    function submit(e) {
        e.preventDefault();
        const opts = { onSuccess: cerrar, preserveScroll: true };
        if (fila) {
            put(`/admin/grupos/${fila.id_grupo}`, opts);
        } else {
            post('/admin/grupos', opts);
        }
    }

    const selects = [
        { name: 'id_materia', label: 'Materia', opciones: materias },
        { name: 'id_maestro', label: 'Maestro', opciones: maestros },
        { name: 'id_ciclo', label: 'Ciclo escolar', opciones: ciclos },
    ];

    return (
        <Modal open onClose={cerrar} title={fila ? 'Editar — Grupos' : 'Agregar — Grupos'}>
            <form onSubmit={submit} className="space-y-4">
                {selects.map((s) => (
                    <FormField key={s.name} label={s.label} name={s.name} error={errors[s.name]}>
                        <Select
                            value={data[s.name]}
                            onChange={(e) => setData(s.name, e.target.value)}
                            required
                        >
                            <option value="">Elige…</option>
                            {s.opciones.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                ))}
                <FormField label="Clave" name="clave" error={errors.clave}>
                    <TextInput
                        value={data.clave}
                        onChange={(e) => setData('clave', e.target.value)}
                        placeholder="3A"
                        required
                    />
                </FormField>
                <FormField label="Cupo máximo" name="cupo_maximo" error={errors.cupo_maximo}>
                    <TextInput
                        type="number"
                        min={1}
                        value={data.cupo_maximo}
                        onChange={(e) => setData('cupo_maximo', e.target.value)}
                        required
                    />
                </FormField>
                <FormField label="Curso Moodle" name="id_lti_contexto" error={errors.id_lti_contexto}>
                    <Select
                        value={data.id_lti_contexto ?? ''}
                        onChange={(e) => setData('id_lti_contexto', e.target.value)}
                    >
                        <option value="">Sin vincular</option>
                        {contextos.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>
                </FormField>
                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing} aria-busy={processing}>
                        {fila ? 'Guardar cambios' : 'Crear'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function InscripcionesModal({ grupo, alumnos, cerrar }) {
    const { flash, errors: erroresPagina } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({ id_alumno: '' });
    const [sincronizando, setSincronizando] = useState(false);
    const [resumenVisible, setResumenVisible] = useState(false);
    const inscritos = new Set(grupo.inscripciones.map((i) => i.id_alumno));
    const disponibles = alumnos.filter((a) => !inscritos.has(a.id_alumno));

    function sincronizar() {
        setResumenVisible(false);
        setSincronizando(true);
        router.post(`/admin/grupos/${grupo.id_grupo}/sincronizar`, {}, {
            preserveScroll: true,
            onSuccess: () => setResumenVisible(true),
            onFinish: () => setSincronizando(false),
        });
    }

    function inscribir(e) {
        e.preventDefault();
        post(`/admin/grupos/${grupo.id_grupo}/inscripciones`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    function quitar(inscripcion) {
        if (confirm(`¿Dar de baja a ${inscripcion.nombre} del grupo ${grupo.clave}?`)) {
            router.delete(`/admin/grupos/${grupo.id_grupo}/inscripciones/${inscripcion.id_inscripcion}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <Modal open onClose={cerrar} title={`Alumnos — ${grupo.clave} · ${grupo.materia}`}>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <p className="text-[13px] text-tinta-2">Inscritos activos</p>
                    <Badge tone={grupo.inscritos >= grupo.cupo_maximo ? 'warn' : 'muted'}>
                        {grupo.inscritos} / {grupo.cupo_maximo}
                    </Badge>
                </div>

                {grupo.id_lti_contexto && (
                    <div className="space-y-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={sincronizar}
                            disabled={sincronizando}
                        >
                            {sincronizando ? 'Sincronizando…' : 'Sincronizar desde Moodle'}
                        </Button>
                        {erroresPagina?.sincronizar && (
                            <p className="rounded-ctl bg-alerta-tinte px-3 py-2 text-[13px] font-medium text-alerta">
                                {erroresPagina.sincronizar}
                            </p>
                        )}
                        {resumenVisible && flash?.success && (
                            <p className="rounded-ctl bg-hueco px-3 py-2 text-[13px] text-tinta-2">
                                {flash.success}
                            </p>
                        )}
                    </div>
                )}

                {grupo.inscripciones.length === 0 ? (
                    <p className="rounded-ctl bg-hueco px-4 py-3 text-[13px] text-tinta-3">
                        Aún no hay alumnos inscritos en este grupo.
                    </p>
                ) : (
                    <ul className="divide-y divide-borde rounded-ctl ring-1 ring-borde">
                        {grupo.inscripciones.map((i) => (
                            <li key={i.id_inscripcion} className="flex items-center justify-between gap-3 px-3 py-2">
                                <div className="min-w-0">
                                    <p className="truncate text-sm text-tinta">{i.nombre}</p>
                                    <p className="font-mono text-xs tabular-nums text-tinta-2">{i.matricula}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => quitar(i)}
                                    className="shrink-0 rounded-ctl px-2 py-1 text-xs font-medium text-alerta hover:bg-alerta-tinte"
                                >
                                    Quitar
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <form onSubmit={inscribir} className="space-y-3 border-t border-borde pt-4">
                    <FormField label="Agregar alumno" name="id_alumno" error={errors.id_alumno}>
                        <Select
                            value={data.id_alumno}
                            onChange={(e) => setData('id_alumno', e.target.value)}
                            required
                        >
                            <option value="">Elige un alumno…</option>
                            {disponibles.map((a) => (
                                <option key={a.id_alumno} value={a.id_alumno}>
                                    {a.etiqueta}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={cerrar}>
                            Cerrar
                        </Button>
                        <Button type="submit" disabled={processing || !data.id_alumno}>
                            Inscribir
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}

export default function Grupos({ filas, materias, maestros, ciclos, alumnos, contextos }) {
    const { errors } = usePage().props;
    const [busqueda, setBusqueda] = useState('');
    const [editando, setEditando] = useState(null); // null | 'nuevo' | fila
    const [idInscripciones, setIdInscripciones] = useState(null);

    const visibles = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        if (!q) return filas;
        return filas.filter((f) =>
            [f.clave, f.materia, f.maestro, f.ciclo].join(' ').toLowerCase().includes(q),
        );
    }, [filas, busqueda]);

    // Se deriva de props para que el modal refleje altas/bajas tras cada visita Inertia.
    const grupoAbierto = idInscripciones === null ? null : filas.find((f) => f.id_grupo === idInscripciones);

    function eliminar(fila) {
        if (confirm(`¿Eliminar el grupo ${fila.clave}?`)) {
            router.delete(`/admin/grupos/${fila.id_grupo}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title="Grupos" />
            <AdminNav />
            <CabeceraAdmin
                titulo="Grupos"
                busqueda={busqueda}
                setBusqueda={setBusqueda}
                onAgregar={() => setEditando('nuevo')}
                contador={`${filas.length} ${filas.length === 1 ? 'registro' : 'registros'}`}
            />

            {errors?.eliminar && (
                <p className="mb-4 rounded-ctl bg-alerta-tinte px-4 py-2.5 text-[13px] font-medium text-alerta">
                    {errors.eliminar}
                </p>
            )}

            {visibles.length === 0 ? (
                <EmptyState
                    title={busqueda ? 'Sin resultados' : 'Aún no hay grupos'}
                    hint={busqueda ? 'Prueba con otra búsqueda.' : 'Crea el primer grupo con el botón Agregar.'}
                />
            ) : (
                <Lamina depth={0.2} retardo={120}>
                <Table head={['Clave', 'Materia', 'Maestro', 'Ciclo', 'Cupo', 'Inscritos', '']}>
                    {visibles.map((f) => (
                        <tr key={f.id_grupo}>
                            <td className="dato px-4 py-3 text-xs text-tinta-2">{f.clave}</td>
                            <td className="px-4 py-3 text-tinta">{f.materia}</td>
                            <td className="px-4 py-3 text-tinta">{f.maestro}</td>
                            <td className="px-4 py-3 text-tinta">{f.ciclo}</td>
                            <td className="dato px-4 py-3 text-xs text-tinta-2">{f.cupo_maximo}</td>
                            <td className="px-4 py-2.5">
                                <Badge tone={f.inscritos >= f.cupo_maximo ? 'warn' : 'muted'}>
                                    {f.inscritos}
                                </Badge>
                            </td>
                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    onClick={() => setIdInscripciones(f.id_grupo)}
                                    className={`${claseAccion} text-portal hover:text-portal-fuerte`}
                                    aria-label={`Alumnos ${f.clave}`}
                                >
                                    Alumnos
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setEditando(f)}
                                    className={`${claseAccion} ml-4 text-portal hover:text-portal-fuerte`}
                                    aria-label={`Editar ${f.clave}`}
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => eliminar(f)}
                                    className={`${claseAccion} ml-4 text-tinta-3 hover:text-alerta`}
                                    aria-label={`Eliminar ${f.clave}`}
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    ))}
                </Table>
                </Lamina>
            )}

            {editando !== null && (
                <GrupoFormModal
                    fila={editando === 'nuevo' ? null : editando}
                    materias={materias}
                    maestros={maestros}
                    ciclos={ciclos}
                    contextos={contextos}
                    cerrar={() => setEditando(null)}
                />
            )}

            {grupoAbierto && (
                <InscripcionesModal
                    grupo={grupoAbierto}
                    alumnos={alumnos}
                    cerrar={() => setIdInscripciones(null)}
                />
            )}
        </AppLayout>
    );
}
