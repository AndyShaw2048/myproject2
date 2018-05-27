<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="{{url('layui/css/layui.css')}}">
        <link rel="stylesheet" href="{{url('message/message.css')}}">
        <script src="{{url('layui/layui.js')}}"></script>

        <title>查看信息</title>

    </head>
    <body>
    <div class="layui-container" style="margin-top: 200px">
        <div class="layui-inline"> <!-- 注意：这一层元素并不是必须的 -->
            <input type="text" class="layui-input" id="test1" placeholder="选择日期">
        </div>
        <a href="/logout" class="layui-btn layui-btn-normal">退出</a>
        <table class="layui-table">
            <colgroup>
                <col width="300">
                <col width="300">
                <col>
            </colgroup>
            <thead>
            <tr>
                <th>昵称</th>
                <th>加入时间</th>
                <th>签名</th>
            </tr>
            </thead>
            <tbody>
            @foreach($infos as $info)
                <tr>
                    <td>{{$info->bianhao}}</td>
                    <td>{{$info->renshu}}</td>
                    <td>{{$info->date}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    </body>
    <script>
        layui.use('laydate', function(){
            var laydate = layui.laydate;

            //执行一个laydate实例
            laydate.render({
                elem: '#test1' //指定元素
                ,done: function(value, date, endDate){
                    window.location.href='/info/'+value;
                }
            });
        });    </script>
</html>
