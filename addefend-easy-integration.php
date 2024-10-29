<?php
/*
Plugin Name: AdDefend Easy Integration
Plugin URI: https://wordpress.org/plugins/addefend-easy-integration/
Description: The AdDefend Easy Intregration plug-in supports publishers in integrating the AdDefend anti-adblock solution.
Version: 1.12
Author: AdDefend GmbH
Author URI: https://www.addefend.com/
Text Domain: addefend-easy-integration
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.html
============================================================================================================
This software is provided "as is" and any express or implied warranties, including, but not limited to, the
implied warranties of merchantibility and fitness for a particular purpose are disclaimed. In no event shall
the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or
consequential damages(including, but not limited to, procurement of substitute goods or services; loss of
use, data, or profits; or business interruption) however caused and on any theory of liability, whether in
contract, strict liability, or tort(including negligence or otherwise) arising in any way out of the use of
this software, even if advised of the possibility of such damage.

For full license details see license.txt
============================================================================================================
*/
define( 'ADDEFEND_PLUGIN_PHP_FILE', __FILE__ );
define( 'ADDEFEND_PLUGIN_ROOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADDEFEND_MARKER', 'AdDefend' );
define( 'ADDEFEND_HTACCESS_MARKER', 'AdDefend' );
define( 'ADDEFEND_DEFAULT_IMAGE_DIRECTORY', '/wp-content');
include ADDEFEND_PLUGIN_ROOT_DIR.'Utils/Debugging.php';
include ADDEFEND_PLUGIN_ROOT_DIR.'Utils/misc.php';
include ADDEFEND_PLUGIN_ROOT_DIR.'View/options.php';
include ADDEFEND_PLUGIN_ROOT_DIR.'Components/script_integration.php';
include ADDEFEND_PLUGIN_ROOT_DIR.'Components/proxy_integration.php';
include ADDEFEND_PLUGIN_ROOT_DIR.'Components/proxy_internal_forwarding.php';
