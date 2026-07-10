<?php
/**
 * Modèle « Pop-up » : encapsule un enregistrement et ses métadonnées.
 *
 * Contient le schéma des champs (source unique de vérité) utilisé à la fois
 * pour les valeurs par défaut, la sanitation à l'enregistrement et la lecture.
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Un objet Popup lit et écrit ses réglages depuis les post meta.
 */
class Popup {

	/**
	 * ID du post.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Cache des valeurs lues.
	 *
	 * @var array|null
	 */
	protected $values = null;

	/**
	 * Constructeur.
	 *
	 * @param int $post_id ID du post pop-up.
	 */
	public function __construct( $post_id ) {
		$this->id = (int) $post_id;
	}

	/**
	 * ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Titre interne (titre du post).
	 *
	 * @return string
	 */
	public function get_internal_title() {
		return get_the_title( $this->id );
	}

	/**
	 * Schéma complet des champs.
	 *
	 * Chaque entrée : default, sanitize (callable ou identifiant interne).
	 *
	 * @return array<string,array>
	 */
	public static function schema() {
		return array(
			// --- Contenu ---
			'visible_title'      => array( 'default' => '' ),
			'content'            => array( 'default' => '' ),
			'button_text'        => array( 'default' => '' ),
			'button_url'         => array( 'default' => '' ),
			'button_target'      => array( 'default' => '_self' ),

			// --- Activation ---
			'enabled'            => array( 'default' => 0 ),

			// --- Dimensions / mise en page ---
			'width'              => array( 'default' => '600px' ),
			'max_width'          => array( 'default' => '90vw' ),
			'min_height'         => array( 'default' => '' ),
			'max_height'         => array( 'default' => '85vh' ),
			'padding'            => array( 'default' => '32px' ),
			'text_align'         => array( 'default' => 'left' ),
			'title_size'         => array( 'default' => '24px' ),
			'content_size'       => array( 'default' => '16px' ),
			'button_text_size'   => array( 'default' => '16px' ),
			'button_width'       => array( 'default' => 'auto' ),
			'button_padding'     => array( 'default' => '12px 20px' ),
			'radius'             => array( 'default' => '8px' ),
			'bg_color'           => array( 'default' => '#ffffff' ),
			'title_color'        => array( 'default' => '#111111' ),
			'text_color'         => array( 'default' => '#333333' ),
			'button_color'       => array( 'default' => '#1a7d33' ),
			'button_text_color'  => array( 'default' => '#ffffff' ),
			'button_hover_color' => array( 'default' => '#125b25' ),

			// --- Ciblage ---
			'targeting_mode'     => array( 'default' => 'everywhere' ),
			'include_pages'      => array( 'default' => array() ),
			'include_posts'      => array( 'default' => array() ),
			'exclude_pages'      => array( 'default' => array() ),
			'exclude_posts'      => array( 'default' => array() ),

			// --- Fréquence ---
			'frequency_mode'     => array( 'default' => 'session' ),
			'frequency_days'     => array( 'default' => 7 ),
			'cookie_days'        => array( 'default' => 365 ),
			'campaign_version'   => array( 'default' => '1' ),

			// --- Déclenchement ---
			'trigger_mode'       => array( 'default' => 'immediate' ),
			'trigger_delay'      => array( 'default' => 0 ),
			'trigger_delay_unit' => array( 'default' => 's' ),

			// --- Programmation ---
			'start_datetime'     => array( 'default' => '' ),
			'end_datetime'       => array( 'default' => '' ),

			// --- Masque ---
			'overlay_color'       => array( 'default' => '#000000' ),
			'overlay_opacity'     => array( 'default' => 0.6 ),
			'overlay_transparent' => array( 'default' => 0 ),
			'overlay_blur'        => array( 'default' => 0 ),
			'overlay_blur_px'     => array( 'default' => 4 ),
			'anim_speed'          => array( 'default' => 250 ),
			'anim_disabled'       => array( 'default' => 0 ),
			'block_scroll'        => array( 'default' => 1 ),

			// --- Fermeture ---
			'close_size'          => array( 'default' => 20 ),
			'close_color'         => array( 'default' => '#333333' ),
			'close_hover_color'   => array( 'default' => '#000000' ),
			'close_position'      => array( 'default' => 'inside-tr' ),
			'close_offset_x'      => array( 'default' => 12 ),
			'close_offset_y'      => array( 'default' => 12 ),
			'close_hit_area'      => array( 'default' => 40 ),
			'close_bg_enabled'    => array( 'default' => 0 ),
			'close_bg_color'      => array( 'default' => '#ffffff' ),
			'close_bg_radius'     => array( 'default' => 50 ),
			'close_on_overlay'    => array( 'default' => 1 ),
			'close_on_esc'        => array( 'default' => 1 ),

			// --- Avancé ---
			'priority'            => array( 'default' => 10 ),
		);
	}

