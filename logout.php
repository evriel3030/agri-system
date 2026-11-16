<?php
require __DIR__ . '/includes/bootstrap.php';

// Log out only through a POST-less simple link is fine here (no data is changed on the server).
logout();
session_start();                       // new session just to show the message
flash('success', 'You have been logged out.');
redirect('login.php');
