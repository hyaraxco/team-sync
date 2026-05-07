<?php

namespace App\Interfaces;

use App\Models\License;
use Illuminate\Database\Eloquent\Collection;

interface LicenseRepositoryInterface
{
    public function getAll(): Collection;

    public function getById(int $id): License;

    public function getActive(): ?License;

    public function deactivateAll(): void;

    public function create(array $data): License;

    public function update(License $license, array $data): License;

    public function delete(License $license): void;
}
