<?php
/**
 * @package SpeakAbout
 * @version 1.1
 */
/*
Plugin Name: SpeakAbout - Inline comments
Plugin URI: https://speakabout.io/
Description: Connect with your blog readers through an inline highlighting comments tool.
Author: Ben Reimer
Version: 1.1
Author URI: https://www.benreimer.design
All Rights Reserved. 
*/


//ENQUEUE MY JS
function speakabout_enqueue_script() { 
	wp_enqueue_script('rangycore', plugin_dir_url(__FILE__) . 'rangy-core.js');
	wp_enqueue_script('rangyclass', plugin_dir_url(__FILE__) . 'rangy-classapplier.js');
	wp_enqueue_script('rangyhighlighter', plugin_dir_url(__FILE__) . 'rangy-highlighter.js');
	wp_enqueue_script('index', plugin_dir_url(__FILE__) . 'index.js', array('jquery'));
	wp_enqueue_style('speakaboutstyle', plugin_dir_url(__FILE__) . 'speak-about-style.css');
	wp_localize_script( 'index', 'sa_ajax', array( 
	'ajaxurl' => admin_url( 'admin-ajax.php')
	));
}
add_action('wp_enqueue_scripts', 'speakabout_enqueue_script');


/* CRON JOB 
 ----------------------------- */

add_filter( 'cron_schedules', 'speakabout_three_hours_interval' );
 
function speakabout_three_hours_interval( $schedules ) {
    $schedules['three_hours'] = array(
        'interval' => 10800,
        'display'  => esc_html__( 'Every Three Hours' ),
    );
    return $schedules;
}

add_action( 'speakabout_cron_hook', 'speakabout_cron_exec' );

if ( ! wp_next_scheduled( 'speakabout_cron_hook' ) ) {
    wp_schedule_event( time(), 'three_hours', 'speakabout_cron_hook');
}

function speakabout_cron_exec(){
	speakabout_build_feedback_emails();
}



/* BUILD THE DATABASE
 ----------------------------- */
global $speakabout_db_version;
$speakabout_db_version = '1.0';

function speakabout_install(){
	global $wpdb; 
	global $speakabout_db_version;

	$table_name = $wpdb->prefix . 'speakabout';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		commenter_id text NOT NULL,
		item_id text NOT NULL,
		highlight text NOT NULL,
		highlight_with_context text NOT NULL,
		comment text NOT NULL,
		page_name text NOT NULL,
		page_url text NOT NULL,
		has_been_emailed boolean NOT NULL default 0,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'speakabout_db_version', $speakabout_db_version );
	
	$installed_ver = get_option( "speakabout_db_version" );

	if ( $installed_ver != $speakabout_db_version ) {
		//update the db if necessary, code here for future versions

		// $sql = "CREATE TABLE $table_name (
		// 	id mediumint(9) NOT NULL AUTO_INCREMENT,
		// 	time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		// 	commenter_id text NOT NULL,
		// 	highlight text NOT NULL,
		// 	highlight_with_context text NOT NULL,
		// 	comment text NOT NULL,
		// 	page_name text NOT NULL,
		// 	page_url text NOT NULL,
		// 	has_been_emailed boolean NOT NULL default 0,
		// 	PRIMARY KEY  (id)
		// ) $charset_collate;";

		// require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		// dbDelta( $sql );
		
		update_option(  'speakabout_db_version', $speakabout_db_version  );
	}
}

function speakabout_update_db_check() {
    global $speakabout_db_version;
    if ( get_site_option( 'speakabout_db_version' ) != $speakabout_db_version ) {
        speakabout_install();
	}
}
add_action( 'plugins_loaded', 'speakabout_update_db_check' );
register_activation_hook( __FILE__, 'speakabout_install' );


/* KSES
 ----------------------------- */
 function speakabout_wp_kses() {
	return array (
		'a' => array (
			'href' => array(),
			'class' => array(),
		),
		'div' => array(),
		'span' => array(),
		'p' => array(),
		'mark' => array(),
	);
}


/* SEND TO DATABASE
 ----------------------------- */

add_action( 'wp_ajax_addFeedback', 'speakabout_store_feedback' );
add_action( 'wp_ajax_nopriv_addFeedback', 'speakabout_store_feedback' );

