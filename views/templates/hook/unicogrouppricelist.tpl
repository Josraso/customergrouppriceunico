{*
 * Template del hook displayAdminProductsExtra.
 * Muestra un campo de precio por cada grupo de cliente.
 * Compatible con Bootstrap 5 (PS9) y Bootstrap 4 (PS7/8).
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

<div class="unico-group-price-container">
  <h4 class="unico-group-price-title">{l s='Precio por grupo de cliente' d='Admin.Global'}</h4>

  {foreach from=$customer_groups item="group"}
    <div class="unico-group-row">
      <label class="unico-group-label">{$group.name|escape:'html':'UTF-8'}</label>
      <div class="input-group unico-product-price">
        <span class="input-group-text">{$currency_symbol|escape:'html':'UTF-8'}</span>
        <input
          type="number"
          step="0.000001"
          min="0"
          name="unico_group_price[{$group.id|escape:'html':'UTF-8'}][product_price]"
          value="{$group.product_price|escape:'html':'UTF-8'}"
          placeholder="0.000000"
          class="form-control"
        >
      </div>
    </div>
  {/foreach}
</div>

<style>
  .unico-group-price-container {
    padding: 1rem 0;
  }
  .unico-group-price-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 1rem;
  }
  .unico-group-row {
    margin-bottom: 1rem;
  }
  .unico-group-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.4rem;
  }
  .unico-product-price {
    max-width: 20rem;
  }
</style>
