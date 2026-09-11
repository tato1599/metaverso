/**
 * La lectura: la tabla del instrumento.
 *
 * Cabecera en rótulo mono sobre el pozo, filas separadas por reglas capilares y
 * sin cebra. Por defecto es un panel embutido, porque casi siempre va DENTRO de
 * una Card y una lámina dentro de otra lámina es el "nested card" que el sistema
 * prohíbe; `suelta` la convierte en lámina cuando la tabla es el objeto de la
 * pantalla.
 *
 * `derechas` recibe los índices de columna cuyo ENCABEZADO va a la derecha, para
 * que se alinee con su cifra. Las celdas las alinea cada página con `text-right`
 * escrito a mano, y no un prop: Tailwind solo genera las clases que ve
 * literalmente en el código, así que una clase construida en tiempo de ejecución
 * —`[&_td:nth-child(${i})]:text-right`— nunca llega a existir. Se veía bien en
 * el JSX y no alineaba nada.
 *
 * Y a la derecha van las cantidades (cupo, calificación, conteos), no los
 * identificadores: una matrícula o una clave son mono, pero no se suman.
 */
export default function Table({ head = [], derechas = [], suelta = false, children }) {
    return (
        <div
            className={`overflow-x-auto ${
                suelta ? 'lamina-cara' : 'rounded-carta bg-vidrio-2 outline outline-regla-suave -outline-offset-1'
            }`}
        >
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-borde bg-hueco/55">
                        {head.map((h, i) => (
                            <th
                                key={h || i}
                                scope="col"
                                className={`rotulo px-4 py-2.5 text-tinta-2 ${
                                    derechas.includes(i) ? 'text-right' : ''
                                }`}
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-regla-suave">{children}</tbody>
            </table>
        </div>
    );
}
