@extends('panel.layout')
@section('titulo', 'Mis grupos')
@section('contenido')
<h1>Grupos</h1>
@forelse ($grupos as $grupo)
    <div class="card">
        <a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">
            {{ $grupo->clave }} — {{ optional($grupo->materia)->nombre }}
        </a>
        <div style="color:#6b7280;font-size:.9rem">
            Ciclo {{ optional($grupo->ciclo)->nombre }} · {{ $grupo->inscripciones_count }} alumnos
        </div>
    </div>
@empty
    <div class="card">No tienes grupos asignados.</div>
@endforelse
@endsection
