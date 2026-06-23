<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Elegir práctica</title>
<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem}.p{border:1px solid #e5e7eb;border-radius:.6rem;padding:1rem;margin:.5rem 0;display:flex;justify-content:space-between;align-items:center}.btn{background:#4f46e5;color:#fff;border:0;padding:.5rem 1rem;border-radius:.5rem;cursor:pointer}</style>
</head><body>
<h1>Elige la práctica para esta actividad</h1>
@forelse ($practicas as $practica)
<form method="POST" action="{{ route('lti.deeplink.responder') }}" class="p">@csrf
    <span>{{ $practica->titulo }}</span>
    <input type="hidden" name="id_practica" value="{{ $practica->id_practica }}">
    <button class="btn">Elegir</button>
</form>
@empty
<p>No hay prácticas configuradas todavía. Pídele al administrador que cree una.</p>
@endforelse
</body></html>
