<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ProfilesController extends Controller
{
    protected $imageManager;

    public function __construct()
    {
        $this->middleware('auth');

        // GD 드라이버로 ImageManager 인스턴스 생성
        $this->imageManager = new ImageManager(new GdDriver());
    }

    // 프로필 페이지를 보여주는 메서드
    public function index(User $user)
    {
        // 현재 사용자가 프로필 사용자를 팔로우하고 있는지 확인
        $follows = (auth()->user()) ? auth()->user()->following->contains($user->id) : false;

        // 포스트 개수를 캐시해서 가져옴 (30초 동안 캐시)
        $postCount = Cache::remember(
            'count.posts.' . $user->id,
            now()->addSeconds(30),
            function () use ($user) {
                return $user->posts->count();
            }
        );

        // 팔로워 개수를 캐시해서 가져옴 (30초 동안 캐시) // 팔로워 및 팔로잉 수의 빈번한 변경
        $followersCount = Cache::remember(
            'count.followers.' . $user->id,
            now()->addSeconds(30),
            function () use ($user) {
                return $user->profile->followers->count();
            }
        );

        // 팔로잉 개수를 캐시해서 가져옴 (30초 동안 캐시)
        $followingCount = Cache::remember(
            'count.following.' . $user->id,
            now()->addSeconds(30),
            function () use ($user) {
                return $user->following->count();
            }
        );

        // 'profiles.index' 뷰에 필요한 데이터 전달하여 렌더링
        return view('profiles.index', compact('user', 'follows', 'postCount', 'followersCount', 'followingCount'));
    }

    // 프로필 수정 폼을 보여주는 메서드
    public function edit(User $user)
    {
        // 현재 사용자가 프로필을 업데이트할 권한이 있는지 확인
        $this->authorize('update', $user->profile);

        // 'profiles.edit' 뷰에 사용자 데이터 전달하여 렌더링
        return view('profiles.edit', compact('user'));
    }

    // 프로필 업데이트 메서드
    public function update(User $user)
    {
        // 현재 사용자가 프로필을 업데이트할 권한이 있는지 확인
        $this->authorize('update', $user->profile);

        // 폼 데이터 유효성 검사
        $data = request()->validate([
            'title' => 'required',
            'description' => 'required',
            'url' => 'url',
            'image' => '',
        ]);

        // 이미지가 업로드되었는지 확인
        if (request('image')) {
            // 이미지를 'public/profile' 디렉토리에 저장
            $imagePath = request('image')->store('profile', 'public');

            // 저장된 이미지의 전체 경로를 확인
            $storagePath = storage_path("app/public/{$imagePath}");

            // 파일이 실제로 존재하는지 확인
            if (!file_exists($storagePath)) {
                throw new \Exception("File does not exist: {$storagePath}");
            }

            // Intervention Image를 사용하여 이미지 크기 조정
            $image = $this->imageManager->read($storagePath)->resize(1000, 1000);

            // 처리된 이미지를 저장 (명시적 경로 지정)
            $image->save($storagePath);

            // 업데이트할 데이터 배열에 이미지 경로 추가
            $imageArray = ['image' => $imagePath];
        }

        // 프로필 업데이트
        auth()->user()->profile->update(array_merge(
            $data,
            $imageArray ?? []
        ));

        // 업데이트 후 사용자 프로필 페이지로 리다이렉트
        return redirect("/profile/{$user->id}");
    }
}

// Intervention Image 라이브러리는 PHP에서 이미지를 쉽게 처리할 수 있게 해주는 라이브러리로, GD와 Imagick 드라이버를 지원