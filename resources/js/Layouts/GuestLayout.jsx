import { Link } from '@inertiajs/react';
import { Franja, Marca, Placa, useLamparaRasante } from '../Components/Placa';

export { Marca };

export default function GuestLayout({ children, pie }) {
    useLamparaRasante();

    return (
        <div className="relative min-h-dvh overflow-x-clip">
            <Placa />
            <Franja />

            {/*
             * El alto descuenta la franja y el recorrido de la entrada: así el
             * translateY del último elemento aterriza en pista reservada y no
             * saca una barra de scroll en una página que cabe entera.
             */}
            <div className="relative z-10 mx-auto flex min-h-[calc(100dvh-3px-var(--entra-viaje))] w-full max-w-[1288px] flex-col px-5 sm:px-8 lg:px-12">
                <header
                    data-entra
                    className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-5"
                >
                    <Link
                        href="/"
                        className="rounded-ctl outline-offset-4 focus-visible:outline-2 focus-visible:outline-portal"
                    >
                        <Marca />
                    </Link>
                    <p className="rotulo grabado text-tinta-3">Campus de prácticas · TecNM</p>
                </header>

                <main className="flex flex-1 flex-col justify-center py-6">{children}</main>

                {/* El cierre: regla con ticks, como la escala de un aparato. */}
                <footer data-entra style={{ '--retardo': '620ms' }} className="pt-8 pb-5">
                    <div className="filete" aria-hidden="true">
                        {Array.from({ length: 25 }, (_, i) => (
                            <i key={i} />
                        ))}
                    </div>
                    <div className="mt-4 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                        <p className="rotulo grabado text-tinta-3">Tecnológico Nacional de México</p>
                        {pie}
                    </div>
                </footer>
            </div>
        </div>
    );
}
