<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/9/20
 * Time: 10:09 AM
 */

namespace App\Repositories\Contracts;


use Illuminate\Database\Eloquent\Model;

class HistoryModelBase extends Model {

    protected $connection = 'history_mysql';

}
