import { router, usePage } from '@inertiajs/react';

function Wordmark() {
    return (
        <span className="flex items-baseline gap-1.5">
            <span className="font-display text-[15px] font-bold tracking-tight text-tinta">metaverso</span>
            <span className="font-mono text-[10px] tracking-widest text-tinta-3 uppercase">prácticas</span>
        </span>
    );
}

/* ponytail: nav con <a> plano — el panel sigue en Blade hasta F5; Link de Inertia llegará entonces */
function navPorRol(rol) {
    if (rol === 'Alumno') {
        return [{ href: '/mi/calendario', label: 'Calendario' }];
    }
    if (['Maestro', 'Coordinador', 'Admin'].includes(rol)) {
        return [{ href: '/panel', label: 'Panel' }];
    }
    return [];
}

export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const links = navPorRol(user?.rol);
    const path = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="min-h-dvh">
            <header className="border-b border-borde bg-superficie">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-6 px-4">
                    <div className="flex items-center gap-8">
                        <Wordmark />
                        <nav className="flex items-center gap-1">
                            {links.map((l) => (
                                <a
                                    key={l.href}
                                    href={l.href}
                                    className={`rounded-ctl px-3 py-1.5 text-[13px] font-medium transition-colors ${
                                        path.startsWith(l.href)
                                            ? 'bg-portal-tinte text-portal'
                                            : 'text-tinta-2 hover:bg-hueco hover:text-tinta'
                                    }`}
                                >
                                    {l.label}
                                </a>
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
                                className="rounded-ctl px-3 py-1.5 text-[13px] font-medium text-tinta-2 transition-colors hover:bg-hueco hover:text-tinta"
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
