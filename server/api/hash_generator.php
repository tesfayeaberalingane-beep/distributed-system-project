<?php
$password = '1344'; // The plaintext password you want to use
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Plaintext: " . $password . "\n";
echo "New Hash: " . $hashed_password . "\n";
?>