<?php
/*
Plugin Name: Sponsors
Description: Adds a Sponsor custom post type for managing sponsor logos and content.
Version: 1.0
Author: Toby Batch
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap the plugin
use Sponsors\Sponsors;

$plugin = new Sponsors();

//register_activation_hook(__FILE__, array($plugin, 'activate'));
//register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));
