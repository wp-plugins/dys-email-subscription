<?php 
/*
Plugin Name: DYS Email Subscription
Plugin URI: https://github.com/dpiquet/dys-email-subscription
Description: A simple WordPress plugin for e-mailing subscription list.
Author: Sebastian Orellana, Damien PIQUET
Author URI: https://github.com/dpiquet/
Version: 1.5
Text Domain: dys-email-subscription
Domain Path: /lang
*/

/*
TODO:
	import csv (useful ?)
	fonction recherche (admin side) ?
	use wordpress nonce to avoid mass database attack
	save registration as unix epoch so we can print it the language we want
	check security (database injection)
	protect form with JS ?
	remove form when user has subscribe

*/


/*---------------------------------------------------
register settings
----------------------------------------------------*/
load_plugin_textdomain( 'dys-email-subscription', false, basename( dirname( __FILE__ ) ) . '/lang' );
    
/**
  *  Create the widget
  */

class DYSemailingList extends WP_Widget {
	function __construct() {
		parent::__construct(
			'dys_emailing_list_widget', 
			__( 'Emailing List', 'dys-email-subscription' ),
			array( 'description' => __( 'email collector widget', 'dys-email-subscription' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = '';
		if( isset( $instance['title'] ) ) { $title = $instance['title']; }
		else { $title = __( 'Subscribe to our News !', 'dys-email-subscription' ); }
		
		emailing_form( $title );
	}

	public function form( $instance ) {
		if ( isset( $instance['title'] ) ) {
			$titleValue = $instance['title'];
		} else {
			$titleValue = __( 'Subscribe to our News !', 'dys-email-subscription' );
		}

?>
		<p>
		  <label for="<?php echo $this->get_field_id( 'title' ); ?>">
			<?php echo _e( 'Title:', 'dys-email-subscription' ); ?>
		  </label>
		  <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $titleValue ); ?>">
		</p>

<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}
}

/** register the widget, maybe we should not use anon func ? */
add_action( 'widgets_init', function() { register_widget( 'DYSemailingList' ); } );

function theme_settings_init() {
    global $plugin_page;

    if ( $plugin_page == 'emailing_list' ) {

	if ( isset( $_POST['export_subscribers'] ) ) {

	    $today = date("Y-m-d");
            
            global $wpdb;
            $result = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."emailinglist GROUP BY email" ); 

            if ( $_POST['export_subscribers'] == 'xls' ) {

	        header( "Content-Type: application/vnd.ms-excel" );
	        header( "Expires: 0" );
	        header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
	        header( "content-disposition: attachment;filename=emailing-$today.xls" );

	        echo "<table>
	        <thead>    
	         <tr>
	         <th>" . __( 'Email', 'dys-email-subscription' ) . "</th>
	         <th>" . __( 'Registration date', 'dys-email-subscription' ) . "</th>
	         </tr>
	        </thead>";

	        foreach( $result as $r ) {
		    echo "<tbody><tr>";
		    echo "<td>".$r->email."</td>";
		    echo "<td>".$r->time."</td>";
		    echo "</tr></tbody>";
	        }
	        echo "</table>";

	        exit;
            }
            elseif ( $_POST['export_subscribers'] == 'csv' ) {
                 
	        header( "Content-Type: text/csv" );
	        header( "Expires: 0" );
	        header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
	        header( "content-disposition: attachment;filename=emailing-$today.csv" );

		echo __( 'Email', 'dys-email-subscription' ) . ';' . __( 'Registration date', 'dys-email-subscription' ) . "\n";

	        foreach($result as $r) {
		    echo $r->email . ';' . $r->time . "\n";
	        }

	        exit;
            }
	    else { exit; }
        }
    }
    
    register_setting( 'theme_settings_page', 'theme_settings_page' );
}



/*---------------------------------------------------
add settings page to menu
----------------------------------------------------*/
function add_settings_page() {
    add_menu_page( __( 'Subscribers', 'dys-email-subscription' ), __( 'Subscribers', 'dys-email-subscription' ), 'edit_posts',  'emailing_list', 'emailing');
}
 
/*---------------------------------------------------
add actions
----------------------------------------------------*/
add_action( 'admin_init', 'theme_settings_init' );
add_action( 'admin_menu', 'add_settings_page' );

global $emailing_db_version;
$emailing_db_version = "1.0";

function emailing_install() {
   global $wpdb;
   global $emailing_db_version;

   $table_name = $wpdb->prefix . "emailinglist";
      
   $sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  email varchar(89) NOT NULL,
  UNIQUE KEY id (id)
    );";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
 
