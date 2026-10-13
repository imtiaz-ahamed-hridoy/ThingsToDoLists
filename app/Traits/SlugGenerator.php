<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait SlugGenerator
{
    /**
     * Generate a unique slug for any given model and field.
     *
     * Usage:
     * $slug = $this->generateSlug(Post::class, 'slug', $request->title);
     *
     * @param string $modelClass The model class, e.g., Post::class
     * @param string $field The database column for the slug, e.g., 'slug'
     * @param string $value The string to convert to a slug
     * @return string
     */
    public function generateSlug(string $modelClass, string $field, string $value): string
    {
        $slugBase = Str::slug($value);
        $slug = $slugBase;

        $counter = 1;

        while ($modelClass::where($field, $slug)->exists()) {
            $slug = $slugBase . '-' . $counter;
            $counter++;

            // safety to prevent infinite loop
            if ($counter > 50) {
                throw new \Exception("Unable to generate unique slug for: $value");
            }
        }

        return $slug;
    }
}
