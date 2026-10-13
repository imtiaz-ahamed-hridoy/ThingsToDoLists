<?php

namespace App\Http\Controllers\API\Newsletter;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;

class NewsletterController extends Controller
{
    use ApiResponse;

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:100',
            'number' => 'nullable|max:20',
            'address' => 'nullable',
        ]);

        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing && $existing->status === 'subscribed') {
            return $this->error('You are already subscribed!', 200);
        }

        if ($existing && $existing->status === 'unsubscribed') {
            $existing->update(['status' => 'subscribed']);
            return $this->success('Subscribed successfully!', $existing->only(['email', 'name']), 200);
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $validated['email'],
            'name' => $validated['name'] ?? null,
            'number' => $validated['number'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => 'subscribed',
        ]);

        return $this->success('Subscribed successfully!', $subscriber->only(['email', 'name']), 200);
    }
}

