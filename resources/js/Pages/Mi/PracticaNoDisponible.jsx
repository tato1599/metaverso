import { Head, Link } from '@inertiajs/react';
import Encabezado from '../../Components/Encabezado';
import Lamina from '../../Components/Lamina';
import AppLayout from '../../Layouts/AppLayout';

/*
 * Dónde aterriza quien entra desde Moodle a una práctica que todavía no se puede
 * reservar. No es una pantalla de error: es una respuesta. Dice qué falta, quién
 * lo tiene que hacer, y deja al alumno dentro de la aplicación en lugar de en un
 * callejón — por eso siempre ofrece su calendario.
 */
const MOTIVOS = {
    'curso-sin-grupo': {
        titulo: 'Tu curso todavía no está enlazado',
        cuerpo: 'Este curso de Moodle aún no está vinculado a un grupo del campus, así que no podemos saber qué fechas te tocan.',
        queHacer: 'Pídele a tu coordinación que enlace el curso con tu grupo. Cuando lo haga, entra otra vez desde Moodle y verás tus horarios.',
    },
    'sin-fechas': {
        titulo: 'Esta práctica aún no tiene fechas',
        cuerpo: 'Tu maestro todavía no ha agendado esta práctica, así que no hay horarios que reservar.',
        queHacer: 'En cuanto la agende aparecerá en tu calendario y podrás apartar tu lugar.',
    },
};

export default function PracticaNoDisponible({ motivo, practica, curso }) {
    const m = MOTIVOS[motivo] ?? MOTIVOS['sin-fechas'];

    return (
        <AppLayout>
            <Head title={m.titulo} />

            <div className="mx-auto w-full max-w-[40rem]">
                <Encabezado
                    rotulo="Desde Moodle"
                    titulo={m.titulo}
                    meta={
                        practica && (
                            <>
                                {practica}
                                {curso && <span className="dato text-tinta-3"> · {curso}</span>}
                            </>
                        )
                    }
                />

                <Lamina retardo={120}>
                    <div className="p-6">
                        <p className="rotulo text-senal">Qué pasó</p>
                        <p className="mt-2 text-[15px] text-tinta-2">{m.cuerpo}</p>

                        <p className="rotulo mt-6 text-tinta-2">Qué sigue</p>
                        <p className="mt-2 text-[15px] text-tinta-2">{m.queHacer}</p>

                        <Link
                            href="/mi/calendario"
                            className="mt-6 inline-flex h-[42px] items-center justify-center rounded-ctl bg-portal px-4 text-[15px] font-semibold text-white outline-offset-[3px] transition-colors hover:bg-portal-fuerte focus-visible:outline-2 focus-visible:outline-portal"
                        >
                            Ir a mi calendario
                        </Link>
                    </div>
                </Lamina>
            </div>
        </AppLayout>
    );
}
