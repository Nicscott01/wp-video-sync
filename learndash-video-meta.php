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







add_action('add_meta_boxes', function () {

    add_meta_box(
        'vimeo-collection-test',
        __('Vimeo Collection Settings Test'),
        'album_metabox',
        ['sfwd-courses'],
        'advanced',
        'high',
        []
    );


}, 20);


function album_metabox()
{

    global $post;

    $value = get_post_meta( $post->ID, '_album_url', true );

?>
    <div class="album sfwd sfwd_options ">
        <label for="album-url">Showcase URL</label>
        <input id="album-url" type="text" name="_album_url" value="<?php echo $value ?>" placeholder="Vimeo Showcase URL">
    </div>
<?php

}


/**
 *  Save the Album URL
 * 
 */

add_action( 'save_post', function( $post_id ) {

    if ( isset( $_POST['_album_url'] ) && !empty( $_POST['_album_url'] ) ) {

        update_post_meta( $post_id, '_album_url', $_POST['_album_url'] ); 

    }

});





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

        $VideoSync = new WPVS\VideoSync('/videos/692913012', '8');

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
