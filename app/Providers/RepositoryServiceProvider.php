<?php

namespace App\Providers;

use App\Repositories\ActivityRepository;
use App\Repositories\AvailabilityCategoryPriceRepository;
use App\Repositories\AvailabilityCheckRepository;
use App\Repositories\BarcodeTypeRepository;
use App\Repositories\BlogArticleRepository;
use App\Repositories\BlogCategoryRepository;
use App\Repositories\BlogPostRepository;
use App\Repositories\BlogTagRepository;
use App\Repositories\BookingQuestionRepository;
use App\Repositories\BookingInfoRepository;
use App\Repositories\CancellationPolicyDetailRepository;
use App\Repositories\CancellationPolicyRepository;
use App\Repositories\CategoriesRepository;
use App\Repositories\CategoryTypeRepository;
use App\Repositories\ChunkUploadSessionRepository;
use App\Repositories\CityRepository;
use App\Repositories\HotelKeywordRepository;
use App\Repositories\CreatorCommunityApplicationRepository;
use App\Repositories\CreatorRepository;
use App\Repositories\CreatorSponsoredExperienceApplicationRepository;
use App\Repositories\CurrencyRepository;
use App\Repositories\DestinationHighlightRepository;
use App\Repositories\DynamicPageRepository;
use App\Repositories\EmailLogRepository;
use App\Repositories\EmailTemplateRepository;
use App\Repositories\FaqRepository;
use App\Repositories\FeatureTypeRepository;
use App\Repositories\HomePageBannerRepository;
use App\Repositories\HomePageSectionRepository;
use App\Repositories\HotelContactFieldRepository;
use App\Repositories\ImageRepository;
use App\Repositories\KeywordRepository;
use App\Repositories\LocationRepository;
use App\Repositories\MailSettingRepository;
use App\Repositories\OptionPricingCategoryRepository;
use App\Repositories\PassengerFieldRepository;
use App\Repositories\PriceRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductOptionRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductVideoRepository;
use App\Repositories\ProviderWebhookLogRepository;
use App\Repositories\RecentlyViewedRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\SystemSettingRepository;
use App\Repositories\TempProductRepository;
use App\Repositories\TestimonialRepository;
use App\Repositories\TieredPriceRepository;
use App\Repositories\UserRepository;
use App\Repositories\VendorInvoiceRepository;
use App\Repositories\VendorPaymentConfirmationRepository;
use App\Repositories\VendorRepository;
use App\Repositories\VideoRepository;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Repositories\Contracts\AvailabilityCategoryPriceRepositoryInterface;
use App\Repositories\Contracts\AvailabilityCheckRepositoryInterface;
use App\Repositories\Contracts\BarcodeTypeRepositoryInterface;
use App\Repositories\Contracts\BlogArticleRepositoryInterface;
use App\Repositories\Contracts\BlogCategoryRepositoryInterface;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Contracts\BlogTagRepositoryInterface;
use App\Repositories\Contracts\BookingQuestionRepositoryInterface;
use App\Repositories\Contracts\BookingInfoRepositoryInterface;
use App\Repositories\Contracts\CancellationPolicyDetailRepositoryInterface;
use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\CategoryTypeRepositoryInterface;
use App\Repositories\Contracts\ChunkUploadSessionRepositoryInterface;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\HotelKeywordRepositoryInterface;
use App\Repositories\Contracts\CreatorCommunityApplicationRepositoryInterface;
use App\Repositories\Contracts\CreatorRepositoryInterface;
use App\Repositories\Contracts\CreatorSponsoredExperienceApplicationRepositoryInterface;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Repositories\Contracts\DestinationHighlightRepositoryInterface;
use App\Repositories\Contracts\DynamicPageRepositoryInterface;
use App\Repositories\Contracts\EmailLogRepositoryInterface;
use App\Repositories\Contracts\EmailTemplateRepositoryInterface;
use App\Repositories\Contracts\FaqRepositoryInterface;
use App\Repositories\Contracts\FeatureTypeRepositoryInterface;
use App\Repositories\Contracts\HomePageBannerRepositoryInterface;
use App\Repositories\Contracts\HomePageSectionRepositoryInterface;
use App\Repositories\Contracts\HotelContactFieldRepositoryInterface;
use App\Repositories\Contracts\ImageRepositoryInterface;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\MailSettingRepositoryInterface;
use App\Repositories\Contracts\OptionPricingCategoryRepositoryInterface;
use App\Repositories\Contracts\PassengerFieldRepositoryInterface;
use App\Repositories\Contracts\PriceRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductOptionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use App\Repositories\Contracts\ProviderWebhookLogRepositoryInterface;
use App\Repositories\Contracts\RecentlyViewedRepositoryInterface;
use App\Repositories\Contracts\RepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Repositories\Contracts\TempProductRepositoryInterface;
use App\Repositories\Contracts\TestimonialRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorInvoiceRepositoryInterface;
use App\Repositories\Contracts\VendorPaymentConfirmationRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Contracts\VideoRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     *
     * Add new repository bindings here as they are created.
     */
    public function register(): void
    {
        // Base (guard — every module must bind its own concrete repository)
        $this->app->bind(RepositoryInterface::class, function ($app) {
            throw new \RuntimeException('RepositoryInterface must be bound to a concrete implementation.');
        });

        // Modules (alphabetical order)
        $this->app->bind(
            AvailabilityCategoryPriceRepositoryInterface::class,
            AvailabilityCategoryPriceRepository::class
        );

        $this->app->bind(
            AvailabilityCheckRepositoryInterface::class,
            AvailabilityCheckRepository::class
        );

        $this->app->bind(
            BarcodeTypeRepositoryInterface::class,
            BarcodeTypeRepository::class
        );

        $this->app->bind(
            BlogArticleRepositoryInterface::class,
            BlogArticleRepository::class
        );

        $this->app->bind(
            BlogCategoryRepositoryInterface::class,
            BlogCategoryRepository::class
        );

        $this->app->bind(
            BlogPostRepositoryInterface::class,
            BlogPostRepository::class
        );

        $this->app->bind(
            BlogTagRepositoryInterface::class,
            BlogTagRepository::class
        );

        $this->app->bind(
            BookingInfoRepositoryInterface::class,
            BookingInfoRepository::class
        );

        $this->app->bind(
            BookingQuestionRepositoryInterface::class,
            BookingQuestionRepository::class
        );

        $this->app->bind(
            CancellationPolicyDetailRepositoryInterface::class,
            CancellationPolicyDetailRepository::class
        );

        $this->app->bind(
            CancellationPolicyRepositoryInterface::class,
            CancellationPolicyRepository::class
        );

        $this->app->bind(
            CategoriesRepositoryInterface::class,
            CategoriesRepository::class
        );

        $this->app->bind(
            CategoryTypeRepositoryInterface::class,
            CategoryTypeRepository::class
        );

        $this->app->bind(
            ChunkUploadSessionRepositoryInterface::class,
            ChunkUploadSessionRepository::class
        );

        $this->app->bind(
            CityRepositoryInterface::class,
            CityRepository::class
        );

        $this->app->bind(
            CreatorCommunityApplicationRepositoryInterface::class,
            CreatorCommunityApplicationRepository::class
        );

        $this->app->bind(
            CreatorRepositoryInterface::class,
            CreatorRepository::class
        );

        $this->app->bind(
            CreatorSponsoredExperienceApplicationRepositoryInterface::class,
            CreatorSponsoredExperienceApplicationRepository::class
        );

        $this->app->bind(
            CurrencyRepositoryInterface::class,
            CurrencyRepository::class
        );

        $this->app->bind(
            DestinationHighlightRepositoryInterface::class,
            DestinationHighlightRepository::class
        );

        $this->app->bind(
            DynamicPageRepositoryInterface::class,
            DynamicPageRepository::class
        );

        $this->app->bind(
            EmailLogRepositoryInterface::class,
            EmailLogRepository::class
        );

        $this->app->bind(
            EmailTemplateRepositoryInterface::class,
            EmailTemplateRepository::class
        );

        $this->app->bind(
            FaqRepositoryInterface::class,
            FaqRepository::class
        );

        $this->app->bind(
            FeatureTypeRepositoryInterface::class,
            FeatureTypeRepository::class
        );

        $this->app->bind(
            HomePageBannerRepositoryInterface::class,
            HomePageBannerRepository::class
        );

        $this->app->bind(
            HomePageSectionRepositoryInterface::class,
            HomePageSectionRepository::class
        );

        $this->app->bind(
            HotelContactFieldRepositoryInterface::class,
            HotelContactFieldRepository::class
        );

        $this->app->bind(
            HotelKeywordRepositoryInterface::class,
            HotelKeywordRepository::class
        );

        $this->app->bind(
            ImageRepositoryInterface::class,
            ImageRepository::class
        );

        $this->app->bind(
            KeywordRepositoryInterface::class,
            KeywordRepository::class
        );

        $this->app->bind(
            LocationRepositoryInterface::class,
            LocationRepository::class
        );

        $this->app->bind(
            MailSettingRepositoryInterface::class,
            MailSettingRepository::class
        );

        $this->app->bind(
            OptionPricingCategoryRepositoryInterface::class,
            OptionPricingCategoryRepository::class
        );

        $this->app->bind(
            PassengerFieldRepositoryInterface::class,
            PassengerFieldRepository::class
        );

        $this->app->bind(
            PriceRepositoryInterface::class,
            PriceRepository::class
        );

        $this->app->bind(
            ProductImageRepositoryInterface::class,
            ProductImageRepository::class
        );

        $this->app->bind(
            ProductOptionRepositoryInterface::class,
            ProductOptionRepository::class
        );

        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        $this->app->bind(
            ProductVideoRepositoryInterface::class,
            ProductVideoRepository::class
        );

        $this->app->bind(
            ProviderWebhookLogRepositoryInterface::class,
            ProviderWebhookLogRepository::class
        );

        $this->app->bind(
            RecentlyViewedRepositoryInterface::class,
            RecentlyViewedRepository::class
        );

        $this->app->bind(
            ReviewRepositoryInterface::class,
            ReviewRepository::class
        );

        $this->app->bind(
            SystemSettingRepositoryInterface::class,
            SystemSettingRepository::class
        );

        $this->app->bind(
            TempProductRepositoryInterface::class,
            TempProductRepository::class
        );

        $this->app->bind(
            TestimonialRepositoryInterface::class,
            TestimonialRepository::class
        );

        $this->app->bind(
            TieredPriceRepositoryInterface::class,
            TieredPriceRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            VendorInvoiceRepositoryInterface::class,
            VendorInvoiceRepository::class
        );

        $this->app->bind(
            VendorPaymentConfirmationRepositoryInterface::class,
            VendorPaymentConfirmationRepository::class
        );

        $this->app->bind(
            VendorRepositoryInterface::class,
            VendorRepository::class
        );

        $this->app->bind(
            VideoRepositoryInterface::class,
            VideoRepository::class
        );

        // Activity (last to keep the alphabetical order)
        $this->app->bind(
            ActivityRepositoryInterface::class,
            ActivityRepository::class
        );
    }

    /**
     * Bootstrap any repository services.
     */
    public function boot(): void
    {
        //
    }
}
