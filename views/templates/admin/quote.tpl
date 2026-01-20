{* views/templates/admin/quote.tpl *}

{* Select2 CSS *}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  .select2-container {
    width: 100% !important;
  }
  .select2-container .select2-selection--single {
    height: 36px;
    padding: 5px;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 24px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 34px;
  }
</style>

<div class="panel">
  <h3><i class="icon-calculator"></i> Cotizador de Envíos</h3>

  <form method="post" action="{$currentIndex}&token={$token}" class="form-horizontal" id="quote-form">

    {* Selector de Pedido *}
    <div class="form-group">
      <label class="control-label col-lg-3">Cargar desde pedido</label>
      <div class="col-lg-7">
        <select id="order-select" class="form-control">
          <option value="">-- Seleccionar pedido (opcional) --</option>
          {if isset($orders)}
            {foreach $orders as $order}
              <option value="{$order.id_order}" 
                data-customer="{$order.customer_name}"
                data-reference="{$order.reference}">
                Pedido #{$order.id_order} - {$order.reference} - {$order.customer_name} - {$order.date_add}
              </option>
            {/foreach}
          {/if}
        </select>
        <p class="help-block">Selecciona un pedido para cargar automáticamente los productos, cantidades y destino</p>
      </div>
      <div class="col-lg-2">
        <button type="button" id="load-order" class="btn btn-info" disabled>
          <i class="icon-download"></i> Cargar
        </button>
      </div>
    </div>

    <hr style="margin: 20px 0;">

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
        <select name="id_city" id="city-select" class="form-control" required>
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