	/**
	 * Renvoie les valeurs par défaut.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::schema() as $key => $def ) {
			$defaults[ $key ] = $def['default'];
		}
		return $defaults;
	}

	/**
	 * Charge toutes les valeurs (depuis les meta, avec repli sur le défaut).
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->values ) {
			return $this->values;
		}

		$values = array();
		foreach ( self::schema() as $key => $def ) {
			$meta_key = FLIBUP_META_PREFIX . $key;
			$exists   = metadata_exists( 'post', $this->id, $meta_key );

			if ( $exists ) {
				$raw = get_post_meta( $this->id, $meta_key, true );
				if ( is_array( $def['default'] ) && ! is_array( $raw ) ) {
					$raw = array();
				}
				$values[ $key ] = $raw;
			} else {
				$values[ $key ] = $def['default'];
			}
		}

		$this->values = $values;
		return $values;
	}

	/**
	 * Renvoie une valeur unique.
	 *
	 * @param string $key Clé.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * La pop-up est-elle activée ?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (int) $this->get( 'enabled' ) === 1
			&& get_post_status( $this->id ) === 'publish';
	}

	/**
	 * Enregistre les valeurs (déjà sanitisées) en post meta.
	 *
	 * @param array $clean Valeurs sanitisées, indexées par clé du schéma.
	 * @return void
	 */
	public function save( array $clean ) {
		foreach ( self::schema() as $key => $def ) {
			if ( array_key_exists( $key, $clean ) ) {
				update_post_meta( $this->id, FLIBUP_META_PREFIX . $key, $clean[ $key ] );
			}
		}
		$this->values = null; // Invalide le cache local.
	}

	/**
	 * Construit la configuration exposée au JavaScript public.
	 *
	 * @return array
	 */
	public function to_frontend_config() {
		$start_ts = Scheduler::datetime_to_timestamp( $this->get( 'start_datetime' ) );
		$end_ts   = Scheduler::datetime_to_timestamp( $this->get( 'end_datetime' ) );

		return array(
			'id'             => $this->id,
			'priority'       => (int) $this->get( 'priority' ),
			'frequency'      => $this->get( 'frequency_mode' ),
			'frequencyDays'  => (int) $this->get( 'frequency_days' ),
			'cookieDays'     => (int) $this->get( 'cookie_days' ),
			'campaign'       => (string) $this->get( 'campaign_version' ),
			'triggerMode'    => $this->get( 'trigger_mode' ),
			'triggerDelayMs' => Scheduler::delay_in_ms( $this->get( 'trigger_delay' ), $this->get( 'trigger_delay_unit' ) ),
			'startTs'        => $start_ts,
			'endTs'          => $end_ts,
			'closeOnOverlay' => (int) $this->get( 'close_on_overlay' ) === 1,
			'closeOnEsc'     => (int) $this->get( 'close_on_esc' ) === 1,
			'blockScroll'    => (int) $this->get( 'block_scroll' ) === 1,
			'animDisabled'   => (int) $this->get( 'anim_disabled' ) === 1,
			'animSpeed'      => (int) $this->get( 'anim_speed' ),
		);
	}

	/**
	 * Construit les variables CSS inline (déjà sanitisées) de la pop-up.
	 *
	 * @return array<string,string>
	 */
	public function css_vars() {
		$vars = array(
			'--flibup-width'              => (string) $this->get( 'width' ),
			'--flibup-max-width'          => (string) $this->get( 'max_width' ),
			'--flibup-min-height'         => (string) $this->get( 'min_height' ),
			'--flibup-max-height'         => (string) $this->get( 'max_height' ),
			'--flibup-padding'            => (string) $this->get( 'padding' ),
			'--flibup-text-align'         => (string) $this->get( 'text_align' ),
			'--flibup-title-size'         => (string) $this->get( 'title_size' ),
			'--flibup-content-size'       => (string) $this->get( 'content_size' ),
			'--flibup-btn-text-size'      => (string) $this->get( 'button_text_size' ),
			'--flibup-btn-width'          => (string) $this->get( 'button_width' ),
			'--flibup-btn-padding'        => (string) $this->get( 'button_padding' ),
			'--flibup-radius'             => (string) $this->get( 'radius' ),
			'--flibup-bg'                 => (string) $this->get( 'bg_color' ),
			'--flibup-title-color'        => (string) $this->get( 'title_color' ),
			'--flibup-text-color'         => (string) $this->get( 'text_color' ),
			'--flibup-btn-bg'             => (string) $this->get( 'button_color' ),
			'--flibup-btn-color'          => (string) $this->get( 'button_text_color' ),
			'--flibup-btn-hover'          => (string) $this->get( 'button_hover_color' ),
			'--flibup-overlay-color'      => (string) $this->get( 'overlay_color' ),
			'--flibup-overlay-opacity'    => (string) $this->overlay_opacity_value(),
			'--flibup-overlay-blur'       => ( (int) $this->get( 'overlay_blur' ) === 1 ) ? ( (int) $this->get( 'overlay_blur_px' ) ) . 'px' : '0px',
			'--flibup-anim-speed'         => ( (int) $this->get( 'anim_disabled' ) === 1 ) ? '0ms' : ( (int) $this->get( 'anim_speed' ) ) . 'ms',
			'--flibup-close-size'         => ( (int) $this->get( 'close_size' ) ) . 'px',
			'--flibup-close-color'        => (string) $this->get( 'close_color' ),
			'--flibup-close-hover'        => (string) $this->get( 'close_hover_color' ),
			'--flibup-close-offset-x'     => ( (int) $this->get( 'close_offset_x' ) ) . 'px',
			'--flibup-close-offset-y'     => ( (int) $this->get( 'close_offset_y' ) ) . 'px',
			'--flibup-close-hit'          => ( (int) $this->get( 'close_hit_area' ) ) . 'px',
			'--flibup-close-bg'           => ( (int) $this->get( 'close_bg_enabled' ) === 1 ) ? (string) $this->get( 'close_bg_color' ) : 'transparent',
			'--flibup-close-bg-radius'    => ( (int) $this->get( 'close_bg_radius' ) ) . '%',
		);

		return $vars;
	}

	/**
	 * Opacité effective du masque (0 si transparent demandé).
	 *
	 * @return float
	 */
	protected function overlay_opacity_value() {
		if ( (int) $this->get( 'overlay_transparent' ) === 1 ) {
			return 0.0;
		}
		return (float) $this->get( 'overlay_opacity' );
	}
}
