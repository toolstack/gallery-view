<?php

/**
 * The core plugin class.
 *
 * @since      1.0
 * @package    Gallery_View
 * @subpackage Gallery_View/includes
 * @author     GregRoss
 * @link       https://toolstack.com/gallery-view
 */

class Gallery_View
{
	private $submenu_page;
	private $user;
	private $view_types = array( 'published' => 'publish', 'scheduled' => 'future', 'drafts' => 'draft' );
	private $tags;
	private $categories;

	// Main constructor.
    public function __construct() {
    	// Add the menu item and scripts actions.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		add_filter( 'set-screen-option', [ $this, 'post_gallery_set_screen_option' ], 10, 3 );
	}

	// Add the menu subpage for the gallery.
	public function admin_menu() {
    	global $current_user;

    	$this->user = $current_user->ID;

		$this->submenu_page = add_submenu_page( 'edit.php', __( 'Gallery', 'gallery-view' ), __( 'Gallery', 'gallery-view' ), 'edit_posts', 'post_gallery', [ $this, 'display_gallery_view_page' ], 10 );

		add_action( 'load-'. $this->submenu_page, [ $this, 'post_gallery_screen_options' ] );
	}

	// Load the css/js.
	public function admin_enqueue_scripts() {
	    wp_enqueue_style( 'gv-admin-css', plugin_dir_url( GVP_PLUGIN_FILE ) . 'css/gallery-view.css' );

	    wp_enqueue_script( 'gv-admin-js', plugin_dir_url( GVP_PLUGIN_FILE ) . 'js/gallery-view.js', ['jquery'], GVP_VERSION, false );
	}

	// Set the screen options for the page.
	public function post_gallery_screen_options() {
		$screen = get_current_screen();

		// get out of here if we are not on our settings page
		if( !is_object( $screen ) || $screen->id != $this->submenu_page )
			return;

		$args = array(
			'label' => __( 'Number of items per page', 'gallery-view' ) . ':',
			'default' => 10,
			'option' => 'gv_items_per_page'
		);

		add_screen_option( 'per_page', $args );
	}

	// Store the screen options when set.
	public function post_gallery_set_screen_option( $status, $option, $value ) {
		if( 'gv_items_per_page' == $option ) {
			return $value;
		}
	}

