<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Testimonial\StoreTestimonialRequest;
use App\Http\Requests\Testimonial\UpdateTestimonialRequest;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use App\Services\TestimonialService;

class TestimonialCmsController extends Controller
{
    public function __construct(
        private TestimonialService $testimonialService,
        private TestimonialRepositoryInterface $testimonialRepository
    ) {}

    public function index()
    {
        $testimonials = $this->testimonialRepository->getLatest();
        return view('backend.layouts.cmsSettings.testimonial.index', compact('testimonials'));
    }

    public function create()
    {
        return view('backend.layouts.cmsSettings.testimonial.create');
    }

    public function store(StoreTestimonialRequest $request)
    {
        $this->testimonialService->create(
            $request->validated(),
            $request->file('image'),
            $request->file('avater'),
            $request->file('country_icon'),
            $request->input('country_icon_manual')
        );

        return redirect()->route('cmsSettings.testimonial.index')
            ->with('success', 'Testimonial Created Successfully');
    }

    public function editTestimonial($id)
    {
        $testimonial = $this->testimonialRepository->findOrFail((int) $id);
        return view('backend.layouts.cmsSettings.testimonial.edit', compact('testimonial'));
    }

    public function update(UpdateTestimonialRequest $request, $id)
    {
        $testimonial = $this->testimonialRepository->findOrFail((int) $id);

        $this->testimonialService->update(
            $testimonial,
            $request->validated(),
            $request->file('image'),
            $request->file('avater'),
            $request->file('country_icon'),
            $request->input('country_icon_manual')
        );

        return redirect()->route('cmsSettings.testimonial.index')
            ->with('success', 'Testimonial Updated Successfully');
    }

    public function destroy($id)
    {
        $testimonial = $this->testimonialRepository->findOrFail((int) $id);
        $this->testimonialService->delete($testimonial);

        return redirect()->back()->with('success', 'Testimonial Deleted Successfully');
    }
}
