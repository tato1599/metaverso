const SECCIONES = [
    { href: '/admin/carreras', label: 'Carreras' },
    { href: '/admin/materias', label: 'Materias' },
    { href: '/admin/practicas', label: 'Prácticas' },
    { href: '/admin/ciclos', label: 'Ciclos' },
    { href: '/admin/espacios', label: 'Espacios' },
    { href: '/admin/grupos', label: 'Grupos' },
    { href: '/admin/usuarios', label: 'Usuarios' },
];

export default function AdminNav() {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <nav className="mb-6 flex flex-wrap gap-1 border-b border-borde pb-3">
            {SECCIONES.map((s) => (
                <a
                    key={s.href}
                    href={s.href}
                    className={`rounded-ctl px-3 py-1.5 text-[13px] font-medium transition-colors outline-offset-2 focus-visible:outline-2 focus-visible:outline-portal ${
                        path.startsWith(s.href)
                            ? 'bg-portal-tinte text-portal'
                            : 'text-tinta-2 hover:bg-hueco hover:text-tinta'
                    }`}
                >
                    {s.label}
                </a>
            ))}
        </nav>
    );
}
