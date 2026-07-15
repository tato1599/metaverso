<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abrir juego — Metaverso TecNM</title>
    <style>
        :root{--indigo:#4f46e5}
        body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0b1020;color:#fff}
        .c{width:min(92vw,560px);text-align:center;padding:2rem}
        .btn{display:inline-block;margin-top:.5rem;padding:.9rem 1.6rem;background:var(--indigo);color:#fff;border:0;border-radius:.75rem;text-decoration:none;font-weight:600;cursor:pointer}
        .card{margin-top:1.5rem;background:#111a35;border:1px solid #25305a;border-radius:.9rem;padding:1.25rem;text-align:left}
        .card h2{margin:.2rem 0 1rem;font-size:1rem;color:#c7d2fe}
        label{display:block;font-size:.85rem;color:#9aa6d6;margin:.6rem 0 .2rem}
        input[type=range]{width:100%}
        .val{font-weight:700;color:#fff}
        pre{background:#0b1020;border:1px solid #25305a;border-radius:.5rem;padding:.75rem;color:#d1d5db;white-space:pre-wrap;word-break:break-word;max-height:220px;overflow:auto}
        .ok{color:#4ade80}.err{color:#f87171}
        .muted{color:#7c89bd;font-size:.85rem}
    </style>
</head>
<body>
<div class="c">
    <h1>Tu práctica está lista</h1>
    <p class="muted">Práctica #{{ $practicaId }} · sesión por Moodle (LTI)</p>

    <a class="btn" href="{{ $deeplink }}">▶ Abrir juego (Unreal)</a>

    <div class="card">
        <h2>Modo demo (sin Unreal)</h2>
        <p class="muted">Simula la partida desde el navegador: canjea la sesión y envía la calificación de regreso a Moodle.</p>
        @isset($escenaReferencia)
            <p class="muted" style="margin-top:.75rem">El juego cargaría la escena
                <strong>{{ $escenaReferencia }}</strong> con esta configuración:</p>
            <pre style="background:#0b1020;color:#d1d5db;padding:.75rem;border-radius:.5rem;overflow:auto">{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endisset
        <label>Calificación: <span class="val" id="valCal">90</span></label>
        <input type="range" id="cal" min="0" max="100" value="90" oninput="document.getElementById('valCal').textContent=this.value">
        <button class="btn" id="btnDemo" style="width:100%;margin-top:1rem">Simular partida y enviar calificación</button>
        <pre id="out" style="display:none"></pre>
    </div>
</div>

<script>
    const deeplink = @json($deeplink);
    const m = deeplink.match(/lti_session_token=([A-Za-z0-9]+)/);
    const token = m ? m[1] : null;
    const out = document.getElementById('out');
    const log = (msg, cls) => { out.style.display='block'; out.className = cls||''; out.textContent = msg; };

    document.getElementById('btnDemo').addEventListener('click', async () => {
        if (!token) { log('No se encontró el token en el deeplink.', 'err'); return; }
        const cal = parseInt(document.getElementById('cal').value, 10);
        try {
            log('1/2 · Canjeando sesión…');
            const r1 = await fetch('/api/game/lti-redeem', {
                method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
                body: JSON.stringify({ lti_session_token: token })
            });
            const d1 = await r1.json();
            if (!r1.ok) { log('Error al canjear ('+r1.status+'):\n'+JSON.stringify(d1,null,2), 'err'); return; }

            log('2/2 · Enviando calificación '+cal+'…');
            const r2 = await fetch('/api/game/sessions/'+d1.id_sesion+'/complete', {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','Authorization':'Bearer '+d1.access_token},
                body: JSON.stringify({ calificacion: cal, datos_resultado: { fuente:'demo-web' } })
            });
            const d2 = await r2.json();
            if (!r2.ok) { log('Error al completar ('+r2.status+'):\n'+JSON.stringify(d2,null,2), 'err'); return; }

            log('✅ Listo. Sesión '+d1.id_sesion+' completada con '+cal+'.\nLa calificación se envió a Moodle (AGS).\nRevisa el libro de calificaciones del curso.', 'ok');
        } catch (e) {
            log('Error de red: '+e.message+'\n¿El backend sigue corriendo en localhost:8000?', 'err');
        }
    });
</script>
</body>
</html>
