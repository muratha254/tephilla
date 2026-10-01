<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * An explicit token wins. Without one, the same user and payload map to one token
     * so a retried POST cannot create a second document.
     */
    public function key(Request $request, string $scope, array $payload): string
    {
        $explicit = trim((string) ($request->headers->get('Idempotency-Key') ?: $request->input('idempotency_key', '')));
        if ($explicit !== '') {
            return mb_substr($explicit, 0, 80);
        }

        return substr(hash('sha256', $scope . '|' . (int) auth()->id() . '|' . json_encode($payload)), 0, 80);
    }

    /**
     * Run $create once for this token. A repeat returns the original record id.
     *
     * @return array{replay:bool, subject_id:int, subject:mixed}
     */
    public function remember(int $companyId, ?int $userId, string $scope, string $token, callable $create): array
    {
        $existing = $this->find($companyId, $scope, $token);
        if ($existing && $existing->subject_id) {
            return ['replay' => true, 'subject_id' => (int) $existing->subject_id, 'subject' => null];
        }

        try {
            $subject = DB::transaction(function () use ($companyId, $userId, $scope, $token, $create) {
                $locked = IdempotencyKey::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $companyId)
                    ->where('scope', $scope)
                    ->where('token', $token)
                    ->lockForUpdate()
                    ->first();

                if ($locked && $locked->subject_id) {
                    return $locked;
                }

                if (! $locked) {
                    $locked = IdempotencyKey::query()->create([
                        'company_id' => $companyId,
                        'user_id' => $userId,
                        'scope' => $scope,
                        'token' => $token,
                    ]);
                }

                $created = $create();
                $locked->update([
                    'subject_type' => $created->getMorphClass(),
                    'subject_id' => $created->getKey(),
                ]);

                return $created;
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueConflict($e)) {
                throw $e;
            }
            $existing = $this->find($companyId, $scope, $token);
            if ($existing && $existing->subject_id) {
                return ['replay' => true, 'subject_id' => (int) $existing->subject_id, 'subject' => null];
            }
            throw $e;
        }

        if ($subject instanceof IdempotencyKey) {
            return ['replay' => true, 'subject_id' => (int) $subject->subject_id, 'subject' => null];
        }

        return ['replay' => false, 'subject_id' => (int) $subject->getKey(), 'subject' => $subject];
    }

    private function find(int $companyId, string $scope, string $token): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('scope', $scope)
            ->where('token', $token)
            ->first();
    }

    private function isUniqueConflict(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();

        return $sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
