import { useEffect, useRef } from 'react';

/**
 * El diálogo: una lámina que se levanta de la placa. Se reserva a lo que sí
 * interrumpe —crear o editar un registro— y no a tareas que merecen su propia
 * URL; el selector de horarios del alumno salió de aquí a `/mi/eventos/{id}`.
 *
 * Usa <dialog> nativo: el foco atrapado, Escape y el backdrop son del navegador,
 * no reimplementados.
 *
 * Va sin canto a propósito: el canto es el filo de una lámina apoyada en la
 * placa, y un diálogo no se apoya en nada — flota por encima de todo.
 */
export default function Modal({ open, onClose, title, children }) {
    const ref = useRef(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) {
            return;
        }
        if (open && !dialog.open) {
            dialog.showModal();
        }
        if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            className="lamina-cara m-auto w-full max-w-lg p-0 backdrop:bg-tinta/40 backdrop:backdrop-blur-[2px] open:motion-safe:animate-[modal-in_0.22s_var(--ease-instrumento)]"
        >
            <div className="border-b border-regla-suave px-5 py-3.5">
                <h2 className="rotulo text-tinta-2">{title}</h2>
            </div>
            <div className="p-5">{children}</div>
        </dialog>
    );
}
