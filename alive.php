<?php
// Check if the cookie named 'auth' (or whatever name you want) is set and equals 'toot'
if (isset($_COOKIE['auth']) && $_COOKIE['auth'] === 'toot') {
    echo "im alive";
} else {
    echo "Access denied";
}
?>
