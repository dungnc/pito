<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/9/20
 * Time: 10:10 AM
 */

namespace App\Repositories\Implement\HistoryModel;


use App\Repositories\Contracts\HistoryModelBase;

class HistoryType extends HistoryModelBase {

    protected $table = 'types';

    protected $fillable = ['class'];

    public $timestamps = false;
}
