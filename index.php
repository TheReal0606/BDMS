<?php
require __DIR__ . '/lib/auth.php';
header('Location: ' . (current_admin() ? 'dashboard.php' : 'login.php'));
