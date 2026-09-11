const MESES = 'enero febrero marzo abril mayo junio julio agosto septiembre octubre noviembre diciembre'.split(' ');

/**
 * Los datetimes del backend llegan como 'YYYY-MM-DD' o 'YYYY-MM-DDTHH:mm' en la
 * zona horaria del campus. Todo aquí se reconstruye POR COMPONENTES y nunca con
 * `new Date(string)`, para que la zona del navegador no mueva una práctica de
 * día. Es la misma razón por la que WeekCalendar agrupa comparando strings.
 */
function aFecha(local) {
    const [anio, mes, dia] = local.slice(0, 10).split('-').map(Number);

    return new Date(anio, mes - 1, dia);
}

export function sumarDias(fecha, dias) {
    const d = aFecha(fecha);
    d.setDate(d.getDate() + dias);
    const pad = (n) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** "lunes 7 de septiembre" — un ISO crudo no es un encabezado. */
export function diaLargo(local) {
    const d = aFecha(local);
    const nombre = d.toLocaleDateString('es-MX', { weekday: 'long' });

    return `${nombre} ${d.getDate()} de ${MESES[d.getMonth()]}`;
}

/** "7–13 de septiembre", o "28 de agosto – 3 de septiembre" si cruza de mes. */
export function rangoSemana(lunes) {
    const a = aFecha(lunes);
    const b = aFecha(sumarDias(lunes, 6));

    return a.getMonth() === b.getMonth()
        ? `${a.getDate()}–${b.getDate()} de ${MESES[a.getMonth()]}`
        : `${a.getDate()} de ${MESES[a.getMonth()]} – ${b.getDate()} de ${MESES[b.getMonth()]}`;
}

/** El español no tolera "1 grupos": la unidad se escribe según la cifra. */
export function plural(n, singular, sufijo = 's') {
    return n === 1 ? singular : singular + sufijo;
}
