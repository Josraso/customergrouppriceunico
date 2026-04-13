{*
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

{if $enable == 1}
<div class="panel">
  <form action="" id="unicoexport" method="post" class="form-horizontal" enctype="multipart/form-data">

    <div class="form-group row">
      <label class="control-label col-lg-2">{l s='Customer Group' d='Admin.Global'}</label>
      <div class="col-lg-6">
        <select name="group" class="form-control">
          <option value="allgroups">{l s='All Customer Groups' d='Admin.Global'}</option>
          {foreach from=$all_customer item='custmr'}
            <option value="{$custmr.id|escape:'html':'UTF-8'}">{$custmr.name|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-2">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="Product">
          {l s='Products' d='Admin.Global'}
        </span>
      </label>
      <div class="col-lg-6">
        <select name="product_ids[]" class="chosen form-control" multiple>
          {foreach from=$products item='result'}
            <option value="{$result.id_product|escape:'html':'UTF-8'}">{$result.name|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
      </div>
    </div>

    <div class="form-group row">
      <label class="control-label col-lg-2">{l s='Limit' d='Admin.Global'}</label>
      <div class="col-lg-4">
        <div class="input-group">
          <span class="input-group-addon">{l s='From' d='Admin.Global'}</span>
          <input type="number" name="limit_from" value="" class="form-control" placeholder="{l s='From' d='Admin.Global'}"/>
          <span class="input-group-addon">{l s='To' d='Admin.Global'}</span>
          <input type="number" name="limit_to" value="" class="form-control" placeholder="{l s='To' d='Admin.Global'}"/>
        </div>
      </div>
    </div>

    <div class="panel-footer">
      <button class="btn btn-default pull-right" type="submit" name="submitUnicoproductexport">
        <i class="icon-download" style="font-size: 20px; padding-right: 5px; vertical-align: middle;"></i>
        {l s='Export' d='Admin.Global'}
      </button>
    </div>

  </form>
</div>

{literal}
<script>
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
</script>
{/literal}

{else}
  <div class="alert alert-info">
    <b>{l s='Please Set Status To True In Customer Group Price Unico Settings.' d='Admin.Global'}</b>
    <ul style="list-style-type: disc">
      <li>{l s='To Enable Unico Export Click On Configure Button Display on Top Right.' d='Admin.Global'}</li>
    </ul>
  </div>
{/if}