   add_option( "emailing_db_version", $emailing_db_version );
   add_option( "emailing_protect_form_with_js", true );
}

function emailing_install_data( $emaling ) {
   global $wpdb;

   $table_name = $wpdb->prefix . 'emailinglist';
   return $wpdb->insert( $table_name, array( 'time' => current_time( 'mysql' ), 'email' => $emaling ) );
}

function remove_subscriber( $email ) {
    global $wpdb;

    $tableName = $wpdb->prefix . 'emailinglist';
    return $wpdb->delete( $tableName, Array( 'email' => $email ), Array( '%s' ) );
}

function emailing_form( $title ) {
?>
<aside id="dys-email-subscription" class="widget widget_dys-email-subscription">
  <h3 class="widget-title"> <?php echo $title; ?> </h3>
  <form name="emailing" method="post"  class="clear">
    <input name="email" id="email" type="email" class="text" placeholder="<?php _e( 'Email Address', 'dys-email-subscription' ) ?>"/>
    <br>
    <input type="radio" name="subscriber_action" value="subscribe" checked>
      <span id="mailing_form_subscribe_text"><?php _e( 'Subscribe', 'dys-email-subscription' ); ?></span>
      <br>
    <input type="radio" name="subscriber_action" value="unsubscribe">
      <span id="mailing_form_unsubscribe_text"><?php _e( 'Unsubscribe', 'dys-email-subscription' ); ?></span>
      <br>
    <input type="submit" name="emailing-send" class="button" value="<?php _e( 'Subscribe', 'dys-email-subscription' ) ?>"/>
  </form>
<?php

    if ( isset( $_POST['emailing-send'] ) ) {
         if ( filter_var( $_POST['email'], FILTER_VALIDATE_EMAIL ) ) {
	     if ( $_POST['subscriber_action'] == 'unsubscribe' ) {
                 $st = remove_subscriber( $_POST['email'] );
		 if ( $st === false ) {
		     echo '<span class="mail-failure">' .
			__( 'An error occured, please try later or contact webmaster', 'dys-email-subscription' ) .
			'</span>';
		 }
		 else {
		     echo '<span class="mail-success">' .
			__( 'Your email address was successfully deleted from our database', 'dys-email-subscription' ) .
			'</span>';
		 }
             }
             elseif ( $_POST['subscriber_action'] == 'subscribe' ) {
		$st = emailing_install_data( $_POST['email'] );
		if ( $st === false ) {
		    echo '<span class="mail-failure">' .
			__( 'An error occured, please try later or contact webmaster', 'dys-email-subscription' ) .
			'</span>';
		}
		else {
		    echo '<span class="mail-success">' .
			__( 'Your email address was subscribed successfully', 'dys-email-subscription' ) .
			'</span>';
		}
	     }
             
         }else {
             echo '<span class="mail-error">' .
		__( 'Email address seems invalid.', 'dys-email-subscription' ) .
		'</span>';
         } 
    }
?>
</aside>
<?php
}
register_activation_hook( __FILE__, 'emailing_install' );

class pagination {
    /**
     *  Script Name: WP Style Pagination Class
     *  Created From: *Digg Style Paginator Class
     *  Script URI: http://www.intechgrity.com/?p=794
     *  Original Script URI: http://www.mis-algoritmos.com/2007/05/27/digg-style-pagination-class/
     *  Description: Class in PHP that allows to use a pagination like WP in your WP Plugins
     *  Script Version: 1.0.0
     *
     *  Author: Swashata Ghosh <swashata4u@gmail.com
     *  Author URI: http://www.intechgrity.com/
     *  Original Author: Victor De la Rocha
     */
 
    /* Default values */
 
    var $total_pages = -1; //items
    var $limit = null;
    var $target = "";
    var $page = 1;
    var $adjacents = 2;
    var $showCounter = false;
    var $className = "pagination-links";
    var $parameterName = "p";
 
