import { useState, type FormEvent } from 'react';
import type { Profile } from './types';

export default function ProfileEditor({
    profile,
    busy,
    onSave,
}: {
    profile: Profile | null;
    busy: boolean;
    onSave: (data: unknown) => void;
}) {
    const [name, setName] = useState(profile?.seller_name ?? '');
    const [address, setAddress] = useState(profile?.seller_address ?? '');
    const [registered, setRegistered] = useState(profile?.vat_registered ?? false);
    const [tin, setTin] = useState(profile?.tin ?? '');
    const [vat, setVat] = useState(profile?.vat_registration ?? '');
    const [from, setFrom] = useState(profile?.vat_effective_from ?? '');
    function submit(e: FormEvent) {
        e.preventDefault();
        onSave({
            country: 'LK',
            seller_name: name,
            seller_address: address,
            vat_registered: registered,
            tin: tin || null,
            vat_registration: vat || null,
            vat_effective_from: from || null,
            revision: profile?.revision ?? 0,
        });
    }
    return (
        <section className="panel" aria-label="Sales tax profile">
            <h2>Sri Lanka Sales profile</h2>
            <p>
                Declare your legal seller details and actual VAT registration. Standard VAT18 is for
                ordinary taxable supplies. Financial-services VAT, SSCL liabilities and other tax
                regimes are outside this quotation calculator.
            </p>
            <form onSubmit={submit}>
                <div className="form-grid">
                    <label>
                        Seller legal name
                        <input
                            required
                            value={name}
                            maxLength={160}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </label>
                    <label>
                        Seller address
                        <textarea
                            required
                            value={address}
                            maxLength={500}
                            onChange={(e) => setAddress(e.target.value)}
                        />
                    </label>
                </div>
                <label className="checkbox-label">
                    <input
                        type="checkbox"
                        checked={registered}
                        onChange={(e) => setRegistered(e.target.checked)}
                    />
                    Seller is VAT registered
                </label>
                {registered && (
                    <div className="form-grid">
                        <label>
                            Seller TIN (9 digits)
                            <input
                                required
                                pattern="[0-9]{9}"
                                value={tin}
                                onChange={(e) => setTin(e.target.value)}
                            />
                        </label>
                        <label>
                            VAT registration number
                            <input
                                required
                                value={vat}
                                maxLength={30}
                                onChange={(e) => setVat(e.target.value)}
                            />
                        </label>
                        <label>
                            VAT registration effective from
                            <input
                                required
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </label>
                    </div>
                )}
                <p className="muted">
                    LKR only. Confirm each supply’s classification and legal basis. Changing this
                    profile blocks issuance/conversion of quotations using an older profile
                    revision.
                </p>
                <button className="primary-button" disabled={busy}>
                    {busy ? 'Saving…' : 'Save Sales profile'}
                </button>
            </form>
        </section>
    );
}
