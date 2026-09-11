import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Lamina from '../../Components/Lamina';
import GuestLayout from '../../Layouts/GuestLayout';

/*
 * CONTRATO DE DIRECCIÓN — Acceso · Metaverso
 *
 * THESIS: esto es una tarea, no una portada. Una sola columna, un solo objeto:
 * la lámina de acceso apoyada en la placa. Rechaza por igual la tarjeta genérica
 * flotando en el vacío y la vitrina de producto al lado del formulario.
 * OWN-WORLD: placa de luz fría (#EEF2F9→#DDE6F3) con retícula grabada de 96px,
 * cáusticas y una sola lámpara rasante; la lámina con canto, derrame y paralaje;
 * tinta navy TecNM, azul institucional para la acción; rótulos en mono.
 * STORY: quien llega sabe de qué escuela es esto, entra con su correo, y si venía
 * de Moodle descubre que no necesitaba pasar por aquí.
 * FIRST VIEWPORT: franja TecNM y faceplate arriba; al centro el título con su
 * barrido y la lámina de acceso con el botón dentro; debajo, la ruta LTI. Cierra
 * el filete con sus ticks.
 * FORM: mundo fijado por el usuario desde su librería (04-instrumento, registro
 * claro). Sin tirada: una dirección fijada por el brief manda sobre el dado.
 */

function Campo({ etiqueta, id, children, pista }) {
    return (
        <div>
            <label htmlFor={id} className="rotulo block text-tinta-2">
                {etiqueta}
            </label>
            <div className="mt-2">{children}</div>
            {pista && (
                <p id={`${id}-pista`} className="mt-1.5 text-[13px] text-tinta-3">
                    {pista}
                </p>
            )}
        </div>
    );
}

const claseCampo =
    'h-[42px] w-full rounded-ctl bg-vidrio-2 px-3 text-[15px] text-tinta shadow-[0_0_0_1px_var(--color-borde)_inset] transition-[background-color,box-shadow] duration-150 placeholder:text-tinta-3 aria-invalid:shadow-[0_0_0_1.5px_var(--color-alerta)_inset] focus:bg-white/80 focus:shadow-[0_0_0_2px_var(--color-portal)_inset] focus:outline-none';

/** Une los ids de descripción que apliquen; devuelve undefined si no hay ninguno. */
function describedBy(...ids) {
    const usables = ids.filter(Boolean);

    return usables.length ? usables.join(' ') : undefined;
}

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ correo: '', password: '' });
    const [verClave, setVerClave] = useState(false);
    const fallo = errors.correo ?? errors.password;

    function submit(e) {
        e.preventDefault();
        post('/login', {
            // Tras un fallo el foco se queda en <body> y hay que retabular desde
            // el principio del documento para reintentar. Lo devolvemos al campo.
            onError: () => document.getElementById('correo')?.focus(),
        });
    }

    return (
        <GuestLayout pie={<p className="rotulo grabado text-tinta-3">Ciudad Juárez · Chihuahua</p>}>
            <Head title="Iniciar sesión" />

            <div className="mx-auto w-full max-w-[26rem]">
                <h1
                    data-entra
                    style={{ '--retardo': '80ms' }}
                    className="grabado relative font-display text-[clamp(1.75rem,3.4vw,2.125rem)] leading-[1.04] font-extrabold tracking-[-0.03em] text-tinta"
                >
                    Entra al laboratorio.
                    {/* Un barrido, una vez. Es el encendido de la placa. */}
                    <span className="barrido" aria-hidden="true">
                        Entra al laboratorio.
                    </span>
                </h1>

                <Lamina depth={0.4} retardo={190} className="mt-6">
                    <form onSubmit={submit} className="space-y-5 p-6">
                        <div className="flex items-baseline justify-between gap-4 border-b border-regla-suave pb-4">
                            <p className="rotulo text-tinta-2">Acceso</p>
                            <p className="rotulo text-tinta-3">tecnm.mx</p>
                        </div>

                        {/* El fallo de credenciales y el rate limit llegan en errors.correo. */}
                        <div aria-live="polite">
                            {fallo && (
                                <p
                                    id="acceso-error"
                                    className="flex items-start gap-2.5 rounded-ctl bg-alerta-tinte px-3 py-2.5 text-[13px] font-medium text-alerta"
                                >
                                    <span
                                        className="mt-[5px] size-1.5 shrink-0 rounded-full bg-alerta"
                                        aria-hidden="true"
                                    />
                                    {fallo}
                                </p>
                            )}
                        </div>

                        <Campo etiqueta="Correo institucional" id="correo">
                            <input
                                id="correo"
                                type="email"
                                name="correo"
                                value={data.correo}
                                onChange={(e) => setData('correo', e.target.value)}
                                placeholder="tu.cuenta@tecnm.mx"
                                autoComplete="username"
                                autoFocus
                                required
                                aria-invalid={errors.correo ? true : undefined}
                                aria-describedby={describedBy(fallo && 'acceso-error')}
                                className={claseCampo}
                            />
                        </Campo>

                        <Campo etiqueta="Contraseña" id="password" pista="La entrega tu coordinación.">
                            <div className="relative">
                                <input
                                    id="password"
                                    type={verClave ? 'text' : 'password'}
                                    name="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="current-password"
                                    required
                                    aria-invalid={errors.password ? true : undefined}
                                    aria-describedby={describedBy(
                                        'password-pista',
                                        fallo && 'acceso-error',
                                    )}
                                    className={`${claseCampo} pr-[5.5rem]`}
                                />
                                <button
                                    type="button"
                                    onClick={() => setVerClave((v) => !v)}
                                    aria-label={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    aria-pressed={verClave}
                                    aria-controls="password"
                                    className="rotulo absolute inset-y-0 right-0 rounded-r-ctl px-3 text-tinta-3 outline-offset-2 transition-colors hover:text-tinta focus-visible:outline-2 focus-visible:outline-portal"
                                >
                                    {verClave ? 'Ocultar' : 'Ver'}
                                </button>
                            </div>
                        </Campo>

                        {/*
                         * Ocupado no es deshabilitado: atenuar el primario justo en el
                         * único instante en que el instrumento debe responder lo dejaba
                         * en 2.42:1. Conserva su color y anuncia el vuelo.
                         */}
                        <button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                            className="h-[42px] w-full rounded-ctl bg-portal text-[15px] font-semibold text-white outline-offset-[3px] transition-[background-color,transform] duration-150 hover:bg-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal disabled:pointer-events-none aria-busy:cursor-progress motion-safe:active:translate-y-px"
                        >
                            {processing ? 'Entrando…' : 'Entrar'}
                        </button>
                    </form>
                </Lamina>

                {/* La otra puerta, que es la que el alumno usa de verdad. */}
                <div
                    data-entra
                    style={{ '--retardo': '300ms' }}
                    className="mt-7 border-t border-regla-suave pt-5"
                >
                    <p className="rotulo grabado text-tinta-2">¿Vienes de Moodle?</p>
                    <p className="mt-2 text-[15px] text-tinta-2">
                        No necesitas entrar aquí. Abre la actividad de la práctica desde tu curso
                        y el acceso se resuelve solo.
                    </p>
                    {/*
                     * Sin enlace a propósito: no hay ruta de contacto ni de recuperación
                     * de contraseña en el backend, y un enlace que va a la portada
                     * fingiría una puerta que no existe.
                     */}
                    <p className="mt-2 text-[13px] text-tinta-3">
                        ¿Sin cuenta o sin contraseña? Pídela a tu coordinación académica.
                    </p>
                </div>
            </div>
        </GuestLayout>
    );
}
