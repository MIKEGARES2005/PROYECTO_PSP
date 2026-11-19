<div
    x-data="{
        grupo: @js(old('grupo', $categoria->grupo)),
        tipoRegistro: @js(old('tipo_registro', $categoria->tipo_registro)),
        cambiarGrupo(grupo) {
            this.grupo = grupo;
            if (grupo === 'equipo') this.tipoRegistro = 'equipo';
            else if (grupo === 'complemento') this.tipoRegistro = 'complemento';
            else if (grupo !== 'red') this.tipoRegistro = 'activo';
        }
    }"
    class="space-y-5"
>
<div>
    <label for="nombre" class="mb-2 block text-sm font-bold text-slate-700">Nombre de categoría</label>
    <input id="nombre" name="nombre" value="{{ old('nombre', $categoria->nombre) }}" required maxlength="100" placeholder="Ej. Sillas de aula" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
    @error('nombre')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
<div>
    <label for="prefijo_codigo" class="mb-2 block text-sm font-bold text-slate-700">Prefijo del código patrimonial</label>
    <input id="prefijo_codigo" name="prefijo_codigo" value="{{ old('prefijo_codigo', $categoria->prefijo_codigo) }}" required minlength="2" maxlength="8" pattern="[A-Za-z0-9]{2,8}" placeholder="Ej. SILLA, PC, TAB, MINI, COM" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm uppercase focus:border-[#4959A6] focus:ring-[#4959A6]">
    @error('prefijo_codigo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    <p class="mt-2 text-xs text-slate-500">De 2 a 8 letras o números; los correlativos se cuentan por categoría.</p>
</div>
<div>
    <label for="grupo" class="mb-2 block text-sm font-bold text-slate-700">Clasificación</label>
    <select id="grupo" name="grupo" required x-model="grupo" @change="cambiarGrupo($event.target.value)" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        <option value="">Selecciona la clasificación</option>
        @foreach (['mobiliario' => 'Mobiliario', 'equipo' => 'Equipo informático', 'electronico' => 'Electrónico independiente', 'complemento' => 'Periféricos y complementos', 'red' => 'Red', 'otro' => 'Otro'] as $valor => $etiqueta)
            <option value="{{ $valor }}" @selected(old('grupo', $categoria->grupo) === $valor)>{{ $etiqueta }}</option>
        @endforeach
    </select>
    @error('grupo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
<div>
    <label for="tipo_registro" class="mb-2 block text-sm font-bold text-slate-700">Ficha que creará el sistema</label>
    <select id="tipo_registro" name="tipo_registro" required x-model="tipoRegistro" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        <option value="activo" @selected(old('tipo_registro', $categoria->tipo_registro) === 'activo')>Activo independiente (mueble, TV Android, jack LAN)</option>
        <option value="equipo" @selected(old('tipo_registro', $categoria->tipo_registro) === 'equipo')>Equipo con ficha técnica y opciones de seguimiento</option>
        <option value="complemento" @selected(old('tipo_registro', $categoria->tipo_registro) === 'complemento')>Periférico o complemento vinculable a un equipo</option>
    </select>
    @error('tipo_registro')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    <p class="mt-2 text-xs leading-5 text-slate-500">Para Router y Wi‑Fi elige Equipo. Para el conector jack LAN elige Activo independiente. Esta ficha no se podrá cambiar mientras la categoría tenga artículos.</p>
</div>
@if ($categoria->exists)
    <div>
        <label for="estado" class="mb-2 block text-sm font-bold text-slate-700">Estado de la categoría</label>
        <select id="estado" name="estado" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
            <option value="activa" @selected(old('estado', $categoria->estado) === 'activa')>Activa</option>
            <option value="inactiva" @selected(old('estado', $categoria->estado) === 'inactiva')>Inactiva</option>
        </select>
    </div>
@endif
</div>