{if isset($quotes) || isset($grouped_packages) || isset($individual_grouped_packages) || isset($individual_non_grouped_items)}

  <div class="panel">
    <h3><i class="icon-list"></i> Resultados de cotización</h3>

    {* RESULTADOS CON PRODUCTOS AGRUPADOS E INDIVIDUALES *}
    {if isset($grouped_packages) || isset($individual_grouped_packages) || isset($individual_non_grouped_items)}
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

        {foreach $grouped_packages as $idx => $package}
          <div class="panel panel-default">
            <div class="panel-heading">
              <strong>Paquete agrupado {$idx + 1}</strong>
            </div>
            <div class="panel-body">
              {* Tabla de detalles de productos en el paquete *}
              {if $package.items_detail|@count > 0}
                <div class="well well-sm" style="margin-bottom:15px;">
                  <h5><i class="icon-list"></i> Productos en el paquete:</h5>
                  <div class="table-responsive">
                    <table class="table table-condensed table-bordered">
                      <thead>
                        <tr style="background-color:#f5f5f5;">
                          <th style="width:35%;">Producto</th>
                          <th style="width:12%;">Cantidad</th>
                          <th style="width:15%;">Peso real (kg)</th>
                          <th style="width:18%;">Peso volumétrico (kg)</th>
                          <th style="width:20%;">Total (kg)</th>
                        </tr>
                      </thead>
                      <tbody>
                        {foreach $package.items_detail as $item}
                          <tr>
                            <td><strong>{$item.name}</strong></td>
                            <td>{$item.units_in_package}</td>
                            <td>{$item.real_weight_unit|number_format:3:",":"."}</td>
                            <td>{$item.volumetric_weight_unit|number_format:3:",":"."}</td>
                            <td>
                              {assign var=maxWeight value=$item.real_weight_unit|max:$item.volumetric_weight_unit}
                              {($maxWeight * $item.units_in_package)|number_format:3:",":"."}
                            </td>
                          </tr>
                        {/foreach}
                      </tbody>
                    </table>
                  </div>
                </div>
              {/if}

              {* Mejor opción de transportadora *}
              {if $package.cheapest}
                <p style="margin-top:10px; font-size:14px;">
                  <strong>✓ Mejor opción: <span style="color:#2196F3;">{$package.cheapest.carrier}</span></strong> 
                  — <strong>$ {$package.cheapest.price|number_format:0:",":"."}</strong>
                </p>
              {else}
                <p class="text-muted">No se encontraron tarifas para este paquete.</p>
              {/if}

              {* Todas las opciones disponibles *}
              {if $package.quotes|@count > 0}
                <hr style="margin:10px 0;">
                <h5><i class="icon-info-circle"></i> Todas las opciones disponibles:</h5>
                <div class="table-responsive">
                  <table class="table table-condensed table-striped">
                    <thead>
                      <tr>
                        <th>Transportadora</th>
                        <th style="width:80px;">Tipo</th>
                        <th style="width:90px;">Peso Real</th>
                        <th style="width:90px;">Peso Vol.</th>
                        <th style="width:90px;">Flete</th>
                        <th style="width:80px;">Empaque</th>
                        <th style="width:80px;">Seguro</th>
                        <th style="width:100px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $package.quotes as $q}
                        <tr>
                          <td>{$q.carrier}</td>
                          <td>{if $q.type == 'per_kg'}Por Kg{else if $q.type == 'Por Kg'}Por Kg{else if $q.type == 'Por Rangos'}Por Rangos{else}Por Rangos{/if}</td>
                          <td>{if isset($q.peso_real)}{$q.peso_real|number_format:2:",":"."}{else}{$q.weight_real|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>{if isset($q.peso_volumetrico)}{$q.peso_volumetrico|number_format:2:",":"."}{else}{$q.weight_vol|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>$ {if isset($q.flete)}{$q.flete|number_format:0:",":"."}{else}{$q.shipping_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.empaque)}{$q.empaque|number_format:0:",":"."}{else}{$q.packaging_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.seguro)}{$q.seguro|number_format:0:",":"."}{else}{$q.insurance_cost|number_format:0:",":"."}{/if}</td>
                          <td><strong>$ {if isset($q.total)}{$q.total|number_format:0:",":"."}{else}{$q.price|number_format:0:",":"."}{/if}</strong></td>
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

      {* PRODUCTOS INDIVIDUALES AGRUPABLES (se agrupan consigo mismos) *}
      {if isset($individual_grouped_packages) && $individual_grouped_packages|@count > 0}
        <div class="alert alert-info">
          <h4><i class="icon-cubes"></i> Productos Individuales Agrupables</h4>
          <p>Productos que se agrupan con unidades del mismo producto</p>
        </div>

        {foreach $individual_grouped_packages as $package}
          <div class="panel panel-default">
            <div class="panel-heading">
              <strong>Paquete individual {$package@iteration}</strong>
              {if $package.units_in_package}
                — {$package.units_in_package} unidad(es)
              {/if}
            </div>
            <div class="panel-body">
              {if $package.cheapest}
                <p>✓ Mejor opción: <strong>{$package.cheapest.carrier}</strong> — <strong>$ {$package.cheapest.price|number_format:0:",":"."}</strong></p>
              {else}
                <p class="text-muted">No se encontraron tarifas para este paquete.</p>
              {/if}

              {if $package.quotes|@count > 0}
                <h5>Todas las opciones disponibles:</h5>
                <div class="table-responsive">
                  <table class="table table-condensed table-striped">
                    <thead>
                      <tr>
                        <th>Transportadora</th>
                        <th style="width:80px;">Tipo</th>
                        <th style="width:90px;">Peso Real</th>
                        <th style="width:90px;">Peso Vol.</th>
                        <th style="width:90px;">Flete</th>
                        <th style="width:80px;">Empaque</th>
                        <th style="width:80px;">Seguro</th>
                        <th style="width:100px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $package.quotes as $q}
                        <tr>
                          <td>{$q.carrier}</td>
                          <td>{if $q.type == 'per_kg'}Por Kg{else}Por Rangos{/if}</td>
                          <td>{if isset($q.peso_real)}{$q.peso_real|number_format:2:",":"."}{else}{$q.weight_real|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>{if isset($q.peso_volumetrico)}{$q.peso_volumetrico|number_format:2:",":"."}{else}{$q.weight_vol|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>$ {if isset($q.flete)}{$q.flete|number_format:0:",":"."}{else}{$q.shipping_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.empaque)}{$q.empaque|number_format:0:",":"."}{else}{$q.packaging_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.seguro)}{$q.seguro|number_format:0:",":"."}{else}{$q.insurance_cost|number_format:0:",":"."}{/if}</td>
                          <td><strong>$ {if isset($q.total)}{$q.total|number_format:0:",":"."}{else}{$q.price|number_format:0:",":"."}{/if}</strong></td>
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

      {* PRODUCTOS INDIVIDUALES NO AGRUPABLES (cada unidad por separado) *}
      {if isset($individual_non_grouped_items) && $individual_non_grouped_items|@count > 0}
        <div class="alert alert-warning">
          <h4><i class="icon-package"></i> Productos Individuales NO Agrupables</h4>
          <p>Productos cotizados individualmente (cada unidad por separado)</p>
        </div>

        {foreach $individual_non_grouped_items as $item}
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
                  <small>Peso real: <strong>{if isset($firstQuote.peso_real)}{$firstQuote.peso_real|number_format:2:",":"."}{else}{$firstQuote.weight_real|default:0|number_format:2:",":"."}{/if} kg</strong></small>
                  &nbsp;•&nbsp;
                  <small>Peso volumétrico: <strong>{if isset($firstQuote.peso_volumetrico)}{$firstQuote.peso_volumetrico|number_format:2:",":"."}{else}{$firstQuote.weight_vol|default:0|number_format:2:",":"."}{/if} kg</strong></small>
                  &nbsp;•&nbsp;
                  <small>Peso facturable: <strong>{if isset($firstQuote.peso_facturable)}{$firstQuote.peso_facturable|number_format:2:",":"."}{else}{$firstQuote.weight_billable|default:0|number_format:2:",":"."}{/if} kg</strong></small>
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
                        <th style="width:80px;">Tipo</th>
                        <th style="width:90px;">Peso Real</th>
                        <th style="width:90px;">Peso Vol.</th>
                        <th style="width:90px;">Flete</th>
                        <th style="width:80px;">Empaque</th>
                        <th style="width:80px;">Seguro</th>
                        <th style="width:100px;">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {foreach $item.quotes as $q}
                        <tr>
                          <td>{$q.carrier}</td>
                          <td>{if $q.type == 'per_kg'}Por Kg{else}Por Rangos{/if}</td>
                          <td>{if isset($q.peso_real)}{$q.peso_real|number_format:2:",":"."}{else}{$q.weight_real|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>{if isset($q.peso_volumetrico)}{$q.peso_volumetrico|number_format:2:",":"."}{else}{$q.weight_vol|default:0|number_format:2:",":"."}{/if} kg</td>
                          <td>$ {if isset($q.flete)}{$q.flete|number_format:0:",":"."}{else}{$q.shipping_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.empaque)}{$q.empaque|number_format:0:",":"."}{else}{$q.packaging_cost|number_format:0:",":"."}{/if}</td>
                          <td>$ {if isset($q.seguro)}{$q.seguro|number_format:0:",":"."}{else}{$q.insurance_cost|number_format:0:",":"."}{/if}</td>
                          <td><strong>$ {if isset($q.total)}{$q.total|number_format:0:",":"."}{else}{$q.price|number_format:0:",":"."}{/if}</strong></td>
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
      {if (isset($grouped_packages) && $grouped_packages|@count > 0) || (isset($individual_grouped_packages) && $individual_grouped_packages|@count > 0) || (isset($individual_non_grouped_items) && $individual_non_grouped_items|@count > 0)}
        <div class="panel-footer">
          <table class="table table-condensed" style="margin-bottom:0;">
            <tbody>
              {if isset($total_grouped) && $total_grouped > 0}
                <tr style="background-color:#f5f5f5;">
                  <td><strong>Total paquetes agrupados:</strong></td>
                  <td style="text-align:right;"><strong>$ {$total_grouped|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($total_individual_grouped) && $total_individual_grouped > 0}
                <tr style="background-color:#f5f5f5;">
                  <td><strong>Total productos individuales agrupables:</strong></td>
                  <td style="text-align:right;"><strong>$ {$total_individual_grouped|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($total_individual_non_grouped) && $total_individual_non_grouped > 0}
                <tr style="background-color:#f5f5f5;">
                  <td><strong>Total productos individuales NO agrupables:</strong></td>
                  <td style="text-align:right;"><strong>$ {$total_individual_non_grouped|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($subtotal)}
                <tr style="background-color:#e8f4f8; font-size:16px;">
                  <td><strong>SUBTOTAL ENVÍO:</strong></td>
                  <td style="text-align:right;"><strong style="color:#2196F3;">$ {$subtotal|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
              {if isset($total_with_tax)}
                <tr style="background-color:#d4e9f7; font-size:18px;">
                  <td><strong>TOTAL ENVÍO (IVA 19%):</strong></td>
                  <td style="text-align:right;"><strong style="color:#1976D2;">$ {$total_with_tax|number_format:0:",":"."}</strong></td>
                </tr>
              {/if}
            </tbody>
          </table>
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
      // Initialize Select2 on new select
      setTimeout(function() {
        $(sel).select2({
          placeholder: '-- Buscar producto --',
          allowClear: true,
          language: {
            noResults: function() { return 'No se encontraron productos'; },
            searching: function() { return 'Buscando...'; },
            inputTooShort: function() { return 'Escribe para buscar'; }
          }
        });
      }, 100);
    });

    document.addEventListener('click', function(e){
      if (e.target && e.target.classList.contains('remove-product')){
        var row = e.target.closest('.product-row');
        if (row) {
          // Destroy Select2 first
          var $select = $(row).find('.product-select');
          if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
          }
          row.parentNode.removeChild(row);
        }
      }
    });

    // populate existing selects on load
    var initialSelects = document.querySelectorAll('.product-select');
    for (var i=0;i<initialSelects.length;i++) populateSelects(initialSelects[i]);
    
    // Initialize Select2 on existing product selects
    setTimeout(function() {
      $('.product-select').each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
          $(this).select2({
            placeholder: '-- Buscar producto --',
            allowClear: true,
            language: {
              noResults: function() { return 'No se encontraron productos'; },
              searching: function() { return 'Buscando...'; },
              inputTooShort: function() { return 'Escribe para buscar'; }
            }
          });
        }
      });
    }, 100);
  })();
  
  // Initialize Select2 on city select
  $(document).ready(function() {
    $('#city-select').select2({
      placeholder: '-- Buscar ciudad --',
      allowClear: true,
      language: {
        noResults: function() { return 'No se encontró la ciudad'; },
        searching: function() { return 'Buscando...'; },
        inputTooShort: function() { return 'Escribe para buscar'; }
      }
    });
    
    // Initialize Select2 on order select
    $('#order-select').select2({
      placeholder: '-- Buscar pedido --',
      allowClear: true,
      language: {
        noResults: function() { return 'No se encontró el pedido'; },
        searching: function() { return 'Buscando...'; },
        inputTooShort: function() { return 'Escribe para buscar'; }
      }
    });
    
    // Enable/disable load button based on order selection
    $('#order-select').on('change', function() {
      var orderId = $(this).val();
      $('#load-order').prop('disabled', !orderId);
    });
    
    // Load order data when button is clicked
    $('#load-order').on('click', function() {
      var orderId = $('#order-select').val();
      if (!orderId) return;
      
      var $btn = $(this);
      $btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Cargando...');
      
      $.ajax({
        url: '{$currentIndex}&token={$token}',
        type: 'POST',
        dataType: 'json',
        data: {
          ajax: 1,
          action: 'getOrderData',
          id_order: orderId
        },
        success: function(response) {
          if (response.error) {
            alert('Error: ' + response.error);
            $btn.prop('disabled', false).html('<i class="icon-download"></i> Cargar');
            return;
          }
          
          if (response.success && response.data) {
            // Clear existing product rows
            $('#product-rows').empty();
            
            // Add products from order
            var products = response.data.products || [];
            products.forEach(function(product, index) {
              // Create new row
              var div = document.createElement('div');
              div.className = 'form-group product-row';
              div.setAttribute('data-index', index);
              div.innerHTML = 
                '<label class="control-label col-lg-3">Producto</label>' +
                '<div class="col-lg-5">' +
                  '<select name="products['+index+'][id_product]" class="form-control product-select">' +
                    '<option value="">-- Selecciona --</option>' +
                  '</select>' +
                '</div>' +
                '<div class="col-lg-2">' +
                  '<input type="number" name="products['+index+'][qty]" class="form-control" min="1" value="'+product.qty+'">' +
                '</div>' +
                '<div class="col-lg-2">' +
                  '<button type="button" class="btn btn-default remove-product">Eliminar</button>' +
                '</div>';
              
              $('#product-rows').append(div);
              
              // Populate select options
              var $select = $(div).find('select');
              var template = document.getElementById('product-options');
              if (template) {
                $select.html('<option value="">-- Selecciona --</option>' + template.innerHTML);
              }
              
              // Set selected product
              $select.val(product.id_product);
              
              // Initialize Select2
              $select.select2({
                placeholder: '-- Buscar producto --',
                allowClear: true,
                language: {
                  noResults: function() { return 'No se encontraron productos'; },
                  searching: function() { return 'Buscando...'; },
                  inputTooShort: function() { return 'Escribe para buscar'; }
                }
              });
            });
            
            // Set city if found
            if (response.data.id_city) {
              $('#city-select').val(response.data.id_city).trigger('change');
            } else {
              alert('Atención: La ciudad "' + (response.data.city_name || 'desconocida') + '" del pedido no se encontró en la base de datos. Por favor, selecciona la ciudad manualmente.');
            }
            
            // Update index counter
            window.productRowIndex = products.length;
            
            // Show success message
            showSuccessMessage('Datos del pedido cargados correctamente');
          }
          
          $btn.prop('disabled', false).html('<i class="icon-download"></i> Cargar');
        },
        error: function(xhr, status, error) {
          alert('Error al cargar datos del pedido: ' + error);
          $btn.prop('disabled', false).html('<i class="icon-download"></i> Cargar');
        }
      });
    });
    
    // Re-initialize Select2 when new product row is added
    $('#add-product').on('click', function() {
      setTimeout(function() {
        $('.product-select').each(function() {
          if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
              placeholder: '-- Buscar producto --',
              allowClear: true,
              language: {
                noResults: function() { return 'No se encontraron productos'; },
                searching: function() { return 'Buscando...'; },
                inputTooShort: function() { return 'Escribe para buscar'; }
              }
            });
          }
        });
      }, 100);
    });
    
    // Destroy Select2 when removing product row
    $(document).on('click', '.remove-product', function() {
      var $select = $(this).closest('.product-row').find('.product-select');
      if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
      }
    });
  });
  
  // Helper function to show success message
  function showSuccessMessage(message) {
    var $alert = $('<div class="alert alert-success" style="margin: 15px 0;">' +
      '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
      '<i class="icon-check"></i> ' + message +
      '</div>');
    
    $('#quote-form').prepend($alert);
    
    setTimeout(function() {
      $alert.fadeOut(function() {
        $(this).remove();
      });
    }, 3000);
  }
</script>

{* Select2 JS Library *}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
