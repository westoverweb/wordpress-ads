<?php
/**
 * Plugin Name: Image Ads
 * Description: Manage image ads for Top Banner, Side Banner, Bottom Banner, and Blog Post Content areas via shortcodes, plus Google AdSense auto-insertion on single posts.
 * Version: 1.2.0
 * Author: WP Site Mason
 * License: GPL2
 *
 * == Changelog ==
 * 1.2.0 - Add Google AdSense settings tab with automatic in-content insertion on single blog posts (not pages).
 * 1.1.0 - Replace per-slot shortcodes with one shortcode per position that randomly rotates among the configured slots on each page load.
 * 1.0.0 - Initial release: Top Banner, Side Banner, Bottom Banner, and Blog Post Content positions, each with 4 image ad slots.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Image_Ads_Plugin {

	const VERSION = '1.2.0';

	const POSITIONS = [
		'top_banner'    => 'Top Banner',
		'side_banner'   => 'Side Banner',
		'bottom_banner' => 'Bottom Banner',
		'blog_post'     => 'Blog Post Content',
	];

	const SLOTS = 4;

	const ADSENSE_OPTION = 'image_ads_adsense';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'the_content', [ $this, 'auto_insert_adsense' ] );
		$this->register_shortcodes();
	}

	// -------------------------------------------------------------------------
	// Admin menu
	// -------------------------------------------------------------------------

	public function add_admin_menu() {
		add_menu_page(
			'Image Ads',
			'Image Ads',
			'manage_options',
			'image-ads',
			[ $this, 'render_admin_page' ],
			'dashicons-format-image',
			30
		);
	}

	// -------------------------------------------------------------------------
	// Admin assets
	// -------------------------------------------------------------------------

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'toplevel_page_image-ads' ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style(
			'image-ads-admin',
			plugin_dir_url( __FILE__ ) . 'admin.css',
			[],
			self::VERSION
		);
		wp_enqueue_script(
			'image-ads-admin',
			plugin_dir_url( __FILE__ ) . 'admin.js',
			[ 'jquery' ],
			self::VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Admin page
	// -------------------------------------------------------------------------

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if ( isset( $_POST['image_ads_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['image_ads_nonce'] ) ), 'image_ads_save' ) ) {
			foreach ( array_keys( self::POSITIONS ) as $pos ) {
				for ( $i = 1; $i <= self::SLOTS; $i++ ) {
					$key = "image_ads_{$pos}_{$i}";
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? $_POST[ $key ] : [];
					update_option( $key, $this->sanitize_ad( $raw ) );
				}
			}

			// AdSense settings. The ad code legitimately contains <script> tags, so it
			// is stored raw — only manage_options users reach this branch.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$adsense_raw = isset( $_POST['image_ads_adsense'] ) && is_array( $_POST['image_ads_adsense'] ) ? wp_unslash( $_POST['image_ads_adsense'] ) : [];
			update_option( self::ADSENSE_OPTION, $this->sanitize_adsense( $adsense_raw ) );

			$saved = true;
		}

		$tabs              = self::POSITIONS;
		$tabs['adsense']   = 'AdSense';

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'top_banner';
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'top_banner';
		}
		?>
		<div class="wrap image-ads-wrap">
			<h1>Image Ads</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=image-ads&tab=' . $tab_key ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="">
				<?php wp_nonce_field( 'image_ads_save', 'image_ads_nonce' ); ?>

				<?php foreach ( self::POSITIONS as $pos_key => $pos_label ) : ?>
					<div class="image-ads-tab <?php echo $active_tab === $pos_key ? 'image-ads-tab--active' : ''; ?>">

						<div class="image-ads-shortcode-bar">
							<strong>Shortcode:</strong>
							<code>[<?php echo esc_html( $this->shortcode_tag( $pos_key ) ); ?>]</code>
							&nbsp; — randomly rotates among the ad slots below that have an image set.
						</div>

						<div class="image-ads-grid">
							<?php for ( $i = 1; $i <= self::SLOTS; $i++ ) : ?>
								<?php $this->render_slot( $pos_key, $i ); ?>
							<?php endfor; ?>
						</div>

					</div>
				<?php endforeach; ?>

				<?php $this->render_adsense_tab( $active_tab === 'adsense' ); ?>

				<p class="submit">
					<button type="submit" class="button button-primary">Save Ads</button>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_slot( $pos_key, $slot_num ) {
		$option_key = "image_ads_{$pos_key}_{$slot_num}";
		$ad         = get_option( $option_key, $this->empty_ad() );
		$has_image  = ! empty( $ad['image_url'] );
		?>
		<div class="image-ad-slot">
			<div class="image-ad-slot__header">
				<span class="image-ad-slot__number">Ad <?php echo esc_html( $slot_num ); ?></span>
			</div>

			<div class="image-ad-slot__preview <?php echo $has_image ? '' : 'image-ad-slot__preview--empty'; ?>">
				<?php if ( $has_image ) : ?>
					<img src="<?php echo esc_url( $ad['image_url'] ); ?>" alt="Ad preview">
				<?php else : ?>
					<span>No image</span>
				<?php endif; ?>
			</div>

			<input type="hidden"
				   name="<?php echo esc_attr( $option_key ); ?>[image_url]"
				   value="<?php echo esc_url( $ad['image_url'] ); ?>"
				   class="ad-image-url">

			<div class="image-ad-slot__actions">
				<button type="button" class="button upload-ad-image">
					<?php echo $has_image ? 'Change Image' : 'Select Image'; ?>
				</button>
				<button type="button" class="button remove-ad-image" <?php echo $has_image ? '' : 'style="display:none"'; ?>>
					Remove
				</button>
			</div>

			<table class="form-table image-ad-slot__fields">
				<tr>
					<th><label>Link URL</label></th>
					<td>
						<input type="url"
							   name="<?php echo esc_attr( $option_key ); ?>[link_url]"
							   value="<?php echo esc_url( $ad['link_url'] ); ?>"
							   class="regular-text"
							   placeholder="https://">
					</td>
				</tr>
				<tr>
					<th><label>Alt Text</label></th>
					<td>
						<input type="text"
							   name="<?php echo esc_attr( $option_key ); ?>[alt_text]"
							   value="<?php echo esc_attr( $ad['alt_text'] ); ?>"
							   class="regular-text"
							   placeholder="Describe the image">
					</td>
				</tr>
				<tr>
					<th><label>New Tab</label></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( $option_key ); ?>[new_tab]"
								   value="1"
								   <?php checked( $ad['new_tab'], '1' ); ?>>
							Open link in a new tab
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_adsense_tab( $is_active ) {
		$adsense   = $this->get_adsense();
		$positions = [
			'before' => 'Before the post content',
			'middle' => 'In the middle of the post content',
			'after'  => 'After the post content',
		];
		?>
		<div class="image-ads-tab <?php echo $is_active ? 'image-ads-tab--active' : ''; ?>">

			<div class="image-ads-shortcode-bar">
				<strong>Google AdSense</strong> — paste your ad unit code below. When enabled, it is
				inserted automatically into <strong>single blog posts only</strong> (never on pages),
				so it runs independently of your banner image ads.
			</div>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="image_ads_adsense_enabled">Enable on posts</label></th>
					<td>
						<label>
							<input type="checkbox"
								   id="image_ads_adsense_enabled"
								   name="image_ads_adsense[enabled]"
								   value="1"
								   <?php checked( $adsense['enabled'], '1' ); ?>>
							Automatically insert the AdSense unit into every single post
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="image_ads_adsense_position">Position</label></th>
					<td>
						<select id="image_ads_adsense_position" name="image_ads_adsense[position]">
							<?php foreach ( $positions as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $adsense['position'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="image_ads_adsense_code">Ad unit code</label></th>
					<td>
						<textarea id="image_ads_adsense_code"
								  name="image_ads_adsense[code]"
								  rows="10"
								  class="large-text code"
								  placeholder="Paste the full AdSense ad unit snippet here, including the <script> and <ins> tags."><?php echo esc_textarea( $adsense['code'] ); ?></textarea>
						<p class="description">
							Paste the complete in-article ad unit code from your AdSense account. The code is
							output exactly as entered, so only paste code from a trusted source.
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Shortcodes
	// -------------------------------------------------------------------------

	private function register_shortcodes() {
		foreach ( array_keys( self::POSITIONS ) as $pos ) {
			add_shortcode( $this->shortcode_tag( $pos ), function( $atts ) use ( $pos ) {
				return $this->render_random_ad( $pos );
			} );
		}
	}

	private function render_random_ad( $pos ) {
		$available = [];
		for ( $i = 1; $i <= self::SLOTS; $i++ ) {
			$ad = get_option( "image_ads_{$pos}_{$i}", $this->empty_ad() );
			if ( ! empty( $ad['image_url'] ) ) {
				$available[] = $ad;
			}
		}

		if ( empty( $available ) ) {
			return '';
		}

		$ad = $available[ array_rand( $available ) ];

		$img = sprintf(
			'<img src="%s" alt="%s" class="image-ad image-ad--%s">',
			esc_url( $ad['image_url'] ),
			esc_attr( $ad['alt_text'] ),
			esc_attr( $pos )
		);

		if ( ! empty( $ad['link_url'] ) ) {
			$target = $ad['new_tab'] === '1' ? ' target="_blank" rel="noopener noreferrer"' : '';
			return sprintf(
				'<a href="%s"%s class="image-ad-link">%s</a>',
				esc_url( $ad['link_url'] ),
				$target,
				$img
			);
		}

		return $img;
	}

	// -------------------------------------------------------------------------
	// AdSense (auto-insert on single posts)
	// -------------------------------------------------------------------------

	public function auto_insert_adsense( $content ) {
		// Posts only — never pages — and only the main content in the main loop.
		if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$adsense = $this->get_adsense();

		if ( $adsense['enabled'] !== '1' || trim( $adsense['code'] ) === '' ) {
			return $content;
		}

		$snippet = '<div class="image-ad-adsense" style="margin:24px 0;">' . $adsense['code'] . '</div>';

		return $this->insert_at_position( $content, $snippet, $adsense['position'] );
	}

	private function insert_at_position( $content, $snippet, $position ) {
		if ( $position === 'before' ) {
			return $snippet . $content;
		}

		if ( $position === 'after' ) {
			return $content . $snippet;
		}

		// Middle: split on closing paragraph tags and insert near the midpoint,
		// always after a complete paragraph rather than inside one.
		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		$count = count( $parts );

		// Too few paragraphs to land in the middle gracefully — append instead.
		if ( $count < 4 ) {
			return $content . $snippet;
		}

		$insert_at = (int) floor( $count / 2 );
		if ( $insert_at % 2 !== 0 ) {
			$insert_at++;
		}

		array_splice( $parts, $insert_at, 0, $snippet );

		return implode( '', $parts );
	}

	private function get_adsense() {
		return wp_parse_args(
			get_option( self::ADSENSE_OPTION, [] ),
			[
				'enabled'  => '0',
				'position' => 'middle',
				'code'     => '',
			]
		);
	}

	private function sanitize_adsense( $raw ) {
		$position = $raw['position'] ?? 'middle';
		if ( ! in_array( $position, [ 'before', 'middle', 'after' ], true ) ) {
			$position = 'middle';
		}

		return [
			'enabled'  => ! empty( $raw['enabled'] ) ? '1' : '0',
			'position' => $position,
			// Stored raw on purpose: AdSense snippets contain <script>/<ins> markup.
			'code'     => isset( $raw['code'] ) ? trim( (string) $raw['code'] ) : '',
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function shortcode_tag( $pos ) {
		$tags = [
			'top_banner'    => 'top_banner',
			'side_banner'   => 'side_banner',
			'bottom_banner' => 'bottom_banner',
			'blog_post'     => 'blog_post_ad',
		];
		return $tags[ $pos ] ?? $pos;
	}

	private function sanitize_ad( $raw ) {
		return [
			'image_url' => esc_url_raw( $raw['image_url'] ?? '' ),
			'link_url'  => esc_url_raw( $raw['link_url'] ?? '' ),
			'alt_text'  => sanitize_text_field( $raw['alt_text'] ?? '' ),
			'new_tab'   => ! empty( $raw['new_tab'] ) ? '1' : '0',
		];
	}

	private function empty_ad() {
		return [
			'image_url' => '',
			'link_url'  => '',
			'alt_text'  => '',
			'new_tab'   => '1',
		];
	}
}

new Image_Ads_Plugin();
