import Button from './Button';
import { TextInput } from './FormField';

/**
 * La cabecera de una pantalla de administración: título, buscador y alta.
 * Estaba copiada en las cinco pantallas de `Admin/*`.
 */
export default function CabeceraAdmin({ titulo, busqueda, setBusqueda, onAgregar, contador }) {
    return (
        <div data-entra className="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div>
                <p className="rotulo grabado text-tinta-3">Administración</p>
                <h1 className="grabado mt-2 font-display text-[clamp(1.5rem,2.6vw,1.875rem)] leading-tight font-extrabold tracking-[-0.03em] text-tinta">
                    {titulo}
                </h1>
                {contador && <p className="rotulo mt-2 text-tinta-3">{contador}</p>}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {/* El ancho lo pone el envoltorio: el control es w-full por diseño. */}
                <div className="w-56">
                    <TextInput
                        type="search"
                        name="busqueda"
                        aria-label={`Buscar en ${titulo.toLowerCase()}`}
                        placeholder="Buscar…"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                </div>
                <Button onClick={onAgregar}>Agregar</Button>
            </div>
        </div>
    );
}

/** Las acciones de una fila: texto, no botones que compitan con el dato. */
export const claseAccion =
    'rotulo rounded-ctl px-1 outline-offset-2 transition-colors focus-visible:outline-2 focus-visible:outline-portal';
