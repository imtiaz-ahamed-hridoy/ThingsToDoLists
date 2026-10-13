<?php

namespace App\Enums;

enum ModelEnum: string
{
    case Plan = 'App\Models\Plan';
    case PlanOpt = 'App\Models\PlanOption';
    case Product = 'App\Models\Product';
    case ProductCt = 'App\Models\ProductCategory';
    case Offer = 'App\Models\Offer';
    case Activity = 'App\Models\Activity';
    case Availability = 'App\Models\Availability';
    case Coupon = 'App\Models\Coupon';
    case CouponUsage = 'App\Models\CouponUsage';
    case DynamicPage = 'App\Models\DynamicPage';
    case EmailLog = 'App\Models\EmailLog';
    case EmailTemplate = 'App\Models\EmailTemplate';
    case Enquiry = 'App\Models\Enquiry';
    case Faq = 'App\Models\Faq';
    case MailSetting = 'App\Models\MailSetting';
    case Review = 'App\Models\Review';
    case Store = 'App\Models\Store';
    case Subscription = 'App\Models\Subscription';
    case SystemSetting = 'App\Models\SystemSetting';
    case User = 'App\Models\User';
    case UserDetails = 'App\Models\UserDetails';
    case Viewer = 'App\Models\Viewer';


    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Plan',
            self::PlanOpt => 'Plan',
            self::Product => 'Product',
            self::ProductCt => 'Product',
            self::Offer => 'Offer',
            self::Activity => 'Activity Logs',
            self::Availability => 'Store Availability',
            self::Coupon => 'Coupon',
            self::CouponUsage => 'Coupon',
            self::DynamicPage => 'DynamicPage',
            self::EmailLog => 'EmailLog',
            self::EmailTemplate => 'EmailTemplate',
            self::Enquiry => 'Enquiry',
            self::Faq => 'Faqs',
            self::MailSetting => 'MailSetting',
            self::Review => 'Review',
            self::Store => 'Store',
            self::Subscription => 'Subscription',
            self::SystemSetting => 'SystemSetting',
            self::User => 'User',
            self::UserDetails => 'User',
            self::Viewer => 'Viewer',

        };
    }
}
