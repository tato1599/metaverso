const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

function fechaLocalStr(d) {
    const pad = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * La semana como lectura del instrumento, no como siete tarjetas.
 *
 * `semana` es el lunes YYYY-MM-DD y los eventos traen `inicio_local` (string en
 * la zona horaria del campus): el agrupamiento compara strings y nunca
 * `new Date()` del navegador, para que la TZ del cliente no mueva un evento de
 * columna. Esa era la razón original del componente y se conserva intacta.
 *
 * La estructura la dan reglas capilares entre columnas, no cajas: en escritorio
 * es una sola rejilla con siete carriles; por debajo de `md` se convierte en una
 * lista de días y **los días vacíos desaparecen**, porque siete cajas vacías
 * apiladas en un teléfono no son información.
 */
export default function WeekCalendar({ semana, eventos, renderEvento }) {
    const [anio, mes, dia] = semana.split('-').map(Number);
    const hoyStr = fechaLocalStr(new Date());

    const dias = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(anio, mes - 1, dia + i);
        const fecha = fechaLocalStr(d);

        return {
            fecha,
            etiqueta: DIAS[i],
            numero: d.getDate(),
            esHoy: fecha === hoyStr,
            eventos: eventos.filter((e) => e.inicio_local.slice(0, 10) === fecha),
        };
    });

    const conEventos = dias.filter((d) => d.eventos.length > 0);

    return (
        <>
            {/* ── Escritorio: siete carriles separados por reglas capilares ── */}
            <div className="hidden md:grid md:grid-cols-7">
                {dias.map((d) => (
                    <div
                        key={d.fecha}
                        className={`min-h-[8rem] min-w-0 border-l border-regla-suave px-3 pt-3 pb-4 first:border-l-0 ${
                            d.esHoy ? 'bg-portal-tinte/40' : ''
                        }`}
                    >
                        <p
                            className={`flex items-baseline justify-between gap-2 border-b-2 pb-2 ${
                                d.esHoy ? 'border-portal' : 'border-transparent'
                            }`}
                        >
                            <span className={`rotulo ${d.esHoy ? 'text-portal' : 'text-tinta-3'}`}>
                                {d.etiqueta}
                            </span>
                            <span
                                className={`dato text-[13px] ${
                                    d.esHoy ? 'font-semibold text-portal' : 'text-tinta-2'
                                }`}
                            >
                                {d.numero}
                            </span>
                        </p>

                        <div className="mt-3 space-y-2">{d.eventos.map(renderEvento)}</div>
                    </div>
                ))}
            </div>

            {/* ── Móvil: lista de días; los vacíos no ocupan sitio ── */}
            <div className="md:hidden">
                {conEventos.length === 0 ? (
                    <p className="rotulo px-5 py-8 text-center text-tinta-3">Sin prácticas esta semana</p>
                ) : (
                    conEventos.map((d) => (
                        <div key={d.fecha} className="border-t border-regla-suave first:border-t-0">
                            <p
                                className={`flex items-baseline gap-2 px-5 py-2.5 ${
                                    d.esHoy ? 'bg-portal-tinte/40' : 'bg-hueco/45'
                                }`}
                            >
                                <span className={`rotulo ${d.esHoy ? 'text-portal' : 'text-tinta-2'}`}>
                                    {d.etiqueta} {d.numero}
                                </span>
                                {d.esHoy && <span className="rotulo text-portal">· hoy</span>}
                            </p>
                            <div className="space-y-2 px-5 py-3">{d.eventos.map(renderEvento)}</div>
                        </div>
                    ))
                )}
            </div>
        </>
    );
}
