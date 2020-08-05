<?php

namespace App\Http\Controllers\API_PARTNER\Food;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Model\MenuFood\Food;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Traits\AdapterHelper;
use App\Model\MenuFood\CategoryFood;
class FoodController extends Controller
{
    /**
     * index all food
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $data = CategoryFood::with(['food'=>function($q) use ($user){
            $q->where('partner_id',$user->id)->orderBy('name','asc');
        },'food.style_menu'])->orderBy('name', 'asc')->get()->toArray();
        
        $foods = [];
        
        for($i = 0 ; $i < sizeOf($data);$i++){
            if(sizeOf($data[$i]["food"]) >0){
                $data[$i]["food"] = AdapterHelper::unique_array($data[$i]["food"],"id");
                array_push($foods,$data[$i]);
            }
        }
        

        
        $food_no_category = Food::with('style_menu')->whereDoesntHave('category')->where('partner_id',$user->id)->get()->toArray();
        if(sizeOf($food_no_category) > 0){
            $foods_no_category = [
                "name" => "Khác",
                "food"=>$food_no_category
            ];
            array_push($foods,$foods_no_category);
        }
    
        return AdapterHelper::sendResponse(true, $foods, 200, 'Success');
    }

    /**
     * show
     */
    public function show($id)
    {
        $data = Food::with(['category', 'style_menu'])->find($id);
        if (!$data) {
            return AdapterHelper::sendResponse(false, 'Not found', 404, 'Food not found');
        }
        return AdapterHelper::sendResponse(true, $data, 200, 'success');
    }
}
