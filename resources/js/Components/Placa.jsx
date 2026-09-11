import { useEffect } from 'react';

/**
 * El observador de la lámpara. Una sola fuente de luz, fija a 42% del alto de la
 * ventana: al hacer scroll, cada lámina cruza el haz y se le enciende el canto.
 *
 * Lecturas primero y escrituras después, dentro de un rAF, para no provocar
 * layout thrashing con varias láminas en pantalla. Bajo prefers-reduced-motion
 * no se engancha nada y se limpian las variables: las láminas quedan en reposo.
 */
export function useLamparaRasante() {
    useEffect(() => {
        const raiz = document.documentElement;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
        const laminas = Array.from(document.querySelectorAll('[data-pane]'));
        let alto = window.innerHeight;
        let ancho = window.innerWidth;
        let pendiente = false;

        function pasada() {
            pendiente = false;
            const luzY = alto * 0.42;
            const tramo = Math.max(260, alto * 0.55);
            const lecturas = laminas.map((l) => l.getBoundingClientRect());

            lecturas.forEach((r, i) => {
                const d = (r.bottom - luzY) / tramo;
                let ig = Math.max(0, 1 - Math.abs(d));
                ig = ig * ig * (3 - 2 * ig); // suavizado
                const depth = parseFloat(laminas[i].dataset.depth) || 0;
                const paralaje = ((alto / 2 - (r.top + r.height / 2)) / alto) * depth * 24;

                // La lámpara es rasante: el reflejo cae en un punto distinto del
                // canto según dónde esté la lámina, en vertical y en horizontal.
                // Sin el término horizontal, dos láminas a la misma altura
                // reciben la luz idéntica y dejan de leerse como dos objetos.
                const cx = (r.left + r.width / 2) / ancho;
                const sx = Math.min(1, Math.max(0, 0.5 - d * 0.34 + (cx - 0.5) * 0.62));

                laminas[i].style.setProperty('--ig', ig.toFixed(3));
                laminas[i].style.setProperty('--sx', sx.toFixed(3));
                laminas[i].style.setProperty('--py', `${paralaje.toFixed(2)}px`);
            });
        }

        function agendar() {
            if (!pendiente) {
                pendiente = true;
                requestAnimationFrame(pasada);
            }
        }

        function alRedimensionar() {
            alto = window.innerHeight;
            ancho = window.innerWidth;
            agendar();
        }

        function encender() {
            raiz.classList.add('motion');
            window.addEventListener('scroll', agendar, { passive: true });
            window.addEventListener('resize', alRedimensionar, { passive: true });
            agendar();
        }

        function apagar() {
            raiz.classList.remove('motion');
            window.removeEventListener('scroll', agendar);
            window.removeEventListener('resize', alRedimensionar);
            laminas.forEach((l) => {
                l.style.removeProperty('--ig');
                l.style.removeProperty('--sx');
                l.style.removeProperty('--py');
            });
        }

        function alCambiarPreferencia() {
            reduce.matches ? apagar() : encender();
        }

        if (!reduce.matches) {
            encender();
        }
        reduce.addEventListener('change', alCambiarPreferencia);

        return () => {
            reduce.removeEventListener('change', alCambiarPreferencia);
            apagar();
        };
    }, []);
}

/** Cáusticas: luz de agua sobre la placa. Turbulencia SVG, cero peticiones de red. */
function Caustica({ id, baseFrequency, numOctaves, seed, alfa, desenfoque, color, className }) {
    return (
        <div className={`absolute -inset-[22%] ${className}`} aria-hidden="true">
            <svg className="block size-full" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
                <filter id={id} x="-8%" y="-8%" width="116%" height="116%" colorInterpolationFilters="sRGB">
                    <feTurbulence
                        type="fractalNoise"
                        baseFrequency={baseFrequency}
                        numOctaves={numOctaves}
                        seed={seed}
                        result="t"
                    />
                    <feColorMatrix
                        in="t"
                        type="matrix"
                        values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  1 0 0 0 0"
                        result="a"
                    />
                    <feComponentTransfer in="a" result="r">
                        <feFuncA type="table" tableValues={`0 ${alfa} 1 ${alfa} 0`} />
                    </feComponentTransfer>
                    <feGaussianBlur in="r" stdDeviation={desenfoque} result="s" />
                    <feFlood floodColor={color} result="f" />
                    <feComposite in="f" in2="s" operator="in" />
                </filter>
                <rect width="1200" height="800" filter={`url(#${id})`} />
            </svg>
        </div>
    );
}

