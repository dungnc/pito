<?php

namespace App\Repositories\Contracts\User;

interface AdminInterface
{
    /**
     * manage User
     */

    public function get_all($type_role, $columns = ['*'], $paginate = false);

    public function search(array $search, $columns = ['*'], $paginate = false);

    public function create_account_for_customer($data);

    public function create_account_for_partner($data);

    public function total_users($type_role);
}
