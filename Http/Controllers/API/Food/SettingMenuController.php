<?php

namespace App\Http\Controllers\API\Food;

use Illuminate\Http\Request;
use App\Traits\AdapterHelper;
use App\Model\TypeParty\TypeParty;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\MenuFood\CategoryFood;
use App\Model\Setting\Menu\SettingMenu;
use App\Model\TypeParty\PartnerHasParty;
use Illuminate\Support\Facades\Validator;

/**
 * @group Order Field Customize
 *
 * APIs for Order Field Customize
 */
class SettingMenuController extends Controller
{

    /**
     * Get List and search menu.
     * @bodyParam name string search tên menu. Example: Buffect nhanh
     */

    public function index(Request $request)
    {
        $query = SettingMenu::with('order_field_customize')->select('*');
        if ($request->name) {
            $query = $query->where('name', 'LIKE', '%' . $request->name . '%');
        }
        $data = $query->orderBy('id', 'asc')->paginate($request->per_page ? $request->per_page : 15);
        return AdapterHelper::sendResponsePaginating(true, $data, 200, 'Success');
    }
}
