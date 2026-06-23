@extends('panel.layout')
@section('titulo', 'Links del grupo')
@section('contenido')
<a class="row-link" href="{{ route('panel.grupos.show', $grupo->id_grupo) }}">← Volver al grupo</a>
<h1>Magic links — {{ $grupo->clave }}</h1>
<p><button type="button" id="btnCsv" class="btn">Descargar CSV</button></p>
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
<script>
const filas = @json($filas);
document.getElementById('btnCsv').addEventListener('click', () => {
    const esc = v => '"' + String(v).replace(/"/g,'""') + '"';
    const lineas = [['nombre','matricula','url'].join(',')];
    filas.forEach(f => lineas.push([esc(f.nombre), esc(f.matricula), esc(f.url)].join(',')));
    const blob = new Blob([lineas.join('\n')], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'links-grupo-{{ $grupo->id_grupo }}.csv';
    a.click();
    URL.revokeObjectURL(a.href);
});
</script>
@endsection
