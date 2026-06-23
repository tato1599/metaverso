@extends('panel.layout')
@section('titulo', 'Resultados '.$grupo->clave)
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">← Volver al grupo</a>
<h1>Resultados — {{ $grupo->clave }}</h1>
<div class="card">
<table>
    <thead><tr><th>Alumno</th><th>Práctica</th><th>Estatus</th><th>Calificación</th><th>Inicio</th><th></th></tr></thead>
    <tbody>
    @forelse ($sesiones as $s)
        <tr>
            <td>{{ optional(optional($s->alumno)->usuario)->nombre }} {{ optional(optional($s->alumno)->usuario)->apellidos }}</td>
            <td>{{ optional($s->practica)->titulo }}</td>
            <td>{{ $s->estatus }}</td>
            <td>{{ $s->calificacion }}</td>
            <td>{{ $s->fecha_inicio }}</td>
            <td><a class="row-link" href="{{ route('panel.sesiones.show', $s->id_sesion) }}">Ver detalle</a></td>
        </tr>
    @empty
        <tr><td colspan="6">Aún no hay sesiones registradas.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
