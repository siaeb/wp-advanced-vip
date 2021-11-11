<?php
	if( ! defined('ABSPATH') ) die();
	class advanced_vip{
		
		public function __construct() {
		
			add_action( 'av_hourly_event', array( $this, 'do_av_hourly_event' ) );

			add_action( 'parse_request' , array($this, 'remote_vip_check') );
			add_action( 'admin_init' , array($this, 'vip_files_upload') );
			add_action( 'admin_init' , array($this, 'vip_group_account_charge') );
			add_action( 'admin_init' , array($this, 'vip_group_account_decharge') );
			add_action( 'admin_init' , array($this, 'DeletePaymentItem') );
		
			register_deactivation_hook( av_plugin_file , array($this, 'deactivation') );
			
			add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );
			add_action( 'wp_ajax_av_ajax_settings_save', array($this, 'av_ajax_settings_save') );
			add_action( 'wp_ajax_av_ajax_delete_vip_user', array($this, 'av_ajax_delete_vip_user') );
			add_action( 'wp_ajax_av_ajax_delete_file', array($this, 'av_ajax_delete_file') );
			add_action( 'wp_ajax_av_ajax_add_vip_user', array($this, 'av_ajax_add_vip_user') );
			
			add_action( 'av_before_payment', array($this, 'av_before_payment') , 10 , 2 );
			
			add_action( 'av_after_payment', array($this, 'av_after_payment') , 10 , 2 );
			
			add_action( 'av_complete_charge' , array( $this , 'removeTempRow' ) );
			
			add_action( 'av_complete_charge' , array( $this , 'sendStartVIPsms' ) );
			
			add_action( 'av_complete_charge' , array( $this , 'sendStartVIPemail' ) );
			
			add_filter( 'cron_schedules', array( $this , 'customCronSchedule' ) );

//add custom columns to posts management screen
            add_filter('manage_post_posts_columns', array( &$this, 'av_add_vipstatus_column' ) );
            add_action('manage_post_posts_custom_column', array( &$this, 'av_vipstatus_content' ), 10, 2);


            add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
            add_action('load-edit.php',         array(&$this, 'custom_bulk_action'));
            add_action('admin_notices',         array(&$this, 'custom_bulk_admin_notices'));
			
		}
		
		function custom_bulk_admin_footer() {

            global $post_type;

            if($post_type == 'post') {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function() {
                        jQuery('<option>').val('no').text('<?php _e('مجانی')?>').appendTo("select[name='action']");
                        jQuery('<option>').val('no').text('<?php _e('مجانی')?>').appendTo("select[name='action2']");


                        jQuery('<option>').val('yes').text('<?php _e('پولی')?>').appendTo("select[name='action']");
                        jQuery('<option>').val('yes').text('<?php _e('پولی')?>').appendTo("select[name='action2']");
                    });
                </script>
                <?php
            }
        }
        function custom_bulk_action() {
            global $typenow;
            $post_type = $typenow;

            if($post_type == 'post') {

                // get the action
                $wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
                $action = $wp_list_table->current_action();

                $allowed_actions = array("yes", "no");
                if(!in_array($action, $allowed_actions)) return;

                // security check
                check_admin_referer('bulk-posts');

                // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
                if(isset($_REQUEST['post'])) {
                    $post_ids = array_map('intval', $_REQUEST['post']);
                }

                if(empty($post_ids)) return;

                // this is based on wp-admin/edit.php
                $sendback = remove_query_arg( array('count', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
                if ( ! $sendback )
                    $sendback = admin_url( "edit.php?post_type=$post_type" );

                $pagenum = $wp_list_table->get_pagenum();
                $sendback = add_query_arg( 'paged', $pagenum, $sendback );

                switch($action) {
                    case 'no':
                        if ( !current_user_can('manage_options')  )
                            wp_die( __('شما برای انجام این عمل سطح دسترسی لازم را ندارید') );

                        $count_of_free_accounts = 0;
                        foreach( $post_ids as $post_id ) {
                            $this->setVipStatus( $post_id, 'no' );
                            $count_of_free_accounts++;
                        }

                        $sendback = add_query_arg( array('count' => $count_of_free_accounts,
                                                    'ids' => join(',', $post_ids) ), $sendback );
                        break;
                    case 'yes':

                        if ( !current_user_can('manage_options') && !current_user_can( 'edit_posts' ) )
                            wp_die( __('شما برای انجام این عمل سطح دسترسی لازم را ندارید') );

                        $count_of_vip_accounts = 0;
                        foreach( $post_ids as $post_id ) {
                            $this->setVipStatus($post_id, 'yes');
                            $count_of_vip_accounts++;
                        }

                        $sendback = add_query_arg( array('count' => $count_of_vip_accounts,
                                                         'ids' => join(',', $post_ids) ), $sendback );
                        break;
                    default: return;
                }

                $sendback =
                    remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );

                wp_redirect($sendback);
                exit();
            }
        }
        function custom_bulk_admin_notices() {
            global $post_type, $pagenow;

            if($pagenow == 'edit.php' && $post_type == 'post' && isset($_REQUEST['count']) && (int) $_REQUEST['count']) {
                $message = sprintf( _n( 'وضعیت پست تغییر داده شد', '%s پست تغییر وضعیت داده شدند.', $_REQUEST['count'] ), number_format_i18n( $_REQUEST['count'] ) );
                echo "<div class=\"updated\"><p>{$message}</p></div>";
            }
        }

        function setVipStatus( $post_id, $vipstatus ) {
		    $result = update_post_meta( $post_id, 'av_show_post_only_for_vip', $vipstatus );
		    return $result;
        }
        function av_add_vipstatus_column( $defaults ) {
		    $defaults['vipstatus'] = 'آزاد/ویژه';
		    return $defaults;
        }
        function av_vipstatus_content( $column_name, $post_id ) {
		if ( $column_name == 'vipstatus' ) {
		        $vipstatus = get_post_meta( $post_id, 'av_show_post_only_for_vip', true );
		        if ( $vipstatus == 'yes' ) {
		            $output = '<img width="25px" height="25px" src="' . av_assets_url . 'images/money.ico' . '" />';
		            echo $output;
                }
            }    
        }
		
		public function do_av_hourly_event() {
			self::doHourlyCloseExpireUsers();
			self::DeleteExpiredUsers();
		}
		
		public function customCronSchedule($schedules){
			$schedules['hourly'] = array(
				'interval' => 60 * 60,
				'display'  => __( 'Once Hour' ),
			);
			return $schedules;
		}
	
		
		public function deactivation(){
			wp_clear_scheduled_hook( 'av_hourly_event' );
		}
		
		public function enqueue_scripts(){

			$url = av_url . 'assets/';
		
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-color' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-effects-slide' );
			wp_enqueue_script( 'av-admin-js' , $url.'js/admin.js' , 'jquery' );
			wp_enqueue_style( 'av-admin-css' , $url.'css/admin.css' );
			
			wp_enqueue_script('av-datapick-cc', $url . 'jalali-date-picker/scripts/jquery.ui.datepicker-cc.js');
			wp_enqueue_script('av-datapick-calendar', $url . 'jalali-date-picker/scripts/calendar.js');
			wp_enqueue_script('av-datapick-cc-ar', $url . 'jalali-date-picker/scripts/jquery.ui.datepicker-cc-ar.js');
			wp_enqueue_script('av-datapick-cc-fa', $url . 'jalali-date-picker/scripts/jquery.ui.datepicker-cc-fa.js');
			wp_enqueue_style('av-datapick-styles', $url . 'jalali-date-picker/styles/jquery-ui-1.8.14.css');
		
			wp_enqueue_script('av-select2-js', $url . 'select2/select2.min.js');
			wp_enqueue_style('av-select2-styles', $url . 'select2/select2.css');
			
			
			wp_enqueue_style('av-codemirror-styles', $url . 'codemirror/codemirror-pack.css');
			wp_enqueue_script('av-codemirror-pack-js', $url . 'codemirror/codemirror-pack.js', array(), false, true);

//            wp_enqueue_script('av-wplistajax', $url . 'js/wplistajax.js',array(), false, true);
			
		}
	
	
	
		public function removeTempRow( $data ){
			global $wpdb,$avdb;
			return $wpdb->query("DELETE FROM ".$avdb->temp." WHERE ID='".$data[0]['ID']."'");
		}
	
	
		public function sendStartVIPemail( $data ){
			global $wpdb,$avdb,$av_settings,$current_user;
			
			if( isset( $av_settings['email_on_vip_start'] ) ){
			
				$userData = get_userdata( $data[0]['user_id'] );
			
				$site_url = parse_url(site_url());
				$site_url = $site_url['host'];
			
				
				$text = str_replace(
					array(
						'{member-name}',
						'{start-jdate}',
						'{expire-jdate}',
						'{expire-human-date}',
						'{site-title}',
						'{site-url}',
						'{refNumber}',
						'{payment-cost}',
						'{user-email}'
					),
					array(
						$userData->data->display_name,
                        self::nice_jdate_from_time_stamp( time() ),
                        self::nice_jdate_from_time_stamp( $data['time_data']['expire_unix'] ),
						human_time_diff( time() , $data['time_data']['expire_unix'] ),
						get_bloginfo('name'),
						site_url(),
						$data['refNumber'],
						$data[0]['payment_price'],
						$userData->data->user_email
					),
					$av_settings['vip_register_mail_template']
				);
				
			
				$headers  = 'From: no-reply@'.$site_url. "\r\n" .
					'MIME-Version: 1.0' . "\r\n" .
					'Content-type: text/html; charset=utf-8' . "\r\n" .
					'X-Mailer: PHP/' . phpversion();
										
				@mail($userData->data->user_email, @$av_settings['vip_register_mail_subject'], $text, $headers);
				
			
			}
			
		}
	
		public function sendStartVIPsms( $data ){
			global $wpdb,$avdb,$av_settings,$current_user;
			
			if( ! is_numeric( $data[0]['user_phone'] ) )
				return;
			if( function_exists('avSendSMS') && isset( $av_settings['sms_on_vip_start'] ) ){
						
				$userData = get_userdata( $data[0]['user_id'] );
				
				$text = str_replace( 
					array(
						'{member-name}',
						'{start-jdate}',
						'{expire-jdate}',
						'{expire-human-date}',
						'{site-title}',
						'{site-url}',
						'{refNumber}',
						'{payment-cost}',
						'{user-email}'
					),
					array(
						$userData->data->display_name,
						$this->nice_jdate_from_time_stamp( time() ),
						$this->nice_jdate_from_time_stamp( $data['time_data']['expire_unix'] ),
						human_time_diff( time() , $data['time_data']['expire_unix'] ),
						get_bloginfo('name'),
						site_url(),
						$data['refNumber'],
						$data[0]['payment_price'],
						$userData->data->user_email
					),
					$av_settings['sms_on_vip_start_template'] 
				);
				
				avSendSMS( $text , $data[0]['user_phone'] );
				
			}
			
		}
		
		
	
		public static function av_gr_to_ja($date,$mod=''){
			$g_y = date('Y',strtotime($date));
			$g_m = date('n',strtotime($date));
			$g_d = date('j',strtotime($date));
			$d_4=$g_y%4;
			$g_a=array(0,0,31,59,90,120,151,181,212,243,273,304,334);
			$doy_g=$g_a[(int)$g_m]+$g_d;
			if($d_4==0 and $g_m>2)$doy_g++;
			$d_33=(int)((($g_y-16)%132)*.0305);
			$a=($d_33==3 or $d_33<($d_4-1) or $d_4==0)?286:287;
			$b=(($d_33==1 or $d_33==2) and ($d_33==$d_4 or $d_4==1))?78:(($d_33==3 and $d_4==0)?80:79);
			if((int)(($g_y-10)/63)==30){$a--;$b++;}
			if($doy_g>$b){
				$jy=$g_y-621; $doy_j=$doy_g-$b;
			}else{
				$jy=$g_y-622; $doy_j=$doy_g+$a;
			}
			if($doy_j<187){
				$jm= (int)(($doy_j-1)/31); $jd=$doy_j-(31*$jm++);
			}else{
				$jm=(int)(($doy_j-187)/30); $jd=$doy_j-186-($jm*30); $jm+=7;
			}
			return($mod=='') ? array($jy,$jm,$jd) : $jy.$mod.$jm.$mod.$jd;
		}
		
		public static function av_ja_to_gr($date,$mod=''){
			$j_y = $date[0];
			$j_m = $date[1];
			$j_d = $date[2];
			$d_4=($j_y+1)%4;
			$doy_j=($j_m<7)?(($j_m-1)*31)+$j_d:(($j_m-7)*30)+$j_d+186;
			$d_33=(int)((($j_y-55)%132)*.0305);
			$a=($d_33!=3 and $d_4<=$d_33)?287:286;
			$b=(($d_33==1 or $d_33==2) and ($d_33==$d_4 or $d_4==1))?78:(($d_33==3 and $d_4==0)?80:79);
			if((int)(($j_y-19)/63)==20){$a--;$b++;}
			if($doy_j<=$a){
				$gy=$j_y+621; $gd=$doy_j+$b;
			}else{
				$gy=$j_y+622; $gd=$doy_j-$a;
			}
			foreach(array(0,31,($gy%4==0)?29:28,31,30,31,30,31,31,30,31,30,31) as $gm=>$v){
				if($gd<=$v)break;
					$gd-=$v;
			}
			return($mod=='')?array($gy,$gm,$gd):$gy.$mod.$gm.$mod.$gd;
		}		
		
		public static function jalali_nice_format($date=null){
			if($date === null) return;
			$year = $date[0];
			$month = $date[1];
			$day = $date[2];
			switch($day){case (1):$day = 'یکم';break;case (2);$day = 'سوم';break;case (3):$day = 'سوم';break;case (4):$day = 'چهارم';break;case (5):$day = 'پنجم';break;case (6):$day = 'ششم';break;case (7):$day = 'هفتم';break;case (8):$day = 'هشتم';break;case (9):$day = 'نهم';break;case (10):$day = 'دهم';break;case (11):$day = 'یازدهم';break;case (12):$day = 'دوازدهم';break;case (13):$day = 'سیزدهم';break;case (14):$day = 'چهاردهم';break;case (15):$day = 'پانزدهم';break;case (16):$day = 'شانزدهم';break;case (17):$day = 'هفدهم';break;case (18):$day = 'هجدهم';break;case (19):$day = 'نوزدهم';break;case (20):$day = 'بیستم';break;case (21):$day = 'بیست یکم';break;case (22):$day = 'بیست دوم';break;case (23):$day = 'بیست سوم';break;case (24):$day = 'بیست چهارم';break;case (25):$day = 'بیست پنجم';break;case (26):$day = 'بیست ششم';break;case (27):$day = 'بیست هفتم';break;case (28):$day = 'بیست هشتم';break;case (29):$day = 'بیست نهم';break;case (30):$day = 'سی ام';break;case (31):$day = 'سی یکم';break;}		
			switch($month){case (1):$month = 'فروردین';break;case (2):$month = 'اردیبهشت';break;case (3):$month = 'خرداد';break;case (4):$month = 'تیر';break;case (5):$month = 'مرداد';break;case (6):$month = 'شهریور';break;case (7):$month = 'مهر';break;case (8):$month = 'آبان';break;case (9):$month = 'آذر';break;case (10):$month = 'دی';break;case (11):$month = 'بهمن';break;case (12):$month = 'اسفند';break;}
			return $day . '، ' . $month . '، ' . $year;
		}
		
		public static function nice_jdate_from_time_stamp( $timestamp = 0 ){
			$date = date( "Y-m-d H:i:s", $timestamp );
			return self::jalali_nice_format( self::av_gr_to_ja( $date ) );
		}
		
		public static function bytesToSize($bytes, $precision = 1){
			$kilobyte = 1024;
			$megabyte = $kilobyte * 1024;
			$gigabyte = $megabyte * 1024;
			$terabyte = $gigabyte * 1024;
			
			if (($bytes >= 0) && ($bytes < $kilobyte)) {
				return $bytes . ' B';

			} elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
				return round($bytes / $kilobyte, $precision) . ' KB';

			} elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
				return round($bytes / $megabyte, $precision) . ' MB';

			} elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
				return round($bytes / $gigabyte, $precision) . ' GB';

			} elseif ($bytes >= $terabyte) {
				return round($bytes / $terabyte, $precision) . ' TB';
			} else {
				return $bytes . ' B';
			}
		}
		
		public function secondsToTime($inputSeconds) {

			$secondsInAMinute = 60;
			$secondsInAnHour  = 60 * $secondsInAMinute;
			$secondsInADay    = 24 * $secondsInAnHour;

			// extract days
			$days = floor($inputSeconds / $secondsInADay);

			// extract hours
			$hourSeconds = $inputSeconds % $secondsInADay;
			$hours = floor($hourSeconds / $secondsInAnHour);

			// extract minutes
			$minuteSeconds = $hourSeconds % $secondsInAnHour;
			$minutes = floor($minuteSeconds / $secondsInAMinute);

			// extract the remaining seconds
			$remainingSeconds = $minuteSeconds % $secondsInAMinute;
			$seconds = ceil($remainingSeconds);

			// return the final array
			$obj = array(
				'd' => (int) $days,
				'h' => (int) $hours,
				'm' => (int) $minutes,
				's' => (int) $seconds,
			);
			return $obj;
		}
		
		public function user_authenticate($user=null,$pass=null){
			if( $user === null || $pass === null ) return;
			$data = wp_authenticate($user,$pass);
			if( get_class($data) === 'WP_User' )
				return $data->data->ID;
			return false;	
		}

		public function remote_vip_check($user=null,$pass=null){
			if( isset($_POST['action']) && $_POST['action'] == 'vip_check' && isset($_POST['user']) && isset($_POST['password'])){
				die($this->user_authenticate($_POST['user'],$_POST['password']));
			}	
		}

		
		public function av_ajax_delete_vip_user(){
		
		}

		public function av_ajax_delete_file(){
			global $wpdb,$avdb,$av_vip_dir;
			$output = array();
			if( isset($_POST['file_id']) ){
				$file_name = $wpdb->get_var("SELECT encypted_name FROM ".$avdb->files." WHERE ID='".$_POST['file_id']."'");
				$file_ext = $wpdb->get_var("SELECT file_type FROM ".$avdb->files." WHERE ID='".$_POST['file_id']."'");
				$delete_file = unlink($av_vip_dir.$file_name.'.'.$file_ext);
				$query = $wpdb->query("DELETE FROM ".$avdb->files." WHERE ID='".$_POST['file_id']."'");
				if( $delete_file !== false && $query !== false )
					$output['status'] = 'success';
				else
					$output['status'] = 'error';
			}
			echo json_encode( $output );
			die();
		}
		
		public function av_ajax_add_vip_user(){
			global $avdb,$wpdb;
			$id = $_POST['userID'];
			$credit = $_POST['cre'];
			$credit_type = strpos($credit, '/') !== false ? 'date' : 'day';
			
			if( $credit_type == 'day' ){
				$credit_date = self::dayToSecounds($_POST['cre']);
			}
			if( $credit_type == 'date' ){
				$credit_f = explode('/',$_POST['cre']);
				$credit_f = self::av_ja_to_gr(array($credit_f[2],$credit_f[1],$credit_f[0]));
				$credit_date = $credit_f[0].'-'.$credit_f[1].'-'.$credit_f[2].' 00:00:00';
			}
			
			$output = array();
			if( current_user_can('administrator') && !empty($_POST['userID']) && !empty($_POST['cre']) ){
			
				$custom_user_vip_data = self::av_vip_stat_by_ID($id);
				
				if( $credit_type == 'date' ){
						if( $custom_user_vip_data === null ){
							$update_ = $wpdb->insert(
								$avdb->users,
								array(
									'start_date' => current_time('mysql'),
									'expire_date' => strtotime($credit_date),
									'user_ID' => $id
								)
							);
							$output['t'] = strtotime($credit_date);
						} else{
							$update_ = $wpdb->update(
								$avdb->users,
								array(
									'expire_date' => strtotime($credit_date)
								),
								array(
									'user_ID' => $id
								)
							);
						}
					} else{
						if( $custom_user_vip_data === null ){
							$update_ = $wpdb->insert(
								$avdb->users,
								array(
									'start_date' => current_time('mysql'),
									'expire_date' => time()+$credit_date,
									'user_ID' => $id
								)
							);						
						} else{
							$user_current_expire_date_in_timestamp = $custom_user_vip_data['expire_date'];
							if( $user_current_expire_date_in_timestamp >= time() ){
								$update_ = $wpdb->update(
									$avdb->users,
									array(
										'expire_date' => $user_current_expire_date_in_timestamp+$credit_date
									),
									array(
										'user_ID' => $id
									)
								);
							} else{
								$update_ = $wpdb->update(
									$avdb->users,
									array(
										'expire_date' => time()+$credit_date
									),
									array(
										'user_ID' => $id
									)
								);
							}
						}
					}
				$output['status'] = $update_ === 0 ?   'error': 'success';
			} else{
				$output['status'] = 'error';
			}
			
			echo json_encode( $output );
		
			die();
		}
		
		public function av_ajax_settings_save(){
		
			if( current_user_can('manage_options') && is_admin() ){
				$params = array();
				parse_str($_POST['data'], $params);
				$params['vip_categories'] = $_POST['vip_cats'];
				$params['default_vip_roles'] = $_POST['vip_roles'];
				if( get_option('av_settings' === false ) ){
					$add_option = add_option('av_settings',serialize($params));
					echo $add_option === false ? 'error' : 'ok';
				} else{
					delete_option('av_settings');
					$update_option = update_option('av_settings',serialize($params));
					echo $update_option === false ? 'error' : 'ok';
				}
			} else {
				echo 'error';
			}
			@mkdir( ABSPATH . $params['protected_files_dir'], 0777, true);
			@file_put_contents(ABSPATH.$params['protected_files_dir'].'/index.html', 'HI!');
			@file_put_contents(ABSPATH.$params['protected_files_dir'].'/.htaccess', "Order Deny,Allow\nDeny from all");
			
			die();
		}


		public static function av_current_user_vip_stat(){
			global $wpdb,$avdb;
			$id = get_current_user_id();
			if( $id === 0 ) return false;
			$query = $wpdb->get_row("SELECT * FROM `".$avdb->users."` WHERE user_ID='".$id."'",ARRAY_A);
			return $query;
		}

		public function av_vip_stat_by_ID($id){
			global $wpdb,$avdb;
			if( $id === 0 ) return false;
			$query = $wpdb->get_row("SELECT * FROM `".$avdb->users."` WHERE user_ID='".$id."'",ARRAY_A);
			return $query;
		}

		public function vip_start_by_userID( $id = 0 ){
			global $wpdb,$avdb;
			return $wpdb->get_var("SELECT start_date FROM `".$avdb->users."` WHERE user_ID='".$id."'");
		}

		public function vip_expire_by_userID( $id = 0 ){
			global $wpdb,$avdb;
			return $wpdb->get_var("SELECT expire_date FROM `".$avdb->users."` WHERE user_ID='".$id."'");
		}

		
		public function vip_files_upload(){
			global $av_vip_dir,$avEcrypt,$wpdb,$avdb;
			if( isset($_POST['av_files_upload']) && 'true' == $_POST['av_files_upload'] ){

				foreach( $_FILES['vip_file']['tmp_name'] as $key => $tmp_name ){
					$name[] = $_FILES['vip_file']['name'][$key];
					$tmp_name1[] = $_FILES['vip_file']['tmp_name'][$key];
					$size[] = $_FILES['vip_file']['size'][$key];
					$type[] = $_FILES['vip_file']['type'][$key];
					$error[] = $_FILES['vip_file']['error'][$key];
				}
				foreach( $tmp_name1 as $of => $val ){
					if ( $error[$of] <= 0 ){
						$fileinfo = pathinfo($name[$of]);
						move_uploaded_file( $val , $av_vip_dir.$avEcrypt->en($fileinfo['filename']).'.'.$fileinfo['extension'] );
						$wpdb->insert( $avdb->files, array(
							'file_name' => $fileinfo['filename'],
							'encypted_name' => $avEcrypt->en($fileinfo['filename']),
							'file_size' => $size[$of],
							'file_type' => $size[$of],
							'file_type' => $fileinfo['extension'],
							'upload_date' => current_time('mysql')
						));
					}	
				}
				foreach( $error as $key => $item ){
					switch (intval($item)){
						case (0):
							$output[] = 'فایل ' . $name[$key] . ' با موفقیت آپلود شد.';
						break;
						case (1):
							$output[] = 'حجم فایل ' . $name[$key] . ' بیشتر از مقدار تعریف شده در php.ini است.';
						break;
						case (2):
							$output[] = 'حجم فایل ' . $name[$key] . ' مجاز نیست.';
						break;
						case (3):
							$output[] = 'فایل ' . $name[$key] . ' به صورت کامل آپلود نشد.';
						break;
						case (4):
							$output[] = 'آپلود فایل ' . $name[$key] . ' موفقیت آمیز نبود.';
						break;
						case (6):
							$output[] = 'به خاطر خطا در مسیر موقت آپلود فایل ' . $name[$key] . ' آپلود نشد.';
						break;
						case (7):
							$output[] = 'فایل ' . $name[$key] . ' قادر به آپلود شدن نبود.';
						break;
						case (8):
							$output[] = 'فایل ' . $name[$key] . ' به دلیلی ناشناخته آپلود نشد.';
						break;
						default:
							$output[] = 'سیستم قادر به تعیین وضعیت آپلود فایل نیست.';
						break;
					}
				}
				header( 'Location: '.admin_url().'admin.php?page=av_files&upload_status='.$avEcrypt->en(serialize($output)) . '&error=' . unserialize($error) );
				exit;
			}		
		}
		
		
		
		public function zarinpal_payment_verify($merchantID,$amount,$au){
			$client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
			$res = $client->PaymentVerification(
					array(
							'MerchantID'	=> $merchantID,
							'Authority' 	=> $au,
							'Amount'	 	=> intval($amount)

					) );


			return  $res;
		}
		
		public function mihanpal_payment_verify($pin,$au,$price){
			$client = new SoapClient("http://mihanpal.com/index.php/payment2/wsdl");
			$result = $client->verify($pin,$au,$price);
			return ( ! empty($result) and $result == 1 ) ? true : $result;
		}
		
		public function jahanpay_payment_verify($api,$orderId,$amount){
			$client = new SoapClient("http://www.jahanpay.com/webservice?wsdl");
			$result = $client->verification($api,$amount,$_GET["au"]);
			
			$errorCode = array(
				-20 => 'API جهان پی نادرست است.' ,
				-21 => 'پاسخ جهان پی: IP سایت نامعتبر است.' ,
				-22 => 'پاسخ جهان پی: مبلغ تراکنش از حداقل کمتر است.' ,
				-23 => 'پاسخ جهان پی: مبلغ تراکنش از حداقل کمتر است.' ,
				-24 => 'مبلغ نامعتبر است' ,
				-6 => 'ارتباط با بانک برقرار نشد' ,
				-26 => 'درگاه غیرفعال است' ,
				-27 => 'آی پی شما مسدود است' ,
				-9 => 'خطای ناشناخته' ,
				-29 => 'آدرس کال بک خالی است ' ,
				-30 => 'چنین تراکنشی یافت نشد' ,
				-31 => 'تراکنش انجام نشده ' ,
				-32 => 'تراکنش انجام شده اما مبلغ نادرست است ' ,
				1 => "تراکنش از طرف جهان پی تایید شد." ,
			);	
			
			switch( intval( $result ) ){
				case( 1 ):
					return array( 'status' => true , 'msg' => $errorCode[1] );
				break;
				case( -20 ):
					return array( 'status' => false , 'msg' => $errorCode[-20] );
				break;
				case( -21 ):
					return array( 'status' => false , 'msg' => $errorCode[-21] );
				break;
				case( -22 ):
					return array( 'status' => false , 'msg' => $errorCode[-22] );
				break;
				case( -23 ):
					return array( 'status' => false , 'msg' => $errorCode[-23] );
				break;
				case( -24 ):
					return array( 'status' => false , 'msg' => $errorCode[-24] );
				break;
				case( -6 ):
					return array( 'status' => false , 'msg' => $errorCode[-6] );
				break;
				case( -26 ):
					return array( 'status' => false , 'msg' => $errorCode[-26] );
				break;
				case( -27 ):
					return array( 'status' => false , 'msg' => $errorCode[-27] );
				break;
				case( -9 ):
					return array( 'status' => false , 'msg' => $errorCode[-9] );
				break;
				case( -29 ):
					return array( 'status' => false , 'msg' => $errorCode[-29] );
				break;
				case( -30 ):
					return array( 'status' => false , 'msg' => $errorCode[-30] );
				break;
				case( -31 ):
					return array( 'status' => false , 'msg' => $errorCode[-31] );
				break;
				case( -32 ):
					return array( 'status' => false , 'msg' => $errorCode[-32] );
				break;
			}
		}
		
		public function parspal_payment_verify($price,$refNumber){
			global $av_settings;
			$request = '';
			$request .= 'MerchantID='.$av_settings['parspal_merchant_id'];
            $request .= '&Password='.$av_settings['parspal_port_password'];
            $request .= '&Price='.$price;
            $request .= '&RefNum='.$refNumber;
			$response = '';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://merchant.arianpal.com/PostService/Default.aspx?Method=Verify");
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			$response = curl_exec($ch);
			curl_close($ch);
			if( $response == 'success' )
				return array( 'status' => true , 'msg' => $response );
			else
				return array( 'status' => false , 'msg' => $response );
		}
		
		public function payline_payment_verify($id_get,$trans_id){		
			global $av_settings;
			$response = wp_remote_post(
				'http://payline.ir/payment/gateway-result-second', 
				array(
					'body' => array( 
						'api' => $av_settings['payline_api'], 
						'id_get' => $id_get, 
						'trans_id' => $trans_id
					)
				)
			);
			
			if( is_wp_error( $response ) )
				return array( 'status' => false , 'msg' => 'خطا در اتصال به Payline' );
			else 
				return intval( $response['body'] ) === 1 ? array( 'status' => true , 'msg' => 'پرداخت تایید شده است.' ) : array( 'status' => false , 'msg' => 'پرداخت از طرف Payline تایید نشد.' );
		}
		
		
		public function payline_test_payment_verify($id_get,$trans_id){		
			global $av_settings;
			$response = wp_remote_post(
				'http://payline.ir/payment-test/gateway-result-second', 
				array(
					'body' => array( 
						'api' => 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567', 
						'id_get' => $id_get, 
						'trans_id' => $trans_id
					)
				)
			);
			
			if( is_wp_error( $response ) )
				return array( 'status' => false , 'msg' => 'خطا در اتصال به Payline' );
			else 
				return intval( $response['body'] ) === 1 ? array( 'status' => true , 'msg' => 'پرداخت تایید شده است.' ) : array( 'status' => false , 'msg' => 'پرداخت از طرف Payline تایید نشد.' );
		}
		
		
		public static function dayToSecounds($day=0){
			return $day * 60 * 60 * 24;
		}
		
		public static function ToSecounds($type,$val){
			switch($type){
				case('min'):
					return intval($val) * 60;
				break;
				case('hour'):
					return intval($val) * 60 * 60;
				break;
				case('day'):
					return intval($val) * 60 * 60 * 24;
				break;
				case('week'):
					return intval($val) * 60 * 60 * 24 * 7;
				break;
				case('mon'):
					return intval($val) * 60 * 60 * 24 * 30;
				break;
				case('year'):
					return intval($val) * 60 * 60 * 24 * 30 * 12;
				break;
			}
		}
		
		public function vip_group_account_charge(){
			global $wpdb,$avdb;
			if( isset($_POST['vip_charge_unit']) && isset($_POST['vip_charge_value']) ){
				if( current_user_can('administrator') ) {
					$in_secount = self::ToSecounds($_POST['vip_charge_unit'],$_POST['vip_charge_value']);
					$add = $wpdb->query("UPDATE ".$avdb->users." SET expire_date = expire_date + ".$in_secount);
					if( $add !== false )
						wp_redirect(admin_url('admin.php?page=av_group_increasing&message=success&added='.$in_secount));
					else
						wp_redirect(admin_url('admin.php?page=av_group_increasing&message=error'));
					exit;
				} else{
					wp_redirect(admin_url('admin.php?page=av_group_increasing&message=error'));
					exit;
				}
			}
		}
		
		public function vip_group_account_decharge(){
			global $wpdb,$avdb;
			if( isset($_POST['vip_charge_unit']) && isset($_POST['vip_decharge_value']) ){
				if( current_user_can('administrator') ) {
					$in_secount = self::ToSecounds($_POST['vip_charge_unit'],$_POST['vip_decharge_value']);
					$add = $wpdb->query("UPDATE ".$avdb->users." SET expire_date = expire_date - ".$in_secount." WHERE `expire_date` < NOW()");
					if( $add !== false )
						wp_redirect(admin_url('admin.php?page=av_group_lessen&message=success&added='.$in_secount));
					else
						wp_redirect(admin_url('admin.php?page=av_group_lessen&message=error'));
					exit;
				} else{
					wp_redirect(admin_url('admin.php?page=av_group_lessen&message=error'));
					exit;
				}
			} 
		}
		
		public static function getRealIp(){
			$ip = '';
			if (!empty($_SERVER['HTTP_CLIENT_IP'])){
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			}
			elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			}
			else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return $ip;
		}
		
		
		public function DeletePaymentItem(){
			global $wpdb,$avdb;
			if( isset( $_GET['action'] ) && $_GET['action'] == 'delete_payment_item' && isset( $_GET['id'] ) ){
				$query = $wpdb->query("DELETE FROM ".$avdb->payments." WHERE ID='".$_GET['id']."'");
				if( $query !== false )
					wp_redirect(admin_url('admin.php?page=av_payments&av_message=5'));
				else
					wp_redirect(admin_url('admin.php?page=av_payments&av_message=6&av_error_message'));
				exit;	
			}
		}
		
		public function user_email_by_id( $id ){
			global $wpdb;
			return $wpdb->get_var( "SELECT `user_email` FROM `".$wpdb->users."` WHERE ID = '".intval($id)."'" );
		}
		
		public function doHourlyCloseExpireUsers(){
			global $wpdb, $avdb, $av_settings;
			$seconds = self::dayToSecounds(2);
			$times = time() + $seconds;
			$TIME = time();
			$users = $wpdb->get_results( "SELECT user_ID FROM ".$avdb->users." WHERE expire_date <" . $times . " AND ".$TIME." < expire_date", ARRAY_A );
			$CloseUsers = array();
			foreach( $users as $key => $val ){
				$CloseUsers[ $val['user_ID'] ] = self::user_email_by_id( $val['user_ID'] );
			}
			foreach( $CloseUsers as $ID => $mail ){
				$user_data = get_userdata( $ID );
				$vipData = self::av_vip_stat_by_ID($ID);
				if( get_transient( "avHourlyEvenetFor_" . $ID ) === false ){
					do_action(
						'avHourlyEvenetCloseExpireUser' , 
						array( 
							"member-name" => $user_data->display_name,
							"member-mail" => $mail,
							"site-title" => get_bloginfo('name'),
							"site-url" => site_url(),
							"expire-jdate" => self::nice_jdate_from_time_stamp( self::vip_expire_by_userID($ID) ),
							"expire-hdate" => human_time_diff( self::vip_expire_by_userID($ID) , time() ),
							"start-jdate" => self::nice_jdate_from_time_stamp( strtotime(self::vip_start_by_userID($ID)) ),
							"start-hdate" => human_time_diff( strtotime(self::vip_start_by_userID($ID)) , time() ),
							"mail" => $mail,
							"phone" => $vipData['phone']
						)
					);
					//file_put_contents( 'ss.txt' , 'hi!' );
					set_transient( "avHourlyEvenetFor_" . $ID , time() , 60 * 60 * 24 );
				}
			}
		}

		public function DeleteExpiredUsers(){
			global $wpdb, $avdb;
			$now = time();
			$wpdb->query( "DELETE FROM ".$avdb->users." WHERE expire_date < " . $now );
		}
		
		
		public function mihanpalPaymentRequest( $pin , $price , $callback , $orderID = 123456 , $description = 'خرید اکانت ویژه' , $bank = 'mellat' ){
		
			$client = new SoapClient("http://mihanpal.com/index.php/payment2/wsdl");
			
			$au = $client->request( $pin , $price , $callback , $orderID , urlencode($description) , $bank );
			
			if(strlen($au) >=8) {
				return array( 'status' => true , 'msg' => $au );
			} else  {
				switch( intval($au) ) {
					case( $r === -1 ):
						return array( 'status' => false , 'msg' => 'پین درگاه میهن پال نامعتبر است.' );
					break;
					case( $r === -2 ):
						return array( 'status' => false , 'msg' => 'میهن پال به خاطر آی پی نامعتبر درخواست پرداخت را رد کرد.' );
					break;
					case( $r === -3 ):
						return array( 'status' => false , 'msg' => 'مبلغ تراکنش از مقدار حداقل میهن پال کمتر است.' );
					break;
					case( $r === -4 ):
						return array( 'status' => false , 'msg' => 'مبلغ تراکنش از مقدار حداکثر میهن پال بیشتر است.' );
					break;
					case( $r === -5 ):
						return array( 'status' => false , 'msg' => 'مبلغ ارسال شده به میهن پال نامعتبر است.' );
					break;
					case( $r === -6 ):
						return array( 'status' => false , 'msg' => 'میهن پال پاسخ داد که ارتباط با بانک برقرار نشد.' );
					break;
					case( $r === -7 ):
						return array( 'status' => false , 'msg' => 'درگاه از طرف میهن پال نامعتبر است.' );
					break;
					case( $r === -8 ):
						return array( 'status' => false , 'msg' => 'میهن پال پاسخ داد آی پی شما نا معتبر است.' );
					break;
					case( $r === -9 ):
						return array( 'status' => false , 'msg' => 'خطای ناشناخته از طرف میهن پال رخ داد.' );
					break;
					case( $r === -10 ):
						return array( 'status' => false , 'msg' => 'میهن پال: آدرس کال بک خالی است.' );
					break;
					default:
						return array( 'status' => false , 'msg' => 'ارتباط با میهن پال برقرار نشد.' . '<br/>' . $au  );
					break;
				}
			}	
		}
		
		public function jahanPayPaymentRequest( $api , $amount , $callbackUrl , $orderId = 123456 , $txt = 'خرید حساب کاربری ویژه' ){
			$client = new SoapClient("http://www.jahanpay.com/webservice?wsdl");
			$request = $client->requestpayment($api , $amount , $callbackUrl , $orderId , $txt);
			if( intval($request) > 0 )
				return array( 'status' => true , 'msg' => $request );
			else
				return array( 'status' => false , 'msg' => 'سیستم قادر به انجام تراکنش از طرف جهان پی نیست.' );
		}
		
		public function paylinePaymentRequest( $api , $amount , $redirect ){
			$response = wp_remote_post(
				'http://payline.ir/payment/gateway-send', 
				array(
					'body' => array( 
						'api' => $api, 
						'amount' => intval($amount) * 10, 
						'redirect' => $redirect, 
					)
				)
			);
			if( is_wp_error( $response ) ) {
				$output = array(
					"status" => false,
					"msg" => 'خطا در اتصال به پی لاین'
				);
			} else {
				
				$r = intval($response['body']);
				
				if( $r > 0 ){
					$output = array(
						"status" => true,
						"msg" => $r
					);
				} else {
					$output['status'] = false;
					switch( $r ) {
						case( $r === -1 ):
							$output['msg'] = 'API وارد شده در تنظیمات با API پی لاین سازگار نیست.';
						break;
						case( $r === -2 ):
							$output['msg'] = 'مقدار هزینه اشتباه است.';
						break;
						case( $r === -4 ):
							$output['msg'] = 'درگاهی با اطلاعات ارسالی یافت نشد.';
						break;
					}
					
				}
				
			}
			return $output;
		}
		
		
		
		public function paylineTestPaymentRequest( $api , $amount , $redirect ){
			$response = wp_remote_post(
				'http://payline.ir/payment-test/gateway-send', 
				array(
					'body' => array( 
						'api' => 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567', 
						'amount' => intval($amount) * 10, 
						'redirect' => $redirect, 
					)
				)
			);
			if( is_wp_error( $response ) ) {
				$output = array(
					"status" => false,
					"msg" => 'خطا در اتصال به پی لاین'
				);
			} else {
				
				$r = intval($response['body']);
				
				if( $r > 0 ){
					$output = array(
						"status" => true,
						"msg" => $r
					);
				} else {
					$output['status'] = false;
					switch( $r ) {
						case( $r === -1 ):
							$output['msg'] = 'API وارد شده در تنظیمات با API پی لاین سازگار نیست.';
						break;
						case( $r === -2 ):
							$output['msg'] = 'مقدار هزینه اشتباه است.';
						break;
						case( $r === -4 ):
							$output['msg'] = 'درگاهی با اطلاعات ارسالی یافت نشد.';
						break;
					}
					
				}
				
			}
			return $output;
		}
		
		
		public function zarinpalPaymentRequest( $merchantID , $amount , $redirect , $desc , $email, $mobile){
			$data = array(
					'MerchantID'		=>	$merchantID,
					'Amount'			=>	$amount,
					'Description'		=>	$desc,
					'Email'				=>	$email,
					'Mobile'			=>	$mobile,
					'CallbackURL'		=>	$redirect
			);
			$client = new SoapClient('https://ir.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
			$res = $client->PaymentRequest($data );

			switch( $res->Status ) {
					case '100' :
						return array( 'status' => true , 'msg' => $res->Authority );
					break;

					case  '-1' :
						return array( 'status' => false , 'msg' => __('اطلاعات ارسال شده به زرین پال ناقص هستند.','av') );
						break;

					case '-2' :
						return array( 'status' => false , 'msg' => __('وب سرویس نا معتبر است. (ممکن است آی پی سایت شما با آی پی ثبت شده برای درگاه زرین پال یکسان نباشند)','av') );
						break;

					case '-11' :
						return array( 'status' => false , 'msg' => __('تراکنش مورد نظر مرتبط با شما نیست.','av') );
						break;

					case '-4' :
						return array( 'status' => false , 'msg' => __('سطح تایید پذیرنده کمتراز سطح نقره ایست','av') );
						break;

					default:
						return array( 'status' => false , 'msg' => __('خطا در اتصال به درگاه زرین پال' ,'av'));
						break;

			}

		}
		
		public function av_after_payment( $ag , $data ){
			global $wpdb,$av_settings,$avdb;
		        $current_user = wp_get_current_user();

			if( $ag == 'arianpal' ) {

			    error_log('we are here ...');


				if( 99 === intval($data[0]) ){
					wp_die('انصراف از پرداخت');
				}				
				else if( 88 === intval($data[0]) ){
					wp_die('پرداخت موفقیت آمیز نبود.');
				}				
				else if( 77 === intval($data[0]) ){
					wp_die('منقضی شدن زمان پرداخت.');
				}				
				else if( 66 === intval($data[0]) ){
					wp_die('پرداخت قبلا انجام شده است.');
				}
				else if ( 100 === intval($data[0])  ) {

					$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[2] ) , ARRAY_A );

					try {
						$arianpal = new av_arianpal();
						$arianpal->verify_payment( $av_settings['arianpal_merchant_id'], $av_settings['arianpal_port_password'], $paymentData[ 'payment_price' ], $data[ 1 ] );

						$charge = avChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );

						do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );

						avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1], 'ag' => 'آرین پال', "paymenter_displayname" => $current_user->display_name ) );

						$html = '';
						$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
						$html .= '<br/>شماره پیگیری پرداخت شما: <strong>'.$data[1].'</strong> می باشد.';
						$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';

						wp_die( $html , 'پرداخت موفقیت آمیز' );
					} catch( Exception $e ) {
						wp_die( $e->getMessage() );
					}
				
				} else {
					
					wp_die( 'اطلاعات دریافت شده از آرین پال نا مشخص هستند.'  );
					
				}

			
				
				
	
			
			} else if ( $ag == 'payline' ) {
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
				
				$verify = $this->payline_payment_verify( $data[0] , $data[1] );
				
				
				if( $verify['status'] && isset($_SESSION['av_payLine_idGet']) && $_SESSION['av_payLine_idGet'] == $data[0] ){
				
					$charge = avChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1] , 'ag' => 'پی لاین' ) );
					
					unset( $_SESSION['av_payLine_idGet'] );
	
					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[1].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' );
				
				}
				
				
			
			} else if ( $ag == 'test' ) {
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
				
				$verify = $this->payline_test_payment_verify( $data[0] , $data[1] );
				
				
				if( $verify['status'] && isset($_SESSION['av_payLineTest_idGet']) && $_SESSION['av_payLineTest_idGet'] == $data[0] ){
				
					$charge = avTestChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1] , 'ag' => 'درگاه آزمایشی' ) );
					
					unset( $_SESSION['av_payLineTest_idGet'] );
	
					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[1].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' );
				
				}
				
				
			
			} else if ( $ag == 'zarinpal' ) {
		
		
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[1] ) , ARRAY_A );
		
				$verify = $this->zarinpal_payment_verify( $av_settings['zarinpal_merchantID'] , $paymentData['payment_price'] , $data[1] );
				
	
				if( $verify === true && isset($_SESSION['av_zarinPal_reqID']) && $_SESSION['av_zarinPal_reqID'] == $data[1] ){
				
					$charge = avChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[0] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[0] , 'ag' => 'زرین پال' ) );
					
					unset($_SESSION['av_zarinPal_reqID']);

					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[0].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
				
				

					wp_die( '<span title="'.$verify.'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
					
				}
				
				
				
				
				
				
			} else if ( $ag == 'mihanpal' ) {
		
		
				
				
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
		
				$verify = $this->mihanpal_payment_verify( $av_settings['mihanpal_pin'] , $data[0]  , $paymentData['payment_price'] );
				
	
				if( $verify === true && isset($_SESSION['av_mihanPal_reqID']) && $_SESSION['av_mihanPal_reqID'] == $data[0] ){
				
					$charge = avChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[0] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[0] , 'ag' => 'میهن پال' ) );
					
					unset($_SESSION['av_mihanPal_reqID']);

					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[0].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( '<span title="'.$verify.'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
					
				}
				
				
				
				
				
			} else if ( $ag == 'jahanpay' ) {	
			
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
			
				$verify = $this->jahanpay_payment_verify( $av_settings['jahanpay_api'] , $data[0]  , $paymentData['payment_price'] );
				
				if( $verify['status'] === true && isset($_SESSION['av_jahanPay_reqID']) && $_SESSION['av_jahanPay_reqID'] == $data[0] ){
					
				
					$charge = avChargeAccount( get_current_user_id() , $paymentData['charge_days'] , $paymentData['user_phone'] );
						
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[0] ) );
						
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[0] , 'ag' => 'جهان پی' ) );
						
					unset($_SESSION['av_jahanPay_reqID']);

					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[0].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
						
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
					
					wp_die( '<span title="'.$verify['msg'].'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
						
				}
			
			}
		

			if( $ag == 'parspal' ) {
			

				if( -99 === intval($data[0]) ){
					wp_die('انصراف از پرداخت');
				}				
				else if( -88 === intval($data[0]) ){
					wp_die('پرداخت موفقیت آمیز نبود.');
				}				
				else if( -77 === intval($data[0]) ){
					wp_die('منقضی شدن زمان پرداخت.');
				}				
				else if( -66 === intval($data[0]) ){
					wp_die('پرداخت قبلا انجام شده است.');
				}
				else if ( 100 === intval($data[0])  ) {
				
					$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[2] ) , ARRAY_A );
					
					$parspal_verify = $this->parspal_payment_verify( $paymentData['payment_price'] , $data[1] );
					
					if( $parspal_verify['status'] ){
						
						
						
						$charge = avChargeAccount( $current_user->ID , $paymentData['charge_days'] , $paymentData['user_phone'] );
						
						do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );
						
						avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1], 'ag' => 'پارس پال', "paymenter_displayname" => $current_user->display_name ) );
						
						$html = '';
						$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
						$html .= '<br/>شماره پیگیری پرداخت شما: <strong>'.$data[1].'</strong> می باشد.';
						$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
						
						
						
						wp_die( $html , 'پرداخت موفقیت آمیز' );
						
					
				
					} else {
				
						wp_die( 'پرداخت شما از طرف Parspal تایید نشد!' );
				
					}
				
				
				} else {
					
					wp_die( 'اطلاعات دریافت شده از پارس پال نا مشخص هستند.'  );
					
				}

			
				
				
	
			
			} else if ( $ag == 'payline' ) {
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
				
				$verify = $this->payline_payment_verify( $data[0] , $data[1] );
				
				
				if( $verify['status'] && isset($_SESSION['av_payLine_idGet']) && $_SESSION['av_payLine_idGet'] == $data[0] ){
				
					$charge = avChargeAccount( $current_user->ID , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1] , 'ag' => 'پی لاین', "paymenter_displayname" => $current_user->display_name  ) );
					
					unset( $_SESSION['av_payLine_idGet'] );
	
					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[1].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' );
				
				}
				
				
			
			} else if ( $ag == 'test' ) {
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
				
				$verify = $this->payline_test_payment_verify( $data[0] , $data[1] );
				
				
				if( $verify['status'] && isset($_SESSION['av_payLineTest_idGet']) && $_SESSION['av_payLineTest_idGet'] == $data[0] ){
				
					$charge = avTestChargeAccount( $current_user->ID , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[1] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[1] , 'ag' => 'درگاه آزمایشی', "paymenter_displayname" => $current_user->display_name  ) );
					
					unset( $_SESSION['av_payLineTest_idGet'] );
	
					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[1].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' );
				
				}
				
				
			
			} else if ( $ag == 'zarinpal' ) {
		
				if($_GET['Status'] == 'OK') {
var_dump($_POST);var_dump($_GET);
						$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[1] ) , ARRAY_A );
				
						$verify = $this->zarinpal_payment_verify( $av_settings['zarinpal_merchantID'] , $paymentData['payment_price'] , $data[1] );
						
			
						if( $verify->Status == 100 && isset($_SESSION['av_zarinPal_reqID']) && $_SESSION['av_zarinPal_reqID'] == $data[1] ){
						
							$charge = avChargeAccount($current_user->ID, $paymentData['charge_days'], $paymentData['user_phone']);

					do_action('av_complete_charge', array($paymentData, $charge, 'time_data' => $charge, 'refNumber' =>  $verify->RefID));

					avInsertPayment(array('price' => $paymentData['payment_price'], 'ref' =>  $verify->RefID, 'ag' => __('زرین پال', 'wpcar-advanced-vip'), "paymenter_displayname" => $current_user->display_name ));

					
							unset($_SESSION['av_zarinPal_reqID']);

							$html = '';
							$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
							$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$verify->RefID.'</strong> می باشد.';
							$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
							
							wp_die( $html , 'پرداخت موفقیت آمیز' );
					
					
					} else {
					
						wp_die( '<span title="'.$verify.'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
						
					}
				}else {
					wp_die('<span title="' . 'انصراف کاریر' . '">' . __('شارژ حساب شما امکان پذیر نیست. (انصراف کاریر )', 'wpcar-advanced-vip') . '</span>');
				}
				
				
				
				
				
			} else if ( $ag == 'mihanpal' ) {
		
		
				
				
				
				
				
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
		
				$verify = $this->mihanpal_payment_verify( $av_settings['mihanpal_pin'] , $data[0]  , $paymentData['payment_price'] );
				
	
				if( $verify === true && isset($_SESSION['av_mihanPal_reqID']) && $_SESSION['av_mihanPal_reqID'] == $data[0] ){
				
					$charge = avChargeAccount( $current_user->ID() , $paymentData['charge_days'] , $paymentData['user_phone'] );
					
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[0] ) );
					
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[0] , 'ag' => 'میهن پال', "paymenter_displayname" => $current_user->display_name  ) );
					
					unset($_SESSION['av_mihanPal_reqID']);

					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[0].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
					
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
				
					wp_die( '<span title="'.$verify.'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
					
				}
				
				
				
				
				
			} else if ( $ag == 'jahanpay' ) {	
			
				$paymentData = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $avdb->temp WHERE payment_id = %s" , $data[0] ) , ARRAY_A );
			
				$verify = $this->jahanpay_payment_verify( $av_settings['jahanpay_api'] , $data[0]  , $paymentData['payment_price'] );
				
				if( $verify['status'] === true && isset($_SESSION['av_jahanPay_reqID']) && $_SESSION['av_jahanPay_reqID'] == $data[0] ){
					
				
					$charge = avChargeAccount( $current_user->ID , $paymentData['charge_days'] , $paymentData['user_phone'] );
						
					do_action( 'av_complete_charge' , array( $paymentData , $charge , 'time_data' => $charge, 'refNumber' => $data[0] ) );
						
					avInsertPayment( array( 'price' => $paymentData['payment_price'] , 'ref' => $data[0] , 'ag' => 'جهان پی', "paymenter_displayname" => $current_user->display_name  ) );
						
					unset($_SESSION['av_jahanPay_reqID']);

					$html = '';
					$html .= $charge['status'] === true ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
					$html .= '<br/>شماره پیگیری پرداخت شما <strong>'.$data[0].'</strong> می باشد.';
					$html .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';
						
					wp_die( $html , 'پرداخت موفقیت آمیز' );
				
				
				} else {
					
					wp_die( '<span title="'.$verify['msg'].'">' . 'شارژ حساب شما امکان پذیر نیست. (دلایل امنیتی)' . '</span>' );
						
				}
			
			}
		
		}
		
		
		public function av_before_payment( $ag , $data ){
			global $wpdb,$av_settings;
			
			$user_id = get_current_user_id();
			$current_user = wp_get_current_user();
			
			if( $ag == 'arianpal' ) {
				
				if( ! empty( $av_settings['arianpal_merchant_id'] ) || ! empty( $av_settings['arianpal_port_password'] ) ) {
					
					$insert = $wpdb->insert(
						$wpdb->prefix.'av_temporary',
						array(
							'payment_price' => $data[0],
							'user_id'       => $current_user->ID,
							'charge_days'   => $data[1],
							'payment_id'    => str_replace( '.' , '' , microtime(true) ),
							'agency'        => 'arianpal',
							'user_phone'    => $data[2]
						)
					);
				
					if( $insert === false ) {
					
						wp_die('خطا در ثبت سفارش');

					} else {
					
						$inserted = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."av_temporary"."` WHERE ID='".$wpdb->insert_id."'",ARRAY_A);

						try {
							$returnPath     = site_url(). '/?av_after_payment=true&agency=arianpal';
							$merchantId     = $av_settings[ 'arianpal_merchant_id' ];
							$password       = $av_settings[ 'arianpal_port_password' ];
							$resNumber      = $inserted[ 'payment_id' ];
							$price          =  $inserted[ 'payment_price' ];
							$paymenter      = $current_user->display_name;
							$email          = $current_user->user_email;
							$mobile         = '-';
							$description    = '';

							$arianpal = new av_arianpal();
							$paymentPath = $arianpal->request_payment($merchantId, $password, $price, $returnPath, $resNumber, $paymenter, $email, $mobile, $description);
							$html =  "<html><head><title>در حال اتصال به درگاه ...</title></head><body onload=\"javascript:window.location='{$paymentPath}'\">در حال اتصال به درگاه ... لطفا منتظر بمانید ...</body></html>";
							wp_die( $html );
						} catch( Exception $e ) {
							wp_die( $e->getMessage(), 'خطا در اتصال به درگاه' );
						}


					}
			
				} else {

					wp_die( 'اطلاعات درگاه آرین پال صحیح نیست.' );

				}

			
			} else if ( $ag == 'payline' ) {
				
				if( empty( $av_settings['payline_api'] ) ) {
					
					wp_die( 'API پی لاین وارد نشده است.' );
				
				} else {
				
					$requestPayment = $this->paylinePaymentRequest( $av_settings['payline_api'] , $data[0] , urldecode(site_url().'/?av_after_payment=true&agency=payline') );
					
					if( $requestPayment['status'] === true ) {
						
						$insert = $wpdb->insert(
							$wpdb->prefix.'av_temporary',
							array(
								'payment_price' => $data[0],
								'user_id' => $user_id,
								'charge_days' => $data[1],
								'payment_id' => $requestPayment['msg'],
								'agency' => 'payline',
								'user_phone' => $data[2]
							)
						);
						if ( $insert === false ) {
							
							wp_die( 'خطا در ثبت سفارش در پایگاه داده' );
							
						} else {
							
							$_SESSION['av_payLine_idGet'] = $requestPayment['msg'];
							
							wp_redirect( 'http://payline.ir/payment/gateway-' . $requestPayment['msg'] );
							
							exit;
						
						}
					
					} else {
						
						wp_die($requestPayment['msg']);
						
					}
				
					
				}
			
		
			}  else if ( $ag == 'test' ) {
				
				
					$requestPayment = $this->paylineTestPaymentRequest( '' , $data[0] , urldecode(site_url().'/?av_after_payment=true&agency=testpayment') );
					
					if( $requestPayment['status'] === true ) {
						
						$insert = $wpdb->insert(
							$wpdb->prefix.'av_temporary',
							array(
								'payment_price' => $data[0],
								'user_id' => $user_id,
								'charge_days' => $data[1],
								'payment_id' => $requestPayment['msg'],
								'agency' => 'payline',
								'user_phone' => $data[2]
							)
						);
						if ( $insert === false ) {
							
							wp_die( 'خطا در ثبت سفارش در پایگاه داده' );
							
						} else {
							
							$_SESSION['av_payLineTest_idGet'] = $requestPayment['msg'];
							
							wp_redirect( 'http://payline.ir/payment-test/gateway-' . $requestPayment['msg'] );
							
							exit;
						
						}
					
					} else {
						
						wp_die($requestPayment['msg']);
						
					}
				
					
				
			
		
			} else if ( $ag == 'zarinpal' ) {
				
				
				if( empty( $av_settings['zarinpal_merchantID'] ) ) {
					wp_die( 'شناسه درگاه زرین پال در تنظیمات وارد نشده است!' );
				} else {
				
				
					$paymentRequest = $this->zarinpalPaymentRequest( $av_settings['zarinpal_merchantID'] , $data[0] , site_url().'/?av_after_payment=true&agency=zarinpal' , 'خرید اکانت ویژه');

					
					if( $paymentRequest['status'] === true ) {
						
						$insert = $wpdb->insert(
							$wpdb->prefix.'av_temporary',
							array(
								'payment_price' => $data[0],
								'user_id' => $user_id,
								'charge_days' => $data[1],
								'payment_id' => $paymentRequest['msg'],
								'agency' => 'zarinpal',
								'user_phone' => $data[2]
							)
						);
						
						if ( $insert === false ) {
							
							wp_die( 'خطا در ثبت سفارش در پایگاه داده' );
							
						} else {
						
							$_SESSION['av_zarinPal_reqID'] = $paymentRequest['msg'];
						
							wp_redirect( 'https://www.zarinpal.com/users/pay_invoice/' . $paymentRequest['msg'] );
							
							exit;
						
						}
					
					} else {
						
						wp_die($paymentRequest['msg']);
						
					}
	
				
				
				}
			
			} else if ( $ag == 'mihanpal' ) {
			
				
				if( empty( $av_settings['mihanpal_pin'] ) ) {
				
				
					wp_die( 'پین اختصاصی درگاه میهن پال وارد نشده است!.' );
					
					
				} else {
				
					$paymentRequest = $this->mihanpalPaymentRequest( $av_settings['mihanpal_pin'] , $data[0] , site_url().'/?av_after_payment=true&agency=mihanpal' , 123456 , 'خرید اکانت ویژه' );

					if( $paymentRequest['status'] === true ) {
					
						
						
						$insert = $wpdb->insert(
							$wpdb->prefix.'av_temporary',
							array(
								'payment_price' => $data[0],
								'user_id' => $user_id,
								'charge_days' => $data[1],
								'payment_id' => $paymentRequest['msg'],
								'agency' => 'mihanpal',
								'user_phone' => $data[2]
							)
						);
						
						if ( $insert === false ) {
							
							wp_die( 'خطا در ثبت سفارش در پایگاه داده' );
							
						} else {
						
						
							$_SESSION['av_mihanPal_reqID'] = $paymentRequest['msg'];
							
						
							wp_redirect( 'http://mihanpal.com/index.php/paymentgateway/?au=' . $paymentRequest['msg'] );
							
							exit;
						
						}

						
						
						
					} else {
						
						wp_die( $paymentRequest['msg'] );
					
					}
					
					
					

				}
				
				
			} else if ( $ag == 'jahanpay' ) {
		
				if( empty( $av_settings['jahanpay_api'] ) ) {
				
					wp_die( 'API جهان پی وارد نشده است!' );
				} else {
				
					$paymentRequest = $this->jahanPayPaymentRequest( $av_settings['jahanpay_api'] , $data[0] , site_url().'/?av_after_payment=true&agency=jahanpay' );
					
					if( $paymentRequest['status'] === true ) {
					
						$insert = $wpdb->insert(
							$wpdb->prefix.'av_temporary',
							array(
								'payment_price' => $data[0],
								'user_id' => $user_id,
								'charge_days' => $data[1],
								'payment_id' => $paymentRequest['msg'],
								'agency' => 'jahanpay',
								'user_phone' => $data[2]
							)
						);
						
						if ( $insert === false ) {
							
							wp_die( 'خطا در ثبت سفارش در پایگاه داده' );
							
							
						} else {
						
						
							$_SESSION['av_jahanPay_reqID'] = $paymentRequest['msg'];
							
						
							wp_redirect( 'http://www.jahanpay.com/pay_invoice/' . $paymentRequest['msg'] );
							
							exit;
						
						}
					
					
					} else {
						
						wp_die( $paymentRequest['msg'] );
					
					}
				
				
				}
		
			}
		
		}
		
	}