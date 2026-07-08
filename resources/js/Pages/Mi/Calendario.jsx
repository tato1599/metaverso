import EmptyState from '../../Components/EmptyState';
import AppLayout from '../../Layouts/AppLayout';

export default function Calendario() {
    return (
        <AppLayout>
            <h1 className="font-display text-xl font-bold text-tinta">Mi calendario</h1>
            <div className="mt-6">
                <EmptyState
                    title="Tu calendario de prácticas aparecerá aquí"
                    hint="Cuando tus docentes agenden prácticas para tus grupos, podrás verlas y reservar tu lugar desde esta página."
                />
            </div>
        </AppLayout>
    );
}
