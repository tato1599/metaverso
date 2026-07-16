import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import Button from '../../Components/Button';
import FormField, { Select, TextInput, Textarea } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const RUTA = '/admin/practicas';

function ParamControl({ param, value, onChange, ...rest }) {
    // rest lleva el id (y props aria) que FormField inyecta; hay que reenviarlo al
    // control real para que el <label htmlFor> lo enfoque.
    if (param.tipo === 'select') {
        return (
            <Select value={value ?? param.default} onChange={(e) => onChange(e.target.value)} {...rest}>
                {param.opciones.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </Select>
        );
    }
    if (param.tipo === 'checkbox') {
        return (
            <input
                type="checkbox"
                checked={Boolean(value)}
                onChange={(e) => onChange(e.target.checked)}
                className="size-4 rounded-sm accent-(--color-portal)"
                {...rest}
            />
        );
    }
    return (
        <TextInput
            type="number"
            min={param.min}
            max={param.max}
            value={value ?? param.default}
            onChange={(e) => onChange(e.target.value === '' ? '' : Number(e.target.value))}
            {...rest}
        />
    );
}

function configPorDefecto(tipoDef) {
    const c = {};
    for (const p of tipoDef?.params ?? []) c[p.name] = p.default;
    return c;
}

export default function Practicas({ registro, filas, materias }) {
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState(null);

    const form = useForm({
        id_materia: '',
        titulo: '',
        descripcion: '',
        objetivos: '',
        orden: 1,
        duracion_estimada: '',
        escena_referencia: registro[0]?.id ?? '',
        config: configPorDefecto(registro[0]),
    });

    const tipoDef = useMemo(
        () => registro.find((t) => t.id === form.data.escena_referencia),
        [registro, form.data.escena_referencia],
    );

    function abrirNuevo() {
        setEditando(null);
        // reset() ya restaura los valores iniciales del useForm (tipo + config del primero).
        form.reset();
        setAbierto(true);
    }

    function abrirEditar(fila) {
        setEditando(fila.id_practica);
        // Práctica heredada con un tipo fuera del registro: cae a un tipo válido con sus
        // defaults (no mezcla la config vieja, cuyas claves no pertenecen al nuevo esquema).
        const tipoConocido = registro.find((t) => t.id === fila.escena_referencia);
        const def = tipoConocido ?? registro[0];
        form.setData({
            id_materia: fila.id_materia,
            titulo: fila.titulo,
            descripcion: fila.descripcion ?? '',
            objetivos: fila.objetivos ?? '',
            orden: fila.orden,
            duracion_estimada: fila.duracion_estimada ?? '',
            escena_referencia: def.id,
            config: tipoConocido ? { ...configPorDefecto(def), ...(fila.config ?? {}) } : configPorDefecto(def),
        });
        setAbierto(true);
    }

    function cambiarTipo(id) {
        const def = registro.find((t) => t.id === id);
        form.setData((d) => ({ ...d, escena_referencia: id, config: configPorDefecto(def) }));
    }

    function setParam(name, value) {
        form.setData((d) => ({ ...d, config: { ...d.config, [name]: value } }));
    }

    function enviar(e) {
        e.preventDefault();
        const opts = { onSuccess: () => setAbierto(false), preserveScroll: true };
        if (editando) form.put(`${RUTA}/${editando}`, opts);
        else form.post(RUTA, opts);
    }

    function eliminar(fila) {
        if (confirm('¿Eliminar esta práctica?')) {
            router.delete(`${RUTA}/${fila.id_practica}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <AdminNav />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="font-display text-xl font-bold text-tinta">Prácticas</h1>
                <Button onClick={abrirNuevo}>Nueva práctica</Button>
            </div>

            <Table head={['Materia', 'Título', 'Tipo', 'Orden', '']}>
                {filas.map((fila) => (
                    <tr key={fila.id_practica}>
                        <td className="px-4 py-2.5 text-tinta">{fila.materia_nombre}</td>
                        <td className="px-4 py-2.5 text-tinta">{fila.titulo}</td>
                        <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{fila.escena_referencia}</td>
                        <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{fila.orden}</td>
                        <td className="px-4 py-2.5 text-right whitespace-nowrap">
                            <button
                                onClick={() => abrirEditar(fila)}
                                className="rounded-ctl px-2 py-1 text-xs font-medium text-tinta-2 hover:bg-hueco hover:text-tinta"
                            >
                                Editar
                            </button>
                            <button
                                onClick={() => eliminar(fila)}
                                className="rounded-ctl px-2 py-1 text-xs font-medium text-alerta hover:bg-alerta-tinte"
                            >
                                Eliminar
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>

            <Modal open={abierto} onClose={() => setAbierto(false)} title={editando ? 'Editar práctica' : 'Nueva práctica'}>
                <form onSubmit={enviar} className="space-y-4">
                    <FormField label="Materia" name="id_materia" error={form.errors.id_materia}>
                        <Select value={form.data.id_materia} onChange={(e) => form.setData('id_materia', e.target.value)} required>
                            <option value="">Elige…</option>
                            {materias.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label="Título" name="titulo" error={form.errors.titulo}>
                        <TextInput value={form.data.titulo} onChange={(e) => form.setData('titulo', e.target.value)} required />
                    </FormField>

                    <FormField label="Descripción" name="descripcion" error={form.errors.descripcion}>
                        <Textarea value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                    </FormField>

                    <FormField label="Objetivos" name="objetivos" error={form.errors.objetivos}>
                        <Textarea value={form.data.objetivos} onChange={(e) => form.setData('objetivos', e.target.value)} />
                    </FormField>

                    <FormField label="Orden" name="orden" error={form.errors.orden}>
                        <TextInput
                            type="number"
                            min={1}
                            value={form.data.orden}
                            onChange={(e) => form.setData('orden', Number(e.target.value))}
                            required
                        />
                    </FormField>

                    <FormField label="Duración estimada (min)" name="duracion_estimada" error={form.errors.duracion_estimada}>
                        <TextInput
                            type="number"
                            min={1}
                            value={form.data.duracion_estimada}
                            onChange={(e) => form.setData('duracion_estimada', e.target.value === '' ? '' : Number(e.target.value))}
                        />
                    </FormField>

                    <FormField label="Tipo de práctica" name="escena_referencia" error={form.errors.escena_referencia}>
                        <Select value={form.data.escena_referencia} onChange={(e) => cambiarTipo(e.target.value)} required>
                            {registro.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    {(tipoDef?.params ?? []).map((p) => (
                        <FormField key={p.name} label={p.label} name={`config.${p.name}`} error={form.errors[`config.${p.name}`]}>
                            <ParamControl param={p} value={form.data.config[p.name]} onChange={(v) => setParam(p.name, v)} />
                        </FormField>
                    ))}

                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="ghost" onClick={() => setAbierto(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Guardar
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
