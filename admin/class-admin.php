<?php
/**
 * Contrôleur d'administration.
 *
 * @package FlibUp
 */

namespace FlibUp\Admin;

use FlibUp\Popup;
use FlibUp\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu, assets, méta-boîtes, enregistrement et page de réglages.
 */
class Admin {

	const NONCE_ACTION = 'flibup_save_popup';
	const NONCE_FIELD  = 'flibup_nonce';

	/**
	 * Capacité requise pour gérer les pop-ups.
	 *
	 * @return string
	 */
	public static function capability() {
		/** Capacité de gestion des pop-ups. */
		return (string) apply_filters( 'flibup_manage_capability', 'manage_options' );
	}

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . FLIBUP_POST_TYPE, array( $this, 'save_popup' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Menu principal Flib'Up.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = self::capability();

		add_menu_page(
			"Flib'Up",
			"Flib'Up",
			$cap,
			'edit.php?post_type=' . FLIBUP_POST_TYPE,
			'',
			'dashicons-external',
			58
		);

		add_submenu_page(
			'edit.php?post_type=' . FLIBUP_POST_TYPE,
			__( 'Toutes les pop-ups', 'flib-up' ),
			__( 'Toutes les pop-ups', 'flib-up' ),
			$cap,
			'edit.php?post_type=' . FLIBUP_POST_TYPE
		);

		add_submenu_page(
			'edit.php?post_type=' . FLIBUP_POST_TYPE,
			__( 'Ajouter', 'flib-up' ),
			__( 'Ajouter', 'flib-up' ),
			$cap,
			'post-new.php?post_type=' . FLIBUP_POST_TYPE
		);

		add_submenu_page(
			'edit.php?post_type=' . FLIBUP_POST_TYPE,
			__( 'Réglages', 'flib-up' ),
			__( 'Réglages', 'flib-up' ),
			$cap,
			'flibup-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enregistre les réglages globaux.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'flibup_settings_group',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitise les réglages globaux.
	 *
	 * @param mixed $input Entrée brute.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'delete_data_on_uninstall' => isset( $input['delete_data_on_uninstall'] ) ? 1 : 0,
			'allow_multiple'           => isset( $input['allow_multiple'] ) ? 1 : 0,
			'github_user'              => isset( $input['github_user'] ) ? sanitize_text_field( $input['github_user'] ) : '',
			'github_repo'              => isset( $input['github_repo'] ) ? sanitize_text_field( $input['github_repo'] ) : '',
		);
	}

