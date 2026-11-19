import Alpine from 'alpinejs';
import { capitalizeInitialLetter } from './capitalize-initial-letter';
import {
    loadProfilePhotoDraft,
    profilePhotoDraftKey,
    restoreProfilePhotoDraft,
    saveProfilePhotoDraft,
} from './profile-photo-draft';
import {
    buildSuggestedUsername,
    normalizeSuggestedCredentialPart,
} from './suggested-credentials';

const toastTimers = new Map();

Alpine.store('toasts', {
    items: [],
    nextId: 1,

    push(payload = {}) {
        const message = String(payload.message ?? '').trim();

        if (message === '') {
            return;
        }

        const type = payload.type ?? 'info';

        if (this.items.some((toast) => toast.type === type && toast.message === message)) {
            return;
        }

        const defaultDuration = ['error', 'warning'].includes(type) ? 9000 : 6000;

        // Los mensajes largos (por ejemplo, claves temporales) permanecen
        // visibles hasta que el usuario los cierre.
        const duration = payload.duration ?? (message.length > 160 ? 0 : defaultDuration);
        const id = this.nextId++;

        this.items.push({ id, type, message, duration });

        if (duration > 0) {
            toastTimers.set(id, setTimeout(() => this.dismiss(id), duration));
        }
    },

    pushMany(messages = []) {
        messages.forEach((message) => this.push(message));
    },

    dismiss(id) {
        const timer = toastTimers.get(id);

        if (timer) {
            clearTimeout(timer);
            toastTimers.delete(id);
        }

        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

window.Alpine = Alpine;
window.buildSuggestedUsername = buildSuggestedUsername;
window.normalizeSuggestedCredentialPart = normalizeSuggestedCredentialPart;

Alpine.start();
initializeProfilePhotoDrafts();

const authenticatedPortal = document.body.dataset.authenticatedPortal;
const loggedOutPortal = getLoggedOutPortal();

if (authenticatedPortal && loggedOutPortal === authenticatedPortal) {
    try {
        sessionStorage.removeItem('system-logged-out-portal');
    } catch (error) {
        console.error('No se pudo limpiar el estado de cierre de sesión.', error);
    }
}

window.addEventListener('pageshow', (event) => {
    const portal = document.body.dataset.authenticatedPortal
        || getLoggedOutPortal();

    if (!event.persisted || !portal) {
        return;
    }

    document.documentElement.style.visibility = 'hidden';

    if (getLoggedOutPortal() === portal) {
        window.location.replace(portal === 'student' ? '/estudiante/login' : '/login');
        return;
    }

    window.location.reload();
});

function getLoggedOutPortal() {
    try {
        return sessionStorage.getItem('system-logged-out-portal');
    } catch (error) {
        console.error('No se pudo consultar el estado de cierre de sesión.', error);

        return null;
    }
}

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.logoutForm) {
        return;
    }

    event.preventDefault();

    if (form.dataset.logoutPending === 'true') {
        return;
    }

    const portal = form.dataset.logoutForm;

    form.dataset.logoutPending = 'true';
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        button.disabled = true;
    });

    try {
        sessionStorage.setItem('system-logged-out-portal', portal);
    } catch (error) {
        console.error('No se pudo guardar el estado de cierre de sesión.', error);
    }

    try {
        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok || !response.redirected) {
            throw new Error('El servidor no confirmó el cierre de sesión.');
        }

        const destination = new URL(response.url, window.location.href);

        if (destination.origin !== window.location.origin) {
            throw new Error('El cierre de sesión devolvió un destino no permitido.');
        }

        window.location.replace(destination.href);
    } catch (error) {
        console.error('No se pudo completar el cierre de sesión con navegación segura.', error);
        HTMLFormElement.prototype.submit.call(form);
    }
});

