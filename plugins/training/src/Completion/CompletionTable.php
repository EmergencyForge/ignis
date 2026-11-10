<?php

declare(strict_types=1);

namespace Plugin\Training\Completion;

use EmergencyForge\Training\Completion\Completion;
use EmergencyForge\Training\Completion\CompletionStore;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;

/** Abschlüsse in `intra_training_completions`, eine Zeile je Teilnahme. */
final class CompletionTable implements CompletionStore
{
    private const TABLE = 'intra_training_completions';

    public function pending(int $limit): array
    {
        $rows = Capsule::table(self::TABLE)->where('finished', false)->orderBy('id')->limit($limit)->get(['participation_id', 'state']);

        return array_values(array_map(self::fromRow(...), $rows->all()));
    }

    public function save(Completion $completion): void
    {
        $now = Carbon::now();
        Capsule::table(self::TABLE)->updateOrInsert(
            ['participation_id' => $completion->participationId],
            [
                'passed'     => $completion->passed,
                'state'      => json_encode($completion->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'finished'   => $completion->isFinished(),
                'updated_at' => $now,
            ],
        );
        Capsule::table(self::TABLE)->where('participation_id', $completion->participationId)->whereNull('created_at')->update(['created_at' => $now]);
    }

    public function find(int $participationId): ?Completion
    {
        $row = Capsule::table(self::TABLE)->where('participation_id', $participationId)->first(['participation_id', 'state']);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * @param list<int> $participationIds
     * @return array<int, array{completion: Completion, document_id: ?int}>
     */
    public function forParticipations(array $participationIds): array
    {
        $out = [];
        foreach (Capsule::table(self::TABLE)->whereIn('participation_id', $participationIds ?: [0])->get(['participation_id', 'state', 'document_id']) as $row) {
            $out[(int) $row->participation_id] = [
                'completion'  => self::fromRow($row),
                'document_id' => $row->document_id !== null ? (int) $row->document_id : null,
            ];
        }

        return $out;
    }

    public function documentId(int $participationId): ?int
    {
        $id = Capsule::table(self::TABLE)->where('participation_id', $participationId)->value('document_id');

        return $id !== null ? (int) $id : null;
    }

    public function setDocument(int $participationId, int $documentId): void
    {
        Capsule::table(self::TABLE)->where('participation_id', $participationId)->update(['document_id' => $documentId]);
    }

    private static function fromRow(object $row): Completion
    {
        $state = json_decode((string) $row->state, true);

        return Completion::fromArray((int) $row->participation_id, is_array($state) ? $state : []);
    }
}
