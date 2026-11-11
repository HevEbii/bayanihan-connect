<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logs a distribution against the donation(s) that funded it (FR03).
 *
 * Each funding line draws an amount from a donation: pesos for cash donations, or units for in-kind
 * donations (which must use the same standardized unit as the distribution). A draw that exceeds a
 * donation's remaining balance is rejected so the coordinator can pick a different or additional source.
 */
class DistributionRecorder
{
    /**
     * @param  array{activity_id:int, item_name:string, quantity:float, unit_id:int, distributed_at:string,
     *     notes?:?string, client_uuid?:?string, beneficiaries?:array<int>, funding:array<array{donation_id:int, amount:float}>}  $data
     */
    public function record(array $data, User $user): Distribution
    {
        return DB::transaction(function () use ($data, $user) {
            $activity = Activity::findOrFail($data['activity_id']);
            $lines = collect($data['funding'])->filter(fn ($l) => ! empty($l['donation_id']) && (float) ($l['amount'] ?? 0) > 0);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['funding' => 'Select at least one funding donation and the amount drawn from it.']);
            }
            if ($lines->pluck('donation_id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['funding' => 'Each donation can only be listed once.']);
            }

            // Lock the donations so two coordinators cannot overdraw the same balance at once.
            $donations = Donation::whereIn('id', $lines->pluck('donation_id'))->lockForUpdate()->get()->keyBy('id');
            $errors = [];
            $inKindTotal = 0.0;
            $hasCash = false;

            foreach ($lines->values() as $i => $line) {
                $donation = $donations->get($line['donation_id']);
                $amount = round((float) $line['amount'], 2);
                $key = "funding.$i.amount";

                if (! $donation) {
                    $errors["funding.$i.donation_id"] = 'The selected donation no longer exists.';
                    continue;
                }
                if ((int) $donation->program_id !== (int) $activity->program_id) {
                    $errors["funding.$i.donation_id"] = "{$donation->reference_no} is assigned to a different program.";
                    continue;
                }
                if (! $donation->isCash() && (int) $donation->unit_id !== (int) $data['unit_id']) {
                    $errors["funding.$i.donation_id"] = "{$donation->reference_no} is recorded in {$donation->unit->name}; the distribution must use the same unit.";
                    continue;
                }

                $remaining = round((float) $donation->quantity - (float) $donation->fundings()->sum('amount'), 2);
                if ($amount > $remaining) {
                    $errors[$key] = "Insufficient balance in {$donation->reference_no}: only {$donation->formatAmount($remaining)} remaining. "
                        .'Choose a different or additional funding source.';
                }

                $donation->isCash() ? $hasCash = true : $inKindTotal += $amount;
            }

            $quantity = round((float) $data['quantity'], 2);
            if (! $errors && $inKindTotal > $quantity) {
                $errors['funding'] = 'In-kind goods drawn ('.Donation::trimNumber($inKindTotal).') exceed the quantity distributed ('.Donation::trimNumber($quantity).').';
            }
            if (! $errors && ! $hasCash && $inKindTotal < $quantity) {
                $errors['funding'] = 'Only '.Donation::trimNumber($inKindTotal).' of '.Donation::trimNumber($quantity)
                    .' units are covered by the selected donations. Add another funding source.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $distribution = Distribution::create([
                'client_uuid' => $data['client_uuid'] ?? null,
                'activity_id' => $activity->id,
                'item_name' => $data['item_name'],
                'quantity' => $quantity,
                'unit_id' => $data['unit_id'],
                'distributed_at' => $data['distributed_at'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $distribution->fundings()->create(['donation_id' => $line['donation_id'], 'amount' => round((float) $line['amount'], 2)]);
            }
            $distribution->beneficiaries()->sync($data['beneficiaries'] ?? []);

            return $distribution;
        });
    }
}
