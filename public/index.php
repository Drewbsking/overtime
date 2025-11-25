<?php
require_once __DIR__ . '/../bootstrap.php';
if (Auth::check()) {
    redirect('/dashboard.php');
}
redirect('/login.php');
