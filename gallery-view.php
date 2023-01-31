<?php

/**
 * Plugin Name: Gallery View
 * Plugin URI:  https://toolstack.com/gallery-view
 * Description: View posts in a gallery layout in the admin.
 * Version:     1.1
 * Author:      GregRoss
 * Author URI:  https://toolstack.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: gallery-view
 */

define( 'GVP_VERSION', '1.1' );
define( 'GVP_PLUGIN_FILE', __FILE__ );

include_once( 'includes/class-gallery-view.php');

$gallery_view_plugin = new Gallery_View();