	// Main page display function.
	public function display_gallery_view_page() {
		// Get the registered image sizes to make sure we know how big the thumbnails are.
		$image_sizes = wp_get_registered_image_subsizes();

		// Get all the user meta data, we need to do this to see if our keys exist or not.
		// While we're getting it, make it pretty by dereferencing the sub-arrays.
		$user_meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $this->user ) );

		// Update the user meta data if we have settings passed in on the page request.
		$user_meta = $this->update_user_meta( $user_meta );

		// Get the posts per page the user has set on the main posts page.
		if( array_key_exists( 'gv_items_per_page', $user_meta ) ) { $items_per_page = $user_meta['gv_items_per_page']; } else { $items_per_page = 10; update_user_meta( $this->user, 'gv_items_per_page', 10 ); }

		// Get the show date setting for the user.
		if( array_key_exists( 'gv_show_date', $user_meta ) ) { $show_date = $user_meta['gv_show_date']; } else { $show_date = '1'; update_user_meta( $this->user, 'gv_show_date', '1' ); }

		// Get the show title setting for the user.
		if( array_key_exists( 'gv_show_title', $user_meta ) ) { $show_title = $user_meta['gv_show_title']; } else { $show_title = '1'; update_user_meta( $this->user, 'gv_show_title', '1' ); }

		// Make a sane default.
		if( $items_per_page < 1 ) { $items_per_page = 10; }

		// Get the available categories.
		$this->categories = get_categories();

		// Get the available tags.
		$this->tags = get_tags( array( 'orderby' => 'name' ) );

		// Setup an array with the view types and translations.
		$views = array( 'all' => __( 'All', 'gallery-view' ), 'publish' => __( 'Published', 'gallery-view' ), 'future' => __( 'Scheduled', 'gallery-view' ), 'draft' => __( 'Drafts', 'gallery-view' ) );

		// Grab the currently set values from the page parameters.
		list( $current_view, $selected_cat_id, $selected_tag_id, $selected_order, $current_page, $selected_date ) = $this->get_page_parameters();

		// Get the list of months that have posts in them.
		$dates = $this->get_date_range( $current_view );

		// Make sure we have a valid date selected, if not reset it.
		if( ! array_key_exists( $selected_date, $dates ) ) { $selected_date = ''; }

		// Time to setup the date query.
		$date_query = '';
		if( $selected_date ) {
			$date_query = array( 'year' => substr( $selected_date, 0, 4 ), 'month' => substr( $selected_date, 4, 2 ) );
		}

		// And the rest of the post query to boot.
		$posts_query = array(
								'posts_per_page' => $items_per_page,
								'cat' => $selected_cat_id,
								'date_query' => $date_query,
								'order' => $selected_order,
								'orderby' => '',
								'paged' => $current_page,
								'tag' => $selected_tag_id,
								'post_status' => $current_view == 'all' ? '' : $current_view,
		);

		// Use WP_Query to get the posts we want.
		$results = new WP_Query( $posts_query );

		// If for some reason we are on a page with no results, and it's not the first page, go back to the first page
		// and rerun the query.  If we still don't have any results, well, that's just fine.
		if( $results->found_posts == 0 && $current_page > 1 ) {
			$posts_query['paged'] = 1;
			$current_page = 1;

			$results = new WP_Query( $posts_query );
		}

		// Use a nicer variable to hold the posts.
		$posts = $results->posts;

		// Set the number of results were actually found as the post count, instead of just what was returned in the current paged results.
		$post_count = $results->found_posts;

		// Time to start outputting some html.
		echo '<div class="wrap">' . PHP_EOL;

		echo '<h1 class="wp-heading-inline">' . __( 'Gallery', 'gallery-view' ) . '</h1>' . PHP_EOL;
		echo '<a href="' . admin_url( 'post-new.php' ) . '" class="page-title-action">' . __( 'Add New', 'gallery-view' ) . '</a>' . PHP_EOL;

		echo '<hr class="wp-header-end">' . PHP_EOL;

		echo '<ul class="subsubsub">' . PHP_EOL;

		// Output the list of views.
		$separator = ' |';
		$last_view = end( $views );
		$item_count = wp_count_posts();
		$view_counts = array(
								'all' => $item_count->publish + $item_count->future + $item_count->draft,
								'publish' => $item_count->publish,
								'future' => $item_count->future,
								'draft' => $item_count->draft,
							);

		foreach( $views as $name => $view ) {
			if( $last_view == $view ) { $separator = ''; }

			$view_selected = '';
			if( $current_view == '' && $name == 'all' ) { $view_selected = 'current'; }
			if( $current_view == $name ) { $view_selected = 'current'; }

			echo '<li class="' . esc_attr( $name ) . '"><a class="' . esc_html( $view_selected ) . '" href="' . $this->build_admin_url( $selected_date, $selected_cat_id, $selected_tag_id, $selected_order, $current_page, $name ) . '">' . esc_html( $view ) . '<span class="count">(' . esc_html( $view_counts[$name] ). ')</span></a>' . $separator . '</li>' . PHP_EOL;
		}

		echo '</ul>' . PHP_EOL;

		echo '<div class="gv-switch-box">' . PHP_EOL;

		if( $show_date == '1' ) { $selected = ' checked'; } else { $selected = ''; }

		echo '<span class="gv-switch-text">' . __( 'Show date', 'gallery-view' ) . '</span>' . PHP_EOL;
		echo '<label class="gv-switch">' . PHP_EOL;
		echo '<input type="checkbox" id="gv_show_date_switch"' . $selected . '>' . PHP_EOL;
		echo '<span class="gv-slider gv-round"></span>' . PHP_EOL;
		echo '</label>' . PHP_EOL;

		if( $show_title == '1' ) { $selected = ' checked'; } else { $selected = ''; }

		echo '<span class="gv-switch-text">' . __( 'Show title', 'gallery-view' ) . '</span>' . PHP_EOL;
		echo '<label class="gv-switch">' . PHP_EOL;
		echo '<input type="checkbox" id="gv_show_title_switch"' . $selected . '>' . PHP_EOL;
		echo '<span class="gv-slider gv-round"></span>' . PHP_EOL;
		echo '</label>' . PHP_EOL;

		echo '</div>' . PHP_EOL;

		echo '<div class="clear"></div>' . PHP_EOL;

		// Open the form for the filters.
		echo '<form id="posts-filter" method="get">' . PHP_EOL;

		// Set some hidden values so the form submission works correctly.
		echo '<input type="hidden" name="page" value="post_gallery">' . PHP_EOL;
		echo '<input type="hidden" name="paged" value="' . esc_attr( $current_page ) . '">' . PHP_EOL;

		// If we're not on the all page, save the current view when we apply the filter.
		if( $current_view != '' && $current_view != 'all' ) {
			echo '<input type="hidden" name="post_status" value="' . esc_attr( $current_view ) . '">' . PHP_EOL;
		}

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">' . PHP_EOL;
		echo '<label for="filter-by-date" class="screen-reader-text">' . __( 'Filter by date', 'gallery-view' ) . '</label>' . PHP_EOL;

		// Output the date selector.
		echo '<select name="m" id="filter-by-date">' . PHP_EOL;

		echo '<option value="0">' . __( 'All dates', 'gallery-view' ) . '</option>' . PHP_EOL;

		foreach( $dates as $name => $date ) {
			echo '<option value="' . esc_attr( $name ) . '"' . selected( $name, $selected_date, false) . '>' . esc_html( $date ) . '</option>' . PHP_EOL;
		}

		echo '</select>' . PHP_EOL;

		// Output the category selector.
		echo '<label class="screen-reader-text" for="cat">' . __( 'Filter by category', 'gallery-view' ) . '</label>' . PHP_EOL;
		echo '<select name="cat" id="cat" class="postform">' . PHP_EOL;

		echo '<option value="0"' . selected( 0, $selected_cat_id, false ) . '>' . __( 'All Categories', 'gallery-view' ) . '</option>' . PHP_EOL;

		foreach( $this->categories as $category ) {
			if( $category->count > 0 ) {
				echo "\t\t" . '<option value="' . esc_attr( $category->term_id ) . '"' . selected( $selected_cat_id, $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>' . PHP_EOL;
			}
		}

		echo '</select>' . PHP_EOL;

		// Output the tag selector.
		echo '<label for="tag" class="screen-reader-text">' . __( 'Filter by tag', 'gallery-view' ) . '</label>' . PHP_EOL;
		echo '<select name="tag" id="tag">' . PHP_EOL;

		echo '<option value=""' . selected( '', $selected_tag_id, false ) . '>' . __( 'All Tags', 'gallery-view' ) . '</option>' . PHP_EOL;

		foreach( $this->tags as $tag ) {
			if( $tag->count > 0 ) {
				echo "\t\t" . '<option value="' . esc_attr( $tag->slug ) . '"' . selected( $selected_tag_id, $tag->slug, false ) . '>' . esc_html( $tag->name ) . '</option>' . PHP_EOL;
			}
		}

		echo '</select>' . PHP_EOL;

		// Output the display order selector.
		echo '<label for="order" class="screen-reader-text">' . __( 'Display order', 'gallery-view' ) . '</label>' . PHP_EOL;
		echo '<select name="order" id="order">' . PHP_EOL;

		echo '<option value="ASC"' . selected( 'ASC', $selected_order, false ) . '>' . __( 'Ascending', 'gallery-view' ) . '</option>' . PHP_EOL;
		echo '<option value="DESC"' . selected( 'DESC', $selected_order, false ) . '>' . __( 'Descending', 'gallery-view' ) . '</option>' . PHP_EOL;

		echo '</select>' . PHP_EOL;

		// Output the Filter button.
		echo '<input type="submit" name="filter_action" id="gallery-query-submit" class="button" value="' . __( 'Filter', 'gallery-view' ) . '">' . PHP_EOL;

		echo '</div>' . PHP_EOL;

		// Close the form.
		echo '</form>' . PHP_EOL;

		// Sanity check our values.
		if( $items_per_page < 1 ) { $items_per_page = 1; }
		if( $current_view == '' ) { $current_view = 'all'; }

		// Figure out how many pages we're going to have.
		$total_pages = ceil( $post_count / $items_per_page );

		// Setup the default 'disabled' first/prev/next/last buttons for the page selector.
		$first_page = '<span class="pagination-links"><span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
		$prev_page = '<span class="pagination-links"><span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
		$next_page = '<span class="pagination-links"><span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
		$last_page = '<span class="pagination-links"><span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';

		// Make sure we're not past the last page or before the first.
		if( $current_page > $total_pages ) { $current_page = $total_pages; }
		if( $current_page < 0 ) { $current_page = 1; }

		// If we have previous pages, make the buttons active.
		if( $current_page != 1 ) {
			$first_page = '<a class="first-page button" href="' . $this->build_admin_url( $selected_date, $selected_cat_id, $selected_tag_id, $selected_order, 1, $current_view ) . '"><span class="screen-reader-text">' . __( 'First page', 'gallery-view' ) . '</span><span aria-hidden="true">«</span></a>';
			$prev_page = '<a class="prev-page button" href="' . $this->build_admin_url( $selected_date, $selected_cat_id, $selected_tag_id, $selected_order, $current_page - 1, $current_view ) . '"><span class="screen-reader-text">' . __( 'Previous page', 'gallery-view' ) . '</span><span aria-hidden="true">‹</span></a>';
		}

		// If we have next pages, make the buttons active.
		if( $current_page != $total_pages ) {
			$last_page = '<a class="last-page button" href="' . $this->build_admin_url( $selected_date, $selected_cat_id, $selected_tag_id, $selected_order, $total_pages, $current_view ) . '"><span class="screen-reader-text">' . __( 'Last page', 'gallery-view' ) . '</span><span aria-hidden="true">»</span></a>';
			$next_page = '<a class="next-page button" href="' . $this->build_admin_url( $selected_date, $selected_cat_id, $selected_tag_id, $selected_order, $current_page + 1, $current_view ) . '"><span class="screen-reader-text">' . __( 'Next page', 'gallery-view' ) . '</span><span aria-hidden="true">›</span></a>';
		}

		// Now output the page selector.
		echo '<div class="tablenav-pages">' . PHP_EOL;
		echo '<span class="displaying-num">' . esc_html( $post_count ) . ' items</span>' . PHP_EOL;
		echo '<span class="pagination-links">' . PHP_EOL;
		echo $first_page . PHP_EOL;
		echo $prev_page . PHP_EOL;
		echo '<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current Page</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $current_page . '" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text"> of <span class="total-pages">' . $total_pages . '</span></span></span>' . PHP_EOL;
		echo $next_page . PHP_EOL;
		echo $last_page . PHP_EOL;
		echo '</span>' . PHP_EOL;
		echo '</div>' . PHP_EOL;

		echo '</div>' . PHP_EOL;

		echo '<div class="clear"></div>' . PHP_EOL;

		// Time to display the posts.
		foreach( $posts as $post ) {
			// Get the thumbnail url for the post.
			$thumbnail = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );

			// Get the edit page url for the post.
			$edit_post_url = get_edit_post_link( $post->ID );

			// Setup the outer div.
			echo '<div class="gv-gallery-box">' . PHP_EOL;

			// Depending on if the post has a featured image or not, we're going to have different content for the div.
			$item_content = '';

			if( $thumbnail ) {
				$item_content = '<img src="' . $thumbnail . '">';
			} else {
				$item_content = __( 'No Featured Image', 'gallery-view' );
			}

			// Output the image/content.
			echo '<a href="' . esc_attr( $edit_post_url ) . '"><div class="gv-checkered" style="font-size: 1.5em; line-height: ' . $image_sizes['thumbnail']['height'] / 2 . 'px; text-align: center; width: ' . $image_sizes['thumbnail']['width'] . 'px; height: ' . $image_sizes['thumbnail']['height'] . 'px;">' . $item_content . '</div></a>' . PHP_EOL;

			// Make a pleasant looking date string.
			$date_string = wp_date( get_option( 'date_format' ), strtotime( $post->post_date ) );
			$post_title = $post->post_title ? $post->post_title : __( '[No Title]', 'gallery-view' );

			// Output the date and the post title.
			if( $show_date || $show_title ) {
				echo '<a href="' . esc_attr( $edit_post_url ) . '"><div class="gv-info-box">' . PHP_EOL;
				if( $show_date ) { echo '' . $date_string . ''; }
				if( $show_title ) { echo '<p class="gv-gallery-title" style="width: ' . $image_sizes['thumbnail']['width'] - 10 . 'px;">' . esc_html( $post_title ) . '</p>'; }
				echo '</div></a>' . PHP_EOL;
			}

			echo '</div>' . PHP_EOL;
		}

		echo '<div class="clear"></div>' . PHP_EOL;

		// Now output the page selector at the bottom of the list.
		echo '<div class="tablenav bottom">';
		echo '<div class="tablenav-pages">' . PHP_EOL;
		echo '<span class="displaying-num">' . esc_html( $post_count ) . ' items</span>' . PHP_EOL;
		echo '<span class="pagination-links">' . PHP_EOL;
		echo $first_page . PHP_EOL;
		echo $prev_page . PHP_EOL;
		echo '<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current Page</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $current_page . '" size="1" aria-describedby="table-paging"><span class="tablenav-paging-text"> of <span class="total-pages">' . $total_pages . '</span></span></span>' . PHP_EOL;
		echo $next_page . PHP_EOL;
		echo $last_page . PHP_EOL;
		echo '</span>' . PHP_EOL;
		echo '</div>' . PHP_EOL;
		echo '</div>' . PHP_EOL;

		echo '</div>' . PHP_EOL;
	}

	// This function creates an admin url to our page with the query tags added as required.
	private function build_admin_url( $dates, $categories, $tags, $order, $paged, $view ) {
		// Setup the base url.
		$url = admin_url( 'edit.php?page=post_gallery' );

		// Check for each value and if we need to add it.
		if( $dates != '' ) { $url .= '&m=' . $dates; }
		if( $categories != '' ) { $url .= '&cat=' . $categories; }
		if( $tags != '' ) { $url .= '&tag=' . $tags; }
		if( $order != '' && $order != 'DESC' ) { $url .= '&order=' . $order; }
		if( $paged != '' && $paged > 1 ) { $url .= '&paged=' . $paged; }
		if( $view != '' && $view != 'all' ) { $url .= '&post_status=' . $view; }

		// Make sure to escape the url as an attribute before returning it.
		return esc_attr( $url );
	}

	// This function gets all the query parameters that have been set for this page load.
	private function get_page_parameters() {
		$current_view = '';

		// Get the currently selected view if there is one.
		if( array_key_exists( 'post_status', $_REQUEST ) && $_REQUEST['post_status'] !== '' && $_REQUEST['post_status'] != '' ) {
			if( $find_view = array_search( $_REQUEST['post_status'], $this->view_types, true ) ) {
				$current_view = $this->view_types[$find_view];
			}
		}

		$selected_cat_id = '';

		// Get the currently selected tag if there is one.
		if( array_key_exists( 'cat', $_REQUEST ) && $_REQUEST['cat'] !== '' && $_REQUEST['cat'] != '' )
			{
			// Make sure its a valid tag.
			foreach( $this->categories as $category )
				{
				if( $category->term_id == $_REQUEST['cat'] )
					$selected_cat_id = $category->term_id;
				}
			}

		$selected_tag_id = '';

		// Get the currently selected tag if there is one.
		if( array_key_exists( 'tag', $_REQUEST ) && $_REQUEST['tag'] !== '' && $_REQUEST['tag'] != '' )
			{
			// Make sure its a valid tag.
			foreach( $this->tags as $tag )
				{
				if( $tag->slug === $_REQUEST['tag'] )
					$selected_tag_id = $tag->slug;
				}
			}

		$selected_order = 'DESC';

		// Get the currently selected order if there is one.
		if( array_key_exists( 'order', $_REQUEST ) && $_REQUEST['order'] !== '' && $_REQUEST['order'] != '' ) {
			if( $_REQUEST['order'] == 'ASC' ) { $selected_order = 'ASC'; }
		}

		$current_page = '';

		// Get the currently selected page if there is one.
		if( array_key_exists( 'paged', $_REQUEST ) && $_REQUEST['paged'] !== '' && $_REQUEST['paged'] != '' ) {
			$current_page = intval( $_REQUEST['paged'] );
		}

		$selected_date = '';

		// Get the currently selected page if there is one.
		if( array_key_exists( 'm', $_REQUEST ) && $_REQUEST['m'] !== '' && $_REQUEST['m'] != '' ) {
			$year = (int) substr( $_REQUEST['m'], 0, 4 );
			$month = (int) substr( $_REQUEST['m'], 4, 2 );
			$selected_date = wp_date( 'Ym', strtotime( $year . '-' . $month . '-28') );
		}

		return array( $current_view, $selected_cat_id, $selected_tag_id, $selected_order, $current_page, $selected_date );
	}

	// This function calculates all the months that have posts in them based upon the current view...
	// through some SQL magic.
	private function get_date_range( $current_view ) {
	   	// Grab the global wpdb instance.
	   	global $wpdb;

	   	// Setup the posts table name.
	   	$table_name_posts = $wpdb->prefix . 'posts';

	   	// Create a where clause based upon the current view.
	   	switch( $current_view ) {
	   		case 'publish':
	   			$where = "post_status = 'publish'";

	   			break;
	   		case 'draft':
	   			$where = "post_status = 'draft'";

	   			break;
	   		case 'future':
	   			$where = "post_status = 'future'";

	   			break;
	   		case '':
	   		case 'all':
	   		default:
	   			$where = "(post_status = 'publish' OR post_status = 'draft' OR post_status = 'future')";

	   			break;
	   	}

	   	// Build the SQL query string, the first seven characters of the post date will always be "YYYY-MM".
	   	// Using DISTINCT ensure we only get a single result.
	   	$sql  = 'SELECT DISTINCT MID(post_date, 1, 7) AS post_date';
	   	$sql .= ' FROM ' . $table_name_posts;
	   	$sql .= ' WHERE ' . $where;
	   	$sql .= ' ORDER BY post_date DESC';

	    // Run the query.
	    $results = $wpdb->get_results( $sql, ARRAY_N );

	    // Now we have to loop through the results to create a more human friendly version of them.
	    $dates = array();

	    foreach( $results as $result ) {
			$dates[str_replace( '-', '', $result[0])] = wp_date( 'F Y', strtotime( $result[0] . '-28' ) );
	    }

	    // Return the dates.
	 	return $dates;
	}

	private function update_user_meta( $user_meta ) {
		// Check to see if we have a new show data setting.
		if( array_key_exists( 'gv_show_date', $_REQUEST ) && $_REQUEST['gv_show_date'] != $user_meta['gv_show_date'] ) {
			if( $_REQUEST['gv_show_date'] == '1' ) {
				update_user_meta( $this->user, 'gv_show_date', '1' );
				$user_meta['gv_show_date'] = '1';
			} else {
				update_user_meta( $this->user, 'gv_show_date', '' );
				$user_meta['gv_show_date'] = '';
			}
		}

		// Check to see if we have a new show data setting.
		if( array_key_exists( 'gv_show_title', $_REQUEST ) && $_REQUEST['gv_show_title'] != $user_meta['gv_show_title'] ) {
			if( $_REQUEST['gv_show_title'] == '1' ) {
				update_user_meta( $this->user, 'gv_show_title', '1' );
				$user_meta['gv_show_title'] = '1';
			} else {
				update_user_meta( $this->user, 'gv_show_title', '' );
				$user_meta['gv_show_title'] = '';
			}
		}

		return $user_meta;
	}

// End of Class
}