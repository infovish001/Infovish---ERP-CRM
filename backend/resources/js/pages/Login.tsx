import { Head, useForm } from '@inertiajs/react';
import AuthFrame from '../components/AuthFrame';

export default function Login({ tenantName }: { tenantName: string }) {
    const form = useForm({ email: '', password: '' });
    return (
        <AuthFrame title={`Sign in to ${tenantName}`}>
            <Head title="Sign in" />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/login', { onFinish: () => form.reset('password') });
                }}
            >
                <label>
                    Email address
                    <input
                        type="email"
                        autoComplete="username"
                        required
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                </label>
                <label>
                    Password
                    <input
                        type="password"
                        autoComplete="current-password"
                        required
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                </label>
                {Object.values(form.errors).map((error) => (
                    <p className="form-error" role="alert" key={error}>
                        {error}
                    </p>
                ))}
                <button className="primary-button" disabled={form.processing}>
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </AuthFrame>
    );
}
