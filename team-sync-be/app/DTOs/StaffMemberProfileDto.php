<?php

namespace App\DTOs;

use App\Models\StaffMemberProfile;

class StaffMemberProfileDto
{
    public function __construct(
        public readonly string $user_id,
        public readonly string $code,
        public readonly string $identity_number,
        public readonly ?string $npwp,
        public readonly ?string $bpjs_ketenagakerjaan,
        public readonly ?string $bpjs_kesehatan,
        public readonly ?string $ptkp_status,
        public readonly string $phone,
        public readonly ?string $date_of_birth,
        public readonly string $gender,
        public readonly ?string $religion,
        public readonly ?string $marital_status,
        public readonly ?string $blood_type,
        public readonly string $place_of_birth,
        public readonly string $address,
        public readonly string $city,
        public readonly string $postal_code,
        public readonly ?string $profile_photo,
        // Job Information
        public readonly string $job_title,
        public readonly ?int $team_id,
        public readonly string $status,
        public readonly string $employment_type,
        public readonly string $work_location,
        public readonly string $start_date,
        public readonly float $monthly_salary,
        // Bank Information
        public readonly string $bank_name,
        public readonly string $account_number,
        public readonly string $account_holder_name,
        // Emergency Contacts
        public readonly array $emergency_contacts = [],
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'code' => $this->code,
            'identity_number' => $this->identity_number,
            'npwp' => $this->npwp,
            'bpjs_ketenagakerjaan' => $this->bpjs_ketenagakerjaan,
            'bpjs_kesehatan' => $this->bpjs_kesehatan,
            'ptkp_status' => $this->ptkp_status,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'blood_type' => $this->blood_type,
            'place_of_birth' => $this->place_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'profile_photo' => $this->profile_photo,
            // Job Information
            'job_title' => $this->job_title,
            'team_id' => $this->team_id,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->monthly_salary,
            // Bank Information
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_holder_name' => $this->account_holder_name,
            // Emergency Contacts
            'emergency_contacts' => $this->emergency_contacts,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            code: $data['code'],
            identity_number: $data['identity_number'],
            npwp: $data['npwp'] ?? null,
            bpjs_ketenagakerjaan: $data['bpjs_ketenagakerjaan'] ?? null,
            bpjs_kesehatan: $data['bpjs_kesehatan'] ?? null,
            ptkp_status: $data['ptkp_status'] ?? null,
            phone: $data['phone'],
            date_of_birth: $data['date_of_birth'],
            gender: $data['gender'],
            religion: $data['religion'] ?? null,
            marital_status: $data['marital_status'] ?? null,
            blood_type: $data['blood_type'] ?? null,
            place_of_birth: $data['place_of_birth'],
            address: $data['address'],
            city: $data['city'],
            postal_code: $data['postal_code'],
            profile_photo: $data['profile_photo'] ?? null,
            // Job Information
            job_title: $data['job_title'],
            team_id: $data['team_id'] ?? null,
            status: $data['status'],
            employment_type: $data['employment_type'],
            work_location: $data['work_location'],
            start_date: $data['start_date'],
            monthly_salary: (float) $data['monthly_salary'],
            // Bank Information
            bank_name: $data['bank_name'],
            account_number: $data['account_number'],
            account_holder_name: $data['account_holder_name'],
            // Emergency Contacts
            emergency_contacts: $data['emergency_contacts'] ?? [],
        );
    }

    public static function fromArrayForUpdate(array $data, StaffMemberProfile $existingProfile): self
    {
        return new self(
            user_id: $data['user_id'] ?? $existingProfile->user_id,
            code: $data['code'] ?? $existingProfile->code ?? '',
            identity_number: $data['identity_number'] ?? $existingProfile->identity_number,
            npwp: $data['npwp'] ?? $existingProfile->npwp,
            bpjs_ketenagakerjaan: $data['bpjs_ketenagakerjaan'] ?? $existingProfile->bpjs_ketenagakerjaan,
            bpjs_kesehatan: $data['bpjs_kesehatan'] ?? $existingProfile->bpjs_kesehatan,
            ptkp_status: $data['ptkp_status'] ?? $existingProfile->ptkp_status,
            phone: $data['phone'] ?? $existingProfile->phone,
            date_of_birth: $data['date_of_birth'] ?? ($existingProfile->date_of_birth ? date('Y-m-d', strtotime((string) $existingProfile->date_of_birth)) : null),
            gender: $data['gender'] ?? $existingProfile->gender,
            religion: $data['religion'] ?? $existingProfile->religion,
            marital_status: $data['marital_status'] ?? $existingProfile->marital_status,
            blood_type: $data['blood_type'] ?? $existingProfile->blood_type,
            place_of_birth: $data['place_of_birth'] ?? $existingProfile->place_of_birth,
            address: $data['address'] ?? $existingProfile->address,
            city: $data['city'] ?? $existingProfile->city,
            postal_code: $data['postal_code'] ?? $existingProfile->postal_code,
            profile_photo: $data['profile_photo'] ?? data_get($existingProfile, 'user.profile_photo'),
            // Job Information
            job_title: $data['job_title'] ?? $existingProfile->jobInformation?->job_title ?? '',
            team_id: $data['team_id'] ?? $existingProfile->jobInformation?->team_id,
            status: $data['status'] ?? $existingProfile->jobInformation?->status ?? 'active',
            employment_type: $data['employment_type'] ?? $existingProfile->jobInformation?->employment_type ?? 'full_time',
            work_location: $data['work_location'] ?? $existingProfile->jobInformation?->work_location ?? 'office',
            start_date: $data['start_date'] ?? ($existingProfile->jobInformation?->start_date ? date('Y-m-d', strtotime((string) $existingProfile->jobInformation->start_date)) : now()->format('Y-m-d')),
            monthly_salary: isset($data['monthly_salary']) ? (float) $data['monthly_salary'] : ($existingProfile->jobInformation?->monthly_salary ?? 0.0),
            // Bank Information
            bank_name: $data['bank_name'] ?? $existingProfile->bankInformation?->bank_name ?? '',
            account_number: $data['account_number'] ?? $existingProfile->bankInformation?->account_number ?? '',
            account_holder_name: $data['account_holder_name'] ?? $existingProfile->bankInformation?->account_holder_name ?? '',
            // Emergency Contacts
            emergency_contacts: $data['emergency_contacts'] ?? $existingProfile->emergency_contacts ?? [],
        );
    }
}
