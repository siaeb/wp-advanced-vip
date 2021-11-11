<?php
	if( ! defined('ABSPATH') ) die();
	global $wpdb;

	if( ! class_exists( 'WP_List_Table' ) )
		require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		
	if( ! function_exists('get_userdata') )
		require_once( ABSPATH . 'wp-includes/pluggable.php' );



//this code is executed when single user removed
    if( isset($_GET['page']) && $_GET['page'] == 'av_vip_members' &&
    isset($_GET['action']) &&
    $_GET['action'] == 'delete' && isset($_GET['member']) ){
    $members_delete = $wpdb->query("DELETE FROM $avdb->users WHERE user_ID = " . $_GET['member']);
    if( $member_delete !== false )
        wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=1');
    else
        wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=2&av_error_message=1' );
    exit;
    }

    //Bulk Action Delete operation on top of table
    if (isset($_GET['page']) && $_GET['page'] == 'av_vip_members' &&
        isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['member']))

    {
        $html = implode(",",$_POST['member']);
        $html = "'" . str_replace(",", "','", $html) . "'";
        $members_delete = $wpdb->query( "DELETE FROM $avdb->users WHERE user_ID IN ($html);");
        if( $member_delete !== false )
            wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=1' );
        else
            wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=2&av_error_message=1' );
        exit;
    }



	//Bulk Action Delete operation Under Table
	if( isset($_GET['page']) && $_GET['page'] == 'av_vip_members' &&
        isset($_POST['action2']) && $_POST['action2'] == 'delete' ){
		$html = implode(",",$_POST['member']);
		$html = "'" . str_replace(",", "','", $html) . "'";
		$members_delete = $wpdb->query( "DELETE FROM $avdb->users WHERE user_ID IN ($html);");
		if( $members_delete !== false )
			wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=3' );
		else
			wp_redirect( admin_url().'admin.php?page=av_vip_members&av_message=4&av_error_message=1' );
		exit;
	}
	

class av_vip_members_table extends WP_List_Table {

    // order and orderby
    private $order = '';
    private $orderby = '';

    function __construct(){
		global $status, $page, $wpdb;
		$constructorArray = array(
            'singular'  => 'vipmember',
            'plural'    => 'vipmembers',
            'ajax'      => true
        );

		if (isset($_GET['screen_baseClass']) && !empty($_GET['screen_baseClass']))
        {
            $constructorArray['screen'] = $_GET['screen_baseClass'];
        }
        parent::__construct($constructorArray);

		add_action( 'admin_head', array( &$this, 'admin_header' ) );
        add_action('admin_footer', array( &$this, 'ajax_script' ));

        $this->set_order();
        $this->set_orderby();
    }


    private function set_order()
    {
        $order = 'DESC';
        if ( isset( $_GET['order'] ) AND $_GET['order'] )
            $order = $_GET['order'];
        $this->order = esc_sql( $order );
    }

    private function set_orderby()
    {
        $orderby = 'start_date';
        if ( isset( $_GET['orderby'] ) AND $_GET['orderby'] )
            $orderby = $_GET['orderby'];
        $this->orderby = esc_sql( $orderby );
    }

    function display() {
        wp_nonce_field( 'ajax-custom-list-nonce', '_ajax_custom_list_nonce' );
        echo '<input type="hidden" id="order" name="order" value="' . $this->_pagination_args['order'] . '" />';
        echo '<input type="hidden" id="orderby" name="orderby" value="' . $this->_pagination_args['orderby'] . '" />';
        parent::display();
    }

