{*
* Shipping Calculator - Configuración
* Panel completo de configuración del módulo
*}

<h3><i class="icon-cogs"></i> Configuración</h3>
<hr>

{* ============================================
   1. CONFIGURACIÓN GLOBAL - EMPAQUE Y PESO MÁXIMO
   ============================================ *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-globe"></i> Configuración Global
    </div>
    <div class="panel-body">
        <p class="text-muted">
            <i class="icon-info-circle"></i> 
            Configuración general aplicable a todos los cálculos de envío.
        </p>
        
        <form method="post" action="{$currentIndex}&token={$token}">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Porcentaje de Empaque (%):</label>
                        <input type="number" 
                               name="packaging_percent" 
                               class="form-control" 
                               value="{$global_packaging|string_format:"%.2f"}"
                               step="0.01"
                               min="0"
                               required>
                        <small class="help-block">
                            Se aplica sobre el costo base del envío.
                        </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Peso Máximo por Paquete (kg):</label>
                        <input type="number" 
                               name="max_package_weight" 
                               class="form-control" 
                               value="{$max_package_weight|string_format:"%.2f"}"
                               step="0.01"
                               min="1"
                               required>
                        <small class="help-block">
                            Límite de peso para productos agrupados en un solo paquete.
                        </small>
                    </div>
                </div>
            </div>            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Porcentaje de IVA (%):</label>
                        <input type="number" 
                               name="vat_percent" 
                               class="form-control" 
                               value="{$vat_percent|string_format:"%.2f"}"
                               step="0.01"
                               min="0"
                               max="100"
                               required>
                        <small class="help-block">
                            IVA aplicado al costo de envío en el checkout (por defecto 19%).
                        </small>
                    </div>
                </div>
                
            </div>
            <button type="submit" name="submitGlobalConfig" class="btn btn-primary">
                <i class="icon-save"></i> Guardar Configuración
            </button>
        </form>
    </div>
</div>

<hr>

{* ============================================
   2. PESO VOLUMÉTRICO POR TRANSPORTADORA
   ============================================ *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cube"></i> Factor de Peso Volumétrico por Transportadora
    </div>
    <div class="panel-body">
        <p class="text-muted">
            <i class="icon-info-circle"></i> 
            El factor volumétrico se usa para calcular: Peso Vol = (Largo × Ancho × Alto) / Factor
        </p>

        {* Tabla de factores volumétricos actuales *}
        {if $carrier_configs && count($carrier_configs) > 0}
            <h4>Factores Volumétricos Configurados:</h4>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Transportadora</th>
                        <th>Tipo</th>
                        <th>Factor Volumétrico</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$carrier_configs item=config}
                        <tr>
                            <td><strong>{$config.carrier.name}</strong></td>
                            <td>
                                <span class="badge badge-{if $config.type == 'per_kg'}info{else}success{/if}">
                                    {if $config.type == 'per_kg'}Por KG{else}Por Rango{/if}
                                </span>
                            </td>
                            <td>
                                {if $config.volumetric_factor}
                                    {$config.volumetric_factor|string_format:"%.0f"}
                                {else}
                                    <span class="text-muted">No configurado</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}

        {* Formulario para agregar/actualizar factor volumétrico *}
        <hr>
        <h4>Configurar Factor Volumétrico:</h4>
        <form method="post" action="{$currentIndex}&token={$token}" class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-3 control-label">Transportadora:</label>
                <div class="col-sm-6">
                    <select name="volumetric_id_carrier" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        {foreach from=$registered_carriers item=carrier}
                            <option value="{$carrier.id_carrier}">{$carrier.name}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Factor Volumétrico:</label>
                <div class="col-sm-6">
                    <input type="number" 
                           name="volumetric_factor" 
                           class="form-control" 
                           required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" name="submitVolumetricFactor" class="btn btn-primary">
                        <i class="icon-save"></i> Guardar Factor Volumétrico
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<hr>

{* ============================================
   3. CONFIGURACIÓN POR TIPO DE TRANSPORTADORA
   ============================================ *}

