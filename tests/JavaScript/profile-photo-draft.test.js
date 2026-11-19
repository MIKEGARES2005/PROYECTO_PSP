import assert from 'node:assert/strict';
import test from 'node:test';
import {
    loadProfilePhotoDraft,
    profilePhotoDraftKey,
    restoreProfilePhotoDraft,
    saveProfilePhotoDraft,
} from '../../resources/js/profile-photo-draft.js';

class MemoryStorage {
    values = new Map();

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, value);
    }

    removeItem(key) {
        this.values.delete(key);
    }
}

class TestFileReader {
    readAsDataURL(file) {
        file.arrayBuffer().then((buffer) => {
            this.result = `data:${file.type};base64,${Buffer.from(buffer).toString('base64')}`;
            this.onload();
        }).catch((error) => {
            this.error = error;
            this.onerror();
        });
    }
}

test('restores a selected photo from a session draft after a validation redirect', async () => {
    const originalFileReader = globalThis.FileReader;
    const originalDataTransfer = globalThis.DataTransfer;

    globalThis.FileReader = TestFileReader;
    globalThis.DataTransfer = class {
        files = [];
        items = {
            add: (file) => this.files.push(file),
        };
    };

    try {
        const storage = new MemoryStorage();
        const key = profilePhotoDraftKey('estudiante-17');
        const photo = new File(['contenido de imagen'], 'perfil.png', {
            type: 'image/png',
            lastModified: 1234,
        });

        await saveProfilePhotoDraft(storage, key, photo, 1000);

        const draft = loadProfilePhotoDraft(storage, key, 1001);
        const input = {
            dataset: {},
            dispatchEvent(event) {
                this.dispatchedEvent = event;
            },
        };

        await restoreProfilePhotoDraft(draft, input);

        assert.equal(input.files[0].name, 'perfil.png');
        assert.equal(input.files[0].type, 'image/png');
        assert.equal(input.files[0].lastModified, 1234);
        assert.equal(await input.files[0].text(), 'contenido de imagen');
        assert.equal(input.dataset.profilePhotoDraftRestored, 'true');
        assert.equal(input.dispatchedEvent.type, 'change');
    } finally {
        if (originalFileReader === undefined) {
            delete globalThis.FileReader;
        } else {
            globalThis.FileReader = originalFileReader;
        }

        if (originalDataTransfer === undefined) {
            delete globalThis.DataTransfer;
        } else {
            globalThis.DataTransfer = originalDataTransfer;
        }
    }
});

test('discards an expired photo draft', async () => {
    const originalFileReader = globalThis.FileReader;

    globalThis.FileReader = TestFileReader;

    try {
        const storage = new MemoryStorage();
        const key = profilePhotoDraftKey('estudiante-17');

        await saveProfilePhotoDraft(
            storage,
            key,
            new File(['contenido de imagen'], 'perfil.png', { type: 'image/png' }),
            1000,
        );

        assert.equal(loadProfilePhotoDraft(storage, key, 901001), null);
        assert.equal(storage.getItem(key), null);
    } finally {
        if (originalFileReader === undefined) {
            delete globalThis.FileReader;
        } else {
            globalThis.FileReader = originalFileReader;
        }
    }
});
