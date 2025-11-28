{* views/templates/admin/quote.tpl *}

<div class="panel">
  <h3><i class="icon-calculator"></i> Cotizador de Envíos</h3>
  <p class="help-block">
    Selecciona uno o varios productos y la ciudad destino para calcular tarifas disponibles según cobertura.
  </p>

  <form method="post" action="{$currentIndex}&token={$token}" class="form-horizontal" id="quote-form">

    <div id="product-rows">
      <div class="form-group product-row" data-index="0">
        <label class="control-label col-lg-3">Producto</label>
        <div class="col-lg-5">
          <select name="products[0][id_product]" class="form-control product-select" required>
            <option value="">-- Selecciona --</option>
            {foreach $products as $p}
              <option value="{$p.id_product}">{$p.name}</option>
            {/foreach}
          </select>
        </div>
        <div class="col-lg-2">
          <input type="number" name="products[0][qty]" class="form-control" min="1" value="1" required>
        </div>
        <div class="col-lg-2">
          <button type="button" class="btn btn-default remove-product">Eliminar</button>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="col-lg-9 col-lg-offset-3">
        <button type="button" id="add-product" class="btn btn-secondary">Agregar producto</button>
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

{if isset($quotes) || isset($grouped_packages) || isset($individual_items)}

  <div class="panel">
    <h3><i class="icon-list"></i> Resultados de cotización</h3>

    {* RESULTADOS CON PRODUCTOS AGRUPADOS E INDIVIDUALES *}
    {if isset($grouped_packages) || isset($individual_items)}
      {if isset($selected_city)}
        <div class="well well-sm">
          <div class="row">
            <div class="col-md-12">
              <strong>Ciudad destino:</strong>
              {$selected_city.name} ({$selected_city.state})
            </div>
          </div>
        </div>
      {/if}

      {* PAQUETES AGRUPADOS *}
      {if isset($grouped_packages) && $grouped_packages|@count > 0}
        <div class="alert alert-info">
          <h4><i class="icon-cube"></i> Paquetes Agrupados</h4>
          <p>Productos mezclados en paquetes de máximo 60 kg</p>
        </div>

        {foreach $grouped_packages as $package}
          <div class="panel panel-default">
            <div class="panel-heading">
              <strong>Paquete {$package.package_id}</strong> — Peso total: {$package.total_weight|number_format:2:",":"."} kg
              <div style="margin-top:8px;">
                <small><strong>Contiene:</strong> {$package.items_summary}</small>
              </div>
            </div>
            <div class="panel-body">
              {if $package.cheapest}
                <p>Mejor opción: <strong>{$package.cheapest.carrier}</strong> — <strong>$ {$package.cheapest.price|number_format:0:",":"."}</strong></p>
              {else}
                <p class="text-muted">No se encontraron tarifas para este paquete.</p>
              {/if}

              {if $package.quotes|@count > 0}
                <div class="table-responsive">
                  <table class="table table-condensed table-striped">
                    <thead>
                      <tr>
                        <th>Transportadora</th>
                        <th>Tipo</th>
                        <th>Precio</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $package.quotes as $q}
                        <tr>
                          <td>{$q.carrier}</td>
                          <td>{if $q.type == 'per_kg'}Por Kg{else}Por Rangos{/if}</td>
                          <td>$ {$q.price|number_format:0:",":"."}</td>
                        </tr>
                      {/foreach}
                    </tbody>
                  </table>
                </div>
              {/if}
            </div>
          </div>
        {/foreach}
      {/if}

      {* PRODUCTOS INDIVIDUALES *}
      {if isset($individual_items) && $individual_items|@count > 0}
        <div class="alert alert-warning">
          <h4><i class="icon-package"></i> Productos Individuales</h4>
          <p>Productos cotizados individualmente (no agrupables)</p>
        </div>

        {foreach $individual_items as $item}
          <div class="panel panel-default">
            <div class="panel-heading">
              <strong>{$item.name}</strong> — Cantidad: {$item.qty}
              {if $item.reason}
                {if $item.reason == 'from_grouped_exceeds_max'}
                  <span class="label label-danger">Peso excede paquete agrupado</span>
                {/if}
              {/if}
              {if $item.quotes|@count > 0}
                {assign var=firstQuote value=$item.quotes[0]}
                <div style="margin-top:8px;">
                  <small>Peso real: <strong>{$firstQuote.weight_real|default:0|number_format:3:",":"."} kg</strong></small>
                  &nbsp;•&nbsp;
                  <small>Peso volumétrico: <strong>{$firstQuote.weight_vol|default:0|number_format:3:",":"."} kg</strong></small>
                </div>
              {/if}
            </div>
            <div class="panel-body">
              {if $item.cheapest}
                <p>Mejor opción: <strong>{$item.cheapest.carrier}</strong> — <strong>$ {$item.cheapest.price|number_format:0:",":"."}</strong></p>
              {else}
                <p class="text-muted">No se encontraron tarifas para este producto.</p>
              {/if}

              {if $item.quotes|@count > 0}
                <div class="table-responsive">
                  <table class="table table-condensed table-striped">
                    <thead>
                      <tr>
                        <th>Transportadora</th>
                        <th>Tipo</th>
                        <th style="width:140px;">Peso real (kg)</th>
                        <th style="width:160px;">Peso volumétrico (kg)</th>
                        <th>Precio</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $item.quotes as $q}
                        <tr>
                          <td>{$q.carrier}</td>
                          <td>{if $q.type == 'per_kg'}Por Kg{else}Por Rangos{/if}</td>
                          <td>{$q.weight_real|default:0|number_format:3:",":"."}</td>
                          <td>{$q.weight_vol|default:0|number_format:3:",":"."}</td>
                          <td>$ {$q.price|number_format:0:",":"."}</td>
                        </tr>
                      {/foreach}
                    </tbody>
                  </table>
                </div>
              {/if}
            </div>
          </div>
        {/foreach}
      {/if}

      {* TOTALES FINALES *}
      {if (isset($grouped_packages) && $grouped_packages|@count > 0) || (isset($individual_items) && $individual_items|@count > 0)}
        <div class="panel-footer">
          <table class="table table-condensed" style="margin-bottom:0;">
            <tbody>
              {if isset($total_grouped) && $total_grouped > 0}
                <tr style="background-color:#f5f5f5;">
                  <td><strong>Total paquetes agrupados:</strong></td>
                  <td style="text-align:right;"><strong>$ {$total_grouped|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($total_individual) && $total_individual > 0}
                <tr style="background-color:#f5f5f5;">
                  <td><strong>Total productos individuales:</strong></td>
                  <td style="text-align:right;"><strong>$ {$total_individual|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($grand_total)}
                <tr style="background-color:#e8f4f8; font-size:16px;">
                  <td><strong>TOTAL ENVÍO:</strong></td>
                  <td style="text-align:right;"><strong style="color:#2196F3;">$ {$grand_total|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>
      {/if}
    {/if}

    {* MODO LEGACY (single-product) *}
    {if isset($quotes)}

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
                <th style="width:140px;">Peso real (kg)</th>
                <th style="width:160px;">Peso volumétrico (kg)</th>
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
                      {$q.weight_real|default:0|number_format:3:",":"."}
                    </td>
                    <td>
                      {$q.weight_vol|default:0|number_format:3:",":"."}
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

    {/if}

  </div>
{/if}

<!-- hidden options template to populate new selects -->
<div id="product-options" style="display:none;">
  {foreach $products as $p}
    <option value="{$p.id_product}">{$p.name}</option>
  {/foreach}
</div>

<script>
  (function(){
    var container = document.getElementById('product-rows');
    var addBtn = document.getElementById('add-product');
    var idx = document.querySelectorAll('.product-row').length;

    function makeRow(index){
      var div = document.createElement('div');
      div.className = 'form-group product-row';
      div.setAttribute('data-index', index);
      div.innerHTML = '\n        <label class="control-label col-lg-3">Producto</label>\n        <div class="col-lg-5">\n          <select name="products['+index+'][id_product]" class="form-control product-select">\n            <option value="">-- Selecciona --</option>\n          </select>\n        </div>\n        <div class="col-lg-2">\n          <input type="number" name="products['+index+'][qty]" class="form-control" min="1" value="1">\n        </div>\n        <div class="col-lg-2">\n          <button type="button" class="btn btn-default remove-product">Eliminar</button>\n        </div>';
      return div;
    }

    function populateSelects(sel){
      var template = document.getElementById('product-options');
      if (template) sel.innerHTML = '<option value="">-- Selecciona --</option>' + template.innerHTML;
    }

    addBtn.addEventListener('click', function(e){
      var row = makeRow(idx++);
      container.appendChild(row);
      var sel = row.querySelector('select');
      populateSelects(sel);
    });

    document.addEventListener('click', function(e){
      if (e.target && e.target.classList.contains('remove-product')){
        var row = e.target.closest('.product-row');
        if (row) row.parentNode.removeChild(row);
      }
    });

    // populate existing selects on load
    var initialSelects = document.querySelectorAll('.product-select');
    for (var i=0;i<initialSelects.length;i++) populateSelects(initialSelects[i]);
  })();
</script>