    /* Buttons next and previous */
    var $nextT = "Next";
    var $nextI = "&#187;"; //&#9658;
    var $prevT = "Previous";
    var $prevI = "&#171;"; //&#9668;
 
    /*     * ** */
    var $calculate = false;
 
    #Total items
 
    function items($value) {
        $this->total_pages = (int) $value;
    }
 
    #how many items to show per page
 
    function limit($value) {
        $this->limit = (int) $value;
    }
 
    #Page to sent the page value
 
    function target($value) {
        $this->target = $value;
    }
 
    #Current page
 
    function currentPage($value) {
        $this->page = (int) $value;
    }
 
    #How many adjacent pages should be shown on each side of the current page?
 
    function adjacents($value) {
        $this->adjacents = (int) $value;
    }
 
    #show counter?
 
    function showCounter($value="") {
        $this->showCounter = ($value === true) ? true : false;
    }
 
    #to change the class name of the pagination div
 
    function changeClass($value="") {
        $this->className = $value;
    }
 
    function nextLabel($value) {
        $this->nextT = $value;
    }
 
    function nextIcon($value) {
        $this->nextI = $value;
    }
 
    function prevLabel($value) {
        $this->prevT = $value;
    }
 
    function prevIcon($value) {
        $this->prevI = $value;
    }
 
    #to change the class name of the pagination div
 
    function parameterName($value="") {
        $this->parameterName = $value;
    }
 
    var $pagination;
 
    function pagination() {
 
    }
 
    function show() {
        if (!$this->calculate)
            if ($this->calculate())
                echo "<span class=\"$this->className\">$this->pagination</span>\n";
    }
 
    function getOutput() {
        if (!$this->calculate)
            if ($this->calculate())
                return "<span class=\"$this->className\">$this->pagination</span>\n";
    }
 
    function get_pagenum_link($id) {
        if (strpos($this->target, '?') === false)
            return "$this->target?$this->parameterName=$id";
        else
            return "$this->target&$this->parameterName=$id";
    }
 
    function calculate() {
        $this->pagination = "";
        $this->calculate == true;
        $error = false;
 
        if ($this->total_pages < 0) {
            echo "It is necessary to specify the <strong>number of pages</strong> (\$class->items(1000))<br />";
            $error = true;
        }
        if ($this->limit == null) {
            echo "It is necessary to specify the <strong>limit of items</strong> to show per page (\$class->limit(10))<br />";
            $error = true;
        }
        if ($error)
            return false;
 
        $n = trim($this->nextT . ' ' . $this->nextI);
        $p = trim($this->prevI . ' ' . $this->prevT);
 
        /* Setup vars for query. */
        if ($this->page)
            $start = ($this->page - 1) * $this->limit;             //first item to display on this page
        else
            $start = 0;                                //if no page var is given, set start to 0
 
        /* Setup page vars for display. */
        $prev = $this->page - 1;                            //previous page is page - 1
        $next = $this->page + 1;                            //next page is page + 1
        $lastpage = ceil($this->total_pages / $this->limit);        //lastpage is = total pages / items per page, rounded up.
        $lpm1 = $lastpage - 1;                        //last page minus 1
 
        /*
          Now we apply our rules and draw the pagination object.
          We're actually saving the code to a variable in case we want to draw it more than once.
         */
 
        if ($lastpage > 1) {
            if ($this->page) {
                //anterior button
                if ($this->page > 1)
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($prev) . "\" class=\"prev\">$p</a>";
                else
                    $this->pagination .= "<a href=\"javascript: void(0)\" class=\"disabled\">$p</a>";
            }
            //pages
            if ($lastpage < 7 + ($this->adjacents * 2)) {//not enough pages to bother breaking it up
                for ($counter = 1; $counter <= $lastpage; $counter++) {
                    if ($counter == $this->page)
                        $this->pagination .= "<a href=\"javascript: void(0)\" class=\"current\">$counter</a>";
                    else
                        $this->pagination .= "<a href=\"" . $this->get_pagenum_link($counter) . "\">$counter</a>";
                }
            }
            elseif ($lastpage > 5 + ($this->adjacents * 2)) {//enough pages to hide some
                //close to beginning; only hide later pages
                if ($this->page < 1 + ($this->adjacents * 2)) {
                    for ($counter = 1; $counter < 4 + ($this->adjacents * 2); $counter++) {
                        if ($counter == $this->page)
                            $this->pagination .= "<a href=\"javascript: void(0)\" class=\"current\">$counter</a>";
                        else
                            $this->pagination .= "<a href=\"" . $this->get_pagenum_link($counter) . "\">$counter</a>";
                    }
                    $this->pagination .= "<span>...</span>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($lpm1) . "\">$lpm1</a>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($lastpage) . "\">$lastpage</a>";
                }
                //in middle; hide some front and some back
                elseif ($lastpage - ($this->adjacents * 2) > $this->page && $this->page > ($this->adjacents * 2)) {
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link(1) . "\">1</a>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link(2) . "\">2</a>";
                    $this->pagination .= "<span>...</span>";
                    for ($counter = $this->page - $this->adjacents; $counter <= $this->page + $this->adjacents; $counter++)
                        if ($counter == $this->page)
                            $this->pagination .= "<a href=\"javascript: void(0)\" class=\"current\">$counter</a>";
                        else
                            $this->pagination .= "<a href=\"" . $this->get_pagenum_link($counter) . "\">$counter</a>";
                    $this->pagination .= "<span>...</span>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($lpm1) . "\">$lpm1</a>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($lastpage) . "\">$lastpage</a>";
                }
                //close to end; only hide early pages
                else {
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link(1) . "\">1</a>";
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link(2) . "\">2</a>";
                    $this->pagination .= "<span>...</span>";
                    for ($counter = $lastpage - (2 + ($this->adjacents * 2)); $counter <= $lastpage; $counter++)
                        if ($counter == $this->page)
                            $this->pagination .= "<a href=\"javascript: void(0)\" class=\"current\">$counter</a>";
                        else
                            $this->pagination .= "<a href=\"" . $this->get_pagenum_link($counter) . "\">$counter</a>";
                }
            }
            if ($this->page) {
                //siguiente button
                if ($this->page < $counter - 1)
                    $this->pagination .= "<a href=\"" . $this->get_pagenum_link($next) . "\" class=\"next\">$n</a>";
                else
                    $this->pagination .= "<a href=\"javascript: void(0)\" class=\"disabled\">$n</a>";
            }
        }
 
        return true;
    }
 
}


