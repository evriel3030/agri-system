<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#(^|/)\.(?!well-known)|^/(config|includes|sql|docs)/|\.(sql|md|example)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
return false; 