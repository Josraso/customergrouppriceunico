<?php
/**
 * Controlador del tab principal del módulo.
 * Redirige a la pagina de configuracion del modulo.
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class CustomergrouppriceunicoController extends AdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=customergrouppriceunico&token=' . Tools::getAdminTokenLite('AdminModules')
        );
    }
}
