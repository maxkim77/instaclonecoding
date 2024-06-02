<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class FollowsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // 이 메서드는 인증된 사용자가 다른 사용자의 프로필을 팔로우하거나 언팔로우하도록 합니다.

    public function store(User $user)
    {
        return auth()->user()->following()->toggle($user->profile);
    }
}
