<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">
            Nueva contraseña
        </h2>
        <p class="text-sm text-gray-600 mt-1">
            Las respuestas fueron verificadas correctamente.
            Ahora puedes establecer una nueva contraseña.
        </p>
    </div>
    <form method="POST" action="{{ route('password.security.reset') }}" x-data="{ nuevaVisible: false, confirmacionVisible: false }">
        @csrf
        <div>
            <x-input-label for="password" :value="__('Nueva contraseña')" />
            <div class="relative mt-1">
                <x-text-input
                    id="password"
                    class="block w-full pr-12"
                    type="password"
                    x-bind:type="nuevaVisible ? 'text' : 'password'"
                    name="password"
                    required
                    autofocus
                    autocomplete="new-password"
                />
                <button type="button" @click="nuevaVisible = !nuevaVisible" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-gray-500 transition hover:text-[#272F7A]" x-bind:aria-label="nuevaVisible ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'" x-bind:title="nuevaVisible ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'" x-bind:aria-pressed="nuevaVisible">
                    <x-icon name="eye" x-show="!nuevaVisible" class="h-5 w-5" />
                    <x-icon name="eye-off" x-cloak x-show="nuevaVisible" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label
                for="password_confirmation"
                :value="__('Confirmar nueva contraseña')"
            />
            <div class="relative mt-1">
                <x-text-input
                    id="password_confirmation"
                    class="block w-full pr-12"
                    type="password"
                    x-bind:type="confirmacionVisible ? 'text' : 'password'"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <button type="button" @click="confirmacionVisible = !confirmacionVisible" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-gray-500 transition hover:text-[#272F7A]" x-bind:aria-label="confirmacionVisible ? 'Ocultar confirmación' : 'Mostrar confirmación'" x-bind:title="confirmacionVisible ? 'Ocultar confirmación' : 'Mostrar confirmación'" x-bind:aria-pressed="confirmacionVisible">
                    <x-icon name="eye" x-show="!confirmacionVisible" class="h-5 w-5" />
                    <x-icon name="eye-off" x-cloak x-show="confirmacionVisible" class="h-5 w-5" />
                </button>
            </div>
            <x-input-error
                :messages="$errors->get('password_confirmation')"
                class="mt-2"
            />
        </div>
        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Cambiar contraseña
            </x-primary-button>
        </div>
    </form>
    <p class="mt-6 border-t border-gray-200 pt-4 text-center text-xs text-gray-500">
        <a href="{{ route('login') }}" class="font-semibold text-[#272F7A] hover:underline">
            Acceso administrativo / docente
        </a>
    </p>
</x-guest-layout>