    function ajax_response() {
        check_ajax_referer( 'ajax-custom-list-nonce', '_ajax_custom_list_nonce' );
        $this->prepare_items();
        extract( $this->_args );
        extract( $this->_pagination_args, EXTR_SKIP );
        ob_start();
        if ( ! empty( $_REQUEST['no_placeholder'] ) )
            $this->display_rows();
        else
            $this->display_rows_or_placeholder();
        $rows = ob_get_clean();
        ob_start();
        $this->print_column_headers();
        $headers = ob_get_clean();
        ob_start();
        $this->pagination('top');
        $pagination_top = ob_get_clean();
        ob_start();
        $this->pagination('bottom');
        $pagination_bottom = ob_get_clean();
        $response = array( 'rows' => $rows );
        $response['pagination']['top'] = $pagination_top;
        $response['pagination']['bottom'] = $pagination_bottom;
        $response['column_headers'] = $headers;
        if ( isset( $total_items ) )
            $response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );
        if ( isset( $total_pages ) ) {
            $response['total_pages'] = $total_pages;
            $response['total_pages_i18n'] = number_format_i18n( $total_pages );
        }
        die( json_encode( $response ) );
    }

	function admin_header() {
		global $avdb,$order_query;
		$page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
		if( 'av_vip_members' != $page )
			return;
		echo '<style type="text/css">';
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '.wp-list-table .column-user_ID { width: 13%; }';
		echo '.wp-list-table .column-user_name { width: 20%; }';
		echo '.wp-list-table .column-start_date { width: 35%; }';
		echo '.wp-list-table .column-expire_date { width: 35%;}';
		echo '.wp-list-table .column-expire_date,.wp-list-table .column-start_date,.wp-list-table .column-user_ID {vertical-align: middle;}';
		echo '</style>';
		echo '<test>';
		//echo 'SELECT * FROM `'.$avdb->users.'` '.$order_query;
		echo '</test>';
	}
 
	function no_items() {
		echo 'کاربری یافت نشد.';
	}
 
	function column_default( $item, $column_name ) {
		switch( $column_name ) { 
			case 'user_ID':
			case 'user_name':
			case 'start_date':
			case 'expire_date':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}
 
	function get_sortable_columns() {
		$sortable_columns = array(
			'user_ID'  => array('user_ID',false),
			'start_date' => array('start_date',false),
			'expire_date'   => array('expire_date',false)
		);
		return $sortable_columns;
	}
 
	function get_columns(){
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'user_ID' => 'آی دی کاربر',
			'user_name' => 'نام کاربر',
			'start_date'    => 'تاریخ شروع اکانت',
			'expire_date'      => 'تاریخ پایان اکانت',
		);
		return $columns;
	}
 
	function usort_reorder( $a, $b ) {
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'start_date';
		// If no order, default to asc
		$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'desc';
		// Determine sort order
		$result = strcmp( $a[$orderby], $b[$orderby] );
		// Send final sort direction to usort
		//return ( $order === 'asc' ) ? $result : -$result;
	}
 
	function column_user_ID($item){
		$actions = array(
			'delete'    => sprintf('<a href="?page=%s&action=%s&member=%s">حذف</a>','av_vip_members','delete',$item['ID'])
		);
		return sprintf('%1$s %2$s', $item['user_ID'], $this->row_actions($actions) );
	}
 
	function get_bulk_actions() {
		$actions = array(
			'delete'    => 'حذف این حساب ها'
		);
		return $actions;
	}
 
	function column_cb($item) {
		return sprintf('<input type="checkbox" name="member[]" value="%s" />', $item['ID']);    
    }
 
	function prepare_items() {
        global $avdb;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		
		$per_page = $this->get_items_per_page('members_per_page');

		if (isset($_GET['s']) && !empty($_GET['s']))
        {
            $final_query = $this->generate_query('display_name', $_GET['s'], false, true);
            $final_query_limit = $this->generate_query('display_name', $_GET['s'], true);
            $found_data = $this->get_users($final_query_limit);
            $total_items = $avdb->get($final_query, 'one');
        }
        else
        {
            $final_query_limit = $this->generate_query('', '', true);
            $found_data = $this->get_users($final_query_limit);
            $total_items = $this->table_count_rows($avdb->users);
        }


        $this->set_pagination_args( array(
                'total_items' => $total_items, //WE have to calculate the total number of items
                'per_page'    => $per_page, //WE have to determine how many items to show on a page
                //WE have to calculate the total number of pages
                'total_pages'	=> ceil( $total_items / $per_page ),
                // Set ordering values if needed (useful for AJAX)
                'orderby'	=> !empty( $_REQUEST['orderby'] ) && '' != $_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'start_date',
                'order'		=> !empty( $_REQUEST['order'] ) && '' != $_REQUEST['order'] ? $_REQUEST['order'] : 'desc'
            )
        );
        $this->items = $found_data;
	}

    function ajax_script() {
        $screen = get_current_screen();
        ?>
        <script type="text/javascript">
            (function($) {
                list = {
                    /**
                     * Register our triggers
                     *
                     * We want to capture clicks on specific links, but also value change in
                     * the pagination input field. The links contain all the information we
                     * need concerning the wanted page number or ordering, so we'll just
                     * parse the URL to extract these variables.
                     *
                     * The page number input is trickier: it has no URL so we have to find a
                     * way around. We'll use the hidden inputs added in TT_Example_List_Table::display()
                     * to recover the ordering variables, and the default paged input added
                     * automatically by WordPress.
                     */
                    init: function() {
                        // This will have its utility when dealing with the page number input
                        var timer;
                        var delay = 500;
                        // Pagination links, sortable link
                        $('.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a').on('click', function(e) {
                            // We don't want to actually follow these links
                            e.preventDefault();
                            // Simple way: use the URL to extract our needed variables
                            var query = this.search.substring(1);

                            var data = {
                                paged: list.__query( query, 'paged' ) || '1',
                                order: list.__query( query, 'order' ) || 'desc',
                                orderby: list.__query( query, 'orderby' ) || 'start_date',
                                s: $('input[name=s]').val(),
                                screen_Id: list_args.screen.id ,
                                screen_baseClass: list_args.screen.base
                            };
                            list.update( data );
                        });
                        // Page number input
                        $('input[name=s]').on('keyup', function(e) {
                            // If user hit enter, we don't want to submit the form
                            // We don't preventDefault() for all keys because it would
                            // also prevent to get the page number!
                            if ( 13 == e.which )
                                e.preventDefault();
                            // This time we fetch the variables in inputs
//                            var query = window.location.search.substring(1);
                            var data = {
                                paged: '1',
                                order: $('input[name=order]').val() || 'desc',
                                orderby: $('input[name=orderby]').val() || 'start_date',
                                s: $(this).val(),
                                screen_Id: list_args.screen.id ,
                                screen_baseClass: list_args.screen.base
                            };
                            // Now the timer comes to use: we wait half a second after
                            // the user stopped typing to actually send the call. If
                            // we don't, the keyup event will trigger instantly and
                            // thus may cause duplicate calls before sending the intended
                            // value
                            window.clearTimeout( timer );
                            timer = window.setTimeout(function() {
                                list.update( data );
                                //unbind current event handler
                                $('input[name=s]').off('keyup');
                            }, delay);
                        });
                    },
                    /** AJAX call
                     *
                     * Send the call and replace table parts with updated version!
                     *
                     * @param    object    data The data to pass through AJAX
                     */
                    update: function( data ) {
                        $.ajax({
                            // /wp-admin/admin-ajax.php
                            url: ajaxurl,
                            // Add action and nonce to our collected data
                            data: $.extend(
                                {
                                    _ajax_custom_list_nonce: $('#_ajax_custom_list_nonce').val(),
                                    action: '_ajax_fetch_custom_list',
                                },
                                data
                            ),
                            beforeSend: function()
                            {
                                //show the spinner
                            },
                            // Handle the successful result
                            success: function( response ) {
                                // WP_List_Table::ajax_response() returns json
                                var response = $.parseJSON( response );
                                // Add the requested rows
                                if ( response.rows.length )
                                    $('#the-list').html( response.rows );
                                // Update column headers for sorting
                                if ( response.column_headers.length )
                                    $('thead tr, tfoot tr').html( response.column_headers );
                                // Update pagination for navigation
                                if ( response.pagination.bottom.length )
                                    $('.tablenav.top .tablenav-pages').html( $(response.pagination.top).html() );
                                if ( response.pagination.top.length )
                                    $('.tablenav.bottom .tablenav-pages').html( $(response.pagination.bottom).html() );
                                // Init back our event handlers
                                list.init();
                            },
                            complete: function() {
                                //hide the spinner
                            }
                        });
                    },
                    /**
                     * Filter the URL Query to extract variables
                     *
                     * @see http://css-tricks.com/snippets/javascript/get-url-variables/
                     *
                     * @param    string    query The URL query part containing the variables
                     * @param    string    variable Name of the variable we want to get
                     *
                     * @return   string|boolean The variable value if available, false else.
                     */
                    __query: function( query, variable ) {
                        var vars = query.split("&");
                        for ( var i = 0; i <vars.length; i++ ) {
                            var pair = vars[ i ].split("=");
                            if ( pair[0] == variable )
                                return pair[1];
                        }
                        return false;
                    },
                }
// Show time!
                list.init();
            })(jQuery);
        </script>
        <?php
    }

    private function generate_query($search_field, $search_value,$useLimit, $returnCount = false)
    {
        global $wpdb, $avdb;
        $users_table_name = $wpdb->prefix . 'users';
        //prepare search query
        if (!empty($search_field) && !empty($search_value))
        {
            $search_condition = "WHERE `{$users_table_name}`.`{$search_field}` LIKE '%$search_value%'";
        }
        //prepare order by section
        $order_query = sprintf("ORDER BY `%s` %s", $this->orderby, $this->order);

        if ($returnCount)
        {
            $query = "SELECT COUNT(*) FROM `{$avdb->users}` INNER JOIN `{$users_table_name}` ON `{$users_table_name}`.id = `{$avdb->users}`.`user_ID`";
        }
        else
        {
            $query = "SELECT * FROM `{$avdb->users}` INNER JOIN `{$users_table_name}` ON `{$users_table_name}`.id = `{$avdb->users}`.`user_ID`";
        }
        if (isset($search_condition) && !empty($search_condition))
        {
            $query .= " " . $search_condition;
        }

        $query .= ' ' . $order_query;


        // pagination
        if ($useLimit)
        {
            $per_page = $this->get_items_per_page('members_per_page');
            $current_page = $this->get_pagenum();
            $offset = ($current_page - 1) * $per_page;
            $query .= sprintf(" LIMIT %s,%s", $offset, $per_page);
        }


        return $query;
    }
    private function table_has_rows($table_name)
    {
        global $wpdb;
        //check if the databse has rows
        if( $wpdb->get_var('SELECT * FROM `'.$table_name.'`') === null ) {
            return false;
        }

        return true;
    }
    private function table_count_rows($table_name)
    {
        global $wpdb;
        //check if the databse has rows
        $result = $wpdb->get_var('SELECT COUNT(*) FROM `'.$table_name.'`');
        return $result;
    }

    private function get_users($query)
    {
        global  $avdb;

        // check if the table ( users ) has any rows
        if (!$this->table_has_rows($avdb->users)) return array();

        //get final results
        $ad_vip_members_array = array();
        foreach( $avdb->get($query,'all',ARRAY_A) as $key => $val ){
            $ad_vip_members_array_user_info = get_userdata($val['user_ID']);
            $ad_vip_members_array_data['ID'] = $val['ID'];
            $ad_vip_members_array_data['user_ID'] = $val['user_ID'];
            $ad_vip_members_array_data['user_name'] = '<img class="av_vip_member_avatar" src="http://www.gravatar.com/avatar/'.md5(strtolower($ad_vip_members_array_user_info->user_email)).'?s=30"/>' .'<span class="av_vip_member_name">'.$ad_vip_members_array_user_info->display_name.'</span>';
            $ad_vip_members_array_data['start_date'] = advanced_vip::jalali_nice_format(advanced_vip::av_gr_to_ja($val['start_date'])) . ' (' . str_replace(array('days','hours','mins','day','min','hour'),array('روز','ساعت','دقیقه','روز','دقیقه','ساعت'),human_time_diff( strtotime($val['start_date']) , time() )).' قبل)';
            $ad_vip_members_array_data['expire_date'] = advanced_vip::jalali_nice_format(advanced_vip::av_gr_to_ja( date('Y-m-d H:i:s',$val['expire_date']) )) . ' (' . str_replace(array('days','hours','mins','day','min','hour'),array('روز','ساعت','دقیقه','روز','دقیقه','ساعت'),human_time_diff( time(), $val['expire_date'] )).' مانده)';

            $ad_vip_members_array[] = $ad_vip_members_array_data;
        }

        return $ad_vip_members_array;
    }

} //class



