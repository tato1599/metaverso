import { usePage } from '@inertiajs/react';

const SECCIONES = [
    { href: '/admin/carreras', label: 'Carreras' },
    { href: '/admin/materias', label: 'Materias' },
    // Solo Admin enlaza prácticas con su juego de Godot.
    { href: '/admin/practicas', label: 'Prácticas', soloAdmin: true },
    { href: '/admin/ciclos', label: 'Ciclos' },
    { href: '/admin/espacios', label: 'Espacios' },
    { href: '/admin/grupos', label: 'Grupos' },
    { href: '/admin/usuarios', label: 'Usuarios' },
];

export default function AdminNav() {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const rol = usePage().props.auth?.user?.rol;
    const secciones = SECCIONES.filter((s) => !s.soloAdmin || rol === 'Admin');

    return (
        <nav className="mb-6 flex flex-wrap gap-1 border-b border-borde pb-3">
            {secciones.map((s) => (
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
