{* modules/shipping_calculator/views/templates/admin/configure.tpl *}

<div class="panel">
  <h3>
    <i class="icon icon-truck"></i>
    {l s='Shipping Calculator – Importar tarifas' mod='shipping_calculator'}
  </h3>
  <p>
    {l s='Aquí puedes importar las tarifas de tus transportistas desde archivos CSV. Selecciona el carrier y el archivo correspondiente.' mod='shipping_calculator'}
  </p>
</div>

<div class="row">

  <div class="col-lg-6">
    <div class="panel">
      <h3>{l s='Importar tarifas por kg' mod='shipping_calculator'}</h3>

      <form method="post" enctype="multipart/form-data" action="{$currentIndex|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />

        <div class="form-group">
          <label for="per_kg_id_carrier">
            {l s='Transportista' mod='shipping_calculator'}
          </label>
          <select name="per_kg_id_carrier" id="per_kg_id_carrier" class="form-control">
            <option value="">
              -- {l s='Selecciona un transportista' mod='shipping_calculator'} --
            </option>
            {foreach from=$carriers item=carrier}
              <option value="{$carrier.id_carrier|intval}">
                {$carrier.name|escape:'html':'UTF-8'}
              </option>
            {/foreach}
          </select>
        </div>

        <div class="form-group">
          <label for="per_kg_csv">
            {l s='Archivo CSV' mod='shipping_calculator'}
          </label>
          <input type="file" name="per_kg_csv" id="per_kg_csv" class="form-control" />
          <p class="help-block">
            {l s='Formato: ID DESTINO, CIUDAD, DEPARTAMENTO, TARIFA x Kg, TIEMPOS DE ENTREGA, TRANSPORTADORA' mod='shipping_calculator'}
          </p>
        </div>

        <button type="submit" name="submitImportPerKgRates" class="btn btn-primary">
          <i class="icon-upload"></i>
          {l s='Importar tarifas por kg' mod='shipping_calculator'}
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="panel">
      <h3>{l s='Importar tarifas por rangos' mod='shipping_calculator'}</h3>

      <form method="post" enctype="multipart/form-data" action="{$currentIndex|escape:'html':'UTF-8'}">
        <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />

        <div class="form-group">
          <label for="range_id_carrier">
            {l s='Transportista' mod='shipping_calculator'}
          </label>
          <select name="range_id_carrier" id="range_id_carrier" class="form-control">
            <option value="">
              -- {l s='Selecciona un transportista' mod='shipping_calculator'} --
            </option>
            {foreach from=$carriers item=carrier}
              <option value="{$carrier.id_carrier|intval}">
                {$carrier.name|escape:'html':'UTF-8'}
              </option>
            {/foreach}
          </select>
        </div>

        <div class="form-group">
          <label for="range_csv">
            {l s='Archivo CSV' mod='shipping_calculator'}
          </label>
          <input type="file" name="range_csv" id="range_csv" class="form-control" />
          <p class="help-block">
            {l s='Formato: columnas de tarifas por rango (<=1, 2-3 kg, 1-60 kg, 61-120 kg, >120 kg), tiempos de entrega, paquetería, masivo, etc.' mod='shipping_calculator'}
          </p>
        </div>

        <button type="submit" name="submitImportRangeRates" class="btn btn-primary">
          <i class="icon-upload"></i>
          {l s='Importar tarifas por rangos' mod='shipping_calculator'}
        </button>
      </form>
    </div>
  </div>

</div>