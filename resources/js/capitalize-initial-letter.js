export function capitalizeInitialLetter(input) {
    const match = input.value.match(/^(\s*)(\p{L})/u);

    if (!match) {
        return;
    }

    const [, leadingWhitespace, firstLetter] = match;
    const uppercaseLetter = firstLetter.toLocaleUpperCase('es');

    if (uppercaseLetter === firstLetter) {
        return;
    }

    const selectionStart = input.selectionStart;
    const selectionEnd = input.selectionEnd;
    const letterIndex = leadingWhitespace.length;
    const cursorAdjustment = uppercaseLetter.length - firstLetter.length;

    input.value = `${leadingWhitespace}${uppercaseLetter}${input.value.slice(letterIndex + firstLetter.length)}`;

    if (selectionStart !== null && selectionEnd !== null) {
        const adjustCursor = (position) => position > letterIndex
            ? position + cursorAdjustment
            : position;

        input.setSelectionRange(adjustCursor(selectionStart), adjustCursor(selectionEnd));
    }
}
