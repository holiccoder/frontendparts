<?php

function replace_html_bracket($code){
    $codethree = str_replace("<","&lt;",$code);
    return $codethree;
}

function create_files($post_id, $html, $css, $js, $title, $bootstrap, $jquery){
    $dir = __DIR__;
    $dir = str_replace("\\","/", $dir);
    $dir = str_replace("inc", "", $dir);
    $html_file = $dir."/html_files/".$post_id.".html";
    $css_file = $dir."/css_files/".$post_id.".css";
    $js_file = $dir."/js_files/".$post_id.".js";
    $html_handle = fopen($html_file,"w") or die("cannot create html file");
    $html_value = html_file_create($post_id, $html, $title, $bootstrap, $jquery);
    fwrite($html_handle, $html_value);
    fclose($html_handle);
    $css_handle = fopen($css_file,"w") or die("cannot create css file");
    fwrite($css_handle, $css);
    fclose($css_handle);
    $js_handle = fopen($js_file,"w") or die("cannot create js file");
    fwrite($js_handle, $js);
    fclose($js_handle);
}

function html_file_create($post_id, $html, $title, $bootstrap, $jquery){
    $file = '<!doctype html>';
    $file .= "\n";
    $file .= '<html lang="zh-CN">';
    $file .= "\n";
    $file .= '<head>';
    $file .= "\n";
    $file .= '<meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">';
    $file .= '<meta http-equiv="X-UA-Compatible" content="ie=edge">';
    $file .= "\n";
    if($bootstrap == "required"){
        $file .= '<link rel="stylesheet" href="'.get_template_directory_uri().'/css/bootstrap.css">';
    }
    $file .= "\n";
    $file .= '<link rel="stylesheet" href="'.get_template_directory_uri().'/css_files/'.$post_id.'.css">';
    $file .= "\n";
    $file .= '<title>'.$title.'</title>';
    $file .= "\n";
    $file .= '</head>';
    $file .= "\n";
    $file .= '<body>';
    $file .= "\n";
    $file .= $html;
    $file .= "\n";
    if($jquery == "required"){
        $file .= '<script src="'.get_template_directory_uri().'/js/jquery.min.js"></script>';
    }
    $file .= "\n";
    $file .= '<script src="'.get_template_directory_uri().'/js_files/'.$post_id.'.js"></script>';
    $file .= "\n";
    $file .= '</body>';
    $file .= "\n";
    $file .= '</html>';
    return $file;
}
