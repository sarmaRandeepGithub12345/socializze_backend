<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class GeneralController extends Controller
{
    protected $helperService;
    public function __construct(HelperService $helperService){
       $this->helperService = $helperService;
    }
    public function searchUser(Request $request){
        
        $search = $request['searchText'] ?? "";
        
        $users=[];
        if($search != ""){
            $users = User::where('username','LIKE',"%$search%")->OrWhere('name','LIKE',"%$search%")->get();
            
        }
        return HelperResponse('success','Users found',200,$users);
    }
}
