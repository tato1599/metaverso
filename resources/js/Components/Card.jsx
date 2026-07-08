export default function Card({ title, footer, className = '', children }) {
    return (
        <section className={`rounded-carta bg-superficie ring-1 ring-borde ${className}`}>
            {title && (
                <header className="border-b border-borde px-4 py-3">
                    <h3 className="text-[15px] font-semibold text-tinta">{title}</h3>
                </header>
            )}
            <div className="p-4">{children}</div>
            {footer && <footer className="border-t border-borde px-4 py-3">{footer}</footer>}
        </section>
    );
}
