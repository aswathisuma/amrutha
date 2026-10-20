<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function __invoke()
    {
        return view('users.index', ['users' => User::with('branch')->orderBy('id')->get()]);
    }
}
