<form method="post" action="{$currentIndex}&token={$token}">
  
  <div class="form-group">
    <label>Transportista</label>
    <select name="id_carrier" class="form-control">
      <option value="">-- Selecciona --</option>
      {foreach $carriers_all as $carrier}
        <option value="{$carrier.id_carrier}">{$carrier.name}</option>
      {/foreach}
    </select>
  </div>

  <div class="form-group">
    <label>Tipo de tarifa</label>
    <select name="rate_type" class="form-control">
      <option value="per_kg">Por Kilogramo</option>
      <option value="range">Por Rangos</option>
    </select>
  </div>

  <button type="submit" name="submitRegisterCarrier" class="btn btn-primary">
    Registrar Transportista
  </button>

</form>