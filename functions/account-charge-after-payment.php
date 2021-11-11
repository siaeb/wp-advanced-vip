<?php
	if( ! defined('ABSPATH') ) die();
	//add_action( 'parse_request' , 'av_after_payment' );
	add_action( 'parse_request' , 'avAfterPayment' );

	
	function avAfterPayment(){
		global $wpdb, $avdb, $current_user, $av_settings;
		if( isset($_GET['av_after_payment']) && $_GET['av_after_payment'] == 'true' && isset($_GET['agency']) ){
	
			if( $_GET['agency'] == 'arianpal' ) {

                if( isset($_POST['status']) && !empty($_POST['status']) ) {
                    do_action( 'av_after_payment' , 'arianpal' , array( $_POST['status'] , $_POST['receipt_number'] , $_POST['reserve_id'], $_POST['order_id'] ) );
                }

			} else if ( $_GET['agency'] == 'payline' ){
				
				if( ! empty( $_POST['id_get'] ) && ! empty( $_POST['trans_id'] ) )
					do_action( 'av_after_payment' , 'payline' , array( $_POST['id_get'] , $_POST['trans_id'] ) );
			
			} else if ( $_GET['agency'] == 'testpayment' ){
				
				if( ! empty( $_POST['id_get'] ) && ! empty( $_POST['trans_id'] ) )
					do_action( 'av_after_payment' , 'test' , array( $_POST['id_get'] , $_POST['trans_id'] ) );
			
			} else if ( $_GET['agency'] == 'zarinpal' ){
				
				if( ! empty( $_GET['Authority'] ) && ! empty( $_GET['Status'] ) )
					do_action( 'av_after_payment' , 'zarinpal' , array(  $_GET['Status'],$_GET['Authority']) );
			
			} else if ( $_GET['agency'] == 'mihanpal' ){
				
				if( ! empty( $_GET['au'] ) && ! empty( $_GET['order_id'] ) )
					do_action( 'av_after_payment' , 'mihanpal' , array( $_GET['au'] , $_GET['order_id'] ) );
			
			} else if ( $_GET['agency'] == 'jahanpay' ){
				
				if( ! empty( $_GET["au"] ) )
					do_action( 'av_after_payment' , 'jahanpay' , array( $_GET["au"] ) );
			
			}
			
	
	
		}
	}
	
	
	function av_after_payment(){
		global $wpdb, $avdb, $current_user, $av_settings, $advanced_vip;

		if( isset($_GET['av_after_payment']) && $_GET['av_after_payment'] == 'true' && isset($_POST['status']) && isset($_POST['refnumber']) && isset($_POST['resnumber']) ){
			if( -99 === intval($_POST['status']) ){
				wp_die('انصراف از پرداخت');
			}				
			if( -88 === intval($_POST['status']) ){
				wp_die('پرداخت موفقیت آمیز نبود.');
			}				
			if( -77 === intval($_POST['status']) ){
				wp_die('منقضی شدن زمان پرداخت.');
			}				
			if( -66 === intval($_POST['status']) ){
				wp_die('پرداخت قبلا انجام شده است.');
			}				
			if( 100 === intval($_POST['status']) ){
				$html = '';
				if( $wpdb->get_var("SELECT * FROM $avdb->temp WHERE ID='".$_POST['resnumber']."'") !== null ){
					$tempData = $wpdb->get_row("SELECT * FROM $avdb->temp WHERE ID='".$_POST['resnumber']."'",ARRAY_A);
					$payment_check = $advanced_vip->parspal_payment_verify( $tempData['payment_price'], $_POST['refnumber'] );
					$add_date = advanced_vip::dayToSecounds( $tempData['charge_days'] );
					$current_user_vip_data = advanced_vip::av_current_user_vip_stat();
					
					// Check For Secure Payment
					if( $payment_check ){
					
						// Check if this is first purchase from user
						
						// There was no user in table
						if( $current_user_vip_data === null ){
						
							$update_ = $wpdb->insert( $avdb->users, array(
								'user_ID' => get_current_user_id(),
								'start_date' => current_time('mysql'),
								'expire_date' => $add_date+time()
							));
							
							$_mail_start_jdate = advanced_vip::nice_jdate_from_time_stamp( time() );
							$_mail_expire_jdate = advanced_vip::nice_jdate_from_time_stamp( $add_date + time() );
							
							
						} else {
							// There is a account of this user already
							$expire_date = $current_user_vip_data['expire_date'];

							$_mail_start_jdate = advanced_vip::nice_jdate_from_time_stamp( strtotime($current_user_vip_data['start_date']) );

							// Check if account is not expired
							if( $expire_date >= time() ){
							
								$update_ = $wpdb->update(
									$avdb->users,
									array(
										'expire_date' => $add_date+$expire_date
									),
									array(
										'user_ID' => get_current_user_id()
									)
								);
								
								$_mail_expire_jdate = advanced_vip::nice_jdate_from_time_stamp( $add_date+$expire_date );
								
							} else{
							
								$update_ = $wpdb->update(
									$avdb->users, 
									array(
										'expire_date' => $add_date+time()
									),
									array(
										'user_ID' => get_current_user_id()
									)
								);
								
								$_mail_expire_jdate = advanced_vip::nice_jdate_from_time_stamp( $add_date+time() );
								
							}
						}
						
						$final_status = '';
						$final_status .= $update_ === 1 ? 'پرداخت موفقیت آمیز بود و اکانت شما شارژ شد.' : 'پرداخت موفقیت آمیز بود، اما به نظر می رسد حساب کاربری شما شارژ نشده است. لطفا با مدیریت سایت تماس بگیرید.';
						$final_status .= '<br/>شماره پیگیری پرداخت شما: <strong>'.$_POST['refnumber'].'</strong> می باشد.';
						$final_status .= '<br/> <a href="'.site_url().'">بازگشت به سایت</a>';

						
						$mail_custom_message = str_replace(
							array(
								'{member-name}',
								'{start-jdate}',
								'{expire-jdate}',
								'{site-title}',
								'{site-url}',
								'{refNumber}',
								'{payment-cost}'
							),
							array(
								$current_user->data->display_name,
								$_mail_start_jdate,
								$_mail_expire_jdate,
								get_bloginfo('name'),
								site_url(),
								$_POST['refnumber'],
								$tempData['payment_price']
							),
							$av_settings['vip_register_mail_template']
						);
						
						$site_url = parse_url( site_url() );
						$site_url = $site_url['host'];
						
						$headers  = 'From: no-reply@'.$site_url. "\r\n" .
							'MIME-Version: 1.0' . "\r\n" .
							'Content-type: text/html; charset=utf-8' . "\r\n" .
							'X-Mailer: PHP/' . phpversion();
						
						@mail( $current_user->data->user_email, $av_settings['vip_register_mail_subject'], $mail_custom_message, $headers);
						
						if( function_exists('avSendSMS') && isset( $av_settings['sms_on_vip_start'] ) && isset($tempData['user_phone']) && $tempData['user_phone'] !== 'null' ){
		
		
							avSendSMS( str_replace( array(
								'{member-name}',
								'{start-jdate}',
								'{expire-jdate}',
								'{expire-human-date}',
								'{site-title}',
								'{site-url}',
								'{refNumber}',
								'{payment-cost}'
							) , array(
							
								$current_user->data->display_name,
								$_mail_start_jdate,
								$_mail_expire_jdate,
								human_time_diff( time() , time() + $add_date ),
								get_bloginfo('name'),
								site_url(),
								$_POST['refnumber'],
								$tempData['payment_price']

							) , $av_settings['sms_on_vip_start_template'] ) , $tempData['user_phone'] );
							
							
						}
						
						wp_die($final_status,'پرداخت موفقیت آمیز');
					} else {
						wp_die('به نظر خطایی پیش آمده است، با مدیریت سایت تماس بگیرید.');
					}
				}
			}				
		}
	}