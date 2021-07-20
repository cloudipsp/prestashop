<?php

/**
 * Class FondyOrder
 */
class FondyOrder extends ObjectModel
{
    public $id_cart;
    public $order_id;
    public $status;
    public $payment_id;
    public $preauth;
    public $checkout_url;

    public static $definition = array(
        'table' => 'fondy_orders',
        'primary' => 'id_cart',
        'fields' => array(
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'order_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'payment_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'preauth' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'checkout_url' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
        )
    );

    public static function getOrderById($order_id)
    {
        $query = new DBQuery();
        $query->select('id_cart');
        $query->from('fondy_orders');
        $query->where('order_id = ' . (int) $order_id);
        $cartID = Db::getInstance()->getValue($query);

        return new self($cartID);
    }
}