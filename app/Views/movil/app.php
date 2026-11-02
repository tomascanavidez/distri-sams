<?php /** App del preventista (PWA): Alpine + IndexedDB. Todo el contenido se arma en el celular. */ ?>
<div x-data="appPreventa" class="app-movil" x-cloak>
    <header class="app-cabecera">
        <button class="btn btn-topbar" type="button" x-show="vista !== 'inicio'" @click="history.back()" aria-label="Volver"><i class="bi bi-arrow-left"></i></button>
        <img src="<?= asset('assets/img/logo.svg') ?>" alt="" width="30" height="30" x-show="vista === 'inicio'" style="background:#fff;border-radius:50%;padding:2px">
        <h1 x-text="{ inicio: 'Preventa', ruta: 'Mi ruta de hoy', clientes: modoElegir ? 'Elegí el cliente' : 'Clientes', cliente: cliente ? cliente.n : 'Cliente', pedido: cliente ? cliente.n : 'Pedido', pedidos: 'Mis pedidos', mas: 'Más opciones' }[vista]"></h1>
        <button class="btn btn-topbar" type="button" @click="sincronizar()" aria-label="Sincronizar"><i class="bi" :class="sincronizando ? 'bi-arrow-repeat' : 'bi-cloud-arrow-up'"></i></button>
    </header>
    <button type="button" class="barra-estado" :class="estadoConexion().clase" @click="sesionVencida ? location.href = DT.url('/login') : sincronizar()">
        <i class="bi" :class="!online ? 'bi-wifi-off' : (sincronizando ? 'bi-arrow-repeat' : (pendientes() ? 'bi-cloud-upload' : 'bi-check-circle'))"></i>
        <span x-text="estadoConexion().texto"></span>
    </button>

    <template x-if="!datos">
        <div class="vacio-movil">
            <i class="bi" :class="online ? 'bi-cloud-download' : 'bi-wifi-off'"></i>
            <p x-show="online">Descargando clientes, productos y precios…</p>
            <p x-show="!online">Todavía no hay datos guardados en este celular. Conectate a internet una vez para descargarlos.</p>
        </div>
    </template>

    <template x-if="datos">
    <div>
        <!-- ===================== INICIO ===================== -->
        <template x-if="vista === 'inicio'">
        <section class="app-seccion">
            <h2>¡Hola, <span x-text="datos.usuario"></span>!</h2>
            <div class="kpis-movil mb-3" x-data="{ r: resumenDia() }" x-effect="r = resumenDia()">
                <div class="kpi-movil"><div class="e">Visitas de hoy</div><div class="v"><span x-text="r.hechos"></span><small class="fs-6 text-body-secondary"> / <span x-text="r.ruta"></span></small></div></div>
                <div class="kpi-movil"><div class="e">Pedidos de hoy</div><div class="v" x-text="r.pedidos"></div></div>
                <div class="kpi-movil"><div class="e">Vendido hoy</div><div class="v fs-5" x-text="DT.dinero(r.vendido)"></div></div>
                <div class="kpi-movil"><div class="e">Sin enviar</div><div class="v" :class="pendientes() ? 'text-warning' : 'text-success'" x-text="pendientes()"></div></div>
            </div>
            <button class="btn btn-primary btn-lg w-100 mb-3 btn-touch" @click="ir('ruta')"><i class="bi bi-signpost-2"></i> Ver mi ruta de hoy</button>
            <h2 class="mt-2">Próximas visitas</h2>
            <template x-for="c in rutaHoy().filter(c => !visitado(c)).slice(0, 4)" :key="c.i">
                <div class="tarjeta">
                    <div class="tarjeta-titulo" x-text="c.n"></div>
                    <div class="tarjeta-sub" x-text="c.dir + (c.hv ? ' · ' + c.hv : '')"></div>
                    <div class="acciones-tarjeta">
                        <button class="btn btn-primary" @click="abrirPedido(c)"><i class="bi bi-cart-plus"></i> Pedido</button>
                        <button class="btn btn-outline-secondary" @click="abrirVisita(c)"><i class="bi bi-x-circle"></i> Sin pedido</button>
                        <a class="btn btn-outline-secondary btn-icono" :href="mapa(c)" target="_blank" rel="noopener" aria-label="Mapa"><i class="bi bi-geo-alt"></i></a>
                    </div>
                </div>
            </template>
            <template x-if="!rutaHoy().filter(c => !visitado(c)).length"><div class="vacio-movil"><i class="bi bi-emoji-smile"></i>No quedan visitas pendientes para hoy.</div></template>
        </section>
        </template>

        <!-- ===================== RUTA ===================== -->
        <template x-if="vista === 'ruta'">
        <section class="app-seccion">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-body-secondary" x-text="rutaHoy().length + ' clientes'"></span>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="verTodos" x-model="verTodos"><label class="form-check-label" for="verTodos">Todos mis clientes</label></div>
            </div>
            <template x-for="c in rutaHoy()" :key="c.i">
                <div class="tarjeta" :class="{ hecha: visitado(c) }">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="min-w-0" @click="verCliente(c)">
                            <div class="tarjeta-titulo" x-text="c.n"></div>
                            <div class="tarjeta-sub" x-text="c.dir + ' · ' + (c.z || '')"></div>
                        </div>
                        <div class="text-end text-nowrap">
                            <div class="fw-bold" :class="c.sal > 0 ? 'saldo-deuda' : ''" x-text="DT.dinero(c.sal)"></div>
                            <small class="text-danger fw-semibold" x-show="c.ven > 0" x-text="'Vencido ' + DT.dinero(c.ven)"></small>
                        </div>
                    </div>
                    <div class="mt-1 d-flex gap-1 flex-wrap">
                        <span class="chip" :class="estadoCliente(c).c"><i class="bi" :class="'bi-' + estadoCliente(c).i"></i> <span x-text="estadoCliente(c).t"></span></span>
                        <span class="chip chip-mal" x-show="c.est === 'bloqueado'"><i class="bi bi-lock"></i> Bloqueado</span>
                        <span class="chip" x-show="c.hv" x-text="c.hv"></span>
                    </div>
                    <div class="acciones-tarjeta">
                        <button class="btn btn-primary" @click="abrirPedido(c)"><i class="bi bi-cart-plus"></i> Pedido</button>
                        <button class="btn btn-outline-secondary" @click="abrirVisita(c)"><i class="bi bi-x-circle"></i> Sin pedido</button>
                        <a class="btn btn-outline-secondary btn-icono" :href="mapa(c)" target="_blank" rel="noopener" aria-label="Cómo llegar"><i class="bi bi-geo-alt"></i></a>
                    </div>
                </div>
            </template>
            <template x-if="!rutaHoy().length"><div class="vacio-movil"><i class="bi bi-calendar-x"></i>Hoy no tenés clientes asignados. Activá "Todos mis clientes".</div></template>
        </section>
        </template>

        <!-- ===================== CLIENTES ===================== -->
        <template x-if="vista === 'clientes'">
        <section>
            <div class="buscador-movil"><input type="search" class="form-control" x-model="qCli" placeholder="Buscar comercio, titular, dirección…" autocomplete="off" aria-label="Buscar cliente"></div>
            <div class="app-seccion pt-1">
                <template x-for="c in clientesFiltrados()" :key="c.i">
                    <div class="tarjeta" @click="elegirCliente(c)" role="button">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="min-w-0"><div class="tarjeta-titulo" x-text="c.n"></div><div class="tarjeta-sub" x-text="c.dir + ' · ' + (c.z || '') + ' · ' + c.c"></div></div>
                            <div class="text-end text-nowrap"><div class="fw-bold" :class="c.sal > 0 ? 'saldo-deuda' : ''" x-text="DT.dinero(c.sal)"></div><i class="bi bi-chevron-right text-body-secondary"></i></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
        </template>

        <!-- ===================== FICHA DE CLIENTE ===================== -->
        <template x-if="vista === 'cliente' && cliente">
        <section class="app-seccion">
            <div class="tarjeta">
                <div class="tarjeta-titulo fs-5" x-text="cliente.n"></div>
                <div class="tarjeta-sub" x-text="(cliente.t || '') + ' · ' + cliente.c"></div>
                <div class="mt-1" x-text="cliente.dir + ', ' + (cliente.loc || '')"></div>
                <div class="tarjeta-sub" x-text="'Zona ' + (cliente.z || '—') + ' · Visita ' + (cliente.hv || '')"></div>
                <div class="acciones-tarjeta">
                    <a class="btn btn-outline-secondary" :href="telefono(cliente)" x-show="cliente.tel"><i class="bi bi-telephone-fill"></i> Llamar</a>
                    <a class="btn btn-success" :href="whatsapp(cliente, mensajeWhatsapp(cliente))" target="_blank" rel="noopener" x-show="cliente.wa"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    <a class="btn btn-outline-primary" :href="mapa(cliente)" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Mapa</a>
                </div>
            </div>
            <div class="kpis-movil mb-2">
                <div class="kpi-movil"><div class="e">Saldo</div><div class="v fs-4" :class="cliente.sal > 0 ? 'saldo-deuda' : 'saldo-favor'" x-text="DT.dinero(cliente.sal)"></div></div>
                <div class="kpi-movil"><div class="e">Vencido</div><div class="v fs-4" :class="cliente.ven > 0 ? 'text-danger' : ''" x-text="DT.dinero(cliente.ven)"></div></div>
                <div class="kpi-movil"><div class="e">Límite</div><div class="v fs-5" x-text="cliente.lim > 0 ? DT.dinero(cliente.lim) : 'Sin límite'"></div></div>
                <div class="kpi-movil"><div class="e">Pago</div><div class="v fs-5" x-text="cliente.cp === 'cta_cte' ? 'Cta. cte.' : 'Contado'"></div></div>
            </div>
            <template x-for="a in alertasCliente(cliente)"><div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle"></i> <span x-text="a"></span></div></template>
            <div class="d-grid gap-2 mb-3">
                <button class="btn btn-primary btn-lg btn-touch" @click="abrirPedido(cliente)"><i class="bi bi-cart-plus"></i> Nuevo pedido</button>
                <button class="btn btn-outline-primary btn-lg btn-touch" @click="abrirPedido(cliente); $nextTick(() => repetirUltimo())" x-show="cliente.ult && cliente.ult.length"><i class="bi bi-arrow-repeat"></i> Repetir último pedido</button>
                <button class="btn btn-outline-secondary btn-lg btn-touch" @click="abrirVisita(cliente)"><i class="bi bi-x-circle"></i> Visita sin pedido</button>
            </div>
            <h2>Últimos pedidos</h2>
            <template x-for="p in cliente.peds"><div class="tarjeta py-2 d-flex justify-content-between"><span x-text="'N° ' + p[0] + ' · ' + p[1].split('-').reverse().join('/')"></span><span><strong x-text="DT.dinero(p[2])"></strong> <span class="chip" x-text="p[3].replace('_', ' ')"></span></span></div></template>
            <template x-if="!cliente.peds.length"><p class="text-body-secondary">Sin pedidos en los últimos 60 días.</p></template>
            <p class="small text-body-secondary">Última visita: <span x-text="cliente.uv ? new Date(cliente.uv.replace(' ', 'T')).toLocaleDateString('es-AR') : '—'"></span> · Última compra: <span x-text="cliente.uc ? new Date(cliente.uc.replace(' ', 'T')).toLocaleDateString('es-AR') : '—'"></span></p>
        </section>
        </template>

        <!-- ===================== CARGA DE PEDIDO ===================== -->
        <template x-if="vista === 'pedido' && cliente">
        <section>
            <div class="px-3 pt-2 small d-flex justify-content-between">
                <span x-text="'Lista ' + (datos.listas[cliente.l] || '') + ' · Saldo ' + DT.dinero(cliente.sal)"></span>
                <button class="btn btn-link btn-sm p-0" @click="repetirUltimo()" x-show="cliente.ult && cliente.ult.length && !carrito.length"><i class="bi bi-arrow-repeat"></i> Repetir último</button>
            </div>
            <template x-for="a in alertasCliente(cliente)"><div class="alert alert-warning py-1 px-2 mx-3 my-1 small"><i class="bi bi-exclamation-triangle"></i> <span x-text="a"></span></div></template>
            <div class="buscador-movil d-flex gap-2">
                <input type="search" class="form-control" x-model="q" @input="limite = 40" placeholder="Buscar producto, marca o código" autocomplete="off" aria-label="Buscar producto">
                <button class="btn btn-outline-secondary" style="min-width:56px;border-radius:.8rem" @click="escanearProducto()" aria-label="Escanear código de barras"><i class="bi bi-upc-scan fs-4"></i></button>
            </div>
            <div class="chips-scroll">
                <button class="btn btn-sm" :class="rubro === 'hab' ? 'btn-primary' : 'btn-outline-secondary'" @click="rubro = 'hab'" x-show="cliente.hab && cliente.hab.length"><i class="bi bi-star-fill"></i> Habituales</button>
                <button class="btn btn-sm" :class="rubro === 'carrito' ? 'btn-primary' : 'btn-outline-secondary'" @click="rubro = 'carrito'" x-show="carrito.length"><i class="bi bi-cart"></i> En el pedido</button>
                <button class="btn btn-sm" :class="rubro === '' ? 'btn-primary' : 'btn-outline-secondary'" @click="rubro = ''">Todos</button>
                <template x-for="r in datos.rubros" :key="r.id"><button class="btn btn-sm" :class="String(rubro) === String(r.id) ? 'btn-primary' : 'btn-outline-secondary'" @click="rubro = r.id; limite = 40" x-text="r.nombre"></button></template>
            </div>
            <div class="app-seccion pt-1" style="padding-bottom: 90px">
                <template x-for="p in productosFiltrados().slice(0, limite)" :key="p.i">
                    <div class="tarjeta" :class="{ 'en-carrito': cantidad(p) > 0 }">
                        <div class="producto-movil">
                            <template x-if="fotoUrl(p)"><img :src="fotoUrl(p)" alt="" loading="lazy" width="56" height="56"></template>
                            <template x-if="!fotoUrl(p)"><div class="sin-foto"><i class="bi bi-box-seam text-body-tertiary fs-4"></i></div></template>
                            <div class="min-w-0">
                                <div class="fw-semibold lh-sm" x-text="p.d"></div>
                                <div class="small text-body-secondary"><span x-text="p.m || ''"></span> · <span :class="p.s > 0 ? 'text-success' : 'text-danger fw-bold'" x-text="'Disp. ' + DT.cant(p.s) + ' ' + p.u"></span></div>
                            </div>
                        </div>
                        <div class="pres-selector mt-2" x-show="p.pr.length > 1">
                            <template x-for="(pr, k) in p.pr" :key="pr[0]"><button class="btn btn-sm" :class="presDe(p)[0] === pr[0] ? 'btn-dark' : 'btn-outline-secondary'" @click="elegirPres(p, k)" x-text="pr[1] + (pr[2] != 1 ? ' x' + DT.cant(pr[2]) : '')"></button></template>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div><div class="precio-grande" x-text="DT.dinero(precio(p))"></div><small class="text-body-secondary" x-text="'por ' + presDe(p)[1].toLowerCase()"></small></div>
                            <div class="stepper">
                                <button class="btn btn-outline-secondary" @click="sumar(p, -1)" :disabled="!cantidad(p)" aria-label="Restar">−</button>
                                <input type="text" inputmode="numeric" class="form-control" :value="cantidad(p) || ''" @change="fijar(p, DT.parse($event.target.value))" @focus="$event.target.select()" placeholder="0" aria-label="Cantidad">
                                <button class="btn btn-primary" @click="sumar(p, 1)" aria-label="Sumar">+</button>
                            </div>
                        </div>
                    </div>
                </template>
                <button class="btn btn-outline-secondary w-100 btn-touch" x-show="productosFiltrados().length > limite" @click="limite += 40">Ver más productos</button>
                <template x-if="!productosFiltrados().length"><div class="vacio-movil"><i class="bi bi-search"></i>No se encontraron productos.</div></template>
            </div>
            <div class="carrito-barra">
                <div class="flex-grow-1"><div class="total" x-text="DT.dinero(totalCarrito())"></div><small x-text="carrito.length + ' productos · ' + DT.cant(unidadesCarrito()) + ' u.'"></small></div>
                <button class="btn btn-light" @click="hoja = 'carrito'; history.pushState({}, '')" :disabled="!carrito.length"><i class="bi bi-cart-check"></i> Ver pedido</button>
            </div>
        </section>
        </template>

        <!-- ===================== MIS PEDIDOS ===================== -->
        <template x-if="vista === 'pedidos'">
        <section class="app-seccion">
            <h2>En el celular</h2>
            <template x-for="o in opsPedidos()" :key="o.uuid">
                <div class="tarjeta">
                    <div class="d-flex justify-content-between"><strong x-text="o.cliente"></strong><strong x-text="DT.dinero(o.total)"></strong></div>
                    <div class="small text-body-secondary" x-text="new Date(o.creado).toLocaleString('es-AR') + ' · ' + o.lineas + ' productos'"></div>
                    <span class="chip mt-1" :class="o.estado === 'revision' ? 'chip-warn' : (o.estado === 'error' ? 'chip-mal' : 'chip-info')" x-text="o.estado === 'revision' ? 'Quedó para revisión en la oficina (N° ' + o.respuesta.numero + ')' : (o.estado === 'error' ? 'Error al enviar, se reintenta' : 'Pendiente de enviar')"></span>
                    <template x-if="o.estado === 'revision'"><ul class="small mt-2 mb-1"><template x-for="m in o.respuesta.mensajes"><li x-text="m"></li></template></ul></template>
                    <button class="btn btn-sm btn-outline-secondary mt-1" x-show="o.estado === 'revision'" @click="descartarOp(o)">Entendido</button>
                </div>
            </template>
            <template x-if="!opsPedidos().length"><p class="text-body-secondary">No hay pedidos pendientes de enviar.</p></template>
            <h2 class="mt-3">Enviados (últimos 7 días)</h2>
            <template x-for="p in pedidosServidor" :key="p.numero">
                <div class="tarjeta py-2">
                    <div class="d-flex justify-content-between"><strong x-text="p.nombre_comercio"></strong><strong x-text="DT.dinero(p.total)"></strong></div>
                    <div class="small d-flex justify-content-between"><span x-text="'N° ' + p.numero + ' · ' + new Date(p.fecha.replace(' ', 'T')).toLocaleDateString('es-AR')"></span><span class="chip" :class="p.estado === 'borrador' ? 'chip-warn' : (p.estado.indexOf('entregado') === 0 ? 'chip-ok' : 'chip-info')" x-text="p.estado === 'borrador' && Number(p.revision_requerida) ? 'En revisión' : p.estado.replace('_', ' ')"></span></div>
                </div>
            </template>
            <template x-if="!pedidosServidor.length"><p class="text-body-secondary" x-text="online ? 'Todavía no hay pedidos.' : 'Se verán al tener conexión.'"></p></template>
        </section>
        </template>

        <!-- ===================== MÁS ===================== -->
        <template x-if="vista === 'mas'">
        <section class="app-seccion">
            <div class="d-grid gap-2 lista-acciones-grandes">
                <button class="btn btn-outline-primary" @click="ir('pedidos')"><i class="bi bi-receipt"></i> Mis pedidos</button>
                <button class="btn btn-outline-primary" @click="sincronizar()"><i class="bi bi-arrow-repeat"></i> Sincronizar ahora</button>
                <button class="btn btn-outline-primary" @click="descargar().then(() => DT.aviso('Datos actualizados', 'exito'))" :disabled="!online"><i class="bi bi-cloud-download"></i> Actualizar precios y stock</button>
                <button class="btn btn-outline-secondary" data-tema-alternar><i class="bi bi-circle-half"></i> Modo claro / oscuro</button>
                <?php if (puede('pedidos.ver') && puede('dashboard.ver')): ?><a class="btn btn-outline-secondary" href="<?= url('/') ?>"><i class="bi bi-display"></i> Ir al sistema completo</a><?php endif; ?>
                <button class="btn btn-outline-danger" @click="cerrarSesion()"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
            </div>
            <p class="small text-body-secondary mt-3">Datos descargados: <span x-text="datos.version"></span><br><span x-text="datos.productos.length + ' productos · ' + datos.clientes.length + ' clientes'"></span><br>Para instalar la app: menú del navegador › "Agregar a pantalla de inicio".</p>
        </section>
        </template>
    </div>
    </template>

    <!-- ===================== HOJAS INFERIORES ===================== -->
    <template x-if="hoja"><div class="hoja-fondo" @click="hoja = null"></div></template>
    <template x-if="hoja === 'visita' && cliente">
        <div class="hoja-inferior">
            <h3>Visita sin pedido</h3>
            <p class="text-body-secondary" x-text="cliente.n"></p>
            <template x-for="(t, k) in motivos" :key="k"><button class="btn opcion-grande" :class="motivoVisita === k ? 'btn-primary' : 'btn-outline-secondary'" @click="motivoVisita = k" x-text="t"></button></template>
            <input class="form-control mb-3" x-model="obsVisita" placeholder="Comentario (opcional)" maxlength="200">
            <button class="btn btn-primary btn-lg w-100 btn-touch" @click="registrarVisita()"><i class="bi bi-check-lg"></i> Registrar visita</button>
        </div>
    </template>
    <template x-if="hoja === 'carrito'">
        <div class="hoja-inferior">
            <div class="d-flex justify-content-between align-items-center"><h3 class="mb-0">Pedido de <span x-text="cliente.n"></span></h3><button class="btn-close" @click="hoja = null" aria-label="Cerrar"></button></div>
            <template x-for="(l, k) in carrito" :key="k">
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between gap-2"><strong x-text="l.descripcion"></strong><strong class="text-nowrap" x-text="DT.dinero(l.precio * l.cantidad)"></strong></div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-body-secondary" x-text="l.presentacion_nombre + ' x ' + DT.cant(l.factor) + ' · ' + DT.dinero(l.precio)"></small>
                        <div class="stepper">
                            <button class="btn btn-outline-secondary" @click="l.cantidad > 1 ? l.cantidad-- : carrito.splice(k, 1)" aria-label="Restar"><i class="bi" :class="l.cantidad > 1 ? 'bi-dash' : 'bi-trash'"></i></button>
                            <input type="text" class="form-control" inputmode="numeric" x-model.number="l.cantidad" aria-label="Cantidad">
                            <button class="btn btn-primary" @click="l.cantidad++" aria-label="Sumar">+</button>
                        </div>
                    </div>
                    <small class="text-danger" x-show="sinStock(l)"><i class="bi bi-exclamation-triangle"></i> Supera el stock disponible (<span x-text="DT.cant(l.disponible)"></span>): puede quedar para revisión.</small>
                </div>
            </template>
            <textarea class="form-control my-3" rows="2" x-model="obs" placeholder="Observaciones para la entrega" maxlength="255"></textarea>
            <div class="d-flex justify-content-between fs-4 fw-bold mb-3"><span>Total</span><span x-text="DT.dinero(totalCarrito())"></span></div>
            <button class="btn btn-primary btn-lg w-100 btn-touch" @click="confirmarPedido()" :disabled="!carrito.length"><i class="bi bi-check2-circle"></i> Confirmar pedido</button>
            <p class="small text-body-secondary text-center mt-2 mb-0" x-show="!online"><i class="bi bi-wifi-off"></i> Sin conexión: el pedido queda guardado y se envía solo al volver la señal.</p>
        </div>
    </template>
    <template x-if="hoja === 'resultado' && ultimoResultado">
        <div class="hoja-inferior text-center">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
            <h3 class="mt-2">Pedido guardado</h3>
            <p><strong x-text="ultimoResultado.cliente"></strong> · <span x-text="DT.dinero(ultimoResultado.total)"></span></p>
            <p class="text-body-secondary" x-text="online ? 'Se está enviando a la oficina.' : 'Sin conexión: se enviará automáticamente cuando vuelva la señal.'"></p>
            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg btn-touch" @click="hoja = null; ir('ruta')"><i class="bi bi-signpost-2"></i> Siguiente cliente</button>
                <button class="btn btn-outline-secondary btn-touch" @click="hoja = null; ir('inicio')">Ir al inicio</button>
            </div>
        </div>
    </template>

    <!-- ===================== BARRA INFERIOR ===================== -->
    <nav class="bottom-nav" aria-label="Navegación de la app">
        <a href="#" :class="{ active: vista === 'inicio' }" @click.prevent="ir('inicio')"><i class="bi bi-house"></i><span>Inicio</span></a>
        <a href="#" :class="{ active: vista === 'ruta' }" @click.prevent="ir('ruta')"><i class="bi bi-signpost-2"></i><span>Mi ruta</span></a>
        <a href="#" class="nav-principal" @click.prevent="vista === 'pedido' && carrito.length ? (hoja = 'carrito') : (ir('clientes'), modoElegir = true)"><i class="bi bi-cart-plus"></i><span x-text="vista === 'pedido' && carrito.length ? 'Ver pedido' : 'Nuevo pedido'"></span></a>
        <a href="#" :class="{ active: vista === 'clientes' && !modoElegir }" @click.prevent="ir('clientes')"><i class="bi bi-shop"></i><span>Clientes</span></a>
        <a href="#" :class="{ active: vista === 'mas' || vista === 'pedidos' }" @click.prevent="ir('mas')"><i class="bi bi-three-dots"></i><span>Más</span><span class="contador" x-show="pendientes()" x-text="pendientes()"></span></a>
    </nav>
    <form method="post" action="<?= url('/logout') ?>" id="formSalir" class="d-none"><input type="hidden" name="_token" value=""></form>
</div>
<?php App\Core\View::inicio('scripts'); ?><script src="<?= asset('assets/js/movil.js') ?>"></script><?php App\Core\View::fin(); ?>
