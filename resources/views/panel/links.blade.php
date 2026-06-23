@extends('panel.layout')
@section('titulo', 'Links del grupo')
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">← Volver al grupo</a>
<h1>Magic links — {{ $grupo->clave }}</h1>
<p><a class="btn" href="{{ route('panel.grupos.eventos.links.csv', [$grupo->id_grupo, $evento->id_evento]) }}">Descargar CSV</a></p>
<div class="card">
<table>
    <thead><tr><th>Alumno</th><th>Matrícula</th><th>Link de acceso</th></tr></thead>
    <tbody>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f['nombre'] }}</td>
            <td>{{ $f['matricula'] }}</td>
            <td><input type="text" readonly value="{{ $f['url'] }}" style="width:100%" onclick="this.select()"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
