<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metaverso Escolar TecNM — Laboratorios virtuales</title>
    <meta name="description" content="El laboratorio del Tec, en un metaverso: los alumnos entran a un espacio virtual, practican y la calificación regresa sola a Moodle.">
    @vite(['resources/css/portada.css'])
    <style>
        :root { --dur: .7s; }

        /* Revelado al hacer scroll: parte oculto, sube y aparece al entrar a viewport. */
        [data-reveal] {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity var(--dur) cubic-bezier(.16,.84,.44,1),
                        transform var(--dur) cubic-bezier(.16,.84,.44,1);
            transition-delay: var(--reveal-delay, 0ms);
            will-change: opacity, transform;
        }
        [data-reveal].is-visible { opacity: 1; transform: none; }

        /* Cromo del header: gana sombra y fondo al bajar. */
        #cabecera { transition: background-color .3s ease, box-shadow .3s ease, border-color .3s ease; }
        #cabecera.flotante { background: rgba(255,255,255,.82); backdrop-filter: blur(12px); box-shadow: 0 6px 24px -12px rgba(19,33,57,.28); }

        /* Franja superior con brillo que recorre. */
        @keyframes barrido { 0% { background-position: -140% 0; } 100% { background-position: 240% 0; } }
        .acento-brillo::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent 40%, rgba(255,255,255,.7) 50%, transparent 60%);
            background-size: 200% 100%;
            animation: barrido 5s linear infinite;
        }

        /* Flotacion suave para las tarjetas del campus virtual. */
        @keyframes flotar { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .flota { animation: flotar 6s ease-in-out infinite; }
        .flota-lento { animation: flotar 8s ease-in-out infinite; }

        /* Piso en perspectiva (rejilla del metaverso). */
        .rejilla {
            background-image:
                linear-gradient(rgba(107,166,232,.22) 1px, transparent 1px),
                linear-gradient(90deg, rgba(107,166,232,.22) 1px, transparent 1px);
            background-size: 34px 34px;
            transform: perspective(420px) rotateX(58deg) scale(1.6);
            transform-origin: bottom center;
        }

        /* "metaverso" con degradado animado en el titulo. */
        @keyframes cambia-tinte { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .palabra-clave {
            background: linear-gradient(90deg, var(--color-azultec), var(--color-azultec-bright), var(--color-azultec));
            background-size: 220% auto;
            -webkit-background-clip: text; background-clip: text; color: transparent;
            animation: cambia-tinte 5s ease infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            [data-reveal] { opacity: 1; transform: none; transition: none; }
            .acento-brillo::after, .flota, .flota-lento, .palabra-clave { animation: none; }
        }
    </style>
</head>
<body class="bg-white text-navy" style="font-family:var(--font-franklin)">

{{-- Franja institucional azul/rojo del TecNM --}}
<div class="acento-brillo relative h-1 w-full" style="background:linear-gradient(90deg,var(--color-azultec) 0 50%,var(--color-rojotec) 50% 100%)"></div>

{{-- ===== HEADER ===== --}}
<header id="cabecera" class="sticky top-0 z-50 border-b border-transparent">
    <div class="mx-auto flex max-w-[1400px] items-center justify-between px-5 py-4 sm:px-10">
        <a href="#top" class="flex items-center gap-3.5">
            <span class="flex flex-col gap-[3px]" aria-hidden="true">
                <span class="h-[5px] w-[30px] rounded-sm bg-azultec"></span>
                <span class="h-[5px] w-[30px] rounded-sm bg-rojotec"></span>
                <span class="h-[5px] w-[18px] rounded-sm bg-slate-300"></span>
            </span>
            <span class="font-semibold tracking-tight text-navy" style="font-family:var(--font-jakarta);font-weight:800;font-size:20px">
                Metaverso Escolar
            </span>
        </a>
        <a href="{{ route('panel.dashboard') }}"
           class="inline-flex items-center rounded-[10px] bg-azultec px-5 py-2.5 text-[15px] font-semibold text-white transition-colors hover:bg-azultec-dark">
            Entrar al panel
        </a>
    </div>
</header>

<main id="top">

{{-- ===== HERO ===== --}}
<section class="relative overflow-hidden" style="background:linear-gradient(180deg,#fff 0%,var(--color-cielo) 100%)">
    <div class="mx-auto grid max-w-[1400px] items-center gap-14 px-5 pb-12 pt-16 sm:px-10 lg:grid-cols-[1fr_1.1fr] lg:pt-24">
        <div>
            <p data-reveal class="mb-6 inline-flex items-center gap-2 rounded-full border border-azultec/20 bg-azultec/5 px-3.5 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-azultec">
                <span class="size-1.5 rounded-full bg-rojotec"></span> Laboratorios virtuales · ITCJ
            </p>
            <h1 data-reveal style="--reveal-delay:80ms;font-family:var(--font-jakarta);font-weight:800;letter-spacing:-.025em"
                class="text-[13vw] leading-[1.03] text-[#101f38] sm:text-6xl lg:text-[64px]">
                El laboratorio del Tec,<br>en un <span class="palabra-clave">metaverso</span>.
            </h1>
            <p data-reveal style="--reveal-delay:160ms" class="mt-6 max-w-lg text-lg leading-relaxed text-slate-500 sm:text-xl">
                Un espacio virtual donde los alumnos entran, practican y aprenden.
                Vívelo, muéstralo y adminístralo desde un solo lugar.
            </p>
            <div data-reveal style="--reveal-delay:240ms" class="mt-9 flex flex-wrap gap-3.5">
                <a href="{{ route('panel.dashboard') }}"
                   class="inline-flex items-center justify-center rounded-[11px] bg-azultec px-7 py-4 text-base font-semibold text-white shadow-[0_10px_26px_rgba(26,54,110,.24)] transition-all hover:-translate-y-0.5 hover:bg-azultec-dark hover:shadow-[0_16px_34px_rgba(26,54,110,.30)]">
                    Entrar al panel
                </a>
                <a href="#metaverso"
                   class="inline-flex items-center justify-center rounded-[11px] border border-slate-300 bg-white px-6 py-4 text-base font-semibold text-slate-700 transition-colors hover:border-azultec hover:text-azultec">
                    Conoce el metaverso
                </a>
            </div>
        </div>

        {{-- Visual del metaverso: campo de particulas conectadas sobre el degradado de marca --}}
        <div data-reveal style="--reveal-delay:200ms" class="relative">
            <div class="absolute inset-0 translate-x-4 translate-y-4 rounded-3xl opacity-[0.14]" style="background:linear-gradient(135deg,var(--color-azultec),var(--color-rojotec))" aria-hidden="true"></div>
            <div class="relative aspect-[16/11] overflow-hidden rounded-3xl border border-slate-200 shadow-[0_30px_70px_-26px_rgba(19,33,57,.34)]"
                 style="background:radial-gradient(120% 120% at 20% 0%,#15347e 0%,var(--color-navy) 60%)">
                <canvas data-particulas data-density="high" class="absolute inset-0 h-full w-full"></canvas>
                {{-- Rejilla de piso --}}
                <div class="absolute inset-x-0 bottom-0 h-1/2 rejilla opacity-70" aria-hidden="true"></div>
                {{-- Etiqueta flotante tipo HUD --}}
                <div class="flota absolute left-6 top-6 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2.5 backdrop-blur-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/60">Sesión en curso</p>
                    <p class="mt-0.5 text-sm font-semibold text-white" style="font-family:var(--font-jakarta)">Práctica 1 · Grupo 3A</p>
                </div>
                <div class="flota-lento absolute bottom-6 right-6 flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 backdrop-blur-sm">
                    <span class="size-1.5 rounded-full" style="background:var(--color-azultec-bright)"></span>
                    <span class="text-xs font-medium text-white/80">Calificación → Moodle</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Fila de valor 01 / 02 / 03 --}}
    <div class="mx-auto max-w-[1400px] px-5 pb-24 pt-10 sm:px-10">
        <div class="grid gap-px border-t border-slate-200 sm:grid-cols-3">
            @foreach ([
                ['01', 'var(--color-azultec)', 'Espacios inmersivos', 'Los alumnos entran a un laboratorio virtual desde cualquier equipo.'],
                ['02', 'var(--color-rojotec)', 'Sesiones en vivo', 'Abres la práctica para tu grupo y sigues su avance en tiempo real.'],
                ['03', 'var(--color-azultec)', 'Todo vuelve a Moodle', 'La calificación regresa sola a la boleta del alumno.'],
            ] as $i => $v)
                <div data-reveal style="--reveal-delay:{{ $i * 120 }}ms"
                     class="border-slate-200 pt-8 sm:border-r sm:px-9 sm:last:border-r-0 sm:first:pl-0 sm:last:pr-0">
                    <div class="mb-3 text-[15px] font-extrabold tracking-wider" style="font-family:var(--font-jakarta);color:{{ $v[1] }}">{{ $v[0] }}</div>
                    <h3 class="mb-2 text-xl font-bold text-[#132139]" style="font-family:var(--font-jakarta)">{{ $v[2] }}</h3>
                    <p class="text-base leading-relaxed text-slate-500">{{ $v[3] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== METAVERSO SHOWCASE ===== --}}
