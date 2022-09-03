<?php

/**
 *  Copies the Video data to the post
 *  
 * 
 */

namespace WPVS;

class VideoSync extends Sync
{


    public $video_id;
    public $video_posts;



    /**
     *  Construct function
     * 
     *  @param string $uri  The Vimeo video URI 
     *
     */

    public function __construct($uri = '', $course_id = '')
    {

        parent::__construct($uri, $course_id);

        //If we have what we need, let's get this show on the road
        if (!empty($this->uri) && !empty($this->course_id)) {

            $this->video_sync_init();
        }

        return $this;
    }






    /**
     *  Initiate the Video Sync
     * 
     * 
     */

    public function video_sync_init()
    {


        $this->get_video_id()->get_video_posts()->get_api_object()->update_video_posts();

        error_log('Finished doing video_sync_init for ' . $this->uri . ' for course  ' . $this->course_id);
    }








    /**
     *  Gets a post by looking for the 
     *  video URI in postmeta table
     * 
     * 
     */

    public function get_video_posts()
    {

        if (empty($this->video_id)) {

            error_log('WPVS: The video ID is not set.');

            return $this;
        }


        //Let's start with looking for our meta value, _video_uri
        $this->video_posts_query = new \WP_Query([
            'post_type' => 'sfwd-lessons',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_video_uri',
                    'value' => $this->uri,
                    'compare' => 'LIKE'
                ], [
                    'key' => '_sfwd-lessons',
                    'value' => $this->video_id,
                    'compare' => 'LIKE'
                ]
            ]
        ]);


        $this->sanitize_video_posts();



        return $this;
    }




    /**
     *  Sanitize Video Posts
     * 
     *  Check to see if the posts returned
     *  from get_posts are all part of this
     *  course_id.
     * 
     * 
     */


    public function sanitize_video_posts()
    {

        if ($this->video_posts_query->post_count) {

            $this->video_posts = [];

            foreach ($this->video_posts_query->posts as $video_post) {

                $course_id = get_post_meta($video_post->ID, 'course_id', true);

                if ($course_id == $this->course_id) {

                    $this->video_posts[] = $video_post;
                }
            }
        }

        return $this;
    }








    /**
     *  Get API object from Vimeo
     *  
     * 
     */

    public function get_api_object()
    {

        if (!empty($this->video_id)) {

            //Get the vimeo metadata
            $this->api_resp = WPVS_Vimeo()->request('/videos/' . $this->video_id, [], 'GET');
        }

        return $this;
    }



    /**
     *  Update Video post from api object
     * 
     * 
     */

    public function update_video_posts()
    {

        if (empty($this->video_posts) && !empty($this->api_resp)) {

            return $this->create_new_video_post();
        }

        foreach ($this->video_posts as $this->current_video_post) {


            $updated_post = wp_update_post($this->create_post_array($this->current_video_post->ID));

            if (is_wp_error($updated_post)) {

                error_log('Failed when trying to update a video post id ' . $this->current_video_post->ID . ' with this error: ' . $updated_post->get_error_message());
            } else {

                //Save the URL (in case it wasn't saved before)
                learndash_update_setting($this->current_video_post->ID, 'lesson_video_enabled', 'on');
                learndash_update_setting($this->current_video_post->ID, 'lesson_video_url', WPVS_Vimeo()->get_video_obj_value('link'));
                learndash_update_setting($this->current_video_post->ID, 'lesson_video_shown', 'BEFORE');
                learndash_update_setting($this->current_video_post->ID, 'lesson_video_hide_complete_button', 'on');


                $this->save_poster_image($this->current_video_post->ID);
            }
        }

        return $this;
    }

    /**
     *  Helper to generate post array
     * 
     *  @param string/int $post_id
     *  
     */

    public function create_post_array($post_id = null)
    {

        $post_arr = [
            'ID' => $post_id,
            'post_title' => sanitize_text_field(WPVS_Vimeo()->get_video_obj_value('name')),
            'post_excerpt' => sanitize_text_field(WPVS_Vimeo()->get_video_obj_value('description')),
            'meta_input' => [
                '_video_uri' => sanitize_text_field(WPVS_Vimeo()->get_video_obj_value('uri')),
                '_video_duration' => sanitize_text_field(WPVS_Vimeo()->get_video_obj_value('duration'))
            ]
        ];

        return $post_arr;
    }


    /**
     *  Create a new Video post from
     *  the current URI and course ID
     * 
     * 
     * 
     */

    public function create_new_video_post()
    {

        $lesson_post = $this->create_post_array();

        $lesson_post['post_type'] = learndash_get_post_type_slug('lesson');
        $lesson_post['post_status'] = 'publish';
        $lesson_post['meta_input']['course_id'] = $this->course_id;

        $lesson_id = wp_insert_post($lesson_post);

        learndash_update_setting($lesson_id, 'lesson_video_enabled', 'on');
        learndash_update_setting($lesson_id, 'lesson_video_url', sanitize_text_field(WPVS_Vimeo()->get_video_obj_value('link')));
        learndash_update_setting($this->current_video_post->ID, 'lesson_video_shown', 'BEFORE');
        learndash_update_setting($this->current_video_post->ID, 'lesson_video_hide_complete_button', 'on');


        $this->new_video_post = $lesson_id;

        $this->save_poster_image($lesson_id);

        return $this;

    }





    /**
     *  Save the poster image
     * 
     */

    public function save_poster_image($post_id)
    {

        //Do the thumbnail update
        $thumbnail_id = $this->save_new_video_thumbnail();

        if ($thumbnail_id) {

            error_log('Saving post thumbnail ' . $thumbnail_id . ' on post ' . $post_id);

            return set_post_thumbnail( $post_id, $thumbnail_id );

        }

        return false;

    }



    /**
     *  Save the Video Poster Image
     * 
     * 
     * 
     * 
     */


    public function save_new_video_thumbnail()
    {


        if (!function_exists('download_url')) {

            include ABSPATH . '/wp-admin/includes/file.php';
        }

        if (!function_exists('media_handle_sideload')) {

            include ABSPATH . '/wp-admin/includes/media.php';
            include ABSPATH . '/wp-admin/includes/image.php';
        }


        // $video_meta, $post_id 

        // error_log( 'Download URL?: ' . function_exists( 'download_url' ) );

        //$video_pictures = $this->client()->request( '/videos/' . $vimeo_id . '/pictures' );
        // $video_pictures = $video_meta['body']['pictures']['sizes'];
        $video_pictures = WPVS_Vimeo()->get_video_obj_value(['pictures', 'sizes']);


        //Find the biggest one
        if (isset($video_pictures)) { //get the first one

            $biggest_size = end($video_pictures);
        }


        if (isset($biggest_size)) {

            //Download the file
            error_log('Attempting to download ' . $biggest_size['link']);

            $tmp = \download_url($biggest_size['link']);


            $file_array = [
                'name' => basename($biggest_size['link'] . '.jpg'),
                'tmp_name' => $tmp,
                'type' => 'image/jpeg'
            ];

            //The filename
            error_log('Taking a look at the file: ' . $file_array['name']);

            //Let's see if that image already exists
            $maybe_existing_file = $this->maybe_file_exists($file_array['name']);


            //If it does, we'll bypass the whole sideload thing
            if (!empty($maybe_existing_file) && isset($maybe_existing_file->ID)) {

                return $maybe_existing_file->ID;
            }


            /**
             * Check for download errors
             * if there are error unlink the temp file name
             */
            if (is_wp_error($tmp)) {
                @unlink($file_array['tmp_name']);
                error_log('Error saving file for attachment: ' . $file_array['tmp_name']);
                return $tmp;
            }

            /**
             * now we can actually use media_handle_sideload
             * we pass it the file array of the file to handle
             * and the post id of the post to attach it to
             * $post_id can be set to '0' to not attach it to any particular post
             */

            $description = $this->current_video_post->post_title . ' video poster';

            $thumbnail_id = \media_handle_sideload($file_array, $this->current_video_post->ID, $description);

            /**
             * We don't want to pass something to $id
             * if there were upload errors.
             * So this checks for errors
             */
            if (is_wp_error($thumbnail_id)) {

                @unlink($file_array['tmp_name']);
                error_log('Error saving attachment with media_handle_sideload: ' . $thumbnail_id->get_error_message());
                return false;
            } else {

                return $thumbnail_id;
            }
        }
    }




    /**
     *    Helper function to see if a 
     *    picture file already exists
     *    in the wordpress db
     * 
     */

    public function maybe_file_exists($file_name)
    {

        global $wpdb;

        $image_src = sanitize_file_name($file_name);

        $query = "SELECT * FROM {$wpdb->posts} WHERE guid LIKE '%$image_src'";

        $results = $wpdb->get_results($query);

        if (!empty($results)) {

            return $results[0];
        } else {

            return false;
        }
    }
}
