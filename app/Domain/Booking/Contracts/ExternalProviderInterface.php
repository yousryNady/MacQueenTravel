<?php

namespace App\Domain\Booking\Contracts;

interface ExternalProviderInterface
{
    public function search(array $criteria): array;

    public function book(array $details): array;

    public function cancel(string $reference): bool;

    public function getProviderName(): string;
}
