<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Ajustes;
use App\Core\Auth;
use App\Core\DB;
use App\Core\ErrorNegocio;
use App\Core\Reloj;
use App\Services\CajaService;
use App\Services\CobranzaService;
use App\Services\ComisionService;
use App\Services\CompraService;
use App\Services\CuentaCorriente;
use App\Services\FinanzasService;
use App\Services\PedidoService;
use App\Services\PrecioService;
use App\Services\RepartoService;
use App\Services\StockService;
use App\Services\VentaService;

/**
 * Datos de demostración realistas de una distribuidora salteña. Se generan con los
 * servicios del sistema (no con SQL suelto), simulando ~4 semanas de operación hasta hoy,
 * para que stock, cuentas corrientes, caja y reportes sean coherentes entre sí.
 */
final class DatosDemo
{
    private static array $u = [];
    private static array $prov = [];
    private static array $prod = [];
    private static array $cli = [];
    private static $log;

    public const USUARIOS = [
        // usuario => [nombre, rol, clave]
        'admin' => ['Administrador', 'admin', 'admin123'],
        'oficina' => ['Mariela Gutiérrez', 'encargado', 'oficina123'],
        'mostrador' => ['Lucas Fernández', 'mostrador', 'mostrador123'],
        'preventista1' => ['Diego Choque', 'preventista', 'preventista123'],
        'preventista2' => ['Carla Mamaní', 'preventista', 'preventista123'],
        'deposito' => ['Ramón Cruz', 'deposito', 'deposito123'],
        'chofer1' => ['Sergio Tolaba', 'chofer', 'chofer123'],
        'chofer2' => ['Julio Guaymás', 'chofer', 'chofer123'],
        'contador' => ['Cdora. Silvia Arias', 'contador', 'contador123'],
    ];

    public static function cargar(?callable $log = null): void
    {
        self::$log = $log ?? fn () => null;
        mt_srand(20251031);
        $inicio = strtotime(date('Y-m-d', time()) . ' 08:00:00') - 35 * 86400;
        Reloj::fijar($inicio);
        Ajustes::limpiar();
        Ajustes::set('credito_modo', 'advertir');

        self::usuarios();
        self::como('admin');
        self::catalogo();
        self::clientes();
        self::comprasIniciales();
        self::simularDias();
        self::extras();
        Reloj::liberar();
        $_SESSION = [];
        Auth::refrescar();
    }

    private static function log(string $m): void
    {
        (self::$log)($m);
    }

    private static function como(string $usuario): void
    {
        $_SESSION['usuario_id'] = self::$u[$usuario];
        Auth::refrescar();
    }

    private static function hora(int $dia, string $hhmm): void
    {
        Reloj::fijar(strtotime(date('Y-m-d', time() + $dia * 86400) . ' ' . $hhmm . ':' . sprintf('%02d', mt_rand(0, 59))));
    }

    // ------------------------------------------------------------------ usuarios

    private static function usuarios(): void
    {
        $roles = DB::pares('SELECT slug, id FROM roles');
        foreach (self::USUARIOS as $usuario => [$nombre, $rol, $clave]) {
            $id = DB::valor('SELECT id FROM usuarios WHERE usuario = ?', [$usuario]);
            if (!$id) {
                $id = DB::insertar('usuarios', [
                    'nombre' => $nombre, 'usuario' => $usuario, 'email' => $usuario . '@distribuidoratoro.com.ar',
                    'password_hash' => password_hash($clave, PASSWORD_DEFAULT), 'rol_id' => $roles[$rol],
                    'caja_id' => $rol === 'mostrador' ? 2 : 1, 'activo' => 1, 'creado_en' => ahora(),
                ]);
            }
            self::$u[$usuario] = (int) $id;
        }
        self::log('Usuarios demo creados');
    }

    // ------------------------------------------------------------------ catálogo

