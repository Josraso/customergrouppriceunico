{*
 * Template del hook displayAdminProductsExtra.
 * Sin dependencia de Bootstrap — estilos propios para maxima compatibilidad PS7/8/9.
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

<style>
.cgpu-wrap { padding: 12px 0; }
.cgpu-wrap h4 { font-size: 14px; font-weight: 700; margin: 0 0 12px; }
.cgpu-row { margin-bottom: 14px; }
.cgpu-row label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; }
.cgpu-field { display: flex; align-items: stretch; width: 220px; border: 1px solid #ced4da; border-radius: 4px; overflow: hidden; }
.cgpu-field .cgpu-symbol { display: flex; align-items: center; padding: 0 10px; background: #e9ecef; color: #495057; font-size: 14px; border-right: 1px solid #ced4da; white-space: nowrap; }
.cgpu-field input { flex: 1; min-width: 0; padding: 7px 10px; border: none; outline: none; font-size: 14px; background: #fff; color: #212529; }
.cgpu-field input:focus { background: #fff; box-shadow: 0 0 0 2px rgba(0,123,255,.25); }
</style>

<div class="cgpu-wrap">
  <h4>{l s='Precio por grupo de cliente' d='Admin.Global'}</h4>

  {foreach from=$customer_groups item="group"}
    <div class="cgpu-row">
      <label>{$group.name|escape:'html':'UTF-8'}</label>
      <div class="cgpu-field">
        <span class="cgpu-symbol">{$currency_symbol|escape:'html':'UTF-8'}</span>
        <input
          type="number"
          step="0.000001"
          min="0"
          name="unico_group_price[{$group.id|escape:'html':'UTF-8'}][product_price]"
          value="{$group.product_price|escape:'html':'UTF-8'}"
          placeholder="0.00"
        >
      </div>
    </div>
  {/foreach}
</div>
