<?php
require_once __DIR__ . '/inc_app.php';
session_destroy();
header('Location: index.php');
