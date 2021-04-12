<?php

/**
 * CoinPayments.net, based on the PayPal Payments Standard plugin
 *
 * @package blesta
 * @subpackage blesta.components.gateways.coinpayments
 * @copyright Copyright (c) 2010, Phillips Data, Inc. Copyright (c) 2014 CoinPayments.net
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class CoinPayments extends NonmerchantGateway
{
    /**
     * @var string The version of this gateway
     */
    private static $version = "2.0.0";
    /**
     * @var string The authors of this gateway
     */
    private static $authors = array(array('name' => "CoinPayments.net", 'url' => "https://www.coinpayments.net"));
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {

        // Load components required by this gateway
        Loader::loadComponents($this, array("Input"));

        // Load the language required by this gateway
        Language::loadLang("coin_payments", null, dirname(__FILE__) . DS . "language" . DS);
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_("CoinPayments.name", true);
    }

    /**
     * Returns the version of this gateway
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this gateway
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {

        $company_id = Configure::get('Blesta.company_id');
        if (!isset($this->Currencies)) {
            Loader::loadHelpers($this, ['CurrencyFormat']);
            $this->Currencies = $this->CurrencyFormat->Currencies;
        }

        return array_map(function ($currency) {
            return $currency->code;
        }, $this->Currencies->getAll($company_id));
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView("settings", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));

        $this->view->set("meta", $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {

        $client_id = $meta['client_id'];
        $webhooks = $meta['webhooks'];

        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'coinpayments_api.php');
        $api = new CoinpaymentsApi();

        // Verify meta data is valid
        $rules = [
            'client_id' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => function ($client_id) use ($api, $webhooks) {
                        $valid = false;
                        if (!empty($client_id)) {
                            if (!$webhooks) {
                                $invoice_params = array(
                                    'client_id' => $client_id,
                                    'currency_id' => 5057,
                                    'invoice_id' => 'Validate invoice',
                                    'amount' => 1,
                                    'display_value' => '0.01',
                                    'invoice_amounts' => false,
                                    'billing_data' => array()
                                );

                                $invoice = $api->createSimpleInvoice($invoice_params);
                                if (!empty($invoice['id'])) {
                                    $valid = true;
                                }
                            } else {
                                $valid = true;
                            }
                        }
                        return $valid;
                    },
                    'message' => Language::_('GatewayPayments.!error.credentials', true)
                ]
            ],
            'client_secret' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => function ($client_secret) use ($api, $client_id, $webhooks) {
                        $valid = false;
                        if (empty($webhooks)) {
                            $valid = true;
                        } else {
                            if (!empty($client_id) && !empty($client_secret)) {
                                $webhooks_list = $api->getWebhooksList($client_id, $client_secret);
                                if (!empty($webhooks_list)) {

                                    $webhooks_urls_list = array();
                                    if (!empty($webhooks_list['items'])) {
                                        $webhooks_urls_list = array_map(function ($webHook) {
                                            return $webHook['notificationsUrl'];
                                        }, $webhooks_list['items']);
                                    }

                                    if (
                                        !in_array($api->getNotificationUrl($client_id, CoinpaymentsApi::PAID_EVENT), $webhooks_urls_list) ||
                                        !in_array($api->getNotificationUrl($client_id, CoinpaymentsApi::CANCELLED_EVENT), $webhooks_urls_list)
                                    ) {
                                        if (
                                            !empty($api->createWebHook($client_id, $client_secret, CoinpaymentsApi::PAID_EVENT)) &&
                                            !empty($api->createWebHook($client_id, $client_secret, CoinpaymentsApi::CANCELLED_EVENT))
                                        ) {
                                            $valid = true;
                                        }
                                    } else {
                                        $valid = true;
                                    }
                                }
                            }
                        }
                        return $valid;
                    },
                    'message' => Language::_('GatewayPayments.!error.credentials', true)
                ]
            ],
        ];

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return array("account_id", "client_id", "client_secret");
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *    - id The contact ID
     *    - client_id The ID of the client this contact belongs to
     *    - user_id The user ID this contact belongs to (if any)
     *    - contact_type The type of contact
     *    - contact_type_id The ID of the contact type
     *    - first_name The first name on the contact
     *    - last_name The last name on the contact
     *    - title The title of the contact
     *    - company The company name of the contact
     *    - address1 The address 1 line of the contact
     *    - address2 The address 2 line of the contact
     *    - city The city of the contact
     *    - state An array of state info including:
     *        - code The 2 or 3-character state code
     *        - name The local name of the country
     *    - country An array of country info including:
     *        - alpha2 The 2-character country code
     *        - alpha3 The 3-cahracter country code
     *        - name The english name of the country
     *        - alt_name The local name of the country
     *    - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *    - id The ID of the invoice being processed
     *    - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *    - description The Description of the charge
     *    - return_url The URL to redirect users to after a successful payment
     *    - recur An array of recurring info including:
     *        - start_date The date/time in UTC that the recurring payment begins
     *        - amount The amount to recur
     *        - term The term to recur
     *        - period The recurring period (day, week, month, year, onetime) used in conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and capture payment form, or an array of HTML markup
     * @throws Exception
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {

        Loader::loadModels($this, ['Clients', 'Contacts', 'Companies']);
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'coinpayments_api.php');
        $api = new CoinpaymentsApi();

        $client = $this->Clients->get($contact_info['client_id'], false);
        $client->fields = $this->Clients->getCustomFieldValues($contact_info['client_id']);

        $client_phone = '';
        $contact_numbers = $this->Contacts->getNumbers($client->contact_id);
        foreach ($contact_numbers as $contact_number) {
            if ($contact_number->type == 'phone') {
                $client_phone = $contact_number->number;
                break;
            }
        }

        if (!empty($client_phone)) {
            $client_phone = preg_replace('/[^0-9]/', '', $client_phone);
        }

        $client_id = $this->meta['client_id'];
        $webhooks = $this->meta['webhooks'];
        $client_secret = $this->meta['client_secret'];
        $host_address = $api->getHost();
        $billing_data = array(
            'company' => $contact_info['company'],
            'first_name' => $contact_info['first_name'],
            'last_name' => $contact_info['last_name'],
            'phone' => $client_phone,
            'email' => $client->email,
            'city' => $contact_info['city'],
            'country' => $contact_info['country']['name'],
            'address_1' => $contact_info['address_1'],
            'address_2' => $contact_info['address_2'],
            'state' => $contact_info['state']['name'],
            'postcode' => $contact_info['zip'],
        );

        if (count($invoice_amounts) == 1) {
            $order_url = sprintf('%s/admin/clients/editinvoice/%s/%s', $api->getHost(), $contact_info['client_id'], $invoice_amounts[0]['id']);
            $order_str = sprintf('Client #%s Invoice #%s', $contact_info['client_id'], $invoice_amounts[0]['id']);
        } else {
            $order_url = sprintf('%s/admin/clients/view/%s', $api->getHost(), $contact_info['client_id']);
            $order_str = sprintf('Client #%s', $contact_info['client_id']);
        }

        $company_id = Configure::get('Blesta.company_id');
        $company = $this->Companies->get($company_id);
        $notes_link = sprintf(
            "%s|Store name: %s|%s",
            $order_url,
            $company->name,
            $order_str
        );

        $invoice_id = sprintf('%s|%s', md5($host_address), $contact_info['client_id']);
        $post_to = sprintf('%s/%s/', CoinpaymentsApi::CHECKOUT_URL, CoinpaymentsApi::API_CHECKOUT_ACTION);

        $coin_currency = $api->getCoinCurrency($this->currency);

        $invoice_params = array(
            'client_id' => $client_id,
            'currency_id' => $coin_currency['id'],
            'invoice_id' => $invoice_id,
            'amount' => number_format($amount, $coin_currency['decimalPlaces'], '', ''),
            'display_value' => $amount,
            'invoice_amounts' => $invoice_amounts,
            'billing_data' => $billing_data,
            'notes_link' => $notes_link,
        );

        if ($webhooks) {
            $invoice_params['client_secret'] = $client_secret;
            $resp = $api->createMerchantInvoice($invoice_params);
            $invoice = array_shift($resp['invoices']);
        } else {
            $invoice = $api->createSimpleInvoice($invoice_params);
        }

        // An array of key/value hidden fields to set for the payment form
        $fields = array(
            'invoice-id' => $invoice['id'],
            'success-url' => $this->ifSet($options['return_url']),
            'cancel-url' => $this->ifSet($options['return_url']),
        );

        return $this->buildForm($post_to, $fields, false);
    }

    /**
     * Builds the HTML form
     *
     * @param string $post_to The URL to post to
     * @param array $fields An array of key/value input fields to set in the form
     * @param boolean $recurring True if this is a recurring payment request, false otherwise
     * @return string The HTML form
     */
    private function buildForm($post_to, $fields, $recurring = false)
    {
        $this->view = $this->makeView("process", "default", str_replace(ROOTWEBDIR, "", dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html"));

        $this->view->set("post_to", $post_to);
        $this->view->set("fields", $fields);
        $this->view->set("recurring", $recurring);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *    - id The ID of the invoice to apply to
     *    - amount The amount to apply to the invoice
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - reference_id The reference ID for gateway-only use with this transaction (optional)
     *    - transaction_id The ID returned by the gateway to identify this transaction
     *    - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {

        $content = file_get_contents('php://input');

        if (!empty($this->meta['webhooks'])) {

            Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'coinpayments_api.php');
            $api = new CoinpaymentsApi();
            $client_id = $this->meta['client_id'];
            $client_secret = $this->meta['client_secret'];
            $signature = $_SERVER['HTTP_X_COINPAYMENTS_SIGNATURE'];
            $request_data = json_decode($content, true);
            if ($api->checkDataSignature($signature, $content, $client_id, $client_secret, $request_data['invoice']['status']) && isset($request_data['invoice']['invoiceId'])) {

                $invoice_str = $request_data['invoice']['invoiceId'];
                $invoice_str = explode('|', $invoice_str);
                $host_hash = array_shift($invoice_str);
                $invoice_id = array_shift($invoice_str);

                if ($host_hash == md5($api->getHost())) {
                    $display_value = $request_data['invoice']['amount']['displayValue'];
                    $trans_id = $request_data['invoice']['id'];

                    $status = 'pending';
                    if ($request_data['invoice']['status'] == CoinpaymentsApi::PAID_EVENT) {
                        $status = 'approved';
                    } elseif ($request_data['invoice']['status'] == CoinpaymentsApi::CANCELLED_EVENT) {
                        $status = 'declined';
                    }

                    $invoice_data = $api->getInvoiceData($request_data['invoice']['id'], $client_id, $client_secret);
                    $invoice_amounts = json_decode($invoice_data["customData"]["amounts"], true);
                    return array(
                        'client_id' => $invoice_id,
                        'amount' => $this->ifSet($display_value),
                        'currency' => $this->ifSet($request_data['invoice']['currency']['symbol']),
                        'status' => $status,
                        'reference_id' => null,
                        'transaction_id' => $trans_id,
                        'parent_transaction_id' => '',
                        'invoices' => $this->ifSet($invoice_amounts),
                    );
                }
            }
        }
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *    - id The ID of the invoice to apply to
     *    - amount The amount to apply to the invoice
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - transaction_id The ID returned by the gateway to identify this transaction
     *    - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        return array('status' => 'pending');
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this transaction
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *    - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *    - reference_id The reference ID for gateway-only use with this transaction (optional)
     *    - transaction_id The ID returned by the remote gateway to identify this transaction
     *    - message The message to be displayed in the interface in addition to the standard message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError("unsupported"));
        return array(
            'status' => "declined",
            'message' => 'CoinPayments does not support refunds.',
        );
    }
}

?>