<?php

namespace App\Services;

use App\Models\Testimonial;
use Illuminate\Http\UploadedFile;

class TestimonialService
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    public function create(array $data, ?UploadedFile $image, ?UploadedFile $avater, ?UploadedFile $countryIcon, ?string $countryIconManual): Testimonial
    {
        $testimonial = new Testimonial();
        $testimonial->name = $data['name'];
        $testimonial->country = $data['country'] ?? null;
        $testimonial->description = $data['description'] ?? null;
        $testimonial->footer_description = $data['footer_description'] ?? null;
        $testimonial->rating = $data['rating'];
        $testimonial->status = (bool) ($data['status'] ?? true);

        if ($image) {
            $testimonial->image = $this->imageUploadService->upload($image, 'cms/testimonials');
        }
        if ($avater) {
            $testimonial->avater = $this->imageUploadService->upload($avater, 'cms/testimonials/avatars');
        }
        if ($countryIcon) {
            $testimonial->country_icon = $this->imageUploadService->upload($countryIcon, 'cms/testimonials/flags');
        } elseif ($countryIconManual) {
            $testimonial->country_icon = $countryIconManual;
        }

        $testimonial->save();

        return $testimonial;
    }

    public function update(Testimonial $testimonial, array $data, ?UploadedFile $image, ?UploadedFile $avater, ?UploadedFile $countryIcon, ?string $countryIconManual): Testimonial
    {
        $testimonial->name = $data['name'];
        $testimonial->country = $data['country'] ?? $testimonial->country;
        $testimonial->description = $data['description'] ?? $testimonial->description;
        $testimonial->footer_description = $data['footer_description'] ?? $testimonial->footer_description;
        $testimonial->rating = $data['rating'];
        $testimonial->status = (bool) ($data['status'] ?? $testimonial->status);

        if ($image) {
            $this->imageUploadService->delete($testimonial->getRawOriginal('image'));
            $testimonial->image = $this->imageUploadService->upload($image, 'cms/testimonials');
        }

        if ($avater) {
            $this->imageUploadService->delete($testimonial->getRawOriginal('avater'));
            $testimonial->avater = $this->imageUploadService->upload($avater, 'cms/testimonials/avatars');
        }

        if ($countryIcon) {
            $this->imageUploadService->delete($testimonial->getRawOriginal('country_icon'));
            $testimonial->country_icon = $this->imageUploadService->upload($countryIcon, 'cms/testimonials/flags');
        } elseif ($countryIconManual) {
            $testimonial->country_icon = $countryIconManual;
        }

        $testimonial->save();

        return $testimonial;
    }

    public function delete(Testimonial $testimonial): void
    {
        $this->imageUploadService->delete($testimonial->getRawOriginal('image'));
        $this->imageUploadService->delete($testimonial->getRawOriginal('avater'));
        $this->imageUploadService->delete($testimonial->getRawOriginal('country_icon'));

        $testimonial->delete();
    }
}
