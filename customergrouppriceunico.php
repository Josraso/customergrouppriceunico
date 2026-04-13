<?php
/**
 * 2007-2024 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

class Customergrouppriceunico extends Module
{
    private $_html = '';
    protected $isSaved = false;

    public function __construct()
    {
        $this->name = 'customergrouppriceunico';
        $this->version = '1.1.0';
        $this->author = 'TMD';
        $this->tab = 'front_office_features';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans(
            'Customer Group Price Unico',
            [],
            'Modules.Customergrouppriceunico.Admin'
        );
        $this->description = $this->trans(
            'Precio unico por grupo de cliente, sin precios por combinacion.',
            [],
            'Modules.Customergrouppriceunico.Admin'
        );
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductAdd')
            && $this->installTab()
            && $this->installDB();
    }

    public function installDB()
    {
        require_once __DIR__ . '/sql/install.php';
        return true;
    }

    public function uninstallDB()
    {
        require_once __DIR__ . '/sql/uninstall.php';
        return true;
    }

    public function installTab()
    {
        $tmdtab = true;
        $tabparent = 'Customergrouppriceunico';
        $id_parent = Tab::getIdFromClassName($tabparent);
        if (!$id_parent) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'Customergrouppriceunico';
            $tab->name = [];
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $this->trans('Customer Group Price Unico');
            }
            $tab->id_parent = 0;
            $tab->module = $this->name;
            $tmdtab &= $tab->add();
            $id_parent = $tab->id;
        }

        $submenu = [
            [
                'class' => 'Customergrouppriceunico',
                'name'  => $this->trans('Form Setting'),
            ],
            [
                'class' => 'Unicoexport',
                'name'  => $this->trans('Unico Export'),
            ],
            [
                'class' => 'Unicoimport',
                'name'  => $this->trans('Unico Import'),
            ],
        ];

        foreach ($submenu as $item) {
            $idtab = Tab::getIdFromClassName($item['class']);
            if (!$idtab) {
                $tab = new Tab();
                $tab->active = 1;
                $tab->class_name = $item['class'];
                $tab->name = [];
                foreach (Language::getLanguages() as $lang) {
                    $tab->name[$lang['id_lang']] = $item['name'];
                }
                $tab->id_parent = $id_parent;
                $tab->module = $this->name;
                $tmdtab &= $tab->add();
            }
        }

        return $tmdtab;
    }

    public function uninstallTab()
    {
        $alltabs = ['Customergrouppriceunico', 'Unicoexport', 'Unicoimport'];
        foreach ($alltabs as $class_name) {
            $id = Tab::getIdFromClassName($class_name);
            if ($id) {
                $tab = new Tab((int) $id);
                $tab->delete();
            }
        }
        return true;
    }

    public function uninstall()
    {
        $this->disableAllSpecificPrices();
        return parent::uninstall()
            && Configuration::deleteByName('CGRPPRICEUNICO_ENABLE')
            && $this->uninstallTab()
            && $this->uninstallDB();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitCgrppriceunico')) {
            $was_enabled = (int) Configuration::get('CGRPPRICEUNICO_ENABLE');
            $now_enabled = (int) Tools::getValue('cgrppriceunico_enable');

            Configuration::updateValue('CGRPPRICEUNICO_ENABLE', $now_enabled);

            if (!$was_enabled && $now_enabled) {
                $this->enableAllSpecificPrices();
            } elseif ($was_enabled && !$now_enabled) {
                $this->disableAllSpecificPrices();
            }

            $this->_html .= $this->displayConfirmation(
                $this->trans('Configuracion guardada correctamente.', [], 'Modules.Customergrouppriceunico.Admin')
            );
        }

        $enable = (int) Configuration::get('CGRPPRICEUNICO_ENABLE');
        $this->context->smarty->assign(['enable' => $enable]);
        $this->_html .= $this->display(__FILE__, 'views/templates/admin/customergrouppriceunico.tpl');
        return $this->_html;
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        if (!(int) Configuration::get('CGRPPRICEUNICO_ENABLE')) {
            return;
        }

        $id_product = (int) $params['id_product'];
        $customer_groups = Group::getGroups((int) $this->context->language->id);
        $customer_grp = [];

        foreach ($customer_groups as $cust_grp) {
            $grp_price = Db::getInstance()->getValue(
                'SELECT `product_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
                WHERE `id_product` = ' . $id_product . ' AND `group_id` = ' . (int) $cust_grp['id_group']
            );

            $customer_grp[] = [
                'id'            => $cust_grp['id_group'],
                'name'          => $cust_grp['name'],
                'product_price' => $grp_price ?: '0',
            ];
        }

        $configure_link = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=customergrouppriceunico&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'currency_symbol' => $this->context->currency->symbol,
            'customer_groups' => $customer_grp,
            'configure_link'  => $configure_link,
        ]);

        return $this->fetch('module:customergrouppriceunico/views/templates/hook/unicogrouppricelist.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        if ($this->isSaved) {
            return;
        }
        $allgroup_price = Tools::getValue('unico_group_price');
        if ($allgroup_price === false) {
            return;
        }
        $this->saveGroupPrices((int) $params['id_product'], (array) $allgroup_price);
        $this->isSaved = true;
    }

    public function hookActionProductAdd($params)
    {
        if ($this->isSaved) {
            return;
        }
        $allgroup_price = Tools::getValue('unico_group_price');
        if ($allgroup_price === false) {
            return;
        }
        $id_product = isset($params['id_product']) ? (int) $params['id_product'] : 0;
        if (!$id_product && isset($params['object'])) {
            $id_product = (int) $params['object']->id;
        }
        if (!$id_product) {
            return;
        }
        $this->saveGroupPrices($id_product, (array) $allgroup_price);
        $this->isSaved = true;
    }

    // -------------------------------------------------------------------------
    // Gestion de precios de grupo
    // -------------------------------------------------------------------------

    protected function saveGroupPrices($id_product, array $allgroup_price)
    {
        $enable = (int) Configuration::get('CGRPPRICEUNICO_ENABLE');
        $date   = date('Y-m-d');

        $this->deleteSpecificPricesForProduct($id_product);

        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'customergrouppriceunico` WHERE `id_product` = ' . $id_product
        );

        foreach ($allgroup_price as $id_group => $row) {
            $price = (float) $row['product_price'];
            $id_specific_price = 'NULL';

            if ($enable && $price > 0) {
                $sp_id = $this->createSpecificPriceForGroup($id_product, (int) $id_group, $price);
                if ($sp_id) {
                    $id_specific_price = (int) $sp_id;
                }
            }

            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'customergrouppriceunico` SET
                `id_product`        = ' . $id_product . ',
                `product_price`     = \'' . (float) $price . '\',
                `group_id`          = ' . (int) $id_group . ',
                `id_specific_price` = ' . $id_specific_price . ',
                `date_add`          = \'' . pSQL($date) . '\',
                `date_upd`          = \'' . pSQL($date) . '\''
            );
        }
    }

    // -------------------------------------------------------------------------
    // SpecificPrice helpers
    // -------------------------------------------------------------------------

    /**
     * Crea un SpecificPrice nativo de PrestaShop para el par producto+grupo.
     * Devuelve el id del SpecificPrice creado, o 0 si falla.
     */
    public function createSpecificPriceForGroup($id_product, $id_group, $price)
    {
        $sp = new SpecificPrice();
        $sp->id_shop              = (int) $this->context->shop->id;
        $sp->id_shop_group        = 0;
        $sp->id_currency          = 0;
        $sp->id_country           = 0;
        $sp->id_group             = (int) $id_group;
        $sp->id_customer          = 0;
        $sp->id_product           = (int) $id_product;
        $sp->id_product_attribute = 0;
        $sp->id_cart              = 0;
        $sp->price                = (float) $price;
        $sp->from_quantity        = 1;
        $sp->reduction            = 0;
        $sp->reduction_tax        = 1;
        $sp->reduction_type       = 'amount';
        $sp->from                 = '0000-00-00 00:00:00';
        $sp->to                   = '0000-00-00 00:00:00';
        $sp->add();
        return (int) $sp->id;
    }

    /**
     * Elimina los SpecificPrices rastreados por el modulo para un producto.
     */
    protected function deleteSpecificPricesForProduct($id_product)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_specific_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
            WHERE `id_product` = ' . (int) $id_product . ' AND `id_specific_price` IS NOT NULL'
        );
        foreach ($rows as $row) {
            $sp = new SpecificPrice((int) $row['id_specific_price']);
            if (Validate::isLoadedObject($sp)) {
                $sp->delete();
            }
        }
    }

    /**
     * Crea SpecificPrices para todos los registros de la tabla (al activar el modulo).
     */
    protected function enableAllSpecificPrices()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'customergrouppriceunico` WHERE `product_price` > 0'
        );
        foreach ($rows as $row) {
            if (!empty($row['id_specific_price'])) {
                continue;
            }
            $sp_id = $this->createSpecificPriceForGroup(
                (int) $row['id_product'],
                (int) $row['group_id'],
                (float) $row['product_price']
            );
            if ($sp_id) {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'customergrouppriceunico`
                    SET `id_specific_price` = ' . $sp_id . '
                    WHERE `id_group_price` = ' . (int) $row['id_group_price']
                );
            }
        }
    }

    /**
     * Elimina todos los SpecificPrices rastreados por el modulo (al desactivar).
     */
    protected function disableAllSpecificPrices()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_specific_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
            WHERE `id_specific_price` IS NOT NULL'
        );
        foreach ($rows as $row) {
            $sp = new SpecificPrice((int) $row['id_specific_price']);
            if (Validate::isLoadedObject($sp)) {
                $sp->delete();
            }
        }
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'customergrouppriceunico` SET `id_specific_price` = NULL'
        );
    }

    /**
     * Resincroniza los SpecificPrices de un producto concreto.
     * Llamado desde el importador tras importar precios.
     */
    public function syncSpecificPricesForProduct($id_product)
    {
        if (!(int) Configuration::get('CGRPPRICEUNICO_ENABLE')) {
            return;
        }
        $this->deleteSpecificPricesForProduct($id_product);
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'customergrouppriceunico`
            SET `id_specific_price` = NULL WHERE `id_product` = ' . (int) $id_product
        );
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
            WHERE `id_product` = ' . (int) $id_product . ' AND `product_price` > 0'
        );
        foreach ($rows as $row) {
            $sp_id = $this->createSpecificPriceForGroup(
                (int) $row['id_product'],
                (int) $row['group_id'],
                (float) $row['product_price']
            );
            if ($sp_id) {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'customergrouppriceunico`
                    SET `id_specific_price` = ' . $sp_id . '
                    WHERE `id_group_price` = ' . (int) $row['id_group_price']
                );
            }
        }
    }
}
