<form method="post" enctype="multipart/form-data" action="{$currentIndex}&token={$token}">
  
  <div class="form-group">
    <label>Transportista</label>
    <select name="id_carrier" class="form-control">
      <option value="">-- Selecciona --</option>
      {foreach from=$registered_carriers item=carrier}
        <option value="{$carrier.id_carrier}">
          {$carrier.name}
        </option>
      {/foreach}
    </select>
  </div>

  <div class="form-group">
    <label>Archivo CSV</label>
    <input type="file" name="rates_csv" class="form-control"/>
  </div>

  <button type="submit" name="submitImportRates" class="btn btn-primary">
    Importar tarifas
  </button>

</form>