{*
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}

<div class="panel">
  <form action="" id="cgrppriceunico" method="post" class="form-horizontal" enctype="multipart/form-data">
    <div class="form-group">
      <label class="control-label col-lg-2">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true"
          title="{l s='Activar o desactivar Customer Group Price Unico' d='Admin.Global'}">
          {l s='Status' d='Admin.Global'}
        </span>
      </label>
      <div class="col-lg-6">
        <span class="switch prestashop-switch fixed-width-lg">
          {if $enable|escape:'html':'UTF-8'}
            <input type="radio" name="cgrppriceunico_enable" id="enable_on" value="1" checked="checked">
            <label for="enable_on">{l s='Enable' d='Admin.Global'}</label>
            <input type="radio" name="cgrppriceunico_enable" id="enable_off" value="0">
            <label for="enable_off">{l s='Disable' d='Admin.Global'}</label>
          {else}
            <input type="radio" name="cgrppriceunico_enable" id="enable_on" value="1">
            <label for="enable_on">{l s='Enable' d='Admin.Global'}</label>
            <input type="radio" name="cgrppriceunico_enable" id="enable_off" value="0" checked="checked">
            <label for="enable_off">{l s='Disable' d='Admin.Global'}</label>
          {/if}
          <a class="slide-button btn"></a>
        </span>
      </div>
    </div>

    <div class="panel-footer">
      <button class="btn btn-default pull-right" type="submit" name="submitCgrppriceunico">
        <i class="process-icon-save"></i>{l s='Save' d='Admin.Global'}
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
