<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/7/20
 * Time: 11:06 AM
 */

namespace App\Repositories\Implement;


use App\Jobs\StoreHistoryJob;
use App\Repositories\Contracts\HistoryDriverInterface;
use App\Repositories\Implement\HistoryModel\HistoryType;
use App\Repositories\Implement\HistoryModel\HistoryWarehouse;
use Illuminate\Support\Collection;
use phpDocumentor\Reflection\Types\Boolean;

class HistoryMySqlSimpleDriver implements HistoryDriverInterface {

    public static function newInstance() {
        return new HistoryMySqlSimpleDriver();
    }

    public function registerType($className) {
        $type = HistoryType::firstOrCreate(['class' => $className]);
        return $type;
    }

    public function getType($className) {

        $type = HistoryType::firstOrCreate(['class' => $className]);
        return $type;
    }

    public static function storeAsync($class, $source_id, $data, $option = null) {
        $instance = self::newInstance();
        $type = $instance->registerType($class);
        $instance->save($type->id, $source_id, $data, $option);
    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param null $option sync or async save
     * @throws \Exception
     */
    public function store($class, $source_id, $data, $option = null) {

        if ($option['sync'] == true) {
            $type = $this->registerType($class);
            $this->save($type->id, $source_id, $data, $option);
        } else {
            StoreHistoryJob::dispatch([
                'class' => $class,
                'source_id' => $source_id,
                'data' => $data,
//                'description' => null,
                'option' => $option
            ]);
        }

    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param null $option Array not use
     * @return Collection[HistoryWarehouse]
     */
    public function getAll($class, $source_id, $option = null) {
        // TODO: Implement getAll() method.

        $type = $this->getType($class);

        if ($option['detail']) {
            $data = HistoryWarehouse::where('type_id', $type->id)
                ->where('source_id', $source_id)
                ->orderBy('created_at', $option['ASC'] ? 'ASC' : 'DESC')
                ->paginate($option['per_page'] ?? 15);
            return $data;
        }
        $data = HistoryWarehouse::where('type_id', $type->id)
            ->where('source_id', $source_id)
            ->select(['id', 'type_id', 'source_id', 'migration', 'description', 'agent', 'created_at', 'updated_at'])
            ->orderBy('created_at', $option['ASC'] ? 'ASC' : 'DESC')
            ->paginate($option['per_page'] ?? 15);
        return $data;
    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param $record_id String Specify record ID
     * @param null $option Array not use
     * @return HistoryWarehouse
     */
    public function getDetail($class, $source_id, $record_id, $option = null) {
        $type = $this->getType($class);
        $data = HistoryWarehouse::where('id', $record_id)->first();
        return $data;
    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param null $option not use
     * @return HistoryWarehouse
     */
    public function getLast($class, $source_id, $option = null) {
        $type = $this->getType($class);
        $data = HistoryWarehouse::where('type_id', $type->id)
            ->where('source_id', $source_id)
            ->orderBy('created_at', 'DESC')
            ->first();
        return $data;
    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param null $option Array option : id_array | from, to
     * @return Boolean
     */
    public function removeMany($class, $source_id, $option = null) {

        $type = $this->getType($class);
        // TODO: Implement remove() method.
        $query = HistoryWarehouse::where('type_id', $type->id)
            ->where('source_id', $source_id);
        if (isset($option['id_array']))
            $query = $query->whereIn('id', $option['id_array']);
        else {
            if (isset($option['from']))
                $query = $query->where('id', '>=', $option['from']);
            if (isset($option['to']))
                $query = $query->where('id', '<=', $option['to']);
        }
        $query = $query->delete();
        return $query;
    }

    /**
     * @param $class String Model Name
     * @param $source_id String Source Target Id
     * @param $record_id String Specify record ID
     * @param null $option Array not use
     * @return Boolean
     */
    public function removeOne($class, $source_id, $record_id, $option = null) {
        // TODO: Implement remove() method.
//        $type = $this->getType($class);

        $data = HistoryWarehouse::where('id', $record_id)->delete();

        return $data;
    }

    private function save($type_id, $source_id, $data, $option = null) {
        if (!isset($data['changed_data'])
            || !isset($type_id)
            || !isset($source_id)
        ) throw new \Exception("Missing require data to store change");

        $record = new HistoryWarehouse();
        $record->type_id = $type_id;
        $record->source_id = $source_id;
        $record->migration = json_encode($data['changed_data']);
        $record->data_snapshot = json_encode($data['snapshot'] ?? null);
        if (isset($option['description'])) $record = $option['description'];
        if (isset($option['agent_sign'])) $record = $option['agent_sign'];

        $record->save();
    }

}

