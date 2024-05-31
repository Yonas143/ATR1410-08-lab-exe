<?php


function socialv_filter_array($arr)
{
    $res = array();
    foreach ($arr as $key => $val) {
        if ($val != null) {
            array_push($res, $val);
        }
    }
    return $res;
}

function sv_get_product_downloads($product)
{
    $downloads = array();

    if ($product->is_downloadable()) {
        foreach ($product->get_downloads() as $file_id => $file) {
            $downloads[] = array(
                'id'   => $file_id, // MD5 hash.
                'name' => $file['name'],
                'file' => $file['file'],
            );
        }
    }

    return $downloads;
}

function sv_get_taxonomy_terms_helper($product, $taxonomy = 'cat')
{
    $terms = array();

    foreach (wc_get_object_terms($product->get_id(), 'product_' . $taxonomy) as $term) {
        $terms[] = array(
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }

    return $terms;
}

function sv_get_product_images_helper($product)
{
    $images = array();
    $attachment_ids = array();

    if (!$product) {
        return $images;
    }
    // Add featured image.
    if ($product->get_image_id()) {
        $attachment_ids[] = $product->get_image_id();
    }

    $attachment_ids = array_merge($attachment_ids, $product->get_gallery_image_ids());

    foreach ($attachment_ids as $position => $attachment_id) {
        $attachment_post = get_post($attachment_id);
        if (is_null($attachment_post)) {
            continue;
        }

        $attachment = wp_get_attachment_image_src($attachment_id, 'full');
        if (!is_array($attachment)) {
            continue;
        }

        $images[] = array(
            'id'            => (int) $attachment_id,
            'date_created'  => wc_rest_prepare_date_response($attachment_post->post_date_gmt),
            'date_modified' => wc_rest_prepare_date_response($attachment_post->post_modified_gmt),
            'src'           => current($attachment),
            'name'          => get_the_title($attachment_id),
            'alt'           => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'position'      => (int) $position,
        );
    }

    if (empty($images)) {
        $images[] = array(
            'id'            => 0,
            'date_created'  => wc_rest_prepare_date_response(current_time('mysql')), // Default to now.
            'date_modified' => wc_rest_prepare_date_response(current_time('mysql')),
            'src'           => wc_placeholder_img_src(),
            'name'          => __('Placeholder', 'socialv-api'),
            'alt'           => __('Placeholder', 'socialv-api'),
            'position'      => 0,
        );
    }

    return $images;
}

function sv_get_product_attribute_taxonomy_label($name)
{
    $tax    = get_taxonomy($name);
    $labels = get_taxonomy_labels($tax);

    return $labels->singular_name;
}

function sv_get_product_attribute_options($product_id, $attribute)
{
    if (isset($attribute['is_taxonomy']) && $attribute['is_taxonomy']) {
        return wc_get_product_terms($product_id, $attribute['name'], array('fields' => 'names'));
    } elseif (isset($attribute['value'])) {
        return array_map('trim', explode('|', $attribute['value']));
    }

    return array();
}

function sv_get_product_attributes($product)
{
    $attributes = array();

    if ($product->is_type('variation')) {
        // Variation attributes.
        foreach ($product->get_variation_attributes() as $attribute_name => $attribute) {
            $name = str_replace('attribute_', '', $attribute_name);

            if (!$attribute) {
                continue;
            }

            // Taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`.
            if (0 === strpos($attribute_name, 'attribute_pa_')) {
                $option_term = get_term_by('slug', $attribute, $name);
                $attributes[] = array(
                    'id'     => wc_attribute_taxonomy_id_by_name($name),
                    'name'   => sv_get_product_attribute_taxonomy_label($name),
                    'option' => $option_term && !is_wp_error($option_term) ? $option_term->name : $attribute,
                );
            } else {
                $attributes[] = array(
                    'id'     => 0,
                    'name'   => $name,
                    'option' => $attribute,
                );
            }
        }
    } else {
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute['is_taxonomy']) {
                $attributes[] = array(
                    'id'        => wc_attribute_taxonomy_id_by_name($attribute['name']),
                    'name'      => sv_get_product_attribute_taxonomy_label($attribute['name']),
                    'position'  => (int) $attribute['position'],
                    'visible'   => (bool) $attribute['is_visible'],
                    'variation' => (bool) $attribute['is_variation'],
                    'options'   => sv_get_product_attribute_options($product->get_id(), $attribute),
                );
            } else {
                $attributes[] = array(
                    'id'        => 0,
                    'name'      => $attribute['name'],
                    'position'  => (int) $attribute['position'],
                    'visible'   => (bool) $attribute['is_visible'],
                    'variation' => (bool) $attribute['is_variation'],
                    'options'   => sv_get_product_attribute_options($product->get_id(), $attribute),
                );
            }
        }
    }

    return $attributes;
}


