<?php
session_start();
session_unset();
session_destroy();
header("Location: ../pags/login.html");
exit();
?>
