<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM
 * @copyright  2014-2019 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.0.0
 */

class FondyCls
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';
    const ORDER_EXPIRED = 'expired';
    const ORDER_PROCESSING = 'processing';

    const ORDER_SEPARATOR = '#';

    const SIGNATURE_SEPARATOR = '|';

//    const URL = "https://api.fondy.eu/api/checkout/redirect/";
    const API_URL = 'https://api.fondy.eu/api/';

    private static $merchantId;
    private static $secretKey;

    /**
     * @param mixed $merchantId
     */
    public static function setMerchantId($merchantId): void
    {
        self::$merchantId = $merchantId;
    }

    /**
     * @param mixed $secretKey
     */
    public static function setSecretKey($secretKey): void
    {
        self::$secretKey = $secretKey;
    }


    public static function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $password;
        foreach ($data as $v) {
            $str .= self::SIGNATURE_SEPARATOR . $v;
        }

        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    public static function validateRequest($response)
    {
        if (self::$merchantId != $response['merchant_id']) {
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }

        $responseSignature = $response['signature'];
        unset($response['response_signature_string']);
        unset($response['signature']);

        if (self::getSignature($response, self::$secretKey) != $responseSignature) {
            return 'An error has occurred during payment. Signature is not valid.';
        }

        return true;
    }

    public static function getCheckoutUrl($requestData)
    {
        $request = self::sendToAPI('checkout/url', $requestData);

        return $request->checkout_url;
    }

    public static function capture($requestData)
    {
        $request = self::sendToAPI('capture/order_id', $requestData);

        if ($request->capture_status != 'captured')
            throw new \Exception('Fondy capture status: ' . $request->capture_status);

        return true;
    }

    public static function reverse($requestData)
    {
        $request = self::sendToAPI('reverse/order_id', $requestData);

        if ($request->reverse_status != 'approved')
            throw new \Exception('Fondy refund status: ' . $request->reverse_status);

        return true;
    }

    public static function sendToAPI($endpoint, $requestData)
    {
        $requestData['merchant_id'] = self::$merchantId;

        $requestData['signature'] = self::getSignature($requestData, self::$secretKey);
        $request = self::sendCurl(self::API_URL . $endpoint, $requestData);

        if (empty($request->response) && empty($request->response->response_status))
            throw new \Exception('Unknown Fondy API answer.');

        if ($request->response->response_status != 'success')
            throw new \Exception('Fondy: ' . $request->response->error_message);

        return $request->response;
    }

    private static function sendCurl($url, $fields)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['request' => $fields]));
        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }
}
