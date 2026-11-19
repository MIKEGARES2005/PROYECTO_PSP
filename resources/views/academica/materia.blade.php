@if ($esDocente)
    <x-docente-layout>
        @include('academica.partials.materia-detail')
    </x-docente-layout>
@else
    <x-admin-layout>
        <x-admin-shell>
            @include('academica.partials.materia-detail')
        </x-admin-shell>
    </x-admin-layout>
@endif
