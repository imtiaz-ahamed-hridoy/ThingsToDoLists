<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogPostRequest;
use App\Http\Requests\Blog\UpdateBlogPostRequest;
use App\Repositories\Contracts\BlogCategoryRepositoryInterface;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Contracts\BlogTagRepositoryInterface;
use App\Services\BlogPostService;

class BlogPostController extends Controller
{
    public function __construct(
        private BlogPostService $blogService,
        private BlogPostRepositoryInterface $blogPostRepository,
        private BlogCategoryRepositoryInterface $blogCategoryRepository,
        private BlogTagRepositoryInterface $blogTagRepository
    ) {}

    public function index()
    {
        $posts = $this->blogPostRepository->getParents();
        return view('backend.layouts.cmsSettings.blogs.index', compact('posts'));
    }

    public function create()
    {
        $categories = $this->blogCategoryRepository->query()->orderBy('name')->get();
        $tags = $this->blogTagRepository->query()->orderBy('name')->get();
        return view('backend.layouts.cmsSettings.blogs.create', compact('categories', 'tags'));
    }

    public function store(StoreBlogPostRequest $request)
    {
        $this->blogService->createPost(
            $request->validated(),
            $request->file('featured_image'),
            $this->normalizeTags($request),
            [
                'titles'       => $request->input('child_title', []),
                'subtitles'    => $request->input('child_subtitle', []),
                'descriptions' => $request->input('child_description', []),
                'images'       => $request->file('child_image', []),
            ]
        );

        return redirect()->route('cmsSettings.blogs.index')
            ->with('success', 'Blog post created successfully');
    }

    public function edit($id)
    {
        $post = $this->blogPostRepository->findWithChildren((int) $id);
        abort_unless($post, 404);
        $categories = $this->blogCategoryRepository->query()->orderBy('name')->get();
        $tags = $this->blogTagRepository->query()->orderBy('name')->get();
        return view('backend.layouts.cmsSettings.blogs.edit', compact('post', 'categories', 'tags'));
    }

    public function update(UpdateBlogPostRequest $request, $id)
    {
        $post = $this->blogPostRepository->findOrFail((int) $id);

        $this->blogService->updatePost(
            $post,
            $request->validated(),
            $request->file('featured_image'),
            $this->normalizeTags($request),
            [
                'titles'        => $request->input('child_title', []),
                'subtitles'     => $request->input('child_subtitle', []),
                'descriptions'  => $request->input('child_description', []),
                'images'        => $request->file('child_image', []),
                'clear_images'  => $request->input('clear_child_image', []),
                'existing_ids'  => $request->input('child_id', []),
            ]
        );

        return redirect()->route('cmsSettings.blogs.index')
            ->with('success', 'Blog post updated successfully');
    }

    public function destroy($id)
    {
        $post = $this->blogPostRepository->findOrFail((int) $id);
        $this->blogService->deletePost($post);

        return redirect()->route('cmsSettings.blogs.index')
            ->with('success', 'Blog post deleted');
    }

    public function toggleStatus($id)
    {
        $post = $this->blogPostRepository->findOrFail((int) $id);
        $this->blogService->toggleStatus($post);

        return redirect()->back()
            ->with('success', 'Blog publish status updated');
    }

    private function normalizeTags($request): array
    {
        $tags = $request->input('tags', []);
        $newTags = $request->input('tags_new');
        if (!empty($newTags) && is_string($newTags)) {
            foreach (array_map('trim', explode(',', $newTags)) as $name) {
                if ($name !== '') $tags[] = $name;
            }
        }
        return is_array($tags) ? $tags : [];
    }
}
