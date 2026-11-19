const PROFILE_PHOTO_DRAFT_MAX_AGE_MS = 15 * 60 * 1000;
const PROFILE_PHOTO_MAX_SIZE = 2 * 1024 * 1024;
const PROFILE_PHOTO_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);

export function profilePhotoDraftKey(profileKey) {
    return `system-profile-photo-draft:${profileKey}`;
}

export function saveProfilePhotoDraft(storage, key, file, savedAt = Date.now()) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => {
            if (typeof reader.result !== 'string') {
                reject(new Error('No se pudo leer la fotografía seleccionada.'));

                return;
            }

            try {
                storage.setItem(key, JSON.stringify({
                    name: file.name,
                    type: file.type,
                    lastModified: file.lastModified,
                    dataUrl: reader.result,
                    savedAt,
                }));

                resolve();
            } catch (error) {
                reject(error);
            }
        };

        reader.onerror = () => {
            reject(reader.error ?? new Error('No se pudo leer la fotografía seleccionada.'));
        };

        reader.readAsDataURL(file);
    });
}

export function loadProfilePhotoDraft(storage, key, now = Date.now()) {
    const storedDraft = storage.getItem(key);

    if (storedDraft === null) {
        return null;
    }

    try {
        const draft = JSON.parse(storedDraft);
        const isValidDraft = typeof draft.name === 'string'
            && typeof draft.type === 'string'
            && PROFILE_PHOTO_TYPES.has(draft.type)
            && Number.isSafeInteger(draft.lastModified)
            && typeof draft.dataUrl === 'string'
            && draft.dataUrl.length <= 3_000_000
            && draft.dataUrl.startsWith(`data:${draft.type};base64,`)
            && Number.isSafeInteger(draft.savedAt)
            && now >= draft.savedAt
            && now - draft.savedAt <= PROFILE_PHOTO_DRAFT_MAX_AGE_MS;

        if (isValidDraft) {
            return draft;
        }

        storage.removeItem(key);

        return null;
    } catch (error) {
        storage.removeItem(key);
        console.error('No se pudo recuperar el borrador de la fotografía del perfil.', error);

        return null;
    }
}

export async function restoreProfilePhotoDraft(draft, input) {
    const response = await fetch(draft.dataUrl);

    if (!response.ok) {
        throw new Error('No se pudo recuperar el archivo de fotografía seleccionado.');
    }

    const photoBlob = await response.blob();

    if (photoBlob.size > PROFILE_PHOTO_MAX_SIZE) {
        throw new Error('La fotografía seleccionada supera el tamaño máximo permitido.');
    }

    const photoFile = new File([photoBlob], draft.name, {
        type: draft.type,
        lastModified: draft.lastModified,
    });
    const transfer = new DataTransfer();

    transfer.items.add(photoFile);
    input.files = transfer.files;
    input.dataset.profilePhotoDraftRestored = 'true';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}
