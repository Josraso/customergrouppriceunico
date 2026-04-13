<?php
/**
 * Controlador de importacion Excel.
 * Formato de columnas:
 *   A: Id Grupo  |  B: Nombre Grupo (ignorado)  |  C: Id Producto  |  D: Nombre Producto (ignorado)  |  E: Precio
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
require_once _PS_MODULE_DIR_ . 'customergrouppriceunico/classes/Customergrouppriceunico.php';

class UnicoimportController extends AdminController
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
        $enable = (int) Configuration::get('CGRPPRICEUNICO_ENABLE');

        if (Tools::isSubmit('submitUnicoproductimport')) {
            $this->import();
        }

        $this->context->smarty->assign(['enable' => $enable]);

        $this->content .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'customergrouppriceunico/views/templates/admin/unicoimport.tpl'
        );

        $this->context->smarty->assign(['content' => $this->content]);
    }

    public function import()
    {
        if (empty($_FILES['import']['name'])) {
            $this->errors[] = $this->trans('Please import valid excel file!', [], 'Shop.Notifications.Error');
            return;
        }

        $filename = $_FILES['import']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $this->errors[] = $this->trans('Please import valid excel file!', [], 'Shop.Notifications.Error');
            return;
        }

        if ($this->errors) {
            return;
        }

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

        if (!is_readable($_FILES['import']['tmp_name'])) {
            $this->errors[] = $this->trans('Please import valid excel file!', [], 'Shop.Notifications.Error');
            return;
        }

        $reader_excel = $reader->load($_FILES['import']['tmp_name']);
        $excel_file   = $reader_excel->getActiveSheet()->toArray(null, true, true, true);

        $data = [];
        $i = 0;
        foreach ($excel_file as $sheetData) {
            if ($i !== 0) {
                $id_group      = $sheetData['A'];
                $id_product    = $sheetData['C'];
                $product_price = $sheetData['E'];

                if (!empty($id_group) && !empty($id_product)) {
                    $data[] = [
                        'id_group'      => (int) $id_group,
                        'id_product'    => (int) $id_product,
                        'product_price' => $product_price,
                    ];
                }
            }
            ++$i;
        }

        if (!empty($data)) {
            $date = date('Y-m-d');
            foreach ($data as $row) {
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'customergrouppriceunico` WHERE
                    `id_product` = ' . (int) $row['id_product'] . ' AND `group_id` = ' . (int) $row['id_group']
                );

                $product_price = !empty($row['product_price']) ? (float) $row['product_price'] : 0;

                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'customergrouppriceunico` SET
                    `id_product`        = ' . (int) $row['id_product'] . ',
                    `product_price`     = \'' . (float) $product_price . '\',
                    `group_id`          = ' . (int) $row['id_group'] . ',
                    `id_specific_price` = NULL,
                    `date_add`          = \'' . pSQL($date) . '\',
                    `date_upd`          = \'' . pSQL($date) . '\''
                );
            }

            // Resincronizar SpecificPrices para cada producto importado
            $module = Module::getInstanceByName('customergrouppriceunico');
            if ($module) {
                $imported_products = array_unique(array_column($data, 'id_product'));
                foreach ($imported_products as $id_product) {
                    $module->syncSpecificPricesForProduct((int) $id_product);
                }
            }
        }

        $this->confirmations[] = $this->trans('File Import Successfully');
    }
}
