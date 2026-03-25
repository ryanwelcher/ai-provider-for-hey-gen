<?php

/**
 * Plugin Name: AI Provider for HeyGen
 * Plugin URI: https://github.com/WordPress/ai-provider-for-hey-gen
 * Description: AI Provider for HeyGen for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Ryan Welcher
 * Author URI: https://make.wordpress.org/ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-hey-gen
 *
 * @package HeyGen
 */

declare(strict_types=1);

namespace HeyGen;

use WordPress\AiClient\AiClient;
use HeyGen\Provider\HeyGen;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for HeyGen with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( HeyGen::class ) ) {
		return;
	}

	$registry->registerProvider( HeyGen::class );
}

add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
