<?php
 
// Defining plugin constants:
define("LOGFILE", WP_PLUGIN_DIR."/ical-sync/icalsync_cron.log");

// Run the cron job when the event is triggered
add_action( 'icalsync_cron_event', 'icalsync_cron_job_main_func' );
function icalsync_cron_job_main_func() {

	file_put_contents(LOGFILE, print_r("=================================\tINIT TIME:\t" . date("Y-m-d H:i:s") . "\t=====================================", true) . "\n");

	$meta_key = 'ical_url'; // same as it is in database table

	//////////////////////////////////
	//				ROOMS			//
	//////////////////////////////////
	// get ical hotel_room meta values from database
	$post_type_rooms = 'hotel_room'; // same as it is in database table
	// get all the post ids from database, based on this query
    $posts_rooms = get_posts(
        array(
            'post_type' => $post_type_rooms,
            'meta_key' => $meta_key,
            'posts_per_page' => -1,
        )
    );
	// fetch all the relevant posts and save in associate Array: post_id, ical_url
    foreach( $posts_rooms as $post ) {
		$postID = $post->ID;
		$postURL = get_post_meta( $post->ID, $meta_key, true );
		run_ical_sync_process($post_type_rooms, $postID, $postURL);
    }



	//////////////////////////////////
	//				RENTALS			//
	//////////////////////////////////
	// get ical st_rental meta values from database
	$post_type_rental = 'st_rental'; // all
    $posts_rentals = get_posts(
        array(
            'post_type' => $post_type_rental,
            'meta_key' => $meta_key,
            'posts_per_page' => -1,
        )
    );
	// fetch all the relevant posts and save in associate Array: post_id, ical_url
    $meta_values_rentals = array();
    foreach( $posts_rentals as $post ) {
		$postID = $post->ID;
		$postURL = get_post_meta( $post->ID, $meta_key, true );
		run_ical_sync_process($post_type_rental, $postID, $postURL);
    }

	file_put_contents(LOGFILE, print_r("[=====\tCRON-TASK-COMPLETED AT:\t\t" . date("Y-m-d H:i:s"), TRUE) . "\t=====]\n", FILE_APPEND);
		
}

