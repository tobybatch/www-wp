<?php

/*
Plugin Name: Rugby Player Cards
Description: Tracks player development with multiple dated cards
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap the plugin
use RugbyPlayerCards\RugbyPlayerCards;

new RugbyPlayerCards();
