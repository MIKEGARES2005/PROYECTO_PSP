<x-guest-estudiante-layout :showStudentLink="true">
    <x-toast-push
        type="error"
        :message="$errors->any() ? 'Por favor, introduzca los datos correctos.' : null"
    />

    <div class="mb-6 text-center">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-700">Nuevo ingreso</p>
        <h2 class="mt-2 text-xl font-extrabold tracking-tight text-slate-800">Solicita tu acceso</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Completa tus datos y un administrador revisará tu solicitud.</p>
    </div>

    <form method="POST" action="{{ route('registro.aspirante.store') }}" lang="es" class="space-y-5">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="nombres" value="Nombres" class="text-slate-700" />
                <x-text-input id="nombres" name="nombres" type="text" class="mt-1 block w-full" :value="old('nombres')" required autofocus data-capitalize-first autocapitalize="sentences" autocorrect="on" spellcheck="true" />
                @error('nombres') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="apellido_paterno" value="Apellido paterno" class="text-slate-700" />
                <x-text-input id="apellido_paterno" name="apellido_paterno" type="text" class="mt-1 block w-full" :value="old('apellido_paterno')" required data-capitalize-first autocapitalize="sentences" autocorrect="on" spellcheck="true" />
                @error('apellido_paterno') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="apellido_materno" value="Apellido materno" class="text-slate-700" />
                <x-text-input id="apellido_materno" name="apellido_materno" type="text" class="mt-1 block w-full" :value="old('apellido_materno')" data-capitalize-first autocapitalize="sentences" autocorrect="on" spellcheck="true" />
                @error('apellido_materno') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="telefono" value="Teléfono" class="text-slate-700" />
                <x-text-input id="telefono" name="telefono" type="tel" class="mt-1 block w-full" :value="old('telefono')" inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" autocapitalize="none" autocorrect="off" spellcheck="false" />
                @error('telefono') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
            <legend class="px-2 text-sm font-bold text-slate-700">Documento de identidad</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_10rem] sm:gap-4">
                <div>
                    <x-input-label for="ci" value="Cédula de identidad" class="text-slate-700" />
                    <x-text-input id="ci" name="ci" type="text" class="mt-1 block w-full bg-white" :value="old('ci')" required inputmode="numeric" pattern="[0-9]{5,15}" minlength="5" maxlength="15" autocapitalize="none" autocorrect="off" spellcheck="false" />
                    @error('ci') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="ci_extension" value="Extensión" class="text-slate-700" />
                    <select id="ci_extension" name="ci_extension" required class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-500/20">
                        @foreach (['LP', 'OR', 'PT', 'CB', 'SC', 'TJ', 'CH', 'BE', 'PD'] as $extension)
                            <option value="{{ $extension }}" @selected(old('ci_extension') === $extension)>{{ $extension }}</option>
                        @endforeach
                    </select>
                    @error('ci_extension') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <div>
            <x-input-label for="matricula" value="Matrícula (opcional)" class="text-slate-700" />
            <x-text-input id="matricula" name="matricula" type="text" class="mt-1 block w-full" :value="old('matricula')" autocapitalize="none" autocorrect="off" spellcheck="false" />
            <p class="mt-1 text-xs text-slate-500">Si todavía no tienes matrícula, puedes dejar este campo vacío.</p>
            @error('matricula') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-input-label for="correo_institucional" value="Correo institucional (opcional)" class="text-slate-700" />
            <div data-email-domain-field class="mt-1 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-emerald-600 focus-within:ring-1 focus-within:ring-emerald-600">
                <x-text-input id="correo_institucional" name="correo_institucional" type="text" inputmode="email" data-email-domain="itibb.edu.bo" class="min-w-0 flex-1 border-0 focus:ring-0" :value="old('correo_institucional')" placeholder="nombre@itibb.edu.bo" autocapitalize="none" autocorrect="off" spellcheck="false" />
                <span data-email-domain-suffix class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">@itibb.edu.bo</span>
            </div>
            @error('correo_institucional') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-input-label for="correo_personal" value="Correo personal" class="text-slate-700" />
            <div data-email-domain-field class="mt-1 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-emerald-600 focus-within:ring-1 focus-within:ring-emerald-600">
                <x-text-input id="correo_personal" name="correo_personal" type="text" inputmode="email" data-email-domain="gmail.com" class="min-w-0 flex-1 border-0 focus:ring-0" placeholder="nombre@gmail.com" :value="old('correo_personal')" autocapitalize="none" autocorrect="off" spellcheck="false" />
                <span data-email-domain-suffix class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">@gmail.com</span>
            </div>
            @error('correo_personal') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-slate-700">Dirección (opcional)</p>
            <x-student-address-fields :address="old('direccion')" />
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition hover:from-emerald-700 hover:to-teal-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
            Enviar solicitud de registro
        </button>
    </form>
</x-guest-estudiante-layout>
