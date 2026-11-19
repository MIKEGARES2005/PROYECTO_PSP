@php($esAdministrador = $user->tipo_usuario === 'administrador')
@php($esDocente = $user->tipo_usuario === 'docente')

@if ($esAdministrador)
    <x-admin-layout>
        <x-admin-shell>
            @include('security.partials.page-content', ['esAdministrador' => $esAdministrador])
        </x-admin-shell>
    </x-admin-layout>
@elseif ($esDocente)
    <x-docente-layout>
        @include('security.partials.page-content', ['esAdministrador' => $esAdministrador])
    </x-docente-layout>
@else
    <x-admin-layout>
        <div class="min-h-screen bg-slate-50">
            @include('security.partials.page-content', ['esAdministrador' => $esAdministrador])
        </div>
    </x-admin-layout>
@endif
