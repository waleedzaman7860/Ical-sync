<?php
/**
 * Plugin Name: iCal Sync
 * Plugin URI: https://www.nothing.com/
 * Description: iCal Sync functionality.
 * Version: 0.1
 * Author: vivestia
 * Author URI: https://www.testinggg.com/
 * License: GPL2
 **/

// UNIQUE prefix: icalt

// If this file is called directly, abort.
if ( ! function_exists( 'add_action' ) ) {
    echo "You shouldn't be here...!!!";
    exit;
}

include 'ical-sync-cron.php'; // including the cron functions and hooks

//////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////// ACTIVATE / DEACTIVATE PLUGIN ///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////

// Register the cron job when the plugin is activated
register_activation_hook( __FILE__, 'ical_sync_plugin_activate' );
function ical_sync_plugin_activate() {
    if ( ! wp_next_scheduled( 'icalsync_cron_event' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'icalsync_cron_event' );
    }
}

// Unregister the cron job when the plugin is deactivated
register_deactivation_hook( __FILE__, 'ical_sync_plugin_deactivate' );
function ical_sync_plugin_deactivate() {
    wp_clear_scheduled_hook( 'icalsync_cron_event' );
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Creating callback functions ( For getting last updated time by sending post_id to server form jquery function. ) //
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////
//				ROOMS			//
//////////////////////////////////
function rooms_last_updated_time_action_callback() {
    $post_id = $_POST['variable'];
    if ($post_id) {
        $time = get_post_meta( $post_id, 'sys_created', true );
        if ( !empty( $time ) ) {
            echo '(Last updated: ' . date( 'Y-m-d H:i:s', $time ) . ')<br>';
        }
    }
    else {
        echo "Error!";
    }
    /*
        The default response from admin-ajax.php is,
        die( '0' );
        ...by adding your own wp_die() or exit() or die() after returning your desired content prevents the default response from admin-ajax.php being returned as well.
        It also generally means that your ajax call has succeeded.
    */
    wp_die();
}

add_action( 'wp_ajax_rooms_last_updated_time_action', 'rooms_last_updated_time_action_callback' );
add_action( 'wp_ajax_nopriv_rooms_last_updated_time_action', 'rooms_last_updated_time_action_callback' );


//////////////////////////////////
//				RENTALS			//
//////////////////////////////////
function rentals_last_updated_time_action_callback() {
    $post_id = $_POST['variable'];
    if ($post_id) {
        $time = get_post_meta( $post_id, 'sys_created', true );
        if ( !empty( $time ) ) {
            echo '(Last updated: ' . date( 'Y-m-d H:i:s', $time ) . ')<br>';
        }
    }
    else {
        echo "Error!";
    }
    /*
        The default response from admin-ajax.php is,
        die( '0' );
        ...by adding your own wp_die() or exit() or die() after returning your desired content prevents the default response from admin-ajax.php being returned as well.
        It also generally means that your ajax call has succeeded.
    */
    wp_die();
}

add_action( 'wp_ajax_rentals_last_updated_time_action', 'rentals_last_updated_time_action_callback' );
add_action( 'wp_ajax_nopriv_rentals_last_updated_time_action', 'rentals_last_updated_time_action_callback' );


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////


function icalt_sync_func(){

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
    $meta_values_rooms = array();
    foreach( $posts_rooms as $post ) {
        $meta_values_rooms[] = array($post->ID, get_post_meta( $post->ID, $meta_key, true ));
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
        $meta_values_rentals[] = array($post->ID, get_post_meta( $post->ID, $meta_key, true ));
    }

    ?>
    <div id='wrapper' style='text-align: center;'>
        <p style="color:red;font-weight:600;">IMPORTANT: Please do not refresh the page while running the import process.</p>
        <div id="rooms_div" style="display: inline-block; padding:20px; margin:20px; vertical-align: top; text-align:center;" >
            <p><?php echo "Total Rooms: " . count($posts_rooms); ?></p>
            <button class="button button-primary button-large" id="rooms_save_ical">Import Rooms</button>
            <img class="spinner spinner-import" style="display: none; float: none; visibility: visible;" src="/wp-admin/images/spinner.gif" alt="spinner">
            <p><i id="rooms_last_updated_time"></i></p>
            <p><i id="rooms_ical_data_import_status"></i></p>
            <div style="color:green;" id="rooms_form-message"></div>
        </div>
        <div id="rentals_div" style="display: inline-block; padding:20px; margin:20px; vertical-align: top; text-align:center;" >
            <p><?php echo "Total Rentals: " . count($posts_rentals); ?></p>
            <button class="button button-primary button-large" id="rentals_save_ical">Import Rentals</button>
            <img class="spinner spinner-import" style="display: none; float: none; visibility: visible;" src="/wp-admin/images/spinner.gif" alt="spinner">
            <p><i id="rentals_last_updated_time"></i></p>
            <p><i id="rentals_ical_data_import_status"></i></p>
            <div style="color:green;" id="rentals_form-message"></div>
        </div>
    </div>
    <hr>

    <script>
        jQuery(function($){

            var flag = false;
            var body = $('body');

            //////////////////////////////////
            //				ROOMS			//
            //////////////////////////////////
            var rooms_meta_values = <?php echo json_encode($meta_values_rooms); ?>;
            var rooms_currentIndex = 0;
            var rooms_ical_page_id = rooms_meta_values[rooms_currentIndex][0];
            var rooms_ical_url = rooms_meta_values[rooms_currentIndex][1];

            function rooms_getLastUpdatedTime(){
                var data = {
                    action: 'rooms_last_updated_time_action',
                    variable: rooms_ical_page_id
                };
                $.post(ajaxurl, data, function(response) {
                    $('#rooms_last_updated_time').html(response);
                });
            }
            rooms_getLastUpdatedTime();

            body.on('click', '#rooms_save_ical', function(event){
                event.preventDefault();
                var parent = $(this).parent(),
                    t = $(this),
                    spinner = $('.spinner-import', parent),
                    message = $('#rooms_form-message', parent);
                if(flag){
                    return false;
                }
                flag = true;
                spinner.show();
                function rooms_postData() {
                    if (rooms_currentIndex > rooms_meta_values.length) {
                        flag = false;
                        spinner.hide();
                        rooms_getLastUpdatedTime();
                        $('#rooms_ical_data_import_status').html("<p><span style='font-weight: 600; background-color: green; color: white; padding: 5px; border-radius: 5px;'>Task completed successfully!</span></p>");
                        return;
                    }
                    rooms_ical_page_id = rooms_meta_values[rooms_currentIndex][0];
                    rooms_ical_url = rooms_meta_values[rooms_currentIndex][1];
                    var currentData = {
                        "action" : "st_import_ical",
                        "url" : rooms_ical_url,
                        "post_id" : rooms_ical_page_id,
                        "ical_type" : $("input[name='type_ical']:checked").val(),
                        "security" : st_params._s
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: currentData,
                        success: function(response) {
                            //console.log("Processing ID# " + rooms_currentIndex +"/"+ rooms_meta_values.length);
                            // Move on to the next item in the array
                            rooms_currentIndex++;
                            message.html("Processing ID# " + rooms_currentIndex +"/"+ rooms_meta_values.length);

                            // If there are more items, post the next one
                            if (rooms_currentIndex < rooms_meta_values.length) {
                                rooms_postData();
                            }
                            else {
                                flag = false;
                                spinner.hide();
                                rooms_getLastUpdatedTime();
                                $('#rooms_ical_data_import_status').html("<p><span style='font-weight: 600; background-color: green; color: white; padding: 5px; border-radius: 5px;'>Task completed successfully!</span></p>");
                                return;
                            }
                        },
                        error: function(error) {
                            console.log("Error occurred:", error);
                        }
                    });
                }

                // Start the data posting process
                rooms_postData();
            }); // ROOMS button click function ended.



            //////////////////////////////////
            //				RENTALS			//
            //////////////////////////////////
            var rentals_meta_values = <?php echo json_encode($meta_values_rentals); ?>;
            var rentals_currentIndex = 0;
            var rentals_ical_page_id = rentals_meta_values[rentals_currentIndex][0];
            var rentals_ical_url = rentals_meta_values[rentals_currentIndex][1];

            function rentals_getLastUpdatedTime(){
                var data = {
                    action: 'rentals_last_updated_time_action',
                    variable: rentals_ical_page_id
                };
                $.post(ajaxurl, data, function(response) {
                    $('#rentals_last_updated_time').html(response);
                });
            }
            rentals_getLastUpdatedTime();

            body.on('click', '#rentals_save_ical', function(event){
                event.preventDefault();
                var parent = $(this).parent(),
                    t = $(this),
                    spinner = $('.spinner-import', parent),
                    message = $('#rentals_form-message', parent);
                if(flag){
                    return false;
                }
                flag = true;
                spinner.show();
                function rentals_postData() {
                    if (rentals_currentIndex > rentals_meta_values.length) {
                        flag = false;
                        spinner.hide();
                        rentals_getLastUpdatedTime();
                        $('#rentals_ical_data_import_status').html("<p><span style='font-weight: 600; background-color: green; color: white; padding: 5px; border-radius: 5px;'>Task completed successfully!</span></p>");
                        return;
                    }
                    rentals_ical_page_id = rentals_meta_values[rentals_currentIndex][0];
                    rentals_ical_url = rentals_meta_values[rentals_currentIndex][1];
                    var currentData = {
                        "action" : "st_import_ical",
                        "url" : rentals_ical_url,
                        "post_id" : rentals_ical_page_id,
                        "ical_type" : $("input[name='type_ical']:checked").val(),
                        "security" : st_params._s
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: currentData,
                        success: function(response) {
                            console.log("Processing ID# " + rentals_currentIndex +"/"+ rentals_meta_values.length);
                            // Move on to the next item in the array
                            rentals_currentIndex++;
                            message.html("Processing ID# " + rentals_currentIndex +"/"+ rentals_meta_values.length);

                            // If there are more items, post the next one
                            if (rentals_currentIndex < rentals_meta_values.length) {
                                rentals_postData();
                            }
                            else {
                                flag = false;
                                spinner.hide();
                                rentals_getLastUpdatedTime();
                                $('#rentals_ical_data_import_status').html("<p><span style='font-weight: 600; background-color: green; color: white; padding: 5px; border-radius: 5px;'>Task completed successfully!</span></p>");
                                return;
                            }
                        },
                        error: function(error) {
                            console.log("Error occurred:", error);
                        }
                    });
                }

                // Start the data posting process
                rentals_postData();
            }); // RENTALS button click function ended.



        }); // jquery ended
    </script>
    <?php
}
//add_action( 'icalt_add_every_three_minutes_event', 'icalt_sync_func' );


// ADD PLUGIN TO ADMIN MENU
function icalt_add_my_custom_menu(){
    add_menu_page("iCal Sync","iCal Sync", "manage_options", "ical-sync","","dashicons-plugins-checked",2);

    add_submenu_page(
        "ical-sync", // the parent menu slug
        "iCal Testing", // sub menu page title
        "SubMenu Item", // sub menu item text
        "manage_options", // capabilities: setting to administrative rights
        "ical-sync", // your sub menu slug: write it in accordance with your sub menu name
        "icalt_sync_func", // call back function for sub menu item
    );
}
add_action("admin_menu","icalt_add_my_custom_menu");
