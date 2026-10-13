<?php

namespace Tests\Feature;

use App\Jobs\ProcessGlobalTixBookingWebhookJob;
use App\Models\BookingInfo;
use App\Models\ProviderWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalTixBookingWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_ticket_update_job_updates_booking_and_webhook_log(): void
    {
        $booking = BookingInfo::create([
            'booking_reference' => 'HWVYAVOGGT',
            'provider_type' => 'globaltix',
            'status' => 'CONFIRMED',
            'total_price' => 100,
            'currency' => 'USD',
            'globaltix_tickets' => [
                ['code' => 'GTM0G0GM', 'status' => 'READY'],
            ],
        ]);

        $log = ProviderWebhookLog::create([
            'provider' => 'globaltix',
            'event' => 'BOOKING_TICKET_UPDATE',
            'status' => 'queued',
            'headers' => [],
            'payload' => [],
        ]);

        $job = new ProcessGlobalTixBookingWebhookJob(
            $log->id,
            'BOOKING_TICKET_UPDATE',
            [
                'referenceNumber' => 'HWVYAVOGGT',
                'eTicketUrl' => 'https://new-eticket.globaltix.com/uat/download/ticket?fn=HWVYAVOGGT.pdf',
                'tickets' => [
                    ['code' => 'GTM0G0GM', 'status' => 'READY'],
                ],
            ]
        );

        $job->handle();

        $booking->refresh();
        $log->refresh();

        $this->assertSame('READY', (string) $booking->status);
        $this->assertSame('https://new-eticket.globaltix.com/uat/download/ticket?fn=HWVYAVOGGT.pdf', $booking->e_ticket_url);
        $this->assertSame('READY', strtoupper((string) data_get($booking->globaltix_tickets, '0.status')));
        $this->assertSame('processed', $log->status);
        $this->assertSame(1, (int) $log->processed);
        $this->assertSame(1, (int) $log->updated);
    }

    public function test_ticket_redeem_job_forces_terminal_status_and_ticket_updates(): void
    {
        $booking = BookingInfo::create([
            'booking_reference' => 'LB6YX7ZKGT',
            'provider_type' => 'globaltix',
            'status' => 'READY',
            'total_price' => 100,
            'currency' => 'USD',
            'globaltix_tickets' => [
                ['code' => 'GTHNCCUC', 'status' => 'READY'],
                ['code' => 'GTUV5EZT', 'status' => 'READY'],
            ],
        ]);

        $log = ProviderWebhookLog::create([
            'provider' => 'globaltix',
            'event' => 'TICKET_REDEEM',
            'status' => 'queued',
            'headers' => [],
            'payload' => [],
        ]);

        $job = new ProcessGlobalTixBookingWebhookJob(
            $log->id,
            'TICKET_REDEEM',
            [
                'referenceNumber' => 'LB6YX7ZKGT',
                'tickets' => [
                    ['id' => 823127, 'code' => 'GTHNCCUC', 'quantity' => 1],
                    ['id' => 823128, 'code' => 'GTUV5EZT', 'quantity' => 1],
                ],
            ]
        );

        $job->handle();

        $booking->refresh();
        $log->refresh();

        $ticketMap = collect((array) $booking->globaltix_tickets)->keyBy(fn (array $ticket) => strtoupper((string) ($ticket['code'] ?? '')));

        $this->assertSame('REDEEMED', (string) $booking->status);
        $this->assertSame('REDEEMED', strtoupper((string) data_get($ticketMap->get('GTHNCCUC'), 'status')));
        $this->assertSame('REDEEMED', strtoupper((string) data_get($ticketMap->get('GTUV5EZT'), 'status')));
        $this->assertSame('processed', $log->status);
        $this->assertSame(1, (int) $log->processed);
        $this->assertSame(1, (int) $log->updated);
    }
}