add_action( 'wp_ajax_deleteFeedback', 'speakabout_delete_feedback' );
add_action( 'wp_ajax_nopriv_deleteFeedback', 'speakabout_delete_feedback' );

add_action( 'wp_ajax_updateFeedback', 'speakabout_update_feedback' );
add_action( 'wp_ajax_nopriv_updateFeedback', 'speakabout_update_feedback' );

function speakabout_store_feedback(){
	global $wpdb;
	$commenter_id = sanitize_key($_POST['userId']);
	$item_id = sanitize_key($_POST['itemId']);
	$highlight = sanitize_text_field($_POST['highlight']);
	$highlight_with_context = wp_kses_post($_POST['highlightWithContext']);
	$comment = sanitize_text_field($_POST['comment']);
	$page_name = sanitize_title($_POST['pageName']);
	$page_url = esc_url_raw($_POST['pageURL']);
	$has_been_emailed = 0;

	$table_name = $wpdb->prefix . 'speakabout';
	
	$wpdb->insert( 
		$table_name, 
		array( 
			'time' => current_time( 'mysql' ), 
			'commenter_id' => $commenter_id, 
			'item_id' => $item_id, 
			'highlight' => $highlight, 
			'highlight_with_context' => $highlight_with_context, 
			'comment' => $comment, 
			'page_name' => $page_name, 
			'page_url' => $page_url, 
			'has_been_emailed' => $has_been_emailed, 
		) 
	); 
	die();
}

function speakabout_update_feedback(){


	global $wpdb;
	$wpdb->show_errors;

	$commenter_id = sanitize_key($_POST['userId']);
	$item_id = sanitize_key($_POST['itemId']);
	$highlight = sanitize_text_field($_POST['highlightWithContext']);
	$highlight_with_context = wp_kses_post($_POST['highlightWithContext']);
	$comment = sanitize_text_field($_POST['comment']);
	$page_name = sanitize_title($_POST['pageName']);
	$page_url = esc_url_raw($_POST['pageURL']);
	$has_been_emailed = 0;

	

	$table_name = $wpdb->prefix . 'speakabout';
	
	$wpdb->update( 
		$table_name, 
		array( 
			'comment' => $comment,
			'highlight' => $highlight, 
			'highlight_with_context' => $highlight_with_context

		),
		array( 
			'item_id' => $item_id
		) 
	); 

	$wpdb->print_error;
	exit( var_dump( $wpdb->last_query ) );
	die();
}

function speakabout_delete_feedback(){

	global $wpdb;
	$commenter_id = sanitize_key($_POST['userId']);
	$item_id = sanitize_key($_POST['itemId']);
	$highlight = sanitize_text_field($_POST['highlightWithContext']);
	$highlight_with_context = wp_kses_post($_POST['highlightWithContext']);
	$comment = sanitize_text_field($_POST['comment']);
	$page_name = sanitize_title($_POST['pageName']);
	$page_url = esc_url_raw($_POST['pageURL']);
	$has_been_emailed = 0;

	$table_name = $wpdb->prefix . 'speakabout';
	
	$wpdb->delete( 
		$table_name, 
		array( 
			'commenter_id' => $commenter_id,
			'item_id' => $item_id, 
			'page_url' => $page_url
		) 
	); 
	die();
}

/* DECIDE WHAT FEEDBACK TO EMAIL OUT
 ----------------------------- */
function speakabout_build_feedback_emails(){
	//search through db for unsent comments, build reports, call send function

	global $wpdb;
    $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}speakabout", OBJECT);

   $commenterList = array(); //fill with users who have multiple comments to avoid sending multiple emails to them. 
   foreach ( $results as $result ) {
		if ($result->has_been_emailed == 0){
			$commenterId = $result->commenter_id;
			if (in_array($commenterId, $commenterList)){
				//this shouldn't happen, in theory. 
			} 
			else {
				//this user is not yet in the list. 
				//unknown number of comments
				$userFeedback = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}speakabout WHERE commenter_id = '" . $commenterId . "'", OBJECT);
				$numberOfComments = count($userFeedback);
				//now we know how many comments they have. 
				//put them in the list so we don't run through their comments again
				array_push($commenterList, $commenterId);
				//now that we only run through them once, build the report of their feedback
				$emailHeader = speakabout_build_email_header($result->page_url, $result->page_name);
				$emailBase = speakabout_build_email_base();
				$report = $emailHeader;
				
				foreach ( $userFeedback as $feedback) {
					if ($feedback->has_been_emailed == 0){
						$report = $report . speakabout_htmlify_feedback($feedback->highlight_with_context, $feedback->comment); 
					}
				}
				speakabout_set_email_bool_to_sent($commenterId);
				$report = $report . $emailBase;
				speakabout_send_email($report);
			}		
		}
   }
}