function run_ical_sync_process($post_type, $post_id, $url) {
	//file_put_contents(LOGFILE, print_r("Processing Now:\n\tPT: " . $post_type . "\n\tPI: " . $post_id . "\n\tURL: " . $url, TRUE) . "\n", FILE_APPEND);
	file_put_contents(LOGFILE, print_r("[" . date("Y-m-d H:i:s") . "]\tProcessing -> \t[Post_Type: '" . $post_type . "']\t[Post_ID: '" . $post_id, TRUE) . "']", FILE_APPEND);
	if (!empty($url) && in_array($post_type, ['hotel_room', 'st_rental', 'st_tours', 'st_activity'])) {
	$ical = new ICal($url);
	if (!empty($ical)) {

		//file_put_contents(LOGFILE, "Url fetched successfuly: " . $url . "\n", FILE_APPEND);

		$events = $ical->events();
		$result_total = 0;

		if (!empty($events) && is_array($events)) {

			//file_put_contents(LOGFILE, "Entered events check!" . "\n", FILE_APPEND);

			foreach ($events as $key => $event) {
				$sumary = explode('|', $event['SUMMARY']);
				$price = 0;
				$available = 'available';
				if ($post_type == 'st_rental') {
					if ($sumary[0] == 'Not available' || $sumary[0] == 'Blocked' || !is_numeric($sumary[0])) {
						$available = 'unavailable';
					} else {
						$price = (float) $sumary[0];
						if ( $price < 0 ) {
							$price = 0;
						}
					}
					if ( isset( $sumary[1] ) && ! empty( $sumary[1] ) && strtolower( $sumary[1] ) == 'unavailable' ) {
						$available = 'unavailable';
					}
					if ( isset( $event['DTSTART'] ) && isset( $event['DTEND'] ) ) {
						if ( strlen( $event['DTSTART'] ) > 8 ) {
							$event['DTSTART'] = substr( $event['DTSTART'], 0, 8 );
						}
						if ( strlen( $event['DTEND'] ) > 8 ) {
							$event['DTEND'] = substr( $event['DTEND'], 0, 8 );
						}
						$start        = DateTime::createFromFormat( 'Ymd', $event['DTSTART'] );
						$start        = strtotime( $start->format( 'Y-m-d' ) );
						$end          = DateTime::createFromFormat( 'Ymd', $event['DTEND'] );
						$end          = strtotime( $end->format( 'Y-m-d' ) );
						$end          = strtotime( '-1 day', $end );
						//file_put_contents(LOGFILE, print_r("Submitting: \n\tpost_id: " . $post_id . "\n\tpost_type: ". $post_type . "\n\tprice: ". $price . "\n\tstart: ". $start . "\n\tend: ". $end . "\n\tavailable: ". $available, true) . "\n", FILE_APPEND);
						$res          = import_event( $post_id, $post_type, $price, $start, $end, $available );
						$result_total += $res;									
					}
				} elseif ( $post_type == 'hotel_room' ) {

					//file_put_contents(LOGFILE, "Entered post type: hotel_room. => Summary: " . $sumary[0] . "\n", FILE_APPEND);

					if ($sumary[0] == 'Not available' || $sumary[0] == 'Blocked' || !is_numeric($sumary[0])) {
						//file_put_contents(LOGFILE, print_r("summary-1 unavailable: <" . $sumary[0] . ">", true) . "\n", FILE_APPEND);
						$available = 'unavailable';
					} else {
						//file_put_contents(LOGFILE, "Summary available settings prices etc!" . "\n", FILE_APPEND);
						$price = (float) $sumary[0];
						if ( $price < 0 ) {
							$price = 0;
						}
						if (isset( $sumary[1] ))
							$adult_price = floatval( $sumary[1] );
						if (isset( $sumary[2] ))
							$child_price = floatval( $sumary[2] );
					}
					if ( isset( $sumary[1] ) && ! empty( $sumary[1] ) && strtolower( $sumary[1] ) == 'unavailable' ) {
						//file_put_contents(LOGFILE, print_r("summary-2 unavailable", true) . "\n", FILE_APPEND);
						$available = 'unavailable';
					}
					if ( isset( $event['DTSTART'] ) && isset( $event['DTEND'] ) ) {
						if ( strlen( $event['DTSTART'] ) > 8 ) {
							$event['DTSTART'] = substr( $event['DTSTART'], 0, 8 );
						}
						if ( strlen( $event['DTEND'] ) > 8 ) {
							$event['DTEND'] = substr( $event['DTEND'], 0, 8 );
						}
						//file_put_contents(LOGFILE, "Date start: " . $event['DTSTART']. " Date end: " . $event['DTEND'] . "\n", FILE_APPEND);
						$start        = DateTime::createFromFormat( 'Ymd', $event['DTSTART'] );
						$start        = strtotime( $start->format( 'Y-m-d' ) );
						$end          = DateTime::createFromFormat( 'Ymd', $event['DTEND'] );
						$end          = strtotime( $end->format( 'Y-m-d' ) );
						$end          = strtotime( '-1 day', $end );
						//file_put_contents(LOGFILE, print_r("all good till $end: " . $end , true) . "\n", FILE_APPEND);
						//file_put_contents(LOGFILE, print_r("Submitting: \n\tpost_id: " . $post_id . "\n\tpost_type: ". $post_type . "\n\tprice: ". $price . "\n\tstart: ". $start . "\n\tend: ". $end . "\n\tavailable: ". $available . "\n\tadult_price: ". $adult_price . "\n\tchild_price: ". $child_price, true) . "\n", FILE_APPEND);
						$res          = import_event_hotel_room( $post_id, $post_type, $price, $start, $end, $available, $adult_price, $child_price );
						//file_put_contents(LOGFILE, "RES: " . print_r($res, true) . "\n", FILE_APPEND);
						$result_total += $res;
						//file_put_contents(LOGFILE, print_r("result_total: " . $result_total, true) . "\n", FILE_APPEND);
					}
				}elseif($post_type == 'st_tours' or $post_type == 'st_activity'){
					if ($sumary[0] == 'Not available' || $sumary[0] == 'Blocked' || !is_numeric($sumary[0])) {
						$available = 'unavailable';
					}
					if ( isset( $sumary[1] ) && ! empty( $sumary[1] ) && strtolower( $sumary[1] ) == 'unavailable' ) {
						$available = 'unavailable';
					}
					$adult_price = $child_price = $infant_price = $base_price = 0;
					$group_day = 0;
					if($available != 'unavailable'){
						$adult_price = (float)$sumary[0];

						if (isset($sumary[1])) {
							$child_price = (float)$sumary[1];
						}

						if (isset($sumary[2])) {
							$infant_price = (float)$sumary[2];
						}

						if (isset($sumary[3])) {
							$base_price = (float)$sumary[3];
						}

						if (isset($sumary[4]) && !empty($sumary[4]) && strtolower($sumary[4]) == 'unavailable') {
							$available = 'unavailable';
						}
						if (isset($sumary[5]) && (int)$sumary[5] > 0) {
							$group_day = 1;
						}
					}
					if (isset($event['DTSTART']) && isset($event['DTEND'])) {
						if (strlen($event['DTSTART']) > 8) {
							$event['DTSTART'] = substr($event['DTSTART'], 0, 8);
						}
						if (strlen($event['DTEND']) > 8) {
							$event['DTEND'] = substr($event['DTEND'], 0, 8);
						}
						$start = DateTime::createFromFormat('Ymd', $event['DTSTART']);
						$start = strtotime($start->format('Y-m-d'));
						$end = DateTime::createFromFormat('Ymd', $event['DTEND']);
						$end = strtotime($end->format('Y-m-d'));
						$end = strtotime('-1 day', $end);
						$res = import_calendar_tour($post_id, $post_type, $adult_price, $child_price, $infant_price, $base_price, $group_day, $start, $end, $available);
						$result_total += $res;
					}
				}
			}
		}
	}
}
	if($result_total > 0){
		update_post_meta($post_id, 'sys_created', current_time('timestamp', 1));
		update_post_meta($post_id, 'ical_url', $url);
		echo json_encode([
			'status' => 1,
			'message' => '<p class="text-success">' . __('Successful!', 'traveler') . '</p>'
		]);

		file_put_contents(LOGFILE, "\t[STATUS => 1]" . "\n", FILE_APPEND);

		//die;
	}else{
		echo json_encode([
			'status' => 1,
			'message' => '<p class="text-danger">' . __('Import failed!', 'traveler') . '</p>'
		]);

		file_put_contents(LOGFILE, "\t[STATUS => 0]" . "\n", FILE_APPEND);

		//die;
	}
}

