<?php

namespace App\Http\Controllers;

use App\Info;
use Illuminate\Http\Request;
use App\Password;
use Illuminate\Support\Facades\Redis;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InfoController extends Controller
{
    /**
     * 参数保存
     * @param null $bianhao
     * @param null $renshu
     * @param null $name
     * @param null $amount
     * @return string
     */
    public function store($bianhao = null,$renshu = null,$name = null,$amount = null)
    {
        if(!($bianhao && $renshu && $name && $amount))
            return '参数不可为空';
        $current = date('Y-m-d',time());
        $type = substr($bianhao,0,1);
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
            $i->name = $name;
            $i->amount = $amount;
            $i->type = $type;
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
            session()->put('type','');
            session()->put('name','');
            session()->put('startDate',date('Y-m-d',time()));
            session()->put('endDate',date('Y-m-d',time()));
            session()->save();
            return redirect('info');
        }
        return redirect()->back()->withErrors(['msg'=>'密码错误']);
    }
    
    public function info()
    {
        $array = array();
        if(session('type')!='')
            $array['type'] = session('type');
        if(session('name')!='')
            $array['name'] = session('name');
        if(!session()->exists('isLogin'))
        {
            return redirect('login');
        }
        $infos = null;
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        Redis::flushall();
        for(;$startDate < $endDate;)
        {
            $infos = Info::where('date',$startDate)
                            ->where($array)->paginate(10);
            foreach($infos as $i => $info)
            {
                Redis::set($info->bianhao.':'.$info->date,$info->renshu);
            }
            $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
        }

        $infos = Info::where('date','>=',session('startDate'))
                     ->where('date','<=',session('endDate'))
                     ->where($array)->paginate(10);
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        return view('info',['infos'=>$infos,'startDate'=>$startDate,'endDate'=>$endDate]);
    }
    
    public function logout()
    {
        session()->flush();
        return redirect('login');
    }

    public function export()
    {
        try
        {
            $array = array();
            if(session('type')!='')
                $array['type'] = session('type');
            if(session('name')!='')
                $array['name'] = session('name');
            $infos = Info::where('date','>=',session('startDate'))
                         ->where('date','<=',session('endDate'))
                         ->where($array)->get();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', '编号');
            $sheet->setCellValue('B1', '人数');
            $sheet->setCellValue('C1', '群名称');
            $sheet->setCellValue('D1', '群人数');
            $sheet->setCellValue('E1', '日期');

            foreach($infos as $i => $info)
            {
                $sheet->setCellValue('A'.($i+2), $info->bianhao);
                $sheet->setCellValue('B'.($i+2), $info->renshu);
                $sheet->setCellValue('C'.($i+2), $info->name);
                $sheet->setCellValue('D'.($i+2), $info->amount);
                $sheet->setCellValue('E'.($i+2), $info->date);
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

    /**
     * ajax 设置筛选条件
     * @param Request $request
     */
    public function setCondition(Request $request)
    {
        session()->put('type',$request->type);
        session()->put('name',$request->name);
        session()->put('startDate',$request->startDate);
        session()->put('endDate',$request->endDate);
        session()->save();
        
        return response()->json(array(
            'code' => 200
                                ));
    }
}
