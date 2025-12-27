<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Contracts\TenantServiceInterface;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService implements TenantServiceInterface
{
    public function __construct(
        private WalletServiceInterface $walletService
    ) {}

    public function paginate(): LengthAwarePaginator
    {
        return Tenant::query()
            ->latest()
            ->paginate(15);
    }

    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $data['domain'] = $this->generateTenantDomain($data['slug']);

            $tenant = Tenant::create($data);

            $this->walletService->getWallet($tenant->id);

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['is_active' => true]);

        return $tenant->fresh();
    }

    public function deactivate(Tenant $tenant): Tenant
    {
        $tenant->update(['is_active' => false]);

        return $tenant->fresh();
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    private function generateTenantDomain(string $slug): string
    {
        $baseDomain = config('app.base_domain', 'macqueen.com');
        return "{$slug}.{$baseDomain}";
    }

}
