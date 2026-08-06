<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return view('user.index');
    }

    public function data()
    {
        // If logged in user is admin, show all users including other admins
        // Otherwise, exclude admin users
        $currentUser = auth()->user();
        if ($currentUser && (strtolower($currentUser->role ?? '') === 'admin' || ($currentUser->level ?? 0) == 1)) {
            $user = User::orderBy('id', 'desc')->get();
        } else {
            $user = User::isNotAdmin()->orderBy('id', 'desc')->get();
        }

        return datatables()
            ->of($user)
            ->addIndexColumn()
            ->addColumn('role', function ($user) {
                return $user->role ?: 'cashier';
            })
            ->addColumn('permissions', function ($user) {
                $perms = [];
                if ($user->can_create) $perms[] = 'C';
                if ($user->can_read) $perms[] = 'R';
                if ($user->can_update) $perms[] = 'U';
                if ($user->can_delete) $perms[] = 'D';
                $global = implode('', $perms);
                $inv = [];
                if ($user->inv_create ?? false) $inv[] = 'C';
                if ($user->inv_read ?? true) $inv[] = 'R';
                if ($user->inv_update ?? false) $inv[] = 'U';
                if ($user->inv_delete ?? false) $inv[] = 'D';
                $sal = [];
                if ($user->sal_create ?? false) $sal[] = 'C';
                if ($user->sal_read ?? true) $sal[] = 'R';
                if ($user->sal_update ?? false) $sal[] = 'U';
                if ($user->sal_delete ?? false) $sal[] = 'D';
                $exp = [];
                if ($user->exp_create ?? false) $exp[] = 'C';
                if ($user->exp_read ?? true) $exp[] = 'R';
                if ($user->exp_update ?? false) $exp[] = 'U';
                if ($user->exp_delete ?? false) $exp[] = 'D';
                $rep = [];
                if ($user->rep_create ?? false) $rep[] = 'C';
                if ($user->rep_read ?? true) $rep[] = 'R';
                if ($user->rep_update ?? false) $rep[] = 'U';
                if ($user->rep_delete ?? false) $rep[] = 'D';
                $pay = [];
                if ($user->pay_create ?? false) $pay[] = 'C';
                if ($user->pay_read ?? false) $pay[] = 'R';
                if ($user->pay_update ?? false) $pay[] = 'U';
                if ($user->pay_delete ?? false) $pay[] = 'D';
                return 'Global: ' . $global
                    . '<br>Inventory: ' . implode('', $inv)
                    . '<br>Sales: ' . implode('', $sal)
                    . '<br>Expense: ' . implode('', $exp)
                    . '<br>Reports: ' . implode('', $rep)
                    . '<br>Consignment: ' . implode('', [
                        ($user->con_create ?? false) ? 'C' : null,
                        ($user->con_read ?? false) ? 'R' : null,
                        ($user->con_update ?? false) ? 'U' : null,
                        ($user->con_delete ?? false) ? 'D' : null,
                    ])
                    . '<br>Payroll: ' . implode('', $pay);
            })
            ->addColumn('aksi', function ($user) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if ($u && $u->can_update) {
                    $buttons .= '<button type="button" onclick="editForm(`'. route('user.update', $user->id) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-pencil"></i></button>';
                }
                if ($u && $u->can_delete) {
                    $buttons .= '<button type="button" onclick="deleteData(`'. route('user.destroy', $user->id) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi','permissions'])
            ->make(true);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->role = $request->input('role', 'cashier');
        // sync legacy numeric level with role
        $roleLevel = 2;
        if ($user->role === 'admin') { $roleLevel = 1; }
        elseif ($user->role === 'manager') { $roleLevel = 2; }
        else { $roleLevel = 2; }
        $user->level = $roleLevel;
        $user->can_create = (bool) $request->input('can_create', false);
        $user->can_read = (bool) $request->input('can_read', true);
        $user->can_update = (bool) $request->input('can_update', false);
        $user->can_delete = (bool) $request->input('can_delete', false);
        $user->inv_create = (bool) $request->input('inv_create', false);
        $user->inv_read = (bool) $request->input('inv_read', false);
        $user->inv_update = (bool) $request->input('inv_update', false);
        $user->inv_delete = (bool) $request->input('inv_delete', false);
        $user->sal_create = (bool) $request->input('sal_create', false);
        $user->sal_read = (bool) $request->input('sal_read', false);
        $user->sal_update = (bool) $request->input('sal_update', false);
        $user->sal_delete = (bool) $request->input('sal_delete', false);
        $user->exp_create = (bool) $request->input('exp_create', false);
        $user->exp_read = (bool) $request->input('exp_read', false);
        $user->exp_update = (bool) $request->input('exp_update', false);
        $user->exp_delete = (bool) $request->input('exp_delete', false);
        $user->rep_create = (bool) $request->input('rep_create', false);
        $user->rep_read = (bool) $request->input('rep_read', false);
        $user->rep_update = (bool) $request->input('rep_update', false);
        $user->rep_delete = (bool) $request->input('rep_delete', false);
        $user->con_create = (bool) $request->input('con_create', false);
        $user->con_read = (bool) $request->input('con_read', false);
        $user->con_update = (bool) $request->input('con_update', false);
        $user->con_delete = (bool) $request->input('con_delete', false);
        $user->pay_create = (bool) $request->input('pay_create', false);
        $user->pay_read = (bool) $request->input('pay_read', false);
        $user->pay_update = (bool) $request->input('pay_update', false);
        $user->pay_delete = (bool) $request->input('pay_delete', false);
        // already set above
        $user->foto = '/img/user.png';
        $user->save();

        return response()->json('Data saved successfully', 200);
    }

    public function show($id)
    {
        $user = User::find($id);

        return response()->json($user);
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->has('password') && $request->password != "") 
            $user->password = bcrypt($request->password);
        if ($request->filled('role')) {
            $user->role = $request->input('role');
        }
        // sync legacy numeric level with role
        if ($user->role === 'admin') { $user->level = 1; }
        elseif ($user->role === 'manager') { $user->level = 2; }
        else { $user->level = 2; }
        $user->can_create = (bool) $request->input('can_create', $user->can_create);
        $user->can_read = (bool) $request->input('can_read', $user->can_read);
        $user->can_update = (bool) $request->input('can_update', $user->can_update);
        $user->can_delete = (bool) $request->input('can_delete', $user->can_delete);
        $user->inv_create = (bool) $request->input('inv_create', $user->inv_create);
        $user->inv_read = (bool) $request->input('inv_read', $user->inv_read);
        $user->inv_update = (bool) $request->input('inv_update', $user->inv_update);
        $user->inv_delete = (bool) $request->input('inv_delete', $user->inv_delete);
        $user->sal_create = (bool) $request->input('sal_create', $user->sal_create);
        $user->sal_read = (bool) $request->input('sal_read', $user->sal_read);
        $user->sal_update = (bool) $request->input('sal_update', $user->sal_update);
        $user->sal_delete = (bool) $request->input('sal_delete', $user->sal_delete);
        $user->exp_create = (bool) $request->input('exp_create', $user->exp_create);
        $user->exp_read = (bool) $request->input('exp_read', $user->exp_read);
        $user->exp_update = (bool) $request->input('exp_update', $user->exp_update);
        $user->exp_delete = (bool) $request->input('exp_delete', $user->exp_delete);
        $user->rep_create = (bool) $request->input('rep_create', $user->rep_create);
        $user->rep_read = (bool) $request->input('rep_read', $user->rep_read);
        $user->rep_update = (bool) $request->input('rep_update', $user->rep_update);
        $user->rep_delete = (bool) $request->input('rep_delete', $user->rep_delete);
        $user->con_create = (bool) $request->input('con_create', $user->con_create);
        $user->con_read = (bool) $request->input('con_read', $user->con_read);
        $user->con_update = (bool) $request->input('con_update', $user->con_update);
        $user->con_delete = (bool) $request->input('con_delete', $user->con_delete);
        $user->pay_create = (bool) $request->input('pay_create', $user->pay_create ?? false);
        $user->pay_read = (bool) $request->input('pay_read', $user->pay_read ?? false);
        $user->pay_update = (bool) $request->input('pay_update', $user->pay_update ?? false);
        $user->pay_delete = (bool) $request->input('pay_delete', $user->pay_delete ?? false);
        $user->update();

        return response()->json('Data saved successfully', 200);
    }

    public function destroy($id)
    {
        $user = User::find($id)->delete();

        return response(null, 204);
    }

    public function profil()
    {
        $profil = auth()->user();
        return view('user.profil', compact('profil'));
    }

    public function updateProfil(Request $request)
    {
        $user = User::query()->findOrFail(auth()->id());

        $newPassword = trim((string) $request->input('password', ''));
        $oldPassword = trim((string) $request->input('old_password', ''));
        $confirmPassword = trim((string) $request->input('password_confirmation', ''));

        $rules = [
            'name' => 'required|string|max:255',
        ];
        $messages = [];

        if ($newPassword !== '') {
            $rules['password'] = 'required|string|min:4';
            $rules['old_password'] = 'required|string';
            $messages['password.min'] = 'New password must be at least 4 characters.';
            $messages['old_password.required'] = 'Enter your current password to set a new one.';
        }

        $request->validate($rules, $messages);

        if ($newPassword !== '' && $newPassword !== $confirmPassword) {
            return response()->json(['message' => 'Confirm password does not match.'], 422);
        }

        $user->name = $request->name;

        if ($newPassword !== '') {
            if (! $this->verifyUserPassword($user, $oldPassword)) {
                return response()->json(['message' => 'The current password is incorrect.'], 422);
            }
            $user->password = Hash::make($newPassword);
        }

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $nama = 'logo-' . date('YmdHis') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/img'), $nama);

            $user->foto = "/img/$nama";
        }

        $user->save();

        return response()->json($user, 200);
    }

    /**
     * Verify a plain password against the hash stored in the database.
     */
    private function verifyUserPassword(User $user, string $plain): bool
    {
        $plain = trim($plain);
        if ($plain === '') {
            return false;
        }

        $hash = User::query()->whereKey($user->getKey())->value('password');
        if ($hash === null || $hash === '') {
            return false;
        }

        return Hash::check($plain, (string) $hash);
    }
}
