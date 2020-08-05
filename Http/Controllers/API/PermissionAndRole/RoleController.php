<?php

namespace App\Http\Controllers\API\PermissionAndRole;

use App\Model\Role;
use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use App\Http\Controllers\Controller;
use App\Model\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @group Role
 *
 * APIs for role
 */
class RoleController extends Controller
{

    /**
     * Get List role.
     */

    public function index(Request $request)
    {
        $user = $request->user();
        $data = Role::with('permissions')->orderBy('id','desc')->get();
        return AdapterHelper::sendResponse(true,$data,200,'Success');
    }

    /**
     * Create role.
     * @bodyParam name string required . Example: supper admin
     * @bodyParam list_permission string required json list name permission . Example: ["ticket_end.index","ticket_end.show"]
     */

    public function create(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'list_permission' => 'required'
        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }
        DB::beginTransaction();
        try {
            //code...
            $list_permission_name = json_decode($request->list_permission);
            if (!$list_permission_name) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'list permission fail json');
            }
            $role = Role::create(['name'=>$request->name,'title'=>$request->title]);
            $list_permission = Permission::whereIn('name', $list_permission_name)->get();
            foreach ($list_permission as $permission) {
                $role->givePermissionTo($permission);
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th,"Mobile",$request->getRequestUri());
            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        return AdapterHelper::sendResponse(true,'success',200,'Success');

    }

    /**
     * Update role.
     * @bodyParam name string required . Example: supper admin
     * @bodyParam list_permission string required json list name permission . Example: ["ticket_end.index","ticket_end.show"]
     * 
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'list_permission' => 'required'

        ]);
        if ($validator->fails()) {
            return AdapterHelper::sendResponse(false, 'Validator error', 400, $validator->errors()->first());
        }

        try {
            //code...
            $list_permission_name = json_decode($request->list_permission);
            if (!$list_permission_name) {
                return AdapterHelper::sendResponse(false, 'Validator error', 400, 'list permission fail json');
            }
            $fieldUpdates = $request->only(['name','title']);
            $role = Role::findOrFail($id);
            $role->update($fieldUpdates);
            $role->permissions()->detach();
            $list_permission = Permission::whereIn('name', $list_permission_name)->get();
            foreach ($list_permission as $permission) {
                $role->givePermissionTo($permission);
            }
            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            AdapterHelper::write_log_error($th,"Mobile",$request->getRequestUri());

            return AdapterHelper::sendResponse(false, $th->getMessage(), 500, $th->getMessage());
        }
        
        return AdapterHelper::sendResponse(true,'success',200,'Success');
    }
    /**
     * detail role
     */
    public function show(Request $request, $id)
    {
        $data = Role::with('permissions')->find($id);
        if(!$data){
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }
        return AdapterHelper::sendResponse(true,$data,200,'Success');
    }

    /**
     * delete role.
     */
    public function destroy($id){
        $role = Role::find($id);
        if(!$role){
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Not found');
        }
        $role->permissions()->detach();
        $role->delete();
        return AdapterHelper::sendResponse(true,'success',200,'Success');
    }
}
