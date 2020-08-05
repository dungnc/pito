<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/9/20
 * Time: 10:12 AM
 */

namespace App\Repositories\Implement\HistoryModel;


use App\Repositories\Contracts\HistoryModelBase;

class HistoryWarehouse extends HistoryModelBase {

    protected $table = 'data_warehouse';

    protected $fillable = ['type_id', 'object_id', 'data'];

    public function getMigrationAttribute($value) {
        return json_decode(json_decode($value));
    }

    public function getDataSnapshotAttribute($value) {
        return json_decode($value);
    }

    public function getAgentAttribute($value) {
        return json_decode($value);
    }
}
