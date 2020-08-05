<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Permission\Models\Permission as PermissionModel;

class Permission extends PermissionModel
{
    use HasTranslations;
    //
}
