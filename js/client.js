var SOP_CLIENT = function($) {
    var sop_active_category;
    var sop_posts_grid;
    var sop_spinner;
    var grid_instance;
    var activeRow;
    var page;
    var max_num_pages;

    var paging_input;
    var displaying_num;

    var initialize = function() {
        sop_active_category = $("#sop_active_category");
        sop_active_category.change(function(event) {
            grid_instance.loadData([]);
            page = 1;
            max_num_pages = 1;
            selection = $("#sop_active_category").val();
            requestPosts(selection);
        });

        sop_posts_grid = $("#sop_posts_grid");
        sop_posts_grid.handsontable({
            startRows: 0,
            startCols: 0,
            width: 1200,
            minSpareRows: 1,
            colHeaders: ["ID", "Title", "Content", "Excerpt"],
            colWidths: [50, 200, 600, 300],
            columns: [
                { // post ID column is readonly
                    readOnly: true,
                    data: "post_ID"
                },
                {
                    type: "text",
                    data: "post_title",
                    renderer: postTitleRenderer
                },
                {
                    type: "text",
                    data: "post_content",
                    renderer: postContentRenderer
                },
                {
                    type: "text",
                    data: "post_excerpt",
                    renderer: postContentRenderer
                }
            ],
            afterChange: function(change, source) {
                if ("edit" != source && "paste" != source && undefined != source) {
                    return;
                }

                if (change.length > 0) { // At least one cell edited
                    for (var i = 0; i < change.length; i++) {
                        single = change[i];

                        if (0 == single[1] && "edit" == source) { // Ignore update to post ID column
                            return;
                        }

                        activeRow = single[0];
                        post_ID = grid_instance.getDataAtRowProp(activeRow, "post_ID");
                        post_title = grid_instance.getDataAtRowProp(activeRow, "post_title");
                        post_content = grid_instance.getDataAtRowProp(activeRow, "post_content");
                        post_excerpt = grid_instance.getDataAtRowProp(activeRow, "post_excerpt");

                        if (null == post_ID) { // Empty cell filled
                            if (null != post_title) { // Create post only if post_title is filled
                                cat_ID = sop_active_category.val();
                                requestCreateNewPost(post_title, post_content, post_excerpt, cat_ID);
                            }
                        } else {
                            requestEditPost(post_ID, post_title, post_content, post_excerpt);
                        }
                    }
                }
            },
            beforeKeyDown: function(event) {
                var selected = grid_instance.getSelected();
                if (undefined == selected) {
                    return;
                }
                activeRow = selected[0];

                if (0 == selected[1]) {
                    if (8 == event.keyCode || 46 == event.keyCode) {
                        post_ID = grid_instance.getDataAtCell(activeRow, 0);
                        if (null == post_ID) {
                            return;
                        }

                        requestDeletePost(post_ID);
                    }
                }
            }
        });

        sop_spinner = $("#sop_spinner");
        displaying_num = $(".displaying-num");
        paging_input = $(".paging-input");
        
        page = 1;
        $("div.tablenav").click(changePage);

        grid_instance = sop_posts_grid.handsontable("getInstance");
        var cat_ID = sop_active_category.val();
        requestPosts(cat_ID);
        requestCategories();
    };

    var postTitleRenderer = function(instance, td, row, col, prop, value, cellProperties) {
        var escaped = Handsontable.helper.stringify(value);
        escaped = SOP_UTILS.strip_tags(escaped, '');
        td.innerHTML = escaped;
        return td;
    };

    var postContentRenderer = function(instance, td, row, col, prop, value, cellProperties) {
        var escaped = Handsontable.helper.stringify(value);
        escaped = SOP_UTILS.strip_tags(escaped, '<em><strong><a><img><h1><h2><h3><h4><h5><h6><blockquote><table><thead><tr><th><tbody><td><dl><dt><ul><ol><li><pre><address><code><abbr><acronym><big><del><ins><q><sub><sup><var>');
        td.innerHTML = escaped;
        return td;
    };

    var changePage = function(event) {
        var cls = event.target.getAttribute("class");
        var valid = false;
        
        switch (cls) {
            case "first-page":
            page = 1;
            valid = true;
            break;

            case "prev-page":
            page--;
            page = Math.max(1, page);
            valid = true;
            break;

            case "next-page":
            page++;
            page = Math.min(max_num_pages, page);
            valid = true;
            break;

            case "last-page":
            page = max_num_pages;
            valid = true;
            break;
        }

        if (valid) { // Initiate request only if a valid control has been clicked
            var cat_ID = sop_active_category.val();
            requestPosts(cat_ID);
        }
    };

    var requestPosts = function(cat_ID) {
        showSpinner();
        var data = {action: "fetch_posts", cat_ID: cat_ID, page: page};
        $.post(ajaxurl, data, function(response, status, xhr) {
            hideSpinner();
            response = $.parseJSON(response);
            var posts = $.parseJSON(response["posts"]);
            max_num_pages = Math.max(response["max_num_pages"], 1);

            displaying_num.text(response["found_posts"] + " items");
            paging_input.text(page + " of " + max_num_pages);

            grid_instance.loadData(posts);
        });
    };

    var requestCategories = function() {
        var data = {action: "fetch_categories"};
        $.post(ajaxurl, data, function(response, status, xhr) {
            sop_active_category.html(response);
            selection = sop_active_category.val();
            requestPosts(selection);
        });
    };

    var requestCreateNewPost = function(post_title, post_content, post_excerpt, cat_ID) {
        showSpinner();
        var data = {action: "create_new_post", post_title: post_title, post_content: post_content, post_excerpt: post_excerpt, post_category: cat_ID};
        $.post(ajaxurl, data, function(response, status, xhr) {
            hideSpinner();
            grid_instance.setDataAtCell(activeRow, 0, response);
        });
    };

    var requestEditPost = function(post_ID, post_title, post_content, post_excerpt) {
        showSpinner();
        var data = {action: "edit_post", post_ID: post_ID, post_title: post_title, post_content: post_content, post_excerpt: post_excerpt};
        $.post(ajaxurl, data, function(response, status, xhr) {
            hideSpinner();
        });
    };

    var requestDeletePost = function(post_ID) {
        showSpinner();
        var data = {action: "delete_post", post_ID: post_ID};
        $.post(ajaxurl, data, function(response, status, xhr) {
            hideSpinner();
            grid_instance.alter("remove_row", activeRow);
            console.log(response);
        });
    };

    var showSpinner = function() {
        sop_spinner.css("display", "inline");
        displaying_num.text("");
        paging_input.text("");
    };

    var hideSpinner = function() {
        sop_spinner.css("display", "none");
    };

    $(document).ready(initialize);
}(jQuery);