<section id="metaverso" class="relative overflow-hidden py-24 text-slate-100 sm:py-28" style="background:var(--color-navy)">
    {{-- Campo de particulas ambiental de fondo --}}
    <canvas data-particulas data-density="low" class="pointer-events-none absolute inset-0 h-full w-full opacity-60"></canvas>

    <div class="relative mx-auto max-w-[1400px] px-5 sm:px-10">
        <div class="mx-auto mb-14 max-w-3xl text-center">
            <div data-reveal class="mb-4 text-xs font-bold uppercase tracking-[0.22em]" style="color:var(--color-azultec-bright)">Conoce el metaverso</div>
            <h2 data-reveal style="--reveal-delay:80ms;font-family:var(--font-jakarta);font-weight:800;letter-spacing:-.02em" class="text-4xl leading-tight text-white sm:text-[42px]">
                Un campus virtual donde sucede la práctica
            </h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            @foreach ([['Aula virtual', 'El grupo se reúne en el laboratorio y trabaja la práctica del día.'], ['Avatar del alumno', 'Cada estudiante recorre y manipula el espacio con su propio avatar.']] as $i => $c)
                <article data-reveal style="--reveal-delay:{{ $i * 140 }}ms"
                         class="group relative aspect-[16/9] overflow-hidden rounded-2xl border border-white/10"
                         style="background:radial-gradient(130% 120% at {{ $i ? '80%' : '20%' }} 0%,#183a86 0%,#0b1a34 65%)">
                    <div class="absolute inset-x-0 bottom-0 h-3/5 rejilla opacity-60" aria-hidden="true"></div>
                    {{-- Nucleo luminoso flotante --}}
                    <div class="{{ $i ? 'flota-lento' : 'flota' }} absolute left-1/2 top-[38%] -translate-x-1/2 -translate-y-1/2" aria-hidden="true">
                        <div class="size-24 rounded-full blur-2xl" style="background:radial-gradient(circle,var(--color-azultec-bright) 0%,transparent 70%);opacity:.75"></div>
                    </div>
                    <div class="absolute inset-0 flex items-end p-6">
                        <div>
                            <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                                <span class="size-1.5 rounded-full" style="background:var(--color-azultec-bright)"></span> {{ $c[0] }}
                            </p>
                            <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-300">{{ $c[1] }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ===== CTA PROFESORES ===== --}}
