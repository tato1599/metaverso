<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Abrir la práctica — Metaverso</title>
    {{--
      Página-máquina en Blade a propósito (decisión de stack): dispara un deeplink
      de esquema propio, lo que exige navegación top-level y nada de SPA.

      Trae el MODO DEMO que antes vivía en lti/abrir-juego.blade.php. Al hacerse
      obligatoria la reserva, aquel interstitial dejó de ser alcanzable, y con él
      se habría perdido la única forma de probar el circuito completo
      —sesión → calificación → Moodle— sin el motor de juego instalado.
    --}}
    <style>
        :root {
            --papel: #eef2f9; --hueco: #dde6f3; --vidrio: rgba(255,255,255,.62);
            --tinta: #0e1f3a; --tinta-2: #2b4470; --tinta-3: #45608f;
            --portal: #1a3a8f; --portal-fuerte: #132b6b;
            --telemetria: #0e6b4f; --alerta: #b3123b;
            --regla: rgba(14,31,58,.14); --regla-suave: rgba(14,31,58,.075);
            --mono: ui-monospace, "SFMono-Regular", Menlo, monospace;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font: 400 15px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--tinta);
            background:
                radial-gradient(118% 76% at 68% -10%, #fff 0%, rgba(255,255,255,0) 56%),
                radial-gradient(94% 60% at 6% 106%, #c7d6ea 0%, rgba(199,214,234,0) 60%),
                linear-gradient(176deg, #f6f9fc 0%, var(--papel) 46%, var(--hueco) 100%);
        }
        .franja { position: fixed; inset: 0 0 auto 0; height: 3px;
            background: linear-gradient(90deg, var(--portal) 0 50%, var(--alerta) 50% 100%); }
        .lamina { width: 100%; max-width: 32rem; border-radius: 6px; overflow: hidden;
            background: linear-gradient(172deg, rgba(255,255,255,.78) 0%, var(--vidrio) 44%, rgba(228,240,251,.5) 100%);
            box-shadow: 0 26px 52px -30px rgba(14,31,58,.34), 0 6px 14px -8px rgba(14,31,58,.16),
                        inset 0 1px 0 rgba(255,255,255,.96); }
        .canto { height: 6px; margin: 0 3px;
            background: linear-gradient(180deg,#fff 0%,#eaf1fa 26%,#bcd3ec 52%,#6f9bcd 76%,#3f6ba8 92%,var(--portal) 100%);
            border-radius: 0 0 5px 5px; }
        .cuerpo { padding: 28px; }
        .rotulo { font: 500 11px/1 var(--mono); letter-spacing: .16em; text-transform: uppercase; color: var(--tinta-3); }
        h1 { margin: 10px 0 0; font-size: 22px; letter-spacing: -.02em; }
        p { margin: 8px 0 0; color: var(--tinta-2); }
        .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%;
            height: 42px; margin-top: 20px; border: 0; border-radius: 4px; cursor: pointer;
            background: var(--portal); color: #fff; font: 600 15px/1 inherit; text-decoration: none; }
        .btn:hover { background: var(--portal-fuerte); }
        .btn.sec { background: transparent; color: var(--tinta); box-shadow: 0 0 0 1px var(--regla) inset; }
        .demo { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--regla-suave); }
        label { display: block; margin-top: 12px; }
        input[type=range] { width: 100%; accent-color: var(--portal); }
        .val { font: 500 15px/1 var(--mono); color: var(--tinta); }
        pre { display: none; margin-top: 14px; padding: 12px; border-radius: 4px; background: var(--hueco);
            font: 400 12px/1.5 var(--mono); white-space: pre-wrap; word-break: break-word;
            max-height: 220px; overflow: auto; color: var(--tinta-2); }
        pre.ok { color: var(--telemetria); } pre.err { color: var(--alerta); }
    </style>
</head>
<body>
<div class="franja" aria-hidden="true"></div>

<div>
    <div class="lamina">
        <div class="cuerpo">
            <p class="rotulo">Tu práctica está lista</p>
            <h1>Abriendo el juego…</h1>
            <p>Si no se abre solo, usa el botón. Al terminar, tu calificación se registra sola.</p>

            <a class="btn" href="{{ $deeplink }}">Abrir el juego</a>

            <div class="demo">
                <p class="rotulo">Modo demo · sin motor de juego</p>
                <p style="font-size:13px">
                    Simula la partida desde el navegador: canjea la sesión y envía la calificación de vuelta.
                </p>
                <label>
                    <span class="rotulo">Calificación</span>
                    <span class="val" id="valCal">90</span>
                </label>
                <input type="range" id="cal" min="0" max="100" value="90"
                       oninput="document.getElementById('valCal').textContent = this.value">
                <button class="btn sec" id="btnDemo" type="button">Simular partida y enviar calificación</button>
                <pre id="salida"></pre>
            </div>
        </div>
    </div>
    <div class="canto" aria-hidden="true"></div>
</div>

<script>
    const token = @json($token);
    const salida = document.getElementById('salida');
    const decir = (msg, clase) => { salida.style.display = 'block'; salida.className = clase || ''; salida.textContent = msg; };

    // El deeplink se dispara solo, pero sin retrasar la página: si no hay motor
    // instalado el navegador no hace nada y el modo demo sigue a mano.
    setTimeout(() => { window.location.href = @json($deeplink); }, 400);

    document.getElementById('btnDemo').addEventListener('click', async () => {
        const cal = parseInt(document.getElementById('cal').value, 10);
        const json = { 'Content-Type': 'application/json', Accept: 'application/json' };

        try {
            decir('1/3 · Canjeando el enlace…');
            const r1 = await fetch('/api/game/redeem', { method: 'POST', headers: json, body: JSON.stringify({ token }) });
            const d1 = await r1.json();
            if (!r1.ok) { return decir('No se pudo canjear (' + r1.status + '):\n' + JSON.stringify(d1, null, 2), 'err'); }

            const auth = { ...json, Authorization: 'Bearer ' + d1.access_token };

            decir('2/3 · Abriendo la sesión…');
            const r2 = await fetch('/api/game/sessions', {
                method: 'POST', headers: auth,
                body: JSON.stringify({ id_evento: d1.evento.id_evento }),
            });
            const d2 = await r2.json();
            if (!r2.ok) { return decir('No se pudo abrir la sesión (' + r2.status + '):\n' + JSON.stringify(d2, null, 2), 'err'); }

            decir('3/3 · Enviando la calificación ' + cal + '…');
            const r3 = await fetch('/api/game/sessions/' + d2.id_sesion + '/complete', {
                method: 'POST', headers: auth,
                body: JSON.stringify({ calificacion: cal, datos_resultado: { fuente: 'demo-web' } }),
            });
            const d3 = await r3.json();
            if (!r3.ok) { return decir('No se pudo completar (' + r3.status + '):\n' + JSON.stringify(d3, null, 2), 'err'); }

            decir('Listo. Sesión ' + d2.id_sesion + ' completada con ' + cal + '.\n'
                + 'Si entraste desde Moodle, la calificación ya viajó al libro de calificaciones.', 'ok');
        } catch (e) {
            decir('Error de red: ' + e.message + '\n¿El backend sigue corriendo?', 'err');
        }
    });
</script>
</body>
</html>
