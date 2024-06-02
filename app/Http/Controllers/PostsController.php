<?php

namespace App\Http\Controllers;

use App\Post;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class PostsController extends Controller
{
    protected $imageManager;

    public function __construct()
    {
        $this->middleware('auth');

        // GD 드라이버로 ImageManager 인스턴스 생성
        $this->imageManager = new ImageManager(new GdDriver());
    }

    // 포스트 목록을 보여주는 메서드
    public function index()
    {
        // 사용자가 팔로우하고 있는 사용자들의 ID 목록을 가져옴
        $users = auth()->user()->following()->pluck('profiles.user_id');

        // 팔로우하고 있는 사용자들의 포스트를 최신순으로 가져옴
        $posts = Post::whereIn('user_id', $users)->with('user')->latest()->paginate(5);

        // 'posts.index' 뷰에 포스트 데이터를 전달하여 렌더링
        return view('posts.index', compact('posts'));
    }

    // 새 포스트 작성 폼을 보여주는 메서드
    public function create()
    {
        // 'posts.create' 뷰를 반환
        return view('posts.create');
    }

    // 새 포스트를 저장하는 메서드
    public function store()
    {
        // 폼 데이터를 유효성 검사
        $data = request()->validate([
            'caption' => 'required',
            'image' => ['required', 'image'],
        ]);

        // 이미지 파일을 'public/uploads' 디렉토리에 저장
        $imagePath = request('image')->store('uploads', 'public');

        // 업로드된 이미지 경로를 로그에 기록 (디버깅용)
        \Log::info('Uploaded image path: ' . $imagePath);

        // 저장된 이미지의 전체 경로를 확인
        $storagePath = storage_path("app/public/{$imagePath}");
        \Log::info('Full storage path: ' . $storagePath);

        // 파일이 실제로 존재하는지 확인
        if (!file_exists($storagePath)) {
            throw new \Exception("File does not exist: {$storagePath}");
        }

        // Intervention Image를 사용하여 이미지 크기 조정
        $image = $this->imageManager->make($storagePath)->resize(1200, 1200);

        // 처리된 이미지를 저장 (명시적 경로 지정)
        $image->save($storagePath);

        // 데이터베이스에 새 포스트 생성
        auth()->user()->posts()->create([
            'caption' => $data['caption'],
            'image' => $imagePath,
        ]);

        // 사용자 프로필 페이지로 리다이렉트
        return redirect('/profile/' . auth()->user()->id);
    }

    // 특정 포스트를 보여주는 메서드
    public function show(Post $post)
    {
        // 'posts.show' 뷰에 포스트 데이터를 전달하여 렌더링
        return view('posts.show', compact('post'));
    }
}
