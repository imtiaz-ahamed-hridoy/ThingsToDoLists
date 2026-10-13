<?php

test("user registration and login", function () {
    $response = $this->postJson("/api/v1/register", [
        "name" => "Regular User",
        "email" => "regular_login@test.com",
        "password" => "Password123!",
        "password_confirmation" => "Password123!",
    ]);
    $response->assertStatus(201);

    $login = $this->postJson("/api/v1/login", [
        "email" => "regular_login@test.com",
        "password" => "Password123!"
    ]);
    $login->assertStatus(200);
});

test("user registration does not return a token before email verification", function () {
    $email = 'pending_verify_' . uniqid() . '@test.com';

    $response = $this->postJson("/api/v1/register", [
        "name" => "Pending Verify User",
        "email" => $email,
        "password" => "Password123!",
        "password_confirmation" => "Password123!",
    ]);

    $response->assertStatus(201)
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('otp');

    $otp = \Illuminate\Support\Facades\DB::table('otps')->where('identifier', $email)->latest('id')->value('token');
    expect($otp)->not->toBeEmpty();

    $verification = $this->postJson("/api/v1/email/verify", [
        "email" => $email,
        "otp" => $otp,
    ]);

    $verification->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['token_type', 'token'],
        ]);

    expect($verification->json('data.token'))->not->toBeEmpty();
});

test("creator registration and login", function () {
    $response = $this->postJson("/api/v1/creator/register", [
        "full_name" => "Creator User",
        "email" => "creator_login@test.com",
        "password" => "Password123!",
        "password_confirmation" => "Password123!",
        "partner_type" => "creator",
        "currency" => "USD",
        "country" => "USA",
        "main_channel" => "instagram",
        "is_agree" => true,
        "policy_agreed" => true,
        "posts_on_social_media" => true,
        "instagram_handle" => "hellothere"
    ]);
    $response->assertStatus(201)
        ->assertJsonMissingPath('data.otp');

    $otp = \Illuminate\Support\Facades\DB::table('otps')->where('identifier', 'creator_login@test.com')->latest('id')->value('token');
    $this->postJson("/api/v1/creator/email/verify", [
        "email" => "creator_login@test.com",
        "otp" => $otp,
    ])->assertStatus(200);

    $login = $this->postJson("/api/v1/creator/login", [
        "email" => "creator_login@test.com",
        "password" => "Password123!"
    ]);
    $login->assertStatus(200);
});

test("supplier registration and login", function () {
    $response = $this->postJson("/api/v1/supplier/register", [
        "name" => "Supplier User",
        "email" => "supplier_login@test.com",
        "password" => "Password123!",
        "password_confirmation" => "Password123!",
        "business_type" => "company",
        "activities_count" => "1-10",
        "locations" => ["Miami", "New York"],
        "country" => "USA",
        "currency" => "USD",
        "brand_name" => "Super Supplier",
        "is_agree" => true
    ]);
    $response->assertStatus(201)
        ->assertJsonMissingPath('otp');

    $login = $this->postJson("/api/v1/supplier/login", [
        "email" => "supplier_login@test.com",
        "password" => "Password123!"
    ]);
    $login->assertStatus(200);
});
