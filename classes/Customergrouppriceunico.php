<?php
/**
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class Customergrouppriceunicomodel extends ObjectModel
{
    public $id;

    /** @var string Object creation date */
    public $date_add;

    /** @var string Object update date */
    public $date_upd;

    public static $definition = [
        'table'   => 'customergrouppriceunico',
        'primary' => 'id_group_price',
        'fields'  => [
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
        ],
    ];

    protected $webserviceParameters = [
        'objectsNodeName' => 'customergrouppriceunico',
        'fields'          => [
            'status' => [],
        ],
    ];

    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id, $idLang);
    }
}
