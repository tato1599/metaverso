import Lamina from './Lamina';

/**
 * Una tarjeta en este sistema es una lámina de vidrio con su canto (ver DESIGN.md).
 * La cabecera se separa con una regla capilar, no con un cambio de fondo.
 */
export default function Card({ title, footer, depth = 0, className = '', children }) {
    return (
        <Lamina depth={depth} className={className}>
            {title && (
                <header className="border-b border-regla-suave px-5 py-3.5">
                    <h3 className="rotulo text-tinta-2">{title}</h3>
                </header>
            )}
            <div className="p-5">{children}</div>
            {footer && (
                <footer className="border-t border-regla-suave bg-hueco/45 px-5 py-3">{footer}</footer>
            )}
        </Lamina>
    );
}
