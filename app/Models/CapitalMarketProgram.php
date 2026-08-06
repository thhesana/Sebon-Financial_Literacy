<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalMarketProgram extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'CapitalMarketProgram';

    protected $primaryKey = 'CapitalMarketProgramID';

    public $timestamps = false;

    protected $fillable = [
        'CapitalMarketProgramName',
        'CapitalMarketProgramDate',
        'CapitalMarketProgramDetail',
        'Status',
    ];

    public function isActive(): bool
    {
        $status = strtoupper(trim((string) $this->Status));

        return in_array($status, ['1', 'Y', 'YES', 'ACTIVE', 'TRUE'], true)
            || $this->Status === 1
            || $this->Status === true;
    }

    /**
     * @return array<int, array{StartDate: string, EndDate: string}>
     */
    public function dateRanges(): array
    {
        $raw = trim((string) $this->CapitalMarketProgramDate);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $ranges = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $start = trim((string) ($row['StartDate'] ?? ''));
            $end = trim((string) ($row['EndDate'] ?? ''));
            if ($start === '' && $end === '') {
                continue;
            }
            $ranges[] = [
                'StartDate' => $start,
                'EndDate' => $end,
            ];
        }

        return $ranges;
    }

    public function dateRangesLabel(): string
    {
        $ranges = $this->dateRanges();
        if ($ranges === []) {
            return '—';
        }

        return collect($ranges)
            ->map(function (array $r) {
                $start = $r['StartDate'] ?: '?';
                $end = $r['EndDate'] ?: '?';

                return $start === $end ? $start : ($start.' → '.$end);
            })
            ->implode('; ');
    }

    public function displayLabel(): string
    {
        $name = (string) ($this->CapitalMarketProgramName ?: 'Program');
        $dates = $this->dateRangesLabel();

        return $dates === '—' ? $name : ($name.' ('.$dates.')');
    }

    /**
     * Fiscal years whose window overlaps any program date range.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\FiscalYearMaster>  $fiscalYears
     * @return list<string>
     */
    public function matchingFiscalYears($fiscalYears): array
    {
        $ranges = $this->dateRanges();
        if ($ranges === []) {
            return [];
        }

        $matched = [];
        foreach ($fiscalYears as $fy) {
            if (! $fy->fy_startdate || ! $fy->fy_enddate) {
                continue;
            }
            $fyStart = $fy->fy_startdate->format('Y-m-d');
            $fyEnd = $fy->fy_enddate->format('Y-m-d');

            foreach ($ranges as $range) {
                $start = $range['StartDate'] ?? '';
                $end = $range['EndDate'] ?? '';
                if ($start === '' || $end === '') {
                    continue;
                }
                if ($start <= $fyEnd && $end >= $fyStart) {
                    $matched[] = (string) $fy->fy;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\FiscalYearMaster>  $fiscalYears
     */
    public function fiscalYearsLabel($fiscalYears): string
    {
        $labels = $this->matchingFiscalYears($fiscalYears);

        return $labels === [] ? '—' : implode(', ', $labels);
    }
}
