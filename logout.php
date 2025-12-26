<?php
require_once 'auth.php';

$auth = new Auth();
$auth->logout();

json_response(true, null, 'Logged out successfully');
?>