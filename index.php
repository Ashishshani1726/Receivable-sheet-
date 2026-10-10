<?php
require_once __DIR__.'/inc/db.php';
if(!setup_complete()){header('Location: setup.php');exit;}
require_once __DIR__.'/inc/auth.php';
if(current_user())header('Location: dashboard.php');else header('Location: login.php');exit;
