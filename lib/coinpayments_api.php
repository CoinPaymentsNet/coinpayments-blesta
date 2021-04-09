<?php

/**
 * CoinPayments API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.coinpayments
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinpaymentsApi
{
    const API_URL = 'https://api.coinpayments.net';
    const API_VERSION = '1';

    const API_SIMPLE_INVOICE_ACTION = 'invoices';
    const API_WEBHOOK_ACTION = 'merchant/clients/%s/webhooks';
    const API_MERCHANT_INVOICE_ACTION = 'merchant/invoices';
    const API_CURRENCIES_ACTION = 'currencies';
    const API_CHECKOUT_ACTION = 'checkout';
    const FIAT_TYPE = 'fiat';

    const PAID_EVENT = 'Paid';
    const CANCELLED_EVENT = 'Cancelled';

    const WEBHOOK_NOTIFICATION_URL = '/coin_payments/';

    /**
     * @param $client_id
     * @param $client_secret
     * @param $event
     * @return bool|mixed
     * @throws Exception
     */
    public function createWebHook($client_id, $client_secret, $event)
    {

        $action = sprintf(self::API_WEBHOOK_ACTION, $client_id);

        $params = array(
            "notificationsUrl" => $this->getNotificationUrl($client_id, $event),
            "notifications" => [
                sprintf("invoice%s", $event),
            ],
        );

        return $this->sendRequest('POST', $action, $client_id, $params, $client_secret);
    }

    /**
     * @param $client_id
     * @param int $currency_id
     * @param string $invoice_id
     * @param int $amount
     * @param string $display_value
     * @param bool $invoice_amounts
     * @return bool|mixed
     * @throws Exception
     */
    public function createSimpleInvoice(
        $client_id,
        $currency_id = 5057,
        $invoice_id = 'Validate invoice',
        $amount = 1,
        $display_value = '0.01',
        $invoice_amounts = false,
        $billing_data = []
    )
    {

        $action = self::API_SIMPLE_INVOICE_ACTION;

        $params = array(
            'clientId' => $client_id,
            'invoiceId' => $invoice_id,
            'amount' => [
                'currencyId' => $currency_id,
                "displayValue" => $display_value,
                'value' => $amount
            ],
        );

        if (!empty($invoice_amounts)) {
            $params['custom']['amounts'] = $invoice_amounts;
        }

        $params = $this->appendInvoiceMetadata($params);
        if(!empty($billing_data)){
            $params = $this->appendBillingData($params, $billing_data);
        }

        return $this->sendRequest('POST', $action, $client_id, $params);
    }

    /**
     * @param $client_id
     * @param $client_secret
     * @param $currency_id
     * @param $invoice_id
     * @param $amount
     * @param $display_value
     * @param $invoice_amounts
     * @return bool|mixed
     * @throws Exception
     */
    public function createMerchantInvoice($client_id, $client_secret, $currency_id, $invoice_id, $amount, $display_value, $invoice_amounts, $billing_data)
    {

        $action = self::API_MERCHANT_INVOICE_ACTION;

        $params = array(
            "invoiceId" => $invoice_id,
            "amount" => [
                "currencyId" => $currency_id,
                "displayValue" => $display_value,
                "value" => $amount
            ],
        );

        if (!empty($invoice_amounts)) {
            $params['custom']['amounts'] = $invoice_amounts;
        }

        $params = $this->appendInvoiceMetadata($params);
        if(!empty($billing_data)){
            $params = $this->appendBillingData($params, $billing_data);
        }

        return $this->sendRequest('POST', $action, $client_id, $params, $client_secret);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function getCoinCurrency($name)
    {

        $params = array(
            'types' => self::FIAT_TYPE,
            'q' => $name,
        );
        $items = array();

        $listData = $this->getCoinCurrencies($params);
        if (!empty($listData['items'])) {
            $items = $listData['items'];
        }

        return array_shift($items);
    }

    /**
     * @param array $params
     * @return bool|mixed
     * @throws Exception
     */
    public function getCoinCurrencies($params = array())
    {
        return $this->sendRequest('GET', self::API_CURRENCIES_ACTION, false, $params);
    }

    /**
     * @param $client_id
     * @param $client_secret
     * @return bool|mixed
     * @throws Exception
     */
    public function getWebhooksList($client_id, $client_secret)
    {

        $action = sprintf(self::API_WEBHOOK_ACTION, $client_id);

        return $this->sendRequest('GET', $action, $client_id, null, $client_secret);
    }

    /**
     * @return string
     */
    public function getNotificationUrl($client_id, $event)
    {
        $params = [
            'clientId' => $client_id,
            'event' => $event,
        ];
        return Configure::get("Blesta.gw_callback_url") . Configure::get("Blesta.company_id") . self::WEBHOOK_NOTIFICATION_URL . '?' . http_build_query($params);
    }

    /**
     * @param $signature_string
     * @param $client_secret
     * @return string
     */
    public function encodeSignatureString($signature_string, $client_secret)
    {
        return base64_encode(hash_hmac('sha256', $signature_string, $client_secret, true));
    }

    /**
     * @param $signature
     * @param $content
     * @param $client_id
     * @param $client_secret
     * @param $event
     * @return bool
     */
    public function checkDataSignature($signature, $content, $client_id, $client_secret, $event)
    {

        $request_url = $this->getNotificationUrl($client_id, $event);
        $signature_string = sprintf('%s%s', $request_url, $content);
        $encoded_pure = $this->encodeSignatureString($signature_string, $client_secret);
        return $signature == $encoded_pure;
    }

    /**
     * @param $request_data
     * @return mixed
     */
    protected function appendInvoiceMetadata($request_data)
    {
        $hostname = Configure::get("Blesta.gw_callback_url");

        $request_data['metadata'] = array(
            "integration" => sprintf("Blesta_v%s", BLESTA_VERSION),
            "hostname" => $hostname,
        );

        return $request_data;
    }

    /**
     * @param $request_params
     * @param $billing_data
     * @return array
     */
    protected function appendBillingData($request_params, $billing_data)
    {

        $request_params['buyer'] = array(
            'companyName' => $billing_data['company'],
            'name' => array(
                'firstName' => $billing_data['first_name'],
                'lastName' => $billing_data['last_name']
            ),
            'phoneNumber' => $billing_data['phone'],
        );

        if (preg_match('/^.*@.*$/', $billing_data['email'])) {
            $request_params['buyer']['emailAddress'] = $billing_data['email'];
        }

        if (!empty($billing_data['address_1']) &&
            !empty($billing_data['city']) &&
            preg_match('/^([A-Z]{2})$/', $billing_data['country'])
        ) {
            $request_params['buyer']['address'] = array(
                'address1' => $billing_data['address_1'],
                'address2' => $billing_data['address_2'],
                'provinceOrState' => $billing_data['state'],
                'city' => $billing_data['city'],
                'countryCode' => $billing_data['country'],
                'postalCode' => $billing_data['postcode'],
            );

        }

        return $request_params;
    }

    /**
     * @param $method
     * @param $api_url
     * @param $client_id
     * @param $date
     * @param $client_secret
     * @param $params
     * @return string
     */
    protected function createSignature($method, $api_url, $client_id, $date, $client_secret, $params)
    {

        if (!empty($params)) {
            $params = json_encode($params);
        }

        $signature_data = array(
            chr(239),
            chr(187),
            chr(191),
            $method,
            $api_url,
            $client_id,
            $date->format('c'),
            $params
        );

        $signature_string = implode('', $signature_data);

        return $this->encodeSignatureString($signature_string, $client_secret);
    }

    /**
     * @param $action
     * @return string
     */
    protected function getApiUrl($action)
    {
        return sprintf('%s/api/v%s/%s', self::API_URL, self::API_VERSION, $action);
    }

    /**
     * @param $method
     * @param $api_action
     * @param $client_id
     * @param null $params
     * @param null $client_secret
     * @return bool|mixed
     * @throws Exception
     */
    protected function sendRequest($method, $api_action, $client_id, $params = null, $client_secret = null)
    {

        $response = false;

        $api_url = $this->getApiUrl($api_action);
        $date = new \Datetime();
        try {

            $curl = curl_init();

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            );

            $headers = array(
                'Content-Type: application/json',
            );

            if ($client_secret) {
                $signature = $this->createSignature($method, $api_url, $client_id, $date, $client_secret, $params);
                $headers[] = 'X-CoinPayments-Client: ' . $client_id;
                $headers[] = 'X-CoinPayments-Timestamp: ' . $date->format('c');
                $headers[] = 'X-CoinPayments-Signature: ' . $signature;

            }

            $options[CURLOPT_HTTPHEADER] = $headers;

            if ($method == 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            } elseif ($method == 'GET' && !empty($params)) {
                $api_url .= '?' . http_build_query($params);
            }

            $options[CURLOPT_URL] = $api_url;

            curl_setopt_array($curl, $options);

            $response = json_decode(curl_exec($curl), true);

            curl_close($curl);

        } catch (Exception $e) {

        }
        return $response;
    }

}