/**
 * La placa de luz: el material del que está hecha la página.
 * Retícula grabada de 96px enmascarada, una sola lámpara rasante y —en las
 * pantallas de llegada— dos capas de cáusticas.
 *
 * `causticas={false}` en las pantallas de trabajo: la atmósfera es para llegar,
 * no para operar, y estos equipos son de laboratorio escolar, no de gama alta.
 */
export function Placa({ causticas = true }) {
    return (
        <div className="placa" aria-hidden="true">
            <div
                className="absolute -inset-px"
                style={{
                    backgroundImage:
                        'repeating-linear-gradient(90deg, var(--color-regla-capilar) 0 1px, transparent 1px 96px), repeating-linear-gradient(0deg, var(--color-regla-capilar) 0 1px, transparent 1px 96px)',
                    // El #000 de la máscara es canal alfa, no un color de la paleta.
                    maskImage: 'radial-gradient(128% 90% at 50% 32%, #000 10%, transparent 82%)',
                    WebkitMaskImage: 'radial-gradient(128% 90% at 50% 32%, #000 10%, transparent 82%)',
                }}
            />

            {causticas && (
                <>
                    <Caustica
                        id="caustica-a"
                        className="caustica caustica--a opacity-[0.42]"
                        baseFrequency="0.0040 0.0082"
                        numOctaves="4"
                        seed="17"
                        alfa="0.04"
                        desenfoque="1.5"
                        color="#FFFFFF"
                    />
                    <Caustica
                        id="caustica-b"
                        className="caustica caustica--b opacity-[0.26] blur-[9px]"
                        baseFrequency="0.0028 0.0061"
                        numOctaves="3"
                        seed="41"
                        alfa="0.06"
                        desenfoque="2.4"
                        color="#CFE6FA"
                    />
                </>
            )}

            {/* La lámpara: una sola fuente, rasante. */}
            <div
                className="absolute inset-0 opacity-90 mix-blend-soft-light"
                style={{
                    backgroundImage:
                        'linear-gradient(178deg, rgba(14,31,58,.10) 0%, rgba(14,31,58,.02) 15%, rgba(255,255,255,0) 25%, rgba(255,255,255,.85) 43%, rgba(255,255,255,0) 63%, rgba(14,31,58,.05) 90%, rgba(14,31,58,.10) 100%)',
                }}
            />
        </div>
    );
}

/** La franja institucional TecNM. Es la firma del sistema, no un adorno. */
export function Franja() {
    return (
        <div
            className="relative z-10 h-[3px] w-full"
            aria-hidden="true"
            style={{
                background: 'linear-gradient(90deg, var(--color-portal) 0 50%, var(--color-alerta) 50% 100%)',
            }}
        />
    );
}

export function Marca({ compacta = false, className = '' }) {
    return (
        <span className={`flex items-center gap-3 ${className}`}>
            <span className="flex flex-col gap-[2.5px]" aria-hidden="true">
                <span className={`h-[4px] rounded-canto bg-portal ${compacta ? 'w-[22px]' : 'w-[26px]'}`} />
                <span className={`h-[4px] rounded-canto bg-alerta ${compacta ? 'w-[22px]' : 'w-[26px]'}`} />
                <span className={`h-[4px] rounded-canto bg-borde-fuerte ${compacta ? 'w-[13px]' : 'w-[15px]'}`} />
            </span>
            <span
                className={`grabado font-display font-extrabold tracking-[-0.03em] text-tinta ${
                    compacta ? 'text-[15px]' : 'text-[17px]'
                }`}
            >
                Metaverso
            </span>
        </span>
    );
}
