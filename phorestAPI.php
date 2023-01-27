<?php

class PhorestAPI {

    private $businessId;
    private $branchId;
    private $url;
    private $headers;

    function __construct() {
        $this->businessId = get_option( 'ph_businessId' );
        $this->branchId = get_option( 'ph_branchId' );
        $this->url = 'https://api-gateway-eu.phorest.com/third-party-api-server/api';

        $this->headers = array(
            'Authorization' => 'Basic <<TOKEN>>'
        );
    }

    function find_client($customer) {

        // --------------- BY EMAIL
        if (isset($customer['billing_email'])) {
            $params = '?email=' . $customer['billing_email'];

            $url = sprintf( '%s/business/%s/client%s', $this->url, $this->businessId, $params );
            $response = wp_remote_get( $url, array( 'headers' => $this->headers ) );
            $body = json_decode( wp_remote_retrieve_body( $response ) );
            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return null;
            }

            if ($body->page->size > 0) {
                return $body->_embedded->clients[0];
            }
        }

        // ----------------- BY NAME
        if (isset($customer['billing_first_name']) && isset($customer['billing_last_name'])) {
            $params = '?firstName=' . $customer['billing_first_name'] . '&lastName=' . $customer['billing_last_name'];

            $url = sprintf( '%s/business/%s/client%s', $this->url, $this->businessId, $params );
            $response = wp_remote_get( $url, array( 'headers' => $this->headers ) );
            $body = json_decode( wp_remote_retrieve_body( $response ) );
            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return null;
            }

            if ($body->page->size > 0) {
                return $body->_embedded->clients[0];
            }
        }


        // ----------------- BY PHONENUMBER
        if (isset($customer['billing_phone'])) {
            $params = '?phone=' . $customer['billing_phone'];
            
            $url = sprintf( '%s/business/%s/client%s', $this->url, $this->businessId, $params );
            $response = wp_remote_get( $url, array( 'headers' => $this->headers ) );
            $body = json_decode( wp_remote_retrieve_body( $response ) );
            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return null;
            }

            if ($body->page->size > 0) {
                return $body->_embedded->clients[0];
            }
        }

        return null;
    }

    function create_client($customer) {
        // neuer Kunde erstellen
        $url = sprintf( '%s/business/%s/client', 
            $this->url, 
            $this->businessId
        );
        $array_with_parameters = '
        {
            "firstName": "' . $customer['billing_first_name'] . '",
            "lastName": "' . $customer['billing_last_name'] . '",
            "mobile": "' . $customer['billing_phone'] . '",
            "landLine": "",
            "email": "' . $customer['billing_email'] . '",
            "address": {
                "streetAddress1": "' . $customer['billing_address_1'] . '",
                "streetAddress2": "' . $customer['billing_address_2'] . '",
                "city": "' . $customer['billing_city'] . '",
                "state": "' . $customer['billing_state'] . '",
                "postalCode": "' . $customer['billing_postcode'] . '",
                "country": "' . $customer['billing_country'] . '"
            },
            "clientSince": "' . date('Y-m-d\Th:i:s.v\Z', time()) . '",
            "gender": "MALE",
            "notes": "Kunde über WooCommerce anlegt",
            "smsMarketingConsent": false,
            "emailMarketingConsent": false,
            "smsReminderConsent": false,
            "emailReminderConsent": false,
            "creatingBranchId": "' . $this->branchId . '",
            "archived": false,
            "deleted": false,
            "banned": false
        }
        ';
        $response = wp_remote_post( $url, array(
            'headers'     => array_merge(
                array(),
                $this->headers,
                array(
                    'Content-Type' => 'application/json; charset=utf-8'
                )
            ),
            'body'        => $array_with_parameters,
            'method'      => 'POST',
            'data_format' => 'body',
        ));
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code !== 201 ) {
            return null;
        }

        return json_decode($response['body']);
    }

    function find_or_create_client($customer) {
        $customerRsp = $this->find_client($customer);
        if ( ! isset($customerRsp)) {
            $customerRsp = $this->create_client($customer);
        }
        if ( ! isset($customerRsp)) {
            return null;
        }

        return $customerRsp;  
    }

    function get_product($sku) {
        if (isset($sku)) {
            $params = '?searchQuery=' . $sku;
            $url = sprintf( '%s/business/%s/branch/%s/product%s', $this->url, $this->businessId, $this->branchId, $params );
            $response = wp_remote_get( $url, array( 'headers' => $this->headers ) );
            $body = json_decode( wp_remote_retrieve_body( $response ) );
            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return null;
            }

            if ( $body->page->size > 0 ) {
                return $body->_embedded->products[0];
            }
        }
        return null;
    }

    function get_products() {
        $products = array();
        $pageNo = 0;

        do {
            $params = '?page=' . $pageNo . '&size=100';
            $url = sprintf( '%s/business/%s/branch/%s/product%s', $this->url, $this->businessId, $this->branchId, $params );
            $response = wp_remote_get( $url, array( 'headers' => $this->headers ) );
            $body = json_decode( wp_remote_retrieve_body( $response ) );
            $response_code = wp_remote_retrieve_response_code( $response );
    
            if ( $response_code !== 200 ) {
                return null;
            }

            if ( isset( $body->_embedded ) ) {
                foreach ($body->_embedded->products as $key => $item) {
                    if ( ! $item->barcode) continue;
    
                    array_push($products, array(
                        "productId" => $item->productId,
                        "name" => $item->name,
                        "barcode" => $item->barcode,
                        "quantity_stock" => $item->quantityInStock,
                    ));
                }
            }
                
            $pageNo++;
        } while( $body->page->totalPages >= $pageNo );

        return $products;
    }

    function create_purchase($purchase_parameters) {
        // neuer Kunde erstellen
        $url = sprintf( '%s/business/%s/branch/%s/purchase', 
            $this->url, 
            $this->businessId,
            $this->branchId
        );
    
        $response = wp_remote_post( $url, array(
            'headers'     => array_merge(
                array(),
                $this->headers,
                array(
                    'Content-Type' => 'application/json; charset=utf-8'
                )
            ),
            'body'        => json_encode($purchase_parameters),
            'method'      => 'POST',
            'data_format' => 'body',
        ));
        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code !== 201 ) {
            
            $result = new stdClass();
            if ( gettype($response) === 'array' && isset( $response['errors'] ) ) {
                $result->error = json_encode( $response['errors'] );
            } 
            if ( gettype($response) === 'array' && isset( $response['body'] ) ) {
                $result->error = json_encode( $response['body'] );
            }
            $result->errorCode = $response_code;
            return $result;
        }

        return json_decode($response['body']);
    }
}


?>