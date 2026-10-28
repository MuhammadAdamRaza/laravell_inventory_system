export function moneyToCents(value) {
    const match = String(value ?? '')
        .trim()
        .match(/^(\d+)(?:\.(\d{1,2}))?$/);
    if (!match) return null;

    return BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0') || '0');
}

export function centsToDecimal(cents) {
    const value = BigInt(cents);
    const sign = value < 0n ? '-' : '';
    const absolute = value < 0n ? -value : value;
    const whole = absolute / 100n;
    const fraction = String(absolute % 100n).padStart(2, '0');

    return sign + whole + '.' + fraction;
}

export function formatMoney(value) {
    const match = String(value ?? '0')
        .trim()
        .match(/^(-?)(\d+)(?:\.(\d{1,2}))?$/);
    if (!match) return '0.00';

    const groupedWhole = BigInt(match[2]).toLocaleString('en-PK');
    const fraction = (match[3] || '').padEnd(2, '0');

    return match[1] + groupedWhole + '.' + fraction;
}