function speakabout_set_email_bool_to_sent($commenter_id){

	global $wpdb;
	$table_name = $wpdb->prefix . 'speakabout';

	$wpdb->update( 
		$table_name, 
		array( 
			'has_been_emailed' => 1,	
		), 
		array( 'commenter_id' => $commenter_id ), 
		array( 
			'%d'
		), 
		array( '%s' ) 
	);
}

function speakabout_htmlify_feedback($highlight, $comment){
	return '  <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Arial, sans-serif"><![endif]-->
    <div style="color:#555555;font-family:Arial, Helvetica, sans-serif;line-height:120%;padding-top:10px;padding-right:10px;padding-bottom:0px;padding-left:10px;">
    	<div style="font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 20px; color: #555555;">
          ' . $highlight . '
        </div>
	</div> 
	<!--[if mso]></td></tr></table><![endif]-->
    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 0px; padding-bottom: 0px; font-family: Arial, sans-serif"><![endif]-->
    <div style="color:#555555;font-family:Arial, Helvetica, sans-serif;line-height:120%;padding-top:0px;padding-right:10px;padding-bottom:20px;padding-left:10px;">
    	<div style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 14px; color: #555555;">
    		<p style="font-size: 14px; line-height: 24px; margin: 0;font-size:18px; color:#333; background-color:#f0f0f0;padding:10px;margin:2px 0px;">
				<span style="font-size: 18px;"> 
					' . $comment . '
				</span>
    		</p>
    	</div>
    </div> ';
}

function speakabout_build_email_header($page_url, $page_title){
	return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml"><head><!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]--><meta content="text/html; charset=utf-8" http-equiv="Content-Type"/><meta content="width=device-width" name="viewport"/><!--[if !mso]><!--><meta content="IE=edge" http-equiv="X-UA-Compatible"/><!--<![endif]--><title></title><!--[if !mso]><!--><!--<![endif]--><style type="text/css">body {margin: 0;padding: 0;}table,td,tr {vertical-align: top;border-collapse: collapse;}* {line-height: inherit;}a[x-apple-data-detectors=true] {color: inherit !important;text-decoration: none !important;}</style><style id="media-query" type="text/css">@media (max-width: 520px) {.block-grid,.col {min-width: 320px !important;max-width: 100% !important;display: block !important;}.block-grid {width: 100% !important;}.col {width: 100% !important;}.col>div {margin: 0 auto;}img.fullwidth,img.fullwidthOnMobile {max-width: 100% !important;}.no-stack .col {min-width: 0 !important;display: table-cell !important;}.no-stack.two-up .col {width: 50% !important;}.no-stack .col.num4 {width: 33% !important;}.no-stack .col.num8 {width: 66% !important;}.no-stack .col.num4 {width: 33% !important;}.no-stack .col.num3 {width: 25% !important;}.no-stack .col.num6 {width: 50% !important;}.no-stack .col.num9 {width: 75% !important;}.video-block {max-width: none !important;}.mobile_hide {min-height: 0px;max-height: 0px;max-width: 0px;display: none;overflow: hidden;font-size: 0px;}.desktop_hide {display: block !important;max-height: none !important;}}</style></head><body class="clean-body" style="margin: 0; padding: 0; -webkit-text-size-adjust: 100%; background-color: #FFFFFF;"><!--[if IE]><div class="ie-browser"><![endif]--><table bgcolor="#FFFFFF" cellpadding="0" cellspacing="0" class="nl-container" role="presentation" style="table-layout: fixed; vertical-align: top; min-width: 320px; Margin: 0 auto; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; width: 100%;" valign="top" width="100%"><tbody><tr style="vertical-align: top;" valign="top"><td style="word-break: break-word; vertical-align: top;" valign="top"><!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="background-color:#FFFFFF"><![endif]--><div style="background-color:transparent;"><div class="block-grid" style="Margin: 0 auto; min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; background-color: transparent;"><div style="border-collapse: collapse;display: table;width: 100%;background-color:transparent;"><!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:transparent;"><tr><td align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:500px"><tr class="layout-full-width" style="background-color:transparent"><![endif]--><!--[if (mso)|(IE)]><td align="center" width="500" style="background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;" valign="top"><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;"><![endif]--><div class="col num12" style="min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;"><div style="width:100% !important;"><!--[if (!mso)&(!IE)]><!--><div style="border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;"><!--<![endif]--><!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Arial, sans-serif"><![endif]--><div style="color:#555555;font-family:Arial, Helvetica, sans-serif;line-height:120%;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;"><div style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 14px; color: #555555;">
	<p style="font-size: 14px; line-height: 24px; margin: 0;"><span style="font-size: 22px;">
		Your page <strong><a href="' . $page_url . '" style="text-decoration:none;color:#202020">' . $page_title . '</a></strong> has new feedback!</span></p></div></div><!--[if mso]></td></tr></table><![endif]--><table border="0" cellpadding="0" cellspacing="0" class="divider" role="presentation" style="table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;" valign="top" width="100%"><tbody><tr style="vertical-align: top;" valign="top"><td class="divider_inner" style="word-break: break-word; vertical-align: top; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; padding-top: 10px; padding-right: 10px; 
		padding-bottom: 20px; padding-left: 10px;" valign="top"><table align="center" border="0" cellpadding="0" cellspacing="0" class="divider_content" role="presentation" style="table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-top: 1px solid #BBBBBB; width: 100%;" valign="top" width="100%"><tbody><tr style="vertical-align: top;" valign="top"><td style="word-break: break-word; vertical-align: top; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;" valign="top"><span></span></td></tr></tbody></table></td></tr></tbody></table>';

}