<section class="bg-white py-24 sm:py-28">
    <div class="mx-auto max-w-3xl px-5 text-center sm:px-10">
        <div data-reveal class="mb-4 text-xs font-bold uppercase tracking-[0.22em] text-rojotec">Para profesores y directivos</div>
        <h2 data-reveal style="--reveal-delay:80ms;font-family:var(--font-jakarta);font-weight:800;letter-spacing:-.02em" class="mb-4 text-4xl leading-[1.08] text-[#101f38] sm:text-[46px]">
            Administra tus grupos desde tu panel
        </h2>
        <p data-reveal style="--reveal-delay:160ms" class="mx-auto mb-9 max-w-xl text-lg leading-relaxed text-slate-500 sm:text-xl">
            Programa prácticas, abre sesiones y revisa el avance de tus alumnos. Todo en un mismo lugar.
        </p>
        <a data-reveal style="--reveal-delay:240ms" href="{{ route('panel.dashboard') }}"
           class="inline-flex items-center justify-center rounded-xl bg-azultec px-9 py-4 text-[17px] font-bold text-white shadow-[0_12px_30px_rgba(26,54,110,.26)] transition-all hover:-translate-y-0.5 hover:bg-azultec-dark hover:shadow-[0_18px_38px_rgba(26,54,110,.32)]">
            Entrar al panel
        </a>
    </div>
</section>

</main>

