export async function requestJson<T>(url: string, method = 'GET', body?: unknown): Promise<T> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!response.ok) {
        const error = (await response.json().catch(() => ({}))) as { message?: string };
        throw new Error(error.message ?? 'The request could not be completed.');
    }
    return response.status === 204 ? (undefined as T) : ((await response.json()) as T);
}