function sv_get_product_default_attributes($product)
{
    $default = array();

    if ($product->is_type('variable')) {
        foreach (array_filter((array) $product->get_default_attributes(), 'strlen') as $key => $value) {
            if (0 === strpos($key, 'pa_')) {
                $default[] = array(
                    'id'     => wc_attribute_taxonomy_id_by_name($key),
                    'name'   => sv_get_product_attribute_taxonomy_label($key),
                    'option' => $value,
                );
            } else {
                $default[] = array(
                    'id'     => 0,
                    'name'   => wc_attribute_taxonomy_slug($key),
                    'option' => $value,
                );
            }
        }
    }

    return $default;
}

function sv_woo_featured_video($product_id)
{
    $woofv_video_embed = get_post_meta($product_id, '_woofv_video_embed', true);
    if ($woofv_video_embed == null) {
        $woofv_video_embed = (object) [];
    }
    return $woofv_video_embed;
}

function sv_get_cart_product_ids()
{
    $cart_data = wc()->api->get_endpoint_data('/wc/store/v1/cart');
    if (!$cart_data) return false;

    if (!isset($cart_data['items']) || empty($cart_data['items'])) return false;

    $ids = [];
    foreach ($cart_data['items'] as $item) {
        $ids[] = $item['id'];
    }

    return $ids;
}

function sv_get_product_details_helper($product_id, $userid = null)
{
    global $product;
    global $wpdb;
    $product = wc_get_product($product_id);

    if ($product === false) {
        return [];
    }

    $temp = array(
        'id'                    => $product->get_id(),
        'name'                  => $product->get_name(),
        'slug'                  => $product->get_slug(),
        'permalink'             => $product->get_permalink(),
        'date_created'          => wc_rest_prepare_date_response($product->get_date_created()),
        'date_modified'         => wc_rest_prepare_date_response($product->get_date_modified()),
        'type'                  => $product->get_type(),
        'status'                => $product->get_status(),
        'featured'              => $product->is_featured(),
        'catalog_visibility'    => $product->get_catalog_visibility(),
        'description'           => wpautop(do_shortcode($product->get_description())),
        'short_description'     => apply_filters('woocommerce_short_description', $product->get_short_description()),
        'sku'                   => $product->get_sku(),
        'price'                 => $product->get_price(),
        'regular_price'         => $product->get_regular_price(),
        'sale_price'            => $product->get_sale_price() ? $product->get_sale_price() : '',
        'date_on_sale_from'     => $product->get_date_on_sale_from() ? date_i18n('Y-m-d', $product->get_date_on_sale_from()->getOffsetTimestamp()) : '',
        'date_on_sale_to'       => $product->get_date_on_sale_to() ? date_i18n('Y-m-d', $product->get_date_on_sale_to()->getOffsetTimestamp()) : '',
        'price_html'            => $product->get_price_html(),
        'on_sale'               => $product->is_on_sale(),
        'purchasable'           => $product->is_purchasable(),
        'total_sales'           => $product->get_total_sales(),
        'virtual'               => $product->is_virtual(),
        'downloadable'          => $product->is_downloadable(),
        'downloads'             => sv_get_product_downloads($product),
        'download_limit'        => $product->get_download_limit(),
        'download_expiry'       => $product->get_download_expiry(),
        'download_type'         => 'standard',
        'external_url'          => $product->is_type('external') ? $product->get_product_url() : '',
        'button_text'           => $product->is_type('external') ? $product->get_button_text() : '',
        'tax_status'            => $product->get_tax_status(),
        'tax_class'             => $product->get_tax_class(),
        'manage_stock'          => $product->managing_stock(),
        'stock_quantity'        => $product->get_stock_quantity(),
        'in_stock'              => $product->is_in_stock(),
        'backorders'            => $product->get_backorders(),
        'backorders_allowed'    => $product->backorders_allowed(),
        'backordered'           => $product->is_on_backorder(),
        'sold_individually'     => $product->is_sold_individually(),
        'weight'                => $product->get_weight(),
        'dimensions'            => array(
            'length' => $product->get_length(),
            'width'  => $product->get_width(),
            'height' => $product->get_height(),
        ),
        'shipping_required'     => $product->needs_shipping(),
        'shipping_taxable'      => $product->is_shipping_taxable(),
        'shipping_class'        => $product->get_shipping_class(),
        'shipping_class_id'     => $product->get_shipping_class_id(),
        'reviews_allowed'       => $product->get_reviews_allowed(),
        'average_rating'        => wc_format_decimal($product->get_average_rating(), 2),
        'rating_count'          => $product->get_rating_count(),
        'related_ids'           => array_map('absint', array_values(wc_get_related_products($product->get_id(), 40))),
        'upsell_ids'            => array_map('absint', $product->get_upsell_ids()),
        'cross_sell_ids'        => array_map('absint', $product->get_cross_sell_ids()),
        'parent_id'             => $product->get_parent_id(),
        'purchase_note'         => wpautop(do_shortcode(wp_kses_post($product->get_purchase_note()))),
        'categories'            => sv_get_taxonomy_terms_helper($product),
        'tags'                  => sv_get_taxonomy_terms_helper($product, 'tag'),
        'images'                => sv_get_product_images_helper($product),
        'attributes'            => sv_get_product_attributes($product),
        'default_attributes'    => sv_get_product_default_attributes($product),
        'variations'            => $product->get_children(),
        'grouped_products'      => array(),
        'upsell_id'             => array(),
        'related_id'            => array(),
        'menu_order'            => $product->get_menu_order(),

    );

    $temp['is_added_cart'] = false;
    $temp['is_added_wishlist'] = false;

    if ($userid != null) {
        $cart_items = sv_get_cart_product_ids();

        if ($cart_items)
            $is_added_cart = in_array($product_id, $cart_items);
        else
            $is_added_cart = false;

        $temp['is_added_cart'] = $is_added_cart;
        $wishlist_item = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}yith_wcwl WHERE user_id='{$userid}' AND prod_id='{$product_id}'", OBJECT);
        if ($wishlist_item != null) {
            $temp['is_added_wishlist'] = true;
        }
    }

    if (isset($temp['upsell_ids']) && count($temp['upsell_ids'])) {
        $upsell_products = [];

        foreach ($temp['upsell_ids'] as $key => $p_id) {

            $upsell_product = wc_get_product($p_id);

            if ($upsell_product != null) {
                $upsell_products[] = [
                    'id'                    => $upsell_product->get_id(),
                    'name'                  => $upsell_product->get_name(),
                    'slug'                  => $upsell_product->get_slug(),
                    'price'                 => $upsell_product->get_price(),
                    'regular_price'         => $upsell_product->get_regular_price(),
                    'sale_price'            => $upsell_product->get_sale_price() ? $upsell_product->get_sale_price() : '',
                    'images'                => sv_get_product_images_helper($upsell_product),
                ];
            }
        }

        if (count($upsell_products)) {
            $temp['upsell_id'] = $upsell_products;
        }
    }
    if (isset($temp['related_ids']) && count($temp['related_ids'])) {
        $related_products = [];

        foreach ($temp['related_ids'] as $key => $p_id) {

            $related_product = wc_get_product($p_id);

            if ($related_product != null) {
                $related_products[] = [
                    'id'                    => $related_product->get_id(),
                    'name'                  => $related_product->get_name(),
                    'slug'                  => $related_product->get_slug(),
                    'price'                 => $related_product->get_price(),
                    'regular_price'         => $related_product->get_regular_price(),
                    'sale_price'            => $related_product->get_sale_price() ? $related_product->get_sale_price() : '',
                    'images'                => sv_get_product_images_helper($related_product),
                ];
            }
        }

        if (count($related_products)) {
            $temp['related_id'] = $related_products;
        }
    }

    $temp['woofv_video_embed'] = sv_woo_featured_video($product->get_id());
    return $temp;
}

