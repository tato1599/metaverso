import { useForm } from '@inertiajs/react';
import Button from '../../Components/Button';
import FormField, { TextInput } from '../../Components/FormField';
import GuestLayout from '../../Layouts/GuestLayout';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        correo: '',
        password: '',
    });

    function submit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <GuestLayout>
            <form
                onSubmit={submit}
                className="space-y-4 rounded-panel bg-superficie p-6 ring-1 ring-borde"
            >
                <FormField label="Correo institucional" name="correo" error={errors.correo}>
                    <TextInput
                        id="correo"
                        type="email"
                        name="correo"
                        value={data.correo}
                        onChange={(e) => setData('correo', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />
                </FormField>
                <FormField label="Contraseña" name="password" error={errors.password}>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                        required
                    />
                </FormField>
                <Button type="submit" disabled={processing} className="w-full">
                    {processing ? 'Entrando…' : 'Entrar'}
                </Button>
            </form>
            <p className="mt-4 text-center text-xs text-tinta-3">
                ¿Sin cuenta o sin contraseña? Pídela a tu coordinación.
            </p>
        </GuestLayout>
    );
}
