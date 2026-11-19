<div
    x-data="{
        open: false,
        sending: false,
        accepted: false,
        errorMessage: '',
        async requestShutdown() {
            this.sending = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('system.shutdown') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    }
                });
                const result = await response.json();

                if (!response.ok) {
                    this.errorMessage = result.message || 'No se pudo solicitar la finalización.';
                    return;
                }

                this.accepted = true;
            } catch (error) {
                this.errorMessage = 'No se pudo conectar con el ayudante local. El sistema continúa activo.';
            } finally {
                this.sending = false;
            }
        }
    }"
>
    <button
        type="button"
        @click="open = true; accepted = false; errorMessage = ''"
        class="group flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-3 text-sm font-bold text-rose-700 shadow-sm transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-2"
        aria-label="Finalizar sistema"
        title="Finalizar sistema"
    >
        <x-icon name="logout" class="h-4 w-4 transition group-hover:translate-x-0.5" />
        <span class="hidden xl:inline">Finalizar sistema</span>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        @keydown.escape.window="if (!sending && !accepted) open = false"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="system-shutdown-title"
    >
        <section
            x-show="open"
            x-transition
            @click.stop
            class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
        >
            <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 px-6 py-5 text-white">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-500/15 text-rose-300 ring-1 ring-rose-300/25">
                        <x-icon name="warning" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-200">Control del servidor</p>
                        <h2 id="system-shutdown-title" class="mt-1 text-lg font-black">¿Finalizar el sistema?</h2>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-6">
                <p x-show="!accepted" class="text-sm leading-6 text-slate-600">
                    Se detendrán Laravel, las tareas programadas y Apache/MySQL de XAMPP. La ventana dedicada del sistema se cerrará; Cloudflare no se detendrá.
                </p>

                <div x-cloak x-show="accepted" class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status" aria-live="polite">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </span>
                    <span>Solicitud recibida. El servidor está finalizando el sistema. La ventana dedicada de Edge se cerrará en la computadora servidor; si solicitaste el cierre desde otro dispositivo, esa pestaña no puede cerrarse automáticamente.</span>
                </div>

                <p x-cloak x-show="errorMessage" x-text="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert"></p>

                <div x-show="!accepted" class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        @click="open = false"
                        :disabled="sending"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="requestShutdown()"
                        :disabled="sending"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 px-5 text-sm font-black text-white shadow-lg shadow-rose-600/20 transition hover:from-rose-700 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-70"
                    >
                        <span x-show="sending" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                        <span x-text="sending ? 'Enviando…' : 'Finalizar sistema'"></span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>
