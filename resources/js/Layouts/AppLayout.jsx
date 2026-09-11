import { Link, router, usePage } from '@inertiajs/react';
import { Franja, Marca, Placa, useLamparaRasante } from '../Components/Placa';

function navPorRol(rol) {
    if (rol === 'Alumno') {
        return [
            { href: '/mi/calendario', label: 'Calendario' },
            { href: '/mi/cursos', label: 'Mis cursos' },
        ];
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

/**
 * El elemento en curso se marca con una regla de 2px bajo la línea base, no con
 * una píldora de fondo: en este sistema la estructura la da la línea.
 */
function EnlaceNav({ href, label, activo }) {
    return (
        <Link
            href={href}
            aria-current={activo ? 'page' : undefined}
            className={`relative -mb-px border-b-2 px-1 py-3 text-[13px] font-semibold transition-colors outline-offset-2 focus-visible:outline-2 focus-visible:outline-portal ${
                activo
                    ? 'border-portal text-tinta'
                    : 'border-transparent text-tinta-2 hover:border-borde-fuerte hover:text-tinta'
            }`}
        >
            {label}
        </Link>
    );
}

/** Los avisos son lecturas del instrumento: punto de estado + texto, sin pastilla. */
function Aviso({ tono, children }) {
    const color = tono === 'ok' ? 'text-telemetria' : 'text-alerta';
    const punto = tono === 'ok' ? 'bg-telemetria' : 'bg-alerta';

    return (
        <p className={`flex items-start gap-2.5 px-5 py-3 text-[13px] font-medium ${color}`}>
            <span className={`mt-[6px] size-1.5 shrink-0 rounded-full ${punto}`} aria-hidden="true" />
            {children}
        </p>
    );
}

export default function AppLayout({ children }) {
    useLamparaRasante();

    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const links = navPorRol(user?.rol);
    const path = usePage().url.split('?')[0];
    const hayAviso = flash?.success || flash?.error;

    return (
        <div className="relative min-h-dvh overflow-x-clip">
            {/* Sin cáusticas: la atmósfera es para llegar, no para operar. */}
            <Placa causticas={false} />
            <Franja />

            {/* Columna flex para que el pie se apoye abajo aunque la pantalla traiga poco dato. */}
            <div className="relative z-10 flex min-h-[calc(100dvh-3px)] flex-col">
                {/* El faceplate del aparato. */}
                <header className="border-b border-borde bg-superficie/70 backdrop-blur-md">
                    {/*
                     * En móvil la navegación baja a su propia fila desplazable y la marca
                     * comparte línea con la salida; en sm+ es una sola fila. El orden lo
                     * resuelve `order`, no un segundo encabezado duplicado.
                     */}
                    <div className="mx-auto flex max-w-[1288px] flex-wrap items-center gap-x-8 px-5 sm:px-8 lg:px-12">
                        <Link
                            href={user?.rol === 'Alumno' ? '/mi/calendario' : '/panel'}
                            className="order-1 rounded-ctl py-3 outline-offset-4 focus-visible:outline-2 focus-visible:outline-portal"
                        >
                            <Marca compacta />
                        </Link>

                        {/*
                         * Quién eres es información; salir es un control. Antes ambos
                         * eran rótulo mono en el mismo gris y nadie podía saber cuál se
                         * pulsaba. La regla que los separa es la del sistema: lo que se
                         * acciona vive dentro de un contorno y va en el azul de acción;
                         * lo que solo se lee, no.
                         */}
                        {user && (
                            <div className="order-2 ml-auto flex items-center gap-4 py-2 sm:order-3">
                                <span className="hidden items-baseline gap-2 sm:flex">
                                    <span className="text-[13px] font-medium text-tinta">
                                        {user.nombre} {user.apellidos}
                                    </span>
                                    <span className="rotulo text-tinta-3">{user.rol}</span>
                                </span>

                                <button
                                    onClick={() => router.post('/logout')}
                                    className="rotulo rounded-ctl px-2.5 py-2 text-portal shadow-[0_0_0_1px_var(--color-borde)_inset] outline-offset-2 transition-[background-color,box-shadow,color] duration-150 hover:bg-vidrio-2 hover:text-portal-fuerte hover:shadow-[0_0_0_1px_var(--color-borde-fuerte)_inset] focus-visible:outline-2 focus-visible:outline-portal"
                                >
                                    Salir
                                </button>
                            </div>
                        )}

                        <nav className="order-3 -mb-px flex w-full items-center gap-6 overflow-x-auto sm:order-2 sm:w-auto [&::-webkit-scrollbar]:hidden">
                            {links.map((l) => (
                                <EnlaceNav
                                    key={l.href}
                                    href={l.href}
                                    label={l.label}
                                    activo={
                                        l.href === '/panel'
                                            ? path === '/panel'
                                            : path.startsWith(l.raiz ?? l.href)
                                    }
                                />
                            ))}
                        </nav>
                    </div>
                </header>

                {hayAviso && (
                    <div
                        data-entra
                        aria-live="polite"
                        className="mx-auto mt-5 w-full max-w-[1288px] px-5 sm:px-8 lg:px-12"
                    >
                        <div className="lamina">
                            <div className="lamina-cara">
                                {flash.success && <Aviso tono="ok">{flash.success}</Aviso>}
                                {flash.error && <Aviso tono="error">{flash.error}</Aviso>}
                            </div>
                            <span className="canto" aria-hidden="true" />
                        </div>
                    </div>
                )}

                <main className="mx-auto w-full max-w-[1288px] flex-1 px-5 py-9 sm:px-8 lg:px-12">
                    {children}
                </main>

                <footer className="mx-auto w-full max-w-[1288px] px-5 pt-4 pb-8 sm:px-8 lg:px-12">
                    <div className="filete" aria-hidden="true">
                        {Array.from({ length: 25 }, (_, i) => (
                            <i key={i} />
                        ))}
                    </div>
                    <p className="rotulo grabado mt-4 text-tinta-3">
                        Tecnológico Nacional de México · Campus de prácticas
                    </p>
                </footer>
            </div>
        </div>
    );
}
