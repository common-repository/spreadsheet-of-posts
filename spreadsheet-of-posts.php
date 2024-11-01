<?php
    /*
    Plugin Name: Spreadsheet of Posts
    Plugin URI: http://www.notadesigner.com
    Description: Plugin for displaying Wordpress posts in a spreadsheet layout
    Author: Pranav Negandhi
    Version: 1.0
    Author URI: http://www.notadesigner.com
    */

    class sop_admin
    {
        function __construct()
        {
            add_action("admin_enqueue_scripts", array($this, "admin_enqueue_scripts"));
            add_action("admin_menu", array($this, "admin_menu"));
            add_action("admin_footer", array($this, "generate_admin_footer"));
            add_action("wp_ajax_fetch_categories", array($this, "handle_fetch_categories"));
            add_action("wp_ajax_fetch_posts", array($this, "handle_fetch_posts"));
            add_action("wp_ajax_create_new_post", array($this, "handle_create_new_post"));
            add_action("wp_ajax_edit_post", array($this, "handle_edit_post"));
            add_action("wp_ajax_delete_post", array($this, "handle_delete_post"));
            add_filter("pre_post_title", array($this, "mask_empty_value"));
            add_filter("pre_post_content", array($this, "mask_empty_value"));
            add_filter("wp_insert_post_data", array($this, "unmask_empty_value"));
        }

        function admin_enqueue_scripts()
        {
            wp_enqueue_style("handsontable", plugin_dir_url(__FILE__) . "js/handsontable/jquery.handsontable.full.css");
            wp_enqueue_script("handsontable", plugin_dir_url(__FILE__) . "js/handsontable/jquery.handsontable.full.js", array("jquery"), false, true);
            wp_enqueue_style("sop_styles", plugin_dir_url(__FILE__) . "css/styles.css");
        }

        function admin_menu()
        {
            add_object_page("Spreadsheet of Posts", "Spreadsheet of Posts", "edit_posts", "sop_admin_menu", array($this, "generate_admin_page"), plugin_dir_url(__FILE__) . "css/icon-small.png");
        }

        function generate_admin_footer()
        {
            wp_enqueue_script("sop_utils", plugin_dir_url(__FILE__) . "js/utils.js", array("handsontable"), false, true);
            wp_enqueue_script("sop_client", plugin_dir_url(__FILE__) . "js/client.js", array("handsontable"), false, true);
        }

        function handle_fetch_categories()
        {
            $data = wp_dropdown_categories(array(
                "hide_empty" => 0,
                "name" => "sop_active_category",
                "orderby" => "name",
                "selected" => $category->parent,
                "hierarchical" => true,
                "echo" => false,
                "show_option_none" => __("None")
            ));
            print_r($data);
            die();
        }

        function handle_fetch_posts()
        {
            $cat_ID = $_POST["cat_ID"];
            $page = intval($_POST["page"]);

            $data = array();
            $query = new WP_Query("cat=" . $cat_ID . "&posts_per_page=25&paged=" . $page);
            if ($query->have_posts())
            {
                while ($query->have_posts())
                {
                    $query->the_post();
                    array_push($data, array(
                            "post_ID" => get_the_ID(),
                            "post_title" => get_the_title(),
                            "post_content" => get_the_content(),
                            "post_excerpt" => $query->post->post_excerpt
                        )
                    );
                }
            }

            $response = array(
                "found_posts" => $query->found_posts,
                "posts" => json_encode($data),
                "max_num_pages" => $query->max_num_pages
            );
            print_r(json_encode($response));
            die();
        }

        function handle_create_new_post()
        {
            $new_post = array(
                "post_title" => $_POST["post_title"],
                "post_content" => $_POST["post_content"],
                "post_category" => array($_POST["post_category"]),
                "post_status" => "publish"
            );
            $response = wp_insert_post($new_post, true);
            print_r(json_encode($response));
            die();
        }

        function handle_edit_post()
        {
            $post_ID = $_POST["post_ID"];
            $post_title = $_POST["post_title"];
            $post_content = $_POST["post_content"];
            $post_excerpt = $_POST["post_excerpt"];

            $edited_post = array(
                "ID" => $post_ID,
                "post_title" => $post_title,
                "post_content" => $post_content,
                "post_excerpt" => $post_excerpt
            );
            $response = wp_update_post($edited_post, true);
            print_r(json_encode($response));
            die();
        }

        function handle_delete_post()
        {
            $post_ID = $_POST["post_ID"];

            $response = wp_delete_post($post_ID);
            print_r(json_encode($response));
            die();
        }

        function mask_empty_value($value)
        {
            if (empty($value))
            {
                return "%empty%";
            }

            return $value;
        }

        function unmask_empty_value($data)
        {
            if ("%empty%" == $data["post_title"])
            {
                $data["post_title"] = "";
            }

            if ("%empty%" == $data["post_content"])
            {
                $data["post_content"] = "";
            }

            return $data;
        }

        function generate_admin_page()
        {
            if (!current_user_can("edit_posts"))
            {
                wp_die(__("You do not have sufficient permissions to access this page."));
            }
            ?>

            <div class="wrap">
                <div id="sop_heading_icon" class="icon32"><br></div>
                <h2>Spreadsheet of Posts</h2>
                <div id="sop_toolbar" class="tablenav top">
                    <label for="sop_active_category">Category 
                        <?php wp_dropdown_categories(array('hide_empty' => 0, 'name' => 'sop_active_category', 'orderby' => 'name', 'selected' => $category->parent, 'hierarchical' => true, 'show_option_none' => __('None'))); ?>
                        <img id="sop_spinner" src="<?php echo plugin_dir_url(__FILE__) . "js/spinner.gif" ?>">
                    </label>
                    <div class="tablenav-pages">
                        <span class="displaying-num"></span>
                        <span class="pagination-links">
                            <a class="first-page" title="" href="#">&laquo;</a>
                            <a class="prev-page" title="Go to the previous page" href="#">&lsaquo;</a>
                            <span class="paging-input"></span>
                            <a class="next-page" title="Go to the next page" href="#">&rsaquo;</a>
                            <a class="last-page" title="Go to the last page" href="#">&raquo;</a>
                        </span>
                    </div>
                </div>
                <div id="sop_posts_grid" />
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"></span>
                        <span class="pagination-links">
                            <a class="first-page" title="" href="#">&laquo;</a>
                            <a class="prev-page" title="Go to the previous page" href="#">&lsaquo;</a>
                            <span class="paging-input"></span>
                            <a class="next-page" title="Go to the next page" href="#">&rsaquo;</a>
                            <a class="last-page" title="Go to the last page" href="#">&raquo;</a>
                        </span>
                    </div>
                    <br class="clear">
                </div>
            </div>

            <?php
            wp_reset_postdata();
        }
    }

    new sop_admin();
?>