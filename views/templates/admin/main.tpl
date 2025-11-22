<div class="row">
  <div class="col-md-2">
    <div class="list-group">

      <a href="#" class="list-group-item active" data-panel="panel-carriers">
        <i class="icon-truck"></i> Registrar Transportista
      </a>
      
      <a href="#" class="list-group-item" data-panel="panel-import">
        <i class="icon-upload"></i> Importar Tarifas
      </a>

      <a href="#" class="list-group-item" data-panel="panel-help">
        <i class="icon-info-circle"></i> Ayuda
      </a>

    </div>
  </div>

  <div class="col-md-9">

    <div id="panel-import" class="panel panel-default panel-body active-panel">
      {include file="./import_rates.tpl"}
    </div>

    <div id="panel-carriers" class="panel panel-default panel-body" style="display:none;">
      {include file="./register_carrier.tpl"}
    </div>

    <div id="panel-help" class="panel panel-default panel-body" style="display:none;">
      {include file="./help.tpl"}
    </div>

  </div>
</div>

<script>
document.querySelectorAll('.list-group-item').forEach(item => {
  item.addEventListener('click', function(e) {
    e.preventDefault();

    // cambiar activo del menú
    document.querySelectorAll('.list-group-item').forEach(i => i.classList.remove('active'));
    this.classList.add('active');

    let target = this.getAttribute('data-panel');

    // ocultar todos
    document.querySelectorAll('.panel.panel-default.panel-body').forEach(p => {
      p.style.display = 'none';
      p.classList.remove('active-panel');
    });

    // mostrar seleccionado
    document.getElementById(target).style.display = 'block';
    document.getElementById(target).classList.add('active-panel');
  });
});
</script>