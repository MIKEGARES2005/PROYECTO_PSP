<x-admin-layout>
    @php
        $docenteNotificacionesIniciales = ($docenteNotificaciones ?? collect())
            ->map(fn ($notificacion): array => [
                'id' => $notificacion->id,
                'titulo' => $notificacion->titulo,
                'mensaje' => $notificacion->mensaje,
                'url' => $notificacion->url ?: route('dashboard'),
                'leida' => $notificacion->leida_en !== null,
                'creada' => $notificacion->created_at?->diffForHumans(),
            ])
            ->values();
        $docenteNotificacionesNoLeidas ??= 0;
    @endphp

    <div
        class="min-h-screen bg-[#F3F8FF] text-slate-800"
        x-data="{
            sidebarOpen: false,
            profileOpen: false,
            notificationsOpen: false,
            qrOpen: false,
            notifications: @js($docenteNotificacionesIniciales),
            unreadNotifications: {{ $docenteNotificacionesNoLeidas }},
            notificationError: null,
            async refreshNotifications() {
                try {
                    const response = await fetch('{{ route('admin.notifications.index') }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!response.ok) {
                        throw new Error('No se pudieron consultar las notificaciones.');
                    }
                    const data = await response.json();
                    this.notifications = data.notificaciones.map(notification => ({
                        id: notification.id,
                        titulo: notification.titulo,
                        mensaje: notification.mensaje,
                        creada: notification.creada,
                        leida: notification.leida,
                        url: notification.url || '{{ route('dashboard') }}'
                    }));
                    this.unreadNotifications = data.no_leidas;
                    this.notificationError = null;
                } catch (error) {
                    this.notificationError = 'No se pudieron cargar las notificaciones. Intenta de nuevo.';
                }
            },
            async clearNotifications() {
                try {
                    const response = await fetch('{{ route('admin.notifications.clear') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) {
                        throw new Error('No se pudo limpiar la bandeja.');
                    }
                    const data = await response.json();
                    this.notifications = data.notificaciones;
                    this.unreadNotifications = data.no_leidas;
                    this.notificationError = null;
                } catch (error) {
                    this.notificationError = 'No se pudo limpiar la bandeja. Intenta de nuevo.';
                }
            }
        }"
        x-init="refreshNotifications(); setInterval(() => refreshNotifications(), 15000)"
    >
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="dashboard-orb dashboard-orb-blue absolute -left-48 -top-48 h-[32rem] w-[32rem] rounded-full blur-2xl"></div>
            <div class="dashboard-orb dashboard-orb-cyan absolute -bottom-56 right-0 h-[34rem] w-[34rem] rounded-full blur-2xl"></div>
            <div class="dashboard-grid absolute inset-0 opacity-50"></div>
        </div>

        <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false" aria-hidden="true"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col overflow-hidden bg-gradient-to-b from-[#1D74C9] via-[#145FAE] to-[#0C4387] text-white shadow-2xl shadow-sky-950/30 transition-transform duration-300 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Navegación docente"
        >
            <div class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-sky-200/20 blur-3xl" aria-hidden="true"></div>
            <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-20" aria-hidden="true"></div>

            <div class="relative shrink-0 border-b border-white/15 px-5 py-5">
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('dashboard') }}" class="group flex items-center gap-3">
                        <span class="relative flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-white via-sky-100 to-sky-300 text-sm font-black text-sky-800 shadow-lg shadow-sky-950/20 transition duration-300 group-hover:rotate-3 group-hover:scale-105">
                            IT
                            <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-sky-700 bg-emerald-300"></span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-black tracking-[0.14em] text-white">ITIBB</span>
                            <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.18em] text-sky-100/90">Panel docente</span>
                        </span>
                    </a>
                    <button type="button" @click="sidebarOpen = false" class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20 lg:hidden" aria-label="Cerrar menú">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-5 flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3 py-2.5">
                    <span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.9)]"></span>
                    <span class="text-[11px] font-semibold text-sky-50">Sesión docente activa</span>
                </div>
            </div>

            <nav class="dashboard-scrollbar relative flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Pestañas del docente" x-init="$nextTick(() => { $el.scrollTop = Number(sessionStorage.getItem('itibb-navigation-scroll') || 0) })" @scroll.passive="sessionStorage.setItem('itibb-navigation-scroll', $el.scrollTop)">
                @include('components.docente-navigation')
            </nav>

            <div class="relative shrink-0 border-t border-white/15 px-5 py-4">
                <p class="text-xs font-semibold text-sky-50">Sistema local de laboratorio</p>
                <p class="mt-1 text-xs text-sky-100/75">ITIBB · Informática Industrial</p>
            </div>
        </aside>

        <div class="relative min-h-screen lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-sky-100/80 bg-white/90 px-4 py-3 backdrop-blur-xl sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="sidebarOpen = true" :aria-expanded="sidebarOpen" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-sky-100 bg-white text-sky-700 shadow-sm transition hover:border-sky-300 lg:hidden" aria-label="Abrir menú">
                            <x-icon name="menu" class="h-5 w-5" />
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-sky-600">Panel de control</p>
                            <h1 class="truncate text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Centro docente ITIBB</h1>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                        <div class="relative" @click.outside="notificationsOpen = false">
                            <button
                                type="button"
                                @click="notificationsOpen = !notificationsOpen"
                                :aria-expanded="notificationsOpen"
                                class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-violet-200 bg-gradient-to-br from-white to-violet-50 text-violet-700 shadow-sm transition hover:border-fuchsia-300 hover:from-violet-50 hover:to-fuchsia-50 hover:text-fuchsia-700"
                                aria-label="Notificaciones"
                                title="Notificaciones"
                            >
                                <x-icon name="bell" class="h-5 w-5" />
                                <span
                                    x-cloak
                                    x-show="unreadNotifications > 0"
                                    x-text="unreadNotifications > 9 ? '9+' : unreadNotifications"
                                    class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-gradient-to-r from-fuchsia-500 to-rose-500 px-1 text-[10px] font-black text-white ring-2 ring-white shadow-sm shadow-fuchsia-500/30"
                                ></span>
                            </button>

                            <div
                                x-cloak
                                x-show="notificationsOpen"
                                x-transition
                                class="absolute right-0 top-12 z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-violet-100 bg-white shadow-2xl shadow-violet-950/20"
                                role="region"
                                aria-label="Bandeja de notificaciones"
                            >
                                <div class="border-b border-violet-100 bg-gradient-to-r from-violet-50 via-fuchsia-50 to-white px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-800">Notificaciones</p>
                                            <p class="mt-0.5 text-[11px] text-slate-500">Actividad reciente de tu cuenta</p>
                                        </div>
                                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-fuchsia-600 shadow-sm ring-1 ring-fuchsia-100">
                                            <x-icon name="bell" class="h-4 w-4" />
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearNotifications()"
                                        :disabled="notifications.length === 0"
                                        class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-white px-3 py-2 text-[11px] font-bold text-rose-600 shadow-sm ring-1 ring-rose-100 transition hover:bg-rose-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-rose-600"
                                    >
                                        Limpiar bandeja
                                    </button>
                                </div>
                                <p x-cloak x-show="notificationError" x-text="notificationError" class="border-b border-rose-100 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700" role="alert"></p>
                                <div class="max-h-80 overflow-y-auto">
                                    <template x-for="notification in notifications" :key="notification.id">
                                        <a
                                            :href="notification.url || '{{ route('dashboard') }}'"
                                            @click="notificationsOpen = false"
                                            class="group flex gap-3 border-b border-slate-50 px-4 py-3 transition hover:bg-violet-50/70"
                                        >
                                            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100 transition group-hover:bg-white">
                                                <x-icon name="bell" class="h-4 w-4" />
                                                <span x-show="!notification.leida" class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-fuchsia-500 ring-2 ring-white"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-xs font-bold text-slate-800" x-text="notification.titulo"></span>
                                                <span class="mt-1 block text-xs leading-5 text-slate-500" x-text="notification.mensaje"></span>
                                                <span class="mt-1 block text-[10px] font-medium text-slate-400" x-text="notification.creada"></span>
                                            </span>
                                        </a>
                                    </template>
                                    <div x-cloak x-show="notifications.length === 0" class="px-4 py-8 text-center">
                                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-400 ring-1 ring-violet-100">
                                            <x-icon name="bell" class="h-5 w-5" />
                                        </span>
                                        <p class="mt-2 text-xs font-semibold text-slate-500">Tu bandeja está al día</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="qrOpen = true"
                            class="group flex h-10 items-center justify-center gap-2 rounded-xl border border-violet-500 bg-gradient-to-r from-violet-600 via-fuchsia-600 to-rose-500 px-3 text-sm font-bold text-white shadow-md shadow-fuchsia-700/20 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-fuchsia-700/30 focus:outline-none focus:ring-2 focus:ring-fuchsia-300 focus:ring-offset-2"
                            aria-label="Abrir acceso estudiantil QR"
                            title="Acceso estudiantil QR"
                        >
                            <x-icon name="qr-code" class="h-4 w-4 transition group-hover:scale-110" />
                            <span class="hidden xl:inline">Acceso estudiantil QR</span>
                        </button>

                        <x-system-shutdown-control />

                        <div class="relative" @click.outside="profileOpen = false">
                            <button type="button" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 shadow-sm transition hover:border-sky-200">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 text-sm font-black text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="hidden text-left sm:block">
                                    <span class="block max-w-[9rem] truncate text-xs font-bold text-slate-800">{{ auth()->user()->name }}</span>
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">Docente</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden h-3.5 w-3.5 text-slate-400 sm:block" />
                            </button>

                            <div x-cloak x-show="profileOpen" x-transition class="absolute right-0 top-12 z-50 w-60 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/20">
                                <div class="border-b border-slate-100 px-3 py-2">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ auth()->user()->name }}</p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ auth()->user()->username }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-sky-50 hover:text-sky-700">
                                    <x-icon name="cog" class="h-4 w-4" />
                                    Mi cuenta
                                </a>
                                @if (\Illuminate\Support\Facades\Route::has('security.questions.edit'))
                                    <a href="{{ route('security.questions.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-sky-50 hover:text-sky-700">
                                        <x-icon name="key" class="h-4 w-4" />
                                        Preguntas de seguridad
                                    </a>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('logout') }}" data-logout-form="staff">
                            @csrf
                            <button type="submit" class="flex h-10 items-center justify-center gap-2 rounded-xl border border-rose-100 bg-white px-3 text-sm font-bold text-rose-600 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" aria-label="Cerrar sesión" title="Cerrar sesión">
                                <x-icon name="logout" class="h-4 w-4" />
                                <span class="hidden sm:inline">Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div
                x-cloak
                x-show="qrOpen"
                x-transition.opacity
                @keydown.escape.window="qrOpen = false"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="teacher-qr-title"
                @click.self="qrOpen = false"
            >
                <div x-show="qrOpen" x-transition class="relative flex h-[min(90vh,850px)] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <div class="flex shrink-0 items-center justify-between gap-4 bg-gradient-to-r from-violet-950 via-fuchsia-950 to-slate-950 px-5 py-4 text-white sm:px-7">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-fuchsia-200">Portal del estudiante</p>
                            <h2 id="teacher-qr-title" class="mt-1 text-lg font-black sm:text-xl">Acceso estudiantil QR</h2>
                        </div>
                        <button type="button" @click="qrOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-fuchsia-100 transition hover:bg-white/10 hover:text-white" aria-label="Cerrar acceso estudiantil QR">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </div>
                    <iframe src="{{ route('estudiante.qr', ['embed' => 1]) }}" title="Código QR de acceso al portal estudiantil" class="min-h-0 flex-1 w-full border-0 bg-slate-950"></iframe>
                </div>
            </div>

            <main class="relative space-y-6 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-admin-layout>
