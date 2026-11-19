@if ($esDocente)
    <x-docente-layout>
        @include('academica.partials.semestre-list')
    </x-docente-layout>
@else
    <x-admin-layout>
        <x-admin-shell>
            @include('academica.partials.semestre-list')
        </x-admin-shell>
    </x-admin-layout>
@endif