document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.name !== 'foto_personal') {
        return;
    }

    if (input.dataset.profilePhotoDraftRestored === 'true') {
        delete input.dataset.profilePhotoDraftRestored;

        return;
    }

    const form = input.form;
    const profileKey = form?.dataset.profilePhotoDraftKey;

    if (!profileKey) {
        return;
    }

    const key = profilePhotoDraftKey(profileKey);
    const file = input.files?.[0];

    if (!file) {
        try {
            sessionStorage.removeItem(key);
        } catch (error) {
            console.error('No se pudo limpiar el borrador de la fotografía del perfil.', error);
        }

        return;
    }

    persistProfilePhotoDraft(key, file)
        .catch((error) => console.error('No se pudo guardar temporalmente la fotografía del perfil.', error));
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.profilePhotoDraftKey) {
        return;
    }

    if (form.dataset.profilePhotoDraftSubmission === 'ready') {
        delete form.dataset.profilePhotoDraftSubmission;

        return;
    }

    const input = form.querySelector('input[type="file"][name="foto_personal"]');
    const file = input?.files?.[0];

    if (!file) {
        return;
    }

    event.preventDefault();

    const continueSubmission = () => {
        form.dataset.profilePhotoDraftSubmission = 'ready';

        if (event.submitter instanceof HTMLElement) {
            form.requestSubmit(event.submitter);

            return;
        }

        form.requestSubmit();
    };

    persistProfilePhotoDraft(profilePhotoDraftKey(form.dataset.profilePhotoDraftKey), file)
        .then(continueSubmission).catch((error) => {
            console.error('No se pudo guardar temporalmente la fotografía del perfil.', error);
            continueSubmission();
        });
});

document.addEventListener('input', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    if (input.hasAttribute('data-capitalize-first')) {
        capitalizeInitialLetter(input);
    }

    if (input.hasAttribute('data-email-domain')) {
        normalizeEmailDomainInput(input);
    }
}, true);

document.querySelectorAll('input[data-email-domain]').forEach(normalizeEmailDomainInput);

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelectorAll('input[data-email-domain]').forEach((input) => {
        const address = input.value.trim();
        const domain = input.dataset.emailDomain;

        if (address !== '' && domain && !address.includes('@')) {
            input.value = `${address}@${domain}`;
        }
    });
});

function normalizeEmailDomainInput(input) {
    const domain = input.dataset.emailDomain;

    if (!domain) {
        return;
    }

    const value = input.value.trim();
    const suffix = `@${domain}`;

    if (value.toLowerCase().endsWith(suffix.toLowerCase())) {
        input.value = value.slice(0, -suffix.length);
    }

    const suffixElement = input.closest('[data-email-domain-field]')?.querySelector('[data-email-domain-suffix]');
    suffixElement?.classList.toggle('hidden', input.value.includes('@'));
}

function initializeProfilePhotoDrafts() {
    document.querySelectorAll('form[data-profile-photo-draft-key]').forEach((form) => {
        const key = profilePhotoDraftKey(form.dataset.profilePhotoDraftKey);

        try {
            if (form.dataset.profilePhotoUpdated === 'true') {
                sessionStorage.removeItem(key);

                return;
            }

            const draft = loadProfilePhotoDraft(sessionStorage, key);

            if (!draft) {
                return;
            }

            const input = form.querySelector('input[type="file"][name="foto_personal"]');

            if (!input) {
                return;
            }

            restoreProfilePhotoDraft(draft, input).catch((error) => {
                console.error('No se pudo restaurar la fotografía seleccionada del perfil.', error);

                try {
                    sessionStorage.removeItem(key);
                } catch (storageError) {
                    console.error('No se pudo limpiar el borrador de la fotografía del perfil.', storageError);
                }
            });
        } catch (error) {
            console.error('No se pudo recuperar el borrador de la fotografía del perfil.', error);
        }
    });
}

function persistProfilePhotoDraft(key, file) {
    try {
        return saveProfilePhotoDraft(sessionStorage, key, file);
    } catch (error) {
        return Promise.reject(error);
    }
}

/**
 * Abre el portal estudiantil reutilizando siempre la misma ventana.
 */
