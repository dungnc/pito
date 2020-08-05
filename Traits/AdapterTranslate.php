<?php

namespace App\Traits;

use app;

class AdapterTranslate
{

    private $model;
    private $data_handle;
    public function __construct($model, $data)
    {
        $this->model = app($model);
        $this->data_handle = $data;
    }

    public function translation($locale = 'vi', $option = null)
    {
        $translation = $this->data_handle->translatable;
        $results = $this->data_handle->toArray();
        foreach ($results as $key => $value) {
            if (\in_array($key, $translation)) {
                $results[$key] = $value[$locale];
            }
        }
        return $results;
    }
    // public function tran
}
