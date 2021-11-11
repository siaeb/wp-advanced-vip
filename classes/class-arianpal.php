<?php
class av_arianpal  {
    private  $_soap_address      = 'https://api.parspal.com/v1/payment/request';
    private  $_test_soap_address = 'https://sandbox.api.parspal.com/v1/payment/request';
    private  $_test_api_key      = '00000000aaaabbbbcccc000000000000';

    //Request Payment Status Values
    const REQ_READY = 'Ready';
    const REQ_SUCCESS = 'Succeed';
    const REQ_FAILED = 'Failed';
    const REQ_GATEWAY_UNVERIFY = 'GatewayUnverify';
    const REQ_GATEWAY_IS_EXPIRED = 'GatewayIsExpired';
    const REQ_GATEWAY_IS_BLOCKED = 'GatewayIsBlocked';
    const REQ_GATEWAY_INVALID_INFO = 'GatewayInvalidInfo';
    const REQ_USER_NOT_ACTIVE = 'UserNotActive';
    const REQ_INVALID_SERVER_IP = 'InvalidServerIP';
    const REQ_STATUS_NOT_VALID = 'Request status value is not valid';

    //verify parameters
    const VERIFY_READY = 'Ready';
    const VERIFY_NOT_MATCHED_MONEY = 'NotMatchMoney';
    const VERIFY_VERIFYED = 'Verifyed';
    const VERIFY_INVALID_REF = 'InvalidRef';
    const VERIFY_SUCCESS = 'Success';
    const VERIFY_STATUS_NOT_VALID  = 'Status value is not valid';


    public function request_payment( $merchantId, $password, $price, $returnpath, $resnumber, $paymenter, $email, $mobile, $description ) {
        error_log('return path : ' . $returnpath);
        //check if properties are valid
        $request = wp_remote_post( $this->_soap_address, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'sslverify'   => false,
            'headers'     => array(
                'APIKEY'        => $merchantId,
                'Content-Type'  => 'application/json',
            ),
            'body'         => json_encode(array(
                'amount'        => $price,
                'return_url'    => $returnpath,
                'description'   => $description,
                'reserve_id'    => $resnumber,
                'order_id'      => $resnumber,
                'payer' => [
                    'name'   => $paymenter,
                    'mobile' => $mobile,
                    'email'  => $email,
                ],
            )),
        ));

        if (is_wp_error($request)) {
            throw new Exception($request->get_error_message());
        }

        $response = json_decode(wp_remote_retrieve_body($request));
        if (isset($response->status) && strtolower($response->status) === 'accepted' ) {
            return $response->link;
        }

        throw new Exception($response->message);
    }

    public function verify_payment($merchantId, $password, $price, $receipt_number) {

        //check if properties are valid
        $request = wp_remote_post( $this->_soap_address, array(
            'method'      => 'POST',
            'timeout'     => 45,
            'sslverify'   => false,
            'headers'     => array(
                'APIKEY'        => $merchantId,
                'Content-Type'  => 'application/json',
            ),
            'body'         => json_encode(array(
                'amount'         => $price,
                'receipt_number' => $receipt_number,
            )),
        ));

    }

    public function get_request_payment_message( $payment_status ) {
        if ( $payment_status == self::REQ_READY ) {
            return 'هیچ عملیاتی انجام نشده است';
        } else if ( $payment_status == self::REQ_SUCCESS ) {
            return 'درخواست با موفقیت ثبت شد';
        } else if ( $payment_status == self::REQ_FAILED ) {
            return 'عملیات با مشکل مواجه شد';
        } else if ( $payment_status == self::REQ_GATEWAY_UNVERIFY ) {
            return 'درگاه غیر فعال است';
        } else if ( $payment_status == self::REQ_GATEWAY_IS_BLOCKED ) {
            return 'درگاه مسدود شده است';
        } else if ( $payment_status == self::REQ_GATEWAY_IS_EXPIRED ) {
            return 'درگاه فاقد اعتبار است';
        } else if ( $payment_status == self::REQ_GATEWAY_INVALID_INFO ) {
            return 'شناسه یا رمز عبور درگاه نادرست است';
        } else if ( $payment_status == self::REQ_USER_NOT_ACTIVE ) {
            return 'کاربر غیر فعال است';
        } else if ( $payment_status == self::REQ_INVALID_SERVER_IP ) {
            return 'آی پی سرور نامعتبر است';
        }

        return '';
    }

    public function get_verify_message( $verify_status ) {
        if ( $verify_status == self::VERIFY_SUCCESS ) {
            return 'عملیات پرداخت با موفقیت انجام شد.';
        } else if ( $verify_status == self::VERIFY_READY ) {
            return 'هیچ عملیاتی انجام نشده است';
        } else if ( $verify_status == self::VERIFY_NOT_MATCHED_MONEY ) {
            return  'مبلغ واریزی با مبلغ درخواستی یکسان نیست';
        } else if ( $verify_status == self::VERIFY_VERIFYED ) {
            return 'قبلا پرداخت شده است';
        } else if ( $verify_status == self::VERIFY_INVALID_REF ) {
            return 'شماره رسید قابل قبول نیست';
        }
        return '';
    }

}