window.abrirQrEstudiante = function (url) {
    const ventana = window.open(url, 'estudianteQrWindow');

    if (ventana) {
        ventana.focus();
        return;
    }

    window.location.href = url;
};

/**
 * Cierra la ventana del QR cuando fue abierta desde el login.
 * Si se accedió directamente, vuelve al login en la misma pestaña.
 */
window.volverAlLoginDesdeQr = function (loginUrl) {
    const abiertaDesdeLogin = (window.opener && !window.opener.closed)
        || window.name === 'estudianteQrWindow';

    if (!abiertaDesdeLogin) {
        window.location.href = loginUrl;
        return;
    }

    window.close();

    window.setTimeout(function () {
        if (!window.closed) {
            window.location.href = loginUrl;
        }
    }, 150);
};

const QR_FONT_STACK = 'Manrope, "Segoe UI", system-ui, -apple-system, sans-serif';

function qrRoundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);

    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}

function qrCargarImagen(src) {
    return new Promise((resolve, reject) => {
        const imagen = new Image();
        imagen.onload = () => resolve(imagen);
        imagen.onerror = () => reject(new Error('No se pudo cargar el código QR.'));
        imagen.src = src;
    });
}

function qrAjustarTexto(ctx, texto, anchoMaximo, tamanoMaximo, tamanoMinimo, peso = '700') {
    let tamano = tamanoMaximo;

    while (tamano > tamanoMinimo) {
        ctx.font = `${peso} ${tamano}px ${QR_FONT_STACK}`;

        if (ctx.measureText(texto).width <= anchoMaximo) {
            break;
        }

        tamano -= 1;
    }

    return tamano;
}

/**
 * Genera una tarjeta PNG estética (no tamaño carta) con el QR y los datos del portal.
 */
