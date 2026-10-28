<?php
require_once __DIR__ . '/includes/bootstrap.php';
redirect(user() ? 'dashboard.php' : 'login.php');
