<?php
spl_autoload_register(function($class){
    $file = 'src/' . str_replace('\\', '/', $class) . '.php';
    if(is_file($file)){
        require_once $file;
    }
});

