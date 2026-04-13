<?php
/**
 * Override de Product para aplicar precio unico por grupo de cliente.
 *
 * Compatibilidad por version:
 *
 *   PS 1.7.x — Funciona. Override del metodo estatico priceCalculation().
 *   PS 8.x   — Funciona. Mismo metodo, misma firma. Override soportado.
 *   PS 9.x   — Override no se instala (causa pantalla en blanco). Se usa
 *              el hook actionProductPriceCalculation en su lugar.
 *
 * Logica de precios:
 *   - El precio de grupo SUSTITUYE el precio base del producto.
 *   - El precio de atributo (combinacion) de PS se sigue sumando sobre el.
 *   - Impuestos, ecotax y reducciones se aplican con normalidad.
 *   - Los precios se almacenan SIN IVA (igual que el precio base de PS).
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class Product extends ProductCore
{
    public static function priceCalculation(
        $id_shop,
        $id_product,
        $id_product_attribute,
        $id_country,
        $id_state,
        $zipcode,
        $id_currency,
        $id_group,
        $quantity,
        $use_tax,
        $decimals,
        $only_reduc,
        $use_reduc,
        $with_ecotax,
        &$specific_price,
        $use_group_reduction,
        $id_customer = 0,
        $use_customer_price = true,
        $id_cart = 0,
        $real_quantity = 0,
        $id_customization = 0
    ) {
        /*
         * Cuando el modulo esta desactivado delegamos directamente al calculo
         * nativo de PrestaShop. Cero coste, maximo rendimiento, y garantia
         * de compatibilidad total con cualquier version de PS.
         */
        if (!(int) Configuration::get('CGRPPRICEUNICO_ENABLE')) {
            return parent::priceCalculation(
                $id_shop, $id_product, $id_product_attribute,
                $id_country, $id_state, $zipcode, $id_currency,
                $id_group, $quantity, $use_tax, $decimals,
                $only_reduc, $use_reduc, $with_ecotax,
                $specific_price, $use_group_reduction,
                $id_customer, $use_customer_price,
                $id_cart, $real_quantity, $id_customization
            );
        }

        // ------------------------------------------------------------------ //
        // A partir de aqui: logica activa del modulo
        // ------------------------------------------------------------------ //

        static $address = null;
        static $context = null;

        if ($context === null) {
            $context = Context::getContext()->cloneContext();
        }

        if ($address === null) {
            if (is_object($context->cart)
                && $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null
            ) {
                $id_address = $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
                $address = new Address($id_address);
            } else {
                $address = new Address();
            }
        }

        if ($id_shop !== null && $context->shop->id != (int) $id_shop) {
            $context->shop = new Shop((int) $id_shop);
        }

        if (!$use_customer_price) {
            $id_customer = 0;
        }

        if ($id_product_attribute === null) {
            $id_product_attribute = Product::getDefaultAttribute($id_product);
        }

        $cache_id = (int) $id_product . '-' . (int) $id_shop . '-' . (int) $id_currency
            . '-' . (int) $id_country . '-' . $id_state . '-' . $zipcode
            . '-' . (int) $id_group . '-' . (int) $quantity
            . '-' . (int) $id_product_attribute . '-' . (int) $id_customization
            . '-' . (int) $with_ecotax . '-' . (int) $id_customer
            . '-' . (int) $use_group_reduction . '-' . (int) $id_cart
            . '-' . (int) $real_quantity
            . '-' . ($only_reduc ? '1' : '0')
            . '-' . ($use_reduc  ? '1' : '0')
            . '-' . ($use_tax    ? '1' : '0')
            . '-' . (int) $decimals;

        $specific_price = SpecificPrice::getSpecificPrice(
            (int) $id_product,
            $id_shop,
            $id_currency,
            $id_country,
            $id_group,
            $quantity,
            $id_product_attribute,
            $id_customer,
            $id_cart,
            $real_quantity
        );

        if (isset(self::$_prices[$cache_id])) {
            return self::$_prices[$cache_id];
        }

        // Carga de precios nivel 2 (precio base + atributo)
        $cache_id_2 = $id_product . '-' . $id_shop;
        if (!isset(self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute])) {
            $sql = new DbQuery();
            $sql->select('product_shop.`price`, product_shop.`ecotax`');
            $sql->from('product', 'p');
            $sql->innerJoin(
                'product_shop',
                'product_shop',
                '(product_shop.id_product = p.id_product
                AND product_shop.id_shop = ' . (int) $id_shop . ')'
            );
            $sql->where('p.`id_product` = ' . (int) $id_product);

            if (Combination::isFeatureActive()) {
                $sql->select(
                    'IFNULL(product_attribute_shop.id_product_attribute, 0) id_product_attribute,
                    product_attribute_shop.`price`  AS attribute_price,
                    product_attribute_shop.default_on,
                    product_attribute_shop.`ecotax` AS attribute_ecotax'
                );
                $sql->leftJoin(
                    'product_attribute_shop',
                    'product_attribute_shop',
                    '(product_attribute_shop.id_product = p.id_product
                    AND product_attribute_shop.id_shop = ' . (int) $id_shop . ')'
                );
            } else {
                $sql->select('0 as id_product_attribute');
            }

            $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            if (is_array($res) && count($res)) {
                foreach ($res as $row) {
                    $array_tmp = [
                        'price'            => $row['price'],
                        'ecotax'           => $row['ecotax'],
                        'attribute_price'  => $row['attribute_price']  ?? null,
                        'attribute_ecotax' => $row['attribute_ecotax'] ?? null,
                    ];
                    self::$_pricesLevel2[$cache_id_2][(int) $row['id_product_attribute']] = $array_tmp;

                    if (isset($row['default_on']) && $row['default_on'] == 1) {
                        self::$_pricesLevel2[$cache_id_2][0] = $array_tmp;
                    }
                }
            }
        }

        if (!isset(self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute])) {
            return null;
        }

        $result = self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute];

        // Precio base: SpecificPrice fijo si existe, o precio del producto
        if (!$specific_price || $specific_price['price'] < 0) {
            $price = (float) $result['price'];
        } else {
            $price = (float) $specific_price['price'];
        }

        // ---- Inyeccion del precio unico de grupo -------------------------
        //
        // IMPORTANTE: usamos $id_group (parametro del metodo) directamente.
        //
        // NO usamos $context->customer->id_default_group porque $context es
        // una variable static que se cachea en la primera llamada dentro del
        // mismo proceso. Si la primera llamada viene del backoffice (admin),
        // el contexto queda congelado con el grupo del administrador, y todas
        // las llamadas posteriores del frontend usarian ese grupo equivocado,
        // no encontrarian precio de grupo y devolverian el precio normal.
        //
        // El parametro $id_group ya contiene el grupo correcto: PS lo calcula
        // en getPriceStatic() a partir de Customer::getDefaultGroupId() o de
        // Group::getCurrent() para visitantes, antes de llamar a este metodo.
        //
        if ($id_group) {
            $group_price = Db::getInstance()->getValue(
                'SELECT `product_price` FROM `' . _DB_PREFIX_ . 'customergrouppriceunico`
                WHERE `id_product` = ' . (int) $id_product . '
                AND `group_id` = '   . (int) $id_group
            );
            if ((float) $group_price > 0) {
                $price = (float) $group_price;
            }
        }
        // ------------------------------------------------------------------

        // Conversion de moneda
        if (!$specific_price
            || !(
                $specific_price['price'] >= 0
                && $specific_price['id_currency']
                && $id_currency !== $specific_price['id_currency']
            )
        ) {
            $price = Tools::convertPrice($price, $id_currency);
            if (isset($specific_price['price']) && $specific_price['price'] >= 0) {
                $specific_price['price'] = $price;
            }
        }

        // Sumar precio de atributo (combinacion) estandar de PS
        if (is_array($result)
            && (!$specific_price
                || !$specific_price['id_product_attribute']
                || $specific_price['price'] < 0)
        ) {
            $attribute_price = Tools::convertPrice(
                $result['attribute_price'] !== null ? (float) $result['attribute_price'] : 0,
                $id_currency
            );
            if ($id_product_attribute !== false) {
                $price += $attribute_price;
            }
        }

        // Personalizacion
        if ((int) $id_customization && method_exists('Customization', 'getCustomizationPrice')) {
            $price += Tools::convertPrice(
                Customization::getCustomizationPrice($id_customization),
                $id_currency
            );
        }

        // Impuestos
        $address->id_country = $id_country;
        $address->id_state   = $id_state;
        $address->postcode   = $zipcode;

        $tax_manager = TaxManagerFactory::getManager(
            $address,
            Product::getIdTaxRulesGroupByIdProduct((int) $id_product, $context)
        );
        $product_tax_calculator = $tax_manager->getTaxCalculator();

        if ($use_tax) {
            $price = $product_tax_calculator->addTaxes($price);
        }

        // Ecotax
        if (($result['ecotax'] || isset($result['attribute_ecotax'])) && $with_ecotax) {
            $ecotax = $result['ecotax'];
            if (isset($result['attribute_ecotax']) && $result['attribute_ecotax'] > 0) {
                $ecotax = $result['attribute_ecotax'];
            }
            if ($id_currency) {
                $ecotax = Tools::convertPrice($ecotax, $id_currency);
            }
            if ($use_tax) {
                static $psEcotaxTaxRulesGroupId = null;
                if ($psEcotaxTaxRulesGroupId === null) {
                    $psEcotaxTaxRulesGroupId = (int) Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID');
                }
                $tax_manager_eco = TaxManagerFactory::getManager($address, $psEcotaxTaxRulesGroupId);
                $price += $tax_manager_eco->getTaxCalculator()->addTaxes($ecotax);
            } else {
                $price += $ecotax;
            }
        }

        // Reducciones de precio especifico
        $specific_price_reduction = 0;
        if (($only_reduc || $use_reduc) && $specific_price) {
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
        }

        if ($use_reduc) {
            $price -= $specific_price_reduction;
        }

        // Reduccion de grupo
        if ($use_group_reduction) {
            $reduction_from_category = GroupReduction::getValueForProduct($id_product, $id_group);
            if ($reduction_from_category !== false) {
                $group_reduction = $price * (float) $reduction_from_category;
            } else {
                $reduc = Group::getReductionByIdGroup($id_group);
                $group_reduction = $reduc != 0 ? ($price * $reduc / 100) : 0;
            }
            $price -= $group_reduction;
        }

        if ($only_reduc) {
            return Tools::ps_round($specific_price_reduction, $decimals);
        }

        $price = Tools::ps_round($price, $decimals);
        if ($price < 0) {
            $price = 0;
        }

        self::$_prices[$cache_id] = $price;
        return self::$_prices[$cache_id];
    }
}
