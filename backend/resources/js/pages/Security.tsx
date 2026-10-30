import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthFrame from '../components/AuthFrame';
import { requestJson } from '../lib/request';

export default function Security({ confirmed }: { confirmed: boolean }) {
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [secret, setSecret] = useState('');
    const [codes, setCodes] = useState<string[]>([]);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const change = useForm({ current_password: '', password: '', password_confirmation: '' });
    async function act(operation: () => Promise<void>) {
        setBusy(true);
        setMessage('');
        try {
            await operation();
        } catch (error) {
            setMessage(error instanceof Error ? error.message : 'Please try again.');
        } finally {
            setBusy(false);
            setPassword('');
        }
    }
    async function confirmPassword() {
        await requestJson('/user/confirm-password', 'POST', { password });
    }
    return (
        <AuthFrame title="Account security">
            <Head title="Account security" />
            <Link href="/workspace" className="text-button">
                Return to workspace
            </Link>
            <h2>Multi-factor authentication</h2>
            <p>
                {confirmed
                    ? 'Your authenticator is confirmed.'
                    : 'Owners and administrators must confirm an authenticator before entering the workspace.'}
            </p>
            <label>
                Current password
                <input
                    type="password"
                    autoComplete="current-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                />
            </label>
            {!confirmed && (
                <button
                    className="primary-button"
                    disabled={busy || !password}
                    onClick={() =>
                        void act(async () => {
                            await confirmPassword();
                            await requestJson('/user/two-factor-authentication', 'POST');
                            const data = await requestJson<{ secretKey: string }>(
                                '/user/two-factor-secret-key',
                            );
                            setSecret(data.secretKey);
                        })
                    }
                >
                    Set up authenticator
                </button>
            )}
            {secret && !confirmed && (
                <div className="enrollment">
                    <p>Enter this setup key in your authenticator app:</p>
                    <code>{secret}</code>
                    <label>
                        Authentication code
                        <input
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                        />
                    </label>
                    <button
                        className="primary-button"
                        disabled={busy || !code}
                        onClick={() =>
                            void act(async () => {
                                await requestJson(
                                    '/user/confirmed-two-factor-authentication',
                                    'POST',
                                    { code },
                                );
                                setSecret('');
                                setCode('');
                                router.reload();
                            })
                        }
                    >
                        Confirm authenticator
                    </button>
                </div>
            )}
            {confirmed && (
                <div className="button-row">
                    <button
                        className="secondary-button"
                        disabled={busy || !password}
                        onClick={() =>
                            void act(async () => {
                                await confirmPassword();
                                setCodes(
                                    await requestJson<string[]>('/user/two-factor-recovery-codes'),
                                );
                            })
                        }
                    >
                        Show recovery codes
                    </button>
                    <button
                        className="secondary-button"
                        disabled={busy || !password}
                        onClick={() =>
                            void act(async () => {
                                await confirmPassword();
                                await requestJson('/user/two-factor-recovery-codes', 'POST');
                                setCodes(
                                    await requestJson<string[]>('/user/two-factor-recovery-codes'),
                                );
                            })
                        }
                    >
                        Regenerate recovery codes
                    </button>
                    <button
                        className="secondary-button"
                        disabled={busy || !password}
                        onClick={() =>
                            void act(async () => {
                                await confirmPassword();
                                await requestJson('/user/two-factor-authentication', 'DELETE');
                                setCodes([]);
                                router.reload();
                            })
                        }
                    >
                        Disable authenticator
                    </button>
                </div>
            )}
            {codes.length > 0 && (
                <div className="enrollment">
                    <p>Store these codes privately. Each code can be used once to sign in.</p>
                    <ul>
                        {codes.map((value) => (
                            <li key={value}>
                                <code>{value}</code>
                            </li>
                        ))}
                    </ul>
                    <button className="text-button" onClick={() => setCodes([])}>
                        Hide codes
                    </button>
                </div>
            )}
            {message && (
                <p className="form-error" role="alert">
                    {message}
                </p>
            )}
            <hr />
            <h2>Change password</h2>
            <p>
                Use at least 14 characters with mixed case, a number and a symbol. Changing it signs
                out your other sessions.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    change.put('/account/password', { onSuccess: () => change.reset() });
                }}
            >
                <label>
                    Current password
                    <input
                        type="password"
                        autoComplete="current-password"
                        required
                        value={change.data.current_password}
                        onChange={(event) => change.setData('current_password', event.target.value)}
                    />
                </label>
                <label>
                    New password
                    <input
                        type="password"
                        autoComplete="new-password"
                        required
                        value={change.data.password}
                        onChange={(event) => change.setData('password', event.target.value)}
                    />
                </label>
                <label>
                    Confirm new password
                    <input
                        type="password"
                        autoComplete="new-password"
                        required
                        value={change.data.password_confirmation}
                        onChange={(event) =>
                            change.setData('password_confirmation', event.target.value)
                        }
                    />
                </label>
                {Object.values(change.errors).map((error) => (
                    <p role="alert" className="form-error" key={error}>
                        {error}
                    </p>
                ))}
                <button className="primary-button" disabled={change.processing}>
                    Change password
                </button>
            </form>
            <button className="text-button" onClick={() => router.post('/logout')}>
                Sign out
            </button>
        </AuthFrame>
    );
}
