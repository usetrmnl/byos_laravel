<?php

declare(strict_types=1);

use App\Models\Plugin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

test('iCal plugin parses Google Calendar invitation event', function (): void {
    // Set test time close to the event in the issue
    Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00', 'Europe/Budapest'));

    $icalContent = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Example Corp.//EN
BEGIN:VEVENT
DTSTART;TZID=Europe/Budapest:20260311T100000
DTEND;TZID=Europe/Budapest:20260311T110000
DTSTAMP:20260301T100000Z
ORGANIZER:mailto:organizer@example.com
UID:xxxxxxxxxxxxxxxxxxx@google.com
SEQUENCE:0
DESCRIPTION:-::~:~::~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~
 ·:~:~:~:~:~:~:~:~::~:~::-
 Csatlakozás a Google Meet szolgáltatással: https://meet.google.com/xxx-xxxx-xxx

 További információ a Meetről: https://support.google.com/a/users/answer/9282720

 Kérjük, ne szerkeszd ezt a szakaszt.
 -::~:~::~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~:~
 ·:~:~:~:~:~:~:~:~:~:~:~:~::~:~::-
LOCATION:Meet XY Street, ZIP; https://meet.google.com/xxx-xxxx-xxx
SUMMARY:Meeting
STATUS:CONFIRMED
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;RSVP=TRUE;X-NUM-GUESTS=0;X-PM-TOKEN=REDACTED;PARTSTAT=ACCEPTED:mailto:participant1@example.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;RSVP=TRUE;CN=participant2@example.com;X-NUM-GUESTS=0;X-PM-TOKEN=REDACTED;PARTSTAT=ACCEPTED:mailto:participant2@example.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;RSVP=TRUE;CN=participant3@example.com;X-NUM-GUESTS=0;X-PM-TOKEN=REDACTED;PARTSTAT=NEEDS-ACTION:mailto:participant3@example.com
END:VEVENT
END:VCALENDAR
ICS;

    Http::fake([
        'example.com/calendar.ics' => Http::response($icalContent, 200, ['Content-Type' => 'text/calendar']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/calendar.ics',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();
    $plugin->refresh();

    expect($plugin->data_payload)->not->toHaveKey('error');
    expect($plugin->data_payload)->toHaveKey('ical');
    expect($plugin->data_payload['ical'])->toHaveCount(1);
    expect($plugin->data_payload['ical'][0]['SUMMARY'])->toBe('Meeting');

    Carbon::setTestNow();
});

test('iCal plugin parses recurring events with multiple BYDAY correctly', function (): void {
    // Set test now to Monday 2024-03-25
    Carbon::setTestNow(Carbon::parse('2024-03-25 12:00:00', 'UTC'));

    $icalContent = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Example Corp.//EN
BEGIN:VEVENT
DESCRIPTION:XXX-REDACTED
RRULE:FREQ=WEEKLY;UNTIL=20250604T220000Z;INTERVAL=1;BYDAY=TU,TH;WKST=MO
UID:040000008200E00074C5B7101A82E00800000000E07AF34F937EDA01000000000000000
 01000000061F3E918C753424E8154B36E55452933
SUMMARY:Recurring Meeting
DTSTART;VALUE=DATE:20240326
DTEND;VALUE=DATE:20240327
DTSTAMP:20240605T082436Z
CLASS:PUBLIC
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR
ICS;

    Http::fake([
        'example.com/recurring.ics' => Http::response($icalContent, 200, ['Content-Type' => 'text/calendar']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/recurring.ics',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();
    $plugin->refresh();

    $ical = $plugin->data_payload['ical'];

    // Week of March 25, 2024:
    // Tue March 26: 2024-03-26 (DTSTART)
    // Thu March 28: 2024-03-28 (Recurrence)

    // The parser window is now-7 days to now+30 days.
    // Window: 2024-03-18 to 2024-04-24.

    $summaries = collect($ical)->pluck('SUMMARY');
    expect($summaries)->toContain('Recurring Meeting');

    $dates = collect($ical)->map(fn ($event) => Carbon::parse($event['DTSTART'])->format('Y-m-d'))->values();

    // Check if Tuesday March 26 is present
    expect($dates)->toContain('2024-03-26');

    // Check if Thursday March 28 is present (THIS IS WHERE IT IS EXPECTED TO FAIL BASED ON THE ISSUE)
    expect($dates)->toContain('2024-03-28');

    Carbon::setTestNow();
});

test('iCal plugin parses recurring events with multiple BYDAY and specific DTSTART correctly', function (): void {
    // Set test now to Monday 2024-03-25
    Carbon::setTestNow(Carbon::parse('2024-03-25 12:00:00', 'UTC'));

    $icalContent = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
X-WR-TIMEZONE:UTC
PRODID:-//Example Corp.//EN
BEGIN:VEVENT
RRULE:FREQ=WEEKLY;UNTIL=20250604T220000Z;INTERVAL=1;BYDAY=TU,TH;WKST=MO
UID:recurring-event-2
SUMMARY:Recurring Meeting 2
DTSTART:20240326T100000
DTEND:20240326T110000
DTSTAMP:20240605T082436Z
END:VEVENT
END:VCALENDAR
ICS;

    Http::fake([
        'example.com/recurring2.ics' => Http::response($icalContent, 200, ['Content-Type' => 'text/calendar']),
    ]);

    $plugin = Plugin::factory()->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/recurring2.ics',
        'polling_verb' => 'get',
    ]);

    $plugin->updateDataPayload();
    $plugin->refresh();

    $ical = $plugin->data_payload['ical'];
    $dates = collect($ical)->map(fn ($event) => Carbon::parse($event['DTSTART'])->format('Y-m-d'))->values();

    expect($dates)->toContain('2024-03-26');
    expect($dates)->toContain('2024-03-28');

    Carbon::setTestNow();
});
