<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalYearMaster extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'fiscal_year_master';

    protected $primaryKey = 'fiscal_year_master_id';

    public $timestamps = false;

    protected $fillable = [
        'fy',
        'fy_startdate',
        'fy_enddate',
        'fy_status',
        'created_date',
    ];

    protected $casts = [
        'fy_startdate' => 'datetime',
        'fy_enddate' => 'datetime',
        'created_date' => 'datetime',
    ];

    public function isActive(): bool
    {
        $status = strtoupper(trim((string) $this->fy_status));

        return in_array($status, ['1', 'Y', 'YES', 'ACTIVE', 'TRUE'], true)
            || $this->fy_status === 1
            || $this->fy_status === true;
    }
}