{* 3A. TRANSPORTADORAS POR RANGO - SEGURO *}
<div class="panel panel-success">
    <div class="panel-heading">
        <i class="icon-th-list"></i> Configuración de Seguros - Transportadoras POR RANGO
    </div>
    <div class="panel-body">
        <p class="text-muted">
            <i class="icon-info-circle"></i> 
            Para transportadoras por RANGO, el seguro se calcula por rangos de PESO.<br>
            El porcentaje se aplica sobre el valor declarado del paquete.
        </p>

        {* Mostrar seguros configurados por transportadora *}
        {if $carrier_configs && count($carrier_configs) > 0}
            {foreach from=$carrier_configs item=config}
                {if $config.type == 'range'}
                    <div class="well">
                        <h4>
                            <i class="icon-truck"></i> {$config.carrier.name}
                            <small class="text-muted">(Por Rango)</small>
                        </h4>
                        
                        {if $config.insurances && count($config.insurances) > 0}
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Peso Mín (kg)</th>
                                        <th>Peso Máx (kg)</th>
                                        <th>% Seguro</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$config.insurances item=insurance}
                                        <tr>
                                            <td>{$insurance.min|string_format:"%.2f"}</td>
                                            <td>
                                                {if $insurance.max > 0}
                                                    {$insurance.max|string_format:"%.2f"}
                                                {else}
                                                    <span class="text-muted">Sin límite</span>
                                                {/if}
                                            </td>
                                            <td>{$insurance.value_number|string_format:"%.2f"}%</td>
                                            <td>
                                                <form method="post" action="{$currentIndex}&token={$token}" style="display:inline;">
                                                    <input type="hidden" name="id_config" value="{$insurance.id_config}">
                                                    <button type="submit" name="deleteConfig" class="btn btn-xs btn-danger"
                                                            onclick="return confirm('¿Eliminar este rango?');">
                                                        <i class="icon-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        {else}
                            <p class="text-warning"><i class="icon-warning"></i> No hay rangos de seguro configurados.</p>
                        {/if}
                    </div>
                {/if}
            {/foreach}
        {/if}

        {* Formulario para agregar rango de seguro *}
        <hr>
        <h4>Agregar Rango de Seguro (Transportadora por Rango):</h4>
        <form method="post" action="{$currentIndex}&token={$token}" class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-3 control-label">Transportadora:</label>
                <div class="col-sm-6">
                    <select name="range_insurance_carrier" class="form-control" required>
                        <option value="">-- Seleccionar --</option>
                        {foreach from=$carrier_configs item=config}
                            {if $config.type == 'range'}
                                <option value="{$config.carrier.id_carrier}">{$config.carrier.name}</option>
                            {/if}
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Peso Mínimo (kg):</label>
                <div class="col-sm-3">
                    <input type="number" name="range_insurance_min" class="form-control" 
                           step="0.01" min="0" required>
                </div>
                <label class="col-sm-2 control-label">Peso Máximo (kg):</label>
                <div class="col-sm-3">
                    <input type="number" name="range_insurance_max" class="form-control" 
                           step="0.01" min="0">
                    <p class="help-block">Dejar en 0 para sin límite</p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Porcentaje de Seguro (%):</label>
                <div class="col-sm-6">
                    <input type="number" name="range_insurance_percentage" class="form-control" 
                           step="0.01" min="0" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-6">
                    <button type="submit" name="submitRangeInsurance" class="btn btn-success">
                        <i class="icon-plus"></i> Agregar Rango de Seguro
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<hr>

