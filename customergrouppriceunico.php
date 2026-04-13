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

        // Aviso informativo en PS9: override operativo pero deprecated
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            $this->warning = $this->trans(
                'PS9 detectado: el sistema de overrides esta deprecated. El modulo funciona correctamente en la version actual de PS9.',
                [],
                'Modules.Customergrouppriceunico.Admin'
            );
        }
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
        $tmdtab   = true;
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
            $tab->module    = $this->name;
            $tmdtab  &= $tab->add();
            $id_parent = $tab->id;
        }

        $submenu = [
            ['class' => 'Customergrouppriceunico', 'name' => $this->trans('Form Setting')],
            ['class' => 'Unicoexport',             'name' => $this->trans('Unico Export')],
            ['class' => 'Unicoimport',             'name' => $this->trans('Unico Import')],
        ];

        foreach ($submenu as $item) {
            if (!Tab::getIdFromClassName($item['class'])) {
                $tab = new Tab();
                $tab->active = 1;
                $tab->class_name = $item['class'];
                $tab->name = [];
                foreach (Language::getLanguages() as $lang) {
                    $tab->name[$lang['id_lang']] = $item['name'];
                }
                $tab->id_parent = $id_parent;
                $tab->module    = $this->name;
                $tmdtab &= $tab->add();
            }
        }

        return $tmdtab;
    }

    public function uninstallTab()
    {
        foreach (['Customergrouppriceunico', 'Unicoexport', 'Unicoimport'] as $class_name) {
            $id = Tab::getIdFromClassName($class_name);
            if ($id) {
                (new Tab((int) $id))->delete();
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

    // -------------------------------------------------------------------------
    // Configuracion del modulo
    // -------------------------------------------------------------------------

    public function getContent()
    {
        if (Tools::isSubmit('submitCgrppriceunico')) {
            Configuration::updateValue(
                'CGRPPRICEUNICO_ENABLE',
                (int) Tools::getValue('cgrppriceunico_enable')
            );
            $this->_html .= $this->displayConfirmation(
                $this->trans('Configuracion guardada correctamente.', [], 'Modules.Customergrouppriceunico.Admin')
            );
        }

        $enable = (int) Configuration::get('CGRPPRICEUNICO_ENABLE');
        $this->context->smarty->assign(['enable' => $enable]);
        $this->_html .= $this->display(__FILE__, 'views/templates/admin/customergrouppriceunico.tpl');
        return $this->_html;
    }

    // -------------------------------------------------------------------------
    // Hook: formulario de producto
    // -------------------------------------------------------------------------

    public function hookDisplayAdminProductsExtra($params)
    {
        if (!(int) Configuration::get('CGRPPRICEUNICO_ENABLE')) {
            return;
        }

        $id_product      = (int) $params['id_product'];
        $customer_groups = Group::getGroups((int) $this->context->language->id);
        $customer_grp    = [];

        foreach ($customer_groups as $cust_grp) {
            $grp_price = Db::getInstance()->getValue(
                'SELECT `product_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
                WHERE `id_product` = ' . $id_product . '
                AND `group_id` = '   . (int) $cust_grp['id_group']
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

    // -------------------------------------------------------------------------
    // Hooks: guardar precios al salvar producto
    // -------------------------------------------------------------------------

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

    /**
     * Igual que hookActionProductUpdate pero para productos nuevos.
     * En PS los params pueden traer id_product o el objeto Product.
     */
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
    // Persistencia de precios de grupo
    // -------------------------------------------------------------------------

    protected function saveGroupPrices($id_product, array $allgroup_price)
    {
        $date = date('Y-m-d');

        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
            WHERE `id_product` = ' . $id_product
        );

        foreach ($allgroup_price as $id_group => $row) {
            $price = (float) $row['product_price'];
            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'customergrouppriceunico` SET
                `id_product`    = ' . $id_product . ',
                `product_price` = \'' . $price . '\',
                `group_id`      = ' . (int) $id_group . ',
                `date_add`      = \'' . pSQL($date) . '\',
                `date_upd`      = \'' . pSQL($date) . '\''
            );
        }
    }
}
