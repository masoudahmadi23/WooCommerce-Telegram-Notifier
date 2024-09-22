<?php

/*
 * Plugin Name: WooCommerce Telegram Notifier
 * Description: Sends product details to a Telegram channel when a new WooCommerce product is published or updated.
 * Version: 1.5
 * Author: Masoud Ahmadi
*/

// اضافه کردن صفحه تنظیمات به منوی پیشخوان
function telegram_settings_menu() {
    add_options_page(
        'تنظیمات تلگرام',
        'تنظیمات تلگرام',
        'manage_options',
        'telegram-settings',
        'telegram_settings_page'
    );
}
add_action('admin_menu', 'telegram_settings_menu');

// محتوای صفحه تنظیمات
function telegram_settings_page() {
    ?>
    <div class="wrap">
        <h1>تنظیمات بات تلگرام</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('telegram_settings_group');
            do_settings_sections('telegram-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// ثبت تنظیمات
function telegram_settings_init() {
    register_setting('telegram_settings_group', 'telegram_bot_token');
    register_setting('telegram_settings_group', 'telegram_chat_id');

    add_settings_section(
        'telegram_settings_section',
        'تنظیمات اصلی',
        null,
        'telegram-settings'
    );

    add_settings_field(
        'telegram_bot_token',
        'توکن بات تلگرام',
        'telegram_bot_token_render',
        'telegram-settings',
        'telegram_settings_section'
    );

    add_settings_field(
        'telegram_chat_id',
        'آیدی کانال تلگرام',
        'telegram_chat_id_render',
        'telegram-settings',
        'telegram_settings_section'
    );
}
add_action('admin_init', 'telegram_settings_init');

// ورودی توکن بات تلگرام
function telegram_bot_token_render() {
    $telegram_bot_token = get_option('telegram_bot_token');
    ?>
    <input type="text" name="telegram_bot_token" value="<?php echo esc_attr($telegram_bot_token); ?>" style="width: 100%;" />
    <?php
}

// ورودی آیدی کانال تلگرام
function telegram_chat_id_render() {
    $telegram_chat_id = get_option('telegram_chat_id');
    ?>
    <input type="text" placeholder="@yourid" name="telegram_chat_id" value="<?php echo esc_attr($telegram_chat_id); ?>" style="width: 100%;" />
    <?php
}

// تابع ارسال اطلاعات به تلگرام
function send_to_telegram($product_id) {
    // اطمینان حاصل کنید که نوع پست محصول است
    if (get_post_type($product_id) !== 'product') {
        return;
    }

    // بررسی اینکه آیا پیام قبلاً ارسال شده است یا خیر، وابسته به زمان
    $has_sent = get_post_meta($product_id, '_telegram_notified_' . current_time('YmdHi'), true);

    // اگر پیام قبلاً ارسال نشده باشد
    if (!$has_sent) {
        // اطلاعات محصول
        $product = wc_get_product($product_id);
        $title = $product->get_name();
        $price = $product->get_price();
        $short_description = wp_strip_all_tags($product->get_short_description());
        $product_url = get_permalink($product_id);

        // تصویر شاخص محصول
        $featured_image_url = wp_get_attachment_url($product->get_image_id());

        // گالری تصاویر محصول (تا 3 تصویر)
        $gallery_image_ids = $product->get_gallery_image_ids();
        $gallery_images = [];

        // اضافه کردن تصویر شاخص به لیست تصاویر
        if ($featured_image_url) {
            $gallery_images[] = [
                'type' => 'photo',
                'media' => $featured_image_url,
                'caption' => "🛍 *محصول جدید*\n\n 🔖 نام محصول: $title\n\n💲 قیمت: $price\n\n 📋 توضیح:\n  $short_description \n\n 🛒 <a href=\"$product_url\">مشاهده محصول</a>",
                'parse_mode' => 'HTML'
            ];
        }

        // اضافه کردن 3 تصویر از گالری محصول
        foreach (array_slice($gallery_image_ids, 0, 3) as $gallery_image_id) {
            $gallery_image_url = wp_get_attachment_url($gallery_image_id);
            if ($gallery_image_url) {
                $gallery_images[] = [
                    'type' => 'photo',
                    'media' => $gallery_image_url
                ];
            }
        }

        // دریافت تنظیمات تلگرام از پیشخوان
        $telegram_token = get_option('telegram_bot_token');
        $chat_id = get_option('telegram_chat_id');

        // ارسال گروهی تصاویر به تلگرام
        if ($telegram_token && $chat_id) {
            $telegram_api_url = "https://api.telegram.org/bot$telegram_token/sendMediaGroup";
            $args = [
                'body' => [
                    'chat_id' => $chat_id,
                    'media' => json_encode($gallery_images),
                ],
            ];

            // ارسال درخواست به API تلگرام
            $response = wp_remote_post($telegram_api_url, $args);

            // بررسی پاسخ
            if (is_wp_error($response)) {
                error_log('Telegram API Error: ' . $response->get_error_message());
            } else {
                $response_body = wp_remote_retrieve_body($response);
                error_log('Telegram API Response: ' . $response_body);
            }

            // علامت گذاری محصول به عنوان اطلاع رسانی شده
            update_post_meta($product_id, '_telegram_notified_' . current_time('YmdHi'), '1');
        } else {
            error_log('Telegram settings are not configured correctly.');
        }
    }
}

// هوک برای انتشار و به روزرسانی محصول
add_action('woocommerce_update_product', 'send_to_telegram');
