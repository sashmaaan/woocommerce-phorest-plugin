<?php
/*
Plugin Name: Phorest
Description: Führt einen erfolgreich getätigten Kauf auf Phorest aus und checkt regelmäßig die Bestände in Phorest und gleicht diese in WooCommerce an
Version: 1.0
Author: Sascha Schmolz
Author URI: https://sascha-schmolz.de
*/

defined( 'ABSPATH' ) or die( 'Are you ok?' );
require_once('phorestAPI.php');

// Füge einen Menüpunkt im Adminbereich hinzu
function phorestAPI_admin_menu() {
    add_menu_page( 'Phorest API', 'Phorest API', 'manage_options', 'phorestAPI_admin_menu', 'ph_phorestAPI_admin_menu_page', 'dashicons-admin-generic', null );
}
add_action( 'admin_menu', 'phorestAPI_admin_menu' );
// Zeige die Seite für den Menüpunkt
function ph_phorestAPI_admin_menu_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $logfile = plugin_dir_path( __FILE__ ) . 'debug.log';
    
    if ( isset($_POST['delete_log']) ) {
        if (file_exists( $logfile )) {
            unlink( $logfile );
        }
    } else {
        // Prüfe, ob das Formular abgeschickt wurde
        if ( isset($_POST['ph_businessId']) ) {
            // Sanitize user input
            $ph_businessId = sanitize_text_field( $_POST['ph_businessId'] );

            // Update option in database
            update_option( 'ph_businessId', $ph_businessId );
        }

        // Prüfe, ob das Formular abgeschickt wurde
        if ( isset($_POST['ph_branchId']) ) {
            // Sanitize user input
            $ph_branchId = sanitize_text_field( $_POST['ph_branchId'] );

            // Update option in database 
            update_option( 'ph_branchId', $ph_branchId );
        }

        // Prüfe, ob das Formular abgeschickt wurde
        if ( isset($_POST['ph_purchase2Phorest']) ) {
            // Sanitize user input
            $ph_purchase2Phorest = sanitize_text_field( $_POST['ph_purchase2Phorest'] );

            // Update option in database
            update_option( 'ph_purchase2Phorest', $ph_purchase2Phorest );
        }

        // Prüfe, ob das Formular abgeschickt wurde
        if ( isset($_POST['ph_log_level']) ) {
            // Sanitize user input
            $ph_log_level = (int) $_POST['ph_log_level'];

            // Update option in database
            update_option( 'ph_log_level', $ph_log_level );
        }
    }

    // Hole den aktuellen Wert der Option aus der Datenbank
    $ph_businessId = get_option( 'ph_businessId' );
    $ph_branchId = get_option( 'ph_branchId' );
    $ph_purchase2Phorest = get_option( 'ph_purchase2Phorest' );
    $ph_log_level = get_option( 'ph_log_level' );
    $get_stock_from_phorest = isset( $_POST['get_stock_from_phorest'] );
    
    $logs = '';
    if (file_exists( $logfile )) {
        $logs = file_get_contents( $logfile );
    }

    ?>

    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php
        if ( isset( $_GET['ph_flash'] ) ) { //it is work pagewise 
            echo '<div class="notice notice-success is-dismissible">
                    <p>' . $_GET['ph_flash'] . '</p>
                </div>';
        }
        if ( isset( $_POST['delete_log'] ) ) { //it is work pagewise 
            echo '<div class="notice notice-success is-dismissible">
                <p>Logs wurden gelöscht</p>
            </div>';
        }
        ?>
        
        <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="ph_businessId">Business ID</label></th>
                <td><input type="text" name="ph_businessId" id="ph_businessId" value="<?php echo esc_attr( $ph_businessId ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="ph_branchId">Branch ID</label></th>
                <td><input type="text" name="ph_branchId" id="ph_branchId" value="<?php echo esc_attr( $ph_branchId ); ?>"></td>
            </tr>
                <th scope="row"><label for="ph_log_level">Loglevel</label></th>
                <td>
                    <select name="ph_log_level">
                        <option value="1" <?php if ($ph_log_level == "1") echo 'selected' ?>>Critical</option>
                        <option value="2" <?php if ($ph_log_level == "2") echo 'selected' ?>>Error</option>
                        <option value="3" <?php if ($ph_log_level == "3") echo 'selected' ?>>Warning</option>
                        <option value="4" <?php if ($ph_log_level == "4") echo 'selected' ?>>Info</option>
                        <option value="5" <?php if ($ph_log_level == "5") echo 'selected' ?>>Debug</option>
                    </select>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Speichern">
        </p>

        <hr/>
        <input type="submit" class="button button-secondary" name="get_stock_from_phorest" value="Phorest Bestand nach WooCommerce einlesen" /><br/>
        <span style="font-size: 10px">(diese Funktion wird einmal täglich ausgeführt, manuelle Ausführung daher nicht nötig)</span>
        <hr/>

        <h2 style="display: inline-block; margin-right: 25px;">Log</h2>
        <input type="submit" name="delete_log" value="Logs löschen" />


        <div class="textfile-content" style="font-family: monospace"><?php echo str_replace("\n", '<br/>', $logs); ?></div>

        </form>
    </div>

    <?php

    if ( $get_stock_from_phorest ) {
        adjustStocksFromPhorest();
        wp_redirect($_SERVER['HTTP_REFERER'] . '&ph_flash=Bestandsdaten aktualisiert');
    }
}