function sv_get_wishlist_items($items)
{
    $wishlist_items = [];

    foreach ($items as $wishlis_item) {

        if ($wishlis_item) {
            $item = $wishlis_item->get_data();
            $products = wc_get_product($item['product_id']);


            if (!$products || $products->status != 'publish') continue;

            $datarray = [
                'pro_id'            => $products->get_id(),
                'name'              => $products->get_name(),
                'sku'               => $products->get_sku(),
                'pro_type'          => $products->get_type(),
                'price'             => $products->get_price(),
                'regular_price'     => $products->get_regular_price(),
                'sale_price'        => $products->get_sale_price(),
                'stock_quantity'    => $products->get_stock_quantity(),
                'in_stock'          => $products->is_in_stock(),
            ];
            $thumb = wp_get_attachment_image_src($products->get_image_id(), "thumbnail");
            $full = wp_get_attachment_image_src($products->get_image_id(), "full");

            $datarray['thumbnail'] = !empty($thumb) ? $thumb[0] : null;
            $datarray['full'] = !empty($full) ? $full[0] : null;

            $gallery = array();
            foreach ($products->get_gallery_image_ids() as $img_id) {
                $g = wp_get_attachment_image_src($img_id, "full");
                $gallery[] = $g[0];
            }
            $datarray['gallery'] = $gallery;
            $gallery = array();

            $datarray['created_at'] = date_i18n($item['date_added']);


            $wishlist_items[] = $datarray;
        }
    }
    return $wishlist_items;
}
