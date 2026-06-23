@extends('panel.layout')
@section('titulo', 'Sesión #'.$sesion->id_sesion)
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.resultados', optional($sesion->evento)->id_grupo) }}">← Volver a resultados</a>
<h1>Sesión #{{ $sesion->id_sesion }}</h1>
<div class="card">
    <p><strong>Alumno:</strong> {{ optional(optional($sesion->alumno)->usuario)->nombre }} {{ optional(optional($sesion->alumno)->usuario)->apellidos }}</p>
    <p><strong>Práctica:</strong> {{ optional($sesion->practica)->titulo }}</p>
    <p><strong>Estatus:</strong> {{ $sesion->estatus }} · <strong>Calificación:</strong> {{ $sesion->calificacion }}</p>
    <p><strong>Inicio:</strong> {{ $sesion->fecha_inicio }} · <strong>Fin:</strong> {{ $sesion->fecha_fin }}</p>
    <h2>Telemetría</h2>
    <pre style="background:#0b1020;color:#d1d5db;padding:1rem;border-radius:.5rem;overflow:auto">{{ json_encode($sesion->datos_resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
@endsection