window.descargarQrEstudiante = async function ({
    qrSrc,
    portalUrl = '',
    filename = 'qr-acceso-estudiantil.png',
    button = null,
} = {}) {
    if (!qrSrc) {
        window.alert('El código QR todavía no está disponible.');
        return;
    }

    const etiquetaOriginal = button ? button.innerHTML : null;
    const textoOriginal = button ? button.textContent : null;

    if (button) {
        button.disabled = true;
        button.textContent = 'Generando imagen...';
    }

    try {
        if (document.fonts?.ready) {
            await document.fonts.ready;
        }

        const imagenQr = await qrCargarImagen(qrSrc);
        const canvas = document.createElement('canvas');
        canvas.width = 1000;
        canvas.height = 1250;

        const ctx = canvas.getContext('2d');

        const fondo = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
        fondo.addColorStop(0, '#050b1f');
        fondo.addColorStop(0.45, '#0b2a5b');
        fondo.addColorStop(1, '#211c4f');
        ctx.fillStyle = fondo;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const brilloSuperior = ctx.createRadialGradient(180, 40, 0, 180, 40, 470);
        brilloSuperior.addColorStop(0, 'rgba(34, 211, 238, 0.28)');
        brilloSuperior.addColorStop(1, 'rgba(34, 211, 238, 0)');
        ctx.fillStyle = brilloSuperior;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const brilloInferior = ctx.createRadialGradient(900, 1180, 0, 900, 1180, 520);
        brilloInferior.addColorStop(0, 'rgba(99, 102, 241, 0.32)');
        brilloInferior.addColorStop(1, 'rgba(99, 102, 241, 0)');
        ctx.fillStyle = brilloInferior;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.strokeStyle = 'rgba(148, 163, 184, 0.06)';
        ctx.lineWidth = 1;

        for (let x = 0; x <= canvas.width; x += 50) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, canvas.height);
            ctx.stroke();
        }

        for (let y = 0; y <= canvas.height; y += 50) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(canvas.width, y);
            ctx.stroke();
        }

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        if ('letterSpacing' in ctx) {
            ctx.letterSpacing = '5px';
        }

        ctx.fillStyle = '#67e8f9';
        ctx.font = `800 22px ${QR_FONT_STACK}`;
        ctx.fillText('INSTITUTO TECNOLÓGICO «ITIBB»', 500, 96);

        if ('letterSpacing' in ctx) {
            ctx.letterSpacing = '0px';
        }

        ctx.fillStyle = '#ffffff';
        ctx.font = `900 66px ${QR_FONT_STACK}`;
        ctx.fillText('Acceso Estudiantil', 500, 176);

        ctx.fillStyle = '#cbd5e1';
        ctx.font = `500 26px ${QR_FONT_STACK}`;
        ctx.fillText('Escanea este código para ingresar al Portal del Estudiante', 500, 232);

        const tarjeta = { x: 100, y: 292, width: 800, height: 790, radius: 52 };

        ctx.save();
        ctx.shadowColor = 'rgba(2, 6, 23, 0.55)';
        ctx.shadowBlur = 70;
        ctx.shadowOffsetY = 28;
        ctx.fillStyle = '#ffffff';
        qrRoundRect(ctx, tarjeta.x, tarjeta.y, tarjeta.width, tarjeta.height, tarjeta.radius);
        ctx.fill();
        ctx.restore();

        const borde = ctx.createLinearGradient(
            tarjeta.x,
            tarjeta.y,
            tarjeta.x + tarjeta.width,
            tarjeta.y + tarjeta.height,
        );
        borde.addColorStop(0, 'rgba(34, 211, 238, 0.95)');
        borde.addColorStop(0.5, 'rgba(255, 255, 255, 0.55)');
        borde.addColorStop(1, 'rgba(129, 140, 248, 0.95)');
        ctx.strokeStyle = borde;
        ctx.lineWidth = 3;
        qrRoundRect(ctx, tarjeta.x, tarjeta.y, tarjeta.width, tarjeta.height, tarjeta.radius);
        ctx.stroke();

        const tamanoQr = 610;
        const qrX = (canvas.width - tamanoQr) / 2;
        const qrY = tarjeta.y + (tarjeta.height - tamanoQr) / 2;
        ctx.drawImage(imagenQr, qrX, qrY, tamanoQr, tamanoQr);

        ctx.fillStyle = '#e2e8f0';
        ctx.font = `600 27px ${QR_FONT_STACK}`;
        ctx.fillText('Apunta con la cámara de tu celular o tablet', 500, 1128);

        const pildora = { x: 70, y: 1158, width: 860, height: 58, radius: 29 };
        ctx.fillStyle = 'rgba(255, 255, 255, 0.10)';
        qrRoundRect(ctx, pildora.x, pildora.y, pildora.width, pildora.height, pildora.radius);
        ctx.fill();

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.20)';
        ctx.lineWidth = 1.5;
        qrRoundRect(ctx, pildora.x, pildora.y, pildora.width, pildora.height, pildora.radius);
        ctx.stroke();

        const textoUrl = portalUrl || 'Portal del Estudiante';
        const tamanoUrl = qrAjustarTexto(ctx, textoUrl, 810, 22, 12, '600');
        ctx.fillStyle = '#e0f2fe';
        ctx.font = `600 ${tamanoUrl}px ${QR_FONT_STACK}`;
        ctx.fillText(textoUrl, 500, 1188);

        ctx.fillStyle = '#94a3b8';
        ctx.font = `500 20px ${QR_FONT_STACK}`;
        ctx.fillText('Gestión de equipos de cómputo · Informática Industrial', 500, 1240);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

        if (!blob) {
            throw new Error('No se pudo generar el archivo PNG.');
        }

        const enlace = document.createElement('a');
        const objectUrl = URL.createObjectURL(blob);
        enlace.href = objectUrl;
        enlace.download = filename;
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (error) {
        console.error(error);
        window.alert('No se pudo generar la imagen del QR. Intenta nuevamente.');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = etiquetaOriginal ?? textoOriginal ?? 'Descargar imagen';
        }
    }
};
