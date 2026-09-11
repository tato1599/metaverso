import { Link, usePage } from '@inertiajs/react';

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

/**
 * Navegación de segundo nivel. Misma gramática que la principal —regla de 2px
 * bajo la línea base, no píldora de fondo— y con Link de Inertia: antes eran
 * anclas planas, así que cambiar de sección recargaba la aplicación entera.
 */
export default function AdminNav() {
    const { props, url } = usePage();
    const path = url.split('?')[0];
    const rol = props.auth?.user?.rol;
    const secciones = SECCIONES.filter((s) => !s.soloAdmin || rol === 'Admin');

    return (
        <nav className="mb-7 flex flex-wrap gap-x-6 border-b border-borde">
            {secciones.map((s) => {
                const activo = path.startsWith(s.href);

                return (
                    <Link
                        key={s.href}
                        href={s.href}
                        aria-current={activo ? 'page' : undefined}
                        className={`-mb-px border-b-2 px-1 py-2.5 text-[13px] font-semibold transition-colors outline-offset-2 focus-visible:outline-2 focus-visible:outline-portal ${
                            activo
                                ? 'border-portal text-tinta'
                                : 'border-transparent text-tinta-2 hover:border-borde-fuerte hover:text-tinta'
                        }`}
                    >
                        {s.label}
                    </Link>
                );
            })}
        </nav>
    );
}
