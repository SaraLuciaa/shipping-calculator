# 📦 REGLAS DEL COTIZADOR DE ENVÍOS - DOCUMENTACIÓN ULTRA DETALLADA

## 📋 ÍNDICE

1. [Introducción y Arquitectura General](#1-introducción-y-arquitectura-general)
2. [Clasificación de Productos](#2-clasificación-de-productos)
3. [Cálculo de Pesos](#3-cálculo-de-pesos)
4. [Estrategias de Empaquetado](#4-estrategias-de-empaquetado)
5. [Tipos de Transportadoras y Tarifas](#5-tipos-de-transportadoras-y-tarifas)
6. [Cálculo de Costos Adicionales](#6-cálculo-de-costos-adicionales)
7. [Aplicación de IVA](#7-aplicación-de-iva)
8. [Flujo Completo de Cotización](#8-flujo-completo-de-cotización)
9. [Reglas de Validación](#9-reglas-de-validación)
10. [Casos Especiales y Excepciones](#10-casos-especiales-y-excepciones)

---

## 1. INTRODUCCIÓN Y ARQUITECTURA GENERAL

### 1.1 Propósito del Sistema

El **Shipping Calculator** es un módulo de PrestaShop diseñado para calcular costos de envío de forma dinámica basándose en:
- **Ciudad de destino** (cobertura geográfica)
- **Peso de los productos** (real y volumétrico)
- **Tipo de producto** (agrupable o individual)
- **Configuración de transportadoras** (por kg o por rangos)
- **Costos adicionales** (empaque, seguro)
- **Impuestos** (IVA configurable)

### 1.2 Componentes Principales

```
Shipping Calculator
│
├── shipping_calculator.php (módulo principal)
│   ├── getOrderShippingCost() → Llamado por PrestaShop en checkout
│   └── calculateShipping() → Lógica central de cálculo
│
├── Services (src/services/)
│   ├── ShippingQuoteService.php (coordinador principal)
│   ├── WeightCalculatorService.php (cálculo de pesos)
│   ├── ShippingGroupedPackageService.php (productos agrupables)
│   ├── IndividualGroupablePackageService.php (individuales agrupables)
│   ├── CarrierRegistryService.php (gestión transportadoras)
│   ├── RateImportService.php (importación tarifas CSV)
│   ├── CityLookupService.php (búsqueda ciudades)
│   └── NormalizerService.php (normalización texto)
│
└── Controllers
    └── AdminShippingCalculatorController.php (backoffice)
```

### 1.3 Base de Datos

**Tablas principales:**

1. **`shipping_rate_type`**: Registro de transportadoras y tipo (per_kg o range)
2. **`shipping_per_kg_rate`**: Tarifas por kilogramo (ciudad + precio/kg)
3. **`shipping_range_rate`**: Tarifas por rangos de peso (ciudad + rango + precio fijo)
4. **`shipping_config`**: Configuraciones globales y por transportadora
5. **`shipping_product`**: Configuración de empaquetado por producto
6. **`city`**: Ciudades con cobertura (tabla estándar PrestaShop)

---

## 2. CLASIFICACIÓN DE PRODUCTOS

### 2.1 Tipos de Productos Según Empaquetado

El sistema clasifica cada producto en el carrito según la configuración en `shipping_product`:

#### 🔵 **Tipo 1: Productos Agrupados** (`is_grouped = 1`)

**Definición:**
- Productos que **pueden mezclarse entre sí** en un mismo paquete
- Ejemplo: Camisetas, libros pequeños, accesorios

**Características:**
- Se intentan empacar juntos usando **algoritmo Best-Fit**
- Restricción: Peso total del paquete ≤ `max_package_weight` (configuración global)
- Si se supera peso máximo, se dividen en múltiples paquetes

**Campo `max_units_per_package`:**
- Si es **0 o NULL**: Sin límite de unidades por paquete (solo limitado por peso)
- Si es **> 0**: Máximo N unidades del mismo producto por paquete

**Ejemplo:**
```
Producto: Camiseta
- is_grouped = 1
- max_units_per_package = 5
- peso_unitario = 0.3 kg
- Cantidad en carrito = 12 unidades

Resultado:
- Paquete 1: 5 camisetas (1.5 kg)
- Paquete 2: 5 camisetas (1.5 kg)
- Paquete 3: 2 camisetas (0.6 kg)
```

#### 🟢 **Tipo 2: Productos Individuales Agrupables** (`is_grouped = 0` + `max_units_per_package > 0`)

**Definición:**
- Productos que **NO se mezclan con otros**, pero **pueden agruparse consigo mismos**
- Ejemplo: Botellas de vino (se empacan juntas, pero no con otros productos)

**Características:**
- Cada producto genera sus propios paquetes
- Se agrupan **solo unidades del mismo producto**
- Restricción: Peso ≤ `max_package_weight` Y Unidades ≤ `max_units_per_package`

**Ejemplo:**
```
Producto: Botella de Vino 750ml
- is_grouped = 0
- max_units_per_package = 6
- peso_unitario = 1.2 kg
- Cantidad en carrito = 10 unidades

Resultado:
- Paquete 1: 6 botellas (7.2 kg)
- Paquete 2: 4 botellas (4.8 kg)
```

#### 🔴 **Tipo 3: Productos Individuales NO Agrupables** (`is_grouped = 0` + `max_units_per_package = 0`)

**Definición:**
- Productos que **se envían completamente por separado**
- Ejemplo: Muebles, electrodomésticos grandes

**Características:**
- **Cada unidad** genera un envío independiente
- Se cotizan individualmente
- No se agrupan con nada

**Ejemplo:**
```
Producto: Refrigerador
- is_grouped = 0
- max_units_per_package = 0
- peso_unitario = 45 kg
- Cantidad en carrito = 2 unidades

Resultado:
- Envío 1: 1 refrigerador (45 kg)
- Envío 2: 1 refrigerador (45 kg)
```

### 2.2 Configuración por Producto

**Ubicación en BackOffice:**
`Catálogo → Productos → Editar Producto → Pestaña "Transporte"`

**Campos:**

| Campo | Valores | Descripción |
|-------|---------|-------------|
| `is_grouped` | 0 o 1 | 0=Individual, 1=Agrupado |
| `max_units_per_package` | 0 o N | 0=No agrupa, N=Máximo por paquete |

**Matriz de Configuración:**

| is_grouped | max_units | Comportamiento |
|------------|-----------|----------------|
| 1 | 0 | Agrupado, sin límite de unidades |
| 1 | N > 0 | Agrupado, máximo N unidades por paquete |
| 0 | 0 | Individual NO agrupable (cada unidad aparte) |
| 0 | N > 0 | Individual agrupable consigo mismo (máx N unidades) |

### 2.3 Validación de Productos Sin Configuración

**Comportamiento por defecto:**
- Si no existe registro en `shipping_product` → Se asume `is_grouped=0`, `max_units_per_package=0`
- Es decir: **Producto individual NO agrupable**

---

## 3. CÁLCULO DE PESOS

### 3.1 Peso Real vs Peso Volumétrico

#### 3.1.1 Peso Real
**Fuente:** Campo `weight` de la tabla `product` (en kg)

**Validación:**
- Si peso ≤ 0 → Se asigna peso mínimo de **0.1 kg**

#### 3.1.2 Peso Volumétrico

**Definición:** Peso calculado según el **volumen** que ocupa el producto.

**Fórmula:**
```
Peso Volumétrico = (Largo × Ancho × Alto) / Factor Volumétrico

Donde:
- Largo, Ancho, Alto = en centímetros
- Factor Volumétrico = configurado por transportadora (ej: 5000)
```

**Implementación:**
```php
public function volumetricWeight($length, $width, $height, $weightVol)
{
    if ($length <= 0 || $width <= 0 || $height <= 0) {
        return 0;
    }
    
    if ($weightVol <= 0) {
        return 0;
    }

    return ($length/100 * $width/100 * $height/100) * (float)$weightVol;
}
```

**Conversión a metros:**
```
length/100 = convierte cm a metros
width/100 = convierte cm a metros
height/100 = convierte cm a metros

Volumen (m³) = (length/100) × (width/100) × (height/100)
Peso Vol (kg) = Volumen × Factor
```

### 3.2 Peso Facturable (Peso a Cobrar)

**Regla Fundamental:**
```
Peso Facturable = MAX(Peso Real, Peso Volumétrico)
```

**Lógica:**
- Las transportadoras cobran por el **mayor** entre peso real y volumétrico
- Protege contra productos "livianos pero voluminosos"

**Ejemplos:**

**Ejemplo 1: Producto Pesado y Compacto**
```
Laptop:
- Peso real = 2.5 kg
- Dimensiones = 35cm × 25cm × 3cm
- Factor volumétrico = 5000

Peso volumétrico = (0.35 × 0.25 × 0.03) × 5000 = 0.13125 kg

Peso facturable = MAX(2.5, 0.13125) = 2.5 kg ✅
```

**Ejemplo 2: Producto Liviano y Voluminoso**
```
Almohada:
- Peso real = 0.5 kg
- Dimensiones = 60cm × 40cm × 15cm
- Factor volumétrico = 5000

Peso volumétrico = (0.60 × 0.40 × 0.15) × 5000 = 180 kg

Peso facturable = MAX(0.5, 180) = 180 kg ✅
```

### 3.3 Factor Volumétrico por Transportadora

**Configuración:**
- Cada transportadora tiene su propio factor
- Se configura en: `BackOffice → Calculadora de Envíos → Configuración → Factor Volumétrico`

**Factores comunes:**
- **5000**: Estándar para terrestre
- **6000**: Más permisivo
- **4000**: Más estricto (mayor peso volumétrico)

**Regla para cotización múltiple:**
- Se usa el **factor MÍNIMO** de todas las transportadoras (más conservador)
- Garantiza que el peso calculado funcione para todas

**Consulta SQL:**
```sql
SELECT MIN(CAST(value_number AS UNSIGNED)) as min_factor
FROM ps_shipping_config
WHERE name = 'Peso volumetrico' AND value_number > 0
```

---

## 4. ESTRATEGIAS DE EMPAQUETADO

### 4.1 Algoritmo Best-Fit para Productos Agrupados

**Objetivo:** Minimizar el número de paquetes y optimizar el uso del espacio/peso.

#### 4.1.1 Flujo del Algoritmo

**Paso 1: Preparación**
```
Para cada producto agrupado (is_grouped=1):
1. Calcular peso_volumetrico_unitario
2. peso_max_unitario = MAX(peso_real, peso_volumetrico)
3. Determinar límite por max_units_per_package
```

**Paso 2: Iteración por Producto**
```
Para cada producto con cantidad Q:
  Si max_units_per_package = 0:
    - Intentar agregar todas las unidades juntas
    - Si excede peso máximo, dividir en paquetes
  
  Si max_units_per_package = N:
    - Dividir en lotes de máximo N unidades
    - Cada lote intenta caber en paquete existente
```

**Paso 3: Best-Fit**
```
Para cada unidad/lote a empacar:
  1. Buscar paquete existente con espacio disponible
  2. Seleccionar el que tenga MAYOR peso actual (best-fit)
  3. Si ninguno cabe, crear nuevo paquete
```

**¿Por qué "mayor peso actual"?**
- Llena paquetes casi llenos primero
- Evita crear muchos paquetes medio vacíos
- Optimiza uso de peso máximo

#### 4.1.2 Ejemplo Completo

**Escenario:**
```
Configuración:
- max_package_weight = 60 kg

Productos en carrito:
1. Camiseta (is_grouped=1, max_units=5, peso=0.3kg) × 12 unidades
2. Libro (is_grouped=1, max_units=0, peso=0.8kg) × 8 unidades
3. Gorra (is_grouped=1, max_units=10, peso=0.2kg) × 15 unidades
```

**Ejecución:**

**Paso 1: Procesar Camisetas (12 unidades, max 5/paquete)**
```
Iteración 1: 5 camisetas → Peso = 1.5 kg
  - No hay paquetes → Crear Paquete 1 (1.5 kg)

Iteración 2: 5 camisetas → Peso = 1.5 kg
  - Paquete 1 tiene 1.5kg + 1.5kg = 3kg ≤ 60kg ✅
  - Agregar a Paquete 1 (3 kg)

Iteración 3: 2 camisetas → Peso = 0.6 kg
  - Paquete 1 tiene 3kg + 0.6kg = 3.6kg ≤ 60kg ✅
  - Agregar a Paquete 1 (3.6 kg)

Estado: [Paquete 1: 3.6 kg (12 camisetas)]
```

**Paso 2: Procesar Libros (8 unidades, sin límite)**
```
Intentar agregar 8 libros juntos → Peso = 6.4 kg

Evaluación:
- Paquete 1: 3.6kg + 6.4kg = 10kg ≤ 60kg ✅
- Best-fit: Paquete 1 (mayor peso actual)
- Agregar a Paquete 1 (10 kg)

Estado: [Paquete 1: 10 kg (12 camisetas + 8 libros)]
```

**Paso 3: Procesar Gorras (15 unidades, max 10/paquete)**
```
Iteración 1: 10 gorras → Peso = 2 kg
  - Paquete 1: 10kg + 2kg = 12kg ≤ 60kg ✅
  - Agregar a Paquete 1 (12 kg)

Iteración 2: 5 gorras → Peso = 1 kg
  - Paquete 1: 12kg + 1kg = 13kg ≤ 60kg ✅
  - Agregar a Paquete 1 (13 kg)

Estado Final: [Paquete 1: 13 kg (12 camisetas + 8 libros + 15 gorras)]
```

**Resultado:**
- ✅ **1 paquete agrupado de 13 kg**
- Total productos: 35 unidades
- Optimización exitosa

### 4.2 Estrategia para Individuales Agrupables

**Característica clave:** Cada producto genera **sus propios paquetes independientes**.

#### 4.2.1 Flujo

```
Para cada producto individual agrupable (is_grouped=0, max_units>0):
  1. Calcular peso_unitario_facturable
  2. Validar si peso_unitario ≤ max_package_weight
  3. Si excede → Marcar como "oversized" (cotización especial)
  4. Si no excede → Agrupar en paquetes respetando:
     - Peso total ≤ max_package_weight
     - Unidades ≤ max_units_per_package
```

#### 4.2.2 Ejemplo

**Escenario:**
```
Producto: Botella Aceite de Oliva 1L
- is_grouped = 0
- max_units_per_package = 6
- peso_unitario = 1.1 kg
- Cantidad = 20 unidades

Configuración:
- max_package_weight = 60 kg
```

**Cálculo:**
```
Restricción peso: 60kg / 1.1kg = 54 unidades máx (pero limitado a 6)
Restricción unidades: 6 unidades máx

Límite efectivo: MIN(54, 6) = 6 unidades por paquete

Distribución:
- Paquete 1: 6 botellas × 1.1kg = 6.6 kg
- Paquete 2: 6 botellas × 1.1kg = 6.6 kg
- Paquete 3: 6 botellas × 1.1kg = 6.6 kg
- Paquete 4: 2 botellas × 1.1kg = 2.2 kg

Total: 4 paquetes individuales
```

### 4.3 Estrategia para Individuales NO Agrupables

**Regla simple:** Cada unidad = 1 paquete

**Ejemplo:**
```
Producto: Televisor 50"
- is_grouped = 0
- max_units_per_package = 0
- peso_unitario = 18 kg
- Cantidad = 3 unidades

Resultado:
- Paquete 1: 1 televisor (18 kg)
- Paquete 2: 1 televisor (18 kg)
- Paquete 3: 1 televisor (18 kg)

Total: 3 paquetes individuales
```

---

## 5. TIPOS DE TRANSPORTADORAS Y TARIFAS

### 5.1 Registro de Transportadoras

**Tabla:** `shipping_rate_type`

**Campos:**
- `id_carrier`: ID de la transportadora en PrestaShop
- `type`: Tipo de tarifa (`per_kg` o `range`)
- `active`: 1=Activa, 0=Inactiva

**Proceso de registro:**
1. Admin selecciona transportadora existente en PrestaShop
2. Asigna tipo de tarifa (por kg o por rango)
3. Sistema crea registro en `shipping_rate_type`

### 5.2 Transportadoras por Kilogramo (`per_kg`)

#### 5.2.1 Concepto

**Definición:** El costo se calcula multiplicando el peso por una tarifa fija por kilogramo.

**Fórmula básica:**
```
Costo Base = Peso Facturable × Precio por KG
```

#### 5.2.2 Estructura de Tarifas

**Tabla:** `shipping_per_kg_rate`

**Campos:**
- `id_carrier`: Transportadora
- `id_city`: Ciudad de destino
- `price`: Precio por kilogramo ($/kg)
- `active`: Estado

**Ejemplo de datos:**
```
id_carrier | id_city | price  | active
-----------|---------|--------|-------
42         | 1515    | 2500   | 1     (Bogotá: $2,500/kg)
42         | 1516    | 3200   | 1     (Medellín: $3,200/kg)
42         | 1517    | 4100   | 1     (Cali: $4,100/kg)
```

#### 5.2.3 Reglas Especiales

**A. Flete Mínimo Nacional**

**Configuración:** `shipping_config` → `name='Flete minimo'`

**Regla:**
```
Si (Peso × Precio/kg) < Flete Mínimo:
  Costo = Flete Mínimo
Sino:
  Costo = Peso × Precio/kg
```

**Ejemplo:**
```
Configuración:
- Precio/kg = $2,500
- Flete mínimo = $8,000

Caso 1: Paquete de 2 kg
  Cálculo: 2 × 2,500 = $5,000
  $5,000 < $8,000 → Se cobra $8,000 ✅

Caso 2: Paquete de 5 kg
  Cálculo: 5 × 2,500 = $12,500
  $12,500 > $8,000 → Se cobra $12,500 ✅
```

**B. Kilos de Cobro Mínimo**

**Configuración:** `shipping_config` → `name='Kilos minimo'`

**Regla:**
```
Si Peso Real < Kilos Mínimo:
  Peso Facturable = Kilos Mínimo
Sino:
  Peso Facturable = MAX(Peso Real, Peso Volumétrico)
```

**Ejemplo:**
```
Configuración:
- Precio/kg = $2,500
- Kilos mínimo = 3 kg

Caso 1: Paquete de 1.5 kg
  Peso a cobrar = MAX(3, 1.5) = 3 kg
  Costo = 3 × 2,500 = $7,500 ✅

Caso 2: Paquete de 5 kg
  Peso a cobrar = MAX(3, 5) = 5 kg
  Costo = 5 × 2,500 = $12,500 ✅
```

#### 5.2.4 Seguro para Transportadoras POR KG

**Configuración:** Rangos de valor declarado en `shipping_config`

**Estructura:**
```
name = 'Seguro'
id_carrier = [ID transportadora]
min = Valor declarado mínimo ($)
max = Valor declarado máximo ($) [0 = sin límite]
value_number = Valor fijo o porcentaje
```

**Reglas de interpretación:**

**Caso A: value_number ≥ 100 → Valor Fijo**
```
Ejemplo:
min=0, max=50000, value_number=2000
Interpretación: Para paquetes con valor $0-$50,000 → Seguro fijo $2,000
```

**Caso B: value_number < 100 → Porcentaje**
```
Ejemplo:
min=50000, max=0, value_number=3.5
Interpretación: Para paquetes con valor >$50,000 → 3.5% del valor declarado
```

**Ejemplo completo:**
```
Transportadora: Coordinadora
Configuración de seguro:

Rango 1: min=0,     max=50000,  value=2000   → Seguro fijo $2,000
Rango 2: min=50000, max=0,      value=3.5    → 3.5% del valor

Aplicación:
- Paquete valor $30,000 → Seguro = $2,000
- Paquete valor $100,000 → Seguro = $100,000 × 0.035 = $3,500
```

### 5.3 Transportadoras por Rango (`range`)

#### 5.3.1 Concepto

**Definición:** El costo es un **precio fijo** según el rango de peso en que caiga el paquete.

#### 5.3.2 Estructura de Tarifas

**Tabla:** `shipping_range_rate`

**Campos:**
- `id_carrier`: Transportadora
- `id_city`: Ciudad de destino
- `min_weight`: Peso mínimo del rango (kg)
- `max_weight`: Peso máximo del rango (kg) [0 = sin límite]
- `price`: Precio fijo del rango ($)
- `active`: Estado

**Ejemplo de datos:**
```
id_carrier | id_city | min_weight | max_weight | price   | active
-----------|---------|------------|------------|---------|-------
43         | 1515    | 0          | 1          | 8500    | 1
43         | 1515    | 1          | 3          | 12000   | 1
43         | 1515    | 3          | 5          | 15500   | 1
43         | 1515    | 5          | 10         | 22000   | 1
43         | 1515    | 10         | 0          | 35000   | 1
```

#### 5.3.3 Lógica de Selección de Rango

**Consulta SQL:**
```sql
SELECT price, min_weight, max_weight
FROM ps_shipping_range_rate
WHERE id_carrier = ?
  AND id_city = ?
  AND active = 1
  AND min_weight <= [peso_facturable]
  AND (max_weight = 0 OR max_weight >= [peso_facturable])
ORDER BY min_weight DESC
LIMIT 1
```

**Regla:**
- Se busca el rango donde: `min_weight ≤ peso ≤ max_weight`
- Si `max_weight = 0` → Sin límite superior
- Se ordena por `min_weight DESC` para tomar el más específico

**Ejemplo de aplicación:**
```
Rangos configurados:
- 0-1 kg   → $8,500
- 1-3 kg   → $12,000
- 3-5 kg   → $15,500
- 5-10 kg  → $22,000
- 10+ kg   → $35,000

Casos:
- Paquete 0.8 kg  → Rango 0-1    → $8,500
- Paquete 2.5 kg  → Rango 1-3    → $12,000
- Paquete 8.2 kg  → Rango 5-10   → $22,000
- Paquete 15 kg   → Rango 10+    → $35,000
```

#### 5.3.4 Seguro para Transportadoras POR RANGO

**Configuración:** Rangos de peso en `shipping_config`

**Estructura:**
```
name = 'Seguro'
id_carrier = [ID transportadora]
min = Peso mínimo (kg)
max = Peso máximo (kg) [0 = sin límite]
value_number = Porcentaje sobre valor declarado
```

**Diferencia clave:** En transportadoras POR RANGO, el seguro se calcula sobre **rangos de PESO**, no de valor.

**Ejemplo:**
```
Transportadora: Servientrega
Configuración de seguro:

Rango 1: min=0,  max=5,  value=2.5   → 0-5kg  → 2.5% del valor
Rango 2: min=5,  max=10, value=3.0   → 5-10kg → 3.0% del valor
Rango 3: min=10, max=0,  value=4.0   → 10+kg  → 4.0% del valor

Aplicación:
- Paquete 3kg, valor $50,000  → Seguro = $50,000 × 0.025 = $1,250
- Paquete 7kg, valor $80,000  → Seguro = $80,000 × 0.030 = $2,400
- Paquete 12kg, valor $100,000 → Seguro = $100,000 × 0.040 = $4,000
```

### 5.4 Importación Masiva de Tarifas

**Herramienta:** `RateImportService.php`

**Formato CSV esperado:**

**Para transportadoras POR KG:**
```csv
ciudad,precio_kg
Bogotá,2500
Medellín,3200
Cali,2800
```

**Para transportadoras POR RANGO:**
```csv
ciudad,min_peso,max_peso,precio
Bogotá,0,1,8500
Bogotá,1,3,12000
Bogotá,3,5,15500
Medellín,0,1,9000
Medellín,1,3,13000
```

**Proceso:**
1. Admin sube archivo CSV
2. Sistema normaliza nombres de ciudades
3. Busca coincidencias en tabla `city`
4. Inserta/actualiza tarifas
5. Genera reporte de importación

---

## 6. CÁLCULO DE COSTOS ADICIONALES

### 6.1 Costo de Empaque

#### 6.1.1 Configuración Global

**Ubicación:** `shipping_config` → `name='Empaque'` con `id_carrier=0`

**Valor:** Porcentaje sobre el costo base del envío (ej: 5%)

#### 6.1.2 Fórmula

```
Costo Empaque = Costo Base Envío × (Porcentaje Empaque / 100)
```

#### 6.1.3 Ejemplo

```
Configuración:
- Porcentaje empaque = 5%

Cálculo:
- Costo base envío = $25,000
- Costo empaque = $25,000 × 0.05 = $1,250

Total parcial = $25,000 + $1,250 = $26,250
```

#### 6.1.4 Implementación

```php
private function calculatePackagingCost($shippingCost)
{
    $row = Db::getInstance()->getRow("
        SELECT value_number
        FROM "._DB_PREFIX_."shipping_config
        WHERE name = 'Empaque' 
        AND (id_carrier = 0 OR id_carrier IS NULL)
    ");

    if ($row && isset($row['value_number'])) {
        $percent = (float)$row['value_number'];
        return $shippingCost * ($percent / 100);
    }

    return 0.0;
}
```

### 6.2 Costo de Seguro

#### 6.2.1 Valor Declarado del Paquete

**Cálculo:**
```
Para cada paquete:
  Valor Declarado = Σ (Precio Unitario × Cantidad de cada producto en el paquete)
```

**Ejemplo:**
```
Paquete contiene:
- 3 camisetas × $25,000 = $75,000
- 2 libros × $40,000 = $80,000

Valor declarado = $75,000 + $80,000 = $155,000
```

#### 6.2.2 Aplicación Según Tipo de Transportadora

**A. Transportadora POR KG:**
- Busca rango según **valor declarado**
- Aplica valor fijo o porcentaje según configuración

**B. Transportadora POR RANGO:**
- Busca rango según **peso del paquete**
- Aplica porcentaje sobre valor declarado

#### 6.2.3 Ejemplo Completo

**Escenario:**
```
Transportadora: Coordinadora (por kg)
Paquete: 5 kg, valor declarado $120,000

Configuración seguro:
- min=0, max=50000, value=2000      → Fijo $2,000
- min=50000, max=100000, value=2.5  → 2.5%
- min=100000, max=0, value=3.5      → 3.5%

Aplicación:
$120,000 cae en rango min=100000, max=0
value=3.5 < 100 → Es porcentaje
Seguro = $120,000 × 0.035 = $4,200
```

---

## 7. APLICACIÓN DE IVA

### 7.1 Configuración del IVA

**Ubicación:** Configuración global del módulo (`SHIPPING_CALCULATOR_VAT_PERCENT`)

**Valor por defecto:** 19%

**Modificación:** `BackOffice → Calculadora de Envíos → Configuración → Porcentaje IVA`

### 7.2 Momento de Aplicación

**Punto crítico:** El IVA se aplica en el método `calculateShipping()` que es llamado por PrestaShop durante el checkout.

### 7.3 Fórmula

```
Total sin IVA = Costo Base + Empaque + Seguro
Multiplicador IVA = 1 + (IVA% / 100)
Total con IVA = Total sin IVA × Multiplicador IVA
Total con IVA = ROUND(Total con IVA, 2)
```

### 7.4 Ejemplo

```
Configuración:
- IVA = 19%

Cálculo:
- Costo base = $25,000
- Empaque (5%) = $1,250
- Seguro = $4,200
- Subtotal = $30,450

Multiplicador = 1 + (19/100) = 1.19
Total con IVA = $30,450 × 1.19 = $36,235.50
Total redondeado = $36,235.50
```

### 7.5 Implementación

```php
private function calculateShipping()
{
    // ... lógica de cotización ...
    
    $totalCost = (float)$quoteResult['grand_total'];

    // Incluir IVA en el checkout usando configuración
    $vatPercent = (float)Configuration::get('SHIPPING_CALCULATOR_VAT_PERCENT', 19.0);
    $vatMultiplier = 1 + ($vatPercent / 100);
    $totalWithTax = round($totalCost * $vatMultiplier, 2);

    return $totalWithTax;
}
```

### 7.6 Comportamiento en el Checkout

**PrestaShop recibe:**
- Precio **con IVA ya incluido**
- El carrier se crea con `id_tax_rules_group = 0` (sin reglas de impuesto adicionales)
- Evita doble imposición

---

## 8. FLUJO COMPLETO DE COTIZACIÓN

### 8.1 Proceso General

```
1. Usuario llega al Checkout
   ↓
2. PrestaShop llama: getOrderShippingCost()
   ↓
3. Módulo ejecuta: calculateShipping()
   ↓
4. Validaciones iniciales:
   - ¿Hay dirección de entrega?
   - ¿La ciudad tiene cobertura?
   - ¿Hay productos en el carrito?
   ↓
5. Clasificación de productos (agrupados, individuales agrupables, individuales)
   ↓
6. Generación de paquetes según estrategia
   ↓
7. Cotización por paquete con todas las transportadoras
   ↓
8. Selección del cheapest (más económico) por paquete
   ↓
9. Suma de costos + empaque + seguro
   ↓
10. Aplicación de IVA
    ↓
11. Retorno del costo final a PrestaShop
    ↓
12. PrestaShop muestra el carrier con el precio
```

### 8.2 Desglose Detallado por Servicio

#### 8.2.1 ShippingQuoteService::quoteMultipleWithGrouped()

**Entrada:**
```php
$items = [
    [
        'id_product' => 123,
        'qty' => 5,
        'is_grouped' => 1,
        'max_units_per_package' => 10
    ],
    // ... más productos
];
$id_city = 1515; // Bogotá
```

**Proceso:**

**Paso 1: Obtener factor volumétrico mínimo**
```php
$maxVolumetricFactor = $this->getMaxVolumetricFactor();
// Retorna el factor MÁS BAJO de todas las transportadoras activas
```

**Paso 2: Separar productos por tipo**
```php
$groupedProducts = [];             // is_grouped=1
$individualGroupableProducts = []; // is_grouped=0 && max_units>0
$individualNonGroupableProducts = []; // is_grouped=0 && max_units=0
```

**Paso 3: Procesar productos agrupados**
```php
$groupedService = new ShippingGroupedPackageService();
$groupedResult = $groupedService->buildGroupedPackages($groupedProducts, $maxVolumetricFactor);

// Retorna:
// - grouped_packages: Array de paquetes mixtos
// - individual_products: Productos que no cupieron
```

**Paso 4: Cotizar paquetes agrupados**
```php
foreach ($groupedResult['grouped_packages'] as $package) {
    $packageWeight = $package['total_weight'];
    $packageValue = calcular_valor_declarado($package['items']);
    
    $quotes = $this->quoteByWeight($packageWeight, $id_city, $packageValue);
    $cheapest = seleccionar_mas_barato($quotes);
    
    $totalGrouped += $cheapest['price'];
}
```

**Paso 5: Procesar individuales agrupables**
```php
$individualGroupableService = new IndividualGroupablePackageService();
$individualResult = $individualGroupableService->buildIndividualPackages(
    $individualGroupableProducts,
    $maxVolumetricFactor
);

// Retorna:
// - individual_packages: Paquetes del mismo producto
// - oversized_products: Productos que exceden peso máximo
```

**Paso 6: Cotizar individuales agrupables**
```php
foreach ($individualResult['individual_packages'] as $package) {
    $quotes = $this->quoteByWeight($package['total_weight'], $id_city, $package['value']);
    $cheapest = seleccionar_mas_barato($quotes);
    
    $totalIndividualGrouped += $cheapest['price'];
}
```

**Paso 7: Cotizar individuales NO agrupables**
```php
foreach ($individualNonGroupableProducts as $product) {
    for ($i = 0; $i < $product['qty']; $i++) {
        $quotes = $this->quoteByWeight($product['weight'], $id_city, $product['price']);
        $cheapest = seleccionar_mas_barato($quotes);
        
        $totalIndividualNonGrouped += $cheapest['price'];
    }
}
```

**Paso 8: Calcular total general**
```php
$grandTotal = $totalGrouped + $totalIndividualGrouped + $totalIndividualNonGrouped;

return [
    'grouped_packages' => [...],
    'individual_grouped_packages' => [...],
    'individual_non_grouped_items' => [...],
    'total_grouped' => $totalGrouped,
    'total_individual_grouped' => $totalIndividualGrouped,
    'total_individual_non_grouped' => $totalIndividualNonGrouped,
    'grand_total' => $grandTotal
];
```

#### 8.2.2 ShippingQuoteService::quoteByWeight()

**Entrada:**
```php
quoteByWeight($weight, $id_city, $declaredValue)
```

**Proceso:**

**Paso 1: Obtener transportadoras con cobertura**
```php
$carriers = $this->getCarriersWithCityCoverage($id_city);
// Retorna transportadoras que tienen tarifas para esa ciudad
```

**Paso 2: Cotizar con cada transportadora**
```php
foreach ($carriers as $carrier) {
    $type = $carrier['type']; // 'per_kg' o 'range'
    
    if ($type === 'per_kg') {
        $basePrice = $this->calculatePerKg($carrier['id'], $id_city, $weight);
    } else {
        $basePrice = $this->calculateRange($carrier['id'], $id_city, $weight);
    }
    
    if ($basePrice === null) continue; // Sin cobertura
    
    // Agregar empaque
    $packagingCost = $this->calculatePackagingCost($basePrice);
    
    // Agregar seguro
    $insuranceCost = $this->calculateInsuranceCost(
        $carrier['id'], 
        $type, 
        $weight, 
        $declaredValue
    );
    
    $totalPrice = $basePrice + $packagingCost + $insuranceCost;
    
    $quotes[] = [
        'carrier' => $carrier['name'],
        'price' => $totalPrice,
        'base_price' => $basePrice,
        'packaging' => $packagingCost,
        'insurance' => $insuranceCost
    ];
}
```

**Paso 3: Ordenar por precio**
```php
usort($quotes, function($a, $b) {
    return $a['price'] <=> $b['price'];
});

return $quotes; // [0] es el más barato
```

---

## 9. REGLAS DE VALIDACIÓN

### 9.1 Validación en el Checkout

**Condiciones para mostrar el carrier:**

```php
// 1. Debe existir dirección de entrega
if (!$cart->id_address_delivery) {
    return 0; // Muestra "Por calcular"
}

// 2. La dirección debe tener ciudad válida
$address = new Address($cart->id_address_delivery);
if (!Validate::isLoadedObject($address) || empty($address->city)) {
    return 0; // Muestra "Por calcular"
}

// 3. La ciudad debe existir en la BD y tener cobertura
$cityRow = buscar_ciudad_en_bd($address->city);
if (!$cityRow) {
    return 0; // Muestra "Por calcular"
}

// 4. Debe haber productos en el carrito
$products = $cart->getProducts();
if (empty($products)) {
    return 0;
}

// 5. La cotización debe retornar un valor válido
$quoteResult = $quoteService->quoteMultipleWithGrouped($items, $id_city);
if (!is_array($quoteResult) || 
    !isset($quoteResult['grand_total']) || 
    $quoteResult['grand_total'] <= 0) {
    return false; // OCULTA el carrier
}

// 6. Si todo OK, retornar precio con IVA
return $totalWithTax;
```

### 9.2 Validación de Pesos

**Regla de peso mínimo:**
```php
if ($weight <= 0) {
    $weight = 0.1; // Peso mínimo 100 gramos
}
```

**Validación de dimensiones:**
```php
if ($height <= 0 || $width <= 0 || $depth <= 0) {
    $volumetricWeight = 0; // No se puede calcular peso volumétrico
}
```

### 9.3 Validación de Cobertura

**Transportadora POR KG:**
```sql
SELECT COUNT(*) FROM shipping_per_kg_rate
WHERE id_carrier = ? 
  AND id_city = ? 
  AND active = 1
```

**Transportadora POR RANGO:**
```sql
SELECT COUNT(*) FROM shipping_range_rate
WHERE id_carrier = ? 
  AND id_city = ? 
  AND active = 1
```

**Regla:** Si no hay registros activos → Esa transportadora NO cotiza para esa ciudad.

---

## 10. CASOS ESPECIALES Y EXCEPCIONES

### 10.1 Productos Sin Configuración de Empaquetado

**Escenario:** Producto nuevo sin registro en `shipping_product`

**Comportamiento:**
```php
$groupedRow = Db::getInstance()->getRow("
    SELECT is_grouped, max_units_per_package
    FROM "._DB_PREFIX_."shipping_product
    WHERE id_product = ".(int)$id_product
);

if (!$groupedRow) {
    // Valores por defecto
    $is_grouped = 0; // Individual
    $max_units = 0;  // NO agrupable
}
```

**Resultado:** Se trata como **individual NO agrupable** (cada unidad por separado).

### 10.2 Productos Oversized (Exceden Peso Máximo)

**Definición:** Producto cuyo peso unitario > `max_package_weight`

**Detección:**
```php
if ($weightPerUnit > $this->maxWeightPerPackage) {
    $oversizedProducts[] = [
        'id_product' => $id_product,
        'quantity' => $quantity,
        'reason' => 'unit_exceeds_max_weight',
        'unit_weight' => $weightPerUnit
    ];
    continue; // No se procesa normalmente
}
```

**Tratamiento:**
- Se marcan en el array `oversized_products`
- Pueden requerir cotización manual o transportadoras especiales
- Actualmente se cotizan individualmente como **NO agrupables**

### 10.3 Ciudades Sin Cobertura

**Escenario:** Ciudad no existe en BD o ninguna transportadora tiene tarifas

**Comportamiento:**
```php
$cityRow = Db::getInstance()->getRow("
    SELECT id_city FROM city WHERE name LIKE '".pSQL($cityName)."'
");

if (!$cityRow) {
    return 0; // Muestra mensaje "Por calcular"
}

$carriers = $this->getCarriersWithCityCoverage($id_city);
if (empty($carriers)) {
    return false; // OCULTA el carrier (sin cobertura)
}
```

### 10.4 Múltiples Transportadoras (Selección del Cheapest)

**Lógica:** Para cada paquete, se cotiza con TODAS las transportadoras activas y se selecciona la más económica.

**Ejemplo:**
```
Paquete: 8 kg a Bogotá

Cotizaciones:
- Coordinadora: $28,500
- Servientrega: $32,000
- Interrapidísimo: $27,800
- Deprisa: $31,200

Seleccionado: Interrapidísimo ($27,800) ✅
```

**Ventaja:** Optimización automática de costos por paquete.

### 10.5 Carritos con Productos de Tipos Mixtos

**Escenario:** Carrito con productos agrupados + individuales agrupables + individuales NO agrupables

**Ejemplo:**
```
Carrito:
1. Camisetas (agrupadas) × 10
2. Botellas vino (individual agrupable) × 6
3. Televisor (individual NO agrupable) × 1

Procesamiento:
- Paso 1: Empacar camisetas en paquetes agrupados
- Paso 2: Empacar botellas en sus propios paquetes (máx 6 por paquete)
- Paso 3: Cada televisor = 1 paquete

Resultado:
- Paquete 1: 10 camisetas (agrupado)
- Paquete 2: 6 botellas (individual agrupable)
- Paquete 3: 1 televisor (individual NO agrupable)

Total: 3 paquetes, cada uno cotizado independientemente
```

### 10.6 Manejo de Errores y Excepciones

**Try-Catch en calculateShipping():**
```php
try {
    // ... toda la lógica de cotización ...
    return $totalWithTax;
} catch (Exception $e) {
    // Log del error (opcional)
    return false; // Oculta el carrier
}
```

**Resultado:** Si ocurre cualquier error, el carrier simplemente no aparece en el checkout.

---

## 📊 RESUMEN EJECUTIVO

### Puntos Clave del Sistema

1. **Clasificación Inteligente:** 3 tipos de productos según empaquetado
2. **Optimización de Paquetes:** Algoritmo Best-Fit minimiza costos
3. **Flexibilidad de Tarifas:** Soporta por kg y por rangos
4. **Cálculo Preciso:** Peso real vs volumétrico (siempre el mayor)
5. **Costos Transparentes:** Base + Empaque + Seguro + IVA
6. **Cobertura Geográfica:** Ciudad por ciudad, transportadora por transportadora
7. **Selección Óptima:** Cheapest automático por paquete

### Configuraciones Críticas

| Configuración | Ubicación | Impacto |
|---------------|-----------|---------|
| Peso máximo por paquete | Config Global | Límite de agrupación |
| Porcentaje empaque | Config Global | Costo adicional fijo |
| Porcentaje IVA | Config Global | Impuesto final |
| Factor volumétrico | Por transportadora | Cálculo peso volumétrico |
| Flete mínimo | Por transportadora (kg) | Precio mínimo de envío |
| Kilos mínimo | Por transportadora (kg) | Peso mínimo a cobrar |
| Rangos de seguro | Por transportadora | Costo de seguro |
| is_grouped | Por producto | Estrategia de empaquetado |
| max_units_per_package | Por producto | Límite de agrupación |

### Fórmulas Maestras

**Peso Facturable:**
```
MAX(Peso Real, Peso Volumétrico, Kilos Mínimo*)
* Solo para transportadoras por kg
```

**Costo Final:**
```
(Σ Costos Base de Paquetes + Empaque + Seguro) × (1 + IVA%)
```

**Peso Volumétrico:**
```
(Largo × Ancho × Alto en metros) × Factor Volumétrico
```

---

## 🔧 CONFIGURACIÓN RECOMENDADA INICIAL

### Para Comenzar a Usar el Sistema

1. **Configuración Global:**
   - Peso máximo por paquete: **60 kg**
   - Porcentaje empaque: **5%**
   - Porcentaje IVA: **19%**

2. **Registrar Transportadoras:**
   - Mínimo 2 transportadoras para comparación
   - Asignar tipo (por kg o por rango) según su estructura real

3. **Configurar Factores Volumétricos:**
   - Estándar: **5000** para terrestre
   - Consultar con cada transportadora su factor real

4. **Importar Tarifas:**
   - Preparar CSV con ciudades y tarifas
   - Importar usando el panel de administración
   - Verificar cobertura

5. **Configurar Productos:**
   - Revisar catálogo completo
   - Asignar `is_grouped` y `max_units_per_package`
   - Validar pesos y dimensiones

6. **Configurar Seguros:**
   - Definir rangos según políticas de cada transportadora
   - Validar porcentajes

7. **Pruebas:**
   - Crear pedidos de prueba con diferentes combinaciones
   - Verificar cálculos en backoffice usando cotizador
   - Validar en checkout

---

## 📞 SOPORTE Y MANTENIMIENTO

### Validaciones Periódicas

- **Mensual:** Actualizar tarifas según cambios de transportadoras
- **Trimestral:** Revisar factores volumétricos
- **Anual:** Auditar configuración de productos

### Troubleshooting Común

**Problema:** Carrier no aparece en checkout
**Solución:** Verificar cobertura de ciudad y que `grand_total > 0`

**Problema:** Precios muy altos
**Solución:** Revisar peso volumétrico y factor configurado

**Problema:** Productos no se agrupan
**Solución:** Verificar configuración `is_grouped` y peso máximo

---

**Documento generado:** Diciembre 29, 2025  
**Versión del módulo:** 1.0.0  
**Estado:** Producción
