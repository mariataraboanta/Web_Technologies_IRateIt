<?php
function autoload($class){
    
    if(file_exists(PDIR . SEP . 'app' .  SEP . $class . '.php')){
        require_once(PDIR . SEP . 'app' . SEP . $class . '.php');
    }else if(file_exists(PDIR . SEP . 'app' . SEP . 'controllers' . SEP . $class . '.php')){
        require_once(PDIR .  SEP . 'app' . SEP . 'controllers' . SEP . $class . '.php');
    } else if(file_exists(PDIR . SEP . 'app' . SEP . 'models' . SEP . $class . '.php')){
        require_once(PDIR .  SEP . 'app' . SEP . 'models' . SEP . $class . '.php');
    } else if(file_exists(PDIR . SEP . 'app' . SEP . 'core' . SEP . $class . '.php')){
        require_once(PDIR .  SEP . 'app' . SEP . 'core' . SEP . $class . '.php');
    } else if(file_exists(PDIR . SEP . 'app' . SEP . 'middleware' . SEP . $class . '.php')){
        require_once(PDIR .  SEP . 'app' . SEP . 'middleware' . SEP . $class . '.php');
    } else {
        echo ("Nu s-a gasit calea: $class in: " . PDIR . SEP . $class . '.php');
        exit();
    }
}
spl_autoload_register('autoload');
?>