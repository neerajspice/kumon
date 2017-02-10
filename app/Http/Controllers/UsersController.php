<?php

namespace App\Http\Controllers;
//use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Auth;
use App\User;
use Input;
use App\Warehouse;
use App\Center;
use App\Integration;
class UsersController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
        $this->middleware(function($request,$next){
            if(Auth::user() && Auth::user()->role !=1){
                Auth::logout();
                    return redirect()->to('/home');
            }else{
                 return $next($request);
            }
        });
       // print_r(Auth::user());
        //
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }
    public function show()
    {
       $user = User::where("role","!=","1")->get();         
        return view('accounts')->with(["center"=>$user,"left_title"=>"Users"]);
    }
    public function resetPassword(Request $request)
    {
       User::where('id', $request->input('id'))->update(['password'=>bcrypt($request->input('password'))]);
       return "success";
    }
    public function integration(Request $request)
    {
        $store= "";
        $whName = "Warehouse";
        if(Input::has('store')){
            $store = Input::get("store");
            $whName = Warehouse::where("id",$store)->pluck("centerName");
        }
       $wh = Warehouse::all();
      $ct = Integration::where("warehouse",$store)->with("Center")->get();
      $ex = Integration::pluck("center");
       $centers = Center::whereNotIn('id',$ex)->get();
       //dd($ct->toArray());
       return view('integration')->with(["warehouse"=>$wh,
                                    "center"=> $ct->toArray(),
                                    "left_title"=>"Integration",
                                     "whName"=>$whName,
                                     "store"=>$store,
                                     "cts"=>$centers,
                                ]);
    }
    
    public function create(Request $request){
        if(Input::has("warehouse")){
            Integration::Create(Input::all());
            $msg = "Added Successfully";
        }
        if(Input::has("id")){
            Integration::destroy(Input::get("id"));
            $msg = "Deleted Successfully";
        }
        return back()->with(["message"=>$msg]);
    }
}
