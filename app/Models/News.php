<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $fillable = [
        'title',
        'category',
        'status',
        'content',
        'image_url',
        'slug',
        'author_id',
        'branch_id',
        'is_global',
        'published_at',
        'views'
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'news_branch');
    }

    protected $casts = [
        'published_at' => 'datetime'
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function incrementViews()
    {
        $this->increment('views');
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($news) {
            if (empty($news->slug)) {
                $news->slug = \Illuminate\Support\Str::slug($news->title) . '-' . time();
            }
        });
    }
}