function av_add_menu_items(){

	add_menu_page(
		'پیشخوان',
		'اشتراک ویژه',
		'manage_options',
		'av_dashbourd',
		'av_dashbourd_func',
		av_url.'assets/images/menu-icon.png'
	);
	
	$hook = add_submenu_page( 
		'av_dashbourd',
		'کاربران ویژه',
		'کاربران ویژه',
		'activate_plugins',
		'av_vip_members',
		'av_vip_members_func'
	);

	add_submenu_page(
		'av_dashbourd',
		'فایل های محافظت شده',
		'فایل های محافظت شده',
		'manage_options',
		'av_files',
		'av_files_func'
	);

	add_submenu_page(
		'av_dashbourd',
		'لیست پرداخت ها',
		'لیست پرداخت ها',
		'manage_options',
		'av_payments',
		'av_payments_func'
	);

	/*add_submenu_page(
		'av_dashbourd',
		'کوپن ها',
		'کوپن ها',
		'manage_options',
		'av_coupons',
		'av_coupons_func'
	);*/

	add_submenu_page(
		'av_dashbourd',
		'تنظیمات',
		'تنظیمات',
		'manage_options',
		'av_settings',
		'av_settings_func'
	);

	add_submenu_page(
		'av_dashbourd',
		'راهنما',
		'راهنما',
		'read',
		'av_help',
		'av_help_func'
	);

	add_submenu_page(
		null,
		'اضافه کردن دستی کاربر',
		'اضافه کردن دستی کاربر',
		'manage_options',
		'av_add_vip_member',
		'av_add_vip_member_func'
	);

	add_submenu_page(
		null,
		'افزایش اعتبار گروهی',
		'افزایش اعتبار گروهی',
		'manage_options',
		'av_group_increasing',
		'av_group_increasing_func'
	);

	add_submenu_page(
		null,
		'کاهش دادن گروهی اعتبار',
		'کاهش دادن گروهی اعتبار',
		'manage_options',
		'av_group_lessen',
		'av_group_lessen_func'
	);

	/*add_submenu_page(
		null,
		'اضافه کردن کوپن',
		'اضافه کردن کوپن',
		'manage_options',
		'av_add_coupon',
		'av_add_coupon_func'
	);*/
	
  add_action( "load-$hook", 'av_add_options' );
  
}
 
