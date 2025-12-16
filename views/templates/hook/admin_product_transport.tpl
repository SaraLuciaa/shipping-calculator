<div class="form-group">
    <h2><strong>Configuración de Envío</strong></h2>

    <div class="row">
        <div class="col-md-6">
            <label>Tipo de embalaje</label>
            <select name="shipping_is_grouped" class="form-control">
                <option value="">-- Selecciona --</option>
                <option value="0" {if $is_grouped === '0'}selected{/if}>Individual</option>
                <option value="1" {if $is_grouped === '1'}selected{/if}>Agrupado</option>
            </select>
            <p class="help-block">
                <strong>Individual:</strong> Puede agruparse entre sí mismo o no agruparse.<br>
                <strong>Agrupado:</strong> Se agrupa con otros productos agrupados.
            </p>
        </div>

        <div class="col-md-6">
            <label>Máximo de unidades por paquete</label>
            <input type="number" 
                   name="shipping_max_units_per_package" 
                   class="form-control"
                   value="{$max_units_per_package}"
                   min="0"
                   step="1"
                   placeholder="0 = Sin límite">
            <p class="help-block">
                <strong>Si es Individual:</strong> Máx. unidades del mismo producto por paquete. 0 = No se agrupa.<br>
                <strong>Si es Agrupado:</strong> Máx. unidades de este producto por paquete. 0 = Sin límite.
            </p>
        </div>
    </div>
</div>