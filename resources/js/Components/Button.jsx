const variantes = {
    primary:
        'bg-portal text-white hover:bg-portal-fuerte focus-visible:outline-portal',
    secondary:
        'bg-superficie text-tinta ring-1 ring-borde-fuerte ring-inset hover:bg-hueco focus-visible:outline-portal',
    danger: 'bg-alerta text-white hover:opacity-90 focus-visible:outline-alerta',
    ghost: 'text-tinta-2 hover:bg-hueco hover:text-tinta focus-visible:outline-portal',
};

export default function Button({ variant = 'primary', className = '', children, ...rest }) {
    return (
        <button
            className={`inline-flex h-9 items-center justify-center gap-2 rounded-ctl px-4 text-sm font-medium transition-[background-color,transform] duration-100 outline-offset-2 focus-visible:outline-2 motion-safe:active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 ${variantes[variant]} ${className}`}
            {...rest}
        >
            {children}
        </button>
    );
}