function speakabout_build_email_base(){
	 return '<!--[if mso]></center></v:textbox></v:roundrect></td></tr></table><![endif]--></div><!--[if (!mso)&(!IE)]><!--></div><!--<![endif]--></div></div><!--[if (mso)|(IE)]></td></tr></table><![endif]--><!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]--></div></div></div><!--[if (mso)|(IE)]></td></tr></table><![endif]--></td></tr></tbody></table><!--[if (IE)]></div><![endif]--></body></html>';
	
}


/* EMAIL THE REPORT
 ----------------------------- */
function speakabout_send_email($report){

	$options = get_option( 'speakAbout_settings' );
	$reportEmail = $options['speakAbout_report_email'];
	$reportTitle = $options['speakAbout_report_title'];
	
	$message = stripslashes($report);

	$to = $reportEmail;

	if (empty($reportTitle)){
		$subject = "Message from SpeakAbout";
	} else {
		$subject = $reportTitle;
	}
	
	$headers = array('Content-Type: text/html; charset=UTF-8');
	wp_mail( $to, $subject, $message, $headers);

    die();
}


/* OPTIONS ADMIN 
 ----------------------------- */
add_action( 'admin_menu', 'speakabout_add_admin_menu' );
add_action( 'admin_init', 'speakabout_settings_init' );

function speakabout_plugin_scripts($hook) {
    if ( 'toplevel_page_speakabout_settings' != $hook ) {
        return;
    }
	wp_enqueue_style( 'speakaboutsettings', plugins_url('speak-about-settings.css', __FILE__));
	wp_enqueue_script('speakaboutsettings_js', plugin_dir_url(__FILE__) . 'speak-about-settings.js', array('jquery'));
}
add_action( 'admin_enqueue_scripts', 'speakabout_plugin_scripts' ); 


function speakabout_add_admin_menu() {
	add_menu_page( 'SpeakAbout', 'SpeakAbout', 'manage_options', 'speakabout_settings', 'speakabout_options_page', plugin_dir_url(__FILE__) . 'assets/dash-icon.png', 65);
}

