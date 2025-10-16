<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::with('roles');

        
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $users->where(function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('sort_by') && $request->has('sort_order')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->sort_order;
            
            $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'asc';
            
            switch ($sortBy) {
                case 'name':
                case 'email':
                case 'phone':
                case 'created_at':
                    $users->orderBy($sortBy, $sortOrder);
                    break;
                default:
                    $users->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            $users->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', env('PAGINATION_LIMIT', 10));
        $perPage = min($perPage, 100);
        
        $users = $users->paginate($perPage);
        sleep(1);
        return response()->json($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // expects password_confirmation
            'roles' => ['sometimes', 'array']
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = new User();
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->phone = $validated['phone'] ?? null;
            $user->password = Hash::make($validated['password']);
            $user->save();

            // Optional: assign/sync roles if provided as role IDs
            if ($request->filled('roles') && is_array($request->roles)) {
                // assumes a belongsToMany relationship: $user->roles()
                $user->syncRoles($request->roles);
            }

            return response()->json($user->load('roles'), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id)->load('roles');
        return response()->json($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::find($id)->load('roles');
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'], // if provided, expects password_confirmation
            'roles' => ['sometimes', 'array']
        ]);

        return DB::transaction(function () use ($validated, $request, $user) {
            if (array_key_exists('name', $validated)) {
                $user->name = $validated['name'];
            }
            if (array_key_exists('email', $validated)) {
                $user->email = $validated['email'];
            }
            if (array_key_exists('phone', $validated)) {
                $user->phone = $validated['phone'];
            }
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            if ($request->has('roles') && is_array($request->roles)) {
                $user->syncRoles($request->roles);
            }

            return response()->json($user->load('roles'));
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);

        try {
            $user->delete();
            return response()->json(['message' => 'User deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete user.', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get logged in user information
     *
     * @return void
     */
    public function getAuthUser(){
        return response()->json(Auth::user()->load('roles'));
    }
}
