<?php

	if( ! defined('ABSPATH') ) die();

	$avdb = new advanced_vip_db;

	$avEcrypt = new av_ecrypt;

	$av_settings = stripslashes_deep( @unserialize( get_option('av_settings') ) );

	$av_vip_dir = ABSPATH . @$av_settings['protected_files_dir'] . '/';

	$current_user = wp_get_current_user();

    function isBotDetected() {

        if ( preg_match('/abacho|accona|AddThis|AdsBot|ahoy|AhrefsBot|AISearchBot|alexa|altavista|anthill|appie|applebot|arale|araneo|AraybOt|ariadne|arks|aspseek|ATN_Worldwide|Atomz|baiduspider|baidu|bbot|bingbot|bing|Bjaaland|BlackWidow|BotLink|bot|boxseabot|bspider|calif|CCBot|ChinaClaw|christcrawler|CMC\/0\.01|combine|confuzzledbot|contaxe|CoolBot|cosmos|crawler|crawlpaper|crawl|curl|cusco|cyberspyder|cydralspider|dataprovider|digger|DIIbot|DotBot|downloadexpress|DragonBot|DuckDuckBot|dwcp|EasouSpider|ebiness|ecollector|elfinbot|esculapio|ESI|esther|eStyle|Ezooms|facebookexternalhit|facebook|facebot|fastcrawler|FatBot|FDSE|FELIX IDE|fetch|fido|find|Firefly|fouineur|Freecrawl|froogle|gammaSpider|gazz|gcreep|geona|Getterrobo-Plus|get|girafabot|golem|googlebot|\-google|grabber|GrabNet|griffon|Gromit|gulliver|gulper|hambot|havIndex|hotwired|htdig|HTTrack|ia_archiver|iajabot|IDBot|Informant|InfoSeek|InfoSpiders|INGRID\/0\.1|inktomi|inspectorwww|Internet Cruiser Robot|irobot|Iron33|JBot|jcrawler|Jeeves|jobo|KDD\-Explorer|KIT\-Fireball|ko_yappo_robot|label\-grabber|larbin|legs|libwww-perl|linkedin|Linkidator|linkwalker|Lockon|logo_gif_crawler|Lycos|m2e|majesticsEO|marvin|mattie|mediafox|mediapartners|MerzScope|MindCrawler|MJ12bot|mod_pagespeed|moget|Motor|msnbot|muncher|muninn|MuscatFerret|MwdSearch|NationalDirectory|naverbot|NEC\-MeshExplorer|NetcraftSurveyAgent|NetScoop|NetSeer|newscan\-online|nil|none|Nutch|ObjectsSearch|Occam|openstat.ru\/Bot|packrat|pageboy|ParaSite|patric|pegasus|perlcrawler|phpdig|piltdownman|Pimptrain|pingdom|pinterest|pjspider|PlumtreeWebAccessor|PortalBSpider|psbot|rambler|Raven|RHCS|RixBot|roadrunner|Robbie|robi|RoboCrawl|robofox|Scooter|Scrubby|Search\-AU|searchprocess|search|SemrushBot|Senrigan|seznambot|Shagseeker|sharp\-info\-agent|sift|SimBot|Site Valet|SiteSucker|skymob|SLCrawler\/2\.0|slurp|snooper|solbot|speedy|spider_monkey|SpiderBot\/1\.0|spiderline|spider|suke|tach_bw|TechBOT|TechnoratiSnoop|templeton|teoma|titin|topiclink|twitterbot|twitter|UdmSearch|Ukonline|UnwindFetchor|URL_Spider_SQL|urlck|urlresolver|Valkyrie libwww\-perl|verticrawl|Victoria|void\-bot|Voyager|VWbot_K|wapspider|WebBandit\/1\.0|webcatcher|WebCopier|WebFindBot|WebLeacher|WebMechanic|WebMoose|webquest|webreaper|webspider|webs|WebWalker|WebZip|wget|whowhere|winona|wlm|WOLP|woriobot|WWWC|XGET|xing|yahoo|YandexBot|YandexMobileBot|yandex|yeti|Zeus/i', $_SERVER['HTTP_USER_AGENT'])
        ) {
            return true; // 'Above given bots detected'
        }

        return false;

    }

    function av_is_post_vip($post_id = '') {
        if (!$post_id || !absint($post_id)) {
            $post_id = get_the_ID();
        }

        return get_post_meta($post_id, "av_show_post_only_for_vip", true) == 'yes';
    }

	function av_custom_auth( $user, $password ){
		$check = wp_authenticate_username_password( NULL, $user, $password );
		return is_wp_error( $check ) ? false : $check->data->ID;
	}

	function av_user_vip_check_by_id( $id ){
		global $avdb,$wpdb;
		if(
			$wpdb->get_var("SELECT user_ID FROM $avdb->users WHERE user_ID='".$id."'") === null ||
			$wpdb->get_var("SELECT expire_date FROM $avdb->users WHERE user_ID='".$id."'") < time()
		){
			return false;
		} else{
			return true;
		}
	}

	function av_get_user_vip_data_by_id( $id ){
		global $avdb,$wpdb;
		return $wpdb->get_row( "SELECT * FROM ".$avdb->users." WHERE user_ID = '".$id."'" , ARRAY_A );
	}

	function av_user_vip_check_by_username_password( $user, $password ){
		global $av_settings;


		$check = wp_authenticate_username_password( NULL, $user, $password );

		if( is_wp_error( $check ) )
			return false;

		if( count( array_intersect( (array) $check->roles , (array) @ $av_settings['default_vip_roles'] ) ) > 0 )
			return true;

		return av_user_vip_check_by_id( $check->data->ID );

	}


	function av_file_path_by_id( $id ){
		global $av_vip_dir, $wpdb, $avdb;
		$query = $wpdb->get_row( $wpdb->prepare("SELECT encypted_name,file_type FROM ".$avdb->files." WHERE ID = '%d'" , $id) , ARRAY_A );
		return $av_vip_dir . $query['encypted_name'] . '.' . $query['file_type'];
	}

	function av_file_by_id( $id ){
		global $av_vip_dir, $wpdb, $avdb;
		$file = $wpdb->get_row( $wpdb->prepare("SELECT * FROM ".$avdb->files." WHERE ID = '%d'", $id) , ARRAY_A );
		return $file;
	}

	$av_httpDL = new av_httpdownload;
	$advanced_vip = new advanced_vip;

	$user_found = $wpdb->get_var("SELECT `user_ID` FROM $avdb->users WHERE user_ID='".get_current_user_id()."'") ? true : false;
    $is_expired = $wpdb->get_var("SELECT expire_date FROM $avdb->users WHERE user_ID='".get_current_user_id()."'") < current_time('timestamp');
    $is_default_vip_roles = count( array_intersect( (array) $current_user->roles , (array) @ $av_settings['default_vip_roles'] ) ) > 0;

	if ( !$user_found || !$is_expired || $is_default_vip_roles ) {
		$av_current_user_vip = true;
	} else{
		$av_current_user_vip = false;
	}


	register_activation_hook( av_plugin_file , 'av_install' );
	function av_install(){
		require_once( av_func_dir . 'install.php' );
	}
