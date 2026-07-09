import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminNav from '../../Components/AdminNav';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import EmptyState from '../../Components/EmptyState';
import FormField, { Select, TextInput } from '../../Components/FormField';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

function CampoPassword({ value, onChange, requerido, placeholder }) {
    return (
        <TextInput
            type="password"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            required={requerido}
            placeholder={placeholder}
            autoComplete="new-password"
        />
    );
}

function ModalCrear({ cerrar, roles, carreras }) {
    const { data, setData, post, processing, errors } = useForm({
        correo: '',
        nombre: '',
        apellidos: '',
        id_rol: '',
        password: '',
        matricula: '',
        id_carrera: '',
        semestre_actual: '',
        generacion: String(new Date().getFullYear()),
        numero_empleado: '',
    });

    const rolElegido = roles.find((r) => String(r.id_rol) === String(data.id_rol));
    const esAlumno = rolElegido?.nombre === 'Alumno';
    const esMaestro = rolElegido?.nombre === 'Maestro';

    function submit(e) {
        e.preventDefault();
        post('/admin/usuarios', { onSuccess: cerrar, preserveScroll: true });
    }

    return (
        <Modal open onClose={cerrar} title="Agregar — Usuarios">
            <form onSubmit={submit} className="space-y-4">
                <FormField label="Correo" name="correo" error={errors.correo}>
                    <TextInput type="email" required value={data.correo} onChange={(e) => setData('correo', e.target.value)} />
                </FormField>
                <FormField label="Nombre" name="nombre" error={errors.nombre}>
                    <TextInput required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                </FormField>
                <FormField label="Apellidos" name="apellidos" error={errors.apellidos}>
                    <TextInput required value={data.apellidos} onChange={(e) => setData('apellidos', e.target.value)} />
                </FormField>
                <FormField label="Rol" name="id_rol" error={errors.id_rol}>
                    <Select required value={data.id_rol} onChange={(e) => setData('id_rol', e.target.value)}>
                        <option value="">Elige…</option>
                        {roles.map((r) => (
                            <option key={r.id_rol} value={r.id_rol}>
                                {r.nombre}
                            </option>
                        ))}
                    </Select>
                </FormField>
                <FormField label="Contraseña" name="password" error={errors.password}>
                    <CampoPassword requerido value={data.password} onChange={(v) => setData('password', v)} />
                </FormField>

                {esAlumno && (
                    <>
                        <FormField label="Matrícula" name="matricula" error={errors.matricula}>
                            <TextInput required value={data.matricula} onChange={(e) => setData('matricula', e.target.value)} />
                        </FormField>
                        <FormField label="Carrera" name="id_carrera" error={errors.id_carrera}>
                            <Select required value={data.id_carrera} onChange={(e) => setData('id_carrera', e.target.value)}>
                                <option value="">Elige…</option>
                                {carreras.map((c) => (
                                    <option key={c.id_carrera} value={c.id_carrera}>
                                        {c.nombre}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                        <FormField label="Semestre actual" name="semestre_actual" error={errors.semestre_actual}>
                            <TextInput
                                type="number"
                                min={1}
                                max={15}
                                required
                                value={data.semestre_actual}
                                onChange={(e) => setData('semestre_actual', e.target.value)}
                            />
                        </FormField>
                        <FormField label="Generación" name="generacion" error={errors.generacion}>
                            <TextInput required value={data.generacion} onChange={(e) => setData('generacion', e.target.value)} />
                        </FormField>
                    </>
                )}

                {esMaestro && (
                    <FormField label="Número de empleado" name="numero_empleado" error={errors.numero_empleado}>
                        <TextInput
                            required
                            value={data.numero_empleado}
                            onChange={(e) => setData('numero_empleado', e.target.value)}
                        />
                    </FormField>
                )}

                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Crear
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ModalEditar({ cerrar, usuario }) {
    const { data, setData, put, processing, errors } = useForm({
        correo: usuario.correo,
        nombre: usuario.nombre,
        apellidos: usuario.apellidos,
        activo: Boolean(usuario.activo),
        password: '',
    });

    function submit(e) {
        e.preventDefault();
        put(`/admin/usuarios/${usuario.id_usuario}`, { onSuccess: cerrar, preserveScroll: true });
    }

    return (
        <Modal open onClose={cerrar} title="Editar — Usuarios">
            <form onSubmit={submit} className="space-y-4">
                <FormField label="Correo" name="correo" error={errors.correo}>
                    <TextInput type="email" required value={data.correo} onChange={(e) => setData('correo', e.target.value)} />
                </FormField>
                <FormField label="Nombre" name="nombre" error={errors.nombre}>
                    <TextInput required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                </FormField>
                <FormField label="Apellidos" name="apellidos" error={errors.apellidos}>
                    <TextInput required value={data.apellidos} onChange={(e) => setData('apellidos', e.target.value)} />
                </FormField>
                <FormField label="Activo" name="activo" error={errors.activo}>
                    <input
                        id="activo"
                        type="checkbox"
                        checked={data.activo}
                        onChange={(e) => setData('activo', e.target.checked)}
                        className="size-4 rounded-sm accent-(--color-portal)"
                    />
                </FormField>
                <FormField label="Nueva contraseña (opcional)" name="password" error={errors.password}>
                    <CampoPassword
                        value={data.password}
                        onChange={(v) => setData('password', v)}
                        placeholder="Dejar en blanco para no cambiarla"
                    />
                </FormField>
                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Guardar cambios
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Usuarios({ titulo, filas, roles, carreras }) {
    const [busqueda, setBusqueda] = useState('');
    const [editando, setEditando] = useState(null); // null | 'nuevo' | fila

    const visibles = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        if (!q) return filas;
        return filas.filter((f) => JSON.stringify(f).toLowerCase().includes(q));
    }, [filas, busqueda]);

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

            {visibles.length === 0 ? (
                <EmptyState
                    title={busqueda ? 'Sin resultados' : 'Aún no hay usuarios'}
                    hint={busqueda ? 'Prueba con otra búsqueda.' : 'Crea el primer usuario con el botón Agregar.'}
                />
            ) : (
                <Table head={['Nombre', 'Correo', 'Rol', 'Activo', '']}>
                    {visibles.map((f) => (
                        <tr key={f.id_usuario}>
                            <td className="px-4 py-2.5 text-tinta">
                                {f.nombre} {f.apellidos}
                            </td>
                            <td className="px-4 py-2.5 font-mono text-xs text-tinta-2">{f.correo}</td>
                            <td className="px-4 py-2.5">
                                <Badge tone="muted">{f.rol?.nombre ?? '—'}</Badge>
                            </td>
                            <td className="px-4 py-2.5">
                                <Badge tone={f.activo ? 'ok' : 'danger'}>{f.activo ? 'activo' : 'inactivo'}</Badge>
                            </td>
                            <td className="px-4 py-2.5 text-right whitespace-nowrap">
                                <button
                                    onClick={() => setEditando(f)}
                                    className="rounded-ctl px-2 py-1 text-xs font-medium text-tinta-2 hover:bg-hueco hover:text-tinta"
                                >
                                    Editar
                                </button>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}

            {editando === 'nuevo' && <ModalCrear cerrar={() => setEditando(null)} roles={roles} carreras={carreras} />}
            {editando !== null && editando !== 'nuevo' && (
                <ModalEditar cerrar={() => setEditando(null)} usuario={editando} />
            )}
        </AppLayout>
    );
}
