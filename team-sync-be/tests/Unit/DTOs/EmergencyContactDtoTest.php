<?php

namespace Tests\Unit\DTOs;

use App\DTOs\EmergencyContactDto;
use App\Models\EmergencyContact;
use Tests\TestCase;

class EmergencyContactDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = EmergencyContactDto::fromArray($this->payload());

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame('Jane Doe', $dto->full_name);
        $this->assertSame('Spouse', $dto->relationship);
        $this->assertSame('08123456789', $dto->phone);
        $this->assertSame('jane@example.com', $dto->email);
    }

    public function test_from_array_uses_null_for_optional_email(): void
    {
        $dto = EmergencyContactDto::fromArray([
            'staff_member_id' => 1,
            'full_name' => 'Jane Doe',
            'relationship' => 'Spouse',
            'phone' => '08123456789',
        ]);

        $this->assertNull($dto->email);
    }

    public function test_to_array_preserves_payload_shape(): void
    {
        $dto = EmergencyContactDto::fromArray($this->payload());

        $this->assertSame($this->payload(), $dto->toArray());
    }

    public function test_from_array_for_update_merges_with_existing_contact(): void
    {
        $contact = $this->makeEmergencyContact([
            'staff_member_id' => 10,
            'full_name' => 'Old Name',
            'relationship' => 'Parent',
            'phone' => '08111111',
            'email' => 'old@example.com',
        ]);

        $dto = EmergencyContactDto::fromArrayForUpdate([
            'full_name' => 'New Name',
        ], $contact);

        $this->assertSame(10, $dto->staff_member_id);
        $this->assertSame('New Name', $dto->full_name);
        $this->assertSame('Parent', $dto->relationship);
        $this->assertSame('08111111', $dto->phone);
        $this->assertSame('old@example.com', $dto->email);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $contact = $this->makeEmergencyContact([
            'staff_member_id' => 10,
            'full_name' => 'Old Name',
        ]);

        $dto = EmergencyContactDto::fromArrayForUpdate($this->payload(), $contact);

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame('Jane Doe', $dto->full_name);
        $this->assertSame('jane@example.com', $dto->email);
    }

    private function makeEmergencyContact(array $attributes): EmergencyContact
    {
        $contact = new EmergencyContact();
        foreach ($attributes as $key => $value) {
            $contact->{$key} = $value;
        }

        return $contact;
    }

    private function payload(): array
    {
        return [
            'staff_member_id' => 1,
            'full_name' => 'Jane Doe',
            'relationship' => 'Spouse',
            'phone' => '08123456789',
            'email' => 'jane@example.com',
        ];
    }
}
