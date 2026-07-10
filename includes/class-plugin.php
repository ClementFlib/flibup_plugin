<?php
/**
 * Classe principale du plugin (chef d'orchestre).
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instancie et coordonne les différents modules du plugin.
 */
final class Plugin {

	/**
	 * Instance unique.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Modules chargés.
	 *
	 * @var array<string,object>
	 */
	private $modules = array();

	/**
	 * Renvoie l'instance unique.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur privé (singleton).
	 */
	private function __construct() {}

	/**
	 * Initialise le plugin.
	 *
	 * @return void
	 */
	public function init() {
		// Traductions.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Type de contenu personnalisé (toujours nécessaire, admin et public).
		$this->modules['post_type'] = new Post_Type();
		$this->modules['post_type']->register_hooks();

		// Mise à jour GitHub (uniquement en admin, pour ne pas ralentir le public).
		if ( is_admin() ) {
			$this->modules['admin']   = new Admin\Admin();
			$this->modules['admin']->register_hooks();

			$this->modules['ajax']    = new Admin\Ajax();
			$this->modules['ajax']->register_hooks();

			$this->modules['updater'] = new Updater();
			$this->modules['updater']->register_hooks();
		}

		// Rendu public.
		if ( ! is_admin() ) {
			$this->modules['frontend'] = new Frontend\Frontend();
			$this->modules['frontend']->register_hooks();
		}

		// L'aperçu admin passe par le front avec un paramètre dédié : on charge
		// le module frontend aussi lorsque la prévisualisation est demandée.
		if ( isset( $_GET['flibup_preview'] ) && ! isset( $this->modules['frontend'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->modules['frontend'] = new Frontend\Frontend();
			$this->modules['frontend']->register_hooks();
		}
	}

	/**
	 * Renvoie un module chargé.
	 *
	 * @param string $key Clé du module.
	 * @return object|null
	 */
	public function get_module( $key ) {
		return isset( $this->modules[ $key ] ) ? $this->modules[ $key ] : null;
	}

	/**
	 * Charge le domaine de traduction.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'flib-up', false, dirname( FLIBUP_BASENAME ) . '/languages' );
	}

	/**
	 * Activation : enregistre le CPT puis rafraîchit les permaliens.
	 *
	 * @return void
	 */
	public static function activate() {
		$post_type = new Post_Type();
		$post_type->register_post_type();

		// Valeurs par défaut des réglages globaux.
		Settings::maybe_set_defaults();

		flush_rewrite_rules();
	}

	/**
	 * Désactivation : nettoie les permaliens et le cache interne.
	 * Aucune donnée n'est supprimée ici.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flibup_clear_active_cache();
		flush_rewrite_rules();
	}
}