/*---------------------------------------------------
Theme Emaling Suscripción
----------------------------------------------------*/
function emailing() {

    if ( isset( $_GET['action'] ) ) {
        //someone wants to del user; check capabilities before proceeding
	if( $_GET['action'] == 'delete' ) {
            if ( isset( $_GET['email'] ) ) {
                if ( filter_var( $_GET['email'], FILTER_VALIDATE_EMAIL ) ) {
                     $st = remove_subscriber( $_GET['email'] );
		     if ( $st === false ) {
		         echo '<span class="mail-failure">' .
				__( 'An error occured, please try later or contact webmaster', 'dys-email-subscription' ) .
				'</span>';
		     }
		     else {
		         echo '<span class="mail-success">' .
				__( 'Email was successfully removed from database', 'dys-email-subscription' ) .
				'</span>';
		     }
                }
		else {
			echo 'span class="mail-failure">' .
				__( 'Sorry, the address you supplied seems invalid. Please check', 'dys-email-subscription' ) .
				'</span>';
		}
            }
	}
    }

?>
         <div class="wrap">
         <div id="icon-users" class="icon32"></div>
         <h2><?php _e( 'Subscribers', 'dys-email-subscription' ) ?></h2><br/><br/>

         <form method="post" id="download_form" action="">
            <input type="hidden" name="export_subscribers" value="xls">
            <input type="submit" name="exportar_xls" class="button-primary" value="<?php _e( 'Export to Excel', 'dys-email-subscription' ); ?>" />
         </form>

         <form method="post" id="download_form" action="">
            <input type="hidden" name="export_subscribers" value="csv">
            <input type="submit" name="export_cvs" class="button-primary" value="<?php _e( 'Export to CSV', 'dys-email-subscription' ); ?>" />
         </form><br/>


         <form method="get" id="filtrar" action="">
            <label><?php _e( 'Show per page', 'dys-email-subscription' ); ?></label>
            <input type="hidden" name="page" value="emailing_list" />
            <select name="perpage">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="150">150</option>
                <option value="999999"><?php _e( 'All', 'dys-email-subscription' ); ?></option>
            </select>
            <input type="submit" class="button-primary" value="<?php _e( 'Show', 'dys-email-subscription' ); ?>" />
         </form>
         
         <br/><br/>
<?php

global $wpdb;

$pagination_count = $wpdb->get_var( 'SELECT COUNT(DISTINCT email) FROM ' . $wpdb->prefix . 'emailinglist' );
if($pagination_count > 0) {
    //get current page
    if ( isset( $_GET['p'] ) ) {
    	$this_page = ($_GET['p'] > 0)? (int) $_GET['p'] : 1;
    } else { $this_page = 1; }

    //Records per page
    if ( isset( $_GET['perpage'] ) ) {
	$per_page = (int) $_GET['perpage'];
    }
    else { $per_page = 20; }

    //Total Page
    $total_page = ceil( $pagination_count/$per_page );
 
    //initiate the pagination variable
    $pag = new pagination();
    //Set the pagination variable values
    $pag->Items( $pagination_count );
    $pag->limit( $per_page );

    if( $per_page != 20 ) {
        $pag->target( "admin.php?page=emailing_list&perpage=".$per_page );
    } else { $pag->target( "admin.php?page=emailing_list" ); }
    
    
    $pag->currentPage( $this_page );
 
    //Done with the pagination
    //Now get the entries
    //But before that a little anomaly checking
    $list_start = ($this_page - 1)*$per_page;
    if($list_start >= $pagination_count)  //Start of the list should be less than pagination count
        $list_start = ($pagination_count - $per_page);
    if($list_start < 0) //list start cannot be negative
        $list_start = 0;
    $list_end = ($this_page * $per_page) - 1;

    $search = false;
 
    //Get the data from the database
    if ( isset( $_GET['search'] ) && $_GET['search'] != '' ) {
	$search = $_GET['search'];

	sanitize_email( $search );

	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'emailinglist WHERE email LIKE "%s" GROUP BY email DESC LIMIT %d, %d', '%' . $search . '%', $list_start, $per_page ) );

    } else {
	$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'emailinglist GROUP BY email DESC LIMIT %d, %d', $list_start, $per_page ) );
    }

    echo '<form action=""><input type="hidden" name="page" value="emailing_list" />';
    echo '<input type="hidden" name="p" value="' . $this_page . '">';
    echo '<input type="text" name="search" placeholder="' . __( 'Your research' ) . '" value="' . $search . '">';
    echo '<input type="Submit" value="' . __( 'Search' ) . '">';
    echo '</form>';


    if( $result ) { 

        echo "<table class='widefat'>
        <thead>    
        <tr>
        <th>" . __( 'Email', 'dys-email-subscription' ) . "</th>
        <th>" . __( 'Registration date', 'dys-email-subscription' ) . "</th>
        <th>" . __( 'Actions', 'dys-email-subscription' ) . "</th>
        </tr>
        </thead>
        <tfoot>    
        <tr>
        <th>" . __( 'Email', 'dys-email-subscription' ) . "</th>
        <th>" . __( 'Registration date', 'dys-email-subscription' ) . "</th>
        <th>" . __( 'Actions', 'dys-email-subscription' ) . "</th>
        </tr>
        </tfoot>";

        foreach( $result as $r ) {
                echo '<tbody><tr>';
                echo '<td>' . $r->email . '</td>';
                echo '<td>' . $r->time . '</td>';
                echo '<td><form action=""><input type="hidden" name="page" value="emailing_list" />';
		echo '<input type="hidden" name="email" value="' . $r->email . '">';
		echo '<input type="hidden" name="p" value="' . $this_page . '">';
		echo '<input type="hidden" name="action" value="delete">';
		echo '<input type="Submit" value="' . __('Delete') . '">';
		echo '</td>';
                echo "</tr></tbody>";
        }
        echo "</table>";
?>

        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $pagination_count . ' ' . __( 'subscribers' ); ?> </span>
                <?php $pag->show(); ?>
            </div>
        </div> 
        </div> 

<?php
        } else {
            echo '<h3>' . __( 'Your research did not match any result', 'dys-email-subscription' ) . '</h3>';
        }
    }
    else { echo '<h3>' . __( 'No subscribers so far', 'dys-email-subscription' ) . '</h3>'; }
}

?>
