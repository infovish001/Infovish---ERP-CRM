export class RequestError extends Error {
    constructor(
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}
export async function requestJson<T>(
    url: string,
    method = 'GET',
    body?: unknown,
    headers: Record<string, string> = {},
): Promise<T> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            ...headers,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!response.ok) {
        const error = (await response.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        throw new RequestError(
            error.message ?? 'The request could not be completed.',
            error.errors,
        );
    }
    return response.status === 204 ? (undefined as T) : ((await response.json()) as T);
}