function av_add_options() {
	global $av_vip_member_table;
	$option = 'per_page';
	$args = array(
		'label' => 'کاربر ویژه',
		'default' => 10,
		'option' => 'members_per_page'
	);
	add_screen_option( $option, $args );
	$av_vip_member_table = new av_vip_members_table();
}
add_action( 'admin_menu', 'av_add_menu_items' );

//save/load screen option
add_filter('set-screen-option', 'test_table_set_option', 10, 3);
function test_table_set_option($status, $option, $value) {
    return $value;
}
 
 
 
function av_vip_members_func(){
	global $av_vip_member_table ;
	echo '</pre><div class="wrap"><h2>کاربران ویژه</h2>';
	$av_vip_member_table->prepare_items();
?>
	<form method="post">
		<input type="hidden" name="page" value="av_vip_members">
		<?php
		$av_vip_member_table->search_box( 'جست و جوی نام کاربران', 'search_id' );
		$av_vip_member_table->display(); 
	echo '</form></div><div id="av-actions-list-btn"><a class="av-button">عملیات ویژه</a><ul class="hdn" id="av-actions-list">
		<li><a href="'.admin_url('admin.php?page=av_add_vip_member').'">اضافه کردن دستی کاربر</a></li>
		<li><a href="'.admin_url('admin.php?page=av_group_increasing').'">افزایش گروهی اعتبار</a></li>
		<li><a href="'.admin_url('admin.php?page=av_group_lessen').'">کاهش گروهی اعتبار</a></li>
	</ul></div>'; 
}

