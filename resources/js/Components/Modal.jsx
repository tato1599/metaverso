import { useEffect, useRef } from 'react';

export default function Modal({ open, onClose, title, children }) {
    const ref = useRef(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            className="m-auto w-full max-w-md rounded-panel bg-superficie p-0 shadow-xl ring-1 ring-borde backdrop:bg-tinta/35 open:motion-safe:animate-[modal-in_0.2s_cubic-bezier(0.23,1,0.32,1)]"
        >
            <div className="border-b border-borde px-5 py-3.5">
                <h2 className="font-display text-base font-semibold text-tinta">{title}</h2>
            </div>
            <div className="p-5">{children}</div>
        </dialog>
    );
}
