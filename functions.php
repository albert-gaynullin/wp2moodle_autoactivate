<?php
add_action( 'woocommerce_order_status_completed', 'auto_activate', 10, 1);
function auto_activate( $order_id ) {
	$order = wc_get_order( $order_id );
	$downloads = $order->get_downloadable_items();
	$user_id = $order->get_user_id();
	$user = get_userdata( $user_id );
	foreach ($downloads as $download) {
		$activate_url = get_wp2moodle_activate_url($download["download_url"], $user);
		file_get_contents($activate_url);
		curl_close($curl);
	}
}

function get_wp2moodle_activate_url($url, $user) {

	if (strpos($url, 'wp2moodle.txt') !== false) {
		// mp url is full url = including http:// and so on... we want the file url
		$path = $_SERVER['DOCUMENT_ROOT'] . parse_url($url)["path"];
		$cohort = "";
		$group = "";
		$data = file($path); // now it's an array!
		foreach ($data as $row) {
			$pair = explode("=",$row);
			switch (strtolower(trim($pair[0]))) {
				case "group":
					$group = trim(str_replace(array('\'','"'), '', $pair[1]));
					break;
				case "cohort":
					$cohort = trim(str_replace(array('\'','"'), '', $pair[1]));
					break;
				case "course":
					$course = trim(str_replace(array('\'','"'), '', $pair[1]));
					break;
				case "activity":
					$activity = trim(str_replace(array('\'','"'), '', $pair[1]));
					break;
			}
		}
		$url = wp2moodle_generate_activate_hyperlink($user, $cohort,$group,$course,$activity);
	}
	return $url;
}

function wp2moodle_generate_activate_hyperlink($user, $cohort,$group,$course,$activity = 0) {

	$update = get_option('wp2m_update_details') ?: "true";

    $enc = array(
		"offset" => rand(1234,5678),						// just some junk data to mix into the encryption
		"stamp" => time(),									// unix timestamp so we can check that the link isn't expired
		"firstname" => $user->user_firstname,		// first name
		"lastname" => $user->user_lastname,			// last name
		"email" => $user->user_email,				// email
		"username" => $user->user_login,			// username
		"passwordhash" => $user->user_pass,			// hash of password (we don't know/care about the raw password)
		"idnumber" => $user->ID,					// int id of user in this db (for user matching on services, etc)
		"cohort" => $cohort,								// string containing cohort to enrol this user into
		"group" => $group,									// string containing group to enrol this user into
		"course" => $course,								// string containing course id, optional
		"updatable" => $update,								// if user profile fields can be updated in moodle
		"activity" => $activity						// index of first [visible] activity to go to, if auto-open is enabled in moodle
	);

	// encode array as querystring
	$details = http_build_query($enc);

	// encryption = 3des using shared_secret
	return rtrim(get_option('wp2m_moodle_url'),"/").WP2M_MOODLE_PLUGIN_URL.encrypt_string($details, get_option('wp2m_shared_secret'));
	//return get_option('wp2m_moodle_url').WP2M_MOODLE_PLUGIN_URL.'=>'.$details;
}
?>
