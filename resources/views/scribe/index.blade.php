<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>API Metaverso Escolar TecNM</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                                    <ul id="tocify-subheader-introduction" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="flujo-general">
                                <a href="#flujo-general">Flujo general</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="diagrama-de-secuencia">
                                <a href="#diagrama-de-secuencia">Diagrama de secuencia</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacion">
                                <a href="#autenticacion">Autenticación</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-generacion-de-links-docente" class="tocify-header">
                <li class="tocify-item level-1" data-unique="generacion-de-links-docente">
                    <a href="#generacion-de-links-docente">Generación de links (docente)</a>
                </li>
                                    <ul id="tocify-subheader-generacion-de-links-docente" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="generacion-de-links-docente-POSTapi-links">
                                <a href="#generacion-de-links-docente-POSTapi-links">Generar magic link de sesión</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-flujo-de-juego-unreal" class="tocify-header">
                <li class="tocify-item level-1" data-unique="flujo-de-juego-unreal">
                    <a href="#flujo-de-juego-unreal">Flujo de juego (Unreal)</a>
                </li>
                                    <ul id="tocify-subheader-flujo-de-juego-unreal" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="flujo-de-juego-unreal-POSTapi-game-redeem">
                                <a href="#flujo-de-juego-unreal-POSTapi-game-redeem">Canjear magic link (redeem)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="flujo-de-juego-unreal-GETapi-game-me">
                                <a href="#flujo-de-juego-unreal-GETapi-game-me">Obtener datos del alumno autenticado</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="flujo-de-juego-unreal-POSTapi-game-sessions">
                                <a href="#flujo-de-juego-unreal-POSTapi-game-sessions">Iniciar sesión de práctica</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="flujo-de-juego-unreal-POSTapi-game-sessions--id--complete">
                                <a href="#flujo-de-juego-unreal-POSTapi-game-sessions--id--complete">Completar sesión de práctica</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Última actualización: 23 de June de 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>API REST consumida por el cliente Unreal Engine del Metaverso Escolar TecNM. Permite generar magic links de sesión, canjearlos por tokens de acceso y registrar sesiones de práctica con calificación.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<p>Esta documentación describe todos los endpoints de la API del Metaverso Escolar TecNM.</p>
<h2 id="flujo-general">Flujo general</h2>
<p>El flujo completo es:</p>
<ol>
<li><strong>Docente/Sistema</strong> genera un magic link (<code>POST /api/links</code>) usando <code>X-Api-Key</code>.</li>
<li>El alumno abre la URL web (<code>/jugar/{token}</code>), que dispara el <strong>deeplink</strong> al cliente Unreal.</li>
<li><strong>Unreal</strong> canjea el token (<code>POST /api/game/redeem</code>) y obtiene un Bearer token de Sanctum.</li>
<li>Con ese Bearer token, Unreal llama <code>GET /api/game/me</code>, <code>POST /api/game/sessions</code> y <code>POST /api/game/sessions/{id}/complete</code>.</li>
</ol>
<h2 id="diagrama-de-secuencia">Diagrama de secuencia</h2>
<pre><code>sequenceDiagram
    participant D as Docente/Sistema
    participant W as Web (/jugar)
    participant U as Unreal
    participant API as Backend API
    D-&gt;&gt;API: POST /api/links (X-Api-Key)
    API--&gt;&gt;D: { url, deeplink, expira }
    D-&gt;&gt;W: comparte el link al alumno
    W-&gt;&gt;U: deeplink tecnm-metaverso://play?token=...
    U-&gt;&gt;API: POST /api/game/redeem { token }
    API--&gt;&gt;U: { access_token (Bearer), alumno, practica, evento }
    U-&gt;&gt;API: POST /api/game/sessions { id_evento } (Bearer)
    API--&gt;&gt;U: { id_sesion, estatus: en_progreso }
    U-&gt;&gt;API: POST /api/game/sessions/{id}/complete { calificacion, datos_resultado }
    API--&gt;&gt;U: { estatus: completada, calificacion }</code></pre>
