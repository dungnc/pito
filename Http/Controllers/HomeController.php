<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Model\Setting\PatternMailTool;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function test_hack(Request $request)
    {
        return view('test_hack');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    // public function test_hack(Request $request)
    // {
    //     return view('test_hack');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        if ($request->file) {
            if ($request->hasFile('file')) {
                // dd($request->file->getClientOriginalExtension());

            }
        }
        return view('home');
    }

    public function log_error(Request $request)
    {

        $chanel = $request->channel ? $request->channel : 'state';
        $dir = '../storage/logs/';

        $dir .= $chanel;
        $data = [];
        if (file_exists($dir)) {

            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $key => $file) {
                $data[] = $dir . "/" . $file;
            }
        }

        if ($request->dir) {
            $content = File::get($request->dir);
            if ($request->delete) {
                File::delete($request->dir);
            }
            dd($content);
        }
        return view('log-error', compact('data'));
    }

    public function index_mail_tool(Request $request)
    {
        $data = PatternMailTool::get()->toArray();
        foreach ($data as $key => $value) {
            $data[$key]['json_regex'] = $this->json_regex_mail_to_text($value['json_regex']);
        }
        return view('tool.index', compact('data'));
    }

    public function add_mail_tool(Request $request)
    {
        $new = new PatternMailTool();
        $new->name = $request->name;
        $new->json_regex = json_encode($this->text_regex_mail_to_json($request->json_regex));
        $new->content = $request->content;
        $new->save();
        return redirect(route('mail_tool.index'));
    }

    public function show_create(Request $request)
    {

        return view('tool.add-edit');
    }

    private function json_regex_mail_to_text($param)
    {
        $param = json_decode($param);
        $res = "";
        foreach ($param as $key => $value) {
            foreach ($value as $key1 => $value1) {
                $res .= $key1 . ":" . $value1 . ",";
            }
        }
        return trim($res, ',');
    }
    private function text_regex_mail_to_json($param)
    {
        $list = explode(",", trim($param, ","));
        $res = [];
        foreach ($list as $key => $value) {
            $tmp = explode(":", $value);
            $res[] = [
                $tmp[0] => $tmp[1]
            ];
        }
        return $res;
    }
    public function edit_mail_tool(Request $request, $id)
    {
        $new = PatternMailTool::find($id);
        $new->name = $request->name;
        $new->json_regex = json_encode($this->text_regex_mail_to_json($request->json_regex));
        $new->content = $request->content;
        $new->save();
        return redirect(route('mail_tool.index'));
    }

    public function upload_image_ckeditor(Request $request, $id)
    { }
}
