<?php
session_start();
require_once '../config/session_check.php';
requireGuestLogin();

header('Location: booking.php#depositPayment');
exit;
