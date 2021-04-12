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
    const CHECKOUT_URL = 'https://checkout.coinpayments.net';
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
     * @param $invoice_params
     * @return bool|mixed
     * @throws Exception
     */
    public function createSimpleInvoice($invoice_params)
    {

        $action = self::API_SIMPLE_INVOICE_ACTION;

        $params = array(
            'clientId' => $invoice_params['client_id'],
            'invoiceId' => $invoice_params['invoice_id'],
            'amount' => [
                'currencyId' => $invoice_params['currency_id'],
                "displayValue" => $invoice_params['display_value'],
                'value' => $invoice_params['amount'],
            ],
            'notesToRecipient' => $invoice_params['notes_link'],
        );

        if (!empty($invoice_params['invoice_amounts'])) {
            $params['customData']['amounts'] = json_encode($invoice_params['invoice_amounts']);
        }

        $params = $this->appendInvoiceMetadata($params);
        if (!empty($invoice_params['billing_data'])) {
            $params = $this->appendBillingData($params, $invoice_params['billing_data']);
        }

        return $this->sendRequest('POST', $action, $invoice_params['client_id'], $params);
    }

    /**
     * @param $invoice_params
     * @return bool|mixed
     * @throws Exception
     */
    public function createMerchantInvoice($invoice_params)
    {

        $action = self::API_MERCHANT_INVOICE_ACTION;

        $params = array(
            "invoiceId" => $invoice_params['invoice_id'],
            "amount" => [
                "currencyId" => $invoice_params['currency_id'],
                "displayValue" => $invoice_params['display_value'],
                "value" => $invoice_params['amount']
            ],
            'notesToRecipient' => $invoice_params['notes_link'],
        );

        if (!empty($invoice_params['invoice_amounts'])) {
            $params['customData']['amounts'] = json_encode($invoice_params['invoice_amounts']);
        }

        $params = $this->appendInvoiceMetadata($params);
        if (!empty($invoice_params['billing_data'])) {
            $params = $this->appendBillingData($params, $invoice_params['billing_data']);
        }

        return $this->sendRequest('POST', $action, $invoice_params['client_id'], $params, $invoice_params['client_secret']);
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

    public function getInvoiceData($invoice_id, $client_id, $client_secret = false)
    {

        $action = self::API_SIMPLE_INVOICE_ACTION;
        $action = sprintf('%s/%s', $action, $invoice_id);

        return $this->sendRequest('GET', $action, $client_id, null, $client_secret);
    }

    /**
     * Returns the host name of this gateway
     *
     * @return string Host name of this gateway
     */
    public function getHost()
    {
        return 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    }

    /**
     * @param $request_data
     * @return mixed
     */
    protected function appendInvoiceMetadata($request_data)
    {
        $hostname = $this->getHost();

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
