<!doctype html>
<html <?php language_attributes();?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <?php wp_head();?>
    <title><?php bloginfo("name");?></title>
</head>
<body>

<?php if(have_posts()): while(have_posts()): the_post();?>
<div class="wrapper">
    <div class="upper-half" id="upper-half">
        <iframe src="<?php echo get_template_directory_uri()."/html_files/".get_the_ID().".html";?>" frameborder="0" width="100%" height="100%"></iframe>
    </div>
    <div class="down-half" id="down-half">
        <div class="down-left html" id="down-left">
            <div class="heading-html">HTML</div>
            <pre><code class="language-markup line-numbers"><?php the_field("html");?></code></pre>
        </div>
        <div class="down-middle css" id="down-middle">
            <div class="heading-css">CSS</div>
            <pre><code class="line-numbers language-css"><?php the_field("css");?></code></pre>
        </div>
        <div class="down-right javascript" id="down-right">
            <div class="heading-javascript">Javascript</div>
            <pre><code class="language-javascript line-numbers">
                <?php the_field("javascript");?>
            </code></pre>
        </div>
    </div>
</div>

<?php endwhile; else:?>

没有任何内容.

<?php endif; ?>


<?php wp_footer();?>

</body>
</html>