function import_event($post_id, $post_type, $price, $start, $end, $available) {

	//file_put_contents(LOGFILE, "Entered import_event() function." . "\n", FILE_APPEND);

	global $wpdb;
	$table = $wpdb->prefix . 'st_room_availability';
	if($post_type == 'st_rental'){
		$table = $wpdb->prefix . 'st_rental_availability';
	}
	$sql = "SELECT
			count(id)
		FROM
			{$table}
		WHERE
			post_id = {$post_id}
		AND (
			(
				{$start} BETWEEN check_in
				AND check_out
			)
			OR (
				{$end} BETWEEN check_in
				AND check_out
			)
		)";

	$count = (int)$wpdb->get_var($sql);
	$string = '';

	$number = $parent_id = $booking_period = $adult_number = $child_number = 0;
	$allow_full_day = 'on';

	if($post_type == 'hotel_room'){
		$number = get_post_meta($post_id, 'number_room', true);
		$parent_id = get_post_meta($post_id, 'room_parent', true);
		$booking_period = get_post_meta($parent_id, 'hotel_booking_period', true);
		$allow_full_day = get_post_meta($post_id, 'allow_full_day', true);
		$adult_number = get_post_meta($post_id, 'adult_number', true);
		$child_number = get_post_meta($post_id, 'children_number', true);
	}else{
		$number = get_post_meta($post_id, 'rental_number', true);
		$booking_period = get_post_meta($parent_id, 'rentals_booking_period', true);
		$allow_full_day = get_post_meta($post_id, 'allow_full_day', true);
		$adult_number = get_post_meta($post_id, 'rental_max_adult', true);
		$child_number = get_post_meta($post_id, 'rental_max_children', true);
	}


	if ( $count == 0 ) {
		for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
			$string .= $wpdb->prepare("(null, %d, %d, %d, %s, %d, %d, %s, %s,%s, %s, %s, %s),", $number, $parent_id, $booking_period, $allow_full_day, $adult_number, $child_number, $post_id, $post_type, $i, $i, $price, $available);
		}
	}else{
		for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
			$sql_del = "
						DELETE
						FROM
							{$table}
						WHERE
							post_id = {$post_id}
						AND check_in = {$i}
						AND check_out = {$i}
						";
			$wpdb->query($sql_del);
			$string .= $wpdb->prepare("(null, %d, %d, %d, %s, %d, %d, %s, %s,%s, %s, %s, %s),", $number, $parent_id, $booking_period, $allow_full_day, $adult_number, $child_number, $post_id, $post_type, $i, $i, $price, $available);
		}
	}
	if (!empty($string)) {
		$string = substr($string, 0, -1);
		$sql = "INSERT INTO {$table} (id, `number`, parent_id, booking_period, allow_full_day, adult_number, child_number, post_id, post_type,check_in,check_out,price, status) VALUES {$string}";
		$result = $wpdb->query($sql);
		return $result;
	}else{
		return 0;
	}
}

