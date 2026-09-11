import { cloneElement, isValidElement } from 'react';

/**
 * Un campo del instrumento: rótulo mono arriba, control de vidrio tenue, y el
 * error como regla en `alerta` más su mensaje. El `aria-invalid` lo pone el
 * propio FormField clonando el control, para que ninguna pantalla se olvide.
 */
export default function FormField({ label, name, error, pista, children }) {
    const errorId = `${name}-error`;
    const pistaId = `${name}-pista`;
    const descritoPor = [pista && pistaId, error && errorId].filter(Boolean).join(' ');

    const control = isValidElement(children)
        ? cloneElement(children, {
              id: children.props.id ?? name,
              ...(descritoPor && { 'aria-describedby': descritoPor }),
              ...(error && { 'aria-invalid': true }),
          })
        : children;

    return (
        <div>
            <label htmlFor={name} className="rotulo block text-tinta-2">
                {label}
            </label>
            <div className="mt-2">{control}</div>
            {pista && !error && (
                <p id={pistaId} className="mt-1.5 text-[13px] text-tinta-3">
                    {pista}
                </p>
            )}
            {error && (
                <p id={errorId} className="mt-1.5 flex items-start gap-2 text-[13px] font-medium text-alerta">
                    <span className="mt-[6px] size-1.5 shrink-0 rounded-full bg-alerta" aria-hidden="true" />
                    {error}
                </p>
            )}
        </div>
    );
}

const control =
    'h-[42px] w-full rounded-ctl bg-vidrio-2 px-3 text-[15px] text-tinta shadow-[0_0_0_1px_var(--color-borde)_inset] transition-[background-color,box-shadow] duration-150 placeholder:text-tinta-3 aria-invalid:shadow-[0_0_0_1.5px_var(--color-alerta)_inset] focus:bg-white/80 focus:shadow-[0_0_0_2px_var(--color-portal)_inset] focus:outline-none';

export function TextInput({ className = '', ...rest }) {
    return <input className={`${control} ${className}`} {...rest} />;
}

export function Select({ className = '', children, ...rest }) {
    return (
        <select className={`${control} ${className}`} {...rest}>
            {children}
        </select>
    );
}

export function Textarea({ className = '', ...rest }) {
    return <textarea rows={3} className={`${control} h-auto py-2.5 ${className}`} {...rest} />;
}
