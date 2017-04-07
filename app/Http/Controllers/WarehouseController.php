<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\Consignment;
use App\Orders;
use Input;
use File;
use Excel;
use Validator;
use DB;
use App\Category;
use App\Item;
use App\Stoks;
use App\Center;
use App\Integration;
use App\Render;
use Illuminate\Pagination\LengthAwarePaginator;//Paginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/*
 *  Helping quotes for phpexcel 
 *  $fileName = $file->getClientOriginalName(); // getClient follod by property
 * $destinationPath = 'uploads';
 * $contents = File::get( $destinationPath.'/'. $fileName);
 *  config(['excel.import.startRow' => 6]);
 * $rows = Excel::selectSheetsByIndex(0)->load(Input::file('file'), function($reader) {
 *   $reader->noHeading();
 *  })->get();
 * 
 * 
 */
class WarehouseController extends Controller
{
    public function __construct()
    {
       // $this->middleware('auth');
        $this->middleware(function($request,$next){
            if(Auth::user() && Auth::user()->role !=3){
                Auth::logout();
                    return redirect()->to('/');
            }else{
                 return $next($request);
            }
        });
    }
    
    public function stock(Request $request){
        $author = Auth::id();
        $cond = ["warehouse"=>$author];
        $cnd = [1 ];
        if (Input::has('search'))
        {
            $cnd = trim(Input::get('search'));
            $data = Stoks::where($cond)->where('category','!=',1)->with("Items")->whereHas('Items', function($q) use ($cnd){
            $q->where('code','like', '%'.$cnd.'%' )->orWhere('item','like', '%'.$cnd.'%');})->paginate(10);
        }else{
           $data = Stoks::where($cond)->where('category','!=',1)->paginate(10);
        }
        
        
        return view("warehouse.stock",["left_title"=>"warehouse",'data'=>  $data,"include"=>"tableAvailble"]);
    }
    public function wks(Request $request){
        $author = Auth::id();
        $cond = ["warehouse"=>$author];
        $cnd = [1 ];
        if (Input::has('search'))
        {
            $cnd = trim(Input::get('search'));
            $data = Stoks::where($cond)->where('category','=',1)->with("Items")->whereHas('Items', function($q) use ($cnd){
            $q->where('code','like', '%'.$cnd.'%' )->orWhere('item','like', '%'.$cnd.'%');})->paginate(10);
        }else{
           $data = Stoks::where($cond)->where('category','=',1)->paginate(10);
        }
        
        
        return view("warehouse.stock",["left_title"=>"warehouse",'data'=>  $data,"include"=>"tableAvailble"]);
    }
    public function wksLevel(Request $request){
        $wks = Category::where('category',"wks")->pluck('id');
       $parent = $wks[0];
       $cat = Category::where('parent',$parent)->get()->toArray();
      foreach($cat as $cats){
          $sCat = Category::where('parent',$cats['id'])->get()->toArray();
          foreach ($sCat as $level){
            $iLevel[] = $cats['category']." wks ".$level["category"];
          }
      }
      //dd($iLevel);
        $author = Auth::id();
        $cond = ["warehouse"=>$author];
        $cnd = [1 ];
        if (Input::has('search'))
        {
            $cnd = trim(Input::get('search'));
            //foreach ($iLevel as $k=>$lv){
                 $data[$cnd] = Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($cnd){
                $q->where('item','like', $cnd.'%');})->sum('count');
            //}
        }else{
            foreach ($iLevel as $lv){
                 $data[$lv] = Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');})->sum('count');
            }
          //->paginate(10);dd($data);
        }
        //$units = new Paginator($data, 1);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $collection = new Collection($data);
        $perPage = 10;
        $currentPageSearchResults = $collection->slice(($currentPage-1) * $perPage, $perPage)->all();
        $units= new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);
        return view("warehouse.stock",["left_title"=>"warehouse",'data'=>  $units,"include"=>"tableLevelStock"]);
    }
    public function stockCenter($cent=3){
       $iLevel =  $this->level_get();
       $author = Auth::id();
      $cond = ["warehouse"=>$author,'target'=>$cent];
      $cnd = [1 ];
      foreach ($iLevel as $lv){
          $data = Render::where($cond)->with("Items")->get()->toArray();
                $data1 = Render::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', '%'.$lv.'%');});
                $qt = $data1->sum('quantity');dd($data);
                $total = $data1->sum("total");
                $tot_amt = $total+$pp[$lv]['amt'];
                $tot_ct = $qt+$pp[$lv]['qt'];
                if($tot_amt !=0 && $tot_ct != 0 ):
                $prc[$lv] = $tot_amt/$tot_ct;
                Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');})->update(['unit_price'=>$prc[$lv]]);
                endif;
      }
       dd($iLevel);
    }
    
    public function consignments(){
        $author = Auth::id();
        $data = Orders::where('warehouse',$author)->orderBy('id', 'desc')->paginate(10);
        return view("warehouse.stock",["left_title"=>"warehouse",'data'=>  $data,"include"=>"tableConsignment","input"=>"consignment"]);
    }
    public function transfer(){
        return "transfer";
    }
    public function render(Request $request,$cent){
        extract(Input::All());
        $author = Auth::id();
        $rules = array(
            'file' => 'required',
            'start' => 'required',
            'sheet' => 'required',
        );
     //   dd($request);
        if($request->isMethod('post')){
            $validator = Validator::make(Input::all(), $rules);
             // process the form
            if ($validator->fails()) 
            {  echo "fai";
                return redirect()->back()->withErrors($validator);
            }
        
        if ($request->hasFile('file')) {
            $data = $this->upload($request);
         if($data == "error"){
            return redirect()->back()->with(["message"=>'Record Already Exists.']);
         }
         if(!empty($data)){
             $this->issueToCenter($data, $center);
             $this->issueToCenterNci($center,$startNci);
             $this->issueToCenterCi($center,$startCi);
         }
        }else{
            redirect()->back()->with(["message"=>'Record Already Exists or No File Selected.']);
        }
        }
        $it = Integration::where('warehouse',$author)->pluck("center");
        $cnt = Center::whereIn('id',$it)->pluck("centerName","id")->toArray();
        $cnt1 = Center::pluck("centerName","id")->toArray();
        $data = Render::distinct()->where('warehouse',$author)->where('target',$cent)->orderBy('updated_at', 'desc')->get(['updated_at','target']);
         $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $collection = new Collection($data);
        $perPage = 10;
        $currentPageSearchResults = $collection->slice(($currentPage-1) * $perPage, $perPage)->all();
        $units= new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);
        //dd($units);
        return view("warehouse.center",["left_title"=>"warehouse","centers"=>$cnt,"center"=>$cnt1,'data'=>  $units,"include"=>"tableRender","input"=>"render"]);
    }
    
    public function create(Request $request){
        $author = Auth::id();
        $rules = array(
            'file' => 'required',
            'freight' => 'required',
            'start' => 'required',
            'sheet' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);
            // process the form
        if ($validator->fails()) 
        {
            return redirect()->back()->withErrors($validator);
        }
        $CatCollection = $this->CatCollection();
        $itemCollection = $this->itemCollection();//dd($itemCollection);
       if ($request->hasFile('file')) {
           extract(Input::All());
           $file = $request->file('file');
           $fileName = $file->getClientOriginalName();
           $destinationPath = 'uploads';
           if(file_exists($destinationPath."/".$fileName)){
                return redirect()->back()->with(["message"=>'Record Already Exists.']);
           }else{
           $file->move($destinationPath,$fileName);
           
            config(['excel.import.startRow' => $start]);
             $data = Excel::selectSheetsByIndex($sheet)->load($destinationPath."/".$fileName, function($reader) {
              // $reader->noHeading();
           })->toArray();//dd($data);
           $x = Orders::Create(["file"=>$fileName]);
           if(!empty($data)){
               foreach ($data as $key => $value) {
                $itemCode = str_slug($value["item_code"]);
                   if(!array_key_exists($itemCode,$itemCollection)){
                       $this->createNewItem($value);
                   }
               }
               $this->loadStacks();
               $pp = $this->defaulPrice();
               //Stoks::where("warehouse",0)->update(["warehouse"=>$author]);
               $itemCollection = $this->itemCollection();
		foreach ($data as $key => $value) {
                    $order = trim($value['order_no']);
                   if($order != ""):
                       $this->order = $order;
                    $insert[] = ['orderNo' => $x->id, 
                        'warehouse'=>$author, 
                        'item' => $itemCollection[str_slug($value["item_code"],'-')],
                        "category" =>$CatCollection[strtolower($value["subject"])],
                        'freight' =>0,
                        "quantity"=>$value["order_quantity"],
                        'ammount_myr'=>$value["amount_myr"],
                        'exchange_rate'=>$value["exchange_rate"],
                        'ammount_inr'=>$value["amount_in_inr"],
                        "created_at"=>date("Y-m-d H:i:s"),
                        ];
                   endif;
		}

		if(!empty($insert)){
                    
                    
                    $sum = array_column($insert, "ammount_inr");
                    $sum = array_sum($sum);
                    $ratio = $freight/$sum;
                    $insert1 =$insert;
                    $insert = $this->freightDivision($insert1,$ratio);
                    if(Consignment::insert($insert)):
                        $y = Orders::where("id",$x->id)->update([
                            "warehouse"=>$author,
                            "orderNo"=>$this->order,
                            "orderDate"=>date("Y-m-d H:i:s"),
                            "freight"=>$freight,
                            "others"=>$other,
                            "cnf"=>$cnf,
                            "custom"=>$custom,
                            "amount"=>$sum,
                            "sum"=>$freight+$sum+$cnf+$custom+$other,
                        ]); 
                        
                    endif;
                    if(!isset($y)){
                        Oders::destroy($x->id);
                        Consignment::where('orderNo', $x->id)->delete();
                        unlink($destinationPath."/".$fileName);
                    }
                    $this->updatePrice($x->id,$pp);
		}

            }
             return redirect()->back()->with(["message"=>'Record Added successfully']);
          }
        }
       
    }
    public function CatCollection(){
         $dd = Category::all()->toArray();
        foreach($dd as $d){
            $ds[strtolower($d["category"])] = $d["id"];
        }
        return $ds;
    } 
    public function itemCollection(){
         $dd = Item::all()->toArray();
         $ds = array();
        foreach($dd as $d){
            $key = str_slug(strtolower($d["code"]),'-');
            $ds[$key] = $d["id"];
        }
        return $ds;
    }
    public function createNewItem(array $array){
        $CatCollection = $this->CatCollection();
        $name = $array["item_name"];
        $od = $array["order_no"];
        $code = $array["item_code"];
        $subject = strtolower($array["subject"]);
        $subject_code = $array["subject_code"];
        if(isset($subject) && isset($name) && isset($od)):
            $data["item"] = $name;
            $data["code"] = $code;
            $data["category"] = $CatCollection[$subject];
        endif;
        if(isset($data))
        Item::Create($data);
        
    } 
    public function loadStacks(){
       $author = Auth::id();
       $data = array();
       $roles = Stoks::where("warehouse",$author)->pluck('specify')->toArray();
       $id = Item::whereNotIn("id",$roles)->get();
       foreach($id as $v){
            $data[] = ["category"=>$v->category,
                        "unit_price"=>0,
                        "count"=>0,
                        "specify"=>$v->id,
                        "warehouse"=>$author,
                ];
       }
       Stoks::insert($data);
    }
    
    public function freightDivision(array $array,$ratio){
        $d = [];
        foreach($array as $v):
            $v["freight"] = $ratio*$v["ammount_inr"];
            $v["total"] = (1+$ratio)*$v["ammount_inr"];
            $d[] = $v;
        endforeach;
        return $d;
    }
    
    public function upload($request){
        extract(Input::All());
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $destinationPath = 'uploads';
        if(file_exists($destinationPath."/".$fileName)){
            $err = "error";
            return $err;
        }else{
            $file->move($destinationPath,$fileName);
             $this->fileName = $destinationPath."/".$fileName;
            config(['excel.import.startRow' => $start]);
            $data = Excel::selectSheetsByIndex($sheet)->load($destinationPath."/".$fileName, function($reader) {
                  // $reader->noHeading();
               // foreach ($reader->toArray() as $row) {
                 //                           $bb[] = $row;
                   //                     }
            //echo '<pre>';print_r($bb );echo '</pre>';  die();
            })->toArray();
         return $data;
        }
    }
    
    public function addCharges(){
        $author = Auth::id();
       $data =  Orders::find(Input::get("order"));
       $data->others += Input::get("other");
       $data->freight += Input::get("freight");
       $data->custom += Input::get("custom");
       $data->cnf += Input::get("cnf");
       $amt = Input::only("other","freight","cnf","custom");
       $oc = array_sum($amt);
       $data->sum += $oc;
       $data->save();
       $sum = $data->sum;
       $ratio = $oc/$sum;
       $arr = Stoks::where("warehouse",$author)->pluck("id")->toArray();
       foreach($arr as $vl){
           $query = Stoks::find($vl);
           $total = $query->unit_price * $query->count;
           $newTotal = (1+$ratio)*$total;
           $newPrice = $newTotal/$query->count;
           $query->unit_price = $newPrice;
           $query->save();
       }
       return redirect()->back()->with(["message"=>'Record Added successfully']);
    }
    public function updatePrice($order, $defalt = []){
        $pp = $defalt;
        //dd($pp);
      $iLevel = $this->level_get();
      $author = Auth::id();
      $cond = ["warehouse"=>$author];
      $cnd = [1 ];
      foreach ($iLevel as $lv){
                $data1 = Consignment::where($cond)->where('orderNo',$order)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');});
                $qt = $data1->sum('quantity');
                $total = $data1->sum("total");
                $tot_amt = $total+$pp[$lv]['amt'];
                $tot_ct = $qt+$pp[$lv]['qt'];
                if($tot_amt !=0 && $tot_ct != 0 ):
                $prc[$lv] = $tot_amt/$tot_ct;
                Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');})->update(['unit_price'=>$prc[$lv]]);
                endif;
      }
      //dd($prc);
    }
    public function level_get(){
         $wks = Category::where('category',"wks")->pluck('id');
       $parent = $wks[0];
       $cat = Category::where('parent',$parent)->get()->toArray();
      foreach($cat as $cats){
          $sCat = Category::where('parent',$cats['id'])->get()->toArray();
          foreach ($sCat as $level){
            $iLevel[] = $cats['category']." wks ".$level["category"];
          }
      }
      return $iLevel;
    }
    
    public function defaulPrice(){
      $iLevel = $this->level_get();
      $author = Auth::id();
      $cond = ["warehouse"=>$author];
      $cnd = [1 ];
      foreach ($iLevel as $lv){
                 $count = Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');})->sum('count');
                 $up = Stoks::where($cond)->with("Items")->whereHas('Items', function($q) use ($lv){
                $q->where('item','like', $lv.'%');})->pluck('unit_price')->first();
               // if($count !=0 && $up != 0 ):
                $data[$lv] = ["amt"=>$count*$up,'qt'=>$count];
               // endif;
      }
      return $data;
    }
    
    public function issueToCenter(array $data, $center){
        $sub = ["MATHS"=>"ME","ENGLISH"=>"EE","ME"=>"ME","EE"=>"EE"];
        $this->time = time();
        foreach($data as $row){
            $index = $row["subject"];
            if(isset($sub[$index])):
             $item_group =  @$sub[$index]." WKS ".$row["level"];
            endif;
            foreach ($row as $key=>$rows){
                if(is_numeric($key)):
                 $item_key = $item_group." ".$this->zeroPadding($key);
                $item_code = Item::where("item","like","%".$item_key."%")->pluck("id")->toArray();
                if(($item_code) && $rows > 0){
                    $item_code = $item_code[0];
                    $item[] = [
                        "item"=>$item_code,
                        "quantity"=>(int)$rows,
                        "target"=>$center,
                        "targetType"=>1,
                        "warehouse"=>Auth::id(),
                        "created_at"=> date("Y-m-d"),
                        "updated_at"=> $this->time,
                        'filename'=>$this->fileName
                    ];
                }
              //  $item[$item_code] = $rows;
                endif;
            }
        }
        Render::insert($item);
        //echo $this->fileName;die;
       //dd($item);
    }
    public function issueToCenterNci($center,$start=11){
        $sheet=1;
        $item=[];
       config(['excel.import.startRow' => $start]);
            $data = Excel::selectSheetsByIndex($sheet)->load($this->fileName, function($reader) {
                  // $reader->noHeading();
            })->toArray();//dd($data);
            foreach($data as $row){
                $item_code = Item::where("code","=",$row['itemcode'])->pluck("id")->toArray();
                if(($item_code) && $row["qty_order"] > 0){
                    $item_code = $item_code[0];
                    $item[] = [
                        "item"=>$item_code,
                        "quantity"=>(int)$row["qty_order"],
                        "target"=>$center,
                        "targetType"=>1,
                        "warehouse"=>Auth::id(),
                        "created_at"=> date("Y-m-d"),
                        "updated_at"=> $this->time,
                        'filename'=>$this->fileName
                    ];
                }
            }//dd($item);
        Render::insert($item);
    }
    public function issueToCenterCi($center,$start=17){
        $sheet=2;
        $item=[];
       config(['excel.import.startRow' => $start]);
            $data = Excel::selectSheetsByIndex($sheet)->load($this->fileName, function($reader) {
                  // $reader->noHeading();
            })->toArray();//dd($data);
            foreach($data as $row){
                $item_code = Item::where("code","=",$row['code'])->pluck("id")->toArray();
                if(($item_code) && $row["qty_ordered"] > 0){
                    $item_code = $item_code[0];
                    $item[] = [
                        "item"=>$item_code,
                        "quantity"=>(int)$row["qty_ordered"],
                        "target"=>$center,
                        "targetType"=>1,
                        "warehouse"=>Auth::id(),
                        "created_at"=> date("Y-m-d"),
                        "updated_at"=> $this->time,
                        'filename'=>$this->fileName
                    ];
                }
            }//dd($item);
         Render::insert($item);
    }
    public function zeroPadding($x){
        $ct =  strlen($x);
        switch ($ct){
            case 1:
                return  "00".$x;
                break;
            case 2:
                return  "0".$x;
                break;
            default :
                return  $x;
                break;
        }
    }
}
