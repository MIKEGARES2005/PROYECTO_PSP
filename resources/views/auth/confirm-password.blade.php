<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-[#272F7A]">Confirma tu contraseña</h2>
        <p class="mt-2 text-sm text-gray-600">
            Esta es una zona segura del sistema. Confirma tu contraseña antes de continuar.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5" x-data="{ passwordVisible: false }">
        @csrf

        <div>
            <x-input-label for="password" value="Contraseña" />
            <div class="relative mt-1">
                <x-text-input
                    id="password"
                    class="block w-full pr-12"
                    type="password"
                    x-bind:type="passwordVisible ? 'text' : 'password'"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <button type="button" @click="passwordVisible = !passwordVisible" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500 transition hover:text-[#272F7A]" x-bind:aria-label="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:title="passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:aria-pressed="passwordVisible">
                    <x-icon name="eye" x-show="!passwordVisible" class="h-5 w-5" />
                    <x-icon name="eye-off" x-cloak x-show="passwordVisible" class="h-5 w-5" />
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>
                Confirmar
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
