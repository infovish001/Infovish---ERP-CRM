import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthFrame from '../components/AuthFrame';

export default function Challenge() {
    const [recovery, setRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });
    return (
        <AuthFrame title="Verify your sign-in">
            <Head title="Verify sign-in" />
            <p>
                Enter{' '}
                {recovery
                    ? 'one of your saved recovery codes'
                    : 'the code from your authenticator app'}
                .
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/two-factor-challenge', { onFinish: () => form.reset() });
                }}
            >
                {recovery ? (
                    <label>
                        Recovery code
                        <input
                            autoComplete="off"
                            type="password"
                            required
                            value={form.data.recovery_code}
                            onChange={(event) => form.setData('recovery_code', event.target.value)}
                        />
                    </label>
                ) : (
                    <label>
                        Authentication code
                        <input
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            required
                            value={form.data.code}
                            onChange={(event) => form.setData('code', event.target.value)}
                        />
                    </label>
                )}
                {Object.values(form.errors).map((error) => (
                    <p className="form-error" role="alert" key={error}>
                        {error}
                    </p>
                ))}
                <button className="primary-button" disabled={form.processing}>
                    Verify and sign in
                </button>
                <button
                    type="button"
                    className="text-button"
                    onClick={() => {
                        form.reset();
                        setRecovery(!recovery);
                    }}
                >
                    {recovery ? 'Use an authenticator code' : 'Use a recovery code'}
                </button>
            </form>
        </AuthFrame>
    );
}
