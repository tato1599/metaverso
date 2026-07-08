const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function mismaFecha(a, b) {
    return a.toDateString() === b.toDateString();
}

/**
 * Grid semanal reutilizable (docente en F2, alumno en F3).
 * `semana` es el lunes en formato YYYY-MM-DD; `renderEvento` pinta el chip de cada slot.
 */
export default function WeekCalendar({ semana, eventos, renderEvento }) {
    const lunes = new Date(`${semana}T00:00:00`);
    const hoy = new Date();
    const dias = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(lunes);
        d.setDate(d.getDate() + i);
        return d;
    });

    return (
        <div className="grid gap-3 md:grid-cols-7">
            {dias.map((d, i) => {
                const esHoy = mismaFecha(d, hoy);
                const delDia = eventos.filter((e) => mismaFecha(new Date(e.inicio), d));
                return (
                    <div
                        key={d.toISOString()}
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