{* 3B. TRANSPORTADORAS POR KG - CONFIGURACIÓN COMPLETA *}
<div class="panel panel-info">
    <div class="panel-heading">
        <i class="icon-balance-scale"></i> Configuración Completa - Transportadoras POR KG
    </div>
    <div class="panel-body">
        <p class="text-muted">
            <i class="icon-info-circle"></i> 
            Para transportadoras por KG, se configuran múltiples parámetros según sus reglas específicas.
        </p>

        {* Mostrar configuraciones actuales de transportadoras POR KG *}
        {if $carrier_configs && count($carrier_configs) > 0}
            {foreach from=$carrier_configs item=config}
                {if $config.type == 'per_kg'}
                    <div class="well">
                        <h4>
                            <i class="icon-truck"></i> {$config.carrier.name}
                            <small class="text-muted">(Por KG)</small>
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-condensed">
                                    <tr>
                                        <td><strong>Flete Mínimo Nacional:</strong></td>
                                        <td class="text-right">
                                            {if $config.min_freight}
                                                ${$config.min_freight|string_format:"%.2f"}
                                            {else}
                                                <span class="text-muted">No configurado</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kilos de Cobro Mínimo:</strong></td>
                                        <td class="text-right">
                                            {if $config.min_kilos}
                                                {$config.min_kilos|string_format:"%.2f"} kg
                                            {else}
                                                <span class="text-muted">No configurado</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Valor Base Unidad (Seguro):</strong></td>
                                        <td class="text-right">
                                            {if $config.base_value}
                                                ${$config.base_value|string_format:"%.2f"}
                                            {else}
                                                <span class="text-muted">No configurado</span>
                                            {/if}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-condensed">
                                    <tr>
                                        <td><strong>Seguro Mínimo (cuando Vr < Base):</strong></td>
                                        <td class="text-right">
                                            {if $config.min_insurance}
                                                ${$config.min_insurance|string_format:"%.2f"}
                                            {else}
                                                <span class="text-muted">No configurado</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>% Seguro (cuando Vr > Base):</strong></td>
                                        <td class="text-right">
                                            {if $config.insurance_percent}
                                                {$config.insurance_percent|string_format:"%.2f"}%
                                            {else}
                                                <span class="text-muted">No configurado</span>
                                            {/if}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        {* Mostrar rangos de seguro si existen *}
                        {if $config.insurance_ranges && count($config.insurance_ranges) > 0}
                            <hr>
                            <h5><i class="icon-shield"></i> Rangos de Seguro Configurados:</h5>
                            <table class="table table-bordered table-sm">
                                <thead style="background-color: #f5f5f5;">
                                    <tr>
                                        <th>Valor Mín ($)</th>
                                        <th>Valor Máx ($)</th>
                                        <th>Valor Seguro</th>
                                        <th>Tipo</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$config.insurance_ranges item=insurance}
                                        <tr>
                                            <td>${$insurance.min|string_format:"%.2f"}</td>
                                            <td>
                                                {if $insurance.max > 0}
                                                    ${$insurance.max|string_format:"%.2f"}
                                                {else}
                                                    <span class="text-muted">Sin límite</span>
                                                {/if}
                                            </td>
                                            <td>
                                                {if $insurance.value_number >= 100}
                                                    ${$insurance.value_number|string_format:"%.2f"}
                                                {else}
                                                    {$insurance.value_number|string_format:"%.2f"}%
                                                {/if}
                                            </td>
                                            <td>
                                                {if $insurance.value_number >= 100}
                                                    <span class="badge badge-success">Valor Fijo</span>
                                                {else}
                                                    <span class="badge badge-info">Porcentaje</span>
                                                {/if}
                                            </td>
                                            <td>
                                                <form method="post" action="{$currentIndex}&token={$token}" style="display:inline;">
                                                    <input type="hidden" name="id_config" value="{$insurance.id_config}">
                                                    <button type="submit" name="deleteConfig" class="btn btn-xs btn-danger"
                                                            onclick="return confirm('¿Eliminar este rango?');">
                                                        <i class="icon-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        {/if}
                    </div>
                {/if}
            {/foreach}
        {/if}

        {* Formulario para configurar transportadora POR KG *}
        <hr>
        <h4>Configurar Transportadora Por KG:</h4>
        <form method="post" action="{$currentIndex}&token={$token}">
            <div class="form-group">
                <label>Transportadora:</label>
                <select name="perkg_id_carrier" class="form-control" required>
                    <option value="">-- Seleccionar Transportadora Por KG --</option>
                    {foreach from=$carrier_configs item=config}
                        {if $config.type == 'per_kg'}
                            <option value="{$config.carrier.id_carrier}">{$config.carrier.name}</option>
                        {/if}
                    {/foreach}
                </select>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Flete Mínimo Nacional ($):</label>
                        <input type="number" name="perkg_min_freight" class="form-control" 
                               step="0.01" min="0">
                        <p class="help-block">Valor mínimo a cobrar por flete</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Kilos de Cobro Mínimo (kg):</label>
                        <input type="number" name="perkg_min_kilos" class="form-control" 
                               step="0.01" min="0">
                        <p class="help-block">Mínimo de kilos que se cobran</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Valor Base Unidad para Cálculo de Seguro Mínimo ($):</label>
                        <input type="number" name="perkg_base_value" class="form-control" 
                               step="0.01" min="0">
                        <p class="help-block">Valor de comparación para aplicar seguro mínimo o porcentaje</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Seguro Mínimo cuando Vr unidad < Valor Base ($):</label>
                        <input type="number" name="perkg_min_insurance" class="form-control" 
                               step="0.01" min="0">
                        <p class="help-block">Valor fijo cuando el producto vale menos que el valor base</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>% Seguro cuando Vr unidad > Valor Base (%):</label>
                <input type="number" name="perkg_insurance_percent" class="form-control" 
                       step="0.01" min="0">
                <p class="help-block">Porcentaje que se aplica cuando el producto vale más que el valor base</p>
            </div>

            <button type="submit" name="submitPerKgConfig" class="btn btn-info btn-lg btn-block">
                <i class="icon-save"></i> Guardar Configuración Transportadora Por KG
            </button>
        </form>
    </div>
</div>

<style>
    .well {
        background-color: #f9f9f9;
        border: 1px solid #e3e3e3;
        margin-bottom: 20px;
    }
    .table-condensed td {
        padding: 5px;
    }
    .badge {
        padding: 5px 10px;
        font-size: 12px;
    }
</style>