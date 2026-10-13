<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerEmailJob;
use App\Mail\CustomUserMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendCustomerEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_implements_should_queue(): void
    {
        $reflection = new \ReflectionClass(SendCustomerEmailJob::class);

        $this->assertTrue(
            $reflection->implementsInterface(ShouldQueue::class),
            'SendCustomerEmailJob must implement ShouldQueue to be queued.'
        );
    }

    public function test_job_has_correct_queue_configuration(): void
    {
        $mail = new CustomUserMail('Test Subject', '<p>Test body</p>');
        $job = new SendCustomerEmailJob($mail, 'test@example.com', 'test_key_123');

        $this->assertEquals('email', $job->queue, 'Job must be routed to the "email" queue.');
        $this->assertEquals(60, $job->timeout, 'Email jobs should timeout after 60 seconds.');
        $this->assertEquals(3, $job->tries, 'Email jobs should retry up to 3 times.');
        $this->assertEquals(15, $job->backoff, 'Email jobs should wait 15 seconds between retries.');
    }

    public function test_job_dispatches_to_email_queue(): void
    {
        Queue::fake();

        $mail = new CustomUserMail('Queue Test', '<p>Queue routing test</p>');
        SendCustomerEmailJob::dispatch($mail, 'user@test.com', 'queue_test_456');

        Queue::assertPushed(SendCustomerEmailJob::class, function (SendCustomerEmailJob $job) {
            return $job->queue === 'email'
                && $job->to === 'user@test.com'
                && $job->uniqueKey === 'queue_test_456';
        });
    }

    public function test_job_dispatched_only_once_per_dispatch(): void
    {
        Queue::fake();

        $mail = new CustomUserMail('Count Test', '<p>Count test</p>');
        SendCustomerEmailJob::dispatch($mail, 'count@test.com', 'count_test');

        Queue::assertPushed(SendCustomerEmailJob::class, 1);
    }

    public function test_job_stores_mailable_and_recipient(): void
    {
        $mail = new CustomUserMail('Subject Here', '<h1>HTML body</h1>');
        $job = new SendCustomerEmailJob($mail, 'recipient@example.com', 'storage_test');

        $this->assertSame($mail, $job->mailable);
        $this->assertSame('recipient@example.com', $job->to);
        $this->assertSame('storage_test', $job->uniqueKey);
    }

    public function test_multiple_email_jobs_can_be_dispatched_independently(): void
    {
        Queue::fake();

        $mail1 = new CustomUserMail('First', '<p>First</p>');
        $mail2 = new CustomUserMail('Second', '<p>Second</p>');

        SendCustomerEmailJob::dispatch($mail1, 'first@test.com', 'multi_1');
        SendCustomerEmailJob::dispatch($mail2, 'second@test.com', 'multi_2');

        Queue::assertPushed(SendCustomerEmailJob::class, 2);

        $jobs = Queue::pushed(SendCustomerEmailJob::class);

        $this->assertCount(2, $jobs);
        $this->assertEquals('first@test.com', $jobs[0]->to);
        $this->assertEquals('second@test.com', $jobs[1]->to);
        $this->assertEquals('multi_1', $jobs[0]->uniqueKey);
        $this->assertEquals('multi_2', $jobs[1]->uniqueKey);
    }

    public function test_job_can_hold_identical_unique_key_across_instances(): void
    {
        $mail = new CustomUserMail('Key Test', '<p>Key</p>');

        $job1 = new SendCustomerEmailJob($mail, 'key@test.com', 'same_key');
        $job2 = new SendCustomerEmailJob($mail, 'key@test.com', 'same_key');

        $this->assertSame('same_key', $job1->uniqueKey);
        $this->assertSame('same_key', $job2->uniqueKey);
    }

    public function test_job_without_unique_key_defaults_to_empty_string(): void
    {
        $mail = new CustomUserMail('No Key', '<p>No key</p>');
        $job = new SendCustomerEmailJob($mail, 'nokey@test.com');

        $this->assertSame('', $job->uniqueKey);
    }
}