function import_event_hotel_room($post_id, $post_type, $price, $start, $end, $available, $adult_price=0, $child_price=0){
	//file_put_contents(LOGFILE, "Entered import_event_hotel_room() function." . "\n", FILE_APPEND);
	
	global $wpdb;
	$table = $wpdb->prefix . 'st_room_availability';
	$sql = "SELECT
			count(id)
		FROM
			{$table}
		WHERE
			post_id = {$post_id}
		AND (
			(
				{$start} BETWEEN check_in
				AND check_out
			)
			OR (
				{$end} BETWEEN check_in
				AND check_out
			)
		)";

	$count = (int)$wpdb->get_var($sql);
	$string = '';

	$number = $parent_id = $booking_period = $adult_number = $child_number = 0;
	$allow_full_day = 'on';

	if($post_type == 'hotel_room'){
		$number = get_post_meta($post_id, 'number_room', true);
		$parent_id = get_post_meta($post_id, 'room_parent', true);
		$booking_period = get_post_meta($parent_id, 'hotel_booking_period', true);
		$allow_full_day = get_post_meta($post_id, 'allow_full_day', true);
		$adult_number = get_post_meta($post_id, 'adult_number', true);
		$child_number = get_post_meta($post_id, 'children_number', true);
	}


	if ( $count == 0 ) {
		for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
			$string .= $wpdb->prepare("(null, %d, %d, %d, %s, %d, %d, %s, %s,%s, %s, %s, %s, %s, %s),", $number, $parent_id, $booking_period, $allow_full_day, $adult_number, $child_number, $post_id, $post_type, $i, $i, $price, $available, $adult_price, $child_price);
		}
	}else{
		for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
			$sql_del = "
						DELETE
						FROM
							{$table}
						WHERE
							post_id = {$post_id}
						AND check_in = {$i}
						AND check_out = {$i}
						";
			$wpdb->query($sql_del);
			$string .= $wpdb->prepare("(null, %d, %d, %d, %s, %d, %d, %s, %s,%s, %s, %s, %s, %s, %s),", $number, $parent_id, $booking_period, $allow_full_day, $adult_number, $child_number, $post_id, $post_type, $i, $i, $price, $available, $adult_price, $child_price);
		}
	}
	if (!empty($string)) {
		$string = substr($string, 0, -1);
		$sql = "INSERT INTO {$table} (id, `number`, parent_id, booking_period, allow_full_day, adult_number, child_number, post_id, post_type,check_in,check_out,price, status, adult_price, child_price) VALUES {$string}";
		$result = $wpdb->query($sql);
		//file_put_contents(LOGFILE, "\t[FUNC: import_event_hotel_room() returned: 1]", FILE_APPEND);
		return $result;
	}else{
		//file_put_contents(LOGFILE, "\t[FUNC: import_event_hotel_room() returned: 0]", FILE_APPEND);
		return 0;
	}
}

