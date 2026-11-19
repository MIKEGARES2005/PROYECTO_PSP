import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildSuggestedUsername,
    normalizeSuggestedCredentialPart,
} from '../../resources/js/suggested-credentials.js';

test('preserves the Spanish eñe in suggested usernames and password parts', () => {
    assert.equal(buildSuggestedUsername('Cuenta', 'Castañeta'), 'cuenta_castañeta');
    assert.equal(normalizeSuggestedCredentialPart('CASTAÑETA'), 'castañeta');
});

test('limits suggested usernames to 50 characters', () => {
    assert.equal(buildSuggestedUsername('A'.repeat(48), 'Castañeta').length, 50);
});

test('normalizes other accents and removes punctuation from suggested password parts', () => {
    assert.equal(normalizeSuggestedCredentialPart(' Muñoz-Pérez '), 'muñozperez');
});