function ph_log($level, $msg) {

    $levelNumber = 0;
    switch ($level) {
        case 'CRIT': 
            $levelNumber = 1;
            $color = '#800000';
            break;
        case 'ERROR': 
            $levelNumber = 2;
            $color = '#ff0000';
            break;
        case 'WARN': 
            $levelNumber = 3;
            $color = '#ff9933';
            break;
        case 'INFO': 
            $levelNumber = 4;
            $color = '#009933';
            break;
        case 'DEBUG': 
            $levelNumber = 5;
            $color = '#0033cc';
            break;
        default:
            $levelNumber = 3;
            $color = '#ff9933';
    }

    if ( $levelNumber > (int) get_option( 'ph_log_level' ) ) {
        return;
    }

    $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
    $datetime = date('Y-m-d H:i:s');
    $logfile = fopen($pluginlog, "a") or die("Unable to open file!");

    fwrite($logfile, "<span style='font-weight: bold; color: " . $color . "'>[" . $datetime . "] " . $level . ":</span>\t " . $msg . "\n");
}

function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


// Füge einen Hook für den WooCommerce-Action 'woocommerce_thankyou' hinzu
add_action( 'woocommerce_thankyou', 'ph_create_purchase', 10, 1 );
// Funktion, die den POST-Request an die Phorest-API sendet
function ph_create_purchase( $order_id ) {

    $phorestAPI = new PhorestAPI();

    $order = wc_get_order( $order_id );
    ph_log('INFO', 'new order (orderId ' . $order_id . ')');

    $paymentMethod = $order->get_payment_method();
    $total = $order->get_total();

    $customer = array(
        'billing_first_name' => $order->get_billing_first_name(),
        'billing_last_name'  => $order->get_billing_last_name(),
        'billing_company'    => $order->get_billing_company(),
        'billing_address_1'  => $order->get_billing_address_1(),
        'billing_address_2'  => $order->get_billing_address_2(),
        'billing_city'       => $order->get_billing_city(),
        'billing_state'      => $order->get_billing_state(),
        'billing_postcode'   => $order->get_billing_postcode(),
        'billing_country'    => $order->get_billing_country(),
        'billing_email'      => $order->get_billing_email(),
        'billing_phone'      => $order->get_billing_phone(),
    );
    $customer = $phorestAPI->find_or_create_client($customer);
    if ( ! isset($customer)) {
        ph_log( 'ERROR', 'order #' . $order_id . ' failed to read or create customer via PhorestAPI');
        return;
    }

    ph_log( 'DEBUG', 'order #' . $order_id . ' customer: <pre>' . print_r($customer, true) . '</pre>');

    $items = array();
    $sum = 0;
    foreach ( $order->get_items() as $item_id => $item ) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $wp_product = wc_get_product( $item->get_product_id() );
        $sku = $wp_product->get_sku();

        if ( ! isset( $sku ) ) {
            ph_log( 'WARN',  sprintf( 'article "%s" has no barcode/sku', $product_name ) );
            continue;
        }

        $ph_product = $phorestAPI->get_product($sku);

        if ( ! isset( $ph_product) ) {
            ph_log( 'WARN',  sprintf( 'article "%s" %s not found in Phorest', $product_name, $sku ) );
            continue;
        }

        if ((double) $item->get_product()->get_price() != (double) $ph_product->price) {
            ph_log( 'WARN',  sprintf( 'article "%s" %s has different prices in woocommerce (%f) and phorest %f', $product_name, $sku, (double)$item->get_product()->get_price(), $ph_product->price ) );
        }

        $ph_product->quantity = $quantity;
        $sum += $ph_product->price * $quantity;

        ph_log( 'DEBUG',  sprintf( 'sum = %f', $sum ) );
        ph_log( 'DEBUG',  sprintf( 'item price = %f, rounded = %f', $ph_product->price, round($ph_product->price, 2) ) );

        array_push($items, array(
            // "staffId" => 'nOyAmzOP_j3o0YAiiDM__Q',
            "branchProductId" => $ph_product->productId,
            "price" => sprintf("%.2f", $ph_product->price),
            "quantity" => $ph_product->quantity
        ));
    }

    $payment = new stdClass();
    $payment->amount = sprintf("%.2f", $sum);
    $payment->type = 'CREDIT';
    $purchase_data = array(
        "number" => guidv4(),
        "clientId" => $customer->clientId,
        "items" => $items,
        "payments" => array($payment)
    );
    
    ph_log( 'DEBUG',  sprintf( 'creating purchase: ' . json_encode( $purchase_data ) ) );
    $purchase = $phorestAPI->create_purchase($purchase_data);

    if ( isset( $purchase->error ) ) {
        ph_log( 'ERROR',  sprintf( '#%s - creating purchase failed: %s', $purchase->errorCode, $purchase->error) );
        return;
    }
    
    ph_log( 'DEBUG', 'purchase #' . $purchase->purchaseId . ': <pre>' . print_r($purchase, true) . '</pre>');
    ph_log( 'INFO',  sprintf( 'purchase created: orderId %s - purchaseId %s', $order_id, $purchase->purchaseId ) );
}



