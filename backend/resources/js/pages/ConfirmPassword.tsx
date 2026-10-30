import { Head, useForm } from '@inertiajs/react';
import AuthFrame from '../components/AuthFrame';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });
    return (
        <AuthFrame title="Confirm your password">
            <Head title="Confirm password" />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/user/confirm-password', { onFinish: () => form.reset() });
                }}
            >
                <label>
                    Current password
                    <input
                        type="password"
                        autoComplete="current-password"
                        required
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                </label>
                {form.errors.password && (
                    <p role="alert" className="form-error">
                        {form.errors.password}
                    </p>
                )}
                <button className="primary-button" disabled={form.processing}>
                    Continue
                </button>
            </form>
        </AuthFrame>
    );
}
