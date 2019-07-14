<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="<?php echo get_template_directory_uri();?>/css/bootstrap.css">
    <title>前端组件库</title>
</head>
<body>
<?php the_field("bootstrap_css_dependent");?>
     <div class="container">
         <div class="row">按布局和功能</div>
         <div class="row">
             <div class="col-sm">
                 <button class="btn btn-primary">
                     全宽自适应组件
                 </button>
             </div>
             <div class="col-sm">
                 <button class="btn btn-primary">
                     布局组件
                 </button>
             </div>
             <div class="col-sm">
                 <button class="btn btn-primary">
                     功能性组件
                 </button>
             </div>
             <div class="col-sm">
                 <button class="btn btn-primary">
                     提交表单
                 </button>
             </div>
             <div class="col-sm">
                 <button class="btn btn-primary">
                     移动端全宽组件
                 </button>
             </div>
         </div>
         <div class="row">按网站类型:</div>
         <div class="row">
             <div class="col-sm">
                 <div class="btn btn-primary">
                     电商网站
                 </div>
             </div>
             <div class="col-sm">
                 <div class="btn btn-primary">
                     博客
                 </div>
             </div>
             <div class="col-sm">
                 <div class="btn btn-primary">
                     论坛
                 </div>
             </div>
             <div class="col-sm">
                 <div class="btn btn-primary">
                     企业网站
                 </div>
             </div>
             <div class="col-sm">
                 <div class="btn btn-primary">
                     视频网站
                 </div>
             </div>
         </div>

     </div>
</body>
</html>