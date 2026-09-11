import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../../Components/Button';
import EmptyState from '../../Components/EmptyState';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import Table from '../../Components/Table';
import AppLayout from '../../Layouts/AppLayout';
import { plural } from '../../fechas';

function descargarCsv(grupo, filas) {
    const esc = (v) => `"${String(v).replace(/"/g, '""')}"`;
    const encabezado = 'nombre,matricula,url';
    const cuerpo = filas.map((f) => [esc(f.nombre), esc(f.matricula), esc(f.url)].join(',')).join('\n');
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
            <td className="px-4 py-3 text-tinta">{f.nombre}</td>
            <td className="dato px-4 py-3 text-xs text-tinta-2">{f.matricula}</td>
            <td className="max-w-[22rem] truncate px-4 py-3 font-mono text-[11px] text-tinta-3" title={f.url}>
                {f.url}
            </td>
            <td className="px-4 py-3 text-right">
                <button
                    type="button"
                    onClick={() => {
                        navigator.clipboard.writeText(f.url);
                        setCopiado(true);
                        setTimeout(() => setCopiado(false), 1500);
                    }}
                    aria-label={`Copiar el enlace de ${f.nombre}`}
                    className={`rotulo rounded-ctl px-1 outline-offset-2 transition-colors focus-visible:outline-2 focus-visible:outline-portal ${
                        copiado ? 'text-telemetria' : 'text-portal hover:text-portal-fuerte'
                    }`}
                >
                    {copiado ? 'Copiado' : 'Copiar'}
                </button>
            </td>
        </tr>
    );
}

export default function Links({ grupo, filas }) {
    return (
        <AppLayout>
            <Head title={`Enlaces · ${grupo.clave}`} />

            <Encabezado
                volver={{ href: `/panel/grupos/${grupo.id_grupo}`, label: `Grupo ${grupo.clave}` }}
                titulo="Enlaces de juego"
                meta="Un enlace de un solo uso por alumno. Compártelos por el canal que uses con el grupo."
                acciones={
                    filas.length > 0 && (
                        <Button variant="secondary" onClick={() => descargarCsv(grupo, filas)}>
                            Descargar CSV
                        </Button>
                    )
                }
            />

            {filas.length === 0 ? (
                <EmptyState
                    title="Sin alumnos inscritos"
                    hint="Inscribe alumnos al grupo para poder generar sus enlaces de juego."
                />
            ) : (
                <Lamina depth={0.25} retardo={120}>
                    <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-regla-suave px-5 py-3.5">
                        <p className="rotulo text-tinta-2">Enlaces generados</p>
                        <p className="rotulo text-tinta-3">
                            <span className="dato">{filas.length}</span> {plural(filas.length, 'enlace')}
                        </p>
                    </header>
                    <Table head={['Alumno', 'Matrícula', 'Enlace', '']}>
                        {filas.map((f) => (
                            <FilaLink key={f.matricula} f={f} />
                        ))}
                    </Table>
                </Lamina>
            )}
        </AppLayout>
    );
}
