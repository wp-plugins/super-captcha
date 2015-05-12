<?php
/*
Plugin Name: Super Captcha Anti-Bot Suite
Plugin URI: http://goldsborowebdevelopment.com/product/super-captcha/
Description: WordPress's FIRST EVER 3D CAPTCHA that stops spam blogs, registrations, and comments COLD.
Author: Goldsboro Web Development
Version: 3.0.4
Author URI: http://goldsborowebdevelopment.com
Copyright (C) 2015 Goldsboro Web Development

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License version 3.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
	This license applies only to this software and not any services
	offered as part of this software such as access to the MyGWD Database
	or customer support.
	
*/
new newSuperCaptcha();
class newSuperCaptcha
	{
	// Setting up the class to initialize in WordPress
	var $adminOptionsName = "newSuperCaptcha";
	var $db_table_name = '';
	var $scVersion = '3.0.4'; // Used to alert admins to change configs and offers version number inside contextual areas.
	function newSuperCaptchaBC() { $this->newSuperCaptcha(); } // Backwards compatibility...
	function newSuperCaptcha() 
		{
		// Initializing the plugin
		add_action					( 'admin_menu', 					array ( &$this, 'createMenus' ) ); // The admin menu
		add_action					( 'init',							array ( &$this, 'register_scsession' ) ); // Nicely setting up sessions
		add_shortcode				( 'supercaptcha',					array ( &$this, 'scshortcode') );
		if( esc_attr( get_option('secure_register') ) == true )
			{
			// Hooking into the registration forms
			if(function_exists('bp_include'))
				{
				add_action('bp_before_registration_submit_buttons',		array( &$this, 'signup_bpform' ) );
				add_action('bp_signup_validate',						array( &$this, 'signup_bppost') );
				}
			elseif(function_exists('signup_extra_fields'))
				{
				add_action('signup_extra_fields',						array( &$this, 'signup_form' ) );
				add_filter('wpmu_validate_user_signup',					array( &$this, 'signup_post') );
				} else {
				add_action('register_form',								array( &$this, 'signup_form' ) );
				add_filter('registration_errors',						array( &$this, 'signup_post') );
				}
			}
		if( esc_attr( get_option('secure_blog') ) == true )
			{
			//Hooking into the blog creation forms.
			add_filter		('wpmu_validate_blog_signup',				array( &$this, 'signup_post' ) );
			add_action		('signup_blogform',							array( &$this, 'signup_bpform' ) );		
			}
		if( esc_attr( get_option('secure_comments') ) == true )
			{
			//Hooking into comments.
			add_action			('comment_form_after_fields', 			array( &$this, 'signup_form' ) );
			add_action			('preprocess_comment',					array( &$this, 'comments_submit' ) );
			}
		if( esc_attr( get_option('secure_login') ) == true )
			{
			//Hooking into the login form.
			add_action		('login_form',								array( &$this, 'signup_form' ) );
			add_filter		('login_head',								array( &$this, 'login_post' ) );
			}
		if( esc_attr( get_option('pro_notice') ) != true )
			{
			//A little shameless advertising.
			if ( is_multisite() )
				{
				add_action('all_admin_notices', 				   		array(&$this,'pro_notice'));
				} else {
				add_action('admin_notices', 				           	array(&$this,'pro_notice'));
				}
			}
		if( esc_attr( get_option('sc_setup') ) != $this->scVersion )
			{
			//Alerts the admin that the version has changed and they may need to reconfigure.
			add_action('all_admin_notices', 							array( &$this, 'first_setup' ) );
			}
		add_action('wp_dashboard_setup',								array( &$this, 'sc_widgets' ) );
		}

	function createMenus()
		{
		// Creating the Admin Menu
		add_thickbox();
		$adminpage = add_menu_page	( 'SC Suite', 						'Super Captcha', 			'administrator', 		__FILE__, 		array ( &$this, 'adminOptions' ),		'dashicons-lock', 3 );
		add_action					( 'admin_init', 					array ( &$this, 'register_scsettings' ) );
		add_action					( 'load-'.$adminpage, 				array ( &$this, 'main_page_help' ) );
		}
	function register_scsession()
		{
		if( !session_id() )
			{
			session_start();
			}
		}
	function adminOptions()
		{
		// Some HTML
		$thisresult = null;
		$thisresulta = null;
		$thisresultb = null;
		if(!empty($_POST['check']))
			{
			if ( $this->validateCode( $_POST['check'] ) == true )
				{
				$thisresult = '<p><span style="color:#008800;"><strong>You passed!</strong></span></p>';
				} else {
				$thisresult = '<p><span style="color:#880000;"><strong>You failed!</strong></span></p>';
				}
			}
		if(!empty( $_POST['testip'] ))
			{
			if ( $this->pro_spam_check( $_POST['testip'] ) == true )
				{
				$thisresulta = ('<p><span style="color:#990000;"><strong>' . $_POST['testip'] . ' is listed.</strong></span> <small>('.$this->thickboxlink( 'https://my.goldsborowebdevelopment.com/submitticket.php?step=2&deptid=7','Submit Removal' ).')</small> <a class="button button-secondary" href="mailto:'.$this->get_abuse_email($_POST['testip']).'?subject=Abuse Report for '.$_POST['testip'].'&body=The IP '.$_POST['testip'].', is automating signups and spamming my website, '.site_url().' over HTTP (port 80). Please review.">ISP Report &raquo;</a></p>' );
				} else {
				$thisresulta = ('<p><span style="color:#009900;"><strong>' . $_POST['testip'] . ' is clean.</strong></span></p>' );
				}
			}
		if(!empty( $_POST['reportip'] ))
			{
			if ( $this->report_spam( $_POST['reportip'] ) == true )
				{
				$thisresultb = ('<p><span style="color:#009900;"><strong>' . $_POST['reportip'] . ' was reported. <a class="button button-secondary" href="mailto:'.$this->get_abuse_email($_POST['reportip']).'?subject=Abuse Report for '.$_POST['reportip'].'&body=The IP '.$_POST['reportip'].', is automating signups and spamming my website, '.site_url().' over HTTP (port 80). Please review.">ISP Report &raquo;</a></strong></span></small></p>' );
				} else {
				$thisresultb = ('<p><span style="color:#990000;"><strong>ERROR! Could not report ' . $_POST['reportip'] . '. Either you have tried reporting too many times or your domain has been disabled from reporting.</strong></span></p>' );
				}
			}
		?>
		<div class="wrap" id="scaptcha">
		  <div id="dashicons-lock" class="icon32"><br /></div>
		  <h2>Super Captcha <sup><?php if ( $this->pro_license_check( ) == true ) : ?><span style="color:#009900;"><em>pro</em></span><?php else : ?><span style="color:#DD0000;"><em>free</em></span><?php endif; ?></sup> Anti-Bot Suite</h2>
		  <div style="width:100%;overflow: hidden;height:auto;">
			<div style="width:262px;float:right;">
			<h4>Is It Working?</h4>
			  <?php if( esc_attr( get_option('enable_test') ) == true ) : ?>
				<?php echo $thisresult; ?>
				<?php $this->getCaptchaImage(); ?>
				<form method="post" action="">
				<input type="text" name="check" />
				<input type="submit" name="Test &raquo;" class="button button-secondary" value="Test &raquo;" />
				</form>
			  <?php else: ?>
			  <strong>In order to have this plugin listed at wordpress.org/plugins we had to remove the iFrame here to ensure the plugin is working before enabling.  In order to enable this iFrame, tick "WP.org compliance" option and save.</strong>
			  <?php endif; ?>
			<?php if ( $this->pro_license_check( ) == true ) : ?>
			<h4>Check IP</h4>
			<p>Every now and then, that guy will say he's being blocked. So you can test here and request removal.</p>
			<?php echo $thisresulta; ?>
			<form method="post" action="">
				<input type="text" name="testip" />
				<input type="submit" name="Check &raquo;" class="button button-secondary" value="Check &raquo;" />
			</form>
			<?php endif; ?>
			<h4>Report A Spammer IP</h4>
			<p>You can manually submit a spammer here if they have spammed you. <strong>USE WITH CARE:</strong> Abuse of
			this feature will result in the inability to use it in the future.</p>
			<?php echo $thisresultb; ?>
			<form method="post" action="">
				<input type="text" name="reportip" />
				<input type="submit" name="Report &raquo;" class="button button-secondary" value="Report &raquo;" />
			</form>
			</div>
			<div style="width:auto!important;min-width:400px;overflow:hidden;padding-right:25px;">
				<form method="post" action="options.php">
					<?php settings_fields( 'sc-settings-group' ); ?>
					<?php do_settings_sections( 'sc-settings-group' ); ?>
					<table class="form-table">
						<tr valign="top">
						<th scope="row">Font</th>
							<td>
								<select name="font">
								  <option value="1"<?php if( esc_attr( get_option('font') ) == 1 ): ?> SELECTED<?php endif; ?>>3DCaptcha.font</option>
								  <option value="2"<?php if( esc_attr( get_option('font') ) == 2 ): ?> SELECTED<?php endif; ?>>AD MONO.font</option>
								  <option value="3"<?php if( esc_attr( get_option('font') ) == 3 ): ?> SELECTED<?php endif; ?>>AcidDreamer.font</option>
								  <option value="4"<?php if( esc_attr( get_option('font') ) == 4 ): ?> SELECTED<?php endif; ?>>elephant.font</option>
								  <option value="5"<?php if( esc_attr( get_option('font') ) == 5 ): ?> SELECTED<?php endif; ?>>Jiggery Pokery.font</option>
								  <option value="6"<?php if( esc_attr( get_option('font') ) == 6 ): ?> SELECTED<?php endif; ?>>Mash Note.font</option>
								  <option value="7"<?php if( esc_attr( get_option('font') ) == 7 ): ?> SELECTED<?php endif; ?>>Model Worker.font</option>
								  <option value="8"<?php if( esc_attr( get_option('font') ) == 8 ): ?> SELECTED<?php endif; ?>>Pants Patrol.font</option>
								  <option value="9"<?php if( esc_attr( get_option('font') ) == 9 ): ?> SELECTED<?php endif; ?>>Xposed.font</option>
								</select>
								<p><strong>WARNING:</strong> Read the help context before changing this value!</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Font Size</th>
							<td>
								<input style="width:50px;" type="number" name="font_size" value="<?php if( esc_attr( get_option('font_size') ) == false ): ?>24<?php else: echo esc_attr( get_option('font_size') ); endif; ?>" />px <br />
								<small>24px is a great number, anything below 15px is way too small and above 30px is to large to fit on the image.</small>
							</td>
						</tr>
						<tr valign="top">
						<th scope="row">Background</th>
							<td>
								<select name="background">
								  <option value="1"<?php if( esc_attr( get_option('background') ) == 1 ): ?> SELECTED<?php endif; ?>>Digital Dream</option>
								  <option value="2"<?php if( esc_attr( get_option('background') ) == 2 ): ?> SELECTED<?php endif; ?>>Blue Marble</option>
								  <option value="3"<?php if( esc_attr( get_option('background') ) == 3 ): ?> SELECTED<?php endif; ?>>Blue Hue</option>
								  <option value="4"<?php if( esc_attr( get_option('background') ) == 4 ): ?> SELECTED<?php endif; ?>>Blue Tile</option>
								  <option value="5"<?php if( esc_attr( get_option('background') ) == 5 ): ?> SELECTED<?php endif; ?>>Blue Noise</option>
								  <option value="6"<?php if( esc_attr( get_option('background') ) == 6 ): ?> SELECTED<?php endif; ?>>Granite</option>
								  <option value="7"<?php if( esc_attr( get_option('background') ) == 7 ): ?> SELECTED<?php endif; ?>>Workshop Steel</option>
								</select>
							</td>
						</tr>
						
						<tr valign="top">
						<th scope="row">Distortion Matrix</th>
							<td>
								<input type="checkbox" name="distortion" value="true"<?php if( esc_attr( get_option('distortion') ) == true ): ?> CHECKED<?php endif; ?> /> <p><strong>WARNING:</strong> Read the help context before changing this value!</p>
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row">Secure Forms</th>
							<td>
								<input type="checkbox" name="secure_login" value="true"<?php if( esc_attr( get_option('secure_login') ) == true ) : ?> CHECKED<?php endif; ?> /> Login Form<br />
								<input type="checkbox" name="secure_register" value="true"<?php if( esc_attr( get_option('secure_register') ) == true ): ?> CHECKED<?php endif; ?> /> Registration Form<br />
								<?php if(function_exists('signup_extra_fields') || function_exists('bp_include') ) : ?>
								<input type="checkbox" name="secure_blog" value="true"<?php if( esc_attr( get_option('secure_blog') ) == true ): ?> CHECKED<?php endif; ?> /> Blog Creation Form<br />
								<?php endif; ?>
								<input type="checkbox" name="secure_comments" value="true"<?php if( esc_attr( get_option('secure_comments') ) == true ): ?> CHECKED<?php endif; ?> /> Comments Forms<br />
							</td>
						</tr>
						<?php if ( $this->pro_license_check( ) != true ) : ?>
						<tr valign="top">
							<th scope="row">Stop Nagging</th>
							<td>
								<input type="checkbox" name="pro_notice" value="true"<?php if( esc_attr( get_option('pro_notice') ) == true ): ?> CHECKED<?php endif; ?> /> Checking this box turns off Super Captcha Pro Reminders.<br />
							</td>
						</tr>
						<?php else : ?>
						<input type="hidden" name="pro_notice" value="true" />
						<?php endif; ?>
						
						
						<tr valign="top">
							<th scope="row">License Key <small>See Help Context</small></th>
							<td>
								<?php if ( $this->pro_license_check( ) == true ) : ?>
								<input style="width:300px;border-color:#009900!important;" type="text" name="pro_license" value="<?php echo esc_attr( get_option('pro_license') ); ?>" /> <input type="submit" style="color:#009900!important;" name="Revalidate &raquo;" class="button button-secondary" value="Revalidate &raquo;" /><br />
								<?php $licinfo = $this->pro_license_info( ); ?>
								<span style="color:#008800;"><strong>Licensed to: <?php _e( $licinfo['FNAME'] ); ?> <?php _e( $licinfo['LNAME'] ); ?>, <?php _e( $licinfo['DOM'] ); ?> [<?php _e( $licinfo['IP'] ); ?>]</strong></span>
								<?php elseif ( esc_attr( get_option('pro_license') ) == true ) : ?>
								<input style="width:300px;" type="text" name="pro_license" value="" /> <a href="https://my.goldsborowebdevelopment.com/cart.php?a=add&pid=68&sld=whmcs&tld=<?php echo $_SERVER['HTTP_HOST']; ?>" class="button button-secondary" style="color:#FF0000;" target="_blank">Go Pro &raquo;</a>
								<br />
								<span style="color:#ff0000;"><strong><?php _e( $this->pro_license_check( true ) ); ?></strong></span>
								<?php else : ?>
								<input style="width:300px;" type="text" name="pro_license" value="<?php echo esc_attr( get_option('pro_license') ); ?>" /> <a href="https://my.goldsborowebdevelopment.com/cart.php?a=add&pid=68&sld=whmcs&tld=<?php echo $_SERVER['HTTP_HOST']; ?>" class="button button-secondary" style="color:#FF0000;" target="_blank">Go Pro &raquo;</a>
								<?php endif; ?>
								
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row">WP.org Compliance</th>
							<td>
								<input type="checkbox" name="enable_test" value="true"<?php if( esc_attr( get_option('enable_test') ) == true ): ?> CHECKED<?php endif; ?> /> Allow Polling<br />
								<small>This option will allow you to see the testing iframe, and open remote pages to remove accidentally submitted IP addresses.  No additional information is submitted or collected!</small>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Customization / Multilingual</th>
							<td>
								You can now customize the messages SuperCAPTCHA displays on your site here.
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Error: Missing</th>
							<td>
								<?php if( esc_attr( get_option('error_msg_missing') ) == false ) :
								$message1 = 'You must enter the code you see.';
								else:
								$message1 = esc_attr( get_option('error_msg_missing') );
								endif; ?>
								<input style="width:400px;border-color:#880000!important;" type="text" name="error_msg_missing" value="<?php echo $message1; ?>" />
								When the CAPTCHA field is not filled out.
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Error: Wrong</th>
							<td>
								<?php if( esc_attr( get_option('error_msg_wrong') ) == false ) :
								$message2 = 'The verification code you entered was incorrect.';
								else:
								$message2 = esc_attr( get_option('error_msg_wrong') );
								endif; ?>
								<input style="width:400px;border-color:#880000!important;" type="text" name="error_msg_wrong" value="<?php echo $message2; ?>" />
								When the CAPTCHA field is wrong.
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Image Field</th>
							<td>
								<?php if( esc_attr( get_option('img_field_name') ) == false ) :
								$message3 = 'Anti-Bot Code';
								else:
								$message3 = esc_attr( get_option('img_field_name') );
								endif; ?>
								<input type="text" name="img_field_name" value="<?php echo $message3; ?>" />
								Field name for the CAPTCHA image.
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Text Field</th>
							<td>
								<?php if( esc_attr( get_option('txt_field_name') ) == false ) :
								$message4 = 'Anti-Bot Verification';
								else:
								$message4 = esc_attr( get_option('txt_field_name') );
								endif; ?>
								<input type="text" name="txt_field_name" value="<?php echo $message4; ?>" />
								Field name for the CAPTCHA code input box.
							</td>
						</tr>
		
					</table>
					<input type="hidden" name="sc_setup" value="<?php echo $this->scVersion; ?>" />
					<?php submit_button(); ?>

				</form>
			<p><?php echo $this->get_footer_stats() ; ?></p>
			</div>
		  </div>
		  <div style="clear:both;"></div>
		</div>
		  
		<?php
		}
	function thickboxlink( $theURL,$theTitle,$width = false, $height = false )
		{
		if( esc_attr( get_option('enable_test') ) == true )
			{
			if($width == false)
				{
				$width = 800;
				}
			if($height == false)
				{
				$height = 600;
				}
			$code = ('<a href="'.$theURL.'?TB_iframe=true&width='.$width.'&height='.$height.'" class="thickbox">'.$theTitle.'</a>');
			} else {
			$code = ('<a href="'.$theURL.'" class="thickbox" target="_blank">'.$theTitle.'</a> <strong>Thickbox not allowed by default. Enable WP.org Compliance.');
			}
		return $code;
		}
	function getCaptchaImage()
		{
		?>
		<iframe src="http://spam.goldsborowebdevelopment.com/verify/display.php?uid=<?php echo(urlencode(site_url())); ?>&sid=<?php echo(session_id()); ?>&f=<?php echo(esc_attr( get_option('font') )); ?>&bg=<?php echo(esc_attr( get_option('background') )); ?>&dis=<?php echo(esc_attr( get_option('distortion') )); ?><?php if( esc_attr( get_option('font_size') ) != false ) : ?>&fs=<?php echo(esc_attr( get_option('font_size') )); ?><?php endif; ?>" style="border:none;width:262px;height:150px;">
				
		</iframe>
		<?php
		}
	function validateCode( $code = false )
		{
		$result = wp_remote_retrieve_body( wp_remote_get('http://spam.goldsborowebdevelopment.com/verify/check.php?uid='.urlencode(site_url()).'&sid='.session_id().'&code='.$code) );
		if ( $result == 'pass' && $this->pro_spam_check() == false )
			{
			return true;
			} else {
			// We're going to report them to MyGWD's Spam DB here...
			if( $this->report_spam() ) :   endif;
			// And they don't get to comment...
			return false;
			}
		}
	function get_abuse_email( $ip )
		{
		$result = wp_remote_retrieve_body( wp_remote_get('http://spam.goldsborowebdevelopment.com/abuse-lookup/?ip=' . $ip ) );
		return $result;
		}
	function pro_license_info( $field = false )
		{
		// Parse license info for neat display in this plugin.
		$rawdata = $this->pro_license_check( true );
		$parsefields = explode(',',$rawdata);
		$fieldarray = null; // initialize the array.
		for($i=0;$i<count($parsefields);$i++)
			{
			$parsedata = explode('=',$parsefields[$i]); // there can only be 0 and 1.
			$fieldarray[$parsedata[0]] = $parsedata[1];
			}
		
		// if the field is not requested, return the entire array.
		if( $field == false )
			{
			return $fieldarray;
			} else {
			// if the field is set, we'll only display the field data.
			return $fieldarray[$field];
			}
		}
	function pro_license_check( $verbose = false )
		{
		if ( esc_attr( get_option('pro_license') ) == true )
			{
			$result = wp_remote_retrieve_body( wp_remote_get('http://goldsborowebdevelopment.com/license.php?key='. esc_attr( get_option('pro_license') ) .'&domain='. $_SERVER['SERVER_NAME'] ) );
			$breakdown = explode(':',$result);
			if($breakdown[0] == 'valid')
				{
				if($verbose ==  true)
					{
					return $breakdown[1];
					} else {
					return true;					
					}
				} else {
				if($verbose ==  true)
					{
					return $breakdown[1];
					} else {
					return false;
					}
				}
			} else {
			return false;
			}
		}
	function get_footer_stats()
		{
		$result = wp_remote_retrieve_body( wp_remote_get( 'http://spam.goldsborowebdevelopment.com/?stats=true' ) );
		return $result;
		}
	function pro_spam_check( $ip = false )
		{
		if( empty( $ip ) )
			{
			// If an IP isn't passed in the $ip variable, we'll assume that its the current person accessing WordPress.
			$ip = $_SERVER['REMOTE_ADDR'];
			}
		if( $this->pro_license_check( ) == false)
			{
			// If your license is invalid it will return false regardless; this will just prevent an extra external query.
			// if you try and force an invalid license too many times with an invalid key, host name, and an IP that doesn't
			// match our records, our system will permanently ban your IP and this plugin will become useless on your server.
			return false;
			}
		$result = wp_remote_retrieve_body( wp_remote_get('http://spam.goldsborowebdevelopment.com/?check='. $ip .'&license='. esc_attr( get_option('pro_license') ) .'&domain='. $_SERVER['SERVER_NAME'] ) );
		// Interpret the results.
		if($result == 'LISTED')
			{
			return true;
			} else {
			return false;
			}
		}
	function report_spam( $ip = false )
		{
		if( empty( $ip ) )
			{
			$ip = $_SERVER['REMOTE_ADDR'];
			}
		$result = wp_remote_retrieve_body( wp_remote_get('http://spam.goldsborowebdevelopment.com/?submit='. $ip ) );
		if($result == 'Success')
			{
			return true;
			} else {
			return false;
			}
		}
	function register_scsettings()
		{
		// Registering Plugin Settings
		register_setting( 'sc-settings-group', 'secure_login' );
		register_setting( 'sc-settings-group', 'secure_register' );
		register_setting( 'sc-settings-group', 'secure_comments' );
		register_setting( 'sc-settings-group', 'secure_blog' );
		register_setting( 'sc-settings-group', 'font' );
		register_setting( 'sc-settings-group', 'background' );
		register_setting( 'sc-settings-group', 'distortion' );
		register_setting( 'sc-settings-group', 'pro_notice' );
		register_setting( 'sc-settings-group', 'sc_setup' );
		register_setting( 'sc-settings-group', 'pro_license' );
		register_setting( 'sc-settings-group', 'enable_test' );
		
		// v 3.0.3 update
		register_setting( 'sc-settings-group', 'error_msg_missing' );
		register_setting( 'sc-settings-group', 'error_msg_wrong' );
		register_setting( 'sc-settings-group', 'img_field_name' );
		register_setting( 'sc-settings-group', 'txt_field_name' );
		register_setting( 'sc-settings-group', 'font_size' );
		}
	function signup_bpform()
		{
		// The HTML
		global $bp;
		?>
		<div class="editfield">
				<label for="Image"><?php _e( esc_attr( get_option('img_field_name') ) ); ?></label>
		<?php
		$this->getCaptchaImage();
		?>
				</div>
		<?php
		if( !empty( $bp->signup->errors['scaptcha'] ) ) :
		?>
		<div class="error" style="color:#880000;"><?php echo $bp->signup->errors['scaptcha']; ?></div>
		<?php endif; ?>
		<div class="editfield">
				<label for="SpamCode"><?php _e( esc_attr( get_option('txt_field_name') ) ); ?></label>
				<input type="text" name="SpamCode" id="user_name" class="input" size="15" tabindex="50" />
		</div>
		<?php		
		}
	function signup_form()
		{
		// The HTML
		?>
			<label for="Image"><?php _e( esc_attr( get_option('img_field_name') ) ); ?></label>
		<?php
		$this->getCaptchaImage();
		?>
			<label for="SpamCode"><?php _e( esc_attr( get_option('txt_field_name') ) ); ?></label>
			<input type="text" name="SpamCode" id="user_name" class="input" size="10" tabindex="50" />
		<?php
		}
	function signup_bppost()
		{
		global $bp;
		if( empty( $_POST['SpamCode'] ) && isset( $_POST ) )
			{
			$bp->signup->errors['scaptcha'] = esc_attr( get_option('error_msg_missing') );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		elseif ( $this->validateCode( $_POST['SpamCode'] ) == true )
			{
			if( $this->pro_spam_check(  ) == true )
				{
				$bp->signup->errors['scaptcha'] = '<strong>YOU ('.$_SERVER['REMOTE_ADDR'].') ARE BLACKLISTED!</strong>';
				if( $this->report_spam(  ) == true) 
					{
					
					}
				}
			} else {
			$bp->signup->errors['scaptcha'] = esc_attr( get_option('error_msg_wrong') );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		}
	function signup_post( $errors )
		{
		if( empty( $_POST['SpamCode'] ) && isset( $_POST ) )
			{
			$errors->add('captcha', __( esc_attr( get_option( 'error_msg_missing' ) ) ) );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		elseif ( $this->validateCode( $_POST['SpamCode'] ) == true)
			{
			if( $this->pro_spam_check(  ) == true )
				{
				$errors->add('captcha', __( '<strong>YOU ('.$_SERVER['REMOTE_ADDR'].') ARE BLACKLISTED!</strong>') );
				if( $this->report_spam(  ) == true) 
					{
					
					}
				}
			} else {
			$errors->add('captcha', __( esc_attr( get_option( 'error_msg_wrong' ) ) ) );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		return $errors;
		}
	function login_post( )
		{
		global $error;
		if( empty( $_POST['SpamCode'] ) && isset( $_POST ) )
			{
			$error = esc_attr( get_option( 'error_msg_missing' ) );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		elseif ( $this->validateCode( $_POST['SpamCode'] ) == true )
			{
			if( $this->pro_spam_check(  ) == true )
				{
				$error = '<strong>YOU ('.$_SERVER['REMOTE_ADDR'].') ARE BLACKLISTED!</strong>';
				if( $this->report_spam(  ) == true) 
					{
					
					}
				}
			} else {
			$error = esc_attr( get_option( 'error_msg_wrong' ) );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		}
	function comments_submit( $content )
		{
		if( $this->pro_spam_check(  ) == true )
			{
			wp_die( __('<strong>YOU ('.$_SERVER['REMOTE_ADDR'].') ARE BLACKLISTED!</strong>') );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			}
		if(is_user_logged_in())
			{
			return $content;
			}
		if ( $this->validateCode( $_POST['SpamCode'] ) != true )
			{
			wp_die( __('<strong>ERROR</strong>: '. esc_attr( get_option( 'error_msg_wrong' ) ) .'. <a href="' . $_SERVER['HTTP_REFERER'] . '">Try again</a>') );
			if( $this->report_spam(  ) == true) 
				{
				
				}
			} else {
			return $content;
			}
		}
	function main_page_help()
		{
		$screen = get_current_screen();
			if ( $this->pro_license_check( ) == true ) 
				{
				$pro = 'pro';
				$licinfo = $this->pro_license_info( );
				$licout  = ('
				  <h2> Service License </h2>
				  <p>Thanks for purchasing Super CAPTCHA Pro!<p>
				  <p>Name: '. $licinfo['FNAME'] .' '. $licinfo['LNAME'] .' ('. $licinfo['EMAIL'] .')<br />
				  Domain: '. $licinfo['DOM'] .' (Can only be used on this domain)<br />
				  IP: '. $licinfo['IP'] .' (Can only be used on this IP)<br />
				  Expiration: '. $licinfo['EXP'] .' (Yes the year is right -- the billing software will terminate the license if it expires)<br />
				  ');
				} else {
				$pro = 'free';
				$licout = null;
				}
			$overview = ('
		  <h2> Super Captcha <sup><em>'.$pro.'</em></sup> Suite <small>v'.$this->scVersion.'</small></h2>
		  <p>Thanks for downloading this plugin!  We do hope that you enjoy Super Captcha as much as we enjoyed creating it! May the rest
		  of your WordPress Administration days be spam free!</p>
		  
		  <p>We have created this help context to thoroughly step you through how to properly operate this plugin.  If something 
		  doesn\'t appear to be working, or you mess something up, its fine, just come here and find the topic.</p>
		  
		  <h3>Backstory \'n Stuff</h3>
		  <p>You\'ve been there, you have installed 20 or more CAPTCHA plugins for WordPress to see them ultimately fail in even slowing down
		  spam bots.  We know, we have been there too. This plugin solves ALL of it.  You see, this software is as simple as it gets.  While
		  everyone is trying to complicate their CAPTCHAs with images, flash puzzles, and coloring it like a 2 year old with eight-inch markers,
		  we decided to make an image provoke deduction skills.  You see, a computer has no ability to reason, therefore it cannot use any power
		  of deduction.  What this means is, if we lay lines over an invisible word, you can see whats missing to near-instantly know what the
		  word is. But a computer that follows "edges" to find patterns, figuring out this CAPTCHA becomes an impossibility.</p>
		  
		  <p>The Super Captcha project started about five years ago as a simple 2D CAPTCHA that was designed to slow down bots on multi-user
		  WordPress sites.  As you know, back five years ago, there wasn\'t much in the way for support of the whole multi-user platform as
		  far as stopping automated blog creation and user signups. Our software evolved to a more sophisticated logic algorithm and a massive
		  blacklist database.</p>
		  
		  <p>About a year ago, our project was forcibly removed from the WordPress repository because we asked those using our software to keep
		  a link-back in the footer of their site.  This both let bots know not to mess with their site and gave us a little credit for pouring
		  nearly 4 years of our livelihoods into this project -- a link that had been there since before the policy was created.</p>
		  
		  <p>We\'ve become compliant by now hosting Super Captcha\'s image generator on our own servers with a link on the actual image inside the
		  iframe which satisfies WordPress.org\'s requirements to not forcefully place a link \'<strong>INSIDE</strong>\' the site. While we do
		  fully understand that this will cause significant page-rank loss, we currently forsee no other way to keep this product free and maintain
		  development.</p>
		  
		  <p>Wordpress moderators seem to prefer a more damaging route to compliance for authors to maintain credit for their work. To protest
		  email <code>plugins@wordpress.org</code></p>
		  
			');
		
		$whatsnew = ('
			<h2> What\'s New in Version '.$this->scVersion.'</h2>
			<p>This update features new widgets in your dashboard so you can easily check IPs against our database or report
			them without having to visit the configuration page.  We have also added an email button when you check a listed IP
			or submit an ip with a pre-populated form and email address that is pre-queried and matched to the abuse contact
			on file for that IP.</p>
			
			<h3>Updates &amp; Fixes</h3>
			<ul>
			  <li><strong>FEATURE</strong> - Reimplemented previous features that were requested by Wordpress.org to remove.  In order to comply with their rules we have placed a check box that will allow you to turn these features on.</li>
			  <li><strong>FEATURE</strong> - If your license key is invalid, the system is now more verbose in letting you know why.</li>
			  <li><strong>FEATURE</strong> - Font size can now be adjusted and tweaked.</li>
			  <li><strong>FEATURE</strong> - Some back-end work that is preparing the code for the addition of security levels.</li>
			  <li><strong>FIX</strong> - Error message will no longer display when visiting the login page for the first time.</li>
			  <li><strong>FIX</strong> - Failed CAPTCHA tests are now being properly reported.</li>
			  <li><strong>FIX</strong> - IPv6 Support added.
			</ul>
			
			<h3>Known Issues <small>on the list to be fixed</small></h3>
			<ul>
			  <li>Some fonts will not render properly.</li>
			  <li><strike>Report to ISP button does not support IPv6</strike></li>
			</ul>
			
			<h3>Comming Soon&trade; Features <small>pending time and funding</small></h3>
			<ul>
			  <li><strike>Adjustable font size</strike></li>
			  <li>Solid background with ability to change color</li>
			  <li>Adding over 100 new fonts</li>
			  <li>More integration with other plugins such as BBPress and Askmet</li>
			  <li>Adjustable line color</li>
			  <li><strike>Customizable Error Messages and Fields <small>likely to be in next release</small></strike></li>
			</ul>
			');
		
		$manual_override = ('
		  <h2> Manual Override </h2>
		  <p>If you have a non-standard theme or a plugin that is completely taking control of form hooks in WordPress, you can manually
		  copy the code below into your theme.  We suggest creating a child theme to do this.</p>
		  <p><code style="margin-left:50px;"><span style="color:#770000;">&lt;?php</span>
		  <span style="color:#000077;">if</span><span style="color:#770077;">(</span><span style="color:#007700;">function_exists</span><span style="color:#770077;">(</span><span style="color:#000077;">array</span><span style="color:#770077;">(</span> <span style="color:#770077;">&</span><span style="color:#000077">$newsupercaptcha</span><span style="color:#770077;">,</span> <span style="color:#999999;">\'signup_form\'</span> <span style="color:#000077">))</span> <span style="color:#770077;">:</span> <span style="color:#000000">_e</span><span style="color:#000077">( $newsupercaptcha</span><span style="color:#770077;">-&gt;</span><span style="color:#007700">signup_form</span><span style="color:#000077">(  ) )</span><span style="color:#770077;">;</span> <span style="color:#000077">endif</span><span style="color:#770077;">;</span> <span style="color:#770000">?&gt;</span></code></p>

		  
		  <p>If allowed, you may also add this short code into a page or a widget to display the captcha image and form field for custom login widgets.</p>
		  <p><code style="margin-left:50px;">[supercaptcha]</code>
		  
		  <p>We are always open to suggestions and willing to work with other plugin authors to integrate our code with.  If you would like to see
		  Super Captcha in your favorite plugin, let that plugin author know that they can simply email us at support@goldsborowebdevelopment.com 
		  for a full integration API.</p>
			');
			
		$fonttext = ('
		  <h2> Using Fonts </h2>
		  <p>We have several fonts currently pre-installed into the system and you can change them at any time to fit your tastes.</p>
		  
		  <p><span style="color:#FF0000;"><strong>WARNING</strong></span><br />
		  Not all fonts may be currently tested for every situation to properly display in your browser.  With Distortion enabled and with some
		  more complex backgrounds, it is possible that a font will be unreadable.  We are working to fix this and even add a font size field in
		  a later version which should resolve this issue.  Be sure that you turn off all Super Captcha form protections before changing this field.</p>
			');
		
		$bgtext = ('
		  <h2> Using Backgrounds </h2>
		  <p>We have several backgrounds you can chose from.  Each background will add a diffrent level of protection and difficulty for bots that like
		  finding edges to try and figure out a CAPTCHA.  Because our CAPTCHA is unbreakable even with a solid black background, the backgrounds are merely
		  aesthetic.</p>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg1.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Digital Dream</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg2.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Blue Marble</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg3.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Blue Hue</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg4.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Blue Tile</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg5.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Blue Noise</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg6.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Granite</h3>
		  </div>
		  
		  <div style="display:inline-block;height:90px;width:150px;float:left;overflow:hidden;">
		  <img src="http://spam.goldsborowebdevelopment.com/verify/includes/super-captcha-bg7.jpg" width="145" alt="Digital Dream" />
		  <h3 style="margin-top:-75px;margin-left:15px;color:#FFFFFF!important;">Workshop Steel</h3>
		  </div>
		  
		  <div style="clear:both;"></div>
	  
			');

		$license = ($licout . '
		  <h2> Software License </h2>
		  <iframe src="' . plugins_url( 'docs/agpl.html', __FILE__ ) . '" style="width:100%;height:220px;"></iframe>
		  
		  <h2> Terms of Service </h2>
		  <iframe src="' . plugins_url( 'docs/tos.html', __FILE__ ) . '" style="width:100%;height:220px;"></iframe>

');
		$distortiontext = ('
		  <h2> Distortion </h2>
		  <p>This feature runs in conjunction with your background to add waves to the lines in the 3D image. Distortion just adds insult to injury for bots.  They already cannot reason out the missing text, but making it distorted sends any
		  programmer trying to teach a computer to interpret the image into a flying rage.</p>
		  
		  <h3>How It Works</h3>
		  <p>When enabling distortion, the image background is used to create a matrix which will determine how much distortion occurs.  For instance, the more complex a background is
		  the significantly more distortion that will be applied as the matrix believes your goal is complexity.</p>
		  	');
			
		$SCPro = ('
		<h2>Super Captcha Pro -- Why you need it</h2>
		<p>You are under no obligation to buy Super Captcha Pro.  In fact you can use this software as-is for as long as you like and we can keep our lights on.  But buying Super Captcha Pro
		Does a few great things:</p>
		<ul>
		  <li>You help us keep our lights on and feed our developers.</li>
		  <li>You get priority support and the ability to contact our 24/7 Support hotline.</li>
		  <li>You get improved protection <ul>
		    <li>Cloud Processing Auto-Learning Bot Behavior</li>
			<li>Pre-Blocking known bots from all your forms</li>
			<li>Instantly communicate with our server</li>
			<li>GEO IP blacklists (You decide what country signs up!)</li>
		  </ul></li>
		  <li>Get API access to our database to use our captcha on any platform</li>
		</ul>
		');
		
		$SCPaid = ('
		<h2>You\'re Awesome!</h2>
		<p>Thanks for going pro!  Think about this for a moment:  It takes only $100.00 per month to keep our servers online.  With your monthly subscription
		you will power us for half a day this month.  That might not sound like much, but think about it like this -- if only one percent of the users we had
		using SuperCaptcha 2.x had subscribed, we not only would have an excess of funding for the servers, we could add all the bells and whistles to this
		suite that would make heads spin.</p>
		<p>So go ahead, pat yourself on the back because you deserve it!  Remember to help encourage others to go pro as well.  The more we have subscribed to
		pro, the better we can make this software and service!</p>
		');
		
		$stillspam = ('
		<h2>Still Being Spammed?</h2>
		<p>There are a few things you want to check to make sure that this plugin is to blame when everything seems to be working but the spammers are still
		somehow getting through and signing up on your site.</p>
		<ul>
		 <li>Have you emptied your pending signups? No? That is the problem.  Yes? Keep reading.</li>
		 <li>If using mutisite or buddypress, have you tested to ensure that registrations can only happen on your main site where
		 this plugin is active? No? That is the problem.  Yes? Keep reading.</li>
		 <li>Have you disabled blog creation from the admin panel for subscribers? No? That is the problem.  Yes? Well... *sadface* keep reading.</li>
		 <li>And it comes to this.  Sadly there are companies out there that are paid to hire real humans pennies per hour to sit and solve CAPTCHAs all day.
		 
		 There is honestly nothing that can be done about it except to have Pro active so these people are caught when you mark them as spam and are then banned
		 for everyone using Pro.</li>
		</ul>
		');
		// Add my_help_tab if current screen is My Admin Page
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Overview',
			'title'	=> __('Overview'),
			'content'	=> '<p>' . __( $overview ) . '</p>',
			) );
		
		if ( $this->pro_license_check( ) == true )
			{
			$thisver = $SCPaid;
			$title = 'THANK YOU!';
			} else {
			$thisver = $SCPro;
			$title = 'Why Pro?';
			}
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Pro',
			'title'	=> __($title),
			'content'	=> '<p>' . __( $thisver ) . '</p>',
			) );
		
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha New',
			'title'	=> __('What\'s New'),
			'content'	=> '<p>' . __( $whatsnew ) . '</p>',
			) );			
			
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Manual',
			'title'	=> __('Manual Override'),
			'content'	=> '<p>' . __( $manual_override ) . '</p>',
			) );
			
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Fonts',
			'title'	=> __('Using Fonts'),
			'content'	=> '<p>' . __( $fonttext ) . '</p>',
			) );
			
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Backgrounds',
			'title'	=> __('Using Backgrounds'),
			'content'	=> '<p>' . __( $bgtext ) . '</p>',
			) );
		
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Distortion',
			'title'	=> __('Distortion Matrix'),
			'content'	=> '<p>' . __( $distortiontext ) . '</p>',
			) );
			
		$screen->add_help_tab( array(
			'id'	=> 'Super Captcha Spammed',
			'title'	=> __('Still Getting Spam'),
			'content' => '<p>' . __( $stillspam ) . '</p>',
			) );
		
		$screen->add_help_tab( array(
			'id'	=> 'GWD Weather Plugin License',
			'title'	=> __('License'),
			'content'	=> '<p>' . __( $license ) . '</p>',
			) );
			
		}
	function scshortcode()
		{
		$html = ('
			<label for="Image">'.esc_attr( get_option('img_field_name') ).'</label>
			' . $this->getCaptchaImage() . '
			<label for="SpamCode">'.esc_attr( get_option('txt_field_name') ).'</label>
			<input type="text" name="SpamCode" id="user_name" class="input" size="10" tabindex="50" />
			');
		return $html;
		}
	function pro_notice()
		{
		?>
		<div class="error"><p><strong>Super Captcha Pro</strong><br />
		Making the choice to go Pro with Super Captcha not only entitles you to better security, automated blocking, and auto-learning bot behaviors,
		but you help us keep the lights on and feed a few of our developers.  If only 1% of our users go Pro for one year, this service could sustain
		itself for another 20 years; though we would prefer hiring more guys to make this software even better!<br /><br />
		<a href="admin.php?page=new-super-captcha%2Fnew-super-captcha.php">Dismiss</a> <a href="https://my.goldsborowebdevelopment.com/cart.php?a=add&pid=70&sld=whmcs&tld=<?php echo $_SERVER['HTTP_HOST']; ?>" target="_blank" class="button" style="float:right;margin-top:-5px;">Try Pro Free &raquo;</a>
		
		</p></div>
		<?php
		}
	function first_setup()
		{
		if( !empty( $_REQUEST['page'] ) && $_REQUEST['page'] != 'super-captchasuper-captcha.php') :
			?>
			<div class="updated"><p><a href="admin.php?page=super-captcha/super-captcha.php" style="float:right" class="button button-secondary">Take Me There &raquo;</a><strong>HAIL CAPTAIN!</strong><br />
			You have just installed or upgraded Super Captcha but have not set it up yet or checked the configuration.<br />You're needed in the control room to hit some checkboxes and stuff to get it working. </p></div>
			<?php 
		endif;
		}
	function sc_widgets()
		{
		global $wp_meta_boxes;
		if( $this->pro_license_check( ) == true )
			{
			wp_add_dashboard_widget('super_captcha_checkip_widget', 'Super Captcha Check IP', array( &$this, 'check_widget' ) );	
			}
		wp_add_dashboard_widget('super_captcha_submitip_widget', 'Super Captcha Submit IP', array( &$this, 'submit_widget' ) );	
		}
	function submit_widget()
		{
		if(!empty( $_POST['reportip'] ))
			{
			if ( $this->report_spam( $_POST['reportip'] ) == true )
				{
				$thisresultb = ('<p><span style="color:#009900;"><strong>' . $_POST['reportip'] . ' was reported. <a class="button button-secondary" href="mailto:'.$this->get_abuse_email($_POST['reportip']).'?subject=Abuse Report for '.$_POST['reportip'].'&body=The IP '.$_POST['reportip'].', is automating signups and spamming my website, '.site_url().' over HTTP (port 80). Please review.">ISP Report &raquo;</a></strong></span></small></p>' );
				} else {
				$thisresultb = ('<p><span style="color:#990000;"><strong>ERROR! Could not report ' . $_POST['reportip'] . '. Either you have tried reporting too many times or your domain has been disabled from reporting.</strong></span></p>' );
				}
			}
		?>
			<p>You can manually submit a spammer here if they have spammed you. <strong>USE WITH CARE:</strong> Abuse of
			this feature will result in the inability to use it in the future.</p>
			<?php echo $thisresultb; ?>
			<form method="post" action="">
				<input type="text" name="reportip" />
				<input type="submit" name="Report &raquo;" class="button button-secondary" value="Report &raquo;" />
			</form>
		<?php
		}
	function check_widget()
		{
		if(!empty( $_POST['testip'] ))
			{
			if ( $this->pro_spam_check( $_POST['testip'] ) == true )
				{
				$thisresulta = ('<p><span style="color:#990000;"><strong>' . $_POST['testip'] . ' is listed.</strong></span> <small>('.$this->thickboxlink( 'https://my.goldsborowebdevelopment.com/submitticket.php?step=2&deptid=7','Submit Removal' ).')</small> <a class="button button-secondary" href="mailto:'.$this->get_abuse_email($_POST['testip']).'?subject=Abuse Report for '.$_POST['testip'].'&body=The IP '.$_POST['testip'].', is automating signups and spamming my website, '.site_url().' over HTTP (port 80). Please review.">ISP Report &raquo;</a></p>' );
				} else {
				$thisresulta = ('<p><span style="color:#009900;"><strong>' . $_POST['testip'] . ' is clean.</strong></span></p>' );
				}
			}
		?>
			<p>Every now and then, that guy will say he's being blocked. So you can test here and request removal.</p>
			<?php echo $thisresulta; ?>
			<form method="post" action="">
				<input type="text" name="testip" />
				<input type="submit" name="Check &raquo;" class="button button-secondary" value="Check &raquo;" />
			</form>
		<?php
		}
	}
?>