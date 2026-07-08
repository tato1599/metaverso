import { useForm } from '@inertiajs/react';
import Button from './Button';
import FormField, { TextInput } from './FormField';
import Modal from './Modal';

function aInputLocal(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function selectClases() {
    return 'h-9 w-full rounded-ctl bg-hueco px-3 text-sm text-tinta ring-1 ring-borde ring-inset focus:bg-superficie focus:ring-2 focus:ring-portal focus:outline-none';
}

/**
 * Crear (sin `evento`) o reprogramar (`evento` presente: solo fechas/espacio/cupo,
 * espejo de la whitelist del PUT).
 */
export default function EventoFormModal({ open, onClose, grupos, espacios, cupoDefault, evento }) {
    const editando = Boolean(evento);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        id_grupo: '',
        id_practica: '',
        id_espacio: evento?.id_espacio ?? '',
        fecha_hora_inicio: aInputLocal(evento?.inicio),
        fecha_hora_fin: aInputLocal(evento?.fin),
        cupo_maximo: evento?.cupo_maximo ?? cupoDefault,
    });

    const grupoActivo = grupos?.find((g) => String(g.id_grupo) === String(data.id_grupo));
    const espacioActivo = espacios?.find((e) => String(e.id_espacio) === String(data.id_espacio));

    function alCambiarEspacio(idEspacio) {
        setData((prev) => {
            const cap = espacios?.find((e) => String(e.id_espacio) === String(idEspacio))?.capacidad;
            const tope = cap ? Math.min(cupoDefault, cap) : cupoDefault;
            return { ...prev, id_espacio: idEspacio, cupo_maximo: editando ? prev.cupo_maximo : tope };
        });
    }

    function cerrar() {
        clearErrors();
        if (!editando) reset();
        onClose();
    }

    function submit(e) {
        e.preventDefault();
        const opts = { onSuccess: cerrar };
        if (editando) {
            put(`/panel/eventos/${evento.id_evento}`, opts);
        } else {
            post('/panel/eventos', opts);
        }
    }

    return (
        <Modal open={open} onClose={cerrar} title={editando ? 'Reprogramar práctica' : 'Agendar práctica'}>
            <form onSubmit={submit} className="space-y-4">
                {!editando && (
                    <>
                        <FormField label="Grupo" name="id_grupo" error={errors.id_grupo}>
                            <select
                                className={selectClases()}
                                value={data.id_grupo}
                                onChange={(e) => setData((prev) => ({ ...prev, id_grupo: e.target.value, id_practica: '' }))}
                                required
                            >
                                <option value="">Elige un grupo…</option>
                                {grupos.map((g) => (
                                    <option key={g.id_grupo} value={g.id_grupo}>
                                        {g.clave} — {g.materia}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField label="Práctica" name="id_practica" error={errors.id_practica}>
                            <select
                                className={selectClases()}
                                value={data.id_practica}
                                onChange={(e) => setData('id_practica', e.target.value)}
                                disabled={!grupoActivo}
                                required
                            >
                                <option value="">{grupoActivo ? 'Elige una práctica…' : 'Primero elige un grupo'}</option>
                                {grupoActivo?.practicas.map((p) => (
                                    <option key={p.id_practica} value={p.id_practica}>
                                        {p.titulo}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </>
                )}

                <div className="grid grid-cols-2 gap-3">
                    <FormField label="Inicio" name="fecha_hora_inicio" error={errors.fecha_hora_inicio}>
                        <TextInput
                            type="datetime-local"
                            value={data.fecha_hora_inicio}
                            onChange={(e) => setData('fecha_hora_inicio', e.target.value)}
                            required
                        />
                    </FormField>
                    <FormField label="Fin" name="fecha_hora_fin" error={errors.fecha_hora_fin}>
                        <TextInput
                            type="datetime-local"
                            value={data.fecha_hora_fin}
                            onChange={(e) => setData('fecha_hora_fin', e.target.value)}
                            required
                        />
                    </FormField>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <FormField label="Espacio (opcional)" name="id_espacio" error={errors.id_espacio}>
                        <select
                            className={selectClases()}
                            value={data.id_espacio ?? ''}
                            onChange={(e) => alCambiarEspacio(e.target.value)}
                        >
                            <option value="">Sin espacio</option>
                            {espacios.map((e) => (
                                <option key={e.id_espacio} value={e.id_espacio}>
                                    {e.nombre}
                                    {e.capacidad ? ` (cap. ${e.capacidad})` : ''}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField label="Cupo del slot" name="cupo_maximo" error={errors.cupo_maximo}>
                        <TextInput
                            type="number"
                            min="1"
                            max={espacioActivo?.capacidad ?? 32767}
                            className="font-mono tabular-nums"
                            value={data.cupo_maximo}
                            onChange={(e) => setData('cupo_maximo', e.target.value)}
                            required
                        />
                    </FormField>
                </div>

                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {editando ? 'Guardar cambios' : 'Agendar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
