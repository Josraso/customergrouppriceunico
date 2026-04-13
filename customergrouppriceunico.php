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

        // PS9: los overrides de clase causan pantalla en blanco; se usa hook en su lugar
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            $this->warning = $this->trans(
                'PS9 detectado: se usa hook actionProductPriceCalculation en lugar de override de clase.',
                [],
                'Modules.Customergrouppriceunico.Admin'
            );
        }
    }

    public function install()
    {
        $result = parent::install()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionAfterUpdateProductFormHandler')
            && $this->registerHook('actionAfterCreateProductFormHandler')
            && $this->installTab()
            && $this->installDB();

        // PS9+: el sistema de overrides causa pantalla en blanco; usamos hook en su lugar
        if ($result && version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            $result = $this->registerHook('actionProductPriceCalculation');
        }

        return $result;
    }

    /**
     * PS9+: no instalar el override de clase (causa pantalla en blanco).
     * Para PS < 9 se delega al comportamiento nativo.
     */
    public function installOverrides()
    {
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            return true;
        }

        return parent::installOverrides();
    }

    /**
     * Siempre intentar limpiar overrides al desinstalar, incluso en PS9+.
     * Necesario para borrar el override que instalaciones antiguas
     * (con codigo previo) dejaron en el class index de PS9.
     */
    public function uninstallOverrides()
    {
        parent::uninstallOverrides();
        return true;
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
    // Hooks PS8.1+ / PS9: formulario Symfony de producto
    // En PS9 el controlador del producto es Symfony. Ademas de actionProductUpdate
    // (que sigue disparandose), estos hooks son el mecanismo oficial para el
    // nuevo formulario de producto. Los registramos como seguro adicional.
    // -------------------------------------------------------------------------

    public function hookActionAfterUpdateProductFormHandler($params)
    {
        if ($this->isSaved) {
            return;
        }
        $allgroup_price = Tools::getValue('unico_group_price');
        if ($allgroup_price === false) {
            return;
        }
        $id_product = isset($params['id']) ? (int) $params['id'] : 0;
        if (!$id_product && isset($params['product'])) {
            $id_product = (int) $params['product']->id;
        }
        if (!$id_product) {
            return;
        }
        $this->saveGroupPrices($id_product, (array) $allgroup_price);
        $this->isSaved = true;
    }

    public function hookActionAfterCreateProductFormHandler($params)
    {
        if ($this->isSaved) {
            return;
        }
        $allgroup_price = Tools::getValue('unico_group_price');
        if ($allgroup_price === false) {
            return;
        }
        $id_product = isset($params['id']) ? (int) $params['id'] : 0;
        if (!$id_product && isset($params['product'])) {
            $id_product = (int) $params['product']->id;
        }
        if (!$id_product) {
            return;
        }
        $this->saveGroupPrices($id_product, (array) $allgroup_price);
        $this->isSaved = true;
    }

    // -------------------------------------------------------------------------
    // Hook PS9+: calculo de precio via hook (sin override de clase)
    // El hook actionProductPriceCalculation se dispara AL FINAL de
    // ProductCore::priceCalculation(), con $price ya calculado (con IVA,
    // ecotax y reducciones aplicadas). Recalculamos desde cero sustituyendo
    // el precio base por nuestro precio de grupo.
    // -------------------------------------------------------------------------

    public function hookActionProductPriceCalculation($params)
    {
        if (!(int) Configuration::get('CGRPPRICEUNICO_ENABLE')) {
            return;
        }

        // only_reduc: peticion de descuento, no precio final; no aplica
        if (!empty($params['only_reduc'])) {
            return;
        }

        $id_product = (int) ($params['id_product'] ?? 0);
        $id_group   = (int) ($params['id_group']   ?? 0);

        if (!$id_product || !$id_group) {
            return;
        }

        $group_price = Db::getInstance()->getValue(
            'SELECT `product_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
            WHERE `id_product` = ' . $id_product . '
            AND `group_id` = '   . $id_group
        );

        if ((float) $group_price <= 0) {
            return;
        }

        // Extraer parametros del hook
        $id_shop              = (int) ($params['id_shop']               ?? Context::getContext()->shop->id);
        $id_currency          = (int) ($params['id_currency']           ?? 0);
        $id_product_attribute = (int) ($params['id_product_attribute']  ?? 0);
        $id_customization     = (int) ($params['id_customization']      ?? 0);
        $id_country           = (int) ($params['id_country']            ?? 0);
        $id_state             = (int) ($params['id_state']              ?? 0);
        $zipcode              = $params['zip_code']                     ?? '';
        $use_tax              = (bool) ($params['use_tax']              ?? false);
        $with_ecotax          = (bool) ($params['with_ecotax']          ?? true);
        $use_reduc            = (bool) ($params['use_reduc']            ?? true);
        $use_group_reduction  = (bool) ($params['use_group_reduction']  ?? true);
        $specific_price       = $params['specific_price']               ?? null;
        $address              = $params['address']                      ?? null;
        $context_obj          = $params['context']                      ?? null;

        // --- Recalculo desde cero con precio de grupo como base ---

        // Precio base: nuestro precio de grupo (almacenado sin IVA, moneda base)
        $price = (float) $group_price;

        // Conversion de moneda
        $price = Tools::convertPrice($price, $id_currency);

        // Precio de atributo (combinacion): se suma sobre el precio de grupo
        if ($id_product_attribute && Combination::isFeatureActive()) {
            $attribute_price = Db::getInstance()->getValue(
                'SELECT `price` FROM `' . _DB_PREFIX_ . 'product_attribute_shop`
                WHERE `id_product_attribute` = ' . $id_product_attribute . '
                AND `id_shop` = ' . $id_shop
            );
            if ($attribute_price !== false) {
                $price += Tools::convertPrice((float) $attribute_price, $id_currency);
            }
        }

        // Personalizacion
        if ($id_customization) {
            $price += Tools::convertPrice(
                Customization::getCustomizationPrice($id_customization),
                $id_currency
            );
        }

        // Impuestos
        if ($address === null) {
            $address = new Address();
            $address->id_country = $id_country;
            $address->id_state   = $id_state;
            $address->postcode   = $zipcode;
        }

        $tax_manager = TaxManagerFactory::getManager(
            $address,
            Product::getIdTaxRulesGroupByIdProduct($id_product, $context_obj)
        );
        $product_tax_calculator = $tax_manager->getTaxCalculator();

        if ($use_tax) {
            $price = $product_tax_calculator->addTaxes($price);
        }

        // Ecotax
        if ($with_ecotax) {
            $ecotax_row = Db::getInstance()->getRow(
                'SELECT ps.`ecotax`' .
                ($id_product_attribute ? ', pas.`ecotax` AS `attribute_ecotax`' : '') . '
                FROM `' . _DB_PREFIX_ . 'product_shop` ps' .
                ($id_product_attribute
                    ? ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                        ON pas.`id_product_attribute` = ' . $id_product_attribute . '
                        AND pas.`id_shop` = ps.`id_shop`'
                    : '') . '
                WHERE ps.`id_product` = ' . $id_product . '
                AND ps.`id_shop` = '   . $id_shop
            );

            if ($ecotax_row) {
                $ecotax = (float) $ecotax_row['ecotax'];
                if (!empty($ecotax_row['attribute_ecotax'])
                    && (float) $ecotax_row['attribute_ecotax'] > 0
                ) {
                    $ecotax = (float) $ecotax_row['attribute_ecotax'];
                }
                if ($ecotax > 0) {
                    if ($id_currency) {
                        $ecotax = Tools::convertPrice($ecotax, $id_currency);
                    }
                    if ($use_tax) {
                        $eco_tax_group_id = (int) Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID');
                        $eco_manager = TaxManagerFactory::getManager($address, $eco_tax_group_id);
                        $price += $eco_manager->getTaxCalculator()->addTaxes($ecotax);
                    } else {
                        $price += $ecotax;
                    }
                }
            }
        }

        // Reduccion de precio especifico (si existe un SpecificPrice independiente)
        if ($use_reduc && $specific_price) {
            if ($specific_price['reduction_type'] === 'amount') {
                $reduction_amount = $specific_price['reduction'];
                if (!$specific_price['id_currency']) {
                    $reduction_amount = Tools::convertPrice($reduction_amount, $id_currency);
                }
                $specific_price_reduction = $reduction_amount;
                if (!$use_tax && $specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->removeTaxes($specific_price_reduction);
                }
                if ($use_tax && !$specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->addTaxes($specific_price_reduction);
                }
            } else {
                $specific_price_reduction = $price * $specific_price['reduction'];
            }
            $price -= $specific_price_reduction;
        }

        // Reduccion de grupo (se aplica sobre el precio de grupo tambien)
        if ($use_group_reduction) {
            $reduction_from_category = GroupReduction::getValueForProduct($id_product, $id_group);
            if ($reduction_from_category !== false) {
                $price -= $price * (float) $reduction_from_category;
            } else {
                $reduc = Group::getReductionByIdGroup($id_group);
                if ($reduc != 0) {
                    $price -= $price * $reduc / 100;
                }
            }
        }

        if ($price < 0) {
            $price = 0;
        }

        $params['price'] = $price;
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
