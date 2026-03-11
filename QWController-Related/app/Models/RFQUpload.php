<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RFQUpload extends Model
{
    use HasFactory;
    
    protected $table = 'rfq_uploads';

    // Fillable fields for mass assignment
    protected $fillable = [
        'project_id',
        'supplier_id',
        'bom_id',
        'file_name',
        'file_path',
        'upload_date',
        'validation_status',
        'validation_errors',
        'status',
        'remarks',
        'ai_scan_enabled',
    ];

    // Cast 'ai_scan_enabled' as a boolean
    protected $casts = [
        'ai_scan_enabled' => 'boolean',
        'upload_date' => 'datetime',
    ];

    // Define the relationship with the Project model
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // Define the relationship with the Supplier model
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    // Define the relationship with the BomUpload model
    public function bomUpload()
    {
        return $this->belongsTo(BomUpload::class, 'bom_id');
    }
}