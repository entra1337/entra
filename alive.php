<?php
header('Content-Type: text/plain');
http_response_code(200);

// Check if the cookie named 'auth' is set and equals 'toot'
if (isset($_COOKIE['auth']) && $_COOKIE['auth'] === 'toot') {
    echo "im alive";
} else {
    echo "Access denied";
}
?>
