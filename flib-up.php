<?php

/**
 * Plugin Name:       Flib'Up
 * Plugin URI:        https://github.com/ClementFlib/flibup_plugin
 * Description:       Création, configuration, programmation et affichage de fenêtres pop-up sur WordPress. Ciblage des pages, fréquence, programmation des dates, accessibilité et mise à jour depuis GitHub.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Les Flibustiers
 * Author URI:        https://les-flibustiers.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flib-up
 * Domain Path:       /languages
 * Update URI:        https://github.com/ClementFlib/flibup_plugin
 *
 * @package FlibUp
 */

namespace FlibUp;

// Sécurité : empêcher l'accès direct au fichier.
if (! defined('ABSPATH')) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Constantes du plugin.
 * ---------------------------------------------------------------------------
 */

define('FLIBUP_VERSION', '1.0.4');
define('FLIBUP_FILE', __FILE__);
define('FLIBUP_DIR', plugin_dir_path(__FILE__));
define('FLIBUP_URL', plugin_dir_url(__FILE__));
define('FLIBUP_BASENAME', plugin_basename(__FILE__));
define('FLIBUP_POST_TYPE', 'flibup_popup');
define('FLIBUP_META_PREFIX', '_flibup_');

/*
 * Coordonnées GitHub par défaut pour la mise à jour.
 * Elles peuvent être surchargées via les constantes FLIBUP_GITHUB_USER /
 * FLIBUP_GITHUB_REPO (dans wp-config.php) ou via les filtres correspondants.
 */
if (! defined('FLIBUP_GITHUB_USER')) {
	define('FLIBUP_GITHUB_USER', 'ClementFlib');
}
if (! defined('FLIBUP_GITHUB_REPO')) {
	define('FLIBUP_GITHUB_REPO', 'flibup_plugin');
}

/*
 * ---------------------------------------------------------------------------
 * Autoloader PSR-4 minimal (sans Composer).
 * FlibUp\Admin\Admin  =>  admin/class-admin.php
 * FlibUp\Popup        =>  includes/class-popup.php
 * ---------------------------------------------------------------------------
 */
spl_autoload_register(
	static function ($class) {
		$prefix = 'FlibUp\\';
		if (0 !== strpos($class, $prefix)) {
			return;
		}

		$relative = substr($class, strlen($prefix));
		$relative = str_replace('\\', '/', $relative);

		// Convertit le nom de classe en nom de fichier « class-xxx.php ».
		$parts     = explode('/', $relative);
		$class_end = array_pop($parts);
		$filename  = 'class-' . strtolower(str_replace('_', '-', $class_end)) . '.php';

		// Détermine le sous-dossier.
		$subdir = 'includes';
		if (! empty($parts)) {
			$first = strtolower($parts[0]);
			if ('admin' === $first) {
				$subdir = 'admin';
			} elseif ('frontend' === $first) {
				$subdir = 'public';
			}
		}

		$path = FLIBUP_DIR . $subdir . '/' . $filename;

		if (is_readable($path)) {
			require_once $path;
		}
	}
);

// Fichier d'aide (fonctions procédurales).
require_once FLIBUP_DIR . 'includes/helpers.php';

/*
 * ---------------------------------------------------------------------------
 * Hooks d'activation / désactivation.
 * ---------------------------------------------------------------------------
 */
register_activation_hook(__FILE__, array(__NAMESPACE__ . '\\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array(__NAMESPACE__ . '\\Plugin', 'deactivate'));

/*
 * ---------------------------------------------------------------------------
 * Démarrage.
 * ---------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->init();
	}
);
