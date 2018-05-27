<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="{{url('layui/css/layui.css')}}">
        <link rel="stylesheet" href="{{url('message/message.css')}}">
        <script src="{{url('layui/layui.js')}}"></script>

        <title>验证密码</title>

    </head>
    <body>
        <div class="layui-container" style="width: 500px;margin-top: 300px;text-align: center">
            <form class="layui-form" action="" method="post">
                @csrf
                <div class="layui-form-item">
                    <label class="layui-form-label">查看密码</label>
                    <div class="layui-input-block">
                        <input type="password" name="password" required  lay-verify="required" placeholder="请输入 查看密码" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit >登录</button>
                        <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                    </div>
                </div>
            </form>

            <script>
                //Demo
                layui.use('form', function(){
                    var form = layui.form;
                });
            </script>
            @if(!$errors->isEmpty())
                <script>alert('{{$errors->first('msg')}}');</script>
            @endif
        </div>
    </body>
</html>
