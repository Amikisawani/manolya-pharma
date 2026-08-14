/**
 * Affiche les quantités en français : virgule décimale, 3 décimales.
 * Ex. 12.000 (stockage) → « 12,000 » (UI) — même valeur, pas « 12 000 » ni « 12 ».
 */
export function formatQty(
    value: string | number | null | undefined,
    fractionDigits = 3,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const n = toQtyNumber(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
        useGrouping: false,
    }).format(n);
}

/**
 * Parse une quantité saisie / reçue.
 * Accepte « 12,000 » (FR) et « 12.000 » (EN décimal) comme 12 — pas comme 12 000.
 */
export function toQtyNumber(value: string | number | null | undefined): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    let raw = String(value).trim().replace(/\s/g, '').replace(/\u00A0/g, '');

    // Format FR avec virgule décimale : 12,000 ou 12,5
    if (raw.includes(',')) {
        raw = raw.replace(/\./g, '').replace(',', '.');
    }

    const n = Number(raw);

    return Number.isFinite(n) ? n : 0;
}

export function useQuantity() {
    return { formatQty, toQtyNumber };
}