    private static function cuit(string $base): string
    {
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $s = 0;
        for ($i = 0; $i < 10; $i++) {
            $s += (int) $base[$i] * $pesos[$i];
        }
        $dv = 11 - $s % 11;
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);
        return substr($base, 0, 2) . '-' . substr($base, 2, 8) . '-' . $dv;
    }

    private static function ean(int $n): string
    {
        $base = '779' . str_pad((string) $n, 9, '0', STR_PAD_LEFT);
        $s = 0;
        for ($i = 0; $i < 12; $i++) {
            $s += (int) $base[$i] * ($i % 2 ? 3 : 1);
        }
        return $base . ((10 - $s % 10) % 10);
    }

    private static function catalogo(): void
    {
        $proveedores = [
            ['Golosinas del Norte S.R.L.', 'Golonorte', '3070123456', 'Av. Tavella 3100', 'Salta', '(0387) 424-1100', 'Hernán Paz', 15],
            ['Bebidas del Norte S.A.', 'BebiNorte', '3071234567', 'Ruta 9 km 1605', 'Salta', '(0387) 425-2200', 'Paola Ríos', 7],
            ['Tabacalera Andina S.A.', 'Tabacalera Andina', '3069876543', 'Av. Paraguay 2450', 'Salta', '(0387) 427-3300', 'Martín Lobo', 0],
            ['Mayorista Salteño de Almacén S.A.', 'Mayorista Salteño', '3070555123', 'Av. Chile 1840', 'Salta', '(0387) 431-4400', 'Gabriela Soria', 21],
            ['Limpieza y Perfumería NOA S.R.L.', 'LyP NOA', '3071999888', 'Av. Independencia 950', 'Salta', '(0387) 439-5500', 'Ricardo Vera', 30],
        ];
        foreach ($proveedores as $i => [$razon, $fantasia, $cuit, $dir, $loc, $tel, $contacto, $plazo]) {
            self::$prov[$i + 1] = DB::insertar('proveedores', [
                'razon_social' => $razon, 'nombre_fantasia' => $fantasia, 'cuit' => self::cuit($cuit), 'condicion_iva' => 'RI',
                'direccion' => $dir, 'localidad' => $loc, 'telefono' => $tel, 'email' => 'ventas@' . strtolower(preg_replace('/\W/', '', $fantasia)) . '.com.ar',
                'contacto' => $contacto, 'plazo_pago_dias' => $plazo, 'activo' => 1, 'creado_en' => ahora(),
            ]);
        }
        $rubros = [];
        foreach (['Golosinas', 'Bebidas', 'Galletitas', 'Cigarrillos', 'Almacén', 'Limpieza', 'Perfumería', 'Descartables'] as $r) {
            $rubros[$r] = DB::insertar('rubros', ['nombre' => $r, 'activo' => 1]);
        }
        // [descripción, rubro, marca, proveedor, unidad, costo, iva, internos, presentaciones, lotes]
        $productos = [
            ['Alfajor Guaymallén dulce de leche 38 g', 'Golosinas', 'Guaymallén', 1, 'Unidad', 210, 21, 0, [['Display', 40], ['Caja', 480]], 1],
            ['Alfajor Jorgito chocolate 55 g', 'Golosinas', 'Jorgito', 1, 'Unidad', 380, 21, 0, [['Display', 12], ['Caja', 144]], 1],
            ['Alfajor Fantoche triple 85 g', 'Golosinas', 'Fantoche', 1, 'Unidad', 520, 21, 0, [['Display', 12], ['Caja', 120]], 1],
            ['Alfajor Milka Oreo 61 g', 'Golosinas', 'Milka', 1, 'Unidad', 690, 21, 0, [['Display', 12], ['Caja', 72]], 1],
            ['Alfajor Terrabusi clásico 50 g', 'Golosinas', 'Terrabusi', 1, 'Unidad', 470, 21, 0, [['Display', 6], ['Caja', 72]], 1],
            ['Chicle Beldent menta 10 u.', 'Golosinas', 'Beldent', 1, 'Unidad', 280, 21, 0, [['Display', 20], ['Caja', 400]], 0],
            ['Caramelos Sugus confitados 700 g', 'Golosinas', 'Arcor', 1, 'Bolsa', 3800, 21, 0, [['Caja', 12]], 0],
            ['Chupetín Pico Dulce', 'Golosinas', 'Arcor', 1, 'Unidad', 95, 21, 0, [['Display', 24], ['Caja', 576]], 0],
            ['Turrón Arcor 25 g', 'Golosinas', 'Arcor', 1, 'Unidad', 180, 21, 0, [['Display', 50], ['Caja', 600]], 1],
            ['Bombón Bon o Bon leche 15 g', 'Golosinas', 'Arcor', 1, 'Unidad', 160, 21, 0, [['Display', 30], ['Caja', 360]], 1],
            ['Chocolate Milka leche 55 g', 'Golosinas', 'Milka', 1, 'Unidad', 1050, 21, 0, [['Display', 12], ['Caja', 144]], 0],
            ['Mantecol 41 g', 'Golosinas', 'Mantecol', 1, 'Unidad', 450, 21, 0, [['Display', 20], ['Caja', 240]], 1],
            ['Chicle Topline menta 7 u.', 'Golosinas', 'Topline', 1, 'Unidad', 310, 21, 0, [['Display', 20], ['Caja', 400]], 0],
            ['Pastillas Halls mentol 9 u.', 'Golosinas', 'Halls', 1, 'Unidad', 300, 21, 0, [['Display', 12], ['Caja', 288]], 0],
            ['Rocklets chocolate 40 g', 'Golosinas', 'Arcor', 1, 'Unidad', 520, 21, 0, [['Display', 18], ['Caja', 216]], 0],
            ['Gomitas Mogul 30 g', 'Golosinas', 'Arcor', 1, 'Unidad', 350, 21, 0, [['Display', 12], ['Caja', 288]], 0],
            ['Coca-Cola 500 ml', 'Bebidas', 'Coca-Cola', 2, 'Botella', 620, 21, 25, [['Pack', 12]], 0],
            ['Coca-Cola 2,25 L', 'Bebidas', 'Coca-Cola', 2, 'Botella', 1450, 21, 60, [['Pack', 8]], 0],
            ['Coca-Cola Zero 500 ml', 'Bebidas', 'Coca-Cola', 2, 'Botella', 620, 21, 25, [['Pack', 12]], 0],
            ['Sprite 1,5 L', 'Bebidas', 'Sprite', 2, 'Botella', 1100, 21, 45, [['Pack', 8]], 0],
            ['Fanta naranja 500 ml', 'Bebidas', 'Fanta', 2, 'Botella', 600, 21, 25, [['Pack', 12]], 0],
            ['Agua Villavicencio sin gas 500 ml', 'Bebidas', 'Villavicencio', 2, 'Botella', 380, 21, 0, [['Pack', 12]], 0],
            ['Agua saborizada Levité pomelo 1,5 L', 'Bebidas', 'Levité', 2, 'Botella', 900, 21, 20, [['Pack', 6]], 0],
            ['Jugo Cepita naranja 1 L', 'Bebidas', 'Cepita', 2, 'Unidad', 1200, 21, 0, [['Pack', 8]], 1],
            ['Energizante Speed 250 ml', 'Bebidas', 'Speed', 2, 'Lata', 780, 21, 30, [['Pack', 24]], 0],
            ['Cerveza Salta rubia lata 473 ml', 'Bebidas', 'Cerveza Salta', 2, 'Lata', 900, 21, 40, [['Pack', 24]], 0],
            ['Gatorade manzana 500 ml', 'Bebidas', 'Gatorade', 2, 'Botella', 850, 21, 20, [['Pack', 6]], 0],
            ['Pepsi 1,5 L', 'Bebidas', 'Pepsi', 2, 'Botella', 1000, 21, 40, [['Pack', 8]], 0],
            ['Galletitas Oreo 118 g', 'Galletitas', 'Oreo', 1, 'Paquete', 720, 21, 0, [['Caja', 30]], 1],
            ['Galletitas Pepitos 119 g', 'Galletitas', 'Pepitos', 1, 'Paquete', 700, 21, 0, [['Caja', 30]], 1],
            ['Galletitas Criollitas 100 g', 'Galletitas', 'Bagley', 1, 'Paquete', 560, 21, 0, [['Caja', 40]], 1],
            ['Galletitas Express 101 g', 'Galletitas', 'Terrabusi', 1, 'Paquete', 540, 21, 0, [['Caja', 40]], 1],
            ['Galletitas Chocolinas 170 g', 'Galletitas', 'Bagley', 1, 'Paquete', 900, 21, 0, [['Caja', 24]], 1],
            ['Galletitas Sonrisas 118 g', 'Galletitas', 'Bagley', 1, 'Paquete', 620, 21, 0, [['Caja', 36]], 1],
            ['Bizcochitos 9 de Oro clásicos 200 g', 'Galletitas', '9 de Oro', 1, 'Paquete', 850, 21, 0, [['Caja', 20]], 1],
            ['Obleas Opera 55 g', 'Galletitas', 'Terrabusi', 1, 'Unidad', 420, 21, 0, [['Display', 20], ['Caja', 240]], 1],
            ['Cigarrillos Marlboro box 20', 'Cigarrillos', 'Marlboro', 3, 'Atado', 1150, 21, 1600, [['Cartón', 10]], 0],
            ['Cigarrillos Philip Morris box 20', 'Cigarrillos', 'Philip Morris', 3, 'Atado', 1050, 21, 1450, [['Cartón', 10]], 0],
            ['Cigarrillos Lucky Strike 20', 'Cigarrillos', 'Lucky Strike', 3, 'Atado', 1000, 21, 1400, [['Cartón', 10]], 0],
            ['Cigarrillos Chesterfield 20', 'Cigarrillos', 'Chesterfield', 3, 'Atado', 800, 21, 1100, [['Cartón', 10]], 0],
            ['Cigarrillos Camel 20', 'Cigarrillos', 'Camel', 3, 'Atado', 1000, 21, 1400, [['Cartón', 10]], 0],
            ['Encendedor Bic maxi', 'Cigarrillos', 'Bic', 3, 'Unidad', 650, 21, 0, [['Display', 50]], 0],
            ['Yerba mate Taragüí 1 kg', 'Almacén', 'Taragüí', 4, 'Paquete', 2900, 21, 0, [['Fardo', 10]], 0],
            ['Yerba mate Playadito 500 g', 'Almacén', 'Playadito', 4, 'Paquete', 1700, 21, 0, [['Fardo', 20]], 0],
            ['Azúcar Ledesma 1 kg', 'Almacén', 'Ledesma', 4, 'Paquete', 900, 21, 0, [['Fardo', 10]], 0],
            ['Fideos Lucchetti tirabuzón 500 g', 'Almacén', 'Lucchetti', 4, 'Paquete', 780, 21, 0, [['Bulto', 20]], 0],
            ['Arroz Gallo Oro 1 kg', 'Almacén', 'Gallo', 4, 'Paquete', 1500, 21, 0, [['Bulto', 10]], 0],
            ['Harina 000 Pureza 1 kg', 'Almacén', 'Pureza', 4, 'Paquete', 650, 10.5, 0, [['Bulto', 10]], 0],
            ['Aceite de girasol Natura 900 ml', 'Almacén', 'Natura', 4, 'Botella', 1800, 21, 0, [['Caja', 12]], 0],
            ['Mayonesa Hellmann\'s 237 g', 'Almacén', 'Hellmann\'s', 4, 'Unidad', 950, 21, 0, [['Caja', 24]], 1],
            ['Puré de tomate Arcor 520 g', 'Almacén', 'Arcor', 4, 'Unidad', 700, 21, 0, [['Caja', 24]], 1],
            ['Té La Virginia 25 saquitos', 'Almacén', 'La Virginia', 4, 'Caja', 550, 21, 0, [['Bulto', 12]], 0],
            ['Café La Virginia instantáneo 170 g', 'Almacén', 'La Virginia', 4, 'Frasco', 3500, 21, 0, [['Caja', 12]], 0],
            ['Caldo Knorr gallina 12 u.', 'Almacén', 'Knorr', 4, 'Caja', 820, 21, 0, [['Bulto', 24]], 0],
            ['Mermelada Arcor durazno 454 g', 'Almacén', 'Arcor', 4, 'Frasco', 1100, 21, 0, [['Caja', 12]], 1],
            ['Atún La Campagnola 170 g', 'Almacén', 'La Campagnola', 4, 'Lata', 1900, 21, 0, [['Caja', 48]], 0],
            ['Lavandina Ayudín 1 L', 'Limpieza', 'Ayudín', 5, 'Botella', 750, 21, 0, [['Caja', 12]], 0],
            ['Detergente Magistral limón 500 ml', 'Limpieza', 'Magistral', 5, 'Botella', 1500, 21, 0, [['Caja', 12]], 0],
            ['Jabón en polvo Ala 800 g', 'Limpieza', 'Ala', 5, 'Paquete', 2300, 21, 0, [['Caja', 10]], 0],
            ['Limpiador Cif crema 375 ml', 'Limpieza', 'Cif', 5, 'Unidad', 1400, 21, 0, [['Caja', 12]], 0],
            ['Esponja Mortimer doble uso', 'Limpieza', 'Mortimer', 5, 'Unidad', 350, 21, 0, [['Display', 12]], 0],
            ['Papel higiénico Higienol 4 x 30 m', 'Limpieza', 'Higienol', 5, 'Paquete', 1300, 21, 0, [['Bulto', 12]], 0],
            ['Rollo de cocina Sussex 3 u.', 'Limpieza', 'Sussex', 5, 'Paquete', 1200, 21, 0, [['Bulto', 12]], 0],
            ['Shampoo Sedal 340 ml', 'Perfumería', 'Sedal', 5, 'Unidad', 2600, 21, 0, [['Caja', 12]], 0],
            ['Desodorante Rexona aerosol 150 ml', 'Perfumería', 'Rexona', 5, 'Unidad', 2400, 21, 0, [['Caja', 12]], 0],
            ['Pasta dental Colgate 90 g', 'Perfumería', 'Colgate', 5, 'Unidad', 1100, 21, 0, [['Caja', 12]], 0],
            ['Jabón de tocador Dove 90 g', 'Perfumería', 'Dove', 5, 'Unidad', 950, 21, 0, [['Caja', 48]], 0],
            ['Toallitas Always x 8', 'Perfumería', 'Always', 5, 'Paquete', 1400, 21, 0, [['Caja', 12]], 0],
            ['Afeitadora Gillette Prestobarba', 'Perfumería', 'Gillette', 5, 'Unidad', 700, 21, 0, [['Display', 12]], 0],
            ['Vasos plásticos 220 cc x 50', 'Descartables', 'Darnel', 4, 'Paquete', 1300, 21, 0, [['Bulto', 20]], 0],
            ['Bolsas de residuos 50x70 x 10', 'Descartables', 'Rolls', 4, 'Paquete', 600, 21, 0, [['Bulto', 30]], 0],
            ['Servilletas Elite x 50', 'Descartables', 'Elite', 4, 'Paquete', 650, 21, 0, [['Bulto', 24]], 0],
            ['Platos descartables x 10', 'Descartables', 'Darnel', 4, 'Paquete', 900, 21, 0, [['Bulto', 20]], 0],
            ['Sorbetes x 100', 'Descartables', 'Descartables Norte', 4, 'Paquete', 550, 21, 0, [['Bulto', 50]], 0],
        ];
        $marcas = [];
        $n = 0;
        $ubic = ['Golosinas' => 'A', 'Galletitas' => 'B', 'Bebidas' => 'C', 'Cigarrillos' => 'D', 'Almacén' => 'E', 'Limpieza' => 'F', 'Perfumería' => 'G', 'Descartables' => 'H'];
        foreach ($productos as [$desc, $rubro, $marca, $prov, $unidad, $costo, $iva, $internos, $pres, $lotes]) {
            $n++;
            $marcas[$marca] ??= DB::insertar('marcas', ['nombre' => $marca, 'activo' => 1]);
            $mayor = end($pres)[1];
            $id = DB::insertar('productos', [
                'codigo' => str_pad((string) $n, 5, '0', STR_PAD_LEFT), 'codigo_barras' => self::ean($n * 10),
                'descripcion' => $desc, 'rubro_id' => $rubros[$rubro], 'marca_id' => $marcas[$marca], 'proveedor_id' => self::$prov[$prov],
                'unidad_base' => $unidad, 'costo' => $costo, 'costo_promedio' => $costo, 'iva_alicuota' => $iva, 'impuesto_interno' => $internos,
                'stock_minimo' => $mayor, 'stock_maximo' => $mayor * 6, 'maneja_lotes' => $lotes,
                'ubicacion' => $ubic[$rubro] . '-' . str_pad((string) ($n % 12 + 1), 2, '0', STR_PAD_LEFT), 'activo' => 1, 'creado_en' => ahora(),
            ]);
            DB::insertar('producto_presentaciones', ['producto_id' => $id, 'nombre' => $unidad, 'factor' => 1, 'codigo_barras' => self::ean($n * 10), 'es_venta' => 1, 'es_compra' => 0, 'predeterminada_venta' => count($pres) === 1 && $mayor <= 12 ? 0 : 1, 'activo' => 1]);
            foreach ($pres as $k => [$pn, $pf]) {
                DB::insertar('producto_presentaciones', [
                    'producto_id' => $id, 'nombre' => $pn, 'factor' => $pf, 'codigo_barras' => self::ean($n * 10 + $k + 1),
                    'es_venta' => 1, 'es_compra' => 1, 'predeterminada_venta' => count($pres) === 1 && $mayor <= 12 ? 1 : 0,
                    'predeterminada_compra' => $k === count($pres) - 1 ? 1 : 0, 'activo' => 1,
                ]);
            }
            PrecioService::recalcularProducto($id, 'Alta del producto');
            self::$prod[$id] = ['id' => $id, 'prov' => $prov, 'pres' => $pres, 'costo' => $costo, 'mayor' => $mayor, 'rubro' => $rubro, 'lotes' => $lotes];
        }
        // Algunos precios fijos y promociones
        $ids = array_keys(self::$prod);
        PrecioService::guardarPrecio($ids[16], 3, 1100.0, null, 'Precio sugerido por la marca', 'fijo', null, true);
        DB::insertar('promociones', ['producto_id' => $ids[0], 'descripcion' => 'Llevando 40 te regalamos 2', 'cantidad_compra' => 40, 'cantidad_bonificada' => 2, 'desde' => date('Y-m-d', Reloj::ahora()), 'hasta' => null, 'activo' => 1]);
        DB::insertar('promociones', ['producto_id' => $ids[16], 'descripcion' => 'Pack 12 + 1 de regalo', 'cantidad_compra' => 12, 'cantidad_bonificada' => 1, 'desde' => date('Y-m-d', Reloj::ahora()), 'hasta' => null, 'activo' => 1]);
        self::log(count($productos) . ' productos, 5 proveedores, 8 rubros');
    }

    // ------------------------------------------------------------------ clientes y logística

    private static function clientes(): void
    {
        $roles = DB::pares('SELECT slug, id FROM roles');
        $p1 = DB::insertar('preventistas', ['usuario_id' => self::$u['preventista1'], 'nombre' => 'Diego Choque', 'telefono' => '387 154 112233', 'comision_porc' => 3, 'comision_base' => 'ventas', 'activo' => 1]);
        $p2 = DB::insertar('preventistas', ['usuario_id' => self::$u['preventista2'], 'nombre' => 'Carla Mamaní', 'telefono' => '387 155 445566', 'comision_porc' => 2.5, 'comision_base' => 'cobranzas', 'activo' => 1]);
        $zonas = [
            'Centro' => ['Microcentro, B° Hernando de Lerma, Plaza 9 de Julio', '1,3,5', '2,4,6', $p1],
            'Norte' => ['Tres Cerritos, Castañares, Ciudad del Milagro, El Huaico', '2,4,6', '3,5,6', $p1],
            'Sur' => ['Santa Ana, Limache, Intersindical, Solidaridad', '1,3,5', '2,4,6', $p2],
            'Oeste' => ['Villa Las Rosas, Villa Lavalle, San Remo, El Tribuno', '2,4,6', '3,5,6', $p2],
        ];
        $z = [];
        foreach ($zonas as $nombre => [$desc, $visita, $entrega, $prev]) {
            $z[$nombre] = DB::insertar('zonas', ['nombre' => $nombre, 'descripcion' => $desc, 'dias_visita' => $visita, 'dias_entrega' => $entrega, 'preventista_id' => $prev, 'activo' => 1]);
        }
        $veh = DB::insertar('vehiculos', ['patente' => 'AF123CD', 'descripcion' => 'Renault Kangoo furgón blanco', 'capacidad_kg' => 800, 'capacidad_bultos' => 120, 'activo' => 1]);
        RepartoService::depositoVehiculo($veh);
        DB::insertar('choferes', ['usuario_id' => self::$u['chofer1'], 'nombre' => 'Sergio Tolaba', 'dni' => '28456123', 'telefono' => '387 156 778899', 'licencia_vencimiento' => date('Y-m-d', strtotime('+8 months')), 'activo' => 1]);
        DB::insertar('choferes', ['usuario_id' => self::$u['chofer2'], 'nombre' => 'Julio Guaymás', 'dni' => '31987654', 'telefono' => '387 154 990011', 'licencia_vencimiento' => date('Y-m-d', strtotime('+20 days')), 'activo' => 1]);

        // [comercio, titular, tipo comercio, dirección, zona, cond. IVA, lista, cond. pago, días crédito, límite, lat, lng]
        $c = [
            ['Kiosco El Pibe', 'Jorge Gutiérrez', 'Kiosco', 'Caseros 845', 'Centro', 'MONO', 2, 'cta_cte', 7, 300000, -24.7881, -65.4108],
            ['Almacén Don Carlos', 'Carlos Villagra', 'Almacén', 'Alvarado 1230', 'Centro', 'RI', 1, 'cta_cte', 15, 800000, -24.7905, -65.4052],
            ['Despensa La Esquina', 'Rosa Zerpa', 'Despensa', 'España 702', 'Centro', 'MONO', 2, 'contado', 0, 0, -24.7869, -65.4083],
            ['Maxikiosco 24 hs Balcarce', 'Federico Ruiz', 'Maxikiosco', 'Balcarce 920', 'Centro', 'RI', 1, 'cta_cte', 7, 500000, -24.7798, -65.4119],
            ['Kiosco Escolar Belgrano', 'Ana Cardozo', 'Kiosco', 'Av. Belgrano 1450', 'Centro', 'MONO', 2, 'contado', 0, 0, -24.7853, -65.4192],
            ['Kiosco La Estación', 'Pedro Aramayo', 'Kiosco', 'Av. Ameghino 690', 'Centro', 'CF', 3, 'contado', 0, 0, -24.7792, -65.4141],
            ['Almacén Los Ceibos', 'Liliana Copa', 'Almacén', 'Av. Reyes Católicos 1520', 'Norte', 'MONO', 2, 'cta_cte', 15, 400000, -24.7640, -65.4060],
            ['Maxikiosco Tres Cerritos', 'Gustavo Saavedra', 'Maxikiosco', 'Los Mandarinos 120', 'Norte', 'RI', 1, 'cta_cte', 7, 600000, -24.7660, -65.4021],
            ['Despensa El Huaico', 'Norma Tejerina', 'Despensa', 'Av. Juan Carlos Dávalos 2300', 'Norte', 'MONO', 2, 'contado', 0, 0, -24.7480, -65.3999],
            ['Kiosco Castañares', 'Walter Burgos', 'Kiosco', 'Av. Houssay 950', 'Norte', 'MONO', 2, 'cta_cte', 7, 200000, -24.7563, -65.3897],
            ['Autoservicio El Sol', 'Hugo Liendro', 'Autoservicio', 'Av. Bolivia 4100', 'Norte', 'RI', 4, 'cta_cte', 21, 1500000, -24.7398, -65.4083],
            ['Kiosco Universitario', 'Matías Flores', 'Kiosco', 'Av. Bolivia 5150', 'Norte', 'MONO', 2, 'contado', 0, 0, -24.7300, -65.4071],
            ['Almacén Doña Rosa', 'Rosa Chocobar', 'Almacén', 'Av. Tavella 2100', 'Sur', 'MONO', 2, 'cta_cte', 7, 250000, -24.8095, -65.4185],
            ['Despensa Santa Ana', 'Ramona Quispe', 'Despensa', 'Calle 7 N° 540, B° Santa Ana I', 'Sur', 'CF', 3, 'contado', 0, 0, -24.8210, -65.4290],
            ['Maxikiosco Limache', 'Cristian Mendoza', 'Maxikiosco', 'Av. Tavella 3900', 'Sur', 'RI', 1, 'cta_cte', 15, 700000, -24.8270, -65.4170],
            ['Kiosco Los Hermanos', 'Daniel Sajama', 'Kiosco', 'Av. Independencia 1840', 'Sur', 'MONO', 2, 'cta_cte', 7, 150000, -24.8140, -65.4080],
            ['Despensa San Cayetano', 'Mirta Ontiveros', 'Despensa', 'Pje. Los Lapachos 230, B° Intersindical', 'Sur', 'MONO', 2, 'contado', 0, 0, -24.8180, -65.4005],
            ['Almacén Solidaridad', 'Beatriz Condori', 'Almacén', 'Manzana 320 Casa 14, B° Solidaridad', 'Sur', 'MONO', 2, 'cta_cte', 7, 200000, -24.8355, -65.4302],
            ['Kiosco Villa Las Rosas', 'Omar Tapia', 'Kiosco', 'Av. Arenales 1210', 'Oeste', 'MONO', 2, 'contado', 0, 0, -24.8010, -65.4390],
            ['Almacén La Familia', 'Graciela Vilte', 'Almacén', 'Av. Chile 1990', 'Oeste', 'RI', 1, 'cta_cte', 15, 600000, -24.7960, -65.4310],
            ['Despensa El Cerro', 'Luis Alancay', 'Despensa', 'Av. San Martín 2800', 'Oeste', 'MONO', 2, 'cta_cte', 7, 180000, -24.7920, -65.4420],
            ['Kiosco Rapidito', 'Sofía Cáceres', 'Kiosco', 'Av. Jujuy 560', 'Oeste', 'CF', 3, 'contado', 0, 0, -24.7948, -65.4230],
            ['Maxikiosco San Remo', 'Javier Ríos', 'Maxikiosco', 'Av. Chile 2650', 'Oeste', 'MONO', 2, 'cta_cte', 7, 300000, -24.7990, -65.4400],
            ['Despensa La Merced', 'Elena Guanuco', 'Despensa', 'Av. Entre Ríos 1680', 'Oeste', 'MONO', 2, 'contado', 0, 0, -24.7780, -65.4280],
            ['Kiosco El Tribuno', 'Ramiro Vargas', 'Kiosco', 'Av. Ex Combatientes de Malvinas 3400', 'Oeste', 'MONO', 2, 'cta_cte', 7, 150000, -24.8300, -65.4480],
        ];
        $dnis = 20000000;
        foreach ($c as $i => [$nombre, $titular, $tipo, $dir, $zona, $iva, $lista, $cond, $dias, $limite, $lat, $lng]) {
            $prev = $zonas[$zona][3];
            $dnis += 731919;
            $esCuit = $iva === 'RI' || $iva === 'MONO';
            $id = DB::insertar('clientes', [
                'codigo' => 'C' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'nombre_comercio' => $nombre, 'titular' => $titular,
                'tipo_documento' => $esCuit ? 'CUIT' : 'DNI', 'documento' => $esCuit ? self::cuit('20' . $dnis) : (string) $dnis,
                'condicion_iva' => $iva, 'tipo_comercio' => $tipo, 'direccion' => $dir, 'localidad' => 'Salta', 'zona_id' => $z[$zona],
                'latitud' => $lat, 'longitud' => $lng, 'telefono' => '387 4' . mt_rand(10, 99) . '-' . mt_rand(1000, 9999),
                'whatsapp' => '387 15' . mt_rand(4, 6) . ' ' . mt_rand(100000, 999999), 'dias_visita' => $zonas[$zona][1],
                'horario_visita' => ['8 a 12 h', '9 a 13 h', '16 a 20 h'][$i % 3], 'dias_entrega' => $zonas[$zona][2], 'horario_entrega' => '9 a 13 h',
                'lista_precio_id' => $lista, 'preventista_id' => $prev, 'limite_credito' => $limite, 'condicion_pago' => $cond,
                'dias_credito' => $dias, 'estado' => 'activo', 'orden_ruta' => $i + 1, 'creado_en' => ahora(),
            ]);
            self::$cli[$id] = ['id' => $id, 'zona' => $zona, 'prev' => $prev, 'cond' => $cond, 'dias' => array_map('intval', explode(',', $zonas[$zona][1]))];
        }
        self::log('25 clientes en 4 zonas, 2 preventistas, 2 choferes, 1 vehículo');
    }

    // ------------------------------------------------------------------ compras

    private static function comprasIniciales(): void
    {
        self::como('deposito');
        foreach (self::$prov as $nro => $provId) {
            $items = [];
            foreach (self::$prod as $p) {
                if ($p['prov'] !== $nro) {
                    continue;
                }
                $pres = end($p['pres']);
                $items[] = ['producto_id' => $p['id'], 'factor' => $pres[1], 'presentacion_nombre' => $pres[0], 'cantidad' => mt_rand(6, 10), 'costo_unitario' => $p['costo'] * $pres[1]];
            }
            self::ordenYRecepcion($provId, $items, -33, true);
        }
        self::log('Compras iniciales recibidas (stock inicial)');
    }

    private static function ordenYRecepcion(int $provId, array $items, int $dia, bool $conFactura): void
    {
        self::hora($dia, '08:30');
        self::como('oficina');
        $oc = CompraService::guardarOrden(['proveedor_id' => $provId, 'items' => $items, 'enviar' => true, 'fecha' => hoy()]);
        self::hora($dia + 1, '10:00');
        self::como('deposito');
        $recItems = [];
        foreach (DB::filas('SELECT * FROM orden_compra_items WHERE orden_id = ?', [$oc]) as $it) {
            $p = self::$prod[(int) $it['producto_id']];
            $venc = $p['lotes'] ? date('Y-m-d', Reloj::ahora() + mt_rand($p['rubro'] === 'Bebidas' ? 40 : 45, 150) * 86400) : null;
            $recItems[] = [
                'orden_item_id' => $it['id'], 'producto_id' => $it['producto_id'], 'factor' => $it['factor'], 'presentacion_nombre' => $it['presentacion_nombre'],
                'cantidad' => $it['cantidad'], 'costo_unitario' => $it['costo_unitario'],
                'lote' => $venc ? 'L' . date('ymd', Reloj::ahora()) . mt_rand(10, 99) : null, 'vencimiento' => $venc,
            ];
        }
        $rec = CompraService::recibir(['orden_id' => $oc, 'remito_proveedor' => '0001-' . str_pad((string) mt_rand(1000, 99999), 8, '0', STR_PAD_LEFT), 'items' => $recItems]);
        if ($conFactura) {
            self::como('oficina');
            $neto = (float) DB::valor('SELECT total FROM recepciones WHERE id = ?', [$rec]);
            CompraService::registrarFactura([
                'proveedor_id' => $provId, 'tipo' => 'FA', 'punto_venta' => mt_rand(2, 12), 'numero' => mt_rand(10000, 99999), 'fecha' => hoy(),
                'neto_gravado' => $neto, 'iva_21' => round($neto * 0.21, 2), 'percepcion_iibb' => round($neto * 0.03, 2),
                'recepcion_id' => $rec, 'orden_id' => $oc,
            ]);
        }
    }

    // ------------------------------------------------------------------ simulación diaria

    private static function simularDias(): void
    {
        $choferes = DB::columna('SELECT id FROM choferes ORDER BY id');
        $vehiculo = (int) DB::valor('SELECT id FROM vehiculos LIMIT 1');
        $consumidor = (int) Ajustes::get('cliente_consumidor_final', 1);
        for ($d = -28; $d <= 0; $d++) {
            $ts = time() + $d * 86400;
            $dow = (int) date('N', $ts);
            if ($dow === 7 && $d !== 0) {
                continue;
            }
            // Apertura de cajas
            self::hora($d, '08:00');
            self::como('oficina');
            if (!CajaService::sesionAbierta(1)) {
                CajaService::abrir(1, 30000, 'Fondo fijo');
            }
            self::como('mostrador');
            if (!CajaService::sesionAbierta(2)) {
                CajaService::abrir(2, 15000, 'Cambio');
            }

            // 1) Reparto de los pedidos preparados (tomados el día anterior)
            $preparados = DB::columna("SELECT id FROM pedidos WHERE estado IN ('confirmado') AND hoja_ruta_id IS NULL ORDER BY id");
            if ($preparados) {
                self::hora($d, '07:30');
                self::como('deposito');
                foreach ($preparados as $pid) {
                    PedidoService::cambiarEstado((int) $pid, 'en_preparacion');
                    PedidoService::cambiarEstado((int) $pid, 'preparado');
                }
                self::hora($d, '08:15');
                self::como('oficina');
                $hr = RepartoService::crear(['fecha' => hoy(), 'vehiculo_id' => $vehiculo, 'chofer_id' => $choferes[abs($d) % 2], 'pedidos' => $preparados]);
                // Orden de paradas según orden de ruta del cliente
                $paradas = DB::filas('SELECT hp.id FROM hoja_ruta_paradas hp JOIN clientes c ON c.id = hp.cliente_id WHERE hp.hoja_ruta_id = ? ORDER BY c.orden_ruta', [$hr]);
                RepartoService::ordenar($hr, array_combine(array_column($paradas, 'id'), range(1, count($paradas))));
                self::hora($d, '09:00');
                RepartoService::iniciar($hr);
                self::entregas($hr, $d);
            }

            // 2) Preventa del día
            foreach (self::$cli as $c) {
                if (!in_array($dow === 7 ? 6 : $dow, $c['dias'], true) || ($d === 0 && mt_rand(1, 100) > 60)) {
                    continue;
                }
                $prevUsuario = $c['prev'] === (int) DB::valor('SELECT id FROM preventistas WHERE usuario_id = ?', [self::$u['preventista1']]) ? 'preventista1' : 'preventista2';
                self::hora($d, sprintf('%02d:%02d', mt_rand(9, 12), mt_rand(0, 59)));
                self::como($prevUsuario);
                if (mt_rand(1, 100) <= 22) {
                    DB::insertar('visitas', [
                        'uuid' => uuid4(), 'cliente_id' => $c['id'], 'preventista_id' => $c['prev'], 'usuario_id' => self::$u[$prevUsuario],
                        'fecha' => ahora(), 'resultado' => 'sin_pedido', 'motivo' => ['tiene_stock', 'cerrado', 'sin_dinero', 'no_estaba'][mt_rand(0, 3)],
                    ]);
                    DB::query('UPDATE clientes SET ultima_visita = ? WHERE id = ?', [ahora(), $c['id']]);
                    continue;
                }
                $pedido = self::nuevoPedido($c['id'], mt_rand(3, 8));
                if ($pedido) {
                    DB::insertar('visitas', ['uuid' => uuid4(), 'cliente_id' => $c['id'], 'preventista_id' => $c['prev'], 'usuario_id' => self::$u[$prevUsuario], 'fecha' => ahora(), 'resultado' => 'pedido', 'pedido_id' => $pedido]);
                    DB::query('UPDATE clientes SET ultima_visita = ? WHERE id = ?', [ahora(), $c['id']]);
                }
            }

            // 3) Mostrador
            self::como('mostrador');
            for ($k = 0, $n = mt_rand(2, 5); $k < $n; $k++) {
                self::hora($d, sprintf('%02d:%02d', mt_rand(9, 19), mt_rand(0, 59)));
                $items = self::itemsAlAzar(mt_rand(1, 4), true);
                try {
                    $total = self::totalItems($items, 3);
                    $forma = ['efectivo', 'efectivo', 'efectivo', 'tarjeta', 'mercadopago'][mt_rand(0, 4)];
                    VentaService::emitir(['clase' => 'factura', 'cliente_id' => $consumidor, 'items' => $items, 'origen' => 'mostrador', 'lista_precio_id' => 3, 'pagos' => [['forma' => $forma, 'importe' => $total]], 'caja_id' => 2]);
                } catch (ErrorNegocio) {
                }
            }

            // 4) Cobranzas en oficina (clientes de cuenta corriente con deuda)
            if ($dow === 2 || $dow === 5) {
                self::como('oficina');
                $deudores = DB::filas("SELECT cliente_id, SUM(pendiente) AS deuda FROM cc_clientes WHERE importe > 0 AND pendiente > 0 GROUP BY cliente_id HAVING deuda > 0 ORDER BY RAND(" . abs($d) . ") LIMIT 3");
                foreach ($deudores as $deu) {
                    if (mt_rand(1, 100) > 65) {
                        continue;
                    }
                    self::hora($d, sprintf('%02d:%02d', mt_rand(10, 17), mt_rand(0, 59)));
                    $importe = round((float) $deu['deuda'] * [1, 1, 0.5][mt_rand(0, 2)], -2) ?: (float) $deu['deuda'];
                    $pago = mt_rand(1, 100) <= 25
                        ? ['forma' => 'cheque', 'importe' => $importe, 'cheque' => ['numero' => (string) mt_rand(10000000, 99999999), 'banco' => ['Banco Macro', 'Banco Nación', 'Banco Galicia', 'Banco Santander'][mt_rand(0, 3)], 'fecha_pago' => date('Y-m-d', Reloj::ahora() + mt_rand(5, 30) * 86400)]]
                        : ['forma' => ['efectivo', 'transferencia'][mt_rand(0, 1)], 'importe' => $importe];
                    CobranzaService::crearRecibo(['cliente_id' => $deu['cliente_id'], 'pagos' => [$pago], 'origen' => 'cobranza', 'caja_id' => 1]);
                }
            }

            // 5) Gastos y compras de reposición
            self::como('oficina');
            if ($dow === 1) {
                self::hora($d, '11:00');
                FinanzasService::registrarGasto(['categoria_id' => 1, 'descripcion' => 'Nafta Kangoo AF123CD', 'importe' => mt_rand(45, 60) * 1000, 'forma_pago' => 'efectivo', 'vehiculo_id' => $vehiculo, 'caja_id' => 1]);
                foreach (self::$prov as $nro => $provId) {
                    $sug = CompraService::sugerencia($provId, 21, 12);
                    if (!$sug) {
                        continue;
                    }
                    $items = array_map(fn ($s) => ['producto_id' => $s['id'], 'presentacion_id' => $s['presentacion_id'], 'cantidad' => max(1, $s['sugerido']), 'costo_unitario' => round($s['costo_presentacion'] * (1 + mt_rand(0, 30) / 1000), 2)], $sug);
                    if ($d <= -2) {
                        self::ordenYRecepcion($provId, $items, $d, true);
                        self::hora($d, '16:00');
                    } else {
                        self::como('oficina');
                        CompraService::guardarOrden(['proveedor_id' => $provId, 'items' => $items, 'enviar' => true]);
                    }
                }
            }
            if ($d === -20) {
                self::hora($d, '12:00');
                FinanzasService::registrarGasto(['categoria_id' => 3, 'descripcion' => 'Alquiler depósito Av. Tavella', 'importe' => 850000, 'forma_pago' => 'banco']);
                FinanzasService::registrarGasto(['categoria_id' => 4, 'descripcion' => 'EDESA - luz depósito', 'importe' => 96500, 'forma_pago' => 'banco']);
            }
            if ($d === -15) {
                self::hora($d, '12:30');
                FinanzasService::registrarGasto(['categoria_id' => 2, 'descripcion' => 'Sueldos quincena', 'importe' => 1950000, 'forma_pago' => 'banco']);
                FinanzasService::registrarGasto(['categoria_id' => 5, 'descripcion' => 'Service Kangoo + cubiertas', 'importe' => 240000, 'forma_pago' => 'efectivo', 'vehiculo_id' => $vehiculo, 'caja_id' => 1]);
                foreach ([2, 4] as $nro) {
                    $deuda = CuentaCorriente::proveedores()->saldo(self::$prov[$nro]);
                    if ($deuda > 0) {
                        CompraService::registrarPago(['proveedor_id' => self::$prov[$nro], 'detalle' => [['forma' => 'transferencia', 'importe' => round($deuda * 0.7, 2), 'referencia' => 'Transf. ' . mt_rand(100000, 999999)]]]);
                    }
                }
            }

            // 6) Cierre de cajas (excepto hoy)
            if ($d < 0) {
                self::hora($d, '20:30');
                foreach ([1 => 'oficina', 2 => 'mostrador'] as $caja => $usr) {
                    self::como($usr);
                    $s = CajaService::sesionAbierta($caja);
                    if ($s) {
                        $teorico = CajaService::saldoTeorico((int) $s['id']);
                        // Se deposita el excedente y se deja el fondo fijo
                        $fondo = $caja === 1 ? 30000 : 15000;
                        if ($teorico > $fondo + 1000) {
                            $sobrante = round($teorico - $fondo, -2);
                            CajaService::movimiento((int) $s['id'], 'egreso', 'Depósito en Banco Macro', $sobrante, 'manual');
                            CajaService::movimientoBanco(1, 'ingreso', 'Depósito de efectivo de caja', $sobrante, null, 'caja', (int) $s['id']);
                        }
                        $teorico = CajaService::saldoTeorico((int) $s['id']);
                        $dif = mt_rand(1, 100) <= 10 ? -mt_rand(1, 5) * 100 : 0;
                        CajaService::cerrar((int) $s['id'], $teorico + $dif, null, $dif ? 'Diferencia de arqueo' : null);
                    }
                }
            }
        }
        self::log('Simulación de 4 semanas de operación completa');
    }

    private static function totalItems(array $items, int $lista): float
    {
        $t = 0.0;
        foreach ($items as $i) {
            $t += round(PrecioService::precioLista((int) $i['producto_id'], $lista) * (float) $i['factor'], 2) * (float) $i['cantidad'];
        }
        return round($t, 2);
    }

    private static function itemsAlAzar(int $n, bool $unidades = false): array
    {
        // Sólo productos con stock disponible suficiente (como haría un preventista que consulta el stock)
        $ids = array_values(array_filter(array_keys(self::$prod), fn ($id) => StockService::disponible($id, 1) >= max(36, self::$prod[$id]['pres'][0][1] * 3)));
        shuffle($ids);
        $items = [];
        foreach (array_slice($ids, 0, $n) as $id) {
            $p = self::$prod[$id];
            if ($unidades || mt_rand(1, 100) <= 25) {
                $items[] = ['producto_id' => $id, 'factor' => 1, 'presentacion_nombre' => DB::valor('SELECT unidad_base FROM productos WHERE id = ?', [$id]), 'cantidad' => mt_rand(1, $unidades ? 3 : 12)];
            } else {
                [$nombre, $factor] = $p['pres'][0];
                $items[] = ['producto_id' => $id, 'factor' => $factor, 'presentacion_nombre' => $nombre, 'cantidad' => mt_rand(1, 3)];
            }
        }
        return $items;
    }

    /** Pedido confirmado con reintento si falta stock de algún producto. */
    private static function nuevoPedido(int $clienteId, int $nItems): ?int
    {
        for ($intento = 0; $intento < 3; $intento++) {
            try {
                return PedidoService::guardar(['cliente_id' => $clienteId, 'items' => self::itemsAlAzar($nItems), 'confirmar' => true, 'aplicar_promociones' => true, 'origen' => 'movil', 'uuid' => uuid4()]);
            } catch (ErrorNegocio) {
                $nItems = max(1, $nItems - 2);
            }
        }
        return null;
    }

    private static function entregas(int $hr, int $d): void
    {
        $chofer = (int) DB::valor('SELECT ch.usuario_id FROM hojas_ruta h JOIN choferes ch ON ch.id = h.chofer_id WHERE h.id = ?', [$hr]);
        $_SESSION['usuario_id'] = $chofer;
        Auth::refrescar();
        $paradas = DB::filas('SELECT hp.*, p.total, c.condicion_pago FROM hoja_ruta_paradas hp JOIN pedidos p ON p.id = hp.pedido_id JOIN clientes c ON c.id = hp.cliente_id WHERE hp.hoja_ruta_id = ? ORDER BY hp.orden', [$hr]);
        $hora = 9 * 60 + 30;
        foreach ($paradas as $i => $pa) {
            // Hoy el reparto queda a mitad de camino
            if ($d === 0 && $i >= max(1, intdiv(count($paradas), 3))) {
                break;
            }
            $hora += mt_rand(15, 35);
            self::hora($d, sprintf('%02d:%02d', intdiv($hora, 60), $hora % 60));
            $r = mt_rand(1, 100);
            $resultado = $r <= 84 ? 'entregado' : ($r <= 91 ? 'parcial' : ($r <= 96 ? 'rechazado' : 'cerrado'));
            $datos = ['resultado' => $resultado, 'evento_uuid' => uuid4()];
            $total = (float) $pa['total'];
            if ($resultado === 'parcial') {
                $items = DB::filas('SELECT id, cantidad_base, factor FROM pedido_items WHERE pedido_id = ?', [$pa['pedido_id']]);
                foreach ($items as $k => $it) {
                    $datos['items'][$it['id']] = $k === 0 ? max(0, (float) $it['cantidad_base'] - (float) $it['factor']) : (float) $it['cantidad_base'];
                }
                $datos['motivo'] = 'faltante';
            }
            if (in_array($resultado, ['rechazado'], true)) {
                $datos['motivo'] = ['sin_dinero', 'no_pidio', 'precio'][mt_rand(0, 2)];
            }
            try {
                if (in_array($resultado, ['entregado', 'parcial'], true)) {
                    $res = RepartoService::registrarEntrega((int) $pa['id'], $datos);
                    $comp = (float) DB::valor('SELECT total FROM comprobantes WHERE id = ?', [$res['comprobante_id']]);
                    $paga = $pa['condicion_pago'] === 'contado' ? 100 : (mt_rand(1, 100) <= 45 ? 100 : 0);
                    if ($paga && $comp > 0) {
                        $forma = ['efectivo', 'efectivo', 'efectivo', 'transferencia', 'mercadopago'][mt_rand(0, 4)];
                        // Se registra el cobro en un segundo evento (como lo hace la app)
                        CobranzaService::crearRecibo(['cliente_id' => $pa['cliente_id'], 'pagos' => [['forma' => $forma, 'importe' => $comp, 'referencia' => $forma !== 'efectivo' ? 'Op. ' . mt_rand(100000, 999999) : null]], 'origen' => 'reparto', 'hoja_ruta_id' => $hr, 'comprobante_id' => $res['comprobante_id']]);
                    }
                } else {
                    RepartoService::registrarEntrega((int) $pa['id'], $datos);
                }
            } catch (ErrorNegocio) {
            }
        }
        if ($d < 0) {
            self::hora($d, '17:30');
            self::como('oficina');
            $datos = RepartoService::datosRendicion($hr);
            $esperado = (float) ($datos['esperado']['efectivo'] ?? 0);
            $reprogramar = [];
            foreach ($datos['paradas'] as $pa) {
                if ($pa['estado'] === 'cerrado' || ($pa['estado'] === 'rechazado' && mt_rand(0, 1))) {
                    $reprogramar[$pa['id']] = 1;
                }
            }
            $devuelto = [];
            foreach ($datos['productos'] as $k => $p) {
                $devuelto[$p['id']] = $k === 0 && mt_rand(1, 100) <= 15 && $p['devolucion_esperada'] > 0 ? $p['devolucion_esperada'] - 1 : $p['devolucion_esperada'];
            }
            RepartoService::rendir($hr, [
                'rendido' => ['efectivo' => $esperado - (mt_rand(1, 100) <= 12 ? 500 : 0)] + array_diff_key($datos['esperado'], ['efectivo' => 1]),
                'devuelto' => $devuelto, 'reprogramar' => $reprogramar, 'caja_id' => 1,
                'observaciones' => 'Rendición del día',
            ]);
        }
    }

    // ------------------------------------------------------------------ casos especiales

    private static function extras(): void
    {
        self::como('oficina');
        // Cheques: uno depositado y uno endosado a un proveedor
        $cheques = DB::columna("SELECT id FROM cheques WHERE estado = 'cartera' ORDER BY id");
        if (count($cheques) >= 2) {
            self::hora(-3, '10:00');
            FinanzasService::depositarCheque((int) $cheques[0], 1);
            CompraService::registrarPago(['proveedor_id' => self::$prov[1], 'detalle' => [['forma' => 'cheque_tercero', 'cheque_id' => $cheques[1]]], 'observaciones' => 'Pago con cheque de tercero']);
        }
        // Devolución de un cliente con nota de crédito
        self::hora(-2, '11:30');
        $fact = DB::fila("SELECT c.*, ci.producto_id, ci.cantidad, ci.factor, ci.precio_unitario FROM comprobantes c JOIN comprobante_items ci ON ci.comprobante_id = c.id WHERE c.tipo IN ('FA','FB') AND c.estado = 'emitido' AND c.origen = 'reparto' AND c.cliente_id <> 1 ORDER BY c.id DESC LIMIT 1");
        if ($fact) {
            VentaService::emitir(['clase' => 'nc', 'cliente_id' => $fact['cliente_id'], 'comprobante_asociado_id' => $fact['id'], 'deposito_id' => 1,
                'items' => [['producto_id' => $fact['producto_id'], 'factor' => $fact['factor'], 'cantidad' => 1, 'precio_unitario' => $fact['precio_unitario'], 'reingresa_stock' => 0]],
                'observaciones' => 'Devolución: mercadería dañada (no reingresa a stock)']);
        }
        // Una factura de mostrador anulada
        self::hora(0, '09:30');
        self::como('mostrador');
        $ultima = DB::valor("SELECT id FROM comprobantes WHERE origen = 'mostrador' AND estado = 'emitido' AND fecha < ? ORDER BY id DESC LIMIT 1", [hoy() . ' 00:00:00']);
        if ($ultima && CajaService::sesionAbierta(2)) {
            VentaService::anular((int) $ultima, 'Error en la carga: se facturó a consumidor final por error');
        }
        // Presupuesto
        self::como('oficina');
        self::hora(0, '10:15');
        $cli = array_keys(self::$cli)[10];
        VentaService::emitir(['clase' => 'presupuesto', 'cliente_id' => $cli, 'items' => self::itemsAlAzar(5), 'observaciones' => 'Presupuesto para reposición de góndola']);
        // Ajuste por rotura
        self::como('deposito');
        $prodId = array_keys(self::$prod)[17];
        StockService::ajustar(1, 'rotura', [[$prodId, -2]], 'Botellas rotas al descargar');
        // Inventario parcial aplicado de Cigarrillos con una diferencia
        self::hora(0, '08:10');
        $rubroCig = (int) DB::valor("SELECT id FROM rubros WHERE nombre = 'Cigarrillos'");
        $invId = DB::insertar('inventarios', ['fecha' => ahora(), 'deposito_id' => 1, 'rubro_id' => $rubroCig, 'descripcion' => 'Control semanal de cigarrillos', 'estado' => 'abierto', 'usuario_id' => self::$u['deposito']]);
        foreach (DB::filas('SELECT p.id, COALESCE(s.cantidad, 0) AS stock FROM productos p LEFT JOIN stock s ON s.producto_id = p.id AND s.deposito_id = 1 WHERE p.rubro_id = ?', [$rubroCig]) as $k => $p) {
            DB::insertar('inventario_items', ['inventario_id' => $invId, 'producto_id' => $p['id'], 'stock_sistema' => $p['stock'], 'cantidad_contada' => $k === 1 ? max(0, (float) $p['stock'] - 1) : $p['stock']]);
        }
        // Pedidos cargados desde la app que quedaron para revisión
        self::como('preventista1');
        self::hora(0, '11:40');
        $clis = array_keys(self::$cli);
        $p = PedidoService::guardar(['cliente_id' => $clis[3], 'items' => self::itemsAlAzar(3), 'origen' => 'movil', 'uuid' => uuid4(),
            'revision_detalle' => json_encode(['El precio de "Coca-Cola 500 ml" cambió: en el celular figuraba $ 900,00 y ahora es $ 1.100,00.'], JSON_UNESCAPED_UNICODE)]);
        DB::query('UPDATE pedidos SET revision_requerida = 1 WHERE id = ?', [$p]);
        // Liquidación de comisiones de la primera quincena simulada
        self::como('admin');
        $desde = date('Y-m-d', time() - 28 * 86400);
        $hasta = date('Y-m-d', time() - 14 * 86400);
        foreach (DB::columna('SELECT id FROM preventistas') as $prev) {
            try {
                $liq = ComisionService::liquidar((int) $prev, $desde, $hasta, 'Primera quincena');
                ComisionService::cambiarEstado($liq, 'pagada');
            } catch (ErrorNegocio) {
            }
        }
        self::log('Casos especiales: cheques, NC, anulación, presupuesto, ajuste, inventario, revisión, comisiones');
    }
}
