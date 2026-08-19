<?php
/**
 * Rendu public des pop-ups.
 *
 * @package FlibUp
 */

namespace FlibUp\Frontend;

use FlibUp\Popup;
use FlibUp\Settings;
use FlibUp\Targeting;
use FlibUp\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collecte les pop-ups éligibles, charge les assets si nécessaire et injecte
 * le balisage dans le pied de page.
 */
class Frontend {

	/**
	 * Pop-ups à afficher sur la requête courante, indexées par ID.
	 *
	 * @var array<int,Popup>
	 */
	private $to_render = array();

	/**
	 * Contenus déjà rendus (shortcodes exécutés), indexés par ID.
	 *
	 * @var array<int,string>
	 */
	private $rendered_content = array();

	/**
	 * Mode prévisualisation (ID de la pop-up) ou 0.
	 *
	 * @var int
	 */
	private $preview_id = 0;

	/**
	 * Les assets ont-ils déjà été demandés ?
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp', array( $this, 'prepare' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'render' ), 99 );
	}

	/**
	 * Déclare les shortcodes de déclenchement.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'flibup_button', array( $this, 'shortcode_button' ) );
		add_shortcode( 'flibup_trigger', array( $this, 'shortcode_button' ) );
	}

	/**
	 * Détermine les pop-ups à afficher (le plus tôt possible dans le rendu).
	 *
	 * @return void
	 */
	public function prepare() {
		// Prévisualisation admin.
		if ( isset( $_GET['flibup_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->maybe_prepare_preview();
			if ( $this->preview_id ) {
				return;
			}
		}

		if ( is_admin() ) {
			return;
		}

		$ids = $this->get_eligible_popup_ids();
		if ( empty( $ids ) ) {
			return;
		}

		$auto  = array();
		$click = array();

		foreach ( $ids as $id ) {
			$popup = new Popup( $id );

			if ( ! $popup->is_enabled() ) {
				continue;
			}
			// On n'injecte pas une pop-up définitivement expirée.
			if ( Scheduler::is_expired( $popup ) ) {
				continue;
			}
			if ( ! Targeting::matches_current( $popup ) ) {
				continue;
			}

			if ( $popup->is_click_triggered() ) {
				$click[] = $popup;
			} else {
				$auto[] = $popup;
			}
		}

		// Tri par priorité décroissante.
		$by_priority = static function ( Popup $a, Popup $b ) {
			return (int) $b->get( 'priority' ) <=> (int) $a->get( 'priority' );
		};
		usort( $auto, $by_priority );
		usort( $click, $by_priority );

		// Si plusieurs pop-ups automatiques et mode multiple désactivé : on ne
		// garde que la première. Les pop-ups déclenchées au clic ne sont jamais
		// écartées, puisqu'elles n'apparaissent que sur action du visiteur.
		if ( ! Settings::allow_multiple() && count( $auto ) > 1 ) {
			$auto = array( $auto[0] );
		}

		foreach ( array_merge( $auto, $click ) as $popup ) {
			$this->to_render[ $popup->get_id() ] = $popup;
		}
	}

	/**
	 * Ajoute une pop-up au rendu de la page courante, hors ciblage.
	 *
	 * Utilisé par le shortcode : poser un bouton déclencheur sur une page
	 * implique d'y afficher la pop-up correspondante.
	 *
	 * @param int $id ID de la pop-up.
	 * @return bool Vrai si la pop-up est bien prise en charge.
	 */
	public function request_popup( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		if ( isset( $this->to_render[ $id ] ) ) {
			$this->enqueue_assets_now();
			return true;
		}

		if ( get_post_type( $id ) !== FLIBUP_POST_TYPE ) {
			return false;
		}

		$popup = new Popup( $id );
		if ( ! $popup->is_enabled() || Scheduler::is_expired( $popup ) ) {
			return false;
		}

		$this->to_render[ $id ] = $popup;
		$this->enqueue_assets_now();

		// Rendu immédiat du contenu : un shortcode imbriqué (formulaire, carte,
		// galerie…) doit encore pouvoir déclarer ses propres styles et scripts,
		// ce qui ne serait plus possible au moment du rendu en pied de page.
		$this->get_rendered_content( $popup );

		return true;
	}

	/**
	 * Prépare la prévisualisation (utilisateur habilité uniquement).
	 *
	 * @return void
	 */
	private function maybe_prepare_preview() {
		$id = isset( $_GET['flibup_preview'] ) ? absint( $_GET['flibup_preview'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'flibup_preview_' . $id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		if ( get_post_type( $id ) !== FLIBUP_POST_TYPE ) {
			return;
		}

		$this->preview_id       = $id;
		$this->to_render[ $id ] = new Popup( $id );
	}

	/**
	 * Liste des ID de pop-ups potentiellement actives (avec cache transient).
	 *
	 * @return array<int>
	 */
	private function get_eligible_popup_ids() {
		$cached = get_transient( FLIBUP_ACTIVE_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => FLIBUP_POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => FLIBUP_META_PREFIX . 'enabled',
						'value' => '1',
					),
				),
			)
		);

		$ids = array_map( 'intval', $query->posts );

		set_transient( FLIBUP_ACTIVE_CACHE_KEY, $ids, 12 * HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Enregistre les assets publics sans les charger.
	 *
	 * Ils peuvent être demandés tardivement par le shortcode, au milieu du
	 * contenu : l'enregistrement doit donc précéder le rendu de la page.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'flibup-public',
			FLIBUP_URL . 'assets/css/public.css',
			array(),
			FLIBUP_VERSION
		);

		wp_register_script(
			'flibup-public',
			FLIBUP_URL . 'assets/js/public.js',
			array(),
			FLIBUP_VERSION,
			true
		);

		wp_localize_script(
			'flibup-public',
			'flibupPublic',
			array(
				'allowMultiple' => Settings::allow_multiple(),
				'preview'       => (bool) $this->preview_id,
				'now'           => time(),
			)
		);
	}

	/**
	 * Charge les assets publics si au moins une pop-up est à rendre.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( empty( $this->to_render ) ) {
			return;
		}

		$this->enqueue_assets_now();

		// Le contenu est rendu dès maintenant (et non au pied de page) afin que
		// les shortcodes qui déclarent leurs propres styles et scripts aient
		// encore la possibilité de les faire charger.
		foreach ( $this->to_render as $popup ) {
			$this->get_rendered_content( $popup );
		}
	}

	/**
	 * Demande le chargement effectif des assets.
	 *
	 * @return void
	 */
	private function enqueue_assets_now() {
		if ( $this->assets_enqueued ) {
			return;
		}
		wp_enqueue_style( 'flibup-public' );
		wp_enqueue_script( 'flibup-public' );
		$this->assets_enqueued = true;
	}

	/**
	 * Renvoie le contenu rendu d'une pop-up (shortcodes exécutés, mis en cache).
	 *
	 * @param Popup $popup Pop-up.
	 * @return string
	 */
	private function get_rendered_content( Popup $popup ) {
		$id = $popup->get_id();

		if ( ! isset( $this->rendered_content[ $id ] ) ) {
			$this->rendered_content[ $id ] = flibup_render_content( (string) $popup->get( 'content' ) );
		}

		return $this->rendered_content[ $id ];
	}

	/**
	 * Shortcode `[flibup_button id="12" text="…"]`.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public function shortcode_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'text'  => __( 'Ouvrir', 'flib-up' ),
				'class' => '',
				'style' => 'button',
				'title' => '',
			),
			$atts,
			'flibup_button'
		);

		$id = absint( $atts['id'] );
		if ( ! $this->request_popup( $id ) ) {
			return '';
		}

		$classes = array( 'flibup-trigger' );
		$classes[] = ( 'link' === $atts['style'] ) ? 'flibup-trigger-link' : 'flibup-trigger-button';

		if ( '' !== trim( (string) $atts['class'] ) ) {
			$classes = array_merge( $classes, preg_split( '/\s+/', sanitize_text_field( $atts['class'] ) ) );
		}

		$title = sanitize_text_field( (string) $atts['title'] );

		return sprintf(
			'<button type="button" class="%1$s" data-flibup-open="%2$d"%3$s>%4$s</button>',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			$id,
			$title ? ' title="' . esc_attr( $title ) . '"' : '',
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Injecte le balisage des pop-ups dans le pied de page.
	 *
	 * @return void
	 */
	public function render() {
		if ( empty( $this->to_render ) ) {
			return;
		}

		echo "\n<!-- Flib'Up -->\n";
		foreach ( $this->to_render as $popup ) {
			$this->render_popup( $popup );
		}
		echo "<!-- /Flib'Up -->\n";
	}

	/**
	 * Balisage d'une pop-up.
	 *
	 * @param Popup $popup Pop-up.
	 * @return void
	 */
	private function render_popup( Popup $popup ) {
		$id                = $popup->get_id();
		$config            = $popup->to_frontend_config();
		$config['preview'] = ( $this->preview_id === $id );

		$visible_title = (string) $popup->get( 'visible_title' );
		$content       = $this->get_rendered_content( $popup );
		$button_text   = (string) $popup->get( 'button_text' );
		$button_url    = (string) $popup->get( 'button_url' );
		$button_target = ( '_blank' === $popup->get( 'button_target' ) ) ? '_blank' : '_self';

		$image     = $popup->get_image();
		$image_pos = (string) $popup->get( 'image_position' );

		$css_vars = $popup->css_vars();
		$style    = '';
		foreach ( $css_vars as $prop => $val ) {
			$style .= $prop . ':' . $val . ';';
		}

		$dialog_id = 'flibup-dialog-' . $id;
		$title_id  = 'flibup-title-' . $id;
		$has_title = ( '' !== trim( $visible_title ) );
		$is_modal  = ( (int) $popup->get( 'overlay_passthrough' ) !== 1 );

		$overlay_classes = array(
			'flibup-overlay',
			'flibup-pos-' . (string) $popup->get( 'position' ),
		);
		if ( (int) $popup->get( 'overlay_blur' ) === 1 && $is_modal ) {
			$overlay_classes[] = 'flibup-has-blur';
		}
		if ( ! $is_modal ) {
			$overlay_classes[] = 'flibup-passthrough';
		}

		$dialog_classes = array(
			'flibup-dialog',
			'flibup-close-' . (string) $popup->get( 'close_position' ),
			'flibup-img-' . ( $image ? $image_pos : 'none' ),
			'flibup-img-align-' . (string) $popup->get( 'image_align' ),
		);

		$json = wp_json_encode( $config );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $overlay_classes ) ); ?>"
			id="flibup-overlay-<?php echo esc_attr( $id ); ?>"
			data-flibup="<?php echo esc_attr( $json ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			hidden>
			<div class="<?php echo esc_attr( implode( ' ', $dialog_classes ) ); ?>"
				id="<?php echo esc_attr( $dialog_id ); ?>"
				role="dialog"
				aria-modal="<?php echo $is_modal ? 'true' : 'false'; ?>"
				<?php if ( $has_title ) : ?>
					aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
				<?php else : ?>
					aria-label="<?php esc_attr_e( 'Fenêtre d\'information', 'flib-up' ); ?>"
				<?php endif; ?>
				tabindex="-1">

