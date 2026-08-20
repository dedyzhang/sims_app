<?php

namespace App\Services\Keuangan;

use App\Models\RkasPlan;
use Illuminate\Support\Collection;

class RkasValidationService
{
    private const MAX_SIGNED_BIGINT = '9223372036854775807';

    public function inspect(RkasPlan $plan): Collection
    {
        $plan->loadMissing(['referenceSet', 'items.reference']);
        $findings = collect();
        $references = $plan->referenceSet?->references?->keyBy('uuid') ?? collect();
        $totalPlanned = 0;
        $componentTotals = [];

        if (! $plan->referenceSet) {
            $findings->push($this->finding('reference_set_missing', 'error', 'Paket referensi ARKAS tidak ditemukan.'));
        } elseif (! $plan->referenceSet->is_active && $plan->status === RkasPlan::STATUS_DRAFT) {
            $findings->push($this->finding('reference_set_inactive', 'warning', 'Paket referensi ini sudah tidak aktif untuk rencana baru, tetapi tetap dipertahankan untuk arsip historis.'));
        }

        if ($plan->referenceSet && (
            (int) $plan->referenceSet->tahun_anggaran !== (int) $plan->tahun_anggaran
            || $this->normalise((string) $plan->referenceSet->jenjang) !== $this->normalise((string) $plan->jenjang)
            || $this->normalise((string) $plan->referenceSet->sumber_dana) !== $this->normalise((string) $plan->sumber_dana)
        )) {
            $findings->push($this->finding('reference_scope_mismatch', 'error', 'Tahun, jenjang, dan sumber dana RKAS harus sama dengan paket referensi yang dipilih.', null, [
                'plan' => ['tahun_anggaran' => $plan->tahun_anggaran, 'jenjang' => $plan->jenjang, 'sumber_dana' => $plan->sumber_dana],
                'reference' => ['tahun_anggaran' => $plan->referenceSet->tahun_anggaran, 'jenjang' => $plan->referenceSet->jenjang, 'sumber_dana' => $plan->referenceSet->sumber_dana],
            ]));
        }

        if ((int) $plan->pagu <= 0) {
            $findings->push($this->finding('pagu_required', 'error', 'Pagu sumber dana harus lebih besar dari Rp0.'));
        }

        foreach ($plan->items as $item) {
            $reference = $item->reference_uuid ? $references->get($item->reference_uuid) : null;
            $itemTotal = (int) $item->total;

            if (! $reference || $reference->kode_kegiatan !== $item->kode_kegiatan) {
                $findings->push($this->finding('reference_invalid', 'error', "Kode kegiatan {$item->kode_kegiatan} tidak cocok dengan paket referensi aktif.", $item));
            }

            if ((int) $item->bulan_dianggarkan < 1 || (int) $item->bulan_dianggarkan > 12) {
                $findings->push($this->finding('month_invalid', 'error', 'Bulan dianggarkan harus berada pada rentang 1 sampai 12.', $item));
            }

            if (trim((string) $item->uraian_belanja) === '' || mb_strlen(trim((string) $item->uraian_belanja)) < 3) {
                $findings->push($this->finding('description_required', 'error', 'Uraian belanja wajib diisi minimal 3 karakter.', $item));
            }

            if (trim((string) $item->satuan) === '') {
                $findings->push($this->finding('unit_required', 'error', 'Satuan belanja wajib diisi.', $item));
            }

            try {
                $expected = $this->calculateTotal((int) $item->jumlah, (int) $item->harga_satuan);
                if ($expected !== $itemTotal) {
                    $findings->push($this->finding('total_mismatch', 'error', 'Total item tidak sama dengan jumlah dikali harga satuan.', $item, [
                        'expected_total' => $expected,
                        'stored_total' => $itemTotal,
                    ]));
                }
            } catch (\Throwable $e) {
                $findings->push($this->finding('total_overflow', 'error', 'Perkalian jumlah dan harga satuan melebihi kapasitas nominal yang didukung.', $item));
            }

            if ((int) $item->jumlah <= 0 || (int) $item->harga_satuan < 0 || $itemTotal < 0) {
                $findings->push($this->finding('amount_invalid', 'error', 'Jumlah harus lebih besar dari 0 dan nominal tidak boleh negatif.', $item));
            }

            try {
                $totalPlanned = $this->add($totalPlanned, $itemTotal);
            } catch (\Throwable) {
                $findings->push($this->finding('plan_total_overflow', 'error', 'Total rencana melebihi kapasitas nominal yang didukung.'));
                break;
            }

            $component = $this->normalise((string) ($item->komponen ?: $reference?->komponen));
            if ($component !== '') {
                $componentTotals[$component] = ($componentTotals[$component] ?? 0) + $itemTotal;
            }
        }

        if ($totalPlanned > (int) $plan->pagu) {
            $findings->push($this->finding('pagu_exceeded', 'error', 'Total rencana melebihi pagu sumber dana.', null, [
                'total_planned' => $totalPlanned,
                'pagu' => (int) $plan->pagu,
            ]));
        } elseif ($totalPlanned < (int) $plan->pagu) {
            $findings->push($this->finding('pagu_remaining', 'warning', 'Masih ada sisa pagu yang belum direncanakan.', null, [
                'total_planned' => $totalPlanned,
                'pagu' => (int) $plan->pagu,
                'remaining' => (int) $plan->pagu - $totalPlanned,
            ]));
        }

        $rules = is_array($plan->referenceSet?->rules) ? $plan->referenceSet->rules : [];
        foreach (($rules['percentages'] ?? []) as $rule) {
            $components = array_map(fn ($value) => $this->normalise((string) $value), (array) ($rule['components'] ?? [$rule['component'] ?? '']));
            $amount = 0;
            foreach ($componentTotals as $component => $componentAmount) {
                foreach ($components as $allowed) {
                    if ($allowed !== '' && ($component === $allowed || str_contains($component, $allowed))) {
                        $amount += $componentAmount;
                        break;
                    }
                }
            }

            if ((int) $plan->pagu <= 0) {
                continue;
            }

            $actualBps = $this->percentageBps($amount, (int) $plan->pagu);
            $label = (string) ($rule['label'] ?? 'Komponen');
            $min = isset($rule['min_bps']) ? (int) $rule['min_bps'] : null;
            $max = isset($rule['max_bps']) ? (int) $rule['max_bps'] : null;

            if ($min !== null && $actualBps < $min) {
                $findings->push($this->finding('component_minimum', 'error', "Komponen {$label} belum memenuhi batas minimum yang ditetapkan.", null, [
                    'actual_bps' => $actualBps, 'minimum_bps' => $min, 'component_total' => $amount,
                ]));
            }
            if ($max !== null && $actualBps > $max) {
                $findings->push($this->finding('component_maximum', 'error', "Komponen {$label} melebihi batas maksimum yang ditetapkan.", null, [
                    'actual_bps' => $actualBps, 'maximum_bps' => $max, 'component_total' => $amount,
                ]));
            }
        }

        return $findings;
    }

