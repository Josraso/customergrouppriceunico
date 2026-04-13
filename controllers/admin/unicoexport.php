<?php
/**
 * Controlador de exportacion Excel simplificado.
 * Columnas exportadas: Id Grupo | Nombre Grupo | Id Producto | Nombre Producto | Precio Grupo
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once _PS_MODULE_DIR_ . 'customergrouppriceunico/classes/Customergrouppriceunico.php';

class UnicoexportController extends AdminController
{
    protected $can_add_tab = true;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'customergrouppriceunico';
        $this->list_id = 'customergrouppriceunico';
        $this->identifier = 'id_group_price';
        $this->className = 'Customergrouppriceunicomodel';
        $this->_defaultOrderBy = 'id_group_price';
        $this->_defaultOrderWay = 'DESC';

        parent::__construct();

        $this->bulk_actions = [
            'delete' => [
                'text'    => $this->trans('Delete selected', [], 'Admin.Actions'),
                'icon'    => 'icon-trash',
                'confirm' => $this->trans('Delete selected items?', [], 'Admin.Notifications.Warning'),
            ],
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJqueryUi('ui.widget');
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_configure'] = [
                'href'   => $this->context->link->getAdminLink('AdminModules', false)
                    . '&configure=customergrouppriceunico&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc'   => $this->trans('Configure', [], 'Admin.Actions'),
                'target' => '_blank',
                'icon'   => 'icon-wrench',
            ];
        }
        parent::initPageHeaderToolbar();
    }

    public function initContent()
    {
        $enable   = Tools::getValue('cgrppriceunico_enable', Configuration::get('CGRPPRICEUNICO_ENABLE'));
        $products = Product::getSimpleProducts((int) $this->context->language->id);

        $customer_groups = Group::getGroups(Context::getContext()->language->id);
        $customer_grp = [];
        foreach ($customer_groups as $cust_grp) {
            $customer_grp[] = [
                'id'   => $cust_grp['id_group'],
                'name' => $cust_grp['name'],
            ];
        }

        if (Tools::isSubmit('submitUnicoproductexport')) {
            $groupid = Tools::getValue('group');
            if ($groupid == 'allgroups') {
                $this->allGroupexport();
            } else {
                $this->exportXls();
            }
        }

        $this->context->smarty->assign([
            'enable'       => $enable,
            'products'     => $products,
            'all_customer' => $customer_grp,
        ]);

        $this->content .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'customergrouppriceunico/views/templates/admin/unicoexport.tpl'
        );

        $this->context->smarty->assign(['content' => $this->content]);
    }

    public static function getSimpleProducts($id_lang, $start = null, $end = null)
    {
        $context = Context::getContext();
        $front = !in_array($context->controller->controller_type, ['front', 'modulefront']) ? false : true;

        $sql = 'SELECT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p
            ' . Shop::addSqlAssociation('product', 'p') . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` '
            . Shop::addSqlRestrictionOnLang('pl') . ')
            WHERE pl.`id_lang` = ' . (int) $id_lang . '
            ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
            ORDER BY pl.`name`';

        if ($start !== null && $end !== null) {
            $sql .= ' LIMIT ' . (int) $start . ', ' . (int) $end;
        }

        $allproducts = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $allproduct = [];
        foreach ($allproducts as $product) {
            $allproduct[] = $product['id_product'];
        }
        return $allproduct;
    }

    public function exportXls()
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $groupid = Tools::getValue('group');

        $products = Tools::getValue('product_ids');
        if (!empty($products)) {
            $selected_products = $products;
        } else {
            $limit_from = Tools::getValue('limit_from');
            $limit_to   = Tools::getValue('limit_to');
            if ($limit_from !== '' && $limit_to !== '') {
                $selected_products = $this->getSimpleProducts(
                    (int) $this->context->language->id,
                    (int) $limit_from,
                    (int) $limit_to
                );
            } else {
                // Sin filtro: exportar todos los productos
                $selected_products = $this->getSimpleProducts((int) $this->context->language->id);
            }
        }

        $alldataexport = [];
        foreach ($selected_products as $prod) {
            $groupname = $this->getGroup($groupid, $id_lang);

            $grp_sql  = 'SELECT * FROM ' . _DB_PREFIX_ . 'customergrouppriceunico WHERE
                id_product=' . (int) $prod . ' AND group_id=' . (int) $groupid;
            $grup_price = Db::getInstance()->getRow($grp_sql);

            $Products      = new Product($prod, false, $id_lang);
            $product_price = !empty($grup_price['product_price']) ? $grup_price['product_price'] : '0';

            $alldataexport[] = [
                'productid'     => $Products->id,
                'product_name'  => $Products->name,
                'product_price' => $product_price,
                'groupid'       => $groupid,
                'groupname'     => $groupname,
            ];
        }

        $this->buildSpreadsheet($alldataexport, false);
    }

    public function allGroupexport()
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $products = Tools::getValue('product_ids');
        if (!empty($products)) {
            $selected_products = $products;
        } else {
            $limit_from = Tools::getValue('limit_from');
            $limit_to   = Tools::getValue('limit_to');
            if ($limit_from !== '' && $limit_to !== '') {
                $selected_products = $this->getSimpleProducts(
                    (int) $this->context->language->id,
                    (int) $limit_from,
                    (int) $limit_to
                );
            } else {
                // Sin filtro: exportar todos los productos
                $selected_products = $this->getSimpleProducts((int) $this->context->language->id);
            }
        }

        $allgroupdata = [];
        foreach ($this->getAllGroups() as $group) {
            $allproexport = [];
            foreach ($selected_products as $prod) {
                $grp_sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'customergrouppriceunico WHERE
                    id_product=' . (int) $prod . ' AND group_id=' . (int) $group['id'];
                $grup_price = Db::getInstance()->getRow($grp_sql);

                $Products      = new Product($prod, false, $id_lang);
                $product_price = !empty($grup_price['product_price']) ? $grup_price['product_price'] : '0';

                $allproexport[] = [
                    'productid'     => $Products->id,
                    'product_name'  => $Products->name,
                    'product_price' => $product_price,
                    'groupid'       => $group['id'],
                    'groupname'     => $group['name'],
                ];
            }
            $allgroupdata = array_merge($allgroupdata, $allproexport);
        }

        $this->buildSpreadsheet($allgroupdata, true);
    }

    protected function buildSpreadsheet(array $rows, $allGroups)
    {
        $objPHPSpreadsheet = new Spreadsheet();
        $objPHPSpreadsheet->getProperties()
            ->setCreator('TMD Unico Export')
            ->setTitle('Customer Group Price Unico');
        $objPHPSpreadsheet->setActiveSheetIndex(0);

        $i   = 1;
        $cel = 'A';
        $headers = ['Id Group', 'Group Name', 'Id Product', 'Product Name', 'Product Group Price'];
        foreach ($headers as $header) {
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $header);
            ++$cel;
        }

        foreach ($rows as $data) {
            $cel = 'A';
            ++$i;
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $data['groupid']);   ++$cel;
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $data['groupname']); ++$cel;
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $data['productid']); ++$cel;
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $data['product_name']); ++$cel;
            $objPHPSpreadsheet->getActiveSheet()->SetCellValue($cel . $i, $data['product_price']); ++$cel;
        }

        $lastCol = 'F';
        for ($col = 'A'; $col != $lastCol; ++$col) {
            $objPHPSpreadsheet->getActiveSheet()->getColumnDimension($col)->setWidth(25);
        }
        $objPHPSpreadsheet->getActiveSheet()->getRowDimension(1)->setRowHeight(30);
        $objPHPSpreadsheet->getActiveSheet()
            ->getStyle('A1:E1')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF6a6a7a');

        $styleArray = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 9,
                'name'  => 'Verdana',
            ],
        ];
        $objPHPSpreadsheet->getActiveSheet()->getStyle('A1:E1')->applyFromArray($styleArray);

        $filename = 'CustomerGroupPriceUnico.xlsx';
        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $Writer = new Xlsx($objPHPSpreadsheet);
        $Writer->save('php://output');
        exit;
    }

    public function getAllGroups()
    {
        $customer_groups = Group::getGroups(Context::getContext()->language->id);
        $customer_grp = [];
        foreach ($customer_groups as $cust_grp) {
            $customer_grp[] = [
                'id'   => $cust_grp['id_group'],
                'name' => $cust_grp['name'],
            ];
        }
        return $customer_grp;
    }

    public function getGroup($id, $id_lang)
    {
        $group_info = new Group((int) $id, $id_lang);
        return $group_info->name;
    }
}