function speakabout_settings_init(  ) {
	register_setting( 'saPlugin', 'speakAbout_settings' );
    add_settings_section(
        'saPlugin_section_email',
        __( 'Email', 'wordpress' ),
        'speakAbout_email_section_callback',
        'saPlugin'
	);
	add_settings_field(
        'speakAbout_report_title',
        __( 'Email Subject Line:', 'wordpress' ),
        'speakAbout_report_title_render',
        'saPlugin',
        'saPlugin_section_email'
    );
    add_settings_field(
        'speakAbout_report_email',
        __( 'Email Address:', 'wordpress' ),
        'speakAbout_report_email_render',
        'saPlugin',
        'saPlugin_section_email'
	);
	add_settings_section(
        'saPlugin_section_perm',
        __( 'Permissions', 'wordpress' ),
        'speakabout_perm_section_callback',
        'saPlugin'
	);
	add_settings_field(
        'speakAbout_perm',
        __( 'Run SpeakAbout on:', 'wordpress' ),
        'speakAbout_perm_render',
        'saPlugin',
        'saPlugin_section_perm'
    );
	add_settings_section(
        'saPlugin_section_highlighter',
        __( 'Highlighter', 'wordpress' ),
        'speakAbout_highlighter_section_callback',
        'saPlugin'
	);
	add_settings_field(
        'speakAbout_highlight_color',
        __( 'Highlighter color:', 'wordpress' ),
        'speakAbout_highlight_color_render',
        'saPlugin',
        'saPlugin_section_highlighter'
	);
	add_settings_section(
        'saPlugin_section_bugreport',
        __( 'Feedback', 'wordpress' ),
        'speakAbout_bugreport_section_callback',
        'saPlugin'
	);
}


function speakAbout_report_page_render( ){
	$options = get_option( 'speakAbout_settings' );
    ?>
    <input type='text' placeholder="New" style="width: 250px" name='speakAbout_settings[speakAbout_page]' value='<?php echo $options['speakAbout_page']; ?>'>
    <?php
}

function speakAbout_report_title_render(  ) {
    $options = get_option( 'speakAbout_settings' );
    ?>
    <input type='text' placeholder="New message from SpeakAbout!" style="width: 250px" name='speakAbout_settings[speakAbout_report_title]' value='<?php echo $options['speakAbout_report_title']; ?>'>
    <?php
}

function speakAbout_report_email_render(  ) {
    $options = get_option( 'speakAbout_settings' );
    ?>
    <input type='text' style="width: 250px" name='speakAbout_settings[speakAbout_report_email]' value='<?php echo $options['speakAbout_report_email']; ?>'>
    <?php
}

function speakAbout_highlight_color_render(  ) {
	$options = get_option( 'speakAbout_settings' );
	$color = $options['speakAbout_highlight_color'];
	$isCustom = false;
	$customValue = "#dddddd";

		if ($color == "red" || $color == "yellow" || $color == "green" || $color == "blue"){
		}
		else if (empty($color)){
			$color == "red";
		}
		else {
			$customValue = $color;
			$isCustom = true;
		}
    ?>
		<div class="switch-field">
			<input type="radio" id="radio-red" name="speakAbout_settings[speakAbout_highlight_color]" value="red" checked  <?php checked($color, "red") ?> />
			<label for="radio-red">Red</label>
			<input type="radio" id="radio-yellow" name="speakAbout_settings[speakAbout_highlight_color]" value="yellow" <?php checked($color, "yellow") ?>  />
			<label for="radio-yellow">Yellow</label>
			<input type="radio" id="radio-green" name="speakAbout_settings[speakAbout_highlight_color]" value="green" <?php checked($color, "green") ?>  />
			<label for="radio-green">Green</label>
			<input type="radio" id="radio-blue" name="speakAbout_settings[speakAbout_highlight_color]" value="blue"   <?php checked($color, "blue") ?> />
			<label for="radio-blue">Blue</label>
			<input type="radio" id="radio-custom" name="speakAbout_settings[speakAbout_highlight_color]" value='<?php echo $customValue ?>'   <?php checked($isCustom) ?> />
			<label for="radio-custom">
				Custom
				<input type="text" placeholder='<?php echo $customValue ?>' id="colorInput" value="" minlength="4" maxlength="7" spellcheck="false">
			</label>
			
		</div>
    <?php
}

