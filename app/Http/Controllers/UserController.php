<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isAdmin()) {
                return redirect()->route('dashboard')->with('error', 'No tiene acceso a esta sección.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,employee',
        ]);

        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear el usuario.');
        }
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:admin,employee',
        ]);

        try {
            $user->name = $request->name;
            $user->email = $request->email;
            $user->role = $request->role;

            if ($request->password) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar el usuario.');
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::user()->id) {
            return back()->with('error', 'No puede eliminarse a sí mismo.');
        }

        try {
            $user->delete();
            return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar el usuario.');
        }
    }
}