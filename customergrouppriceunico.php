<?php
/**
 * 2007-2020 PrestaShop.
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

    public function __construct()
    {
        $this->name = 'customergrouppriceunico';
        $this->version = '1.0.0';
        $this->author = 'TMD';
        $this->tab = 'front_office_features';
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
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

        $enable = Tools::getValue('cgrppriceunico_enable', Configuration::get('CGRPPRICEUNICO_ENABLE'));
        $this->context->smarty->assign(['enable' => $enable]);
        $this->context->smarty->clearAllCache();
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionProductUpdate')
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
        return parent::uninstall()
            && Configuration::deleteByName('CGRPPRICEUNICO_ENABLE')
            && $this->uninstallTab()
            && $this->uninstallDB();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitCgrppriceunico')) {
            Configuration::updateValue(
                'CGRPPRICEUNICO_ENABLE',
                (bool) Tools::getValue('cgrppriceunico_enable')
            );
            $this->_html .= $this->displayConfirmation(
                $this->trans('Configuracion guardada correctamente.', [], 'Modules.Customergrouppriceunico.Admin')
            );
        }

        $this->_html .= $this->display(__FILE__, 'views/templates/admin/customergrouppriceunico.tpl');
        return $this->_html;
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $enable = Tools::getValue('cgrppriceunico_enable', Configuration::get('CGRPPRICEUNICO_ENABLE'));
        $currency = $this->context->currency;
        $currency_symbol = $currency->symbol;

        $id_product = $params['id_product'];
        $customer_groups = Group::getGroups(Context::getContext()->language->id);
        $customer_grp = [];

        foreach ($customer_groups as $cust_grp) {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'customergrouppriceunico WHERE
                id_product=' . (int) $id_product . ' AND group_id=' . (int) $cust_grp['id_group'];
            $grp_price = Db::getInstance()->getRow($sql);
            $grp_product_price = !empty($grp_price['product_price']) ? $grp_price['product_price'] : '0';

            $customer_grp[] = [
                'id'            => $cust_grp['id_group'],
                'name'          => $cust_grp['name'],
                'product_price' => $grp_product_price,
            ];
        }

        $configure_link = $this->context->link->getAdminLink(
            'AdminModules',
            false
        ) . '&configure=customergrouppriceunico&token=' . Tools::getAdminTokenLite('AdminModules');

        $this->context->smarty->assign([
            'currency_symbol' => $currency_symbol,
            'customer_groups' => $customer_grp,
            'configure_link'  => $configure_link,
        ]);

        if ($enable == 1) {
            return $this->fetch('module:customergrouppriceunico/views/templates/hook/unicogrouppricelist.tpl');
        }
    }

    protected $isSaved = false;

    public function hookActionProductUpdate($params)
    {
        if ($this->isSaved) {
            return null;
        }

        $id_product = $params['id_product'];
        $date = date('y-m-d');
        $allgroup_price = Tools::getValue('unico_group_price');

        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'customergrouppriceunico` WHERE id_product=' . (int) $id_product
        );

        if (!empty($allgroup_price)) {
            foreach ($allgroup_price as $id_group => $row) {
                $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'customergrouppriceunico SET
                    `id_product`    = \'' . pSQL($id_product) . '\',
                    `product_price` = \'' . pSQL($row['product_price']) . '\',
                    `group_id`      = \'' . pSQL($id_group) . '\',
                    `date_add`      = \'' . pSQL($date) . '\',
                    `date_upd`      = \'' . pSQL($date) . '\'';
                Db::getInstance()->execute($sql);
            }
        }

        if ($allgroup_price) {
            $this->isSaved = true;
        }
    }
}
