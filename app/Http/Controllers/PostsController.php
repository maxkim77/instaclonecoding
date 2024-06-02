<?php

namespace App\Http\Controllers;

use App\Post;
use Illuminate\Http\Request;
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

    public function index()
    {
        $users = auth()->user()->following()->pluck('profiles.user_id');

        $posts = Post::whereIn('user_id', $users)->with('user')->latest()->paginate(5);

        return view('posts.index', compact('posts'));
    }

    public function create()
    {
        return view('posts.create');
    }

    public function store()
    {
        $data = request()->validate([
            'caption' => 'required',
            'image' => ['required', 'image'],
        ]);

        // 파일 업로드 경로 확인
        $imagePath = request('image')->store('uploads', 'public');

        // 업로드된 이미지 경로 출력 (디버깅용)
        \Log::info('Uploaded image path: ' . $imagePath);

        // 이미지 경로와 파일 확인
        $storagePath = storage_path("app/public/{$imagePath}");
        \Log::info('Full storage path: ' . $storagePath);

        if (!file_exists($storagePath)) {
            throw new \Exception("File does not exist: {$storagePath}");
        }

        // Intervention Image로 이미지 처리
        $image = $this->imageManager->read($storagePath)->resize(1200, 1200);
        $image->save($storagePath);  // 저장 경로를 명시적으로 지정

        // 데이터베이스에 포스트 생성
        auth()->user()->posts()->create([
            'caption' => $data['caption'],
            'image' => $imagePath,
        ]);

        return redirect('/profile/' . auth()->user()->id);
    }

    public function show(Post $post)
    {
        return view('posts.show', compact('post'));
    }
}