function import_calendar_tour($post_id, $post_type, $adult_price, $child_price, $infant_price, $base_price, $group_day, $start, $end, $available){
	
	//file_put_contents(LOGFILE, "Entered import_calendar_tour() function." . "\n", FILE_APPEND);
	
	global $wpdb;
	$table = $wpdb->prefix . 'st_tour_availability';
	if($post_type == 'st_activity'){
		$table = $wpdb->prefix . 'st_activity_availability';
	}
	$sql = "SELECT
			count(id)
		FROM
			{$table}
		WHERE
			post_id = {$post_id}
		AND (
			(
				{$start} BETWEEN check_in
				AND check_out
			)
			OR (
				{$end} BETWEEN check_in
				AND check_out
			)
		)";

	$count = (int)$wpdb->get_var($sql);
	$string = '';

	$tour_period = get_post_meta($post_id, 'tours_booking_period', true);
	$max_people = get_post_meta($post_id, 'max_people', true);
	if(empty($max_people))
		$max_people = 0;

	if ( $count == 0 ) {
		if($group_day != 1 || $available == 'unavailable') {
			for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
				$string .= $wpdb->prepare("(null, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s),", $max_people, $tour_period, $post_id, $i, $i, $adult_price, $child_price, $infant_price, $base_price, $group_day, $available);
			}
		}else{
			$string = $wpdb->prepare("(null, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s),", $max_people, $tour_period, $post_id, $start, $end, $adult_price, $child_price, $infant_price, $base_price, $group_day, $available);
		}
	}else{
		if($group_day != 1 || $available == 'unavailable') {
			for ($i = (int)$start; $i <= (int)$end; $i = strtotime('+1 day', $i)) {
				$sql_del = "
						DELETE
						FROM
							{$table}
						WHERE
							post_id = {$post_id}
						AND check_in = {$i}
						AND check_out = {$i}
						";
				$wpdb->query($sql_del);
				$string .= $wpdb->prepare("(null, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s),", $max_people, $tour_period, $post_id, $i, $i, $adult_price, $child_price, $infant_price, $base_price, $group_day, $available);
			}
		}else{
			$sql_del = "
						DELETE
						FROM
							{$table}
						WHERE
							post_id = {$post_id}
						AND check_in = {$start}
						AND check_out = {$end}
						";
			$wpdb->query($sql_del);
			$string = $wpdb->prepare("(null, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s),", $max_people, $tour_period, $post_id, $start, $end, $adult_price, $child_price, $infant_price, $base_price, $group_day, $available);
		}
	}
	if (!empty($string)) {
		$string = substr($string, 0, -1);
		$sql = "INSERT INTO {$table} (id, `number`, booking_period, post_id,check_in,check_out,adult_price,child_price,infant_price, price, groupday, status) VALUES {$string}";
		$result = $wpdb->query($sql);
		return $result;
	}else{
		return 0;
	}
}

