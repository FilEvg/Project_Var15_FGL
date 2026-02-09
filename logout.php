<?php
require_once 'config.php';

session_start();
// Завершаем гостевой режим, если он был
endGuestMode();
session_destroy();
redirect('login.php');
?>