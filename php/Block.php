<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types                 = get_post_types( array( 'public' => true ) );
		$class_name                 = $attributes['className'];
		$current_post_id            = get_the_ID();
		$tag_name                   = 'foo';
		$cat_name                   = 'baz';
		$post_types_count_transient = get_transient( 'xwp_site_counts' );
		$post_types_list            = $post_types_count_transient;
		$post_count_transient       = get_transient( 'xwp_post_counts' );
		$post_count_query           = $post_count_transient;

		if ( false === $post_types_list ) {
			foreach ( $post_types as $post_type_slug ) {
				$post_types_list[ $post_type_slug ]['count'] = count(
					get_posts(
						array(
							'post_type'      => $post_type_slug,
							'posts_per_page' => -1,
						)
					)
				);
			}
			set_transient( 'xwp_site_counts', $post_types_list, 24 * HOUR_IN_SECONDS );
		}

		if ( false === $post_count_transient ) {
			$post_count_query = new WP_Query(
				array(
					'post_type'   => array( 'post', 'page' ),
					'post_status' => 'any',
					'date_query'  => array(
						array(
							'hour'    => 9,
							'compare' => '>=',
						),
						array(
							'hour'    => 17,
							'compare' => '<=',
						),
					),
					'tax_query'   => array(
						array(
							'taxonomy' => 'post_tag',
							'field'    => 'slug',
							'terms'    => $tag_name,
						),
						array(
							'taxonomy' => 'category',
							'field'    => 'name',
							'terms'    => $cat_name,
						),
					),
					// 'post__not_in' => array( $current_post_id ),
				)
			);
			set_transient( 'xwp_post_counts', $post_count_query, 24 * HOUR_IN_SECONDS );
		}

		ob_start();

		?>
		<div class="<?php echo isset( $class_name ) ? esc_attr( $class_name ) : ''; ?>">
			<h2><?php esc_html_e( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
			<?php foreach ( $post_types_list as $post_type_key => $post_type_value ) : ?>
				<li>
					<?php
					echo esc_html(
						sprintf(
							__( 'There are %1$d %2$s.', 'site-counts' ),
							absint( $post_type_value['count'] ),
							$post_type_key
						)
					);
					?>
				</li>
			<?php endforeach; ?>
			</ul>
			<p>
				<?php
				echo esc_html(
					sprintf(
						__( 'The current post ID is %1$d.', 'site_counts' ),
						absint( $current_post_id )
					)
				);
				?>
			</p>

			<?php if ( $post_count_query->have_posts() ) : ?>
				 <h2>
					<?php
						echo esc_html(
							sprintf(
								__( '%1$d %2$s with the tag of %3$s and the category of %4$s', 'site-counts' ),
								absint( $post_count_query->found_posts ),
								_n(
									'post',
									'posts',
									absint( $post_count_query->found_posts ),
									'site-counts'
								),
								'foo',
								'baz'
							)
						);
					?>
				</h2>
				<ul>
				<?php
				while ( $post_count_query->have_posts() ) :
					$post_count_query->the_post();
					?>
					<li><?php the_title(); ?></li>
					<?php
				endwhile;
				wp_reset_postdata();
			endif;
			?>
			</ul>
		</div>

		<?php
		return ob_get_clean();
	}
}
