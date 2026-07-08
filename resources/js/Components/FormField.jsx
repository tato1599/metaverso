export default function FormField({ label, name, error, children }) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={name} className="block text-[13px] font-medium text-tinta-2">
                {label}
            </label>
            {children}
            {error && <p className="text-xs text-alerta">{error}</p>}
        </div>
    );
}

export function TextInput({ className = '', ...rest }) {
    return (
        <input
            className={`h-9 w-full rounded-ctl bg-hueco px-3 text-sm text-tinta ring-1 ring-borde ring-inset placeholder:text-tinta-3 focus:bg-superficie focus:ring-2 focus:ring-portal focus:outline-none ${className}`}
            {...rest}
        />
    );
}
