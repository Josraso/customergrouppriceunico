<?php
/**
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'customergrouppriceunico` (
    `id_group_price` int(11) NOT NULL AUTO_INCREMENT,
    `id_product`     int(11) NOT NULL,
    `group_id`       int(11) NOT NULL,
    `product_price`  decimal(20,6) NOT NULL,
    `date_add`       date NOT NULL,
    `date_upd`       date NOT NULL,
    PRIMARY KEY (`id_group_price`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
