<?php

/**
 *  Plugin Name: WP Video Sync
 *  Author: Nic Scott
 *  Version: 0.1
 *  Description: Save the metadata of videos into the wp postmeta table. Requires SDKS to be installed first.
 * 
 */


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}




//Dependencies
include(__DIR__ . '/vendor/autoload.php');

//Load our files
include(__DIR__ . '/inc/Admin.class.php');
include(__DIR__ . '/inc/VimeoClient.class.php');
include(__DIR__ . '/inc/Scheduler.class.php');
include(__DIR__ . '/inc/Sync.class.php');
include(__DIR__ . '/inc/AlbumSync.class.php');
include(__DIR__ . '/inc/VideoSync.class.php');


function WPVS_Vimeo()
{

    return \WPVS\VimeoClient::get_instance();
}


WPVS_Vimeo();



function WPVS_Scheduler()
{

    return \WPVS\Scheduler::get_instance();
}


WPVS_Scheduler();









/**
 *  Debug function
 * 
 * 
 */

if (!function_exists('mydump')) {

    function mydump($stuff)
    {

        if (!is_admin()) {
            var_dump($stuff);
        }
    }
}






/**
 *   Testing
 */


add_action('init', function () {

    if ( is_admin() ) {

        return;
    }

    if ( isset( $_GET['video_sync_test' ])) {

        echo 'Video Sync Test';

        $VideoSync = new WPVS\VideoSync('/videos/536092033', '5');

        echo '<pre>';
        var_dump($VideoSync);
        echo '</pre>';

        die();

    } elseif ( isset( $_GET['album_sync_test' ] ) ) {


        echo 'Album Sync Test';

        $AlbumSync = new WPVS\AlbumSync( 'https://vimeo.com/showcase/8215098', '5');
       

    

        echo '<pre>';
        var_dump(  $AlbumSync );
        echo '</pre>';

        die();

    }


    
});
