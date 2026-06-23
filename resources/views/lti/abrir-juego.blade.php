<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Abrir juego — Metaverso TecNM</title>
<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0b1020;color:#fff}.c{text-align:center;padding:2rem}a.btn{display:inline-block;margin-top:1rem;padding:1rem 2rem;background:#4f46e5;color:#fff;border-radius:.75rem;text-decoration:none;font-weight:600}</style>
</head><body><div class="c">
<h1>Tu práctica está lista</h1>
<p>Haz clic para abrir el juego en Unreal Engine.</p>
<a class="btn" href="{{ $deeplink }}">Abrir juego</a>
</div>
<script>window.location.href = @json($deeplink);</script>
</body></html>
