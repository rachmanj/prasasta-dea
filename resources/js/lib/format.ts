export function formatIDR(value: number | string | null | undefined): string {
    const n = typeof value === 'string' ? parseFloat(value) : value ?? 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number.isFinite(n) ? n : 0);
}

export function formatNumber(value: number | string | null | undefined): string {
    const n = typeof value === 'string' ? parseFloat(value) : value ?? 0;
    return new Intl.NumberFormat('id-ID').format(Number.isFinite(n) ? n : 0);
}
