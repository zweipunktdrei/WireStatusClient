<?php

namespace ProcessWire;

/**
 * WireStatusClient Info
 *
 * Metadata configuration for the WireStatusClient module.
 *
 * @author Markus Thomas
 */

$info = [
    'title' => __('WireStatus Client'),
    'summary' => __('Securely exposes system version, diagnostic info, and pending module updates to a WireStatus Master installation.'),
    'version' => '1.0.1',
    'author' => 'Markus Thomas',
    'icon' => 'plug',
    'requires' => 'ProcessWire>=3.0.0',
    'autoload' => true,
    'singular' => true,
];
