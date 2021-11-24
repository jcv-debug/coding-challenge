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
		// Update cache on post edit / save.
		add_action( 'save_post', [ $this, 'update_block_cache' ], 10, 3 );
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
	 * Registers the block.
	 */
	public function update_block_cache() {
		// Update the post_by_types cache.
		$this->get_posts_by_type( true );
		// Update the post_by_types cache.
		$this->get_posts_by_cat_tag( 0, true );
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes The attributes for the block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes ) {
		ob_start();
		?>
		<div<?php echo isset( $attributes['className'] ) ? ' class="' . $attributes['className'] . '"' : ''; ?>>
			<h2><?php echo __( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
				<?php
				foreach ( $this->get_posts_by_type() as $post ) :
					?>
					<li>
						<?php
						// translators: %1$d is replaced with number of posts per type.
						// translators: %2$s is replaced with posts type label.
						echo sprintf( _n( 'There is %1$d %2$s.', 'There are %1$d %2$s.', $post->count, 'site-counts' ), $post->count, $post->label );
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
			$posts_by_cat_tag = $this->get_posts_by_cat_tag( get_the_ID() );
			if ( $posts_by_cat_tag->count ) :
				?>
			<h2>
				<?php
				// translators: %d is replaced with query post counts.
				echo sprintf( _n( '%d post with the tag of foo and the category of baz', '%d posts with the tag of foo and the category of baz', $posts_by_cat_tag->count, 'site-counts' ), $posts_by_cat_tag->count );
				?>
				<ul>
					<?php foreach ( $posts_by_cat_tag->items as $post ) : ?>
						<li><?php echo $post->post_title; ?></li>
					<?php endforeach; ?>
					<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get's posts by type and stores them into 'counts_posts_by_type' cache
	 *
	 * @param bool $force When true, forces transient cache to be rewritten.
	 * @return array An array of StdClass objects
	 */
	private function get_posts_by_type( bool $force = false ) {
		// Check for the $posts_by_type key in the 'site-counts' group cache.
		$posts_by_type = get_transient( 'site-counts_posts_by_type' );
		if ( false === $force && false !== $posts_by_type ) {
			return $posts_by_type;
		} else {
			$posts_by_type = [];
		}
		// No cache is present, retrieve posts and compile.
		$post_types = get_post_types( [ 'public' => true ] );
		foreach ( $post_types as $post_type_slug ) {
			$post_type_object = get_post_type_object( $post_type_slug );
			$post_stats       = wp_count_posts( $post_type_slug );
			$posts_by_type[]  = (object) [
				'count' => $post_stats->publish,
				'label' => $post_type_object->labels->{( $post_stats->publish > 1 ? 'name' : 'singular_name' )},
			];
		}
		// Cache posts_by_type array for 30 minutes.
		set_transient( 'site-counts_posts_by_type', $posts_by_type, 30 * MINUTE_IN_SECONDS );
		// Return posts_by_type array.
		return $posts_by_type;
	}

	/**
	 * Get's 5 posts with foo tag and baz cat and stores them into 'site-counts_posts_by_cat_tag' cache
	 *
	 * @param int  $avoid_id Skip post with this ID.
	 * @param bool $force When true, forces transient cache to be rewritten.
	 * @return array An array of StdClass objects
	 */
	private function get_posts_by_cat_tag( int $avoid_id = 0, bool $force = false ) {
		// Check for the $posts_by_type key in the 'site-counts' group cache.
		$collection = get_transient( 'site-counts_posts_by_cat_tag' );
		// No cache is present, retrieve posts.
		if ( true === $force || false === $collection ) {
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
			$collection = $query->posts;
			// Cache posts_by_cat_tag array for 30 minutes.
			set_transient( 'site-counts_posts_by_cat_tag', $collection, 30 * MINUTE_IN_SECONDS );
		}
		// Initialize collection object.
		$posts_by_cat_tag = (object) [
			'count' => 0,
			'items' => [],
		];
		// Parse collection into final object.
		foreach ( $collection as $post ) {
			if ( $avoid_id === $post->post_id ) {
				continue;
			}
			$posts_by_cat_tag->items[] = $post;
			$posts_by_cat_tag->count ++;
		}
		// Return posts_by_cat_tag object.
		return $posts_by_cat_tag;
	}
}
