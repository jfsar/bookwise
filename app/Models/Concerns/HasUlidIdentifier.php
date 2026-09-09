<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUlidIdentifier
{
    public static function bootHasUlidIdentifier(): void
    {
        static::creating(function (self $model) {
            $model->ulid ??= (string) Str::ulid();
        });
    }
}
