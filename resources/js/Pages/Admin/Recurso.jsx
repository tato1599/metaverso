import { router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import Button from '../../Components/Button';
import EmptyState from '../../Components/EmptyState';
import FormField, { Select, TextInput, Textarea } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

/*
 * Página CRUD genérica, schema-driven. SOLO LECTURA para las fases de recursos:
 * el contrato de campos[] es { name, label, tipo, requerido?, min?, max?, opciones? }
 * con tipo ∈ { text, number, date, select, checkbox, textarea }.
 */

function valorInicial(campo) {
    return campo.tipo === 'checkbox' ? false : '';
}

function CampoControl({ campo, value, onChange }) {
    const props = {
        id: campo.name,
        value: value ?? '',
        onChange: (e) => onChange(campo.tipo === 'checkbox' ? e.target.checked : e.target.value),
        required: campo.requerido,
    };

    switch (campo.tipo) {
        case 'select':
            return (
                <Select {...props}>
                    <option value="">Elige…</option>
                    {(campo.opciones ?? []).map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </Select>
            );
        case 'textarea':
            return <Textarea {...props} />;
        case 'checkbox':
            return (
                <input
                    id={campo.name}
                    type="checkbox"
                    checked={Boolean(value)}
                    onChange={props.onChange}
                    className="size-4 rounded-sm accent-(--color-portal)"
                />
            );
        default:
            return <TextInput {...props} type={campo.tipo} min={campo.min} max={campo.max} />;
    }
}

function FormularioModal({ abierto, cerrar, titulo, campos, rutaBase, idKey, fila }) {
    const inicial = Object.fromEntries(campos.map((c) => [c.name, fila?.[c.name] ?? valorInicial(c)]));
    const { data, setData, post, put, processing, errors } = useForm(inicial);

    function submit(e) {
        e.preventDefault();
        const opts = { onSuccess: cerrar, preserveScroll: true };
        if (fila) {
            put(`${rutaBase}/${fila[idKey]}`, opts);
        } else {
            post(rutaBase, opts);
        }
    }

    return (
        <Modal open={abierto} onClose={cerrar} title={titulo}>
            <form onSubmit={submit} className="space-y-4">
                {campos.map((c) => (
                    <FormField key={c.name} label={c.label} name={c.name} error={errors[c.name]}>
                        <CampoControl campo={c} value={data[c.name]} onChange={(v) => setData(c.name, v)} />
                    </FormField>
                ))}
                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {fila ? 'Guardar cambios' : 'Crear'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Recurso({ titulo, rutaBase, idKey, columnas, filas, campos }) {
    const { errors } = usePage().props;
    const [busqueda, setBusqueda] = useState('');
    const [editando, setEditando] = useState(null); // null | 'nuevo' | fila

    const visibles = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        if (!q) return filas;
        return filas.filter((f) => JSON.stringify(f).toLowerCase().includes(q));
    }, [filas, busqueda]);

    function eliminar(fila) {
        if (confirm(`¿Eliminar este registro de ${titulo.toLowerCase()}?`)) {
            router.delete(`${rutaBase}/${fila[idKey]}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <AdminNav />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="font-display text-xl font-bold text-tinta">{titulo}</h1>
                <div className="flex items-center gap-2">
                    <TextInput
                        type="search"
                        name="busqueda"
                        aria-label="Buscar"
                        placeholder="Buscar…"
                        className="w-56"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                    <Button onClick={() => setEditando('nuevo')}>Agregar</Button>
                </div>
            </div>

            {errors?.eliminar && (
                <p className="mb-4 rounded-ctl bg-alerta-tinte px-4 py-2.5 text-[13px] font-medium text-alerta">
                    {errors.eliminar}
                </p>
            )}

            {visibles.length === 0 ? (
                <EmptyState
                    title={busqueda ? 'Sin resultados' : `Aún no hay ${titulo.toLowerCase()}`}
                    hint={busqueda ? 'Prueba con otra búsqueda.' : 'Crea el primer registro con el botón Agregar.'}
                />
            ) : (
                <Table head={[...columnas.map((c) => c.label), '']}>
                    {visibles.map((f) => (
                        <tr key={f[idKey]}>
                            {columnas.map((c) => (
                                <td
                                    key={c.key}
                                    className={`px-4 py-2.5 ${c.mono ? 'font-mono text-xs tabular-nums text-tinta-2' : 'text-tinta'}`}
                                >
                                    {typeof f[c.key] === 'boolean' ? (f[c.key] ? 'sí' : 'no') : f[c.key]}
                                </td>
                            ))}
                            <td className="px-4 py-2.5 text-right whitespace-nowrap">
                                <button
                                    onClick={() => setEditando(f)}
                                    className="rounded-ctl px-2 py-1 text-xs font-medium text-tinta-2 hover:bg-hueco hover:text-tinta"
                                >
                                    Editar
                                </button>
                                <button
                                    onClick={() => eliminar(f)}
                                    className="rounded-ctl px-2 py-1 text-xs font-medium text-alerta hover:bg-alerta-tinte"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            {editando !== null && (
                <FormularioModal
                    abierto
                    cerrar={() => setEditando(null)}
                    titulo={editando === 'nuevo' ? `Agregar — ${titulo}` : `Editar — ${titulo}`}
                    campos={campos}
                    rutaBase={rutaBase}
                    idKey={idKey}
                    fila={editando === 'nuevo' ? null : editando}
                />
            )}
        </AppLayout>
    );
}
