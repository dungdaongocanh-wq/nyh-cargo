<?php
require_once 'config/session.php';
require_once 'config/constants.php';
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;