<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;

use Illuminate\Http\Request;

class DiskController extends Controller
{
    public function uploadFile(Request $request){
        
        $validation = Validator::make($request->all(),[
         'file'=> 'required|file|max:2048',
         ]);
         $file = $request->file('file');
         $path = $file->store('uploads','s3');

         

        return $path;

    }
    public function test(Request $request){
        return "hi";
    }
}
