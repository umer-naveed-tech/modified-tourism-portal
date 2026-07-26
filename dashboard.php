<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if($_SESSION['user_role'] == 'agent') {
    header('Location: agent_dashboard.php');
} else {
    header('Location: visitor_dashboard.php');
}
exit();
?>