{{-- ===== FOOTER ===== --}}
<footer class="py-9" style="background:var(--color-navy)">
    <div class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-4 px-5 sm:px-10">
        <div class="flex items-center gap-3 text-sm text-slate-400">
            <span class="flex flex-col gap-0.5" aria-hidden="true">
                <span class="h-[3px] w-[18px] rounded-sm" style="background:var(--color-azultec-bright)"></span>
                <span class="h-[3px] w-[18px] rounded-sm" style="background:var(--color-rojotec-soft)"></span>
            </span>
            Metaverso Escolar · Tecnológico Nacional de México · ITCJ
        </div>
        <div class="text-sm text-slate-500">Plataforma de laboratorios virtuales</div>
    </div>
</footer>

<script>
(() => {
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --- Revelado al hacer scroll --- */
    const objetivos = document.querySelectorAll('[data-reveal]');
    if (sinMovimiento || !('IntersectionObserver' in window)) {
        objetivos.forEach(el => el.classList.add('is-visible'));
    } else {
        const io = new IntersectionObserver((entradas) => {
            entradas.forEach(e => {
                if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
            });
        }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });
        objetivos.forEach(el => io.observe(el));
    }

    /* --- Sombra del header al bajar --- */
    const cabecera = document.getElementById('cabecera');
    const alScroll = () => cabecera.classList.toggle('flotante', window.scrollY > 8);
    alScroll();
    window.addEventListener('scroll', alScroll, { passive: true });

    /* --- Campo de particulas conectadas (constelacion) --- */
    const COLORES = ['#6ba6e8', '#8fbdf0', '#e0728a', '#ffffff'];

    function iniciarParticulas(canvas) {
        const ctx = canvas.getContext('2d');
        const denso = canvas.dataset.density === 'high';
        let ancho = 0, alto = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
        let particulas = [], anim = null, visible = true;

        function medir() {
            const r = canvas.getBoundingClientRect();
            ancho = r.width; alto = r.height;
            canvas.width = ancho * dpr; canvas.height = alto * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            const objetivo = Math.round((ancho * alto) / (denso ? 9000 : 20000));
            const n = Math.max(14, Math.min(denso ? 90 : 48, objetivo));
            particulas = Array.from({ length: n }, () => ({
                x: Math.random() * ancho, y: Math.random() * alto,
                vx: (Math.random() - 0.5) * 0.35, vy: (Math.random() - 0.5) * 0.35,
                r: Math.random() * 1.8 + 1,
                c: COLORES[(Math.random() * COLORES.length) | 0],
            }));
        }

        function dibujar() {
            ctx.clearRect(0, 0, ancho, alto);
            const union = denso ? 118 : 140;
            for (let i = 0; i < particulas.length; i++) {
                const p = particulas[i];
                for (let j = i + 1; j < particulas.length; j++) {
                    const q = particulas[j];
                    const dx = p.x - q.x, dy = p.y - q.y;
                    const d = Math.hypot(dx, dy);
                    if (d < union) {
                        ctx.globalAlpha = (1 - d / union) * 0.5;
                        ctx.strokeStyle = '#6ba6e8';
                        ctx.lineWidth = 0.6;
                        ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                    }
                }
            }
            ctx.globalAlpha = 1;
            for (const p of particulas) {
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = p.c; ctx.fill();
            }
        }

        function paso() {
            for (const p of particulas) {
                p.x += p.vx; p.y += p.vy;
                if (p.x < -20) p.x = ancho + 20; else if (p.x > ancho + 20) p.x = -20;
                if (p.y < -20) p.y = alto + 20; else if (p.y > alto + 20) p.y = -20;
            }
            dibujar();
            anim = requestAnimationFrame(paso);
        }

        function arrancar() { if (!anim && visible) paso(); }
        function detener() { if (anim) { cancelAnimationFrame(anim); anim = null; } }

        medir();
        if (sinMovimiento) { dibujar(); return; }

        // Solo anima cuando el canvas esta a la vista y la pestaña activa.
        const io = new IntersectionObserver(([e]) => { visible = e.isIntersecting; visible ? arrancar() : detener(); });
        io.observe(canvas);
        document.addEventListener('visibilitychange', () => document.hidden ? detener() : arrancar());
        let t; window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => { detener(); medir(); arrancar(); }, 200); }, { passive: true });
        arrancar();
    }

    document.querySelectorAll('canvas[data-particulas]').forEach(iniciarParticulas);
})();
</script>

</body>
</html>
