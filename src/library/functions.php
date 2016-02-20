<?php

if (!function_exists('h')) {
    function h($text, $double = true, $charset = 'UTF-8') {
        if (is_bool($text)) {
            return $text;
        }

        if (is_object($text)) {
            if (method_exists($text, '__toString')) {
                $text = (string)$text;
            } else {
                $text = '(object)' . get_class($text);
            }
        }

        if (is_string($double)) {
            $charset = $double;
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, $charset, $double);
    }
}

if (!function_exists('debug')) {
    function debug($obj) {
        var_dump($obj);
    }
}
