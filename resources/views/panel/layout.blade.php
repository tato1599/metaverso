<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel de Maestros') — Metaverso TecNM</title>
    <style>
        :root{--indigo:#4f46e5;--ink:#1f2937;--bg:#f5f6fa;--line:#e5e7eb}
        *{box-sizing:border-box}body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--ink)}
        header{background:var(--indigo);color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
        header a{color:#fff;text-decoration:none;font-weight:600}
        main{max-width:1000px;margin:1.5rem auto;padding:0 1rem}
        .card{background:#fff;border:1px solid var(--line);border-radius:.75rem;padding:1.25rem;margin-bottom:1rem}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.6rem;border-bottom:1px solid var(--line)}
        th{font-size:.8rem;text-transform:uppercase;color:#6b7280}
        .btn{display:inline-block;background:var(--indigo);color:#fff;border:0;padding:.55rem 1rem;border-radius:.6rem;text-decoration:none;font-weight:600;cursor:pointer}
        a.row-link{color:var(--indigo);text-decoration:none;font-weight:600}
        form.inline{display:inline}
    </style>
</head>
<body>
    <header>
        <a href="{{ route('panel.dashboard') }}">Metaverso TecNM — Panel</a>
        <span>
            {{ auth()->user()->nombre }} {{ auth()->user()->apellidos }}
            <form class="inline" method="POST" action="{{ route('panel.salir') }}">@csrf
                <button class="btn" style="background:#374151">Salir</button>
            </form>
        </span>
    </header>
    <main>@yield('contenido')</main>
</body>
</html>
