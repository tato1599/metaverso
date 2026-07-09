<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Carrera;
use App\Models\Maestro;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Usuarios', [
            'titulo' => 'Usuarios',
            'filas' => Usuario::with('rol:id_rol,nombre')->orderBy('nombre')->orderBy('apellidos')->get()
                ->map(fn (Usuario $u) => [
                    'id_usuario' => $u->id_usuario,
                    'correo' => $u->correo,
                    'nombre' => $u->nombre,
                    'apellidos' => $u->apellidos,
                    'activo' => $u->activo,
                    'id_rol' => $u->id_rol,
                    'rol' => ['nombre' => $u->rol?->nombre],
                ])->values(),
            'roles' => Rol::orderBy('nombre')->get(['id_rol', 'nombre']),
            'carreras' => Carrera::orderBy('nombre')->get(['id_carrera', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $rol = Rol::find($request->integer('id_rol'));

        $reglas = [
            'correo' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'correo')],
            'nombre' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'id_rol' => ['required', 'integer', Rule::exists('roles', 'id_rol')],
            'password' => ['required', 'string', 'min:8'],
        ];

        if ($rol?->nombre === 'Alumno') {
            $reglas += [
                'matricula' => ['required', 'string', 'max:50', Rule::unique('alumnos', 'matricula')],
                'id_carrera' => ['required', 'integer', Rule::exists('carreras', 'id_carrera')],
                'semestre_actual' => ['required', 'integer', 'min:1', 'max:15'],
                'generacion' => ['required', 'string', 'max:20'],
            ];
        }

        if ($rol?->nombre === 'Maestro') {
            $reglas['numero_empleado'] = ['required', 'string', 'max:50', Rule::unique('maestros', 'numero_empleado')];
        }

        $datos = $request->validate($reglas);

        DB::transaction(function () use ($datos, $rol) {
            $usuario = Usuario::create([
                'correo' => $datos['correo'],
                'nombre' => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'id_rol' => $datos['id_rol'],
                'contrasena_hash' => Hash::make($datos['password']),
            ]);

            if ($rol?->nombre === 'Alumno') {
                Alumno::create([
                    'id_usuario' => $usuario->id_usuario,
                    'id_carrera' => $datos['id_carrera'],
                    'matricula' => $datos['matricula'],
                    'semestre_actual' => $datos['semestre_actual'],
                    'generacion' => $datos['generacion'],
                ]);
            }

            if ($rol?->nombre === 'Maestro') {
                Maestro::create([
                    'id_usuario' => $usuario->id_usuario,
                    'numero_empleado' => $datos['numero_empleado'],
                ]);
            }
        });

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, Usuario $usuario)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($usuario->id_usuario, 'id_usuario')],
            'nombre' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'activo' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (! $datos['activo'] && $usuario->id_usuario === $request->user()->id_usuario) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivarte a ti mismo.']);
        }

        $cambios = [
            'correo' => $datos['correo'],
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'activo' => $datos['activo'],
        ];
        if (! empty($datos['password'])) {
            $cambios['contrasena_hash'] = Hash::make($datos['password']);
        }
        $usuario->update($cambios);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Usuario $usuario)
    {
        throw ValidationException::withMessages(['eliminar' => 'Los usuarios se desactivan, no se eliminan.']);
    }
}