<h2 id="autenticacion">Autenticación</h2>
<p>Los endpoints de <code>/api/game/*</code> (excepto <code>redeem</code>) requieren el header <code>Authorization: Bearer &lt;token&gt;</code> obtenido del canjeo del magic link. El token tiene una capacidad (<code>ability</code>) <code>game</code> y expira según <code>SANCTUM_TOKEN_TTL_MINUTES</code> (por defecto 480 minutos).</p>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {TU_ACCESS_TOKEN}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Obtén el token llamando a <code>POST /api/game/redeem</code> con un magic link válido. El token es de tipo Bearer y expira según <code>SANCTUM_TOKEN_TTL_MINUTES</code> (por defecto 480 minutos).</p>

        <h1 id="generacion-de-links-docente">Generación de links (docente)</h1>

    

                                <h2 id="generacion-de-links-docente-POSTapi-links">Generar magic link de sesión</h2>

<p>
</p>

<p>Genera un magic link de un solo uso para que un alumno ingrese al metaverso.
El link incluye una URL web (<code>/jugar/{token}</code>) y un deeplink para Unreal Engine.
Requiere autenticación por clave de API en el header <code>X-Api-Key</code>.</p>

<span id="example-requests-POSTapi-links">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/links" \
    --header "X-Api-Key: string required" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"id_usuario\": 42,
    \"id_evento\": 7,
    \"plataforma\": \"unreal\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/links"
);

const headers = {
    "X-Api-Key": "string required",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id_usuario": 42,
    "id_evento": 7,
    "plataforma": "unreal"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-links">
            <blockquote>
            <p>Example response (201, Magic link generado exitosamente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;url&quot;: &quot;http://localhost:8000/jugar/abc123xyz456&quot;,
    &quot;deeplink&quot;: &quot;tecnm-metaverso://play?token=abc123xyz456&quot;,
    &quot;expira&quot;: &quot;2026-06-23T18:00:00+00:00&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Clave de API inválida o ausente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No autorizado&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Datos de entrada inválidos):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The id_usuario field is required.&quot;,
    &quot;errors&quot;: {
        &quot;id_usuario&quot;: [
            &quot;The id_usuario field is required.&quot;
        ],
        &quot;id_evento&quot;: [
            &quot;The id_evento field is required.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-links" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-links"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-links"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-links" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-links">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-links" data-method="POST"
      data-path="api/links"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-links', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-links"
                    onclick="tryItOut('POSTapi-links');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-links"
                    onclick="cancelTryOut('POSTapi-links');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-links"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/links</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key"                data-endpoint="POSTapi-links"
               value="string required"
               data-component="header">
    <br>
<p>Example: <code>string required</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-links"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-links"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>id_usuario</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id_usuario"                data-endpoint="POSTapi-links"
               value="42"
               data-component="body">
    <br>
<p>El ID del usuario (alumno) para quien se genera el link. Example: <code>42</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>id_evento</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id_evento"                data-endpoint="POSTapi-links"
               value="7"
               data-component="body">
    <br>
<p>El ID del evento de agenda al que pertenece la sesión. Example: <code>7</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>plataforma</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plataforma"                data-endpoint="POSTapi-links"
               value="unreal"
               data-component="body">
    <br>
<p>optional La plataforma destino. Por defecto: <code>unreal</code>. Example: <code>unreal</code></p>
        </div>
        </form>

                <h1 id="flujo-de-juego-unreal">Flujo de juego (Unreal)</h1>

    

                                <h2 id="flujo-de-juego-unreal-POSTapi-game-redeem">Canjear magic link (redeem)</h2>

<p>
</p>

<p>Canjea un magic link de un solo uso y devuelve un token de acceso Bearer de Sanctum
junto con los datos del alumno, la práctica y el evento. El token queda marcado como
usado de forma atómica, por lo que no puede reutilizarse.</p>

<span id="example-requests-POSTapi-game-redeem">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/game/redeem" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"abc123xyz456\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/game/redeem"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "abc123xyz456"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-game-redeem">
            <blockquote>
            <p>Example response (200, Token canjeado exitosamente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;access_token&quot;: &quot;1|abcdefghijklmnopqrstuvwxyz1234567890&quot;,
    &quot;token_type&quot;: &quot;Bearer&quot;,
    &quot;alumno&quot;: {
        &quot;id_alumno&quot;: 15,
        &quot;numero_control&quot;: &quot;21TI0001&quot;,
        &quot;nombre&quot;: &quot;Juan P&eacute;rez L&oacute;pez&quot;,
        &quot;semestre&quot;: 5,
        &quot;id_grupo&quot;: 3
    },
    &quot;practica&quot;: {
        &quot;id_practica&quot;: 2,
        &quot;nombre&quot;: &quot;Pr&aacute;ctica 1 &ndash; Redes LAN virtuales&quot;,
        &quot;descripcion&quot;: &quot;Configuraci&oacute;n de switches y VLANs en entorno virtual&quot;
    },
    &quot;evento&quot;: {
        &quot;id_evento&quot;: 7,
        &quot;fecha_hora_inicio&quot;: &quot;2026-06-23T10:00:00+00:00&quot;,
        &quot;fecha_hora_fin&quot;: &quot;2026-06-23T12:00:00+00:00&quot;,
        &quot;estatus&quot;: &quot;activo&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Token inválido (no encontrado)):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Token inv&aacute;lido&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (410, Token expirado o ya utilizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Token expirado o ya utilizado&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Falta el campo token):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The token field is required.&quot;,
    &quot;errors&quot;: {
        &quot;token&quot;: [
            &quot;The token field is required.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-game-redeem" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-game-redeem"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-game-redeem"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-game-redeem" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-game-redeem">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-game-redeem" data-method="POST"
      data-path="api/game/redeem"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-game-redeem', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-game-redeem"
                    onclick="tryItOut('POSTapi-game-redeem');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-game-redeem"
                    onclick="cancelTryOut('POSTapi-game-redeem');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-game-redeem"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/game/redeem</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-game-redeem"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-game-redeem"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-game-redeem"
               value="abc123xyz456"
               data-component="body">
    <br>
<p>El token del magic link generado por <code>POST /api/links</code>. Example: <code>abc123xyz456</code></p>
        </div>
        </form>

                    <h2 id="flujo-de-juego-unreal-GETapi-game-me">Obtener datos del alumno autenticado</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Devuelve la información del usuario y del alumno asociado al token Bearer activo.
Útil para que Unreal Engine confirme la identidad del jugador al iniciar la sesión.</p>

<span id="example-requests-GETapi-game-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/game/me" \
    --header "Authorization: Bearer {TU_ACCESS_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/game/me"
);

const headers = {
    "Authorization": "Bearer {TU_ACCESS_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-game-me">
            <blockquote>
            <p>Example response (200, Datos del alumno autenticado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;usuario&quot;: {
        &quot;id_usuario&quot;: 42,
        &quot;name&quot;: &quot;Juan P&eacute;rez L&oacute;pez&quot;,
        &quot;email&quot;: &quot;juan.perez@tecnm.mx&quot;
    },
    &quot;alumno&quot;: {
        &quot;id_alumno&quot;: 15,
        &quot;numero_control&quot;: &quot;21TI0001&quot;,
        &quot;nombre&quot;: &quot;Juan P&eacute;rez L&oacute;pez&quot;,
        &quot;semestre&quot;: 5,
        &quot;id_grupo&quot;: 3
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Token inválido o expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-game-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-game-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-game-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-game-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-game-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-game-me" data-method="GET"
      data-path="api/game/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-game-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-game-me"
                    onclick="tryItOut('GETapi-game-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-game-me"
                    onclick="cancelTryOut('GETapi-game-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-game-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/game/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-game-me"
               value="Bearer {TU_ACCESS_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TU_ACCESS_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-game-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-game-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="flujo-de-juego-unreal-POSTapi-game-sessions">Iniciar sesión de práctica</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Crea una nueva sesión de práctica en estado <code>en_progreso</code> para el alumno autenticado
en el evento especificado. Verifica que el alumno esté inscrito en el grupo del evento.</p>

<span id="example-requests-POSTapi-game-sessions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/game/sessions" \
    --header "Authorization: Bearer {TU_ACCESS_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"id_evento\": 7
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/game/sessions"
);

const headers = {
    "Authorization": "Bearer {TU_ACCESS_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "id_evento": 7
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-game-sessions">
            <blockquote>
            <p>Example response (201, Sesión iniciada exitosamente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id_sesion&quot;: 88,
    &quot;estatus&quot;: &quot;en_progreso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Token inválido o expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (403, Alumno no inscrito en el grupo del evento):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;El alumno no est&aacute; inscrito en el grupo de este evento&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Falta el campo id_evento):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The id_evento field is required.&quot;,
    &quot;errors&quot;: {
        &quot;id_evento&quot;: [
            &quot;The id_evento field is required.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-game-sessions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-game-sessions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-game-sessions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-game-sessions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-game-sessions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-game-sessions" data-method="POST"
      data-path="api/game/sessions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-game-sessions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-game-sessions"
                    onclick="tryItOut('POSTapi-game-sessions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-game-sessions"
                    onclick="cancelTryOut('POSTapi-game-sessions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-game-sessions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/game/sessions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-game-sessions"
               value="Bearer {TU_ACCESS_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TU_ACCESS_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-game-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-game-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>id_evento</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id_evento"                data-endpoint="POSTapi-game-sessions"
               value="7"
               data-component="body">
    <br>
<p>El ID del evento de agenda en el que se inicia la sesión. Example: <code>7</code></p>
        </div>
        </form>

                    <h2 id="flujo-de-juego-unreal-POSTapi-game-sessions--id--complete">Completar sesión de práctica</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Marca una sesión de práctica como <code>completada</code> y guarda la calificación obtenida
(entre 0 y 100) y los datos de resultado opcionales. Solo el alumno dueño de la
sesión puede completarla, y únicamente si está en estado <code>en_progreso</code>.</p>

<span id="example-requests-POSTapi-game-sessions--id--complete">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/game/sessions/88/complete" \
    --header "Authorization: Bearer {TU_ACCESS_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"calificacion\": 85.5,
    \"datos_resultado\": {
        \"tiempo_segundos\": 240,
        \"errores\": 3
    }
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/game/sessions/88/complete"
);

const headers = {
    "Authorization": "Bearer {TU_ACCESS_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "calificacion": 85.5,
    "datos_resultado": {
        "tiempo_segundos": 240,
        "errores": 3
    }
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-game-sessions--id--complete">
            <blockquote>
            <p>Example response (200, Sesión completada exitosamente):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id_sesion&quot;: 88,
    &quot;estatus&quot;: &quot;completada&quot;,
    &quot;calificacion&quot;: 85.5
}</code>
 </pre>
            <blockquote>
            <p>Example response (401, Token inválido o expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (403, Sesión pertenece a otro alumno):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Sesi&oacute;n de otro alumno&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404, Sesión no encontrada):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\SesionPractica] 88&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (409, La sesión no está en progreso):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;La sesi&oacute;n no est&aacute; en progreso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422, Calificación fuera de rango o faltante):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;The calificacion field must not be greater than 100.&quot;,
    &quot;errors&quot;: {
        &quot;calificacion&quot;: [
            &quot;The calificacion field must not be greater than 100.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-game-sessions--id--complete" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-game-sessions--id--complete"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-game-sessions--id--complete"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-game-sessions--id--complete" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-game-sessions--id--complete">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-game-sessions--id--complete" data-method="POST"
      data-path="api/game/sessions/{id}/complete"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-game-sessions--id--complete', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-game-sessions--id--complete"
                    onclick="tryItOut('POSTapi-game-sessions--id--complete');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-game-sessions--id--complete"
                    onclick="cancelTryOut('POSTapi-game-sessions--id--complete');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-game-sessions--id--complete"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/game/sessions/{id}/complete</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-game-sessions--id--complete"
               value="Bearer {TU_ACCESS_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TU_ACCESS_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-game-sessions--id--complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-game-sessions--id--complete"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="POSTapi-game-sessions--id--complete"
               value="88"
               data-component="url">
    <br>
<p>El ID de la sesión de práctica a completar. Example: <code>88</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>calificacion</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="calificacion"                data-endpoint="POSTapi-game-sessions--id--complete"
               value="85.5"
               data-component="body">
    <br>
<p>La calificación obtenida, entre 0 y 100. Example: <code>85.5</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>datos_resultado</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="datos_resultado"                data-endpoint="POSTapi-game-sessions--id--complete"
               value=""
               data-component="body">
    <br>
<p>optional Objeto JSON con datos adicionales del resultado (estadísticas, logs, etc.).</p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
