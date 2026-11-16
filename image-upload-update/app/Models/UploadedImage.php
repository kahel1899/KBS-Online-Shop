<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedImage extends Model
{
    protected $fillable = [
        'mime',
        'size',
        'width',
        'height',
        'data',
    ];
}
