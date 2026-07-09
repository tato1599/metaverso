import { Link, router, usePage } from '@inertiajs/react';

function Wordmark() {
    return (
        <span className="flex items-baseline gap-1.5">
            <span className="font-display text-[15px] font-bold tracking-tight text-tinta">metaverso</span>
            <span className="font-mono text-[10px] tracking-widest text-tinta-3 uppercase">prácticas</span>
        </span>
    );
}

function navPorRol(rol) {
    if (rol === 'Alumno') {
        return [{ href: '/mi/calendario', label: 'Calendario' }];
    }
    if (['Maestro', 'Coordinador', 'Admin'].includes(rol)) {
        const links = [
            { href: '/panel', label: 'Panel' },
            { href: '/panel/agenda', label: 'Agenda' },
        ];
        if (['Coordinador', 'Admin'].includes(rol)) {
            links.push({ href: '/admin/carreras', label: 'Administración', raiz: '/admin' });
        }
        return links;
    }
    return [];
}

export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const links = navPorRol(user?.rol);
    const path = usePage().url.split('?')[0];

    return (
        <div className="min-h-dvh">
            <header className="border-b border-borde bg-superficie">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-6 px-4">
                    <div className="flex items-center gap-8">
                        <Wordmark />
                        <nav className="flex items-center gap-1">
                            {links.map((l) => (
                                <Link
                                    key={l.href}
                                    href={l.href}
                                    className={`rounded-ctl px-3 py-1.5 text-[13px] font-medium transition-colors outline-offset-2 focus-visible:outline-2 focus-visible:outline-portal ${
                                        (l.href === '/panel' ? path === '/panel' : path.startsWith(l.raiz ?? l.href))
                                            ? 'bg-portal-tinte text-portal'
                                            : 'text-tinta-2 hover:bg-hueco hover:text-tinta'
                                    }`}
                                >
                                    {l.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    {user && (
                        <div className="flex items-center gap-3">
                            <span className="hidden text-[13px] text-tinta-2 sm:block">
                                {user.nombre} {user.apellidos}
                            </span>
                            <button
                                onClick={() => router.post('/logout')}
                                className="rounded-ctl px-3 py-1.5 text-[13px] font-medium text-tinta-2 transition-colors outline-offset-2 hover:bg-hueco hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                            >
                                Salir
                            </button>
                        </div>
                    )}
                </div>
            </header>

            {(flash?.success || flash?.error) && (
                <div className="mx-auto max-w-6xl px-4 pt-4">
                    {flash.success && (
                        <p className="rounded-ctl bg-telemetria-tinte px-4 py-2.5 text-[13px] font-medium text-telemetria">
                            {flash.success}
                        </p>
                    )}
                    {flash.error && (
                        <p className="rounded-ctl bg-alerta-tinte px-4 py-2.5 text-[13px] font-medium text-alerta">
                            {flash.error}
                        </p>
                    )}
                </div>
            )}

            <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>
        </div>
    );
}
