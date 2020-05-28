IMPORTANT NOTE:

This is only for use with: https://alpha.coinpayments.net/

NOT for use with https://coinpayments.net

Demonstration Website Disclaimer:   The information presented on alpha.coinpayments.net (the "Demo Site") is for demonstration purposes only. All content on the Demo Site is considered “in development” and should be used at your own risk. CoinPayments Inc. assumes no responsibility or liability for any errors or omissions in the content of the Demo Site. The information contained in the Demo Site is provided on an "as is" basis with no guarantees of completeness, accuracy, usefulness or timeliness and without any warranties of any kind whatsoever, express or implied. CoinPayments Inc. does not warrant that the Demo Site and any information or material downloaded from the Demo Site, will be uninterrupted, error-free, omission-free or free of viruses or other harmful items.

In no event will CoinPayments Inc. or its directors, officers, employees, shareholders, service providers or agents, be liable to you, or anyone else, for any decision(s) made or action(s) taken in reliance upon the information contained in the Demo Site, nor for any direct, indirect, incidental, special, exemplary, punitive, consequential, or other damages whatsoever (including, but not limited to, liability for loss of use, funds, data or profits) whether in an action of contract, statute, tort or otherwise, relating to the use of the Demo Site.

# Coin Payments Gateway
## Install the Gateway

1. You can install the gateway via git:

    ```
    git clone https://github.com/CoinPaymentsNet/coinpayments-blesta/tree/cps_api_v2
    ```

2. Upload the source code to a /components/gateways/nonmerchant/coin_payments/ directory within
your Blesta installation path.

    For example:

    ```
    /var/www/html/blesta/components/nonmerchant/coin_payments/
    ```

## Update the Gateway

Upload with replace the source code to a /components/gateways/nonmerchant/coin_payments/ directory within your Blesta installation path.
   
For example:
   
```
/var/www/html/blesta/components/nonmerchant/coin_payments/
```
   
Log in to your admin Blesta account and navigate to:
    > Settings > Payment Gateways

Find the Coin Payments gateway and click the "Upgrade" button to install it


## Configure Gateway
1. Log in to your admin Blesta account and navigate to:
    > Settings > Payment Gateways

2. Find the Coin Payments gateway and click the "Install" button to install it

3. Edit the gateway settings with your CoinPayments Client ID and Client Secret.

4. You're done!