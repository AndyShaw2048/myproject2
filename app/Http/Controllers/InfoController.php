<?php

namespace App\Http\Controllers;

use App\Info;
use Illuminate\Http\Request;
use App\Password;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        if(session()->exists('isLogin'))
        {
            return redirect('info');
        }
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
            session()->put('date',$date);
            session()->save();
            $infos = Info::where('date',$date)->paginate(10);
            return view('info',['infos'=>$infos]);
        }
        session()->put('date',date("Y-m-d",time()));
        session()->save();
        $infos = Info::where('date',date('Y-m-d',time()))->paginate(10);
        return view('info',['infos'=>$infos]);
    }
    
    public function logout()
    {
        session()->forget('isLogin');
        session()->save();
        return redirect('login');
    }

    public function export()
    {
        try
        {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', '编号');
            $sheet->setCellValue('B1', '人数');
            $sheet->setCellValue('C1', '日期');
            $infos = Info::where('date',session('date'))->get();
            foreach($infos as $i => $info)
            {
                $sheet->setCellValue('A'.($i+2), $info->bianhao);
                $sheet->setCellValue('B'.($i+2), $info->renshu);
                $sheet->setCellValue('C'.($i+2), $info->date);
            }
            $writer = new Xlsx($spreadsheet);
            $filename = 'export/'.md5(time()).'.xlsx';
            $writer->save($filename);
        }
        catch(\Exception $e)
        {
            return '导出失败';
        }
        return redirect(url($filename));
    }
}
