{*
 * Template del hook displayAdminProductsExtra.
 * Muestra un campo de precio por cada grupo de cliente.
 * Sin campos de combinacion — precio unico por grupo.
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

<div class="panel">
  <div class="panel-heading">
    {l s='Precio por grupo de cliente' d='Admin.Global'}
  </div>

  <div class="form-group col-md-12">
    {foreach from=$customer_groups item="group"}
      <h4>{$group.name|escape:'html':'UTF-8'}</h4>
      <div class="row">
        <div class="col-xl-10 col-lg-6 form-group">
          <div class="input-group money-type unico-product-price">
            <div class="input-group-prepend">
              <span class="input-group-text">{$currency_symbol|escape:'html':'UTF-8'}</span>
            </div>
            <input
              type="number"
              step="0.000001"
              min="0"
              name="unico_group_price[{$group.id|escape:'html':'UTF-8'}][product_price]"
              value="{$group.product_price|escape:'html':'UTF-8'}"
              placeholder="{l s='Precio Final' d='Admin.Global'}"
              class="form-control"
            >
          </div>
        </div>
      </div>
    {/foreach}
  </div>
</div>

<style>
  .unico-product-price {
    max-width: 53rem !important;
  }
</style>
