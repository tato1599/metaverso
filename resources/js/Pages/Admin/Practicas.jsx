import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import Button from '../../Components/Button';
import FormField, { Select, TextInput, Textarea } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

const RUTA = '/admin/practicas';

export default function Practicas({ juegos, filas, materias }) {
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState(null);

    const form = useForm({
        id_materia: '',
        titulo: '',
        descripcion: '',
        objetivos: '',
        orden: 1,
        duracion_estimada: '',
        escena_referencia: juegos[0]?.id ?? '',
    });

    function abrirNuevo() {
        setEditando(null);
        form.reset();
        setAbierto(true);
    }

    function abrirEditar(fila) {
        setEditando(fila.id_practica);
        // Si la práctica apunta a un juego que ya no está en el catálogo, cae al primero.
        const juegoValido = juegos.some((j) => j.id === fila.escena_referencia)
            ? fila.escena_referencia
            : (juegos[0]?.id ?? '');
        form.setData({
            id_materia: fila.id_materia,
            titulo: fila.titulo,
            descripcion: fila.descripcion ?? '',
            objetivos: fila.objetivos ?? '',
            orden: fila.orden,
            duracion_estimada: fila.duracion_estimada ?? '',
            escena_referencia: juegoValido,
        });
        setAbierto(true);
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

            <Table head={['Materia', 'Título', 'Juego', 'Orden', '']}>
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

                    <FormField label="Juego / escena de Godot" name="escena_referencia" error={form.errors.escena_referencia}>
                        <Select value={form.data.escena_referencia} onChange={(e) => form.setData('escena_referencia', e.target.value)} required>
                            {juegos.map((j) => (
                                <option key={j.id} value={j.id}>
                                    {j.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>

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
