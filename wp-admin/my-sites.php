<?php
/**
 * My Sites dashboard.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

require_once( dirname( __FILE__ ) . '/admin.php' );

if ( !is_multisite() )
	wp_die( __( 'Multisite support is not enabled.' ) );

if ( ! current_user_can('read') )
	wp_die( __( 'Sorry, you are not allowed to access this page.' ) );

$action = isset( $_POST['action'] ) ? $_POST['action'] : 'splash';

$blogs = get_blogs_of_user( $current_user->ID );

$updated = false;
if ( 'updateblogsettings' == $action && ( isset( $_POST['primary_blog'] ) || isset( $_POST['primary_blog_name'] ) ) ) {
	check_admin_referer( 'update-my-sites' );

	$blog = get_site( (int) $_POST['primary_blog'] );
	if ( $blog && isset( $blog->domain ) ) {
		update_user_option( $current_user->ID, 'primary_blog', $blog->blog_id, true );
		$updated = true;
	} else {
		wp_die( __( 'The primary site you chose does not exist.' ) );
	}
}

$title = __( 'My Sites' );
$parent_file = 'index.php';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' =>
		'<p>' . __('This screen shows an individual user all of their sites in this network, and also allows that user to set a primary site. They can use the links under each site to visit either the front end or the dashboard for that site.') . '</p>'
) );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="https://codex.wordpress.org/Dashboard_My_Sites_Screen">Documentation on My Sites</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/">Support Forums</a>') . '</p>'
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );

if ( $updated ) { ?>
	<div id="message" class="updated notice is-dismissible"><p><strong><?php _e( 'Settings saved.' ); ?></strong></p></div>
<?php } ?>

<div class="wrap">
<h1><?php
echo esc_html( $title );

if ( in_array( get_site_option( 'registration' ), array( 'all', 'blog' ) ) ) {
	/** This filter is documented in wp-login.php */
	$sign_up_url = apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) );
	printf( ' <a href="%s" class="page-title-action">%s</a>', esc_url( $sign_up_url ), esc_html_x( 'Add New', 'site' ) );
}
?></h1>

<?php
if ( empty( $blogs ) ) :
	echo '<p>';
	_e( 'You must be a member of at least one site to use this page.' );
	echo '</p>';
else :
?>
<form id="myblogs" method="post">
	<?php
	choose_primary_blog();
	submit_button();
	/**
	 * Fires before the sites table on the My Sites screen.
	 *
	 * @since 3.0.0
	 */
	do_action( 'myblogs_allblogs_options' );

?>
	<input type="hidden" name="action" value="updateblogsettings" />
	<?php wp_nonce_field( 'update-my-sites' ); ?>

<div class="tablenav">
<?php
	// Pagination start
	$per_page = 10;
	$total_blogs = count( $blogs );

	$current_page = ( isset( $_GET['paged'] ) && intval( $_GET['paged'] ) > 0 ) ? intval( $_GET['paged'] ) : 1;
	$page_start = ( ( $current_page - 1 ) * $per_page );
	$oblogs = $blogs;

	$blogs = array_slice( $blogs, $page_start, $per_page );
	// Pagination end

	$page_links = paginate_links( array(
		'base' => add_query_arg( 'paged', '%#%' ),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => ceil($total_blogs / $per_page),
		'current' => $current_page
	));

	if ( $page_links )
		echo "<div class='tablenav-pages'>$page_links</div>";

	do_action( 'myblogs_bulk_actions' );

	?>
	<br clear="all" />
</div>
	<table class="widefat fixed">
	<?php
	/**
	 * Enable the Global Settings section on the My Sites screen.
	 *
	 * By default, the Global Settings section is hidden. Passing a non-empty
	 * string to this filter will enable the section, and allow new settings
	 * to be added, either globally or for specific sites.
	 *
	 * @since MU
	 *
	 * @param string $settings_html The settings HTML markup. Default empty.
	 * @param object $context       Context of the setting (global or site-specific). Default 'global'.
	 */
	$settings_html = apply_filters( 'myblogs_options', '', 'global' );
	if ( $settings_html != '' ) {
		echo '<tr><td valign="top"><h3>' . __( 'Global Settings' ) . '</h3></td><td>';
		echo $settings_html;
		echo '</td></tr>';
	}
	reset( $blogs );
	$num = count( $blogs );
	$cols = 1;
	if ( $num >= 20 )
		$cols = 4;
	elseif ( $num >= 10 )
		$cols = 2;
	$num_rows = ceil( $num / $cols );
	$split = 0;
	for ( $i = 1; $i <= $num_rows; $i++ ) {
		$rows[] = array_slice( $blogs, $split, $cols );
		$split = $split + $cols;
	}

	$c = '';
	foreach ( $rows as $row ) {
		$c = $c == 'alternate' ? '' : 'alternate';
		echo "<tr class='$c'>";
		$i = 0;
		foreach ( $row as $user_blog ) {
			$s = $i == 3 ? '' : 'border-right: 1px solid #ccc;';
			echo "<td valign='top' style='$s'>";
		echo "<h3>{$user_blog->blogname}</h3>";
		/**
		 * Filters the row links displayed for each site on the My Sites screen.
		 *
		 * @since MU
		 *
		 * @param string $string    The HTML site link markup.
		 * @param object $user_blog An object containing the site data.
		 */
			echo "<p>" . apply_filters( 'myblogs_blog_actions', "<a href='" . esc_url( get_home_url( $user_blog->userblog_id ) ). "'>" . __( 'Visit' ) . "</a> | <a href='" . esc_url( get_admin_url( $user_blog->userblog_id ) ) . "'>" . __( 'Dashboard' ) . "</a>", $user_blog ) . "</p>";
		/** This filter is documented in wp-admin/my-sites.php */
		echo apply_filters( 'myblogs_options', '', $user_blog );
			echo "</td>";
			$i++;
		}
		echo "</tr>";
	}?>
	</table>
	<div class="tablenav">
	<?php
	if ( $page_links )
		echo "<div class='tablenav-pages'>$page_links</div>";
	?>
	</div>
	<input type="hidden" name="action" value="updateblogsettings" />
	<?php wp_nonce_field( 'update-my-sites' ); ?>
	</form>
<?php endif; ?>
	</div>
<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );
