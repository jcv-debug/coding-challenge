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
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes for the block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes ) {
		$post_types = get_post_types( [ 'public' => true ] );
		ob_start();
		?>
		<div<?php echo isset( $attributes['className'] ) ? ' class="' . $attributes['className'] . '"' : ''; ?>>
			<h2><?php echo __( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
				<?php
				foreach ( $post_types as $post_type_slug ) :
					$post_type_object = get_post_type_object( $post_type_slug );
					$post_stats       = wp_count_posts( $post_type_slug );
					?>
					<li>
						<?php
						// translators: %1$d is replaced with number of posts per type.
						// translators: %2$s is replaced with posts type label.
						echo sprintf( _n( 'There is %1$d %2$s.', 'There are %1$d %2$s.', $post_stats->publish, 'site-counts' ), $post_stats->publish, $post_type_object->labels->{( $post_stats->publish > 1 ? 'name' : 'singular_name' )} );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php
				// translators: %d is replaced with Post ID.
				echo sprintf( __( 'The current post ID is %d.', 'site-counts' ), get_the_ID() );
				?>
			</p>
			<?php
			$query      = new WP_Query(
				[
					'fields'         => [ 'ID', 'post_title' ],
					'post_type'      => [ 'post', 'page' ],
					'post_status'    => 'any',
					'date_query'     => [
						[
							'hour'    => 9,
							'compare' => '>=',
						],
						[
							'hour'    => 17,
							'compare' => '<=',
						],
					],
					'tag'            => 'foo',
					'category_name'  => 'baz',
					'posts_per_page' => 5,
					'no_found_rows'  => true,
				],
			);
			$posts      = [];
			$post_count = 0;
			foreach ( $query->posts as $post ) {
				if ( get_the_ID() === $post->post_id ) {
					continue;
				}
				$posts[] = $post;
				$post_count++;
			}
			if ( $post_count ) :
				?>
			<h2>
				<?php
				// translators: %d is replaced with query post counts.
				echo sprintf( _n( '%d post with the tag of foo and the category of baz', '%d posts with the tag of foo and the category of baz', $post_count, 'site-counts' ), $post_count );
				?>
				<ul>
					<?php foreach ( $posts as $post ) : ?>
						<li><?php echo $post->post_title; ?></li>
					<?php endforeach; ?>
					<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
