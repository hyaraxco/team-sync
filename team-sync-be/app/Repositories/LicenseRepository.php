<?php

namespace App\Repositories;

use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use Illuminate\Database\Eloquent\Collection;

class LicenseRepository implements LicenseRepositoryInterface
{
    public function getAll(): Collection
    {
        return License::query()
            ->latest()
            ->get();
    }

    public function getById(int $id): License
    {
        return License::query()->findOrFail($id);
    }

    public function getActive(): ?License
    {
        return License::query()->valid()->latest('activated_at')->first();
    }

    public function deactivateAll(): void
    {
        License::query()
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function create(array $data): License
    {
        return License::query()->create($data);
    }

    public function update(License $license, array $data): License
    {
        $license->update($data);

        return $license->refresh();
    }

    public function delete(License $license): void
    {
        $license->delete();
    }
}
