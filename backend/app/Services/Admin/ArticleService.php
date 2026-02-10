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

    public function updateCategory(Article $article, string $category): Article
    {
        if ($article->category->value === $category) {
            return $article;
        }

        $article->update(['category' => $category]);

        return $article;
    }

    public function updateStatus(Article $article, string $status): Article
    {
        if ($article->status->value === $status) {
            return $article;
        }

        $data = ['status' => $status];

        if ($status === ArticleStatus::Published->value) {
            $data['published_at'] = now();
        } else {
            $data['published_at'] = null;
        }

        $article->update($data);

        return $article;
    }

    public function updateArticle(Article $article, array $data, ?UploadedFile $featuredImage = null): Article
    {
        $updateData = [];

        foreach (['title', 'content', 'excerpt', 'category'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (array_key_exists('status', $data)) {
            $newStatus = $data['status'];
            $updateData['status'] = $newStatus;

            if ($article->status->value !== $newStatus) {
                if ($newStatus === ArticleStatus::Published->value) {
                    $updateData['published_at'] = now();
                } else {
                    $updateData['published_at'] = null;
                }
            }
        }

        if ($featuredImage) {
            $this->deleteOldImage($article);
            $updateData['featured_image'] = $this->uploadFeaturedImage($featuredImage);
        }

        if (!empty($updateData)) {
            $article->update($updateData);
        }

        return $article;
    }

    public function deleteArticle(Article $article): void
    {
        $this->deleteOldImage($article);
        $article->delete();
    }

    private function deleteOldImage(Article $article): void
    {
        if ($article->featured_image) {
            Storage::disk('public')->delete(self::STORAGE_PATH . '/' . $article->featured_image);
        }
    }

    private function uploadFeaturedImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $image, $filename);

        return $filename;
    }
}
