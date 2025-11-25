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

{if isset($quotes) || isset($quotes_multi)}

  <div class="panel">
    <h3><i class="icon-list"></i> Resultados de cotización</h3>

    {if isset($quotes_multi)}
      <div class="well well-sm">
        <div class="row">
          <div class="col-md-12">
            <strong>Ciudad destino:</strong>
            {if isset($selected_city)}{$selected_city.name} ({$selected_city.state}){else}-{/if}
          </div>
        </div>
      </div>

      {foreach $quotes_multi as $item}
        <div class="panel panel-default">
          <div class="panel-heading">
            <strong>{$item.name}</strong> — Cantidad: {$item.qty}
            {if $item.is_grouped}
              <span class="label label-warning">Agrupado (no se cotiza)</span>
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
                      <th>Precio</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach $item.quotes as $q}
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

      <div class="panel-footer">
        <h4>Total envío (sumando la opción más barata por producto): <strong>$ {$quotes_total|number_format:0:",":"."}</strong></h4>
      </div>
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
