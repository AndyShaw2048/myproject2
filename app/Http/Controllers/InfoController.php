<?php

namespace App\Http\Controllers;

use App\Info;
use Illuminate\Http\Request;
use App\Password;

class InfoController extends Controller
{
    public function store($bianhao = null,$renshu = null)
    {
        if(!($bianhao && $renshu))
            return '参数不可为空';
        $current = date('Y-m-d',time());

        $info = Info::where('bianhao',$bianhao)
                    ->where('date',$current)->get();
        if(!$info->isEmpty())
        {
            try
            {
                Info::where('bianhao',$bianhao)
                    ->where('date',$current)
                    ->update([
                                 'renshu' => $renshu
                             ]);
            } catch(\Exception $e)
            {
                return 'fail';
            }
            return 'success';
        }
        try
        {
            $i  = new Info();
            $i->bianhao = $bianhao;
            $i->renshu = $renshu;
            $i->date = $current;
            $i->save();
        }catch(\Exception $e)
        {
            return 'fail';
        }
        return 'success';

    }

    public function index()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $pwd = Password::where('password',$request->password)->get();
        if(!$pwd->isEmpty())
        {
            session()->put('isLogin','1');
            session()->save();
            return redirect('info');
        }
        return redirect()->back()->withErrors(['msg'=>'密码错误']);
    }
    
    public function info($date = null)
    {
        if(!session()->exists('isLogin'))
        {
            return redirect('login');
        }
        if($date)
        {
            $infos = Info::where('date',$date)->get();
            return view('info',['infos'=>$infos]);
        }
        $infos = Info::where('date',date('Y-m-d',time()))->get();
        return view('info',['infos'=>$infos]);
    }
    
    public function logout()
    {
        session()->forget('isLogin');
        session()->save();
        return redirect('login');
    }
}
