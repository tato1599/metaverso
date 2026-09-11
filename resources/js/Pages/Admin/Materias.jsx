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

const RUTA_BASE = '/admin/materias';

function FormularioMateria({ cerrar, carreras, fila }) {
    const { data, setData, post, put, processing, errors } = useForm({
        clave: fila?.clave ?? '',
        nombre: fila?.nombre ?? '',
        creditos: fila?.creditos ?? '',
        carreras: (fila?.carreras ?? []).map((c) => ({ id_carrera: c.id_carrera, semestre: c.semestre })),
    });

    function cambiarAsignacion(i, campo, valor) {
        setData(
            'carreras',
            data.carreras.map((a, j) => (j === i ? { ...a, [campo]: valor } : a)),
        );
    }

    function agregarAsignacion() {
        setData('carreras', [...data.carreras, { id_carrera: '', semestre: '' }]);
    }

    function quitarAsignacion(i) {
        setData(
            'carreras',
            data.carreras.filter((_, j) => j !== i),
        );
    }

    function submit(e) {
        e.preventDefault();
        const opts = { onSuccess: cerrar, preserveScroll: true };
        if (fila) {
            put(`${RUTA_BASE}/${fila.id_materia}`, opts);
        } else {
            post(RUTA_BASE, opts);
        }
    }

    return (
        <Modal open onClose={cerrar} title={fila ? 'Editar — Materias' : 'Agregar — Materias'}>
            <form onSubmit={submit} className="space-y-4">
                <FormField label="Clave" name="clave" error={errors.clave}>
                    <TextInput value={data.clave} onChange={(e) => setData('clave', e.target.value)} required />
                </FormField>
                <FormField label="Nombre" name="nombre" error={errors.nombre}>
                    <TextInput value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required />
                </FormField>
                <FormField label="Créditos" name="creditos" error={errors.creditos}>
                    <TextInput
                        type="number"
                        min={0}
                        value={data.creditos}
                        onChange={(e) => setData('creditos', e.target.value)}
                        required
                    />
                </FormField>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="block text-[13px] font-medium text-tinta-2">Carreras (semestre en que se cursa)</span>
                        <Button type="button" variant="ghost" onClick={agregarAsignacion}>
                            Agregar carrera
                        </Button>
                    </div>
                    {data.carreras.length === 0 && (
                        <p className="text-xs text-tinta-3">Sin asignaciones. Usa “Agregar carrera” para asignarla.</p>
                    )}
                    {data.carreras.map((a, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <div className="flex-1">
                                <Select
                                    value={a.id_carrera}
                                    onChange={(e) => cambiarAsignacion(i, 'id_carrera', e.target.value)}
                                    required
                                    aria-label={`Carrera ${i + 1}`}
                                >
                                    <option value="">Elige…</option>
                                    {carreras.map((c) => (
                                        <option key={c.id_carrera} value={c.id_carrera}>
                                            {c.nombre}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <TextInput
                                type="number"
                                min={1}
                                max={15}
                                className="w-24"
                                placeholder="Sem."
                                value={a.semestre}
                                onChange={(e) => cambiarAsignacion(i, 'semestre', e.target.value)}
                                required
                                aria-label={`Semestre ${i + 1}`}
                            />
                            <button
                                type="button"
                                onClick={() => quitarAsignacion(i)}
                                className="rounded-ctl px-2 py-2 text-xs font-medium text-alerta hover:bg-alerta-tinte"
                            >
                                Quitar
                            </button>
                        </div>
                    ))}
                    {Object.entries(errors)
                        .filter(([k]) => k === 'carreras' || k.startsWith('carreras.'))
                        .map(([k, mensaje]) => (
                            <p key={k} className="text-xs text-alerta">
                                {mensaje}
                            </p>
                        ))}
                </div>

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

export default function Materias({ filas, carreras }) {
    const { errors } = usePage().props;
    const [busqueda, setBusqueda] = useState('');
    const [editando, setEditando] = useState(null); // null | 'nuevo' | fila

    const visibles = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        if (!q) return filas;
        return filas.filter((f) => JSON.stringify(f).toLowerCase().includes(q));
    }, [filas, busqueda]);

    function eliminar(fila) {
        if (confirm('¿Eliminar esta materia?')) {
            router.delete(`${RUTA_BASE}/${fila.id_materia}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title="Materias" />
            <AdminNav />
            <CabeceraAdmin
                titulo="Materias"
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
                    title={busqueda ? 'Sin resultados' : 'Aún no hay materias'}
                    hint={busqueda ? 'Prueba con otra búsqueda.' : 'Crea el primer registro con el botón Agregar.'}
                />
            ) : (
                <Lamina depth={0.2} retardo={120}>
                <Table head={['Clave', 'Nombre', 'Créditos', 'Carreras', '']}>
                    {visibles.map((f) => (
                        <tr key={f.id_materia}>
                            <td className="dato px-4 py-3 text-xs text-tinta-2">{f.clave}</td>
                            <td className="px-4 py-3 text-tinta">{f.nombre}</td>
                            <td className="dato px-4 py-3 text-xs text-tinta-2">{f.creditos}</td>
                            <td className="px-4 py-2.5">
                                {f.carreras.length === 0 ? (
                                    <span className="text-xs text-tinta-3">—</span>
                                ) : (
                                    <div className="flex flex-wrap gap-1">
                                        {f.carreras.map((c) => (
                                            <Badge key={c.id_carrera}>
                                                {c.nombre} · sem. {c.semestre}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </td>
                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    onClick={() => setEditando(f)}
                                    className={`${claseAccion} text-portal hover:text-portal-fuerte`}
                                    aria-label={`Editar ${f.nombre}`}
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => eliminar(f)}
                                    className={`${claseAccion} ml-4 text-tinta-3 hover:text-alerta`}
                                    aria-label={`Eliminar ${f.nombre}`}
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
                <FormularioMateria
                    cerrar={() => setEditando(null)}
                    carreras={carreras}
                    fila={editando === 'nuevo' ? null : editando}
                />
            )}
        </AppLayout>
    );
}
