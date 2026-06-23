<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo — Metaverso Escolar TecNM</title>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #f5f6fa;
            --surface:  #ffffff;
            --border:   #e0e3eb;
            --text:     #1a1d2e;
            --muted:    #6b7280;
            --accent:   #4f46e5;
            --accent-h: #4338ca;
            --success:  #16a34a;
            --error:    #dc2626;
            --warn:     #d97706;
            --radius:   10px;
            --mono:     'JetBrains Mono', 'Fira Mono', 'Cascadia Code', ui-monospace, monospace;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── Layout ── */
        .page-header {
            background: var(--accent);
            color: #fff;
            padding: 2.5rem 2rem 2rem;
        }
        .page-header .inner { max-width: 860px; margin: 0 auto; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: .35rem; }
        .page-header p  { opacity: .88; font-size: .975rem; max-width: 640px; }
        .page-header a  { color: #c7d2fe; text-decoration: underline; }
        .page-header a:hover { color: #fff; }

        .badge-dev {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef3c7; color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 99px; font-size: .78rem; font-weight: 600;
            padding: 2px 10px; margin-top: .9rem;
        }

        main { max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

        /* ── Section cards ── */
        .section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 1.75rem;
            overflow: hidden;
        }
        .section-header {
            display: flex; align-items: center; gap: .6rem;
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--border);
            background: #fafbfc;
        }
        .section-header .num {
            background: var(--accent); color: #fff;
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; flex-shrink: 0;
        }
        .section-header h2 { font-size: 1rem; font-weight: 600; }
        .section-body { padding: 1.4rem; }

        /* ── Diagram ── */
        #mermaid-container {
            display: flex;
            justify-content: center;
            overflow-x: auto;
            padding: .5rem 0;
        }
        #mermaid-container svg { max-width: 100%; height: auto; }

        /* ── Stepper ── */
        .step {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: border-color .2s;
        }
        .step.is-active  { border-color: var(--accent); }
        .step.is-done    { border-color: var(--success); }
        .step.is-error   { border-color: var(--error); }
        .step.is-disabled { opacity: .55; pointer-events: none; }

        .step-header {
            display: flex; align-items: center; gap: .65rem;
            padding: .85rem 1.1rem;
            background: #fafbfc;
            border-bottom: 1px solid var(--border);
        }
        .step-badge {
            width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .72rem; font-weight: 700; flex-shrink: 0;
            background: var(--border); color: var(--muted);
        }
        .is-active  .step-badge { background: var(--accent); color: #fff; }
        .is-done    .step-badge { background: var(--success); color: #fff; }
        .is-error   .step-badge { background: var(--error);   color: #fff; }

        .step-title { font-weight: 600; font-size: .92rem; }
        .step-body  { padding: 1.1rem; }

        .form-row {
            display: flex; flex-wrap: wrap; gap: .75rem;
            margin-bottom: .9rem;
        }
        .form-group { display: flex; flex-direction: column; gap: .3rem; flex: 1; min-width: 160px; }
        .form-group label { font-size: .8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .form-group input, .form-group textarea {
            padding: .5rem .75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: var(--mono);
            font-size: .88rem;
            background: #f9fafb;
            color: var(--text);
            width: 100%;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 72px; }

        .slider-row { display: flex; align-items: center; gap: .8rem; margin-bottom: .9rem; }
        .slider-row label { font-size: .8rem; font-weight: 600; color: var(--muted); white-space: nowrap; }
        .slider-row input[type=range] { flex: 1; accent-color: var(--accent); }
        .slider-val { font-family: var(--mono); font-size: .9rem; font-weight: 700; min-width: 36px; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .55rem 1.1rem;
            border: none; border-radius: 7px;
            font-size: .9rem; font-weight: 600;
            cursor: pointer; transition: background .15s, opacity .15s;
            text-decoration: none;
        }
        .btn-primary  { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-h); }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .btn-sm { padding: .35rem .75rem; font-size: .82rem; }

        /* ── Response panel ── */
        .response-panel {
            margin-top: .9rem;
            border: 1px solid var(--border);
            border-radius: 7px;
            overflow: hidden;
            display: none;
        }
        .response-panel.visible { display: block; }
        .response-meta {
            display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
            padding: .45rem .8rem;
            background: #f3f4f6;
            border-bottom: 1px solid var(--border);
            font-family: var(--mono); font-size: .8rem;
        }
        .status-badge {
            padding: 1px 7px; border-radius: 4px;
            font-weight: 700; font-size: .78rem;
        }
        .status-2xx { background: #dcfce7; color: #166534; }
        .status-4xx, .status-5xx { background: #fee2e2; color: #991b1b; }
        .method-badge { color: var(--accent); font-weight: 700; }
        .url-text { color: var(--muted); word-break: break-all; }

        .response-body {
            padding: .8rem 1rem;
            background: #1e1e2e;
            overflow-x: auto;
        }
        .response-body pre {
            font-family: var(--mono);
            font-size: .82rem;
            line-height: 1.55;
            color: #cdd6f4;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .response-body.is-error pre { color: #f38ba8; }

        /* ── Token display ── */
        .token-pill {
            display: inline-flex; align-items: center; gap: .5rem;
            background: #eff6ff; border: 1px solid #bfdbfe;
            color: #1d4ed8; border-radius: 6px;
            padding: .3rem .7rem; font-family: var(--mono); font-size: .8rem;
            word-break: break-all; margin-top: .5rem;
        }

        /* ── Note box ── */
        .note {
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 7px; padding: .85rem 1rem;
            font-size: .85rem; color: #78350f;
            margin-top: 1rem;
        }
        .note strong { font-weight: 700; }
    </style>
</head>
<body>

<header class="page-header">
    <div class="inner">
        <h1>Metaverso Escolar TecNM</h1>
        <p>
            Plataforma de prácticas inmersivas para el Tecnológico Nacional de México.
            El cliente de Unreal Engine se autentica contra este backend para validar alumnos,
            iniciar sesiones de práctica y registrar calificaciones. Consulta la
            <a href="/docs" target="_blank">documentación interactiva (Scribe/OpenAPI)</a>
            para el detalle completo de cada endpoint.
        </p>
        <div class="badge-dev">
            ⚠ Demo de desarrollo — solo disponible en entorno local
        </div>
    </div>
</header>

<main>

    {{-- ── Sección 1: Diagrama de flujo ── --}}
    <div class="section">
        <div class="section-header">
            <span class="num">1</span>
            <h2>Diagrama de flujo del juego</h2>
        </div>
        <div class="section-body">
            <p style="color:var(--muted); font-size:.9rem; margin-bottom:1rem;">
                Flujo completo desde que el docente genera un magic link hasta que el alumno completa la práctica en Unreal Engine.
            </p>
            <div id="mermaid-container">
                <div class="mermaid">
sequenceDiagram
    participant D as Docente/Sistema
    participant W as Web (/jugar)
    participant U as Unreal Engine
    participant API as Backend API
    D->>API: POST /api/links (X-Api-Key)
    API-->>D: { url, deeplink, expira }
    D->>W: comparte el link al alumno
    W->>U: deeplink tecnm-metaverso://play?token=...
    U->>API: POST /api/game/redeem { token }
    API-->>U: { access_token (Bearer), alumno, practica, evento }
    U->>API: GET /api/game/me (Bearer)
    API-->>U: { usuario, alumno }
    U->>API: POST /api/game/sessions { id_evento } (Bearer)
    API-->>U: { id_sesion, estatus: en_progreso }
    U->>API: POST /api/game/sessions/{id}/complete { calificacion, datos_resultado }
    API-->>U: { estatus: completada, calificacion }
                </div>
            </div>
        </div>
    </div>

    {{-- ── Sección 2: Demo en vivo ── --}}
    <div class="section">
        <div class="section-header">
            <span class="num">2</span>
            <h2>Demo en vivo — flujo completo de la API</h2>
        </div>
        <div class="section-body">
            <p style="color:var(--muted); font-size:.88rem; margin-bottom:1.25rem;">
                Ejecuta cada paso en orden. Las respuestas se muestran tal como las devuelve el servidor.
                El Bearer token y el <code>id_sesion</code> se propagan automáticamente entre pasos.
            </p>

            {{-- PASO 0 --}}
            <div class="step is-active" id="step-0">
                <div class="step-header">
                    <span class="step-badge">0</span>
                    <span class="step-title">Generar magic link (POST /api/links)</span>
                </div>
                <div class="step-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>id_usuario</label>
                            <input type="number" id="inp-usuario" value="2" min="1">
                        </div>
                        <div class="form-group">
                            <label>id_evento</label>
                            <input type="number" id="inp-evento" value="1" min="1">
                        </div>
                        <div class="form-group" style="flex:2">
                            <label>X-Api-Key</label>
                            <input type="text" id="inp-apikey" value="{{ $apiKey ?? '' }}" placeholder="tu clave de API">
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="generarLink()">▶ Generar magic link</button>
                    <div class="response-panel" id="resp-0"></div>
                    <div id="token-display" style="display:none; margin-top:.8rem;">
                        <span style="font-size:.82rem; color:var(--muted); font-weight:600;">TOKEN EXTRAÍDO:</span>
                        <div class="token-pill" id="token-value"></div>
                    </div>
                </div>
            </div>

            {{-- PASO 1 --}}
            <div class="step is-disabled" id="step-1">
                <div class="step-header">
                    <span class="step-badge">1</span>
                    <span class="step-title">Canjear token — POST /api/game/redeem</span>
                </div>
                <div class="step-body">
                    <div class="form-row">
                        <div class="form-group" style="flex:3">
                            <label>Token (auto-rellenado del paso anterior)</label>
                            <input type="text" id="inp-token" placeholder="se rellenará automáticamente">
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="redeem()">▶ Canjear token (redeem)</button>
                    <div class="response-panel" id="resp-1"></div>
                </div>
            </div>

            {{-- PASO 2 --}}
            <div class="step is-disabled" id="step-2">
                <div class="step-header">
                    <span class="step-badge">2</span>
                    <span class="step-title">Iniciar sesión — POST /api/game/sessions</span>
                </div>
                <div class="step-body">
                    <p style="font-size:.85rem; color:var(--muted); margin-bottom:.8rem;">
                        Usa el <code>id_evento</code> configurado arriba y el Bearer token del paso anterior.
                    </p>
                    <button class="btn btn-primary" onclick="iniciarSesion()">▶ Iniciar sesión de práctica</button>
                    <div class="response-panel" id="resp-2"></div>
                </div>
            </div>

            {{-- PASO 3 --}}
            <div class="step is-disabled" id="step-3">
                <div class="step-header">
                    <span class="step-badge">3</span>
                    <span class="step-title">Completar sesión — POST /api/game/sessions/{id}/complete</span>
                </div>
                <div class="step-body">
                    <div class="slider-row">
                        <label>Calificación</label>
                        <input type="range" id="slider-cal" min="0" max="100" value="85" oninput="document.getElementById('val-cal').textContent = this.value">
                        <span class="slider-val" id="val-cal">85</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1">
                            <label>datos_resultado (JSON)</label>
                            <textarea id="inp-datos">{"aciertos":9,"errores":1}</textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="completarSesion()">▶ Completar sesión</button>
                    <div class="response-panel" id="resp-3"></div>
                </div>
            </div>

            <div class="note">
                <strong>Nota:</strong> El magic link es de <strong>un solo uso</strong>.
                Si quieres repetir el flujo, haz clic en "Generar magic link" de nuevo — se creará un token nuevo.
                El endpoint <code>POST /api/links</code> requiere la <code>X-Api-Key</code> del archivo <code>.env</code> (prefijada arriba para este entorno de desarrollo).
            </div>
        </div>
    </div>

</main>

<script>
    // ── Estado de la demo ──
    const state = {
        token: null,
        bearerToken: null,
        idSesion: null,
        idEvento: () => parseInt(document.getElementById('inp-evento').value, 10) || 1,
    };

    // ── Mermaid init ──
    mermaid.initialize({ startOnLoad: true, theme: 'base', themeVariables: {
        primaryColor: '#4f46e5', primaryTextColor: '#fff',
        primaryBorderColor: '#4338ca', lineColor: '#6b7280',
        secondaryColor: '#eff6ff', tertiaryColor: '#f5f6fa',
        fontSize: '14px',
    }});

    // ── Helpers ──
    function setStepState(n, state) {
        const el = document.getElementById('step-' + n);
        el.className = 'step ' + (state === 'active' ? 'is-active' : state === 'done' ? 'is-done' : state === 'error' ? 'is-error' : 'is-disabled');
    }

    function enableStep(n) {
        const el = document.getElementById('step-' + n);
        el.classList.remove('is-disabled');
        el.classList.add('is-active');
    }

    function showResponse(panelId, method, url, status, body, isError) {
        const panel = document.getElementById(panelId);
        panel.classList.add('visible');
        const statusClass = isError ? (status >= 500 ? 'status-5xx' : 'status-4xx') : 'status-2xx';
        panel.innerHTML = `
            <div class="response-meta">
                <span class="status-badge ${statusClass}">${status}</span>
                <span class="method-badge">${method}</span>
                <span class="url-text">${url}</span>
            </div>
            <div class="response-body ${isError ? 'is-error' : ''}">
                <pre>${escHtml(JSON.stringify(body, null, 2))}</pre>
            </div>
        `;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    async function apiFetch(method, path, headers, body) {
        const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...headers } };
        if (body !== undefined) opts.body = JSON.stringify(body);
        const resp = await fetch(path, opts);
        let json;
        try { json = await resp.json(); } catch { json = { error: 'Respuesta no es JSON' }; }
        return { status: resp.status, ok: resp.ok, json };
    }

    // ── Paso 0: Generar magic link ──
    async function generarLink() {
        const apiKey   = document.getElementById('inp-apikey').value.trim();
        const idUsuario= parseInt(document.getElementById('inp-usuario').value, 10);
        const idEvento = state.idEvento();

        if (!apiKey) { alert('Ingresa la X-Api-Key'); return; }

        const { status, ok, json } = await apiFetch('POST', '/api/links',
            { 'X-Api-Key': apiKey },
            { id_usuario: idUsuario, id_evento: idEvento }
        );

        showResponse('resp-0', 'POST', '/api/links', status, json, !ok);

        if (ok && json.deeplink) {
            // Extraer token del deeplink: tecnm-metaverso://play?token=XYZ
            const m = json.deeplink.match(/[?&]token=([^&]+)/);
            state.token = m ? m[1] : null;
            if (state.token) {
                document.getElementById('inp-token').value = state.token;
                document.getElementById('token-value').textContent = state.token;
                document.getElementById('token-display').style.display = 'block';
            }
            setStepState(0, 'done');
            enableStep(1);
        } else {
            setStepState(0, 'error');
        }
    }

    // ── Paso 1: Redeem ──
    async function redeem() {
        const token = document.getElementById('inp-token').value.trim();
        if (!token) { alert('No hay token disponible. Ejecuta el Paso 0 primero.'); return; }

        const { status, ok, json } = await apiFetch('POST', '/api/game/redeem', {}, { token });

        showResponse('resp-1', 'POST', '/api/game/redeem', status, json, !ok);

        if (ok && json.access_token) {
            state.bearerToken = json.access_token;
            setStepState(1, 'done');
            enableStep(2);
        } else {
            setStepState(1, 'error');
        }
    }

    // ── Paso 2: Iniciar sesión ──
    async function iniciarSesion() {
        if (!state.bearerToken) { alert('No hay Bearer token. Ejecuta el Paso 1 primero.'); return; }

        const { status, ok, json } = await apiFetch('POST', '/api/game/sessions',
            { Authorization: 'Bearer ' + state.bearerToken },
            { id_evento: state.idEvento() }
        );

        showResponse('resp-2', 'POST', '/api/game/sessions', status, json, !ok);

        if (ok && json.id_sesion) {
            state.idSesion = json.id_sesion;
            setStepState(2, 'done');
            enableStep(3);
        } else {
            setStepState(2, 'error');
        }
    }

    // ── Paso 3: Completar sesión ──
    async function completarSesion() {
        if (!state.idSesion) { alert('No hay sesión activa. Ejecuta el Paso 2 primero.'); return; }

        const calificacion = parseInt(document.getElementById('slider-cal').value, 10);
        let datosResultado;
        try {
            datosResultado = JSON.parse(document.getElementById('inp-datos').value);
        } catch {
            alert('El JSON de datos_resultado no es válido.'); return;
        }

        const url = `/api/game/sessions/${state.idSesion}/complete`;
        const { status, ok, json } = await apiFetch('POST', url,
            { Authorization: 'Bearer ' + state.bearerToken },
            { calificacion, datos_resultado: datosResultado }
        );

        showResponse('resp-3', 'POST', url, status, json, !ok);
        setStepState(3, ok ? 'done' : 'error');
    }
</script>

</body>
</html>