    public function calculateTotal(int $quantity, int $unitPrice): int
    {
        if ($quantity < 0 || $unitPrice < 0) {
            throw new \InvalidArgumentException('Nominal tidak boleh negatif.');
        }
        if (function_exists('bcmul')) {
            $result = bcmul((string) $quantity, (string) $unitPrice, 0);
            $this->assertFitsSignedBigInt($result);

            return (int) $result;
        }
        if ($quantity !== 0 && $unitPrice > intdiv(PHP_INT_MAX, $quantity)) {
            throw new \OverflowException('Nominal melebihi kapasitas integer.');
        }

        return $quantity * $unitPrice;
    }

    private function add(int $left, int $right): int
    {
        if (function_exists('bcadd')) {
            $result = bcadd((string) $left, (string) $right, 0);
            $this->assertFitsSignedBigInt($result);

            return (int) $result;
        }
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new \OverflowException('Nominal melebihi kapasitas integer.');
        }

        return $left + $right;
    }

    private function percentageBps(int $amount, int $base): int
    {
        if (function_exists('bcdiv') && function_exists('bcmul')) {
            return (int) bcdiv(bcmul((string) $amount, '10000', 0), (string) $base, 0);
        }
        if ($amount > intdiv(PHP_INT_MAX, 10000)) {
            return (int) floor(($amount / max(1, $base)) * 10000);
        }

        return intdiv($amount * 10000, max(1, $base));
    }

    private function assertFitsSignedBigInt(string $value): void
    {
        $normalised = ltrim($value, '+');
        $normalised = ltrim($normalised, '0');
        $normalised = $normalised === '' ? '0' : $normalised;

        if (strlen($normalised) > strlen(self::MAX_SIGNED_BIGINT)
            || (strlen($normalised) === strlen(self::MAX_SIGNED_BIGINT) && strcmp($normalised, self::MAX_SIGNED_BIGINT) > 0)) {
            throw new \OverflowException('Nominal melebihi kapasitas integer.');
        }
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function finding(string $code, string $severity, string $message, ?object $item = null, array $details = []): array
    {
        return ['kode' => $code, 'severity' => $severity, 'message' => $message, 'item_uuid' => $item?->uuid, 'details' => $details];
    }
}