	/**
	 * Ajoute la méta-boîte de configuration.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'flibup_config',
			__( 'Configuration de la pop-up', 'flib-up' ),
			array( new Meta_Boxes(), 'render' ),
			FLIBUP_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Enregistre la pop-up.
	 *
	 * @param int      $post_id ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function save_popup( $post_id, $post ) {
		// Sauvegarde automatique : on ignore.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Nonce.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Capacité.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$clean = self::sanitize_fields( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessus.

		$popup = new Popup( $post_id );
		$popup->save( $clean );

		flibup_clear_active_cache();
	}

	/**
	 * Sanitise l'ensemble des champs d'une pop-up.
	 *
	 * @param array $src Données brutes ($_POST).
	 * @return array Valeurs sanitisées indexées par clé du schéma.
	 */
	public static function sanitize_fields( array $src ) {
		$get = static function ( $key, $default = '' ) use ( $src ) {
			return isset( $src[ $key ] ) ? wp_unslash( $src[ $key ] ) : $default;
		};

		$clean = array();

		// --- Contenu ---
		$clean['visible_title'] = sanitize_text_field( $get( 'flibup_visible_title' ) );
		$clean['content']       = wp_kses_post( $get( 'flibup_content' ) );
		$clean['button_text']   = sanitize_text_field( $get( 'flibup_button_text' ) );
		$clean['button_url']    = esc_url_raw( $get( 'flibup_button_url' ) );
		$target                 = $get( 'flibup_button_target' );
		$clean['button_target'] = ( '_blank' === $target ) ? '_blank' : '_self';

		// --- Activation ---
		$clean['enabled'] = ! empty( $src['flibup_enabled'] ) ? 1 : 0;

		// --- Dimensions ---
		$clean['width']     = flibup_compose_length( $get( 'flibup_width_val' ), $get( 'flibup_width_unit' ), 'px' );
		$clean['max_width'] = flibup_compose_length( $get( 'flibup_max_width_val' ), $get( 'flibup_max_width_unit' ), 'vw' );
		$clean['min_height'] = flibup_compose_length( $get( 'flibup_min_height_val' ), $get( 'flibup_min_height_unit' ), 'px' );
		$clean['max_height'] = flibup_compose_length( $get( 'flibup_max_height_val' ), $get( 'flibup_max_height_unit' ), 'vh' );
		if ( '' === $clean['width'] ) {
			$clean['width'] = '600px';
		}
		if ( '' === $clean['max_width'] ) {
			$clean['max_width'] = '90vw';
		}
		if ( '' === $clean['max_height'] ) {
			$clean['max_height'] = '85vh';
		}

		$clean['padding']          = flibup_sanitize_css_length( $get( 'flibup_padding' ), '32px' );
		$align                     = $get( 'flibup_text_align' );
		$clean['text_align']       = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left';
		$clean['title_size']       = flibup_sanitize_css_length( $get( 'flibup_title_size' ), '24px' );
		$clean['content_size']     = flibup_sanitize_css_length( $get( 'flibup_content_size' ), '16px' );
		$clean['button_text_size'] = flibup_sanitize_css_length( $get( 'flibup_button_text_size' ), '16px' );

		$btn_width           = trim( (string) $get( 'flibup_button_width' ) );
		$clean['button_width'] = ( 'auto' === $btn_width || '100%' === $btn_width )
			? $btn_width
			: flibup_sanitize_css_length( $btn_width, 'auto' );

		$clean['button_padding'] = self::sanitize_padding_shorthand( $get( 'flibup_button_padding' ), '12px 20px' );
		$clean['radius']         = flibup_sanitize_css_length( $get( 'flibup_radius' ), '8px' );

		$clean['bg_color']           = flibup_sanitize_color( $get( 'flibup_bg_color' ), '#ffffff' );
		$clean['title_color']        = flibup_sanitize_color( $get( 'flibup_title_color' ), '#111111' );
		$clean['text_color']         = flibup_sanitize_color( $get( 'flibup_text_color' ), '#333333' );
		$clean['button_color']       = flibup_sanitize_color( $get( 'flibup_button_color' ), '#1a7d33' );
		$clean['button_text_color']  = flibup_sanitize_color( $get( 'flibup_button_text_color' ), '#ffffff' );
		$clean['button_hover_color'] = flibup_sanitize_color( $get( 'flibup_button_hover_color' ), '#125b25' );

		// --- Ciblage ---
		$mode                   = $get( 'flibup_targeting_mode' );
		$allowed_modes          = array( 'everywhere', 'front_page', 'all_pages', 'all_posts', 'selected' );
		$clean['targeting_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : 'everywhere';
		$clean['include_pages'] = self::sanitize_id_list( $get( 'flibup_include_pages', array() ) );
		$clean['include_posts'] = self::sanitize_id_list( $get( 'flibup_include_posts', array() ) );
		$clean['exclude_pages'] = self::sanitize_id_list( $get( 'flibup_exclude_pages', array() ) );
		$clean['exclude_posts'] = self::sanitize_id_list( $get( 'flibup_exclude_posts', array() ) );

		// --- Fréquence ---
		$freq                    = $get( 'flibup_frequency_mode' );
		$allowed_freq            = array( 'always', 'session', 'visitor', 'days' );
		$clean['frequency_mode'] = in_array( $freq, $allowed_freq, true ) ? $freq : 'session';
		$clean['frequency_days'] = flibup_sanitize_int_range( $get( 'flibup_frequency_days' ), 1, 3650, 7 );
		$clean['cookie_days']    = flibup_sanitize_int_range( $get( 'flibup_cookie_days' ), 1, 3650, 365 );
		$clean['campaign_version'] = sanitize_text_field( $get( 'flibup_campaign_version', '1' ) );
		if ( '' === $clean['campaign_version'] ) {
			$clean['campaign_version'] = '1';
		}

		// --- Déclenchement ---
		$tmode                 = $get( 'flibup_trigger_mode' );
		$clean['trigger_mode'] = ( 'delay' === $tmode ) ? 'delay' : 'immediate';
		$clean['trigger_delay'] = flibup_sanitize_int_range( $get( 'flibup_trigger_delay' ), 0, 600000, 0 );
		$tunit                  = $get( 'flibup_trigger_delay_unit' );
		$clean['trigger_delay_unit'] = ( 'ms' === $tunit ) ? 'ms' : 's';

		// --- Programmation ---
		$clean['start_datetime'] = self::sanitize_datetime( $get( 'flibup_start_datetime' ) );
		$clean['end_datetime']   = self::sanitize_datetime( $get( 'flibup_end_datetime' ) );

		// --- Masque ---
		$clean['overlay_color']       = flibup_sanitize_color( $get( 'flibup_overlay_color' ), '#000000' );
		$clean['overlay_opacity']     = flibup_sanitize_float_range( $get( 'flibup_overlay_opacity' ), 0, 1, 0.6 );
		$clean['overlay_transparent'] = ! empty( $src['flibup_overlay_transparent'] ) ? 1 : 0;
		$clean['overlay_blur']        = ! empty( $src['flibup_overlay_blur'] ) ? 1 : 0;
		$clean['overlay_blur_px']     = flibup_sanitize_int_range( $get( 'flibup_overlay_blur_px' ), 0, 50, 4 );
		$clean['anim_speed']          = flibup_sanitize_int_range( $get( 'flibup_anim_speed' ), 0, 5000, 250 );
		$clean['anim_disabled']       = ! empty( $src['flibup_anim_disabled'] ) ? 1 : 0;
		$clean['block_scroll']        = ! empty( $src['flibup_block_scroll'] ) ? 1 : 0;

		// --- Fermeture ---
		$clean['close_size']        = flibup_sanitize_int_range( $get( 'flibup_close_size' ), 8, 100, 20 );
		$clean['close_color']       = flibup_sanitize_color( $get( 'flibup_close_color' ), '#333333' );
		$clean['close_hover_color'] = flibup_sanitize_color( $get( 'flibup_close_hover_color' ), '#000000' );
		$cpos                       = $get( 'flibup_close_position' );
		$allowed_pos                = array( 'inside-tr', 'inside-tl', 'outside-tr', 'outside-tl' );
		$clean['close_position']    = in_array( $cpos, $allowed_pos, true ) ? $cpos : 'inside-tr';
		$clean['close_offset_x']    = flibup_sanitize_int_range( $get( 'flibup_close_offset_x' ), -100, 100, 12 );
		$clean['close_offset_y']    = flibup_sanitize_int_range( $get( 'flibup_close_offset_y' ), -100, 100, 12 );
		$clean['close_hit_area']    = flibup_sanitize_int_range( $get( 'flibup_close_hit_area' ), 20, 100, 40 );
		$clean['close_bg_enabled']  = ! empty( $src['flibup_close_bg_enabled'] ) ? 1 : 0;
		$clean['close_bg_color']    = flibup_sanitize_color( $get( 'flibup_close_bg_color' ), '#ffffff' );
		$clean['close_bg_radius']   = flibup_sanitize_int_range( $get( 'flibup_close_bg_radius' ), 0, 50, 50 );
		$clean['close_on_overlay']  = ! empty( $src['flibup_close_on_overlay'] ) ? 1 : 0;
		$clean['close_on_esc']      = ! empty( $src['flibup_close_on_esc'] ) ? 1 : 0;

		// --- Avancé ---
		$clean['priority'] = flibup_sanitize_int_range( $get( 'flibup_priority' ), 0, 1000, 10 );

		return $clean;
	}

	/**
	 * Sanitise une valeur « padding » type « 12px 20px » (1 à 4 longueurs).
	 *
	 * @param mixed  $value   Valeur.
	 * @param string $default Repli.
	 * @return string
	 */
	private static function sanitize_padding_shorthand( $value, $default ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return $default;
		}
		$parts = preg_split( '/\s+/', $value );
		if ( count( $parts ) > 4 ) {
			return $default;
		}
		$clean = array();
		foreach ( $parts as $p ) {
			$s = flibup_sanitize_css_length( $p, '' );
			if ( '' === $s ) {
				return $default;
			}
			$clean[] = $s;
		}
		return implode( ' ', $clean );
	}

