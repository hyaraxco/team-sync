<?php

use App\Models\ThrPayroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('eventForReligion returns correct event for islam', function () {
    expect(ThrPayroll::eventForReligion('islam'))->toBe(ThrPayroll::EVENT_IDUL_FITRI);
});

it('eventForReligion returns correct event for kristen', function () {
    expect(ThrPayroll::eventForReligion('kristen'))->toBe(ThrPayroll::EVENT_NATAL);
});

it('eventForReligion returns correct event for katolik', function () {
    expect(ThrPayroll::eventForReligion('katolik'))->toBe(ThrPayroll::EVENT_NATAL);
});

it('eventForReligion returns correct event for hindu', function () {
    expect(ThrPayroll::eventForReligion('hindu'))->toBe(ThrPayroll::EVENT_NYEPI);
});

it('eventForReligion returns correct event for budha', function () {
    expect(ThrPayroll::eventForReligion('budha'))->toBe(ThrPayroll::EVENT_WAISAK);
});

it('eventForReligion returns correct event for konghucu', function () {
    expect(ThrPayroll::eventForReligion('konghucu'))->toBe(ThrPayroll::EVENT_IMLEK);
});

it('eventForReligion returns null for unknown religion', function () {
    expect(ThrPayroll::eventForReligion('judaism'))->toBeNull()
        ->and(ThrPayroll::eventForReligion(''))->toBeNull();
});

it('eventForReligion is case-insensitive', function () {
    expect(ThrPayroll::eventForReligion('ISLAM'))->toBe(ThrPayroll::EVENT_IDUL_FITRI)
        ->and(ThrPayroll::eventForReligion('Kristen'))->toBe(ThrPayroll::EVENT_NATAL)
        ->and(ThrPayroll::eventForReligion('HINDU'))->toBe(ThrPayroll::EVENT_NYEPI)
        ->and(ThrPayroll::eventForReligion('Budha'))->toBe(ThrPayroll::EVENT_WAISAK)
        ->and(ThrPayroll::eventForReligion('KONGHUCU'))->toBe(ThrPayroll::EVENT_IMLEK)
        ->and(ThrPayroll::eventForReligion('KATOLIK'))->toBe(ThrPayroll::EVENT_NATAL);
});

it('eventLabel returns correct label for known events', function () {
    expect(ThrPayroll::eventLabel(ThrPayroll::EVENT_IDUL_FITRI))->toBe('Idul Fitri')
        ->and(ThrPayroll::eventLabel(ThrPayroll::EVENT_NATAL))->toBe('Natal / Christmas')
        ->and(ThrPayroll::eventLabel(ThrPayroll::EVENT_NYEPI))->toBe('Nyepi')
        ->and(ThrPayroll::eventLabel(ThrPayroll::EVENT_WAISAK))->toBe('Waisak')
        ->and(ThrPayroll::eventLabel(ThrPayroll::EVENT_IMLEK))->toBe('Imlek');
});

it('eventLabel falls back to ucfirst formatted string for unknown events', function () {
    expect(ThrPayroll::eventLabel('some_unknown_event'))->toBe('Some unknown event')
        ->and(ThrPayroll::eventLabel('custom_holiday'))->toBe('Custom holiday');
});

it('getEventLabelAttribute delegates to eventLabel', function () {
    $thr = ThrPayroll::factory()->forEvent(ThrPayroll::EVENT_NATAL)->create();

    expect($thr->event_label)->toBe('Natal / Christmas');
});

it('constants have expected values', function () {
    expect(ThrPayroll::STATUS_DRAFT)->toBe('draft')
        ->and(ThrPayroll::STATUS_PROCESSING)->toBe('processing')
        ->and(ThrPayroll::STATUS_PENDING)->toBe('pending')
        ->and(ThrPayroll::STATUS_APPROVED)->toBe('approved')
        ->and(ThrPayroll::STATUS_PAID)->toBe('paid')
        ->and(ThrPayroll::MIN_DAYS_BEFORE_HOLIDAY)->toBe(7)
        ->and(ThrPayroll::MIN_TENURE_MONTHS)->toBe(1)
        ->and(ThrPayroll::FULL_THR_TENURE_MONTHS)->toBe(12);
});
