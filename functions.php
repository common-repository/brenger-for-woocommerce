<?php

/**
 * Polyfill for array_key_last(), since that is only supported in PHP 7.3 and
 * up. This can be removed if the minimum required version of PHP for this
 * plugin goes to 7.3 or higher.
 */
if( !function_exists('array_key_last') ) {
    function array_key_last(array $array) {
        if( !empty($array) ) return key(array_slice($array, -1, 1, true));
    }
}
