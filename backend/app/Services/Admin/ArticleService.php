<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleService
{
    private const STORAGE_PATH = 'articles/featured';

    public function createArticle(Admin $admin, array $data, ?UploadedFile $featuredImage = null): Article
    {
        $status = $data['status'] ?? ArticleStatus::Draft->value;

        $articleData = [
            'admin_id' => $admin->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'],
            'status' => $status,
            'excerpt' => $data['excerpt'] ?? null,
        ];

        if ($status === ArticleStatus::Published->value) {
            $articleData['published_at'] = now();
        }

        if ($featuredImage) {
            $articleData['featured_image'] = $this->uploadFeaturedImage($featuredImage);
        }

        return Article::create($articleData);
    }

    private function uploadFeaturedImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $image, $filename);

        return $filename;
    }
}
