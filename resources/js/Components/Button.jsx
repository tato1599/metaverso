/**
 * Los controles del instrumento. Esquina casi recta, altura 42px —la misma que
 * el campo, para que columna de formulario y botón compartan módulo.
 *
 * `aria-busy` no es `disabled`: un botón enviando conserva su color pleno y solo
 * cambia la etiqueta. Atenuar el primario justo en el instante en que el
 * instrumento responde lo deja en 2.42:1 y apaga la única respuesta de la pantalla.
 */
const variantes = {
    primary: 'bg-portal text-white hover:bg-portal-fuerte focus-visible:outline-portal',
    secondary:
        'bg-vidrio-2 text-tinta shadow-[0_0_0_1px_var(--color-borde)_inset] hover:bg-white/70 hover:shadow-[0_0_0_1px_var(--color-borde-fuerte)_inset] focus-visible:outline-portal',
    danger: 'bg-alerta text-white hover:opacity-90 focus-visible:outline-alerta',
    ghost: 'text-tinta-2 hover:bg-vidrio-2 hover:text-tinta focus-visible:outline-portal',
};

export default function Button({ variant = 'primary', className = '', children, ...rest }) {
    return (
        <button
            className={`inline-flex h-[42px] items-center justify-center gap-2 rounded-ctl px-4 text-[15px] font-semibold transition-[background-color,box-shadow,transform] duration-150 outline-offset-2 focus-visible:outline-2 disabled:pointer-events-none disabled:opacity-45 aria-busy:cursor-progress aria-busy:opacity-100 motion-safe:active:translate-y-px ${variantes[variant]} ${className}`}
            {...rest}
        >
            {children}
        </button>
    );
}