register_activation_hook( __FILE__, 'ph_cron_activate' );
function ph_cron_activate() {
    if (! wp_next_scheduled ( 'phorestAPI_stock_adjustment_event' ) ) {
        wp_schedule_event(time(), 'daily', 'phorestAPI_stock_adjustment_event');
        ph_log( 'INFO', 'CRON "Phorest stock adjustment" activated' );
    }
}

register_deactivation_hook( __FILE__, 'ph_cron_deactivate' ); 
function ph_cron_deactivate() {
    $timestamp = wp_next_scheduled( 'phorestAPI_stock_adjustment_event' );
    wp_clear_scheduled_hook( 'phorestAPI_stock_adjustment_event' );
    ph_log( 'INFO', 'CRON "Phorest stock adjustment" deactivated' );
}


add_action('phorestAPI_stock_adjustment_event', 'adjustStocksFromPhorest' );
function adjustStocksFromPhorest() {
    ph_log('DEBUG', 'adjusting stock in WooCommerce from Phorest data');

    $phorestAPI = new PhorestAPI();
    $products = $phorestAPI->get_products();

    $not_found_products = 0;
    $products_updated = 0;

    foreach ($products as $ph_product) {
        try {
            $productId = wc_get_product_id_by_sku( $ph_product['barcode'] );
            if ( ! $productId) {
                $not_found_products++;
                continue;
            }
            $product = new WC_Product( $productId );
            $current_stock = $product->get_stock_quantity();

            if ($current_stock !== $ph_product['quantity_stock']) {
                wc_update_product_stock($product, $ph_product['quantity_stock']);
                ph_log('DEBUG', 'product updated: ' . $ph_product['name'] . ' (' . $ph_product['barcode'] . ') old=' . $current_stock . ' new=' . $ph_product['quantity_stock']);
                $products_updated++;
            }
        } catch (Exception $e) {
            $not_found_products++;
        }
    }

    ph_log('INFO', 'stock adjusted in WooCommerce from Phorest data (' . $not_found_products . ' products not found by barcode, ' . $products_updated . ' products updated )');
    return true;
}

add_filter("http_request_args", "sw_http_request_args", 100, 1);
function sw_http_request_args($request)
{
    $request["timeout"] = 60;
    return $request;
}

add_action("http_api_curl", "sw_http_api_curl", 100, 1);
function sw_http_api_curl($handle)
{
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
}

?>