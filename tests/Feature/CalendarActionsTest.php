<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Personnel\AccountLink;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Calendar\Models\CalendarEvent;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Löschen und Zu- oder Absagen eines Termins lesen Kennung und Antwort über
 * EventActionRequest. Die Kennung kommt aus der Adresse, `id` im Post ist
 * der Rückfall.
 */
final class CalendarActionsTest extends FeatureTestCase
{
    private int $userId;
    private int $mitarbeiterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mitarbeiterId = FixtureFactory::personnel()->id;
        $user = FixtureFactory::user(['aktenid' => $this->mitarbeiterId]);
        $this->userId = $user->id;
        AccountLink::forget();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function termin(): int
    {
        $event = new CalendarEvent();
        $event->title      = 'Dienstbesprechung ' . uniqid();
        $event->starts_at  = date('Y-m-d 10:00:00', strtotime('+1 day'));
        $event->ends_at    = date('Y-m-d 11:00:00', strtotime('+1 day'));
        $event->visibility = CalendarEvent::VISIBILITY_ATTENDEES;
        $event->created_by = $this->userId;
        $event->save();
        Capsule::table('intra_calendar_attendees')->insert(['event_id' => $event->id, 'mitarbeiter_id' => $this->mitarbeiterId]);

        return (int) $event->id;
    }

    private function antwort(int $eventId): ?string
    {
        $wert = Capsule::table('intra_calendar_attendees')->where('event_id', $eventId)->where('mitarbeiter_id', $this->mitarbeiterId)->value('response');

        return $wert === null ? null : (string) $wert;
    }

    #[Test]
    public function zusagen_und_absagen(): void
    {
        $id = $this->termin();

        $this->assertRedirect($this->post('/calendar/respond', ['response' => 'accepted'], ['query' => ['id' => (string) $id]]), '/calendar');
        $this->assertSame('accepted', $this->antwort($id));

        // Rückfall auf die Kennung im Post
        $this->post('/calendar/respond', ['id' => (string) $id, 'response' => 'declined']);
        $this->assertSame('declined', $this->antwort($id));
    }

    #[Test]
    public function unbekannte_antwort_wird_abgewiesen(): void
    {
        $id = $this->termin();
        $vorher = $this->antwort($id);

        $this->post('/calendar/respond', ['response' => 'vielleicht'], ['query' => ['id' => (string) $id]]);
        $this->assertSame('Ungültige Antwort.', $_SESSION['flash']['text'] ?? null);

        $this->post('/calendar/respond', ['response' => ['accepted']], ['query' => ['id' => (string) $id]]);
        $this->assertSame('Ungültige Eingabe.', $_SESSION['flash']['text'] ?? null);

        $this->assertSame($vorher, $this->antwort($id));
    }

    #[Test]
    public function termin_loeschen(): void
    {
        $id = $this->termin();

        $this->assertRedirect($this->post('/calendar/delete', [], ['query' => ['id' => (string) $id]]), '/calendar');
        $this->assertFalse(Capsule::table('intra_calendar_events')->where('id', $id)->exists());

        $id = $this->termin();
        $this->post('/calendar/delete', ['id' => (string) $id]);
        $this->assertFalse(Capsule::table('intra_calendar_events')->where('id', $id)->exists());
    }

    #[Test]
    public function loeschen_mit_fremdem_feld_wird_abgewiesen(): void
    {
        $id = $this->termin();

        $this->post('/calendar/delete', ['id' => (string) $id, 'force' => '1']);

        $this->assertSame('Ungültige Eingabe.', $_SESSION['flash']['text'] ?? null);
        $this->assertTrue(Capsule::table('intra_calendar_events')->where('id', $id)->exists());
    }
}
