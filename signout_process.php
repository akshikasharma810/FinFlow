<?php
// signout_process.php

// 1. Start the session to be able to access session variables.
session_start();

// 2. Unset all session variables to clear the user data.
$_SESSION = array();

// 3. Destroy the session. This will delete the session file on the server.
session_destroy();

// 4. Redirect the user to the sign-in page.
// The signin.html file is where you want the user to go after logging out.
header("Location: signin.html?status=signedout");
exit();
?>