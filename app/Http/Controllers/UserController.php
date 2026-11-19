<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\ReporteGenerado;
use App\Models\User;
use App\Rules\CarnetIdentidad;
use App\Rules\NombreCompleto;
use App\Rules\TelefonoBoliviano;
use App\Services\BitacoraService;
use App\Services\NotificacionService;
use App\Services\UserAnonymizationService;
use App\Support\DireccionEstudiante;
use App\Support\NormalizadorCredencial;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserController extends Controller
{
    /** Mostrar y buscar cuentas de usuario. */
    public function index(Request $request)
    {
        $categoriasPermitidas = ['administrador', 'docente', 'estudiante'];
        $categoria = $request->query('categoria', 'administrador');

        if (! in_array($categoria, $categoriasPermitidas, true)) {
            $categoria = 'administrador';
        }

        $semestresPermitidos = [1, 2, 3, 4];
        $semestreSeleccionado = $categoria === 'estudiante'
            ? $request->query('semestre', 'todos')
            : null;
        $semestre = $semestreSeleccionado === 'todos'
            ? null
            : (int) $semestreSeleccionado;

        if ($categoria === 'estudiante' && $semestre !== null && ! in_array($semestre, $semestresPermitidos, true)) {
            $semestre = null;
        }

        $buscar = trim((string) $request->input('buscar', ''));
        $consulta = User::with(['docente', 'estudiante'])
            ->where('tipo_usuario', $categoria)
            ->when($buscar !== '', function ($query) use ($buscar, $categoria) {
                $like = '%'.mb_strtolower($buscar).'%';
                $relation = $categoria === 'estudiante' ? 'estudiante' : 'docente';

                $query->where(function ($q) use ($like, $relation, $categoria) {
                    $q->whereRaw('LOWER(username) LIKE ?', [$like])
                        ->orWhereHas($relation, function ($perfil) use ($like, $categoria) {
                            $perfil->whereRaw('LOWER(nombres) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(apellido_paterno) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(apellido_materno) LIKE ?', [$like]);

                            if ($categoria === 'estudiante') {
                                $perfil->orWhereRaw('LOWER(matricula) LIKE ?', [$like]);
                            }
                        });
                });
            });

        if ($categoria === 'estudiante') {
            if ($semestre !== null) {
                $consulta->whereHas('estudiante', function ($query) use ($semestre) {
                    $query->where('semestre', $semestre);
                });
            }

            $usuarios = $consulta
                ->leftJoin('estudiantes', 'users.estudiante_id', '=', 'estudiantes.id')
                ->select('users.*')
                ->orderByRaw('LOWER(estudiantes.apellido_paterno) ASC')
                ->orderByRaw('LOWER(estudiantes.apellido_materno) ASC')
                ->orderByRaw('LOWER(estudiantes.nombres) ASC')
                ->orderBy('users.id', 'desc')
                ->paginate(10)
                ->withQueryString();
        } else {
            $usuarios = $consulta
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10)
                ->withQueryString();
        }

        $conteos = User::query()
            ->whereIn('tipo_usuario', $categoriasPermitidas)
            ->selectRaw('tipo_usuario, count(*) as total')
            ->groupBy('tipo_usuario')
            ->pluck('total', 'tipo_usuario');

        return view('usuarios.index', compact(
            'usuarios', 'buscar', 'categoria', 'semestre', 'semestresPermitidos', 'conteos'
        ));
    }

    /** Mostrar el formulario de creación. */
    public function create(): View
    {
        return view('usuarios.create');
    }

    /** Mostrar perfiles archivados mediante eliminación lógica. */
    public function deleted(): View
    {
        $usuarios = User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->with([
                'docente' => fn ($query) => $query->withTrashed(),
                'estudiante' => fn ($query) => $query->withTrashed(),
            ])
            ->latest('deleted_at')
            ->paginate(10);

        $discoPublico = Storage::disk('public');
        $directorioPdf = 'reportes/usuarios-eliminados';
        $archivosPdf = collect(
            $discoPublico->directoryExists($directorioPdf)
                ? $discoPublico->files($directorioPdf)
                : [],
        )
            ->filter(fn (string $ruta): bool => Str::endsWith(Str::lower($ruta), '.pdf'))
            ->sortDesc()
            ->map(fn (string $ruta): array => [
                'nombre' => basename($ruta),
                'url' => route('usuarios.deleted.pdf', ['filename' => basename($ruta)]),
            ])
            ->values();
        $rutaDirectorioPdf = $discoPublico->path($directorioPdf);

        return view('usuarios.deleted', compact('usuarios', 'archivosPdf', 'directorioPdf', 'rutaDirectorioPdf'));
    }

    /** Mostrar un PDF de respaldo de perfil eliminado. */
    public function deletedPdf(string $filename): BinaryFileResponse
    {
        abort_unless(
            $filename === basename($filename)
                && Str::endsWith(Str::lower($filename), '.pdf'),
            404,
        );

        $rutaReporte = 'reportes/usuarios-eliminados/'.$filename;
        $discoPublico = Storage::disk('public');

        abort_unless($discoPublico->exists($rutaReporte), 404);

        return response()->file($discoPublico->path($rutaReporte), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Crear una cuenta. */
    public function store(Request $request): RedirectResponse
    {
        $esEstudiante = $request->input('tipo_usuario') === 'estudiante';
        $esDocente = $request->input('tipo_usuario') === 'docente';
        $tablaPerfil = $esEstudiante ? 'estudiantes' : 'docentes';

        $reglasSemestre = $esEstudiante
            ? ['required', 'integer', 'between:1,4']
            : ['nullable'];

        $datos = $request->validate([
            'tipo_usuario' => ['required', Rule::in(['administrador', 'docente', 'estudiante'])],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'perfil.nombres' => ['required', 'string', 'max:100', new NombreCompleto],
            'perfil.apellido_paterno' => ['required', 'string', 'max:100', new NombreCompleto],
            'perfil.apellido_materno' => ['required', 'string', 'max:100', new NombreCompleto],
            'perfil.ci' => [
                'required',
                'string',
                new CarnetIdentidad,
                Rule::unique($tablaPerfil, 'ci'),
            ],
            'perfil.ci_extension' => [
                'required',
                Rule::in(['LP', 'OR', 'PT', 'CB', 'SC', 'TJ', 'CH', 'BE', 'PD']),
            ],
            'perfil.telefono' => ['nullable', 'string', new TelefonoBoliviano],
            'perfil.cargo' => [
                Rule::requiredIf($esDocente),
                'nullable',
                'string',
                'max:100',
            ],
            'perfil.especialidad' => ['nullable', 'string', 'max:150'],
            'perfil.nivel_academico' => ['nullable', 'string', 'max:100'],
            'perfil.fecha_ingreso' => ['nullable', 'date'],
            'perfil.matricula' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('estudiantes', 'matricula'),
            ],
            'perfil.semestre' => $reglasSemestre,
            'perfil.generacion_ingreso' => $esEstudiante
                ? ['required', 'string', 'regex:/^(?:I|II)-\d{4}$/']
                : ['nullable'],
            'perfil.correo_institucional' => ['nullable', 'email', 'max:150'],
            'perfil.correo_personal' => ['nullable', 'email', 'max:150'],
            'perfil.direccion' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'direccion_zona' => ['nullable', 'string', 'max:80'],
            'direccion_tipo_via' => [
                Rule::requiredIf(filled($request->input('direccion_via'))),
                'nullable',
                Rule::in(['Av.', 'C.']),
            ],
            'direccion_via' => ['nullable', 'string', 'max:120'],
            'direccion_numero_puerta' => ['nullable', 'string', 'max:20'],
            'foto_personal' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $perfilDatos = Arr::only($datos['perfil'], $esEstudiante
            ? [
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'ci',
                'ci_extension',
                'telefono',
                'matricula',
                'semestre',
                'generacion_ingreso',
                'correo_institucional',
                'correo_personal',
                'direccion',
            ]
            : [
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'ci',
                'ci_extension',
                'telefono',
                'correo_personal',
                'cargo',
                'especialidad',
                'nivel_academico',
                'fecha_ingreso',
            ]);

        if ($esEstudiante) {
            $perfilDatos['direccion'] = DireccionEstudiante::desdeDatos(
                $datos,
                $datos['direccion'] ?? $datos['perfil']['direccion'] ?? null,
            );
        }

        if (! $esEstudiante && ! $esDocente) {
            $perfilDatos['cargo'] = 'Administrador';
        }

        $foto = $request->file('foto_personal');
        $rutaFoto = $foto?->store(
            $esEstudiante ? 'fotos/estudiantes' : 'fotos/docentes',
            'public'
        );

        if ($foto !== null && $rutaFoto === false) {
            throw ValidationException::withMessages([
                'foto_personal' => 'No se pudo guardar la fotografía.',
            ]);
        }

        $usuarioCreado = null;

        try {
            DB::transaction(function () use ($datos, $perfilDatos, $esEstudiante, $rutaFoto, &$usuarioCreado): void {
                if ($rutaFoto !== null) {
                    $perfilDatos['foto_personal'] = $rutaFoto;
                }

                $perfil = $esEstudiante
                    ? Estudiante::create($perfilDatos + ['estado' => 'activo'])
                    : Docente::create($perfilDatos + ['estado' => 'activo']);

                $usuarioCreado = User::create([
                    'username' => $datos['username'],
                    'password' => $datos['password'], // Se aplica cast 'hashed' en el modelo
                    'tipo_usuario' => $datos['tipo_usuario'],
                    'docente_id' => $esEstudiante ? null : $perfil->id,
                    'estudiante_id' => $esEstudiante ? $perfil->id : null,
                    'debe_cambiar_password' => true, // Contraseña temporal inicial
                ]);

                BitacoraService::registrar(
                    'crear_usuario',
                    'users',
                    $usuarioCreado->id,
                    null,
                    ['username' => $usuarioCreado->username, 'tipo_usuario' => $usuarioCreado->tipo_usuario]
                );
            });
        } catch (Throwable $exception) {
            if ($rutaFoto !== null) {
                Storage::disk('public')->delete($rutaFoto);
            }

            throw $exception;
        }

        return redirect()->route('usuarios.index', [
            'categoria' => $datos['tipo_usuario'],
        ])->with('success', 'El perfil y la cuenta se crearon correctamente con contraseña temporal.');
    }

    /** Mostrar una cuenta. */
    public function show(string $id): View
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);

        return view('usuarios.show', compact('usuario'));
    }

    /** Entregar la fotografía personal de una cuenta al panel administrativo. */
    public function photo(string $id): BinaryFileResponse
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        $perfil = $usuario->tipo_usuario === 'estudiante'
            ? $usuario->estudiante
            : $usuario->docente;
        $rutaFoto = $perfil?->foto_personal;

        abort_unless($rutaFoto !== null && Storage::disk('public')->exists($rutaFoto), 404);

        return response()->file(Storage::disk('public')->path($rutaFoto), [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Exportar la ficha de una cuenta como plantilla PDF. */
    public function exportPdf(string $id): Response
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        $nombreArchivo = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) preg_replace('/[^\pL\pN\s-]/u', '', $usuario->nombreCompleto()))
        );
        $nombreArchivo = ($nombreArchivo !== '' ? $nombreArchivo : 'usuario-'.$usuario->id).'.pdf';

        return Pdf::loadView('usuarios.pdf', compact('usuario'))
            ->setPaper('letter', 'portrait')
            ->download($nombreArchivo);
    }

    public function editPersonal(string $id): View
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        abort_if($usuario->tipo_usuario === 'administrador' && $usuario->id === Auth::id(), 403);

        $perfil = $usuario->tipo_usuario === 'estudiante'
            ? $usuario->estudiante
            : $usuario->docente;

        abort_if($perfil === null, 404);

        return view('usuarios.edit-personal', compact('usuario', 'perfil'));
    }

    public function updatePersonal(Request $request, string $id): RedirectResponse
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        abort_if($usuario->tipo_usuario === 'administrador' && $usuario->id === Auth::id(), 403);

        $esEstudiante = $usuario->tipo_usuario === 'estudiante';
        $perfil = $esEstudiante ? $usuario->estudiante : $usuario->docente;

        abort_if($perfil === null, 404);

        $reglas = [
            'nombres' => ['required', 'string', 'max:100', new NombreCompleto],
            'apellido_paterno' => ['required', 'string', 'max:100', new NombreCompleto],
            'apellido_materno' => ['required', 'string', 'max:100', new NombreCompleto],
            'ci' => [
                'required',
                'string',
                new CarnetIdentidad,
                Rule::unique($esEstudiante ? 'estudiantes' : 'docentes', 'ci')
                    ->ignore($perfil->id),
            ],
            'ci_extension' => [
                'required',
                Rule::in(['LP', 'OR', 'PT', 'CB', 'SC', 'TJ', 'CH', 'BE', 'PD']),
            ],
            'telefono' => ['nullable', 'string', new TelefonoBoliviano],
            'correo_personal' => ['nullable', 'email', 'max:150'],
            'foto_personal' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($esEstudiante) {
            $reglas += [
                'matricula' => [
                    'nullable',
                    'string',
                    'max:30',
                    Rule::unique('estudiantes', 'matricula')->ignore($perfil->id),
                ],
                'semestre' => ['required', 'integer', 'between:1,4'],
                'generacion_ingreso' => ['required', 'string', 'regex:/^(?:I|II)-\d{4}$/'],
                'correo_institucional' => ['nullable', 'email', 'max:150'],
                'direccion' => ['nullable', 'string', 'max:255'],
                'direccion_zona' => ['nullable', 'string', 'max:80'],
                'direccion_tipo_via' => [
                    Rule::requiredIf(filled($request->input('direccion_via'))),
                    'nullable',
                    Rule::in(['Av.', 'C.']),
                ],
                'direccion_via' => ['nullable', 'string', 'max:120'],
                'direccion_numero_puerta' => ['nullable', 'string', 'max:20'],
            ];
        } else {
            $reglas += [
                'cargo' => ['required', 'string', 'max:100'],
                'especialidad' => ['nullable', 'string', 'max:150'],
                'nivel_academico' => ['nullable', 'string', 'max:100'],
                'fecha_ingreso' => ['nullable', 'date'],
            ];
        }

        $datos = $request->validate($reglas);
        $datosPerfil = Arr::only($datos, array_keys($reglas));

        if ($esEstudiante) {
            $datosPerfil = Arr::except($datosPerfil, [
                'direccion_zona',
                'direccion_tipo_via',
                'direccion_via',
                'direccion_numero_puerta',
            ]);
            $datosPerfil['direccion'] = DireccionEstudiante::desdeDatos($datos, $datos['direccion'] ?? null);
        }

        $foto = $request->file('foto_personal');
        $rutaFoto = $foto?->store(
            $esEstudiante ? 'fotos/estudiantes' : 'fotos/docentes',
            'public'
        );

        if ($foto !== null && $rutaFoto === false) {
            throw ValidationException::withMessages([
                'foto_personal' => 'No se pudo guardar la fotografía.',
            ]);
        }

        if ($rutaFoto !== null) {
            $datosPerfil['foto_personal'] = $rutaFoto;
        }

        $fotoAnterior = $perfil->foto_personal;
        $antes = $perfil->toArray();

        try {
            $perfil->update($datosPerfil);
            BitacoraService::registrar(
                'actualizar_perfil',
                $esEstudiante ? 'estudiantes' : 'docentes',
                $perfil->id,
                $antes,
                $perfil->toArray()
            );
        } catch (Throwable $exception) {
            if ($rutaFoto !== null) {
                Storage::disk('public')->delete($rutaFoto);
            }

            throw $exception;
        }

        if ($rutaFoto !== null && $fotoAnterior !== null) {
            Storage::disk('public')->delete($fotoAnterior);
        }

        return redirect()->route('usuarios.show', $usuario->id)
            ->with('success', 'Los datos personales se actualizaron correctamente.');
    }

    /** Activar o desactivar la cuenta mediante el estado de su perfil personal. */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);

        if ($usuario->id === Auth::id() && $datos['estado'] === 'inactivo') {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $perfil = match ($usuario->tipo_usuario) {
            'administrador', 'docente' => $usuario->docente,
            'estudiante' => $usuario->estudiante,
            default => null,
        };

        if ($perfil === null) {
            return back()->with('error', 'La cuenta no tiene un perfil personal asociado.');
        }

        $estadoAnterior = $perfil->estado;
        $perfil->update(['estado' => $datos['estado']]);

        if (
            $datos['estado'] === 'inactivo'
            && in_array($usuario->tipo_usuario, ['docente', 'estudiante'], true)
        ) {
            $this->invalidateAccountSessions($usuario);
        }

        BitacoraService::registrar(
            'cambiar_estado_usuario',
            'users',
            $usuario->id,
            ['estado' => $estadoAnterior],
            ['estado' => $datos['estado']]
        );

        $mensaje = $datos['estado'] === 'activo'
            ? 'La cuenta se activó correctamente.'
            : 'La cuenta se desactivó correctamente.';

        return redirect()->route('usuarios.show', $usuario->id)
            ->with('success', $mensaje);
    }

    /** Eliminar lógicamente el usuario y su perfil personal asociado. */
    public function destroy(string $id): RedirectResponse
    {
        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        abort_if($usuario->id === Auth::id(), 403);

        $perfil = match ($usuario->tipo_usuario) {
            'administrador', 'docente' => $usuario->docente,
            'estudiante' => $usuario->estudiante,
            default => null,
        };

        $categoria = $usuario->tipo_usuario;
        $antes = [
            'username' => $usuario->username,
            'tipo_usuario' => $usuario->tipo_usuario,
            'perfil_id' => $perfil?->id,
        ];
        $rutaReporte = 'reportes/usuarios-eliminados/usuario-'.$usuario->id.'-'.now()->format('YmdHis').'.pdf';

        if (! Storage::disk('public')->put(
            $rutaReporte,
            Pdf::loadView('usuarios.pdf', compact('usuario'))->setPaper('letter', 'portrait')->output(),
        )) {
            throw new \RuntimeException('No se pudo guardar el respaldo PDF del usuario eliminado.');
        }

        try {
            DB::transaction(function () use ($perfil, $usuario, $antes, $rutaReporte): void {
                ReporteGenerado::create([
                    'tipo' => 'otro',
                    'parametros' => [
                        'tipo' => 'eliminacion_usuario',
                        'usuario_id' => $usuario->id,
                        'username' => $usuario->username,
                    ],
                    'generado_por' => Auth::id(),
                    'ruta' => $rutaReporte,
                    'vence_en' => null,
                ]);

                $usuario->setRememberToken(null);
                $usuario->save();
                $this->invalidateAccountSessions($usuario);

                if ($perfil !== null) {
                    $perfil->delete();
                }

                $usuario->delete();

                BitacoraService::registrar(
                    'eliminar_perfil_usuario',
                    'users',
                    $usuario->id,
                    $antes,
                    ['deleted_at' => now()->toDateTimeString(), 'respaldo_pdf' => $rutaReporte]
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($rutaReporte);

            throw $exception;
        }

        return redirect()->route('usuarios.index', ['categoria' => $categoria])
            ->with('success', 'Perfil eliminado correctamente');
    }

    /** Restaurar una cuenta y su perfil personal archivados. */
    public function restore(string $id): RedirectResponse
    {
        $usuario = User::onlyTrashed()
            ->with([
                'docente' => fn ($query) => $query->withTrashed(),
                'estudiante' => fn ($query) => $query->withTrashed(),
            ])
            ->findOrFail($id);

        if ($usuario->anonymized_at !== null) {
            return redirect()->route('usuarios.deleted')
                ->withErrors(['usuario' => 'Este perfil ya fue anonimizado y no puede restaurarse.']);
        }

        $perfil = in_array($usuario->tipo_usuario, ['administrador', 'docente'], true)
            ? $usuario->docente
            : ($usuario->tipo_usuario === 'estudiante' ? $usuario->estudiante : null);

        abort_if($perfil !== null && ! $perfil->trashed(), 404);

        $gracePeriodCutoff = now()->subDays((int) config('retention.users.deletion_grace_period_days', 2));

        if ($usuario->deleted_at?->lte($gracePeriodCutoff)) {
            return redirect()->route('usuarios.deleted')
                ->withErrors(['usuario' => 'El periodo de restauración de esta cuenta ya expiró.']);
        }

        DB::transaction(function () use ($perfil, $usuario): void {
            $perfil?->restore();
            $usuario->setRememberToken(null);
            $usuario->restore();

            BitacoraService::registrar(
                'restaurar_perfil_usuario',
                'users',
                $usuario->id,
                ['deleted_at' => $usuario->deleted_at?->toDateTimeString()],
                ['deleted_at' => null]
            );
        });

        return redirect()->route('usuarios.deleted')
            ->with('success', 'Perfil recuperado correctamente');
    }

    /** Finalizar anticipadamente la eliminación de un perfil archivado. */
    public function finalizeDeletion(
        string $id,
        Request $request,
        UserAnonymizationService $anonymization,
    ): RedirectResponse {
        $usuario = User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->findOrFail($id);

        $anonymization->anonymize($usuario, $request->user());

        return redirect()->route('usuarios.deleted')
            ->with('success', 'Los datos personales del perfil se anonimizaron; el PDF original se conserva indefinidamente junto con el historial.');
    }

    /** Recuperar cuenta: generar credenciales temporales para docentes y estudiantes. */
    public function recover(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        abort_if(! $admin || ! $admin->isAdministrador(), 403);

        $usuario = User::with(['docente', 'estudiante'])->findOrFail($id);
        abort_if($usuario->id === $admin->id, 403);
        abort_if(! in_array($usuario->tipo_usuario, ['administrador', 'docente', 'estudiante'], true), 404);

        $perfil = $usuario->tipo_usuario === 'estudiante'
            ? $usuario->estudiante
            : $usuario->docente;
        abort_if($perfil === null, 404);

        $request->validate([
            'admin_password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('admin_password'), $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña de administrador es incorrecta.',
            ], 422);
        }

        // Generar username temporal único
        $baseUsername = NormalizadorCredencial::normalizar((string) $perfil->nombres)
            .'_'.NormalizadorCredencial::normalizar((string) $perfil->apellido_paterno);
        $baseUsername = Str::substr($baseUsername ?: 'usuario', 0, 50);
        $tempUsername = $baseUsername;
        $sufijo = 2;

        while (User::where('username', $tempUsername)->whereKeyNot($usuario->id)->exists()) {
            $colision = '_'.$sufijo++;
            $tempUsername = Str::substr($baseUsername, 0, 50 - Str::length($colision)).$colision;
        }

        // Generar una contraseña temporal a partir del apellido y el CI.
        $ci = preg_replace('/\D+/', '', (string) $perfil->ci) ?: '00000000';
        $basePassword = NormalizadorCredencial::normalizar((string) $perfil->apellido_paterno).$ci;

        $tempPassword = $basePassword ?: Str::random(12);

        $antes = $usuario->only(['username', 'debe_cambiar_password']);

        DB::transaction(function () use ($usuario, $tempUsername, $tempPassword, $antes): void {
            $usuario->forceFill([
                'username' => $tempUsername,
                'password' => $tempPassword, // Se aplica cast 'hashed' en el modelo
                'debe_cambiar_password' => true,
                'remember_token' => null,
            ])->save();

            $this->invalidateAccountSessions($usuario);

            BitacoraService::registrar(
                'recuperar_cuenta',
                'users',
                $usuario->id,
                $antes,
                ['username' => $tempUsername, 'debe_cambiar_password' => true]
            );

            if (in_array($usuario->tipo_usuario, ['docente', 'estudiante'], true)) {
                NotificacionService::notificarUsuario(
                    (int) $usuario->id,
                    'recuperacion_aprobada',
                    'Recuperación aprobada',
                    'Tu cuenta fue recuperada con éxito. Inicia sesión con las credenciales temporales que te proporcionó administración y actualiza tu usuario y contraseña.',
                    $usuario->tipo_usuario === 'estudiante'
                        ? route('estudiante.profile.edit')
                        : route('profile.edit')
                );
            }
        });

        if ($usuario->tipo_usuario === 'estudiante') {
            foreach ([Auth::guard('student')->getName(), Auth::guard('web')->getName()] as $guardKey) {
                if ((string) $request->session()->get($guardKey) === (string) $usuario->id) {
                    $request->session()->forget($guardKey);
                }
            }
        }

        return response()->json([
            'success' => true,
            'username' => $tempUsername,
            'password' => $tempPassword,
        ]);
    }

    private function invalidateAccountSessions(User $usuario): void
    {
        $sessionConnection = DB::connection(config('session.connection'));
        $sessionTable = config('session.table');

        if ($usuario->tipo_usuario !== 'estudiante') {
            $sessionConnection->table($sessionTable)
                ->where('user_id', $usuario->id)
                ->delete();

            return;
        }

        $sessionConnection->table($sessionTable)
            ->where('user_id', $usuario->id)
            ->delete();

        // Student sessions can share a record with a different guard, so remove only this user's auth keys.
        $guardKeys = [
            Auth::guard('student')->getName(),
            Auth::guard('web')->getName(),
        ];
        $activeSince = now()->subMinutes(config('session.lifetime'))->timestamp;
        $sessions = $sessionConnection->table($sessionTable)
            ->where('last_activity', '>=', $activeSince)
            ->get(['id', 'payload']);

        foreach ($sessions as $session) {
            $serializedData = base64_decode($session->payload, true);

            if ($serializedData === false) {
                continue;
            }

            $serialization = config('session.serialization', 'php');
            $attributes = $serialization === 'json'
                ? json_decode($serializedData, true)
                : @unserialize($serializedData);

            if (! is_array($attributes)) {
                continue;
            }

            $hasMatchingGuard = false;

            foreach ($guardKeys as $guardKey) {
                if ((string) ($attributes[$guardKey] ?? '') === (string) $usuario->id) {
                    unset($attributes[$guardKey]);
                    $hasMatchingGuard = true;
                }
            }

            if (! $hasMatchingGuard) {
                continue;
            }

            $updatedData = $serialization === 'json'
                ? json_encode($attributes, JSON_THROW_ON_ERROR)
                : serialize($attributes);

            $sessionConnection->table($sessionTable)
                ->where('id', $session->id)
                ->update(['payload' => base64_encode($updatedData)]);
        }
    }
}
