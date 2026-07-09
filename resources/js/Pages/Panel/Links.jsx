import { Link } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../Components/Button';
import EmptyState from '../../Components/EmptyState';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';

function descargarCsv(grupo, filas) {
    const encabezado = 'nombre,matricula,url';
    const cuerpo = filas.map((f) => `"${f.nombre}",${f.matricula},${f.url}`).join('\n');
    const blob = new Blob([`${encabezado}\n${cuerpo}`], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `links-grupo-${grupo.id_grupo}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

function FilaLink({ f }) {
    const [copiado, setCopiado] = useState(false);

    return (
        <tr>
            <td className="px-4 py-2.5 text-tinta">{f.nombre}</td>
            <td className="px-4 py-2.5 font-mono text-xs tabular-nums text-tinta-2">{f.matricula}</td>
            <td className="max-w-xs truncate px-4 py-2.5 font-mono text-[11px] text-tinta-3">{f.url}</td>
            <td className="px-4 py-2.5 text-right">
                <button
                    onClick={() => {
                        navigator.clipboard.writeText(f.url);
                        setCopiado(true);
                        setTimeout(() => setCopiado(false), 1500);
                    }}
                    className="rounded-ctl px-2 py-1 text-xs font-medium text-tinta-2 hover:bg-hueco hover:text-tinta"
                >
                    {copiado ? 'Copiado ✓' : 'Copiar'}
                </button>
            </td>
        </tr>
    );
}

export default function Links({ grupo, filas }) {
    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href={`/panel/grupos/${grupo.id_grupo}`} className="text-[13px] text-tinta-3 hover:text-tinta">
                        ‹ Grupo {grupo.clave}
                    </Link>
                    <h1 className="mt-1 font-display text-xl font-bold text-tinta">Enlaces de juego</h1>
                    <p className="text-[13px] text-tinta-2">
                        Un enlace de un solo uso por alumno. Compártelos por el canal que uses con el grupo.
                    </p>
                </div>
                {filas.length > 0 && (
                    <Button variant="secondary" onClick={() => descargarCsv(grupo, filas)}>
                        Descargar CSV
                    </Button>
                )}
            </div>

            {filas.length === 0 ? (
                <EmptyState title="Sin alumnos inscritos" hint="Inscribe alumnos al grupo para generar sus enlaces." />
            ) : (
                <Table head={['Alumno', 'Matrícula', 'Enlace', '']}>
                    {filas.map((f) => (
                        <FilaLink key={f.matricula} f={f} />
                    ))}
                </Table>
            )}
        </AppLayout>
    );
}
