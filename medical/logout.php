<?php
session_start();
session_destroy();
header("Location: visites.php");
exit();
?>
