<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(int $companyId, string $documentType): string
    {
        return DB::transaction(function () use ($companyId, $documentType) {
            $sequence = NumberSequence::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $prefixes = config('sellix.document_prefixes', []);
                $sequence = NumberSequence::query()->withoutGlobalScope('company')->create([
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'prefix' => $prefixes[$documentType] ?? strtoupper(substr($documentType, 0, 3)) . '-',
                    'next_number' => 1,
                    'padding' => (int) config('sellix.number_padding', 5),
                ]);

                $sequence = NumberSequence::query()
                    ->withoutGlobalScope('company')
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $number = $sequence->prefix . str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);
            $sequence->next_number = $sequence->next_number + 1;
            $sequence->save();

            return $number;
        });
    }

    public function peek(int $companyId, string $documentType): string
    {
        $sequence = NumberSequence::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->first();

        if (! $sequence) {
            $prefixes = config('sellix.document_prefixes', []);
            $prefix = $prefixes[$documentType] ?? strtoupper(substr($documentType, 0, 3)) . '-';
            $padding = (int) config('sellix.number_padding', 5);

            return $prefix . str_pad('1', $padding, '0', STR_PAD_LEFT);
        }

        return $sequence->prefix . str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);
    }
}
