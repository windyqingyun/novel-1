<?php

namespace App\Model\admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;


class Novel extends BaseModel
{

    protected $fillable = ['user_id','name', 'pic', 'author', 'desc', 'status', 'sections'];
    public function hot()
    {
        return $this->hasOne(Hot::class,'novel_id','id');
    }
    //cart关联
    public function cart()
    {
        return $this->belongsToMany(Cart::class,'novel_carts','novel_id','cart_id')
            ->withPivot(['novel_id','cart_id']);
    }

    //首页
    public static function index()
    {
        $data['novels'] = Hot::with('Novel')->orderBy('collectors','desc')->take(10)->get();
        $sql = 'SELECT count(n.name) nums,n.author,count(h.visitors) vs,count(h.collectors) cs 
FROM novels n LEFT JOIN hots h ON n.id = h.novel_id GROUP BY n.author ORDER BY cs desc LIMIT 10';
        $data['authors'] = DB::select($sql);
        return $data;
    }
    //添加
    public function add($input, $file,$user)
    {
        $input = Novel::upFile($input, $file);
        //获得分类
        $input['status'] = $input['sections'] =0;
        $input['user_id'] = $user->id;

        unset($input['_token']);
        //添加进小说表
        $res1 = Novel::create($input);
        $novel_id = $res1->getKey();
        $novel = self::find($novel_id);

        //更新关系表
        $res2 = true;
        $cart = Cart::findMany($input['cart_id']);
        foreach($cart as $v){
            $res2 = self::saveCart($novel,$v);
        }
        if ($res1&&$res2) {
            return true;
        } else {
            return false;
        }
    }
    public function saveCart($novel,$input)
    {
        $novel->cart()->save($input);
        return true;
    }
    public function delCart($novel,$input)
    {
        $novel->cart()->detach($input);
        return true;
    }
    //修改
    public function edit($input)
    {
        $this->upCart($input);
        unset($input['_token'],$input['cart_id']);
        $pic = Novel::where('id', $input['id'])->get(['pic'])->toArray();
        if (isset($input['pic'])) {
            $res = unlink($pic[0]['pic']);
            $input = Novel::upFile($input['pic'], $input);
            unset($input['address']);
            if ($res) {
                return Novel::where('id', $input['id'])->update($input);
            } else {
                return false;
            }
        } else {
            $input['pic'] = $pic[0]['pic'];
            return Novel::where('id', $input['id'])->update($input);
        }
    }
    public function upCart($input)
    {
        //更新分类
        $carts = Cart::findMany($input['cart_id']);
        $novel = Novel::find($input['id']);
        $oldCarts = $novel->cart;
        //增加
        $addCarts = $carts->diff($oldCarts);

        foreach($addCarts as $addCart) {
            self::saveCart($novel,$addCart);
        }

        //删除
        $delCarts = $oldCarts->diff($carts);
        foreach($delCarts as $delCart) {
            self::delCart($novel,$delCart);
        }
    }
    //删除
    public static function del($input)
    {
        $id = $input['id'];
        $novel = Novel::find($id);
        $res1 = $res2 = true;
        if($novel){
            $res1 = $novel->delete();
        }
        //删除图片
        $arr = $novel->toArray();
        if(file_exists('/' . $arr['pic'])) {
            $res2 = unlink($arr['pic']);
        }
        //删除全部章节内容
        $res3 = Section::delAll($id);
        //更新热度表
        $res4 = Hot::del($id);
        if ($res1 && $res2 && $res3 && $res4) {
            return true;
        } else {
            return false;
        }
    }
    //查询列表
    public function lists()
    {

        $data = self::paginate(3);
        return $data;
    }
    //查询详细信息
    public static function info($id)
    {
        $data = self::where('id',$id)->with('cart')->with('hot')
            ->get()
            ->toArray();
        $data = $data[0];
        return $data;
    }
    //上传文件
    public static function upFile($input, $file)
    {
        $path = '/uploads/novelPic/'.date('Y/md');
        //文件上传
        $input = BaseModel::uploadPic($input, $file, $path);
        //添加全部数据
        return $input;
    }
    public static function show($ad)
    {
        $file = trim($ad);
        $content = file_get_contents($file)?:false;
        if(!$content)
        {
            return '404 NOT FOUND';
        }
        return $content;
    }
    //判断能否修改
    public static function change($id)
    {
        if( Novel::find($id)) {
            if(!Gate::allows('change'))
            {return false;}
            $user_id = self::where('id',$id)
                ->get(['user_id'])->toArray();
            $user_id = $user_id[0]['user_id'];
            if(!(Gate::allows('users')||session('admin.id')==$user_id))
            {return false;}
            return true;
        }else {
            return false;
        }
    }

}
