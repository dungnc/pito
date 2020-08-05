<?php
namespace App\Traits;
class RelationshipUpdateObserver {

    public function updating($model) {
        $data = $model->getAttributes();

        $model->relationship->fill($data['relationship']);

        $model->push();
    }

}
