<?php
require_once __DIR__ . '/includes/config.php';

// Destroy session
session_destroy();

// Redirect to login
redirect(BASE_URL . '/index.php');
