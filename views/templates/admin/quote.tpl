{* views/templates/admin/quote.tpl *}

<div class="panel">
  <h3><i class="icon-calculator"></i> Cotizador de Envíos</h3>
  <p class="help-block">
    Selecciona el producto y la ciudad destino para calcular tarifas disponibles según cobertura.
  </p>

  <form method="post" action="{$currentIndex}&token={$token}" class="form-horizontal">
    
    <div class="form-group">
      <label class="control-label col-lg-3">Producto</label>
      <div class="col-lg-9">
        <select name="id_product" class="form-control" required>
          <option value="">-- Selecciona --</option>
          {foreach $products as $p}
            <option value="{$p.id_product}"
              {if isset($selected_product) && $selected_product.id_product == $p.id_product}selected{/if}>
              {$p.name}
            </option>
          {/foreach}
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">Cantidad</label>
      <div class="col-lg-3">
        <input type="number" name="qty" class="form-control" min="1"
               value="{if isset($selected_qty)}{$selected_qty}{else}1{/if}" required>
      </div>
      <div class="col-lg-6">
        <p class="help-block">Se usa para cálculo final (por ahora 1 = unidad).</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">Ciudad destino</label>
      <div class="col-lg-9">
        <select name="id_city" class="form-control" required>
          <option value="">-- Selecciona --</option>
          {foreach $cities as $c}
            <option value="{$c.id_city}"
              {if isset($selected_city) && $selected_city.id_city == $c.id_city}selected{/if}>
              {$c.name} ({$c.state})
            </option>
          {/foreach}
        </select>
      </div>
    </div>

    <div class="panel-footer">
      <button type="submit" name="submitQuote" class="btn btn-primary pull-right">
        <i class="icon-search"></i> Calcular Envío
      </button>
      <div class="clearfix"></div>
    </div>

  </form>
</div>


{* ==========================
   RESULTADOS
   ========================== *}
{if isset($quotes)}

  <div class="panel">
    <h3><i class="icon-list"></i> Resultados de cotización</h3>

    <div class="well well-sm">
      <div class="row">
        <div class="col-md-6">
          <strong>Producto:</strong>
          {if isset($selected_product)}{$selected_product.name}{else}-{/if}
        </div>
        <div class="col-md-2">
          <strong>Cantidad:</strong>
          {if isset($selected_qty)}{$selected_qty}{else}1{/if}
        </div>
        <div class="col-md-4">
          <strong>Ciudad destino:</strong>
          {if isset($selected_city)}{$selected_city.name} ({$selected_city.state}){else}-{/if}
        </div>
      </div>
    </div>

    {if $quotes|count > 0}
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>Transportadora</th>
              <th style="width:160px;">Tipo</th>
              <th style="width:180px;">Precio</th>
            </tr>
          </thead>
          <tbody>
            {foreach $quotes as $q}
              <tr>
                <td>
                  <strong>{$q.carrier}</strong>
                </td>
                <td>
                  {if $q.type == 'per_kg'}
                    <span class="badge badge-info">Por Kg</span>
                  {else}
                    <span class="badge badge-success">Por Rangos</span>
                  {/if}
                </td>
                <td>
                  <strong>$ {$q.price|number_format:0:",":"."}</strong>
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <div class="alert alert-warning">
        No se encontraron tarifas disponibles para este producto y ciudad.
      </div>
    {/if}

  </div>
{/if}
