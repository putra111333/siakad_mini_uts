<?php
// File: public/logout.php

require_once __DIR__ . '/../src/Auth.php';

// Tinggal panggil static method logout dari class Auth
Auth::logout();