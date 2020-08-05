<?php

/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/13/20
 * Time: 5:40 PM
 */

namespace App\Traits;


use App\Repositories\Implement\Schema\HistoryMigrationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ModelHelper
{
    function getMeaningAttribute($instance)
    {
        if ($instance instanceof Model) {
            $attributes = $instance->getAttributes();
            $relations = $instance->getRelations();
            foreach ($relations as $key => $relation) {
                if ($relation instanceof Model) {
                    $relations[$key] = $this->getMeaningAttribute($relation);
                } else if ($relation instanceof Collection) {
                    $collection_data = [];
                    $list_items = $relation->all();
                    foreach ($list_items as $k => $child) {
                        $collection_data[$k] = $this->getMeaningAttribute($child);
                    }
                    $relations[$key] = $collection_data;
                }
            }
            return array_merge($attributes, $relations);
        } else throw new \Exception("Only support Illuminate\Database\Eloquent\Model instance");
    }

    private function exposeTransformation(&$field_change, $prev_data, $current_data, $blacklist, $parent_key = null)
    {
        foreach ($current_data as $key => $value) {
            if (is_array($value)) {
                if (!in_array($key, $blacklist)) {
                    $next_key = $parent_key ? $parent_key . '.' . $key : $key;
                    if (isset($prev_data[$key])) {
                        $this->exposeTransformation($field_change, $prev_data[$key], $value, $blacklist, $next_key);
                    // xcz
                    }
                }
            } else if (isset($prev_data[$key]) && $value != $prev_data[$key] && !in_array($key, $blacklist)) {
                $key_name = $parent_key ? $parent_key . '.' . $key : $key;
                $field_change[$key_name] = new HistoryMigrationType($key_name, $prev_data[$key], $value);
            }
        }
    }
}
