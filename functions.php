<?php

include 'inc/functions.php';

function add_frontend_scripts(){
    wp_enqueue_style("jqueryui",get_template_directory_uri()."/css/jquery-ui.min.css",array(),"1.0.0", "all");
    wp_enqueue_style("perfect-scrollbar",get_template_directory_uri()."/css/perfect-scrollbar.min.css",array(),"1.0.0", "all");
    wp_enqueue_style("prism",get_template_directory_uri()."/css/prism.css",array(),"1.0.0", "all");
    wp_enqueue_style("custom",get_template_directory_uri()."/css/custom.css",array(),"1.0.0", "all");
    wp_enqueue_script("jquerymin",get_template_directory_uri()."/js/jquery.min.js","","1.0.0",true);
    wp_enqueue_script("jquery-ui",get_template_directory_uri()."/js/jquery-ui.min.js","","1.0.0",true);
    wp_enqueue_script("perfect-scrollbar",get_template_directory_uri()."/js/perfect-scrollbar.js","","1.0.0",true);
    wp_enqueue_script("alsoresize",get_template_directory_uri()."/js/alsoresize.min.js","","1.0.0",true);
    wp_enqueue_script("prism",get_template_directory_uri()."/js/prism.js","","1.0.0",true);
    wp_enqueue_script("custom",get_template_directory_uri()."/js/custom.js","","1.0.0",true);
}

add_action("wp_enqueue_scripts", "add_frontend_scripts");

function qianduan_theme_setup()
{
    register_nav_menus(array(
        'topmenu'=>__('topmenu')
    ));
    add_theme_support('post-thumbnails');
}

add_action('after_setup_theme', 'qianduan_theme_setup');

function replace_two_brackets($post_id){
    $html_value_old = get_post_meta($post_id, "html", true);
    $css_value = get_post_meta($post_id, "css", true);
    $js_value = get_post_meta($post_id, "javascript", true);
    $title = get_the_title($post_id);
    $bootstrap = get_post_meta($post_id, "bootstrap_css_dependent", true);
    $jquery = get_post_meta($post_id, "jquery_dependent", true);
    $html_value_new = replace_html_bracket($html_value_old);
    create_files($post_id, $html_value_old, $css_value, $js_value, $title, $bootstrap, $jquery);
    html_file_create($post_id, $html_value_old, $title, $bootstrap, $jquery);
    update_post_meta($post_id, "html", $html_value_new);
}

add_action("acf/save_post","replace_two_brackets");