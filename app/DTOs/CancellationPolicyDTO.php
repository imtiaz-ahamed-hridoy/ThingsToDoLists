<?php

namespace App\DTOs;

/**
 * Normalized cancellation policy from any provider.
 *
 * Bokun:     cancellationPolicy.title, policyType, penaltyRules
 * GlobalTix: option.isCancellable, cancellationPolicy, cancellationNotes
 * Supplier:  user-entered policy text
 */
final class CancellationPolicyDTO
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $policyType,
        public readonly ?string $description,
        public readonly mixed   $tax = null,
        public readonly array   $penaltyRules = [],
        public readonly array   $metadata = [],
    ) {}

    public static function fromBokunData(array $data): self
    {
        $policy = $data['cancellationPolicy'] ?? [];

        return new self(
            title:        $policy['title'] ?? 'Standard',
            policyType:   $policy['policyType'] ?? $policy['policyTypeEnum'] ?? 'STANDARD',
            description:  $policy['description'] ?? $policy['title'] ?? null,
            tax:          $policy['tax'] ?? null,
            penaltyRules: $policy['penaltyRules'] ?? [],
            metadata:     $policy,
        );
    }

    public static function fromGlobalTixData(array $data): self
    {
        $isCancellable = (bool) ($data['isCancellable'] ?? false);

        return new self(
            title:       $isCancellable ? 'Cancellable' : 'Non-refundable',
            policyType:  $isCancellable ? 'STANDARD' : 'NON_REFUNDABLE',
            description: $isCancellable ? 'Cancellable' : 'Non-refundable',
            tax:         null,
            metadata:    ['cancellation_notes' => $data['cancellationNotes'] ?? []],
        );
    }

    public static function fromSupplierData(array $data): self
    {
        return new self(
            title:       $data['cancellation_title'] ?? 'Standard',
            policyType:  $data['cancellation_type'] ?? 'STANDARD',
            description: $data['cancellation_description'] ?? null,
        );
    }
}
