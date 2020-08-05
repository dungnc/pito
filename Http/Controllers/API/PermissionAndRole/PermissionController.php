<?php

namespace App\Http\Controllers\API\PermissionAndRole;

use App\Model\Role;
use App\Model\User;
use App\Model\Permission;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;


/**
 * @group Permission
 *
 * APIs for Permission
 */
class PermissionController extends Controller
{

    /**
     * Get List all permission of user.
     *
     */

    public function get_list_all(Request $request)
    {
        $user = $request->user();
        $permissions = Permission::orderBy('id', 'desc')->get();
        // $roles = Role::all();
        $data = [];
        foreach ($permissions as $key => $value) {
            $data[$key] = $value->toArray();
            $data[$key]['role'] = $value->getRoleNames();
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Get List permission of user.
     *
     */

    public function index(Request $request)
    {
        $user = $request->user();
        $data = $user->getAllPermissions();
        return AdapterHelper::sendResponse(true, $data, 200, 'Success');
    }

    /**
     * Create Permission .
     * @bodyParam name string required The name of permission. Example: order-create
     * @bodyParam list_role array required. Example: ["PITO_ADMIN","PARTNER","CUSTOMER","SUPER_ADMIN"]
     */

    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'list_role' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $list_role_name = json_decode($request->list_role);
            if (!$list_role_name) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'list role fail json');
            }
            $permission = Permission::create(['name' => $request->name]);

            $list_role = Role::whereIn('name', $list_role_name)->get();
            foreach ($list_role as $role) {
                $role->givePermissionTo($permission);
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th,"Mobile",$request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Update Permission .
     * @bodyParam name string required The name of permission. Example: order-create
     * @bodyParam list_role array required. Example: ["PITO_ADMIN","PARTNER","CUSTOMER","SUPER_ADMIN"]
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'list_role' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        DB::beginTransaction();
        try {
            //code...
            $list_role_name = json_decode($request->list_role);
            $permission = Permission::find($id);
            if (!$permission) {
                return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
            }
            $permission->update(['name' => $request->name]);
            $permission->roles()->detach();
            $list_role = Role::whereIn('name', $list_role_name)->get();
            foreach ($list_role as $role) {
                $role->givePermissionTo($permission);
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th,"Mobile",$request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }

        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    /**
     * Delete Permission .
     *
     */
    public function destroy($id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }
        $permission->roles()->detach();
        $permission->delete();
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }

    public function reloadPermission(Request $request)
    {
        $routeCollection = Route::getRoutes()->get();
        DB::beginTransaction();
        try {
            //code...
            foreach ($routeCollection as $value) {
                if (strlen(strstr($value->action['prefix'], 'api')) > 0) {
                    $check = false;
                    foreach ($value->action['middleware'] as $middle_ware) {
                        if (strlen(strstr($middle_ware, 'role_or_permission'))) {
                            $check = true;
                        }
                    }
                    if ($check) {
                        $name_permission = $value->getAction()['as'];
                        $check_exist = Permission::where('name', $name_permission)->exists();
                        if (!$check_exist) {
                            Permission::create(['name' => $name_permission, 'title' => $name_permission]);
                            if (!Role::findByName('SUPER_ADMIN')->hasPermissionTo($name_permission))
                                Role::findByName('SUPER_ADMIN')->givePermissionTo($name_permission);
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th,"Mobile",$request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true, 'success', 200, 'Success');
    }
}
