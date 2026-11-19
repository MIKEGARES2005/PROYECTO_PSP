import assert from 'node:assert/strict';
import test from 'node:test';
import { capitalizeInitialLetter } from '../../resources/js/capitalize-initial-letter.js';

function createTextInput(value, selectionStart = value.length, selectionEnd = selectionStart) {
    return {
        value,
        selectionStart,
        selectionEnd,
        setSelectionRange(start, end) {
            this.selectionStart = start;
            this.selectionEnd = end;
        },
    };
}

test('capitalizes the first letter as soon as the user types it', () => {
    const input = createTextInput('m', 1);

    capitalizeInitialLetter(input);

    assert.equal(input.value, 'M');
    assert.equal(input.selectionStart, 1);
});

test('preserves the cursor while capitalizing an existing word', () => {
    const input = createTextInput('maria', 3);

    capitalizeInitialLetter(input);

    assert.equal(input.value, 'Maria');
    assert.equal(input.selectionStart, 3);
});

test('preserves accents and does not add accents the user removed', () => {
    const autocorrectedInput = createTextInput('ángel');
    const userCorrectedInput = createTextInput('Angel');

    capitalizeInitialLetter(autocorrectedInput);
    capitalizeInitialLetter(userCorrectedInput);

    assert.equal(autocorrectedInput.value, 'Ángel');
    assert.equal(userCorrectedInput.value, 'Angel');
});
