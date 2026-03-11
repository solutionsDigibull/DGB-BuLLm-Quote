<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BomUpload extends Model
{
    use HasFactory;

    protected $table = 'bom_uploads';

    protected $fillable = [
        'user_id',
        'version_bom_id',
        'folder_name',
        'file_name',
        'version',
        'status',
        'project_id',
    ];

    /**
     * Get the user that owns the BomUpload.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    /**
     * Get the bom details associated with the BomUpload.
     */
    public function bomDetails()
    {
        return $this->hasOne(BomDetail::class, 'bom_upload_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function rfqEmails()
    {
        return $this->hasMany(RfqEmail::class);
    }
}
