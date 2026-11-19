export function normalizeSuggestedCredentialPart(value) {
    return String(value)
        .trim()
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/n\u0303/g, 'ñ')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9ñ]/g, '');
}

export function buildSuggestedUsername(nombres, apellidoPaterno) {
    return [
        normalizeSuggestedCredentialPart(nombres),
        normalizeSuggestedCredentialPart(apellidoPaterno),
    ].filter(Boolean).join('_').slice(0, 50);
}
