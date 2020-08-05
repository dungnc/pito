<?php

/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/7/20
 * Time: 11:10 AM
 */

namespace App\Traits;

use App\Repositories\Implement\HistoryMySqlSimpleDriver;
use App\Repositories\Implement\Schema\HistoryMigrationType;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

trait HistoryStorage
{

    use ModelHelper;
    private $driver = null;

    private $pre_snapshot = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Change driver when necessary
        $this->driver = new HistoryMySqlSimpleDriver();
    }

    public function getMigrationAttribute($value)
    {
        return json_decode($value);
    }

    public function getDataSnapshotAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * @param $instance_to_init
     * @throws \Exception
     */
    public function startListenChange($instance_to_init = null)
    {
        $data = $instance_to_init ?? $this;
        $this->pre_snapshot = $this->getMeaningAttribute($data);
    }

    /**
     * @param null $snapshot_to_save
     * @param null $current_instance
     * @param null $instance_to_compare
     * @param bool $sync
     * @throws \Exception
     */
    public function commitChange($snapshot_to_save = null, $current_instance = null, $instance_to_compare = null, $sync = true)
    {
        try {
            $current_data = $current_instance ? $this->getMeaningAttribute($current_instance) : $this->getMeaningAttribute($this);

            $prev_data = $instance_to_compare ? $this->getMeaningAttribute($instance_to_compare) : $this->pre_snapshot;

            $blacklist = $this->blacklist ?? [];

            $field_change = [];
            $this->exposeTransformation($field_change, $prev_data, $current_data, $blacklist);
            if (count($field_change) > 0) {
                $field_change = json_encode($field_change, true);
                $this->saveSnapshot($field_change, $snapshot_to_save ?? $this, $sync);
            }
            $prev_data = null;
        } catch (Exception $exception) {

            Log::critical($exception->getMessage());
        }
    }


    public function saveSnapshot($changed_data, $snapshot, $sync)
    {
        $this->saveAdapter([
            'changed_data' => $changed_data,
            'snapshot' => $snapshot
        ], $sync);
    }

    private function saveAdapter($data_cluster, $sync)
    {
        $this->driver->store(get_class($this), $this['id'], $data_cluster, ['sync' => $sync]);
    }
}
