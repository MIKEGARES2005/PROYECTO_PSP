@if (session('staff_login_welcome'))
    @php($esAdministradora = auth()->user()?->tipo_usuario === 'administrador')

    <div
        x-data="{
            visible: true,
            timer: null,
            finish() {
                window.clearTimeout(this.timer);
                this.visible = false;
                window.dispatchEvent(new CustomEvent('staff-welcome-finished'));
            }
        }"
        x-init="timer = window.setTimeout(() => finish(), 1400)"
        x-on:keydown.escape.window="finish()"
        x-show="visible"
        x-transition.opacity.duration.200ms
        x-cloak
        class="motion-reduce:transition-none fixed inset-0 z-[100] flex items-center justify-center overflow-hidden bg-slate-950/85 px-4 py-8"
        role="status"
        aria-live="polite"
        aria-labelledby="staff-login-welcome-title"
    >
        <section class="staff-welcome-card relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/15 bg-white px-7 py-10 text-center shadow-2xl shadow-slate-950/40 sm:px-12 sm:py-14">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[1.75rem] {{ $esAdministradora ? 'bg-gradient-to-br from-cyan-400 to-indigo-600' : 'bg-gradient-to-br from-sky-400 to-blue-700' }} text-white shadow-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    @if ($esAdministradora)
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 14.6 9l5.8.85-4.2 4.1.99 5.8L12 17l-5.19 2.75.99-5.8-4.2-4.1L9.4 9 12 3.75Z" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8.25 4.5v5.25c0 4.5-3.15 7.5-8.25 9.75-5.1-2.25-8.25-5.25-8.25-9.75V7.5L12 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.75 12 2.1 2.1 4.4-4.45" />
                    @endif
                </svg>
            </div>

            <p class="mt-7 text-xs font-black uppercase tracking-[0.24em] {{ $esAdministradora ? 'text-indigo-600' : 'text-sky-700' }}">
                Acceso autorizado · ITIBB
            </p>
            <h1 id="staff-login-welcome-title" class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                ¡Bienvenido al sistema!
            </h1>
            <p class="mt-5 text-lg font-semibold text-slate-600">
                {{ $esAdministradora ? 'Estimada administradora' : 'Estimado docente' }}
            </p>
            <p class="mt-1 break-words text-2xl font-black {{ $esAdministradora ? 'text-indigo-700' : 'text-sky-700' }}">
                {{ auth()->user()?->name }}
            </p>
            <p class="mx-auto mt-4 max-w-sm text-sm leading-6 text-slate-500">
                Tu espacio de trabajo está listo. Te llevamos a tu panel de inicio.
            </p>

            <button
                type="button"
                x-on:click="finish()"
                class="mt-8 inline-flex items-center justify-center gap-2 rounded-xl {{ $esAdministradora ? 'bg-indigo-700 hover:bg-indigo-800 focus-visible:outline-indigo-600' : 'bg-sky-700 hover:bg-sky-800 focus-visible:outline-sky-600' }} px-6 py-3 text-sm font-bold text-white shadow-lg transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Continuar al panel
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m-6.75-6.75L19.5 12l-6.75 6.75" />
                </svg>
            </button>

            <div class="absolute inset-x-0 bottom-0 h-1.5 bg-slate-100" aria-hidden="true">
                <div class="staff-welcome-progress h-full {{ $esAdministradora ? 'bg-gradient-to-r from-cyan-400 to-indigo-600' : 'bg-gradient-to-r from-sky-400 to-blue-700' }}"></div>
            </div>
        </section>
    </div>
@endif
