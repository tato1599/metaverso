<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- El mundo visual se compromete con el registro claro (ver DESIGN.md): sin
         esto, los controles nativos se invierten en navegadores en modo oscuro. --}}
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#eef2f9">
    <title inertia>Metaverso · campus de prácticas</title>
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
