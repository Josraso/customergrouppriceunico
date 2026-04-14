{*
 * Template del hook displayAdminProductsExtra.
 *
 * PS9: raw_purified (HTMLPurifier) elimina <input>, <script> y <style>.
 * Se usan divs de datos puros; product_admin.js los convierte en campos
 * editables en tiempo de ejecucion.
 *
 * PS7/8: el JS tambien funciona — lee los divs y construye los inputs.
 *
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
<div class="cgpu-wrap">
  <div class="cgpu-cur">{$currency_symbol|escape:'html':'UTF-8'}</div>
  {foreach from=$customer_groups item="group"}
    <div class="cgpu-group">
      <div class="cgpu-gid">{$group.id|escape:'html':'UTF-8'}</div>
      <div class="cgpu-gname">{$group.name|escape:'html':'UTF-8'}</div>
      <div class="cgpu-gval">{$group.product_price|escape:'html':'UTF-8'}</div>
    </div>
  {/foreach}
</div>
