<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomDetail extends Model
{
    use HasFactory;


    protected $table = 'bom_details';

    protected $fillable = [
        'bom_upload_id',
        'json_data',
        'fields_count',
        'empty_fields',
        'non_empty_fields',
        'completion_percentage',
        'priority',
        'total_fg_count',
        'total_mpn_count',
        'total_cpn_count',
        'is_valid_bom',
        'proto',
        'eau',
        'centum_id',
        'assigned_to',
        'quote_id',
        'new_bid',
        'total_assembly_count',
        'total_commodity_count',
        'volume_count',
        'volume_quantity',
        'settings',
    ];



    
    public function bomUpload()
    {
        return $this->belongsTo(BomUpload::class);
    }
}
