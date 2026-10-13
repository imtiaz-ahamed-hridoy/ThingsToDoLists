<?php

namespace App\Http\Controllers\API\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomePageBannerResource;
use App\Repositories\Contracts\HomePageBannerRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomePageBannerApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private HomePageBannerRepositoryInterface $homePageBannerRepository
    ) {}

    /**
     * Return active banners for public homepage (cached as resolved arrays, 1 hour).
     */
    public function index()
    {
        $banners = Cache::remember('homepage:banners:active', now()->addMinutes(20), function () {
            $items = $this->homePageBannerRepository->getActive();
            return HomePageBannerResource::collection($items)->resolve();
        });

        return $this->success(
            'Home page banners fetched successfully.',
            $banners
        );
    }
}