	/**
	 * Sanitise une liste d'ID.
	 *
	 * @param mixed $value Liste ou chaîne CSV.
	 * @return array<int>
	 */
	private static function sanitize_id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitise une date/heure « Y-m-d\TH:i » ou « Y-m-d H:i » en « Y-m-d H:i ».
	 *
	 * @param mixed $value Valeur.
	 * @return string
	 */
	private static function sanitize_datetime( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$value = str_replace( 'T', ' ', $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::\d{2})?$/', $value, $m ) ) {
			return sprintf( '%s-%s-%s %s:%s', $m[1], $m[2], $m[3], $m[4], $m[5] );
		}
		return '';
	}

	/**
	 * Charge les assets uniquement sur les écrans du plugin.
	 *
	 * @param string $hook Hook courant.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$is_popup_edit = ( $screen && FLIBUP_POST_TYPE === $screen->post_type
			&& in_array( $screen->base, array( 'post', 'post-new' ), true ) );
		$is_settings   = ( false !== strpos( (string) $hook, 'flibup-settings' ) );

		if ( ! $is_popup_edit && ! $is_settings ) {
			return;
		}

		wp_enqueue_style(
			'flibup-admin',
			FLIBUP_URL . 'assets/css/admin.css',
			array(),
			FLIBUP_VERSION
		);

		if ( $is_popup_edit ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'flibup-admin',
				FLIBUP_URL . 'assets/js/admin.js',
				array( 'jquery', 'wp-color-picker' ),
				FLIBUP_VERSION,
				true
			);

			wp_localize_script(
				'flibup-admin',
				'flibupAdmin',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'searchNonce'  => wp_create_nonce( 'flibup_search' ),
					'i18n'         => array(
						'search'  => __( 'Rechercher…', 'flib-up' ),
						'noResult' => __( 'Aucun résultat', 'flib-up' ),
						'remove'  => __( 'Retirer', 'flib-up' ),
					),
				)
			);
		}
	}

	/**
	 * Rendu de la page de réglages.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'flib-up' ) );
		}

		$settings = Settings::all();
		?>
		<div class="wrap flibup-settings">
			<h1><?php esc_html_e( "Flib'Up — Réglages", 'flib-up' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'flibup_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Autoriser plusieurs pop-ups par page', 'flib-up' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[allow_multiple]" value="1" <?php checked( 1, (int) $settings['allow_multiple'] ); ?> />
								<?php esc_html_e( 'Si coché, les pop-ups éligibles sont affichées en file d\'attente (une à la fois). Sinon, seule la pop-up de plus haute priorité s\'affiche.', 'flib-up' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Suppression des données à la désinstallation', 'flib-up' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( 1, (int) $settings['delete_data_on_uninstall'] ); ?> />
								<?php esc_html_e( 'Si coché, toutes les pop-ups et réglages seront supprimés lors de la désinstallation du plugin. Décoché par défaut (les données sont conservées).', 'flib-up' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Compte GitHub', 'flib-up' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( Settings::OPTION ); ?>[github_user]" value="<?php echo esc_attr( $settings['github_user'] ); ?>" placeholder="<?php echo esc_attr( defined( 'FLIBUP_GITHUB_USER' ) ? FLIBUP_GITHUB_USER : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Laisser vide pour utiliser la valeur par défaut définie dans le code.', 'flib-up' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dépôt GitHub', 'flib-up' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="<?php echo esc_attr( Settings::OPTION ); ?>[github_repo]" value="<?php echo esc_attr( $settings['github_repo'] ); ?>" placeholder="<?php echo esc_attr( defined( 'FLIBUP_GITHUB_REPO' ) ? FLIBUP_GITHUB_REPO : '' ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
