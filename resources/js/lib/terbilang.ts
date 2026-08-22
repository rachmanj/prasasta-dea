const UNITS = [
    '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
    'Sepuluh', 'Sebelas', 'Dua Belas', 'Tiga Belas', 'Empat Belas', 'Lima Belas',
    'Enam Belas', 'Tujuh Belas', 'Delapan Belas', 'Sembilan Belas',
];

function tensWord(tens: number, rest: number): string {
    if (tens === 1) return UNITS[10 + rest];
    let word = `${UNITS[tens]} Puluh`;
    if (rest > 0) word += ` ${UNITS[rest]}`;
    return word;
}

function convert(n: number): string {
    if (n < 20) return UNITS[n];
    if (n < 100) return tensWord(Math.floor(n / 10), n % 10);
    if (n < 200) {
        const rest = n - 100;
        return `Seratus${rest > 0 ? ` ${convert(rest)}` : ''}`;
    }
    if (n < 1000) {
        const hundreds = Math.floor(n / 100);
        const rest = n % 100;
        return `${UNITS[hundreds]} Ratus${rest > 0 ? ` ${convert(rest)}` : ''}`;
    }
    if (n < 2000) {
        const rest = n - 1000;
        return `Seribu${rest > 0 ? ` ${convert(rest)}` : ''}`;
    }
    if (n < 1_000_000) {
        const thousands = Math.floor(n / 1000);
        const rest = n % 1000;
        return `${convert(thousands)} Ribu${rest > 0 ? ` ${convert(rest)}` : ''}`;
    }
    if (n < 1_000_000_000) {
        const millions = Math.floor(n / 1_000_000);
        const rest = n % 1_000_000;
        return `${convert(millions)} Juta${rest > 0 ? ` ${convert(rest)}` : ''}`;
    }
    const billions = Math.floor(n / 1_000_000_000);
    const rest = n % 1_000_000_000;
    return `${convert(billions)} Miliar${rest > 0 ? ` ${convert(rest)}` : ''}`;
}

export function terbilangRupiah(amount: number): string {
    const n = Math.round(Math.abs(amount));
    if (n === 0) return 'Nol Rupiah';
    return `${convert(n).trim()} Rupiah`;
}
