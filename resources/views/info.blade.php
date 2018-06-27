<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="_token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="{{url('layui/css/layui.css')}}">
        <link rel="stylesheet" href="{{url('message/message.css')}}">
        <link rel="stylesheet" href="http://apps.bdimg.com/libs/bootstrap/3.3.4/css/bootstrap.min.css">
        <link rel="stylesheet" href="{{url('assets/css/amazeui.min.css')}}">
        <link rel="stylesheet" href="{{url('assets/css/app.css')}}">
        <script src="{{url('layui/layui.js')}}"></script>
        <title>查看信息</title>
        <style>
            .export {
                margin-top: 15px;
                margin-bottom: 100px;
                float: left;
            }
            .page {
                float: right;
            }
        </style>
    </head>
    <body>
    <div class="am-container">
        <div style="margin-top: 150px">
            <div class="am-alert am-alert-danger" id="my-alert" style="display: none">
                <p>开始日期应小于结束日期！</p>
            </div>
            <div class="layui-form" action="" style="float:left;margin-right: 20px">
                <div class="layui-inline">
                    <label class="layui-form-label">编&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;号</label>
                    <div class="layui-input-inline" style="margin-left: 18px">
                        <input type="text" id="type" placeholder="编号首字母 或 编号全名，中间使用,隔开" class="layui-input"
                               style="width: 350px;" value="{{session('type')=='' ? '':session('type')}}" title="例：A,b,C 或 A152,b563,c58">
                    </div>
                </div>
                <br>
                <div class="layui-inline">
                    <label class="layui-form-label">群&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;号</label>
                    <div class="layui-input-inline" style="margin-left: 18px">
                        <input type="text" id="name" placeholder="群编号(纯数字)" class="layui-input" style="width: 350px;" value="{{session('number')=='' ? '':session('number')}}">
                    </div>
                </div>
            </div>
            <div class="am-g" style="">
                <div>
                    <button type="button" class="am-btn am-btn-default am-margin-right" id="my-start">开始日期</button>
                    <span id="my-startDate">{{session('startDate')}}</span>
                </div>
                <div style="margin-top: 6px">
                    <button type="button" class="am-btn am-btn-default am-margin-right" id="my-end">结束日期</button>
                    <span id="my-endDate">{{session('endDate')}}</span>
                </div>
            </div>
        </div>
        <button class="am-btn am-btn-primary" style="margin-left: 10px" onclick="sendPost()">提交</button>
        <a href="/logout" class="am-btn am-btn-primary" style="margin-left: 10px">退出</a>
        <button class="am-btn am-btn-default" style="margin-left: 10px" onclick="getAmount()">计算当前总人数</button>

        <div class="am-scrollable-horizontal" style="margin-top: 20px">
            <table class="am-table am-table-bordered am-table-striped am-table-hover am-text-nowrap">
                <thead>
                <tr>
                    <th>编号</th>
                    <th>群名称</th>
                    @while($startDate != $endDate)
                        <th>人数({{$startDate}})</th>
                        {{--<th>时间({{$startDate}})</th>--}}
                    <?php $startDate = date("Y-m-d",strtotime($startDate." +1 day"));?>
                    @endwhile
                </tr>
                </thead>
                <tbody>
                @if($infos->isEmpty())
                    <td colspan="13">暂无数据</td>
                @else
                @foreach($infos as $info)
                    <?php
                    $startDate = session('startDate');
                    $endDate = date("Y-m-d",strtotime(session('endDate').'+1 day'));
                    ?>
                    <tr>
                        <td>{{$info->bianhao}}</td>
                        <td>{{$info->name}}</td>
                        @for(;$startDate < $endDate;)
                            <td>{{\Illuminate\Support\Facades\Redis::get($info->bianhao.':'.$startDate)}}</td>
                            <?php $startDate = date("Y-m-d",strtotime($startDate." +1 day"));?>
                        @endfor
                    </tr>
                @endforeach
                @endif
                </tbody>
            </table>
        </div>
        <div class="export">
            <form action="/info/export" method="post">
                @csrf
            <button type="submit" class="am-btn am-btn-primary">导出当前日期记录</button>
            </form>
        </div>
        <div class="page">
            {{$infos->links()}}
        </div>
    </div>

    <div class="am-modal am-modal-loading am-modal-no-btn" tabindex="-1" id="my-modal-loading">
        <div class="am-modal-dialog">
            <div class="am-modal-hd">正在载入...</div>
            <div class="am-modal-bd">
                <span class="am-icon-spinner am-icon-spin"></span>
            </div>
        </div>
    </div>

    <div class="am-modal am-modal-alert" tabindex="-1" id="my-alert-amount">
        <div class="am-modal-dialog">
            <div class="am-modal-bd" id="amount-text">
                当前条件下，编号人数：<span id="b_number"></span>，群组人数：<span id="g_number"></span>
                <br>总人数：<span id="total_number"></span>
            </div>
            <div class="am-modal-footer">
                <span class="am-modal-btn">确定</span>
            </div>
        </div>
    </div>
    </body>

    <script src="{{url('js/jquery-1.10.2.min.js')}}"></script>
    <script src="{{url('assets/js/amazeui.min.js')}}"></script>
    <script src="{{url('message/message.js')}}"></script>
    <script>
        /*
         * 日期选择
         */
        $(function() {
            var myDate = new Date();
            var date = myDate.getFullYear()+'-'+myDate.getMonth()+'-'+myDate.getDate();

            var DateOne = $('#my-startDate').text();
            var OneMonth = DateOne.substring(5,DateOne.lastIndexOf ('-'));
            var OneDay = DateOne.substring(DateOne.length,DateOne.lastIndexOf ('-')+1);
            var OneYear = DateOne.substring(0,DateOne.indexOf ('-'));
            var startDate = new Date(OneYear,OneMonth-1,OneDay);


            var DateOne = $('#my-endDate').text();
            var OneMonth = DateOne.substring(5,DateOne.lastIndexOf ('-'));
            var OneDay = DateOne.substring(DateOne.length,DateOne.lastIndexOf ('-')+1);
            var OneYear = DateOne.substring(0,DateOne.indexOf ('-'));
            var endDate = new Date(OneYear,OneMonth-1,OneDay);

            var $alert = $('#my-alert');
            $('#my-start').datepicker().
            on('changeDate.datepicker.amui', function(event) {
                console.log(event.date);
                console.log(endDate);
                if (event.date.valueOf() > endDate.valueOf()) {
                    $alert.find('p').text('开始日期应小于结束日期！').end().show();
                } else {
                    $alert.hide();
                    startDate = new Date(event.date);
                    $('#my-startDate').text($('#my-start').data('date'));
                }
                $(this).datepicker('close');
            });

            $('#my-end').datepicker().
            on('changeDate.datepicker.amui', function(event) {
                if (event.date.valueOf() < startDate.valueOf()) {
                    $alert.find('p').text('结束日期应大于开始日期！').end().show();
                } else {
                    $alert.hide();
                    endDate = new Date(event.date);
                    $('#my-endDate').text($('#my-end').data('date'));
                }
                $(this).datepicker('close');
            });
        });

        /**
         * 设置筛选条件
         */
        function sendPost()
        {
            var type = $('#type').val();
            var name = $('#name').val();
            var startDate = $('#my-startDate').text();
            var endDate = $('#my-endDate').text();
            var time = daysBetween(endDate,startDate)+1;
            if(time>10)
            {
                var $alert = $('#my-alert');
                $alert.find('p').text('日期相差不得大于10天').end().show();
                return;
            }
            $.ajax({
                type: 'POST',
                url: '/info/set',
                data: {type:type,number:name,startDate:startDate,endDate:endDate},
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                },
                success: function(data){
                    console.log(data);
                    if (data.code == 200){
                        window.setTimeout("window.location='/info'",0);
                    }
                    else{
                        $.message({
                            message: '提交失败',
                            type: 'error'
                        });
                    }
                },
                error: function(xhr, type){
                    alert('Ajax error!')
                }
            });
        }

        /**
         * 计算时间差
         * @param DateOne
         * @param DateTwo
         * @returns {number}
         */
        function daysBetween(DateOne,DateTwo)
        {
            var OneMonth = DateOne.substring(5,DateOne.lastIndexOf ('-'));
            var OneDay = DateOne.substring(DateOne.length,DateOne.lastIndexOf ('-')+1);
            var OneYear = DateOne.substring(0,DateOne.indexOf ('-'));

            var TwoMonth = DateTwo.substring(5,DateTwo.lastIndexOf ('-'));
            var TwoDay = DateTwo.substring(DateTwo.length,DateTwo.lastIndexOf ('-')+1);
            var TwoYear = DateTwo.substring(0,DateTwo.indexOf ('-'));

            var cha=((Date.parse(OneMonth+'/'+OneDay+'/'+OneYear)- Date.parse(TwoMonth+'/'+TwoDay+'/'+TwoYear))/86400000);
            return Math.abs(cha);
        }

        function getAmount()
        {
            $('#my-modal-loading').modal();
            $.ajax({
                type: 'POST',
                url: '/info/amount',
                data: {},
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                },
                success: function(data){
                    if (data.code == 200){
                        window.setTimeout("$('#my-modal-loading').modal('close')",1000);
                        $('#b_number').text(data.b_number);
                        $('#g_number').text(data.g_number);
                        $('#total_number').text(data.g_number+data.b_number);
                        window.setTimeout("$('#my-alert-amount').modal()",1000);
                    }
                    else{
                        $.message({
                            message: '提交失败',
                            type: 'error'
                        });
                    }
                },
                error: function(xhr, type){
                    window.setTimeout("$('#my-modal-loading').modal('close')",1000);
                    alert('Ajax error!')
                }
            });
        }
    </script>
</html>