				<button type="button" class="flibup-close" aria-label="<?php esc_attr_e( 'Fermer', 'flib-up' ); ?>">
					<?php echo $this->close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique interne. ?>
				</button>

				<?php
				$this->render_image( $popup, $image, 'top', $image_pos );
				$this->render_image( $popup, $image, 'above_title', $image_pos );
				?>

				<?php if ( $has_title ) : ?>
					<h2 class="flibup-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $visible_title ); ?></h2>
				<?php endif; ?>

				<?php $this->render_image( $popup, $image, 'below_title', $image_pos ); ?>

				<?php if ( '' !== trim( $content ) ) : ?>
					<div class="flibup-content">
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contenu filtré à l'enregistrement puis rendu par flibup_render_content(). ?>
					</div>
				<?php endif; ?>

				<?php $this->render_image( $popup, $image, 'below_content', $image_pos ); ?>

				<?php if ( '' !== trim( $button_text ) && '' !== trim( $button_url ) ) : ?>
					<div class="flibup-actions">
						<a class="flibup-button"
							href="<?php echo esc_url( $button_url ); ?>"
							<?php if ( '_blank' === $button_target ) : ?>
								target="_blank" rel="noopener noreferrer"
							<?php endif; ?>>
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Affiche l'image du corps si son emplacement correspond.
	 *
	 * @param Popup      $popup  Pop-up.
	 * @param array|null $image  Données de l'image.
	 * @param string     $slot   Emplacement en cours de rendu.
	 * @param string     $wanted Emplacement configuré.
	 * @return void
	 */
	private function render_image( Popup $popup, $image, $slot, $wanted ) {
		if ( null === $image || $slot !== $wanted ) {
			return;
		}

		$link      = (string) $popup->get( 'image_link' );
		$has_link  = ( '' !== trim( $link ) );
		$classes   = array( 'flibup-media', 'flibup-media-' . $slot );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $has_link ) : ?>
				<a href="<?php echo esc_url( $link ); ?>">
			<?php endif; ?>
			<img
				src="<?php echo esc_url( $image['src'] ); ?>"
				alt="<?php echo esc_attr( $image['alt'] ); ?>"
				<?php if ( $image['width'] && $image['height'] ) : ?>
					width="<?php echo esc_attr( $image['width'] ); ?>" height="<?php echo esc_attr( $image['height'] ); ?>"
				<?php endif; ?>
				<?php if ( '' !== $image['srcset'] ) : ?>
					srcset="<?php echo esc_attr( $image['srcset'] ); ?>" sizes="<?php echo esc_attr( $image['sizes'] ); ?>"
				<?php endif; ?>
				loading="lazy"
				decoding="async" />
			<?php if ( $has_link ) : ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Icône SVG de fermeture (légère, intégrée).
	 *
	 * @return string
	 */
	private function close_svg() {
		return '<svg class="flibup-close-icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>';
	}
}
