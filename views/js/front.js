/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(function() {
    // Monitorear cambios en la dirección de entrega para actualizar el precio del carrier
    
    // Para PrestaShop 1.7+ (Checkout moderno)
    if (typeof prestashop !== 'undefined') {
        prestashop.on('updatedDeliveryForm', function() {
            updateCarrierPrices();
            updateCarrierPriceDisplay();
        });
        
        prestashop.on('updatedAddressForm', function() {
            setTimeout(function() {
                updateCarrierPrices();
                updateCarrierPriceDisplay();
            }, 500);
        });
    }
    
    // Para PrestaShop 1.6 (Checkout clásico)
    $(document).on('change', '#id_address_delivery, select[name="id_address_delivery"]', function() {
        setTimeout(function() {
            updateCarrierPrices();
            updateCarrierPriceDisplay();
        }, 300);
    });
    
    // Observar cambios en la ciudad dentro del formulario de dirección
    $(document).on('change', 'input[name="city"], select[name="city"], select[name="id_state"]', function() {
        // Dar un pequeño delay para que se guarde la dirección
        setTimeout(function() {
            updateCarrierPrices();
            updateCarrierPriceDisplay();
        }, 500);
    });
    
    // Inicializar la visualización al cargar la página
    updateCarrierPriceDisplay();
});

/**
 * Actualiza los precios de los carriers recalculando el costo de envío
 */
function updateCarrierPrices() {
    // Trigger de actualización del carrito/carriers de PrestaShop
    if (typeof prestashop !== 'undefined' && prestashop.cart) {
        // En PrestaShop 1.7+, actualizar el carrito dispara el recálculo
        $.ajax({
            url: prestashop.urls.pages.cart,
            data: {
                ajax: 1,
                action: 'refresh'
            },
            success: function() {
                // El carrito se actualiza automáticamente
                console.log('Shipping Calculator: Carrier prices updated');
                setTimeout(updateCarrierPriceDisplay, 200);
            }
        });
    } else {
        // En PrestaShop 1.6, refrescar la página de carriers
        if (typeof updateCarrierList === 'function') {
            updateCarrierList();
            setTimeout(updateCarrierPriceDisplay, 200);
        }
    }
}

/**
 * Actualiza la visualización del precio para mostrar "Por calcular" cuando corresponda
 */
function updateCarrierPriceDisplay() {
    // Verificar si hay una dirección seleccionada
    var addressSelected = false;
    var citySelected = false;
    
    // PrestaShop 1.7+
    if ($('#delivery-addresses').length > 0) {
        var selectedAddress = $('#delivery-addresses input[type="radio"]:checked');
        addressSelected = selectedAddress.length > 0;
    }
    // PrestaShop 1.6
    else if ($('#id_address_delivery').length > 0) {
        var addressId = $('#id_address_delivery').val();
        addressSelected = addressId && addressId !== '0' && addressId !== '';
    }
    
    // Verificar si hay ciudad seleccionada
    var cityInput = $('input[name="city"]').val();
    var citySelect = $('select[name="city"]').val();
    citySelected = (cityInput && cityInput.trim() !== '') || (citySelect && citySelect !== '0' && citySelect !== '');
    
    // Si no hay dirección o ciudad, mostrar "Por calcular"
    if (!addressSelected || !citySelected) {
        // Buscar el carrier del módulo y actualizar su visualización
        $('.delivery-option, .js-delivery-option').each(function() {
            var carrierName = $(this).find('.carrier-name, .delivery-option-name').text().toLowerCase();
            
            // Si es el carrier de "Shipping Calculator" (ajustar nombre según configuración)
            if (carrierName.includes('shipping') || carrierName.includes('calculator')) {
                var priceElement = $(this).find('.carrier-price, .delivery-option-price');
                
                // Agregar clase para CSS personalizado
                priceElement.addClass('shipping-pending');
                
                // Modificar el texto del precio
                var originalPrice = priceElement.find('.value, .price-value').first();
                if (originalPrice.length > 0 && !priceElement.find('.pending-text').length) {
                    originalPrice.hide();
                    priceElement.append('<span class="pending-text" style="color: #f39c12; font-style: italic;">Por calcular</span>');
                }
            }
        });
        
        // También actualizar el total del carrito
        updateCartTotal(0, true);
    } else {
        // Remover el texto "Por calcular" si la ciudad está seleccionada
        $('.carrier-price, .delivery-option-price').each(function() {
            $(this).removeClass('shipping-pending');
            $(this).find('.pending-text').remove();
            $(this).find('.value, .price-value').show();
        });
    }
}

/**
 * Actualiza el total del carrito considerando si el envío está "Por calcular"
 */
function updateCartTotal(shippingCost, isPending) {
    if (isPending) {
        // Mostrar mensaje en el total indicando que falta calcular el envío
        var totalShipping = $('.cart-total .value, #total_shipping, .order-total-shipping');
        if (totalShipping.length > 0 && !totalShipping.find('.pending-shipping').length) {
            totalShipping.append(' <span class="pending-shipping" style="font-size: 0.85em; color: #f39c12;">(+ envío por calcular)</span>');
        }
    } else {
        // Remover el mensaje si ya está calculado
        $('.pending-shipping').remove();
    }
}
