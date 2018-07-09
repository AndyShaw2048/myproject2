<?php

namespace App\Http\Controllers;

use App\Info;
use Illuminate\Http\Request;
use App\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\View;
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
        preg_match("/\d+/",$name,$number);
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
                                 'renshu' => $renshu,
                                 'name' => $name,
                                 'amount' => $amount
                             ]);
            } catch(\Exception $e)
            {
                return 'fail-1';
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
            $i->number = isset($number[0]) ? $number[0] : '0' ;
            $i->type = $type;
            $i->date = $current;
            $i->save();
        }catch(\Exception $e)
        {
            return 'fail-2'.$e->getMessage();
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
            session()->put('changeType',0);
            session()->save();
            return redirect('info');
        }
        return redirect()->back()->withErrors(['msg'=>'密码错误']);
    }
    
    public function info()
    {
        if(!session()->exists('isLogin'))
        {
            return redirect('login');
        }

        //构造批量查询sql语句
        $sql = $this->getSql();

        $infos = null;
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        Redis::flushall();
        for(;$startDate < $endDate;)
        {
            $infos = DB::table('info')->whereRaw($sql)->where('date',$startDate)->groupBy('bianhao')->get();
            foreach($infos as $i => $info)
            {
                Redis::set($info->bianhao.':'.$info->date,$info->renshu);
                Redis::set($info->bianhao.':'.$info->date.':amount',$info->amount);
            }
            $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
        }
        $infos = DB::table('info')->whereRaw($sql)->groupBy('bianhao')->paginate(10);
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        return view('info',['infos'=>$infos,'startDate'=>$startDate,'endDate'=>$endDate]);
    }
    
    public function logout()
    {
        session()->flush();
        return redirect('login');
    }

    /**
     * 详情导出
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     */
    public function export()
    {
        try
        {
            $sql = $this->getSql();
            $infos = $infos = DB::table('info')->whereRaw($sql)->orderBy('type')->get();
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
                $sheet->setCellValue('C'.($i+2), $info->number);
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
        session()->put('number',$request->number);
        session()->put('startDate',$request->startDate);
        session()->put('endDate',$request->endDate);
        session()->save();
        
        return response()->json(array(
            'code' => 200
                                ));
    }


    /**
     * 计算当前条件下的总人数
     * @var int
     */
    protected $b_number = 0;
    protected $g_number = 0;
    public function getAmount()
    {
        $this->amount = 0;
        $sql = $this->getSql();

        DB::table('info')->whereRaw($sql)->orderBy('type')->chunk(500,function($infos){
                foreach($infos as $info)
                {
                    $this->b_number = $this->b_number + $info->renshu;
                    $this->g_number = $this->g_number + $info->amount;
                }
            });
        return response()->json(array(
                                    'code' => 200,
                                    'b_number' => $this->b_number,
                                    'g_number' => $this->g_number
                                ));
    }

    /**
     * 构建sql语句
     */
    public function getSql()
    {
        $sql = null;
        if(session('type')!='')
        {
            $array = explode(',',session('type'));
            foreach($array as $i => $item)
            {
                if(strlen($item)==1)
                {
                    if($i == 0)
                        $sql = '( type = \''.$item.'\' ';
                    else
                        $sql = $sql.' or type = \''.$item.'\' ';
                }
                else
                {
                    if($i == 0)
                        $sql = '( bianhao = \''.$item.'\' ';
                    else
                        $sql = $sql.' or bianhao = \''.$item.'\' ';
                }

            }
            $sql = $sql.')';
        }
        if(session('number')!='')
        {
            $array = explode(',',session('number'));
            foreach($array as $i => $item)
            {
                if(is_null($sql))
                    $sql = '( number = \''.$item.'\' ';
                elseif($i == 0)
                    $sql = $sql.' and ( number = \''.$item.'\' ';
                else
                    $sql = $sql.' or number = \''.$item.'\' ';
            }
            $sql = $sql.' )';
        }
        if(is_null($sql))
            $sql = ' (date >= \''.session('startDate').'\' and date <= \''.session('endDate').'\')';
        else
            $sql = $sql.' and (date >= \''.session('startDate').'\' and date <= \''.session('endDate').'\')';

        return $sql;
    }

    /**
     * 获取总人数页面
     *
     */
    protected $amount = 0;
    protected $count = 0;
    public function getNumber()
    {
        if(!session()->exists('isLogin'))
        {
            return redirect('login');
        }

        $types[] = explode(",",session('type'));
        $types[] = explode(",",session('number'));
        session()->put('array',$types);
        session()->save();

        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        Redis::flushall();
        for(;$startDate < $endDate;)
        {
            foreach($types as $i => $type)
            {
                if($i == 0)
                {
                    foreach($type as $i => $item)
                    {
                        if(strlen($item) == 1)
                        {
                            Info::where('type',$item)->where('date',$startDate)
                                 ->chunk(500,function($infos){
                                     foreach($infos as $info)
                                     {
                                         $this->count++;
                                         $this->amount = $this->amount + $info->renshu;
                                     }
                                 });
                            Redis::set($item.':'.$startDate,$this->amount);
                            Redis::set($item.':'.$startDate.':count',$this->count);
                            $this->amount = 0;
                            $this->count = 0;
                        }
                        else
                        {
                            Info::where('bianhao',$item)->where('date',$startDate)
                                ->chunk(500,function($infos){
                                    foreach($infos as $info)
                                    {
                                        $this->amount = $this->amount + $info->renshu;
                                    }
                                });
                            Redis::set($item.':'.$startDate,$this->amount);
                            $this->amount = 0;
                        }
                    }
                }
                else
                {
                    if(isset($type[0]))
                    {
                        foreach($type as $i => $item)
                        {
                            Info::where('number',$item)->where('date',$startDate)
                                ->chunk(500,function($infos){
                                    foreach($infos as $info)
                                    {
                                        $this->count++;
                                        $this->amount = $this->amount + $info->amount;
                                    }
                                });
                            Redis::set($item.':'.$startDate,$this->amount);
                            Redis::set($item.':'.$startDate.':count',$this->count);
                            $this->amount = 0;
                            $this->count = 0;
                        }
                    }
                }
            }
            $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
        }

        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        return view('amount',['types'=>$types,'startDate'=>$startDate,'endDate'=>$endDate]);
    }

    /**
     * 总人数导出
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     */
    public function amountExport()
    {
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        $types = session('array');
        $ascii = '66';
        try
        {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', '选项');
            for(;$startDate < $endDate;)
            {
                $sheet->setCellValue(chr($ascii).'1', $startDate);
                $ascii++;
                $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
            }

            $startDate = session('startDate');
            $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
            $ascii = '66';
            $i = 0;
            foreach($types as $type)
            {
                if($type[0] != '')
                {
                    foreach($type as  $item)
                    {
                        $sheet->setCellValue('A'.($i+2),$item);
                        while($startDate < $endDate)
                        {
                            $sheet->setCellValue(chr($ascii).($i+2),Redis::get($item.':'.$startDate));
                            $ascii++;
                            $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
                        }
                        $ascii = '66';
                        $i++;
                        $startDate = session('startDate');
                        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
                    }
                }
            }
            $writer = new Xlsx($spreadsheet);
            $filename = 'export/'.md5(time()).'.xlsx';
            $writer->save($filename);
        }
        catch(\Exception $e)
        {
            return '导出失败'.$e->getMessage();
        }
        return redirect(url($filename));
    }

    /**
     * 群人数导出
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     */
    public function exportGroup()
    {
        //将该日期中的数据放入小表中
        $startDate = session('startDate');
        $endDate = session('endDate');
        DB::statement("TRUNCATE table_cache");
        $sql = "insert into table_cache(id,name,amount,number,date) select id,name,amount,number,date from info where date>=\"$startDate\" and date<=\"$endDate\" and number is not null";
        DB::statement($sql);

        //选出不重复的number值
        $numbers= DB::table('table_cache')->select('number')->distinct()
                    ->where('date','>=',session('startDate'))
                    ->where('date','<=',session('endDate'))->orderBy('number')->get();
        $startDate = session('startDate');
        $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
        $ascii = '66';
        try
        {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', '群名称');
            for(;$startDate < $endDate;)
            {
                $sheet->setCellValue(chr($ascii).'1', $startDate);
                $ascii++;
                $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
            }

            $startDate = session('startDate');
            $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
            $ascii = '66';
            foreach($numbers as $i => $number)
            {
                while($startDate < $endDate)
                {
                    /**
                     *该处使用了存储过程
                    DROP PROCEDURE IF EXISTS `proc_getamount`;
                    DELIMITER ;;
                    CREATE DEFINER=`root`@`localhost` PROCEDURE `proc_getamount`(IN a int, IN b VARCHAR(20), OUT result int)
                    BEGIN
                    select sum(amount) into result from table_cache where number=a and date=b;
                    END
                    ;;
                    DELIMITER ;
                     *
                     */
                    DB::statement("call proc_getamount($number->number,\"$startDate\",@amount)");
                    $res = DB::selectOne('select @amount as amount');
                    $sheet->setCellValue('A'.($i+2), '达人福利vip'.$number->number.'群');
                    $sheet->setCellValue(chr($ascii).($i+2), $res->amount);
                    $startDate = date("Y-m-d",strtotime($startDate." +1 day"));
                    $ascii++;
                }
                $ascii = '66';
                $startDate = session('startDate');
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'export/'.md5(time()).'.xlsx';
            $writer->save($filename);
        }
        catch(\Exception $e)
        {
            return '导出失败:'.$e->getMessage();
        }
//        return redirect(url($filename));
        return response()->json(array([
            'code' => '200',
            'file' => url($filename)
                                      ]));
    }

}
