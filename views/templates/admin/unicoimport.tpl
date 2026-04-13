{*
 * @author    TMD
 * @copyright 2007-2024 TMD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *
 * Formato esperado del Excel (.xls):
 *   Columna A: Id Grupo
 *   Columna B: Nombre Grupo  (informativo, no se usa)
 *   Columna C: Id Producto
 *   Columna D: Nombre Producto (informativo, no se usa)
 *   Columna E: Precio del grupo
 *}

{if $enable == 1}
<div class="panel">
  <div class="alert alert-info">
    <strong>{l s='Formato del fichero Excel:' d='Admin.Global'}</strong>
    <ul style="list-style-type: disc; margin-top: 5px;">
      <li>{l s='Columna A: Id Grupo' d='Admin.Global'}</li>
      <li>{l s='Columna B: Nombre Grupo (informativo)' d='Admin.Global'}</li>
      <li>{l s='Columna C: Id Producto' d='Admin.Global'}</li>
      <li>{l s='Columna D: Nombre Producto (informativo)' d='Admin.Global'}</li>
      <li>{l s='Columna E: Precio Final del Grupo' d='Admin.Global'}</li>
    </ul>
  </div>

  <form action="" id="unicoimport" method="post" class="form-horizontal" enctype="multipart/form-data">
    <div class="form-group">
      <label class="control-label col-lg-2">
        <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="Import">
          {l s='Import' d='Admin.Global'}
        </span>
      </label>
      <div class="col-lg-5">
        <input type="file" name="import" class="form-control">
      </div>
      <div class="col-lg-2">
        <button class="btn btn-default" type="submit" name="submitUnicoproductimport">
          <i class="icon-upload" style="font-size: 20px; padding-right: 5px; vertical-align: middle;"></i>
          {l s='Import' d='Admin.Global'}
        </button>
      </div>
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
      <li>{l s='To Enable Unico Import Click On Configure Button Display on Top Right.' d='Admin.Global'}</li>
    </ul>
  </div>
{/if}
