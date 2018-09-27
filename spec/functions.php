<?php

function d(...$vars) {
    foreach ($vars as $var) {
        var_dump($var);
    }
    die();
}

function dw(...$vars) {
    d(...array_map(function($var) {
        return $var->getWrappedObject();
    }, $vars));
}