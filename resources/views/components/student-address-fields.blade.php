@props(['address' => null])

@php
    $componentesDireccion = \App\Support\DireccionEstudiante::desglosar($address);
    $zona = old('direccion_zona', $componentesDireccion['zona']);
    $tipoVia = old('direccion_tipo_via', $componentesDireccion['tipo_via']);
    $via = old('direccion_via', $componentesDireccion['via']);
    $numeroPuerta = old('direccion_numero_puerta', $componentesDireccion['numero_puerta']);
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ tipoVia: @js($tipoVia), via: @js($via) }">
    <div>
        <label for="direccion_zona" class="mb-2 block text-sm font-semibold text-slate-700">Zona</label>
        <div class="flex rounded-xl shadow-sm">
            <span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm font-semibold text-slate-500">Zn.</span>
            <input id="direccion_zona" name="direccion_zona" value="{{ $zona }}" maxlength="80" autocomplete="address-level3" data-capitalize-first autocapitalize="sentences" autocorrect="on" spellcheck="true" class="min-w-0 w-full rounded-r-xl border-slate-300 px-3 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        @error('direccion_zona') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="direccion_via" class="mb-2 block text-sm font-semibold text-slate-700">Avenida o calle</label>
        <div class="flex gap-2">
            <select id="direccion_tipo_via" name="direccion_tipo_via" aria-label="Tipo de vía" x-model="tipoVia" :required="via.trim() !== ''" class="w-28 shrink-0 rounded-xl border-slate-300 px-3 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                <option value="">Tipo</option>
                <option value="Av." @selected($tipoVia === 'Av.')>Av.</option>
                <option value="C." @selected($tipoVia === 'C.')>C.</option>
            </select>
            <input id="direccion_via" name="direccion_via" x-model="via" value="{{ $via }}" maxlength="120" autocomplete="address-line1" placeholder="Nombre de la avenida o calle" data-capitalize-first autocapitalize="sentences" autocorrect="on" spellcheck="true" class="min-w-0 w-full rounded-xl border-slate-300 px-3 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        @error('direccion_tipo_via') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('direccion_via') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="direccion_numero_puerta" class="mb-2 block text-sm font-semibold text-slate-700">Número de puerta</label>
        <div class="flex rounded-xl shadow-sm">
            <span class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm font-semibold text-slate-500">#</span>
            <input id="direccion_numero_puerta" name="direccion_numero_puerta" value="{{ $numeroPuerta }}" maxlength="20" autocomplete="address-line2" autocapitalize="none" autocorrect="off" spellcheck="false" class="min-w-0 w-full rounded-r-xl border-slate-300 px-3 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        @error('direccion_numero_puerta') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
