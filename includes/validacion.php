<?php

function validar_decimal_no_negativo($valor, $maximo = 99999999.99)
{
    if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
        return false;
    }

    $texto = trim((string)$valor);
    if (!preg_match('/^[+]?(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/D', $texto)) {
        return false;
    }

    $numero = (float)$texto;
    return is_finite($numero) && $numero >= 0 && $numero <= $maximo ? $numero : false;
}

function validar_precio_producto($valor)
{
    $precio = validar_decimal_no_negativo($valor);
    return $precio !== false && $precio > 0 ? $precio : false;
}

function validar_texto($valor, $maximo, $obligatorio = true)
{
    if (!is_string($valor) || !preg_match('//u', $valor) || strpos($valor, "\0") !== false) {
        return false;
    }

    $texto = trim($valor);
    if ($obligatorio && $texto === "") {
        return false;
    }

    return preg_match_all('/./us', $valor) <= $maximo;
}

function validar_contrasena_nueva($valor)
{
    return is_string($valor) && strlen($valor) >= 8 && strlen($valor) <= 72 && strpos($valor, "\0") === false;
}

function validar_importe_calculado($valor)
{
    return (is_int($valor) || is_float($valor)) && is_finite((float)$valor)
        && $valor >= 0 && round($valor, 2) <= 99999999.99;
}

function validar_id_positivo($valor)
{
    if (!is_string($valor) && !is_int($valor)) {
        return false;
    }
    if (!preg_match('/^[0-9]+$/D', (string)$valor)) {
        return false;
    }
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 && $id <= 2147483647 ? $id : false;
}

function validar_lineas_pedido($productos)
{
    if (!is_array($productos) || !$productos || !array_is_list($productos) || count($productos) > 10000) {
        return false;
    }
    $ids = array();
    $lineas = array();
    foreach ($productos as $producto) {
        if (!is_array($producto) || array_diff(array_keys($producto), array("id_producto", "cantidad"))) {
            return false;
        }
        $id = validar_id_positivo($producto["id_producto"] ?? null);
        $cantidad = validar_id_positivo($producto["cantidad"] ?? null);
        if ($id === false || $cantidad === false || isset($ids[$id])) {
            return false;
        }
        $ids[$id] = true;
        $lineas[] = array("id_producto" => $id, "cantidad" => $cantidad);
    }
    return $lineas;
}
