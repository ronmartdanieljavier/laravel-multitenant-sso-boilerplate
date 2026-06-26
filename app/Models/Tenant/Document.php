<?php

namespace App\Models\Tenant;

use Database\Factories\Documents\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $file_name
 * @property int $file_size
 * @property string $mime_type
 * @property int $uploaded_by_user_id
 * @property string $uploaded_by_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by_user_id',
        'uploaded_by_name',
    ];

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }
}
