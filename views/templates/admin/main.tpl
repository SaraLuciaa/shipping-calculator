<div class="row">
  <div class="col-md-2">
    <div class="list-group">

      <a href="#"
         class="list-group-item {if $active_panel=='panel-carriers'}active{/if}"
         data-panel="panel-carriers">
        <i class="icon-truck"></i> Registrar Transportista
      </a>

      <a href="#"
         class="list-group-item {if $active_panel=='panel-import'}active{/if}"
         data-panel="panel-import">
        <i class="icon-upload"></i> Importar Tarifas
      </a>

      <a href="#"
         class="list-group-item {if $active_panel=='panel-quote'}active{/if}"
         data-panel="panel-quote">
        <i class="icon-calculator"></i> Cotizador de Envíos
      </a>

      <a href="#"
         class="list-group-item {if $active_panel=='panel-config'}active{/if}"
         data-panel="panel-config">
        <i class="icon-cogs"></i> Configuración
      </a>

      <a href="#"
         class="list-group-item {if $active_panel=='panel-help'}active{/if}"
         data-panel="panel-help">
        <i class="icon-info-circle"></i> Ayuda
      </a>

    </div>
  </div>

  <div class="col-md-9">

    <!-- PANEL: IMPORTAR TARIFAS -->
    <div id="panel-import"
         class="panel panel-default panel-body"
         style="{if $active_panel=='panel-import'}display:block;{else}display:none;{/if}">
      {include file="./import_rates.tpl"}
    </div>

    <!-- PANEL: REGISTRAR TRANSPORTISTA -->
    <div id="panel-carriers"
         class="panel panel-default panel-body"
         style="{if $active_panel=='panel-carriers'}display:block;{else}display:none;{/if}">
      {include file="./register_carrier.tpl"}
    </div>

    <!-- PANEL: COTIZADOR -->
    <div id="panel-quote"
         class="panel panel-default panel-body"
         style="{if $active_panel=='panel-quote'}display:block;{else}display:none;{/if}">
      {include file="./quote.tpl"}
    </div>

    <!-- PANEL: CONFIGURACIÓN -->
    <div id="panel-config"
         class="panel panel-default panel-body"
         style="{if $active_panel=='panel-config'}display:block;{else}display:none;{/if}">
      {include file="./configure.tpl"}
    </div>

    <!-- PANEL: AYUDA -->
    <div id="panel-help"
         class="panel panel-default panel-body"
         style="{if $active_panel=='panel-help'}display:block;{else}display:none;{/if}">
      {include file="./help.tpl"}
    </div>

  </div>
</div>


<script>
// === Control de pestañas en tiempo real (clic del usuario) ===
document.querySelectorAll('.list-group-item').forEach(item => {
  item.addEventListener('click', function(e) {
    e.preventDefault();

    // Quitar activo del menú
    document.querySelectorAll('.list-group-item').forEach(i => i.classList.remove('active'));
    this.classList.add('active');

    // Panel a mostrar
    let target = this.getAttribute('data-panel');

    // Ocultar todos los paneles
    document.querySelectorAll('.panel.panel-default.panel-body').forEach(p => {
      p.style.display = 'none';
    });

    // Mostrar panel seleccionado
    document.getElementById(target).style.display = 'block';
  });
});
</script>