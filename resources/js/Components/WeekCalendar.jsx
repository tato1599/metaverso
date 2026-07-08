const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function fechaLocalStr(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Grid semanal reutilizable (docente en F2, alumno en F3).
 * `semana` es el lunes YYYY-MM-DD y los eventos traen `inicio_local`
 * (string en la zona horaria del campus) — el agrupamiento compara strings,
 * nunca `new Date()` del navegador, para que la TZ del cliente no mueva eventos de columna.
 */
export default function WeekCalendar({ semana, eventos, renderEvento }) {
    const [anio, mes, dia] = semana.split('-').map(Number);
    const hoyStr = fechaLocalStr(new Date());
    const dias = Array.from({ length: 7 }, (_, i) => new Date(anio, mes - 1, dia + i));

    return (
        <div className="grid gap-3 md:grid-cols-7">
            {dias.map((d, i) => {
                const fechaDia = fechaLocalStr(d);
                const esHoy = fechaDia === hoyStr;
                const delDia = eventos.filter((e) => e.inicio_local.slice(0, 10) === fechaDia);
                return (
                    <div
                        key={fechaDia}
                        className={`min-h-28 rounded-carta bg-superficie p-2 ring-1 ${
                            esHoy ? 'ring-2 ring-portal' : 'ring-borde'
                        }`}
                    >
                        <p className="mb-2 flex items-baseline justify-between px-1">
                            <span className="text-[11px] font-semibold tracking-wide text-tinta-3 uppercase">
                                {DIAS[i]}
                            </span>
                            <span className={`font-mono text-xs tabular-nums ${esHoy ? 'font-bold text-portal' : 'text-tinta-2'}`}>
                                {d.getDate()}
                            </span>
                        </p>
                        <div className="space-y-2">{delDia.map(renderEvento)}</div>
                    </div>
                );
            })}
        </div>
    );
}
