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
    public $last_tran_type;
    public $preauth;
    public $checkout_url;

    public static $definition = array(
        'table' => 'fondy_orders',
        'primary' => 'order_id',
        'fields' => array(
            'order_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'payment_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'last_tran_type' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'preauth' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'checkout_url' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
        )
    );

    /**
     * get fondy order from Prestashop id_order
     *
     * @param $order_id
     * @return FondyOrder
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByPSOrderId($order_id)
    {
        $query = new DBQuery();
        $query->select('order_id');
        $query->from('fondy_orders');
        $query->where("order_id LIKE '$order_id#%'");
        $order_id = Db::getInstance()->getValue($query);

        return new self($order_id);
    }
}