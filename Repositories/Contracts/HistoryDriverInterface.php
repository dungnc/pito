<?php

namespace App\Repositories\Contracts;


/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/7/20
 * Time: 11:14 AM
 */
interface HistoryDriverInterface {

    /**
     * @param $class
     * @param $source_id
     * @param $data
     * @param null $option
     * @return mixed
     */
    public function store($class, $source_id, $data, $option = null);

    /**
     * @param $class
     * @param $source_id
     * @param null $option
     * @return mixed
     */
    public function getLast($class, $source_id, $option = null);

    /**
     * @param $class
     * @param $source_id
     * @param null $option
     * @return mixed
     */
    public function getAll($class, $source_id, $option = null);

    /**
     * @param $class
     * @param $source_id
     * @param $record_id
     * @param null $option
     * @return mixed
     */
    public function getDetail($class, $source_id, $record_id, $option = null);

    /**
     * @param $className
     * @return mixed
     */
    public function registerType($className);


    /**
     * @param $class
     * @param $source_id
     * @param null $option
     * @return mixed
     */
    public function removeMany($class, $source_id, $option = null);

    /**
     * @param $class
     * @param $source_id
     * @param $record_id
     * @param null $option
     * @return mixed
     */
    public function removeOne($class, $source_id, $record_id, $option = null);
}