function speakAbout_perm_render( ) {

	$options = get_option( 'speakAbout_settings', [] );
	$speakabout_permissions = isset( $options['speakAbout_perm'] )
	? (array) $options['speakAbout_perm'] : [];
		
	$stateOfChecks = "default";
	$numOfChecks = count($speakabout_permissions);
	if ($numOfChecks > 0 || $stateOfChecks == "notDefault"){
		$stateOfChecks = "notDefault";
	}

	?>
	<div id="permissions-field">
		<input type='checkbox' style="display:none" name='speakAbout_settings[speakAbout_perm][]' checked value="<?php echo $stateOfChecks ?>">
		<input type='checkbox' name='speakAbout_settings[speakAbout_perm][]' <?php checked( in_array( 'pages', $speakabout_permissions )  || $stateOfChecks == 'default'); ?> value='pages'>
        <label>Pages</label>
		<input type='checkbox' name='speakAbout_settings[speakAbout_perm][]' <?php checked( in_array( 'posts', $speakabout_permissions )  || $stateOfChecks == 'default'); ?> value='posts'>
        <label>Posts</label>
		<input type='checkbox' name='speakAbout_settings[speakAbout_perm][]' <?php checked( in_array( 'archive', $speakabout_permissions ) || $stateOfChecks == 'default'); ?> value='archive'>
        <label>Archive</label>
		<input type='checkbox' name='speakAbout_settings[speakAbout_perm][]' <?php checked( in_array( 'home', $speakabout_permissions ) ); ?> value='home'>
        <label>Home</label>
		<input type='checkbox' name='speakAbout_settings[speakAbout_perm][]' <?php checked( in_array( 'front_page', $speakabout_permissions ) ); ?> value='front_page'>
        <label>Front Page</label>
	</div>
	<?php

}

function speakAbout_email_section_callback(  ) {
    // echo __( 'Change your email settings', 'wordpress' );
}
function speakAbout_highlighter_section_callback(  ) {
    // echo __( 'Change your highlighter settings', 'wordpress' );
}
function speakAbout_perm_section_callback(  ) {
   // echo __( 'Choose which pages to run SpeakAbout on', 'wordpress' );
}
function speakAbout_bugreport_section_callback(  ) {
     echo __( "Suggestions, bugs, or general feedback? We would love to hear it at <a href='mailto:feedback@speakabout.io'> feedback@speakabout.io </a>. <br> Visit our site at <a target='_blank' href='https://www.speakabout.io'> www.speakabout.io</a>", 'wordpress' );
}

function speakAbout_options_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	?>
    <form action='options.php' method='post'>
        <h1>SpeakAbout Settings</h1>
		<br>
        <?php
        settings_fields( 'saPlugin' );
        do_settings_sections( 'saPlugin' );
        submit_button();
        ?>
    </form>
    <?php
}

//NOTIFICATION UPON ACTIVATION
// https://stackoverflow.com/questions/38233751/show-message-after-activating-wordpress-plugin

register_activation_hook( __FILE__, 'speakabout_activation_notice_hook' );

function speakabout_activation_notice_hook() {
    set_transient( 'admin_notice', true, 5 );
}

add_action( 'admin_notices', 'speakabout_admin_activation_notice' );

function speakabout_admin_activation_notice(){

    /* Check transient, if available display notice */
    if( get_transient( 'admin_notice' ) ){
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Please add an email address</strong> under SpeakAbout settings to send reports.</p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'admin_notice' );
    }
}



//OTHER 

function speakabout_highlightColorToJS(){
	$options = get_option( 'speakAbout_settings' );
	$color = $options['speakAbout_highlight_color'];

	if ($color == "red") $color = "#f38c8c";
	if ($color == "yellow") $color = "#f3ec8c";
	if ($color == "green") $color = "#98f38c";
	if ($color == "blue") $color = "#8cc1f3";

	return '<script> var highlightColor = "' . $color . '"</script>'; 
}

function add_speakabout_to_page($content) {

	$options = get_option( 'speakAbout_settings' );
	$permissions = $options['speakAbout_perm'];
	$build = false; 


	if( is_singular() && is_main_query() && in_array('posts', $permissions)) $build = true;
	if( is_page() && in_array('pages', $permissions)) $build = true;
	if( is_front_page() && in_array('front_page', $permissions)) $build = true;
	if( is_home() && in_array('home', $permissions)) $build = true;
	if( is_archive() && in_array('archive', $permissions)) $build = true;


	if ($build){
		$speakabout_highlighterinfo = speakabout_highlightColorToJS();
		$speakabout_open = "<div id='speakaboutWrapper'>";
		$speakabout_close = "</div>";
		$content = $speakabout_open . $speakabout_highlighterinfo . $content . $speakabout_close;	
	}
	return $content;
}

add_filter('the_content', 'add_speakabout_to_page');

?>