@extends('panel.layout')
@section('titulo', 'Grupo '.$grupo->clave)
@section('contenido')
<a class="row-link" href="{{ route('panel.dashboard') }}">← Volver</a>
<h1>Grupo {{ $grupo->clave }} — {{ optional($grupo->materia)->nombre }}</h1>
<p><a class="btn" href="{{ route('panel.grupos.resultados', $grupo->id_grupo) }}">Ver resultados</a></p>

<div class="card">
    <h2>Alumnos inscritos ({{ $alumnos->count() }})</h2>
    <table>
        <thead><tr><th>Matrícula</th><th>Nombre</th></tr></thead>
        <tbody>
        @foreach ($alumnos as $insc)
            <tr>
                <td>{{ optional($insc->alumno)->matricula }}</td>
                <td>{{ optional(optional($insc->alumno)->usuario)->nombre }} {{ optional(optional($insc->alumno)->usuario)->apellidos }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Eventos / prácticas</h2>
    <table>
        <thead><tr><th>Práctica</th><th>Inicio</th><th>Estatus</th><th></th></tr></thead>
        <tbody>
        @foreach ($eventos as $evento)
            <tr>
                <td>{{ optional($evento->practica)->titulo }}</td>
                <td>{{ $evento->fecha_hora_inicio }}</td>
                <td>{{ $evento->estatus }}</td>
                <td>
                    <form class="inline" method="POST" action="{{ route('panel.grupos.eventos.links', [$grupo->id_grupo, $evento->id_evento]) }}">@csrf
                        <button class="btn">Generar links del grupo</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
