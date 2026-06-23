<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Metaverso Escolar — Abrir juego</title>
    <style>
        body { font-family: system-ui, sans-serif; display:grid; place-items:center; min-height:100vh; margin:0; background:#0b1020; color:#fff; }
        .card { text-align:center; padding:2rem; }
        a.btn { display:inline-block; margin-top:1rem; padding:1rem 2rem; background:#3b82f6; color:#fff; border-radius:.75rem; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Metaverso Escolar TecNM</h1>
        <p>Tu sesión está lista. Haz clic para abrir el juego.</p>
        <a class="btn" href="{{ $deeplink }}">Abrir juego</a>
    </div>
    <script>window.location.href = @json($deeplink);</script>
</body>
</html>
