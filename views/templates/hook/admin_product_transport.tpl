<div class="form-group">
    <label><strong>Tipo de embalaje</strong></label>

    <select name="shipping_is_grouped" class="form-control">
        <option value="">-- Selecciona --</option>
        <option value="0" {if $is_grouped === '0'}selected{/if}>Individual</option>
        <option value="1" {if $is_grouped === '1'}selected{/if}>Agrupado</option>
    </select>

    <p class="help-block">
        Define si este producto se envía agrupado o individual.
    </p>
</div>